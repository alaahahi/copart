<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }} — {{ ($voucherType ?? 'receipt') === 'receipt' ? 'وصل قبض' : 'وصل صرف' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 8px;
            font-family: Arial, Tahoma, sans-serif;
            background: #fff;
            color: #111;
        }
        .mkl-voucher-sheet {
            border: 1px solid #bbb;
            padding: 10px 14px 8px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .mkl-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 6px;
        }
        .mkl-logos-left {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 52px;
        }
        .mkl-partner-logo {
            height: 42px;
            max-width: 72px;
            object-fit: contain;
        }
        .mkl-logo-m { height: 46px; }
        .mkl-logos-right {
            display: flex;
            align-items: center;
            gap: 14px;
            text-align: center;
        }
        .mkl-haulf {
            font-size: 42px;
            font-weight: 900;
            letter-spacing: 1px;
            line-height: 1;
            color: #1a1a1a;
        }
        .mkl-main-logo {
            height: 48px;
            max-width: 120px;
            object-fit: contain;
        }
        .mkl-branch {
            font-size: 11px;
            font-weight: 700;
            margin-top: 2px;
        }
        .mkl-branch-url {
            font-size: 9px;
            color: #444;
        }
        .mkl-type-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #d9d9d9;
            border: 1px solid #999;
            padding: 6px 10px;
            gap: 12px;
        }
        .mkl-type-options {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
        }
        .mkl-type-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            line-height: 1.2;
        }
        .mkl-check {
            width: 14px;
            height: 14px;
            border: 1.5px solid #222;
            background: #fff;
            display: inline-block;
            flex-shrink: 0;
        }
        .mkl-check.checked::after {
            content: '✓';
            display: block;
            text-align: center;
            font-size: 11px;
            line-height: 12px;
            font-weight: 700;
        }
        .mkl-kr { display: block; font-size: 10px; }
        .mkl-ar { display: block; font-weight: 700; }
        .mkl-en { display: block; font-size: 10px; font-style: italic; }
        .mkl-currency-box {
            display: flex;
            align-items: stretch;
            border: 1px solid #222;
            min-width: 160px;
        }
        .mkl-amount-cell {
            flex: 1;
            min-width: 90px;
            background: #fff;
            border-left: 1px solid #222;
            padding: 6px 10px;
            font-size: 14px;
            font-weight: 700;
            text-align: center;
        }
        .mkl-currency-label {
            background: #111;
            color: #fff;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 56px;
        }
        .mkl-date-row {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            margin: 8px 0 10px;
            font-size: 12px;
        }
        .mkl-date-value {
            border: 1px solid #666;
            border-radius: 14px;
            padding: 3px 16px;
            min-width: 110px;
            text-align: center;
            background: #fff;
        }
        .mkl-field-row {
            display: grid;
            grid-template-columns: 90px 1fr auto;
            align-items: end;
            gap: 8px;
            margin-bottom: 10px;
            font-size: 12px;
        }
        .mkl-split-row {
            grid-template-columns: 50px 1fr 50px 1fr auto;
        }
        .mkl-field-en {
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }
        .mkl-field-value {
            border-bottom: 1px dotted #333;
            min-height: 18px;
            padding: 0 4px 2px;
            font-weight: 700;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .mkl-field-ar {
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.15;
            min-width: 120px;
        }
        .mkl-mid { font-size: 10px; align-self: center; }
        .mkl-lot-label, .mkl-rest-label { text-align: center; }
        .mkl-signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 16px 0 10px;
            text-align: center;
            font-size: 12px;
            font-weight: 700;
        }
        .mkl-sign-box {
            border-top: 1px dotted #666;
            padding-top: 28px;
        }
        .mkl-footer {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            border-top: 2px solid #4fc3f7;
            padding-top: 6px;
            margin-top: 6px;
            font-size: 10px;
            color: #222;
        }
        .mkl-copy-sep {
            border: none;
            border-top: 1px dashed #999;
            margin: 12px 0;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    @include('Components.voucherMklUsdBody', [
        'voucherType' => $voucherType ?? 'receipt',
        'clientName' => $clientName ?? '',
        'amount' => $amount ?? 0,
        'currency' => $currency ?? '$',
        'created' => $created ?? now(),
        'description' => $description ?? '',
        'vin' => $vin ?? '',
        'lot' => $lot ?? '',
        'paidUp' => $paidUp ?? '',
        'rest' => $rest ?? '',
        'config' => $config ?? [],
    ])

    <hr class="mkl-copy-sep">

    @include('Components.voucherMklUsdBody', [
        'voucherType' => $voucherType ?? 'receipt',
        'clientName' => $clientName ?? '',
        'amount' => $amount ?? 0,
        'currency' => $currency ?? '$',
        'created' => $created ?? now(),
        'description' => $description ?? '',
        'vin' => $vin ?? '',
        'lot' => $lot ?? '',
        'paidUp' => $paidUp ?? '',
        'rest' => $rest ?? '',
        'config' => $config ?? [],
    ])

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
