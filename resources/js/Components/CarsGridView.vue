<script setup>
import { computed } from "vue";
import { useToast } from "vue-toastification";
import { asNumber, formatMoney } from "@/utils/formatMoney";
import { resolveCarLogoUrl } from "@/utils/carLogo";
import {
  carRemaining,
  carPaymentStatusMeta,
  carPaymentGridCardClass,
} from "@/utils/carPaymentStatus";
import { carProfit as profitOf } from "@/utils/carProfit";

const props = defineProps({
  /** Cars to render */
  cars: { type: Array, default: () => [] },
  /**
   * Field layout:
   * - sales / client: *_s America + Erbil line items + remaining
   * - purchase: purchase America + Erbil line items + profit
   */
  variant: {
    type: String,
    default: "sales",
    validator: (v) => ["sales", "purchase", "client"].includes(v),
  },
  /** Show client name above title (Sales / purchases) */
  showClient: { type: Boolean, default: null },
  /** Empty-state message */
  emptyMessage: { type: String, default: "" },
  /** Optional VIN query — highlights matching cards */
  highlightQuery: { type: String, default: "" },
  /** Optional override for article class */
  cardClassFn: { type: Function, default: null },
});

const toast = useToast();

const isPurchase = computed(() => props.variant === "purchase");
const isClient = computed(() => props.variant === "client");
const showClientName = computed(() =>
  props.showClient != null ? props.showClient : !isClient.value
);

/** Erbil breakdown chips (same as Clients/Show) — hide zeros via hasMoney */
const ERBIL_DETAIL_CHIPS = [
  { salesKey: "expenses_s", purchaseKey: "expenses", labelKey: "erbil_shipping" },
  { salesKey: "erbil_clearance_s", purchaseKey: "erbil_clearance", labelKey: "erbil_clearance" },
  { salesKey: "erbil_transfer_s", purchaseKey: "erbil_transfer", labelKey: "erbil_transfer_fee" },
  { salesKey: "erbil_border_repair_s", purchaseKey: "erbil_border_repair", labelKey: "erbil_border_repair" },
  { salesKey: "erbil_customs_s", purchaseKey: "erbil_customs", labelKey: "erbil_customs" },
];

const hasText = (v) => v != null && String(v).trim() !== "";
const hasMoney = (v) => asNumber(v) !== 0;
const money = (v) => formatMoney(v, "$");

const field = (car, salesKey, purchaseKey) =>
  isPurchase.value ? car?.[purchaseKey] : car?.[salesKey];

/** Purchases: cost total; Sales/Clients: sales total_s. */
const paymentOptions = (car) =>
  isPurchase.value
    ? { totalKey: "total", scheme: "purchase" }
    : { totalKey: "total_s", scheme: "sales" };

const remainingOf = (car) => carRemaining(car, paymentOptions(car));
const statusMeta = (car) => carPaymentStatusMeta(car, paymentOptions(car));

const brandLogoUrl = (car) =>
  resolveCarLogoUrl(car?.car_type || car?.make || car?.name);

const normalizeVinQuery = (q) => String(q || "").trim().toLowerCase();
const carMatchesVin = (car, q) => {
  const needle = normalizeVinQuery(q);
  if (!needle) return false;
  const vin = String(car?.vin || "").toLowerCase();
  const chassis = String(car?.chassis || car?.car_number || "").toLowerCase();
  return vin.includes(needle) || chassis.includes(needle);
};

const articleClass = (car) => {
  if (typeof props.cardClassFn === "function") {
    return props.cardClassFn(car);
  }
  const highlighted =
    !!normalizeVinQuery(props.highlightQuery) &&
    carMatchesVin(car, props.highlightQuery);
  return carPaymentGridCardClass(car, { ...paymentOptions(car), highlighted });
};

const getImageUrl = (name) => `/public/uploadsResized/${name}`;
const getDownloadUrl = (name) => `/public/uploads/${name}`;

const copyVinToClipboard = async (vin) => {
  const text = String(vin || "").trim();
  if (!text) return;
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text);
    } else {
      const ta = document.createElement("textarea");
      ta.value = text;
      ta.setAttribute("readonly", "");
      ta.style.position = "fixed";
      ta.style.left = "-9999px";
      document.body.appendChild(ta);
      ta.select();
      document.execCommand("copy");
      document.body.removeChild(ta);
    }
    toast.success("تم نسخ رقم الشاصي", {
      timeout: 2500,
      position: "bottom-right",
      rtl: true,
    });
  } catch (e) {
    console.error(e);
    toast.error("فشل نسخ رقم الشاصي", {
      timeout: 3000,
      position: "bottom-right",
      rtl: true,
    });
  }
};
</script>

