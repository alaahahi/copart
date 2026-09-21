@php
    /** @var \App\Models\Car $car */
    /** @var \App\Models\User $client */
    /** @var \App\Models\SystemConfig|null $config */

    $configService = app(\App\Services\SystemConfigService::class);
    $fresh = $configService->current();
    $brandingSvc = app(\App\Services\SystemBrandingService::class);

    if (! $config instanceof \App\Models\SystemConfig) {
        $config = $fresh;
    }

    // Always prefer live row for branding files / contact (avoids stale print payloads).
    $cfg = array_merge($fresh->toArray(), $config->toArray());

    // Brand from .env (APP_PRODUCT_NAME → APP_NAME), never the seeded "Laravel" title.
    $brandName = \App\Support\Branding::name();
    $tagline = \App\Support\Branding::tagline();
    $phone = trim((string) ($cfg['receipt_phone'] ?? $fresh->receipt_phone ?? ''));
    $address = trim((string) ($cfg['receipt_address'] ?? $fresh->receipt_address ?? ''));
    $website = trim((string) ($cfg['receipt_website'] ?? $fresh->receipt_website ?? ''));

    // Logo: settings app_logo → branding resolve → receipt logos → static fallbacks.
    $storedLogo = $cfg['app_logo'] ?? $fresh->app_logo ?? null;
    $resolvedLogo = $brandingSvc->resolve($storedLogo);
    if (! $resolvedLogo) {
        $resolvedLogo = $brandingSvc->resolve($cfg['receipt_logo_haulf'] ?? $fresh->receipt_logo_haulf ?? null)
            ?: $brandingSvc->resolve($cfg['receipt_logo_main'] ?? $fresh->receipt_logo_main ?? null)
            ?: \App\Helpers\Help::normalizePublicPath($cfg['receipt_logo_main'] ?? null)
            ?: \App\Helpers\Help::normalizePublicPath($cfg['receipt_logo_haulf'] ?? null);
    }

    $logoUrl = \App\Helpers\Help::publicAssetUrl($resolvedLogo)
        ?? \App\Helpers\Help::publicAssetUrl('/img/logo-color.png')
        ?? \App\Helpers\Help::publicAssetUrl('/img/logo.jpg');

    $carType = trim((string) ($car->car_type ?? ''));
    $typeParts = preg_split('/\s+/', $carType, 2) ?: [];
    $make = $typeParts[0] ?? ($carType !== '' ? $carType : '—');
    $model = isset($typeParts[1]) && $typeParts[1] !== '' ? $typeParts[1] : '—';

    $invoiceDate = $car->date
        ? \Carbon\Carbon::parse($car->date)->format('M j, Y')
        : '—';
    $printDate = now()->format('M j, Y');
    $updatedAt = $car->updated_at
        ? \Carbon\Carbon::parse($car->updated_at)->format('M j, Y g:i A')
        : '—';

    $total = (float) ($car->total_s ?? 0);
    $paid = (float) ($car->paid ?? 0);
    $discount = (float) ($car->discount ?? 0);
    $damage = (float) ($car->damage_compensation ?? 0);
    $balanceDue = round($total - $paid - $discount - $damage, 2);

    if ($balanceDue <= 0.009 && $total > 0) {
        $status = 'PAID';
        $statusClass = 'is-paid';
    } elseif ($paid > 0.009) {
        $status = 'PARTIAL';
        $statusClass = 'is-partial';
    } else {
        $status = 'UNPAID';
        $statusClass = 'is-unpaid';
    }

    $erbilSub = (float) \App\Models\Car::erbilTransferSubtotal($car->getAttributes(), true);
    $money = fn ($n) => '$' . \App\Helpers\Help::formatMoney($n, '$');

    $lineItems = [];
    $push = function (string $label, float $amount, bool $credit = false) use (&$lineItems) {
        if (abs($amount) < 0.005) {
            return;
        }
        $lineItems[] = [
            'description' => $label,
            'amount' => $credit ? -abs($amount) : abs($amount),
            'credit' => $credit,
        ];
    };

    $push('Purchase Amount (USA)', (float) ($car->shipping_dolar_s ?? 0));
    $push('Towing', (float) ($car->dinar_s ?? 0));
    $push('Shipping', (float) ($car->coc_dolar_s ?? 0));
    $push('Repair Expenses', (float) ($car->checkout_s ?? 0));
    $push('Erbil Transfer', $erbilSub);
    $push('Erbil Expenses / Commission', (float) ($car->commission_s ?? 0));
    $push('Discount', $discount, true);
    $push('Damage Compensation', $damage, true);

    if ($lineItems === [] && abs($total) > 0.005) {
        $push('Vehicle Total', $total);
    }

    $auctionName = $car->auction->name ?? '—';
    $routeName = $car->shippingRoute->name ?? '—';
    $clientPhone = trim((string) ($client->phone ?? ''));
    $clientName = trim((string) ($client->name ?? ''));
    $note = trim((string) ($car->note ?? ''));
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice #{{ $car->id }} — {{ $car->vin ?: $brandName }}</title>
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --soft: #f8fafc;
            --green: #166534;
            --green-soft: #dcfce7;
            --amber: #92400e;
            --amber-soft: #fef3c7;
            --rose: #9f1239;
            --rose-soft: #ffe4e6;
        }
        @page { size: A4; margin: 12mm; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #dbe3ee;
            color: var(--ink);
            font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
            font-size: 13px;
            line-height: 1.45;
            overflow-x: hidden;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        html {
            scrollbar-width: thin;
            scrollbar-color: #64748b #cbd5e1;
        }
        html::-webkit-scrollbar,
        body::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        html::-webkit-scrollbar-track,
        body::-webkit-scrollbar-track {
            background: #cbd5e1;
        }
        html::-webkit-scrollbar-thumb,
        body::-webkit-scrollbar-thumb {
            background: #64748b;
            border-radius: 999px;
            border: 2px solid #cbd5e1;
        }
        html::-webkit-scrollbar-thumb:hover,
        body::-webkit-scrollbar-thumb:hover {
            background: #475569;
        }
        .toolbar {
            position: sticky; top: 0; z-index: 40;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 10px 18px; background: #0b1220; color: #f8fafc;
        }
        .toolbar p { margin: 0; font-size: 12px; opacity: .85; }
        .toolbar button {
            border: 0; border-radius: 8px; padding: 8px 14px; font-weight: 700; cursor: pointer;
        }
        .toolbar .print { background: #059669; color: #fff; }
        .toolbar .back { background: #334155; color: #fff; margin-inline-start: 8px; }

        .sheet {
            width: min(210mm, calc(100% - 36px));
            max-width: 210mm;
            min-height: 297mm;
            margin: 18px auto;
            padding: 34px 36px 40px;
            background: #fff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .12);
        }

        .top {
            display: grid;
            grid-template-columns: 1.2fr .8fr;
            gap: 24px;
            align-items: start;
            padding-bottom: 22px;
            border-bottom: 2px solid var(--ink);
        }
        .brand {
            display: flex; align-items: center; gap: 16px; min-width: 0;
        }
        .brand img {
            width: 78px; height: 78px; object-fit: contain;
            border-radius: 12px; background: var(--soft); border: 1px solid var(--line);
        }
        .brand-fallback {
            width: 78px; height: 78px; border-radius: 12px;
            display: grid; place-items: center;
            background: linear-gradient(145deg, #0f172a, #1e3a5f);
            color: #fff; font-weight: 800; font-size: 22px; letter-spacing: .04em;
        }
        .brand h1 {
            margin: 0; font-size: 26px; line-height: 1.1; letter-spacing: .01em;
        }
        .brand .tag { margin-top: 4px; color: var(--muted); font-size: 12.5px; }

        .meta { text-align: right; }
        .meta .label {
            margin: 0 0 8px; font-size: 36px; font-weight: 800; letter-spacing: .08em; line-height: 1;
        }
        .badge {
            display: inline-block; margin-bottom: 12px; padding: 4px 12px;
            border-radius: 999px; font-size: 11px; font-weight: 800; letter-spacing: .08em;
            background: #e2e8f0; color: #334155;
        }
        .badge.is-paid { background: var(--green-soft); color: var(--green); }
        .badge.is-partial { background: var(--amber-soft); color: var(--amber); }
        .badge.is-unpaid { background: var(--rose-soft); color: var(--rose); }
        .meta ul { list-style: none; margin: 0; padding: 0; color: var(--muted); font-size: 12.5px; }
        .meta li { margin: 4px 0; }
        .meta strong { color: var(--ink); }

        .section { margin-top: 28px; }
        .section-title {
            margin: 0 0 12px; padding-bottom: 8px;
            border-bottom: 1px solid var(--line);
            color: var(--green); font-size: 12px; font-weight: 800;
            letter-spacing: .1em; text-transform: uppercase;
        }

        .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; }
        .party-name { margin: 0 0 6px; font-size: 16px; font-weight: 750; }
        .party-line { margin: 0; color: var(--muted); font-size: 12.5px; line-height: 1.55; }

        .grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px 20px; }
        .field-label {
            display: block; margin-bottom: 3px;
            color: #94a3b8; font-size: 11px; font-weight: 750;
            letter-spacing: .06em; text-transform: uppercase;
        }
        .field-value { font-size: 14px; font-weight: 650; word-break: break-word; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; }

        table.items { width: 100%; border-collapse: collapse; }
        table.items th {
            padding: 10px 0; border-bottom: 1px solid var(--line);
            text-align: left; color: #94a3b8; font-size: 11px;
            letter-spacing: .08em; text-transform: uppercase;
        }
        table.items th:last-child,
        table.items td:last-child { text-align: right; }
        table.items td {
            padding: 12px 0; border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        table.items td.credit { color: var(--amber); }

        .totals-wrap { display: flex; justify-content: flex-end; margin-top: 18px; }
        .totals {
            width: 300px; padding: 16px 18px; border-radius: 12px;
            background: var(--soft); border: 1px solid var(--line);
        }
        .totals-row {
            display: flex; justify-content: space-between; gap: 16px;
            margin: 7px 0; color: var(--muted); font-size: 13px;
        }
        .totals-row.due {
            margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line);
            color: var(--ink); font-size: 17px; font-weight: 800;
        }
        .totals-row.due span:last-child { color: var(--green); }

        .note {
            margin-top: 22px; padding: 12px 14px; border-radius: 10px;
            background: var(--soft); border: 1px solid var(--line);
            color: var(--muted); font-size: 12.5px;
        }
        .note strong { color: var(--ink); }

        .footer {
            margin-top: 40px; padding-top: 14px; border-top: 1px solid var(--line);
            text-align: center; color: #94a3b8; font-size: 11px;
        }

        @media print {
            html, body {
                background: #fff !important;
                overflow: visible !important;
            }
            .toolbar { display: none !important; }
            .sheet {
                width: auto; max-width: none; min-height: auto; margin: 0; padding: 0;
                box-shadow: none;
            }
        }
        @media (max-width: 720px) {
            .sheet { margin: 0; padding: 20px 16px 28px; min-height: auto; }
            .top, .parties, .grid3 { grid-template-columns: 1fr; }
            .meta { text-align: left; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <p>Invoice preview — review then print</p>
    <div>
        <button type="button" class="print" onclick="window.print()">Print</button>
        <button type="button" class="back" onclick="window.history.back()">Back</button>
    </div>
</div>

<article class="sheet">
    <header class="top">
        <div class="brand">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $brandName }}">
            @else
                <div class="brand-fallback">{{ mb_strtoupper(mb_substr($brandName, 0, 2)) }}</div>
            @endif
            <div>
                <h1>{{ $brandName }}</h1>
                @if($tagline !== '')
                    <div class="tag">{{ $tagline }}</div>
                @endif
            </div>
        </div>
        <div class="meta">
            <p class="label">INVOICE</p>
            <span class="badge {{ $statusClass }}">{{ $status }}</span>
            <ul>
                <li><strong>Invoice #</strong> {{ $car->id }}</li>
                <li><strong>Invoice Date</strong> {{ $invoiceDate }}</li>
                <li><strong>Print Date</strong> {{ $printDate }}</li>
                <li><strong>Last Updated</strong> {{ $updatedAt }}</li>
            </ul>
        </div>
    </header>

    <section class="section parties">
        <div>
            <h2 class="section-title">From</h2>
            <p class="party-name">{{ $brandName }}</p>
            @if($address !== '')
                <p class="party-line">{{ $address }}</p>
            @endif
            @if($phone !== '')
                <p class="party-line">{{ $phone }}</p>
            @endif
            @if($website !== '')
                <p class="party-line">{{ $website }}</p>
            @endif
        </div>
        <div>
            <h2 class="section-title">Bill To</h2>
            <p class="party-name">{{ $clientName !== '' ? $clientName : '—' }}</p>
            @if($clientPhone !== '')
                <p class="party-line">{{ $clientPhone }}</p>
            @else
                <p class="party-line">No billing phone on file</p>
            @endif
        </div>
    </section>

    <section class="section">
        <h2 class="section-title">Vehicle Details</h2>
        <div class="grid3">
            <div>
                <span class="field-label">Year</span>
                <div class="field-value">{{ $car->year ?: '—' }}</div>
            </div>
            <div>
                <span class="field-label">Make</span>
                <div class="field-value">{{ $make }}</div>
            </div>
            <div>
                <span class="field-label">Model</span>
                <div class="field-value">{{ $model }}</div>
            </div>
            <div>
                <span class="field-label">VIN</span>
                <div class="field-value mono" dir="ltr">{{ $car->vin ?: '—' }}</div>
            </div>
            <div>
                <span class="field-label">Lot / Stock #</span>
                <div class="field-value mono">{{ $car->car_number ?: '—' }}</div>
            </div>
            <div>
                <span class="field-label">Color</span>
                <div class="field-value">{{ $car->car_color ?: '—' }}</div>
            </div>
            <div>
                <span class="field-label">Auction</span>
                <div class="field-value">{{ $auctionName }}</div>
            </div>
            <div>
                <span class="field-label">Shipping Route</span>
                <div class="field-value">{{ $routeName }}</div>
            </div>
            <div>
                <span class="field-label">Buyer / Client #</span>
                <div class="field-value">{{ $client->id ?? '—' }}</div>
            </div>
        </div>
    </section>

    <section class="section">
        <h2 class="section-title">Invoice Items</h2>
        <table class="items">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lineItems as $item)
                    <tr>
                        <td>{{ $item['description'] }}</td>
                        <td class="{{ !empty($item['credit']) ? 'credit' : '' }}">{{ $money($item['amount']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2">No line items</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="totals-wrap">
            <div class="totals">
                <div class="totals-row"><span>Subtotal</span><span>{{ $money($total) }}</span></div>
                @if($paid > 0.009)
                    <div class="totals-row"><span>Paid</span><span>{{ $money($paid) }}</span></div>
                @endif
                @if($discount > 0.009)
                    <div class="totals-row"><span>Discount</span><span>−{{ $money($discount) }}</span></div>
                @endif
                @if($damage > 0.009)
                    <div class="totals-row"><span>Damage Comp.</span><span>−{{ $money($damage) }}</span></div>
                @endif
                <div class="totals-row due">
                    <span>Balance Due</span>
                    <span>{{ $money(max(0, $balanceDue)) }} USD</span>
                </div>
            </div>
        </div>
    </section>

    @if($note !== '')
        <div class="note"><strong>Note:</strong> {{ $note }}</div>
    @endif

    <footer class="footer">
        {{ $brandName }}@if($tagline !== '') — {{ $tagline }}@endif
        @if($website !== '') · {{ $website }}@endif
    </footer>
</article>
</body>
</html>
