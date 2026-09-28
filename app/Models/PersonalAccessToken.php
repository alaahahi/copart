<?php

namespace App\Models;

use App\Services\Auth\SanctumTokenPairService;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Prefer signed (stateless) tokens so SQLite is never locked by PAT inserts.
 * DB-backed tokens are ignored on purpose for first-party SPA/mobile auth.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /** @var bool */
    public $stateless = false;

    public function markAsStateless(): void
    {
        $this->stateless = true;
    }

    /**
     * @param  string  $token
     * @return static|null
     */
    public static function findToken($token)
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        return app(SanctumTokenPairService::class)->resolveAccessTokenModel($token);
    }

    /**
     * Never persist last_used_at / inserts for signed tokens.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if ($this->stateless) {
            return true;
        }

        return parent::save($options);
    }
}
