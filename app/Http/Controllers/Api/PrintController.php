<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Snapshot;
use App\Services\DocumentTree;
use Illuminate\Http\JsonResponse;

/**
 * Print data for headless Chrome. No login: these routes only answer short-lived signed URLs that the
 * PDF job creates (middleware signed:relative).
 */
class PrintController extends Controller
{
    public function document(Document $document, DocumentTree $tree): JsonResponse
    {
        return response()->json(['document' => $document->toSummary(), 'blocks' => $tree->build($document)]);
    }

    public function snapshot(Snapshot $snapshot): JsonResponse
    {
        return response()->json($snapshot->toPrintData());
    }
}
