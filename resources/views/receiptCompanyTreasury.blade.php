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
<div class="row">
    <div class="col-4 text-center py-3">
        <h5>{{ $config['first_title_ar'] ?? '' }}</h5>
        <h5>{{ $config['second_title_ar'] ?? '' }}</h5>
    </div>
    <div class="col-4 text-center py-3">
        <h5 class="pt-3">
            @if(!empty($entryId))
                وصل قاصة الشركة
            @else
                كشف قاصة الشركة
            @endif
        </h5>
        <p class="mb-0">{{ $currency === '$' ? 'دولار USD' : 'دينار IQD' }}</p>
    </div>
    <div class="col-4 text-center py-3">
        @include('Components.logo')
    </div>
</div>

<div class="row p-2 text-center border-top border-bottom" style="font-size: 14px">
    @if(!empty($from) && !empty($to))
    <div class="col">الفترة: {{ $from }} — {{ $to }}</div>
    @endif
    @if(!empty($tag))
    <div class="col">التاغ: {{ $tag }}</div>
    @endif
    <div class="col">تاريخ الطباعة: {{ date('Y-m-d') }}</div>
</div>

@php
    $totalDebit = $entries->sum('debit');
    $totalCredit = $entries->sum('credit');
    $lastBalance = $entries->last()?->balance ?? 0;
@endphp

<div class="row p-2 text-center border-bottom alert-primary" style="font-size: 14px">
    <div class="col-3">إجمالي المدين: {{ number_format($totalDebit, $currency === '$' ? 2 : 0) }}</div>
    <div class="col-3">إجمالي الدائن: {{ number_format($totalCredit, $currency === '$' ? 2 : 0) }}</div>
    <div class="col-3">رصيد نهاية الكشف: {{ number_format($lastBalance, $currency === '$' ? 2 : 0) }}</div>
    <div class="col-3">عدد الحركات: {{ $entries->count() }}</div>
</div>

@if(empty($entryId))
<div class="row p-2 text-center border-bottom" style="font-size: 13px">
    <div class="col-6">رصيد الدولار الحالي: {{ number_format($balanceUsd ?? 0, 2) }} $</div>
    <div class="col-6">رصيد الدينار الحالي: {{ number_format($balanceIqd ?? 0, 0) }} IQD</div>
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
                <td>{{ $entry->debit > 0 ? number_format($entry->debit, $currency === '$' ? 2 : 0) : '—' }}</td>
                <td>{{ $entry->credit > 0 ? number_format($entry->credit, $currency === '$' ? 2 : 0) : '—' }}</td>
                <td>{{ number_format($entry->balance, $currency === '$' ? 2 : 0) }}</td>
            </tr>
            @empty
            <tr><td colspan="7">لا توجد حركات</td></tr>
            @endforelse
        </tbody>
        @if($entries->count())
        <tfoot>
            <tr class="table-secondary fw-bold">
                <td colspan="4">المجموع</td>
                <td>{{ number_format($totalDebit, $currency === '$' ? 2 : 0) }}</td>
                <td>{{ number_format($totalCredit, $currency === '$' ? 2 : 0) }}</td>
                <td>{{ number_format($lastBalance, $currency === '$' ? 2 : 0) }}</td>
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
