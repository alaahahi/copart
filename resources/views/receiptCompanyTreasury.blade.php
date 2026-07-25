<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
@include('Components.reportHead', [
    'pageTitle' => !empty($entryId) ? 'وصل قاصة الشركة' : 'كشف قاصة الشركة',
])
</head>
<body class="erp-report">
@include('Components.reportToolbar')
<div class="erp-report-page">
@include('Components.reportHeader', [
    'title' => !empty($entryId) ? 'وصل قاصة الشركة' : 'كشف قاصة الشركة',
    'subtitle' => ($currency === '$' ? 'عملة: دولار أمريكي' : 'عملة: دينار عراقي'),
    'config' => $config ?? null,
])

@if((!empty($from) && !empty($to)) || !empty($tag))
<div class="erp-report-meta">
    @if(!empty($from) && !empty($to))
    <div class="erp-report-meta__item">
        <span class="erp-report-meta__label">الفترة:</span>
        <span class="erp-report-meta__value">{{ $from }} — {{ $to }}</span>
    </div>
    @endif
    @if(!empty($tag))
    <div class="erp-report-meta__item">
        <span class="erp-report-meta__label">التصنيف:</span>
        <span class="erp-report-meta__value">{{ $tag }}</span>
    </div>
    @endif
</div>
@endif

@php
    $totalDebit = $entries->sum('debit');
    $totalCredit = $entries->sum('credit');
    $lastBalance = $entries->last()?->balance ?? 0;
@endphp

<div class="erp-report-summary">
    <div class="erp-report-summary__card">
        <span class="erp-report-summary__label">إجمالي المدين</span>
        <span class="erp-report-summary__value num">{{ \App\Helpers\Help::formatMoney($totalDebit, $currency) }}</span>
    </div>
    <div class="erp-report-summary__card">
        <span class="erp-report-summary__label">إجمالي الدائن</span>
        <span class="erp-report-summary__value num">{{ \App\Helpers\Help::formatMoney($totalCredit, $currency) }}</span>
    </div>
    <div class="erp-report-summary__card">
        <span class="erp-report-summary__label">رصيد نهاية الكشف</span>
        <span class="erp-report-summary__value num">{{ \App\Helpers\Help::formatMoney($lastBalance, $currency) }}</span>
    </div>
    <div class="erp-report-summary__card">
        <span class="erp-report-summary__label">عدد الحركات</span>
        <span class="erp-report-summary__value">{{ $entries->count() }}</span>
    </div>
</div>

@if(empty($entryId))
<div class="erp-report-summary">
    <div class="erp-report-summary__card">
        <span class="erp-report-summary__label">رصيد الدولار الحالي</span>
        <span class="erp-report-summary__value num">{{ \App\Helpers\Help::formatMoney($balanceUsd ?? 0, '$') }}</span>
    </div>
    <div class="erp-report-summary__card">
        <span class="erp-report-summary__label">رصيد الدينار الحالي</span>
        <span class="erp-report-summary__value num">{{ \App\Helpers\Help::formatMoney($balanceIqd ?? 0, 'IQD') }}</span>
    </div>
</div>
@endif

<div class="erp-report-table-wrap">
    <table class="erp-report-table">
        <thead>
            <tr>
                <th>#</th>
                <th>التاريخ</th>
                <th>البيان</th>
                <th>التصنيف</th>
                <th>مدين</th>
                <th>دائن</th>
                <th>الرصيد</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $index => $entry)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $entry->entry_date?->format('Y-m-d') ?? '' }}</td>
                <td class="text-start-rtl">{{ $entry->description ?? '' }}</td>
                <td>{{ $entry->tag ?? '—' }}</td>
                <td class="num">{{ $entry->debit > 0 ? \App\Helpers\Help::formatMoney($entry->debit, $currency) : '—' }}</td>
                <td class="num">{{ $entry->credit > 0 ? \App\Helpers\Help::formatMoney($entry->credit, $currency) : '—' }}</td>
                <td class="num">{{ \App\Helpers\Help::formatMoney($entry->balance, $currency) }}</td>
            </tr>
            @empty
            <tr><td colspan="7">لا توجد حركات</td></tr>
            @endforelse
        </tbody>
        @if($entries->count())
        <tfoot>
            <tr>
                <td colspan="4">المجموع</td>
                <td class="num">{{ \App\Helpers\Help::formatMoney($totalDebit, $currency) }}</td>
                <td class="num">{{ \App\Helpers\Help::formatMoney($totalCredit, $currency) }}</td>
                <td class="num">{{ \App\Helpers\Help::formatMoney($lastBalance, $currency) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>

@include('Components.reportFooter', ['config' => $config ?? null])
</div>
</body>
</html>
