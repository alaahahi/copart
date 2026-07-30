<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair: production may have marked 2026_07_30_040100 as ran while the
 * column was skipped (guard required shipping_routes, or FK failed on SQLite).
 * This migration is idempotent and only ensures car.shipping_route_id exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('car')) {
            return;
        }

        // Ensure lookup table exists first (in case 040000 was skipped).
        if (! Schema::hasTable('shipping_routes')) {
            Schema::create('shipping_routes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('owner_id');
                $table->string('name', 255);
                $table->timestamps();
                $table->index('owner_id');
                $table->unique(['owner_id', 'name']);
            });
        }

        if (Schema::hasColumn('car', 'shipping_route_id')) {
            return;
        }

        // Step 1: column + index only (no FK) — safest on SQLite.
        Schema::table('car', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_route_id')->nullable()->after('auction_id');
        });

        // `after()` is ignored on SQLite; if column still missing, force raw.
        if (! Schema::hasColumn('car', 'shipping_route_id')) {
            DB::statement('ALTER TABLE car ADD COLUMN shipping_route_id INTEGER NULL');
        }

        if (Schema::hasColumn('car', 'shipping_route_id')) {
            try {
                Schema::table('car', function (Blueprint $table) {
                    $table->index('shipping_route_id');
                });
            } catch (\Throwable $e) {
                // Index may already exist.
            }
        }

        // Step 2: FK best-effort (skip on SQLite — Laravel often recreates tables).
        if (
            Schema::hasColumn('car', 'shipping_route_id')
            && Schema::getConnection()->getDriverName() !== 'sqlite'
        ) {
            try {
                Schema::table('car', function (Blueprint $table) {
                    $table->foreign('shipping_route_id')
                        ->references('id')
                        ->on('shipping_routes')
                        ->onDelete('set null');
                });
            } catch (\Throwable $e) {
                // Column is enough for app writes; FK can be added manually later.
            }
        }
    }

    public function down(): void
    {
        // Do not drop — this is a repair migration.
    }
};
