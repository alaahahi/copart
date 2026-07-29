<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Deprecated: wallets.balance is no longer synced — ledger is the source of truth.
 */
class SyncWalletsFromLedger extends Command
{
    protected $signature = 'ledger:sync-wallets {--owner= : Owner id only} {--dry-run : Show diffs without updating}';

    protected $description = '[Deprecated] No-op — wallet balances removed; use ledger only';

    public function handle(): int
    {
        $this->warn('ledger:sync-wallets is a no-op. Balances are read from the chart of accounts / journal only.');

        return self::SUCCESS;
    }
}
