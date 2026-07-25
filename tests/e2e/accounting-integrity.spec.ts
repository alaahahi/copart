import { test, expect } from '@playwright/test';
import {
  browserGet,
  browserPost,
  createSoftAssert,
  round2,
  sumLines,
} from './helpers/api';

/**
 * Phase 1 — Accounting integrity (API-heavy, UI login via storageState).
 *
 * Story:
 * 1) Ensure QA merchant (client) exists
 * 2) Snapshot trial balance + cash ledger row
 * 3) Post cash receipt (وصل قبض) via API
 * 4) Assert journal debit === credit
 * 5) Assert trial balance still balances
 * 6) Assert cash debit rose by exactly the receipt amount (no double-count)
 * 7) Soft-delete QA merchant
 */

function cashRow(rows: Array<{ code?: string; type?: string; debit?: number; credit?: number; name?: string }>) {
  return (
    rows.find((r) => String(r.code || '').startsWith('1100')) ||
    rows.find((r) => r.type === 'asset' && /نقد|صندوق|cash/i.test(String(r.name || ''))) ||
    null
  );
}

test.describe('Accounting integrity @accounting', () => {
  test('cash receipt keeps journals and trial balance balanced (no double wallet post)', async ({
    page,
  }) => {
    const { softAssert, assertAll } = createSoftAssert();
    const stamp = Date.now();
    const qaName = `qa+e2e-${stamp}@test.local`;
    // Whole dollars: increaseWallet() is typed int $amount (cents would truncate).
    const amount = 100 + (stamp % 50);

    await page.goto('/accounting');
    await expect(page).not.toHaveURL(/\/login/);

    // Warm XSRF cookie for Sanctum SPA posts (same-origin fetch)
    await page.evaluate(async () => {
      await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' }).catch(() => null);
    });

    const createClient = await browserPost(page, '/api/clientsStore', {
      name: qaName,
      phone: '0700000000',
      show_in_dashboard: false,
    });
    softAssert(
      createClient.ok,
      `فشل إنشاء تاجر QA: HTTP ${createClient.status}`,
      `Failed to create QA merchant: HTTP ${createClient.status}`,
    );
    const clientId = createClient.body?.id as number | undefined;
    softAssert(!!clientId, 'لم يُرجع إنشاء التاجر معرّفاً', 'Client create response missing id');

    const tbBefore = await browserGet(page, '/api/ledgerTrialBalance?currency=$');
    softAssert(
      tbBefore.ok,
      `فشل جلب ميزان المراجعة قبل العملية: HTTP ${tbBefore.status}`,
      `Trial balance before failed: HTTP ${tbBefore.status}`,
    );

    const totalDebitBefore = round2(Number(tbBefore.body?.total_debit ?? 0));
    const totalCreditBefore = round2(Number(tbBefore.body?.total_credit ?? 0));
    softAssert(
      totalDebitBefore === totalCreditBefore,
      `ميزان المراجعة غير متوازن قبل العملية: مدين ${totalDebitBefore} ≠ دائن ${totalCreditBefore}`,
      `Trial balance unbalanced before: debit ${totalDebitBefore} ≠ credit ${totalCreditBefore}`,
    );

    const cashBefore = cashRow(tbBefore.body?.rows || []);
    const cashDebitBefore = round2(Number(cashBefore?.debit ?? 0));

    const journalsBefore = await browserGet(page, '/api/ledgerJournals?limit=5&currency=$');
    softAssert(
      journalsBefore.ok,
      `فشل جلب القيود قبل العملية: HTTP ${journalsBefore.status}`,
      `Journals before failed: HTTP ${journalsBefore.status}`,
    );
    const entriesBefore: any[] = journalsBefore.body?.entries || [];
    const latestIdBefore = Number(entriesBefore[0]?.id ?? 0);

    const note = `QA-E2E-${stamp}`;
    const receipt = await browserPost(page, '/api/receiptArrived', {
      amountDollar: amount,
      amountDinar: 0,
      amountNote: note,
      note,
    });
    softAssert(
      receipt.ok,
      `فشل تسجيل وصل القبض: HTTP ${receipt.status} — ${JSON.stringify(receipt.body)}`,
      `Cash receipt failed: HTTP ${receipt.status} — ${JSON.stringify(receipt.body)}`,
    );

    const journalsAfter = await browserGet(page, '/api/ledgerJournals?limit=20&currency=$');
    softAssert(
      journalsAfter.ok,
      `فشل جلب القيود بعد العملية: HTTP ${journalsAfter.status}`,
      `Journals after failed: HTTP ${journalsAfter.status}`,
    );

    const entries: any[] = journalsAfter.body?.entries || [];
    const created =
      entries.find(
        (e) =>
          Number(e.id) > latestIdBefore &&
          (String(e.memo || '').includes(note) ||
            (e.lines || []).some(
              (l: any) =>
                round2(Number(l.debit || 0)) === amount || round2(Number(l.credit || 0)) === amount,
            )),
      ) || entries.find((e) => Number(e.id) > latestIdBefore);

    softAssert(!!created, 'لم يُنشأ قيد يومية بعد وصل القبض', 'No new journal entry after cash receipt');

    if (created) {
      const totals = sumLines(created.lines || []);
      const d = round2(totals.debit);
      const c = round2(totals.credit);
      softAssert(
        d === c,
        `القيد غير متوازن (مدين ≠ دائن): مدين ${d} ≠ دائن ${c} — سند ${created.voucher_no}`,
        `Journal unbalanced (debit ≠ credit): debit ${d} ≠ credit ${c} — voucher ${created.voucher_no}`,
      );
      softAssert(
        d === amount,
        `مبلغ القيد لا يطابق الوصل: متوقع ${amount} حصل ${d}`,
        `Journal amount mismatch: expected ${amount} got ${d}`,
      );
    }

    const tbAfter = await browserGet(page, '/api/ledgerTrialBalance?currency=$');
    softAssert(
      tbAfter.ok,
      `فشل جلب ميزان المراجعة بعد العملية: HTTP ${tbAfter.status}`,
      `Trial balance after failed: HTTP ${tbAfter.status}`,
    );

    const totalDebitAfter = round2(Number(tbAfter.body?.total_debit ?? 0));
    const totalCreditAfter = round2(Number(tbAfter.body?.total_credit ?? 0));
    softAssert(
      totalDebitAfter === totalCreditAfter,
      `ميزان المراجعة غير متوازن بعد العملية: مدين ${totalDebitAfter} ≠ دائن ${totalCreditAfter}`,
      `Trial balance unbalanced after: debit ${totalDebitAfter} ≠ credit ${totalCreditAfter}`,
    );

    const cashAfter = cashRow(tbAfter.body?.rows || []);
    const cashDebitAfter = round2(Number(cashAfter?.debit ?? 0));
    const cashDelta = round2(cashDebitAfter - cashDebitBefore);

    softAssert(
      cashDelta === amount,
      `حركة الصندوق مزدوجة أو ناقصة: المتوقع +${amount} حصل +${cashDelta} (قبل ${cashDebitBefore} → بعد ${cashDebitAfter})`,
      `Cash box double-count or miss: expected +${amount} got +${cashDelta} (before ${cashDebitBefore} → after ${cashDebitAfter})`,
    );

    softAssert(
      round2(totalDebitAfter - totalDebitBefore) === amount,
      `إجمالي المدين في الميزان لم يرتفع بمبلغ الوصل فقط: Δ=${round2(totalDebitAfter - totalDebitBefore)} متوقع ${amount}`,
      `Trial balance debit total did not rise by receipt only: Δ=${round2(totalDebitAfter - totalDebitBefore)} expected ${amount}`,
    );

    if (clientId) {
      const del = await browserPost(page, '/api/delClient', { id: clientId });
      softAssert(
        del.ok || del.status === 422,
        `تعذر حذف تاجر QA (soft): HTTP ${del.status}`,
        `Could not soft-delete QA merchant: HTTP ${del.status}`,
      );
    }

    assertAll();
  });

  test('trial balance endpoint itself reports balanced books', async ({ page }) => {
    await page.goto('/ledger');
    await expect(page).not.toHaveURL(/\/login/);
    const { softAssert, assertAll } = createSoftAssert();
    const tb = await browserGet(page, '/api/ledgerTrialBalance?currency=$');

    softAssert(tb.ok, 'فشل ميزان المراجعة', 'Trial balance request failed');
    const d = round2(Number(tb.body?.total_debit ?? 0));
    const c = round2(Number(tb.body?.total_credit ?? 0));
    softAssert(
      d === c,
      `ميزان المراجعة غير متوازن: مدين ${d} ≠ دائن ${c}`,
      `Trial balance unbalanced: debit ${d} ≠ credit ${c}`,
    );
    assertAll();
  });
});
