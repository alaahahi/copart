<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AdminSeeder extends Seeder
{
    /**
     * Idempotent admin user for local / fresh ERP installs.
     *
     * Expects UserTypeSeeder to have run first (admin type).
     * Matches production dump pattern: type_id=admin (id 1), is_band=0,
     * owner_id default 1, plus a wallet row like UserController::store.
     */
    public function run(): void
    {
        if (! Schema::hasTable((new UserType)->getTable()) || ! Schema::hasTable((new User)->getTable())) {
            throw new RuntimeException(
                'الجداول الأساسية غير موجودة في قاعدة البيانات. نفّذ الأمر "php artisan migrate" أولاً ثم أعد تشغيل الـ seeder.'
            );
        }

        $adminType = UserType::query()->where('name', 'admin')->first();

        if (! $adminType) {
            $this->call(UserTypeSeeder::class);
            $adminType = UserType::query()->where('name', 'admin')->firstOrFail();
        }

        $ownerId = 1;
        if (Schema::hasTable('owner') && ! DB::table('owner')->where('id', $ownerId)->exists()) {
            DB::table('owner')->insert([
                'id' => $ownerId,
                'slug' => 'default',
                'location' => null,
                'title' => 'Default',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $user = User::query()->updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'admin',
                'password' => Hash::make('12345678'),
                'type_id' => $adminType->id,
                'owner_id' => $ownerId,
                'is_band' => 0,
                'created' => Carbon::now()->format('Y-m-d'),
                'year_date' => (int) Carbon::now()->format('Y'),
            ]
        );

        // Wallets removed — admin uses ledger only.

        // Secondary admin. firstOrCreate (not updateOrCreate) so an already
        // changed password is never reset by re-running the seeder.
        $secondEmail = env('SEED_ADMIN2_EMAIL', 'admin2@admin.com');
        $secondPassword = env('SEED_ADMIN2_PASSWORD', '12345678');

        $second = User::query()->firstOrCreate(
            ['email' => $secondEmail],
            [
                'name' => 'admin2',
                'password' => Hash::make($secondPassword),
                'type_id' => $adminType->id,
                'owner_id' => $ownerId,
                'is_band' => 0,
                'created' => Carbon::now()->format('Y-m-d'),
                'year_date' => (int) Carbon::now()->format('Y'),
            ]
        );

        if ($second->wasRecentlyCreated) {
            $this->command?->warn("Created admin user {$secondEmail} with the default password. Change it after first login.");
        }
    }
}
