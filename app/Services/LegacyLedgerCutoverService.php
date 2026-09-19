<?php

namespace App\Services;

use App\Models\Car;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * One-time cutover: legacy wallets + car remainders → double-entry opening balances.
 *
 * Does NOT replay historical transactions. Idempotent via source=legacy_cutover + reference.
 */
class LegacyLedgerCutoverService
{
    public const SOURCE = 'legacy_cutover';

    public function __construct(
        protected LedgerService $ledger,
        protected ClientAccountService $clientAccounts,
        protected VaultService $vaults
    ) {
    }

    /**
     * @return array{
     *   dry_run: bool,
     *   owners: list<int>,
     *   clients_provisioned: int,
     *   vaults_synced: int,
     *   posted: int,
     *   skipped: int,
     *   warnings: list<string>,
     *   comparisons: list<array<string,mixed>>,
     *   trial_balance: array<int, array{currency:string,debit:float,credit:float}>
     * }
     */
    public function run(?int $ownerId = null, bool $dryRun = true): array
    {
        if (! Schema::hasTable('ledger_accounts') || ! Schema::hasTable('journal_entries')) {
            throw new InvalidArgumentException('جداول القيد المزدوج غير موجودة — شغّل migrate أولاً.');
        }

        $owners = $this->resolveOwners($ownerId);
        $warnings = [];
        $comparisons = [];
        $posted = 0;
        $skipped = 0;
        $clientsProvisioned = 0;
        $vaultsSynced = 0;

        $clientTypeId = (int) UserType::query()->where('name', 'client')->value('id');
        $accountTypeId = (int) UserType::query()->where('name', 'account')->value('id');

        if ($clientTypeId <= 0) {
            throw new InvalidArgumentException('نوع المستخدم client غير موجود في user_type.');
        }

        $walletRows = $this->loadWalletSnapshot();

        foreach ($owners as $oid) {
            $this->ledger->ensureSystemAccounts($oid);

            if (! $dryRun) {
                $synced = $this->vaults->syncAllForOwner($oid);
                $vaultsSynced += count($synced);
                $this->vaults->ensureMainBoxVault($oid);
            } else {
                // Count how many would sync
                $vaultsSynced += User::query()
                    ->where('owner_id', $oid)
                    ->where('type_id', $accountTypeId)
                    ->count();
            }

            $clients = User::query()
                ->where('owner_id', $oid)
                ->where('type_id', $clientTypeId)
                ->orderBy('id')
                ->get();

            foreach ($clients as $client) {
                $clientsProvisioned++;
                if (! $dryRun) {
                    $this->clientAccounts->provisionForClient($client);
                } else {
                    // Ensure codes would exist conceptually — skip DB writes
                }

                $remaining = $this->carRemainingForClient((int) $client->id);
                $wallet = $walletRows[(int) $client->id] ?? ['balance' => 0.0, 'balance_dinar' => 0.0];

                $arBalanceAfter = null;
                $qasaUsdAfter = null;
                $qasaIqdAfter = null;

                // AR from unpaid cars
                if (abs($remaining) >= 0.005) {
                    $r = $this->postSignedOpening(
                        $oid,
                        fn () => $this->ledger->clientReceivableAccount($oid, (int) $client->id),
                        $remaining,
                        '$',
                        "قطع إرثي: ذمم سيارات تاجر #{$client->id} ({$client->name})",
                        'legacy_ar',
                        (int) $client->id,
                        $dryRun
                    );
                    $posted += $r['posted'];
                    $skipped += $r['skipped'];
                    if ($r['warning']) {
                        $warnings[] = $r['warning'];
                    }
                }

                $withQasa = LedgerService::clientHasQasa($client) || abs((float) $wallet['balance']) >= 0.005 || abs((float) $wallet['balance_dinar']) >= 0.005;

                if ($withQasa && ! LedgerService::clientHasQasa($client) && (abs((float) $wallet['balance']) >= 0.005 || abs((float) $wallet['balance_dinar']) >= 0.005)) {
                    $warnings[] = "تاجر #{$client->id} ({$client->name}) لديه رصيد محفظة دون show_in_dashboard — سيتم إنشاء عهدة 1210/1220.";
                }

                if ($withQasa) {
                    if (! $dryRun) {
                        // Force custody accounts even if flag was off but wallet has balance.
                        $this->ledger->ensureClientLedgerAccounts($oid, (int) $client->id, true);
                    }

                    if (abs((float) $wallet['balance']) >= 0.005) {
                        $r = $this->postSignedOpening(
                            $oid,
                            fn () => $this->ledger->clientQasaAccount($oid, (int) $client->id, '$'),
                            (float) $wallet['balance'],
                            '$',
                            "قطع إرثي: عهدة دولار تاجر #{$client->id} ({$client->name})",
                            'legacy_qasa_usd',
                            (int) $client->id,
                            $dryRun
                        );
                        $posted += $r['posted'];
                        $skipped += $r['skipped'];
                        if ($r['warning']) {
                            $warnings[] = $r['warning'];
                        }
                    }

                    if (abs((float) $wallet['balance_dinar']) >= 0.005) {
                        $r = $this->postSignedOpening(
                            $oid,
                            fn () => $this->ledger->clientQasaAccount($oid, (int) $client->id, 'IQD'),
                            (float) $wallet['balance_dinar'],
                            'IQD',
                            "قطع إرثي: عهدة دينار تاجر #{$client->id} ({$client->name})",
                            'legacy_qasa_iqd',
                            (int) $client->id,
                            $dryRun
                        );
                        $posted += $r['posted'];
                        $skipped += $r['skipped'];
                        if ($r['warning']) {
                            $warnings[] = $r['warning'];
                        }
                    }
                }

                if (! $dryRun) {
                    $ar = $this->ledger->clientReceivableAccount($oid, (int) $client->id);
                    $arBalanceAfter = $ar->balance('$');
                    if ($withQasa) {
                        $qasaUsdAfter = $this->ledger->clientQasaAccount($oid, (int) $client->id, '$')->balance('$');
                        $qasaIqdAfter = $this->ledger->clientQasaAccount($oid, (int) $client->id, 'IQD')->balance('IQD');
                    }
                }

                $comparisons[] = [
                    'kind' => 'client',
                    'owner_id' => $oid,
                    'user_id' => (int) $client->id,
                    'name' => $client->name,
                    'car_remaining' => round($remaining, 2),
                    'wallet_usd' => round((float) $wallet['balance'], 2),
                    'wallet_iqd' => round((float) $wallet['balance_dinar'], 2),
                    'ledger_ar' => $arBalanceAfter !== null ? round($arBalanceAfter, 2) : null,
                    'ledger_qasa_usd' => $qasaUsdAfter !== null ? round($qasaUsdAfter, 2) : null,
                    'ledger_qasa_iqd' => $qasaIqdAfter !== null ? round($qasaIqdAfter, 2) : null,
                ];
            }

            // Cash / vault users (type account)
            $cashUsers = User::query()
                ->where('owner_id', $oid)
                ->where('type_id', $accountTypeId)
                ->orderBy('id')
                ->get();

            foreach ($cashUsers as $cashUser) {
                $wallet = $walletRows[(int) $cashUser->id] ?? ['balance' => 0.0, 'balance_dinar' => 0.0];
                $usd = (float) $wallet['balance'];
                $iqd = (float) $wallet['balance_dinar'];

                if (abs($usd) < 0.005 && abs($iqd) < 0.005) {
                    continue;
                }

                if (! $dryRun) {
                    $this->vaults->syncFromSystemUser($cashUser);
                }

                $isMainBox = strcasecmp((string) ($cashUser->email ?? ''), 'mainBox@account.com') === 0
                    || strcasecmp((string) ($cashUser->name ?? ''), 'الصندوق') === 0;

                if (abs($usd) >= 0.005) {
                    $r = $this->postSignedOpening(
                        $oid,
                        function () use ($oid, $isMainBox, $cashUser) {
                            if ($isMainBox) {
                                return $this->ledger->systemAccount($oid, LedgerService::CODE_CASH_USD);
                            }
                            // Prefer vault COA if linked; else system cash.
                            $vault = \App\Models\Vault::query()
                                ->where('legacy_user_id', (int) $cashUser->id)
                                ->first();
                            if ($vault && $vault->ledger_account_id) {
                                $acc = LedgerAccount::find($vault->ledger_account_id);
                                if ($acc) {
                                    return $acc;
                                }
                            }

                            return $this->ledger->systemAccount($oid, LedgerService::CODE_CASH_USD);
                        },
                        $usd,
                        '$',
                        "قطع إرثي: نقد دولار — {$cashUser->name} (#{$cashUser->id})",
                        'legacy_cash_usd',
                        (int) $cashUser->id,
                        $dryRun
                    );
                    $posted += $r['posted'];
                    $skipped += $r['skipped'];
                    if ($r['warning']) {
                        $warnings[] = $r['warning'];
                    }
                }

                if (abs($iqd) >= 0.005) {
                    $r = $this->postSignedOpening(
                        $oid,
                        fn () => $this->ledger->systemAccount($oid, LedgerService::CODE_CASH_IQD),
                        $iqd,
                        'IQD',
                        "قطع إرثي: نقد دينار — {$cashUser->name} (#{$cashUser->id})",
                        'legacy_cash_iqd',
                        (int) $cashUser->id,
                        $dryRun
                    );
                    $posted += $r['posted'];
                    $skipped += $r['skipped'];
                    if ($r['warning']) {
                        $warnings[] = $r['warning'];
                    }
                }

                $comparisons[] = [
                    'kind' => 'cash',
                    'owner_id' => $oid,
                    'user_id' => (int) $cashUser->id,
                    'name' => $cashUser->name,
                    'car_remaining' => null,
                    'wallet_usd' => round($usd, 2),
                    'wallet_iqd' => round($iqd, 2),
                    'ledger_ar' => null,
                    'ledger_qasa_usd' => null,
                    'ledger_qasa_iqd' => null,
                ];
            }

            // Treasury net report (informational)
            if (Schema::hasTable('company_treasury_entries')) {
                $netUsd = (float) DB::table('company_treasury_entries')
                    ->where('owner_id', $oid)
                    ->when(Schema::hasColumn('company_treasury_entries', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                    ->where(function ($q) {
                        $q->where('currency', '$')->orWhere('currency', 'USD');
                    })
                    ->selectRaw('COALESCE(SUM(COALESCE(debit,0) - COALESCE(credit,0)),0) as net')
                    ->value('net');
                if (abs($netUsd) >= 0.005) {
                    $warnings[] = "owner #{$oid}: company_treasury_entries صافي {$netUsd} \$ — لم يُرحَّل تلقائياً (راجع يدوياً إن لزم).";
                }
            }
        }

        $trial = $dryRun ? [] : $this->trialBalanceSummary($ownerId);

        Log::info('Legacy ledger cutover finished', [
            'dry_run' => $dryRun,
            'posted' => $posted,
            'skipped' => $skipped,
            'clients' => $clientsProvisioned,
        ]);

        return [
            'dry_run' => $dryRun,
            'owners' => $owners,
            'clients_provisioned' => $clientsProvisioned,
            'vaults_synced' => $vaultsSynced,
            'posted' => $posted,
            'skipped' => $skipped,
            'warnings' => $warnings,
            'comparisons' => $comparisons,
            'trial_balance' => $trial,
        ];
    }

    /**
     * @return list<int>
     */
    private function resolveOwners(?int $ownerId): array
    {
        if ($ownerId) {
            return [$ownerId];
        }

        return User::query()
            ->whereNotNull('owner_id')
            ->distinct()
            ->orderBy('owner_id')
            ->pluck('owner_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{balance:float,balance_dinar:float}>
     */
    private function loadWalletSnapshot(): array
    {
        $table = Schema::hasTable('legacy_cutover_wallets')
            ? 'legacy_cutover_wallets'
            : (Schema::hasTable('wallets') ? 'wallets' : null);

        if (! $table) {
            return [];
        }

        $rows = DB::table($table)->get(['user_id', 'balance', 'balance_dinar']);
        $map = [];
        foreach ($rows as $row) {
            $uid = (int) $row->user_id;
            $map[$uid] = [
                'balance' => (float) ($row->balance ?? 0),
                'balance_dinar' => (float) ($row->balance_dinar ?? 0),
            ];
        }

        return $map;
    }

    private function carRemainingForClient(int $clientId): float
    {
        $q = Car::query()
            ->where('client_id', $clientId)
            ->whereNull('deleted_at');

        // remaining = total_s - paid - discount - damage_compensation
        $hasDamage = Schema::hasColumn('car', 'damage_compensation');
        $expr = $hasDamage
            ? 'COALESCE(SUM(COALESCE(total_s,0) - COALESCE(paid,0) - COALESCE(discount,0) - COALESCE(damage_compensation,0)),0)'
            : 'COALESCE(SUM(COALESCE(total_s,0) - COALESCE(paid,0) - COALESCE(discount,0)),0)';

        return round((float) $q->selectRaw($expr.' as remaining')->value('remaining'), 2);
    }

    /**
     * @param  callable():LedgerAccount  $accountResolver
     * @return array{posted:int,skipped:int,warning:?string}
     */
    private function postSignedOpening(
        int $ownerId,
        callable $accountResolver,
        float $signedAmount,
        string $currency,
        string $memo,
        string $referenceType,
        int $referenceId,
        bool $dryRun
    ): array {
        $currency = $currency === 'IQD' ? 'IQD' : '$';
        $amount = round($signedAmount, 2);

        if (abs($amount) < 0.005) {
            return ['posted' => 0, 'skipped' => 0, 'warning' => null];
        }

        $exists = JournalEntry::query()
            ->where('source', self::SOURCE)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where(function ($q) use ($currency) {
                $q->where('currency', $currency)->orWhereNull('currency');
            })
            ->whereNull('deleted_at')
            ->exists();

        // Also match by memo+currency when currency column null historically
        if (! $exists) {
            $exists = JournalEntry::query()
                ->where('source', self::SOURCE)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->where('memo', $memo)
                ->whereNull('deleted_at')
                ->exists();
        }

        if ($exists) {
            return ['posted' => 0, 'skipped' => 1, 'warning' => null];
        }

        if ($dryRun) {
            return ['posted' => 1, 'skipped' => 0, 'warning' => null];
        }

        $account = $accountResolver();
        $opening = $this->ledger->systemAccount($ownerId, LedgerService::CODE_OPENING);
        $abs = abs($amount);
        $debitNature = in_array($account->type, ['asset', 'expense'], true);

        // Positive amount → normal opening (debit asset / credit opening).
        // Negative → reverse (credit asset / debit opening) = credit balance on asset.
        $increase = $amount > 0;

        if ($debitNature) {
            $lines = $increase
                ? [
                    ['account_id' => $account->id, 'debit' => $abs, 'credit' => 0, 'currency' => $currency, 'memo' => $memo],
                    ['account_id' => $opening->id, 'debit' => 0, 'credit' => $abs, 'currency' => $currency, 'memo' => $memo],
                ]
                : [
                    ['account_id' => $opening->id, 'debit' => $abs, 'credit' => 0, 'currency' => $currency, 'memo' => $memo],
                    ['account_id' => $account->id, 'debit' => 0, 'credit' => $abs, 'currency' => $currency, 'memo' => $memo],
                ];
        } else {
            $lines = $increase
                ? [
                    ['account_id' => $opening->id, 'debit' => $abs, 'credit' => 0, 'currency' => $currency, 'memo' => $memo],
                    ['account_id' => $account->id, 'debit' => 0, 'credit' => $abs, 'currency' => $currency, 'memo' => $memo],
                ]
                : [
                    ['account_id' => $account->id, 'debit' => $abs, 'credit' => 0, 'currency' => $currency, 'memo' => $memo],
                    ['account_id' => $opening->id, 'debit' => 0, 'credit' => $abs, 'currency' => $currency, 'memo' => $memo],
                ];
        }

        $this->ledger->post([
            'owner_id' => $ownerId,
            'entry_date' => now()->toDateString(),
            'memo' => $memo,
            'source' => self::SOURCE,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'currency' => $currency,
        ], $lines);

        return ['posted' => 1, 'skipped' => 0, 'warning' => null];
    }

    /**
     * @return array<int, array{currency:string,debit:float,credit:float}>
     */
    public function trialBalanceSummary(?int $ownerId = null): array
    {
        $q = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->whereNull('je.deleted_at')
            ->when($ownerId, fn ($qq) => $qq->where('je.owner_id', $ownerId))
            ->groupBy('jl.currency')
            ->selectRaw('jl.currency, ROUND(SUM(jl.debit),2) as debit, ROUND(SUM(jl.credit),2) as credit');

        return $q->get()->map(fn ($r) => [
            'currency' => (string) $r->currency,
            'debit' => (float) $r->debit,
            'credit' => (float) $r->credit,
        ])->all();
    }
}
