@php
    $cfg = is_array($config ?? null) ? $config : (isset($config) ? $config->toArray() : []);
    $isReceipt = ($voucherKind ?? 'receipt') === 'receipt';
    $brandName = \App\Support\Branding::resolveName((string) ($cfg['first_title_ar'] ?? ''));
    $subtitle = trim((string) ($cfg['second_title_ar'] ?? ''));
    if (in_array($subtitle, ['', 'Laravel'], true)) {
        $subtitle = '';
    }

    $footer = \App\Support\VoucherPrint::footerContact($cfg);
    $phone = $footer['phone'];
    $address = $footer['address'];
    $website = $footer['website'];

    $clientName = $clientData['client']->name ?? '—';
    $currency = $currency ?? '$';
    $amountNum = (float) ($amount ?? 0);
    $amountText = \App\Helpers\Help::formatMoney($amountNum, $currency);
    $amountWords = \App\Helpers\Help::numberToWords($amountNum, $currency);
    $createdDisplay = ! empty($created)
        ? \Carbon\Carbon::parse($created)->timezone((string) config('app.timezone'))->format('Y-m-d H:i')
        : '—';

    $titleAr = $isReceipt ? 'وصل قبض' : 'وصل صرف';
    $titleEn = $isReceipt ? 'Receipt Voucher' : 'Payment Voucher';
    $partyAr = $isReceipt ? 'استلمت من' : 'دفعت إلى';
    $partyEn = $isReceipt ? 'Received from' : 'Paid to';
    $currencyMark = in_array($currency, ['IQD', 'iqd'], true) ? 'د.ع' : '$';
    $copyLabel = $copyLabel ?? 'الأصل';
    $sheetClass = $isReceipt ? 'rv-sheet rv-sheet--receipt' : 'rv-sheet rv-sheet--payment';
@endphp
<section class="{{ $sheetClass }}">
    <div class="rv-bar" aria-hidden="true"></div>
    <div class="rv-mark" aria-hidden="true">{{ $titleAr }}</div>

    <header class="rv-head">
        <div class="rv-brand">
            <div class="rv-brand-name">{{ $brandName }}</div>
            @if($subtitle !== '')
                <div class="rv-brand-sub">{{ $subtitle }}</div>
            @endif
            <span class="rv-copy">{{ $copyLabel }}</span>
        </div>
        <div class="rv-title">
            <div class="rv-title-ar">{{ $titleAr }}</div>
            <div class="rv-title-en">{{ $titleEn }}</div>
        </div>
        <div class="rv-logo">
            @include('Components.logo')
        </div>
    </header>

    <div class="rv-meta">
        <div class="rv-meta-card">
            <span>الرقم / No.</span>
            <strong>{{ $transactions_id ?? '—' }}</strong>
        </div>
        <div class="rv-meta-card">
            <span>التاريخ / Date</span>
            <strong dir="ltr">{{ $createdDisplay }}</strong>
        </div>
    </div>

    <div class="rv-fields">
        <div class="rv-row">
            <span class="rv-label">شركة / Company</span>
            <span class="rv-value">{{ $clientName }}</span>
        </div>
        <div class="rv-row">
            <span class="rv-label">{{ $partyAr }} / {{ $partyEn }}</span>
            <span class="rv-value">{{ $clientName }}</span>
        </div>
        <div class="rv-row">
            <span class="rv-label">مبلغ قدره / In words</span>
            <span class="rv-value" dir="rtl"><bdi>{{ $amountWords }}</bdi></span>
        </div>
        <div class="rv-row">
            <span class="rv-label">الملاحظات / Notes</span>
            <span class="rv-value">
                {{ $description ?: '—' }}
                @if(!empty($isCarPayment) && filled($lotNumber ?? null))
                    <span class="rv-chip">LOT: {{ $lotNumber }}</span>
                @endif
                @if(!empty($isCarPayment) && filled($restAmount ?? null))
                    <span class="rv-chip">المتبقي: {{ $restAmount }} {{ $currencyMark }}</span>
                @endif
            </span>
        </div>
    </div>

    <div class="rv-bottom">
        <div class="rv-amount">
            <span>المبلغ / AMOUNT</span>
            <div class="rv-amount-num" dir="ltr">
                <em>{{ $currencyMark }}</em>
                <strong>{{ $amountText }}</strong>
            </div>
        </div>
        <div class="rv-sign">
            <div class="rv-sign-line"></div>
            <div>المستلم / Received</div>
        </div>
        <div class="rv-sign">
            <div class="rv-sign-line"></div>
            <div>المحاسب / Accountant</div>
        </div>
    </div>

    @if($address !== '' || $phone !== '' || $website !== '')
        <footer class="rv-foot">
            @if($website !== '')
                <span dir="ltr">🌐 {{ $website }}</span>
            @endif
            @if($address !== '')
                <span>📍 العنوان: {{ $address }}</span>
            @endif
            @if($phone !== '')
                <span dir="ltr">📞 {{ $phone }}</span>
            @endif
        </footer>
    @endif
</section>
