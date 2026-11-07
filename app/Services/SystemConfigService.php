<?php

namespace App\Services;

use App\Models\SystemConfig;
use App\Support\Branding;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Single entry point for reading the singleton system_config row.
 *
 * On fresh or partially migrated databases the table or the row may be absent;
 * branding reads happen on every request (shared Inertia data, login page,
 * report blades) and must never be fatal. Resolved once per request.
 */
class SystemConfigService
{
    protected ?SystemConfig $resolved = null;

    protected bool $loaded = false;

    /** Never null: falls back to an unsaved model carrying sane defaults. */
    public function current(): SystemConfig
    {
        if ($this->loaded) {
            return $this->resolved;
        }

        $this->loaded = true;
        $this->resolved = $this->fetch() ?? $this->defaults();

        return $this->resolved;
    }

    /** True when a persisted row was found (callers that must not write to a phantom row). */
    public function exists(): bool
    {
        return $this->current()->exists;
    }

    /**
     * Branding payload for the shared Inertia props / layouts.
     *
     * @return array{appName: string, tagline: string, logo: ?string, cover: ?string}
     */
    public function branding(): array
    {
        $config = $this->current();
        $files = app(SystemBrandingService::class);

        return [
            'appName' => Branding::resolveName((string) $config->first_title_ar),
            'tagline' => Branding::tagline(),
            'logo' => $files->resolve($config->app_logo),
            'cover' => $files->resolve($config->app_cover),
        ];
    }

    /**
     * Dashboard widget visibility (defaults to shown when column/row missing).
     *
     * @return array{clock: bool, exchange: bool, today_cash: bool}
     */
    public function dashboardWidgets(): array
    {
        $config = $this->current();

        return [
            'clock' => $this->flag($config, 'dash_show_clock', true),
            'exchange' => $this->flag($config, 'dash_show_exchange', true),
            'today_cash' => $this->flag($config, 'dash_show_today_cash', true),
        ];
    }

    /** Nejum/Kaml payments module — off by default until enabled in settings. */
    public function nejumPaymentsEnabled(): bool
    {
        return $this->flag($this->current(), 'nejum_payments_enabled', false);
    }

    protected function flag(SystemConfig $config, string $column, bool $default): bool
    {
        try {
            if (! Schema::hasTable('system_config') || ! Schema::hasColumn('system_config', $column)) {
                return $default;
            }
        } catch (Throwable) {
            return $default;
        }

        $value = $config->getAttribute($column);

        return $value === null ? $default : (bool) $value;
    }

    /** Forget the cached instance (after saving settings, or in tests). */
    public function flush(): void
    {
        $this->loaded = false;
        $this->resolved = null;
    }

    protected function fetch(): ?SystemConfig
    {
        try {
            if (! Schema::hasTable('system_config')) {
                return null;
            }

            return SystemConfig::query()->first();
        } catch (Throwable) {
            // Missing table/column or unreachable database must not break rendering.
            return null;
        }
    }

    protected function defaults(): SystemConfig
    {
        return new SystemConfig([
            'first_title_ar' => Branding::name(),
            'first_title_kr' => '',
            'second_title_ar' => '',
            'second_title_kr' => '',
            'third_title_ar' => '',
            'third_title_kr' => '',
            'receipt_template' => 'default',
            'dash_show_clock' => true,
            'dash_show_exchange' => true,
            'dash_show_today_cash' => true,
            'nejum_payments_enabled' => false,
        ]);
    }
}
