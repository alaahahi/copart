<?php

namespace App\Policies;

use App\Models\User;

/**
 * Settings user management — admin only (type_id = 1).
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isAdmin($actor);
    }

    public function view(User $actor, User $model): bool
    {
        return $this->isAdmin($actor);
    }

    public function create(User $actor): bool
    {
        return $this->isAdmin($actor);
    }

    public function update(User $actor, User $model): bool
    {
        return $this->isAdmin($actor);
    }

    public function delete(User $actor, User $model): bool
    {
        return $this->isAdmin($actor);
    }

    public function resetPassword(User $actor, User $model): bool
    {
        return $this->isAdmin($actor);
    }

    protected function isAdmin(User $user): bool
    {
        return (int) $user->type_id === 1;
    }
}
