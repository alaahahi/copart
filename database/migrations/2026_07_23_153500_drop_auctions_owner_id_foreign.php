<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove auctions.owner_id → users.id FK.
 *
 * Production tenants use Auth::user()->owner_id as a tenant key that may not
 * exist as a users.id row (e.g. owner_id=1). payment_tags already works without
 * this FK; auctions must match that pattern or inserts fail with 1452.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('auctions')) {
            return;
        }

        Schema::table('auctions', function (Blueprint $table) {
            try {
                $table->dropForeign(['owner_id']);
            } catch (\Throwable $e) {
                // Constraint name may differ, or already dropped.
            }
        });

        // Fallback for MySQL when Laravel's dropForeign name doesn't match.
        try {
            Schema::getConnection()->statement(
                'ALTER TABLE `auctions` DROP FOREIGN KEY `auctions_owner_id_foreign`'
            );
        } catch (\Throwable $e) {
            // Already gone.
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('auctions')) {
            return;
        }

        Schema::table('auctions', function (Blueprint $table) {
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
