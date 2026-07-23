<?php

namespace App\Services;

use App\Models\Transactions;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

/**
 * حركة بين الحسابات (Transfer between accounts).
 *
 * Moves money between the ERP's "account" wallet users (cash box / mainBox,
 * Dubai, Iran, border, howler, main/in/out accounts, ...) using ONE balanced
 * ledger journal (LedgerService::postAccountTransfer — Dr destination / Cr
 * source). Wallet balances are never written directly: they are re-synced
 * from the ledger after posting, and mirrored into `transactions` so the
 * existing per-account "Wallet" screens keep showing full history.
 */
class AccountTransferService
{
    public function __construct(protected LedgerService $ledger)
    {
    }

    /**
     * @return array{journal: \App\Models\JournalEntry, from_transaction: Transactions, to_transaction: Transactions}
     */
    public function transfer(
        int $ownerId,
        int $fromUserId,
        int $toUserId,
        float $amount,
        string $currency,
        ?string $memo,
        ?string $entryDate
    ): array {
        if ($fromUserId === $toUserId) {
            throw new InvalidArgumentException('لا يمكن التحويل من وإلى نفس الحساب.');
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('مبلغ التحويل يجب أن يكون أكبر من صفر.');
        }

        return DB::transaction(function () use ($ownerId, $fromUserId, $toUserId, $amount, $currency, $memo, $entryDate) {
            $fromUser = User::with('wallet')->where('owner_id', $ownerId)->findOrFail($fromUserId);
            $toUser = User::with('wallet')->where('owner_id', $ownerId)->findOrFail($toUserId);

            if (!$fromUser->wallet) {
                throw new RuntimeException('الحساب المرسل لا يملك محفظة بعد.');
            }
            if (!$toUser->wallet) {
                $toUser->setRelation('wallet', Wallet::create(['user_id' => $toUser->id, 'balance' => 0, 'balance_dinar' => 0]));
            }

            $fromAccount = $this->ledger->walletLedgerAccount($ownerId, $fromUserId, $currency);
            $available = $fromAccount->balance($currency);
            if ($amount > round($available, 2)) {
                throw new RuntimeException('الرصيد غير كافٍ في الحساب المرسل لإتمام التحويل.');
            }

            $memoText = $memo ?: sprintf('تحويل من %s إلى %s', $fromUser->name, $toUser->name);
            $entryDate = $entryDate ?: now()->toDateString();

            $journal = $this->ledger->postAccountTransfer(
                $ownerId,
                $fromUserId,
                $toUserId,
                $amount,
                $currency,
                $memoText,
                null,
                $entryDate
            );

            $hasJournalColumn = Schema::hasColumn('transactions', 'journal_entry_id');

            $outTransaction = Transactions::create(array_filter([
                'wallet_id' => $fromUser->wallet->id,
                'type' => 'transfer_out',
                'description' => $memoText,
                'amount' => $amount * -1,
                'currency' => $currency,
                'created' => $entryDate,
                'is_pay' => 0,
                'discount' => 0,
                'journal_entry_id' => $hasJournalColumn ? $journal->id : null,
            ], fn ($v) => $v !== null));

            $inTransaction = Transactions::create(array_filter([
                'wallet_id' => $toUser->wallet->id,
                'type' => 'transfer_in',
                'description' => $memoText,
                'amount' => $amount,
                'currency' => $currency,
                'created' => $entryDate,
                'is_pay' => 0,
                'discount' => 0,
                'parent_id' => $outTransaction->id,
                'journal_entry_id' => $hasJournalColumn ? $journal->id : null,
            ], fn ($v) => $v !== null));

            $this->ledger->syncWalletFromLedger($ownerId, $fromUserId);
            $this->ledger->syncWalletFromLedger($ownerId, $toUserId);

            Log::info('Account transfer posted', [
                'owner_id' => $ownerId,
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'amount' => $amount,
                'currency' => $currency,
                'journal_entry_id' => $journal->id,
                'by' => Auth::id(),
            ]);

            return [
                'journal' => $journal,
                'from_transaction' => $outTransaction,
                'to_transaction' => $inTransaction,
            ];
        });
    }

    /**
     * List of "account" wallet users eligible for transfer (cash box, Dubai,
     * Iran, border, howler, main/in/out/debt accounts, ...). Client AR
     * accounts are intentionally excluded — client debt/payment already has
     * its own dedicated flow (سند قبض/دفع).
     */
    public function transferableAccounts(int $ownerId): \Illuminate\Support\Collection
    {
        $accountTypeId = Cache::get('user_type_account')
            ?? \App\Models\UserType::where('name', 'account')->value('id');

        return User::with('wallet')
            ->where('owner_id', $ownerId)
            ->where('type_id', $accountTypeId)
            ->whereHas('wallet')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'balance' => (float) ($user->wallet->balance ?? 0),
                'balance_dinar' => (float) ($user->wallet->balance_dinar ?? 0),
            ])
            ->values();
    }
}
