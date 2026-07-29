import { asNumber } from "@/utils/formatMoney";

/**
 * Remaining balance on a car: totalKey − paid − discount.
 * Default totalKey is sales total (`total_s`). Pass `totalKey: "total"` for purchases.
 * @param {Record<string, unknown>|null|undefined} car
 * @param {{ totalKey?: string, paidKey?: string, discountKey?: string|false }} [options]
 * @returns {number}
 */
export function carRemaining(car, options = {}) {
  const totalKey = options.totalKey ?? "total_s";
  const paidKey = options.paidKey ?? "paid";
  const discountKey = options.discountKey === false ? null : (options.discountKey ?? "discount");
  const discount = discountKey ? asNumber(car?.[discountKey]) : 0;
  return asNumber(car?.[totalKey]) - asNumber(car?.[paidKey]) - discount;
}

/**
 * Payment badge from amounts (not `results`):
 * - remaining ≤ 0 → مدفوع
 * - paid > 0 && remaining > 0 → مدفوع جزئي
 * - else → غير مدفوع
 *
 * @param {Record<string, unknown>|null|undefined} car
 * @param {{ totalKey?: string, paidKey?: string, discountKey?: string|false }} [options]
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
 * Table row surfaces from payment status.
 *
 * Sales (default): paid=green, partial=amber, unpaid+total>0=rose
 * Purchase (`scheme: "purchase"`): paid=green, partial=red, unpaid=default
 *
 * @param {Record<string, unknown>|null|undefined} car
 * @param {{ totalKey?: string, paidKey?: string, discountKey?: string|false, scheme?: 'sales'|'purchase' }} [options]
 * @returns {string}
 */
export function carPaymentRowClass(car, options = {}) {
  const scheme = options.scheme ?? "sales";
  const { status } = carPaymentStatusMeta(car, options);
  const total = asNumber(car?.[options.totalKey ?? "total_s"]);

  if (status === "paid") {
    return "bg-emerald-100 text-slate-800 dark:bg-emerald-900 dark:text-slate-100";
  }

  if (status === "partially_paid") {
    if (scheme === "purchase") {
      return "bg-rose-100 text-slate-800 dark:bg-rose-900 dark:text-slate-100";
    }
    return "bg-amber-100 text-slate-800 dark:bg-amber-900 dark:text-slate-100";
  }

  if (scheme === "purchase") {
    return "bg-white text-slate-800 dark:bg-slate-900 dark:text-slate-100";
  }

  if (status === "unpaid" && total > 0) {
    return "bg-rose-100 text-slate-800 dark:bg-rose-900 dark:text-slate-100";
  }

  return "bg-white text-slate-800 dark:bg-slate-900 dark:text-slate-100";
}

/**
 * Default grid card surface from payment status (dark-safe).
 * @param {Record<string, unknown>|null|undefined} car
 * @param {{ totalKey?: string, paidKey?: string, discountKey?: string|false, scheme?: 'sales'|'purchase', highlighted?: boolean }} [options]
 * @returns {string}
 */
export function carPaymentGridCardClass(car, options = {}) {
  const base =
    "rounded-xl border px-3 py-2 shadow-sm transition hover:shadow-md";
  const scheme = options.scheme ?? "sales";

  if (options.highlighted) {
    return `${base} border-amber-400 bg-amber-300/30 ring-2 ring-amber-400 dark:border-amber-400 dark:bg-amber-400/20 dark:ring-amber-400`;
  }

  const { status } = carPaymentStatusMeta(car, options);
  if (status === "paid") {
    return `${base} border-emerald-600 bg-emerald-950/40 dark:border-emerald-600 dark:bg-emerald-950`;
  }
  if (status === "partially_paid") {
    if (scheme === "purchase") {
      return `${base} border-rose-600 bg-rose-950/30 dark:border-rose-600 dark:bg-rose-950/50`;
    }
    return `${base} border-amber-500 bg-amber-950/30 dark:border-amber-500 dark:bg-amber-950/50`;
  }
  if (
    scheme !== "purchase" &&
    status === "unpaid" &&
    asNumber(car?.[options.totalKey ?? "total_s"]) > 0
  ) {
    return `${base} border-rose-600 bg-rose-950/30 dark:border-rose-600 dark:bg-rose-950/50`;
  }
  return `${base} border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600`;
}

/**
 * Allow car soft-delete only when unpaid / not settled.
 * Hide when paid > 0 OR fully settled (remaining ≤ 0 with total > 0),
 * including discount-only settlement.
 *
 * @param {Record<string, unknown>|null|undefined} car
 * @param {{ totalKey?: string, paidKey?: string, discountKey?: string|false }} [options]
 * @returns {boolean}
 */
export function canDeleteCar(car, options = {}) {
  const paid = asNumber(car?.[options.paidKey ?? "paid"]);
  if (paid > 0) {
    return false;
  }
  const total = asNumber(car?.[options.totalKey ?? "total_s"]);
  const remaining = carRemaining(car, options);
  if (total > 0 && remaining <= 0) {
    return false;
  }
  return true;
}

export default carPaymentStatusMeta;
