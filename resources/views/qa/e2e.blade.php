<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>QA — اختبارات e2e</title>
    <style>
        :root {
            --bg: #0b1220;
            --panel: #111827;
            --panel-2: #1a2332;
            --border: #243044;
            --text: #e5edf7;
            --muted: #8fa3bf;
            --accent: #38bdf8;
            --ok: #34d399;
            --warn: #fbbf24;
            --danger: #f87171;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            background: radial-gradient(circle at top left, #0f2744 0%, var(--bg) 50%);
            color: var(--text);
            line-height: 1.55;
            min-height: 100vh;
        }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 24px 18px 48px; }
        .hero { margin-bottom: 18px; }
        .hero h1 { margin: 0 0 6px; font-size: 1.65rem; }
        .hero p { margin: 0; color: var(--muted); font-size: 0.95rem; }
        .toolbar {
            display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
            background: var(--panel); border: 1px solid var(--border);
            border-radius: 14px; padding: 12px 14px; margin-bottom: 12px;
        }
        .toolbar .group-label {
            width: 100%; color: var(--muted); font-size: 12px; margin: 2px 0 0;
        }
        button {
            background: var(--panel-2); color: var(--text); border: 1px solid var(--border);
            padding: 10px 14px; border-radius: 10px; font-size: 14px; cursor: pointer;
        }
        button.primary { background: #1d4ed8; border-color: #2563eb; }
        button.primary:hover { background: #2563eb; }
        button.accent { background: #0f766e; border-color: #14b8a6; }
        button.accent:hover { background: #0d9488; }
        button:disabled { opacity: 0.55; cursor: not-allowed; }
        .cards {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px; margin-bottom: 16px;
        }
        .card {
            background: linear-gradient(180deg, var(--panel-2), var(--panel));
            border: 1px solid var(--border); border-radius: 14px; padding: 14px 16px;
        }
        .card .label { color: var(--muted); font-size: 12px; margin-bottom: 6px; }
        .card .value { font-size: 1.4rem; font-weight: 700; }
        .card.ok .value { color: var(--ok); }
        .card.danger .value { color: var(--danger); }
        .card.info .value { color: var(--accent); }
        .card.warn .value { color: var(--warn); }
        .panel {
            background: var(--panel); border: 1px solid var(--border);
            border-radius: 14px; padding: 14px 16px; margin-bottom: 14px;
        }
        .panel h2 { margin: 0 0 10px; font-size: 1rem; }
        .status { min-height: 22px; font-size: 13px; color: var(--muted); margin-bottom: 10px; white-space: pre-wrap; }
        .status.error { color: var(--danger); }
        .status.ok { color: var(--ok); }
        details summary {
            cursor: pointer; color: var(--accent); font-size: 14px; user-select: none;
        }
        pre.log {
            margin: 12px 0 0; padding: 12px; border-radius: 10px;
            background: #0a0f18; border: 1px solid var(--border);
            color: #cbd5e1; font-size: 11px; line-height: 1.45;
            max-height: 420px; overflow: auto;
            direction: ltr; text-align: left; white-space: pre-wrap; word-break: break-word;
            font-family: Consolas, "Courier New", monospace;
        }
        .meta { color: var(--muted); font-size: 12px; }
        a.back { color: var(--accent); text-decoration: none; font-size: 13px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="hero">
        <a class="back" href="{{ url('/dashboard') }}">← العودة للوحة التحكم</a>
        <h1>مراقبة اختبارات e2e</h1>
        <p>تشغيل Playwright على دفعات قصيرة (أساسي / محاسبة صفحات / إدارة) أو شامل بالتتابع — لتجنب انتهاء مهلة الشبكة.</p>
    </div>

    <div class="toolbar">
        <span class="group-label">محاسبة (سلامة القيود)</span>
        <button type="button" class="primary" id="btn-accounting" data-suite="accounting">تشغيل اختبارات المحاسبة</button>

        <span class="group-label">فحص النظام (مجزّأ)</span>
        <button type="button" id="btn-system-core" data-suite="system-core">فحص أساسي</button>
        <button type="button" id="btn-system-accounting" data-suite="system-accounting">فحص المحاسبة</button>
        <button type="button" id="btn-system-admin" data-suite="system-admin">فحص الإدارة</button>
        <button type="button" class="accent" id="btn-system-full">فحص شامل (تتابعي)</button>

        <span class="group-label">أخرى</span>
        <button type="button" id="btn-all">تشغيل كل اختبارات e2e</button>
        <button type="button" id="btn-refresh">تحديث النتيجة</button>
        <span class="meta" id="running-hint"></span>
    </div>

    <div class="status" id="status-msg">جاهز</div>

    <div class="cards">
        <div class="card" id="card-status">
            <div class="label">الحالة</div>
            <div class="value" id="val-status">—</div>
        </div>
        <div class="card ok">
            <div class="label">نجحت</div>
            <div class="value" id="val-passed">0</div>
        </div>
        <div class="card danger">
            <div class="label">فشلت</div>
            <div class="value" id="val-failed">0</div>
        </div>
        <div class="card warn">
            <div class="label">تخطي</div>
            <div class="value" id="val-skipped">0</div>
        </div>
        <div class="card info">
            <div class="label">المدة (ث)</div>
            <div class="value" id="val-duration">—</div>
        </div>
        <div class="card info">
            <div class="label">آخر تشغيل</div>
            <div class="value" id="val-time" style="font-size:0.95rem">—</div>
        </div>
    </div>

    <div class="panel">
        <h2>التفاصيل</h2>
        <p class="meta" id="val-suite">Suite: —</p>
        <p class="meta" id="val-command" style="direction:ltr;text-align:left">—</p>
        <details id="log-details" open>
            <summary>سجل الإخراج (stdout)</summary>
            <pre class="log" id="val-log">لا توجد نتائج بعد. اختر فحصاً من الأعلى.</pre>
        </details>
    </div>
</div>

<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const statusMsg = document.getElementById('status-msg');
    const runningHint = document.getElementById('running-hint');
    const runButtons = Array.from(document.querySelectorAll('[data-suite], #btn-system-full, #btn-all, #btn-refresh'));

    const SYSTEM_CHUNKS = @json($systemChunks ?? ['system-core', 'system-accounting', 'system-admin']);
    const ALL_CHUNKS = ['accounting', ...SYSTEM_CHUNKS];

    const suiteLabels = {
        accounting: 'تشغيل اختبارات المحاسبة…',
        'system-core': 'فحص أساسي…',
        'system-accounting': 'فحص صفحات المحاسبة…',
        'system-admin': 'فحص الإدارة…',
        system: 'فحص شامل (تتابعي)…',
        health: 'فحص شامل (تتابعي)…',
        all: 'تشغيل كل الاختبارات (تتابعي)…',
    };

    function setBusy(busy, label) {
        runButtons.forEach(b => b.disabled = busy);
        runningHint.textContent = busy ? (label || 'جاري التشغيل… قد يستغرق دقائق') : '';
    }

    function paint(result) {
        if (!result) {
            document.getElementById('val-status').textContent = 'لا يوجد';
            return;
        }
        const ok = !!result.ok;
        document.getElementById('card-status').className = 'card ' + (ok ? 'ok' : 'danger');
        document.getElementById('val-status').textContent = ok ? 'نجاح' : 'فشل';
        document.getElementById('val-passed').textContent = result.passed ?? 0;
        document.getElementById('val-failed').textContent = result.failed ?? 0;
        document.getElementById('val-skipped').textContent = result.skipped ?? 0;
        document.getElementById('val-duration').textContent =
            result.duration_ms != null ? (result.duration_ms / 1000).toFixed(1) : '—';
        document.getElementById('val-time').textContent = result.finished_at || result.started_at || '—';
        document.getElementById('val-suite').textContent = 'Suite: ' + (result.suite || '—');
        document.getElementById('val-command').textContent = result.command || '—';
        document.getElementById('val-log').textContent = result.stdout || '(فارغ)';
    }

    function snippet(text, max) {
        const t = (text || '').replace(/\s+/g, ' ').trim();
        if (!t) return '(فارغ)';
        return t.length > max ? t.slice(0, max) + '…' : t;
    }

    /**
     * Parse JSON safely — never call response.json() on HTML error pages.
     */
    async function parseJsonResponse(res) {
        const text = await res.text();
        const trimmed = (text || '').trim();
        if (!trimmed) {
            return { ok: false, data: null, raw: '', error: 'استجابة فارغة من الخادم' };
        }
        try {
            return { ok: true, data: JSON.parse(trimmed), raw: trimmed, error: null };
        } catch (e) {
            return {
                ok: false,
                data: null,
                raw: trimmed,
                error: 'الخادم لم يُرجع JSON (ربما مهلة أو صفحة خطأ HTML). الرمز: '
                    + res.status + ' — مقتطف: ' + snippet(trimmed, 180),
            };
        }
    }

    async function refresh() {
        statusMsg.className = 'status';
        statusMsg.textContent = 'جاري التحديث…';
        try {
            const res = await fetch('{{ route('qa.e2e.last') }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const parsed = await parseJsonResponse(res);
            if (!parsed.ok) {
                statusMsg.className = 'status error';
                statusMsg.textContent = parsed.error;
                return;
            }
            paint(parsed.data.result);
            statusMsg.textContent = parsed.data.result ? 'تم تحميل آخر نتيجة' : 'لا توجد نتيجة محفوظة';
        } catch (e) {
            statusMsg.className = 'status error';
            statusMsg.textContent = 'خطأ شبكة: ' + (e && e.message ? e.message : e);
        }
    }

    async function postSuite(suite, extra) {
        const payload = Object.assign({ suite }, extra || {});
        const res = await fetch('{{ route('qa.e2e.run') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(payload),
        });
        const parsed = await parseJsonResponse(res);
        if (!parsed.ok) {
            const err = new Error(parsed.error);
            err.nonJson = true;
            err.status = res.status;
            err.raw = parsed.raw;
            throw err;
        }
        return { res, data: parsed.data };
    }

    function mergeResults(label, parts) {
        // Kept for rare fallback when a chunk response has no cumulative parent yet.
        const merged = {
            suite: label,
            chunks: [],
            command: '',
            started_at: parts[0]?.started_at || null,
            finished_at: parts[parts.length - 1]?.finished_at || null,
            duration_ms: 0,
            exit_code: 0,
            ok: true,
            passed: 0,
            failed: 0,
            skipped: 0,
            flaky: 0,
            total: 0,
            stdout: '',
        };
        const commands = [];
        const logs = [];
        for (const p of parts) {
            if (!p) continue;
            merged.duration_ms += Number(p.duration_ms || 0);
            merged.passed += Number(p.passed || 0);
            merged.failed += Number(p.failed || 0);
            merged.skipped += Number(p.skipped || 0);
            merged.flaky += Number(p.flaky || 0);
            merged.total += Number(p.total || 0);
            if (Number(p.exit_code || 0) !== 0) {
                merged.exit_code = Number(p.exit_code || 1);
                merged.ok = false;
            }
            commands.push(p.command || '');
            logs.push('===== CHUNK: ' + (p.suite || '?') + ' =====\n' + (p.stdout || ''));
            merged.chunks.push({
                suite: p.suite,
                ok: !!p.ok,
                exit_code: p.exit_code,
                passed: p.passed,
                failed: p.failed,
                skipped: p.skipped,
                duration_ms: p.duration_ms,
            });
        }
        merged.command = commands.filter(Boolean).join(' && ');
        merged.stdout = logs.join('\n\n');
        return merged;
    }

    async function runSuite(suite) {
        setBusy(true, suiteLabels[suite] || 'جاري التشغيل…');
        statusMsg.className = 'status';
        statusMsg.textContent = suiteLabels[suite] || 'جاري تشغيل Playwright…';
        try {
            const { data } = await postSuite(suite);
            paint(data.result || null);
            statusMsg.className = 'status ' + ((data.result && data.result.ok) ? 'ok' : 'error');
            statusMsg.textContent = data.message || 'انتهى التشغيل';
        } catch (e) {
            statusMsg.className = 'status error';
            if (e && e.nonJson) {
                statusMsg.textContent = e.message;
                document.getElementById('val-log').textContent = snippet(e.raw || '', 4000);
            } else {
                statusMsg.textContent = 'خطأ شبكة: ' + (e && e.message ? e.message : e);
            }
        } finally {
            setBusy(false);
        }
    }

    /**
     * «فحص شامل» / «الكل»: طلبات HTTP منفصلة لكل جزء ثم دمج النتائج في الواجهة.
     * أقصر من عملية واحدة طويلة — يقلل احتمال إرجاع HTML بسبب مهلة nginx/PHP.
     */
    async function runSequential(chunks, label) {
        setBusy(true, suiteLabels[label] || 'تشغيل تتابعي…');
        statusMsg.className = 'status';
        const parts = [];
        try {
            for (let i = 0; i < chunks.length; i++) {
                const suite = chunks[i];
                statusMsg.textContent = `جزء ${i + 1}/${chunks.length}: ${suiteLabels[suite] || suite}`;
                runningHint.textContent = statusMsg.textContent;
                const { data } = await postSuite(suite, {
                    merge_as: label,
                    chunk_index: i,
                    chunk_total: chunks.length,
                });
                if (data.result) {
                    // Server returns cumulative parent result when merge_as is set.
                    parts.push(data.result);
                    paint(data.result);
                }
            }
            const merged = parts.length ? parts[parts.length - 1] : mergeResults(label, parts);
            paint(merged);
            statusMsg.className = 'status ' + (merged.ok ? 'ok' : 'error');
            statusMsg.textContent = merged.ok
                ? 'انتهى الفحص الشامل بنجاح'
                : 'انتهى الفحص الشامل مع إخفاقات في بعض الأجزاء';
        } catch (e) {
            if (parts.length) {
                paint(parts[parts.length - 1]);
            }
            statusMsg.className = 'status error';
            if (e && e.nonJson) {
                statusMsg.textContent = e.message;
                const prevLog = parts.length ? (parts[parts.length - 1].stdout || '') : '';
                document.getElementById('val-log').textContent =
                    (prevLog + '\n\n' + snippet(e.raw || '', 2000)).trim();
            } else {
                statusMsg.textContent = 'خطأ شبكة: ' + (e && e.message ? e.message : e);
            }
        } finally {
            setBusy(false);
        }
    }

    document.querySelectorAll('[data-suite]').forEach(btn => {
        btn.addEventListener('click', () => runSuite(btn.getAttribute('data-suite')));
    });
    document.getElementById('btn-system-full').addEventListener('click', () => {
        runSequential(SYSTEM_CHUNKS, 'system');
    });
    document.getElementById('btn-all').addEventListener('click', () => {
        runSequential(ALL_CHUNKS, 'all');
    });
    document.getElementById('btn-refresh').addEventListener('click', () => refresh());

    @if (!empty($last))
    paint(@json($last));
    @endif
})();
</script>
</body>
</html>
