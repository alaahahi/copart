<?php

namespace Database\Seeders;

use App\Models\UserType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

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
        $table = (new UserType)->getTable();

        if (! Schema::hasTable($table)) {
            throw new RuntimeException(
                "جدول \"{$table}\" غير موجود. نفّذ الأمر \"php artisan migrate\" أولاً ثم أعد تشغيل الـ seeder."
            );
        }

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

        // Legacy "annual clients" module (AnnualController) needs this type.
        // Its legacy id is unknown, so allocate the next free id instead of guessing.
        if (! UserType::query()->where('name', 'clientAnnual')->exists()) {
            UserType::query()->create([
                'id' => (int) UserType::query()->max('id') + 1,
                'name' => 'clientAnnual',
            ]);
        }
    }
}
