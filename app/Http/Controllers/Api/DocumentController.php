<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Document;
use App\Services\BlockLocks;
use App\Services\DocumentEditor;
use App\Services\DocumentTree;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DocumentController extends Controller
{
    public function __construct(private DocumentEditor $editor, private DocumentTree $tree) {}

    public function index(): JsonResponse
    {
        $documents = Document::query()
            ->orderByDesc('updated_at')
            ->get(['id', 'slug', 'title', 'version', 'seq', 'updated_by', 'updated_at', 'meta', 'title_page']);

        return response()->json(['data' => $documents->map->toSummary()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['title' => ['required', 'string', 'max:255']]);
        $document = $this->editor->createDocument($request->user(), $validated['title']);

        return response()->json(['document' => $document->toSummary(), 'blocks' => []], 201);
    }

    public function show(Document $document, BlockLocks $locks): JsonResponse
    {
        return response()->json([
            'document' => $document->toSummary(),
            'blocks' => $this->tree->build($document),
            'locks' => $locks->active($document)->map->toHolder()->values(),
        ]);
    }

    public function update(UpdateDocumentRequest $request, Document $document): JsonResponse
    {
        $validated = $request->validated();
        $revision = $this->editor->updateDocument($document, $request->user(), $validated, $validated['version']);

        return response()->json(['seq' => $revision->seq, 'document' => $revision->after]);
    }

    public function destroy(Document $document): Response
    {
        $document->delete();

        return response()->noContent();
    }

    public function export(Document $document): JsonResponse
    {
        return response()
            ->json($this->tree->export($document), options: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Disposition', "attachment; filename=\"{$document->slug}.json\"");
    }

    public function import(Request $request): JsonResponse
    {
        $document = $this->tree->import($request->json()->all(), $request->user());

        return response()->json(['document' => $document->toSummary()], 201);
    }
}
