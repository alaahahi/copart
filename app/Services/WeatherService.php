<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Dashboard weather (Open-Meteo, no API key) with shared Laravel cache.
 */
class WeatherService
{
    public const CACHE_KEY = 'dashboard-weather';

    public const CACHE_TTL_SECONDS = 3600;

    /** Erbil defaults when SystemConfig has no geo fields. */
    public const DEFAULT_LATITUDE = 36.19;

    public const DEFAULT_LONGITUDE = 44.01;

    public const DEFAULT_CITY = 'Erbil';

    /**
     * Current temperature in °C for the ERP dashboard clock card.
     *
     * @return array{temperature: float|null, city: string, unit: string, cached: bool, updated_at: string|null}
     */
    public function currentTemperature(): array
    {
        $city = (string) (env('WEATHER_CITY') ?: self::DEFAULT_CITY);
        $lat = (float) (env('WEATHER_LAT') ?: self::DEFAULT_LATITUDE);
        $lon = (float) (env('WEATHER_LON') ?: self::DEFAULT_LONGITUDE);

        try {
            $payload = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () use ($lat, $lon, $city) {
                return $this->fetchFromOpenMeteo($lat, $lon, $city);
            });

            if (! is_array($payload) || ! array_key_exists('temperature', $payload)) {
                return $this->emptyPayload($city);
            }

            return [
                'temperature' => $payload['temperature'] !== null
                    ? round((float) $payload['temperature'], 1)
                    : null,
                'city' => (string) ($payload['city'] ?? $city),
                'unit' => '°C',
                'cached' => true,
                'updated_at' => $payload['updated_at'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('WeatherService: cache/fetch failed', [
                'message' => $e->getMessage(),
            ]);

            return $this->emptyPayload($city);
        }
    }

    /**
     * @return array{temperature: float|null, city: string, updated_at: string}
     */
    protected function fetchFromOpenMeteo(float $lat, float $lon, string $city): array
    {
        $response = Http::timeout(8)
            ->acceptJson()
            ->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $lat,
                'longitude' => $lon,
                'current' => 'temperature_2m',
                'timezone' => 'Asia/Baghdad',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Open-Meteo HTTP '.$response->status());
        }

        $temp = data_get($response->json(), 'current.temperature_2m');

        if ($temp === null || ! is_numeric($temp)) {
            throw new \RuntimeException('Open-Meteo missing temperature_2m');
        }

        return [
            'temperature' => (float) $temp,
            'city' => $city,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{temperature: null, city: string, unit: string, cached: bool, updated_at: null}
     */
    protected function emptyPayload(string $city): array
    {
        return [
            'temperature' => null,
            'city' => $city,
            'unit' => '°C',
            'cached' => false,
            'updated_at' => null,
        ];
    }
}
