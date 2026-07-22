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
     * Client debt increases (increaseWallet): Debit AR / Credit Revenue
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
     * Client payment / decreaseWallet: Debit Cash / Credit AR
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
}
