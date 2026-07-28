<?php

namespace App\Services\Analytics;

use App\Models\Car;
use App\Models\CarExpenses;
use App\Models\User;
use App\Models\UserType;
use App\Services\CarService;
use App\Services\LedgerService;
use App\Services\SystemWalletService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnalyticsService
{
    public function __construct(protected LedgerService $ledger)
    {
    }

    /**
     * Full analytics payload for a period (used by dashboard API).
     *
     * @param  array{from:string,to:string,currency:string,client_id?:int|null,results?:int|null}  $filters
     */
    public function dashboard(int $ownerId, array $filters): array
    {
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();
        $currency = ($filters['currency'] ?? '$') === 'IQD' ? 'IQD' : '$';
        $clientId = isset($filters['client_id']) && $filters['client_id'] !== '' && $filters['client_id'] !== null
            ? (int) $filters['client_id']
            : null;
        $results = isset($filters['results']) && $filters['results'] !== '' && $filters['results'] !== null
            ? (int) $filters['results']
            : null;

        $periodDays = max(1, $from->diffInDays($to) + 1);
        $prevTo = $from->copy()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->subDays($periodDays - 1)->startOfDay();

        $kpis = $this->kpis($ownerId, $from, $to, $currency, $clientId, $results);
        $prevKpis = $this->kpis($ownerId, $prevFrom, $prevTo, $currency, $clientId, $results);

        return [
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'currency' => $currency,
                'client_id' => $clientId,
                'results' => $results,
            ],
            'traders' => $this->tradersList($ownerId),
            'kpis' => $kpis,
            'mom' => $this->momDelta($kpis, $prevKpis),
            'previous_period' => [
                'from' => $prevFrom->toDateString(),
                'to' => $prevTo->toDateString(),
                'kpis' => $prevKpis,
            ],
            'trader_profits' => $this->traderProfits($ownerId, $from, $to, $currency, $clientId, $results),
            'car_pricing' => $this->carPricing($ownerId, $from, $to, $clientId, $results),
            'expenses' => $this->expensesBreakdown($ownerId, $from, $to, $currency),
            'aging' => $this->arAging($ownerId, $currency, $clientId),
            'alerts' => $this->alerts($ownerId, $from, $to, $currency, $clientId, $results, $kpis),
            'cash' => $this->cashSummary($ownerId, $currency),
        ];
    }

    public function tradersList(int $ownerId): array
    {
        $clientTypeId = $this->clientTypeId();

        $query = User::query()
            ->where('owner_id', $ownerId)
            ->where('type_id', $clientTypeId);
        SystemWalletService::scopeExcludeSystemVaults($query);

        return $query
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->values()
            ->all();
    }

    /**
     * @return array<string, float|int>
     */
    public function kpis(
        int $ownerId,
        Carbon $from,
        Carbon $to,
        string $currency,
        ?int $clientId,
        ?int $results
    ): array {
        $cars = $this->carQuery($ownerId, $from, $to, $clientId, $results)->get([
            'id', 'total', 'total_s', 'paid', 'discount', 'profit', 'results', 'client_id',
        ]);

        $sales = (float) $cars->sum('total_s');
        $cost = (float) $cars->sum('total');
        $paid = (float) $cars->sum('paid');
        $discount = (float) $cars->sum('discount');
        // Profit only for cars with sale pricing — purchase-only cost is not a "loss".
        $carService = app(CarService::class);
        $netProfit = (float) $cars->sum(fn ($c) => $carService->computeProfit(
            (float) ($c->total_s ?? 0),
            (float) ($c->total ?? 0)
        ));
        $margin = $sales > 0 ? round(($netProfit / $sales) * 100, 2) : 0.0;

        $periodExpenses = $this->periodExpensesTotal($ownerId, $from, $to, $currency);

        $receivables = $this->ledger->sumClientsReceivableWithFallback(
            $ownerId,
            $this->clientTypeId(),
            $currency
        );

        $cashBox = 0.0;
        try {
            $cashBox = (float) $this->ledger->cashAccount($ownerId, $currency)->balance($currency);
        } catch (\Throwable) {
            $cashBox = 0.0;
        }

        return [
            'sales' => round($sales, 2),
            'cost' => round($cost, 2),
            'net_profit' => round($netProfit, 2),
            'margin_pct' => $margin,
            'paid' => round($paid, 2),
            'discount' => round($discount, 2),
            'remaining' => round($sales - $paid - $discount, 2),
            'receivables' => round($receivables, 2),
            'cash_box' => round($cashBox, 2),
            'period_expenses' => round($periodExpenses, 2),
            'cars_count' => $cars->count(),
            'cars_unpaid' => $cars->where('results', 0)->count(),
            'cars_paid' => $cars->whereIn('results', [1, 2])->count(),
            'loss_cars' => $cars->filter(function ($c) use ($carService) {
                $profit = $carService->computeProfit(
                    (float) ($c->total_s ?? 0),
                    (float) ($c->total ?? 0)
                );

                return (float) ($c->total_s ?? 0) > 0 && $profit < 0;
            })->count(),
        ];
    }

    /**
     * Trader P&L table for the period.
     */
    public function traderProfits(
        int $ownerId,
        Carbon $from,
        Carbon $to,
        string $currency,
        ?int $clientId,
        ?int $results
    ): array {
        $rows = $this->carQuery($ownerId, $from, $to, $clientId, $results)
            ->select([
                'client_id',
                DB::raw('COUNT(*) as cars_count'),
                DB::raw('COALESCE(SUM(total_s), 0) as sales'),
                DB::raw('COALESCE(SUM(total), 0) as cost'),
                DB::raw('COALESCE(SUM(CASE WHEN COALESCE(total_s, 0) > 0 THEN COALESCE(total_s, 0) - COALESCE(total, 0) ELSE 0 END), 0) as profit'),
                DB::raw('COALESCE(SUM(paid), 0) as paid'),
                DB::raw('COALESCE(SUM(discount), 0) as discount'),
            ])
            ->groupBy('client_id')
            ->get();

        $clientIds = $rows->pluck('client_id')->filter()->unique()->values();
        $names = User::query()
            ->whereIn('id', $clientIds)
            ->pluck('name', 'id');

        $mapped = $rows->map(function ($row) use ($ownerId, $currency, $names) {
            $sales = (float) $row->sales;
            $profit = (float) $row->profit;
            $paid = (float) $row->paid;
            $discount = (float) $row->discount;
            $clientId = (int) $row->client_id;
            $ledgerBalance = $clientId
                ? (float) $this->ledger->clientBalance($ownerId, $clientId, $currency)
                : 0.0;

            return [
                'client_id' => $clientId,
                'trader' => $names[$clientId] ?? ('#' . $clientId),
                'cars_count' => (int) $row->cars_count,
                'sales' => round($sales, 2),
                'cost' => round((float) $row->cost, 2),
                'profit' => round($profit, 2),
                'margin_pct' => $sales > 0 ? round(($profit / $sales) * 100, 2) : 0.0,
                'paid' => round($paid, 2),
                'remaining' => round($sales - $paid - $discount, 2),
                'ledger_balance' => round($ledgerBalance, 2),
            ];
        })->sortByDesc('profit')->values();

        $maxAbsProfit = max(1.0, (float) $mapped->max(fn ($r) => abs($r['profit'])));

        return $mapped->map(function ($row) use ($maxAbsProfit) {
            $row['bar_pct'] = round((abs($row['profit']) / $maxAbsProfit) * 100, 1);
            $row['bar_positive'] = $row['profit'] >= 0;

            return $row;
        })->all();
    }

    public function carPricing(
        int $ownerId,
        Carbon $from,
        Carbon $to,
        ?int $clientId,
        ?int $results
    ): array {
        $cars = $this->carQuery($ownerId, $from, $to, $clientId, $results)->get([
            'id', 'car_type', 'vin', 'car_number', 'total', 'total_s', 'profit', 'client_id', 'date',
        ]);

        $avgSale = $cars->avg('total_s') ?? 0;
        $avgCost = $cars->avg('total') ?? 0;
        $carService = app(CarService::class);
        $soldCars = $cars->filter(fn ($c) => (float) ($c->total_s ?? 0) > 0);
        $avgProfit = $soldCars->isEmpty()
            ? 0
            : $soldCars->avg(fn ($c) => $carService->computeProfit(
                (float) ($c->total_s ?? 0),
                (float) ($c->total ?? 0)
            ));

        $buckets = [
            'loss' => 0,
            '0_10' => 0,
            '10_20' => 0,
            '20_plus' => 0,
        ];

        foreach ($cars as $car) {
            $sale = (float) $car->total_s;
            if ($sale <= 0) {
                // Purchase-only: not counted as loss until registered in sales.
                continue;
            }
            $profit = $carService->computeProfit($sale, (float) ($car->total ?? 0));
            $margin = ($profit / $sale) * 100;
            if ($margin < 0) {
                $buckets['loss']++;
            } elseif ($margin < 10) {
                $buckets['0_10']++;
            } elseif ($margin < 20) {
                $buckets['10_20']++;
            } else {
                $buckets['20_plus']++;
            }
        }

        $mapCar = function ($car) use ($carService) {
            $sale = (float) $car->total_s;
            $profit = $carService->computeProfit($sale, (float) ($car->total ?? 0));

            return [
                'id' => $car->id,
                'label' => trim(($car->car_type ?? '') . ' ' . ($car->car_number ?? $car->vin ?? '#' . $car->id)),
                'vin' => $car->vin,
                'sales' => round((float) $car->total_s, 2),
                'cost' => round((float) $car->total, 2),
                'profit' => round($profit, 2),
                'margin_pct' => $sale > 0 ? round(($profit / $sale) * 100, 2) : 0.0,
                'date' => $car->date,
            ];
        };

        // Rank only cars that have sale pricing.
        [$top, $worst] = $this->splitTopAndWorstCars($soldCars->values(), $mapCar, 10);

        return [
            'avg_sale' => round((float) $avgSale, 2),
            'avg_cost' => round((float) $avgCost, 2),
            'avg_profit' => round((float) $avgProfit, 2),
            'cars_count' => $cars->count(),
            'margin_buckets' => $buckets,
            'top_cars' => $top,
            'worst_cars' => $worst,
        ];
    }

    /**
     * Best / worst cars by profit without listing the same car in both.
     *
     * - 0 cars → both empty
     * - 1 car → only «أعلى ربح» if profit >= 0, only «أقل ربح» if profit < 0
     * - 2+ cars → up to $limit on each side, capped so the lists never overlap
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Car>  $cars
     * @param  callable(\App\Models\Car): array  $mapCar
     * @return array{0: list<array>, 1: list<array>}
     */
    protected function splitTopAndWorstCars($cars, callable $mapCar, int $limit = 10): array
    {
        $count = $cars->count();

        if ($count === 0) {
            return [[], []];
        }

        if ($count === 1) {
            $car = $cars->first();
            $mapped = [$mapCar($car)];
            $profit = (float) ($car->total_s ?? 0) - (float) ($car->total ?? 0);

            return ($profit >= 0)
                ? [$mapped, []]
                : [[], $mapped];
        }

        // Cap each side so top and worst never share a car (e.g. 2 cars → 1 each).
        $perSide = min($limit, intdiv($count, 2));
        $profitOf = fn ($car) => (float) ($car->total_s ?? 0) - (float) ($car->total ?? 0);

        $top = $cars
            ->sortByDesc(fn ($car) => [$profitOf($car), $car->id])
            ->take($perSide)
            ->values()
            ->map($mapCar)
            ->all();

        $worst = $cars
            ->sortBy(fn ($car) => [$profitOf($car), $car->id])
            ->take($perSide)
            ->values()
            ->map($mapCar)
            ->all();

        return [$top, $worst];
    }

    public function expensesBreakdown(
        int $ownerId,
        Carbon $from,
        Carbon $to,
        string $currency
    ): array {
        // General expenses table removed — only car_expenses remain.
        $genTotal = 0.0;
        $byType = [];

        $carExpQuery = CarExpenses::query()
            ->where('owner_id', $ownerId)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('created', [$from->toDateString(), $to->toDateString()])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->where(function ($d) {
                            $d->whereNull('created')->orWhere('created', '');
                        })->whereBetween('created_at', [
                            $from->toDateTimeString(),
                            $to->toDateTimeString(),
                        ]);
                    });
            });

        $carDollar = (float) (clone $carExpQuery)->sum('amount_dollar');
        $carDinar = (float) (clone $carExpQuery)->sum('amount_dinar');
        $carTotal = $currency === 'IQD' ? $carDinar : $carDollar;

        $monthly = $this->expensesMonthlyTrend($ownerId, $to, 12);

        return [
            'general_total' => round($genTotal, 2),
            'car_total' => round($carTotal, 2),
            'car_dollar' => round($carDollar, 2),
            'car_dinar' => round($carDinar, 2),
            'combined_total' => round($genTotal + ($currency === 'IQD' ? 0 : $carDollar), 2),
            'by_type' => $byType,
            'monthly_trend' => $monthly,
        ];
    }

    /**
     * AR aging from unpaid / partially paid cars by entry date.
     */
    public function arAging(int $ownerId, string $currency, ?int $clientId): array
    {
        $query = Car::query()
            ->where('owner_id', $ownerId)
            ->where('results', 0);

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        $cars = $query->get(['id', 'total_s', 'paid', 'discount', 'date', 'created_at', 'client_id']);

        $buckets = [
            '0_30' => 0.0,
            '31_60' => 0.0,
            '61_90' => 0.0,
            '90_plus' => 0.0,
            'unknown' => 0.0,
        ];
        $counts = [
            '0_30' => 0,
            '31_60' => 0,
            '61_90' => 0,
            '90_plus' => 0,
            'unknown' => 0,
        ];

        $today = Carbon::today();

        foreach ($cars as $car) {
            $remaining = max(0, (float) $car->total_s - (float) $car->paid - (float) $car->discount);
            if ($remaining <= 0) {
                continue;
            }

            $ref = $this->resolveCarDate($car);
            if (!$ref) {
                $buckets['unknown'] += $remaining;
                $counts['unknown']++;
                continue;
            }

            $days = $ref->diffInDays($today);
            if ($days <= 30) {
                $buckets['0_30'] += $remaining;
                $counts['0_30']++;
            } elseif ($days <= 60) {
                $buckets['31_60'] += $remaining;
                $counts['31_60']++;
            } elseif ($days <= 90) {
                $buckets['61_90'] += $remaining;
                $counts['61_90']++;
            } else {
                $buckets['90_plus'] += $remaining;
                $counts['90_plus']++;
            }
        }

        $ledgerReceivables = round(
            $this->ledger->sumClientsReceivableWithFallback($ownerId, $this->clientTypeId(), $currency),
            2
        );

        return [
            'buckets' => array_map(fn ($v) => round($v, 2), $buckets),
            'counts' => $counts,
            'cars_remaining_total' => round(array_sum($buckets), 2),
            'ledger_receivables' => $ledgerReceivables,
        ];
    }

    public function alerts(
        int $ownerId,
        Carbon $from,
        Carbon $to,
        string $currency,
        ?int $clientId,
        ?int $results,
        array $kpis
    ): array {
        $alerts = [];

        if (($kpis['loss_cars'] ?? 0) > 0) {
            $alerts[] = [
                'level' => 'danger',
                'code' => 'loss_cars',
                'message' => 'يوجد ' . $kpis['loss_cars'] . ' سيارة بخسارة في الفترة',
                'value' => $kpis['loss_cars'],
            ];
        }

        if (($kpis['receivables'] ?? 0) > 0 && ($kpis['sales'] ?? 0) > 0) {
            $arRatio = ($kpis['receivables'] / max(1, $kpis['sales'])) * 100;
            if ($arRatio >= 50 || $kpis['receivables'] >= 10000) {
                $alerts[] = [
                    'level' => 'warning',
                    'code' => 'high_ar',
                    'message' => 'ذمم التجار مرتفعة: ' . \App\Helpers\Help::formatMoney($kpis['receivables'], '$'),
                    'value' => $kpis['receivables'],
                ];
            }
        }

        $sales = (float) ($kpis['sales'] ?? 0);
        $expenses = (float) ($kpis['period_expenses'] ?? 0);
        if ($sales > 0) {
            $expenseRatio = ($expenses / $sales) * 100;
            if ($expenseRatio >= 30) {
                $alerts[] = [
                    'level' => 'warning',
                    'code' => 'expense_ratio',
                    'message' => 'نسبة المصاريف إلى المبيعات ' . round($expenseRatio, 1) . '%',
                    'value' => round($expenseRatio, 2),
                ];
            }
        }

        if (($kpis['margin_pct'] ?? 0) < 5 && ($kpis['cars_count'] ?? 0) > 0) {
            $alerts[] = [
                'level' => 'info',
                'code' => 'low_margin',
                'message' => 'هامش الربح منخفض: ' . $kpis['margin_pct'] . '%',
                'value' => $kpis['margin_pct'],
            ];
        }

        return $alerts;
    }

    public function cashSummary(int $ownerId, string $currency): array
    {
        $cashUsd = 0.0;
        $cashIqd = 0.0;
        $treasuryUsd = 0.0;
        $treasuryIqd = 0.0;

        try {
            $cashUsd = (float) $this->ledger->cashAccount($ownerId, '$')->balance('$');
        } catch (\Throwable) {
        }
        try {
            $cashIqd = (float) $this->ledger->cashAccount($ownerId, 'IQD')->balance('IQD');
        } catch (\Throwable) {
        }
        try {
            $treasuryUsd = (float) $this->ledger->treasuryAccount($ownerId, '$')->balance('$');
        } catch (\Throwable) {
        }
        try {
            $treasuryIqd = (float) $this->ledger->treasuryAccount($ownerId, 'IQD')->balance('IQD');
        } catch (\Throwable) {
        }

        // Operational treasury table if present
        $opsTreasuryUsd = null;
        $opsTreasuryIqd = null;
        if (Schema::hasTable('company_treasury_entries')) {
            $opsTreasuryUsd = $this->opsTreasuryBalance($ownerId, '$');
            $opsTreasuryIqd = $this->opsTreasuryBalance($ownerId, 'IQD');
        }

        return [
            'cash_usd' => round($cashUsd, 2),
            'cash_iqd' => round($cashIqd, 2),
            'treasury_usd' => round($treasuryUsd, 2),
            'treasury_iqd' => round($treasuryIqd, 2),
            'ops_treasury_usd' => $opsTreasuryUsd !== null ? round($opsTreasuryUsd, 2) : null,
            'ops_treasury_iqd' => $opsTreasuryIqd !== null ? round($opsTreasuryIqd, 2) : null,
            'selected_cash' => round($currency === 'IQD' ? $cashIqd : $cashUsd, 2),
            'selected_treasury' => round($currency === 'IQD' ? $treasuryIqd : $treasuryUsd, 2),
        ];
    }

    protected function momDelta(array $current, array $previous): array
    {
        $keys = ['sales', 'cost', 'net_profit', 'margin_pct', 'period_expenses', 'cars_count', 'receivables', 'cash_box'];
        $out = [];
        foreach ($keys as $key) {
            $cur = (float) ($current[$key] ?? 0);
            $prev = (float) ($previous[$key] ?? 0);
            $delta = $cur - $prev;
            $pct = $prev != 0.0 ? round(($delta / abs($prev)) * 100, 2) : ($cur != 0.0 ? 100.0 : 0.0);
            $out[$key] = [
                'current' => $cur,
                'previous' => $prev,
                'delta' => round($delta, 2),
                'pct' => $pct,
            ];
        }

        return $out;
    }

    protected function carQuery(
        int $ownerId,
        Carbon $from,
        Carbon $to,
        ?int $clientId,
        ?int $results
    ) {
        $query = Car::query()->where('owner_id', $ownerId);

        // Prefer business date; fall back to created_at when date is empty/null
        $query->where(function ($q) use ($from, $to) {
            $q->where(function ($inner) use ($from, $to) {
                $inner->whereNotNull('date')
                    ->where('date', '!=', '')
                    ->where('date', '!=', '0000-00-00')
                    ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
            })->orWhere(function ($inner) use ($from, $to) {
                $inner->where(function ($d) {
                    $d->whereNull('date')
                        ->orWhere('date', '')
                        ->orWhere('date', '0000-00-00');
                })->whereBetween('created_at', [
                    $from->toDateTimeString(),
                    $to->toDateTimeString(),
                ]);
            });
        });

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        if ($results !== null) {
            $query->where('results', $results);
        }

        return $query;
    }

    protected function periodExpensesTotal(
        int $ownerId,
        Carbon $from,
        Carbon $to,
        string $currency
    ): float {
        $carCol = $currency === 'IQD' ? 'amount_dinar' : 'amount_dollar';

        return (float) CarExpenses::query()
            ->where('owner_id', $ownerId)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('created', [$from->toDateString(), $to->toDateString()])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->where(function ($d) {
                            $d->whereNull('created')->orWhere('created', '');
                        })->whereBetween('created_at', [
                            $from->toDateTimeString(),
                            $to->toDateTimeString(),
                        ]);
                    });
            })
            ->sum($carCol);
    }

    protected function expensesMonthlyTrend(int $ownerId, Carbon $anchorTo, int $months = 12): array
    {
        $start = $anchorTo->copy()->startOfMonth()->subMonths($months - 1);
        $end = $anchorTo->copy()->endOfMonth();

        $carRows = CarExpenses::query()
            ->where('owner_id', $ownerId)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('created', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($inner) use ($start, $end) {
                        $inner->where(function ($d) {
                            $d->whereNull('created')->orWhere('created', '');
                        })->whereBetween('created_at', [
                            $start->toDateTimeString(),
                            $end->toDateTimeString(),
                        ]);
                    });
            })
            ->get(['amount_dollar', 'created', 'created_at']);

        $trend = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $key = $m->format('Y-m');
            $trend[$key] = [
                'month' => $key,
                'label' => $m->format('Y/m'),
                'general' => 0.0,
                'car' => 0.0,
                'total' => 0.0,
            ];
        }

        foreach ($carRows as $row) {
            $dt = $row->created ?: $row->created_at;
            if (!$dt) {
                continue;
            }
            $key = Carbon::parse($dt)->format('Y-m');
            if (isset($trend[$key])) {
                $trend[$key]['car'] += (float) $row->amount_dollar;
            }
        }

        foreach ($trend as &$item) {
            $item['general'] = round($item['general'], 2);
            $item['car'] = round($item['car'], 2);
            $item['total'] = round($item['general'] + $item['car'], 2);
        }
        unset($item);

        return array_values($trend);
    }

    protected function opsTreasuryBalance(int $ownerId, string $currency): float
    {
        $last = DB::table('company_treasury_entries')
            ->where('owner_id', $ownerId)
            ->where('currency', $currency)
            ->whereNull('deleted_at')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->value('balance');

        if ($last !== null) {
            return (float) $last;
        }

        $debit = (float) DB::table('company_treasury_entries')
            ->where('owner_id', $ownerId)
            ->where('currency', $currency)
            ->whereNull('deleted_at')
            ->sum('debit');
        $credit = (float) DB::table('company_treasury_entries')
            ->where('owner_id', $ownerId)
            ->where('currency', $currency)
            ->whereNull('deleted_at')
            ->sum('credit');

        return $debit - $credit;
    }

    protected function resolveCarDate($car): ?Carbon
    {
        $raw = $car->date ?? null;
        if ($raw && $raw !== '0000-00-00' && $raw !== '') {
            try {
                return Carbon::parse($raw)->startOfDay();
            } catch (\Throwable) {
            }
        }
        if (!empty($car->created_at)) {
            try {
                return Carbon::parse($car->created_at)->startOfDay();
            } catch (\Throwable) {
            }
        }

        return null;
    }

    protected function clientTypeId(): int
    {
        static $id = null;
        if ($id === null) {
            $id = (int) UserType::where('name', 'client')->value('id');
        }

        return $id;
    }
}
