import { expect, Page } from '@playwright/test';

export type SoftAssert = (condition: boolean, messageAr: string, messageEn: string) => void;

/**
 * Soft assertion helper — collects failures then throws once.
 * Messages are bilingual (Arabic / English) for the Blade QA panel.
 */
export function createSoftAssert() {
  const failures: string[] = [];

  const softAssert: SoftAssert = (condition, messageAr, messageEn) => {
    if (!condition) {
      failures.push(`${messageAr} | ${messageEn}`);
    }
  };

  const assertAll = () => {
    expect(failures, failures.join('\n')).toEqual([]);
  };

  return { softAssert, assertAll, failures };
}

export function sumLines(
  lines: Array<{ debit?: number; credit?: number }>,
): { debit: number; credit: number } {
  return lines.reduce(
    (acc, line) => ({
      debit: acc.debit + Number(line.debit ?? 0),
      credit: acc.credit + Number(line.credit ?? 0),
    }),
    { debit: 0, credit: 0 },
  );
}

export function round2(n: number): number {
  return Math.round(n * 100) / 100;
}

type BrowserApiResult = {
  ok: boolean;
  status: number;
  body: any;
};

/**
 * Call Laravel /api from inside the authenticated browser context.
 * Prefer this over page.request — Sanctum SPA auth needs same-origin cookies + CSRF.
 */
export async function browserApi(
  page: Page,
  method: 'GET' | 'POST',
  path: string,
  data?: Record<string, unknown>,
): Promise<BrowserApiResult> {
  return page.evaluate(
    async ({ method, path, data }) => {
      const xsrfMatch = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
      const xsrf = xsrfMatch ? decodeURIComponent(xsrfMatch[1]) : '';
      const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      };
      if (xsrf) {
        headers['X-XSRF-TOKEN'] = xsrf;
      }
      if (method === 'POST') {
        headers['Content-Type'] = 'application/json';
      }

      const res = await fetch(path, {
        method,
        headers,
        credentials: 'same-origin',
        body: method === 'POST' ? JSON.stringify(data ?? {}) : undefined,
      });

      let body: any = null;
      const text = await res.text();
      try {
        body = text ? JSON.parse(text) : null;
      } catch {
        body = { raw: text };
      }

      return { ok: res.ok, status: res.status, body };
    },
    { method, path, data },
  );
}

export async function browserGet(page: Page, path: string) {
  return browserApi(page, 'GET', path);
}

export async function browserPost(page: Page, path: string, data: Record<string, unknown>) {
  return browserApi(page, 'POST', path, data);
}
