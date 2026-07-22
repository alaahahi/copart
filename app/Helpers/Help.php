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
}
