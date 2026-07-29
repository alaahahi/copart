import { test, expect } from '@playwright/test';
import { browserGet, createSoftAssert, round2 } from './helpers/api';
import {
  dismissPaymentPopups,
  findCarByVin,
  makeCarFlowIds,
  searchCarsList,
  softDeleteCarAndMerchant,
  unlockPurchasesPin,
  warmCsrf,
} from './helpers/carFlow';

/**
 * Full car lifecycle (purchases → sales pricing → client payment).
 *
 * Uses the real Inertia pages + modal fields where selectors are stable.
 * Soft-asserts bilingual messages for the QA Blade panel.
 *
 * Tags: @car-flow
 * Run: npm run test:e2e:car-flow
 *      php artisan qa:e2e --suite=car-flow
 *
 * Env (same as other e2e suites):
 *   APP_URL / E2E_BASE_URL
 *   E2E_ADMIN_EMAIL (default admin@admin.com)
 *   E2E_ADMIN_PASSWORD (default 12345678)
 */

test.describe('Car lifecycle purchase → sale → payment @car-flow', () => {
  test('add car on purchases, set sale on sales, pay on client show', async ({ page }) => {
    test.setTimeout(180_000);
    const { softAssert, assertAll } = createSoftAssert();
    const ids = makeCarFlowIds();
    dismissPaymentPopups(page);

    let carId: number | undefined;
    let clientId: number | undefined;

    try {
      await page.goto('/dashboard');
      await expect(page).not.toHaveURL(/\/login/);
      await warmCsrf(page);

      // --- 1) Purchases: unlock PIN + register car via ModalAddCars ---
      await unlockPurchasesPin(page);

      await page.getByRole('button', { name: /إضافة سيارة|Add Car/i }).click();
      await expect(page.locator('#vin')).toBeVisible({ timeout: 15_000 });

      // New merchant (avoids fragile vue-search-select)
      await page.getByRole('button', { name: /إضافة تاجر|Add Customer|addCustomer/i }).click();
      await expect(page.locator('#client_name')).toBeVisible();
      await page.locator('#client_name').fill(ids.merchantName);
      await page.locator('#client_phone').fill('07001234567');

      await page.locator('#vin').fill(ids.vin);
      await page.locator('#car_type').fill('QA E2E Sedan');
      await page.locator('#year').fill('2020');
      await page.locator('#car_color').fill('Black');
      await page.locator('#car_number').fill(ids.lot);
      await page.locator('#shipping_dolar').fill(String(ids.purchaseShipping));
      await page.locator('#note').fill(`QA-CAR-FLOW-${ids.stamp}`);

      const addResponsePromise = page.waitForResponse(
        (r) => r.url().includes('/api/addCars') && r.request().method() === 'POST',
        { timeout: 45_000 },
      );
      await page.locator('.car-modal-footer .car-btn-primary').filter({ hasText: /نعم|Yes/i }).click();
      const addResponse = await addResponsePromise;
      softAssert(
        addResponse.ok(),
        `فشل إضافة السيارة من المشتريات: HTTP ${addResponse.status()}`,
        `Purchases addCars failed: HTTP ${addResponse.status()}`,
      );

      // Modal should close; locate created car
      await expect(page.locator('#vin')).toBeHidden({ timeout: 30_000 });

      const foundAfterAdd = await findCarByVin(page, ids.vin);
      softAssert(
        foundAfterAdd.ok && !!foundAfterAdd.car,
        `لم تظهر السيارة في getIndexCar بعد الإضافة (VIN ${ids.vin})`,
        `Car not found after addCars (VIN ${ids.vin})`,
      );
      carId = foundAfterAdd.car?.id ? Number(foundAfterAdd.car.id) : undefined;
      clientId = foundAfterAdd.car?.client_id
        ? Number(foundAfterAdd.car.client_id)
        : foundAfterAdd.car?.client?.id
          ? Number(foundAfterAdd.car.client.id)
          : undefined;

      softAssert(!!carId, 'معرّف السيارة مفقود بعد الإضافة', 'Missing car id after create');
      softAssert(!!clientId, 'معرّف التاجر مفقود بعد الإضافة', 'Missing client id after create');

      const purchaseTotal = round2(Number(foundAfterAdd.car?.total ?? 0));
      softAssert(
        purchaseTotal === ids.purchaseShipping,
        `تكلفة الشراء غير متوقعة: ${purchaseTotal} متوقع ${ids.purchaseShipping}`,
        `Purchase total mismatch: ${purchaseTotal} expected ${ids.purchaseShipping}`,
      );

      // UI: car appears in purchases list search
      await searchCarsList(page, ids.vin);
      await expect(page.getByText(ids.vin, { exact: false }).first()).toBeVisible({
        timeout: 25_000,
      });

      // --- 2) Sales: set sale pricing via ModalEditCar_S ---
      await page.goto('/sales');
      await expect(page).not.toHaveURL(/\/login/);
      await searchCarsList(page, ids.vin);
      await expect(page.getByText(ids.vin, { exact: false }).first()).toBeVisible({
        timeout: 25_000,
      });

      const salesRow = page.locator('tr', { hasText: ids.vin }).first();
      // List view action buttons have no title attribute (unlike grid view).
      await salesRow.locator('td').filter({ has: page.locator('button') }).locator('button').first().click();
      await expect(page.locator('#shipping_dolar')).toBeVisible({ timeout: 15_000 });
      await page.locator('#shipping_dolar').fill(String(ids.saleShipping));

      const saleResponsePromise = page.waitForResponse(
        (r) => r.url().includes('/api/updateCarsS') && r.request().method() === 'POST',
        { timeout: 45_000 },
      );
      await page.locator('.car-modal-footer .car-btn-primary').filter({ hasText: /نعم|Yes/i }).click();
      const saleResponse = await saleResponsePromise;
      softAssert(
        saleResponse.ok(),
        `فشل تحديث أسعار البيع: HTTP ${saleResponse.status()}`,
        `Sales updateCarsS failed: HTTP ${saleResponse.status()}`,
      );

      const foundAfterSale = await findCarByVin(page, ids.vin);
      const totalS = round2(Number(foundAfterSale.car?.total_s ?? 0));
      softAssert(
        totalS === ids.saleShipping,
        `سعر البيع (total_s) غير متوقع: ${totalS} متوقع ${ids.saleShipping}`,
        `Sale total_s mismatch: ${totalS} expected ${ids.saleShipping}`,
      );

      // --- 3) Client payment via ModalAddCarPayment on showClients ---
      softAssert(!!clientId, 'لا يمكن فتح صفحة التاجر بدون client_id', 'Cannot open client show without client_id');
      if (clientId) {
        await page.goto(`/showClients/${clientId}`);
        await expect(page).not.toHaveURL(/\/login/);
        await expect(page.getByText(ids.vin, { exact: false }).first()).toBeVisible({
          timeout: 30_000,
        });

        const payBtn = page
          .locator('tr', { hasText: ids.vin })
          .first()
          .getByTitle(/دفع|Pay/i);
        // Grid view actions may sit outside <tr> — fall back to first pay title on page
        if (await payBtn.count()) {
          await payBtn.click();
        } else {
          await page.getByTitle(/دفع|Pay/i).first().click();
        }

        await expect(page.locator('#amountPayment')).toBeVisible({ timeout: 15_000 });
        await page.locator('#amountPayment').fill(String(ids.paymentAmount));

        const payResponsePromise = page.waitForResponse(
          (r) => r.url().includes('/api/addPaymentCar') && r.request().method() === 'GET',
          { timeout: 45_000 },
        );
        await page
          .locator('#amountPayment')
          .locator('xpath=ancestor::*[contains(@class,"rounded-xl")][1]')
          .locator('button')
          .filter({ hasText: /نعم|Yes/i })
          .click();
        const payResponse = await payResponsePromise;
        softAssert(
          payResponse.ok(),
          `فشل تسجيل دفعة السيارة: HTTP ${payResponse.status()}`,
          `addPaymentCar failed: HTTP ${payResponse.status()}`,
        );
      }

      const foundAfterPay = await findCarByVin(page, ids.vin);
      const paid = round2(Number(foundAfterPay.car?.paid ?? 0));
      softAssert(
        paid === ids.paymentAmount,
        `المدفوع على السيارة غير متوقع: ${paid} متوقع ${ids.paymentAmount}`,
        `Car paid amount mismatch: ${paid} expected ${ids.paymentAmount}`,
      );

      // --- 4) Trial balance still balances after the lifecycle ---
      const tb = await browserGet(page, '/api/ledgerTrialBalance?currency=$');
      softAssert(tb.ok, 'فشل جلب ميزان المراجعة', 'Trial balance request failed');
      const d = round2(Number(tb.body?.total_debit ?? 0));
      const c = round2(Number(tb.body?.total_credit ?? 0));
      softAssert(
        d === c,
        `ميزان المراجعة غير متوازن بعد تدفق السيارة: مدين ${d} ≠ دائن ${c}`,
        `Trial balance unbalanced after car flow: debit ${d} ≠ credit ${c}`,
      );
    } finally {
      await softDeleteCarAndMerchant(page, carId, clientId);
    }

    assertAll();
  });
});
