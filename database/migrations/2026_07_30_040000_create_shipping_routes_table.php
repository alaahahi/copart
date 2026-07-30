<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-scoped list of shipping routes/methods, managed from Settings
 * ("طرق الشحن") and used to populate the طريق الشحن select on car forms.
 * Mirrors the auctions table pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipping_routes')) {
            return;
        }

        Schema::create('shipping_routes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->string('name', 255);
            $table->timestamps();

            // No FK on owner_id — same as auctions / payment_tags. Tenant
            // owner_id may not exist as a users.id row (legacy multi-tenant).
            $table->index('owner_id');
            $table->unique(['owner_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_routes');
    }
};
