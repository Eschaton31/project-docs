<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Images for Image blocks: everything under public/img (where the hand-built pages keep theirs).
 * Uploads go into a folder of the uploader's choosing there, or public/img/uploads/<year-month>.
 */
class ImageController extends Controller
{
    private const EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];

    /**
     * Up to five folder levels; every segment starts with a letter or digit, so "..", absolute paths and
     * hidden folders can't be named and uploads stay inside public/img.
     */
    private const FOLDER = '/^[A-Za-z0-9][A-Za-z0-9 _.-]*(\/[A-Za-z0-9][A-Za-z0-9 _.-]*){0,4}$/';

    public function index(): JsonResponse
    {
        $disk = Storage::disk('images');

        $images = collect($disk->allFiles())
            ->filter(fn (string $path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true))
            ->map(fn (string $path) => [
                'path' => $disk->url($path),
                'name' => basename($path),
                'folder' => dirname($path) === '.' ? '' : str_replace('\\', '/', dirname($path)),
                'size' => $disk->size($path),
            ])
            ->sortBy(['folder', 'name'])
            ->values();

        $folders = collect($disk->allDirectories())
            ->map(fn (string $path) => str_replace('\\', '/', $path))
            ->sort()
            ->values();

        return response()->json(['data' => $images, 'folders' => $folders]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // no SVG uploads: served from this origin, an SVG opened directly could run script
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,gif,webp', 'max:10240'],
            'folder' => ['nullable', 'string', 'max:150', 'regex:'.self::FOLDER],
        ]);
        $file = $validated['file'];
        $folder = trim($validated['folder'] ?? '') ?: 'uploads/'.now()->format('Y-m');

        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'image';
        // the random suffix means an upload never replaces an existing image
        $path = $file->storeAs($folder, "{$name}-".Str::lower(Str::random(6)).'.'.$file->extension(), 'images');

        Asset::create([
            'disk' => 'images',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json([
            'image' => ['path' => Storage::disk('images')->url($path), 'name' => basename($path), 'folder' => $folder],
        ], 201);
    }
}
