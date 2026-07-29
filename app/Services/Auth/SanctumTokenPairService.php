<?php

namespace App\Services\Auth;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class SanctumTokenPairService
{
    public const ACCESS_TOKEN_NAME = 'erp-access';

    public const REFRESH_TOKEN_NAME = 'erp-refresh';

    public const REFRESH_ABILITY = 'refresh-token';

    /**
     * Issue a Sanctum access + refresh token pair (30-day refresh by default).
     *
     * @return array{access_token: string, refresh_token: string, access_expires_at: string|null, refresh_expires_at: string|null}
     */
    public function issue(User $user, bool $revokeExisting = true): array
    {
        if ($revokeExisting) {
            $this->revokePair($user);
        }

        $accessTtl = (int) config('sanctum.access_token_ttl_minutes', 1440);
        $refreshTtl = (int) config('sanctum.refresh_token_ttl_minutes', 43200);

        $access = $user->createToken(
            self::ACCESS_TOKEN_NAME,
            ['*'],
            now()->addMinutes($accessTtl)
        );

        $refresh = $user->createToken(
            self::REFRESH_TOKEN_NAME,
            [self::REFRESH_ABILITY],
            now()->addMinutes($refreshTtl)
        );

        return $this->formatPair($access, $refresh);
    }

    /**
     * Rotate access (and refresh) from a valid refresh token plain text.
     *
     * @return array{access_token: string, refresh_token: string, access_expires_at: string|null, refresh_expires_at: string|null}
     */
    public function refresh(string $refreshTokenPlain): array
    {
        $token = PersonalAccessToken::findToken($refreshTokenPlain);

        if (! $token
            || $token->name !== self::REFRESH_TOKEN_NAME
            || ! $token->can(self::REFRESH_ABILITY)
            || ($token->expires_at && $token->expires_at->isPast())
        ) {
            abort(401, 'Invalid or expired refresh token.');
        }

        /** @var User $user */
        $user = $token->tokenable;

        if (! $user || ($user->is_band ?? false)) {
            abort(401, 'Invalid or expired refresh token.');
        }

        $token->delete();

        return $this->issue($user, true);
    }

    public function revokePair(User $user): void
    {
        $user->tokens()
            ->whereIn('name', [self::ACCESS_TOKEN_NAME, self::REFRESH_TOKEN_NAME])
            ->delete();
    }

    /**
     * @return array{access_token: string, refresh_token: string, access_expires_at: string|null, refresh_expires_at: string|null}
     */
    private function formatPair(NewAccessToken $access, NewAccessToken $refresh): array
    {
        return [
            'access_token' => $access->plainTextToken,
            'refresh_token' => $refresh->plainTextToken,
            'access_expires_at' => optional($access->accessToken->expires_at)?->toIso8601String(),
            'refresh_expires_at' => optional($refresh->accessToken->expires_at)?->toIso8601String(),
        ];
    }
}
