<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Runs fixed Playwright suites for the QA Blade panel.
 * Never accepts arbitrary shell input — suite names are whitelisted.
 *
 * System suites are split into short chunks so each Process stays under
 * typical PHP/nginx timeouts; «system» / «health» / «all» run chunks sequentially.
 */
class QaE2eRunnerService
{
    public const SUITE_ACCOUNTING = 'accounting';

    public const SUITE_CAR_FLOW = 'car-flow';

    public const SUITE_SYSTEM_CORE = 'system-core';

    public const SUITE_SYSTEM_ACCOUNTING = 'system-accounting';

    public const SUITE_SYSTEM_ADMIN = 'system-admin';

    public const SUITE_SYSTEM = 'system';

    /** Alias for SUITE_SYSTEM */
    public const SUITE_HEALTH = 'health';

    public const SUITE_ALL = 'all';

    /** @var list<string> */
    public const ALLOWED_SUITES = [
        self::SUITE_ACCOUNTING,
        self::SUITE_CAR_FLOW,
        self::SUITE_SYSTEM_CORE,
        self::SUITE_SYSTEM_ACCOUNTING,
        self::SUITE_SYSTEM_ADMIN,
        self::SUITE_SYSTEM,
        self::SUITE_HEALTH,
        self::SUITE_ALL,
    ];

    /** Chunks composing a full system health run (order matters). */
    public const SYSTEM_CHUNKS = [
        self::SUITE_SYSTEM_CORE,
        self::SUITE_SYSTEM_ACCOUNTING,
        self::SUITE_SYSTEM_ADMIN,
    ];

    public function resultPath(): string
    {
        return storage_path('app/qa/last-e2e.json');
    }

