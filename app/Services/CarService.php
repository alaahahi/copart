<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Car;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CarService
{
    /**
     * Validate that a frontend-supplied auction id actually belongs to this
     * tenant before it is persisted on a car — the المزاد select must never
     * be trusted blindly (security rule: never trust frontend). Returns null
     * when the id is empty or doesn't belong to the tenant, so the field
     * stays optional and never breaks the car save.
     */
    public function resolveAuctionId(int $ownerId, $auctionId): ?int
    {
        if (!$auctionId) {
            return null;
        }

        return Auction::where('id', $auctionId)->where('owner_id', $ownerId)->value('id');
    }

    /**
     * Car payment color flag used in tables:
     * 0 = unpaid (default), 1 = partial (red), 2 = fully paid (green).
     *
     * remaining = total_s - paid - discount
     * When remaining <= 0 and something was paid/discounted → green (2).
     */
    public function resolveResultsStatus(float $totalS, float $paid, float $discount): int
    {
        $remaining = $totalS - $paid - $discount;

        if ($paid + $discount <= 0) {
            return 0;
        }

        if ($remaining > 0) {
            return 1;
        }

        return 2;
    }

    /**
     * Car is "in sales" when it has a sales total (total_s > 0).
     * Purchase-only cars must not display cost as a negative "loss".
     */
    public function hasSalePricing(?float $totalS): bool
    {
        return (float) ($totalS ?? 0) > 0;
    }

    /**
     * Profit = sales total − purchase total, only once sale pricing exists.
     * Otherwise 0 (not yet calculated — not a realized deficit).
     */
    public function computeProfit(?float $totalS, ?float $total): float
    {
        if (! $this->hasSalePricing($totalS)) {
            return 0.0;
        }

        return (float) $totalS - (float) ($total ?? 0);
    }

    /**
     * Split a sales-price change into AR / cost-recovery / revenue deltas.
     *
     * Client AR must move by the full sales delta (what the trader owes).
     * Shipping revenue (4100) must move by profit delta only.
     * The remainder offsets car-purchase expense (5110) so cost is not booked as income.
     *
     * Invariant: sales_delta === cost_recovery_delta + revenue_delta
     *
     * @return array{sales_delta: float, revenue_delta: float, cost_recovery_delta: float, old_profit: float, new_profit: float}
     */
    public function computeSaleDebtSplit(?float $oldTotalS, ?float $newTotalS, ?float $costTotal): array
    {
        $old = (float) ($oldTotalS ?? 0);
        $new = (float) ($newTotalS ?? 0);
        $cost = (float) ($costTotal ?? 0);

        $oldProfit = $this->computeProfit($old, $cost);
        $newProfit = $this->computeProfit($new, $cost);

        $salesDelta = round($new - $old, 2);
        $revenueDelta = round($newProfit - $oldProfit, 2);
        $costRecoveryDelta = round($salesDelta - $revenueDelta, 2);

        return [
            'sales_delta' => $salesDelta,
            'revenue_delta' => $revenueDelta,
            'cost_recovery_delta' => $costRecoveryDelta,
            'old_profit' => round($oldProfit, 2),
            'new_profit' => round($newProfit, 2),
        ];
    }

    /**
     * Soft-delete a car row and renumber the remaining (non-deleted) cars'
     * display sequence ("no"). The car row and its full history (payments,
     * transactions, expenses, images) are preserved — this NEVER
     * force-deletes.
     *
     * The caller is responsible for wrapping this together with any
     * wallet/accounting reversal in a single DB::transaction so the whole
     * delete stays atomic and no accounting history is lost mid-way.
     */
    public function softDelete(Car $car, int $ownerId): void
    {
        $snapshot = $car->only(['id', 'no', 'vin', 'car_number', 'client_id', 'total', 'total_s', 'paid']);

        // SoftDeletes trait -> UPDATE ... SET deleted_at = now() (never a real DELETE).
        $car->delete();

        $this->renumberActiveCars();

        Log::info('Car soft-deleted', array_merge($snapshot, [
            'owner_id' => $ownerId,
            'deleted_by' => Auth::id(),
            'deleted_at' => now()->toDateTimeString(),
        ]));
    }

    /**
     * Free a VIN for re-create when only soft-deleted rows still hold it.
     *
     * Global UNIQUE on car.vin (and MySQL without partial unique) still
     * blocks INSERT after DelCar / system reset. Validation already rejects
     * a second *active* car; this purges soft-deleted duplicates for the
     * same owner so the new insert can succeed on both SQLite and MySQL.
     */
    public function releaseSoftDeletedVin(string $vin, int $ownerId): void
    {
        $vin = trim($vin);
        if ($vin === '') {
            return;
        }

        $trashed = Car::onlyTrashed()
            ->where('owner_id', $ownerId)
            ->where('vin', $vin)
            ->get();

        foreach ($trashed as $car) {
            $snapshot = $car->only(['id', 'no', 'vin', 'car_number', 'client_id', 'owner_id']);
            $car->forceDelete();

            Log::info('Soft-deleted car force-deleted to free VIN for reuse', array_merge($snapshot, [
                'released_by' => Auth::id(),
            ]));
        }
    }

    /**
     * Renumber display sequence ("no") for non-deleted cars.
     * PHP-side loop works on both MySQL and SQLite — avoids MySQL-only
     * user variables (SET @row_number / @row_number := …) that SQLite rejects.
     */
    public function renumberActiveCars(): void
    {
        $ids = DB::table('car')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('id');

        $no = 1;
        foreach ($ids as $id) {
            DB::table('car')->where('id', $id)->update(['no' => $no]);
            $no++;
        }
    }
}
