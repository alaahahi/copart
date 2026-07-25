<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate QA / Playwright HTTP endpoints for production.
 * Enable only when QA_E2E_ENABLED=true in .env.
 */
class EnsureQaE2eEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('qa.enabled')) {
            abort(404);
        }

        return $next($request);
    }
}
