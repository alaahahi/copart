<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\User;
use App\Models\UserType;
use App\Models\Vault;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Vaults (قاصات) are independent from traders (تجار).
 * Cash boxes (cash/bank/safe + mainBox) own real COA cash accounts — not 1200 party mirrors.
 */
class VaultService
{
    /** Types allowed when creating / updating vaults from the UI. */
    public const CREATABLE_TYPES = [
        Vault::TYPE_CASH,
        Vault::TYPE_BANK,
        Vault::TYPE_SAFE,
    ];

    public function __construct(protected LedgerService $ledger)
    {
    }

    /**
     * Create a cash vault with a real cash COA account (not 1200 party).
     * Still creates a hidden technical User for payment API morph compatibility.
     *
     * @param  array{
     *   name:string,
     *   type?:string,
     *   code?:?string,
     *   currency_default?:?string,
     *   is_active?:bool,
     *   show_in_accounting?:bool,
     *   notes?:?string
     * }  $data
     */
    public function create(int $ownerId, array $data): Vault
    {
        if (! Schema::hasTable('vaults')) {
            throw new RuntimeException('جدول القاصات غير موجود — شغّل php artisan migrate');
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('اسم القاصة مطلوب.');
        }

        $type = (string) ($data['type'] ?? Vault::TYPE_CASH);
        if (! in_array($type, self::CREATABLE_TYPES, true)) {
            throw new InvalidArgumentException('نوع القاصة غير صالح. المسموح: نقد، بنك، خزنة.');
        }

        $code = $this->normalizeCode((string) ($data['code'] ?? '')) ?: $this->uniqueCodeFromName($ownerId, $name);

        return DB::transaction(function () use ($ownerId, $name, $type, $code, $data) {
            $accountTypeId = (int) UserType::query()->where('name', 'account')->value('id');
            if ($accountTypeId <= 0) {
                throw new RuntimeException('نوع المستخدم account غير موجود — شغّل UserTypeSeeder أولاً.');
            }

            $email = $this->uniqueTechnicalEmail($ownerId, $code);
            $today = Carbon::now()->format('Y-m-d');
            $year = (int) Carbon::now()->format('Y');

            $legacyUser = User::query()->create([
                'email' => $email,
                'name' => $name,
                'password' => Hash::make(bin2hex(random_bytes(16))),
                'type_id' => $accountTypeId,
                'owner_id' => $ownerId,
                'is_band' => 0,
                'show_in_dashboard' => false,
                'phone' => null,
                'created' => $today,
                'year_date' => $year,
            ]);

            // No Wallet rows — balances live on ledger via ledger_account_id.

            $this->ledger->ensureSystemAccounts($ownerId);

            $vaultAttrs = [
                'owner_id' => $ownerId,
                'name' => $name,
                'code' => $code,
                'type' => $type,
                'currency_default' => $data['currency_default'] ?? null,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
                'show_in_accounting' => array_key_exists('show_in_accounting', $data)
                    ? (bool) $data['show_in_accounting']
                    : true,
                'legacy_user_id' => (int) $legacyUser->id,
                'ledger_account_id' => null,
                'notes' => isset($data['notes']) ? (trim((string) $data['notes']) ?: null) : null,
            ];
            if (Schema::hasColumn('vaults', 'wallet_id')) {
                $vaultAttrs['wallet_id'] = null;
            }

            $vault = Vault::query()->create($vaultAttrs);

            // Real cash COA under cash (11V-{vaultId}) — NOT 1200 party.
            $cashParent = LedgerAccount::query()
                ->where('owner_id', $ownerId)
                ->where('code', LedgerService::CODE_CASH_USD)
                ->first();

            $ledgerAccount = $this->ledger->createAccount($ownerId, [
                'code' => '11V-'.$vault->id,
                'name' => 'Cash vault: '.$name,
                'name_ar' => 'نقد قاصة: '.$name,
                'type' => 'asset',
                'currency' => null,
                'parent_id' => $cashParent?->id,
                'is_active' => true,
            ]);

            $vault->forceFill(['ledger_account_id' => (int) $ledgerAccount->id])->save();

            Log::info('Vault created with cash COA account', [
                'vault_id' => $vault->id,
                'owner_id' => $ownerId,
                'legacy_user_id' => $legacyUser->id,
                'ledger_account_id' => $ledgerAccount->id,
                'code' => $code,
                'type' => $type,
            ]);

            return $vault->fresh(['legacyUser', 'ledgerAccount']);
        });
    }

