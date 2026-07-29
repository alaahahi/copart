import { expect, Page } from '@playwright/test';
import { browserGet, browserPost } from './api';

/** Purchases page frontend PIN (see resources/js/Pages/purchases.vue). */
export const PURCHASES_PIN = '12457';

export type CarFlowIds = {
  stamp: number;
  vin: string;
  lot: string;
  merchantName: string;
  purchaseShipping: number;
  saleShipping: number;
  paymentAmount: number;
};

/** Unique identifiers so parallel / repeat runs never collide on VIN. */
export function makeCarFlowIds(): CarFlowIds {
  const stamp = Date.now();
  const suffix = String(stamp).slice(-12);
  return {
    stamp,
    // 17-ish char VIN-like token; unique per run
    vin: `QA${suffix}`.padEnd(17, '0').slice(0, 17),
    lot: `9${suffix}`.slice(0, 12),
    merchantName: `qa+car-flow-${stamp}@test.local`,
    purchaseShipping: 1000,
    saleShipping: 1500,
    paymentAmount: 400,
  };
}

export async function warmCsrf(page: Page) {
  await page.evaluate(async () => {
    await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' }).catch(() => null);
  });
}

/** Unlock the purchases page PIN gate (5 digit OTP inputs). */
export async function unlockPurchasesPin(page: Page) {
  await page.goto('/purchases');
  await expect(page).not.toHaveURL(/\/login/);

  const addCarBtn = page.getByRole('button', { name: /إضافة سيارة|Add Car/i });
  if (await addCarBtn.isVisible().catch(() => false)) {
    return;
  }

  const digits = page.locator('.pin-otp__digit');
  await expect(digits.first()).toBeVisible({ timeout: 20_000 });

  for (let i = 0; i < PURCHASES_PIN.length; i++) {
    await digits.nth(i).fill(PURCHASES_PIN[i]!);
  }

  await expect(addCarBtn).toBeVisible({ timeout: 15_000 });
}

export async function findCarByVin(page: Page, vin: string) {
  const res = await browserGet(page, `/api/getIndexCar?q=${encodeURIComponent(vin)}&limit=50&page=1`);
  const rows: any[] = res.body?.data || [];
  const car = rows.find((r) => String(r.vin || '') === vin) || rows[0] || null;
  return { ok: res.ok, status: res.status, car, body: res.body };
}

export async function softDeleteCarAndMerchant(
  page: Page,
  carId: number | undefined,
  clientId: number | undefined,
) {
  try {
    if (carId) {
      await browserPost(page, '/api/DelCar', { id: carId });
    }
    if (clientId) {
      await browserPost(page, '/api/delClient', { id: clientId });
    }
  } catch {
    /* page may already be closed after timeout */
  }
}

/** Close receipt print popups opened after car payment. */
export function dismissPaymentPopups(page: Page) {
  page.on('popup', async (popup) => {
    try {
      await popup.close();
    } catch {
      /* already closed */
    }
  });
}

export async function searchCarsList(page: Page, query: string) {
  const search = page.locator('.erp-search__input').first();
  await expect(search).toBeVisible({ timeout: 15_000 });
  await search.fill('');
  await search.fill(query);
}
