import { test } from '@playwright/test';
import { runSystemHealthChunk } from './helpers/systemHealth';

/**
 * System chunk: core ERP screens (dashboard, purchases, sales, clients).
 * Tags: @system @health @system-core
 */
test.describe('System health core @system @health @system-core', () => {
  test('core pages load without server or JS crash', async ({ page }) => {
    await runSystemHealthChunk(page, [
      { name: 'Dashboard', path: '/dashboard' },
      { name: 'Purchases', path: '/purchases' },
      { name: 'Sales', path: '/sales' },
      { name: 'Clients', path: '/clients' },
    ]);
  });
});
