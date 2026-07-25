#
Playwright E2E — Shipping ERP
اختبارات نهاية-إلى-نهاية / End-to-end tests
============================================

## Prerequisites / المتطلبات

1. App running locally (`APP_URL` in `.env`, e.g. `https://copart.test`)
2. Seeded admin: `admin@admin.com` / `12345678` (AdminSeeder)
3. Node deps + Chromium:

```bash
npm install
npx playwright install chromium
```

Optional overrides:

```env
QA_E2E_ENABLED=true
E2E_BASE_URL=https://copart.test
E2E_ADMIN_EMAIL=admin@admin.com
E2E_ADMIN_PASSWORD=12345678
QA_E2E_TIMEOUT=900
```

> **Live / production:** keep `QA_E2E_ENABLED=false` (default). Routes `/qa/e2e*` return **404** until enabled.
> **إعادة التفعيل محلياً:** ضع `QA_E2E_ENABLED=true` في `.env` ثم `php artisan config:clear`.

## Run locally / التشغيل محلياً

```bash
# كل اختبارات e2e (smoke + accounting + system chunks)
npm run test:e2e

# سلامة المحاسبة فقط
npm run test:e2e:accounting

# فحص النظام (كل أجزاء الصفحات في أمر واحد)
npm run test:e2e:system
# أو نفس الأمر:
npm run test:e2e:health

# أجزاء منفصلة
npm run test:e2e:system:core
npm run test:e2e:system:accounting
npm run test:e2e:system:admin
```

أو عبر Artisan (المجاميع `system` / `health` / `all` تشغّل الأجزاء **بالتتابع** كعمليات Process منفصلة ثم تدمج `last-e2e.json`):

```bash
php artisan qa:e2e --suite=accounting
php artisan qa:e2e --suite=system-core
php artisan qa:e2e --suite=system-accounting
php artisan qa:e2e --suite=system-admin
php artisan qa:e2e --suite=system          # = تتابع الأجزاء الثلاثة
php artisan qa:e2e --suite=health          # alias لـ system
php artisan qa:e2e --suite=all             # accounting ثم أجزاء النظام
```

## Blade monitoring page / صفحة المراقبة

بعد الدخول كـ **admin** (`type_id = 1`):

- URL: `/qa/e2e`  (named route `qa.e2e`)
- مثال: `https://copart.test/qa/e2e`

الأزرار:

| الزر | Suite / سلوك |
|------|----------------|
| تشغيل اختبارات المحاسبة | `accounting` (`@accounting`) |
| فحص أساسي | `system-core` |
| فحص المحاسبة | `system-accounting` (صفحات دفتر/محفظة/قاصة) |
| فحص الإدارة | `system-admin` |
| فحص شامل (تتابعي) | يطلب الأجزاء الثلاثة **طلب HTTP منفصل لكل جزء** ثم يدمج العرض |
| تشغيل كل اختبارات e2e | تتابع: accounting ثم الأجزاء الثلاثة |

آخر تشغيل يُحفظ في `storage/app/qa/last-e2e.json`.

### JSON API contract / عقد الـ API

- `POST /qa/e2e/run` و `GET /qa/e2e/last` **دائماً** JSON (حتى عند الفشل).
- الواجهة لا تستدعي `response.json()` مباشرة: إن رجع HTML (مهلة nginx/PHP) تظهر رسالة عربية مع رمز الحالة + مقتطف.
- مهلة Symfony Process الافتراضية: **900 ثانية** لكل جزء (`QA_E2E_TIMEOUT`). PHP `max_execution_time` يُرفع أثناء التشغيل.

الضيوف لا يصلون للصفحة (auth + admin). الأوامر ثابتة — بدون حقن أوامر.

## What is covered / التغطية

| Test | Tags | Purpose |
|------|------|---------|
| `auth.setup.ts` | `@setup` | تسجيل دخول مرة واحدة وحفظ `storageState` |
| `smoke.spec.ts` | — | تحميل سريع لـ dashboard / accounting / ledger |
| `accounting-integrity.spec.ts` | `@accounting` | تاجر QA + وصل قبض + مدين=دائن + ميزان مراجعة |
| `system-core.spec.ts` | `@system @health @system-core` | dashboard, purchases, sales, clients |
| `system-accounting.spec.ts` | `@system @health @system-accounting` | accounting, ledger, treasury, wallet |
| `system-admin.spec.ts` | `@system @health @system-admin` | analytics, settings, sync, `/qa/e2e` |
| `car-flow.phase2.spec.ts` | — | stubs متجاوزة لتدفق السيارة الكامل |

تجار QA بأسماء `qa+…@test.local` ويُحذَفون ناعماً بعد اختبار المحاسبة.

## Notes / ملاحظات

- للحسابات: فضّل asserts عبر API؛ الجلسة من واجهة الدخول.
- شهادات HTTPS محلية: `ignoreHTTPSErrors` مفعّل عندما يكون `APP_URL` https.
- لا ترفع `tests/e2e/.auth/` (مُستثنى من git).
- تجنّب تشغيل «كل النظام» كعملية Playwright واحدة طويلة من لوحة QA — استخدم الأجزاء أو «فحص شامل (تتابعي)».
