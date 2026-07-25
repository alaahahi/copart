{{-- App branding logo for print/reports (fallback to legacy static logos). --}}
@php
    $stored = null;
    if (isset($config)) {
        $stored = data_get($config, 'app_logo');
    }
    if (empty($stored)) {
        $stored = \App\Models\SystemConfig::query()->value('app_logo');
    }

    $resolved = app(\App\Services\SystemBrandingService::class)->resolve($stored);
    $logoSrc = \App\Helpers\Help::publicAssetUrl($resolved)
        ?? \App\Helpers\Help::publicAssetUrl('/img/logo-color.png')
        ?? \App\Helpers\Help::publicAssetUrl('/img/logo.jpg');
@endphp
@if($logoSrc)
    <img src="{{ $logoSrc }}" alt="{{ config('app.name') }}" style="max-height:70px;max-width:140px;object-fit:contain;" />
@endif
