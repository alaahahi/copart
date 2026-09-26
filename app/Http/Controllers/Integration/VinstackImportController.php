<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Services\VinstackVehicleImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class VinstackImportController extends Controller
{
    public function storeVehicle(Request $request, VinstackVehicleImportService $import): JsonResponse
    {
        $ownerId = (int) $request->attributes->get('vinstack_owner_id');

        $data = $request->validate([
            'vin' => ['required', 'string', 'max:255'],
            'vinstack_vehicle_id' => ['nullable', 'integer'],
            'vehicle_id' => ['nullable', 'integer'],
            'make' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable'],
            'price' => ['nullable', 'numeric'],
            'color' => ['nullable', 'string', 'max:100'],
            'lot' => ['nullable', 'string', 'max:100'],
            'auction' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'string', 'max:40'],
            'loading_date' => ['nullable', 'string', 'max:40'],
            'eta' => ['nullable', 'string', 'max:40'],
            'loading_point' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'booking_number' => ['nullable', 'string', 'max:100'],
            'container_number' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'keys' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'images' => ['nullable', 'array'],
            'images.*' => ['nullable', 'string', 'max:2000'],
            'raw_data' => ['nullable', 'array'],
            'dealer' => ['required', 'array'],
            'dealer.phone' => ['required', 'string', 'max:40'],
            'dealer.name' => ['nullable', 'string', 'max:255'],
            'dealer.company_name' => ['nullable', 'string', 'max:255'],
            'dealer.email' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $import->queuePending($ownerId, $data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('vinstack vehicle queue failed', [
                'vin' => $data['vin'] ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Queue failed: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'data' => $result,
            'message' => 'تم إرسال السيارة لقائمة موافقة المحاسبة.',
        ], 202);
    }
}
