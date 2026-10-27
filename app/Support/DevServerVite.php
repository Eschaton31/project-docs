<?php

namespace App\Support;

use Illuminate\Foundation\Vite;

/**
 * `composer dev` serves Vite on every address, so point each page at the dev server the visitor can reach:
 * through the cloudflared tunnel it's the same origin (the tunnel routes /vite-dev/ to Vite, see vite.config.js),
 * otherwise it's port 5173 on whichever address the page was opened on (localhost, LAN IP, 127.0.0.1 for PDFs).
 */
class DevServerVite extends Vite
{
    public function devServerUrl()
    {
        $url = parent::devServerUrl();
        $request = request();

        if ($url === null) {
            return null;
        }

        $path = rtrim(parse_url($url, PHP_URL_PATH) ?? '', '/');

        if ($request->headers->has('X-Forwarded-For')) {
            return $path;
        }

        return 'http://'.$request->getHost().':'.parse_url($url, PHP_URL_PORT).$path;
    }
}
