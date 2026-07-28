<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Models\UserType;
use App\Models\Wallet;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only double-entry integrity checks against journal_entries / journal_lines.
 */
class AccountingIntegrityService
{
    /**
     * @return array{
     *   ok: bool,
     *   tables_missing: bool,
     *   entries_checked: int,
     *   lines_checked: int,
     *   unbalanced_entries: list<array{id:int,voucher_no:?string,currency:string,debit:float,credit:float}>,
     *   empty_entries: list<array{id:int,voucher_no:?string}>,
     *   orphan_lines: int,
     *   missing_accounts: int,
     *   lines_on_deleted_entries: int,
     *   wallet_mismatches: list<array{user_id:int,name:?string,wallet_usd:float,ledger_usd:float,wallet_iqd:float,ledger_iqd:float}>,
     *   summary: array<string,int|bool>
     * }
     */
    public function check(?int $ownerId = null, bool $checkWallets = true): array
    {
        if (!Schema::hasTable('journal_entries') || !Schema::hasTable('journal_lines')) {
            return [
                'ok' => false,
                'tables_missing' => true,
                'entries_checked' => 0,
                'lines_checked' => 0,
                'unbalanced_entries' => [],
                'empty_entries' => [],
                'orphan_lines' => 0,
                'missing_accounts' => 0,
                'lines_on_deleted_entries' => 0,
                'wallet_mismatches' => [],
                'summary' => ['tables_missing' => true],
            ];
        }

        $unbalanced = [];
        $empty = [];

        $entriesQuery = JournalEntry::query()
            ->with('lines')
            ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId))
            ->orderBy('id');

        $entriesChecked = 0;
        $linesChecked = 0;

        $entriesQuery->chunkById(200, function ($entries) use (&$unbalanced, &$empty, &$entriesChecked, &$linesChecked) {
            foreach ($entries as $entry) {
                $entriesChecked++;
                $lines = $entry->lines;
                $linesChecked += $lines->count();

                if ($lines->isEmpty()) {
                    $empty[] = [
                        'id' => (int) $entry->id,
                        'voucher_no' => $entry->voucher_no,
                    ];
                    continue;
                }

                $byCurrency = [];
                foreach ($lines as $line) {
                    $currency = (string) ($line->currency ?: '$');
                    if (!isset($byCurrency[$currency])) {
                        $byCurrency[$currency] = ['debit' => 0.0, 'credit' => 0.0];
                    }
                    $byCurrency[$currency]['debit'] += (float) $line->debit;
                    $byCurrency[$currency]['credit'] += (float) $line->credit;
                }

                foreach ($byCurrency as $currency => $totals) {
                    $debit = round($totals['debit'], 2);
                    $credit = round($totals['credit'], 2);
                    if ($debit !== $credit) {
                        $unbalanced[] = [
                            'id' => (int) $entry->id,
                            'voucher_no' => $entry->voucher_no,
                            'currency' => $currency,
                            'debit' => $debit,
                            'credit' => $credit,
                        ];
                    }
                }
            }
        });

        // Orphans have no parent entry; owner filter cannot apply.
        $orphanLines = JournalLine::query()
            ->whereNotIn('journal_entry_id', JournalEntry::withTrashed()->select('id'))
            ->count();

        $missingAccounts = 0;
        if (Schema::hasTable('ledger_accounts')) {
            $missingAccounts = JournalLine::query()
                ->whereNotIn('ledger_account_id', LedgerAccount::query()->select('id'))
                ->count();
        }

        $linesOnDeleted = 0;
        if (Schema::hasColumn('journal_entries', 'deleted_at')) {
            $linesOnDeleted = JournalLine::query()
                ->whereIn('journal_entry_id', JournalEntry::onlyTrashed()->select('id'))
                ->count();
        }

        $walletMismatches = [];
        if ($checkWallets && Schema::hasTable('wallets') && Schema::hasTable('ledger_accounts')) {
            $walletMismatches = $this->walletSpotCheck($ownerId);
        }

        $ok = empty($unbalanced)
            && empty($empty)
            && $orphanLines === 0
            && $missingAccounts === 0;

        return [
            'ok' => $ok,
            'tables_missing' => false,
            'entries_checked' => $entriesChecked,
            'lines_checked' => $linesChecked,
            'unbalanced_entries' => $unbalanced,
            'empty_entries' => $empty,
            'orphan_lines' => $orphanLines,
            'missing_accounts' => $missingAccounts,
            'lines_on_deleted_entries' => $linesOnDeleted,
            'wallet_mismatches' => $walletMismatches,
            'summary' => [
                'entries_checked' => $entriesChecked,
                'lines_checked' => $linesChecked,
                'unbalanced_count' => count($unbalanced),
                'empty_count' => count($empty),
                'orphan_lines' => $orphanLines,
                'missing_accounts' => $missingAccounts,
                'lines_on_deleted_entries' => $linesOnDeleted,
                'wallet_mismatch_count' => count($walletMismatches),
            ],
        ];
    }

    /**
     * Spot-check client wallets vs AR ledger (informational; not part of ok/fail).
     *
     * @return list<array{user_id:int,name:?string,wallet_usd:float,ledger_usd:float,wallet_iqd:float,ledger_iqd:float}>
     */
    protected function walletSpotCheck(?int $ownerId): array
    {
        $ledger = app(LedgerService::class);
        $clientTypeId = UserType::where('name', 'client')->value('id');
        if (!$clientTypeId) {
            return [];
        }

        $mismatches = [];
        User::query()
            ->where('type_id', $clientTypeId)
            ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId))
            ->with('wallet')
            ->chunkById(200, function ($users) use ($ledger, &$mismatches) {
                foreach ($users as $user) {
                    /** @var Wallet|null $wallet */
                    $wallet = $user->wallet ?? Wallet::where('user_id', $user->id)->first();
                    if (!$wallet) {
                        continue;
                    }
                    $owner = (int) $user->owner_id;
                    $usd = $ledger->clientBalance($owner, (int) $user->id, '$');
                    $iqd = $ledger->clientBalance($owner, (int) $user->id, 'IQD');
                    $wUsd = round((float) $wallet->balance, 2);
                    $wIqd = round((float) $wallet->balance_dinar, 2);
                    if (round($wUsd - $usd, 2) != 0.0 || round($wIqd - $iqd, 2) != 0.0) {
                        $mismatches[] = [
                            'user_id' => (int) $user->id,
                            'name' => $user->name,
                            'wallet_usd' => $wUsd,
                            'ledger_usd' => round($usd, 2),
                            'wallet_iqd' => $wIqd,
                            'ledger_iqd' => round($iqd, 2),
                        ];
                    }
                }
            });

        return $mismatches;
    }
}
