@php
    $footerCompany = $companyName ?? null;
    if (empty($footerCompany)) {
        $cfg = $config ?? null;
        if ($cfg instanceof \App\Models\SystemConfig) {
            $footerCompany = $cfg->first_title_ar;
        } elseif (is_array($cfg)) {
            $footerCompany = $cfg['first_title_ar'] ?? null;
        }
    }
    $footerCompany = $footerCompany ?: (string) config('app.name', '');
@endphp
<footer class="erp-report-footer" dir="rtl">
    <span class="erp-report-footer__company">{{ $footerCompany }}</span>
    <span>صفحة للطباعة · A4</span>
</footer>
