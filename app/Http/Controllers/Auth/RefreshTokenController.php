<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Services\Auth\SanctumTokenPairService;
use Illuminate\Http\JsonResponse;

class RefreshTokenController extends Controller
{
    public function __invoke(RefreshTokenRequest $request, SanctumTokenPairService $tokens): JsonResponse
    {
        $pair = $tokens->refresh($request->validated('refresh_token'));

        return response()->json([
            'status' => 200,
            'message' => 'Token refreshed',
            'data' => $pair,
        ]);
    }
}
