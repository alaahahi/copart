import { asNumber } from "@/utils/formatMoney";

/**
 * Remaining balance on a car (sales total − paid).
 * @param {Record<string, unknown>|null|undefined} car
 * @param {{ totalKey?: string, paidKey?: string }} [options]
 * @returns {number}
 */
export function carRemaining(car, options = {}) {
  const totalKey = options.totalKey ?? "total_s";
  const paidKey = options.paidKey ?? "paid";
  return asNumber(car?.[totalKey]) - asNumber(car?.[paidKey]);
}

/**
 * Payment badge from amounts (not `results`):
 * - remaining ≤ 0 → مدفوع
 * - paid > 0 && remaining > 0 → مدفوع جزئي
 * - else → غير مدفوع
 *
 * @param {Record<string, unknown>|null|undefined} car
 * @param {{ totalKey?: string, paidKey?: string }} [options]
 * @returns {{ status: 'paid'|'partially_paid'|'unpaid', labelKey: string, class: string }}
 */
export function carPaymentStatusMeta(car, options = {}) {
  const paid = asNumber(car?.[options.paidKey ?? "paid"]);
  const remaining = carRemaining(car, options);

  if (remaining <= 0) {
    return {
      status: "paid",
      labelKey: "analytics_paid",
      class:
        "bg-emerald-700 text-white font-bold ring-1 ring-emerald-800 dark:bg-emerald-700 dark:text-white dark:ring-emerald-400",
    };
  }

  if (paid > 0) {
    return {
      status: "partially_paid",
      labelKey: "partially_paid",
      class:
        "bg-amber-500 text-slate-950 font-bold ring-1 ring-amber-600 dark:bg-amber-500 dark:text-slate-950 dark:ring-amber-300",
    };
  }

  return {
    status: "unpaid",
    labelKey: "unpaid",
    class:
      "bg-rose-700 text-white font-bold ring-1 ring-rose-800 dark:bg-rose-700 dark:text-white dark:ring-rose-400",
  };
}

/**
 * Default grid card surface from payment status (dark-safe).
 * @param {Record<string, unknown>|null|undefined} car
 * @param {{ totalKey?: string, paidKey?: string, highlighted?: boolean }} [options]
 * @returns {string}
 */
export function carPaymentGridCardClass(car, options = {}) {
  const base =
    "rounded-xl border px-3 py-2 shadow-sm transition hover:shadow-md";

  if (options.highlighted) {
    return `${base} border-amber-400 bg-amber-300/30 ring-2 ring-amber-400 dark:border-amber-400 dark:bg-amber-400/20 dark:ring-amber-400`;
  }

  const { status } = carPaymentStatusMeta(car, options);
  if (status === "paid") {
    return `${base} border-emerald-600 bg-emerald-950/40 dark:border-emerald-600 dark:bg-emerald-950`;
  }
  if (status === "partially_paid") {
    return `${base} border-amber-500 bg-amber-950/30 dark:border-amber-500 dark:bg-amber-950/50`;
  }
  if (status === "unpaid" && asNumber(car?.[options.totalKey ?? "total_s"]) > 0) {
    return `${base} border-rose-600 bg-rose-950/30 dark:border-rose-600 dark:bg-rose-950/50`;
  }
  return `${base} border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600`;
}

export default carPaymentStatusMeta;
