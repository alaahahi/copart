<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safety net for databases where `users` is absent or partially shaped while the
 * `migrations` table already claims the earlier user migrations ran (aborted
 * batch, or schema imported from a legacy SQL dump). In that state every
 * `add_*_to_users` migration silently skips itself and the app fails at runtime
 * with "no such table: users" / missing column errors.
 *
 * Mirrors 2026_08_29_150000_ensure_system_config_table: create when missing,
 * backfill column-by-column when present, never drop anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                // Nullable: clients/traders imported from the dump may have no login email.
                $table->string('email')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password')->nullable();
                $table->rememberToken();
                $table->bigInteger('type_id')->nullable();
                $table->integer('owner_id')->nullable()->default(1);
                $table->integer('parent_id')->nullable();
                $table->tinyInteger('is_band')->default(0);
                $table->integer('percentage')->nullable()->default(0);
                $table->unsignedBigInteger('morphed_id')->nullable();
                $table->string('morphed_type')->nullable();
                $table->string('phone')->nullable();
                $table->string('device')->nullable();
                $table->text('public_key')->nullable();
                $table->text('publickey_receiver')->nullable();
                $table->date('created')->nullable();
                $table->integer('year_date')->nullable()->default(2024);
                $table->boolean('has_wallet_tags')->default(0);
                $table->boolean('show_in_dashboard')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->index('type_id');
                $table->index('owner_id');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->unique('email');
            });

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'name' => fn () => $table->string('name')->nullable(),
                'email' => fn () => $table->string('email')->nullable(),
                'email_verified_at' => fn () => $table->timestamp('email_verified_at')->nullable(),
                'password' => fn () => $table->string('password')->nullable(),
                'remember_token' => fn () => $table->rememberToken(),
                'type_id' => fn () => $table->bigInteger('type_id')->nullable(),
                'owner_id' => fn () => $table->integer('owner_id')->nullable()->default(1),
                'parent_id' => fn () => $table->integer('parent_id')->nullable(),
                'is_band' => fn () => $table->tinyInteger('is_band')->default(0),
                'percentage' => fn () => $table->integer('percentage')->nullable()->default(0),
                'morphed_id' => fn () => $table->unsignedBigInteger('morphed_id')->nullable(),
                'morphed_type' => fn () => $table->string('morphed_type')->nullable(),
                'phone' => fn () => $table->string('phone')->nullable(),
                'device' => fn () => $table->string('device')->nullable(),
                'public_key' => fn () => $table->text('public_key')->nullable(),
                'publickey_receiver' => fn () => $table->text('publickey_receiver')->nullable(),
                'created' => fn () => $table->date('created')->nullable(),
                'year_date' => fn () => $table->integer('year_date')->nullable()->default(2024),
                'has_wallet_tags' => fn () => $table->boolean('has_wallet_tags')->default(0),
                'show_in_dashboard' => fn () => $table->boolean('show_in_dashboard')->default(false),
                'created_at' => fn () => $table->timestamp('created_at')->nullable(),
                'updated_at' => fn () => $table->timestamp('updated_at')->nullable(),
                'deleted_at' => fn () => $table->softDeletes(),
            ] as $column => $definition) {
                if (! Schema::hasColumn('users', $column)) {
                    $definition();
                }
            }
        });
    }

    public function down(): void
    {
        // Non-destructive: the table may predate migrations (legacy SQL dump).
    }
};
