<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_config')) {
            return;
        }

        Schema::table('system_config', function (Blueprint $table) {
            if (! Schema::hasColumn('system_config', 'dash_show_clock')) {
                $table->boolean('dash_show_clock')->default(true);
            }
            if (! Schema::hasColumn('system_config', 'dash_show_exchange')) {
                $table->boolean('dash_show_exchange')->default(true);
            }
            if (! Schema::hasColumn('system_config', 'dash_show_today_cash')) {
                $table->boolean('dash_show_today_cash')->default(true);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_config')) {
            return;
        }

        Schema::table('system_config', function (Blueprint $table) {
            foreach (['dash_show_clock', 'dash_show_exchange', 'dash_show_today_cash'] as $col) {
                if (Schema::hasColumn('system_config', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
