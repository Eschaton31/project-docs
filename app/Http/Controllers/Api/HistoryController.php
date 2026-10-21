<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\Document;
use App\Models\Revision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reading the revision log: one block's history (with full before/after, for restoring an older
 * version) and the document's activity feed (summaries only).
 */
class HistoryController extends Controller
{
    private const PAGE = 50;

    public function block(Block $block): JsonResponse
    {
        $revisions = Revision::query()
            ->with('user')
            ->where('document_id', $block->document_id)
            ->where('block_id', $block->id)
            ->orderByDesc('seq')
            ->limit(self::PAGE)
            ->get();

        return response()->json([
            'data' => $revisions->map(fn (Revision $revision) => [...$this->entry($revision), 'before' => $revision->before, 'after' => $revision->after]),
        ]);
    }

    /**
     * Newest first; ?before=<seq> pages further back.
     */
    public function activity(Request $request, Document $document): JsonResponse
    {
        $before = $request->validate(['before' => ['sometimes', 'integer', 'min:1']])['before'] ?? null;

        $revisions = $document->revisions()
            ->with('user')
            ->when($before, fn ($query) => $query->where('seq', '<', $before))
            ->orderByDesc('seq')
            ->limit(self::PAGE)
            ->get();

        // deleted blocks that are still deleted can be restored from the feed
        $deleted = Block::onlyTrashed()
            ->whereKey($revisions->where('op', 'block.delete')->pluck('block_id'))
            ->pluck('id')
            ->flip();

        return response()->json([
            'data' => $revisions->map(fn (Revision $revision) => [
                ...$this->entry($revision),
                'restorable' => $revision->op === 'block.delete' && $deleted->has($revision->block_id),
            ]),
            'hasMore' => $revisions->count() === self::PAGE,
        ]);
    }

    private function entry(Revision $revision): array
    {
        return [
            'seq' => $revision->seq,
            'op' => $revision->op,
            'at' => $revision->created_at?->toIso8601String(),
            'user' => $revision->user ? ['id' => $revision->user->id, 'name' => $revision->user->name, 'color' => $revision->user->color] : null,
            'blockId' => $revision->block_id,
            'block' => $this->blockSummary($revision),
        ];
    }

    /**
     * Type and a short label of the block a revision is about, for "Ana edited Paragraph in …".
     */
    private function blockSummary(Revision $revision): ?array
    {
        $node = $revision->after ?? $revision->before;
        if ($revision->op === 'block.insert') {
            $node = $node['blocks'][0] ?? null;
        }
        if (! is_array($node) || ! isset($node['type'])) {
            return null;
        }

        $props = $node['props'] ?? [];
        $label = $props['title'] ?? $props['text'] ?? $props['label'] ?? $props['description'] ?? strip_tags($props['html'] ?? '');

        return [
            'type' => $node['type'],
            'label' => mb_strimwidth(trim(preg_replace('/\s+/', ' ', (string) $label)), 0, 80, '…'),
            'count' => $revision->op === 'block.insert' ? count($revision->after['blocks'] ?? []) : null,
        ];
    }
}
