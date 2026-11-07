<?php

namespace App\Http\Middleware;

use App\Services\SystemConfigService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNejumPaymentsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = app(SystemConfigService::class)->nejumPaymentsEnabled();

        if (! $enabled) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'مدفوعات Nejum غير مفعّلة في الإعدادات',
                ], 403);
            }

            abort(404);
        }

        return $next($request);
    }
}
