<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\BlockLocks;
use App\Support\EditorClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Taking (or renewing), releasing and dropping locks for the requesting tab (X-Client-Id).
 */
class LockController extends Controller
{
    public function __construct(private BlockLocks $locks) {}

    public function store(Request $request, Document $document): JsonResponse
    {
        $target = $this->target($request, $document);
        $lock = $this->locks->acquire($document, $request->user(), $this->clientId(), $target);

        return response()->json(['lock' => $lock->toHolder()]);
    }

    public function destroy(Request $request, Document $document): Response
    {
        $this->locks->release($document, $this->clientId(), $this->target($request, $document));

        return response()->noContent();
    }

    public function destroyMine(Document $document): Response
    {
        $this->locks->releaseAll($document, $this->clientId());

        return response()->noContent();
    }

    private function target(Request $request, Document $document): string
    {
        $target = $request->validate([
            'target' => ['required', 'string', 'regex:/^(settings|block:[0-9a-f-]{36})$/'],
        ])['target'];

        if (str_starts_with($target, 'block:') && ! $document->blocks()->whereKey(substr($target, 6))->exists()) {
            throw ValidationException::withMessages(['target' => 'That block is not in this document.']);
        }

        return $target;
    }

    private function clientId(): string
    {
        return EditorClient::id() ?? throw ValidationException::withMessages([
            'client' => 'Locks need the X-Client-Id header the editor sends.',
        ]);
    }
}
