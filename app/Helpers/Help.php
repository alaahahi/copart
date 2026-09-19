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

    protected static ?string $publicWebPrefix = null;

    /** Forget cached prefix (tests / after config change). */
    public static function flushPublicWebPrefix(): void
    {
        self::$publicWebPrefix = null;
    }

    /**
     * Web prefix for files under public/ (img, storage, css).
     *
     * '' when DOCUMENT_ROOT is already public/ (standard Laravel / this IntelliJ host).
     * '/public' when the site is served from the project root (XAMPP / some tenants).
     * Override with APP_PUBLIC_WEB_PREFIX="" or "/public".
     */
    public static function publicWebPrefix(): string
    {
        if (self::$publicWebPrefix !== null) {
            return self::$publicWebPrefix;
        }

        $forced = config('app.public_web_prefix');
        if ($forced !== null && $forced !== false) {
            $forced = trim((string) $forced);
            if ($forced === '' || $forced === '/') {
                return self::$publicWebPrefix = '';
            }

            return self::$publicWebPrefix = '/'.trim($forced, '/');
        }

        $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
        $public = realpath(public_path()) ?: '';

        if ($docRoot !== '' && $public !== '' && strcasecmp($docRoot, $public) === 0) {
            return self::$publicWebPrefix = '';
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptFile = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
        if ($scriptFile !== '' && str_ends_with($scriptFile, '/public/index.php')
            && ($scriptName === '/index.php' || $scriptName === 'index.php')) {
            return self::$publicWebPrefix = '';
        }

        return self::$publicWebPrefix = '/public';
    }

    /**
     * Normalize a public web path for this deploy.
     *
     * Hosts whose docroot is the project root need /public/img/...
     * Hosts whose docroot is public/ need /img/...
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

        if (str_starts_with($path, 'data:')) {
            return $path;
        }

        // Absolute / protocol-relative: rewrite path segment only, keep host.
        if (preg_match('#^(https?:)?//#i', $path)) {
            $parts = parse_url($path);
            if (empty($parts['host']) || empty($parts['path'])) {
                return $path;
            }

            $normalizedPath = self::normalizePublicPath($parts['path']);
            if ($normalizedPath === null || $normalizedPath === $parts['path']) {
                return $path;
            }

            $origin = '';
            if (! empty($parts['scheme'])) {
                $origin = $parts['scheme'].'://';
            } elseif (str_starts_with($path, '//')) {
                $origin = '//';
            }

            if (! empty($parts['user'])) {
                $origin .= $parts['user'];
                if (isset($parts['pass'])) {
                    $origin .= ':'.$parts['pass'];
                }
                $origin .= '@';
            }

            $origin .= $parts['host'];
            if (! empty($parts['port'])) {
                $origin .= ':'.$parts['port'];
            }

            $suffix = '';
            if (isset($parts['query'])) {
                $suffix .= '?'.$parts['query'];
            }
            if (isset($parts['fragment'])) {
                $suffix .= '#'.$parts['fragment'];
            }

            return $origin.$normalizedPath.$suffix;
        }

        $path = '/'.ltrim($path, '/');

        while (str_starts_with($path, '/public/public/')) {
            $path = substr($path, 7);
        }

        if (! preg_match('#^/(?:public/)?(img|storage|css)/#', $path)) {
            return $path;
        }

        if (preg_match('#^/public/(img|storage|css)/#', $path)) {
            $path = substr($path, 7);
        }

        $webPrefix = self::publicWebPrefix();

        return $webPrefix === '' ? $path : $webPrefix.$path;
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
