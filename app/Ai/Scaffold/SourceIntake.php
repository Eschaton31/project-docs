<?php

namespace App\Ai\Scaffold;

use App\Models\ProseReview;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Checks the R scripts a scaffold drafts from, normalizes them (UTF-8, LF line endings), blanks out secrets, and
 * stores them privately. The same checks apply to browser uploads and to files read from disk for Claude Code.
 */
final class SourceIntake
{
    public const DISK = 'local';

    /**
     * @param  list<array{name: string, contents: string}>  $files
     * @return list<array{name: string, text: string, size: int, redactions: list<array{line: int, kind: string}>}>
     *
     * @throws ValidationException
     */
    public function prepare(array $files): array
    {
        $config = config('ai-review.scaffold');
        $this->assert($files !== [], 'Add at least one R script.');
        $this->assert(count($files) <= $config['max_files'], "Upload at most {$config['max_files']} scripts at a time.");

        $prepared = [];
        $total = 0;
        foreach ($files as $file) {
            $name = self::normalizeName($file['name']);
            $this->assert(strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'r', "[{$name}] isn't an R script (.R).");
            $this->assert(! isset($prepared[$name]), "[{$name}] was added twice.");
            $this->assert(strlen($file['contents']) <= $config['max_file_kb'] * 1024, "[{$name}] is larger than {$config['max_file_kb']} KB.");
            $this->assert(! str_contains($file['contents'], "\0"), "[{$name}] isn't a text file.");

            $text = self::normalizeText($file['contents']);
            $this->assert(trim($text) !== '', "[{$name}] is empty.");

            $redacted = SecretRedactor::redact($text);
            $total += mb_strlen($redacted['text']);
            $prepared[$name] = ['name' => $name, 'text' => $redacted['text'], 'size' => strlen($file['contents']), 'redactions' => $redacted['redactions']];
        }

        $this->assert($total <= $config['max_source_chars'], 'These scripts are too long to draft from at once ('.number_format($total).' characters; the most is '.number_format($config['max_source_chars']).'). Upload the main ones.');

        return array_values($prepared);
    }

    /**
     * Saves prepared scripts for the review as F1, F2… in the order given.
     *
     * @param  list<array{name: string, text: string, size: int, redactions: list}>  $prepared
     * @param  ?string  $entry  the name of the script the admin opens first
     */
    public function store(ProseReview $review, array $prepared, ?string $entry): void
    {
        $entry = $entry !== null && trim($entry) !== '' ? self::normalizeName($entry) : null;
        if ($entry !== null && ! in_array($entry, array_column($prepared, 'name'), true)) {
            throw ValidationException::withMessages(['entry' => "[{$entry}] isn't one of the uploaded scripts."]);
        }

        foreach (array_values($prepared) as $i => $file) {
            $ref = 'F'.($i + 1);
            $path = "scaffold-sources/{$review->id}/{$ref}.R";
            Storage::disk(self::DISK)->put($path, $file['text']);

            $review->sources()->create([
                'ref' => $ref,
                'name' => $file['name'],
                'entry' => $file['name'] === $entry,
                'disk' => self::DISK,
                'path' => $path,
                'size' => $file['size'],
                'sha256' => hash('sha256', $file['text']),
                'chars' => mb_strlen($file['text']),
                'lines' => substr_count($file['text'], "\n") + (str_ends_with($file['text'], "\n") ? 0 : 1),
                'redactions' => $file['redactions'],
            ]);
        }
    }

    /**
     * Uploaded files; $paths are their paths inside a dropped folder (webkitRelativePath), when the browser gave them.
     *
     * @param  list<UploadedFile>  $uploads
     * @param  list<?string>  $paths
     * @return list<array{name: string, contents: string}>
     */
    public static function fromUploads(array $uploads, array $paths = []): array
    {
        return array_map(fn (UploadedFile $file, int $i) => [
            'name' => ($paths[$i] ?? null) ?: $file->getClientOriginalName(),
            'contents' => (string) $file->get(),
        ], array_values($uploads), array_keys(array_values($uploads)));
    }

    /**
     * Files, or folders whose .R files are taken with their paths inside the folder.
     *
     * @param  list<string>  $paths
     * @return list<array{name: string, contents: string}>
     */
    public static function fromPaths(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (File::isDirectory($path)) {
                $scripts = collect(File::allFiles($path))
                    ->filter(fn (SplFileInfo $file) => strtolower($file->getExtension()) === 'r')
                    ->sortBy(fn (SplFileInfo $file) => $file->getRelativePathname());
                foreach ($scripts as $file) {
                    $files[] = ['name' => $file->getRelativePathname(), 'contents' => $file->getContents()];
                }
            } elseif (File::isFile($path)) {
                $files[] = ['name' => basename($path), 'contents' => File::get($path)];
            } else {
                throw ValidationException::withMessages(['files' => "Nothing at [{$path}]."]);
            }
        }

        return $files;
    }

    /**
     * Forward slashes, no drive or leading slash, no "." segments; ".." is refused so a name never points outside
     * the upload.
     */
    public static function normalizeName(string $name): string
    {
        $name = preg_replace('/^[A-Za-z]:/', '', str_replace('\\', '/', trim($name)));
        $segments = array_values(array_filter(explode('/', $name), fn (string $segment) => $segment !== '' && $segment !== '.'));

        if ($segments === [] || in_array('..', $segments, true) || preg_match('/[\x00-\x1f]/', $name) || mb_strlen($name) > 255) {
            throw ValidationException::withMessages(['files' => "[{$name}] isn't a usable file name."]);
        }

        return implode('/', $segments);
    }

    /**
     * UTF-8 without a BOM (Windows-1252 converted), LF line endings.
     */
    public static function normalizeText(string $contents): string
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        return str_replace(["\r\n", "\r"], "\n", $contents);
    }

    private function assert(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['files' => $message]);
        }
    }
}
