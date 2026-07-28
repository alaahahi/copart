<?php

namespace App\Policies;

use App\Models\SystemConfig;
use App\Models\User;

class SystemConfigPolicy
{
    /**
     * Viewing settings is available to authenticated staff (route already auth-gated).
     */
    public function view(User $user, ?SystemConfig $config = null): bool
    {
        return true;
    }

    /**
     * Updating system / WA Queue settings is admin-only.
     */
    public function update(User $user, ?SystemConfig $config = null): bool
    {
        return (int) $user->type_id === 1;
    }

    /**
     * Operational wipe (cars / traders / wallets / payments) — admin only.
     */
    public function reset(User $user, ?SystemConfig $config = null): bool
    {
        return (int) $user->type_id === 1;
    }
}
