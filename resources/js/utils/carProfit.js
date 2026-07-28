import { asNumber } from "@/utils/formatMoney";

/**
 * Car is "in sales" when it has a sales total (total_s > 0).
 * Purchase-only cars must not show cost as a negative "loss".
 *
 * @param {Record<string, unknown>|null|undefined} car
 * @returns {boolean}
 */
export function carHasSalePricing(car) {
  return asNumber(car?.total_s) > 0;
}

/**
 * Profit = total_s − total when sale pricing exists; otherwise null (not calculated).
 *
 * @param {Record<string, unknown>|null|undefined} car
 * @returns {number|null}
 */
export function carProfit(car) {
  if (!carHasSalePricing(car)) {
    return null;
  }
  return asNumber(car?.total_s) - asNumber(car?.total);
}
