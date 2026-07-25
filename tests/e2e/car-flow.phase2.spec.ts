import { test } from '@playwright/test';

/**
 * Phase 2 stubs — full UI car registration → purchase → sale → payment → trial balance.
 * Kept skipped so `test:e2e:accounting` stays green while we extend coverage.
 *
 * Unskip when selectors/routes for car create are stable.
 */

test.describe.skip('Phase 2 — full UI car accounting flow @accounting @phase2', () => {
  test('register car via purchases UI', async ({ page }) => {
    await page.goto('/purchases');
    // TODO: fill car form (VIN, client, costs) and submit
    // TODO: assert car appears in sales/purchases list
  });

  test('record sale / customer payment via UI', async ({ page }) => {
    await page.goto('/sales');
    // TODO: open client, add payment modal, submit
    // TODO: assert remaining / paid consistency on client show page
  });

  test('trial balance still balanced after car payment', async ({ page }) => {
    await page.goto('/ledger');
    // TODO: call /api/ledgerTrialBalance and assert debit === credit
  });
});
