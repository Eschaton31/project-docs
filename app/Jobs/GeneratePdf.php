<?php

namespace App\Jobs;

use App\Models\Export;
use App\Pdf\PdfRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Renders an export in headless Chrome. Runs on the queue because the browser has to load the print view
 * from this same app, which a single-threaded dev server can't serve while it is busy with the request.
 */
class GeneratePdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public Export $export)
    {
        $this->timeout = config('services.pdf.timeout') + 30;
    }

    public function handle(PdfRenderer $renderer): void
    {
        $export = $this->export;
        $export->update(['status' => Export::RUNNING]);

        $path = "exports/{$export->document_id}/{$export->id}.pdf";
        $temp = tempnam(sys_get_temp_dir(), 'pdf');

        try {
            $renderer->render($this->printUrl(), $temp);
            Storage::disk('local')->put($path, fopen($temp, 'r'));
        } finally {
            @unlink($temp);
        }

        $export->update(['status' => Export::DONE, 'path' => $path, 'error' => null]);
        $export->snapshot?->update(['pdf_path' => $path]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->export->update(['status' => Export::FAILED, 'error' => $exception?->getMessage()]);
    }

    /**
     * The print view, reading its data from a short-lived signed URL instead of a login session.
     */
    private function printUrl(): string
    {
        $export = $this->export;
        $expires = now()->addSeconds(config('services.pdf.timeout') + 60);

        $source = $export->snapshot_id
            ? URL::temporarySignedRoute('print.snapshot', $expires, ['snapshot' => $export->snapshot_id], absolute: false)
            : URL::temporarySignedRoute('print.document', $expires, ['document' => $export->document->slug], absolute: false);

        return rtrim(config('services.pdf.base_url'), '/')
            .'/render/'.rawurlencode($export->document->slug)
            .'?'.http_build_query(['mode' => $export->mode, 'source' => $source]);
    }
}
