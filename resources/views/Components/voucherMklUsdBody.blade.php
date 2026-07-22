@php
    $isReceipt = ($voucherType ?? 'receipt') === 'receipt';
    $clientName = $clientName ?? ($clientData['client']->name ?? '');
    $amountDisplay = isset($amount) ? number_format((float) $amount, ($currency ?? '$') === '$' ? 2 : 0) : '';
    $dateParts = [];
    if (!empty($created)) {
        $dt = \Carbon\Carbon::parse($created);
        $dateParts = [$dt->format('Y'), $dt->format('m'), $dt->format('d')];
    }
    $cfg = [];
    if ($config instanceof \Illuminate\Database\Eloquent\Model) {
        $cfg = $config->toArray();
    } elseif (is_array($config ?? null)) {
        $cfg = $config;
    }
    $phone = $cfg['receipt_phone'] ?? '+964 750 468 0510 / 750 705 3555 / 750 438 0888';
    $address = $cfg['receipt_address'] ?? '100 M road near Hanouf motel';
    $website = $cfg['receipt_website'] ?? 'Mklmersin.com';
    $logoLeft1 = $cfg['receipt_logo_left_1'] ?? null;
    $logoLeft2 = $cfg['receipt_logo_left_2'] ?? null;
    $logoLeft3 = $cfg['receipt_logo_left_3'] ?? null;
    $logoHaulf = $cfg['receipt_logo_haulf'] ?? null;
    $logoMain = $cfg['receipt_logo_main'] ?? '/img/logo.jpg';
@endphp
<div class="mkl-voucher-sheet">
    <div class="mkl-header">
        <div class="mkl-logos-brand">
            @if($logoHaulf)
                <img src="{{ $logoHaulf }}" alt="HAULF" class="mkl-haulf-img">
            @else
                <div class="mkl-haulf">HAULF</div>
            @endif
            <div class="mkl-shipping-block">
                @if($logoMain)
                    <img src="{{ $logoMain }}" alt="Logo" class="mkl-main-logo">
                @endif
                <div class="mkl-branch">Georgia Branch</div>
                <div class="mkl-branch-url">GEORGIA.MKLSHIPPING.COM</div>
            </div>
        </div>
        <div class="mkl-logos-partners">
            @if($logoLeft1)
                <img src="{{ $logoLeft1 }}" alt="" class="mkl-partner-logo">
            @endif
            @if($logoLeft2)
                <img src="{{ $logoLeft2 }}" alt="" class="mkl-partner-logo mkl-logo-m">
            @endif
            @if($logoLeft3)
                <img src="{{ $logoLeft3 }}" alt="" class="mkl-partner-logo">
            @endif
        </div>
    </div>

    <div class="mkl-type-bar">
        <div class="mkl-currency-box">
            <div class="mkl-currency-label">دولار</div>
            <div class="mkl-amount-cell" dir="ltr">{{ $amountDisplay }}</div>
        </div>
        <div class="mkl-type-options">
            <label class="mkl-type-item">
                <span class="mkl-check {{ !$isReceipt ? 'checked' : '' }}"></span>
                <span>
                    <span class="mkl-ar">وصل صرف</span>
                    <span class="mkl-kr">وەسڵی پارەدان</span>
                    <span class="mkl-en">Payment Voucher</span>
                </span>
            </label>
            <label class="mkl-type-item">
                <span class="mkl-check {{ $isReceipt ? 'checked' : '' }}"></span>
                <span>
                    <span class="mkl-ar">وصل قبض</span>
                    <span class="mkl-kr">وەسڵی وەرگرتن</span>
                    <span class="mkl-en">Receipt voucher</span>
                </span>
            </label>
        </div>
    </div>

    <div class="mkl-date-row">
        <span class="mkl-date-label">به‌روار / التاريخ :</span>
        <span class="mkl-date-value" dir="ltr">
            {{ $dateParts[0] ?? '202' }} / {{ $dateParts[1] ?? '' }} / {{ $dateParts[2] ?? '' }}
        </span>
    </div>

    <div class="mkl-field-row">
        <span class="mkl-field-ar">
            <span class="mkl-kr">وەرگیرا لە / دراوە</span>
            <span>استلمت من / دفعت الى</span>
        </span>
        <span class="mkl-field-value">{{ $clientName }}</span>
        <span class="mkl-field-en">Payer / Payee:</span>
    </div>

    <div class="mkl-field-row">
        <span class="mkl-field-ar">
            <span class="mkl-kr">بڕی پارە</span>
            <span>المبلغ</span>
        </span>
        <span class="mkl-field-value" dir="ltr">{{ $amountDisplay }} {{ $currency ?? '$' }}</span>
        <span class="mkl-field-en">Amount:</span>
    </div>

    <div class="mkl-field-row mkl-split-row">
        <span class="mkl-field-en">VIN:</span>
        <span class="mkl-field-value" dir="ltr">{{ $vin ?? '' }}</span>
        <span class="mkl-field-en">LOT:</span>
        <span class="mkl-field-value" dir="ltr">{{ $lot ?? '' }}</span>
    </div>

    <div class="mkl-dual-row">
        <div class="mkl-dual-group">
            <span class="mkl-field-ar">
                <span class="mkl-kr">وەرگیراو</span>
                <span>المدفوع</span>
            </span>
            <span class="mkl-field-value" dir="ltr">{{ $paidUp ?? '' }}</span>
            <span class="mkl-field-en">Paid up:</span>
        </div>
        <div class="mkl-dual-group">
            <span class="mkl-field-ar">
                <span class="mkl-kr">ماوە</span>
                <span>الباقي</span>
            </span>
            <span class="mkl-field-value" dir="ltr">{{ $rest ?? '' }}</span>
            <span class="mkl-field-en">Rest:</span>
        </div>
    </div>

    @if(!empty($description))
    <div class="mkl-field-row">
        <span class="mkl-field-ar">ملاحظات</span>
        <span class="mkl-field-value">{{ $description }}</span>
        <span class="mkl-field-en">Notes:</span>
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
        <span>🌐 <bdi>{{ $website }}</bdi></span>
        <span>📍 <bdi>{{ $address }}</bdi></span>
        <span>📞 <bdi dir="ltr">{{ $phone }}</bdi></span>
    </div>
</div>
