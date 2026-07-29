<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reference-only JSON trail of which payments allocated to each car.
 * Accounting journals remain the source of truth for review.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('car')) {
            return;
        }

        Schema::table('car', function (Blueprint $table) {
            if (! Schema::hasColumn('car', 'payment_allocations')) {
                $table->json('payment_allocations')->nullable()->after('paid');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('car') || ! Schema::hasColumn('car', 'payment_allocations')) {
            return;
        }

        Schema::table('car', function (Blueprint $table) {
            $table->dropColumn('payment_allocations');
        });
    }
};
