<?php

namespace App\Services;

use App\Models\Car;
use App\Models\CarExpenses;
use App\Models\CompanyTreasuryEntry;
use App\Models\JournalEntry;
use App\Models\TraderProfitEntry;
use App\Models\Transactions;
use App\Models\Transfers;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Admin-only operational wipe: cars, traders, payments, journals, wallet balances,
 * and non-essential system vaults (commission / expense boxes).
 *
 * Preserves admins, system config/branding/WA, chart of accounts, and mainBox only.
 */
class SystemResetService
{
    public function __construct(
        protected SystemWalletService $systemWallets,
        protected LedgerService $ledger,
        protected AccountingCacheService $accountingCache,
    ) {
    }

    /**
     * @return array{cars:int,expenses:int,transactions:int,journals:int,traders:int,transfers:int,treasury:int,profits:int,wallets_reset:int,vault_users_removed:int,vaults_removed:int}
     */
    public function wipe(User $actor): array
    {
        if ((int) $actor->type_id !== 1) {
            throw ValidationException::withMessages([
                'password' => 'تصفير النظام مسموح للمدير فقط.',
            ]);
        }

        $ownerId = (int) ($actor->owner_id ?: $actor->id);
        $adminTypeId = (int) (UserType::query()->where('name', 'admin')->value('id') ?? 1);
        $clientTypeId = (int) (UserType::query()->where('name', 'client')->value('id') ?? 4);
        $accountTypeId = (int) (UserType::query()->where('name', 'account')->value('id') ?? 2);

        if ($adminTypeId <= 0 || $clientTypeId <= 0) {
            throw new RuntimeException('أنواع المستخدمين غير مكتملة — شغّل UserTypeSeeder.');
        }

        $stats = [
            'cars' => 0,
            'expenses' => 0,
            'transactions' => 0,
            'journals' => 0,
            'traders' => 0,
            'transfers' => 0,
            'treasury' => 0,
            'profits' => 0,
            'wallets_reset' => 0,
            'vault_users_removed' => 0,
            'vaults_removed' => 0,
        ];

        DB::transaction(function () use (
            $actor,
            $ownerId,
            $adminTypeId,
            $clientTypeId,
            $accountTypeId,
            &$stats
        ) {
            $ownerUserIds = User::withTrashed()
                ->where(function ($q) use ($ownerId) {
                    $q->where('owner_id', $ownerId)->orWhere('id', $ownerId);
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $walletIds = [];
            if (Schema::hasTable('wallets')) {
                $walletIds = DB::table('wallets')
                    ->whereIn('user_id', $ownerUserIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            $vaultIds = [];
            if (Schema::hasTable('vaults')) {
                $vaultIds = DB::table('vaults')
                    ->where('owner_id', $ownerId)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            $carIds = Car::withTrashed()
                ->where('owner_id', $ownerId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            // 1) Car expenses
            $stats['expenses'] = CarExpenses::query()
                ->where('owner_id', $ownerId)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now(), 'updated_at' => now()]);

            // 2) Cars
            $stats['cars'] = Car::query()
                ->where('owner_id', $ownerId)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now(), 'updated_at' => now()]);

            // 3) Wallet/vault transactions (payments) for this owner
            $txQuery = Transactions::query()->whereNull('deleted_at');
            $txQuery->where(function ($q) use ($walletIds, $vaultIds, $carIds, $ownerUserIds) {
                if ($walletIds !== []) {
                    $q->orWhereIn('wallet_id', $walletIds);
                }
                if ($vaultIds !== [] && Schema::hasColumn('transactions', 'vault_id')) {
                    $q->orWhereIn('vault_id', $vaultIds);
                }
                if ($carIds !== []) {
                    $q->orWhere(function ($inner) use ($carIds) {
                        $inner->where('morphed_type', Car::class)
                            ->whereIn('morphed_id', $carIds);
                    });
                }
                if ($ownerUserIds !== []) {
                    $q->orWhere(function ($inner) use ($ownerUserIds) {
                        $inner->where('morphed_type', User::class)
                            ->whereIn('morphed_id', $ownerUserIds);
                    });
                }
            });
            $stats['transactions'] = (clone $txQuery)->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);

            // 4) Journal entries (SoftDeletes — lines stay but balances ignore trashed entries)
            $stats['journals'] = JournalEntry::query()
                ->where('owner_id', $ownerId)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now(), 'updated_at' => now()]);

            // 5) Trader profits
            if (Schema::hasTable('trader_profit_entries')) {
                $stats['profits'] = TraderProfitEntry::query()
                    ->where('owner_id', $ownerId)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => now(), 'updated_at' => now()]);
            }

