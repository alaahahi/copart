@php
    $isReceipt = ($voucherType ?? 'receipt') === 'receipt';
    $clientName = $clientName ?? ($clientData['client']->name ?? ($clientData['client']->name ?? ''));
    $amountDisplay = isset($amount) ? number_format((float) $amount, ($currency ?? '$') === '$' ? 2 : 0) : '';
    $dateParts = [];
    if (!empty($created)) {
        $dt = \Carbon\Carbon::parse($created);
        $dateParts = [$dt->format('Y'), $dt->format('m'), $dt->format('d')];
    }
    $phone = $config['receipt_phone'] ?? '+964 750 468 0510 / 750 705 3555 / 750 438 0888';
    $address = $config['receipt_address'] ?? '100 M road near Hanouf motel';
    $website = $config['receipt_website'] ?? 'Mklmersin.com';
@endphp
<div class="mkl-voucher-sheet">
    <div class="mkl-header">
        <div class="mkl-logos-left">
            <img src="/img/receipt/copart.png" alt="Copart" class="mkl-partner-logo" onerror="this.style.display='none'">
            <img src="/img/receipt/m-gold.png" alt="M" class="mkl-partner-logo mkl-logo-m" onerror="this.style.display='none'">
            <img src="/img/receipt/aa.png" alt="AA" class="mkl-partner-logo" onerror="this.style.display='none'">
        </div>
        <div class="mkl-logos-right">
            <div class="mkl-haulf">HAULF</div>
            <div class="mkl-shipping-block">
                <img src="/img/logo.jpg" alt="MKL Shipping" class="mkl-main-logo" onerror="this.style.display='none'">
                <div class="mkl-branch">Georgia Branch</div>
                <div class="mkl-branch-url">GEORGIA.MKLSHIPPING.COM</div>
            </div>
        </div>
    </div>

    <div class="mkl-type-bar">
        <div class="mkl-type-options">
            <label class="mkl-type-item">
                <span class="mkl-check {{ !$isReceipt ? 'checked' : '' }}"></span>
                <span>
                    <span class="mkl-kr">وەسڵی پارەدان</span>
                    <span class="mkl-ar">وصل صرف</span>
                    <span class="mkl-en">Payment Voucher</span>
                </span>
            </label>
            <label class="mkl-type-item">
                <span class="mkl-check {{ $isReceipt ? 'checked' : '' }}"></span>
                <span>
                    <span class="mkl-kr">وەسڵی وەرگرتن</span>
                    <span class="mkl-ar">وصل قبض</span>
                    <span class="mkl-en">Receipt voucher</span>
                </span>
            </label>
        </div>
        <div class="mkl-currency-box">
            <div class="mkl-amount-cell">{{ $amountDisplay }}</div>
            <div class="mkl-currency-label">دولار</div>
        </div>
    </div>

    <div class="mkl-date-row">
        <span class="mkl-date-label">به‌روار / التاريخ :</span>
        <span class="mkl-date-value">
            {{ $dateParts[0] ?? '202' }} / {{ $dateParts[1] ?? '' }} / {{ $dateParts[2] ?? '' }}
        </span>
    </div>

    <div class="mkl-field-row">
        <span class="mkl-field-en">Payer / Payee:</span>
        <span class="mkl-field-value">{{ $clientName }}</span>
        <span class="mkl-field-ar">
            <span class="mkl-kr">وەرگیرا لە / دراوە</span>
            <span>استلمت من / دفعت الى</span>
        </span>
    </div>

    <div class="mkl-field-row">
        <span class="mkl-field-en">Amount:</span>
        <span class="mkl-field-value">{{ $amountDisplay }} {{ $currency ?? '$' }}</span>
        <span class="mkl-field-ar">
            <span class="mkl-kr">بڕی پارە</span>
            <span>المبلغ</span>
        </span>
    </div>

    <div class="mkl-field-row mkl-split-row">
        <span class="mkl-field-en">VIN:</span>
        <span class="mkl-field-value mkl-half">{{ $vin ?? '' }}</span>
        <span class="mkl-field-en mkl-lot-label">LOT:</span>
        <span class="mkl-field-value mkl-half">{{ $lot ?? '' }}</span>
    </div>

    <div class="mkl-field-row mkl-split-row">
        <span class="mkl-field-en">Paid up:</span>
        <span class="mkl-field-value mkl-half">{{ $paidUp ?? '' }}</span>
        <span class="mkl-field-ar mkl-mid">وەرگیراو / المدفوع</span>
        <span class="mkl-field-en mkl-rest-label">Rest:</span>
        <span class="mkl-field-value mkl-half">{{ $rest ?? '' }}</span>
        <span class="mkl-field-ar">ماوە / الباقي</span>
    </div>

    @if(!empty($description))
    <div class="mkl-field-row">
        <span class="mkl-field-en">Notes:</span>
        <span class="mkl-field-value">{{ $description }}</span>
        <span class="mkl-field-ar">ملاحظات</span>
    </div>
    @endif

    <div class="mkl-signatures">
        <div class="mkl-sign-box">
            <span class="mkl-kr">دراوە لەلایەن</span>
            <span>المسلم</span>
        </div>
        <div class="mkl-sign-box">
            <span class="mkl-kr">وەرگر</span>
            <span>المستلم</span>
        </div>
    </div>

    <div class="mkl-footer">
        <span>📞 {{ $phone }}</span>
        <span>📍 {{ $address }}</span>
        <span>🌐 {{ $website }}</span>
    </div>
</div>
