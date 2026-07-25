<?php

namespace Database\Seeders;

use App\Services\LedgerService;
use App\Services\SystemWalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds foundational double-entry system accounts (idempotent).
 *
 * Codes align with LedgerService::systemAccountDefaults() — do not invent new codes here.
 * Also provisions legacy account wallet users (mainBox@account.com, …).
 * Runs after user types / admin so owner_id=1 exists.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('ledger_accounts')) {
            $this->command?->warn('ledger_accounts table missing — skip ChartOfAccountsSeeder.');

            return;
        }

        $ledger = app(LedgerService::class);
        $wallets = app(SystemWalletService::class);
        $ownerIds = $this->resolveOwnerIds();

        foreach ($ownerIds as $ownerId) {
            $ledger->seedSystemAccounts((int) $ownerId);
            // Seed all system vaults once (optional expense boxes included).
            $wallets->ensureForOwner((int) $ownerId, true);
            $this->command?->info("System chart + wallets seeded for owner_id={$ownerId}");
        }
    }

    /**
     * @return list<int>
     */
    protected function resolveOwnerIds(): array
    {
        $ids = [];

        if (Schema::hasTable('owner')) {
            $ids = DB::table('owner')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if ($ids === [] && Schema::hasTable('users')) {
            $ids = DB::table('users')
                ->whereNotNull('owner_id')
                ->distinct()
                ->pluck('owner_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        if ($ids === []) {
            $ids = [1];
        }

        return array_values(array_unique($ids));
    }
}
