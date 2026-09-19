<?php

namespace App\Services;

use App\Support\MysqlDumpToSqliteConverter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Web/CLI shared helpers for MySQL dump → SQLite import + schema upgrade.
 */
class LegacyMysqlImportService
{
    public function __construct(
        protected MysqlDumpToSqliteConverter $converter
    ) {
    }

    public function defaultSqlitePath(): string
    {
        return database_path('database_mazad.sqlite');
    }

    public function defaultDumpPath(): string
    {
        return 'C:\\Users\\ALAA-PC\\Downloads\\salamjalalco_mazad.sql';
    }

    /**
     * Snapshot of DBs + ledger readiness for the ops Blade panel.
     *
     * @return array<string,mixed>
     */
    public function panelStatus(): array
    {
        $live = database_path('database.sqlite');
        $mazad = $this->defaultSqlitePath();
        $active = (string) config('database.connections.sqlite.database');

        return [
            'active_connection' => config('database.default'),
            'active_database' => $active,
            'using_mazad' => $this->pathsEqual($active, $mazad),
            'live_sqlite' => [
                'path' => $live,
                'exists' => is_file($live),
                'size' => is_file($live) ? filesize($live) : 0,
            ],
            'mazad_sqlite' => [
                'path' => $mazad,
                'exists' => is_file($mazad),
                'size' => is_file($mazad) ? filesize($mazad) : 0,
                'counts' => is_file($mazad) ? $this->countsOnFile($mazad) : null,
            ],
            'current_counts' => $this->countsOnConnection(),
            'schema' => [
                'ledger_accounts' => Schema::hasTable('ledger_accounts'),
                'journal_entries' => Schema::hasTable('journal_entries'),
                'journal_lines' => Schema::hasTable('journal_lines'),
                'vaults' => Schema::hasTable('vaults'),
                'legacy_cutover_wallets' => Schema::hasTable('legacy_cutover_wallets'),
                'car_payment_allocations' => Schema::hasTable('car') && Schema::hasColumn('car', 'payment_allocations'),
                'car_damage_compensation' => Schema::hasTable('car') && Schema::hasColumn('car', 'damage_compensation'),
            ],
            'default_dump_path' => $this->defaultDumpPath(),
            'dump_exists' => is_file($this->defaultDumpPath()),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function import(string $dumpPath, string $sqlitePath, bool $force, bool $migrate): array
    {
        if (! is_file($dumpPath)) {
            throw new InvalidArgumentException("ملف الدمب غير موجود: {$dumpPath}");
        }

        if (is_file($sqlitePath) && ! $force) {
            throw new InvalidArgumentException('ملف SQLite الهدف موجود مسبقاً — فعّل overwrite.');
        }

        @set_time_limit(0);
        $started = microtime(true);

        $live = database_path('database.sqlite');
        $backup = null;
        if (is_file($live) && ! $this->pathsEqual($live, $sqlitePath)) {
            $backup = database_path('database.sqlite.bak-before-mazad-'.date('Ymd-His'));
            @copy($live, $backup);
        }

        $import = $this->converter->import($dumpPath, $sqlitePath, true);
        $this->snapshotWallets($sqlitePath);

        $migrateResult = null;
        if ($migrate) {
            $migrateResult = $this->upgradeSchemaOnFile($sqlitePath);
        }

        $elapsedMs = (int) round((microtime(true) - $started) * 1000);

        return [
            'ok' => empty($import['statements_failed']) && ($migrateResult['ok'] ?? true),
            'elapsed_ms' => $elapsedMs,
            'backup' => $backup,
            'sqlite_path' => $sqlitePath,
            'import' => $import,
            'migrate' => $migrateResult,
            'counts' => $this->countsOnFile($sqlitePath),
        ];
    }

    /**
     * @return array{ok:bool,output:string,checks:array<string,bool>}
     */
    public function upgradeSchemaOnFile(string $sqlitePath): array
    {
        $this->bindSqlite($sqlitePath);

        if (Schema::hasTable('migrations')) {
            DB::table('migrations')->delete();
        } else {
            Schema::create('migrations', function ($table) {
                $table->increments('id');
                $table->string('migration');
                $table->integer('batch');
            });
        }

        $code = Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();

        $checks = [
            'ledger_accounts' => Schema::hasTable('ledger_accounts'),
            'journal_entries' => Schema::hasTable('journal_entries'),
            'journal_lines' => Schema::hasTable('journal_lines'),
            'vaults' => Schema::hasTable('vaults'),
            'legacy_cutover_wallets' => Schema::hasTable('legacy_cutover_wallets'),
            'car.payment_allocations' => Schema::hasTable('car') && Schema::hasColumn('car', 'payment_allocations'),
            'car.damage_compensation' => Schema::hasTable('car') && Schema::hasColumn('car', 'damage_compensation'),
        ];

        return [
            'ok' => $code === 0 && ! in_array(false, $checks, true),
            'exit_code' => $code,
            'output' => $output,
            'checks' => $checks,
        ];
    }

    public function bindSqlite(string $sqlitePath): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $sqlitePath,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
    }

    /**
     * @return array<string,int|null>
     */
    public function countsOnConnection(): array
    {
        $keys = ['users', 'car', 'transactions', 'ledger_accounts', 'journal_entries', 'vaults', 'legacy_cutover_wallets'];
        $out = [];
        foreach ($keys as $table) {
            try {
                $out[$table] = Schema::hasTable($table) ? (int) DB::table($table)->count() : null;
            } catch (Throwable $e) {
                $out[$table] = null;
            }
        }

        return $out;
    }

    /**
     * @return array<string,int|null>
     */
    public function countsOnFile(string $sqlitePath): array
    {
        if (! is_file($sqlitePath)) {
            return [];
        }

        try {
            $pdo = new PDO('sqlite:'.$sqlitePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $keys = ['users', 'car', 'transactions', 'wallets', 'legacy_cutover_wallets', 'ledger_accounts', 'journal_entries', 'vaults'];
            $out = [];
            foreach ($keys as $table) {
                $exists = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=".$pdo->quote($table))->fetchColumn();
                if (! $exists) {
                    $out[$table] = null;
                    continue;
                }
                $out[$table] = (int) $pdo->query('SELECT COUNT(*) FROM "'.$table.'"')->fetchColumn();
            }

            return $out;
        } catch (Throwable $e) {
            return ['error' => -1];
        }
    }

    private function snapshotWallets(string $sqlitePath): void
    {
        $pdo = new PDO('sqlite:'.$sqlitePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $has = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='wallets'")->fetchColumn();
        if (! $has) {
            return;
        }
        $pdo->exec('DROP TABLE IF EXISTS legacy_cutover_wallets');
        $pdo->exec('CREATE TABLE legacy_cutover_wallets AS SELECT * FROM wallets');
    }

    private function pathsEqual(string $a, string $b): bool
    {
        $ra = realpath($a) ?: str_replace('\\', '/', $a);
        $rb = realpath($b) ?: str_replace('\\', '/', $b);

        return strcasecmp($ra, $rb) === 0;
    }

    public function writeLastResult(string $action, array $payload): string
    {
        $dir = storage_path('app/qa');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir.'/legacy-cutover-panel-last.json';
        File::put($path, json_encode([
            'action' => $action,
            'at' => now()->toIso8601String(),
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $path;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function lastResult(): ?array
    {
        $path = storage_path('app/qa/legacy-cutover-panel-last.json');
        if (! is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
