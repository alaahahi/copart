<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Dashboard USD↔IQD and CAD↔USD rates from Qamar Al Fajr (قمر الفجر).
 *
 * Source: https://qamaralfajr.com/production/exchange_rates.php
 * No public JSON API was found — rates are scraped from server-rendered HTML.
 * Fragility: parsing depends on button elements + currency labels (IQD / CAD / کەنەدی).
 * Do NOT use Cloudflare RUM (/cdn-cgi/rum) — that is an analytics beacon, not rates.
 *
 * Board convention (observed):
 * - IQD row: IQD per 1 USD (sell/buy).
 * - CAD/EUR/GBP-style rows: USD per 100 foreign units (e.g. CAD 71 ⇒ 0.71 USD per CAD).
 *   Dashboard Canada section shows CAD↔USD only (no CAD→IQD conversion).
 */
class ExchangeRateService
{
    public const CACHE_KEY = 'dashboard-exchange-rates-v3';

    public const LAST_GOOD_CACHE_KEY = 'dashboard-exchange-rates-last-good-v3';

    public const CACHE_TTL_SECONDS = 3600;

    public const SOURCE_URL = 'https://qamaralfajr.com/production/exchange_rates.php';

    public const SOURCE_NAME = 'قمر الفجر';

    /**
     * USD↔IQD and CAD↔USD sell/buy for the ERP dashboard card.
     *
     * @return array{
     *   usd_to_iqd_sell: float|null,
     *   usd_to_iqd_buy: float|null,
     *   iqd_to_usd_sell: float|null,
     *   iqd_to_usd_buy: float|null,
     *   cad_quote_sell: float|null,
     *   cad_quote_buy: float|null,
     *   cad_to_usd_sell: float|null,
     *   cad_to_usd_buy: float|null,
     *   usd_to_cad_sell: float|null,
     *   usd_to_cad_buy: float|null,
     *   cad_available: bool,
     *   cad_note: string|null,
     *   source: string,
     *   source_url: string,
     *   cached: bool,
     *   stale: bool,
     *   updated_at: string|null
     * }
     */
    public function usdIqdRates(): array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached) && $this->isValidPayload($cached)) {
                return $this->present($cached, cached: true, stale: false);
            }

            $fresh = $this->fetchAndParse();
            Cache::put(self::CACHE_KEY, $fresh, self::CACHE_TTL_SECONDS);
            Cache::forever(self::LAST_GOOD_CACHE_KEY, $fresh);

            return $this->present($fresh, cached: true, stale: false);
        } catch (\Throwable $e) {
            Log::warning('ExchangeRateService: fetch/parse failed', [
                'message' => $e->getMessage(),
            ]);

            $lastGood = Cache::get(self::LAST_GOOD_CACHE_KEY);
            if (is_array($lastGood) && $this->isValidPayload($lastGood)) {
                return $this->present($lastGood, cached: true, stale: true);
            }

            return $this->emptyPayload();
        }
    }

    /**
     * @return array{
     *   usd_to_iqd_sell: float,
     *   usd_to_iqd_buy: float,
     *   cad_quote_sell: float|null,
     *   cad_quote_buy: float|null,
     *   updated_at: string
     * }
     */
    protected function fetchAndParse(): array
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'User-Agent' => 'CopartERP-Dashboard/1.0',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
            ->get(self::SOURCE_URL);

        if (! $response->successful()) {
            throw new \RuntimeException('Qamar Al Fajr HTTP '.$response->status());
        }

        $html = (string) $response->body();
        if ($html === '') {
            throw new \RuntimeException('Qamar Al Fajr empty body');
        }

        $iqd = $this->parseCurrencyRow($html, ['IQD']);
        if ($iqd === null) {
            throw new \RuntimeException('Qamar Al Fajr IQD row not found (HTML scrape fragile)');
        }

        $sell = $iqd['sell'];
        $buy = $iqd['buy'];

        // Sanity: USD/IQD market rates are typically 100k–200k IQD per USD.
        if ($sell < 50000 || $sell > 500000 || $buy < 50000 || $buy > 500000) {
            throw new \RuntimeException('Qamar Al Fajr IQD rates out of expected range');
        }

        $cad = $this->parseCurrencyRow($html, ['CAD', 'Canadian', 'کەنەدی', 'كندي', 'كندى']);
        $cadSell = $cad['sell'] ?? null;
        $cadBuy = $cad['buy'] ?? null;

        // CAD board quotes are typically ~50–120 (USD per 100 CAD).
        if ($cadSell !== null && ($cadSell < 20 || $cadSell > 200 || $cadBuy < 20 || $cadBuy > 200)) {
            Log::warning('ExchangeRateService: CAD quotes out of expected range, dropping CAD', [
                'cad_sell' => $cadSell,
                'cad_buy' => $cadBuy,
            ]);
            $cadSell = null;
            $cadBuy = null;
        }

        return [
            'usd_to_iqd_sell' => $sell,
            'usd_to_iqd_buy' => $buy,
            'cad_quote_sell' => $cadSell,
            'cad_quote_buy' => $cadBuy,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Matching currency row: SELL button, BUY button, then label in the same <tr>.
     * Columns on the page: SELL | BUY | flag | CURRENCY
     *
     * @param  list<string>  $labels
     * @return array{sell: float, buy: float}|null
     */
    protected function parseCurrencyRow(string $html, array $labels): ?array
    {
        $parts = [];
        foreach ($labels as $label) {
            $quoted = preg_quote($label, '/');
            // Word-boundary for Latin codes (CAD, IQD); plain match for Arabic/Kurdish.
            $parts[] = preg_match('/^[A-Za-z]+$/', $label) === 1
                ? '\b'.$quoted.'\b'
                : $quoted;
        }

        $labelPattern = implode('|', $parts);
        if ($labelPattern === '') {
            return null;
        }

        // Find the label first, then read sell/buy buttons from that same table row
        // (avoids matching an earlier row when CAD appears later on the page).
        if (! preg_match('/(?:'.$labelPattern.')/iu', $html, $labelMatch, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $labelPos = (int) $labelMatch[0][1];
        $before = substr($html, 0, $labelPos);
        $trStart = strrpos($before, '<tr');
        if ($trStart === false) {
            return null;
        }

        $rowHtml = substr($html, $trStart, ($labelPos - $trStart) + strlen($labelMatch[0][0]) + 20);

        if (! preg_match_all('/<button[^>]*>\s*([\d.,]+)\s*<\/button>/iu', $rowHtml, $buttons)
            || count($buttons[1]) < 2) {
            return null;
        }

        $sell = $this->parseRateNumber($buttons[1][0]);
        $buy = $this->parseRateNumber($buttons[1][1]);

        if ($sell === null || $buy === null || $sell <= 0 || $buy <= 0) {
            return null;
        }

        return ['sell' => $sell, 'buy' => $buy];
    }

    protected function parseRateNumber(string $raw): ?float
    {
        $normalized = str_replace([',', ' ', "\xc2\xa0"], '', trim($raw));
        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * @param  array{
     *   usd_to_iqd_sell: float,
     *   usd_to_iqd_buy: float,
     *   cad_quote_sell?: float|null,
     *   cad_quote_buy?: float|null,
     *   updated_at?: string
     * }  $payload
     * @return array<string, mixed>
     */
    protected function present(array $payload, bool $cached, bool $stale): array
    {
        $sell = (float) $payload['usd_to_iqd_sell'];
        $buy = (float) $payload['usd_to_iqd_buy'];

        $cadQuoteSell = isset($payload['cad_quote_sell']) && is_numeric($payload['cad_quote_sell'])
            ? (float) $payload['cad_quote_sell']
            : null;
        $cadQuoteBuy = isset($payload['cad_quote_buy']) && is_numeric($payload['cad_quote_buy'])
            ? (float) $payload['cad_quote_buy']
            : null;

        $cadAvailable = $cadQuoteSell !== null && $cadQuoteBuy !== null
            && $cadQuoteSell > 0 && $cadQuoteBuy > 0;

        $cadToUsdSell = null;
        $cadToUsdBuy = null;
        $usdToCadSell = null;
        $usdToCadBuy = null;

        if ($cadAvailable) {
            // Board: CAD quote = USD per 100 CAD → USD per 1 CAD / CAD per 1 USD.
            $cadToUsdSell = round($cadQuoteSell / 100, 4);
            $cadToUsdBuy = round($cadQuoteBuy / 100, 4);
            $usdToCadSell = $cadQuoteSell > 0 ? round(100 / $cadQuoteSell, 4) : null;
            $usdToCadBuy = $cadQuoteBuy > 0 ? round(100 / $cadQuoteBuy, 4) : null;
        }

        return [
            'usd_to_iqd_sell' => $sell,
            'usd_to_iqd_buy' => $buy,
            // Reverse: how many USD for 1,000,000 IQD (readable for market rates ~150k).
            'iqd_to_usd_sell' => $sell > 0 ? round(1_000_000 / $sell, 4) : null,
            'iqd_to_usd_buy' => $buy > 0 ? round(1_000_000 / $buy, 4) : null,
            'cad_quote_sell' => $cadAvailable ? $cadQuoteSell : null,
            'cad_quote_buy' => $cadAvailable ? $cadQuoteBuy : null,
            'cad_to_usd_sell' => $cadToUsdSell,
            'cad_to_usd_buy' => $cadToUsdBuy,
            'usd_to_cad_sell' => $usdToCadSell,
            'usd_to_cad_buy' => $usdToCadBuy,
            'cad_available' => $cadAvailable,
            'cad_note' => $cadAvailable ? null : 'CAD rate unavailable from source',
            'source' => self::SOURCE_NAME,
            'source_url' => self::SOURCE_URL,
            'cached' => $cached,
            'stale' => $stale,
            'updated_at' => $payload['updated_at'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyPayload(): array
    {
        return [
            'usd_to_iqd_sell' => null,
            'usd_to_iqd_buy' => null,
            'iqd_to_usd_sell' => null,
            'iqd_to_usd_buy' => null,
            'cad_quote_sell' => null,
            'cad_quote_buy' => null,
            'cad_to_usd_sell' => null,
            'cad_to_usd_buy' => null,
            'usd_to_cad_sell' => null,
            'usd_to_cad_buy' => null,
            'cad_available' => false,
            'cad_note' => 'CAD rate unavailable from source',
            'source' => self::SOURCE_NAME,
            'source_url' => self::SOURCE_URL,
            'cached' => false,
            'stale' => false,
            'updated_at' => null,
        ];
    }

    protected function isValidPayload(mixed $payload): bool
    {
        return is_array($payload)
            && isset($payload['usd_to_iqd_sell'], $payload['usd_to_iqd_buy'])
            && is_numeric($payload['usd_to_iqd_sell'])
            && is_numeric($payload['usd_to_iqd_buy'])
            && (float) $payload['usd_to_iqd_sell'] > 0
            && (float) $payload['usd_to_iqd_buy'] > 0;
    }
}
