{{-- Professional ERP report header: one branding logo + titles (not voucher/MKL multi-logo). --}}
@php
    $cfg = $config ?? null;
    if ($cfg instanceof \App\Models\SystemConfig) {
        $appLogoStored = $cfg->app_logo;
        $firstTitle = $cfg->first_title_ar;
        $secondTitle = $cfg->second_title_ar;
    } else {
        $cfgArr = is_array($cfg) ? $cfg : [];
        $appLogoStored = $cfgArr['app_logo'] ?? null;
        $firstTitle = $cfgArr['first_title_ar'] ?? null;
        $secondTitle = $cfgArr['second_title_ar'] ?? null;
    }

    if (empty($appLogoStored) || empty($firstTitle)) {
        $fresh = \App\Models\SystemConfig::query()->select(['app_logo', 'first_title_ar', 'second_title_ar'])->first();
        $appLogoStored = $appLogoStored ?: ($fresh->app_logo ?? null);
        $firstTitle = $firstTitle ?: ($fresh->first_title_ar ?? null);
        $secondTitle = $secondTitle ?: ($fresh->second_title_ar ?? null);
    }

    $resolvedLogo = app(\App\Services\SystemBrandingService::class)->resolve($appLogoStored);
    $logoUrl = \App\Helpers\Help::publicAssetUrl($resolvedLogo)
        ?? \App\Helpers\Help::publicAssetUrl('/img/logo-color.png')
        ?? \App\Helpers\Help::publicAssetUrl('/img/logo.jpg');

    $appName = $firstTitle ?: (string) config('app.name', '');
    $appSub = $secondTitle ?: null;
    $reportTitle = $title ?? 'تقرير';
    $reportSubtitle = $subtitle ?? null;
@endphp
<header class="erp-report-header" dir="rtl">
    <div class="erp-report-header__row">
        <div class="erp-report-header__brand">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $appName }}" class="erp-report-header__logo">
            @endif
            <div class="erp-report-header__names">
                <p class="erp-report-header__app-name">{{ $appName }}</p>
                @if($appSub)
                    <p class="erp-report-header__app-sub">{{ $appSub }}</p>
                @endif
            </div>
        </div>
        <div class="erp-report-header__title-block">
            <h1 class="erp-report-header__title">{{ $reportTitle }}</h1>
            @if($reportSubtitle)
                <p class="erp-report-header__subtitle">{{ $reportSubtitle }}</p>
            @endif
        </div>
        <div class="erp-report-header__meta">
            <div><strong>تاريخ الطباعة:</strong> {{ date('Y-m-d') }}</div>
            @isset($meta)
                {!! $meta !!}
            @endisset
        </div>
    </div>
</header>
