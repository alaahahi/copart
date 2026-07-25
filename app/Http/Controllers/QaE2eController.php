<?php

namespace App\Http\Controllers;

use App\Services\QaE2eRunnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class QaE2eController extends Controller
{
    public function __construct(protected QaE2eRunnerService $runner)
    {
    }

    public function index(): View
    {
        return view('qa.e2e', [
            'last' => $this->runner->lastResult(),
            'suites' => QaE2eRunnerService::ALLOWED_SUITES,
            'systemChunks' => QaE2eRunnerService::SYSTEM_CHUNKS,
        ]);
    }

    public function last(): JsonResponse
    {
        return response()->json([
            'result' => $this->runner->lastResult(),
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        // Force JSON Accept so Laravel never renders an HTML exception page for this API.
        $request->headers->set('Accept', 'application/json');

        $suite = (string) $request->input('suite', QaE2eRunnerService::SUITE_ACCOUNTING);

        if (! in_array($suite, QaE2eRunnerService::ALLOWED_SUITES, true)) {
            return response()->json([
                'message' => 'Suite غير مسموح',
                'allowed' => QaE2eRunnerService::ALLOWED_SUITES,
            ], 422);
        }

        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('max_input_time', '0');

        $mergeAs = trim((string) $request->input('merge_as', ''));
        $options = null;
        if ($mergeAs !== '') {
            if (! in_array($mergeAs, [
                QaE2eRunnerService::SUITE_SYSTEM,
                QaE2eRunnerService::SUITE_HEALTH,
                QaE2eRunnerService::SUITE_ALL,
            ], true)) {
                return response()->json([
                    'message' => 'merge_as غير مسموح',
                    'result' => null,
                ], 422);
            }
            $options = [
                'merge_as' => $mergeAs,
                'chunk_index' => (int) $request->input('chunk_index', 0),
                'chunk_total' => (int) $request->input('chunk_total', 0),
            ];
        }

        try {
            $result = $this->runner->run($suite, $options);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'result' => null,
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'فشل تشغيل اختبارات Playwright',
                'error' => config('app.debug') ? $e->getMessage() : 'internal_error',
                'result' => null,
            ], 500);
        }

        return response()->json([
            'message' => $result['ok'] ? 'نجحت الاختبارات' : 'فشلت بعض الاختبارات',
            'result' => $result,
        ], 200);
    }
}
