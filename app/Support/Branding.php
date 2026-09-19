<?php

namespace App\Support;

/**
 * Single source of truth for the commercial brand shown in the UI, page titles
 * and printed documents. APP_NAME stays reserved for framework concerns
 * (mail from-name, cache/session prefixes) and must not leak into the product UI.
 */
class Branding
{
    /** Brand name from APP_PRODUCT_NAME, falling back to APP_NAME. */
    public static function name(): string
    {
        $product = trim((string) config('app.product_name'));

        return $product !== '' ? $product : (string) config('app.name', '');
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

        if ($title === '' || $title === trim((string) config('app.name', ''))) {
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