    /**
     * Update vault metadata; sync display name onto the technical legacy user.
     *
     * @param  array{
     *   name?:string,
     *   type?:string,
     *   code?:string,
     *   currency_default?:?string,
     *   is_active?:bool,
     *   show_in_accounting?:bool,
     *   notes?:?string
     * }  $data
     */
    public function update(Vault $vault, array $data): Vault
    {
        return DB::transaction(function () use ($vault, $data) {
            if (isset($data['name'])) {
                $name = trim((string) $data['name']);
                if ($name === '') {
                    throw new InvalidArgumentException('اسم القاصة مطلوب.');
                }
                $vault->name = $name;
            }

            if (isset($data['type'])) {
                $type = (string) $data['type'];
                if (! in_array($type, self::CREATABLE_TYPES, true)) {
                    throw new InvalidArgumentException('نوع القاصة غير صالح. المسموح: نقد، بنك، خزنة.');
                }
                if ($this->isEssentialMainBox($vault) && $type !== Vault::TYPE_CASH) {
                    throw new InvalidArgumentException('لا يمكن تغيير نوع الصندوق الرئيسي.');
                }
                $vault->type = $type;
            }

            if (array_key_exists('code', $data) && $data['code'] !== null && $data['code'] !== '') {
                $code = $this->normalizeCode((string) $data['code']);
                if ($code === '') {
                    throw new InvalidArgumentException('رمز القاصة غير صالح.');
                }
                if ($this->isEssentialMainBox($vault) && strcasecmp($code, 'mainBox') !== 0) {
                    throw new InvalidArgumentException('لا يمكن تغيير رمز الصندوق الرئيسي.');
                }
                $exists = Vault::withTrashed()
                    ->where('owner_id', (int) $vault->owner_id)
                    ->where('code', $code)
                    ->where('id', '!=', (int) $vault->id)
                    ->exists();
                if ($exists) {
                    throw new InvalidArgumentException('رمز القاصة مستخدم مسبقاً.');
                }
                $vault->code = $code;
            }

            if (array_key_exists('currency_default', $data)) {
                $vault->currency_default = $data['currency_default'];
            }
            if (array_key_exists('is_active', $data)) {
                $vault->is_active = (bool) $data['is_active'];
            }
            if (array_key_exists('show_in_accounting', $data)) {
                $vault->show_in_accounting = (bool) $data['show_in_accounting'];
            }
            if (array_key_exists('notes', $data)) {
                $vault->notes = $data['notes'] !== null ? (trim((string) $data['notes']) ?: null) : null;
            }

            $vault->save();

            if ($vault->legacy_user_id && isset($data['name'])) {
                User::query()->where('id', (int) $vault->legacy_user_id)->update([
                    'name' => $vault->name,
                ]);
            }

            if ($vault->ledger_account_id) {
                DB::table('ledger_accounts')
                    ->where('id', (int) $vault->ledger_account_id)
                    ->update([
                        'name' => 'Cash vault: '.$vault->name,
                        'name_ar' => 'نقد قاصة: '.$vault->name,
                        'updated_at' => now(),
                    ]);
            }

            return $vault->fresh(['legacyUser', 'ledgerAccount']);
        });
    }

