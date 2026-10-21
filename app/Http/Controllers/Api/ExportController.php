<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePdf;
use App\Models\Document;
use App\Models\Export;
use App\Support\PrintFileName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One-click PDF: queue an export, poll it, download the file.
 */
class ExportController extends Controller
{
    public function store(Request $request, Document $document): JsonResponse
    {
        $mode = $request->validate(['mode' => ['sometimes', 'in:draft,final']])['mode'] ?? 'draft';

        $export = $document->exports()->create([
            'mode' => $mode,
            'status' => Export::QUEUED,
            'file_name' => PrintFileName::make($document->meta['fileName'] ?? $document->slug).'.pdf',
            'requested_by' => $request->user()->id,
        ]);
        GeneratePdf::dispatch($export);

        return response()->json(['export' => $export->refresh()->toSummary()], 202);
    }

    public function show(Export $export): JsonResponse
    {
        return response()->json(['export' => $export->toSummary()]);
    }

    public function download(Export $export): StreamedResponse
    {
        abort_unless($export->status === Export::DONE && Storage::disk('local')->exists($export->path), 404);

        return Storage::disk('local')->download($export->path, $export->file_name, ['Content-Type' => 'application/pdf']);
    }
}