            // 6) Company treasury
            if (Schema::hasTable('company_treasury_entries')) {
                $stats['treasury'] = CompanyTreasuryEntry::query()
                    ->where('owner_id', $ownerId)
                    ->whereNull('deleted_at')
                    ->update(['deleted_at' => now(), 'updated_at' => now()]);
            }

            // 7) Transfers involving owner users
            if (Schema::hasTable('transfers') && $ownerUserIds !== []) {
                $stats['transfers'] = Transfers::query()
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($ownerUserIds) {
                        $q->whereIn('sender_id', $ownerUserIds)
                            ->orWhereIn('receiver_id', $ownerUserIds)
                            ->orWhereIn('user_id', $ownerUserIds);
                    })
                    ->update(['deleted_at' => now(), 'updated_at' => now()]);
            }

            // 8) Soft-delete traders/clients (never admin, never current user, never system vaults —
            //    non-essential vaults are removed in step 9)
            $traders = User::query()
                ->where('type_id', $clientTypeId)
                ->where(function ($q) use ($ownerId) {
                    $q->where('owner_id', $ownerId)->orWhere('id', $ownerId);
                })
                ->where('id', '!=', (int) $actor->id)
                ->get();

            foreach ($traders as $trader) {
                if (SystemWalletService::isSystemVaultUser($trader, $accountTypeId)) {
                    continue;
                }
                if ((int) $trader->type_id === $adminTypeId) {
                    continue;
                }
                $trader->delete();
                $stats['traders']++;
            }

            // 9) Soft-delete non-essential system vaults (عمولة / مصاريف / optional boxes).
            //    Keep mainBox only — soft-deleted vaults are never auto-recreated.
            $vaultWipe = $this->systemWallets->softDeleteNonEssentialForOwner($ownerId);
            $stats['vault_users_removed'] = $vaultWipe['users'];
            $stats['vaults_removed'] = $vaultWipe['vaults'];

            // 10) Wallets table removed — balances live on ledger only.
            $stats['wallets_reset'] = 0;
            if (Schema::hasTable('wallets')) {
                $survivingUserIds = User::withTrashed()
                    ->where(function ($q) use ($ownerId) {
                        $q->where('owner_id', $ownerId)->orWhere('id', $ownerId);
                    })
                    ->whereNull('deleted_at')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if ($survivingUserIds !== []) {
                    $stats['wallets_reset'] = DB::table('wallets')
                        ->whereIn('user_id', $survivingUserIds)
                        ->update([
                            'balance' => 0,
                            'balance_dinar' => 0,
                            'card' => 0,
                            'updated_at' => now(),
                        ]);
                }
            }

            // 11) Re-ensure essential vault only (mainBox) + ledger chart
            $this->systemWallets->ensureForOwner($ownerId, false);
            $this->ledger->ensureSystemAccounts($ownerId);
        });

        $this->accountingCache->forgetOwnerAccounts($ownerId);

        Log::warning('System operational wipe completed', [
            'owner_id' => $ownerId,
            'wiped_by' => $actor->id,
            'wiped_by_email' => $actor->email,
            'wiped_at' => now()->toDateTimeString(),
            'stats' => $stats,
        ]);

        return $stats;
    }
}
