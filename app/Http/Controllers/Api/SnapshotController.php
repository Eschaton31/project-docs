<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePdf;
use App\Models\Document;
use App\Models\Export;
use App\Models\Snapshot;
use App\Services\DocumentTree;
use App\Support\PrintFileName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Published versions: a frozen copy of the document (e.g. "v1.0 submitted") with its own PDF, which stays
 * reproducible while the live document keeps changing.
 */
class SnapshotController extends Controller
{
    public function index(Document $document): JsonResponse
    {
        $snapshots = $document->snapshots()->with(['creator', 'latestExport'])->latest()->get();

        return response()->json(['data' => $snapshots->map->toSummary()]);
    }

    public function store(Request $request, Document $document, DocumentTree $tree): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'mode' => ['sometimes', 'in:draft,final'],
        ]);

        [$snapshot, $export] = DB::transaction(function () use ($request, $document, $tree, $validated) {
            $document->refresh();
            $snapshot = $document->snapshots()->create([
                'label' => $validated['label'],
                'seq' => $document->seq,
                'tree' => $tree->export($document),
                'created_by' => $request->user()->id,
            ]);
            $export = $snapshot->exports()->create([
                'document_id' => $document->id,
                'mode' => $validated['mode'] ?? 'final',
                'status' => Export::QUEUED,
                'file_name' => $this->fileName($document, $validated['label']),
                'requested_by' => $request->user()->id,
            ]);

            return [$snapshot, $export];
        });

        GeneratePdf::dispatch($export);

        return response()->json(['snapshot' => $snapshot->load(['creator', 'latestExport'])->toSummary()], 201);
    }

    /**
     * The frozen content, for viewing a published version in the print view.
     */
    public function show(Snapshot $snapshot): JsonResponse
    {
        return response()->json([...$snapshot->toPrintData(), 'snapshot' => $snapshot->load(['creator', 'latestExport'])->toSummary()]);
    }

    public function pdf(Snapshot $snapshot): StreamedResponse
    {
        $export = $snapshot->latestExport;
        abort_unless($export?->status === Export::DONE && Storage::disk('local')->exists($export->path), 404);

        return Storage::disk('local')->download($export->path, $export->file_name, ['Content-Type' => 'application/pdf']);
    }

    /**
     * The frozen document as importable JSON (e.g. to start a new document from a published version).
     */
    public function export(Snapshot $snapshot): JsonResponse
    {
        $name = $snapshot->document->slug.'_'.$this->labelSlug($snapshot->label);

        return response()
            ->json($snapshot->tree, options: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Disposition', "attachment; filename=\"{$name}.json\"");
    }

    private function fileName(Document $document, string $label): string
    {
        return PrintFileName::make($document->meta['fileName'] ?? $document->slug).'_'.$this->labelSlug($label).'.pdf';
    }

    /** 'v1.0 submitted' → 'v1-0-submitted' (Str::slug alone would drop the dot and give 'v10'). */
    private function labelSlug(string $label): string
    {
        return Str::slug(str_replace('.', '-', $label));
    }
}
