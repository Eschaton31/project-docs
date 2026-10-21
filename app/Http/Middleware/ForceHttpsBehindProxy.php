<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Behind the HTTPS proxy chain the request itself can look like plain http, so when APP_URL is https,
 * proxied requests get https URLs. Requests made straight to this server (headless Chrome loading the
 * print view from PDF_BASE_URL) keep their own scheme: forcing https there would point the page's
 * scripts at https://127.0.0.1, which nothing serves.
 */
class ForceHttpsBehindProxy
{
    public function handle(Request $request, Closure $next): Response
    {
        if (str_starts_with(config('app.url'), 'https://') && $request->headers->has('X-Forwarded-For')) {
            URL::forceScheme('https');
        }

        return $next($request);
    }
}
