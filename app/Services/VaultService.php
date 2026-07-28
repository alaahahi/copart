<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserType;
use App\Models\Vault;
use App\Models\Wallet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Vaults (قاصات) are independent from traders (تجار).
 * Phase 1: vaults table is UI source of truth; Wallet + legacy_user keep ledger history.
 */
class VaultService
{
    /**
     * Ensure vault rows exist for every system vault user of an owner.
     *
     * @return list<Vault>
     */
    public function syncAllForOwner(int $ownerId): array
    {
        if (! Schema::hasTable('vaults')) {
            return [];
        }

        $accountTypeId = (int) UserType::query()->where('name', 'account')->value('id');
        $clientTypeId = (int) UserType::query()->where('name', 'client')->value('id');

        $query = User::query()->where('owner_id', $ownerId);
        SystemWalletService::scopeSystemVaults($query, $accountTypeId, $clientTypeId);

        $synced = [];
        foreach ($query->with('wallet')->get() as $user) {
            $synced[] = $this->syncFromSystemUser($user);
        }

        return $synced;
    }

    /**
     * Create or update a vault row linked to a legacy system-wallet user.
     */
    public function syncFromSystemUser(User $user): Vault
    {
        $code = $this->codeFromUser($user);
        $type = $this->typeFromUser($user);

        $walletId = $user->wallet?->id
            ?? Wallet::query()->where('user_id', $user->id)->value('id');

        $vault = Vault::withTrashed()
            ->where('owner_id', (int) $user->owner_id)
            ->where(function ($q) use ($user, $code) {
                $q->where('legacy_user_id', $user->id)
                    ->orWhere('code', $code);
            })
            ->first();

        if ($vault && $vault->trashed()) {
            // Respect soft-delete: do not resurrect.
            return $vault;
        }

        if (! $vault) {
            $vault = new Vault([
                'owner_id' => (int) $user->owner_id,
                'code' => $code,
            ]);
        }

        $vault->fill([
            'owner_id' => (int) $user->owner_id,
            'name' => $user->name ?: $code,
            'code' => $code,
            'type' => $type,
            'is_active' => true,
            'show_in_accounting' => $vault->exists ? (bool) $vault->show_in_accounting : true,
            'wallet_id' => $walletId ? (int) $walletId : null,
            'legacy_user_id' => (int) $user->id,
            'notes' => $vault->notes ?: 'Linked to legacy vault user',
        ]);
        $vault->save();

        return $vault->fresh(['wallet', 'legacyUser']);
    }

