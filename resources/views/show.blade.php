<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
@include('Components.reportHead', ['pageTitle' => 'كشف حساب'])
</head>
<body class="erp-report">
@include('Components.reportToolbar')
<div class="erp-report-page">
@include('Components.reportHeader', ['title' => 'كشف حساب', 'config' => $config ?? null])

    <div class="erp-report-meta">
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">الزبون:</span>
            <span class="erp-report-meta__value">{{ $clientData['client']->name }}</span>
        </div>
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">الهاتف:</span>
            <span class="erp-report-meta__value">{{ $clientData['client']->phone }}</span>
        </div>
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">من تاريخ:</span>
            <span class="erp-report-meta__value">{{ request('from') ?: '—' }}</span>
        </div>
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">حتى تاريخ:</span>
            <span class="erp-report-meta__value">{{ request('to') ?: '—' }}</span>
        </div>
    </div>

    <div class="erp-report-summary">
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">المجموع النهائي</span>
            <span class="erp-report-summary__value num">{{ $clientData['cars_sum'] }}</span>
        </div>
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">المبلغ المدفوع</span>
            <span class="erp-report-summary__value num">{{ $clientData['cars_paid'] ?? (($clientData['cars_sum'] ?? 0) - optional(optional($clientData['client'])->wallet)->balance) }}</span>
        </div>
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">المبلغ المتبقي</span>
            <span class="erp-report-summary__value num">{{ $clientData['cars_need_paid'] ?? optional(optional($clientData['client'])->wallet)->balance }}</span>
        </div>
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">عدد السيارات</span>
            <span class="erp-report-summary__value">{{ $clientData['car_total'] }}</span>
        </div>
    </div>

    <div class="erp-report-table-wrap erp-report-table-wrap--fit">
        <table class="erp-report-table erp-report-table--dense">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">السيارة</th>
                    <th scope="col">التاريخ</th>
                    <th scope="col">رقم الشاصي</th>
                    <th scope="col">رقم كوبارت</th>
                    <th scope="col">اللون</th>
                    <th scope="col">الموديل</th>
                    <th scope="col">سعر أمريكا</th>
                    <th scope="col">نقل أمريكا</th>
                    <th scope="col">ريكفري</th>
                    <th scope="col">مصاريف تصليح</th>
                    <th scope="col">نقل أربيل</th>
                    <th scope="col">مصاريف أربيل</th>
                    <th scope="col">المجموع</th>
                    <th scope="col">مدفوع</th>
                    <th scope="col">متبقي</th>
                    <th scope="col">ملاحظة</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($clientData['data'] as $key => $row)
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td class="text-start-rtl">{{ $row->car_type }}</td>
                    <td>{{ $row->date }}</td>
                    <td>{{ $row->vin }}</td>
                    <td>{{ $row->car_number }}</td>
                    <td>{{ $row->car_color }}</td>
                    <td>{{ $row->year }}</td>
                    <td class="num">{{ $row->shipping_dolar_s }}</td>
                    <td class="num">{{ $row->dinar_s }}</td>
                    <td class="num">{{ $row->coc_dolar_s }}</td>
                    <td class="num">{{ $row->checkout_s }}</td>
                    <td class="num">{{ \App\Models\Car::erbilTransferSubtotal($row->getAttributes(), true) }}</td>
                    <td class="num">{{ $row->commission_s ?? 0 }}</td>
                    <td class="num">{{ $row->total_s }}</td>
                    <td class="num">{{ $row->paid }}</td>
                    <td class="num">{{ ($row->total_s) - ($row->paid) }}</td>
                    <td class="text-start-rtl">{{ $row->note }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

@include('Components.reportFooter', ['config' => $config ?? null])
</div>
</body>
</html>
