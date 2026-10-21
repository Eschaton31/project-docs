<?php

namespace App\Pdf;

use Spatie\Browsershot\Browsershot;

/**
 * Headless Chrome through Browsershot (Puppeteer, installed with npm). The print view sets
 * window.PAGED_READY once Paged.js is done; page size and margins come from the @page rules in app.css.
 */
class BrowsershotRenderer implements PdfRenderer
{
    public function __construct(private array $config) {}

    public function render(string $url, string $path): void
    {
        $timeoutMs = $this->config['timeout'] * 1000;

        $browsershot = Browsershot::url($url)
            ->waitForFunction('window.PAGED_READY === true', null, $timeoutMs)
            ->timeout($this->config['timeout'])
            ->showBackground()
            ->setOption('preferCSSPageSize', true)
            ->margins(0, 0, 0, 0);

        if ($this->config['chrome_path']) {
            $browsershot->setChromePath($this->config['chrome_path']);
        }
        if ($this->config['node_binary']) {
            $browsershot->setNodeBinary($this->config['node_binary']);
        }

        $browsershot->savePdf($path);
    }
}
