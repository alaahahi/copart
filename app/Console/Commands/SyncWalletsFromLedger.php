<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserType;
use App\Models\Wallet;
use App\Services\LedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncWalletsFromLedger extends Command
{
    protected $signature = 'ledger:sync-wallets {--owner= : Owner id only} {--dry-run : Show diffs without updating}';

    protected $description = 'Overwrite wallets.balance / balance_dinar from ledger AR accounts';

    public function handle(LedgerService $ledger): int
    {
        if (!Schema::hasTable('ledger_accounts') || !Schema::hasTable('wallets')) {
            $this->error('Required tables missing. Run migrations first.');

            return self::FAILURE;
        }

        $ownerFilter = $this->option('owner');
        $dryRun = (bool) $this->option('dry-run');
        $clientTypeId = UserType::where('name', 'client')->value('id');

        $query = User::query()
            ->where('type_id', $clientTypeId)
            ->when($ownerFilter, fn ($q) => $q->where('owner_id', (int) $ownerFilter));

        $updated = 0;
        $mismatched = 0;

        $query->chunkById(200, function ($users) use ($ledger, $dryRun, &$updated, &$mismatched) {
            foreach ($users as $user) {
                $ownerId = (int) $user->owner_id;
                $usd = $ledger->clientBalance($ownerId, (int) $user->id, '$');
                $iqd = $ledger->clientBalance($ownerId, (int) $user->id, 'IQD');
                $wallet = Wallet::where('user_id', $user->id)->first();

                if (!$wallet) {
                    continue;
                }

                $diffUsd = round((float) $wallet->balance - $usd, 2);
                $diffIqd = round((float) $wallet->balance_dinar - $iqd, 2);

                if ($diffUsd != 0.0 || $diffIqd != 0.0) {
                    $mismatched++;
                    $this->warn("User #{$user->id} {$user->name}: wallet USD {$wallet->balance} → ledger {$usd} | IQD {$wallet->balance_dinar} → {$iqd}");
                }

                if ($dryRun) {
                    continue;
                }

                $ledger->syncWalletFromLedger($ownerId, (int) $user->id);
                $updated++;
            }
        });

        $this->info($dryRun
            ? "Dry-run done. Mismatches: {$mismatched}"
            : "Synced {$updated} wallets. Mismatches before sync: {$mismatched}");

        return self::SUCCESS;
    }
}
