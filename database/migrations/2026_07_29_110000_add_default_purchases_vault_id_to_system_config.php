<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bind «صرف تكلفة مشتريات السيارات» to a cash vault (default = mainBox / الصندوق).
 * Dual MySQL + SQLite safe (column guards). Mirrors default_receipts_vault_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_config') && ! Schema::hasColumn('system_config', 'default_purchases_vault_id')) {
            Schema::table('system_config', function (Blueprint $table) {
                $table->unsignedBigInteger('default_purchases_vault_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('system_config') && Schema::hasColumn('system_config', 'default_purchases_vault_id')) {
            Schema::table('system_config', function (Blueprint $table) {
                $table->dropColumn('default_purchases_vault_id');
            });
        }
    }
};
