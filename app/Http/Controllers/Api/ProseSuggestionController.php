<?php

namespace App\Http\Controllers\Api;

use App\Ai\Agents\ProseReviewer;
use App\Http\Controllers\Controller;
use App\Models\ProseReview;
use App\Models\ProseSuggestion;
use App\Models\Revision;
use App\Services\ProseReviews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Accepting and dismissing suggestions (admins only). Responses carry the document change as an event, since
 * the tab that made it skips its own live events.
 */
class ProseSuggestionController extends Controller
{
    public function __construct(private ProseReviews $reviews) {}

    public function accept(Request $request, ProseSuggestion $suggestion): JsonResponse
    {
        $revision = $this->reviews->accept($suggestion, $request->user());

        return response()->json(['suggestion' => $suggestion->fresh()->toSummary(), 'change' => $revision->toEvent()]);
    }

    public function dismiss(Request $request, ProseSuggestion $suggestion): JsonResponse
    {
        $this->reviews->dismiss($suggestion, $request->user());

        return response()->json(['suggestion' => $suggestion->fresh()->toSummary()]);
    }

    public function acceptRule(Request $request, ProseReview $review): JsonResponse
    {
        $rule = $request->validate(['rule' => ['required', Rule::in(ProseReviewer::RULES)]])['rule'];
        $result = $this->reviews->acceptRule($review, $rule, $request->user());

        return response()->json([
            'accepted' => $result['accepted'],
            'stale' => $result['stale'],
            'locked' => $result['locked'],
            'changes' => array_map(fn (Revision $revision) => $revision->toEvent(), $result['revisions']),
            'suggestions' => $review->suggestions()->orderBy('id')->get()->map->toSummary(),
        ]);
    }

    /**
     * Every pending suggestion of a scaffold, in the template's order.
     */
    public function acceptAll(Request $request, ProseReview $review): JsonResponse
    {
        abort_unless($review->isScaffold(), 404);
        $result = $this->reviews->acceptAll($review, $request->user());

        return response()->json([
            'accepted' => $result['accepted'],
            'stale' => $result['stale'],
            'locked' => $result['locked'],
            'changes' => array_map(fn (Revision $revision) => $revision->toEvent(), $result['revisions']),
            'suggestions' => $review->suggestions()->orderBy('id')->get()->map->toSummary(),
        ]);
    }
}
