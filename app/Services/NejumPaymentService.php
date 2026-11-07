<?php

namespace App\Services;

use App\Models\Car;
use App\Models\NejumPaymentLine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class NejumPaymentService
{
    public function list(int $ownerId, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $page = max((int) ($filters['page'] ?? 1), 1);

        $query = NejumPaymentLine::query()
            ->with(['car:id,vin,car_number,note'])
            ->where('owner_id', $ownerId);

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('pr_date')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array{
     *   total_amount: float,
     *   total_for_nejum: float,
     *   total_nejum_shipping: float,
     *   total_nejum_payments: float,
     *   total_transaction_fee: float,
     *   total_commission_3mula: float,
     *   total_kaml_must_pay: float,
     *   total_kaml_payment: float,
     *   total_us_fee: float,
     *   total_customer_commission_3mula: float,
     *   total_customer_must_pay: float,
     *   count: int
     * }
     */
    public function summary(int $ownerId, array $filters = []): array
    {
        $query = NejumPaymentLine::query()->where('owner_id', $ownerId);
        $this->applyFilters($query, $filters);

        $row = $query->selectRaw('
            COUNT(*) as count,
            COALESCE(SUM(amount), 0) as total_amount,
            COALESCE(SUM(for_nejum), 0) as total_for_nejum,
            COALESCE(SUM(nejum_shipping), 0) as total_nejum_shipping,
            COALESCE(SUM(nejum_payments), 0) as total_nejum_payments,
            COALESCE(SUM(transaction_fee), 0) as total_transaction_fee,
            COALESCE(SUM(commission_3mula), 0) as total_commission_3mula,
            COALESCE(SUM(kaml_must_pay), 0) as total_kaml_must_pay,
            COALESCE(SUM(kaml_payment), 0) as total_kaml_payment,
            COALESCE(SUM(us_fee), 0) as total_us_fee,
            COALESCE(SUM(customer_commission_3mula), 0) as total_customer_commission_3mula,
            COALESCE(SUM(customer_must_pay), 0) as total_customer_must_pay
        ')->first();

        return [
            'count' => (int) ($row->count ?? 0),
            'total_amount' => round((float) ($row->total_amount ?? 0), 4),
            'total_for_nejum' => round((float) ($row->total_for_nejum ?? 0), 4),
            'total_nejum_shipping' => round((float) ($row->total_nejum_shipping ?? 0), 4),
            'total_nejum_payments' => round((float) ($row->total_nejum_payments ?? 0), 4),
            'total_transaction_fee' => round((float) ($row->total_transaction_fee ?? 0), 4),
            'total_commission_3mula' => round((float) ($row->total_commission_3mula ?? 0), 4),
            'total_kaml_must_pay' => round((float) ($row->total_kaml_must_pay ?? 0), 4),
            'total_kaml_payment' => round((float) ($row->total_kaml_payment ?? 0), 4),
            'total_us_fee' => round((float) ($row->total_us_fee ?? 0), 4),
            'total_customer_commission_3mula' => round((float) ($row->total_customer_commission_3mula ?? 0), 4),
            'total_customer_must_pay' => round((float) ($row->total_customer_must_pay ?? 0), 4),
        ];
    }

    public function create(int $ownerId, int $userId, array $data): NejumPaymentLine
    {
        $payload = $this->normalizePayload($data);
        $car = $this->resolveCar($ownerId, $payload['vin'] ?? null, $payload['lot'] ?? null);

        if ($car) {
            $payload['car_id'] = $car->id;
            if (empty($payload['car_info'])) {
                $payload['car_info'] = $this->carLabel($car);
            }
            if (empty($payload['vin']) && $car->vin) {
                $payload['vin'] = $car->vin;
            }
        }

        $payload = $this->applyComputedTotals($payload);
        $payload['owner_id'] = $ownerId;
        $payload['created_by'] = $userId;

        return NejumPaymentLine::query()->create($payload);
    }

    public function update(NejumPaymentLine $line, array $data): NejumPaymentLine
    {
        $payload = $this->normalizePayload($data);
        $car = $this->resolveCar((int) $line->owner_id, $payload['vin'] ?? null, $payload['lot'] ?? null);

        if ($car) {
            $payload['car_id'] = $car->id;
            if (empty($payload['car_info'])) {
                $payload['car_info'] = $this->carLabel($car);
            }
        } elseif (array_key_exists('vin', $data) || array_key_exists('lot', $data)) {
            // Explicit clear / unmatched lookup — drop link.
            $payload['car_id'] = null;
        }

        $payload = $this->applyComputedTotals($payload);
        $line->fill($payload);
        $line->save();

        return $line->fresh(['car:id,vin,car_number,note']);
    }

    public function delete(NejumPaymentLine $line): void
    {
        $line->delete();
    }

    /**
     * @return array{id:int,vin:?string,car_info:string,lot:?string}|null
     */
    public function lookupCar(int $ownerId, ?string $vin, ?string $lot): ?array
    {
        $car = $this->resolveCar($ownerId, $vin, $lot);
        if (! $car) {
            return null;
        }

        return [
            'id' => (int) $car->id,
            'vin' => $car->vin,
            'car_info' => $this->carLabel($car),
            'lot' => $lot ?: null,
        ];
    }

    public function resolveCar(int $ownerId, ?string $vin, ?string $lot): ?Car
    {
        $vin = $this->cleanString($vin);
        $lot = $this->cleanString($lot);

        if ($vin === null && $lot === null) {
            return null;
        }

        if (! Schema::hasTable('car')) {
            return null;
        }

        $query = Car::query()->where('owner_id', $ownerId);

        if ($vin !== null) {
            $byVin = (clone $query)->where('vin', $vin)->first();
            if ($byVin) {
                return $byVin;
            }
        }

        // LOT is stored as free text on lines; try car_number / note contains as soft match.
        if ($lot !== null) {
            if (Schema::hasColumn('car', 'car_number')) {
                $byLot = (clone $query)->where('car_number', $lot)->first();
                if ($byLot) {
                    return $byLot;
                }
            }
        }

        return null;
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['from'])) {
            $query->whereDate('pr_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('pr_date', '<=', $filters['to']);
        }
        if (! empty($filters['line_type']) && in_array($filters['line_type'], [NejumPaymentLine::TYPE_CAR, NejumPaymentLine::TYPE_SHIPPING], true)) {
            $query->where('line_type', $filters['line_type']);
        }
        if (! empty($filters['q'])) {
            $q = '%'.trim((string) $filters['q']).'%';
            $query->where(function (Builder $inner) use ($q) {
                $inner->where('vin', 'like', $q)
                    ->orWhere('lot', 'like', $q)
                    ->orWhere('car_info', 'like', $q)
                    ->orWhere('customer_name', 'like', $q)
                    ->orWhere('note', 'like', $q);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizePayload(array $data): array
    {
        $money = [
            'amount', 'for_nejum', 'nejum_shipping', 'nejum_payments',
            'transaction_fee', 'commission_3mula', 'kaml_must_pay', 'kaml_payment',
            'us_fee', 'customer_commission_3mula', 'customer_must_pay',
        ];
        $dates = ['pr_date', 'nejum_payment_date', 'kaml_payment_date', 'customer_payment_date'];
        $strings = ['line_type', 'car_info', 'vin', 'lot', 'note', 'nejum_note', 'customer_name'];

        $out = [];
        foreach ($strings as $key) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $this->cleanString($data[$key]);
            }
        }
        foreach ($dates as $key) {
            if (array_key_exists($key, $data)) {
                $val = $this->cleanString($data[$key]);
                $out[$key] = $val ?: null;
            }
        }
        foreach ($money as $key) {
            if (array_key_exists($key, $data)) {
                $out[$key] = round((float) ($data[$key] ?? 0), 4);
            }
        }

        if (isset($out['line_type']) && ! in_array($out['line_type'], [NejumPaymentLine::TYPE_CAR, NejumPaymentLine::TYPE_SHIPPING], true)) {
            $out['line_type'] = NejumPaymentLine::TYPE_CAR;
        }

        return $out;
    }

    /**
     * Fill must-pay totals when caller left them empty/zero but components exist.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function applyComputedTotals(array $payload): array
    {
        $forNejum = (float) ($payload['for_nejum'] ?? 0);
        $amount = (float) ($payload['amount'] ?? 0);
        if ($forNejum <= 0 && $amount > 0) {
            $forNejum = $amount;
            $payload['for_nejum'] = $forNejum;
        }

        $kamlCommission = (float) ($payload['commission_3mula'] ?? 0);
        $usFee = (float) ($payload['us_fee'] ?? 0);
        $customerCommission = (float) ($payload['customer_commission_3mula'] ?? $kamlCommission);

        if (! isset($payload['customer_commission_3mula'])) {
            $payload['customer_commission_3mula'] = $customerCommission;
        }

        if (! array_key_exists('kaml_must_pay', $payload) || (float) $payload['kaml_must_pay'] <= 0) {
            $payload['kaml_must_pay'] = round($forNejum + $kamlCommission, 4);
        }

        if (! array_key_exists('customer_must_pay', $payload) || (float) $payload['customer_must_pay'] <= 0) {
            $payload['customer_must_pay'] = round($forNejum + $customerCommission + $usFee, 4);
        }

        return $payload;
    }

    protected function carLabel(Car $car): string
    {
        $parts = array_filter([
            $car->note,
            $car->car_number ? '#'.$car->car_number : null,
            $car->vin,
        ]);

        return $parts ? implode(' · ', $parts) : ('Car #'.$car->id);
    }

    protected function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
