<?php

namespace App\Console\Commands;

use App\Services\LedgerService;
use Illuminate\Console\Command;

/**
 * Removes buggy «نقل السيارة» journals that wrongly Debited Cash / Credited Revenue,
 * then optionally re-posts pure AR↔AR transfers.
 */
class RepairBadCarTransfersCommand extends Command
{
    protected $signature = 'ledger:repair-bad-car-transfers
                            {--owner= : Limit to one owner_id}
                            {--dry-run : Preview only (default)}
                            {--execute : Void bad journals and sync wallets}
                            {--no-repost : Void only — do not recreate AR transfers}';

    protected $description = 'Delete/void fake car-transfer cash+revenue journals; optionally repost AR↔AR';

    public function handle(LedgerService $ledger): int
    {
        $owner = $this->option('owner');
        $ownerId = $owner !== null && $owner !== '' ? (int) $owner : null;
        $dryRun = ! $this->option('execute');
        $repost = ! $this->option('no-repost');

        if ($dryRun) {
            $this->warn('Dry-run — pass --execute to void journals and fix الصندوق.');
        }

        $result = $ledger->repairBadCarClientTransfers($ownerId, $dryRun, $repost);

        $cash = collect($result['details']['cash_hits'] ?? []);
        $rev = collect($result['details']['revenue_hits'] ?? []);
        $pairs = collect($result['details']['pairs'] ?? []);

        $this->info('Fake cash journals: '.$cash->count());
        $this->table(
            ['journal', 'voucher', 'date', 'amount', 'from_client'],
            $cash->map(fn ($r) => [$r['journal_id'], $r['voucher'], $r['entry_date'], $r['amount'], $r['client_id']])
        );

        $this->info('Fake revenue journals: '.$rev->count());
        $this->table(
            ['journal', 'voucher', 'date', 'amount', 'to_client'],
            $rev->map(fn ($r) => [$r['journal_id'], $r['voucher'], $r['entry_date'], $r['amount'], $r['client_id']])
        );

        $this->info('Paired AR transfers to repost: '.$pairs->count());
        $this->table(
            ['from', 'to', 'amount', 'cash_jv', 'revenue_jv'],
            $pairs->map(fn ($p) => [
                $p['from_client_id'],
                $p['to_client_id'],
                $p['amount'],
                $p['cash_journal_id'],
                $p['revenue_journal_id'],
            ])
        );

        if (! $dryRun) {
            $this->info('Voided: '.$result['voided'].' | Reposted AR transfers: '.$result['reposted']);
            $this->info('Synced wallets: '.implode(', ', $result['synced_users'] ?? []));
        } else {
            $would = $result['would_void'] ?? [];
            $this->comment('Would void journal ids: '.implode(', ', $would));
        }

        return self::SUCCESS;
    }
}
