<?php

namespace App\Http\Controllers;

use App\Exports\ExportTraderProfits;
use App\Http\Requests\AnalyticsFilterRequest;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class AnalyticsController extends Controller
{
    protected function authorizeAnalytics(): void
    {
        if (!Auth::check() || !in_array((int) Auth::user()->type_id, [1, 6], true)) {
            abort(403, 'غير مسموح');
        }
    }

    public function index()
    {
        $this->authorizeAnalytics();

        return Inertia::render('Analytics/Index');
    }

    public function dashboard(AnalyticsFilterRequest $request, AnalyticsService $analytics)
    {
        $ownerId = (int) Auth::user()->owner_id;
        $filters = $request->validated();

        $data = $analytics->dashboard($ownerId, $filters);

        return Response::json(['data' => $data], 200);
    }

    public function exportTraders(AnalyticsFilterRequest $request, AnalyticsService $analytics)
    {
        $ownerId = (int) Auth::user()->owner_id;
        $filters = $request->validated();
        $payload = $analytics->dashboard($ownerId, $filters);
        $rows = $payload['trader_profits'] ?? [];

        $filename = 'trader-profits-' . ($filters['from'] ?? 'from') . '-' . ($filters['to'] ?? 'to') . '.xlsx';

        return Excel::download(new ExportTraderProfits($rows), $filename);
    }
}
