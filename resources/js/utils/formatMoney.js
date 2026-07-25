/**
 * Project-wide money/number display helpers.
 * Display only — does not change stored values or accounting math.
 *
 * Rule: up to 2 decimals, strip trailing zeros.
 *   1600    → "1,600"
 *   1600.00 → "1,600"
 *   10.50   → "10.5"
 *   10.5    → "10.5"
 */

const DEFAULT_LOCALE = "en-US";

/**
 * Coerce to a finite number (NaN/null/undefined → 0).
 * @param {unknown} value
 * @returns {number}
 */
export function asNumber(value) {
  if (typeof value === "number") {
    return Number.isFinite(value) ? value : 0;
  }
  if (value == null || value === "") {
    return 0;
  }
  const n = Number(String(value).replace(/,/g, "").trim());
  return Number.isFinite(n) ? n : 0;
}

/**
 * Format a number for display with thousand separators.
 * Strips trailing zeros; keeps real fractional digits up to maxDecimals.
 *
 * @param {unknown} value
 * @param {{ maxDecimals?: number, locale?: string }} [options]
 * @returns {string}
 */
export function formatNumber(value, options = {}) {
  const maxDecimals = options.maxDecimals ?? 2;
  const locale = options.locale ?? DEFAULT_LOCALE;
  const n = asNumber(value);

  return n.toLocaleString(locale, {
    minimumFractionDigits: 0,
    maximumFractionDigits: maxDecimals,
  });
}

/**
 * Format a money amount for display.
 * USD/$ → up to 2 decimals (trailing zeros stripped).
 * IQD / other → whole numbers (0 decimals).
 *
 * @param {unknown} value
 * @param {string} [currency='$']
 * @returns {string}
 */
export function formatMoney(value, currency = "$") {
  const isUsd = currency === "$" || currency === "USD" || currency === "usd";
  return formatNumber(value, { maxDecimals: isUsd ? 2 : 0 });
}

export default formatMoney;
