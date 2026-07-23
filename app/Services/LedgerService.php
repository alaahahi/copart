<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class LedgerService
{
    public const CODE_CASH_USD = '1100';
    public const CODE_CASH_IQD = '1110';
    public const CODE_TREASURY_USD = '1120';
    public const CODE_TREASURY_IQD = '1130';
    public const CODE_CLIENT_AR_PREFIX = '1200';
    public const CODE_REVENUE = '4100';
    public const CODE_EXPENSE = '5100';
    public const CODE_OPENING = '3900';

    /**
     * Ensure default chart of accounts exists for an owner.
     */
    public function ensureSystemAccounts(int $ownerId): void
    {
        $defaults = [
            ['code' => self::CODE_CASH_USD, 'name' => 'Cash USD', 'name_ar' => 'صندوق دولار', 'type' => 'asset', 'currency' => '$'],
            ['code' => self::CODE_CASH_IQD, 'name' => 'Cash IQD', 'name_ar' => 'صندوق دينار', 'type' => 'asset', 'currency' => 'IQD'],
            ['code' => self::CODE_TREASURY_USD, 'name' => 'Company Treasury USD', 'name_ar' => 'قاصة الشركة دولار', 'type' => 'asset', 'currency' => '$'],
            ['code' => self::CODE_TREASURY_IQD, 'name' => 'Company Treasury IQD', 'name_ar' => 'قاصة الشركة دينار', 'type' => 'asset', 'currency' => 'IQD'],
            ['code' => self::CODE_REVENUE, 'name' => 'Shipping Revenue', 'name_ar' => 'إيرادات الشحن', 'type' => 'income', 'currency' => null],
            ['code' => self::CODE_EXPENSE, 'name' => 'General Expenses', 'name_ar' => 'مصاريف عامة', 'type' => 'expense', 'currency' => null],
            ['code' => self::CODE_OPENING, 'name' => 'Opening Balances Equity', 'name_ar' => 'أرصدة افتتاحية', 'type' => 'equity', 'currency' => null],
        ];

        foreach ($defaults as $row) {
            LedgerAccount::firstOrCreate(
                ['owner_id' => $ownerId, 'code' => $row['code']],
                array_merge($row, [
                    'owner_id' => $ownerId,
                    'is_system' => true,
                    'is_active' => true,
                ])
            );
        }
    }

    public function cashAccount(int $ownerId, string $currency): LedgerAccount
    {
        $this->ensureSystemAccounts($ownerId);
        $code = $currency === 'IQD' ? self::CODE_CASH_IQD : self::CODE_CASH_USD;

        return LedgerAccount::where('owner_id', $ownerId)->where('code', $code)->firstOrFail();
    }

    public function treasuryAccount(int $ownerId, string $currency): LedgerAccount
    {
        $this->ensureSystemAccounts($ownerId);
        $code = $currency === 'IQD' ? self::CODE_TREASURY_IQD : self::CODE_TREASURY_USD;

        return LedgerAccount::where('owner_id', $ownerId)->where('code', $code)->firstOrFail();
    }

    public function clientReceivableAccount(int $ownerId, int $clientId): LedgerAccount
    {
        $this->ensureSystemAccounts($ownerId);
        $client = User::find($clientId);
        $code = self::CODE_CLIENT_AR_PREFIX . '-' . $clientId;

        return LedgerAccount::firstOrCreate(
            ['owner_id' => $ownerId, 'code' => $code],
            [
                'name' => 'AR Client #' . $clientId,
                'name_ar' => 'ذمم تاجر: ' . ($client?->name ?? $clientId),
                'type' => 'asset',
                'currency' => null,
                'party_type' => User::class,
                'party_id' => $clientId,
                'is_system' => false,
                'is_active' => true,
            ]
        );
    }

    public function systemAccount(int $ownerId, string $code): LedgerAccount
    {
        $this->ensureSystemAccounts($ownerId);

        return LedgerAccount::where('owner_id', $ownerId)->where('code', $code)->firstOrFail();
    }

    /**
     * Post a balanced double-entry journal.
     *
     * @param  array<int, array{account_id:int,debit?:float|int,credit?:float|int,currency?:string,memo?:string}>  $lines
     */
    public function post(array $payload, array $lines): JournalEntry
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('القيد يحتاج سطرين على الأقل (مدين ودائن).');
        }

        $totals = [];
        foreach ($lines as $i => $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            $currency = $line['currency'] ?? ($payload['currency'] ?? '$');

            if ($debit < 0 || $credit < 0) {
                throw new InvalidArgumentException('لا يسمح بمبالغ سالبة في القيود.');
            }
            if (($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
                throw new InvalidArgumentException("سطر القيد #{$i} يجب أن يكون إما مدين أو دائن.");
            }

            $totals[$currency] = ($totals[$currency] ?? ['debit' => 0, 'credit' => 0]);
            $totals[$currency]['debit'] += $debit;
            $totals[$currency]['credit'] += $credit;
        }

        foreach ($totals as $currency => $sum) {
            if (round($sum['debit'], 2) !== round($sum['credit'], 2)) {
                throw new RuntimeException("القيد غير متوازن للعملة {$currency}: مدين {$sum['debit']} ≠ دائن {$sum['credit']}");
            }
        }

        return DB::transaction(function () use ($payload, $lines) {
            $ownerId = (int) $payload['owner_id'];
            $entry = JournalEntry::create([
                'owner_id' => $ownerId,
                'voucher_no' => $payload['voucher_no'] ?? $this->nextVoucherNo($ownerId),
                'entry_date' => $payload['entry_date'] ?? now()->toDateString(),
                'memo' => $payload['memo'] ?? null,
                'source' => $payload['source'] ?? 'manual',
                'reference_type' => $payload['reference_type'] ?? null,
                'reference_id' => $payload['reference_id'] ?? null,
                'created_by' => $payload['created_by'] ?? Auth::id(),
                'currency' => $payload['currency'] ?? null,
            ]);

            foreach (array_values($lines) as $index => $line) {
                $entry->lines()->create([
                    'ledger_account_id' => (int) $line['account_id'],
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                    'currency' => $line['currency'] ?? ($payload['currency'] ?? '$'),
                    'memo' => $line['memo'] ?? null,
                    'sort_order' => $index + 1,
                ]);
            }

            return $entry->load('lines');
        });
    }

    public function nextVoucherNo(int $ownerId): string
    {
        $year = now()->format('Y');
        $count = JournalEntry::where('owner_id', $ownerId)
            ->whereYear('created_at', $year)
            ->withTrashed()
            ->count() + 1;

        return sprintf('JV-%s-%s-%06d', $ownerId, $year, $count);
    }

    /**
     * Client debt increases (increaseWallet on trader): Debit AR / Credit Revenue
     */
    public function postClientDebtIncrease(int $ownerId, int $clientId, float $amount, string $currency, string $memo, $reference = null): JournalEntry
    {
        $ar = $this->clientReceivableAccount($ownerId, $clientId);
        $revenue = $this->systemAccount($ownerId, self::CODE_REVENUE);

        return $this->post([
            'owner_id' => $ownerId,
            'entry_date' => now()->toDateString(),
            'memo' => $memo,
            'source' => 'wallet',
            'currency' => $currency,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id ?? null,
        ], [
            ['account_id' => $ar->id, 'debit' => $amount, 'credit' => 0, 'currency' => $currency, 'memo' => $memo],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => $amount, 'currency' => $currency, 'memo' => $memo],
        ]);
    }

    /**
     * Client payment / decreaseWallet on trader: Debit Cash / Credit AR
     */
    public function postClientPayment(int $ownerId, int $clientId, float $amount, string $currency, string $memo, $reference = null): JournalEntry
    {
        $cash = $this->cashAccount($ownerId, $currency);
        $ar = $this->clientReceivableAccount($ownerId, $clientId);

        return $this->post([
            'owner_id' => $ownerId,
            'entry_date' => now()->toDateString(),
            'memo' => $memo,
            'source' => 'wallet',
            'currency' => $currency,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id ?? null,
        ], [
            ['account_id' => $cash->id, 'debit' => $amount, 'credit' => 0, 'currency' => $currency, 'memo' => $memo],
            ['account_id' => $ar->id, 'debit' => 0, 'credit' => $amount, 'currency' => $currency, 'memo' => $memo],
        ]);
    }

    /**
     * Cash-box receipt (وصل قبض): Debit Cash / Credit Revenue
     */
    public function postCashReceipt(int $ownerId, float $amount, string $currency, string $memo, $reference = null): JournalEntry
    {
        $cash = $this->cashAccount($ownerId, $currency);
        $revenue = $this->systemAccount($ownerId, self::CODE_REVENUE);

        return $this->post([
            'owner_id' => $ownerId,
            'entry_date' => now()->toDateString(),
            'memo' => $memo,
            'source' => 'cash_box',
            'currency' => $currency,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id ?? null,
        ], [
            ['account_id' => $cash->id, 'debit' => $amount, 'credit' => 0, 'currency' => $currency, 'memo' => $memo],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => $amount, 'currency' => $currency, 'memo' => $memo],
        ]);
    }

    /**
     * Cash-box payment (وصل سحب): Debit Expense / Credit Cash
     */
    public function postCashDisbursement(int $ownerId, float $amount, string $currency, string $memo, $reference = null): JournalEntry
    {
        $cash = $this->cashAccount($ownerId, $currency);
        $expense = $this->systemAccount($ownerId, self::CODE_EXPENSE);

        return $this->post([
            'owner_id' => $ownerId,
            'entry_date' => now()->toDateString(),
            'memo' => $memo,
            'source' => 'cash_box',
            'currency' => $currency,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id ?? null,
        ], [
            ['account_id' => $expense->id, 'debit' => $amount, 'credit' => 0, 'currency' => $currency, 'memo' => $memo],
            ['account_id' => $cash->id, 'debit' => 0, 'credit' => $amount, 'currency' => $currency, 'memo' => $memo],
        ]);
    }

    /**
     * Route wallet increase to the correct electronic accounts.
     * client → AR/Revenue | mainBox → Cash/Revenue | other system → Suspense via cash receipt style on opening
     */
    public function postWalletIncrease(int $ownerId, int $userId, float $amount, string $currency, string $memo, $reference = null): JournalEntry
    {
        $kind = $this->walletPostingKind($ownerId, $userId);

        return match ($kind) {
            'client' => $this->postClientDebtIncrease($ownerId, $userId, $amount, $currency, $memo, $reference),
            'cash_box' => $this->postCashReceipt($ownerId, $amount, $currency, $memo, $reference),
            default => $this->postSystemWalletIncrease($ownerId, $userId, $amount, $currency, $memo, $reference),
        };
    }

    /**
     * Route wallet decrease to the correct electronic accounts.
     */
    public function postWalletDecrease(int $ownerId, int $userId, float $amount, string $currency, string $memo, $reference = null): JournalEntry
    {
        $kind = $this->walletPostingKind($ownerId, $userId);

        return match ($kind) {
            'client' => $this->postClientPayment($ownerId, $userId, $amount, $currency, $memo, $reference),
            'cash_box' => $this->postCashDisbursement($ownerId, $amount, $currency, $memo, $reference),
            default => $this->postSystemWalletDecrease($ownerId, $userId, $amount, $currency, $memo, $reference),
        };
    }

    /**
     * @return 'client'|'cash_box'|'system'
     */
    public function walletPostingKind(int $ownerId, int $userId): string
    {
        $user = User::find($userId);
        if (!$user) {
            return 'system';
        }

        $clientTypeId = (int) (\Illuminate\Support\Facades\Cache::get('user_type_client')
            ?? \App\Models\UserType::where('name', 'client')->value('id'));

        if ($clientTypeId && (int) $user->type_id === $clientTypeId) {
            return 'client';
        }

        if (strcasecmp((string) $user->email, 'mainBox@account.com') === 0) {
            return 'cash_box';
        }

        return 'system';
    }

    /**
     * Internal system wallets (main@account etc.): mirror via party AR against opening equity
     * so trial balance stays balanced without treating them as cash-box.
     */
    protected function postSystemWalletIncrease(int $ownerId, int $userId, float $amount, string $currency, string $memo, $reference = null): JournalEntry
    {
        $party = $this->clientReceivableAccount($ownerId, $userId);
        $opening = $this->systemAccount($ownerId, self::CODE_OPENING);

        return $this->post([
            'owner_id' => $ownerId,
            'entry_date' => now()->toDateString(),
            'memo' => $memo,
            'source' => 'system_wallet',
            'currency' => $currency,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id ?? null,
        ], [
            ['account_id' => $party->id, 'debit' => $amount, 'credit' => 0, 'currency' => $currency, 'memo' => $memo],
            ['account_id' => $opening->id, 'debit' => 0, 'credit' => $amount, 'currency' => $currency, 'memo' => $memo],
        ]);
    }

    protected function postSystemWalletDecrease(int $ownerId, int $userId, float $amount, string $currency, string $memo, $reference = null): JournalEntry
    {
        $party = $this->clientReceivableAccount($ownerId, $userId);
        $opening = $this->systemAccount($ownerId, self::CODE_OPENING);

        return $this->post([
            'owner_id' => $ownerId,
            'entry_date' => now()->toDateString(),
            'memo' => $memo,
            'source' => 'system_wallet',
            'currency' => $currency,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id ?? null,
        ], [
            ['account_id' => $opening->id, 'debit' => $amount, 'credit' => 0, 'currency' => $currency, 'memo' => $memo],
            ['account_id' => $party->id, 'debit' => 0, 'credit' => $amount, 'currency' => $currency, 'memo' => $memo],
        ]);
    }

    /**
     * Treasury deposit: Debit Treasury / Credit Opening or Cash clearing
     * For now deposit increases treasury asset against equity opening (or cash if transfer later).
     */
    public function postTreasuryDeposit(int $ownerId, float $amount, string $currency, string $memo, $reference = null, ?string $entryDate = null): JournalEntry
    {
        $treasury = $this->treasuryAccount($ownerId, $currency);
        $opening = $this->systemAccount($ownerId, self::CODE_OPENING);

        return $this->post([
            'owner_id' => $ownerId,
            'entry_date' => $entryDate ?? now()->toDateString(),
            'memo' => $memo,
            'source' => 'treasury',
            'currency' => $currency,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id ?? null,
        ], [
            ['account_id' => $treasury->id, 'debit' => $amount, 'credit' => 0, 'currency' => $currency, 'memo' => $memo],
            ['account_id' => $opening->id, 'debit' => 0, 'credit' => $amount, 'currency' => $currency, 'memo' => $memo],
        ]);
    }

    public function postTreasuryWithdraw(int $ownerId, float $amount, string $currency, string $memo, $reference = null, ?string $entryDate = null): JournalEntry
    {
        $treasury = $this->treasuryAccount($ownerId, $currency);
        $expense = $this->systemAccount($ownerId, self::CODE_EXPENSE);

        return $this->post([
            'owner_id' => $ownerId,
            'entry_date' => $entryDate ?? now()->toDateString(),
            'memo' => $memo,
            'source' => 'treasury',
            'currency' => $currency,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id ?? null,
        ], [
            ['account_id' => $expense->id, 'debit' => $amount, 'credit' => 0, 'currency' => $currency, 'memo' => $memo],
            ['account_id' => $treasury->id, 'debit' => 0, 'credit' => $amount, 'currency' => $currency, 'memo' => $memo],
        ]);
    }

    public function accountBalance(int $accountId, ?string $currency = null): float
    {
        $account = LedgerAccount::findOrFail($accountId);

        return $account->balance($currency);
    }

    /**
     * Soft-void a journal entry so it no longer affects balances (audit kept via SoftDeletes).
     */
    public function voidJournalEntry(?int $journalEntryId, ?string $reason = null): bool
    {
        if (!$journalEntryId) {
            return false;
        }

        $entry = JournalEntry::query()->find($journalEntryId);
        if (!$entry) {
            return false;
        }

        DB::transaction(function () use ($entry, $reason) {
            if ($reason) {
                $entry->forceFill([
                    'memo' => trim(($entry->memo ? $entry->memo . ' | ' : '') . 'VOID: ' . $reason),
                ])->save();
            }
            $entry->delete();
        });

        \Illuminate\Support\Facades\Log::info('Ledger journal voided', [
            'journal_entry_id' => $journalEntryId,
            'reason' => $reason,
            'by' => Auth::id(),
        ]);

        return true;
    }

    public function restoreJournalEntry(?int $journalEntryId): bool
    {
        if (!$journalEntryId) {
            return false;
        }

        $entry = JournalEntry::onlyTrashed()->find($journalEntryId);
        if (!$entry) {
            return false;
        }

        $entry->restore();

        return true;
    }

    /**
     * Void journal linked to a wallet transaction (by journal_entry_id or reference).
     */
    public function voidJournalForTransaction($transaction, ?string $reason = null): bool
    {
        $journalId = $transaction->journal_entry_id ?? null;

        if (!$journalId) {
            $journalId = JournalEntry::query()
                ->where('reference_type', \App\Models\Transactions::class)
                ->where('reference_id', $transaction->id)
                ->value('id');
        }

        return $this->voidJournalEntry($journalId ? (int) $journalId : null, $reason);
    }

    /**
     * Client AR balance from journal lines (source of truth).
     * Positive = client owes company.
     */
    public function clientBalance(int $ownerId, int $clientId, string $currency = '$'): float
    {
        $account = LedgerAccount::query()
            ->where('owner_id', $ownerId)
            ->where('code', self::CODE_CLIENT_AR_PREFIX . '-' . $clientId)
            ->first();

        if (!$account) {
            return 0.0;
        }

        return $account->balance($currency);
    }

    /**
     * Sum of all client receivable balances for an owner (USD by default).
     */
    public function sumClientsReceivable(int $ownerId, string $currency = '$'): float
    {
        $prefix = self::CODE_CLIENT_AR_PREFIX . '-';

        $row = DB::table('ledger_accounts as la')
            ->join('journal_lines as jl', 'jl.ledger_account_id', '=', 'la.id')
            ->join('journal_entries as je', function ($join) {
                $join->on('je.id', '=', 'jl.journal_entry_id')
                    ->whereNull('je.deleted_at');
            })
            ->where('la.owner_id', $ownerId)
            ->where('la.code', 'like', $prefix . '%')
            ->where('jl.currency', $currency)
            ->selectRaw('ROUND(COALESCE(SUM(jl.debit), 0) - COALESCE(SUM(jl.credit), 0), 2) as balance')
            ->first();

        return (float) ($row->balance ?? 0);
    }

    /**
     * Per-client COALESCE(ledger, wallet) then sum — safe before/after opening migration.
     */
    public function sumClientsReceivableWithFallback(int $ownerId, int $clientTypeId, string $currency = '$'): float
    {
        $prefix = self::CODE_CLIENT_AR_PREFIX . '-';
        $walletColumn = $currency === 'IQD' ? 'balance_dinar' : 'balance';

        $row = DB::selectOne(
            "SELECT ROUND(COALESCE(SUM(client_bal), 0), 2) AS total
             FROM (
                SELECT COALESCE(
                    (
                        SELECT ROUND(COALESCE(SUM(jl.debit), 0) - COALESCE(SUM(jl.credit), 0), 2)
                        FROM ledger_accounts AS la
                        INNER JOIN journal_lines AS jl ON jl.ledger_account_id = la.id
                        INNER JOIN journal_entries AS je ON je.id = jl.journal_entry_id AND je.deleted_at IS NULL
                        WHERE la.owner_id = ?
                          AND la.code = CONCAT(?, u.id)
                          AND jl.currency = ?
                    ),
                    (
                        SELECT w.{$walletColumn} FROM wallets AS w WHERE w.user_id = u.id LIMIT 1
                    ),
                    0
                ) AS client_bal
                FROM users AS u
                WHERE u.owner_id = ?
                  AND u.type_id = ?
             ) AS t",
            [$ownerId, $prefix, $currency, $ownerId, $clientTypeId]
        );

        return (float) ($row->total ?? 0);
    }

    /**
     * Keep wallets.balance as a cache of the correct ledger control account.
     * client → AR | cash box → Cash 1100/1110 | system → party mirror account
     */
    public function syncWalletFromLedger(int $ownerId, int $clientId): void
    {
        $wallet = \App\Models\Wallet::where('user_id', $clientId)->first();
        if (!$wallet) {
            return;
        }

        $kind = $this->walletPostingKind($ownerId, $clientId);

        if ($kind === 'cash_box') {
            $usd = $this->cashAccount($ownerId, '$')->balance('$');
            $iqd = $this->cashAccount($ownerId, 'IQD')->balance('IQD');
        } else {
            $usd = $this->clientBalance($ownerId, $clientId, '$');
            $iqd = $this->clientBalance($ownerId, $clientId, 'IQD');
        }

        $payload = [
            'balance' => $usd,
            'balance_dinar' => $iqd,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('wallets', 'ledger_synced_at')) {
            $payload['ledger_synced_at'] = now();
        }

        $wallet->forceFill($payload)->save();
    }

    /**
     * SQL expression (correlated) for client USD balance from ledger, with wallet fallback.
     * Use as selectSub / selectRaw binding owner_id once.
     */
    public static function clientBalanceSqlSubquery(int $ownerId, string $currency = '$'): \Closure
    {
        $prefix = self::CODE_CLIENT_AR_PREFIX . '-';
        $walletColumn = $currency === 'IQD' ? 'balance_dinar' : 'balance';

        return function ($subquery) use ($ownerId, $currency, $prefix, $walletColumn) {
            $subquery->selectRaw(
                "COALESCE(
                    (
                        SELECT ROUND(COALESCE(SUM(jl.debit), 0) - COALESCE(SUM(jl.credit), 0), 2)
                        FROM ledger_accounts AS la
                        INNER JOIN journal_lines AS jl ON jl.ledger_account_id = la.id
                        INNER JOIN journal_entries AS je ON je.id = jl.journal_entry_id AND je.deleted_at IS NULL
                        WHERE la.owner_id = ?
                          AND la.code = CONCAT(?, users.id)
                          AND jl.currency = ?
                    ),
                    (
                        SELECT w.{$walletColumn} FROM wallets AS w WHERE w.user_id = users.id LIMIT 1
                    ),
                    0
                )",
                [$ownerId, $prefix, $currency]
            );
        };
    }
}
