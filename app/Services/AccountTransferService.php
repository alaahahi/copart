<?php

namespace App\Services;

use App\Models\Transactions;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

/**
 * تحويل نقدي بين القاصات النقدية فقط (Cash transfer between cash boxes).
 *
 * ONE balanced ledger journal (Dr destination / Cr source). Balances come from
 * ledger accounts — never from wallets.balance.
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
            $vaultService = app(VaultService::class);

            $fromVault = $vaultService->findCashVaultByLegacyUser($ownerId, $fromUserId);
            $toVault = $vaultService->findCashVaultByLegacyUser($ownerId, $toUserId);

            if (! $fromVault || ! $toVault) {
                throw new InvalidArgumentException('التحويل النقدي مسموح فقط بين القاصات النقدية (صندوق/بنك/خزنة).');
            }

            $fromUser = User::query()->where('owner_id', $ownerId)->findOrFail($fromUserId);
            $toUser = User::query()->where('owner_id', $ownerId)->findOrFail($toUserId);

            $fromAccount = $this->ledger->walletLedgerAccount($ownerId, $fromUserId, $currency);
            $available = $fromAccount->balance($currency);
            if ($amount > round($available, 2)) {
                throw new RuntimeException('الرصيد غير كافٍ في الحساب المرسل لإتمام التحويل.');
            }

            $memoText = $memo ?: sprintf('تحويل نقدي من %s إلى %s', $fromUser->name, $toUser->name);
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
            $hasVaultColumn = Schema::hasColumn('transactions', 'vault_id');

            $outPayload = [
                'type' => 'transfer_out',
                'description' => $memoText,
                'amount' => $amount * -1,
                'currency' => $currency,
                'created' => $entryDate,
                'is_pay' => 0,
                'discount' => 0,
                'wallet_id' => null,
                'journal_entry_id' => $hasJournalColumn ? $journal->id : null,
            ];
            if ($hasVaultColumn) {
                $outPayload['vault_id'] = (int) $fromVault->id;
            }
            // Legacy wallet_id only while column/table still used for history joins.
            if (Schema::hasTable('wallets') && Schema::hasColumn('vaults', 'wallet_id') && $fromVault->wallet_id) {
                $outPayload['wallet_id'] = (int) $fromVault->wallet_id;
            }

            $outTransaction = Transactions::create(array_filter($outPayload, fn ($v) => $v !== null));

            $inPayload = [
                'type' => 'transfer_in',
                'description' => $memoText,
                'amount' => $amount,
                'currency' => $currency,
                'created' => $entryDate,
                'is_pay' => 0,
                'discount' => 0,
                'parent_id' => $outTransaction->id,
                'wallet_id' => null,
                'journal_entry_id' => $hasJournalColumn ? $journal->id : null,
            ];
            if ($hasVaultColumn) {
                $inPayload['vault_id'] = (int) $toVault->id;
            }
            if (Schema::hasTable('wallets') && Schema::hasColumn('vaults', 'wallet_id') && $toVault->wallet_id) {
                $inPayload['wallet_id'] = (int) $toVault->wallet_id;
            }

            $inTransaction = Transactions::create(array_filter($inPayload, fn ($v) => $v !== null));

            Log::info('Cash vault transfer posted', [
                'owner_id' => $ownerId,
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'from_vault_id' => $fromVault->id,
                'to_vault_id' => $toVault->id,
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
     * Cash-box vaults only. Balances from ledger (not wallet.balance).
     * id remains legacy_user_id so existing transfer APIs keep working.
     */
    public function transferableAccounts(int $ownerId): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable('vaults')) {
            return collect();
        }

        $vaultService = app(VaultService::class);

        return Vault::query()
            ->with(['legacyUser', 'ledgerAccount'])
            ->forOwner($ownerId)
            ->active()
            ->cashBoxes()
            ->whereNotNull('legacy_user_id')
            ->orderBy('name')
            ->get()
            ->filter(fn (Vault $vault) => $vault->isCashBox() && (int) $vault->legacy_user_id > 0)
            ->map(function (Vault $vault) use ($vaultService) {
                return [
                    'id' => (int) $vault->legacy_user_id,
                    'vault_id' => (int) $vault->id,
                    'name' => $vault->name,
                    'email' => $vault->legacyUser?->email,
                    'vault_type' => $vault->type,
                    'vault_code' => $vault->code,
                    'is_vault' => true,
                    'balance' => $vaultService->cashBalance($vault, '$'),
                    'balance_dinar' => $vaultService->cashBalance($vault, 'IQD'),
                ];
            })
            ->values();
    }
}
