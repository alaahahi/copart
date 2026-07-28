<?php



namespace App\Services;



use App\Models\User;

use App\Models\UserType;

use App\Models\Wallet;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Schema;

use RuntimeException;



/**

 * Provisions the legacy "account" wallet users (mainBox / Dubai / Iran / …)

 * that AccountingCacheService and AccountingController still depend on.

 *

 * Ledger chart (1100 Cash, …) is separate — see LedgerService::ensureSystemAccounts.

 * Both are required: cash ledger for double-entry, mainBox User+Wallet for UI/API.

 *

 * Soft-deleted vaults are never recreated. Optional expense vaults are only

 * provisioned via seeding (includeOptional), not on every loadAccounts call.

 */

class SystemWalletService

{

    /**

     * Email → Arabic display name (matches production dump + AccountingCacheService keys).

     *

     * @return array<string, string>

     */

    public static function defaults(): array

    {

        return array_merge(self::requiredCore(), self::optionalVaults());

    }



    /**

     * Core boxes needed for cash posting APIs. Auto-created when missing (never if soft-deleted).

     *

     * @return array<string, string>

     */

    public static function requiredCore(): array

    {

        return [

            'mainBox@account.com' => 'الصندوق',

        ];

    }



    /**

     * Optional system vaults (expense / supplier / contracts). Seed once; do not recreate on request.

     *

     * @return array<string, string>

     */

    public static function optionalVaults(): array

    {

        return [

            'main@account.com' => 'الخزينة',

            'in@account.com' => 'الدخل',

            'out@account.com' => 'الخرج',

            'debt@account.com' => 'دين',

            'transfers@account.com' => 'الحوالات',

            'supplier-out' => 'مدفوعات المورد',

            'supplier-debt' => 'دين المورد',

            'howler' => 'مصاريف أربيل',

            'shipping-coc' => 'مصاريف شهادة COC',

            'border' => 'مصاريف الحدود',

            'iran' => 'مصاريف ايران',

            'dubai' => 'مصاريف دبي',

            'online-contracts' => 'عقود أونلاين دولار',

            'online-contracts-dinar' => 'عقود أونلاين دينار',

            'online-contracts-debt' => 'دين عقود أونلاين دولار',

            'online-contracts-debit-dinar' => 'دين عقود أونلاين دينار',

        ];

    }



    /**

     * Emails / usernames that identify system cash boxes (قاصات النظام).

     *

     * @return list<string>

     */

    public static function systemEmails(): array

    {

        return array_keys(self::defaults());

    }



    /**
     * Legacy client-type rows used as company / commission vaults (not real merchants).
     *
     * @return list<string>
     */
    public static function legacyClientVaultNames(): array
    {
        return [
            'مصاريف الشركة',
            'Company Expenses',
            'عمولة امريكا',
            'عمولة كندا',
        ];
    }

    /**
     * True when the display name is a known or commission-style vault (e.g. «عمولة امريكا»).
     */
    public static function isLegacyClientVaultName(?string $name): bool
    {
        $name = trim((string) $name);
        if ($name === '') {
            return false;
        }

        if (in_array($name, self::legacyClientVaultNames(), true)) {
            return true;
        }

        // Commission / قاصة عمولة boxes created as client users
        return mb_strpos($name, 'عمولة') === 0;
    }

    /**
     * True when the user is a system/cash vault — not a normal merchant.
     * Covers type=account wallets + legacy client vaults like «مصاريف الشركة» / «عمولة …».
     */
    public static function isSystemVaultUser(?object $user, ?int $accountTypeId = null): bool
    {
        if (! $user) {
            return false;
        }

        if ($accountTypeId !== null && (int) ($user->type_id ?? 0) === (int) $accountTypeId) {
            return true;
        }

        $email = $user->email ?? null;

        if (is_string($email) && $email !== '' && in_array($email, self::systemEmails(), true)) {
            return true;
        }

        return self::isLegacyClientVaultName($user->name ?? null);
    }

    /**
     * Restrict a users query to system vaults (account wallets + legacy client vaults).
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function scopeSystemVaults($query, int $accountTypeId, int $clientTypeId): void
    {
        $emails = self::systemEmails();
        $names = self::legacyClientVaultNames();

        $query->where(function ($outer) use ($accountTypeId, $clientTypeId, $emails, $names) {
            $outer->where('users.type_id', $accountTypeId)
                ->orWhere(function ($legacy) use ($clientTypeId, $emails, $names) {
                    $legacy->where('users.type_id', $clientTypeId)
                        ->where(function ($inner) use ($emails, $names) {
                            $inner->whereIn('users.email', $emails)
                                ->orWhereIn('users.name', $names)
                                ->orWhere('users.name', 'like', 'عمولة%');
                        });
                });
        });
    }

    /**
     * Exclude system vaults from a (usually client-type) users query.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function scopeExcludeSystemVaults($query): void
    {
        $emails = self::systemEmails();
        $names = self::legacyClientVaultNames();

        $query->where(function ($q) use ($emails) {
            $q->whereNull('users.email')
                ->orWhere('users.email', '')
                ->orWhereNotIn('users.email', $emails);
        })
            ->whereNotIn('users.name', $names)
            ->where('users.name', 'not like', 'عمولة%');
    }


    /**

     * Idempotent: create missing system wallet users + wallets for an owner.

     *

     * Soft-deleted vaults are skipped (never recreated).

     * By default only required core (mainBox). Pass $includeOptional=true from seeders.

     *

     * @return list<User>

     */

