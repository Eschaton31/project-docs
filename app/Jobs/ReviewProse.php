<?php

namespace App\Jobs;

use App\Ai\Agents\ProseReviewer;
use App\Ai\ProseExtractor;
use App\Ai\ReviewPrompt;
use App\Ai\ReviewRun;
use App\Models\ProseReview;
use App\Services\DocumentTree;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Reviews a document (or one topic) chunk by chunk through the API and saves the suggestions that pass
 * validation. Nothing in the document changes here; an admin applies suggestions one by one.
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
        $review->update(['status' => ProseReview::RUNNING]);
        ReviewRun::broadcast($review);

        $snapshot = (new ProseExtractor)->extract(ReviewRun::read($review, $tree));
        $chunks = ReviewPrompt::chunks($snapshot, config('ai-review.max_chars_per_chunk'));
        $usage = ['input_tokens' => 0, 'output_tokens' => 0];
        $dropped = [];

        foreach ($chunks as $chunk) {
            $response = ProseReviewer::make()->prompt(
                ReviewPrompt::render($snapshot, $chunk, $review->kinds),
                model: config('ai-review.models')[$review->model],
                timeout: config('ai-review.timeout'),
            );

            $dropped = ReviewRun::addDropped($dropped, ReviewRun::save($review, $snapshot, $chunk, $response->structured));
            $usage['input_tokens'] += $response->usage->inputTokens;
            $usage['output_tokens'] += $response->usage->outputTokens;
        }

        ReviewRun::finish($review, $usage, count($chunks), $dropped);
    }

    public function failed(?Throwable $exception): void
    {
        $this->review->update(['status' => ProseReview::FAILED, 'error' => $exception?->getMessage()]);
        ReviewRun::broadcast($this->review);
    }
}
