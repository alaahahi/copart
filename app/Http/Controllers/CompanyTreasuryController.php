<?php

namespace App\Http\Controllers;

use App\Models\CompanyTreasuryEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class CompanyTreasuryController extends Controller
{
    protected function authorizeTreasury(): void
    {
        if (!Auth::check()) {
            abort(403, 'غير مسموح');
        }
    }

    public function index()
    {
        $this->authorizeTreasury();

        return Inertia::render('CompanyTreasury/Index');
    }

    public function getEntries(Request $request)
    {
        $this->authorizeTreasury();

        $ownerId = Auth::user()->owner_id;
        $currency = $request->get('currency', '$');
        $from = $request->get('from');
        $to = $request->get('to');
        $perPage = min(max((int) $request->get('limit', 100), 1), 200);
        $page = max((int) $request->get('page', 1), 1);

        $filteredQuery = CompanyTreasuryEntry::query()
            ->where('owner_id', $ownerId)
            ->where('currency', $currency);

        if ($from && $to) {
            $filteredQuery->whereBetween('entry_date', [$from, $to]);
        }

        $totalDebit = (float) (clone $filteredQuery)->sum('debit');
        $totalCredit = (float) (clone $filteredQuery)->sum('credit');
        $totalCount = (clone $filteredQuery)->count();

        $paginated = (clone $filteredQuery)
            ->orderBy('entry_date', 'asc')
            ->orderBy('id', 'asc')
            ->paginate($perPage, ['*'], 'page', $page);

        return Response::json([
            'entries' => array_map(
                fn (CompanyTreasuryEntry $entry) => $this->formatEntryForApi($entry),
                $paginated->items()
            ),
            'pagination' => [
                'total' => $totalCount,
                'page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
            ],
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'period_balance' => $this->getPeriodEndBalance($ownerId, $currency, $to),
        ], 200);
    }

    public function getSummary()
    {
        $this->authorizeTreasury();

        $ownerId = Auth::user()->owner_id;

        return Response::json([
            'balance_usd' => $this->getLastBalance($ownerId, '$'),
            'balance_iqd' => $this->getLastBalance($ownerId, 'IQD'),
        ], 200);
    }

    public function store(Request $request)
    {
        $this->authorizeTreasury();

        $validated = $request->validate([
            'entry_type' => 'required|in:deposit,withdraw',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:$,IQD',
            'entry_date' => 'required|date',
            'description' => 'nullable|string|max:500',
        ]);

        $ownerId = Auth::user()->owner_id;
        $amount = (float) $validated['amount'];
        $currency = $validated['currency'];
        $isDeposit = $validated['entry_type'] === 'deposit';

        $entry = CompanyTreasuryEntry::create([
            'owner_id' => $ownerId,
            'user_id' => Auth::id(),
            'entry_date' => $validated['entry_date'],
            'description' => $validated['description'] ?? ($isDeposit ? 'إيداع' : 'سحب'),
            'currency' => $currency,
            'debit' => $isDeposit ? $amount : 0,
            'credit' => $isDeposit ? 0 : $amount,
            'balance' => 0,
        ]);

        $this->recalculateBalancesFrom(
            $ownerId,
            $currency,
            $entry->entry_date->format('Y-m-d'),
            (int) $entry->id
        );

        if ($this->getLastBalance($ownerId, $currency) < 0) {
            $entryDate = $entry->entry_date->format('Y-m-d');
            $entryId = (int) $entry->id;
            $entry->forceDelete();
            $this->recalculateAfterDelete($ownerId, $currency, $entryDate, $entryId);

            return Response::json(['message' => 'الرصيد غير كافٍ لإتمام السحب'], 422);
        }

        $entry->refresh();

        return Response::json($this->formatEntryForApi($entry), 201);
    }

    public function destroy(Request $request)
    {
        $this->authorizeTreasury();

        $ownerId = Auth::user()->owner_id;
        $entry = CompanyTreasuryEntry::where('owner_id', $ownerId)->findOrFail($request->id);
        $currency = $entry->currency;
        $entryDate = $entry->entry_date->format('Y-m-d');
        $entryId = (int) $entry->id;
        $entry->delete();
        $this->recalculateAfterDelete($ownerId, $currency, $entryDate, $entryId);

        return Response::json(['message' => 'ok'], 200);
    }

    public function getTrash(Request $request)
    {
        $this->authorizeTreasury();

        $ownerId = Auth::user()->owner_id;
        $currency = $request->get('currency', '$');
        $limit = min(max((int) $request->get('limit', 50), 1), 100);

        $entries = CompanyTreasuryEntry::onlyTrashed()
            ->where('owner_id', $ownerId)
            ->where('currency', $currency)
            ->orderBy('deleted_at', 'desc')
            ->limit($limit)
            ->get();

        return Response::json([
            'entries' => $entries->map(fn (CompanyTreasuryEntry $entry) => $this->formatEntryForApi($entry, true))->values(),
        ], 200);
    }

    public function restore(Request $request)
    {
        $this->authorizeTreasury();

        $ownerId = Auth::user()->owner_id;
        $entry = CompanyTreasuryEntry::onlyTrashed()
            ->where('owner_id', $ownerId)
            ->findOrFail($request->id);

        $currency = $entry->currency;
        $entryDate = $entry->entry_date->format('Y-m-d');
        $entryId = (int) $entry->id;

        $entry->restore();
        $this->recalculateBalancesFrom($ownerId, $currency, $entryDate, $entryId);

        if ($this->getLastBalance($ownerId, $currency) < 0) {
            $entry->delete();
            $this->recalculateAfterDelete($ownerId, $currency, $entryDate, $entryId);

            return Response::json(['message' => 'لا يمكن الاسترجاع — الرصيد سيصبح سالباً'], 422);
        }

        $entry->refresh();

        return Response::json($this->formatEntryForApi($entry), 200);
    }

    public function update(Request $request)
    {
        $this->authorizeTreasury();

        $validated = $request->validate([
            'id' => 'required|integer',
            'entry_type' => 'required|in:deposit,withdraw',
            'amount' => 'required|numeric|min:0.01',
            'entry_date' => 'required|date',
            'description' => 'nullable|string|max:500',
        ]);

        $ownerId = Auth::user()->owner_id;
        $entry = CompanyTreasuryEntry::where('owner_id', $ownerId)->findOrFail($validated['id']);
        $currency = $entry->currency;
        $amount = (float) $validated['amount'];
        $isDeposit = $validated['entry_type'] === 'deposit';

        $oldDate = $entry->entry_date->format('Y-m-d');
        $oldId = (int) $entry->id;
        $previous = $entry->only(['entry_date', 'description', 'debit', 'credit']);

        $entry->update([
            'entry_date' => $validated['entry_date'],
            'description' => $validated['description'] ?? ($isDeposit ? 'إيداع' : 'سحب'),
            'debit' => $isDeposit ? $amount : 0,
            'credit' => $isDeposit ? 0 : $amount,
        ]);

        $entry->refresh();
        $newDate = $entry->entry_date->format('Y-m-d');
        $newId = (int) $entry->id;

        if ([$oldDate, $oldId] <= [$newDate, $newId]) {
            $this->recalculateBalancesFrom($ownerId, $currency, $oldDate, $oldId);
        } else {
            $this->recalculateBalancesFrom($ownerId, $currency, $newDate, $newId);
        }

        if ($this->getLastBalance($ownerId, $currency) < 0) {
            $entry->update($previous);
            $entry->refresh();
            $this->recalculateBalancesFrom($ownerId, $currency, $oldDate, $oldId);

            return Response::json(['message' => 'الرصيد غير كافٍ بعد التعديل'], 422);
        }

        $entry->refresh();

        return Response::json($this->formatEntryForApi($entry), 200);
    }

    protected function getLastBalance(int $ownerId, string $currency): float
    {
        $last = CompanyTreasuryEntry::where('owner_id', $ownerId)
            ->where('currency', $currency)
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $last ? (float) $last->balance : 0.0;
    }

    protected function getPeriodEndBalance(int $ownerId, string $currency, ?string $to): float
    {
        if (!$to) {
            return $this->getLastBalance($ownerId, $currency);
        }

        $last = CompanyTreasuryEntry::where('owner_id', $ownerId)
            ->where('currency', $currency)
            ->where('entry_date', '<=', $to)
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $last ? (float) $last->balance : 0.0;
    }

    protected function getBalanceBefore(int $ownerId, string $currency, string $entryDate, int $entryId): float
    {
        return (float) CompanyTreasuryEntry::where('owner_id', $ownerId)
            ->where('currency', $currency)
            ->where(function ($query) use ($entryDate, $entryId) {
                $query->where('entry_date', '<', $entryDate)
                    ->orWhere(function ($inner) use ($entryDate, $entryId) {
                        $inner->where('entry_date', $entryDate)->where('id', '<', $entryId);
                    });
            })
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as running_balance')
            ->value('running_balance');
    }

    protected function recalculateBalancesFrom(int $ownerId, string $currency, string $fromDate, int $fromId): void
    {
        $balance = $this->getBalanceBefore($ownerId, $currency, $fromDate, $fromId);

        $entries = CompanyTreasuryEntry::where('owner_id', $ownerId)
            ->where('currency', $currency)
            ->where(function ($query) use ($fromDate, $fromId) {
                $query->where('entry_date', '>', $fromDate)
                    ->orWhere(function ($inner) use ($fromDate, $fromId) {
                        $inner->where('entry_date', $fromDate)->where('id', '>=', $fromId);
                    });
            })
            ->orderBy('entry_date', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'debit', 'credit', 'balance']);

        foreach ($entries as $entry) {
            $balance += (float) $entry->debit - (float) $entry->credit;
            $newBalance = round($balance, 2);
            if ((float) $entry->balance !== $newBalance) {
                $entry->balance = $newBalance;
                $entry->saveQuietly();
            }
        }
    }

    protected function recalculateAfterDelete(int $ownerId, string $currency, string $deletedDate, int $deletedId): void
    {
        $next = CompanyTreasuryEntry::where('owner_id', $ownerId)
            ->where('currency', $currency)
            ->where(function ($query) use ($deletedDate, $deletedId) {
                $query->where('entry_date', '>', $deletedDate)
                    ->orWhere(function ($inner) use ($deletedDate, $deletedId) {
                        $inner->where('entry_date', $deletedDate)->where('id', '>', $deletedId);
                    });
            })
            ->orderBy('entry_date', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        if (!$next) {
            return;
        }

        $this->recalculateBalancesFrom(
            $ownerId,
            $currency,
            $next->entry_date->format('Y-m-d'),
            (int) $next->id
        );
    }

    protected function formatEntryForApi(CompanyTreasuryEntry $entry, bool $includeDeleted = false): array
    {
        $data = $entry->toArray();
        $data['entry_time'] = $entry->created_at?->timezone(config('app.timezone'))->format('H:i') ?? '';

        if ($includeDeleted) {
            $data['deleted_time'] = $entry->deleted_at?->timezone(config('app.timezone'))->format('H:i') ?? '';
        }

        return $data;
    }
}
