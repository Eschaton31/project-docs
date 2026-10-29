<?php

namespace App\Jobs;

use App\Ai\Agents\DocScaffolder;
use App\Ai\ProseExtractor;
use App\Ai\Reviewers\ChunkReviewer;
use App\Ai\ReviewPrompt;
use App\Ai\ReviewRun;
use App\Ai\Scaffold\ScaffoldRun;
use App\Models\ProseReview;
use App\Services\DocumentTree;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Reviews a document (or one topic) chunk by chunk, through the API or Claude Code depending on the runner, and
 * saves the suggestions that pass validation. Nothing in the document changes here; an admin applies
 * suggestions one by one.
 */
class ReviewProse implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public ProseReview $review)
    {
        // a scaffold's calls are capped when its scripts are uploaded (ScaffoldRun::assertFits)
        $calls = $review->isScaffold()
            ? config('ai-review.scaffold.max_calls')
            : (int) ceil(config('ai-review.max_chars_per_review') / config('ai-review.max_chars_per_chunk'));
        $this->timeout = app(ChunkReviewer::class)->timeout() * $calls + 60;
        $this->onConnection(config('ai-review.queue_connection'))->onQueue('ai-review');
    }

    public function handle(DocumentTree $tree, ChunkReviewer $reviewer): void
    {
        $review = $this->review;
        $review->update(['status' => ProseReview::RUNNING]);
        ReviewRun::broadcast($review);

        if ($review->isScaffold()) {
            $this->scaffold($review, $tree, $reviewer);

            return;
        }

        $snapshot = (new ProseExtractor)->extract(ReviewRun::read($review, $tree));
        $chunks = ReviewPrompt::chunks($snapshot, config('ai-review.max_chars_per_chunk'));
        $usage = ['input_tokens' => 0, 'output_tokens' => 0];
        $dropped = [];

        foreach ($chunks as $chunk) {
            $answer = $reviewer->review(ReviewPrompt::render($snapshot, $chunk, $review->kinds, $review->brief), $review->model);

            $dropped = ReviewRun::addDropped($dropped, ReviewRun::save($review, $snapshot, $chunk, $answer['output']));
            $usage['input_tokens'] += $answer['usage']['input_tokens'];
            $usage['output_tokens'] += $answer['usage']['output_tokens'];
        }

        ReviewRun::finish($review, $usage, count($chunks), $dropped);
    }

    /**
     * Documentation drafted from the review's R scripts: an overview call, then the script reference calls,
     * all validated together so each template section is suggested once.
     */
    private function scaffold(ProseReview $review, DocumentTree $tree, ChunkReviewer $reviewer): void
    {
        $prepared = ScaffoldRun::prepare($review, $tree);
        $usage = ['input_tokens' => 0, 'output_tokens' => 0];
        $outputs = [];

        foreach ($prepared['calls'] as $call) {
            $answer = $reviewer->review(ScaffoldRun::prompt($prepared, $call, $review->run_notes), $review->model, DocScaffolder::class);
            $outputs[] = $answer['output'];
            $usage['input_tokens'] += $answer['usage']['input_tokens'];
            $usage['output_tokens'] += $answer['usage']['output_tokens'];
        }

        ReviewRun::finish($review, $usage, count($prepared['calls']), ScaffoldRun::save($review, $prepared, $outputs));
    }

    public function failed(?Throwable $exception): void
    {
        $this->review->update(['status' => ProseReview::FAILED, 'error' => $exception?->getMessage()]);
        ReviewRun::broadcast($this->review);
    }
}
