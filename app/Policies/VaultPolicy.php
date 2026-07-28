<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vault;

class VaultPolicy
{
    public function viewAny(User $user): bool
    {
        return (int) $user->owner_id > 0;
    }

    public function view(User $user, Vault $vault): bool
    {
        return (int) $user->owner_id === (int) $vault->owner_id;
    }

    public function create(User $user): bool
    {
        return (int) $user->type_id === 1;
    }

    public function update(User $user, Vault $vault): bool
    {
        return (int) $user->type_id === 1
            && (int) $user->owner_id === (int) $vault->owner_id;
    }

    public function delete(User $user, Vault $vault): bool
    {
        return $this->update($user, $vault);
    }
}
