<?php

namespace App\Services;

use App\Models\TraderProfitEntry;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * ترحيل أرباح التاجر / سحب من الأرباح.
 *
 * Wraps LedgerService's Trader Profits Reserve (3200, equity) postings with
 * an audit trail (trader_profit_entries) so the UI can list who posted what,
 * for which trader/period, and void it later. The reserve's real balance is
 * always derived from journal_entries/journal_lines (LedgerAccount::balance),
 * never from this audit table.
 */
class TraderProfitService
{
    public function __construct(protected LedgerService $ledger)
    {
    }

    public function postProfit(
        int $ownerId,
        int $clientId,
        float $amount,
        string $currency,
        ?string $periodFrom,
        ?string $periodTo,
        ?string $notes,
        ?string $entryDate
    ): TraderProfitEntry {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('مبلغ الترحيل يجب أن يكون أكبر من صفر.');
        }

        return DB::transaction(function () use ($ownerId, $clientId, $amount, $currency, $periodFrom, $periodTo, $notes, $entryDate) {
            $client = User::where('owner_id', $ownerId)->findOrFail($clientId);

            $memo = $notes ?: sprintf('ترحيل أرباح التاجر: %s', $client->name);
            $entryDate = $entryDate ?: now()->toDateString();

            $journal = $this->ledger->postTraderProfitAppropriation(
                $ownerId,
                $amount,
                $currency,
                $memo,
                null,
                $entryDate
            );

            $entry = TraderProfitEntry::create([
                'owner_id' => $ownerId,
                'type' => 'post',
                'client_id' => $clientId,
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'amount' => $amount,
                'currency' => $currency,
                'memo' => $memo,
                'journal_entry_id' => $journal->id,
                'created_by' => Auth::id(),
            ]);

            Log::info('Trader profit posted to profits reserve', [
                'owner_id' => $ownerId,
                'client_id' => $clientId,
                'amount' => $amount,
                'currency' => $currency,
                'journal_entry_id' => $journal->id,
                'by' => Auth::id(),
            ]);

            return $entry->fresh(['client']);
        });
    }

    public function withdraw(
        int $ownerId,
        float $amount,
        string $currency,
        ?string $notes,
        ?string $entryDate
    ): TraderProfitEntry {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('مبلغ السحب يجب أن يكون أكبر من صفر.');
        }

        return DB::transaction(function () use ($ownerId, $amount, $currency, $notes, $entryDate) {
            $profitsBalance = $this->ledger->profitsAccount($ownerId)->balance($currency);
            if ($amount > round($profitsBalance, 2)) {
                throw new RuntimeException('رصيد حساب الأرباح غير كافٍ لإتمام السحب.');
            }

            $cashBalance = $this->ledger->cashAccount($ownerId, $currency)->balance($currency);
            if ($amount > round($cashBalance, 2)) {
                throw new RuntimeException('رصيد الصندوق غير كافٍ لإتمام السحب.');
            }

            $memo = $notes ?: 'سحب من حساب أرباح التجار';
            $entryDate = $entryDate ?: now()->toDateString();

            $journal = $this->ledger->postProfitWithdraw(
                $ownerId,
                $amount,
                $currency,
                $memo,
                null,
                $entryDate
            );

            $entry = TraderProfitEntry::create([
                'owner_id' => $ownerId,
                'type' => 'withdraw',
                'amount' => $amount,
                'currency' => $currency,
                'memo' => $memo,
                'journal_entry_id' => $journal->id,
                'created_by' => Auth::id(),
            ]);

            Log::info('Trader profit withdrawal posted', [
                'owner_id' => $ownerId,
                'amount' => $amount,
                'currency' => $currency,
                'journal_entry_id' => $journal->id,
                'by' => Auth::id(),
            ]);

            return $entry->fresh();
        });
    }

    public function balance(int $ownerId, string $currency): float
    {
        return $this->ledger->profitsAccount($ownerId)->balance($currency);
    }

    public function recent(int $ownerId, int $limit = 50)
    {
        return TraderProfitEntry::with('client:id,name')
            ->where('owner_id', $ownerId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Soft-delete an entry and void its linked journal (audit trail kept via SoftDeletes).
     */
    public function voidEntry(int $ownerId, int $id, ?string $reason = null): void
    {
        DB::transaction(function () use ($ownerId, $id, $reason) {
            $entry = TraderProfitEntry::where('owner_id', $ownerId)->findOrFail($id);

            if ($entry->journal_entry_id) {
                $this->ledger->voidJournalEntry((int) $entry->journal_entry_id, $reason ?: 'حذف حركة أرباح التجار');
            }

            $entry->delete();

            Log::info('Trader profit entry deleted', [
                'id' => $id,
                'owner_id' => $ownerId,
                'reason' => $reason,
                'by' => Auth::id(),
            ]);
        });
    }
}
