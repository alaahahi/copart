<?php

namespace App\Services\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

/**
 * Issues signed access/refresh token pairs without touching personal_access_tokens.
 * SPA browser auth should use the session cookie instead; these tokens are for
 * mobile / non-cookie API clients only.
 */
class SanctumTokenPairService
{
    public const ACCESS_TOKEN_NAME = 'erp-access';

    public const REFRESH_TOKEN_NAME = 'erp-refresh';

    public const REFRESH_ABILITY = 'refresh-token';

    public const TOKEN_PREFIX = 'st1.';

    /**
     * @return array{access_token: string, refresh_token: string, access_expires_at: string|null, refresh_expires_at: string|null}
     */
    public function issue(User $user, bool $revokeExisting = true): array
    {
        // Stateless tokens cannot be revoked server-side without a store;
        // $revokeExisting is kept for call-site compatibility.
        unset($revokeExisting);

        $accessTtl = (int) config('sanctum.access_token_ttl_minutes', 1440);
        $refreshTtl = (int) config('sanctum.refresh_token_ttl_minutes', 43200);

        $accessExpires = now()->addMinutes($accessTtl);
        $refreshExpires = now()->addMinutes($refreshTtl);

        $access = $this->encode([
            'uid' => (int) $user->id,
            'typ' => 'access',
            'name' => self::ACCESS_TOKEN_NAME,
            'abilities' => ['*'],
            'exp' => $accessExpires->getTimestamp(),
            'jti' => (string) Str::uuid(),
        ]);

        $refresh = $this->encode([
            'uid' => (int) $user->id,
            'typ' => 'refresh',
            'name' => self::REFRESH_TOKEN_NAME,
            'abilities' => [self::REFRESH_ABILITY],
            'exp' => $refreshExpires->getTimestamp(),
            'jti' => (string) Str::uuid(),
        ]);

        return [
            'access_token' => $access,
            'refresh_token' => $refresh,
            'access_expires_at' => $accessExpires->toIso8601String(),
            'refresh_expires_at' => $refreshExpires->toIso8601String(),
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: string, access_expires_at: string|null, refresh_expires_at: string|null}
     */
    public function refresh(string $refreshTokenPlain): array
    {
        $payload = $this->decode($refreshTokenPlain);

        if (! $payload
            || ($payload['typ'] ?? null) !== 'refresh'
            || ($payload['name'] ?? null) !== self::REFRESH_TOKEN_NAME
            || ! in_array(self::REFRESH_ABILITY, $payload['abilities'] ?? [], true)
            || (int) ($payload['exp'] ?? 0) < time()
        ) {
            abort(401, 'Invalid or expired refresh token.');
        }

        /** @var User|null $user */
        $user = User::query()->find((int) ($payload['uid'] ?? 0));

        if (! $user || ($user->is_band ?? false)) {
            abort(401, 'Invalid or expired refresh token.');
        }

        return $this->issue($user, true);
    }

    /**
     * No server-side store — clients discard tokens on logout.
     */
    public function revokePair(User $user): void
    {
        unset($user);
    }

    public function isStatelessToken(?string $plain): bool
    {
        return is_string($plain) && str_starts_with($plain, self::TOKEN_PREFIX);
    }

    /**
     * Build an in-memory Sanctum token model for Guard::findToken (no DB row).
     */
    public function resolveAccessTokenModel(string $plain): ?PersonalAccessToken
    {
        $payload = $this->decode($plain);

        if (! $payload
            || ($payload['typ'] ?? null) !== 'access'
            || ($payload['name'] ?? null) !== self::ACCESS_TOKEN_NAME
            || (int) ($payload['exp'] ?? 0) < time()
        ) {
            return null;
        }

        /** @var User|null $user */
        $user = User::query()->find((int) ($payload['uid'] ?? 0));

        if (! $user || ($user->is_band ?? false)) {
            return null;
        }

        $token = new PersonalAccessToken([
            'name' => self::ACCESS_TOKEN_NAME,
            'token' => hash('sha256', $plain),
            'abilities' => $payload['abilities'] ?? ['*'],
            'expires_at' => now()->setTimestamp((int) $payload['exp']),
        ]);

        $token->forceFill([
            'id' => 0,
            'tokenable_type' => $user->getMorphClass(),
            'tokenable_id' => $user->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $token->exists = true;
        $token->markAsStateless();
        $token->setRelation('tokenable', $user);

        return $token;
    }

    /**
     * @param  array{uid:int,typ:string,name:string,abilities:array<int,string>,exp:int,jti:string}  $payload
     */
    private function encode(array $payload): string
    {
        return self::TOKEN_PREFIX.Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{uid?:int,typ?:string,name?:string,abilities?:array<int,string>,exp?:int,jti?:string}|null
     */
    private function decode(string $plain): ?array
    {
        if (! $this->isStatelessToken($plain)) {
            return null;
        }

        try {
            $json = Crypt::decryptString(substr($plain, strlen(self::TOKEN_PREFIX)));
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }
}
