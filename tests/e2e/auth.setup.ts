import { test as setup, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { adminCredentials } from './helpers/env';

const authFile = path.join(__dirname, '.auth', 'admin.json');

setup('authenticate as admin @setup', async ({ page }) => {
  fs.mkdirSync(path.dirname(authFile), { recursive: true });

  const { email, password } = adminCredentials();

  await page.goto('/login');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.locator('form').evaluate((form: HTMLFormElement) => form.requestSubmit());

  await page.waitForURL(/\/(dashboard|sales|accounting|clients)/, { timeout: 45_000 });
  await expect(page).not.toHaveURL(/\/login/);

  await page.context().storageState({ path: authFile });
});
