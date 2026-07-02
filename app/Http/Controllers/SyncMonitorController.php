<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class SyncMonitorController extends Controller
{
    /**
     * جلب قائمة المايجريشنز المتاحة (المنفذة والمعلقة).
     */
    public function getMigrations(Request $request): JsonResponse
    {
        try {
            $showExecuted = filter_var($request->get('show_executed', false), FILTER_VALIDATE_BOOLEAN);

            $migrationsPath = database_path('migrations');
            $files = File::files($migrationsPath);

            $executedMigrations = [];
            try {
                if ($this->tableExists('migrations')) {
                    $executed = DB::table('migrations')->pluck('migration')->toArray();
                    $executedMigrations = array_map(function ($migration) {
                        if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_(.+)$/', $migration, $matches)) {
                            return $matches[1];
                        }
                        return $migration;
                    }, $executed);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get executed migrations', ['error' => $e->getMessage()]);
            }

            $migrations = [];
            foreach ($files as $file) {
                $fileName = $file->getFilename();
                if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_(.+?)\.php$/', $fileName, $matches)) {
                    $migrationName = $matches[1];
                    $isExecuted = in_array($migrationName, $executedMigrations);

                    if ($isExecuted && !$showExecuted) {
                        continue;
                    }

                    $migrations[] = [
                        'file' => $fileName,
                        'name' => $migrationName,
                        'date' => date('Y-m-d H:i:s', $file->getMTime()),
                        'executed' => $isExecuted,
                    ];
                }
            }

            usort($migrations, fn ($a, $b) => strcmp($b['file'], $a['file']));

            return response()->json([
                'migrations' => $migrations,
                'total_executed' => count($executedMigrations),
                'total_pending' => count($migrations),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get migrations', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage(), 'migrations' => []], 500);
        }
    }

    /**
     * فحص المايجريشن للتحقق من وجود بيانات في الجداول المتأثرة قبل التنفيذ.
     */
    public function checkMigration(Request $request): JsonResponse
    {
        try {
            $migrationName = $request->get('migration_name');
            if (!$migrationName) {
                return response()->json(['success' => false, 'error' => 'اسم المايجريشن مطلوب'], 422);
            }

            [$migrationFile, $migrationPath] = $this->findMigration($migrationName);
            if (!$migrationFile) {
                return response()->json(['success' => false, 'error' => 'المايجريشن غير موجود: ' . $migrationName], 404);
            }

            $tables = $this->extractDroppedTables(File::get($migrationPath));

            $tablesWithData = [];
            $totalRecords = 0;
            foreach ($tables as $table) {
                try {
                    $count = DB::table($table)->count();
                    if ($count > 0) {
                        $tablesWithData[] = ['name' => $table, 'count' => $count];
                        $totalRecords += $count;
                    }
                } catch (\Exception $e) {
                    // الجدول غير موجود - لا مشكلة
                }
            }

            return response()->json([
                'success' => true,
                'has_data' => count($tablesWithData) > 0,
                'tables_with_data' => $tablesWithData,
                'total_records' => $totalRecords,
                'affected_tables' => $tables,
                'migration_file' => $migrationFile,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check migration', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * تنفيذ migration محدد حسب الاسم.
     */
    public function runMigration(Request $request): JsonResponse
    {
        try {
            $migrationName = $request->get('migration_name');
            $force = filter_var($request->get('force', false), FILTER_VALIDATE_BOOLEAN);

            if (!$migrationName) {
                return response()->json(['success' => false, 'error' => 'اسم المايجريشن مطلوب'], 422);
            }

            [$migrationFile, $migrationPath] = $this->findMigration($migrationName);
            if (!$migrationFile) {
                return response()->json(['success' => false, 'error' => 'المايجريشن غير موجود: ' . $migrationName], 404);
            }

            if (!$force) {
                $tables = $this->extractDroppedTables(File::get($migrationPath));
                foreach ($tables as $table) {
                    try {
                        $count = DB::table($table)->count();
                        if ($count > 0) {
                            return response()->json([
                                'success' => false,
                                'error' => 'يوجد بيانات في الجداول المتأثرة. لا يمكن تنفيذ المايجريشن حفاظاً على البيانات.',
                                'warning' => true,
                                'table' => $table,
                                'record_count' => $count,
                                'message' => "يوجد {$count} سجل في جدول '{$table}'. استخدم force=true إذا كنت متأكداً.",
                            ], 400);
                        }
                    } catch (\Exception $e) {
                        // الجدول غير موجود - لا مشكلة
                    }
                }
            }

            $exitCode = Artisan::call('migrate', [
                '--path' => 'database/migrations/' . $migrationFile,
                '--force' => true,
            ]);
            $output = Artisan::output();

            if ($exitCode === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم تنفيذ المايجريشن بنجاح',
                    'output' => $output,
                    'migration' => $migrationFile,
                ]);
            }

            return response()->json(['success' => false, 'error' => 'فشل تنفيذ المايجريشن', 'output' => $output], 500);
        } catch (\Exception $e) {
            Log::error('Failed to run migration', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * قراءة آخر أسطر من ملف اللوغ (storage/logs/laravel.log).
     */
    public function getLogs(Request $request): JsonResponse
    {
        try {
            $lines = (int) $request->get('lines', 300);
            $lines = max(50, min($lines, 2000));

            $logFile = storage_path('logs/laravel.log');
            if (!File::exists($logFile)) {
                return response()->json([
                    'entries' => [],
                    'size' => 0,
                    'message' => 'لا يوجد ملف لوغ بعد',
                ]);
            }

            $content = $this->tailFile($logFile, $lines);
            $entries = $this->parseLogEntries($content);

            return response()->json([
                'entries' => $entries,
                'size' => File::size($logFile),
                'modified' => date('Y-m-d H:i:s', File::lastModified($logFile)),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to read logs', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage(), 'entries' => []], 500);
        }
    }

    /**
     * تفريغ ملف اللوغ.
     */
    public function clearLogs(): JsonResponse
    {
        try {
            $logFile = storage_path('logs/laravel.log');
            if (File::exists($logFile)) {
                File::put($logFile, '');
            }
            return response()->json(['success' => true, 'message' => 'تم تفريغ اللوغ']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * البحث عن ملف مايجريشن حسب الاسم.
     */
    protected function findMigration(string $migrationName): array
    {
        $files = File::files(database_path('migrations'));
        foreach ($files as $file) {
            if (str_contains($file->getFilename(), $migrationName)) {
                return [$file->getFilename(), $file->getPathname()];
            }
        }
        return [null, null];
    }

    /**
     * استخراج أسماء الجداول التي قد تُحذف من محتوى المايجريشن.
     */
    protected function extractDroppedTables(string $migrationContent): array
    {
        $tables = [];

        if (preg_match_all('/dropIfExists\([\'"]([^\'"]+)[\'"]\)|drop\([\'"]([^\'"]+)[\'"]\)/i', $migrationContent, $matches)) {
            $tables = array_merge($tables, array_filter(array_merge($matches[1], $matches[2])));
        }
        if (preg_match_all('/Schema::drop\([\'"]([^\'"]+)[\'"]\)/i', $migrationContent, $matches)) {
            $tables = array_merge($tables, $matches[1]);
        }
        if (preg_match('/function\s+down\([^)]*\)\s*\{([^}]+)\}/is', $migrationContent, $downMatch)) {
            if (preg_match_all('/table\([\'"]([^\'"]+)[\'"]\)/i', $downMatch[1], $tableMatches)) {
                $tables = array_merge($tables, $tableMatches[1]);
            }
        }

        return array_values(array_unique(array_filter($tables)));
    }

    /**
     * قراءة آخر N سطر من ملف نصي بكفاءة.
     */
    protected function tailFile(string $filepath, int $lines): string
    {
        $f = fopen($filepath, 'rb');
        if (!$f) {
            return '';
        }

        $buffer = 4096;
        fseek($f, 0, SEEK_END);
        $filesize = ftell($f);
        $pos = $filesize;
        $lineCount = 0;
        $chunks = '';

        while ($pos > 0 && $lineCount <= $lines) {
            $readSize = ($pos - $buffer) > 0 ? $buffer : $pos;
            $pos -= $readSize;
            fseek($f, $pos);
            $chunk = fread($f, $readSize);
            $chunks = $chunk . $chunks;
            $lineCount = substr_count($chunks, "\n");
        }
        fclose($f);

        $allLines = explode("\n", $chunks);
        $allLines = array_slice($allLines, -$lines);
        return implode("\n", $allLines);
    }

    /**
     * تحويل نص اللوغ إلى سجلات منظمة.
     */
    protected function parseLogEntries(string $content): array
    {
        $entries = [];
        $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\][^:]*\.(\w+):(.*)$/';
        $lines = explode("\n", $content);
        $current = null;

        foreach ($lines as $line) {
            if (preg_match($pattern, $line, $m)) {
                if ($current) {
                    $entries[] = $current;
                }
                $current = [
                    'time' => $m[1],
                    'level' => strtolower($m[2]),
                    'message' => trim($m[3]),
                    'stack' => '',
                ];
            } elseif ($current !== null) {
                $current['stack'] .= ($current['stack'] === '' ? '' : "\n") . $line;
            }
        }
        if ($current) {
            $entries[] = $current;
        }

        return array_reverse($entries);
    }

    /**
     * التحقق من وجود جدول في قاعدة البيانات الافتراضية.
     */
    protected function tableExists(string $tableName): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable($tableName);
        } catch (\Exception $e) {
            return false;
        }
    }
}
