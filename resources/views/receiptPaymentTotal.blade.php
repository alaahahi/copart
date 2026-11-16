<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
@include('Components.reportHead', ['pageTitle' => 'تقرير جميع الدفعات'])
</head>
<body class="erp-report">
@include('Components.reportToolbar')
@php
    // Clients/Show print=4 uses $clientData; never touch removed wallets table.
    $payload = $clientData ?? null;
    $client = $payload['client'] ?? null;
    $transactions = collect($payload['transactions'] ?? []);

    // Match Clients/Show payments table: type=out, is_pay=1, amount != 0
    $payments = $transactions
        ->filter(function ($row) {
            $amount = (float) ($row->amount ?? 0);
            return ($row->type ?? null) === 'out'
                && (int) ($row->is_pay ?? 0) === 1
                && $amount !== 0.0
                && empty($row->deleted_at);
        })
        ->values();

    $deposits = $payments->filter(fn ($row) => (float) $row->amount < 0);
    $withdrawals = $payments->filter(fn ($row) => (float) $row->amount > 0);

    $depositsTotal = abs((float) $deposits->sum('amount'));
    $withdrawalsTotal = abs((float) $withdrawals->sum('amount'));
    $netPaid = $depositsTotal - $withdrawalsTotal;

    $carsSum = (float) ($payload['cars_sum'] ?? 0);
    $clientBalance = (float) ($payload['client_balance'] ?? 0);
    $carTotal = (int) ($payload['car_total'] ?? 0);

    $from = request('from');
    $to = request('to');
    $hasDateRange = $from && $to && $from !== '0' && $to !== '0';

    $accountLabel = function ($row) {
        $ma = $row->money_account ?? null;
        if (is_array($ma)) {
            return $ma['name_ar'] ?? $ma['name'] ?? '—';
        }
        return '—';
    };
@endphp
<div class="erp-report-page">
@include('Components.reportHeader', [
    'title' => 'تقرير جميع الدفعات',
    'subtitle' => $client->name ?? null,
    'config' => $config ?? null,
])

    <div class="erp-report-meta">
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">الحساب:</span>
            <span class="erp-report-meta__value">{{ $client->name ?? '—' }}</span>
        </div>
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">الهاتف:</span>
            <span class="erp-report-meta__value">{{ $client->phone ?? '—' }}</span>
        </div>
        @if($hasDateRange)
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">من تاريخ:</span>
            <span class="erp-report-meta__value">{{ $from }}</span>
        </div>
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">حتى تاريخ:</span>
            <span class="erp-report-meta__value">{{ $to }}</span>
        </div>
        @endif
        <div class="erp-report-meta__item">
            <span class="erp-report-meta__label">عدد الحركات:</span>
            <span class="erp-report-meta__value">{{ $payments->count() }}</span>
        </div>
    </div>

    <div class="erp-report-summary">
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">مجموع السيارات</span>
            <span class="erp-report-summary__value num">{{ \App\Helpers\Help::formatMoney($carsSum, '$') }}</span>
        </div>
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">إجمالي الدفعات</span>
            <span class="erp-report-summary__value num">{{ \App\Helpers\Help::formatMoney($depositsTotal, '$') }}</span>
        </div>
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">إجمالي السحوبات</span>
            <span class="erp-report-summary__value num">{{ \App\Helpers\Help::formatMoney($withdrawalsTotal, '$') }}</span>
        </div>
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">صافي المدفوع</span>
            <span class="erp-report-summary__value num">{{ \App\Helpers\Help::formatMoney($netPaid, '$') }}</span>
        </div>
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">المتبقي على الحساب</span>
            <span class="erp-report-summary__value num">{{ \App\Helpers\Help::formatMoney($clientBalance, '$') }}</span>
        </div>
        @if($carTotal > 0)
        <div class="erp-report-summary__card">
            <span class="erp-report-summary__label">عدد السيارات</span>
            <span class="erp-report-summary__value">{{ $carTotal }}</span>
        </div>
        @endif
    </div>

    <div class="erp-report-table-wrap">
        <table class="erp-report-table">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">رقم الوصل</th>
                    <th scope="col">التاريخ</th>
                    <th scope="col">البيان</th>
                    <th scope="col">الحساب</th>
                    <th scope="col">النوع</th>
                    <th scope="col">المبلغ</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $index => $row)
                @php
                    $amount = (float) $row->amount;
                    $isWithdraw = $amount > 0;
                    $displayAmount = $amount * -1; // same sign flip as Clients/Show.vue
                @endphp
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $row->id }}</td>
                    <td>{{ $row->created ?? \Illuminate\Support\Carbon::parse($row->created_at)->format('Y-m-d') }}</td>
                    <td class="text-start-rtl">{{ $row->description }}</td>
                    <td>{{ $accountLabel($row) }}</td>
                    <td>{{ $isWithdraw ? 'سحب' : 'دفعة' }}</td>
                    <td class="num">{{ \App\Helpers\Help::formatMoney($displayAmount, $row->currency ?? '$') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">لا توجد دفعات ضمن الفترة المحددة</td>
                </tr>
                @endforelse
            </tbody>
            @if($payments->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="6">الإجمالي (صافي)</td>
                    <td class="num">{{ \App\Helpers\Help::formatMoney($netPaid, '$') }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

@include('Components.reportFooter', ['config' => $config ?? null])
</div>
</body>
</html>
