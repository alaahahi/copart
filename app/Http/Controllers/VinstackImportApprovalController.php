<?php

namespace App\Http\Controllers;

use App\Models\VinstackImportRequest;
use App\Services\VinstackVehicleImportService;
use App\Support\VinstackIntegrationOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class VinstackImportApprovalController extends Controller
{
    public function page(): Response
    {
        return Inertia::render('VinstackImports/Index');
    }

    public function index(Request $request): JsonResponse
    {
        $ownerId = (int) (Auth::user()->owner_id ?: VinstackIntegrationOwner::resolve());
        $status = $request->string('status')->toString() ?: VinstackImportRequest::STATUS_PENDING;

        $query = VinstackImportRequest::query()
            ->where('owner_id', $ownerId)
            ->orderByDesc('id');

        if (in_array($status, [
            VinstackImportRequest::STATUS_PENDING,
            VinstackImportRequest::STATUS_APPROVED,
            VinstackImportRequest::STATUS_REJECTED,
        ], true)) {
            $query->where('status', $status);
        }

        $rows = $query->limit(200)->get()->map(fn (VinstackImportRequest $row) => [
            'id' => $row->id,
            'vin' => $row->vin,
            'status' => $row->status,
            'make' => $row->make,
            'model' => $row->model,
            'year' => $row->year,
            'dealer_phone' => $row->dealer_phone,
            'dealer_name' => $row->dealer_name,
            'dealer_company' => $row->dealer_company,
            'car_id' => $row->car_id,
            'vinstack_vehicle_id' => $row->vinstack_vehicle_id,
            'reject_reason' => $row->reject_reason,
            'error_message' => $row->error_message,
            'created_at' => optional($row->created_at)?->toDateTimeString(),
            'reviewed_at' => optional($row->reviewed_at)?->toDateTimeString(),
            'payload_summary' => [
                'lot' => $row->payload['lot'] ?? null,
                'auction' => $row->payload['auction'] ?? null,
                'eta' => $row->payload['eta'] ?? null,
                'destination' => $row->payload['destination'] ?? null,
                'images_count' => is_array($row->payload['images'] ?? null)
                    ? count($row->payload['images'])
                    : 0,
            ],
        ]);

        $pendingCount = VinstackImportRequest::query()
            ->where('owner_id', $ownerId)
            ->where('status', VinstackImportRequest::STATUS_PENDING)
            ->count();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'pending_count' => $pendingCount,
                'status' => $status,
            ],
        ]);
    }

    public function approve(Request $request, VinstackImportRequest $import, VinstackVehicleImportService $service): JsonResponse
    {
        $this->assertOwner($import);

        try {
            $result = $service->approve($import, (int) Auth::id());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('vinstack import approve failed', [
                'import_id' => $import->id,
                'message' => $e->getMessage(),
            ]);

            $import->forceFill([
                'error_message' => $e->getMessage(),
            ])->save();

            return response()->json([
                'message' => 'فشلت الموافقة: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'تمت الموافقة وإدخال السيارة للمخزون.',
            'data' => $result,
        ]);
    }

    public function reject(Request $request, VinstackImportRequest $import, VinstackVehicleImportService $service): JsonResponse
    {
        $this->assertOwner($import);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $service->reject($import, (int) Auth::id(), $data['reason'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => 'تم رفض الطلب.',
        ]);
    }

    protected function assertOwner(VinstackImportRequest $import): void
    {
        $ownerId = (int) (Auth::user()->owner_id ?: VinstackIntegrationOwner::resolve());

        if ((int) $import->owner_id !== $ownerId) {
            abort(403);
        }
    }
}
