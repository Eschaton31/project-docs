<?php

namespace App\Jobs;

use App\Ai\Agents\ProseReviewer;
use App\Ai\ProseExtractor;
use App\Ai\ProseSnapshot;
use App\Ai\ReviewPrompt;
use App\Ai\SuggestionValidator;
use App\Models\Block;
use App\Models\ProseReview;
use App\Models\ProseSuggestion;
use App\Services\DocumentTree;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reviews a document (or one topic) chunk by chunk and saves the suggestions that pass validation.
 * Nothing in the document changes here; an admin applies suggestions one by one.
 */
class ReviewProse implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public ProseReview $review)
    {
        $calls = (int) ceil(config('ai-review.max_chars_per_review') / config('ai-review.max_chars_per_chunk'));
        $this->timeout = config('ai-review.timeout') * $calls + 60;
        $this->onConnection(config('ai-review.queue_connection'))->onQueue('ai-review');
    }

    public function handle(DocumentTree $tree): void
    {
        $review = $this->review;
        $document = $review->document;
        $review->update(['status' => ProseReview::RUNNING, 'document_seq' => $document->seq]);

        $nodes = $review->scope_block_id
            ? [$tree->subtree(Block::query()->whereBelongsTo($document)->findOrFail($review->scope_block_id))]
            : $tree->build($document);
        $snapshot = (new ProseExtractor)->extract($nodes);

        $usage = ['input_tokens' => 0, 'output_tokens' => 0];
        $dropped = [];
        $chunks = ReviewPrompt::chunks($snapshot, config('ai-review.max_chars_per_chunk'));

        foreach ($chunks as $chunk) {
            [$rows, $chunkUsage, $chunkDropped] = $this->reviewChunk($snapshot, $chunk);
            foreach ($rows as $row) {
                $review->suggestions()->create([...$row, 'status' => ProseSuggestion::PENDING]);
            }
            $usage['input_tokens'] += $chunkUsage['input_tokens'];
            $usage['output_tokens'] += $chunkUsage['output_tokens'];
            foreach ($chunkDropped as $reason => $count) {
                $dropped[$reason] = ($dropped[$reason] ?? 0) + $count;
            }
        }

        $review->update(['status' => ProseReview::DONE, 'error' => null, ...$usage]);

        Log::info('Prose review finished', [
            'review' => $review->id,
            'document' => $document->id,
            'scope' => $review->scope_block_id,
            'model' => $review->model,
            'chunks' => count($chunks),
            'suggestions' => $review->suggestions()->count(),
            'dropped' => $dropped,
            ...$usage,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->review->update(['status' => ProseReview::FAILED, 'error' => $exception?->getMessage()]);
    }

    /**
     * @return array{0: list<array>, 1: array{input_tokens: int, output_tokens: int}, 2: array<string, int>}
     */
    private function reviewChunk(ProseSnapshot $snapshot, array $chunk): array
    {
        $response = ProseReviewer::make()->prompt(
            ReviewPrompt::render($snapshot, $chunk, $this->review->kinds),
            model: config('ai-review.models')[$this->review->model],
            timeout: config('ai-review.timeout'),
        );

        $validator = new SuggestionValidator($snapshot, $chunk, $this->review->kinds);
        $rows = $validator->validate($response->structured);

        return [
            $rows,
            ['input_tokens' => $response->usage->inputTokens, 'output_tokens' => $response->usage->outputTokens],
            $validator->dropped(),
        ];
    }
}
