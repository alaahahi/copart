<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\User;

class CarPolicy
{
    /**
     * Only staff within the car's own tenant (owner_id) and with an
     * account-management role may delete a car. Mirrors the type_id gate
     * already used to show/hide the delete button in Sales/Purchases/
     * Clients screens (type_id 1 = admin, 6 = account) and the same
     * convention as DeleteTraderProfitEntryRequest.
     */
    public function delete(User $user, Car $car): bool
    {
        if ((int) $car->owner_id !== (int) $user->owner_id) {
            return false;
        }

        return in_array((int) $user->type_id, [1, 6], true);
    }

    /**
     * Restoring a soft-deleted car follows the same rules as deleting it.
     */
    public function restore(User $user, Car $car): bool
    {
        return $this->delete($user, $car);
    }
}
