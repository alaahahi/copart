import { test } from '@playwright/test';
import { runSystemHealthChunk } from './helpers/systemHealth';

/**
 * System chunk: accounting / ledger / wallet / treasury pages.
 * Tags: @system @health @system-accounting
 */
test.describe('System health accounting pages @system @health @system-accounting', () => {
  test('accounting pages load without server or JS crash', async ({ page }) => {
    await runSystemHealthChunk(page, [
      { name: 'Accounting', path: '/accounting' },
      { name: 'Ledger', path: '/ledger' },
      { name: 'Company treasury (قاصة)', path: '/company_treasury' },
      { name: 'Wallet', path: '/wallet' },
    ]);
  });
});
