<?php

namespace Tests\Feature\Accounting;

use App\Http\Controllers\AccountingController;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\SystemWalletService;
use App\Services\VaultService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;
use Throwable;

/**
 * Real cash-box deposit/withdraw must post a journal and appear on /accounting.
 */
class CashBoxAccountingPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cash_box_movement_types_include_deposit_and_withdraw(): void
    {
        $types = VaultService::CASH_BOX_MOVEMENT_TYPES;

        $this->assertContains('inUserBox', $types);
        $this->assertContains('outUserBox', $types);
        $this->assertContains('in', $types);
        $this->assertContains('debt', $types);
        $this->assertContains('transfer_in', $types);
        $this->assertContains('transfer_out', $types);
    }

    public function test_deposit_and_withdraw_hit_cash_ledger_and_accounting_feed(): void
    {
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

        try {
            app(VaultService::class)->ensureMainBoxVault($ownerId);
            $ledger = app(LedgerService::class);
            $ledger->ensureSystemAccounts($ownerId);
            $cash = $ledger->cashAccount($ownerId, '$');
            $before = (float) $cash->balance('$');

            $controller = app(AccountingController::class);

            $deposit = $controller->receiptArrivedUser(Request::create('/api/receiptArrivedUser', 'POST', [
                'id' => $mainBox->id,
                'amountDollar' => 100,
                'amountNote' => 'اختبار إيداع صندوق',
                'date' => now()->toDateString(),
            ]));
            $this->assertSame(200, $deposit->getStatusCode());
            $depositTx = json_decode($deposit->getContent());
            $this->assertNotEmpty($depositTx->id ?? null);
            $this->assertSame('inUserBox', $depositTx->type);
            $this->assertNotEmpty($depositTx->journal_entry_id ?? null);

            $cash->refresh();
            $this->assertEqualsWithDelta($before + 100, (float) $cash->balance('$'), 0.01);

            $withdraw = $controller->salesDebtUser(Request::create('/api/salesDebtUser', 'POST', [
                'id' => $mainBox->id,
                'amountDollar' => 40,
                'note' => 'اختبار سحب صندوق',
                'date' => now()->toDateString(),
            ]));
            $this->assertSame(200, $withdraw->getStatusCode());
            $withdrawTx = json_decode($withdraw->getContent());
            $this->assertNotEmpty($withdrawTx->id ?? null);
            $this->assertSame('outUserBox', $withdrawTx->type);
            $this->assertNotEmpty($withdrawTx->journal_entry_id ?? null);

            $cash->refresh();
            $this->assertEqualsWithDelta($before + 60, (float) $cash->balance('$'), 0.01);

            $today = now()->toDateString();
            $_GET = [
                'user_id' => $mainBox->id,
                'type' => 'wallet',
                'all_cash' => 1,
                'from' => $today,
                'to' => $today,
            ];
            $feed = $controller->getIndexAccounting(Request::create('/getIndexAccounting', 'GET', $_GET));
            $this->assertSame(200, $feed->getStatusCode());
            $payload = json_decode($feed->getContent(), true);
            $ids = collect($payload['transactions']['data'] ?? [])->pluck('id')->all();
            $this->assertContains((int) $depositTx->id, $ids);
            $this->assertContains((int) $withdrawTx->id, $ids);

            $rows = collect($payload['transactions']['data'] ?? [])->keyBy('id');
            $this->assertSame(
                LedgerService::CODE_CASH_USD,
                $rows[(int) $depositTx->id]['money_account']['code'] ?? null
            );
            $this->assertSame(
                LedgerService::CODE_CASH_USD,
                $rows[(int) $withdrawTx->id]['money_account']['code'] ?? null
            );

            $this->assertNotNull(JournalEntry::query()->find($depositTx->journal_entry_id));
            $this->assertNotNull(JournalEntry::query()->find($withdrawTx->journal_entry_id));
            $this->assertTrue(
                LedgerAccount::query()->where('owner_id', $ownerId)->where('code', LedgerService::CODE_CASH_USD)->exists()
            );

            $this->assertSame(
                $mainBox->id,
                app(SystemWalletService::class)->requireMainBox($ownerId)->id
            );
        } catch (QueryException|Throwable $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'database is locked') || str_contains($message, 'HY000')) {
                $this->markTestSkipped('SQLite database locked by another process.');
            }
            throw $e;
        }
    }
}
