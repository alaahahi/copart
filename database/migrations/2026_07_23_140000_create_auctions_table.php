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
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->string('name', 255);
            $table->timestamps();

            $table->index('owner_id');
            $table->unique(['owner_id', 'name']);
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};
