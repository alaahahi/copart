<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional `shipping_route_id` (FK -> shipping_routes.id) to `car`
 * so a car can record its shipping route/method (طريق الشحن). Nullable and
 * ON DELETE SET NULL so existing cars are never broken and deleting a route
 * from settings never destroys car history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('car')
            && Schema::hasTable('shipping_routes')
            && !Schema::hasColumn('car', 'shipping_route_id')
        ) {
            Schema::table('car', function (Blueprint $table) {
                $table->unsignedBigInteger('shipping_route_id')->nullable();
                $table->index('shipping_route_id');
                $table->foreign('shipping_route_id')
                    ->references('id')
                    ->on('shipping_routes')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('car') && Schema::hasColumn('car', 'shipping_route_id')) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                return;
            }

            Schema::table('car', function (Blueprint $table) {
                $table->dropForeign(['shipping_route_id']);
                $table->dropColumn('shipping_route_id');
            });
        }
    }
};