<template>
  <div class="grid w-full grid-cols-1 gap-3">
    <article
      v-for="car in cars"
      :key="'g-' + car.id"
      :class="articleClass(car)"
    >
      <!-- Zone A: logo + identity + badge/actions -->
      <div class="flex items-start gap-2">
        <div
          v-if="brandLogoUrl(car)"
          class="order-first flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-violet-900 to-fuchsia-900 ring-1 ring-violet-700/60 sm:h-14 sm:w-14"
          aria-hidden="true"
        >
          <img
            :src="brandLogoUrl(car)"
            alt=""
            class="h-8 w-8 object-contain brightness-0 invert sm:h-9 sm:w-9"
            loading="lazy"
            @error="
              ($event) => {
                $event.target.style.display = 'none';
                $event.target.parentElement?.classList.add('hidden');
              }
            "
          />
        </div>

        <div class="min-w-0 flex-1">
          <div
            v-if="showClientName && hasText(car.client?.name)"
            class="mb-0.5 text-sm font-semibold text-slate-700 dark:text-slate-200"
          >
            {{ car.client.name }}
          </div>
          <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
            <h3
              class="min-w-0 text-base font-bold leading-tight text-slate-900 dark:text-white sm:text-lg"
            >
              <template v-if="hasText(car.car_type)">{{ car.car_type }}</template>
              <span
                v-if="hasText(car.year)"
                class="ms-1 font-semibold text-slate-500 dark:text-slate-300"
              >{{ car.year }}</span>
              <span
                v-if="!hasText(car.car_type) && !hasText(car.year)"
                class="text-slate-400"
              >—</span>
            </h3>
            <span
              v-if="hasText(car.car_color)"
              class="text-sm text-slate-600 dark:text-slate-200"
            >{{ car.car_color }}</span>
            <span
              v-if="hasText(car.car_number)"
              class="font-mono text-sm font-semibold text-slate-700 dark:text-slate-100"
              dir="ltr"
            >LOT: {{ car.car_number }}</span>
            <span
              v-if="hasText(car.shipping_route?.name)"
              class="rounded-md border border-slate-300 bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
            >
              {{ $t("shipping_route") }}: {{ car.shipping_route.name }}
            </span>
          </div>

          <div
            v-if="hasText(car.vin) || hasText(car.note)"
            class="mt-0.5 flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-0.5"
          >
            <template v-if="hasText(car.vin)">
              <span class="shrink-0 text-xs font-semibold text-slate-500 dark:text-slate-300">
                {{ $t("vin") }}
              </span>
              <span
                class="inline-flex min-w-0 max-w-full items-center gap-1.5"
                dir="ltr"
              >
                <span
                  class="min-w-0 break-all font-mono text-lg font-bold leading-snug tracking-wide text-slate-900 dark:text-white sm:text-xl"
                >
                  {{ car.vin }}
                </span>
                <button
                  type="button"
                  class="print:hidden inline-flex shrink-0 items-center justify-center rounded-md border border-slate-300 bg-slate-100 p-1 text-slate-700 hover:bg-slate-200 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
                  title="نسخ رقم الشاصي"
                  aria-label="نسخ رقم الشاصي"
                  @click.stop="copyVinToClipboard(car.vin)"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="h-4 w-4"
                    aria-hidden="true"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"
                    />
                  </svg>
                </button>
              </span>
            </template>
            <span
              v-if="hasText(car.note)"
              class="min-w-0 max-w-[min(100%,14rem)] truncate text-sm text-slate-700 print:hidden dark:text-slate-200 sm:max-w-[min(100%,18rem)]"
              :title="car.note"
            >
              <span class="text-xs font-semibold text-slate-500 dark:text-slate-300">{{ $t("note") }}:</span>
              {{ car.note }}
            </span>
          </div>
        </div>

        <div class="order-last flex shrink-0 flex-col items-end gap-1">
          <span
            class="rounded-md px-2 py-0.5 text-xs font-bold tracking-wide sm:text-sm"
            :class="statusMeta(car).class"
          >
            {{ $t(statusMeta(car).labelKey) }}
          </span>
          <div class="flex flex-wrap items-center justify-end gap-1 print:hidden">
            <slot name="actions" :car="car" />
          </div>
        </div>
      </div>

      <!-- Zone B: costs + totals -->
      <div
        class="mt-1.5 grid grid-cols-1 gap-1.5 md:grid-cols-[minmax(0,1fr)_11.5rem] md:items-start lg:grid-cols-[minmax(0,1fr)_13rem]"
      >
        <dl class="grid grid-cols-2 gap-1 sm:grid-cols-3 lg:grid-cols-5">
          <div
            v-if="hasMoney(field(car, 'shipping_dolar_s', 'shipping_dolar'))"
            class="rounded-md border border-sky-300 bg-sky-50 px-2 py-1.5 dark:border-sky-600 dark:bg-slate-800"
          >
            <dt class="text-xs font-semibold leading-tight text-sky-800 dark:text-sky-200">
              {{ $t("car_price_usa") }}
            </dt>
            <dd class="mt-0.5 font-mono text-sm font-semibold tabular-nums text-slate-900 dark:text-white">
              {{ money(field(car, "shipping_dolar_s", "shipping_dolar")) }}
            </dd>
          </div>
          <div
            v-if="hasMoney(field(car, 'dinar_s', 'dinar'))"
            class="rounded-md border border-sky-300 bg-sky-50 px-2 py-1.5 dark:border-sky-600 dark:bg-slate-800"
          >
            <dt class="text-xs font-semibold leading-tight text-sky-800 dark:text-sky-200">
              {{ $t("transfer_usa") }}
            </dt>
            <dd class="mt-0.5 font-mono text-sm font-semibold tabular-nums text-slate-900 dark:text-white">
              {{ money(field(car, "dinar_s", "dinar")) }}
            </dd>
          </div>
          <div
            v-if="hasMoney(field(car, 'coc_dolar_s', 'coc_dolar'))"
            class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-800"
          >
            <dt class="text-xs font-semibold leading-tight text-slate-600 dark:text-slate-200">
              {{ $t("recovery") }}
            </dt>
            <dd class="mt-0.5 font-mono text-sm font-semibold tabular-nums text-slate-900 dark:text-white">
              {{ money(field(car, "coc_dolar_s", "coc_dolar")) }}
            </dd>
          </div>
          <div
            v-if="hasMoney(field(car, 'checkout_s', 'checkout'))"
            class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-800"
          >
            <dt class="text-xs font-semibold leading-tight text-slate-600 dark:text-slate-200">
              {{ $t("repair_expenses") }}
            </dt>
            <dd class="mt-0.5 font-mono text-sm font-semibold tabular-nums text-slate-900 dark:text-white">
              {{ money(field(car, "checkout_s", "checkout")) }}
            </dd>
          </div>

          <!-- Erbil line items (sales / client / purchase) — not a combined subtotal -->
          <template
            v-for="chip in ERBIL_DETAIL_CHIPS"
            :key="'erbil-' + car.id + '-' + chip.salesKey"
          >
            <div
              v-if="hasMoney(field(car, chip.salesKey, chip.purchaseKey))"
              class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-800"
            >
              <dt class="text-xs font-semibold leading-tight text-slate-600 dark:text-slate-200">
                {{ $t(chip.labelKey) }}
              </dt>
              <dd class="mt-0.5 font-mono text-sm font-semibold tabular-nums text-slate-900 dark:text-white">
                {{ money(field(car, chip.salesKey, chip.purchaseKey)) }}
              </dd>
            </div>
          </template>

          <div
            v-if="hasMoney(field(car, 'commission_s', 'commission'))"
            class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 dark:border-slate-700 dark:bg-slate-800"
          >
            <dt class="text-xs font-semibold leading-tight text-slate-600 dark:text-slate-200">
              {{ $t("erbil_expenses") }}
            </dt>
            <dd class="mt-0.5 font-mono text-sm font-semibold tabular-nums text-slate-900 dark:text-white">
              {{ money(field(car, "commission_s", "commission")) }}
            </dd>
          </div>
        </dl>

        <aside class="flex flex-col gap-1">
          <dl class="flex flex-col gap-1">
            <div
              v-if="hasMoney(field(car, 'total_s', 'total'))"
              class="flex items-center justify-between gap-2 rounded-md border border-slate-300 bg-slate-100 px-2 py-1.5 dark:border-slate-600 dark:bg-slate-800"
            >
              <dt class="shrink-0 text-xs font-semibold text-slate-600 dark:text-slate-200">
                {{ $t("total") }}
              </dt>
              <dd class="font-mono text-sm font-bold tabular-nums text-slate-900 dark:text-white">
                {{ money(field(car, "total_s", "total")) }}
              </dd>
            </div>
            <div
              v-if="hasMoney(car.paid)"
              class="flex flex-col gap-1 rounded-md border border-slate-300 bg-slate-100 px-2 py-1.5 dark:border-slate-600 dark:bg-slate-800"
            >
              <div class="flex items-center justify-between gap-2">
                <dt class="shrink-0 text-xs font-semibold text-slate-600 dark:text-slate-200">
                  {{ $t("paid") }}
                </dt>
                <dd class="font-mono text-sm font-bold tabular-nums text-emerald-700 dark:text-emerald-300">
                  {{ money(car.paid) }}
                </dd>
              </div>
              <ul
                v-if="Array.isArray(car.payment_allocations) && car.payment_allocations.length"
                class="space-y-0.5 border-t border-slate-200 pt-1 text-[10px] text-slate-600 dark:border-slate-600 dark:text-slate-300"
              >
                <li class="font-semibold text-sky-700 dark:text-sky-300">{{ $t("paid_from_sources") }}</li>
                <li
                  v-for="(row, ai) in car.payment_allocations"
                  :key="`alloc-${car.id}-${ai}`"
                >
                  <template v-if="row.source === 'from_balance'">{{ $t("allocation_from_balance") }}</template>
                  <template v-else-if="row.source === 'legacy_balance'">{{ $t("allocation_legacy") }}</template>
                  <template v-else>{{ $t("allocation_direct") }}</template>
                  · {{ money(row.amount) }}
                  <span v-if="row.transaction_id"> · #{{ row.transaction_id }}</span>
                </li>
              </ul>
            </div>

            <!-- Sales / client: remaining -->
            <div
              v-if="!isPurchase && hasMoney(remainingOf(car))"
              class="flex items-center justify-between gap-2 rounded-md border border-slate-300 bg-slate-100 px-2 py-1.5 dark:border-slate-600 dark:bg-slate-800"
            >
              <dt class="shrink-0 text-xs font-semibold text-slate-600 dark:text-slate-200">
                {{ $t("remaining") }}
              </dt>
              <dd
                class="font-mono text-sm font-bold tabular-nums"
                :class="
                  remainingOf(car) > 0
                    ? 'text-amber-700 dark:text-amber-300'
                    : 'text-emerald-700 dark:text-emerald-300'
                "
              >
                {{ money(remainingOf(car)) }}
              </dd>
            </div>

            <!-- Purchase: profit (only when sale pricing exists) -->
            <div
              v-if="isPurchase"
              class="flex items-center justify-between gap-2 rounded-md border border-slate-300 bg-slate-100 px-2 py-1.5 dark:border-slate-600 dark:bg-slate-800"
            >
              <dt class="shrink-0 text-xs font-semibold text-slate-600 dark:text-slate-200">
                {{ $t("profit") }}
              </dt>
              <dd
                class="font-mono text-sm font-bold tabular-nums"
                :class="
                  profitOf(car) == null
                    ? 'text-slate-500 dark:text-slate-400'
                    : profitOf(car) >= 0
                      ? 'text-emerald-700 dark:text-emerald-300'
                      : 'text-rose-700 dark:text-rose-300'
                "
              >
                <template v-if="profitOf(car) == null">{{ $t("profit_not_calculated") }}</template>
                <template v-else>{{ money(profitOf(car)) }}</template>
              </dd>
            </div>

            <div
              v-if="hasText(car.date)"
              class="flex items-center justify-between gap-2 rounded-md border border-slate-300 bg-slate-100 px-2 py-1.5 dark:border-slate-600 dark:bg-slate-800"
            >
              <dt class="shrink-0 text-xs font-semibold text-slate-600 dark:text-slate-200">
                {{ $t("date") }}
              </dt>
              <dd class="whitespace-nowrap text-sm text-slate-800 dark:text-slate-100">
                {{ car.date }}
              </dd>
            </div>
          </dl>
        </aside>
      </div>

      <!-- Attachments -->
      <div
        v-if="car.car_images?.length"
        class="mt-1.5 flex flex-wrap items-center gap-1 border-t border-slate-200 pt-1.5 print:hidden dark:border-slate-700"
      >
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-300">
          {{ $t("storage") }}
        </span>
        <a
          v-for="(image, imgIndex) in car.car_images"
          :key="imgIndex"
          :href="getDownloadUrl(image.name)"
          target="_blank"
          class="inline-block"
        >
          <img
            :src="getImageUrl(image.name)"
            alt=""
            class="inline max-h-8 max-w-12"
          />
        </a>
      </div>
    </article>

    <div
      v-if="!cars.length"
      class="col-span-full rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-600 dark:text-slate-400"
    >
      {{ emptyMessage || $t("no_data") }}
    </div>
  </div>
</template>
