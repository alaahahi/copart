<?php

namespace App\Services;

use App\Helpers\Help;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class SystemBrandingService
{
    /** Relative to public_path(); served as /public/img/branding/... */
    public const REL_DIR = 'img/branding';

    /**
     * Store logo or cover under public/img/branding (no storage symlink required).
     * Same deploy pattern as receipt logos under public/img/receipt.
     */
    public function store(UploadedFile $file, string $field): string
    {
        $dir = public_path(self::REL_DIR);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        $name = $field.'_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
        $file->move($dir, $name);

        return '/public/'.self::REL_DIR.'/'.$name;
    }

    /**
     * Delete a branding file under img/branding or legacy storage/branding.
     */
    public function delete(?string $urlPath): void
    {
        if (! $urlPath) {
            return;
        }

        foreach ($this->candidateAbsolutePaths($urlPath) as $absolute) {
            if (File::isFile($absolute)) {
                File::delete($absolute);
            }
        }
    }

    /**
     * Resolve a stored branding path to a working /public/... URL.
     * Migrates legacy storage/branding files into public/img/branding when found.
     * Returns null when the file is missing (caller should show empty, not a broken img).
     */
    public function resolve(?string $urlPath): ?string
    {
        if ($urlPath === null || trim($urlPath) === '') {
            return null;
        }

        $path = parse_url(str_replace('\\', '/', trim($urlPath)), PHP_URL_PATH) ?: $urlPath;
        $path = str_replace('\\', '/', $path);

        // Current location: public/img/branding/...
        if (preg_match('#(?:^|/)(?:public/)?(img/branding/[^/?]+)$#', $path, $m)) {
            $rel = $m[1];
            if (File::isFile(public_path($rel))) {
                return '/public/'.$rel;
            }

            return null;
        }

        // Legacy: /storage/branding/... or /public/storage/branding/...
        if (preg_match('#(?:^|/)(?:public/)?storage/(branding/[^/?]+)$#', $path, $m)) {
            $storageRel = $m[1];
            $basename = basename($storageRel);
            $newRel = self::REL_DIR.'/'.$basename;
            $newAbsolute = public_path($newRel);

            if (File::isFile($newAbsolute)) {
                return '/public/'.$newRel;
            }

            $sources = [
                storage_path('app/public/'.$storageRel),
                public_path('storage/'.$storageRel),
            ];

            foreach ($sources as $src) {
                if (! File::isFile($src)) {
                    continue;
                }

                $dir = public_path(self::REL_DIR);
                if (! File::isDirectory($dir)) {
                    File::makeDirectory($dir, 0755, true);
                }

                File::copy($src, $newAbsolute);

                return '/public/'.$newRel;
            }

            // Path saved but file missing (common when storage:link is absent).
            return null;
        }

        // Unknown / other public assets — normalize if possible, else drop broken values.
        $normalized = Help::normalizePublicPath($urlPath);
        if ($normalized === null || $normalized === '') {
            return null;
        }

        return $normalized;
    }

    /**
     * Persist resolved (migrated) paths back onto the config model when they change.
     */
    public function syncStoredPaths($config): bool
    {
        if (! $config) {
            return false;
        }

        $dirty = false;
        foreach (['app_logo', 'app_cover'] as $field) {
            $resolved = $this->resolve($config->{$field});
            $current = $config->{$field} ?: null;
            if ($resolved !== $current) {
                $config->{$field} = $resolved;
                $dirty = true;
            }
        }

        return $dirty;
    }

    /**
     * Absolute filesystem candidates for delete / migrate.
     *
     * @return list<string>
     */
    protected function candidateAbsolutePaths(string $urlPath): array
    {
        $path = parse_url(str_replace('\\', '/', $urlPath), PHP_URL_PATH) ?: $urlPath;
        $path = str_replace('\\', '/', $path);
        $out = [];

        if (preg_match('#(?:^|/)(?:public/)?(img/branding/[^/?]+)$#', $path, $m)) {
            $out[] = public_path($m[1]);
        }

        if (preg_match('#(?:^|/)(?:public/)?storage/(branding/[^/?]+)$#', $path, $m)) {
            $out[] = storage_path('app/public/'.$m[1]);
            $out[] = public_path('storage/'.$m[1]);
            $out[] = public_path(self::REL_DIR.'/'.basename($m[1]));
        }

        return $out;
    }
}
