<style>
    @page { size: A4; margin: 10mm; }
    * { box-sizing: border-box; }
    html, body {
        margin: 0;
        padding: 0;
        background: #fff;
        color: #0f172a;
        font-family: "Segoe UI", Tahoma, Arial, sans-serif;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .rv-page { width: 100%; }
    .rv-sheet {
        border: 1.5px solid #1e293b;
        padding: 14px 16px 12px;
        background: #fff;
    }
    .rv-head {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        gap: 12px;
        padding-bottom: 10px;
        border-bottom: 2px solid #0f172a;
    }
    .rv-brand-name {
        font-size: 18px;
        font-weight: 800;
        letter-spacing: .02em;
        line-height: 1.2;
    }
    .rv-brand-sub { margin-top: 3px; color: #475569; font-size: 12px; }
    .rv-title { text-align: center; }
    .rv-title-ar { font-size: 22px; font-weight: 800; line-height: 1.15; }
    .rv-title-en { margin-top: 3px; color: #334155; font-size: 12px; font-weight: 700; letter-spacing: .04em; }
    .rv-logo { display: flex; justify-content: flex-end; }
    .rv-logo img { max-height: 64px; max-width: 130px; object-fit: contain; }
    .rv-meta {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin: 10px 0;
        padding: 7px 10px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        font-size: 13px;
    }
    .rv-meta span { color: #64748b; margin-inline-end: 6px; }
    .rv-fields { display: grid; gap: 7px; }
    .rv-row {
        display: grid;
        grid-template-columns: 170px 1fr;
        gap: 10px;
        align-items: baseline;
        padding: 6px 0;
        border-bottom: 1px dotted #cbd5e1;
        font-size: 13.5px;
    }
    .rv-label { color: #475569; font-weight: 700; }
    .rv-value { font-weight: 650; }
    .rv-chip {
        display: inline-block;
        margin-inline-start: 6px;
        padding: 1px 7px;
        border-radius: 999px;
        background: #e2e8f0;
        font-size: 11px;
        font-weight: 700;
    }
    .rv-bottom {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 20px;
        margin-top: 16px;
    }
    .rv-amount {
        display: inline-flex;
        align-items: baseline;
        gap: 8px;
        min-width: 180px;
        padding: 8px 12px;
        border: 1.5px solid #0f172a;
        background: #f8fafc;
        font-size: 13px;
    }
    .rv-amount strong { font-size: 20px; font-weight: 800; }
    .rv-amount em { font-style: normal; font-weight: 700; color: #334155; }
    .rv-sign { min-width: 220px; text-align: center; color: #475569; font-size: 12px; }
    .rv-sign-line {
        height: 1px;
        margin-bottom: 6px;
        border-bottom: 1px solid #94a3b8;
    }
    .rv-foot {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-top: 12px;
        padding-top: 8px;
        border-top: 1px solid #e2e8f0;
        color: #475569;
        font-size: 11.5px;
    }
    .rv-cut {
        height: 18px;
        margin: 8px 0;
        border-bottom: 1px dashed #94a3b8;
    }
    @media print {
        html, body { background: #fff; }
        .rv-sheet { break-inside: avoid; }
    }
</style>
