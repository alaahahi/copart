import { test, expect } from '@playwright/test';
import { adminCredentials } from './helpers/env';
import { browserGet, createSoftAssert } from './helpers/api';
import { runSystemHealthChunk, type HealthRoute } from './helpers/systemHealth';

/**
 * Comprehensive ERP Playwright suite.
 *
 * Run:
 *   npm run test:e2e:full
 *
 * Credentials (optional overrides in .env):
 *   E2E_ADMIN_EMAIL / E2E_ADMIN_PASSWORD
 *   E2E_BASE_URL or APP_URL
 *
 * Tags: @full @comprehensive
 */

const CORE_ROUTES: HealthRoute[] = [
  { name: 'Dashboard', path: '/dashboard' },
  { name: 'Purchases', path: '/purchases' },
  { name: 'Sales', path: '/sales' },
  { name: 'Clients', path: '/clients' },
  { name: 'Accounting', path: '/accounting' },
  { name: 'Ledger', path: '/ledger' },
  { name: 'Vaults', path: '/vaults' },
  { name: 'Expense account', path: '/expense-account' },
  { name: 'Company treasury', path: '/company_treasury' },
  { name: 'Car expenses', path: '/car_expenses' },
  { name: 'Annual information', path: '/annual_information' },
  { name: 'Transfers', path: '/transfers' },
  { name: 'Car config', path: '/carConfig' },
  { name: 'Analytics', path: '/analytics' },
  { name: 'Settings', path: '/settings' },
  { name: 'Settings users', path: '/settings/users' },
  { name: 'Sync monitor', path: '/sync-monitor' },
  { name: 'Vinstack imports', path: '/vinstack-imports' },
];

const API_GET_PATHS = [
  '/api/totalInfo',
  '/api/dashboardRecentActivity?limit=5',
  '/api/dashboardWeather',
  '/api/dashboardExchangeRates',
  '/api/ledgerChartOfAccounts',
  '/api/ledgerTrialBalance',
  '/api/vaults?for_ui=1',
  '/api/auctions',
  '/api/shippingRoutes',
];

test.describe('Full suite — guest auth @full @comprehensive @auth', () => {
  test.use({ storageState: { cookies: [], origins: [] } });

  test('login page is reachable', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('form.login-form, form')).toBeVisible();
  });

  test('bad credentials stay on login with validation', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#email').fill('bad-e2e@example.com');
    await page.locator('#password').fill('wrong-password-xyz');
    await page.locator('form').evaluate((form: HTMLFormElement) => form.requestSubmit());

    await expect(page).toHaveURL(/\/login/, { timeout: 20_000 });
    await expect(page.locator('body')).toContainText(
      /غير صحيحة|فشل|خطأ|incorrect|credentials|invalid|failed/i,
    );
  });

  test('admin can log in successfully', async ({ page }) => {
    const { email, password } = adminCredentials();
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('form').evaluate((form: HTMLFormElement) => form.requestSubmit());

    await page.waitForURL(/\/(dashboard|sales|accounting|clients)/, { timeout: 45_000 });
    await expect(page).not.toHaveURL(/\/login/);
  });
});

test.describe('Full suite — authenticated ERP @full @comprehensive', () => {
  test('all core pages load without server/JS crash', async ({ page }) => {
    await runSystemHealthChunk(page, CORE_ROUTES, 240_000);
  });

  test('dashboard loads for authenticated admin', async ({ page }) => {
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
    await expect(page).not.toHaveURL(/\/login/);
    await expect(page.locator('#app')).toBeVisible();
  });

  test('settings exposes dashboard widget toggles', async ({ page }) => {
    await page.goto('/settings', { waitUntil: 'domcontentloaded' });
    await expect(page).not.toHaveURL(/\/login/);

    await expect(
      page.getByText(/بطاقات لوحة التحكم|Dashboard widgets|کارتەکانی داشبۆرد/i).first(),
    ).toBeVisible({ timeout: 20_000 });

    await expect(page.locator('input[type="checkbox"]').first()).toBeVisible();
  });

  test('critical authenticated APIs respond OK', async ({ page }) => {
    test.setTimeout(180_000);
    const { softAssert, assertAll } = createSoftAssert();

    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });
    await expect(page).not.toHaveURL(/\/login/);

    for (const path of API_GET_PATHS) {
      const res = await browserGet(page, path);
      softAssert(
        res.ok && res.status < 500,
        `API فشل: ${path} (HTTP ${res.status})`,
        `API failed: ${path} (HTTP ${res.status})`,
      );
    }

    assertAll();
  });

  test('guest cannot open dashboard without session', async ({ browser }) => {
    const context = await browser.newContext({
      storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();
    try {
      await page.goto('/dashboard');
      await expect(page).toHaveURL(/\/login/, { timeout: 20_000 });
    } finally {
      await context.close();
    }
  });
});
