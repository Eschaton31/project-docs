<?php

use App\Http\Controllers\Api\BlockController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentEventController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\LockController;
use App\Http\Controllers\Api\PrintController;
use App\Http\Controllers\Api\ProseReviewController;
use App\Http\Controllers\Api\ProseScaffoldController;
use App\Http\Controllers\Api\ProseSuggestionController;
use App\Http\Controllers\Api\SnapshotController;
use App\Http\Middleware\AiReviewEnabled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::post('/documents/import', [DocumentController::class, 'import']);
    Route::get('/documents/{document}', [DocumentController::class, 'show']);
    Route::patch('/documents/{document}', [DocumentController::class, 'update']);
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->can('admin');
    Route::get('/documents/{document}/export', [DocumentController::class, 'export']);
    Route::get('/documents/{document}/events', [DocumentEventController::class, 'index']);
    Route::get('/documents/{document}/activity', [HistoryController::class, 'activity']);
    Route::post('/documents/{document}/locks', [LockController::class, 'store']);
    Route::delete('/documents/{document}/locks/mine', [LockController::class, 'destroyMine']);
    Route::delete('/documents/{document}/locks', [LockController::class, 'destroy']);

    Route::post('/documents/{document}/blocks', [BlockController::class, 'store']);
    Route::post('/documents/{document}/blocks/tree', [BlockController::class, 'insertTree']);
    Route::post('/documents/{document}/blocks/replace', [BlockController::class, 'replace']);
    Route::post('/blocks/{block}/restore', [BlockController::class, 'restore'])->withTrashed();
    Route::get('/blocks/{block}/revisions', [HistoryController::class, 'block'])->withTrashed();
    Route::patch('/blocks/{block}', [BlockController::class, 'update']);
    Route::patch('/blocks/{block}/move', [BlockController::class, 'move']);
    Route::delete('/blocks/{block}', [BlockController::class, 'destroy']);

    Route::post('/documents/{document}/exports', [ExportController::class, 'store']);
    Route::get('/exports/{export}', [ExportController::class, 'show']);
    Route::get('/exports/{export}/download', [ExportController::class, 'download']);

    Route::get('/documents/{document}/snapshots', [SnapshotController::class, 'index']);
    Route::post('/documents/{document}/snapshots', [SnapshotController::class, 'store']);
    Route::get('/snapshots/{snapshot}', [SnapshotController::class, 'show']);
    Route::get('/snapshots/{snapshot}/pdf', [SnapshotController::class, 'pdf']);
    Route::get('/snapshots/{snapshot}/export', [SnapshotController::class, 'export']);

    Route::get('/images', [ImageController::class, 'index']);
    Route::post('/images', [ImageController::class, 'store']);

    // AI prose review: admins only
    Route::middleware([AiReviewEnabled::class, 'can:admin'])->group(function () {
        Route::get('/documents/{document}/reviews', [ProseReviewController::class, 'index']);
        Route::post('/documents/{document}/reviews', [ProseReviewController::class, 'store'])->middleware('throttle:ai-review');
        Route::get('/reviews/{review}', [ProseReviewController::class, 'show']);
        Route::post('/reviews/{review}/accept-rule', [ProseSuggestionController::class, 'acceptRule']);
        Route::post('/reviews/{review}/accept-all', [ProseSuggestionController::class, 'acceptAll']);
        // documentation drafted from uploaded R scripts
        Route::post('/documents/{document}/scaffolds', [ProseScaffoldController::class, 'store'])->middleware('throttle:ai-review');
        Route::get('/reviews/{review}/sources/{source}', [ProseScaffoldController::class, 'source']);
        Route::post('/suggestions/{suggestion}/accept', [ProseSuggestionController::class, 'accept']);
        Route::post('/suggestions/{suggestion}/dismiss', [ProseSuggestionController::class, 'dismiss']);
    });
});

// print data for headless Chrome (PDF export): no session, only short-lived signed URLs
Route::middleware('signed:relative')->group(function () {
    Route::get('/print/documents/{document}', [PrintController::class, 'document'])->name('print.document');
    Route::get('/print/snapshots/{snapshot}', [PrintController::class, 'snapshot'])->name('print.snapshot');
});