    /**
     * Soft-disable vaults that are not cash boxes (hide from accounting / transfers).
     *
     * @return int Number of vaults deactivated
     */
    public function deactivateNonCashVaults(int $ownerId): int
    {
        if (! Schema::hasTable('vaults')) {
            return 0;
        }

        $ids = Vault::query()
            ->forOwner($ownerId)
            ->where(function ($q) {
                $q->whereNotIn('type', Vault::CASH_TYPES)
                    ->where(function ($inner) {
                        $inner->where('type', '!=', Vault::TYPE_SYSTEM)
                            ->orWhereRaw('LOWER(code) != ?', ['mainbox']);
                    });
            })
            // Equivalent: NOT (cash type OR system mainBox)
            ->where(function ($q) {
                $q->where('is_active', true)->orWhere('show_in_accounting', true);
            })
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return Vault::query()->whereIn('id', $ids)->update([
            'is_active' => false,
            'show_in_accounting' => false,
            'updated_at' => now(),
        ]);
    }

    /**
     * Soft-delete vault (+ deactivate). Soft-deletes technical user only when no movements.
     * Essential mainBox cannot be deleted.
     *
     * @return array{vault:Vault, legacy_user_deleted:bool}
     */
    public function softDelete(Vault $vault): array
    {
        if ($this->isEssentialMainBox($vault)) {
            throw new InvalidArgumentException('لا يمكن حذف الصندوق الرئيسي (mainBox).');
        }

        $legacyId = (int) ($vault->legacy_user_id ?? 0);
        $hasMovements = $legacyId > 0 && SystemWalletService::vaultHasMovements($legacyId);
        if ($hasMovements) {
            throw new InvalidArgumentException('لا يمكن حذف قاصة عليها حركات مالية.');
        }

        return DB::transaction(function () use ($vault, $legacyId) {
            $vault->forceFill(['is_active' => false])->save();
            $vault->delete();

            $legacyDeleted = false;
            if ($legacyId > 0) {
                $user = User::query()->find($legacyId);
                if ($user && ! SystemWalletService::vaultHasMovements($legacyId)) {
                    $user->delete();
                    $legacyDeleted = true;
                }
            }

            Log::info('Vault soft-deleted', [
                'vault_id' => $vault->id,
                'legacy_user_id' => $legacyId,
                'legacy_user_deleted' => $legacyDeleted,
            ]);

            return [
                'vault' => $vault,
                'legacy_user_deleted' => $legacyDeleted,
            ];
        });
    }

    public function isEssentialMainBox(Vault $vault): bool
    {
        if (strcasecmp((string) $vault->code, 'mainBox') === 0) {
            return true;
        }

        $email = (string) ($vault->legacyUser?->email ?? '');
        if ($email === '' && $vault->legacy_user_id) {
            $email = (string) (User::withTrashed()->where('id', (int) $vault->legacy_user_id)->value('email') ?? '');
        }

        return strcasecmp($email, 'mainBox@account.com') === 0;
    }

    /**
     * Cash balance for a vault from its ledger account (or system cash for mainBox).
     */
    public function cashBalance(Vault $vault, string $currency): float
    {
        $currency = $currency === 'IQD' ? 'IQD' : '$';
        $ownerId = (int) $vault->owner_id;

        if ($this->isEssentialMainBox($vault)) {
            return $this->ledger->cashAccount($ownerId, $currency)->balance($currency);
        }

        if ($vault->ledger_account_id) {
            $account = LedgerAccount::query()->find((int) $vault->ledger_account_id);
            if ($account) {
                return $account->balance($currency);
            }
        }

        return 0.0;
    }

