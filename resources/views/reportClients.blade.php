<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
@include('Components.reportHead', ['pageTitle' => 'تقرير الزبائن'])
</head>
<body class="erp-report">
@include('Components.reportToolbar')
<div class="erp-report-page">
@include('Components.reportHeader', ['title' => 'تقرير الزبائن', 'config' => $config ?? null])

    <div class="erp-report-meta">
        @if(($owner_id ?? '') !== '')
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">الفرع:</span>
            <span class="erp-report-meta__value">
                @if($owner_id == 2)
                    {{ $config['address_kik'] ?? '' }}
                @else
                    {{ $config['address_erb'] ?? '' }}
                @endif
            </span>
        </div>
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">الهاتف:</span>
            <span class="erp-report-meta__value">
                @if($owner_id == 2)
                    {{ $config['mobile_kik'] ?? '' }}
                @else
                    {{ $config['mobile_erb'] ?? '' }}
                @endif
            </span>
        </div>
        @endif
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

    @php
        $totalBalance = 0;
        $totalcar = 0;
        foreach ($data as $item) {
            $totalBalance += $item->balance;
            $totalcar += $item->car_count;
        }
    @endphp

    <div class="erp-report-summary">
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">عدد الزبائن</span>
            <span class="erp-report-summary__value">{{ count($data) }}</span>
        </div>
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">مجموع الدين</span>
            <span class="erp-report-summary__value num">{{ $totalBalance }} $</span>
        </div>
        @if($totalcar)
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">عدد السيارات</span>
            <span class="erp-report-summary__value">{{ $totalcar }}</span>
        </div>
        @endif
    </div>

    <div class="erp-report-table-wrap">
        <table class="erp-report-table">
            <thead>
                <tr>
                    <th scope="col">تسلسل</th>
                    <th scope="col">الاسم</th>
                    <th scope="col">الهاتف</th>
                    <th scope="col">السيارات</th>
                    <th scope="col">غير المدفوعة</th>
                    <th scope="col">المدفوعة</th>
                    <th scope="col">الدين</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data as $key => $row)
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td class="text-start-rtl">{{ $row->name }}</td>
                    <td>{{ $row->phone }}</td>
                    <td>{{ $row->car_count }}</td>
                    <td>{{ $row->car_count - $row->car_count_completed }}</td>
                    <td>{{ $row->car_count_completed }}</td>
                    <td class="num">{{ $row->balance }} $</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

@include('Components.reportFooter', ['config' => $config ?? null])
</div>
</body>
</html>
