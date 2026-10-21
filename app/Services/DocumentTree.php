<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Document;
use App\Models\User;
use App\Support\BlockTypes;
use App\Support\FractionalIndex;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Converts between block rows and the nested JSON tree used by the editor, renderer, export and import.
 */
class DocumentTree
{
    public const SCHEMA_VERSION = 1;

    private const MAX_IMPORT_ERRORS = 50;

    /** Same keys as the options rules on single-block requests. */
    private const OPTION_KEYS = ['pageBreakBefore', 'anchorId', 'draft', 'hidden', 'assignee', 'status'];

    /**
     * Live tree for the editor: nodes carry id, position and version.
     */
    public function build(Document $document): array
    {
        return $this->nodes($this->grouped($document), '', export: false);
    }

    public function subtree(Block $block): array
    {
        return [...$block->toNode(), 'children' => $this->nodes($this->grouped($block->document), $block->id, export: false)];
    }

    /**
     * Portable JSON: no ids, positions or versions, so it can be re-imported as a new document.
     */
    public function export(Document $document): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'slug' => $document->slug,
            'title' => $document->title,
            'meta' => $document->meta ?: new \stdClass,
            'titlePage' => $document->title_page ?: new \stdClass,
            'blocks' => $this->nodes($this->grouped($document), '', export: true),
        ];
    }

    /**
     * Creates a new document from exported/imported JSON in one transaction.
     */
    public function import(array $payload, User $user): Document
    {
        $this->validate($payload);

        return DB::transaction(function () use ($payload, $user) {
            $document = Document::create([
                'slug' => Document::uniqueSlug($payload['slug'] ?? $payload['title']),
                'title' => $payload['title'],
                'meta' => $payload['meta'] ?? [],
                'title_page' => $payload['titlePage'] ?? [],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $ids = $this->insertNodes($payload['blocks'] ?? [], $document, $user, null, null, null);
            $count = Block::query()->where('document_id', $document->id)->count();

            app(DocumentEditor::class)->record($document, $user, 'document.import', null, null, ['blocks' => $count, 'roots' => count($ids)]);

            return $document->refresh();
        });
    }

    /**
     * Throws a ValidationException (keyed like blocks.0.children.1.type) if the nodes break the nesting rules.
     */
    public function assertValidNodes(array $nodes, ?string $parentType): void
    {
        $errors = [];
        $this->validateNodes($nodes, $parentType, 'blocks', $errors);

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Inserts a validated tree of new nodes under $parentId, placing the top-level ones between the sibling
     * positions $after and $before. Call inside a transaction.
     *
     * @return list<string> ids of the top-level blocks created
     */
    public function insertNodes(array $nodes, Document $document, User $user, ?string $parentId, ?string $after, ?string $before): array
    {
        $rows = [];
        $ids = $this->flatten($nodes, $parentId, $document->id, $user->id, now(), $rows, FractionalIndex::nBetween($after, $before, count($nodes)));

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('blocks')->insert($chunk);
        }

        return $ids;
    }

    private function grouped(Document $document): Collection
    {
        return $document->blocks()->orderBy('position')->get()->groupBy(fn (Block $block) => $block->parent_id ?? '');
    }

    private function nodes(Collection $byParent, string $parentKey, bool $export): array
    {
        return $byParent->get($parentKey, collect())
            ->map(fn (Block $block) => [
                ...($export
                    ? ['type' => $block->type, 'props' => $block->props ?: new \stdClass, 'options' => $block->options ?: new \stdClass]
                    : $block->toNode()),
                'children' => $this->nodes($byParent, $block->id, $export),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string> ids of the blocks created at this level
     */
    private function flatten(array $nodes, ?string $parentId, int $documentId, int $userId, $now, array &$rows, ?array $positions = null): array
    {
        $positions ??= FractionalIndex::nBetween(null, null, count($nodes));
        $ids = [];

        foreach (array_values($nodes) as $i => $node) {
            $ids[] = $id = (string) Str::uuid7();
            $rows[] = [
                'id' => $id,
                'document_id' => $documentId,
                'parent_id' => $parentId,
                'position' => $positions[$i],
                'type' => $node['type'],
                'props' => json_encode($node['props'] ?? [], JSON_THROW_ON_ERROR),
                'options' => json_encode(array_intersect_key($node['options'] ?? [], array_flip(self::OPTION_KEYS)), JSON_THROW_ON_ERROR),
                'version' => 1,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $this->flatten($node['children'] ?? [], $id, $documentId, $userId, $now, $rows);
        }

        return $ids;
    }

    private function validate(array $payload): void
    {
        $errors = [];

        if (($payload['schemaVersion'] ?? null) !== self::SCHEMA_VERSION) {
            $errors['schemaVersion'] = ['Only schemaVersion '.self::SCHEMA_VERSION.' can be imported.'];
        }
        if (! is_string($payload['title'] ?? null) || trim($payload['title']) === '') {
            $errors['title'] = ['A title is required.'];
        }
        foreach (['meta', 'titlePage', 'blocks'] as $key) {
            if (isset($payload[$key]) && ! is_array($payload[$key])) {
                $errors[$key] = ["{$key} must be an object or array."];
            }
        }
        if (is_array($payload['blocks'] ?? null)) {
            $this->validateNodes($payload['blocks'], null, 'blocks', $errors);
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validateNodes(array $nodes, ?string $parentType, string $path, array &$errors): void
    {
        foreach (array_values($nodes) as $i => $node) {
            if (count($errors) >= self::MAX_IMPORT_ERRORS) {
                return;
            }

            $at = "{$path}.{$i}";
            $type = is_array($node) ? ($node['type'] ?? null) : null;

            if (! is_string($type)) {
                $errors["{$at}.type"] = ['Each block needs a type.'];

                continue;
            }
            if (! BlockTypes::exists($type)) {
                $errors["{$at}.type"] = ["Unknown block type [{$type}]."];
            } elseif (! BlockTypes::canContain($parentType, $type)) {
                $where = $parentType ? "a {$parentType}" : 'the top level of a document';
                $errors["{$at}.type"] = ["A {$type} cannot be placed in {$where}."];
            }
            foreach (['props', 'options', 'children'] as $key) {
                if (isset($node[$key]) && ! is_array($node[$key])) {
                    $errors["{$at}.{$key}"] = ["{$key} must be an object or array."];
                }
            }

            if (is_array($node['children'] ?? null)) {
                $this->validateNodes($node['children'], $type, "{$at}.children", $errors);
            }
        }
    }
}
