<?php

namespace App\Http\Middleware;

use App\Support\VinstackIntegrationOwner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyVinstackIntegrationToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('vinstack_integration.token', ''));

        if ($expected === '') {
            return response()->json([
                'message' => 'Vinstack integration token is not configured on this server.',
            ], 503);
        }

        $provided = $request->bearerToken()
            ?? $request->header('X-Vinstack-Token')
            ?? '';

        if (! hash_equals($expected, (string) $provided)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $ownerId = VinstackIntegrationOwner::resolve();

        if ($ownerId <= 0) {
            return response()->json([
                'message' => 'Could not resolve tenant owner_id. Set VINSTACK_INTEGRATION_OWNER_ID in .env.',
            ], 503);
        }

        $request->attributes->set('vinstack_owner_id', $ownerId);

        return $next($request);
    }
}
