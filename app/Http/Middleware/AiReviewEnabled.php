<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The AI prose review routes exist only while AI_REVIEW_ENABLED is on.
 */
class AiReviewEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('ai-review.enabled'), 404);

        return $next($request);
    }
}
