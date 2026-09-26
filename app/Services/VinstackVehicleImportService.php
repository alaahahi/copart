<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Car;
use App\Models\User;
use App\Models\UserType;
use App\Models\VinstackImportRequest;
use App\Support\PhoneDigits;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VinstackVehicleImportService
{
    public function __construct(
        protected ClientAccountService $clientAccounts,
        protected CarService $cars,
    ) {}

    /**
     * Queue an incoming Vinstack vehicle for admin approval (does not create car yet).
     *
     * @param  array<string, mixed>  $payload
     * @return array{queued: bool, import_id: int, status: string, vin: string}
     */
    public function queuePending(int $ownerId, array $payload): array
    {
        $vin = strtoupper(trim((string) ($payload['vin'] ?? '')));

        if ($vin === '') {
            throw new \InvalidArgumentException('VIN is required.');
        }

        $dealer = is_array($payload['dealer'] ?? null) ? $payload['dealer'] : [];
        $phone = PhoneDigits::normalize($dealer['phone'] ?? null);

        if ($phone === null) {
            throw new \InvalidArgumentException('Dealer phone is required to match or create a trader.');
        }

        $existing = VinstackImportRequest::query()
            ->where('owner_id', $ownerId)
            ->where('vin', $vin)
            ->where('status', VinstackImportRequest::STATUS_PENDING)
            ->first();

        $attrs = [
            'owner_id' => $ownerId,
            'vin' => $vin,
            'vinstack_vehicle_id' => $payload['vinstack_vehicle_id'] ?? $payload['vehicle_id'] ?? null,
            'status' => VinstackImportRequest::STATUS_PENDING,
            'payload' => $payload,
            'dealer_phone' => $dealer['phone'] ?? $phone,
            'dealer_name' => $dealer['name'] ?? null,
            'dealer_company' => $dealer['company_name'] ?? null,
            'make' => $payload['make'] ?? null,
            'model' => $payload['model'] ?? null,
            'year' => isset($payload['year']) ? (string) $payload['year'] : null,
            'error_message' => null,
            'reject_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'car_id' => null,
            'client_id' => null,
        ];

        if ($existing) {
            $existing->fill($attrs);
            $existing->save();
            $row = $existing;
        } else {
            $row = VinstackImportRequest::query()->create($attrs);
        }

        return [
            'queued' => true,
            'import_id' => (int) $row->id,
            'status' => VinstackImportRequest::STATUS_PENDING,
            'vin' => $vin,
        ];
    }

    /**
     * @return array{created: bool, car_id: int, client_id: int, vin: string, images: int, import_id: int}
     */
    public function approve(VinstackImportRequest $request, int $reviewerId): array
    {
        if (! $request->isPending()) {
            throw new \InvalidArgumentException('هذا الطلب ليس بانتظار الموافقة.');
        }

        $payload = is_array($request->payload) ? $request->payload : [];
        $result = $this->import((int) $request->owner_id, $payload);

        $request->fill([
            'status' => VinstackImportRequest::STATUS_APPROVED,
            'car_id' => $result['car_id'],
            'client_id' => $result['client_id'],
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'error_message' => null,
            'reject_reason' => null,
        ])->save();

        return [
            ...$result,
            'import_id' => (int) $request->id,
        ];
    }

    public function reject(VinstackImportRequest $request, int $reviewerId, ?string $reason = null): void
    {
        if (! $request->isPending()) {
            throw new \InvalidArgumentException('هذا الطلب ليس بانتظار الموافقة.');
        }

        $request->fill([
            'status' => VinstackImportRequest::STATUS_REJECTED,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'reject_reason' => $reason,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{created: bool, car_id: int, client_id: int, vin: string, images: int}
     */
    public function import(int $ownerId, array $payload): array
    {
        $vin = strtoupper(trim((string) ($payload['vin'] ?? '')));

        if ($vin === '') {
            throw new \InvalidArgumentException('VIN is required.');
        }

        $dealer = is_array($payload['dealer'] ?? null) ? $payload['dealer'] : [];
        $phone = PhoneDigits::normalize($dealer['phone'] ?? null);

        if ($phone === null) {
            throw new \InvalidArgumentException('Dealer phone is required to match or create a trader.');
        }

        $client = $this->findOrCreateClient($ownerId, $dealer, $phone);

        $this->cars->releaseSoftDeletedVin($vin, $ownerId);

        $car = Car::query()
            ->where('owner_id', $ownerId)
            ->where('vin', $vin)
            ->whereNull('deleted_at')
            ->first();

        $attrs = $this->mapCarAttributes($ownerId, $client->id, $payload, $vin);
        $imageNames = $this->downloadImages(
            is_array($payload['images'] ?? null) ? $payload['images'] : [],
            $vin,
        );

        if ($imageNames !== []) {
            $attrs['image'] = json_encode(array_values($imageNames));
        }

        $created = false;

        if ($car) {
            $car->fill($attrs);
            $car->save();
        } else {
            $attrs['no'] = ((int) Car::query()->where('owner_id', $ownerId)->max('no')) + 1;
            $attrs['results'] = 0;
            $attrs['year_date'] = (int) Carbon::now()->format('Y');
            $attrs['profit'] = $this->cars->computeProfit(0, (float) ($attrs['total'] ?? 0));
            $car = Car::query()->create($attrs);
            $created = true;
        }

        return [
            'created' => $created,
            'car_id' => (int) $car->id,
            'client_id' => (int) $client->id,
            'vin' => $vin,
            'images' => count($imageNames),
        ];
    }

    /**
     * @param  array<string, mixed>  $dealer
     */
    protected function findOrCreateClient(int $ownerId, array $dealer, string $phoneDigits): User
    {
        $clientTypeId = (int) UserType::query()->where('name', 'client')->value('id');

        if ($clientTypeId <= 0) {
            throw new \RuntimeException('Client user type is missing.');
        }

        $candidates = User::query()
            ->where('owner_id', $ownerId)
            ->where('type_id', $clientTypeId)
            ->whereNotNull('phone')
            ->get(['id', 'name', 'phone', 'owner_id', 'type_id', 'show_in_dashboard']);

        foreach ($candidates as $candidate) {
            if (PhoneDigits::equals($candidate->phone, $phoneDigits)) {
                return $candidate;
            }
        }

        $baseName = trim((string) ($dealer['company_name'] ?? $dealer['name'] ?? ''));
        if ($baseName === '') {
            $baseName = 'تاجر '.$phoneDigits;
        }

        $name = $this->uniqueClientName($baseName, $ownerId);

        $client = new User();
        $client->name = $name;
        $client->phone = $dealer['phone'] ?? $phoneDigits;
        $client->created = Carbon::now()->format('Y-m-d');
        $client->type_id = $clientTypeId;
        $client->owner_id = $ownerId;
        $client->year_date = Carbon::now()->format('Y');
        $client->show_in_dashboard = false;
        $client->save();

        $this->clientAccounts->provisionForClient($client);

        return $client;
    }

    protected function uniqueClientName(string $baseName, int $ownerId): string
    {
        $name = $baseName;
        $i = 0;

        while (
            User::query()
                ->where('name', $name)
                ->exists()
        ) {
            $i++;
            $name = $baseName.' ('.$i.')';
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function mapCarAttributes(int $ownerId, int $clientId, array $payload, string $vin): array
    {
        $raw = is_array($payload['raw_data'] ?? null) ? $payload['raw_data'] : [];

        $make = trim((string) ($payload['make'] ?? $raw['make'] ?? $raw['manufacturer'] ?? ''));
        $model = trim((string) ($payload['model'] ?? $raw['model'] ?? ''));
        $year = $payload['year'] ?? $raw['year'] ?? null;
        $carType = trim($make.' '.$model);
        if ($carType === '') {
            $carType = (string) ($raw['car_type'] ?? $raw['vehicle_type'] ?? 'Vehicle');
        }

        $lot = $payload['lot'] ?? $raw['lot'] ?? $raw['lot_number'] ?? $payload['car_number'] ?? null;
        $color = $payload['color'] ?? $raw['color'] ?? $raw['car_color'] ?? null;
        $date = $this->normalizeDate(
            $payload['purchase_date'] ?? $raw['purchase_date'] ?? $raw['invoice_date'] ?? $payload['date'] ?? null
        );

        $checkout = $this->moneyInt($payload['price'] ?? $raw['price'] ?? $raw['checkout'] ?? 0);
        $shipping = $this->moneyInt($raw['shipping_dolar'] ?? $payload['shipping_dolar'] ?? 0);
        $coc = $this->moneyInt($raw['coc_dolar'] ?? $payload['coc_dolar'] ?? 0);
        $expenses = $this->moneyInt($raw['expenses'] ?? $payload['expenses'] ?? 0);
        $dolarPrice = (int) ($raw['dolar_price'] ?? $payload['dolar_price'] ?? 1);
        if ($dolarPrice <= 0) {
            $dolarPrice = 1;
        }

        $total = $checkout + $shipping + $coc + $expenses;

        $auctionName = $payload['auction'] ?? $raw['auction'] ?? $raw['auction_name'] ?? null;
        $auctionId = $this->resolveOrCreateAuction($ownerId, is_string($auctionName) ? $auctionName : null);

        $noteParts = [];
        foreach ([
            'loading_point' => $payload['loading_point'] ?? $raw['loading_point'] ?? $raw['loading_port'] ?? null,
            'destination' => $payload['destination'] ?? $raw['destination'] ?? $raw['destination_port'] ?? null,
            'eta' => $payload['eta'] ?? $raw['eta'] ?? $raw['eta_date'] ?? null,
            'loading_date' => $payload['loading_date'] ?? $raw['loading_date'] ?? $raw['picked_up_date'] ?? null,
            'booking' => $payload['booking_number'] ?? $raw['booking_number'] ?? $raw['booking_id'] ?? null,
            'container' => $payload['container_number'] ?? $raw['container_number'] ?? $raw['container_id'] ?? null,
            'status' => $payload['status'] ?? $raw['status'] ?? null,
            'keys' => $payload['keys'] ?? $raw['keys'] ?? null,
            'fuel' => $raw['fuel_type'] ?? null,
            'sea_line' => $raw['sea_line'] ?? null,
        ] as $label => $value) {
            if ($value !== null && $value !== '') {
                $noteParts[] = $label.': '.$value;
            }
        }

        $incomingNote = trim((string) ($payload['notes'] ?? $raw['notes'] ?? ''));
        if ($incomingNote !== '') {
            $noteParts[] = $incomingNote;
        }

        $vinstackId = $payload['vinstack_vehicle_id'] ?? $payload['vehicle_id'] ?? null;

        return [
            'vin' => $vin,
            'client_id' => $clientId,
            'owner_id' => $ownerId,
            'car_type' => $carType,
            'car_number' => $lot !== null ? (string) $lot : null,
            'car_color' => $color !== null ? (string) $color : null,
            'year' => $year !== null ? (string) $year : null,
            'date' => $date,
            'checkout' => $checkout,
            'shipping_dolar' => $shipping,
            'coc_dolar' => $coc,
            'expenses' => $expenses,
            'dolar_price' => $dolarPrice,
            'total' => $total,
            'auction_id' => $auctionId,
            'note' => implode("\n", $noteParts),
            'vinstack_vehicle_id' => $vinstackId !== null ? (int) $vinstackId : null,
        ];
    }

    protected function resolveOrCreateAuction(int $ownerId, ?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        $existing = Auction::query()
            ->where('owner_id', $ownerId)
            ->where('name', $name)
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $auction = Auction::query()->create([
            'owner_id' => $ownerId,
            'name' => $name,
        ]);

        return (int) $auction->id;
    }

    /**
     * @param  list<mixed>  $urls
     * @return list<string>
     */
    protected function downloadImages(array $urls, string $vin): array
    {
        $max = max(0, (int) config('vinstack_integration.max_images', 15));
        $dir = public_path('storage/car');

        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $saved = [];
        $i = 0;

        foreach ($urls as $url) {
            if ($i >= $max) {
                break;
            }

            if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            try {
                $response = Http::timeout(45)->withOptions(['allow_redirects' => true])->get($url);

                if (! $response->successful()) {
                    continue;
                }

                $body = $response->body();
                if ($body === '' || strlen($body) < 100) {
                    continue;
                }

                $ext = $this->guessExtension($url, $response->header('Content-Type'));
                $filename = Str::slug(substr($vin, -8)).'_'.($i + 1).'_'.Str::random(6).'.'.$ext;
                $path = $dir.DIRECTORY_SEPARATOR.$filename;

                if (file_put_contents($path, $body) === false) {
                    continue;
                }

                $saved[] = $filename;
                $i++;
            } catch (\Throwable $e) {
                Log::warning('vinstack import image download failed', [
                    'url' => $url,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $saved;
    }

    protected function guessExtension(string $url, ?string $contentType): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }

        $contentType = strtolower((string) $contentType);

        return match (true) {
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'gif') => 'gif',
            default => 'jpg',
        };
    }

    protected function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function moneyInt(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) round((float) $value);
    }
}
