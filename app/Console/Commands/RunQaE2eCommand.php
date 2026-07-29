<?php

namespace App\Console\Commands;

use App\Services\QaE2eRunnerService;
use Illuminate\Console\Command;

class RunQaE2eCommand extends Command
{
    protected $signature = 'qa:e2e
                            {--suite=accounting : accounting|car-flow|system-core|system-accounting|system-admin|system|health|all}';

    protected $description = 'Run Playwright e2e suites (system/all run chunks sequentially) and store last result for the QA Blade page';

    public function handle(QaE2eRunnerService $runner): int
    {
        $suite = (string) $this->option('suite');

        if (! in_array($suite, QaE2eRunnerService::ALLOWED_SUITES, true)) {
            $this->error('Invalid suite. Allowed: '.implode(', ', QaE2eRunnerService::ALLOWED_SUITES));

            return self::FAILURE;
        }

        if (in_array($suite, [QaE2eRunnerService::SUITE_SYSTEM, QaE2eRunnerService::SUITE_HEALTH], true)) {
            $this->info('Running system chunks sequentially: '.implode(', ', QaE2eRunnerService::SYSTEM_CHUNKS));
        } elseif ($suite === QaE2eRunnerService::SUITE_ALL) {
            $this->info('Running all suites sequentially: accounting + '.implode(', ', QaE2eRunnerService::SYSTEM_CHUNKS));
        } else {
            $this->info("Running Playwright suite: {$suite}");
        }

        $result = $runner->run($suite);

        $this->line("Exit: {$result['exit_code']} | passed={$result['passed']} failed={$result['failed']} skipped={$result['skipped']}");
        if (! empty($result['chunks']) && is_array($result['chunks'])) {
            foreach ($result['chunks'] as $chunk) {
                $ok = ! empty($chunk['ok']) ? 'OK' : 'FAIL';
                $this->line(sprintf(
                    '  [%s] %s — passed=%s failed=%s (%sms)',
                    $ok,
                    $chunk['suite'] ?? '?',
                    $chunk['passed'] ?? 0,
                    $chunk['failed'] ?? 0,
                    $chunk['duration_ms'] ?? 0
                ));
            }
        }
        $this->line('Saved: '.$runner->resultPath());

        if (! empty($result['stdout'])) {
            $this->newLine();
            $this->line($result['stdout']);
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