    /**
     * Active vaults for owner (قاصات النظام list).
     */
    public function listForOwner(int $ownerId, bool $activeOnly = true): Collection
    {
        $query = Vault::query()
            ->with(['wallet', 'legacyUser'])
            ->forOwner($ownerId)
            ->orderBy('name');

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Accounting page orange shortcut buttons — vaults only, never traders.
     *
     * Shape matches legacy flaggedWallets: { id: legacy_user_id, name, wallet }.
     */
    public function accountingShortcutUsers(int $ownerId): Collection
    {
        if (! Schema::hasTable('vaults')) {
            return collect();
        }

        return Vault::query()
            ->with(['legacyUser.wallet'])
            ->forOwner($ownerId)
            ->accountingShortcuts()
            ->orderBy('name')
            ->get()
            ->map(function (Vault $vault) {
                $user = $vault->legacyUser;
                if (! $user) {
                    return null;
                }
                // Ensure name follows vault table (source of truth for UI label).
                $user->setAttribute('name', $vault->name);
                $user->setAttribute('vault_id', $vault->id);
                $user->setAttribute('vault_code', $vault->code);
                $user->setAttribute('vault_type', $vault->type);

                return $user;
            })
            ->filter()
            ->values();
    }

    /**
     * Rows for Clients tab «قاصات النظام» — compatible with existing table UI.
     * id = legacy_user_id so /wallet?id=… keeps working.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function systemQasaClientRows(int $ownerId): Collection
    {
        if (! Schema::hasTable('vaults')) {
            return collect();
        }

        $vaults = Vault::query()
            ->with(['wallet', 'legacyUser'])
            ->forOwner($ownerId)
            ->active()
            ->whereNotNull('legacy_user_id')
            ->orderBy('name')
            ->get();

        if ($vaults->isEmpty()) {
            return collect();
        }

        $legacyIds = $vaults->pluck('legacy_user_id')->map(fn ($id) => (int) $id)->all();
        $movementIds = array_fill_keys(SystemWalletService::idsWithMovements($legacyIds), true);

        $balanceByUser = $this->balancesForLegacyUsers($ownerId, $legacyIds);

        return $vaults->map(function (Vault $vault) use ($movementIds, $balanceByUser) {
            $legacyId = (int) $vault->legacy_user_id;
            $hasMovements = isset($movementIds[$legacyId]);

            return (object) [
                'id' => $legacyId,
                'vault_id' => $vault->id,
                'name' => $vault->name,
                'phone' => null,
                'email' => $vault->legacyUser?->email,
                'type_id' => $vault->legacyUser?->type_id,
                'created_at' => $vault->created_at,
                'show_in_dashboard' => true,
                'car_count' => 0,
                'car_count_completed' => 0,
                'car_total_un_pay' => 0,
                'balance' => $balanceByUser[$legacyId] ?? (float) ($vault->wallet?->balance ?? 0),
                'has_movements' => $hasMovements,
                'can_delete' => ! $hasMovements,
                'is_vault' => true,
                'vault_code' => $vault->code,
                'vault_type' => $vault->type,
            ];
        })->sortByDesc(fn ($row) => (float) $row->balance)->values();
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, float>
     */
    protected function balancesForLegacyUsers(int $ownerId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return [];
        }

        $out = [];

        if (Schema::hasTable('ledger_accounts') && Schema::hasTable('journal_lines')) {
            $prefix = LedgerService::CODE_CLIENT_AR_PREFIX.'-';
            $rows = DB::table('ledger_accounts as la')
                ->leftJoin('journal_lines as jl', 'jl.ledger_account_id', '=', 'la.id')
                ->leftJoin('journal_entries as je', function ($join) {
                    $join->on('je.id', '=', 'jl.journal_entry_id')
                        ->whereNull('je.deleted_at');
                })
                ->where('la.owner_id', $ownerId)
                ->where('jl.currency', '$')
                ->where(function ($q) use ($userIds, $prefix) {
                    foreach ($userIds as $id) {
                        $q->orWhere('la.code', $prefix.$id);
                    }
                })
                ->groupBy('la.code')
                ->selectRaw('la.code, ROUND(COALESCE(SUM(jl.debit), 0) - COALESCE(SUM(jl.credit), 0), 2) as bal')
                ->get();

            foreach ($rows as $row) {
                $code = (string) $row->code;
                if (str_starts_with($code, $prefix)) {
                    $uid = (int) substr($code, strlen($prefix));
                    $out[$uid] = (float) $row->bal;
                }
            }
        }

        $missing = array_diff($userIds, array_keys($out));
        if ($missing !== [] && Schema::hasTable('wallets')) {
            $wallets = DB::table('wallets')
                ->whereIn('user_id', $missing)
                ->get(['user_id', 'balance']);
            foreach ($wallets as $w) {
                $out[(int) $w->user_id] = (float) $w->balance;
            }
        }

        return $out;
    }

    public function codeFromUser(User $user): string
    {
        $email = trim((string) ($user->email ?? ''));
        if ($email !== '') {
            $at = strpos($email, '@');
            $base = $at === false ? $email : substr($email, 0, $at);

            return substr(preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $base) ?: 'vault', 0, 64);
        }

        $slug = Str::slug((string) $user->name, '-') ?: 'vault';

        return substr('u'.$user->id.'-'.$slug, 0, 64);
    }

    public function typeFromUser(User $user): string
    {
        $email = (string) ($user->email ?? '');
        $name = trim((string) ($user->name ?? ''));

        if (strcasecmp($email, 'mainBox@account.com') === 0) {
            return Vault::TYPE_CASH;
        }

        if (in_array($name, ['مصاريف الشركة', 'Company Expenses'], true)) {
            return Vault::TYPE_COMPANY;
        }

        if (SystemWalletService::isLegacyClientVaultName($name) && str_starts_with($name, 'عمولة')) {
            return Vault::TYPE_COMMISSION;
        }

        if (in_array($email, ['howler', 'shipping-coc', 'border', 'iran', 'dubai', 'out@account.com'], true)) {
            return Vault::TYPE_EXPENSE;
        }

        if (str_starts_with($email, 'supplier-')) {
            return Vault::TYPE_SUPPLIER;
        }

        if (str_starts_with($email, 'online-contracts')) {
            return Vault::TYPE_CONTRACTS;
        }

        return Vault::TYPE_SYSTEM;
    }
}
