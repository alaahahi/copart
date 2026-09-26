<?php

namespace App\Support;

final class PhoneDigits
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === null || $digits === '') {
            return null;
        }

        return $digits;
    }

    public static function equals(?string $a, ?string $b): bool
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);

        if ($na === null || $nb === null) {
            return false;
        }

        return $na === $nb
            || str_ends_with($na, $nb)
            || str_ends_with($nb, $na);
    }
}
