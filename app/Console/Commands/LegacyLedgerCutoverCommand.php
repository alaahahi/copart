<?php

namespace App\Console\Commands;

use App\Services\AccountingIntegrityService;
use App\Services\LegacyLedgerCutoverService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LegacyLedgerCutoverCommand extends Command
{
    protected $signature = 'ledger:cutover-from-legacy
                            {--owner= : Limit to one owner_id}
                            {--dry-run : Preview without posting (default if --execute missing)}
                            {--execute : Post opening journals}
                            {--report= : Optional path to write JSON comparison report}';

    protected $description = 'Provision client COA accounts and post opening balances from legacy wallets + car remainders';

    public function handle(LegacyLedgerCutoverService $cutover, AccountingIntegrityService $integrity): int
    {
        $owner = $this->option('owner');
        $ownerId = $owner !== null && $owner !== '' ? (int) $owner : null;
        $dryRun = ! $this->option('execute');

        if ($dryRun) {
            $this->warn('Dry-run mode — pass --execute to post. No journals will be written.');
        } else {
            $this->warn('EXECUTE mode — posting legacy_cutover opening balances.');
        }

        $result = $cutover->run($ownerId, $dryRun);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Owners', implode(', ', $result['owners'])],
                ['Clients provisioned', $result['clients_provisioned']],
                ['Vaults synced (or counted)', $result['vaults_synced']],
                [$dryRun ? 'Would post' : 'Posted', $result['posted']],
                ['Skipped (already posted)', $result['skipped']],
                ['Warnings', count($result['warnings'])],
            ]
        );

        foreach (array_slice($result['warnings'], 0, 40) as $w) {
            $this->warn('  · '.$w);
        }
        if (count($result['warnings']) > 40) {
            $this->line('  … '.(count($result['warnings']) - 40).' more warnings');
        }

        $clients = collect($result['comparisons'])->where('kind', 'client');
        $nonzero = $clients->filter(fn ($c) => abs((float) $c['car_remaining']) >= 0.01
            || abs((float) $c['wallet_usd']) >= 0.01
            || abs((float) $c['wallet_iqd']) >= 0.01
        )->take(30);

        if ($nonzero->isNotEmpty()) {
            $this->info('Sample client comparisons (non-zero, max 30):');
            $this->table(
                ['ID', 'Name', 'Car rem', 'Wallet $', 'Wallet IQD', 'AR', 'Qasa $', 'Qasa IQD'],
                $nonzero->map(fn ($c) => [
                    $c['user_id'],
                    mb_substr((string) $c['name'], 0, 24),
                    $c['car_remaining'],
                    $c['wallet_usd'],
                    $c['wallet_iqd'],
                    $c['ledger_ar'] ?? '—',
                    $c['ledger_qasa_usd'] ?? '—',
                    $c['ledger_qasa_iqd'] ?? '—',
                ])->all()
            );
        }

        $cash = collect($result['comparisons'])->where('kind', 'cash')
            ->filter(fn ($c) => abs((float) $c['wallet_usd']) >= 0.01 || abs((float) $c['wallet_iqd']) >= 0.01)
            ->take(20);
        if ($cash->isNotEmpty()) {
            $this->info('Cash/vault wallets (non-zero):');
            $this->table(
                ['ID', 'Name', 'Wallet $', 'Wallet IQD'],
                $cash->map(fn ($c) => [
                    $c['user_id'],
                    mb_substr((string) $c['name'], 0, 28),
                    $c['wallet_usd'],
                    $c['wallet_iqd'],
                ])->all()
            );
        }

        if (! $dryRun) {
            $this->info('Trial balance (sum of all journal lines):');
            $tb = $result['trial_balance'] ?: $cutover->trialBalanceSummary($ownerId);
            $this->table(
                ['Currency', 'Debit', 'Credit', 'Diff'],
                collect($tb)->map(fn ($r) => [
                    $r['currency'],
                    $r['debit'],
                    $r['credit'],
                    round($r['debit'] - $r['credit'], 2),
                ])->all()
            );

            $check = $integrity->check($ownerId, false);
            if ($check['ok']) {
                $this->info('accounting:integrity PASS');
            } else {
                $this->error('accounting:integrity FAIL — unbalanced='.count($check['unbalanced_entries']));
            }
        }

        $reportPath = $this->option('report');
        if ($reportPath) {
            $dir = dirname($reportPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            File::put($reportPath, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $this->info("Report written: {$reportPath}");
        }

        return self::SUCCESS;
    }
}
