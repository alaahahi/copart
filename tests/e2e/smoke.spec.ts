import { test, expect } from '@playwright/test';

test.describe('Smoke @smoke', () => {
  test('admin session reaches dashboard', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).toBeVisible();
  });

  test('accounting page loads for admin', async ({ page }) => {
    await page.goto('/accounting');
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).toBeVisible();
  });

  test('ledger page loads for admin', async ({ page }) => {
    await page.goto('/ledger');
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('body')).toBeVisible();
  });
});
