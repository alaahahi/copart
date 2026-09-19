<?php

namespace App\Console\Commands;

use App\Services\LegacyMysqlImportService;
use Illuminate\Console\Command;

class ImportMysqlDumpToSqlite extends Command
{
    protected $signature = 'db:import-mysql-dump
                            {dump : Absolute path to the .sql dump}
                            {--sqlite= : Target sqlite path (default database/database_mazad.sqlite)}
                            {--force : Overwrite existing sqlite file}
                            {--migrate : After import, reset migrations registry and run artisan migrate}
                            {--keep-wallets-snapshot : Keep legacy_cutover_wallets after migrate (default true)}';

    protected $description = 'Convert a MySQL dump to SQLite and optionally upgrade schema for the current ERP code';

    public function handle(LegacyMysqlImportService $import): int
    {
        $dump = (string) $this->argument('dump');
        $sqlite = (string) ($this->option('sqlite') ?: $import->defaultSqlitePath());
        $force = (bool) $this->option('force');
        $migrate = (bool) $this->option('migrate');

        try {
            $result = $import->import($dump, $sqlite, $force, $migrate);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! empty($result['backup'])) {
            $this->info('Backed up live database.sqlite → '.$result['backup']);
        }

        $this->info('Importing → '.$sqlite);
        $imp = $result['import'] ?? [];
        $this->table(
            ['Metric', 'Value'],
            [
                ['Tables', count($imp['tables'] ?? [])],
                ['Statements OK', $imp['statements_ok'] ?? 0],
                ['Skipped', $imp['statements_skipped'] ?? 0],
                ['Failed', count($imp['statements_failed'] ?? [])],
                ['Elapsed ms', $result['elapsed_ms'] ?? '—'],
            ]
        );

        $counts = $result['counts'] ?? [];
        $this->table(
            ['Table', 'Rows'],
            collect(['users', 'car', 'transactions', 'wallets', 'legacy_cutover_wallets', 'user_type', 'system_config', 'company_treasury_entries'])
                ->map(fn ($t) => [$t, $counts[$t] ?? '—'])
                ->all()
        );

        if (! empty($imp['statements_failed'])) {
            $this->warn('Some statements failed (showing up to 15):');
            foreach (array_slice($imp['statements_failed'], 0, 15) as $f) {
                $this->line('  · '.($f['error'] ?? ''));
            }
        }

        if ($migrate && isset($result['migrate'])) {
            $this->line($result['migrate']['output'] ?? '');
            $this->table(
                ['Check', 'OK'],
                collect($result['migrate']['checks'] ?? [])->map(fn ($ok, $name) => [$name, $ok ? 'yes' : 'NO'])->all()
            );
            $this->info(($result['migrate']['ok'] ?? false) ? 'Schema upgrade complete.' : 'Schema upgrade incomplete.');
        }

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
