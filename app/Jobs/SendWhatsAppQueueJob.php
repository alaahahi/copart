<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsAppQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $url,
        public array $payload
    ) {
    }

    public function handle(): void
    {
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->asJson()
                ->post($this->url, $this->payload);

            if (! $response->successful()) {
                Log::warning('WhatsAppQueueJob: HTTP failed', [
                    'url' => $this->url,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                    'event' => $this->payload['event'] ?? null,
                    'unique_key' => $this->payload['unique_key'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('WhatsAppQueueJob: exception', [
                'url' => $this->url,
                'message' => $e->getMessage(),
                'event' => $this->payload['event'] ?? null,
            ]);
            // Do not rethrow — never break ERP flows when queue driver is sync.
        }
    }
}
