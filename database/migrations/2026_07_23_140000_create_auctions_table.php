<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-scoped list of auction houses (Copart, IAAI, Manheim, ...), managed
 * from Settings ("المزادات") and used to populate the المزاد select on the
 * car add/edit forms. Mirrors the payment_tags table pattern already used
 * for wallet tags.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('auctions')) {
            return;
        }

        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->string('name', 255);
            $table->timestamps();

            // No FK on owner_id — same as payment_tags. Tenant owner_id may
            // not exist as a users.id row (legacy multi-tenant convention).
            $table->index('owner_id');
            $table->unique(['owner_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
