<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Wallet;
use App\Services\LedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class MigrateWalletOpeningBalancesToLedger extends Command
{
    protected $signature = 'ledger:migrate-opening-balances {--owner= : Owner id only} {--dry-run : Show without posting}';

    protected $description = 'Create opening journal entries from current wallets.balance / balance_dinar';

    public function handle(LedgerService $ledger): int
    {
        if (!Schema::hasTable('ledger_accounts') || !Schema::hasTable('wallets')) {
            $this->error('Required tables missing. Run migrations first.');

            return self::failure;
        }

        $ownerFilter = $this->option('owner');
        $dryRun = (bool) $this->option('dry-run');

        $wallets = Wallet::query()
            ->with('user')
            ->where(function ($q) {
                $q->where('balance', '!=', 0)->orWhere('balance_dinar', '!=', 0);
            })
            ->get();

        $posted = 0;
        foreach ($wallets as $wallet) {
            $user = $wallet->user;
            if (!$user || !$user->owner_id) {
                continue;
            }
            $ownerId = (int) $user->owner_id;
            if ($ownerFilter && (int) $ownerFilter !== $ownerId) {
                continue;
            }

            if ($wallet->ledger_synced_at) {
                $this->line("Skip wallet #{$wallet->id} (already synced)");
                continue;
            }

            $ledger->ensureSystemAccounts($ownerId);
            $opening = $ledger->systemAccount($ownerId, LedgerService::CODE_OPENING);
            $ar = $ledger->clientReceivableAccount($ownerId, (int) $user->id);

            foreach (['$' => (float) $wallet->balance, 'IQD' => (float) $wallet->balance_dinar] as $currency => $amount) {
                if (round($amount, 2) == 0.0) {
                    continue;
                }

                $this->info("Owner {$ownerId} / User {$user->id} / {$currency} = {$amount}");

                if ($dryRun) {
                    continue;
                }

                // Positive wallet balance = client owes company = Debit AR / Credit Opening
                // Negative = reverse
                $abs = abs($amount);
                $lines = $amount > 0
                    ? [
                        ['account_id' => $ar->id, 'debit' => $abs, 'credit' => 0, 'currency' => $currency],
                        ['account_id' => $opening->id, 'debit' => 0, 'credit' => $abs, 'currency' => $currency],
                    ]
                    : [
                        ['account_id' => $opening->id, 'debit' => $abs, 'credit' => 0, 'currency' => $currency],
                        ['account_id' => $ar->id, 'debit' => 0, 'credit' => $abs, 'currency' => $currency],
                    ];

                $ledger->post([
                    'owner_id' => $ownerId,
                    'entry_date' => now()->toDateString(),
                    'memo' => 'قيد افتتاحي من رصيد المحفظة',
                    'source' => 'opening',
                    'currency' => $currency,
                    'reference_type' => Wallet::class,
                    'reference_id' => $wallet->id,
                ], $lines);

                $posted++;
            }

            if (!$dryRun) {
                $wallet->forceFill(['ledger_synced_at' => now()])->save();
            }
        }

        $this->info($dryRun ? 'Dry-run complete.' : "Posted {$posted} opening currency lines.");

        return self::SUCCESS;
    }
}