    public function ensureForOwner(int $ownerId, bool $includeOptional = false): array

    {

        $accountTypeId = UserType::query()->where('name', 'account')->value('id');

        if (! $accountTypeId) {

            throw new RuntimeException('نوع المستخدم account غير موجود — شغّل UserTypeSeeder أولاً.');

        }



        $map = $includeOptional ? self::defaults() : self::requiredCore();

        $created = [];

        $password = Hash::make(bin2hex(random_bytes(16)));

        $today = Carbon::now()->format('Y-m-d');

        $year = (int) Carbon::now()->format('Y');



        foreach ($map as $email => $name) {

            // Respect explicit soft-delete: do not resurrect deleted vaults.

            $user = User::withTrashed()->where('email', $email)->first();



            if ($user && $user->trashed()) {

                continue;

            }



            if (! $user) {

                $user = User::query()->create([

                    'email' => $email,

                    'name' => $name,

                    'password' => $password,

                    'type_id' => $accountTypeId,

                    'owner_id' => $ownerId,

                    'is_band' => 0,

                    'show_in_dashboard' => false,

                    'created' => $today,

                    'year_date' => $year,

                ]);

            } elseif ((int) $user->owner_id !== $ownerId || (int) $user->type_id !== (int) $accountTypeId) {

                // Re-attach to this owner if row existed from another install path without owner.

                $user->forceFill([

                    'owner_id' => $ownerId,

                    'type_id' => $accountTypeId,

                    'name' => $user->name ?: $name,

                ])->save();

            }



            Wallet::query()->firstOrCreate(

                ['user_id' => $user->id],

                [

                    'balance' => 0,

                    'balance_dinar' => 0,

                    'card' => 0,

                ]

            );



            $user->load('wallet');

            // Keep vaults table in sync (UI source of truth for قاصات النظام).
            if (Schema::hasTable('vaults')) {
                app(VaultService::class)->syncFromSystemUser($user);
            }

            $created[] = $user;

        }



        return $created;

    }



    /**

     * Ensure main cash box exists and return it (with wallet). Used by payment APIs.

     */

    public function requireMainBox(int $ownerId): User

    {

        $this->ensureForOwner($ownerId, false);



        $mainBox = User::with('wallet')

            ->where('owner_id', $ownerId)

            ->where('email', 'mainBox@account.com')

            ->first();



        if (! $mainBox || ! $mainBox->wallet) {

            $trashed = User::onlyTrashed()->where('email', 'mainBox@account.com')->exists();

            if ($trashed) {

                throw new RuntimeException('تم حذف صندوق المحاسبة (mainBox). أعد استعادته من السجلات ولا يُنشأ تلقائياً بعد الحذف.');

            }

            throw new RuntimeException('لم يتم إنشاء صندوق المحاسبة (mainBox). تحقق من البذر وأنواع المستخدمين.');

        }



        return $mainBox;

    }



    /**
     * User IDs that have any financial movement (wallet tx, ledger lines, treasury).
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public static function idsWithMovements(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return [];
        }

        $found = [];

        $walletHits = DB::table('transactions')
            ->join('wallets', 'wallets.id', '=', 'transactions.wallet_id')
            ->whereIn('wallets.user_id', $userIds)
            ->whereNull('transactions.deleted_at')
            ->distinct()
            ->pluck('wallets.user_id');
        foreach ($walletHits as $id) {
            $found[(int) $id] = true;
        }

        $morphHits = DB::table('transactions')
            ->whereIn('morphed_id', $userIds)
            ->where('morphed_type', User::class)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('morphed_id');
        foreach ($morphHits as $id) {
            $found[(int) $id] = true;
        }

        if (Schema::hasTable('ledger_accounts') && Schema::hasTable('journal_lines')) {
            $ledgerHits = DB::table('ledger_accounts')
                ->join('journal_lines', 'journal_lines.ledger_account_id', '=', 'ledger_accounts.id')
                ->whereIn('ledger_accounts.party_id', $userIds)
                ->where('ledger_accounts.party_type', User::class)
                ->distinct()
                ->pluck('ledger_accounts.party_id');
            foreach ($ledgerHits as $id) {
                $found[(int) $id] = true;
            }
        }

        if (Schema::hasTable('company_treasury_entries')) {
            $treasuryQuery = DB::table('company_treasury_entries')
                ->whereIn('user_id', $userIds);
            if (Schema::hasColumn('company_treasury_entries', 'deleted_at')) {
                $treasuryQuery->whereNull('deleted_at');
            }
            foreach ($treasuryQuery->distinct()->pluck('user_id') as $id) {
                $found[(int) $id] = true;
            }
        }

        return array_map('intval', array_keys($found));
    }



    /**
     * True when this vault/user has wallet, ledger, or treasury movements.
     */
    public static function vaultHasMovements(int|object $user): bool
    {
        $id = is_object($user) ? (int) ($user->id ?? 0) : (int) $user;
        if ($id <= 0) {
            return false;
        }

        return in_array($id, self::idsWithMovements([$id]), true);
    }

}


