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
<style>
    body {
        background: #ffffff !important;
        color: #0f172a !important;
    }
    .erp-report-header {
        background: #ffffff !important;
        color: #0f172a !important;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 12px 16px;
        margin-bottom: 12px;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .erp-report-header__row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .erp-report-header__brand {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
        flex: 1 1 220px;
    }
    .erp-report-header__logo {
        width: 64px;
        height: 64px;
        object-fit: contain;
        flex-shrink: 0;
        background: #ffffff;
    }
    .erp-report-header__names {
        min-width: 0;
        text-align: right;
    }
    .erp-report-header__app-name {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #0f172a !important;
        line-height: 1.35;
    }
    .erp-report-header__app-sub {
        margin: 2px 0 0;
        font-size: 12px;
        font-weight: 500;
        color: #334155 !important;
        line-height: 1.3;
    }
    .erp-report-header__title-block {
        flex: 1 1 200px;
        text-align: center;
    }
    .erp-report-header__title {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: #0f172a !important;
        line-height: 1.3;
    }
    .erp-report-header__subtitle {
        margin: 4px 0 0;
        font-size: 12px;
        font-weight: 500;
        color: #475569 !important;
    }
    .erp-report-header__meta {
        flex: 0 1 160px;
        text-align: left;
        font-size: 12px;
        color: #334155 !important;
        line-height: 1.5;
    }
    .erp-report-header__meta strong {
        color: #0f172a !important;
        font-weight: 600;
    }
    @media print {
        body {
            background: #ffffff !important;
            color: #000000 !important;
        }
        .erp-report-header {
            background: #ffffff !important;
            color: #000000 !important;
            border-color: #94a3b8;
            box-shadow: none !important;
        }
        .erp-report-header__app-name,
        .erp-report-header__title {
            color: #000000 !important;
        }
        .erp-report-header__app-sub,
        .erp-report-header__subtitle,
        .erp-report-header__meta {
            color: #1e293b !important;
        }
    }
</style>
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
