<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('car')) {
            return;
        }

        Schema::table('car', function (Blueprint $table) {
            if (!Schema::hasColumn('car', 'erbil_clearance')) {
                $table->integer('erbil_clearance')->default(0);
            }
            if (!Schema::hasColumn('car', 'erbil_transfer')) {
                $table->integer('erbil_transfer')->default(0);
            }
            if (!Schema::hasColumn('car', 'erbil_border_repair')) {
                $table->integer('erbil_border_repair')->default(0);
            }
            if (!Schema::hasColumn('car', 'erbil_customs')) {
                $table->integer('erbil_customs')->default(0);
            }
            if (!Schema::hasColumn('car', 'erbil_clearance_s')) {
                $table->integer('erbil_clearance_s')->default(0);
            }
            if (!Schema::hasColumn('car', 'erbil_transfer_s')) {
                $table->integer('erbil_transfer_s')->default(0);
            }
            if (!Schema::hasColumn('car', 'erbil_border_repair_s')) {
                $table->integer('erbil_border_repair_s')->default(0);
            }
            if (!Schema::hasColumn('car', 'erbil_customs_s')) {
                $table->integer('erbil_customs_s')->default(0);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('car')) {
            return;
        }

        $cols = [
            'erbil_clearance',
            'erbil_transfer',
            'erbil_border_repair',
            'erbil_customs',
            'erbil_clearance_s',
            'erbil_transfer_s',
            'erbil_border_repair_s',
            'erbil_customs_s',
        ];

        Schema::table('car', function (Blueprint $table) use ($cols) {
            $drop = array_values(array_filter($cols, fn ($c) => Schema::hasColumn('car', $c)));
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
