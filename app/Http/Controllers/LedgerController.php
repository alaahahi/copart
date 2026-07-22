<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

class LedgerController extends Controller
{
    public function index()
    {
        if (!Auth::check()) {
            abort(403);
        }

        return Inertia::render('Ledger/Index');
    }

    public function trialBalance(Request $request, LedgerService $ledger)
    {
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
                ->where('ledger_account_id', $account->id)
                ->where('currency', $currency)
                ->whereHas('entry', function ($query) use ($ownerId, $from, $to) {
                    $query->where('owner_id', $ownerId);
                    if ($from && $to) {
                        $query->whereBetween('entry_date', [$from, $to]);
                    }
                });

            $debit = round((float) (clone $lines)->sum('debit'), 2);
            $credit = round((float) (clone $lines)->sum('credit'), 2);

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
        $ownerId = (int) Auth::user()->owner_id;
        $accountId = (int) $request->get('account_id');
        $currency = $request->get('currency', '$');
        $from = $request->get('from');
        $to = $request->get('to');

        $account = LedgerAccount::where('owner_id', $ownerId)->findOrFail($accountId);

        $openingQuery = JournalLine::query()
            ->where('ledger_account_id', $account->id)
            ->where('currency', $currency)
            ->whereHas('entry', function ($query) use ($ownerId, $from) {
                $query->where('owner_id', $ownerId);
                if ($from) {
                    $query->where('entry_date', '<', $from);
                } else {
                    $query->whereRaw('1 = 0');
                }
            });

        $openingDebit = (float) (clone $openingQuery)->sum('debit');
        $openingCredit = (float) (clone $openingQuery)->sum('credit');
        $running = in_array($account->type, ['liability', 'equity', 'income'], true)
            ? round($openingCredit - $openingDebit, 2)
            : round($openingDebit - $openingCredit, 2);

        $lines = JournalLine::query()
            ->with(['entry:id,voucher_no,entry_date,memo,source'])
            ->where('ledger_account_id', $account->id)
            ->where('currency', $currency)
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
}
