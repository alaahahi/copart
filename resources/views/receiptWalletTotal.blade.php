<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
@php
    $transactions = is_object($data['transactions']) && method_exists($data['transactions'], 'items')
        ? collect($data['transactions']->items())
        : collect($data['transactions']);
    $isAmanah = false;
    if ($transactions->count() > 0) {
        $firstItem = $transactions->first();
        $firstType = is_array($firstItem) ? ($firstItem['type'] ?? '') : ($firstItem->type ?? '');
        $isAmanah = in_array($firstType, ['inUserAmanah', 'outUserAmanah']);
    }
    $walletReportTitle = $isAmanah ? 'كشف حساب الأمانة' : 'كشف حساب الصندوق';
@endphp
@include('Components.reportHead', ['pageTitle' => $walletReportTitle])
</head>
<body class="erp-report">
@include('Components.reportToolbar')
<div class="erp-report-page">
@include('Components.reportHeader', ['title' => $walletReportTitle, 'config' => $config ?? null])

<div class="erp-report-meta">
    <div class="erp-report-meta__item">
        <span class="erp-report-meta__label">الحساب:</span>
        <span class="erp-report-meta__value">{{ $data['user']->name ?? '' }}</span>
    </div>
    <div class="erp-report-meta__item">
        <span class="erp-report-meta__label">الهاتف:</span>
        <span class="erp-report-meta__value">{{ $data['user']->phone ?? '' }}</span>
    </div>
</div>

@php
    $totalInDollar = $transactions->filter(function ($item) {
        $type = is_array($item) ? ($item['type'] ?? '') : ($item->type ?? '');
        $currency = is_array($item) ? ($item['currency'] ?? '$') : ($item->currency ?? '$');
        return $currency == '$' && in_array($type, ['inUser', 'inUserAmanah']);
    })->sum(function ($item) {
        return abs(is_array($item) ? ($item['amount'] ?? 0) : ($item->amount ?? 0));
    });

    $totalOutDollar = $transactions->filter(function ($item) {
        $type = is_array($item) ? ($item['type'] ?? '') : ($item->type ?? '');
        $currency = is_array($item) ? ($item['currency'] ?? '$') : ($item->currency ?? '$');
        return $currency == '$' && in_array($type, ['outUser', 'outUserAmanah']);
    })->sum(function ($item) {
        return abs(is_array($item) ? ($item['amount'] ?? 0) : ($item->amount ?? 0));
    });

    $totalInDinar = $transactions->filter(function ($item) {
        $type = is_array($item) ? ($item['type'] ?? '') : ($item->type ?? '');
        $currency = is_array($item) ? ($item['currency'] ?? '$') : ($item->currency ?? '$');
        return $currency == 'IQD' && in_array($type, ['inUser', 'inUserAmanah']);
    })->sum(function ($item) {
        return abs(is_array($item) ? ($item['amount'] ?? 0) : ($item->amount ?? 0));
    });

    $totalOutDinar = $transactions->filter(function ($item) {
        $type = is_array($item) ? ($item['type'] ?? '') : ($item->type ?? '');
        $currency = is_array($item) ? ($item['currency'] ?? '$') : ($item->currency ?? '$');
        return $currency == 'IQD' && in_array($type, ['outUser', 'outUserAmanah']);
    })->sum(function ($item) {
        return abs(is_array($item) ? ($item['amount'] ?? 0) : ($item->amount ?? 0));
    });

    $balanceDollar = $totalInDollar - $totalOutDollar;
    $balanceDinar = $totalInDinar - $totalOutDinar;
@endphp

<div class="erp-report-summary">
    <div class="erp-report-summary__card">
        <span class="erp-report-summary__label">إجمالي الإيداع ($)</span>
        <span class="erp-report-summary__value num">{{ \App\Helpers\Help::formatMoney($totalInDollar, '$') }}</span>
    </div>
    <div class="erp-report-summary__card">
        <span class="erp-report-summary__label">إجمالي السحب ($)</span>
        <span class="erp-report-summary__value num">{{ \App\Helpers\Help::formatMoney($totalOutDollar, '$') }}</span>
    </div>
    <div class="erp-report-summary__card">
        <span class="erp-report-summary__label">الرصيد ($)</span>
        <span class="erp-report-summary__value num">{{ \App\Helpers\Help::formatMoney($balanceDollar, '$') }}</span>
    </div>
    <div class="erp-report-summary__card">
        <span class="erp-report-summary__label">عدد المعاملات</span>
        <span class="erp-report-summary__value">{{ $transactions->count() }}</span>
    </div>
</div>

@if($totalInDinar > 0 || $totalOutDinar > 0)
<div class="erp-report-summary">
    <div class="erp-report-summary__card">
        <span class="erp-report-summary__label">إجمالي الإيداع (د.ع)</span>
        <span class="erp-report-summary__value num">{{ \App\Helpers\Help::formatMoney($totalInDinar, 'IQD') }}</span>
    </div>
    <div class="erp-report-summary__card">
        <span class="erp-report-summary__label">إجمالي السحب (د.ع)</span>
        <span class="erp-report-summary__value num">{{ \App\Helpers\Help::formatMoney($totalOutDinar, 'IQD') }}</span>
    </div>
    <div class="erp-report-summary__card">
        <span class="erp-report-summary__label">الرصيد (د.ع)</span>
        <span class="erp-report-summary__value num">{{ \App\Helpers\Help::formatMoney($balanceDinar, 'IQD') }}</span>
    </div>
</div>
@endif

<div class="erp-report-table-wrap">
    <table class="erp-report-table">
        <thead>
            <tr>
                <th scope="col">رقم الوصل</th>
                <th scope="col">التاريخ</th>
                <th scope="col">النوع</th>
                <th scope="col">الوصف</th>
                <th scope="col">الإيداع</th>
                <th scope="col">السحب</th>
                <th scope="col">العملة</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transactions as $transaction)
            @php
                $transactionObj = is_array($transaction) ? (object) $transaction : $transaction;
                $type = $transactionObj->type ?? '';
            @endphp
            <tr>
                <td>{{ $transactionObj->id ?? '' }}</td>
                <td>
                    @if(isset($transactionObj->created_at))
                        {{ \Carbon\Carbon::parse($transactionObj->created_at)->format('Y-m-d') }}
                    @else
                        {{ $transactionObj->created ?? '' }}
                    @endif
                </td>
                <td>
                    @if($type == 'inUser')
                        إيداع صندوق
                    @elseif($type == 'outUser')
                        سحب صندوق
                    @elseif($type == 'inUserAmanah')
                        إيداع أمانة
                    @elseif($type == 'outUserAmanah')
                        سحب أمانة
                    @else
                        {{ $type }}
                    @endif
                </td>
                <td class="text-start-rtl">{{ $transactionObj->description ?? '' }}</td>
                <td class="num">
                    @if(in_array($type, ['inUser', 'inUserAmanah']))
                        {{ \App\Helpers\Help::formatMoney(abs($transactionObj->amount ?? 0), $transactionObj->currency ?? '$') }}
                    @else
                        —
                    @endif
                </td>
                <td class="num">
                    @if(in_array($type, ['outUser', 'outUserAmanah']))
                        {{ \App\Helpers\Help::formatMoney(abs($transactionObj->amount ?? 0), $transactionObj->currency ?? '$') }}
                    @else
                        —
                    @endif
                </td>
                <td>{{ $transactionObj->currency ?? '$' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@include('Components.reportFooter', ['config' => $config ?? null])
</div>
</body>
</html>
