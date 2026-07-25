<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Support\Collection;

/**
 * Recent financial operations for the ERP dashboard (journals-first).
 */
class DashboardActivityService
{
    /** @var array<string, string> */
    protected const SOURCE_LABELS = [
        'wallet' => 'دفعة / محفظة',
        'cash_box' => 'صندوق النقد',
        'system_wallet' => 'قاصة النظام',
        'treasury' => 'قاصة الشركة',
        'account_transfer' => 'تحويل بين الحسابات',
        'trader_profit_post' => 'ترحيل أرباح تاجر',
        'trader_profit_withdraw' => 'سحب أرباح تاجر',
        'manual' => 'قيد يدوي',
        'opening' => 'رصيد افتتاحي',
    ];

    /**
     * Last N non-deleted journal entries for the owner, shaped for the dashboard table.
     *
     * @return list<array<string, mixed>>
     */
    public function recentOperations(int $ownerId, int $limit = 12): array
    {
        $limit = min(max($limit, 1), 25);

        /** @var Collection<int, JournalEntry> $entries */
        $entries = JournalEntry::query()
            ->with(['lines.account:id,code,name,name_ar'])
            ->where('owner_id', $ownerId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $entries
            ->map(fn (JournalEntry $entry) => $this->mapEntry($entry))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapEntry(JournalEntry $entry): array
    {
        $lines = $entry->lines ?? collect();
        $totalDebit = round((float) $lines->sum('debit'), 2);
        $totalCredit = round((float) $lines->sum('credit'), 2);
        $amount = max($totalDebit, $totalCredit);

        $currency = $entry->currency
            ?: (string) ($lines->first()?->currency ?? '$');

        $debitLine = $lines->first(fn (JournalLine $l) => (float) $l->debit > 0);
        $creditLine = $lines->first(fn (JournalLine $l) => (float) $l->credit > 0);

        $party = $this->partyLabel($debitLine, $creditLine, $entry->memo);
        $direction = $this->directionFromSource((string) ($entry->source ?? ''), $lines);

        $occurredAt = $entry->created_at ?? $entry->entry_date;

        return [
            'id' => $entry->id,
            'voucher_no' => $entry->voucher_no,
            'time' => $occurredAt?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? null,
            'entry_date' => optional($entry->entry_date)->format('Y-m-d'),
            'type' => $entry->source ?: 'manual',
            'type_label' => self::SOURCE_LABELS[$entry->source] ?? 'عملية محاسبية',
            'party' => $party,
            'memo' => $entry->memo,
            'amount' => $amount,
            'currency' => $currency,
            'direction' => $direction,
            'direction_label' => $direction === 'in' ? 'وارد' : ($direction === 'out' ? 'صادر' : 'قيد'),
            'link' => route('ledger'),
        ];
    }

    protected function partyLabel(?JournalLine $debit, ?JournalLine $credit, ?string $memo): string
    {
        $debitName = $debit?->account?->name_ar ?: $debit?->account?->name;
        $creditName = $credit?->account?->name_ar ?: $credit?->account?->name;

        if ($debitName && $creditName && $debitName !== $creditName) {
            return $debitName.' ← '.$creditName;
        }

        return $debitName ?: $creditName ?: ($memo ?: '—');
    }

    /**
     * Heuristic in/out for cash-like sources; otherwise balanced journal.
     *
     * @param  Collection<int, JournalLine>  $lines
     */
    protected function directionFromSource(string $source, Collection $lines): string
    {
        if ($source === 'trader_profit_withdraw') {
            return 'out';
        }
        if ($source === 'trader_profit_post') {
            return 'in';
        }

        if (in_array($source, ['cash_box', 'treasury', 'system_wallet', 'wallet'], true)) {
            // Cash receipt: debit cash — treat as in; credit cash — out.
            $cashDebit = $lines->contains(function (JournalLine $line) {
                $code = (string) ($line->account?->code ?? '');

                return (float) $line->debit > 0 && str_starts_with($code, '11');
            });
            $cashCredit = $lines->contains(function (JournalLine $line) {
                $code = (string) ($line->account?->code ?? '');

                return (float) $line->credit > 0 && str_starts_with($code, '11');
            });

            if ($cashDebit && ! $cashCredit) {
                return 'in';
            }
            if ($cashCredit && ! $cashDebit) {
                return 'out';
            }
        }

        return 'journal';
    }
}
