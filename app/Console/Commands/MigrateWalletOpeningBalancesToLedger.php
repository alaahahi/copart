<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Deprecated: opening balances should be posted via ledger tools, not wallets table.
 */
class MigrateWalletOpeningBalancesToLedger extends Command
{
    protected $signature = 'ledger:migrate-opening-balances {--owner= : Owner id only} {--dry-run : Show without posting}';

    protected $description = '[Deprecated] No-op — wallets table removed; opening balances belong in the ledger';

    public function handle(): int
    {
        $this->warn('ledger:migrate-opening-balances is a no-op. Use chart/ledger opening entries instead of wallets.');

        return self::SUCCESS;
    }
}
