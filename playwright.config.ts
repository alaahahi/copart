import { defineConfig, devices } from '@playwright/test';
import fs from 'fs';
import path from 'path';

/**
 * Resolve APP_URL from Laravel .env (prefer E2E_BASE_URL override).
 * Defaults match common XAMPP / Valet local setups.
 */
function resolveBaseURL(): string {
  const fromEnv = process.env.E2E_BASE_URL || process.env.APP_URL;
  if (fromEnv && fromEnv.trim() !== '') {
    return fromEnv.trim().replace(/\/$/, '');
  }

  const envPath = path.join(__dirname, '.env');
  if (fs.existsSync(envPath)) {
    const raw = fs.readFileSync(envPath, 'utf8');
    const match = raw.match(/^APP_URL=(.+)$/m);
    if (match?.[1]) {
      return match[1].trim().replace(/^["']|["']$/g, '').replace(/\/$/, '');
    }
  }

  return 'http://127.0.0.1/copart/public';
}

const baseURL = resolveBaseURL();
const isHttps = baseURL.startsWith('https://');

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  timeout: 90_000,
  expect: { timeout: 15_000 },
  reporter: [
    ['list'],
    ['json', { outputFile: 'storage/app/qa/playwright-report.json' }],
    ['html', { open: 'never', outputFolder: 'storage/app/qa/playwright-html' }],
  ],
  use: {
    baseURL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    ignoreHTTPSErrors: isHttps,
    locale: 'ar',
  },
  projects: [
    {
      name: 'setup',
      testMatch: /auth\.setup\.ts/,
    },
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'tests/e2e/.auth/admin.json',
      },
      dependencies: ['setup'],
      testIgnore: /auth\.setup\.ts/,
    },
  ],
  outputDir: 'storage/app/qa/test-results',
});
