<?php

namespace App\Support;

use App\Models\Car;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Facades\Cache;

final class VinstackIntegrationOwner
{
    /**
     * Resolve tenant owner_id for server-to-server imports.
     * Prefer explicit env, then most-used car owner, then first admin user.
     */
    public static function resolve(): int
    {
        $configured = (int) config('vinstack_integration.owner_id', 0);

        if ($configured > 0) {
            return $configured;
        }

        return (int) Cache::remember('vinstack_integration.owner_id', 300, function () {
            $fromCars = Car::query()
                ->selectRaw('owner_id, COUNT(*) as c')
                ->whereNotNull('owner_id')
                ->where('owner_id', '>', 0)
                ->groupBy('owner_id')
                ->orderByDesc('c')
                ->value('owner_id');

            if ($fromCars) {
                return (int) $fromCars;
            }

            $adminTypeId = (int) UserType::query()->where('name', 'admin')->value('id');

            if ($adminTypeId > 0) {
                $fromAdmin = User::query()
                    ->where('type_id', $adminTypeId)
                    ->whereNotNull('owner_id')
                    ->where('owner_id', '>', 0)
                    ->orderBy('id')
                    ->value('owner_id');

                if ($fromAdmin) {
                    return (int) $fromAdmin;
                }
            }

            return (int) User::query()
                ->whereNotNull('owner_id')
                ->where('owner_id', '>', 0)
                ->orderBy('id')
                ->value('owner_id');
        });
    }
}
