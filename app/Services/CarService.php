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

        DB::statement('SET @row_number = 0');
        DB::table('car')
            ->whereNull('deleted_at') // exclude soft-deleted cars from the sequence
            ->orderBy('id')
            ->update(['no' => DB::raw('(@row_number:=@row_number + 1)')]);

        Log::info('Car soft-deleted', array_merge($snapshot, [
            'owner_id' => $ownerId,
            'deleted_by' => Auth::id(),
            'deleted_at' => now()->toDateTimeString(),
        ]));
    }
}
