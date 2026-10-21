<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InsertTreeRequest;
use App\Http\Requests\MoveBlockRequest;
use App\Http\Requests\StoreBlockRequest;
use App\Http\Requests\UpdateBlockRequest;
use App\Models\Block;
use App\Models\Document;
use App\Models\Revision;
use App\Services\DocumentEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Responses carry the seq of the caller's own change, so the client can tell which incoming
 * events it has already applied.
 */
class BlockController extends Controller
{
    public function __construct(private DocumentEditor $editor) {}

    public function store(StoreBlockRequest $request, Document $document): JsonResponse
    {
        $revision = $this->editor->createBlock($document, $request->user(), $request->validated());

        return $this->respond($revision, 201);
    }

    public function insertTree(InsertTreeRequest $request, Document $document): JsonResponse
    {
        $revision = $this->editor->insertTree(
            $document,
            $request->user(),
            $request->validated('parentId'),
            $request->validated('afterId'),
            $request->validated('beforeId'),
            $request->validated('blocks'),
        );

        return response()->json(['seq' => $revision->seq, 'blocks' => $revision->after['blocks']], 201);
    }

    public function restore(Request $request, Block $block): JsonResponse
    {
        return $this->respond($this->editor->restoreBlock($block, $request->user()));
    }

    public function update(UpdateBlockRequest $request, Block $block): JsonResponse
    {
        $validated = $request->validated();
        $revision = $this->editor->updateBlock($block, $request->user(), $validated, $validated['version']);

        return $this->respond($revision);
    }

    public function move(MoveBlockRequest $request, Block $block): JsonResponse
    {
        $revision = $this->editor->moveBlock(
            $block,
            $request->user(),
            $request->validated('parentId'),
            $request->validated('afterId'),
            $request->validated('beforeId'),
            $request->validated('version'),
        );

        return $this->respond($revision);
    }

    public function destroy(Request $request, Block $block): JsonResponse
    {
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $revision = $this->editor->deleteBlock($block, $request->user(), $validated['version']);

        return response()->json(['seq' => $revision->seq]);
    }

    private function respond(Revision $revision, int $status = 200): JsonResponse
    {
        return response()->json(['seq' => $revision->seq, 'block' => $revision->after], $status);
    }
}
