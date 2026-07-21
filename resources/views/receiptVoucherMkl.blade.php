<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }} — {{ ($voucherType ?? 'receipt') === 'receipt' ? 'وصل قبض' : 'وصل صرف' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @page {
            size: A4 portrait;
            margin: 6mm;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, Tahoma, sans-serif;
            background: #fff;
            color: #111;
        }
        .mkl-a4-page {
            width: 100%;
            min-height: 285mm;
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        .mkl-voucher-slot {
            flex: 1 1 0;
            min-height: 0;
            display: flex;
            align-items: stretch;
        }
        .mkl-cut-line {
            flex: 0 0 auto;
            border-top: 1px dashed #888;
            margin: 3mm 0;
        }
        .mkl-voucher-sheet {
            width: 100%;
            border: 1px solid #bbb;
            padding: 5px 10px 4px;
            display: flex;
            flex-direction: column;
            font-size: 10px;
            line-height: 1.25;
            page-break-inside: avoid;
        }
        .mkl-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            margin-bottom: 4px;
        }
        .mkl-logos-left {
            display: flex;
            align-items: center;
            gap: 6px;
            min-height: 38px;
            flex: 1;
        }
        .mkl-partner-logo {
            height: 34px;
            max-width: 64px;
            object-fit: contain;
        }
        .mkl-logo-m { height: 36px; }
        .mkl-logos-right {
            display: flex;
            align-items: center;
            gap: 8px;
            text-align: center;
            flex-shrink: 0;
        }
        .mkl-haulf-img {
            height: 36px;
            max-width: 100px;
            object-fit: contain;
        }
        .mkl-haulf {
            font-size: 28px;
            font-weight: 900;
            letter-spacing: 1px;
            line-height: 1;
            color: #1a1a1a;
        }
        .mkl-main-logo {
            height: 38px;
            max-width: 100px;
            object-fit: contain;
        }
        .mkl-branch {
            font-size: 9px;
            font-weight: 700;
            margin-top: 1px;
        }
        .mkl-branch-url {
            font-size: 8px;
            color: #444;
        }
        .mkl-type-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #d9d9d9;
            border: 1px solid #999;
            padding: 4px 8px;
            gap: 8px;
        }
        .mkl-type-options {
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex: 1;
        }
        .mkl-type-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 9px;
            line-height: 1.15;
        }
        .mkl-check {
            width: 12px;
            height: 12px;
            border: 1.5px solid #222;
            background: #fff;
            display: inline-block;
            flex-shrink: 0;
        }
        .mkl-check.checked::after {
            content: '✓';
            display: block;
            text-align: center;
            font-size: 10px;
            line-height: 10px;
            font-weight: 700;
        }
        .mkl-kr { display: block; font-size: 8px; }
        .mkl-ar { display: block; font-weight: 700; }
        .mkl-en { display: block; font-size: 8px; font-style: italic; }
        .mkl-currency-box {
            display: flex;
            align-items: stretch;
            border: 1px solid #222;
            min-width: 120px;
        }
        .mkl-amount-cell {
            flex: 1;
            min-width: 70px;
            background: #fff;
            border-left: 1px solid #222;
            padding: 4px 8px;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
        }
        .mkl-currency-label {
            background: #111;
            color: #fff;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
        }
        .mkl-date-row {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 6px;
            margin: 4px 0 6px;
            font-size: 10px;
        }
        .mkl-date-value {
            border: 1px solid #666;
            border-radius: 12px;
            padding: 2px 12px;
            min-width: 90px;
            text-align: center;
            background: #fff;
        }
        .mkl-field-row {
            display: grid;
            grid-template-columns: 72px 1fr auto;
            align-items: end;
            gap: 6px;
            margin-bottom: 5px;
            font-size: 10px;
        }
        .mkl-split-row {
            grid-template-columns: 42px 1fr 42px 1fr auto;
        }
        .mkl-field-en {
            font-size: 9px;
            font-weight: 600;
            white-space: nowrap;
        }
        .mkl-field-value {
            border-bottom: 1px dotted #333;
            min-height: 14px;
            padding: 0 3px 1px;
            font-weight: 700;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .mkl-field-ar {
            text-align: left;
            font-size: 9px;
            font-weight: 700;
            line-height: 1.1;
            min-width: 100px;
        }
        .mkl-mid { font-size: 8px; align-self: center; }
        .mkl-signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 8px 0 4px;
            text-align: center;
            font-size: 10px;
            font-weight: 700;
        }
        .mkl-sign-box {
            border-top: 1px dotted #666;
            padding-top: 18px;
        }
        .mkl-footer {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 4px;
            border-top: 2px solid #4fc3f7;
            padding-top: 4px;
            margin-top: auto;
            font-size: 8px;
            color: #222;
        }
        @media print {
            html, body {
                width: 100%;
                height: auto;
            }
            .mkl-a4-page {
                min-height: 0;
                height: 285mm;
            }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="mkl-a4-page">
        <div class="mkl-voucher-slot">
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
        </div>

        <div class="mkl-cut-line"></div>

        <div class="mkl-voucher-slot">
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
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
