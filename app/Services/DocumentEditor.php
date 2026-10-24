<?php

namespace App\Services;

use App\Events\DocumentChanged;
use App\Exceptions\StaleVersionException;
use App\Models\Block;
use App\Models\Document;
use App\Models\Revision;
use App\Models\User;
use App\Support\BlockTypes;
use App\Support\EditorClient;
use App\Support\FractionalIndex;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single write path for documents and blocks.
 *
 * Every change locks the document row (serialising writers), checks the caller's version and that
 * no other tab holds a lock on what it touches, bumps the document's seq and records a revision, all
 * in one transaction. After commit, the other editors are told the document changed.
 */
class DocumentEditor
{
    public function __construct(private BlockLocks $locks) {}

    public function createDocument(User $user, string $title): Document
    {
        return DB::transaction(function () use ($user, $title) {
            $slug = Document::uniqueSlug($title);
            $document = Document::create([
                'slug' => $slug,
                'title' => $title,
                'meta' => [
                    'fileName' => "{$slug}_{yyyyMMdd}",
                    'runningTitle' => ['bold' => mb_strtoupper($title), 'light' => ''],
                    'toc' => true,
                ],
                'title_page' => ['title' => $title],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->record($document, $user, 'document.create', null, null, fn () => $document->toSummary());

            return $document;
        });
    }

    /**
     * @param  array{id?: ?string, type: string, parentId?: ?string, afterId?: ?string, beforeId?: ?string, props?: array, options?: array}  $data
     */
    public function createBlock(Document $document, User $user, array $data): Revision
    {
        return DB::transaction(function () use ($document, $user, $data) {
            $document = $this->lock($document);
            $parent = $this->parent($document, $data['parentId'] ?? null);
            $this->assertCanContain($parent, $data['type']);

            $block = $document->blocks()->create([
                'id' => $data['id'] ?? null,
                'parent_id' => $parent?->id,
                'position' => $this->position($document, $parent?->id, $data['afterId'] ?? null, $data['beforeId'] ?? null),
                'type' => $data['type'],
                'props' => $data['props'] ?? [],
                'options' => $data['options'] ?? [],
                'updated_by' => $user->id,
            ])->refresh();

            return $this->record($document, $user, 'block.create', $block->id, null, $block->toNode());
        });
    }

    public function updateBlock(Block $block, User $user, array $changes, int $version): Revision
    {
        return DB::transaction(function () use ($block, $user, $changes, $version) {
            $document = $this->lock($block->document);
            $block = $this->fresh($block, $version);
            $before = $block->toNode();
            $this->locks->assertFree($document, ["block:{$block->id}"], $before);

            $block->fill(array_intersect_key($changes, array_flip(['props', 'options'])));
            $block->version++;
            $block->updated_by = $user->id;
            $block->save();

            return $this->record($document, $user, 'block.update', $block->id, $before, $block->toNode());
        });
    }

    public function moveBlock(Block $block, User $user, ?string $parentId, ?string $afterId, ?string $beforeId, int $version): Revision
    {
        return DB::transaction(function () use ($block, $user, $parentId, $afterId, $beforeId, $version) {
            $document = $this->lock($block->document);
            $block = $this->fresh($block, $version);
            $parent = $this->parent($document, $parentId);
            $subtree = [$block->id, ...$this->descendantIds($block)];
            $this->locks->assertFree($document, $this->targets($subtree));

            if ($parent && in_array($parent->id, $subtree, true)) {
                throw ValidationException::withMessages(['parentId' => 'A block cannot be moved inside itself.']);
            }
            $this->assertCanContain($parent, $block->type);

            $before = $block->toNode();
            $block->parent_id = $parent?->id;
            $block->position = $this->position($document, $parent?->id, $afterId, $beforeId, exclude: $block->id);
            $block->version++;
            $block->updated_by = $user->id;
            $block->save();

            return $this->record($document, $user, 'block.move', $block->id, $before, $block->toNode());
        });
    }

    /**
     * Soft-deletes the block and everything under it; the revision keeps the subtree for restoring.
     */
    public function deleteBlock(Block $block, User $user, int $version): Revision
    {
        return DB::transaction(function () use ($block, $user, $version) {
            $document = $this->lock($block->document);
            $block = $this->fresh($block, $version);
            $ids = [$block->id, ...$this->descendantIds($block)];
            $this->locks->assertFree($document, $this->targets($ids));

            $subtree = app(DocumentTree::class)->subtree($block);
            Block::query()->whereKey($ids)->update(['deleted_at' => now(), 'updated_by' => $user->id]);

            return $this->record($document, $user, 'block.delete', $block->id, $subtree, null);
        });
    }

    /**
     * Inserts new blocks (with children) in one change: adding a block from a template, outline mode,
     * duplicating, snippets.
     *
     * @param  list<array{type: string, props?: array, options?: array, children?: array}>  $nodes
     */
    public function insertTree(Document $document, User $user, ?string $parentId, ?string $afterId, ?string $beforeId, array $nodes): Revision
    {
        return DB::transaction(function () use ($document, $user, $parentId, $afterId, $beforeId, $nodes) {
            $document = $this->lock($document);
            $parent = $this->parent($document, $parentId);
            $tree = app(DocumentTree::class);
            $tree->assertValidNodes($nodes, $parent?->type);

            [$a, $b] = $this->neighbours($document, $parent?->id, $afterId, $beforeId);
            $ids = $tree->insertNodes($nodes, $document, $user, $parent?->id, $a, $b);
            $blocks = Block::query()->whereKey($ids)->orderBy('position')->get()->map(fn (Block $block) => $tree->subtree($block))->all();

            return $this->record($document, $user, 'block.insert', $ids[0] ?? null, null, ['blocks' => $blocks]);
        });
    }

    /**
     * Replaces neighbouring sibling blocks, and everything inside them, with new nodes in one change: accepting
     * a restructure suggestion, and undoing or redoing one (which replaces the result with copies of what was
     * there). The revision keeps the removed subtrees.
     *
     * @param  list<string>  $blockIds  the siblings to replace, in document order
     * @param  array<string, int>  $versions  block id => the version the caller saw; must include every block in
     *                                        $blockIds and may include blocks inside them, all of which must be unchanged
     * @param  list<array{type: string, props?: array, options?: array, children?: array}>  $nodes
     */
    public function replaceBlocks(Document $document, User $user, array $blockIds, array $versions, array $nodes): Revision
    {
        return DB::transaction(function () use ($document, $user, $blockIds, $versions, $nodes) {
            $document = $this->lock($document);
            $blocks = $document->blocks()->whereKey($blockIds)->lockForUpdate()->get()->keyBy('id');
            if ($blockIds === [] || $blocks->count() !== count(array_unique($blockIds))) {
                throw ValidationException::withMessages(['blockIds' => 'Some of these blocks no longer exist.']);
            }

            $parentId = $blocks[$blockIds[0]]->parent_id;
            $siblings = $document->blocks()->where('parent_id', $parentId)->orderBy('position')->pluck('position', 'id');
            $order = $siblings->keys()->all();
            $start = array_search($blockIds[0], $order, true);
            if (array_slice($order, $start, count($blockIds)) !== $blockIds) {
                throw ValidationException::withMessages(['blockIds' => 'Only neighbouring blocks under the same parent can be replaced together, in order.']);
            }

            $tree = app(DocumentTree::class);
            $subtrees = array_map(fn (string $id) => $tree->subtree($blocks[$id]), $blockIds);
            $ids = array_merge(...array_map($this->subtreeIds(...), $subtrees));

            if (array_diff($blockIds, array_keys($versions))) {
                throw ValidationException::withMessages(['versions' => 'The version of every replaced block is required.']);
            }
            $current = Block::query()->whereKey(array_keys($versions))->where('document_id', $document->id)->pluck('version', 'id');
            $changed = array_filter($versions, fn ($version, $id) => (int) ($current[$id] ?? 0) !== (int) $version, ARRAY_FILTER_USE_BOTH);
            if ($changed) {
                throw new StaleVersionException(['blocks' => $subtrees]);
            }
            $this->locks->assertFree($document, $this->targets($ids));

            $parent = $this->parent($document, $parentId);
            $tree->assertValidNodes($nodes, $parent?->type);

            $after = $order[$start - 1] ?? null;
            $before = $order[$start + count($blockIds)] ?? null;
            Block::query()->whereKey($ids)->update(['deleted_at' => now(), 'updated_by' => $user->id]);
            $newIds = $tree->insertNodes($nodes, $document, $user, $parentId, $after ? $siblings[$after] : null, $before ? $siblings[$before] : null);
            $inserted = Block::query()->whereKey($newIds)->orderBy('position')->get()->map(fn (Block $block) => $tree->subtree($block))->all();

            return $this->record($document, $user, 'block.replace', $blockIds[0], ['blocks' => $subtrees], ['removed' => $blockIds, 'blocks' => $inserted]);
        });
    }

    /**
     * Brings back a deleted block and everything that was deleted with it (undo of delete).
     */
    public function restoreBlock(Block $block, User $user): Revision
    {
        return DB::transaction(function () use ($block, $user) {
            $document = $this->lock($block->document);
            $block = Block::withTrashed()->whereKey($block->getKey())->lockForUpdate()->firstOrFail();
            if (! $block->trashed()) {
                throw ValidationException::withMessages(['block' => 'This block is not deleted.']);
            }
            if ($block->parent_id && ! $document->blocks()->whereKey($block->parent_id)->exists()) {
                throw ValidationException::withMessages(['block' => 'The block it belonged to has been deleted too.']);
            }

            $deletion = $document->revisions()->where('op', 'block.delete')->where('block_id', $block->id)->latest('seq')->firstOrFail();
            Block::withTrashed()->whereKey($this->subtreeIds($deletion->before))->update(['deleted_at' => null, 'updated_by' => $user->id]);

            // a block inserted between the same neighbours since the delete gets the same position; step past it
            $taken = $document->blocks()->where('parent_id', $block->parent_id)->whereKeyNot($block->id)->where('position', $block->position)->exists();
            if ($taken) {
                $next = $document->blocks()->where('parent_id', $block->parent_id)->where('position', '>', $block->position)->orderBy('position')->value('position');
                $block->position = FractionalIndex::between($block->position, $next);
            }

            $block->deleted_at = null;
            $block->version++;
            $block->updated_by = $user->id;
            $block->save();

            return $this->record($document, $user, 'block.restore', $block->id, null, app(DocumentTree::class)->subtree($block));
        });
    }

    /**
     * @param  array{title?: string, meta?: array, titlePage?: array}  $changes
     */
    public function updateDocument(Document $document, User $user, array $changes, int $version): Revision
    {
        return DB::transaction(function () use ($document, $user, $changes, $version) {
            $document = $this->lock($document);
            if ($document->version !== $version) {
                throw new StaleVersionException($document->toSummary());
            }

            $before = $document->toSummary();
            $this->locks->assertFree($document, ['settings'], $before);
            foreach (['title' => 'title', 'meta' => 'meta', 'titlePage' => 'title_page'] as $key => $column) {
                if (array_key_exists($key, $changes)) {
                    $document->{$column} = $changes[$key];
                }
            }
            $document->version++;
            $document->updated_by = $user->id;
            $document->save();

            // the summary includes seq, so it is built after record() bumps it
            return $this->record($document, $user, 'document.update', null, $before, fn () => $document->toSummary());
        });
    }

    /**
     * Records a change on an already-locked document. Call inside the write's transaction.
     */
    public function record(Document $document, User $user, string $op, ?string $blockId, ?array $before, array|\Closure|null $after): Revision
    {
        $document->seq++;
        $document->updated_by = $user->id;
        $document->save();

        $revision = $document->revisions()->create([
            'seq' => $document->seq,
            'block_id' => $blockId,
            'user_id' => $user->id,
            'client_id' => EditorClient::id(),
            'op' => $op,
            'before' => $before,
            'after' => $after instanceof \Closure ? $after() : $after,
        ]);

        // only once the change is committed; a broadcasting outage must not fail the request
        DB::afterCommit(fn () => rescue(fn () => event(new DocumentChanged($revision)), report: true));

        return $revision;
    }

    private function lock(Document $document): Document
    {
        return Document::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
    }

    private function fresh(Block $block, int $version): Block
    {
        $block = Block::query()->whereKey($block->getKey())->lockForUpdate()->firstOrFail();
        if ($block->version !== $version) {
            throw new StaleVersionException($block->toNode());
        }

        return $block;
    }

    private function parent(Document $document, ?string $parentId): ?Block
    {
        if ($parentId === null) {
            return null;
        }

        $parent = $document->blocks()->whereKey($parentId)->first();
        if (! $parent) {
            throw ValidationException::withMessages(['parentId' => 'The parent block does not exist in this document.']);
        }

        return $parent;
    }

    private function assertCanContain(?Block $parent, string $type): void
    {
        if (! BlockTypes::canContain($parent?->type, $type)) {
            $where = $parent ? "a {$parent->type}" : 'the top level of a document';
            throw ValidationException::withMessages(['type' => "A {$type} cannot be placed in {$where}."]);
        }
    }

    /**
     * Position for a block placed after $afterId, before $beforeId, or (with neither) at the end.
     */
    private function position(Document $document, ?string $parentId, ?string $afterId, ?string $beforeId, ?string $exclude = null): string
    {
        return FractionalIndex::between(...$this->neighbours($document, $parentId, $afterId, $beforeId, $exclude));
    }

    /**
     * Positions of the siblings either side of the insertion point (null = no sibling on that side).
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function neighbours(Document $document, ?string $parentId, ?string $afterId, ?string $beforeId, ?string $exclude = null): array
    {
        $siblings = fn () => $document->blocks()
            ->where('parent_id', $parentId)
            ->when($exclude, fn ($query) => $query->whereKeyNot($exclude));

        $anchorPosition = function (string $id, string $field) use ($siblings) {
            $position = $siblings()->whereKey($id)->value('position');
            if ($position === null) {
                throw ValidationException::withMessages([$field => 'The neighbouring block must be a sibling under the same parent.']);
            }

            return $position;
        };

        if ($afterId !== null) {
            $a = $anchorPosition($afterId, 'afterId');
            $b = $siblings()->where('position', '>', $a)->orderBy('position')->value('position');
        } elseif ($beforeId !== null) {
            $b = $anchorPosition($beforeId, 'beforeId');
            $a = $siblings()->where('position', '<', $b)->orderByDesc('position')->value('position');
        } else {
            $a = $siblings()->orderByDesc('position')->value('position');
            $b = null;
        }

        return [$a, $b];
    }

    /**
     * Lock targets for block ids.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function targets(array $ids): array
    {
        return array_map(fn (string $id) => "block:{$id}", $ids);
    }

    /**
     * @return list<string>
     */
    private function subtreeIds(array $node): array
    {
        return [$node['id'], ...array_merge([], ...array_map(fn (array $child) => $this->subtreeIds($child), $node['children'] ?? []))];
    }

    /**
     * @return list<string>
     */
    private function descendantIds(Block $block): array
    {
        $ids = [];
        $frontier = [$block->id];

        while ($frontier) {
            $frontier = Block::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            array_push($ids, ...$frontier);
        }

        return $ids;
    }
}
