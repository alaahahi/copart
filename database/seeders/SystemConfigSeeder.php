<?php

namespace Database\Seeders;

use App\Models\SystemConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Ensures the singleton system_config row exists (idempotent).
 *
 * Many controllers/blades read SystemConfig::first() without a null guard,
 * so a fresh install without this row breaks printing and branding.
 * Defaults mirror SystemConfigController::index().
 */
class SystemConfigSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('system_config')) {
            $this->command?->warn('system_config table missing — skip SystemConfigSeeder.');

            return;
        }

        if (SystemConfig::query()->exists()) {
            return;
        }

        SystemConfig::query()->create([
            'first_title_ar' => \App\Support\Branding::name(),
            'receipt_template' => 'default',
            'wa_base_host' => 'https://wa.intellij-app.com',
            'wa_source' => 'sales',
            'wa_created_by' => 'copart-erp',
        ]);

        $this->command?->info('system_config row created.');
    }
}
