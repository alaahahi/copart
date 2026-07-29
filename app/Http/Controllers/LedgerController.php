<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeactivateLedgerAccountRequest;
use App\Http\Requests\DisburseExpenseRequest;
use App\Http\Requests\StoreLedgerAccountRequest;
use App\Http\Requests\ToggleLedgerAccountAccountingRequest;
use App\Http\Requests\UpdateLedgerAccountRequest;
use App\Http\Requests\UpdatePurchasesVaultRequest;
use App\Http\Requests\UpdateReceiptsVaultRequest;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Transactions;
use App\Models\Vault;
use App\Services\LedgerService;
use App\Services\VaultService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class LedgerController extends Controller
{
    protected function authorizeLedger(): void
    {
        if (!Auth::check() || !in_array((int) Auth::user()->type_id, [1, 6], true)) {
            abort(403, 'غير مسموح');
        }
    }

    public function index()
    {
        $this->authorizeLedger();

        return Inertia::render('Ledger/Index');
    }

    /**
     * Expense / commission COA account movement screen (Vaults tab drill-down).
     */
    public function expenseAccountPage(Request $request, LedgerService $ledger)
    {
        $this->authorizeLedger();

        $ownerId = (int) Auth::user()->owner_id;
        $accountId = (int) $request->get('id');
        $account = LedgerAccount::query()
            ->where('owner_id', $ownerId)
            ->where('is_active', true)
            ->findOrFail($accountId);

        if (! in_array($account->type, ['expense', 'income'], true)) {
            abort(404, 'الحساب ليس مصروفاً أو عمولة');
        }

        $ledger->ensureSystemAccounts($ownerId);
        // Pull car-purchase costs off 5100 onto مشتريات سيارات before balances/UI.
        $ledger->resolveCarPurchasesExpenseAccount($ownerId);
        $account->refresh();

        $cashVaults = app(VaultService::class)->systemQasaClientRows($ownerId)->map(fn ($row) => [
            'vault_id' => $row->vault_id,
            'id' => $row->id,
            'name' => $row->name,
            'vault_code' => $row->vault_code,
            'vault_type' => $row->vault_type,
            'balance' => $row->balance,
        ])->values();

        return Inertia::render('Vaults/ExpenseAccount', [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name_ar ?: $account->name,
                'name_ar' => $account->name_ar,
                'type' => $account->type,
                'is_system' => (bool) $account->is_system,
                'can_disburse' => $account->type === 'expense',
                'balance' => $account->balance('$'),
                'balance_dinar' => $account->balance('IQD'),
            ],
            'cashVaults' => $cashVaults,
        ]);
    }

    /**
     * List expense (+ commission-like) COA accounts for Vaults UI.
     * Pass ?for_accounting=1 to return only accounts with show_in_accounting (Accounting purple chips).
     */
    public function expenseCommissionAccounts(Request $request, LedgerService $ledger)
    {
        $this->authorizeLedger();

        $ownerId = (int) Auth::user()->owner_id;
        $currency = $request->get('currency', '$') === 'IQD' ? 'IQD' : '$';
        $onlyShowInAccounting = $request->boolean('for_accounting');

        $ledger->ensureSystemAccounts($ownerId);

        $expenseParentId = LedgerAccount::query()
            ->where('owner_id', $ownerId)
            ->where('code', LedgerService::CODE_EXPENSE)
            ->where('is_active', true)
            ->value('id');

        return Response::json([
            'accounts' => $ledger->listExpenseCommissionAccounts($ownerId, $currency, $onlyShowInAccounting),
            'suggest_expense_code' => $ledger->suggestExpenseAccountCode($ownerId, 'expense'),
            'suggest_commission_code' => $ledger->suggestExpenseAccountCode($ownerId, 'commission'),
            // Must be int|null — never optional() (JSON-encodes as {})
            'expense_parent_id' => $expenseParentId !== null ? (int) $expenseParentId : null,
            'currency' => $currency,
        ], 200);
    }

    /**
     * Toggle show_in_accounting on an expense/commission COA (Accounting purple shortcuts).
     */
    public function toggleAccountAccounting(
        ToggleLedgerAccountAccountingRequest $request,
        LedgerAccount $account,
        LedgerService $ledger
    ) {
        $ownerId = (int) Auth::user()->owner_id;
        if ((int) $account->owner_id !== $ownerId) {
            abort(403, 'غير مسموح');
        }

        $show = $request->has('show_in_accounting')
            ? (bool) $request->boolean('show_in_accounting')
            : null;

        try {
            $updated = $ledger->toggleShowInAccounting($account, $show);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return Response::json(['message' => 'تعذر تحديث عرض الحساب في المحاسبة'], 500);
        }

        return Response::json([
            'message' => 'تم تحديث عرض الحساب في المحاسبة',
            'show_in_accounting' => (bool) $updated->show_in_accounting,
            'account' => $this->accountPayload($updated),
        ], 200);
    }

    /**
     * صرف مصروف: Dr selected expense COA / Cr selected cash vault.
     */
    public function disburseExpense(DisburseExpenseRequest $request, LedgerService $ledger, VaultService $vaults)
    {
        $ownerId = (int) Auth::user()->owner_id;
        $data = $request->validated();
        $amount = round((float) $data['amount'], 2);
        $currency = $data['currency'] === 'IQD' ? 'IQD' : '$';
        $memo = trim((string) $data['memo']);
        $expenseAccountId = (int) $data['expense_ledger_account_id'];
        $vaultId = (int) $data['cash_vault_id'];

        $vault = Vault::query()->forOwner($ownerId)->findOrFail($vaultId);
        if (! $vault->isCashBox()) {
            return Response::json(['message' => 'المصدر يجب أن يكون قاصة نقدية (نقد/بنك/خزنة).'], 422);
        }
        $cashUserId = (int) ($vault->legacy_user_id ?? 0);
        if ($cashUserId <= 0) {
            return Response::json(['message' => 'القاصة غير مرتبطة بحساب تشغيلي.'], 422);
        }

        try {
            $result = DB::transaction(function () use (
                $ownerId,
                $amount,
                $currency,
                $memo,
                $expenseAccountId,
                $vault,
                $cashUserId,
                $data,
                $ledger,
                $vaults
            ) {
                $created = ! empty($data['entry_date'])
                    ? Carbon::parse($data['entry_date'])->format('Y-m-d')
                    : Carbon::now()->format('Y-m-d');

                $txnAttrs = [
                    'type' => 'outUserBox',
                    'description' => $memo,
                    'amount' => $amount * -1,
                    'is_pay' => 0,
                    'morphed_id' => 0,
                    'morphed_type' => '',
                    'user_added' => Auth::id() ?? 0,
                    'created' => $created,
                    'discount' => 0,
                    'currency' => $currency,
                    'parent_id' => 0,
                    'details' => [
                        'expense_ledger_account_id' => $expenseAccountId,
                        'source' => 'expense_disbursement',
                    ],
                ];

                if (Schema::hasColumn('transactions', 'vault_id')) {
                    $txnAttrs['vault_id'] = (int) $vault->id;
                }
                $walletId = $vaults->resolveWalletIdForLegacyUser($cashUserId);
                if ($walletId) {
                    $txnAttrs['wallet_id'] = $walletId;
                } elseif (Schema::hasColumn('transactions', 'wallet_id')) {
                    $txnAttrs['wallet_id'] = null;
                }

                $transaction = Transactions::create($txnAttrs);

                $journal = $ledger->postCashDisbursement(
                    $ownerId,
                    $amount,
                    $currency,
                    $memo,
                    $transaction,
                    $cashUserId,
                    $expenseAccountId,
                    $created
                );

                if (Schema::hasColumn('transactions', 'journal_entry_id')) {
                    $transaction->forceFill(['journal_entry_id' => $journal->id])->save();
                }

                $ledger->syncWalletFromLedger($ownerId, $cashUserId);

                $expense = $ledger->resolveExpenseAccount($ownerId, $expenseAccountId);

                return [
                    'transaction_id' => $transaction->id,
                    'journal_entry_id' => $journal->id,
                    'voucher_no' => $journal->voucher_no,
                    'expense_balance' => $expense->balance($currency),
                    'expense_balance_dinar' => $expense->balance('IQD'),
                ];
            });
        } catch (InvalidArgumentException|RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return Response::json(['message' => 'تعذر تسجيل صرف المصروف'], 500);
        }

        return Response::json([
            'message' => 'تم صرف المصروف: مدين حساب المصروف / دائن القاصة النقدية',
            ...$result,
        ], 201);
    }

    public function chartOfAccounts(Request $request, LedgerService $ledger)
    {
        $this->authorizeLedger();

        $ownerId = (int) Auth::user()->owner_id;
        $currencyFilter = $this->normalizeChartCurrencyFilter($request->get('currency', '$'));
        $showBoth = $currencyFilter === 'both';
        $q = trim((string) $request->get('q', ''));

        $ledger->ensureSystemAccounts($ownerId);

        $allAccounts = LedgerAccount::query()
            ->where('owner_id', $ownerId)
            ->where('is_active', true)
            ->withCount('lines')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('name_ar', 'like', "%{$q}%");
                });
            })
            ->orderBy('code')
            ->get();

        // Parent picker needs the full chart (not currency-filtered).
        $parentOptions = $allAccounts->map(fn (LedgerAccount $a) => [
            'id' => $a->id,
            'code' => $a->code,
            'name' => $a->name_ar ?: $a->name,
            'type' => $a->type,
        ])->values()->all();

        $accounts = $showBoth
            ? $allAccounts
            : $allAccounts->filter(fn (LedgerAccount $a) => $this->accountMatchesCurrencyFilter($a, $currencyFilter))->values();

        $depthById = $this->accountDepthMap($accounts);

        $typeOrder = ['asset', 'liability', 'equity', 'income', 'expense'];
        $typeLabels = [
            'asset' => 'الأصول',
            'liability' => 'الخصوم',
            'equity' => 'حقوق الملكية',
            'income' => 'الإيرادات',
            'expense' => 'المصاريف',
        ];

        $groups = [];
        foreach ($typeOrder as $type) {
            $typed = $accounts->where('type', $type)->values();
            $ordered = $this->orderAccountsByTree($typed);
            $items = $ordered->map(function (LedgerAccount $account) use ($currencyFilter, $showBoth, $depthById) {
                $hasMovements = ((int) ($account->lines_count ?? 0)) > 0;
                $balanceCurrency = $showBoth
                    ? ($account->currency === 'IQD' ? 'IQD' : '$')
                    : $currencyFilter;

                return [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name_ar ?: $account->name,
                    'name_ar' => $account->name_ar,
                    'name_en' => $account->name,
                    'type' => $account->type,
                    'currency' => $account->currency,
                    'parent_id' => $account->parent_id,
                    'depth' => $depthById[$account->id] ?? 0,
                    'is_system' => (bool) $account->is_system,
                    'has_movements' => $hasMovements,
                    'can_edit_code' => ! $account->is_system && ! $hasMovements,
                    'balance' => $account->balance($balanceCurrency),
                    'balance_usd' => $showBoth ? $account->balance('$') : null,
                    'balance_iqd' => $showBoth ? $account->balance('IQD') : null,
                ];
            })->all();

            if (count($items) === 0) {
                continue;
            }

            $group = [
                'type' => $type,
                'label' => $typeLabels[$type],
                'accounts' => $items,
            ];

            if ($showBoth) {
                // Keep USD / IQD section totals separate — never mix amounts.
                $group['total'] = round(array_sum(array_map(
                    fn (array $row) => (float) ($row['balance_usd'] ?? 0),
                    $items
                )), 2);
                $group['total_iqd'] = round(array_sum(array_map(
                    fn (array $row) => (float) ($row['balance_iqd'] ?? 0),
                    $items
                )), 2);
            } else {
                $group['total'] = round(array_sum(array_column($items, 'balance')), 2);
                $group['total_iqd'] = null;
            }

            $groups[] = $group;
        }

        return Response::json([
            'groups' => $groups,
            'parent_options' => $parentOptions,
            'currency' => $currencyFilter,
        ], 200);
    }

    public function storeAccount(StoreLedgerAccountRequest $request, LedgerService $ledger)
    {
        $this->authorizeLedger();

        $ownerId = (int) Auth::user()->owner_id;
        $data = $request->validated();

        try {
            $account = $ledger->createAccount($ownerId, $data);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Response::json(['message' => 'تعذر إنشاء الحساب'], 500);
        }

        return Response::json([
            'message' => 'تم إضافة الحساب بنجاح',
            'account' => $this->accountPayload($account),
        ], 201);
    }

    public function updateAccount(UpdateLedgerAccountRequest $request, LedgerService $ledger)
    {
        $this->authorizeLedger();

        $ownerId = (int) Auth::user()->owner_id;
        $data = $request->validated();
        $accountId = (int) $data['id'];
        unset($data['id']);

        try {
            $account = $ledger->updateAccount($ownerId, $accountId, $data);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Response::json(['message' => 'تعذر تحديث الحساب'], 500);
        }

        return Response::json([
            'message' => 'تم تحديث الحساب بنجاح',
            'account' => $this->accountPayload($account),
        ], 200);
    }

    public function deactivateAccount(DeactivateLedgerAccountRequest $request, LedgerService $ledger)
    {
        $this->authorizeLedger();

        $ownerId = (int) Auth::user()->owner_id;

        try {
            $account = $ledger->deactivateAccount(
                $ownerId,
                (int) $request->validated('id')
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return Response::json(['message' => 'تعذر إيقاف الحساب'], 500);
        }

        return Response::json([
            'message' => 'تم إيقاف الحساب بنجاح (لن يظهر في شجرة الحسابات)',
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'is_active' => (bool) $account->is_active,
            ],
        ], 200);
    }

    public function trialBalance(Request $request, LedgerService $ledger)
    {
        $this->authorizeLedger();

        $ownerId = (int) Auth::user()->owner_id;
        $currency = $this->normalizeReportCurrency($request->get('currency', '$'));
        $from = $request->get('from');
        $to = $request->get('to');
        $q = trim((string) $request->get('q', ''));

        $ledger->ensureSystemAccounts($ownerId);

        $accounts = LedgerAccount::query()
            ->where('owner_id', $ownerId)
            ->where('is_active', true)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('name_ar', 'like', "%{$q}%");
                });
            })
            ->orderBy('code')
            ->get();

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($accounts as $account) {
            $lines = JournalLine::query()
                ->where('journal_lines.ledger_account_id', $account->id)
                ->where('journal_lines.currency', $currency)
                ->whereHas('entry', function ($query) use ($ownerId, $from, $to) {
                    $query->where('owner_id', $ownerId);
                    if ($from && $to) {
                        $query->whereBetween('entry_date', [$from, $to]);
                    }
                });

            $debit = round((float) (clone $lines)->sum('journal_lines.debit'), 2);
            $credit = round((float) (clone $lines)->sum('journal_lines.credit'), 2);

            if ($debit == 0.0 && $credit == 0.0) {
                continue;
            }

            $balance = in_array($account->type, ['liability', 'equity', 'income'], true)
                ? round($credit - $debit, 2)
                : round($debit - $credit, 2);

            $rows[] = [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name_ar ?: $account->name,
                'type' => $account->type,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        return Response::json([
            'rows' => $rows,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'currency' => $currency,
        ], 200);
    }

    public function accountLedger(Request $request)
    {
        $this->authorizeLedger();

        $ownerId = (int) Auth::user()->owner_id;
        $accountId = (int) $request->get('account_id');
        $currency = $this->normalizeReportCurrency($request->get('currency', '$'));
        $from = $request->get('from');
        $to = $request->get('to');

        $account = LedgerAccount::where('owner_id', $ownerId)->findOrFail($accountId);

        // Viewing general expenses: ensure car purchases no longer sit on 5100.
        if ($account->code === LedgerService::CODE_EXPENSE) {
            app(LedgerService::class)->reclassifyMispostedCarPurchaseExpenses($ownerId);
            // Reload not needed for lines query below (filter by account id).
        } elseif ($account->code === LedgerService::CODE_CAR_PURCHASES
            || str_contains((string) ($account->name_ar ?? ''), 'مشتريات سيارات')) {
            app(LedgerService::class)->resolveCarPurchasesExpenseAccount($ownerId);
        }

        $openingQuery = JournalLine::query()
            ->where('journal_lines.ledger_account_id', $account->id)
            ->where('journal_lines.currency', $currency)
            ->whereHas('entry', function ($query) use ($ownerId, $from) {
                $query->where('owner_id', $ownerId);
                if ($from) {
                    $query->where('entry_date', '<', $from);
                } else {
                    $query->whereRaw('1 = 0');
                }
            });

        $openingDebit = (float) (clone $openingQuery)->sum('journal_lines.debit');
        $openingCredit = (float) (clone $openingQuery)->sum('journal_lines.credit');
        $running = in_array($account->type, ['liability', 'equity', 'income'], true)
            ? round($openingCredit - $openingDebit, 2)
            : round($openingDebit - $openingCredit, 2);

        $lines = JournalLine::query()
            ->with(['entry:id,voucher_no,entry_date,memo,source'])
            ->where('journal_lines.ledger_account_id', $account->id)
            ->where('journal_lines.currency', $currency)
            ->whereHas('entry', function ($query) use ($ownerId, $from, $to) {
                $query->where('owner_id', $ownerId);
                if ($from && $to) {
                    $query->whereBetween('entry_date', [$from, $to]);
                }
            })
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_lines.id')
            ->select('journal_lines.*')
            ->get();

        $rows = [];
        foreach ($lines as $line) {
            $delta = in_array($account->type, ['liability', 'equity', 'income'], true)
                ? ((float) $line->credit - (float) $line->debit)
                : ((float) $line->debit - (float) $line->credit);
            $running = round($running + $delta, 2);

            $rows[] = [
                'id' => $line->id,
                'date' => optional($line->entry?->entry_date)->format('Y-m-d'),
                'voucher_no' => $line->entry?->voucher_no,
                'memo' => $line->memo ?: $line->entry?->memo,
                'source' => $line->entry?->source,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
                'balance' => $running,
            ];
        }

        return Response::json([
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name_ar ?: $account->name,
                'type' => $account->type,
            ],
            'opening_balance' => in_array($account->type, ['liability', 'equity', 'income'], true)
                ? round($openingCredit - $openingDebit, 2)
                : round($openingDebit - $openingCredit, 2),
            'rows' => $rows,
            'currency' => $currency,
        ], 200);
    }

    public function recentJournals(Request $request)
    {
        $this->authorizeLedger();

        $ownerId = (int) Auth::user()->owner_id;
        $currencyRaw = $request->get('currency');
        $chartFilter = $currencyRaw !== null && $currencyRaw !== ''
            ? $this->normalizeChartCurrencyFilter($currencyRaw)
            : null;
        // `both` / empty → no line-currency filter; otherwise `$` | `IQD`.
        $currency = ($chartFilter === null || $chartFilter === 'both')
            ? null
            : $chartFilter;
        $limit = min(max((int) $request->get('limit', 50), 1), 200);

        $entries = JournalEntry::query()
            ->with(['lines.account:id,code,name,name_ar'])
            ->where('owner_id', $ownerId)
            ->when($currency, function ($query) use ($currency) {
                $query->whereHas('lines', fn ($q) => $q->where('currency', $currency));
            })
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (JournalEntry $entry) {
                return [
                    'id' => $entry->id,
                    'voucher_no' => $entry->voucher_no,
                    'entry_date' => optional($entry->entry_date)->format('Y-m-d'),
                    'memo' => $entry->memo,
                    'source' => $entry->source,
                    'lines' => $entry->lines->map(fn (JournalLine $line) => [
                        'account' => $line->account?->name_ar ?: $line->account?->name,
                        'code' => $line->account?->code,
                        'debit' => (float) $line->debit,
                        'credit' => (float) $line->credit,
                        'currency' => $line->currency,
                    ]),
                ];
            });

        return Response::json(['entries' => $entries], 200);
    }

    /**
     * Chart-of-accounts currency filter: `$` | `IQD` | `both`.
     */
    protected function normalizeChartCurrencyFilter(mixed $currency): string
    {
        $currency = is_string($currency) ? trim($currency) : '';
        if (in_array($currency, ['both', 'all', 'multi', 'مزدوج'], true)) {
            return 'both';
        }
        if ($currency === 'IQD') {
            return 'IQD';
        }

        return '$';
    }

    /**
     * Single-currency chart filter: show currency-locked matches + dual/unrestricted (null) accounts.
     * Hide accounts locked to the opposite currency (e.g. 1110/1130 when filter is USD).
     */
    protected function accountMatchesCurrencyFilter(LedgerAccount $account, string $currencyFilter): bool
    {
        $accountCurrency = $account->currency;
        if ($accountCurrency === null || $accountCurrency === '' || $accountCurrency === 'multi') {
            return true;
        }

        return $accountCurrency === $currencyFilter;
    }

    /**
     * Line/report APIs need a concrete journal currency (`both` → `$`).
     */
    protected function normalizeReportCurrency(mixed $currency): string
    {
        $filter = $this->normalizeChartCurrencyFilter($currency);

        return $filter === 'IQD' ? 'IQD' : '$';
    }

    /**
     * @return array<string, mixed>
     */
    protected function accountPayload(LedgerAccount $account): array
    {
        $hasMovements = $account->hasMovements();

        return [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name_ar ?: $account->name,
            'name_ar' => $account->name_ar,
            'name_en' => $account->name,
            'type' => $account->type,
            'currency' => $account->currency,
            'parent_id' => $account->parent_id,
            'is_system' => (bool) $account->is_system,
            'is_active' => (bool) $account->is_active,
            'show_in_accounting' => (bool) $account->show_in_accounting,
            'has_movements' => $hasMovements,
            'can_edit_code' => ! $account->is_system && ! $hasMovements,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, LedgerAccount>  $accounts
     * @return array<int, int>
     */
    protected function accountDepthMap($accounts): array
    {
        $byId = $accounts->keyBy('id');
        $depths = [];

        foreach ($accounts as $account) {
            $depth = 0;
            $cursor = $account;
            $guard = 0;
            while ($cursor?->parent_id && $guard++ < 40) {
                if (! $byId->has($cursor->parent_id)) {
                    break;
                }
                $depth++;
                $cursor = $byId->get($cursor->parent_id);
            }
            $depths[$account->id] = $depth;
        }

        return $depths;
    }

    /**
     * Depth-first order so children appear under their parent within a type group.
     *
     * @param  \Illuminate\Support\Collection<int, LedgerAccount>  $accounts
     * @return \Illuminate\Support\Collection<int, LedgerAccount>
     */
    protected function orderAccountsByTree($accounts)
    {
        $byParent = $accounts->groupBy(fn (LedgerAccount $a) => $a->parent_id ?: 0);
        $ordered = collect();

        $walk = function ($parentKey) use (&$walk, $byParent, &$ordered): void {
            $children = ($byParent->get($parentKey) ?? collect())->sortBy('code')->values();
            foreach ($children as $child) {
                $ordered->push($child);
                $walk($child->id);
            }
        };

        // Roots first (parent missing or outside this type filter), then orphans under missing parents.
        $ids = $accounts->pluck('id')->all();
        $roots = $accounts
            ->filter(fn (LedgerAccount $a) => ! $a->parent_id || ! in_array((int) $a->parent_id, $ids, true))
            ->sortBy('code')
            ->values();

        foreach ($roots as $root) {
            $ordered->push($root);
            $walk($root->id);
        }

        return $ordered->unique('id')->values();
    }

    /**
     * Current «قاصة استلام دفعات الزبائن» + list of vaults for the dropdown.
     */
    public function receiptsVault(VaultService $vaults)
    {
        $this->authorizeLedger();

        $ownerId = (int) Auth::user()->owner_id;
        $current = $vaults->resolveReceiptsVault($ownerId);
        // قاصة الاستلام = نقد فقط (صندوق/بنك/خزنة) — لا نعرض مصاريف/إيرادات COA
        $options = $vaults->listForOwner($ownerId)
            ->filter(fn (Vault $v) => $v->isCashBox() && (int) ($v->legacy_user_id ?? 0) > 0)
            ->map(fn (Vault $v) => [
                'id' => (int) $v->id,
                'name' => $v->name,
                'code' => $v->code,
                'type' => $v->type,
                'legacy_user_id' => (int) $v->legacy_user_id,
                'is_main_box' => $vaults->isEssentialMainBox($v),
            ])
            ->values();

        return Response::json([
            'default_receipts_vault_id' => (int) $current->id,
            'vault' => [
                'id' => (int) $current->id,
                'name' => $current->name,
                'code' => $current->code,
                'type' => $current->type,
                'legacy_user_id' => (int) $current->legacy_user_id,
            ],
            'vaults' => $options,
        ], 200);
    }

    /**
     * Bind client payment receipts to a vault (admin only via Form Request).
     */
    public function updateReceiptsVault(UpdateReceiptsVaultRequest $request, VaultService $vaults)
    {
        $ownerId = (int) Auth::user()->owner_id;
        $vaultId = $request->validated('default_receipts_vault_id');

        try {
            $vault = $vaults->setDefaultReceiptsVaultId(
                $ownerId,
                $vaultId !== null ? (int) $vaultId : null
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return Response::json(['message' => 'تعذر حفظ قاصة استلام الدفعات'], 500);
        }

        return Response::json([
            'message' => 'تم ربط دفعات الزبائن بالقاصة: '.$vault->name,
            'default_receipts_vault_id' => (int) $vault->id,
            'vault' => [
                'id' => (int) $vault->id,
                'name' => $vault->name,
                'code' => $vault->code,
                'type' => $vault->type,
                'legacy_user_id' => (int) $vault->legacy_user_id,
            ],
        ], 200);
    }

    /**
     * Current «قاصة صرف المشتريات» + list of cash vaults for the dropdown.
     */
    public function purchasesVault(VaultService $vaults)
    {
        $this->authorizeLedger();

        $ownerId = (int) Auth::user()->owner_id;
        $current = $vaults->resolvePurchasesVault($ownerId);
        $options = $vaults->listForOwner($ownerId)
            ->filter(fn (Vault $v) => $v->isCashBox() && (int) ($v->legacy_user_id ?? 0) > 0)
            ->map(fn (Vault $v) => [
                'id' => (int) $v->id,
                'name' => $v->name,
                'code' => $v->code,
                'type' => $v->type,
                'legacy_user_id' => (int) $v->legacy_user_id,
                'is_main_box' => $vaults->isEssentialMainBox($v),
            ])
            ->values();

        return Response::json([
            'default_purchases_vault_id' => (int) $current->id,
            'vault' => [
                'id' => (int) $current->id,
                'name' => $current->name,
                'code' => $current->code,
                'type' => $current->type,
                'legacy_user_id' => (int) $current->legacy_user_id,
            ],
            'vaults' => $options,
        ], 200);
    }

    /**
     * Bind car purchase cost outflows to a cash vault (admin only via Form Request).
     */
    public function updatePurchasesVault(UpdatePurchasesVaultRequest $request, VaultService $vaults)
    {
        $ownerId = (int) Auth::user()->owner_id;
        $vaultId = $request->validated('default_purchases_vault_id');

        try {
            $vault = $vaults->setDefaultPurchasesVaultId(
                $ownerId,
                $vaultId !== null ? (int) $vaultId : null
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return Response::json(['message' => 'تعذر حفظ قاصة صرف المشتريات'], 500);
        }

        return Response::json([
            'message' => 'تم ربط صرف المشتريات بالقاصة: '.$vault->name,
            'default_purchases_vault_id' => (int) $vault->id,
            'vault' => [
                'id' => (int) $vault->id,
                'name' => $vault->name,
                'code' => $vault->code,
                'type' => $vault->type,
                'legacy_user_id' => (int) $vault->legacy_user_id,
            ],
        ], 200);
    }
}
