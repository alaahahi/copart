<?php

namespace App\Http\Controllers;

use App\Services\AccountingIntegrityService;
use App\Services\LegacyLedgerCutoverService;
use App\Services\LegacyMysqlImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class LegacyCutoverController extends Controller
{
    public function __construct(
        protected LegacyMysqlImportService $import,
        protected LegacyLedgerCutoverService $cutover,
        protected AccountingIntegrityService $integrity
    ) {
    }

    public function index(): View
    {
        return view('ops.legacy-cutover', [
            'status' => $this->import->panelStatus(),
            'last' => $this->import->lastResult(),
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'status' => $this->import->panelStatus(),
            'last' => $this->import->lastResult(),
        ]);
    }

    public function importDump(Request $request): JsonResponse
    {
        $request->headers->set('Accept', 'application/json');
        @set_time_limit(0);

        $validated = $request->validate([
            'dump_path' => ['required', 'string', 'max:500'],
            'sqlite_path' => ['nullable', 'string', 'max:500'],
            'force' => ['sometimes', 'boolean'],
            'migrate' => ['sometimes', 'boolean'],
            'bind_after' => ['sometimes', 'boolean'],
        ]);

        $dump = (string) $validated['dump_path'];
        $sqlite = (string) ($validated['sqlite_path'] ?: $this->import->defaultSqlitePath());
        $force = (bool) ($validated['force'] ?? true);
        $migrate = (bool) ($validated['migrate'] ?? true);
        $bindAfter = (bool) ($validated['bind_after'] ?? true);

        try {
            $result = $this->import->import($dump, $sqlite, $force, $migrate);
            if ($bindAfter && is_file($sqlite)) {
                $this->import->bindSqlite($sqlite);
                $result['bound'] = true;
                $result['status_after'] = $this->import->panelStatus();
            }
            $path = $this->import->writeLastResult('import', $result);

            return response()->json([
                'ok' => (bool) ($result['ok'] ?? false),
                'message' => ($result['ok'] ?? false) ? 'تم الاستيراد وترقية المخطط' : 'اكتمل مع أخطاء — راجع التفاصيل',
                'result' => $result,
                'report' => $path,
            ], ($result['ok'] ?? false) ? 200 : 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'result' => null,
            ], 422);
        }
    }

    public function cutover(Request $request): JsonResponse
    {
        $request->headers->set('Accept', 'application/json');
        @set_time_limit(0);

        $validated = $request->validate([
            'execute' => ['sometimes', 'boolean'],
            'owner' => ['nullable', 'integer', 'min:1'],
            'use_mazad' => ['sometimes', 'boolean'],
        ]);

        $execute = (bool) ($validated['execute'] ?? false);
        $owner = isset($validated['owner']) ? (int) $validated['owner'] : null;

        if ($request->boolean('use_mazad', true)) {
            $mazad = $this->import->defaultSqlitePath();
            if (! is_file($mazad)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'ملف database_mazad.sqlite غير موجود — نفّذ الاستيراد أولاً.',
                ], 422);
            }
            $this->import->bindSqlite($mazad);
        }

        try {
            $started = microtime(true);
            $result = $this->cutover->run($owner, ! $execute);
            $result['elapsed_ms'] = (int) round((microtime(true) - $started) * 1000);

            if ($execute) {
                $integrity = $this->integrity->check($owner, false);
                $result['integrity'] = [
                    'ok' => $integrity['ok'],
                    'entries_checked' => $integrity['entries_checked'],
                    'unbalanced' => count($integrity['unbalanced_entries']),
                    'empty' => count($integrity['empty_entries']),
                    'orphan_lines' => $integrity['orphan_lines'],
                ];
                $result['trial_balance'] = $result['trial_balance'] ?: $this->cutover->trialBalanceSummary($owner);
            }

            $path = $this->import->writeLastResult($execute ? 'cutover_execute' : 'cutover_dry_run', $result);

            return response()->json([
                'ok' => true,
                'message' => $execute
                    ? 'تم ترحيل القيود الافتتاحية'
                    : 'معاينة جاهزة (لم يُكتب شيء)',
                'result' => $result,
                'report' => $path,
                'status' => $this->import->panelStatus(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
                'result' => null,
            ], 422);
        }
    }

    public function integrity(Request $request): JsonResponse
    {
        $request->headers->set('Accept', 'application/json');

        if ($request->boolean('use_mazad', true)) {
            $mazad = $this->import->defaultSqlitePath();
            if (is_file($mazad)) {
                $this->import->bindSqlite($mazad);
            }
        }

        $owner = $request->filled('owner') ? (int) $request->input('owner') : null;
        $check = $this->integrity->check($owner, false);
        $tb = $this->cutover->trialBalanceSummary($owner);

        $payload = [
            'integrity' => $check,
            'trial_balance' => $tb,
            'status' => $this->import->panelStatus(),
        ];
        $path = $this->import->writeLastResult('integrity', $payload);

        return response()->json([
            'ok' => (bool) $check['ok'],
            'message' => $check['ok'] ? 'سلامة القيود: ناجح' : 'سلامة القيود: فشل',
            'result' => $payload,
            'report' => $path,
        ], $check['ok'] ? 200 : 422);
    }
}
