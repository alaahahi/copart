<style>
    @page { size: A4; margin: 8mm; }
    * { box-sizing: border-box; }
    html, body {
        margin: 0;
        padding: 0;
        background: #e2e8f0;
        color: #0b1220;
        font-family: "Segoe UI", Tahoma, Arial, sans-serif;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .rv-page { width: 100%; }
    .rv-sheet {
        --rv-ink: #0b1220;
        --rv-muted: #334155;
        --rv-line: #94a3b8;
        --rv-paper: #fffdf8;
        --rv-accent: #0f766e;
        --rv-accent-soft: #ccfbf1;
        position: relative;
        overflow: hidden;
        border: 2px solid var(--rv-ink);
        background:
            linear-gradient(180deg, #fff 0%, var(--rv-paper) 100%);
        box-shadow: 0 1px 0 #fff inset;
    }
    .rv-sheet--payment {
        --rv-accent: #9a3412;
        --rv-accent-soft: #ffedd5;
    }
    .rv-sheet::before {
        content: "";
        position: absolute;
        inset: 5px;
        border: 1px solid var(--rv-accent);
        pointer-events: none;
        opacity: .55;
    }
    .rv-mark {
        position: absolute;
        top: 42%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-18deg);
        font-size: 64px;
        font-weight: 800;
        letter-spacing: .12em;
        color: var(--rv-accent);
        opacity: .06;
        white-space: nowrap;
        pointer-events: none;
    }
    .rv-bar {
        height: 8px;
        background: linear-gradient(90deg, var(--rv-ink), var(--rv-accent));
    }
    .rv-head {
        display: grid;
        grid-template-columns: 1fr auto 140px;
        align-items: center;
        gap: 10px;
        padding: 10px 16px 8px;
    }
    .rv-logo { display: flex; justify-content: flex-end; }
    .rv-logo img { max-height: 58px; max-width: 120px; object-fit: contain; }
    .rv-title {
        text-align: center;
        justify-self: center;
        min-width: 210px;
        padding: 6px 16px 7px;
        border: 1.5px solid var(--rv-ink);
        background: var(--rv-accent-soft);
        box-shadow: 3px 3px 0 var(--rv-ink);
    }
    .rv-title-ar { font-size: 22px; font-weight: 800; line-height: 1.15; }
    .rv-title-en {
        margin-top: 2px;
        color: var(--rv-muted);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .rv-brand { text-align: start; }
    .rv-brand-name {
        font-size: 16px;
        font-weight: 800;
        letter-spacing: .03em;
        line-height: 1.2;
    }
    .rv-brand-sub { margin-top: 2px; color: var(--rv-muted); font-size: 11px; font-weight: 600; }
    .rv-copy {
        display: inline-block;
        margin-top: 5px;
        padding: 1px 8px;
        border: 1px solid var(--rv-accent);
        color: var(--rv-accent);
        font-size: 10px;
        font-weight: 800;
        letter-spacing: .06em;
    }
    .rv-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin: 0 16px 10px;
    }
    .rv-meta-card {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 8px;
        padding: 6px 10px;
        border: 1px solid #cbd5e1;
        background: #fff;
        font-size: 12.5px;
    }
    .rv-meta-card span { color: var(--rv-muted); font-weight: 700; }
    .rv-meta-card strong { font-size: 14px; font-weight: 800; }
    .rv-fields {
        margin: 0 16px;
        border: 1px solid #cbd5e1;
        background: #fff;
    }
    .rv-row {
        display: grid;
        grid-template-columns: 168px 1fr;
        gap: 0;
        min-height: 34px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 13px;
    }
    .rv-row:last-child { border-bottom: 0; }
    .rv-label {
        display: flex;
        align-items: center;
        padding: 7px 10px;
        background: #f1f5f9;
        color: #1e293b;
        font-weight: 800;
        border-inline-end: 1px solid #e2e8f0;
    }
    .rv-value {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        padding: 7px 12px;
        font-weight: 700;
    }
    .rv-chip {
        display: inline-block;
        padding: 1px 8px;
        border: 1px solid var(--rv-accent);
        background: var(--rv-accent-soft);
        color: var(--rv-ink);
        font-size: 11px;
        font-weight: 800;
    }
    .rv-bottom {
        display: grid;
        grid-template-columns: 1.1fr .9fr .9fr;
        gap: 12px;
        align-items: stretch;
        margin: 12px 16px 10px;
    }
    .rv-amount {
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-height: 72px;
        padding: 8px 12px;
        background: var(--rv-ink);
        color: #fff;
        border: 2px solid var(--rv-ink);
    }
    .rv-amount span {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .08em;
        color: #cbd5e1;
    }
    .rv-amount-num {
        display: flex;
        align-items: baseline;
        gap: 8px;
        margin-top: 2px;
        font-variant-numeric: tabular-nums;
    }
    .rv-amount-num strong { font-size: 26px; font-weight: 800; line-height: 1; }
    .rv-amount-num em { font-style: normal; font-size: 16px; font-weight: 800; color: #fde68a; }
    .rv-sign {
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        min-height: 72px;
        padding: 8px 10px 6px;
        border: 1px dashed var(--rv-line);
        background: #fff;
        text-align: center;
        color: var(--rv-muted);
        font-size: 11px;
        font-weight: 700;
    }
    .rv-sign-line {
        height: 28px;
        margin-bottom: 6px;
        border-bottom: 1px solid #64748b;
    }
    .rv-foot {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin: 0 16px;
        padding: 7px 2px 10px;
        color: var(--rv-muted);
        font-size: 11px;
        font-weight: 600;
    }
    .rv-cut {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 7px 0;
        color: #64748b;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .14em;
    }
    .rv-cut::before,
    .rv-cut::after {
        content: "";
        flex: 1;
        border-bottom: 1.5px dashed #64748b;
    }
    @media print {
        html, body { background: #fff; }
        .rv-sheet { break-inside: avoid; box-shadow: none; }
    }
</style>
