<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SystemBrandingService
{
    public const DISK = 'public';

    public const DIR = 'branding';

    /**
     * Store logo or cover under storage/app/public/branding and return a public URL path.
     */
    public function store(UploadedFile $file, string $field): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        $name = $field.'_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;

        $path = $file->storeAs(self::DIR, $name, self::DISK);

        return Storage::disk(self::DISK)->url($path);
    }

    /**
     * Delete a previously stored branding file (only under /storage/branding/).
     */
    public function delete(?string $urlPath): void
    {
        if (! $urlPath) {
            return;
        }

        $relative = $this->relativePathFromUrl($urlPath);
        if (! $relative) {
            return;
        }

        if (Storage::disk(self::DISK)->exists($relative)) {
            Storage::disk(self::DISK)->delete($relative);
        }
    }

    protected function relativePathFromUrl(string $urlPath): ?string
    {
        $path = parse_url($urlPath, PHP_URL_PATH) ?: $urlPath;
        $path = str_replace('\\', '/', $path);

        if (! preg_match('#(?:^|/)?storage/(branding/[^/]+)$#', $path, $m)) {
            return null;
        }

        return $m[1];
    }
}
