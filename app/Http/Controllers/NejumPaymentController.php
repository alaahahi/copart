<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNejumPaymentLineRequest;
use App\Http\Requests\UpdateNejumPaymentLineRequest;
use App\Models\NejumPaymentLine;
use App\Services\NejumPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class NejumPaymentController extends Controller
{
    public function __construct(
        protected NejumPaymentService $service
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('NejumPayments/Index');
    }

    public function list(Request $request): JsonResponse
    {
        if (! Schema::hasTable('nejum_payment_lines')) {
            return response()->json([
                'lines' => [],
                'pagination' => ['total' => 0, 'page' => 1, 'last_page' => 1, 'per_page' => 50],
                'summary' => $this->emptySummary(),
                'message' => 'جدول مدفوعات Nejum غير موجود — شغّل php artisan migrate',
            ]);
        }

        $ownerId = (int) Auth::user()->owner_id;
        $filters = $request->only(['from', 'to', 'line_type', 'q', 'page', 'limit']);
        $page = $this->service->list($ownerId, $filters);

        return response()->json([
            'lines' => array_map(fn (NejumPaymentLine $line) => $this->formatLine($line), $page->items()),
            'pagination' => [
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
            ],
            'summary' => $this->service->summary($ownerId, $filters),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        if (! Schema::hasTable('nejum_payment_lines')) {
            return response()->json($this->emptySummary());
        }

        $ownerId = (int) Auth::user()->owner_id;
        $filters = $request->only(['from', 'to', 'line_type', 'q']);

        return response()->json($this->service->summary($ownerId, $filters));
    }

    public function store(StoreNejumPaymentLineRequest $request): JsonResponse
    {
        $line = $this->service->create(
            (int) Auth::user()->owner_id,
            (int) Auth::id(),
            $request->validated()
        );

        return response()->json([
            'message' => 'تم إضافة السطر',
            'line' => $this->formatLine($line->load('car:id,vin,car_number,note')),
        ], 201);
    }

    public function update(UpdateNejumPaymentLineRequest $request, NejumPaymentLine $line): JsonResponse
    {
        $this->assertOwner($line);
        $updated = $this->service->update($line, $request->validated());

        return response()->json([
            'message' => 'تم تحديث السطر',
            'line' => $this->formatLine($updated),
        ]);
    }

    public function destroy(NejumPaymentLine $line): JsonResponse
    {
        $this->assertOwner($line);
        $this->service->delete($line);

        return response()->json(['message' => 'تم حذف السطر']);
    }

    public function lookupCar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vin' => ['nullable', 'string', 'max:64'],
            'lot' => ['nullable', 'string', 'max:64'],
        ]);

        $car = $this->service->lookupCar(
            (int) Auth::user()->owner_id,
            $data['vin'] ?? null,
            $data['lot'] ?? null
        );

        return response()->json([
            'found' => $car !== null,
            'car' => $car,
        ]);
    }

    protected function assertOwner(NejumPaymentLine $line): void
    {
        if ((int) $line->owner_id !== (int) Auth::user()->owner_id) {
            abort(403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatLine(NejumPaymentLine $line): array
    {
        return [
            'id' => $line->id,
            'line_type' => $line->line_type,
            'car_id' => $line->car_id,
            'pr_date' => optional($line->pr_date)?->format('Y-m-d'),
            'car_info' => $line->car_info,
            'vin' => $line->vin,
            'lot' => $line->lot,
            'note' => $line->note,
            'amount' => (float) $line->amount,
            'for_nejum' => (float) $line->for_nejum,
            'nejum_shipping' => (float) $line->nejum_shipping,
            'nejum_payments' => (float) $line->nejum_payments,
            'nejum_payment_date' => optional($line->nejum_payment_date)?->format('Y-m-d'),
            'nejum_note' => $line->nejum_note,
            'transaction_fee' => (float) $line->transaction_fee,
            'commission_3mula' => (float) $line->commission_3mula,
            'kaml_must_pay' => (float) $line->kaml_must_pay,
            'kaml_payment' => (float) $line->kaml_payment,
            'kaml_payment_date' => optional($line->kaml_payment_date)?->format('Y-m-d'),
            'us_fee' => (float) $line->us_fee,
            'customer_commission_3mula' => (float) $line->customer_commission_3mula,
            'customer_must_pay' => (float) $line->customer_must_pay,
            'customer_name' => $line->customer_name,
            'customer_payment_date' => optional($line->customer_payment_date)?->format('Y-m-d'),
            'car' => $line->car ? [
                'id' => $line->car->id,
                'vin' => $line->car->vin,
            ] : null,
        ];
    }

    /**
     * @return array<string, float|int>
     */
    protected function emptySummary(): array
    {
        return [
            'count' => 0,
            'total_amount' => 0,
            'total_for_nejum' => 0,
            'total_nejum_shipping' => 0,
            'total_nejum_payments' => 0,
            'total_transaction_fee' => 0,
            'total_commission_3mula' => 0,
            'total_kaml_must_pay' => 0,
            'total_kaml_payment' => 0,
            'total_us_fee' => 0,
            'total_customer_commission_3mula' => 0,
            'total_customer_must_pay' => 0,
        ];
    }
}
