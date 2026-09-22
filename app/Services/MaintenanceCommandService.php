<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

/**
 * Persist maintenance-command run status per owner so Settings can show
 * «تم التنفيذ» and allow dismissing commands to avoid accidental re-runs.
 */
class MaintenanceCommandService
{
    public const KEY_REPAIR_BAD_CAR_TRANSFERS = 'repair_bad_car_transfers';

    /**
     * Catalog of known maintenance commands (extensible).
     *
     * @return array<string, array{key:string,title:string,hint:string}>
     */
    public function catalog(): array
    {
        return [
            self::KEY_REPAIR_BAD_CAR_TRANSFERS => [
                'key' => self::KEY_REPAIR_BAD_CAR_TRANSFERS,
                'title' => 'إصلاح نقل السيارات (صندوق وهمي)',
                'hint' => 'يحذف قيود «نقل السيارة» الخاطئة على الصندوق/الإيراد ويعيد ترحيل الذمم.',
            ],
        ];
    }

    /**
     * Visible commands for Settings (excludes dismissed unless $includeDismissed).
     *
     * @return list<array<string, mixed>>
     */
    public function listForOwner(int $ownerId, bool $includeDismissed = false): array
    {
        $state = $this->read($ownerId);
        $out = [];

        foreach ($this->catalog() as $key => $meta) {
            $row = $state[$key] ?? [];
            $status = (string) ($row['status'] ?? 'pending');
            if (! $includeDismissed && $status === 'dismissed') {
                continue;
            }

            $out[] = array_merge($meta, [
                'status' => $status,
                'executed_at' => $row['executed_at'] ?? null,
                'executed_by' => $row['executed_by'] ?? null,
                'summary' => $row['summary'] ?? null,
                'output' => $row['output'] ?? null,
                'dismissed_at' => $row['dismissed_at'] ?? null,
            ]);
        }

        return $out;
    }

    public function markExecuted(
        int $ownerId,
        string $key,
        ?string $summary = null,
        ?string $output = null
    ): array {
        if (! isset($this->catalog()[$key])) {
            throw new \InvalidArgumentException('أمر صيانة غير معروف: '.$key);
        }

        $state = $this->read($ownerId);
        $state[$key] = [
            'status' => 'executed',
            'executed_at' => now()->toDateTimeString(),
            'executed_by' => Auth::id(),
            'summary' => $summary,
            'output' => $output,
            'dismissed_at' => null,
        ];
        $this->write($ownerId, $state);

        return $state[$key];
    }

    public function dismiss(int $ownerId, string $key): array
    {
        if (! isset($this->catalog()[$key])) {
            throw new \InvalidArgumentException('أمر صيانة غير معروف: '.$key);
        }

        $state = $this->read($ownerId);
        $prev = $state[$key] ?? [];
        $state[$key] = array_merge($prev, [
            'status' => 'dismissed',
            'dismissed_at' => now()->toDateTimeString(),
        ]);
        $this->write($ownerId, $state);

        return $state[$key];
    }

    public function restore(int $ownerId, string $key): array
    {
        if (! isset($this->catalog()[$key])) {
            throw new \InvalidArgumentException('أمر صيانة غير معروف: '.$key);
        }

        $state = $this->read($ownerId);
        $prev = $state[$key] ?? [];
        $wasExecuted = ! empty($prev['executed_at']);
        $state[$key] = array_merge($prev, [
            'status' => $wasExecuted ? 'executed' : 'pending',
            'dismissed_at' => null,
        ]);
        $this->write($ownerId, $state);

        return $state[$key];
    }

    public function get(int $ownerId, string $key): ?array
    {
        foreach ($this->listForOwner($ownerId, true) as $cmd) {
            if ($cmd['key'] === $key) {
                return $cmd;
            }
        }

        return null;
    }

    /** @return array<string, array<string, mixed>> */
    protected function read(int $ownerId): array
    {
        $path = $this->path($ownerId);
        if (! File::isFile($path)) {
            return [];
        }

        $raw = File::get($path);
        $data = json_decode($raw ?: '{}', true);

        return is_array($data) ? $data : [];
    }

    /** @param  array<string, array<string, mixed>>  $state */
    protected function write(int $ownerId, array $state): void
    {
        $dir = dirname($this->path($ownerId));
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        File::put(
            $this->path($ownerId),
            json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    protected function path(int $ownerId): string
    {
        return storage_path('app/maintenance_commands/owner_'.$ownerId.'.json');
    }
}
