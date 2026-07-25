<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
@include('Components.reportHead', ['pageTitle' => 'تقرير دفعات الصندوق'])
</head>
<body class="erp-report">
@include('Components.reportToolbar')
<div class="erp-report-page">
@include('Components.reportHeader', ['title' => 'تقرير دفعات الصندوق', 'config' => $config ?? null])

    <div class="erp-report-meta">
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">الحساب:</span>
            <span class="erp-report-meta__value">{{ $clientData['client']->name }}</span>
        </div>
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">الهاتف:</span>
            <span class="erp-report-meta__value">{{ $clientData['client']->phone }}</span>
        </div>
        @if(request('from'))
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">من تاريخ:</span>
            <span class="erp-report-meta__value">{{ request('from') }}</span>
        </div>
        @endif
        @if(request('to'))
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">حتى تاريخ:</span>
            <span class="erp-report-meta__value">{{ request('to') }}</span>
        </div>
        @endif
    </div>

    <div class="erp-report-summary">
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">المجموع النهائي</span>
            <span class="erp-report-summary__value num">{{ $clientData['cars_sum'] }}</span>
        </div>
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">المبلغ المدفوع</span>
            <span class="erp-report-summary__value num">{{ $clientData['cars_paid'] }}</span>
        </div>
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">المبلغ المتبقي</span>
            <span class="erp-report-summary__value num">{{ $clientData['cars_need_paid'] }}</span>
        </div>
        @if($clientData['car_total'] ?? null)
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">عدد السيارات</span>
            <span class="erp-report-summary__value">{{ $clientData['car_total'] }}</span>
        </div>
        @endif
    </div>

    <div class="erp-report-table-wrap">
        <table class="erp-report-table">
            <thead>
                <tr>
                    <th scope="col">رقم الوصل</th>
                    <th scope="col">التاريخ</th>
                    <th scope="col">الملاحظة</th>
                    <th scope="col">المبلغ</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($clientData['transactions'] as $data)
                @if($data->type == 'out' && $data->amount < 0 && $data->is_pay == 1)
                <tr>
                    <td>{{ $data->id }}</td>
                    <td>{{ $data->created }}</td>
                    <td class="text-start-rtl">{{ $data->description }}</td>
                    <td class="num">{{ \App\Helpers\Help::formatMoney($data->amount * -1, $data->currency ?? '$') }}</td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
    </div>

@include('Components.reportFooter', ['config' => $config ?? null])
</div>
</body>
</html>
