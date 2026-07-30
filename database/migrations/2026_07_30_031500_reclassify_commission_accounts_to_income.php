<?php

use App\Services\LedgerService;
use Illuminate\Database\Migrations\Migration;

/**
 * Commission / عمولة COA accounts are revenue (income), not expense.
 * Reclassify by name/code pattern and parent under system 4100.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(LedgerService::class)->reclassifyCommissionAccountsToIncome();
    }

    public function down(): void
    {
        // Irreversible intentionally — prior type may have been wrong; no safe restore.
    }
};
