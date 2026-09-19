<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>قطع MySQL → SQLite + ميزان المراجعة</title>
    <style>
        :root {
            --bg: #0b1220;
            --panel: #111827;
            --panel-2: #1a2332;
            --border: #334155;
            --text: #f1f5f9;
            --muted: #94a3b8;
            --accent: #38bdf8;
            --ok: #34d399;
            --warn: #fbbf24;
            --danger: #f87171;
            --emerald: #059669;
            --rose: #e11d48;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            background: radial-gradient(circle at top left, #0f2744 0%, var(--bg) 55%);
            color: var(--text);
            line-height: 1.55;
            min-height: 100vh;
        }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 24px 18px 48px; }
        .hero { margin-bottom: 16px; }
        .hero h1 { margin: 8px 0 6px; font-size: 1.55rem; }
        .hero p { margin: 0; color: var(--muted); font-size: 0.95rem; }
        a.back { color: var(--accent); text-decoration: none; font-size: 13px; }
        .toolbar, .panel {
            background: var(--panel); border: 1px solid var(--border);
            border-radius: 14px; padding: 14px 16px; margin-bottom: 14px;
        }
        .toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .group-label { width: 100%; color: var(--muted); font-size: 12px; margin: 2px 0 0; }
        label.field { display: flex; flex-direction: column; gap: 4px; flex: 1 1 280px; font-size: 12px; color: var(--muted); }
        input[type=text], input[type=number] {
            background: #0b1220; border: 1px solid var(--border); color: var(--text);
            border-radius: 10px; padding: 10px 12px; font-size: 13px;
        }
        .checks { display: flex; gap: 14px; flex-wrap: wrap; color: var(--text); font-size: 13px; }
        .checks label { display: flex; align-items: center; gap: 6px; color: #e2e8f0; }
        button {
            background: var(--panel-2); color: var(--text); border: 1px solid var(--border);
            padding: 10px 14px; border-radius: 10px; font-size: 14px; cursor: pointer;
        }
        button.primary { background: #1d4ed8; border-color: #2563eb; }
        button.primary:hover { background: #2563eb; }
        button.accent { background: var(--emerald); border-color: #10b981; }
        button.accent:hover { background: #10b981; }
        button.danger { background: var(--rose); border-color: #fb7185; }
        button:disabled { opacity: 0.55; cursor: not-allowed; }
        .cards {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px; margin-bottom: 14px;
        }
        .card {
            background: linear-gradient(180deg, var(--panel-2), var(--panel));
            border: 1px solid var(--border); border-radius: 14px; padding: 14px 16px;
        }
        .card .label { color: var(--muted); font-size: 12px; margin-bottom: 6px; }
        .card .value { font-size: 1.25rem; font-weight: 700; word-break: break-word; }
        .card.ok .value { color: var(--ok); }
        .card.danger .value { color: var(--danger); }
        .card.warn .value { color: var(--warn); }
        .card.info .value { color: var(--accent); }
        .panel h2 { margin: 0 0 10px; font-size: 1rem; }
        .status { min-height: 22px; font-size: 13px; color: var(--muted); margin-bottom: 10px; white-space: pre-wrap; }
        .status.error { color: var(--danger); }
        .status.ok { color: var(--ok); }
        table.metrics {
            width: 100%; border-collapse: collapse; font-size: 13px;
        }
        table.metrics th, table.metrics td {
            border-bottom: 1px solid var(--border); padding: 8px 6px; text-align: right;
        }
        table.metrics th { color: var(--muted); font-weight: 600; }
        pre.log {
            margin: 12px 0 0; padding: 12px; border-radius: 10px;
            background: #0a0f18; border: 1px solid var(--border);
            color: #cbd5e1; font-size: 11px; line-height: 1.45;
            max-height: 360px; overflow: auto;
            direction: ltr; text-align: left; white-space: pre-wrap; word-break: break-word;
            font-family: Consolas, "Courier New", monospace;
        }
        .path { font-size: 12px; color: var(--muted); direction: ltr; text-align: left; word-break: break-all; }
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px;
            border: 1px solid var(--border);
        }
        .badge.ok { color: var(--ok); border-color: #065f46; background: #064e3b55; }
        .badge.bad { color: var(--danger); border-color: #7f1d1d; background: #7f1d1d55; }
        details summary { cursor: pointer; color: var(--accent); font-size: 14px; }
    </style>
</head>
<body>
@php
    $s = $status ?? [];
    $mazad = $s['mazad_sqlite'] ?? [];
    $schema = $s['schema'] ?? [];
    $counts = $s['current_counts'] ?? [];
@endphp
<div class="wrap">
    <div class="hero">
        <a class="back" href="{{ url('/dashboard') }}">← العودة للوحة التحكم</a>
        <h1>قطع MySQL → SQLite + ميزان المراجعة</h1>
        <p>تشغيل الاستيراد والقطع المحاسبي من الواجهة مع قياس النتائج (أدمن فقط).</p>
    </div>

    <div class="cards" id="kpi">
        <div class="card info">
            <div class="label">قاعدة نشطة</div>
            <div class="value" id="kpi-active" style="font-size:0.85rem">{{ basename($s['active_database'] ?? '—') }}</div>
        </div>
        <div class="card {{ !empty($s['using_mazad']) ? 'ok' : 'warn' }}">
            <div class="label">على mazad؟</div>
            <div class="value" id="kpi-mazad">{{ !empty($s['using_mazad']) ? 'نعم' : 'لا' }}</div>
        </div>
        <div class="card info">
            <div class="label">مستخدمون / سيارات</div>
            <div class="value" id="kpi-users-cars">{{ ($counts['users'] ?? '—') }} / {{ ($counts['car'] ?? '—') }}</div>
        </div>
        <div class="card info">
            <div class="label">قيود / حسابات</div>
            <div class="value" id="kpi-journals">{{ ($counts['journal_entries'] ?? '—') }} / {{ ($counts['ledger_accounts'] ?? '—') }}</div>
        </div>
        <div class="card {{ !empty($schema['journal_entries']) ? 'ok' : 'danger' }}">
            <div class="label">مخطط القيد</div>
            <div class="value" id="kpi-schema">{{ !empty($schema['journal_entries']) ? 'جاهز' : 'ناقص' }}</div>
        </div>
    </div>

    <div class="panel">
        <h2>مسار الملفات</h2>
        <p class="path">Dump الافتراضي: {{ $s['default_dump_path'] ?? '' }}
            <span class="badge {{ !empty($s['dump_exists']) ? 'ok' : 'bad' }}">{{ !empty($s['dump_exists']) ? 'موجود' : 'غير موجود' }}</span>
        </p>
        <p class="path">Mazad SQLite: {{ $mazad['path'] ?? '' }}
            <span class="badge {{ !empty($mazad['exists']) ? 'ok' : 'bad' }}">{{ !empty($mazad['exists']) ? 'موجود' : 'غير موجود' }}</span>
            @if(!empty($mazad['size'])) — {{ number_format($mazad['size']/1024, 0) }} KB @endif
        </p>
        <p class="path">اتصال التطبيق الحالي: {{ $s['active_database'] ?? '' }}</p>
    </div>

    <div class="toolbar">
        <span class="group-label">1) استيراد الدمب + ترقية المخطط</span>
        <label class="field">مسار ملف .sql
            <input type="text" id="dump_path" value="{{ $s['default_dump_path'] ?? '' }}">
        </label>
        <label class="field">مسار SQLite الهدف
            <input type="text" id="sqlite_path" value="{{ $mazad['path'] ?? '' }}">
        </label>
        <div class="checks">
            <label><input type="checkbox" id="opt_force" checked> overwrite</label>
            <label><input type="checkbox" id="opt_migrate" checked> migrate بعد الاستيراد</label>
            <label><input type="checkbox" id="opt_bind" checked> اربط الجلسة بـ mazad بعد النجاح</label>
        </div>
        <button type="button" class="primary" id="btn-import">تشغيل الاستيراد</button>
        <button type="button" id="btn-refresh">تحديث الحالة</button>
    </div>

    <div class="toolbar">
        <span class="group-label">2) قطع محاسبي (أرصدة افتتاحية) + سلامة القيود</span>
        <label class="field" style="flex:0 1 140px">owner (اختياري)
            <input type="number" id="owner_id" min="1" placeholder="الكل">
        </label>
        <label class="checks"><input type="checkbox" id="use_mazad" checked> استخدم database_mazad.sqlite</label>
        <button type="button" class="primary" id="btn-dry">معاينة dry-run</button>
        <button type="button" class="accent" id="btn-execute">تنفيذ القطع</button>
        <button type="button" id="btn-integrity">فحص integrity + ميزان</button>
    </div>

    <div class="panel">
        <h2>الحالة / النتائج</h2>
        <div class="status" id="status">جاهز.</div>
        <div class="cards" id="result-kpis" style="display:none"></div>
        <div id="result-tables"></div>
        <details open>
            <summary>سجل JSON / مخرجات</summary>
            <pre class="log" id="log">{{ isset($last) ? json_encode($last, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) : '—' }}</pre>
        </details>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const statusEl = document.getElementById('status');
    const logEl = document.getElementById('log');
    const resultKpis = document.getElementById('result-kpis');
    const resultTables = document.getElementById('result-tables');

    function setBusy(busy, msg) {
        document.querySelectorAll('button').forEach(b => b.disabled = busy);
        statusEl.className = 'status';
        statusEl.textContent = msg || (busy ? 'جاري التنفيذ… قد يستغرق دقيقة.' : 'جاهز.');
    }

    function showError(msg) {
        statusEl.className = 'status error';
        statusEl.textContent = msg;
    }

    function showOk(msg) {
        statusEl.className = 'status ok';
        statusEl.textContent = msg;
    }

    function applyStatus(s) {
        if (!s) return;
        document.getElementById('kpi-active').textContent = (s.active_database || '').split(/[\\\\/]/).pop() || '—';
        document.getElementById('kpi-mazad').textContent = s.using_mazad ? 'نعم' : 'لا';
        const c = s.current_counts || {};
        document.getElementById('kpi-users-cars').textContent = `${c.users ?? '—'} / ${c.car ?? '—'}`;
        document.getElementById('kpi-journals').textContent = `${c.journal_entries ?? '—'} / ${c.ledger_accounts ?? '—'}`;
        document.getElementById('kpi-schema').textContent = s.schema?.journal_entries ? 'جاهز' : 'ناقص';
    }

    function renderKpis(map) {
        resultKpis.style.display = 'grid';
        resultKpis.innerHTML = Object.entries(map).map(([label, val]) => {
            const cls = typeof val === 'string' && /fail|فشل|NO/i.test(String(val)) ? 'danger'
                : typeof val === 'string' && /pass|نجح|yes|OK/i.test(String(val)) ? 'ok' : 'info';
            return `<div class="card ${cls}"><div class="label">${label}</div><div class="value">${val}</div></div>`;
        }).join('');
    }

    function renderTable(title, headers, rows) {
        if (!rows || !rows.length) return '';
        const head = headers.map(h => `<th>${h}</th>`).join('');
        const body = rows.map(r => `<tr>${r.map(c => `<td>${c ?? '—'}</td>`).join('')}</tr>`).join('');
        return `<div class="panel"><h2>${title}</h2><table class="metrics"><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></div>`;
    }

    async function post(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body || {}),
        });
        const data = await res.json().catch(() => ({ ok: false, message: 'استجابة غير صالحة' }));
        return { res, data };
    }

    async function get(url) {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        return res.json();
    }

    document.getElementById('btn-refresh').onclick = async () => {
        setBusy(true, 'تحديث الحالة…');
        try {
            const data = await get(@json(route('ops.legacy-cutover.status')));
            applyStatus(data.status);
            logEl.textContent = JSON.stringify(data, null, 2);
            showOk('تم تحديث الحالة.');
        } catch (e) {
            showError(e.message);
        } finally {
            setBusy(false);
        }
    };

    document.getElementById('btn-import').onclick = async () => {
        setBusy(true, 'استيراد الدمب وترقية المخطط…');
        resultTables.innerHTML = '';
        const { res, data } = await post(@json(route('ops.legacy-cutover.import')), {
            dump_path: document.getElementById('dump_path').value.trim(),
            sqlite_path: document.getElementById('sqlite_path').value.trim(),
            force: document.getElementById('opt_force').checked,
            migrate: document.getElementById('opt_migrate').checked,
            bind_after: document.getElementById('opt_bind').checked,
        });
        logEl.textContent = JSON.stringify(data, null, 2);
        const r = data.result || {};
        const counts = r.counts || {};
        renderKpis({
            'الزمن (ms)': r.elapsed_ms ?? '—',
            'جداول': (r.import?.tables || []).length,
            'Statements OK': r.import?.statements_ok ?? '—',
            'فشل': (r.import?.statements_failed || []).length,
            'users': counts.users ?? '—',
            'car': counts.car ?? '—',
            'transactions': counts.transactions ?? '—',
            'migrate': r.migrate?.ok ? 'OK' : (r.migrate ? 'FAIL' : '—'),
        });
        if (data.status_after || data.result?.status_after) applyStatus(data.result.status_after || data.status);
        if (data.ok) showOk(data.message); else showError(data.message || 'فشل الاستيراد');
        setBusy(false);
        if (data.status) applyStatus(data.status);
        if (data.result?.status_after) applyStatus(data.result.status_after);
    };

    async function runCutover(execute) {
        setBusy(true, execute ? 'تنفيذ القطع المحاسبي…' : 'معاينة dry-run…');
        resultTables.innerHTML = '';
        const ownerRaw = document.getElementById('owner_id').value.trim();
        const payload = {
            execute,
            use_mazad: document.getElementById('use_mazad').checked,
        };
        if (ownerRaw) payload.owner = parseInt(ownerRaw, 10);

        const { data } = await post(@json(route('ops.legacy-cutover.cutover')), payload);
        logEl.textContent = JSON.stringify(data, null, 2);
        const r = data.result || {};
        const integ = r.integrity || {};
        const tb = r.trial_balance || [];
        renderKpis({
            'وضع': r.dry_run ? 'dry-run' : 'execute',
            'الزمن (ms)': r.elapsed_ms ?? '—',
            'قيود': r.posted ?? '—',
            'تخطي': r.skipped ?? '—',
            'تجار': r.clients_provisioned ?? '—',
            'قاصات': r.vaults_synced ?? '—',
            'تحذيرات': (r.warnings || []).length,
            'integrity': integ.ok === undefined ? '—' : (integ.ok ? 'PASS' : 'FAIL'),
        });

        const clients = (r.comparisons || []).filter(c => c.kind === 'client')
            .filter(c => Math.abs(c.car_remaining||0) >= 0.01 || Math.abs(c.wallet_usd||0) >= 0.01)
            .slice(0, 25)
            .map(c => [c.user_id, (c.name||'').slice(0,22), c.car_remaining, c.wallet_usd, c.ledger_ar, c.ledger_qasa_usd]);
        const cash = (r.comparisons || []).filter(c => c.kind === 'cash')
            .filter(c => Math.abs(c.wallet_usd||0) >= 0.01)
            .map(c => [c.user_id, c.name, c.wallet_usd, c.wallet_iqd]);
        const tbRows = tb.map(t => [t.currency, t.debit, t.credit, (Math.round((t.debit - t.credit)*100)/100)]);

        resultTables.innerHTML =
            renderTable('ميزان المراجعة', ['عملة','مدين','دائن','فرق'], tbRows) +
            renderTable('عيّنة زبائن (غير صفري)', ['ID','اسم','متبقي سيارات','محفظة $','ذمم AR','عهدة $'], clients) +
            renderTable('صناديق', ['ID','اسم','محفظة $','IQD'], cash);

        if (data.status) applyStatus(data.status);
        if (data.ok) showOk(data.message); else showError(data.message || 'فشل');
        setBusy(false);
    }

    document.getElementById('btn-dry').onclick = () => runCutover(false);
    document.getElementById('btn-execute').onclick = () => {
        if (!confirm('تأكيد تنفيذ القيود الافتتاحية على قاعدة mazad؟')) return;
        runCutover(true);
    };

    document.getElementById('btn-integrity').onclick = async () => {
        setBusy(true, 'فحص سلامة القيود…');
        const ownerRaw = document.getElementById('owner_id').value.trim();
        const body = { use_mazad: document.getElementById('use_mazad').checked };
        if (ownerRaw) body.owner = parseInt(ownerRaw, 10);
        const { data } = await post(@json(route('ops.legacy-cutover.integrity')), body);
        logEl.textContent = JSON.stringify(data, null, 2);
        const i = data.result?.integrity || {};
        const tb = data.result?.trial_balance || [];
        renderKpis({
            'integrity': i.ok ? 'PASS' : 'FAIL',
            'قيود مفحوصة': i.entries_checked ?? '—',
            'غير متوازن': Array.isArray(i.unbalanced_entries) ? i.unbalanced_entries.length : (i.unbalanced ?? '—'),
            'فارغ': Array.isArray(i.empty_entries) ? i.empty_entries.length : (i.empty ?? '—'),
            'أيتام': i.orphan_lines ?? '—',
        });
        resultTables.innerHTML = renderTable('ميزان المراجعة', ['عملة','مدين','دائن','فرق'],
            tb.map(t => [t.currency, t.debit, t.credit, Math.round((t.debit - t.credit)*100)/100]));
        if (data.result?.status) applyStatus(data.result.status);
        if (data.ok) showOk(data.message); else showError(data.message || 'فشل الفحص');
        setBusy(false);
    };
})();
</script>
</body>
</html>
