<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  usdToCad: { type: Number, default: null },
  source: { type: String, default: "xe.com" },
  sourceUrl: {
    type: String,
    default: "https://www.xe.com/currencyconverter/convert/?Amount=1&From=USD&To=CAD",
  },
});

const cadAmount = ref("");

const hasRate = computed(() => {
  const n = Number(props.usdToCad);
  return Number.isFinite(n) && n > 0;
});

const formatMid = (value, digits = 5) => {
  const n = Number(value);
  if (!Number.isFinite(n)) return "—";
  return new Intl.NumberFormat("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: digits,
  }).format(n);
};

const usdFromCad = computed(() => {
  const cad = Number(cadAmount.value);
  const mid = Number(props.usdToCad);
  if (!Number.isFinite(cad) || cad <= 0 || !Number.isFinite(mid) || mid <= 0) {
    return null;
  }
  return cad / mid;
});
</script>

<template>
  <div>
    <template v-if="hasRate">
      <p class="mt-2 font-mono text-lg font-bold tabular-nums text-white" dir="ltr">
        1.00 USD = {{ formatMid(usdToCad) }} CAD
      </p>
      <p class="mt-0.5 text-[11px] text-slate-300" dir="ltr">
        1.00 CAD = {{ formatMid(1 / usdToCad) }} USD
      </p>

      <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
        <label class="block">
          <span class="mb-1 block text-[11px] font-semibold text-slate-200">
            {{ $t("dashboard_exchange_cad_amount") }}
          </span>
          <input
            v-model="cadAmount"
            type="number"
            min="0"
            step="0.01"
            inputmode="decimal"
            dir="ltr"
            class="w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2 font-mono text-sm text-white placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
            :placeholder="$t('dashboard_exchange_cad_placeholder')"
          />
        </label>
        <div>
          <p class="mb-1 text-[11px] font-semibold text-slate-200">
            {{ $t("dashboard_exchange_usd_result") }}
          </p>
          <p
            class="flex min-h-[42px] items-center rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 font-mono text-sm font-bold tabular-nums text-emerald-300"
            dir="ltr"
          >
            <template v-if="usdFromCad != null">{{ formatMid(usdFromCad, 2) }} USD</template>
            <template v-else>—</template>
          </p>
        </div>
      </div>
    </template>

    <a
      :href="sourceUrl"
      target="_blank"
      rel="noopener noreferrer"
      class="mt-2 inline-flex text-[11px] font-semibold text-sky-300 hover:text-sky-200"
    >
      {{ $t("dashboard_exchange_xe_ref") }} · {{ source }}
    </a>
  </div>
</template>
