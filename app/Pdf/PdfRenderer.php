<?php

namespace App\Pdf;

/**
 * Turns a print-view URL into a PDF file. Bound to headless Chrome (Browsershot); tests swap in a fake.
 */
interface PdfRenderer
{
    /**
     * Loads $url, waits until Paged.js has paginated it (window.PAGED_READY), and writes the PDF to $path.
     */
    public function render(string $url, string $path): void;
}
