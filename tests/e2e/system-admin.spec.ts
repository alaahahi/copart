import { test } from '@playwright/test';
import { runSystemHealthChunk } from './helpers/systemHealth';

/**
 * System chunk: settings, analytics, sync, QA panel.
 * Tags: @system @health @system-admin
 */
test.describe('System health admin @system @health @system-admin', () => {
  test('admin / ops pages load without server or JS crash', async ({ page }) => {
    await runSystemHealthChunk(page, [
      { name: 'Analytics', path: '/analytics' },
      { name: 'Settings', path: '/settings' },
      { name: 'Sync monitor', path: '/sync-monitor' },
      { name: 'QA e2e panel', path: '/qa/e2e' },
    ]);
  });
});
