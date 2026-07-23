<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteAuctionRequest;
use App\Http\Requests\StoreAuctionRequest;
use App\Models\Auction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

/**
 * Manages the tenant's auction houses list (Copart, IAAI, Manheim, ...).
 * Used by:
 *  - Settings > المزادات: chip UI to add/remove auction houses.
 *  - Car add/edit forms: المزاد select is populated from this list.
 */
class AuctionController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = Auth::user()->owner_id;

        $auctions = Auction::where('owner_id', $ownerId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Response::json($auctions, 200);
    }

    public function store(StoreAuctionRequest $request)
    {
        $validated = $request->validated();

        $auction = Auction::create([
            'owner_id' => Auth::user()->owner_id,
            'name' => trim($validated['name']),
        ]);

        return Response::json($auction, 201);
    }

    public function destroy(DeleteAuctionRequest $request)
    {
        $validated = $request->validated();

        $auction = Auction::where('id', $validated['id'])
            ->where('owner_id', Auth::user()->owner_id)
            ->first();

        if (!$auction) {
            return Response::json(['message' => 'المزاد غير موجود.'], 404);
        }

        // Cars referencing this auction fall back to "no auction" via the
        // auction_id FK's ON DELETE SET NULL — no manual update needed and
        // no car/accounting history is ever touched.
        $auction->delete();

        return Response::json(['message' => 'تم حذف المزاد.'], 200);
    }
}
