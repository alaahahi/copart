import { Page, Response, test } from '@playwright/test';
import { createSoftAssert } from './api';

export type HealthRoute = {
  name: string;
  path: string;
};

const LARAVEL_ERROR_RE = /Server Error|Whoops!|Whoops|SQLSTATE|Illuminate\\|Fatal error/i;

export async function checkRoute(
  page: Page,
  route: HealthRoute,
  response: Response | null,
  pageErrors: string[],
): Promise<string[]> {
  const label = `${route.name} (${route.path})`;
  const issues: string[] = [];
  const status = response?.status() ?? 0;

  if (status === 500) {
    issues.push(`${label}: HTTP 500 | استجابة 500`);
  } else if (status <= 0 || status >= 500) {
    issues.push(
      `${label}: unacceptable HTTP ${status || 'no response'} | رمز HTTP غير مقبول ${status || 'لا استجابة'}`,
    );
  }

  if (/\/login/i.test(page.url())) {
    issues.push(`${label}: redirected to login | أُعيد التوجيه لصفحة الدخول`);
  }

  const bodyVisible = await page.locator('body').isVisible().catch(() => false);
  if (!bodyVisible) {
    issues.push(`${label}: body not visible | الصفحة لم تُحمَّل`);
  }

  const html = await page.content().catch(() => '');
  const text = await page.locator('body').innerText().catch(() => '');
  if (LARAVEL_ERROR_RE.test(`${html}\n${text}`)) {
    issues.push(`${label}: Laravel error page detected | صفحة خطأ Laravel ظاهرة`);
  }

  if (pageErrors.length > 0) {
    const sample = pageErrors.slice(0, 3).join(' | ');
    issues.push(`${label}: uncaught pageerror — ${sample} | أخطاء JS غير ملتقطة`);
  }

  return issues;
}

/**
 * Soft-visit a list of authenticated routes; collect every failure in one assertAll.
 */
export async function runSystemHealthChunk(
  page: Page,
  routes: HealthRoute[],
  timeoutMs = 120_000,
): Promise<void> {
  test.setTimeout(timeoutMs);

  const { softAssert, assertAll } = createSoftAssert();
  const failedRoutes: string[] = [];

  for (const route of routes) {
    const pageErrors: string[] = [];
    const onError = (err: Error) => {
      pageErrors.push(err.message);
    };
    page.on('pageerror', onError);

    let response: Response | null = null;
    try {
      response = await page.goto(route.path, {
        waitUntil: 'domcontentloaded',
        timeout: 45_000,
      });
      await page.waitForLoadState('networkidle', { timeout: 8_000 }).catch(() => null);
    } catch (err) {
      const msg = err instanceof Error ? err.message : String(err);
      softAssert(
        false,
        `${route.name} (${route.path}): فشل التنقل — ${msg}`,
        `${route.name} (${route.path}): navigation failed — ${msg}`,
      );
      failedRoutes.push(route.path);
      page.off('pageerror', onError);
      continue;
    }

    const issues = await checkRoute(page, route, response, pageErrors);
    page.off('pageerror', onError);

    if (issues.length > 0) {
      failedRoutes.push(route.path);
      for (const issue of issues) {
        const [en, ar] = issue.split(' | ');
        softAssert(false, ar || issue, en || issue);
      }
    }
  }

  if (failedRoutes.length > 0) {
    softAssert(
      false,
      `المسارات الفاشلة: ${[...new Set(failedRoutes)].join(', ')}`,
      `Failed routes: ${[...new Set(failedRoutes)].join(', ')}`,
    );
  }

  assertAll();
}
