<?php

namespace App\Http\Controllers\Api;

use App\Ai\Scaffold\ScaffoldRun;
use App\Ai\Scaffold\SourceIntake;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProseScaffoldRequest;
use App\Jobs\ReviewProse;
use App\Models\Document;
use App\Models\ProseReview;
use App\Models\ProseReviewSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Drafting documentation from uploaded R scripts (admins only). The scripts are stored privately, with secrets
 * redacted, and only ever read as text.
 */
class ProseScaffoldController extends Controller
{
    public function store(StoreProseScaffoldRequest $request, Document $document, SourceIntake $intake): JsonResponse
    {
        abort_unless(config('ai-review.scaffold.enabled'), 404);

        $running = $document->proseReviews()->whereIn('status', [ProseReview::QUEUED, ProseReview::RUNNING])->exists();
        if ($running) {
            throw ValidationException::withMessages(['review' => 'A review of this document is already running.']);
        }

        $prepared = $intake->prepare(SourceIntake::fromUploads($request->file('files'), $request->validated('paths', [])));
        ScaffoldRun::assertFits($prepared);
        $canStart = $request->user()->canStartAiReviews();

        $review = DB::transaction(function () use ($request, $document, $intake, $prepared, $canStart) {
            $review = $document->proseReviews()->create([
                'mode' => ProseReview::SCAFFOLD,
                'scope_block_id' => $request->validated('blockId'),
                'requested_by' => $request->user()->id,
                'model' => $canStart ? $request->model() : ProseReview::CLAUDE_CODE,
                'kinds' => [],
                'run_notes' => $request->runNotes(),
                // without the editor runner, it waits for /doc-scaffold to export it to Claude Code
                'status' => $canStart ? ProseReview::QUEUED : ProseReview::AWAITING,
            ]);
            $intake->store($review, $prepared, $request->validated('entry'));

            return $review;
        });

        if ($canStart) {
            ReviewProse::dispatch($review);
        }

        return response()->json([
            'review' => $review->refresh()->toSummary(),
            'command' => $canStart ? null : "/doc-scaffold --review={$review->id}",
        ], 202);
    }

    /**
     * One uploaded script as stored (redacted), for the citation viewer.
     */
    public function source(ProseReview $review, ProseReviewSource $source): JsonResponse
    {
        abort_unless($source->review_id === $review->id, 404);

        return response()->json(['source' => $source->toSummary(), 'text' => $source->text()]);
    }
}
