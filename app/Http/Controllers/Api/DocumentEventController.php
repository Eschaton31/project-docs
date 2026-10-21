<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catch-up for clients that missed live events (seq gap, reconnect).
 */
class DocumentEventController extends Controller
{
    private const LIMIT = 500;

    public function index(Request $request, Document $document): JsonResponse
    {
        $since = $request->validate(['since' => ['required', 'integer', 'min:0']])['since'];

        $events = $document->revisions()
            ->where('seq', '>', $since)
            ->orderBy('seq')
            ->limit(self::LIMIT + 1)
            ->get();

        // too far behind: cheaper for the client to reload the whole tree
        if ($events->count() > self::LIMIT) {
            return response()->json(['seq' => $document->seq, 'reset' => true, 'events' => []]);
        }

        return response()->json(['seq' => $document->seq, 'reset' => false, 'events' => $events->map->toEvent()]);
    }
}
