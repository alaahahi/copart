@php
    $currency = '$';
    $description = '';
    $amount = 0;
    $created = null;

    if (! empty($transactions_id)) {
        foreach (($clientData['transactions'] ?? []) as $transaction) {
            if ((int) $transaction->id === (int) $transactions_id) {
                $currency = $transaction->currency;
                $description = $transaction->description;
                $amount = $transaction->amount;
                $created = $transaction->created_at;
                break;
            }
        }
    }
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <title>{{ \App\Support\Branding::resolveName((string) data_get($config, 'first_title_ar', '')) }} — وصل صرف</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('Components.receiptDefaultStyles')
</head>
<body>
<div class="rv-page">
    @include('Components.receiptDefaultCopy', ['voucherKind' => 'payment', 'copyLabel' => 'الأصل'])
    <div class="rv-cut" aria-hidden="true">قص من هنا</div>
    @include('Components.receiptDefaultCopy', ['voucherKind' => 'payment', 'copyLabel' => 'النسخة'])
</div>
<script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
