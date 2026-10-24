<?php

namespace App\Http\Controllers\Api;

use App\Ai\ProseExtractor;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProseReviewRequest;
use App\Jobs\ReviewProse;
use App\Models\Block;
use App\Models\Document;
use App\Models\ProseReview;
use App\Models\User;
use App\Services\DocumentTree;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * AI prose reviews (admins only): queue one, poll it, read its suggestions.
 */
class ProseReviewController extends Controller
{
    private const RECENT = 5;

    public function index(Request $request, Document $document): JsonResponse
    {
        $reviews = $document->proseReviews()->latest('id')->limit(self::RECENT)->get();

        return response()->json([
            'reviews' => $reviews->map->toSummary(),
            'runner' => config('ai-review.runner'),
            // otherwise the editor shows the /prose-review command to run in Claude Code
            'canStart' => $request->user()->canStartAiReviews(),
        ]);
    }

    public function store(StoreProseReviewRequest $request, Document $document, DocumentTree $tree): JsonResponse
    {
        if (! $request->user()->canStartAiReviews()) {
            throw ValidationException::withMessages(['review' => config('ai-review.claude_code.user')
                ? "Reviews run on the Claude plan of {$this->owner()}, so only they can start one here. Anyone with Claude Code in this project can run /prose-review {$document->slug}."
                : "Reviews run in Claude Code: run /prose-review {$document->slug} there, or set AI_REVIEW_CLAUDE_CODE_USER to start them from here."]);
        }

        $running = $document->proseReviews()->whereIn('status', [ProseReview::QUEUED, ProseReview::RUNNING])->exists();
        if ($running) {
            throw ValidationException::withMessages(['review' => 'A review of this document is already running.']);
        }

        $blockId = $request->validated('blockId');
        $nodes = $blockId ? [$tree->subtree(Block::query()->findOrFail($blockId))] : $tree->build($document);
        $size = (new ProseExtractor)->extract($nodes)->totalSize();
        if ($size === 0) {
            throw ValidationException::withMessages(['blockId' => 'There is no text to review here.']);
        }
        if ($size > config('ai-review.max_chars_per_review')) {
            throw ValidationException::withMessages(['blockId' => 'This is too much text to review at once; review one topic at a time.']);
        }

        $review = $document->proseReviews()->create([
            'scope_block_id' => $blockId,
            'requested_by' => $request->user()->id,
            'model' => $request->model(),
            'kinds' => $request->kinds(),
            'status' => ProseReview::QUEUED,
        ]);
        ReviewProse::dispatch($review);

        return response()->json(['review' => $review->refresh()->toSummary()], 202);
    }

    private function owner(): string
    {
        $email = config('ai-review.claude_code.user');

        return User::query()->where('email', $email)->value('name') ?? $email;
    }

    public function show(ProseReview $review): JsonResponse
    {
        return response()->json([
            'review' => $review->toSummary(),
            'suggestions' => $review->suggestions()->orderBy('id')->get()->map->toSummary(),
        ]);
    }
}
