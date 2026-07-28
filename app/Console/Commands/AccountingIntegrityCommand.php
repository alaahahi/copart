<?php

namespace App\Console\Commands;

use App\Services\AccountingIntegrityService;
use Illuminate\Console\Command;

class AccountingIntegrityCommand extends Command
{
    protected $signature = 'accounting:integrity
                            {--owner= : Limit checks to a single owner_id}
                            {--skip-wallets : Skip wallet vs ledger spot-check}
                            {--json : Print machine-readable JSON}';

    protected $description = 'Verify double-entry integrity (debit=credit per journal/currency, orphans, empty entries)';

    public function handle(AccountingIntegrityService $checker): int
    {
        $owner = $this->option('owner') !== null && $this->option('owner') !== ''
            ? (int) $this->option('owner')
            : null;
        $checkWallets = !(bool) $this->option('skip-wallets');

        $result = $checker->check($owner, $checkWallets);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        }

        if ($result['tables_missing']) {
            $this->error('Required tables missing (journal_entries / journal_lines). Run migrations first.');

            return self::FAILURE;
        }

        $this->info('Accounting integrity scan');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Entries checked (non-deleted)', $result['entries_checked']],
                ['Lines checked', $result['lines_checked']],
                ['Unbalanced entries', count($result['unbalanced_entries'])],
                ['Empty entries', count($result['empty_entries'])],
                ['Orphan lines', $result['orphan_lines']],
                ['Lines → missing accounts', $result['missing_accounts']],
                ['Lines on soft-deleted entries', $result['lines_on_deleted_entries']],
                ['Wallet ≠ ledger (info)', count($result['wallet_mismatches'])],
            ]
        );

        if (!empty($result['unbalanced_entries'])) {
            $this->error('Unbalanced journals (debit ≠ credit per currency):');
            $this->table(
                ['ID', 'Voucher', 'Currency', 'Debit', 'Credit'],
                collect($result['unbalanced_entries'])->map(fn ($r) => [
                    $r['id'], $r['voucher_no'], $r['currency'], $r['debit'], $r['credit'],
                ])->all()
            );
        }

        if (!empty($result['empty_entries'])) {
            $this->warn('Journal entries with zero lines:');
            foreach ($result['empty_entries'] as $row) {
                $this->line("  #{$row['id']} {$row['voucher_no']}");
            }
        }

        if ($result['orphan_lines'] > 0) {
            $this->error("Orphan journal_lines (no entry): {$result['orphan_lines']}");
        }

        if ($result['missing_accounts'] > 0) {
            $this->error("journal_lines pointing at missing ledger_accounts: {$result['missing_accounts']}");
        }

        if ($result['lines_on_deleted_entries'] > 0) {
            $this->comment("Note: {$result['lines_on_deleted_entries']} line(s) remain under soft-deleted entries (expected if soft-delete does not cascade).");
        }

        if (!empty($result['wallet_mismatches'])) {
            $this->warn('Wallet vs ledger AR mismatches (informational — fix with ledger:sync-wallets --dry-run):');
            $this->table(
                ['User', 'Name', 'Wallet $', 'Ledger $', 'Wallet IQD', 'Ledger IQD'],
                collect($result['wallet_mismatches'])->map(fn ($r) => [
                    $r['user_id'], $r['name'], $r['wallet_usd'], $r['ledger_usd'], $r['wallet_iqd'], $r['ledger_iqd'],
                ])->take(50)->all()
            );
            if (count($result['wallet_mismatches']) > 50) {
                $this->line('  … truncated (showing 50)');
            }
        }

        if ($result['ok']) {
            $this->info('PASS — all non-deleted journals balanced; no orphans / empty entries / missing accounts.');

            return self::SUCCESS;
        }

        $this->error('FAIL — accounting integrity issues found.');

        return self::FAILURE;
    }
}
