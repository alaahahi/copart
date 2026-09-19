@php
    /** @var \App\Models\Car $car */
    /** @var \App\Models\User $client */
    /** @var \App\Models\SystemConfig|null $config */

    $cfg = $config instanceof \Illuminate\Database\Eloquent\Model ? $config->toArray() : (array) ($config ?? []);
    $brandName = \App\Support\Branding::resolveName((string) ($cfg['first_title_ar'] ?? ''));
    $tagline = \App\Support\Branding::tagline();
    $phone = trim((string) ($cfg['receipt_phone'] ?? ''));
    $address = trim((string) ($cfg['receipt_address'] ?? ''));
    $website = trim((string) ($cfg['receipt_website'] ?? ''));

    $logoPath = $cfg['app_logo'] ?? null;
    if (! $logoPath) {
        $logoPath = $cfg['receipt_logo_main'] ?? null;
    }
    $logoUrl = \App\Helpers\Help::publicAssetUrl($logoPath) ?: \App\Helpers\Help::publicAssetUrl('/img/logo.jpg');

    $carType = trim((string) ($car->car_type ?? ''));
    $typeParts = preg_split('/\s+/', $carType, 2) ?: [];
    $make = $typeParts[0] ?? ($carType !== '' ? $carType : '—');
    $model = $typeParts[1] ?? '—';

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
        $statusClass = 'inv-badge--paid';
    } elseif ($paid > 0.009) {
        $status = 'PARTIAL';
        $statusClass = 'inv-badge--partial';
    } else {
        $status = 'UNPAID';
        $statusClass = 'inv-badge--unpaid';
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
    $push('USA Transfer / Towing', (float) ($car->dinar_s ?? 0));
    $push('Recovery', (float) ($car->coc_dolar_s ?? 0));
    $push('Repair Expenses', (float) ($car->checkout_s ?? 0));
    $push('Erbil Transfer', $erbilSub);
    $push('Erbil Expenses / Commission', (float) ($car->commission_s ?? 0));
    $push('Discount', $discount, true);
    $push('Damage Compensation', $damage, true);

    if ($lineItems === [] && $total > 0) {
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
        @page { size: A4; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            background: #e8edf3;
            color: #1e293b;
            font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
            font-size: 13px;
            line-height: 1.45;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .inv-toolbar {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 16px;
            background: #0f172a;
            color: #f8fafc;
        }
        .inv-toolbar__hint { margin: 0; font-size: 12px; opacity: .85; }
        .inv-toolbar__actions { display: flex; gap: 8px; }
        .inv-toolbar button {
            border: 0;
            border-radius: 8px;
            padding: 8px 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .inv-toolbar .btn-print { background: #059669; color: #fff; }
        .inv-toolbar .btn-back { background: #334155; color: #fff; }

        .inv-page {
            max-width: 210mm;
            min-height: 100vh;
            margin: 0 auto;
            padding: 28px 32px 40px;
            background: #fff;
            color: #1e293b;
            box-shadow: 0 1px 10px rgba(15, 23, 42, .08);
        }

        .inv-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            margin-bottom: 28px;
        }
        .inv-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }
        .inv-logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            border-radius: 10px;
            background: #f8fafc;
        }
        .inv-brand__name {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: .02em;
            color: #0f172a;
            line-height: 1.15;
        }
        .inv-brand__tag {
            margin-top: 2px;
            font-size: 12px;
            color: #64748b;
        }

        .inv-meta { text-align: right; min-width: 220px; }
        .inv-title {
            margin: 0 0 8px;
            font-size: 34px;
            font-weight: 800;
            letter-spacing: .04em;
            color: #0f172a;
            line-height: 1;
        }
        .inv-badge {
            display: inline-block;
            margin-bottom: 12px;
            padding: 3px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .06em;
            background: #e2e8f0;
            color: #334155;
        }
        .inv-badge--paid { background: #d1fae5; color: #065f46; }
        .inv-badge--partial { background: #fef3c7; color: #92400e; }
        .inv-badge--unpaid { background: #fee2e2; color: #991b1b; }

        .inv-meta-list { margin: 0; padding: 0; list-style: none; font-size: 12px; color: #475569; }
        .inv-meta-list li { margin: 3px 0; }
        .inv-meta-list strong { color: #0f172a; font-weight: 700; }

        .inv-section { margin-top: 26px; }
        .inv-section__title {
            margin: 0 0 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
            color: #166534;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .inv-parties {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
        }
        .inv-party__name {
            margin: 0 0 4px;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }
        .inv-party__line {
            margin: 0;
            font-size: 12.5px;
            color: #64748b;
            line-height: 1.5;
        }

        .inv-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px 20px;
        }
        .inv-field__label {
            display: block;
            margin-bottom: 2px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .inv-field__value {
            font-size: 13.5px;
            font-weight: 600;
            color: #0f172a;
            word-break: break-word;
        }
        .inv-field__value--mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

        .inv-items { width: 100%; border-collapse: collapse; }
        .inv-items th {
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .inv-items th:last-child,
        .inv-items td:last-child { text-align: right; }
        .inv-items td {
            padding: 11px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13.5px;
            color: #1e293b;
        }
        .inv-items td.credit { color: #b45309; }

        .inv-totals-wrap {
            display: flex;
            justify-content: flex-end;
            margin-top: 18px;
        }
        .inv-totals {
            width: 280px;
            padding: 14px 16px;
            border-radius: 10px;
            background: #f8fafc;
        }
        .inv-totals__row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin: 6px 0;
            font-size: 13px;
            color: #475569;
        }
        .inv-totals__row--due {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }
        .inv-totals__row--due span:last-child { color: #166534; }

        .inv-note {
            margin-top: 22px;
            padding: 12px 14px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 12.5px;
            color: #475569;
        }
        .inv-note strong { color: #0f172a; }

        .inv-footer {
            margin-top: 36px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
        }

        @media print {
            html, body { background: #fff !important; }
            .inv-toolbar { display: none !important; }
            .inv-page {
                max-width: none;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
<div class="inv-toolbar no-print">
    <p class="inv-toolbar__hint">Invoice preview — review then print</p>
    <div class="inv-toolbar__actions">
        <button type="button" class="btn-print" onclick="window.print()">Print</button>
        <button type="button" class="btn-back" onclick="window.history.back()">Back</button>
    </div>
</div>

<div class="inv-page">
    <header class="inv-header">
        <div class="inv-brand">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="inv-logo">
            @endif
            <div>
                <div class="inv-brand__name">{{ $brandName }}</div>
                @if($tagline !== '')
                    <div class="inv-brand__tag">{{ $tagline }}</div>
                @endif
            </div>
        </div>
        <div class="inv-meta">
            <h1 class="inv-title">INVOICE</h1>
            <span class="inv-badge {{ $statusClass }}">{{ $status }}</span>
            <ul class="inv-meta-list">
                <li><strong>Invoice #</strong> {{ $car->id }}</li>
                <li><strong>Invoice Date</strong> {{ $invoiceDate }}</li>
                <li><strong>Print Date</strong> {{ $printDate }}</li>
                <li><strong>Last Updated</strong> {{ $updatedAt }}</li>
            </ul>
        </div>
    </header>

    <section class="inv-section inv-parties">
        <div>
            <h2 class="inv-section__title">From</h2>
            <p class="inv-party__name">{{ $brandName }}</p>
            @if($address !== '')
                <p class="inv-party__line">{{ $address }}</p>
            @endif
            @if($phone !== '')
                <p class="inv-party__line">{{ $phone }}</p>
            @endif
            @if($website !== '')
                <p class="inv-party__line">{{ $website }}</p>
            @endif
        </div>
        <div>
            <h2 class="inv-section__title">Bill To</h2>
            <p class="inv-party__name">{{ $clientName !== '' ? $clientName : '—' }}</p>
            @if($clientPhone !== '')
                <p class="inv-party__line">{{ $clientPhone }}</p>
            @else
                <p class="inv-party__line">No billing phone on file</p>
            @endif
        </div>
    </section>

    <section class="inv-section">
        <h2 class="inv-section__title">Vehicle Details</h2>
        <div class="inv-grid">
            <div class="inv-field">
                <span class="inv-field__label">Year</span>
                <span class="inv-field__value">{{ $car->year ?: '—' }}</span>
            </div>
            <div class="inv-field">
                <span class="inv-field__label">Make</span>
                <span class="inv-field__value">{{ $make }}</span>
            </div>
            <div class="inv-field">
                <span class="inv-field__label">Model</span>
                <span class="inv-field__value">{{ $model }}</span>
            </div>

            <div class="inv-field">
                <span class="inv-field__label">VIN</span>
                <span class="inv-field__value inv-field__value--mono">{{ $car->vin ?: '—' }}</span>
            </div>
            <div class="inv-field">
                <span class="inv-field__label">Lot / Stock #</span>
                <span class="inv-field__value inv-field__value--mono">{{ $car->car_number ?: '—' }}</span>
            </div>
            <div class="inv-field">
                <span class="inv-field__label">Color</span>
                <span class="inv-field__value">{{ $car->car_color ?: '—' }}</span>
            </div>

            <div class="inv-field">
                <span class="inv-field__label">Auction</span>
                <span class="inv-field__value">{{ $auctionName }}</span>
            </div>
            <div class="inv-field">
                <span class="inv-field__label">Shipping Route</span>
                <span class="inv-field__value">{{ $routeName }}</span>
            </div>
            <div class="inv-field">
                <span class="inv-field__label">Buyer / Client #</span>
                <span class="inv-field__value">{{ $client->id ?? '—' }}</span>
            </div>
        </div>
    </section>

    <section class="inv-section">
        <h2 class="inv-section__title">Invoice Items</h2>
        <table class="inv-items">
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
                        <td class="{{ !empty($item['credit']) ? 'credit' : '' }}">
                            {{ $money($item['amount']) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2">No line items</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="inv-totals-wrap">
            <div class="inv-totals">
                <div class="inv-totals__row">
                    <span>Subtotal</span>
                    <span>{{ $money($total) }}</span>
                </div>
                @if($paid > 0.009)
                    <div class="inv-totals__row">
                        <span>Paid</span>
                        <span>{{ $money($paid) }}</span>
                    </div>
                @endif
                @if($discount > 0.009)
                    <div class="inv-totals__row">
                        <span>Discount</span>
                        <span>−{{ $money($discount) }}</span>
                    </div>
                @endif
                @if($damage > 0.009)
                    <div class="inv-totals__row">
                        <span>Damage Comp.</span>
                        <span>−{{ $money($damage) }}</span>
                    </div>
                @endif
                <div class="inv-totals__row inv-totals__row--due">
                    <span>Balance Due</span>
                    <span>{{ $money(max(0, $balanceDue)) }} USD</span>
                </div>
            </div>
        </div>
    </section>

    @if($note !== '')
        <div class="inv-note">
            <strong>Note:</strong> {{ $note }}
        </div>
    @endif

    <footer class="inv-footer">
        {{ $brandName }}@if($tagline !== '') — {{ $tagline }}@endif
        @if($website !== '') · {{ $website }}@endif
    </footer>
</div>
</body>
</html>
