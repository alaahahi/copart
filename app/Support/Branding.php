<?php

namespace App\Support;

/**
 * Single source of truth for the commercial brand shown in the UI, page titles
 * and printed documents. APP_NAME stays reserved for framework concerns
 * (mail from-name, cache/session prefixes) and must not leak into the product UI.
 */
class Branding
{
    /** Brand name: APP_PRODUCT_NAME, else a real APP_NAME, else HAULF. */
    public static function name(): string
    {
        $product = trim((string) (config('app.product_name') ?? ''));
        if ($product !== '') {
            return $product;
        }

        $appName = trim((string) config('app.name', ''));
        if ($appName !== '' && strcasecmp($appName, 'Laravel') !== 0) {
            return $appName;
        }

        return 'HAULF';
    }

    /**
     * Brand name for a settings-configured title.
     *
     * Older rows were seeded with APP_NAME, so a title identical to APP_NAME is
     * treated as an untouched placeholder and the configured brand wins.
     */
    public static function resolveName(string $configuredTitle): string
    {
        $title = trim($configuredTitle);
        $appName = trim((string) config('app.name', ''));
        $placeholders = array_filter(['', 'Laravel', $appName]);

        if ($title === '' || in_array($title, $placeholders, true)) {
            return static::name();
        }

        return $title;
    }

    /** Secondary line from APP_PRODUCT_TAGLINE. */
    public static function tagline(): string
    {
        return trim((string) config('app.product_tagline'));
    }
}
