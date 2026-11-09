<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\CompanyTreasuryController;
use App\Models\CompanyTreasuryEntry;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\VaultService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Accounting → company treasury one-time copy (no ledger journals).
 * Soft-delete of the treasury copy restores the export button flag.
 */
class ExportCashMovementToCompanyTreasuryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_export_copies_once_without_ledger_and_restore_after_delete(): void
    {
        if (! Schema::hasTable('company_treasury_entries')
            || ! Schema::hasColumn('company_treasury_entries', 'source_transaction_id')
        ) {
            $this->markTestSkipped('company_treasury_entries.source_transaction_id is required.');
        }

        $mainBox = User::query()->where('email', 'mainBox@account.com')->first();
        if (! $mainBox) {
            $this->markTestSkipped('mainBox@account.com is required.');
        }

        $ownerId = (int) $mainBox->owner_id;
        $actor = User::query()
            ->where('owner_id', $ownerId)
            ->whereIn('type_id', [1, 2, 5, 6])
            ->where('email', '!=', 'mainBox@account.com')
            ->first();
        if (! $actor) {
            $this->markTestSkipped('No operator user for this owner.');
        }

        $this->actingAs($actor);

        app(VaultService::class)->ensureMainBoxVault($ownerId);
        $ledger = app(LedgerService::class);
        $ledger->ensureSystemAccounts($ownerId);

        $accounting = app(AccountingController::class);
        $treasury = app(CompanyTreasuryController::class);

        $deposit = $accounting->receiptArrivedUser(Request::create('/api/receiptArrivedUser', 'POST', [
            'id' => $mainBox->id,
            'amountDollar' => 75,
            'amountNote' => 'اختبار تصدير لقاصة الشركة',
            'date' => now()->toDateString(),
        ]));
        $this->assertSame(200, $deposit->getStatusCode());
        $depositTx = json_decode($deposit->getContent());
        $this->assertNotEmpty($depositTx->id ?? null);

        $journalCountBefore = JournalEntry::query()->count();

        $export = $treasury->exportFromTransaction(Request::create(
            '/api/companyTreasuryExportFromTransaction',
            'POST',
            ['transaction_id' => (int) $depositTx->id]
        ));
        $this->assertSame(201, $export->getStatusCode(), (string) $export->getContent());
        $entry = json_decode($export->getContent(), true);

        $this->assertSame((int) $depositTx->id, (int) ($entry['source_transaction_id'] ?? 0));
        $this->assertEqualsWithDelta(75.0, (float) ($entry['debit'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(0.0, (float) ($entry['credit'] ?? 0), 0.01);
        $this->assertEmpty($entry['journal_entry_id'] ?? null);
        $this->assertSame($journalCountBefore, JournalEntry::query()->count());

        $dup = $treasury->exportFromTransaction(Request::create(
            '/api/companyTreasuryExportFromTransaction',
            'POST',
            ['transaction_id' => (int) $depositTx->id]
        ));
        $this->assertSame(409, $dup->getStatusCode());

        $today = now()->toDateString();
        $_GET = [
            'user_id' => $mainBox->id,
            'type' => 'wallet',
            'all_cash' => 1,
            'from' => $today,
            'to' => $today,
        ];
        $feed = $accounting->getIndexAccounting(Request::create('/getIndexAccounting', 'GET', $_GET));
        $this->assertSame(200, $feed->getStatusCode());
        $payload = json_decode($feed->getContent(), true);
        $row = collect($payload['transactions']['data'] ?? [])
            ->firstWhere('id', (int) $depositTx->id);
        $this->assertNotNull($row);
        $this->assertTrue((bool) ($row['exported_to_treasury'] ?? false));

        $delete = $treasury->destroy(Request::create(
            '/api/companyTreasuryDelete',
            'POST',
            ['id' => (int) $entry['id']]
        ));
        $this->assertSame(200, $delete->getStatusCode());
        $this->assertSoftDeleted('company_treasury_entries', ['id' => (int) $entry['id']]);

        $feedAfterDelete = $accounting->getIndexAccounting(Request::create('/getIndexAccounting', 'GET', $_GET));
        $payloadAfterDelete = json_decode($feedAfterDelete->getContent(), true);
        $rowAfterDelete = collect($payloadAfterDelete['transactions']['data'] ?? [])
            ->firstWhere('id', (int) $depositTx->id);
        $this->assertNotNull($rowAfterDelete);
        $this->assertFalse((bool) ($rowAfterDelete['exported_to_treasury'] ?? true));

        $reExport = $treasury->exportFromTransaction(Request::create(
            '/api/companyTreasuryExportFromTransaction',
            'POST',
            ['transaction_id' => (int) $depositTx->id]
        ));
        $this->assertSame(201, $reExport->getStatusCode(), (string) $reExport->getContent());
        $newEntry = json_decode($reExport->getContent(), true);
        $this->assertNotSame((int) $entry['id'], (int) ($newEntry['id'] ?? 0));
        $this->assertEmpty($newEntry['journal_entry_id'] ?? null);
        $this->assertSame($journalCountBefore, JournalEntry::query()->count());
    }
}
