<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_config')) {
            return;
        }

        Schema::table('system_config', function (Blueprint $table) {
            foreach ([
                'receipt_logo_left_1',
                'receipt_logo_left_2',
                'receipt_logo_left_3',
                'receipt_logo_haulf',
                'receipt_logo_main',
            ] as $col) {
                if (!Schema::hasColumn('system_config', $col)) {
                    $table->string($col, 500)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('system_config')) {
            return;
        }

        Schema::table('system_config', function (Blueprint $table) {
            foreach ([
                'receipt_logo_left_1',
                'receipt_logo_left_2',
                'receipt_logo_left_3',
                'receipt_logo_haulf',
                'receipt_logo_main',
            ] as $col) {
                if (Schema::hasColumn('system_config', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
