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
            if (! Schema::hasColumn('system_config', 'app_logo')) {
                $table->string('app_logo', 500)->nullable();
            }
            if (! Schema::hasColumn('system_config', 'app_cover')) {
                $table->string('app_cover', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_config')) {
            return;
        }

        Schema::table('system_config', function (Blueprint $table) {
            foreach (['app_logo', 'app_cover'] as $col) {
                if (Schema::hasColumn('system_config', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
