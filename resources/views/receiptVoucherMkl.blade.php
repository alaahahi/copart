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
            font-family: Tahoma, Arial, sans-serif;
            background: #fff;
            color: #111;
            direction: rtl;
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
            padding: 7px 12px 6px;
            display: flex;
            flex-direction: column;
            font-size: 12px;
            line-height: 1.35;
            page-break-inside: avoid;
            direction: rtl;
        }
        .mkl-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 6px;
        }
        .mkl-logos-left {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 44px;
            flex: 1;
        }
        .mkl-logos-right {
            display: flex;
            align-items: center;
            gap: 10px;
            text-align: center;
            flex-shrink: 0;
        }
        .mkl-partner-logo {
            height: 40px;
            max-width: 72px;
            object-fit: contain;
        }
        .mkl-logo-m { height: 42px; }
        .mkl-haulf-img {
            height: 42px;
            max-width: 110px;
            object-fit: contain;
        }
        .mkl-haulf {
            font-size: 32px;
            font-weight: 900;
            letter-spacing: 1px;
            line-height: 1;
            color: #1a1a1a;
        }
        .mkl-main-logo {
            height: 44px;
            max-width: 110px;
            object-fit: contain;
        }
        .mkl-branch {
            font-size: 11px;
            font-weight: 700;
            margin-top: 2px;
        }
        .mkl-branch-url {
            font-size: 10px;
            color: #444;
            direction: ltr;
            unicode-bidi: isolate;
        }
        .mkl-type-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #d9d9d9;
            border: 1px solid #999;
            padding: 6px 10px;
            gap: 16px;
            width: 100%;
        }
        .mkl-type-options {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1 1 auto;
            min-width: 0;
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
        .mkl-ar { display: block; font-weight: 700; font-size: 12px; }
        .mkl-en { display: block; font-size: 10px; font-style: italic; direction: ltr; unicode-bidi: isolate; }
        .mkl-currency-box {
            display: flex;
            align-items: stretch;
            border: 1px solid #222;
            min-width: 140px;
            flex: 0 0 auto;
            margin-inline-start: auto;
        }
        .mkl-amount-cell {
            flex: 1;
            min-width: 80px;
            background: #fff;
            border-inline-end: 1px solid #222;
            padding: 6px 10px;
            font-size: 15px;
            font-weight: 700;
            text-align: center;
            direction: ltr;
            unicode-bidi: isolate;
        }
        .mkl-currency-label {
            background: #111;
            color: #fff;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 54px;
        }
        .mkl-date-row {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 8px;
            margin: 6px 0 8px;
            font-size: 12px;
            font-weight: 600;
        }
        .mkl-date-value {
            border: 1px solid #666;
            border-radius: 14px;
            padding: 3px 14px;
            min-width: 100px;
            text-align: center;
            background: #fff;
            font-size: 12px;
            direction: ltr;
            unicode-bidi: isolate;
        }
        .mkl-field-row {
            display: grid;
            grid-template-columns: 118px 1fr 88px;
            align-items: end;
            gap: 8px;
            margin-bottom: 7px;
            font-size: 12px;
        }
        .mkl-split-row {
            grid-template-columns: 52px 1fr 52px 1fr;
            align-items: end;
            direction: ltr;
        }
        .mkl-dual-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 7px;
        }
        .mkl-dual-group {
            display: grid;
            grid-template-columns: 70px 1fr 58px;
            align-items: end;
            gap: 6px;
        }
        .mkl-field-en {
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            direction: ltr;
            unicode-bidi: isolate;
            text-align: left;
        }
        .mkl-field-value {
            border-bottom: 1px dotted #333;
            min-height: 18px;
            padding: 1px 4px 2px;
            font-weight: 700;
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-align: right;
        }
        .mkl-amount-words {
            white-space: normal;
            overflow: visible;
            text-overflow: unset;
            line-height: 1.4;
        }
        .mkl-amount-words strong {
            font-weight: 800;
            margin-inline-start: 4px;
        }
        .mkl-field-ar {
            text-align: right;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.2;
            min-width: 100px;
        }
        .mkl-signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin: 10px 0 6px;
            text-align: center;
            font-size: 12px;
            font-weight: 700;
        }
        .mkl-sign-box {
            border-top: 1px dotted #666;
            padding-top: 22px;
        }
        .mkl-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            border-top: 2px solid #4fc3f7;
            padding-top: 5px;
            margin-top: auto;
            font-size: 10px;
            font-weight: 600;
            color: #222;
            direction: rtl;
        }
        .mkl-footer span {
            white-space: nowrap;
        }
        .mkl-footer bdi {
            unicode-bidi: isolate;
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
                'isCarPayment' => $isCarPayment ?? false,
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
                'isCarPayment' => $isCarPayment ?? false,
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
