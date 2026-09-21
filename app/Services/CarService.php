<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Car;
use App\Models\ShippingRoute;
use App\Models\Transactions;
use App\Services\LedgerService;
use App\Services\SystemWalletService;
use App\Services\VaultService;
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
     * Validate that a frontend-supplied shipping route id belongs to this
     * tenant before it is persisted on a car — never trust frontend.
     * Returns null when empty or foreign so the field stays optional.
     */
    public function resolveShippingRouteId(int $ownerId, $shippingRouteId): ?int
    {
        if (!$shippingRouteId) {
            return null;
        }

        return ShippingRoute::where('id', $shippingRouteId)
            ->where('owner_id', $ownerId)
            ->value('id');
    }

    /**
     * Sales remaining owed by client:
     * total_s − paid − discount − damage_compensation
     */
    public function remainingBalance(float $totalS, float $paid, float $discount = 0, float $damageCompensation = 0): float
    {
        return round($totalS - $paid - $discount - $damageCompensation, 2);
    }

    /**
     * Car payment color flag used in tables:
     * 0 = unpaid (default), 1 = partial (red), 2 = fully paid (green).
     *
     * remaining = total_s - paid - discount - damage_compensation
     * When remaining <= 0 and something was paid/discounted/compensated → green (2).
     */
    public function resolveResultsStatus(
        float $totalS,
        float $paid,
        float $discount,
        float $damageCompensation = 0
    ): int {
        $remaining = $this->remainingBalance($totalS, $paid, $discount, $damageCompensation);
        $settledWithoutCash = $paid + $discount + $damageCompensation;

        if ($settledWithoutCash <= 0) {
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
     * accounting reversal in a single DB::transaction so the whole
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
     * Purchase cost / expense recording is operational on the car row only.
     * It must not post cash-box journals (حركة صندوق) — those throw the trial
     * balance when later reversed via increaseWallet (Dr Cash / Cr Revenue).
     */
    public function shouldPostPurchaseCash(): bool
    {
        return false;
    }

    /**
     * Void non-payment journals tied to this car (purchase cost, expense
     * adjustments, unpaid AR recognition). Client cash payments (is_pay=1)
     * stay — delete is already blocked when paid > 0.
     *
     * Soft-voids journals so the trial balance and الصندوق return to the
     * pre-car state without posting a new cash receipt.
     */
    public function voidNonPaymentAccounting(Car $car, LedgerService $ledger): void
    {
        $reason = 'حذف سيارة #'.$car->id;
        $carMorphs = [Car::class, 'App\\Models\\Car', 'App\Models\Car'];

        $txs = Transactions::query()
            ->whereIn('morphed_type', $carMorphs)
            ->where('morphed_id', $car->id)
            ->where(function ($q) {
                $q->whereNull('is_pay')->orWhere('is_pay', 0);
            })
            ->get();

        foreach ($txs as $tx) {
            $ledger->voidJournalForTransaction($tx, $reason);
            $tx->delete();
            Log::info('Car non-payment transaction voided on delete', [
                'car_id' => $car->id,
                'transaction_id' => $tx->id,
                'deleted_by' => Auth::id(),
            ]);
        }

        \App\Models\JournalEntry::query()
            ->whereIn('reference_type', $carMorphs)
            ->where('reference_id', $car->id)
            ->get()
            ->each(function ($entry) use ($ledger, $reason) {
                $ledger->voidJournalEntry((int) $entry->id, $reason);
            });

        $this->syncPartiesAfterCarVoid($car, $ledger);
    }

    protected function syncPartiesAfterCarVoid(Car $car, LedgerService $ledger): void
    {
        $ownerId = (int) $car->owner_id;
        $userIds = [];
        if ((int) ($car->client_id ?? 0) > 0) {
            $userIds[] = (int) $car->client_id;
        }

        try {
            $vaults = app(VaultService::class);
            $userIds[] = $vaults->purchasesCashUserId($ownerId);
        } catch (\Throwable $e) {
            // purchases vault optional when no cash was posted
        }
        try {
            $userIds[] = app(VaultService::class)->receiptsCashUserId($ownerId);
        } catch (\Throwable $e) {
            //
        }
        try {
            $userIds[] = (int) app(SystemWalletService::class)->requireMainBox($ownerId)->id;
        } catch (\Throwable $e) {
            //
        }

        foreach (array_unique(array_filter($userIds)) as $uid) {
            try {
                $ledger->syncWalletFromLedger($ownerId, (int) $uid);
            } catch (\Throwable $e) {
                Log::warning('Car delete wallet sync skipped', [
                    'car_id' => $car->id,
                    'user_id' => $uid,
                    'message' => $e->getMessage(),
                ]);
            }
        }
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
