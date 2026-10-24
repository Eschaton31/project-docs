<?php

namespace App\Ai;

use App\Events\ProseReviewUpdated;
use App\Models\Block;
use App\Models\ProseReview;
use App\Models\ProseSuggestion;
use App\Services\DocumentTree;
use Illuminate\Support\Facades\Log;

/**
 * The steps every review shares, whether the model is called from the queue (API key) or the review runs in
 * Claude Code (exported, then imported): read the scope, save each chunk's validated suggestions, finish.
 */
final class ReviewRun
{
    /**
     * The block tree under review, as it is now; records the document's seq it was read at.
     *
     * @return list<array>
     */
    public static function read(ProseReview $review, DocumentTree $tree): array
    {
        $document = $review->document;
        $review->document_seq = $document->seq;
        // an export reads before it saves the review
        if ($review->exists) {
            $review->save();
        }

        return $review->scope_block_id
            ? [$tree->subtree(Block::query()->whereBelongsTo($document)->findOrFail($review->scope_block_id))]
            : $tree->build($document);
    }

    /**
     * Validates one chunk's answer and saves the suggestions that pass.
     *
     * @param  list<string>  $chunk
     * @param  array{text?: list<array>, restructure?: list<array>}  $output
     * @return array<string, int> why the others were dropped
     */
    public static function save(ProseReview $review, ProseSnapshot $snapshot, array $chunk, array $output): array
    {
        $validator = new SuggestionValidator($snapshot, $chunk, $review->kinds);
        foreach ($validator->validate($output) as $row) {
            $review->suggestions()->create([...$row, 'status' => ProseSuggestion::PENDING]);
        }

        return $validator->dropped();
    }

    /**
     * @param  array{input_tokens: int, output_tokens: int}  $usage
     * @param  array<string, int>  $dropped
     */
    public static function finish(ProseReview $review, array $usage, int $chunks, array $dropped): void
    {
        $review->update(['status' => ProseReview::DONE, 'error' => null, 'snapshot' => null, ...$usage]);
        self::broadcast($review);

        Log::info('Prose review finished', [
            'review' => $review->id,
            'document' => $review->document_id,
            'scope' => $review->scope_block_id,
            'model' => $review->model,
            'chunks' => $chunks,
            'suggestions' => $review->suggestions()->count(),
            'dropped' => $dropped,
            ...$usage,
        ]);
    }

    /**
     * @param  array<string, int>  $into
     * @param  array<string, int>  $more
     * @return array<string, int>
     */
    public static function addDropped(array $into, array $more): array
    {
        foreach ($more as $reason => $count) {
            $into[$reason] = ($into[$reason] ?? 0) + $count;
        }

        return $into;
    }

    public static function broadcast(ProseReview $review): void
    {
        // live progress is a nicety; the editor also polls, so a broadcasting outage must not fail the review
        rescue(fn () => event(new ProseReviewUpdated($review)), report: true);
    }
}
