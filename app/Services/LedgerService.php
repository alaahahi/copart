<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\Transactions;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class LedgerService
{
    /**
     * System chart codes — single source of truth (used by seeder + posting).
     */
    public const CODE_CASH_USD = '1100';
    public const CODE_CASH_IQD = '1110';
    public const CODE_TREASURY_USD = '1120';
    public const CODE_TREASURY_IQD = '1130';
    public const CODE_CLIENT_AR_PREFIX = '1200';
    /** Per-trader accounting-visibility USD custody: 1210-{clientId} (not a system vault) */
    public const CODE_CLIENT_QASA_USD_PREFIX = '1210';
    /** Per-trader accounting-visibility IQD custody: 1220-{clientId} (not a system vault) */
    public const CODE_CLIENT_QASA_IQD_PREFIX = '1220';
    public const CODE_REVENUE = '4100';
    public const CODE_EXPENSE = '5100';
    /** Car purchase cost expense (child of 5100) — not general expenses */
    public const CODE_CAR_PURCHASES = '5110';
    public const CODE_OPENING = '3900';
    public const CODE_TRADER_PROFITS = '3200';

    /** @var array<int, true> */
    private array $carPurchaseExpenseReclassified = [];

    /**
     * users column that means «هذا التاجر لديه قاصة» (عرض بالمحاسبة).
     * When true: provision قاصة USD + قاصة IQD ledger accounts in addition to AR.
     * When turned off later: accounts are kept (no delete) — only stop creating/using them in UI lists.
     */
    public const CLIENT_HAS_QASA_FLAG = 'show_in_dashboard';

    /**
     * Default system chart rows (idempotent seed / ensure).
     *
     * @return list<array{code:string,name:string,name_ar:string,type:string,currency:?string}>
     */
    public static function systemAccountDefaults(): array
    {
        return [
            ['code' => self::CODE_CASH_USD, 'name' => 'Cash USD', 'name_ar' => 'الصندوق (دولار)', 'type' => 'asset', 'currency' => '$'],
            ['code' => self::CODE_CASH_IQD, 'name' => 'Cash IQD', 'name_ar' => 'الصندوق (دينار)', 'type' => 'asset', 'currency' => 'IQD'],
            ['code' => self::CODE_TREASURY_USD, 'name' => 'Company Treasury USD', 'name_ar' => 'قاصة الشركة دولار', 'type' => 'asset', 'currency' => '$'],
            ['code' => self::CODE_TREASURY_IQD, 'name' => 'Company Treasury IQD', 'name_ar' => 'قاصة الشركة دينار', 'type' => 'asset', 'currency' => 'IQD'],
            ['code' => self::CODE_REVENUE, 'name' => 'Shipping Revenue', 'name_ar' => 'إيرادات الشحن', 'type' => 'income', 'currency' => null],
            ['code' => self::CODE_EXPENSE, 'name' => 'General Expenses', 'name_ar' => 'مصاريف عامة', 'type' => 'expense', 'currency' => null],
            ['code' => self::CODE_OPENING, 'name' => 'Opening Capital', 'name_ar' => 'رأس المال الافتتاحي', 'type' => 'equity', 'currency' => null],
            ['code' => self::CODE_TRADER_PROFITS, 'name' => 'Trader Profits Reserve', 'name_ar' => 'حساب أرباح التجار', 'type' => 'equity', 'currency' => null],
        ];
    }

    /**
     * Ensure default chart of accounts exists for an owner (runtime safe — does not overwrite renames).
     */
    public function ensureSystemAccounts(int $ownerId): void
    {
        foreach (self::systemAccountDefaults() as $row) {
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

    /**
     * Seed/sync system accounts for an owner (updateOrCreate — used by ChartOfAccountsSeeder).
     */
    public function seedSystemAccounts(int $ownerId): void
    {
        foreach (self::systemAccountDefaults() as $row) {
            LedgerAccount::updateOrCreate(
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

    /**
     * Client receivable / car payments control account: 1200-{clientId}.
     * Used by postClientPayment (Cash ↔ AR) and wallet sync.
     */
    public function clientReceivableAccount(int $ownerId, int $clientId): LedgerAccount
    {
        $this->ensureSystemAccounts($ownerId);
        $client = User::find($clientId);
        $label = $client?->name ?? (string) $clientId;
        $code = self::CODE_CLIENT_AR_PREFIX . '-' . $clientId;

        return LedgerAccount::firstOrCreate(
            ['owner_id' => $ownerId, 'code' => $code],
            [
                'name' => 'AR / Car payments #' . $clientId,
                'name_ar' => 'ذمم الزبون / دفعات السيارات: ' . $label,
                'type' => 'asset',
                'currency' => null,
                'party_type' => User::class,
                'party_id' => $clientId,
                'is_system' => false,
                'is_active' => true,
            ]
        );
    }

    /**
     * Per-trader accounting-visibility custody (USD or IQD): 1210-{id} / 1220-{id}.
     * Not a system vault (قاصة) — those live in the vaults table.
     */
    public function clientQasaAccount(int $ownerId, int $clientId, string $currency): LedgerAccount
    {
        $this->ensureSystemAccounts($ownerId);
        $client = User::find($clientId);
        $label = $client?->name ?? (string) $clientId;
        $isIqd = $currency === 'IQD';
        $code = ($isIqd ? self::CODE_CLIENT_QASA_IQD_PREFIX : self::CODE_CLIENT_QASA_USD_PREFIX) . '-' . $clientId;

        return LedgerAccount::firstOrCreate(
            ['owner_id' => $ownerId, 'code' => $code],
            [
                'name' => ($isIqd ? 'Trader custody IQD #' : 'Trader custody USD #') . $clientId,
                'name_ar' => ($isIqd ? 'عهدة محاسبة دينار: ' : 'عهدة محاسبة دولار: ') . $label,
                'type' => 'asset',
                'currency' => $isIqd ? 'IQD' : '$',
                'party_type' => User::class,
                'party_id' => $clientId,
                'is_system' => false,
                'is_active' => true,
            ]
        );
    }

    /**
     * Provision ledger accounts for a trader.
     * Always: AR (ذمم / دفعات السيارات).
     * If $withQasa (or client.show_in_dashboard): also custody USD + IQD (عرض بالمحاسبة).
     * Never deletes accounts when accounting visibility is turned off.
     *
     * @return array{ar:LedgerAccount,qasa_usd:?LedgerAccount,qasa_iqd:?LedgerAccount}
     */
    public function ensureClientLedgerAccounts(int $ownerId, int $clientId, ?bool $withQasa = null): array
    {
        $client = User::find($clientId);
        if ($withQasa === null) {
            $withQasa = $client ? (bool) $client->{self::CLIENT_HAS_QASA_FLAG} : false;
        }

        $ar = $this->clientReceivableAccount($ownerId, $clientId);
        $qasaUsd = null;
        $qasaIqd = null;

        if ($withQasa) {
            $qasaUsd = $this->clientQasaAccount($ownerId, $clientId, '$');
            $qasaIqd = $this->clientQasaAccount($ownerId, $clientId, 'IQD');
        }

        return [
            'ar' => $ar,
            'qasa_usd' => $qasaUsd,
            'qasa_iqd' => $qasaIqd,
        ];
    }

    public static function clientHasQasa(User $client): bool
    {
        return (bool) ($client->{self::CLIENT_HAS_QASA_FLAG} ?? false);
    }

    public function systemAccount(int $ownerId, string $code): LedgerAccount
    {
        $this->ensureSystemAccounts($ownerId);

        return LedgerAccount::where('owner_id', $ownerId)->where('code', $code)->firstOrFail();
    }

    /**
     * Create a custom (non-system) chart account for an owner.
     *
     * @param  array{
     *   code:string,
     *   name_ar:string,
     *   name?:?string,
     *   type:string,
     *   currency?:?string,
     *   parent_id?:?int,
     *   is_active?:bool,
     *   show_in_accounting?:bool
     * }  $data
     */
    public function createAccount(int $ownerId, array $data): LedgerAccount
    {
        $this->ensureSystemAccounts($ownerId);

        $code = $this->normalizeAccountCode((string) ($data['code'] ?? ''));
        $nameAr = trim((string) ($data['name_ar'] ?? ''));
        $type = (string) ($data['type'] ?? '');
        $currency = $this->normalizeAccountCurrency($data['currency'] ?? null);
        $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' && $data['parent_id'] !== null
            && ! is_array($data['parent_id']) && ! is_object($data['parent_id'])
            && is_numeric($data['parent_id'])
            ? (int) $data['parent_id']
            : null;
        $isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;
        // Default ON — new expense/commission accounts appear as Accounting purple chips.
        $showInAccounting = array_key_exists('show_in_accounting', $data)
            ? (bool) $data['show_in_accounting']
            : true;
        $english = trim((string) ($data['name'] ?? ''));
        if ($english === '') {
            $english = $nameAr;
        }

        if ($nameAr === '') {
            throw new InvalidArgumentException('اسم الحساب مطلوب.');
        }

        if (! in_array($type, ['asset', 'liability', 'equity', 'income', 'expense'], true)) {
            throw new InvalidArgumentException('نوع الحساب غير صالح.');
        }

        if (LedgerAccount::query()->where('owner_id', $ownerId)->where('code', $code)->exists()) {
            throw new InvalidArgumentException('رمز الحساب مستخدم مسبقاً.');
        }

        // مصروف/عمولة بلا أب → تحت «مصاريف عامة» 5100 تلقائياً
        if ($parentId === null && $type === 'expense') {
            $expenseRoot = $this->systemAccount($ownerId, self::CODE_EXPENSE);
            $parentId = $expenseRoot ? (int) $expenseRoot->id : null;
        }

        $parent = $this->resolveParentAccount($ownerId, $parentId, $type);

        $account = LedgerAccount::create([
            'owner_id' => $ownerId,
            'code' => $code,
            'name' => $english,
            'name_ar' => $nameAr,
            'type' => $type,
            'currency' => $currency,
            'parent_id' => $parent?->id,
            'is_system' => false,
            'is_active' => $isActive,
            'show_in_accounting' => $showInAccounting,
        ]);

        Log::info('Ledger account created', [
            'account_id' => $account->id,
            'code' => $account->code,
            'type' => $account->type,
            'parent_id' => $account->parent_id,
            'owner_id' => $ownerId,
            'by' => Auth::id(),
        ]);

        return $account->fresh();
    }

    /**
     * Update chart account fields.
     * Code/type/currency change only when the account has no journal lines.
     * System accounts: name (and parent) only — never code/type/currency.
     *
     * @param  array{
     *   name_ar:string,
     *   name?:?string,
     *   code?:?string,
     *   type?:?string,
     *   currency?:?string,
     *   parent_id?:?int|string|null,
     *   is_active?:bool
     * }  $data
     */
    public function updateAccount(int $ownerId, int $accountId, array $data): LedgerAccount
    {
        $account = LedgerAccount::query()
            ->where('owner_id', $ownerId)
            ->findOrFail($accountId);

        $nameAr = trim((string) ($data['name_ar'] ?? ''));
        if ($nameAr === '') {
            throw new InvalidArgumentException('اسم الحساب مطلوب.');
        }

        $english = trim((string) ($data['name'] ?? ''));
        if ($english === '') {
            $english = $nameAr;
        }

        $hasMovements = $account->hasMovements();
        $updates = [
            'name_ar' => $nameAr,
            'name' => $english,
        ];

        if (array_key_exists('parent_id', $data)) {
            $parentId = $data['parent_id'] === '' || $data['parent_id'] === null
                ? null
                : (int) $data['parent_id'];
            if ($parentId === (int) $account->id) {
                throw new InvalidArgumentException('لا يمكن أن يكون الحساب أباً لنفسه.');
            }
            $typeForParent = array_key_exists('type', $data) && ! $account->is_system && ! $hasMovements
                ? (string) $data['type']
                : (string) $account->type;
            $parent = $this->resolveParentAccount($ownerId, $parentId, $typeForParent, $account->id);
            $updates['parent_id'] = $parent?->id;
        }

        if (! $account->is_system && ! $hasMovements) {
            if (array_key_exists('code', $data) && $data['code'] !== null && trim((string) $data['code']) !== '') {
                $code = $this->normalizeAccountCode((string) $data['code']);
                $taken = LedgerAccount::query()
                    ->where('owner_id', $ownerId)
                    ->where('code', $code)
                    ->where('id', '!=', $account->id)
                    ->exists();
                if ($taken) {
                    throw new InvalidArgumentException('رمز الحساب مستخدم مسبقاً.');
                }
                $updates['code'] = $code;
            }

            if (array_key_exists('type', $data) && $data['type'] !== null && $data['type'] !== '') {
                $type = (string) $data['type'];
                if (! in_array($type, ['asset', 'liability', 'equity', 'income', 'expense'], true)) {
                    throw new InvalidArgumentException('نوع الحساب غير صالح.');
                }
                $updates['type'] = $type;
            }

            if (array_key_exists('currency', $data)) {
                $updates['currency'] = $this->normalizeAccountCurrency($data['currency']);
            }
        } elseif (
            $hasMovements
            && (
                (array_key_exists('code', $data) && $data['code'] !== null && trim((string) $data['code']) !== '' && $this->normalizeAccountCode((string) $data['code']) !== $account->code)
                || (array_key_exists('type', $data) && $data['type'] !== null && $data['type'] !== '' && (string) $data['type'] !== $account->type)
                || (array_key_exists('currency', $data) && $this->normalizeAccountCurrency($data['currency']) !== $account->currency)
            )
        ) {
            throw new RuntimeException('لا يمكن تعديل الرمز أو النوع أو العملة لحساب عليه قيود.');
        }

        if (array_key_exists('is_active', $data) && ! $account->is_system) {
            $updates['is_active'] = (bool) $data['is_active'];
        }

        if (array_key_exists('show_in_accounting', $data)) {
            $updates['show_in_accounting'] = (bool) $data['show_in_accounting'];
        }

        $account->forceFill($updates)->save();

        Log::info('Ledger account updated', [
            'account_id' => $account->id,
            'code' => $account->code,
            'owner_id' => $ownerId,
            'has_movements' => $hasMovements,
            'by' => Auth::id(),
        ]);

        return $account->fresh();
    }

    /**
     * Rename display names for an owner-scoped ledger account.
     * System accounts may be renamed; codes/types stay unchanged.
     */
    public function renameAccount(int $ownerId, int $accountId, string $nameAr, ?string $name = null): LedgerAccount
    {
        return $this->updateAccount($ownerId, $accountId, [
            'name_ar' => $nameAr,
            'name' => $name,
        ]);
    }

    /**
     * Soft-deactivate a non-system account (never hard-delete).
     * Keeps journal history intact; account disappears from active chart.
     * Accounts with movements are deactivated (not deleted) — history preserved.
     */
    public function deactivateAccount(int $ownerId, int $accountId): LedgerAccount
    {
        $account = LedgerAccount::query()
            ->where('owner_id', $ownerId)
            ->findOrFail($accountId);

        if ($account->is_system) {
            throw new RuntimeException('لا يمكن حذف أو إيقاف الحسابات النظامية.');
        }

        if (! $account->is_active) {
            throw new RuntimeException('الحساب موقوف مسبقاً.');
        }

        $hadLines = $account->lines()->exists();

        $account->forceFill(['is_active' => false])->save();

        Log::info('Ledger account deactivated', [
            'account_id' => $account->id,
            'code' => $account->code,
            'owner_id' => $ownerId,
            'had_journal_lines' => $hadLines,
            'by' => Auth::id(),
        ]);

        return $account->fresh();
    }

    protected function normalizeAccountCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if ($code === '' || ! preg_match('/^[A-Z0-9][A-Z0-9\-_\/.]{0,30}$/', $code)) {
            throw new InvalidArgumentException('رمز الحساب غير صالح (أحرف/أرقام و - _ / . فقط).');
        }

        return $code;
    }

    protected function normalizeAccountCurrency(mixed $currency): ?string
    {
        if ($currency === null || $currency === '' || $currency === 'multi') {
            return null;
        }

        $currency = (string) $currency;
        if (! in_array($currency, ['$', 'IQD'], true)) {
            throw new InvalidArgumentException('العملة غير صالحة (USD أو IQD أو متعدد).');
        }

        return $currency;
    }

    /**
     * Parent must belong to same owner, same type, and not create a cycle.
     */
    protected function resolveParentAccount(int $ownerId, ?int $parentId, string $type, ?int $excludeId = null): ?LedgerAccount
    {
        if ($parentId === null) {
            return null;
        }

        $parent = LedgerAccount::query()
            ->where('owner_id', $ownerId)
            ->where('is_active', true)
            ->find($parentId);

        if (! $parent) {
            throw new InvalidArgumentException('الحساب الأب غير موجود.');
        }

        if ($parent->type !== $type) {
            throw new InvalidArgumentException('الحساب الأب يجب أن يكون من نفس النوع.');
        }

        if ($excludeId !== null) {
            $cursor = $parent;
            $guard = 0;
            while ($cursor && $guard++ < 50) {
                if ((int) $cursor->id === (int) $excludeId) {
                    throw new InvalidArgumentException('لا يمكن اختيار حساب فرعي كأب (حلقة في الشجرة).');
                }
                $cursor = $cursor->parent_id
                    ? LedgerAccount::query()->where('owner_id', $ownerId)->find($cursor->parent_id)
                    : null;
            }
        }

        return $parent;
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
     * Client payment / cash-box receipt against AR: Debit Cash (+ Discount expense) / Credit AR.
     * $amount = cash received; $discount = AR reduction without cash (expense).
     */
    public function postClientPayment(
        int $ownerId,
        int $clientId,
        float $amount,
        string $currency,
        string $memo,
        $reference = null,
        float $discount = 0,
        ?int $cashUserId = null
    ): JournalEntry {
        $amount = abs($amount);
        $discount = abs($discount);
        // Prefer the configured receipts vault ledger (mainBox → 1100/1110; other vaults → party mirror).
        $cash = $cashUserId
            ? $this->walletLedgerAccount($ownerId, $cashUserId, $currency)
            : $this->cashAccount($ownerId, $currency);
        $ar = $this->clientReceivableAccount($ownerId, $clientId);

        $lines = [
            ['account_id' => $cash->id, 'debit' => $amount, 'credit' => 0, 'currency' => $currency, 'memo' => $memo],
        ];

        if ($discount > 0) {
            $expense = $this->systemAccount($ownerId, self::CODE_EXPENSE);
            $lines[] = ['account_id' => $expense->id, 'debit' => $discount, 'credit' => 0, 'currency' => $currency, 'memo' => $memo];
            $lines[] = ['account_id' => $ar->id, 'debit' => 0, 'credit' => $amount + $discount, 'currency' => $currency, 'memo' => $memo];
        } else {
            $lines[] = ['account_id' => $ar->id, 'debit' => 0, 'credit' => $amount, 'currency' => $currency, 'memo' => $memo];
        }

        return $this->post([
            'owner_id' => $ownerId,
            'entry_date' => now()->toDateString(),
            'memo' => $memo,
            'source' => 'wallet',
            'currency' => $currency,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id ?? null,
        ], $lines);
    }

    /**
     * Cash-box receipt (وصل قبض): Debit Cash / Credit Revenue.
     * Optional $cashUserId routes to that user's cash vault ledger (not always 1100).
     */
    public function postCashReceipt(int $ownerId, float $amount, string $currency, string $memo, $reference = null, ?int $cashUserId = null): JournalEntry
    {
        $cash = $cashUserId
            ? $this->walletLedgerAccount($ownerId, $cashUserId, $currency)
            : $this->cashAccount($ownerId, $currency);
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
     * Cash-box payment (وصل سحب): Debit Expense / Credit Cash.
     * Optional $cashUserId routes to that user's cash vault ledger.
     * Optional $expenseAccountId posts to a specific expense COA (else system 5100).
     */
    public function postCashDisbursement(
        int $ownerId,
        float $amount,
        string $currency,
        string $memo,
        $reference = null,
        ?int $cashUserId = null,
        ?int $expenseAccountId = null,
        ?string $entryDate = null
    ): JournalEntry {
        $cash = $cashUserId
            ? $this->walletLedgerAccount($ownerId, $cashUserId, $currency)
            : $this->cashAccount($ownerId, $currency);
        $expense = $this->resolveExpenseAccount($ownerId, $expenseAccountId);

        return $this->post([
            'owner_id' => $ownerId,
            'entry_date' => $entryDate ?: now()->toDateString(),
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
     * Resolve an owner expense COA, or fall back to system General Expenses (5100).
     */
    public function resolveExpenseAccount(int $ownerId, ?int $expenseAccountId = null): LedgerAccount
    {
        if ($expenseAccountId) {
            $account = LedgerAccount::query()
                ->where('owner_id', $ownerId)
                ->where('id', $expenseAccountId)
                ->where('is_active', true)
                ->first();

            if (! $account) {
                throw new InvalidArgumentException('حساب المصروف غير موجود.');
            }
            if ($account->type !== 'expense') {
                throw new InvalidArgumentException('الحساب المحدد ليس حساب مصروف في دليل الحسابات.');
            }

            return $account;
        }

        return $this->systemAccount($ownerId, self::CODE_EXPENSE);
    }

    /**
     * Expense (+ optional commission-like income) COA rows for the Vaults «مصاريف وعمولات» tab.
     *
     * @param  bool  $onlyShowInAccounting  When true, only accounts flagged for Accounting purple chips.
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function listExpenseCommissionAccounts(
        int $ownerId,
        string $currency = '$',
        bool $onlyShowInAccounting = false
    ): \Illuminate\Support\Collection {
        $this->ensureSystemAccounts($ownerId);
        // Ensure مشتريات سيارات exists and pull mis-posted car costs off 5100.
        $this->resolveCarPurchasesExpenseAccount($ownerId);
        $currency = $currency === 'IQD' ? 'IQD' : '$';

        $accounts = LedgerAccount::query()
            ->where('owner_id', $ownerId)
            ->where('is_active', true)
            ->when($onlyShowInAccounting, fn ($q) => $q->where('show_in_accounting', true))
            ->where(function ($q) {
                $q->where('type', 'expense')
                    ->orWhere(function ($inner) {
                        $inner->where('type', 'income')
                            ->where('is_system', false)
                            ->where(function ($n) {
                                $n->where('name_ar', 'like', '%عمول%')
                                    ->orWhere('name', 'like', '%commission%')
                                    ->orWhere('name', 'like', '%Commission%')
                                    ->orWhere('code', 'like', '42%');
                            });
                    });
            })
            ->withCount('lines')
            ->orderBy('code')
            ->get();

        return $accounts->map(function (LedgerAccount $account) use ($currency) {
            $hasMovements = ((int) ($account->lines_count ?? 0)) > 0;
            $label = $account->name_ar ?: $account->name;
            $kind = $account->type === 'income' ? 'commission' : 'expense';
            if ($account->type === 'expense' && (
                str_contains((string) $account->name_ar, 'عمول')
                || stripos((string) $account->name, 'commission') !== false
            )) {
                $kind = 'commission';
            }

            return [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $label,
                'name_ar' => $account->name_ar,
                'type' => $account->type,
                'kind' => $kind,
                'is_system' => (bool) $account->is_system,
                'show_in_accounting' => (bool) $account->show_in_accounting,
                'has_movements' => $hasMovements,
                'can_disburse' => $account->type === 'expense',
                'balance' => $account->balance($currency),
                'balance_dinar' => $account->balance('IQD'),
                'currency' => $currency,
            ];
        })->values();
    }

    /**
     * Toggle Accounting purple-chip visibility for an expense/commission COA account.
     */
    public function toggleShowInAccounting(LedgerAccount $account, ?bool $show = null): LedgerAccount
    {
        if (! in_array($account->type, ['expense', 'income'], true)) {
            throw new InvalidArgumentException('عرض بالمحاسبة متاح لحسابات المصاريف والعمولات فقط.');
        }

        $account->show_in_accounting = $show !== null
            ? $show
            : ! (bool) $account->show_in_accounting;
        $account->save();

        Log::info('Ledger account show_in_accounting toggled', [
            'account_id' => $account->id,
            'code' => $account->code,
            'show_in_accounting' => (bool) $account->show_in_accounting,
            'by' => Auth::id(),
        ]);

        return $account->fresh() ?? $account;
    }

    /**
     * Suggest next free expense code under 51xx (or 52xx for commission).
     */
    public function suggestExpenseAccountCode(int $ownerId, string $kind = 'expense'): string
    {
        $this->ensureSystemAccounts($ownerId);
        $prefix = $kind === 'commission' ? '52' : '51';
        $existing = LedgerAccount::query()
            ->where('owner_id', $ownerId)
            ->where('code', 'like', $prefix.'%')
            ->pluck('code');

        $max = 0;
        foreach ($existing as $code) {
            if (preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', (string) $code, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        // 5100 → suffix 00; next free child becomes 5101, etc.
        $next = $max + 1;
        if ($next < 1) {
            $next = 1;
        }

        $candidate = $prefix.str_pad((string) $next, 2, '0', STR_PAD_LEFT);
        $guard = 0;
        while (
            LedgerAccount::query()->where('owner_id', $ownerId)->where('code', $candidate)->exists()
            && $guard++ < 500
        ) {
            $next++;
            $candidate = strlen((string) $next) <= 2
                ? $prefix.str_pad((string) $next, 2, '0', STR_PAD_LEFT)
                : $prefix.$next;
        }

        return $candidate;
    }

    /**
     * Route wallet increase to the correct electronic accounts.
     * client → AR/Revenue | cash_box → Cash/Revenue | other system → party mirror
     */
    public function postWalletIncrease(int $ownerId, int $userId, float $amount, string $currency, string $memo, $reference = null): JournalEntry
    {
        $kind = $this->walletPostingKind($ownerId, $userId);

        return match ($kind) {
            'client' => $this->postClientDebtIncrease($ownerId, $userId, $amount, $currency, $memo, $reference),
            'cash_box' => $this->postCashReceipt($ownerId, $amount, $currency, $memo, $reference, $userId),
            default => $this->postSystemWalletIncrease($ownerId, $userId, $amount, $currency, $memo, $reference),
        };
    }

    /**
     * Route wallet decrease to the correct electronic accounts.
     * Car purchase / dedicated purchases-vault outflows → مصروف مشتريات سيارات (not 5100).
     */
    public function postWalletDecrease(int $ownerId, int $userId, float $amount, string $currency, string $memo, $reference = null): JournalEntry
    {
        $kind = $this->walletPostingKind($ownerId, $userId);

        return match ($kind) {
            'client' => $this->postClientPayment($ownerId, $userId, $amount, $currency, $memo, $reference),
            'cash_box' => $this->postCashDisbursement(
                $ownerId,
                $amount,
                $currency,
                $memo,
                $reference,
                $userId,
                $this->resolveExpenseAccountIdForCashDisbursement($ownerId, $userId, $reference, $memo)
            ),
            default => $this->postSystemWalletDecrease($ownerId, $userId, $amount, $currency, $memo, $reference),
        };
    }

    /**
     * Resolve expense COA for a cash-box disbursement.
     * Car purchases (morph Car / purchases vault ≠ mainBox / purchase memo) → 5110 «مشتريات سيارات».
     * Explicit null → caller/resolveExpenseAccount falls back to general 5100.
     */
    public function resolveExpenseAccountIdForCashDisbursement(
        int $ownerId,
        ?int $cashUserId,
        $reference = null,
        ?string $memo = null
    ): ?int {
        if (! $this->isCarPurchaseDisbursement($ownerId, $cashUserId, $reference, $memo)) {
            return null;
        }

        return (int) $this->resolveCarPurchasesExpenseAccount($ownerId)->id;
    }

    /**
     * Find or create the car-purchases expense account (prefer user-named مشتريات سيارات).
     */
    public function resolveCarPurchasesExpenseAccount(int $ownerId): LedgerAccount
    {
        $this->ensureSystemAccounts($ownerId);

        $named = LedgerAccount::query()
            ->where('owner_id', $ownerId)
            ->where('type', 'expense')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('code', self::CODE_CAR_PURCHASES)
                    ->orWhere('name_ar', 'مشتريات سيارات')
                    ->orWhere('name_ar', 'like', '%مشتريات سيارات%')
                    ->orWhere('name', 'like', '%Car Purchases%');
            })
            ->orderByRaw("CASE
                WHEN name_ar = 'مشتريات سيارات' THEN 0
                WHEN code = ? THEN 1
                ELSE 2
            END", [self::CODE_CAR_PURCHASES])
            ->first();

        if ($named) {
            $this->reclassifyMispostedCarPurchaseExpenses($ownerId, $named);

            return $named;
        }

        $parent = $this->systemAccount($ownerId, self::CODE_EXPENSE);
        $account = LedgerAccount::firstOrCreate(
            ['owner_id' => $ownerId, 'code' => self::CODE_CAR_PURCHASES],
            [
                'name' => 'Car Purchases',
                'name_ar' => 'مشتريات سيارات',
                'type' => 'expense',
                'currency' => null,
                'parent_id' => $parent?->id,
                'is_system' => true,
                'is_active' => true,
                'show_in_accounting' => true,
            ]
        );

        $this->reclassifyMispostedCarPurchaseExpenses($ownerId, $account);

        return $account->fresh() ?? $account;
    }

    /**
     * True when a cash outflow should hit car-purchases expense (not general 5100).
     */
    public function isCarPurchaseDisbursement(
        int $ownerId,
        ?int $cashUserId,
        $reference = null,
        ?string $memo = null
    ): bool {
        if ($reference instanceof Transactions) {
            $morph = (string) ($reference->morphed_type ?? '');
            if ($morph !== '' && (str_ends_with($morph, '\\Car') || $morph === 'App\\Models\\Car')) {
                return true;
            }
            $memo = $memo ?: (string) ($reference->description ?? '');
        }

        $text = (string) ($memo ?? '');
        if ($text !== '' && preg_match('/اضافة\s*سيارة\s*من\s*المشتريات|من\s*المشتريات\s*رقم\s*شانص/u', $text)) {
            return true;
        }

        if ($cashUserId && $cashUserId > 0 && \Illuminate\Support\Facades\Schema::hasTable('vaults')) {
            try {
                $vaults = app(VaultService::class);
                $purchasesVault = $vaults->resolvePurchasesVault($ownerId);
                $purchasesUserId = (int) ($purchasesVault->legacy_user_id ?? 0);
                $isMainBox = strcasecmp((string) $purchasesVault->code, 'mainBox') === 0;
                // Only when a dedicated purchases vault (not الصندوق) pays the outflow.
                if (! $isMainBox && $purchasesUserId > 0 && $purchasesUserId === (int) $cashUserId) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Vault config missing — fall through to general expense.
            }
        }

        return false;
    }

    /**
     * Move car-purchase expense lines wrongly posted on 5100 onto مشتريات سيارات.
     * Debit line only — journal stays balanced. Once per owner per request.
     */
    public function reclassifyMispostedCarPurchaseExpenses(int $ownerId, ?LedgerAccount $purchasesAccount = null): int
    {
        if (isset($this->carPurchaseExpenseReclassified[$ownerId])) {
            return 0;
        }
        $this->carPurchaseExpenseReclassified[$ownerId] = true;

        $general = $this->systemAccount($ownerId, self::CODE_EXPENSE);
        $purchases = $purchasesAccount ?? $this->resolveCarPurchasesExpenseAccount($ownerId);
        if ((int) $general->id === (int) $purchases->id) {
            return 0;
        }

        $lines = \App\Models\JournalLine::query()
            ->where('ledger_account_id', $general->id)
            ->where('debit', '>', 0)
            ->whereHas('entry', function ($q) use ($ownerId) {
                $q->where('owner_id', $ownerId);
            })
            ->with('entry:id,memo,reference_type,reference_id')
            ->get();

        $moved = 0;
        foreach ($lines as $line) {
            if (! $this->journalLineLooksLikeCarPurchase($line)) {
                continue;
            }
            $line->forceFill(['ledger_account_id' => (int) $purchases->id])->save();
            $moved++;
        }

        if ($moved > 0) {
            Log::info('Reclassified car-purchase expenses from 5100', [
                'owner_id' => $ownerId,
                'from_account_id' => $general->id,
                'to_account_id' => $purchases->id,
                'lines' => $moved,
            ]);
        }

        return $moved;
    }

    protected function journalLineLooksLikeCarPurchase(\App\Models\JournalLine $line): bool
    {
        // Memo-only: reference morph can be wrong/stale on legacy rows; avoid moving unrelated 5100 lines.
        $memo = (string) ($line->memo ?: $line->entry?->memo ?? '');
        if ($memo === '') {
            return false;
        }

        return (bool) preg_match(
            '/اضافة\s*سيارة\s*من\s*المشتريات|من\s*المشتريات\s*رقم\s*شانص|مرتجع\s*حذف\s*سيارة|تكلفة\s*شراء|تسعير\s*سيارة/u',
            $memo
        );
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

        // Cash vault linked by legacy_user_id → cash_box (real COA cash).
        if (\Illuminate\Support\Facades\Schema::hasTable('vaults')) {
            $vault = \App\Models\Vault::query()
                ->where('owner_id', $ownerId)
                ->where('legacy_user_id', $userId)
                ->first();
            if ($vault && $vault->isCashBox()) {
                return 'cash_box';
            }
        }

        $clientTypeId = (int) (\Illuminate\Support\Facades\Cache::get('user_type_client')
            ?? \App\Models\UserType::where('name', 'client')->value('id'));

        // Legacy commission / company expense rows were stored as client users — not traders.
        if (SystemWalletService::isSystemVaultUser($user)) {
            if (strcasecmp((string) $user->email, 'mainBox@account.com') === 0) {
                return 'cash_box';
            }

            return 'system';
        }

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

    /**
     * Ledger account that backs a user for posting / balances.
     * cash_box + mainBox → system Cash 1100/1110 (or vault.ledger_account_id for other cash vaults)
     * client & system → party mirror account (1200-{userId})
     */
    public function walletLedgerAccount(int $ownerId, int $userId, string $currency): LedgerAccount
    {
        $kind = $this->walletPostingKind($ownerId, $userId);

        if ($kind !== 'cash_box') {
            return $this->clientReceivableAccount($ownerId, $userId);
        }

        $currency = $currency === 'IQD' ? 'IQD' : '$';

        if (\Illuminate\Support\Facades\Schema::hasTable('vaults')) {
            $vault = \App\Models\Vault::query()
                ->where('owner_id', $ownerId)
                ->where('legacy_user_id', $userId)
                ->first();

            if ($vault && $vault->isCashBox()) {
                $isMainBox = strcasecmp((string) $vault->code, 'mainBox') === 0
                    || strcasecmp((string) ($vault->legacyUser?->email ?? ''), 'mainBox@account.com') === 0;

                // mainBox: currency-specific system cash 1100/1110
                if ($isMainBox) {
                    return $this->cashAccount($ownerId, $currency);
                }

                // Other cash vaults: single ledger account; currency lives on journal lines.
                if ($vault->ledger_account_id) {
                    $account = LedgerAccount::query()->find((int) $vault->ledger_account_id);
                    if ($account) {
                        return $account;
                    }
                }
            }
        }

        // Fallback: system cash (mainBox / legacy email)
        return $this->cashAccount($ownerId, $currency);
    }

    public function profitsAccount(int $ownerId): LedgerAccount
    {
        return $this->systemAccount($ownerId, self::CODE_TRADER_PROFITS);
    }

    /**
     * حركة بين الحسابات (Transfer between accounts): ONE balanced journal,
     * Debit destination account / Credit source account. Pure transfer — never
     * touches revenue/expense, so P&L stays unaffected by internal cash movement.
     */
    public function postAccountTransfer(
        int $ownerId,
        int $fromUserId,
        int $toUserId,
        float $amount,
        string $currency,
        string $memo,
        $reference = null,
        ?string $entryDate = null
    ): JournalEntry {
        if ($fromUserId === $toUserId) {
            throw new InvalidArgumentException('لا يمكن التحويل من وإلى نفس الحساب.');
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('مبلغ التحويل يجب أن يكون أكبر من صفر.');
        }

        $fromAccount = $this->walletLedgerAccount($ownerId, $fromUserId, $currency);
        $toAccount = $this->walletLedgerAccount($ownerId, $toUserId, $currency);

        return $this->post([
            'owner_id' => $ownerId,
            'entry_date' => $entryDate ?? now()->toDateString(),
            'memo' => $memo,
            'source' => 'account_transfer',
            'currency' => $currency,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id ?? null,
        ], [
            ['account_id' => $toAccount->id, 'debit' => $amount, 'credit' => 0, 'currency' => $currency, 'memo' => $memo],
            ['account_id' => $fromAccount->id, 'debit' => 0, 'credit' => $amount, 'currency' => $currency, 'memo' => $memo],
        ]);
    }

    /**
     * ترحيل أرباح التاجر (post trader profit to the Profits reserve):
     * Dr Shipping Revenue (4100) / Cr Trader Profits Reserve (3200).
     * This is an appropriation entry — it reclassifies revenue that has already
     * been recognized against the trader's account into a segregated equity
     * reserve, so the profit can later be tracked and withdrawn/distributed
     * independently from ongoing client AR/revenue activity. It never touches
     * the client's AR balance.
     */
    public function postTraderProfitAppropriation(
        int $ownerId,
        float $amount,
        string $currency,
        string $memo,
        $reference = null,
        ?string $entryDate = null
    ): JournalEntry {
        if ($amount <= 0) {
            throw new InvalidArgumentException('مبلغ الترحيل يجب أن يكون أكبر من صفر.');
        }

        $revenue = $this->systemAccount($ownerId, self::CODE_REVENUE);
        $profits = $this->profitsAccount($ownerId);

        return $this->post([
            'owner_id' => $ownerId,
            'entry_date' => $entryDate ?? now()->toDateString(),
            'memo' => $memo,
            'source' => 'trader_profit_post',
            'currency' => $currency,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id ?? null,
        ], [
            ['account_id' => $revenue->id, 'debit' => $amount, 'credit' => 0, 'currency' => $currency, 'memo' => $memo],
            ['account_id' => $profits->id, 'debit' => 0, 'credit' => $amount, 'currency' => $currency, 'memo' => $memo],
        ]);
    }

    /**
     * سحب من الأرباح (withdraw/distribute from the Profits reserve):
     * Dr Trader Profits Reserve (3200) / Cr Cash (1100/1110).
     * Standard profit-distribution pattern: cash leaves the box, the reserve
     * shrinks by the same amount. Caller must verify both balances beforehand.
     */
    public function postProfitWithdraw(
        int $ownerId,
        float $amount,
        string $currency,
        string $memo,
        $reference = null,
        ?string $entryDate = null
    ): JournalEntry {
        if ($amount <= 0) {
            throw new InvalidArgumentException('مبلغ السحب يجب أن يكون أكبر من صفر.');
        }

        $profits = $this->profitsAccount($ownerId);
        $cash = $this->cashAccount($ownerId, $currency);

        return $this->post([
            'owner_id' => $ownerId,
            'entry_date' => $entryDate ?? now()->toDateString(),
            'memo' => $memo,
            'source' => 'trader_profit_withdraw',
            'currency' => $currency,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id ?? null,
        ], [
            ['account_id' => $profits->id, 'debit' => $amount, 'credit' => 0, 'currency' => $currency, 'memo' => $memo],
            ['account_id' => $cash->id, 'debit' => 0, 'credit' => $amount, 'currency' => $currency, 'memo' => $memo],
        ]);
    }

    public function accountBalance(int $accountId, ?string $currency = null): float
    {
        $account = LedgerAccount::findOrFail($accountId);

        return $account->balance($currency);
    }

    /**
     * Resolve the real "money" ledger account a wallet transaction hit — the
     * physical cash/treasury box when one exists on the journal, otherwise the
     * asset-side party account (client AR or system-box mirror account).
     *
     * Some wallet legs are created alongside a parent cash-box leg and never
     * get their own journal_entry_id (e.g. a car-payment allocation on the
     * client's wallet is linked via parent_id to the mainBox receipt that
     * actually carries the journal). In that case we fall back to the
     * parent's journal — never inventing data, only reading what
     * LedgerService already posted at creation time.
     */
    public function resolveMoneyAccount(?Transactions $transaction): ?LedgerAccount
    {
        if (!$transaction) {
            return null;
        }

        $source = $transaction;

        if (!$source->journal_entry_id && $source->parent_id) {
            $parent = $source->relationLoaded('parent')
                ? $source->parent
                : Transactions::with('journalEntry.lines.account')->find($source->parent_id);

            if ($parent && $parent->journal_entry_id) {
                $source = $parent;
            }
        }

        if (!$source->journal_entry_id) {
            return null;
        }

        $entry = $source->relationLoaded('journalEntry')
            ? $source->journalEntry
            : JournalEntry::with('lines.account')->find($source->journal_entry_id);

        if (!$entry) {
            return null;
        }

        $lines = $entry->lines instanceof \Illuminate\Support\Collection
            ? $entry->lines
            : collect($entry->lines);

        $cashCodes = [self::CODE_CASH_USD, self::CODE_CASH_IQD, self::CODE_TREASURY_USD, self::CODE_TREASURY_IQD];

        // Prefer the physical cash/treasury box: it's literally "where the money went".
        $cashLine = $lines->first(fn ($line) => $line->account && in_array($line->account->code, $cashCodes, true));
        if ($cashLine) {
            return $cashLine->account;
        }

        // Otherwise fall back to the asset-side party account (AR / system mirror).
        $assetLine = $lines->first(fn ($line) => $line->account && $line->account->type === 'asset');
        if ($assetLine) {
            return $assetLine->account;
        }

        return $lines->first()?->account;
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
     * Restore soft-voided journal linked to a wallet transaction.
     */
    public function restoreJournalForTransaction($transaction): bool
    {
        $journalId = $transaction->journal_entry_id ?? null;

        if (!$journalId) {
            $journalId = JournalEntry::withTrashed()
                ->where('reference_type', \App\Models\Transactions::class)
                ->where('reference_id', $transaction->id)
                ->value('id');
        }

        return $this->restoreJournalEntry($journalId ? (int) $journalId : null);
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
     * Driver-safe SQL: bound string prefix + integer/text column (MySQL CONCAT / SQLite ||).
     */
    public static function sqlConcatBoundPrefixWithColumn(string $boundPlaceholder, string $column): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "({$boundPlaceholder} || {$column})";
        }

        return "CONCAT({$boundPlaceholder}, {$column})";
    }

    /**
     * Per-client ledger AR sum (wallet fallback removed — ledger only).
     */
    public function sumClientsReceivableWithFallback(int $ownerId, int $clientTypeId, string $currency = '$'): float
    {
        return $this->sumClientsReceivable($ownerId, $currency);
    }

    /**
     * No-op: balances are read from the ledger only (Wallet table is being removed).
     */
    public function syncWalletFromLedger(int $ownerId, int $clientId): void
    {
        // Intentionally empty — do not write wallets.balance.
    }

    /**
     * SQL expression (correlated) for client USD balance from ledger only.
     * Use as selectSub / selectRaw binding owner_id once.
     */
    public static function clientBalanceSqlSubquery(int $ownerId, string $currency = '$'): \Closure
    {
        $prefix = self::CODE_CLIENT_AR_PREFIX . '-';
        $codeExpr = self::sqlConcatBoundPrefixWithColumn('?', 'users.id');

        return function ($subquery) use ($ownerId, $currency, $prefix, $codeExpr) {
            $subquery->selectRaw(
                "COALESCE(
                    (
                        SELECT ROUND(COALESCE(SUM(jl.debit), 0) - COALESCE(SUM(jl.credit), 0), 2)
                        FROM ledger_accounts AS la
                        INNER JOIN journal_lines AS jl ON jl.ledger_account_id = la.id
                        INNER JOIN journal_entries AS je ON je.id = jl.journal_entry_id AND je.deleted_at IS NULL
                        WHERE la.owner_id = ?
                          AND la.code = {$codeExpr}
                          AND jl.currency = ?
                    ),
                    0
                )",
                [$ownerId, $prefix, $currency]
            );
        };
    }
}
