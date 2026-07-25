<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserType;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

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
        $adminType = UserType::query()->where('name', 'admin')->firstOrFail();

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

        Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => 0,
                'balance_dinar' => 0,
                'card' => 0,
            ]
        );
    }
}
