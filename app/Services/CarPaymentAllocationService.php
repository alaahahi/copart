<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Transactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Operational reference: which payment allocated money to which car.
 * Does NOT post journals — accounting review stays on journal_entries.
 * Maintains car.paid as a cache = sum(allocation amounts).
 */
class CarPaymentAllocationService
{
    public const SOURCE_DIRECT = 'direct_payment';

    public const SOURCE_FROM_BALANCE = 'from_balance';

    public const SOURCE_LEGACY = 'legacy_balance';

    public function __construct(protected CarService $cars)
    {
    }

    /**
     * @param  array{
     *   source:string,
     *   transaction_id?:?int,
     *   amount:float|int,
     *   discount?:float|int,
     *   currency?:string,
     *   note?:?string
     * }  $entry
     */
    public function append(Car $car, array $entry): Car
    {
        $amount = round((float) ($entry['amount'] ?? 0), 2);
        $discount = round((float) ($entry['discount'] ?? 0), 2);

        if ($amount <= 0 && $discount <= 0) {
            return $car;
        }

        if (! Schema::hasColumn('car', 'payment_allocations')) {
            if ($amount > 0) {
                $car->increment('paid', $amount);
            }
            $car = $car->fresh() ?? $car;
            $car->results = $this->cars->resolveResultsStatus(
                (float) $car->total_s,
                (float) $car->paid,
                (float) $car->discount
            );
            $car->save();

            return $car->fresh() ?? $car;
        }

        $list = $this->normalizeList($car->payment_allocations);

        $list[] = [
            'source' => (string) ($entry['source'] ?? self::SOURCE_DIRECT),
            'transaction_id' => isset($entry['transaction_id']) ? (int) $entry['transaction_id'] : null,
            'amount' => $amount,
            'discount' => $discount,
            'currency' => (string) ($entry['currency'] ?? '$'),
            'note' => isset($entry['note']) ? (string) $entry['note'] : null,
            'at' => now()->toIso8601String(),
            'by' => Auth::id() ? (int) Auth::id() : null,
        ];

        return $this->persistAndSync($car, $list);
    }

    /**
     * Remove allocations tied to a payment transaction id, then resync paid cache.
     */
    public function removeByTransactionId(Car $car, int $transactionId): Car
    {
        if (! Schema::hasColumn('car', 'payment_allocations') || $transactionId <= 0) {
            return $car;
        }

        $list = array_values(array_filter(
            $this->normalizeList($car->payment_allocations),
            fn ($row) => (int) ($row['transaction_id'] ?? 0) !== $transactionId
        ));

        return $this->persistAndSync($car, $list);
    }

    /**
     * Clear all allocations (e.g. DelPayFromBalanceCar full reset).
     */
    public function clear(Car $car): Car
    {
        if (! Schema::hasColumn('car', 'payment_allocations')) {
            $car->paid = 0;
            $car->results = 0;
            $car->save();

            return $car->fresh();
        }

        return $this->persistAndSync($car, []);
    }

    /**
     * After mutating discount column independently, sync results from paid cache.
     */
    public function syncPaidFromAllocations(Car $car): Car
    {
        if (! Schema::hasColumn('car', 'payment_allocations')) {
            return $car;
        }

        return $this->persistAndSync($car, $this->normalizeList($car->payment_allocations));
    }

    /**
     * Backfill JSON from car-linked payment txs, or legacy single row from current paid.
     */
    public function backfillCar(Car $car): Car
    {
        if (! Schema::hasColumn('car', 'payment_allocations')) {
            return $car;
        }

        $existing = $this->normalizeList($car->payment_allocations);
        if ($existing !== []) {
            return $this->persistAndSync($car, $existing);
        }

        $list = [];
        $txs = Transactions::query()
            ->whereIn('morphed_type', [Car::class, 'App\\Models\\Car', 'App\Models\Car'])
            ->where('morphed_id', $car->id)
            ->where('type', 'out')
            ->where('is_pay', 1)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        foreach ($txs as $tx) {
            $details = is_array($tx->details) ? $tx->details : [];
            $paidPart = (float) ($details['paid'] ?? 0);
            $discPart = (float) ($details['discount'] ?? $tx->discount ?? 0);
            if ($paidPart <= 0 && $discPart <= 0) {
                $gross = abs((float) $tx->amount);
                $discPart = (float) ($tx->discount ?? 0);
                $paidPart = max(0, $gross - $discPart);
            }
            if ($paidPart <= 0 && $discPart <= 0) {
                continue;
            }
            $list[] = [
                'source' => self::SOURCE_DIRECT,
                'transaction_id' => (int) $tx->id,
                'amount' => round($paidPart, 2),
                'discount' => round($discPart, 2),
                'currency' => (string) ($tx->currency ?? '$'),
                'note' => null,
                'at' => optional($tx->created_at)->toIso8601String() ?? now()->toIso8601String(),
                'by' => null,
            ];
        }

        $sumPaid = array_sum(array_column($list, 'amount'));
        $currentPaid = round((float) $car->paid, 2);
        if ($currentPaid > $sumPaid + 0.009) {
            $list[] = [
                'source' => self::SOURCE_LEGACY,
                'transaction_id' => null,
                'amount' => round($currentPaid - $sumPaid, 2),
                'discount' => 0,
                'currency' => '$',
                'note' => 'توزيع قديم / رصيد بدون أثر حركة كامل',
                'at' => now()->toIso8601String(),
                'by' => null,
            ];
        }

        return $this->persistAndSync($car, $list);
    }

    /**
     * @param  mixed  $raw
     * @return list<array<string, mixed>>
     */
    public function normalizeList(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    public function sumAmounts(array $list): float
    {
        $sum = 0.0;
        foreach ($list as $row) {
            $sum += (float) ($row['amount'] ?? 0);
        }

        return round($sum, 2);
    }

    public function sumDiscounts(array $list): float
    {
        $sum = 0.0;
        foreach ($list as $row) {
            $sum += (float) ($row['discount'] ?? 0);
        }

        return round($sum, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $list
     */
    protected function persistAndSync(Car $car, array $list): Car
    {
        $paid = $this->sumAmounts($list);
        // Keep column discount as max(current column ops, sum from JSON discount fields)
        // Discount may also be incremented separately (addPaymentCarTotal) — prefer column when higher.
        $jsonDiscount = $this->sumDiscounts($list);
        $discount = max((float) $car->discount, $jsonDiscount);

        $car->payment_allocations = $list;
        $car->paid = $paid;
        $car->results = $this->cars->resolveResultsStatus(
            (float) $car->total_s,
            $paid,
            $discount
        );
        $car->save();

        return $car->fresh();
    }
}
