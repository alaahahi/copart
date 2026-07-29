{{-- Shared IntelliJ App credit (screen + print reports stay in sync with Vue IntelliJCredit) --}}
@php
    $intellijYear = $year ?? date('Y');
    $intellijUrl = $url ?? 'https://intellij-app.com/';
    $intellijProduct = $product ?? 'HAULF';
    $intellijBrand = $brand ?? 'IntelliJ App';
    $intellijPoweredBy = $poweredBy ?? 'بواسطة';
    $asPlain = !empty($plain);
@endphp
<span class="intellij-credit" dir="rtl">
    © {{ $intellijYear }} {{ $intellijProduct }} · {{ $intellijPoweredBy }}
    @if($asPlain)
        <span class="intellij-credit__brand">{{ $intellijBrand }}</span>
        <span class="intellij-credit__url">({{ $intellijUrl }})</span>
    @else
        <a
            class="intellij-credit__link"
            href="{{ $intellijUrl }}"
            target="_blank"
            rel="noopener noreferrer"
        >{{ $intellijBrand }}</a>
    @endif
</span>
