<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict routes to seeded admin users (type_id = 1 by default).
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $this->deny($request, 'Unauthorized', 403);
        }

        $allowed = config('qa.admin_type_ids', [1]);
        if (! in_array((int) $user->type_id, $allowed, true)) {
            return $this->deny($request, 'QA access denied — admin only', 403);
        }

        return $next($request);
    }

    protected function deny(Request $request, string $message, int $status): Response
    {
        if ($request->expectsJson() || $request->is('qa/e2e/*')) {
            return response()->json(['message' => $message, 'result' => null], $status);
        }

        abort($status, $message);
    }
}
