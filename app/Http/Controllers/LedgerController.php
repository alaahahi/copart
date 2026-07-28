<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeactivateLedgerAccountRequest;
use App\Http\Requests\StoreLedgerAccountRequest;
use App\Http\Requests\UpdateLedgerAccountRequest;
use App\Http\Requests\UpdateReceiptsVaultRequest;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Vault;
use App\Services\LedgerService;
use App\Services\VaultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
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

    public function chartOfAccounts(Request $request, LedgerService $ledger)
    {
        $this->authorizeLedger();

        $ownerId = (int) Auth::user()->owner_id;
        $currency = $request->get('currency', '$');
        $q = trim((string) $request->get('q', ''));

        $ledger->ensureSystemAccounts($ownerId);

        $accounts = LedgerAccount::query()
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

        $depthById = $this->accountDepthMap($accounts);

        $typeOrder = ['asset', 'liability', 'equity', 'income', 'expense'];
        $typeLabels = [
            'asset' => 'الأصول',
            'liability' => 'الخصوم',
            'equity' => 'حقوق الملكية',
            'income' => 'الإيرادات',
            'expense' => 'المصاريف',
        ];

        $parentOptions = $accounts->map(fn (LedgerAccount $a) => [
            'id' => $a->id,
            'code' => $a->code,
            'name' => $a->name_ar ?: $a->name,
            'type' => $a->type,
        ])->values()->all();

        $groups = [];
        foreach ($typeOrder as $type) {
            $typed = $accounts->where('type', $type)->values();
            $ordered = $this->orderAccountsByTree($typed);
            $items = $ordered->map(function (LedgerAccount $account) use ($currency, $depthById) {
                $hasMovements = ((int) ($account->lines_count ?? 0)) > 0;

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
                    'balance' => $account->balance($currency),
                ];
            })->all();

            if (count($items) === 0) {
                continue;
            }

            $groups[] = [
                'type' => $type,
                'label' => $typeLabels[$type],
                'accounts' => $items,
                'total' => round(array_sum(array_column($items, 'balance')), 2),
            ];
        }

        return Response::json([
            'groups' => $groups,
            'parent_options' => $parentOptions,
            'currency' => $currency,
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
        $currency = $request->get('currency', '$');
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
        $currency = $request->get('currency', '$');
        $from = $request->get('from');
        $to = $request->get('to');

        $account = LedgerAccount::where('owner_id', $ownerId)->findOrFail($accountId);

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
        $currency = $request->get('currency');
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
        $options = $vaults->listForOwner($ownerId)
            ->filter(fn (Vault $v) => (int) ($v->legacy_user_id ?? 0) > 0)
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
}
