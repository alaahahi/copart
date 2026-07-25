<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
@include('Components.reportHead', ['pageTitle' => 'تقرير المصروفات'])
</head>
<body class="erp-report">
@include('Components.reportToolbar')
<div class="erp-report-page">
@include('Components.reportHeader', ['title' => 'تقرير المصروفات', 'config' => $config ?? null])

    <div class="erp-report-meta">
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">الحساب:</span>
            <span class="erp-report-meta__value">{{ $clientData['client']->name }}</span>
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
            <span class="erp-report-summary__value num">{{ $clientData['totalAmount'] }}</span>
        </div>
    </div>

    <div class="erp-report-table-wrap">
        <table class="erp-report-table">
            <thead>
                <tr>
                    <th scope="col">رقم الوصل</th>
                    <th scope="col">التاريخ</th>
                    <th scope="col">المبلغ</th>
                    <th scope="col">البيان</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($clientData['transactions'] as $data)
                @if($data->is_pay == 1)
                <tr>
                    <td>{{ $data->id }}</td>
                    <td>{{ $data->created_at }}</td>
                    <td class="num">{{ \App\Helpers\Help::formatMoney($data->amount, $data->currency ?? '$') }}</td>
                    <td class="text-start-rtl">{{ $data->description }}</td>
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
