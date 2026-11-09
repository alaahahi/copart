<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a company-treasury row to the cash-box transaction it was copied from.
 * Soft-deleted copies free the link so the accounting page can export again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('company_treasury_entries')) {
            return;
        }

        if (! Schema::hasColumn('company_treasury_entries', 'source_transaction_id')) {
            Schema::table('company_treasury_entries', function (Blueprint $table) {
                $table->unsignedBigInteger('source_transaction_id')->nullable()->after('journal_entry_id');
                $table->index('source_transaction_id', 'cte_source_transaction_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('company_treasury_entries')) {
            return;
        }

        if (Schema::hasColumn('company_treasury_entries', 'source_transaction_id')) {
            Schema::table('company_treasury_entries', function (Blueprint $table) {
                $table->dropIndex('cte_source_transaction_id_idx');
                $table->dropColumn('source_transaction_id');
            });
        }
    }
};