    public function reportPath(): string
    {
        return storage_path('app/qa/playwright-report.json');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastResult(): ?array
    {
        $path = $this->resultPath();
        if (! File::exists($path)) {
            return null;
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array{merge_as?:string,chunk_index?:int,chunk_total?:int}|null  $options
     * @return array<string, mixed>
     */
    public function run(string $suite, ?array $options = null): array
    {
        if (! in_array($suite, self::ALLOWED_SUITES, true)) {
            throw new RuntimeException('Invalid QA suite');
        }

        $this->extendPhpTimeLimit();
        File::ensureDirectoryExists(storage_path('app/qa'));

        if (in_array($suite, [self::SUITE_SYSTEM, self::SUITE_HEALTH], true)) {
            return $this->runChunks(self::SYSTEM_CHUNKS, $suite);
        }

        if ($suite === self::SUITE_ALL) {
            return $this->runChunks(array_merge(
                [self::SUITE_ACCOUNTING],
                self::SYSTEM_CHUNKS
            ), $suite);
        }

        $mergeAs = is_array($options) ? (string) ($options['merge_as'] ?? '') : '';
        $persistSingle = $mergeAs === '';
        $result = $this->runSingle($suite, persist: $persistSingle);

        if ($mergeAs !== '' && in_array($mergeAs, [self::SUITE_SYSTEM, self::SUITE_HEALTH, self::SUITE_ALL], true)) {
            return $this->mergeChunkIntoParent($mergeAs, $result, $options ?? []);
        }

        return $result;
    }

    /**
     * Accumulate chunk results into last-e2e.json while the Blade UI runs chunks via separate HTTP calls.
     *
     * @param  array<string, mixed>  $chunkResult
     * @param  array{chunk_index?:int,chunk_total?:int}  $options
     * @return array<string, mixed>
     */
    protected function mergeChunkIntoParent(string $parentSuite, array $chunkResult, array $options): array
    {
        $index = (int) ($options['chunk_index'] ?? 0);
        $previous = $index <= 0 ? null : $this->lastResult();
        $parts = [];

        if (is_array($previous)
            && ($previous['suite'] ?? null) === $parentSuite
            && is_array($previous['chunks'] ?? null)
            && $index > 0) {
            foreach ($previous['chunks'] as $existing) {
                if (! is_array($existing)) {
                    continue;
                }
                $parts[] = [
                    'suite' => (string) ($existing['suite'] ?? ''),
                    'ok' => (bool) ($existing['ok'] ?? false),
                    'exit_code' => (int) ($existing['exit_code'] ?? 1),
                    'passed' => (int) ($existing['passed'] ?? 0),
                    'failed' => (int) ($existing['failed'] ?? 0),
                    'skipped' => (int) ($existing['skipped'] ?? 0),
                    'flaky' => (int) ($existing['flaky'] ?? 0),
                    'total' => (int) ($existing['total'] ?? 0),
                    'duration_ms' => (int) ($existing['duration_ms'] ?? 0),
                    'command' => (string) ($existing['command'] ?? ''),
                    'stdout' => '',
                    'started_at' => $previous['started_at'] ?? null,
                    'finished_at' => $previous['finished_at'] ?? null,
                ];
            }
            // Restore stdout segments from previous parent log is lossy; keep cumulative from this request only for latest.
        }

        $parts[] = $chunkResult;

        $startedAt = $index <= 0
            ? (string) ($chunkResult['started_at'] ?? now()->toIso8601String())
            : (string) ($previous['started_at'] ?? $chunkResult['started_at'] ?? now()->toIso8601String());

        $passed = 0;
        $failed = 0;
        $skipped = 0;
        $flaky = 0;
        $total = 0;
        $durationMs = 0;
        $exitCode = 0;
        $commands = [];
        $chunkMeta = [];
        $stdoutParts = [];

        foreach ($parts as $part) {
            $passed += (int) ($part['passed'] ?? 0);
            $failed += (int) ($part['failed'] ?? 0);
            $skipped += (int) ($part['skipped'] ?? 0);
            $flaky += (int) ($part['flaky'] ?? 0);
            $total += (int) ($part['total'] ?? 0);
            $durationMs += (int) ($part['duration_ms'] ?? 0);
            if ((int) ($part['exit_code'] ?? 1) !== 0) {
                $exitCode = (int) ($part['exit_code'] ?? 1);
            }
            if (! empty($part['command'])) {
                $commands[] = (string) $part['command'];
            }
            $chunkMeta[] = [
                'suite' => $part['suite'] ?? '?',
                'ok' => (bool) ($part['ok'] ?? false),
                'exit_code' => (int) ($part['exit_code'] ?? 1),
                'passed' => (int) ($part['passed'] ?? 0),
                'failed' => (int) ($part['failed'] ?? 0),
                'skipped' => (int) ($part['skipped'] ?? 0),
                'flaky' => (int) ($part['flaky'] ?? 0),
                'total' => (int) ($part['total'] ?? 0),
                'duration_ms' => (int) ($part['duration_ms'] ?? 0),
                'command' => (string) ($part['command'] ?? ''),
            ];
            if (($part['stdout'] ?? '') !== '') {
                $stdoutParts[] = '===== CHUNK: '.($part['suite'] ?? '?')." =====\n".$part['stdout'];
            }
        }

        // Prefer previous combined stdout + latest chunk when continuing a merge.
        $stdout = implode("\n\n", $stdoutParts);
        if ($index > 0 && is_array($previous) && ! empty($previous['stdout']) && ! empty($chunkResult['stdout'])) {
            $stdout = rtrim((string) $previous['stdout'])."\n\n===== CHUNK: ".($chunkResult['suite'] ?? '?')." =====\n".$chunkResult['stdout'];
        }

        $payload = [
            'suite' => $parentSuite,
            'chunks' => $chunkMeta,
            'command' => implode(' && ', array_filter($commands)),
            'started_at' => $startedAt,
            'finished_at' => now()->toIso8601String(),
            'duration_ms' => $durationMs,
            'exit_code' => $exitCode,
            'ok' => $exitCode === 0,
            'passed' => $passed,
            'failed' => $failed,
            'skipped' => $skipped,
            'flaky' => $flaky,
            'total' => $total,
            'stdout' => $this->truncate($stdout),
            'report' => $this->readPlaywrightJsonReport(),
        ];

        File::put($this->resultPath(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $payload;
    }

    /**
     * Run several suites as separate Playwright processes and merge into last-e2e.json.
     *
     * @param  list<string>  $chunks
     * @return array<string, mixed>
     */
    protected function runChunks(array $chunks, string $label): array
    {
        $startedAt = now()->toIso8601String();
        $started = microtime(true);
        $chunkResults = [];
        $commands = [];
        $stdoutParts = [];
        $passed = 0;
        $failed = 0;
        $skipped = 0;
        $flaky = 0;
        $total = 0;
        $exitCode = 0;

        foreach ($chunks as $chunk) {
            $result = $this->runSingle($chunk, persist: false);
            $chunkResults[] = [
                'suite' => $chunk,
                'ok' => (bool) ($result['ok'] ?? false),
                'exit_code' => (int) ($result['exit_code'] ?? 1),
                'passed' => (int) ($result['passed'] ?? 0),
                'failed' => (int) ($result['failed'] ?? 0),
                'skipped' => (int) ($result['skipped'] ?? 0),
                'flaky' => (int) ($result['flaky'] ?? 0),
                'total' => (int) ($result['total'] ?? 0),
                'duration_ms' => (int) ($result['duration_ms'] ?? 0),
            ];
            $commands[] = (string) ($result['command'] ?? '');
            $stdoutParts[] = '===== CHUNK: '.$chunk." =====\n".(string) ($result['stdout'] ?? '');
            $passed += (int) ($result['passed'] ?? 0);
            $failed += (int) ($result['failed'] ?? 0);
            $skipped += (int) ($result['skipped'] ?? 0);
            $flaky += (int) ($result['flaky'] ?? 0);
            $total += (int) ($result['total'] ?? 0);
            if ((int) ($result['exit_code'] ?? 1) !== 0) {
                $exitCode = (int) ($result['exit_code'] ?? 1);
            }
        }

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $payload = [
            'suite' => $label,
            'chunks' => $chunkResults,
            'command' => implode(' && ', array_filter($commands)),
            'started_at' => $startedAt,
            'finished_at' => now()->toIso8601String(),
            'duration_ms' => $durationMs,
            'exit_code' => $exitCode,
            'ok' => $exitCode === 0,
            'passed' => $passed,
            'failed' => $failed,
            'skipped' => $skipped,
            'flaky' => $flaky,
            'total' => $total,
            'stdout' => $this->truncate(implode("\n\n", $stdoutParts)),
            'report' => $this->readPlaywrightJsonReport(),
        ];

        File::put($this->resultPath(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function runSingle(string $suite, bool $persist = true): array
    {
        if (! in_array($suite, self::ALLOWED_SUITES, true)
            || in_array($suite, [self::SUITE_SYSTEM, self::SUITE_HEALTH, self::SUITE_ALL], true)) {
            throw new RuntimeException('Invalid single QA suite: '.$suite);
        }

        $startedAt = now()->toIso8601String();
        $started = microtime(true);

        $command = $this->buildCommand($suite);
        $timeout = (float) config('qa.timeout_seconds', 900);

        try {
            $process = new Process(
                $command,
                base_path(),
                $this->processEnv(),
                null,
                $timeout
            );
            $process->run();
            $stdout = $process->getOutput();
            $stderr = $process->getErrorOutput();
            $exitCode = $process->getExitCode() ?? 1;
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $payload = [
                'suite' => $suite,
                'command' => implode(' ', $command),
                'started_at' => $startedAt,
                'finished_at' => now()->toIso8601String(),
                'duration_ms' => $durationMs,
                'exit_code' => 1,
                'ok' => false,
                'passed' => 0,
                'failed' => 1,
                'skipped' => 0,
                'flaky' => 0,
                'total' => 1,
                'stdout' => $this->truncate('Process error: '.$e->getMessage()),
                'report' => null,
            ];

            if ($persist) {
                File::put($this->resultPath(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            return $payload;
        }

        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $report = $this->readPlaywrightJsonReport();
        $summary = $this->summarizeReport($report, $exitCode);

        $payload = [
            'suite' => $suite,
            'command' => implode(' ', $command),
            'started_at' => $startedAt,
            'finished_at' => now()->toIso8601String(),
            'duration_ms' => $durationMs,
            'exit_code' => $exitCode,
            'ok' => $exitCode === 0,
            'passed' => $summary['passed'],
            'failed' => $summary['failed'],
            'skipped' => $summary['skipped'],
            'flaky' => $summary['flaky'],
            'total' => $summary['total'],
            'stdout' => $this->truncate($stdout."\n".$stderr),
            'report' => $report,
        ];

        if ($persist) {
            File::put($this->resultPath(), json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    protected function buildCommand(string $suite): array
    {
        $npx = $this->npxBinary();
        $base = [$npx, 'playwright', 'test'];

        // Include @setup so storageState is created when --grep filters other projects.
        $grep = match ($suite) {
            self::SUITE_ACCOUNTING => '@accounting|@setup',
            self::SUITE_CAR_FLOW => '@car-flow|@setup',
            self::SUITE_SYSTEM_CORE => '@system-core|@setup',
            self::SUITE_SYSTEM_ACCOUNTING => '@system-accounting|@setup',
            self::SUITE_SYSTEM_ADMIN => '@system-admin|@setup',
            default => throw new RuntimeException('No grep pattern for suite: '.$suite),
        };

        $base[] = '--grep';
        $base[] = $grep;

        return $base;
    }

    protected function extendPhpTimeLimit(): void
    {
        $seconds = (int) config('qa.php_max_execution_seconds', 0);
        if ($seconds <= 0) {
            @set_time_limit(0);
            @ini_set('max_execution_time', '0');
        } else {
            @set_time_limit($seconds);
            @ini_set('max_execution_time', (string) $seconds);
        }
    }

    protected function npxBinary(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 'npx.cmd';
        }

        return 'npx';
    }

    /**
     * @return array<string, string>
     */
    protected function processEnv(): array
    {
        $env = array_merge($_ENV, $_SERVER);
        $clean = [];
        foreach ($env as $key => $value) {
            if (is_string($key) && (is_string($value) || is_numeric($value))) {
                $clean[$key] = (string) $value;
            }
        }

        $clean['APP_URL'] = rtrim((string) config('app.url'), '/');
        $clean['E2E_BASE_URL'] = rtrim((string) (config('qa.base_url') ?: config('app.url')), '/');
        $clean['CI'] = '1';

        return $clean;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function readPlaywrightJsonReport(): ?array
    {
        $path = $this->reportPath();
        if (! File::exists($path)) {
            return null;
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>|null  $report
     * @return array{passed:int,failed:int,skipped:int,flaky:int,total:int}
     */
    protected function summarizeReport(?array $report, int $exitCode): array
    {
        $stats = $report['stats'] ?? null;
        if (is_array($stats)) {
            $expected = (int) ($stats['expected'] ?? 0);
            $unexpected = (int) ($stats['unexpected'] ?? 0);
            $flaky = (int) ($stats['flaky'] ?? 0);
            $skipped = (int) ($stats['skipped'] ?? 0);

            return [
                'passed' => $expected,
                'failed' => $unexpected,
                'skipped' => $skipped,
                'flaky' => $flaky,
                'total' => $expected + $unexpected + $skipped + $flaky,
            ];
        }

        return [
            'passed' => $exitCode === 0 ? 1 : 0,
            'failed' => $exitCode === 0 ? 0 : 1,
            'skipped' => 0,
            'flaky' => 0,
            'total' => 1,
        ];
    }

    protected function truncate(string $text): string
    {
        $max = (int) config('qa.max_log_chars', 200_000);
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, -$max);
    }
}