    /**
     * Temporary helper while wallets table still exists.
     */
    public function resolveWalletIdForLegacyUser(int $userId): ?int
    {
        if ($userId <= 0 || ! Schema::hasTable('wallets')) {
            return null;
        }

        $id = DB::table('wallets')->where('user_id', $userId)->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Resolve vault id for a legacy technical user (cash box).
     */
    public function resolveVaultIdForLegacyUser(int $userId): ?int
    {
        if ($userId <= 0 || ! Schema::hasTable('vaults')) {
            return null;
        }

        $id = Vault::query()->where('legacy_user_id', $userId)->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Find active cash-box vault by legacy user id.
     */
    public function findCashVaultByLegacyUser(int $ownerId, int $legacyUserId): ?Vault
    {
        if ($legacyUserId <= 0 || ! Schema::hasTable('vaults')) {
            return null;
        }

        $vault = Vault::query()
            ->forOwner($ownerId)
            ->where('legacy_user_id', $legacyUserId)
            ->first();

        return $vault && $vault->isCashBox() ? $vault : null;
    }

    /**
     * Normalize / slug a vault code (a-zA-Z0-9_-).
     */
    public function normalizeCode(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }

        return substr(preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $code) ?: '', 0, 64);
    }

    protected function uniqueCodeFromName(int $ownerId, string $name): string
    {
        $base = $this->normalizeCode(Str::slug($name, '-') ?: 'vault');
        if ($base === '') {
            $base = 'vault';
        }
        $base = substr($base, 0, 50);
        $code = $base;
        $i = 1;
        while (
            Vault::withTrashed()
                ->where('owner_id', $ownerId)
                ->where('code', $code)
                ->exists()
        ) {
            $code = substr($base, 0, 50).'-'.$i;
            $i++;
            if ($i > 9999) {
                $code = 'vault-'.Str::lower(Str::random(8));
                break;
            }
        }

        return $code;
    }

    protected function uniqueTechnicalEmail(int $ownerId, string $code): string
    {
        $safe = $this->normalizeCode($code) ?: 'vault';
        $email = 'vault-'.$ownerId.'-'.$safe.'@vault.local';
        $i = 1;
        while (User::withTrashed()->where('email', $email)->exists()) {
            $email = 'vault-'.$ownerId.'-'.$safe.'-'.$i.'@vault.local';
            $i++;
            if ($i > 9999) {
                $email = 'vault-'.$ownerId.'-'.Str::lower(Str::random(10)).'@vault.local';
                break;
            }
        }

        return $email;
    }

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
        foreach ($query->get() as $user) {
            $synced[] = $this->syncFromSystemUser($user);
        }

        $this->deactivateNonCashVaults($ownerId);

