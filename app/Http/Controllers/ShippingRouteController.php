<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteShippingRouteRequest;
use App\Http\Requests\StoreShippingRouteRequest;
use App\Models\ShippingRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

/**
 * Manages the tenant's shipping routes list (طريق الشحن).
 * Used by:
 *  - Settings > طرق الشحن: chip UI to add/remove routes.
 *  - Car add/edit forms: طريق الشحن select is populated from this list.
 */
class ShippingRouteController extends Controller
{
    public function index(Request $request)
    {
        $ownerId = Auth::user()->owner_id;

        $routes = ShippingRoute::where('owner_id', $ownerId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Response::json($routes, 200);
    }

    public function store(StoreShippingRouteRequest $request)
    {
        $validated = $request->validated();

        $route = ShippingRoute::create([
            'owner_id' => Auth::user()->owner_id,
            'name' => trim($validated['name']),
        ]);

        return Response::json($route, 201);
    }

    public function destroy(DeleteShippingRouteRequest $request)
    {
        $validated = $request->validated();

        $route = ShippingRoute::where('id', $validated['id'])
            ->where('owner_id', Auth::user()->owner_id)
            ->first();

        if (!$route) {
            return Response::json(['message' => 'طريق الشحن غير موجود.'], 404);
        }

        // Cars referencing this route fall back to "no route" via the
        // shipping_route_id FK's ON DELETE SET NULL — no manual update needed.
        $route->delete();

        return Response::json(['message' => 'تم حذف طريق الشحن.'], 200);
    }
}
