<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ledger_accounts')) {
            return;
        }

        if (! Schema::hasColumn('ledger_accounts', 'show_in_accounting')) {
            Schema::table('ledger_accounts', function (Blueprint $table) {
                // Match vaults: default true so existing / new COA accounts appear as Accounting chips.
                $table->boolean('show_in_accounting')->default(true)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('ledger_accounts')) {
            return;
        }

        if (Schema::hasColumn('ledger_accounts', 'show_in_accounting')) {
            Schema::table('ledger_accounts', function (Blueprint $table) {
                $table->dropColumn('show_in_accounting');
            });
        }
    }
};
