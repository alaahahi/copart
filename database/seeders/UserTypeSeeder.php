<?php

namespace Database\Seeders;

use App\Models\UserType;
use Illuminate\Database\Seeder;

class UserTypeSeeder extends Seeder
{
    /**
     * Core user types used across ERP controllers/services.
     *
     * IDs match the legacy production dump (admin=1, account=2, client=4).
     * Table has no auto-increment, so ids must be supplied.
     */
    public function run(): void
    {
        $types = [
            ['id' => 1, 'name' => 'admin'],
            ['id' => 2, 'name' => 'account'],
            ['id' => 4, 'name' => 'client'],
        ];

        foreach ($types as $type) {
            UserType::query()->updateOrCreate(
                ['name' => $type['name']],
                ['id' => $type['id']]
            );
        }
    }
}
