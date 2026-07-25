<?php

namespace App\Services;

use App\Jobs\SendWhatsAppQueueJob;
use App\Models\Car;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Bridge to WA Queue API: POST {base}/{tenant}/api/v1/queue
 *
 * Never throws into callers — failures are logged; business TX stays intact.
 */
class WhatsAppQueueService
{
    public const EVENT_DEBT = 'debt_notice';

    public const EVENT_CAR_CREATED = 'car_created';

    public const EVENT_PAYMENT = 'payment_received';

    public const SOURCES = [
        'contracts',
        'crm',
        'sales',
        'invoices',
        'support',
        'marketing',
        'appointments',
    ];

    /** Toggle column on system_config for each event. */
    protected const EVENT_TOGGLES = [
        self::EVENT_DEBT => 'wa_notify_debt',
        self::EVENT_CAR_CREATED => 'wa_notify_car_created',
        self::EVENT_PAYMENT => 'wa_notify_payment',
    ];

    public function config(): ?SystemConfig
    {
        return SystemConfig::query()->first();
    }

    public function isEnabled(?SystemConfig $config = null): bool
    {
        $config ??= $this->config();

        return $config
            && (bool) $config->wa_enabled
            && filled($config->wa_tenant)
            && filled($config->wa_base_host);
    }

    public function shouldSend(string $event, ?SystemConfig $config = null): bool
    {
        $config ??= $this->config();

        if (! $this->isEnabled($config)) {
            return false;
        }

        $column = self::EVENT_TOGGLES[$event] ?? null;
        if (! $column) {
            return false;
        }

        return (bool) $config->{$column};
    }

    /**
     * Build POST URL: {base}/{tenant}/api/v1/queue
     */
    public function queueUrl(?SystemConfig $config = null): ?string
    {
        $config ??= $this->config();
        if (! $config || ! filled($config->wa_tenant) || ! filled($config->wa_base_host)) {
            return null;
        }

        $base = rtrim((string) $config->wa_base_host, '/');
        $tenant = trim((string) $config->wa_tenant, '/');

        return "{$base}/{$tenant}/api/v1/queue";
    }

    /**
     * Normalize Iraqi / local numbers to +964…
     */
    public function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $raw = trim($phone);
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        // 00964… → 964…
        if (str_starts_with($digits, '00964')) {
            $digits = substr($digits, 2);
        }

        // Leading 0 (local Iraq): 07xxxxxxxx → 9647xxxxxxxx
        if (str_starts_with($digits, '0')) {
            $digits = '964' . substr($digits, 1);
        }

        // Bare Iraqi mobile 7xxxxxxxx
        if (strlen($digits) === 10 && str_starts_with($digits, '7')) {
            $digits = '964' . $digits;
        }

        if (! str_starts_with($digits, '964') && strlen($digits) < 11) {
            $digits = '964' . ltrim($digits, '0');
        }

        return '+' . $digits;
    }

    /**
     * Queue a message asynchronously (sync driver runs inline). Safe for callers.
     *
     * @param  array<string, mixed>  $extra  recipient_name, unique_key, priority, …
     */
    public function enqueue(string $event, string $phone, string $message, array $extra = []): bool
    {
        try {
            if (! $this->shouldSend($event)) {
                return false;
            }

            $normalized = $this->normalizePhone($phone);
            if (! $normalized) {
                Log::info('WhatsAppQueue: skip — invalid phone', ['event' => $event]);

                return false;
            }

            $message = mb_substr(trim($message), 0, 4096);
            if ($message === '') {
                return false;
            }

            $config = $this->config();
            $url = $this->queueUrl($config);
            if (! $url) {
                return false;
            }

            $source = (string) ($config->wa_source ?: 'sales');
            if (! in_array($source, self::SOURCES, true)) {
                $source = 'sales';
            }

            $payload = array_filter([
                'phone' => $normalized,
                'message' => $message,
                'source' => $source,
                'event' => $event,
                'created_by' => (string) ($config->wa_created_by ?: 'copart-erp'),
                'recipient_name' => $extra['recipient_name'] ?? null,
                'priority' => isset($extra['priority']) ? (int) $extra['priority'] : 5,
                'unique_key' => $extra['unique_key'] ?? null,
                'scheduled_at' => $extra['scheduled_at'] ?? null,
                'max_retry' => $extra['max_retry'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            SendWhatsAppQueueJob::dispatch($url, $payload);

            return true;
        } catch (\Throwable $e) {
            Log::warning('WhatsAppQueue: enqueue failed', [
                'event' => $event,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function notifyDebt(User $client, ?float $balance = null): bool
    {
        $appName = config('app.name', 'ERP');
        $amount = $balance !== null
            ? number_format(abs($balance), 2)
            : '';

        $message = "السلام عليكم: {$appName} - أربيل، يرجى الأخذ بالعلم تسديد المبلغ المستحق عليكم"
            . ($amount !== '' ? " ({$amount} \$)" : '')
            . ' في أقرب وقت ممكن. شكراً لتعاونكم.';

        return $this->enqueue(self::EVENT_DEBT, (string) $client->phone, $message, [
            'recipient_name' => $client->name,
            'unique_key' => 'debt_notice:' . $client->id . ':' . now()->format('Y-m-d'),
            'priority' => 7,
        ]);
    }

    public function notifyCarCreated(Car $car): bool
    {
        try {
            $car->loadMissing('Client');
            $client = $car->Client;
            if (! $client || ! $client->phone) {
                return false;
            }

            $vin = $car->vin ?: $car->car_number ?: $car->id;
            $type = $car->car_type ?: '';
            $message = "تم إضافة سيارة جديدة لحسابكم"
                . ($type !== '' ? " ({$type})" : '')
                . " — رقم الشاصي: {$vin}.";

            return $this->enqueue(self::EVENT_CAR_CREATED, (string) $client->phone, $message, [
                'recipient_name' => $client->name,
                'unique_key' => 'car_created:' . $car->id,
                'priority' => 5,
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsAppQueue: notifyCarCreated failed', [
                'car_id' => $car->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function notifyPayment(
        User $client,
        float $amount,
        string $currency = '$',
        ?int $transactionId = null,
        ?string $note = null
    ): bool {
        try {
            if (! $client->phone) {
                return false;
            }

            $formatted = number_format(abs($amount), 2);
            $message = "تم استلام دفعة بمبلغ {$formatted} {$currency}"
                . ($note ? " — {$note}" : '')
                . '. شكراً لكم.';

            $unique = $transactionId
                ? 'payment_received:' . $transactionId
                : 'payment_received:' . $client->id . ':' . now()->format('Y-m-d-His');

            return $this->enqueue(self::EVENT_PAYMENT, (string) $client->phone, $message, [
                'recipient_name' => $client->name,
                'unique_key' => $unique,
                'priority' => 6,
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsAppQueue: notifyPayment failed', [
                'client_id' => $client->id ?? null,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