        return $synced;
    }

    /**
     * Create or update a vault row linked to a legacy system-wallet user.
     */
    public function syncFromSystemUser(User $user): Vault
    {
        $code = $this->codeFromUser($user);
        $type = $this->typeFromUser($user);

        $walletId = null;
        if (Schema::hasTable('wallets')) {
            $walletId = DB::table('wallets')->where('user_id', $user->id)->value('id');
        }

        $vault = Vault::withTrashed()
            ->where('owner_id', (int) $user->owner_id)
            ->where(function ($q) use ($user, $code) {
                $q->where('legacy_user_id', $user->id)
                    ->orWhere('code', $code);
            })
            ->first();

        if ($vault && $vault->trashed()) {
            return $vault;
        }

        if (! $vault) {
            $vault = new Vault([
                'owner_id' => (int) $user->owner_id,
                'code' => $code,
            ]);
        }

        $isMainBox = strcasecmp((string) $code, 'mainBox') === 0
            || strcasecmp((string) ($user->email ?? ''), 'mainBox@account.com') === 0
            || $type === Vault::TYPE_CASH;

        $fill = [
            'owner_id' => (int) $user->owner_id,
            'name' => $user->name ?: $code,
            'code' => $code,
            'type' => $isMainBox ? Vault::TYPE_CASH : $type,
            'is_active' => $vault->exists ? (bool) $vault->is_active : true,
            'show_in_accounting' => $isMainBox
                ? true
                : ($vault->exists ? (bool) $vault->show_in_accounting : true),
            'legacy_user_id' => (int) $user->id,
            'notes' => $vault->notes ?: ($isMainBox ? 'الصندوق الأساسي (قاصة نقد)' : 'Linked to legacy vault user'),
        ];
        if (Schema::hasColumn('vaults', 'wallet_id')) {
            $fill['wallet_id'] = $walletId ? (int) $walletId : null;
        }

        $vault->fill($fill);

        if ($isMainBox && Schema::hasTable('ledger_accounts')) {
            $this->ledger->ensureSystemAccounts((int) $user->owner_id);
            $cashId = DB::table('ledger_accounts')
                ->where('owner_id', (int) $user->owner_id)
                ->where('code', LedgerService::CODE_CASH_USD)
                ->value('id');
            if ($cashId) {
                $vault->ledger_account_id = (int) $cashId;
            }
        }

        $vault->save();

        return $vault->fresh(['legacyUser', 'ledgerAccount']);
    }

    /**
     * Ensure الصندوق الرئيسي exists as a vault row (type=cash, show_in_accounting).
     */
    public function ensureMainBoxVault(int $ownerId): Vault
    {
        if (! Schema::hasTable('vaults')) {
            throw new RuntimeException('جدول القاصات غير موجود — شغّل php artisan migrate');
        }

        $mainBox = app(SystemWalletService::class)->requireMainBox($ownerId);
        $vault = $this->syncFromSystemUser($mainBox);
        $this->deactivateNonCashVaults($ownerId);

        return $vault->fresh(['legacyUser', 'ledgerAccount']);
    }

    /**
     * Vault that receives client/trader payments (دفعات الزبائن) — must be a cash box.
     */
    public function resolveReceiptsVault(int $ownerId): Vault
    {
        $mainVault = $this->ensureMainBoxVault($ownerId);

        if (! Schema::hasTable('system_config') || ! Schema::hasColumn('system_config', 'default_receipts_vault_id')) {
            return $mainVault;
        }

        $configuredId = (int) (DB::table('system_config')->value('default_receipts_vault_id') ?? 0);
        if ($configuredId <= 0) {
            return $mainVault;
        }

        $configured = Vault::query()
            ->with(['legacyUser'])
            ->forOwner($ownerId)
            ->active()
            ->cashBoxes()
            ->where('id', $configuredId)
            ->whereNotNull('legacy_user_id')
            ->first();

        if ($configured && $configured->isCashBox()) {
            return $configured;
        }

        return $mainVault;
    }

    /**
     * Legacy user id of the receipts vault (target for client payments).
     */
    public function receiptsCashUserId(int $ownerId): int
    {
        $vault = $this->resolveReceiptsVault($ownerId);
        $legacyId = (int) ($vault->legacy_user_id ?? 0);
        if ($legacyId <= 0) {
            throw new RuntimeException('قاصة استلام دفعات الزبائن غير مرتبطة بمستخدم تقني.');
        }

        return $legacyId;
    }

    /**
     * Persist default receipts vault (admin). Null / 0 → use mainBox. Must be cash box.
     */
    public function setDefaultReceiptsVaultId(int $ownerId, ?int $vaultId): Vault
    {
        $mainVault = $this->ensureMainBoxVault($ownerId);

        if (! Schema::hasTable('system_config')) {
            throw new RuntimeException('جدول إعدادات النظام غير موجود.');
        }
        if (! Schema::hasColumn('system_config', 'default_receipts_vault_id')) {
            throw new RuntimeException('عمود default_receipts_vault_id غير موجود — شغّل php artisan migrate');
        }

        $saveId = null;
        if ($vaultId && $vaultId > 0) {
            $vault = Vault::query()
                ->forOwner($ownerId)
                ->active()
                ->cashBoxes()
                ->where('id', $vaultId)
                ->whereNotNull('legacy_user_id')
                ->first();
            if (! $vault || ! $vault->isCashBox()) {
                throw new InvalidArgumentException('القاصة المحددة يجب أن تكون نقدية (صندوق/بنك/خزنة).');
            }
            $saveId = (int) $vault->id;
        } else {
            $saveId = (int) $mainVault->id;
        }

        $row = DB::table('system_config')->orderBy('id')->first();
        if (! $row) {
            DB::table('system_config')->insert([
                'default_receipts_vault_id' => $saveId,
            ]);
        } else {
            DB::table('system_config')->where('id', $row->id)->update([
                'default_receipts_vault_id' => $saveId,
            ]);
        }

        return $this->resolveReceiptsVault($ownerId);
    }

    /**
     * Vault that pays car purchase cost (صرف المشتريات) — must be a cash box.
     * Separate from قاصة استلام دفعات الزبائن.
     */
    public function resolvePurchasesVault(int $ownerId): Vault
    {
        $mainVault = $this->ensureMainBoxVault($ownerId);

        if (! Schema::hasTable('system_config') || ! Schema::hasColumn('system_config', 'default_purchases_vault_id')) {
            return $mainVault;
        }

        $configuredId = (int) (DB::table('system_config')->value('default_purchases_vault_id') ?? 0);
        if ($configuredId <= 0) {
            return $mainVault;
        }

        $configured = Vault::query()
            ->with(['legacyUser'])
            ->forOwner($ownerId)
            ->active()
            ->cashBoxes()
            ->where('id', $configuredId)
            ->whereNotNull('legacy_user_id')
            ->first();

        if ($configured && $configured->isCashBox()) {
            return $configured;
        }

        return $mainVault;
    }

    /**
     * Legacy user id of the purchases vault (source for car purchase cost outflow).
     */
    public function purchasesCashUserId(int $ownerId): int
    {
        $vault = $this->resolvePurchasesVault($ownerId);
        $legacyId = (int) ($vault->legacy_user_id ?? 0);
        if ($legacyId <= 0) {
            throw new RuntimeException('قاصة صرف المشتريات غير مرتبطة بمستخدم تقني.');
        }

        return $legacyId;
    }

    /**
     * Persist default purchases vault (admin). Null / 0 → use mainBox. Must be cash box.
     */
    public function setDefaultPurchasesVaultId(int $ownerId, ?int $vaultId): Vault
    {
        $mainVault = $this->ensureMainBoxVault($ownerId);

        if (! Schema::hasTable('system_config')) {
            throw new RuntimeException('جدول إعدادات النظام غير موجود.');
        }
        if (! Schema::hasColumn('system_config', 'default_purchases_vault_id')) {
            throw new RuntimeException('عمود default_purchases_vault_id غير موجود — شغّل php artisan migrate');
        }

        $saveId = null;
        if ($vaultId && $vaultId > 0) {
            $vault = Vault::query()
                ->forOwner($ownerId)
                ->active()
                ->cashBoxes()
                ->where('id', $vaultId)
                ->whereNotNull('legacy_user_id')
                ->first();
            if (! $vault || ! $vault->isCashBox()) {
                throw new InvalidArgumentException('القاصة المحددة يجب أن تكون نقدية (صندوق/بنك/خزنة).');
            }
            $saveId = (int) $vault->id;
        } else {
            $saveId = (int) $mainVault->id;
        }

        $row = DB::table('system_config')->orderBy('id')->first();
        if (! $row) {
            DB::table('system_config')->insert([
                'default_purchases_vault_id' => $saveId,
            ]);
        } else {
            DB::table('system_config')->where('id', $row->id)->update([
                'default_purchases_vault_id' => $saveId,
            ]);
        }

        return $this->resolvePurchasesVault($ownerId);
    }

    /**
     * Active cash-box vaults for owner (قاصات النظام list).
     */
    public function listForOwner(int $ownerId, bool $activeOnly = true): Collection
    {
        $query = Vault::query()
            ->with(['legacyUser', 'ledgerAccount'])
            ->forOwner($ownerId)
            ->cashBoxes()
            ->orderBy('name');

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Accounting page orange shortcut buttons — cash vaults only, never traders.
     *
     * Shape matches legacy flaggedWallets: { id: legacy_user_id, name, wallet }.
     */
    public function accountingShortcutUsers(int $ownerId): Collection
    {
        if (! Schema::hasTable('vaults')) {
            return collect();
        }

        return Vault::query()
            ->with(['legacyUser'])
            ->forOwner($ownerId)
            ->cashBoxes()
            ->accountingShortcuts()
            ->orderBy('name')
            ->get()
            ->map(function (Vault $vault) {
                $user = $vault->legacyUser;
                if (! $user) {
                    return null;
                }
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
     * Rows for Vaults Index page «القاصات» — cash boxes only.
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
            ->with(['legacyUser', 'ledgerAccount'])
            ->forOwner($ownerId)
            ->active()
            ->cashBoxes()
            ->whereNotNull('legacy_user_id')
            ->orderBy('name')
            ->get();

        if ($vaults->isEmpty()) {
            return collect();
        }

        $legacyIds = $vaults->pluck('legacy_user_id')->map(fn ($id) => (int) $id)->all();
        $movementIds = array_fill_keys(SystemWalletService::idsWithMovements($legacyIds), true);

        $balanceByUser = $this->balancesForLegacyUsers($ownerId, $legacyIds, $vaults);

        return $vaults->map(function (Vault $vault) use ($movementIds, $balanceByUser) {
            $legacyId = (int) $vault->legacy_user_id;
            $hasMovements = isset($movementIds[$legacyId]);

            $isEssential = strcasecmp((string) $vault->code, 'mainBox') === 0
                || strcasecmp((string) ($vault->legacyUser?->email ?? ''), 'mainBox@account.com') === 0;

            return (object) [
                'id' => $legacyId,
                'vault_id' => $vault->id,
                'name' => $vault->name,
                'phone' => null,
                'email' => $vault->legacyUser?->email,
                'type_id' => $vault->legacyUser?->type_id,
                'created_at' => $vault->created_at,
                'show_in_dashboard' => (bool) $vault->show_in_accounting,
                'show_in_accounting' => (bool) $vault->show_in_accounting,
                'car_count' => 0,
                'car_count_completed' => 0,
                'car_total_un_pay' => 0,
                'balance' => $balanceByUser[$legacyId] ?? $this->cashBalance($vault, '$'),
                'has_movements' => $hasMovements,
                'can_delete' => ! $hasMovements && ! $isEssential,
                'is_essential' => $isEssential,
                'is_vault' => true,
                'vault_code' => $vault->code,
                'vault_type' => $vault->type,
                'notes' => $vault->notes,
            ];
        })->sortByDesc(fn ($row) => (float) $row->balance)->values();
    }

    /**
     * @param  list<int>  $userIds
     * @param  Collection<int, Vault>|null  $vaults
     * @return array<int, float>
     */
    protected function balancesForLegacyUsers(int $ownerId, array $userIds, ?Collection $vaults = null): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return [];
        }

        $out = [];

        $vaults = $vaults ?? Vault::query()
            ->forOwner($ownerId)
            ->whereIn('legacy_user_id', $userIds)
            ->get();

        foreach ($vaults as $vault) {
            $legacyId = (int) ($vault->legacy_user_id ?? 0);
            if ($legacyId <= 0) {
                continue;
            }
            // Prefer vault ledger / system cash when available.
            if ($vault->ledger_account_id || $this->isEssentialMainBox($vault)) {
                $out[$legacyId] = $this->cashBalance($vault, '$');
            }
        }

        $missing = array_diff($userIds, array_keys($out));
        if ($missing !== [] && Schema::hasTable('ledger_accounts') && Schema::hasTable('journal_lines')) {
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
                    if (! isset($out[$uid])) {
                        $out[$uid] = (float) $row->bal;
                    }
                }
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
