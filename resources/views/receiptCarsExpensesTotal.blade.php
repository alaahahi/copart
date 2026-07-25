<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
@include('Components.reportHead', ['pageTitle' => 'تقرير مصاريف السيارات'])
</head>
<body class="erp-report">
@include('Components.reportToolbar')
<div class="erp-report-page">
@include('Components.reportHeader', ['title' => 'تقرير مصاريف السيارات', 'config' => $config ?? null])

    <div class="erp-report-meta">
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">الحساب:</span>
            <span class="erp-report-meta__value">{{ $data['client']->name }}</span>
        </div>
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">الهاتف:</span>
            <span class="erp-report-meta__value">{{ $data['client']->phone }}</span>
        </div>
    </div>

    @php
        $totalAmountDollar = 0;
        $totalAmountDinar = 0;
        $carExpenses = $data['carexpenses'];
        foreach ($carExpenses as $expense) {
            $totalAmountDollar += $expense->amount_dollar;
            $totalAmountDinar += $expense->amount_dinar;
        }
    @endphp

    <div class="erp-report-summary">
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">المجموع بالدولار</span>
            <span class="erp-report-summary__value num">{{ $totalAmountDollar ?? 0 }}</span>
        </div>
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">المجموع بالدينار</span>
            <span class="erp-report-summary__value num">{{ $totalAmountDinar ?? 0 }}</span>
        </div>
    </div>

    <div class="erp-report-table-wrap">
        <table class="erp-report-table">
            <thead>
                <tr>
                    <th scope="col">التاريخ</th>
                    <th scope="col">الملاحظة</th>
                    <th scope="col">المبلغ بالدولار</th>
                    <th scope="col">المبلغ بالدينار</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($carExpenses as $expense)
                <tr>
                    <td>{{ $expense->created }}</td>
                    <td class="text-start-rtl">{{ $expense->note }}</td>
                    <td class="num">{{ \App\Helpers\Help::formatMoney($expense->amount_dollar, '$') }}</td>
                    <td class="num">{{ \App\Helpers\Help::formatMoney($expense->amount_dinar, 'IQD') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

@include('Components.reportFooter', ['config' => $config ?? null])
</div>
</body>
</html>
