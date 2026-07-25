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
}
