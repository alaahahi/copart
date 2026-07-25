<!DOCTYPE html>
<html>
<head>
    <title>{{ config('app.company_name') }}</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
    @page { size: auto; margin: 15px; margin-top: 60px; }
    @media print { .no-print { display: none !important; } }
    </style>
</head>
<body style="direction: rtl;">
<div class="container-fluid">
@include('Components.reportHeader', [
    'title' => !empty($entryId) ? 'وصل قاصة الشركة' : 'كشف قاصة الشركة',
    'subtitle' => ($currency === '$' ? 'دولار USD' : 'دينار IQD'),
    'config' => $config ?? null,
])

<div class="row p-2 text-center border-top border-bottom" style="font-size: 14px">
    @if(!empty($from) && !empty($to))
    <div class="col">الفترة: {{ $from }} — {{ $to }}</div>
    @endif
    @if(!empty($tag))
    <div class="col">التاغ: {{ $tag }}</div>
    @endif
</div>

@php
    $totalDebit = $entries->sum('debit');
    $totalCredit = $entries->sum('credit');
    $lastBalance = $entries->last()?->balance ?? 0;
@endphp

<div class="row p-2 text-center border-bottom alert-primary" style="font-size: 14px">
    <div class="col-3">إجمالي المدين: {{ \App\Helpers\Help::formatMoney($totalDebit, $currency) }}</div>
    <div class="col-3">إجمالي الدائن: {{ \App\Helpers\Help::formatMoney($totalCredit, $currency) }}</div>
    <div class="col-3">رصيد نهاية الكشف: {{ \App\Helpers\Help::formatMoney($lastBalance, $currency) }}</div>
    <div class="col-3">عدد الحركات: {{ $entries->count() }}</div>
</div>

@if(empty($entryId))
<div class="row p-2 text-center border-bottom" style="font-size: 13px">
    <div class="col-6">رصيد الدولار الحالي: {{ \App\Helpers\Help::formatMoney($balanceUsd ?? 0, '$') }} $</div>
    <div class="col-6">رصيد الدينار الحالي: {{ \App\Helpers\Help::formatMoney($balanceIqd ?? 0, 'IQD') }} IQD</div>
</div>
@endif

<div class="row text-center py-2">
    <table class="table table-sm table-striped table-bordered" style="font-size: 12px">
        <thead>
            <tr>
                <th>#</th>
                <th>التاريخ</th>
                <th>البيان</th>
                <th>التاغ</th>
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
                <td>{{ $entry->description ?? '' }}</td>
                <td>{{ $entry->tag ?? '—' }}</td>
                <td>{{ $entry->debit > 0 ? \App\Helpers\Help::formatMoney($entry->debit, $currency) : '—' }}</td>
                <td>{{ $entry->credit > 0 ? \App\Helpers\Help::formatMoney($entry->credit, $currency) : '—' }}</td>
                <td>{{ \App\Helpers\Help::formatMoney($entry->balance, $currency) }}</td>
            </tr>
            @empty
            <tr><td colspan="7">لا توجد حركات</td></tr>
            @endforelse
        </tbody>
        @if($entries->count())
        <tfoot>
            <tr class="table-secondary fw-bold">
                <td colspan="4">المجموع</td>
                <td>{{ \App\Helpers\Help::formatMoney($totalDebit, $currency) }}</td>
                <td>{{ \App\Helpers\Help::formatMoney($totalCredit, $currency) }}</td>
                <td>{{ \App\Helpers\Help::formatMoney($lastBalance, $currency) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>
</div>

<script>
$(document).ready(function() { window.print(); });
</script>
</body>
</html>
