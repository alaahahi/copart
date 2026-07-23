<?php

namespace App\Console\Commands;

use App\Models\JournalEntry;
use App\Models\Transactions;
use App\Services\LedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Audit / cleanup tool for the "رأس المال" (capital) confusion reported on the
 * purchases dashboard.
 *
 * Context (see resources/js/Pages/purchases.vue + app/Http/Controllers/DashboardController.php):
 * The "رأس المال" KPI shown on the purchases (and sales) page was NOT reading a
 * real capital / opening-equity ledger balance. It was a derived value:
 *
 *     mainAccount = SUM(car.total) - (SUM(car.paid) + SUM(car.discount))
 *
 * computed over ALL cars for the owner. There is no wallet, transaction type,
 * or journal entry actually tagged as "capital" anywhere in this codebase, so
 * the negative number (-44,550) was a symptom of that formula (purchase totals
 * vs. payments/discounts across the whole cars table), not of real "capital"
 * transactions. The UI card has been removed (see purchases.vue / Sales.vue),
 * matching Dashboard.vue where it was already intentionally omitted.
 *
 * This command exists as a SAFETY NET in case the real production database
 * (different from any local/sandbox DB) does contain rows that were actually
 * tagged/labelled as capital or opening-equity (e.g. type/tag = 'capital',
 * description mentioning "رأس المال", or an opening-equity ledger account with
 * a non-zero balance). It NEVER touches car/client/sale transactions - it only
 * looks at rows that explicitly reference capital/opening-equity.
 *
 * Usage:
 *   php artisan capital:cleanup                 # dry run, just reports what it finds
 *   php artisan capital:cleanup --purge --force  # soft-deletes + voids journals for matches
 */
class CleanupCapitalTransactions extends Command
{
    protected $signature = 'capital:cleanup {--owner= : Limit to a single owner_id} {--purge : Actually soft-delete/void matches (default is dry-run/report only)} {--force : Required together with --purge to actually execute}';

    protected $description = 'Audit and (optionally) soft-delete/void transactions & journal entries tagged as capital / opening-equity';

    public function handle(LedgerService $ledger): int
    {
        $ownerId = $this->option('owner');
        $purge = (bool) $this->option('purge');
        $force = (bool) $this->option('force');

        $this->info('=== Capital / رأس المال transaction audit ===');

        if (!Schema::hasTable('transactions')) {
            $this->error('Table "transactions" does not exist on the DB configured in .env (DB_DATABASE=' . config('database.connections.mysql.database') . '). This is not the real application database - point .env at the production DB before running this command.');

            return self::FAILURE;
        }

        $txQuery = Transactions::query()
            ->where(function ($q) {
                $q->where('type', 'capital')
                    ->orWhere('tag', 'capital')
                    ->orWhere('type', 'opening')
                    ->orWhere('tag', 'opening')
                    ->orWhere('description', 'like', '%رأس المال%')
                    ->orWhere('description', 'like', '%راس المال%')
                    ->orWhere('description', 'like', '%capital%');
            });

        if ($ownerId) {
            $txQuery->whereHas('wallet.user', function ($q) use ($ownerId) {
                $q->where('owner_id', $ownerId);
            });
        }

        $transactions = $txQuery->get();

        $this->line('Matching wallet transactions found: ' . $transactions->count());
        foreach ($transactions as $t) {
            $this->line("  #{$t->id} wallet_id={$t->wallet_id} type={$t->type} tag={$t->tag} amount={$t->amount} {$t->currency} desc=\"{$t->description}\"");
        }

        // Real double-entry opening-equity ledger account (code 3900). This is
        // legitimate accounting infrastructure (LedgerService::CODE_OPENING),
        // not the buggy KPI. We only report its balance here for visibility -
        // we do NOT delete/void it automatically, since wiping it would break
        // the trial balance for the real opening-balance migration. Flag it
        // for manual review only if it looks wrong.
        if (Schema::hasTable('ledger_accounts')) {
            $openingAccounts = \App\Models\LedgerAccount::query()
                ->where('code', LedgerService::CODE_OPENING)
                ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId))
                ->get();

            $this->info('Opening-equity ledger accounts (code 3900) - informational only, NOT auto-modified:');
            foreach ($openingAccounts as $acc) {
                $this->line("  owner_id={$acc->owner_id} name_ar={$acc->name_ar} balance={$acc->balance()}");
            }
        }

        if ($transactions->isEmpty()) {
            $this->info('No capital-tagged transactions found. The negative "رأس المال" figure was a derived-formula bug on the purchases/sales KPI card, not real capital transactions to delete. No data was changed.');

            return self::SUCCESS;
        }

        if (!$purge) {
            $this->warn('Dry-run only. Re-run with --purge --force to soft-delete these rows and void their linked journal entries.');

            return self::SUCCESS;
        }

        if (!$force) {
            $this->error('--purge requires --force to confirm this is intentional.');

            return self::FAILURE;
        }

        $deletedIds = [];

        DB::transaction(function () use ($transactions, $ledger, &$deletedIds) {
            foreach ($transactions as $t) {
                $ledger->voidJournalForTransaction($t, 'Capital cleanup: dashboard KPI reported wrong balance');
                $t->delete(); // soft delete (Transactions uses SoftDeletes)
                $deletedIds[] = $t->id;
            }
        });

        Log::info('Capital transactions cleanup executed', [
            'deleted_transaction_ids' => $deletedIds,
            'by' => auth()->id(),
            'owner_filter' => $ownerId,
        ]);

        $this->info('Soft-deleted + voided journals for transaction ids: ' . implode(', ', $deletedIds));

        return self::SUCCESS;
    }
}
