{{-- Sticky print toolbar (hidden on print via .erp-report-toolbar) --}}
<div class="erp-report-toolbar no-print" dir="rtl">
    <p class="erp-report-toolbar__hint">{{ $toolbarHint ?? 'معاينة التقرير — راجع البيانات ثم اطبع' }}</p>
    <div class="erp-report-toolbar__actions">
        <button type="button" class="btn-print" onclick="window.print()">طباعة</button>
        <button type="button" class="btn-back" onclick="window.history.back()">رجوع</button>
    </div>
</div>
