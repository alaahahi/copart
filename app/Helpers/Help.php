<?php

namespace App\Helpers;

use Alkoumi\LaravelArabicTafqeet\Tafqeet;

class Help
{
    public static function numberToWords($number, $currency = 'usd')
    {
        if ($currency == '$') {
            $currency = 'usd';
        }
        if ($currency == 'IQD') {
            $currency = 'iqd';
        }

        return Tafqeet::inArabic($number, $currency);
    }

    /**
     * Display-only number formatting: thousand separators, up to $maxDecimals,
     * trailing zeros stripped (1600.00 → "1,600", 10.50 → "10.5").
     */
    public static function formatNumber($number, int $maxDecimals = 2): string
    {
        $n = round((float) $number, $maxDecimals);

        if (abs($n - round($n)) < 1e-9) {
            return number_format($n, 0, '.', ',');
        }

        $formatted = number_format($n, $maxDecimals, '.', ',');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * Display-only money formatting.
     * USD/$ → up to 2 decimals (trailing zeros stripped).
     * IQD / other → whole numbers.
     */
    public static function formatMoney($number, string $currency = '$'): string
    {
        $isUsd = in_array($currency, ['$', 'USD', 'usd'], true);

        return self::formatNumber($number, $isUsd ? 2 : 0);
    }

    /**
     * Normalize a public web path for this deploy (docroot is often project root,
     * so static files live under /public/... — matching uploads elsewhere).
     *
     * Accepts stored values like /img/receipt/x.png, public/img/..., or full URLs.
     */
    public static function normalizePublicPath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:')) {
            return $path;
        }

        $path = '/'.ltrim($path, '/');

        while (str_starts_with($path, '/public/public/')) {
            $path = substr($path, 7);
        }

        // Legacy receipt paths omitted /public (404 when site is served from project root).
        if (preg_match('#^/img/#', $path)) {
            $path = '/public'.$path;
        }

        return $path;
    }

    /**
     * Public asset URL for receipts/print (absolute preferred for print/PDF reliability).
     */
    public static function publicAssetUrl(?string $path, bool $absolute = true): ?string
    {
        $normalized = self::normalizePublicPath($path);
        if ($normalized === null || $normalized === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $normalized) || str_starts_with($normalized, 'data:')) {
            return $normalized;
        }

        return $absolute ? url($normalized) : $normalized;
    }
}
