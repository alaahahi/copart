@php
    $footer = \App\Support\VoucherPrint::footerContact($config ?? null);
    $phone = $footer['phone'];
    $address = $footer['address'];
    $website = $footer['website'];
@endphp
@if($address !== '' || $phone !== '' || $website !== '')
<div class="row text-center mt-2" style="font-size: 12px; color: #0b1220;">
    @if($website !== '')
        <div class="col-12 col-md-4 py-1" dir="ltr">🌐 {{ $website }}</div>
    @endif
    @if($address !== '')
        <div class="col-12 col-md-4 py-1">📍 {{ $address }}</div>
    @endif
    @if($phone !== '')
        <div class="col-12 col-md-4 py-1" dir="ltr">📞 {{ $phone }}</div>
    @endif
</div>
@endif
