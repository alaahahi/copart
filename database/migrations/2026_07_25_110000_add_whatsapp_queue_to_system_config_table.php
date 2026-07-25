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
            if (! Schema::hasColumn('system_config', 'wa_enabled')) {
                $table->boolean('wa_enabled')->default(false);
            }
            if (! Schema::hasColumn('system_config', 'wa_base_host')) {
                $table->string('wa_base_host', 255)->default('https://wa.intellij-app.com');
            }
            if (! Schema::hasColumn('system_config', 'wa_tenant')) {
                $table->string('wa_tenant', 100)->nullable();
            }
            if (! Schema::hasColumn('system_config', 'wa_source')) {
                $table->string('wa_source', 32)->default('sales');
            }
            if (! Schema::hasColumn('system_config', 'wa_created_by')) {
                $table->string('wa_created_by', 100)->default('copart-erp');
            }
            if (! Schema::hasColumn('system_config', 'wa_notify_debt')) {
                $table->boolean('wa_notify_debt')->default(false);
            }
            if (! Schema::hasColumn('system_config', 'wa_notify_car_created')) {
                $table->boolean('wa_notify_car_created')->default(false);
            }
            if (! Schema::hasColumn('system_config', 'wa_notify_payment')) {
                $table->boolean('wa_notify_payment')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_config')) {
            return;
        }

        Schema::table('system_config', function (Blueprint $table) {
            foreach ([
                'wa_enabled',
                'wa_base_host',
                'wa_tenant',
                'wa_source',
                'wa_created_by',
                'wa_notify_debt',
                'wa_notify_car_created',
                'wa_notify_payment',
            ] as $col) {
                if (Schema::hasColumn('system_config', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
