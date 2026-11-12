<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
@include('Components.reportHead', ['pageTitle' => 'كشف حساب'])
<style>
    /* Summary cards stay larger */
    .erp-report-summary__value,
    .erp-report-summary__value.num {
        text-align: center !important;
        font-size: 20px !important;
        font-weight: 800 !important;
    }

    /* Statement list: one size + bold for every cell */
    table.erp-report-table.erp-report-table--dense thead th,
    table.erp-report-table.erp-report-table--dense tbody td,
    table.erp-report-table.erp-report-table--dense tbody th {
        font-size: 10px !important;
        font-weight: 800 !important;
        text-align: center !important;
        vertical-align: middle !important;
        overflow: hidden !important;
        letter-spacing: 0.01em;
    }
    table.erp-report-table.erp-report-table--dense td.cell-date,
    table.erp-report-table.erp-report-table--dense th.cell-date {
        white-space: nowrap !important;
        padding-left: 1px !important;
        padding-right: 1px !important;
        max-width: 4.5rem;
    }
    table.erp-report-table.erp-report-table--dense td.cell-vin {
        white-space: nowrap !important;
        letter-spacing: 0 !important;
        text-overflow: clip;
    }
    table.erp-report-table.erp-report-table--dense td.cell-car {
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    table.erp-report-table.erp-report-table--dense td.num {
        font-variant-numeric: tabular-nums;
        white-space: nowrap !important;
        direction: ltr;
        unicode-bidi: isolate;
    }
</style>
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
            <span class="erp-report-summary__value num">{{ $clientData['car_total'] }}</span>
        </div>
    </div>

    @php
        $rows = collect($clientData['data'] ?? []);
        $hasNum = static function (string $field) use ($rows): bool {
            return $rows->contains(static function ($row) use ($field) {
                return abs((float) ($row->{$field} ?? 0)) >= 0.01;
            });
        };
        $hasText = static function (string $field) use ($rows): bool {
            return $rows->contains(static function ($row) use ($field) {
                return trim((string) ($row->{$field} ?? '')) !== '';
            });
        };
        $hasErbilTransfer = $rows->contains(static function ($row) {
            return abs((float) \App\Models\Car::erbilTransferSubtotal($row->getAttributes(), true)) >= 0.01;
        });

        $showUsaPrice = $hasNum('shipping_dolar_s');
        $showTowingUsa = $hasNum('dinar_s');
        $showShipping = $hasNum('coc_dolar_s');
        $showRepair = $hasNum('checkout_s');
        $showErbilTransfer = $hasErbilTransfer;
        $showErbilExpenses = $hasNum('commission_s');
        $showPaid = $hasNum('paid');
        $showDiscount = $hasNum('discount');
        $showDamage = $hasNum('damage_compensation');
        $showNote = $hasText('note');
    @endphp

    @php
        $dateLabel = static function ($value): string {
            if ($value === null || $value === '') {
                return '';
            }
            try {
                return \Carbon\Carbon::parse($value)->format('y-m-d');
            } catch (\Throwable $e) {
                return (string) $value;
            }
        };
    @endphp

    <div class="erp-report-table-wrap erp-report-table-wrap--fit">
        <table class="erp-report-table erp-report-table--dense">
            <colgroup>
                <col style="width:3%">
                <col style="width:14%">
                <col style="width:7%">
                <col style="width:15%">
                <col style="width:8%">
                <col style="width:6%">
                <col style="width:5%">
                @if ($showUsaPrice)<col style="width:7%">@endif
                @if ($showTowingUsa)<col style="width:6%">@endif
                @if ($showShipping)<col style="width:5%">@endif
                @if ($showRepair)<col style="width:6%">@endif
                @if ($showErbilTransfer)<col style="width:6%">@endif
                @if ($showErbilExpenses)<col style="width:6%">@endif
                <col style="width:7%">
                @if ($showPaid)<col style="width:6%">@endif
                @if ($showDiscount)<col style="width:5%">@endif
                @if ($showDamage)<col style="width:6%">@endif
                <col style="width:7%">
                @if ($showNote)<col style="width:8%">@endif
            </colgroup>
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">السيارة</th>
                    <th scope="col" class="cell-date">التاريخ</th>
                    <th scope="col">رقم الشاصي</th>
                    <th scope="col">رقم كوبارت</th>
                    <th scope="col">اللون</th>
                    <th scope="col">الموديل</th>
                    @if ($showUsaPrice)<th scope="col">سعر أمريكا</th>@endif
                    @if ($showTowingUsa)<th scope="col">نقل امريكا</th>@endif
                    @if ($showShipping)<th scope="col">شحن</th>@endif
                    @if ($showRepair)<th scope="col">مصاريف تصليح</th>@endif
                    @if ($showErbilTransfer)<th scope="col">نقل أربيل</th>@endif
                    @if ($showErbilExpenses)<th scope="col">مصاريف أربيل</th>@endif
                    <th scope="col">المجموع</th>
                    @if ($showPaid)<th scope="col">مدفوع</th>@endif
                    @if ($showDiscount)<th scope="col">خصم</th>@endif
                    @if ($showDamage)<th scope="col">تعويض ضرر</th>@endif
                    <th scope="col">متبقي</th>
                    @if ($showNote)<th scope="col">ملاحظة</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $key => $row)
                <tr>
                    <td class="num">{{ $key + 1 }}</td>
                    <td class="cell-car">{{ $row->car_type }}</td>
                    <td class="cell-date">{{ $dateLabel($row->date) }}</td>
                    <td class="cell-vin" dir="ltr">{{ $row->vin }}</td>
                    <td class="num">{{ $row->car_number }}</td>
                    <td>{{ $row->car_color }}</td>
                    <td class="num">{{ $row->year }}</td>
                    @if ($showUsaPrice)<td class="num">{{ $row->shipping_dolar_s }}</td>@endif
                    @if ($showTowingUsa)<td class="num">{{ $row->dinar_s }}</td>@endif
                    @if ($showShipping)<td class="num">{{ $row->coc_dolar_s }}</td>@endif
                    @if ($showRepair)<td class="num">{{ $row->checkout_s }}</td>@endif
                    @if ($showErbilTransfer)<td class="num">{{ \App\Models\Car::erbilTransferSubtotal($row->getAttributes(), true) }}</td>@endif
                    @if ($showErbilExpenses)<td class="num">{{ $row->commission_s ?? 0 }}</td>@endif
                    <td class="num">{{ $row->total_s }}</td>
                    @if ($showPaid)<td class="num">{{ $row->paid }}</td>@endif
                    @if ($showDiscount)<td class="num">{{ $row->discount ?? 0 }}</td>@endif
                    @if ($showDamage)<td class="num">{{ $row->damage_compensation ?? 0 }}</td>@endif
                    <td class="num">{{ ($row->total_s) - ($row->paid) - ($row->discount ?? 0) - ($row->damage_compensation ?? 0) }}</td>
                    @if ($showNote)<td class="text-start-rtl">{{ $row->note }}</td>@endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

@include('Components.reportFooter', ['config' => $config ?? null])
</div>
</body>
</html>
