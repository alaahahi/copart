<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bind «دفعات الزبائن» to a vault (default = mainBox / الصندوق).
 * Dual MySQL + SQLite safe (column guards).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_config') && ! Schema::hasColumn('system_config', 'default_receipts_vault_id')) {
            Schema::table('system_config', function (Blueprint $table) {
                $table->unsignedBigInteger('default_receipts_vault_id')->nullable()->index();
            });
        }

        // Ensure mainBox vaults are visible in accounting shortcuts.
        if (Schema::hasTable('vaults')) {
            DB::table('vaults')
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->where('code', 'mainBox')
                        ->orWhere('type', 'cash');
                })
                ->update([
                    'show_in_accounting' => true,
                    'is_active' => true,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('system_config') && Schema::hasColumn('system_config', 'default_receipts_vault_id')) {
            Schema::table('system_config', function (Blueprint $table) {
                $table->dropColumn('default_receipts_vault_id');
            });
        }
    }
};
