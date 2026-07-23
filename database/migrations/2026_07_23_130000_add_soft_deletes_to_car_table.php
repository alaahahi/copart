<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enables Soft Deletes on the `car` table so deleting a car never destroys
 * its row/history — it only stamps `deleted_at`.
 *
 * Guarded with hasTable/hasColumn checks because this project manages the
 * `car` table's base schema outside of migrations (see
 * add_erbil_transfer_fields_to_car_table.php and the DelCar controller,
 * which already references a `deleted_at` column). This migration is a
 * safe no-op wherever the column already exists or the table isn't present.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('car') && !Schema::hasColumn('car', 'deleted_at')) {
            Schema::table('car', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('car') && Schema::hasColumn('car', 'deleted_at')) {
            Schema::table('car', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
