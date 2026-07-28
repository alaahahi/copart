<?php

namespace Tests\Feature\Accounting;

use App\Services\AccountingIntegrityService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Scans the configured local DB (read-only) for double-entry integrity.
 * Does not use RefreshDatabase — intentionally validates live ledger data.
 */
class AccountingIntegrityTest extends TestCase
{
    public function test_all_live_journals_are_balanced_and_linked(): void
    {
        if (!Schema::hasTable('journal_entries') || !Schema::hasTable('journal_lines')) {
            $this->markTestSkipped('Ledger tables not migrated yet.');
        }

        $result = app(AccountingIntegrityService::class)->check(null, true);

        $this->assertFalse($result['tables_missing']);
        $this->assertSame(
            0,
            count($result['unbalanced_entries']),
            'Unbalanced journals: '.json_encode($result['unbalanced_entries'], JSON_UNESCAPED_UNICODE)
        );
        $this->assertSame(
            0,
            count($result['empty_entries']),
            'Empty journals: '.json_encode($result['empty_entries'], JSON_UNESCAPED_UNICODE)
        );
        $this->assertSame(0, $result['orphan_lines'], 'Orphan journal_lines found');
        $this->assertSame(0, $result['missing_accounts'], 'Lines point at missing ledger_accounts');
        $this->assertTrue($result['ok']);
    }

    public function test_accounting_integrity_artisan_command_exits_zero_when_clean(): void
    {
        if (!Schema::hasTable('journal_entries') || !Schema::hasTable('journal_lines')) {
            $this->markTestSkipped('Ledger tables not migrated yet.');
        }

        $this->artisan('accounting:integrity', ['--skip-wallets' => true])
            ->assertSuccessful();
    }
}
