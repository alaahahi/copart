<script setup>
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";
import { useToast } from "vue-toastification";

const props = defineProps({
  usdToCad: { type: Number, default: null },
  source: { type: String, default: "xe.com" },
  sourceUrl: {
    type: String,
    default: "https://www.xe.com/currencyconverter/convert/?Amount=1&From=USD&To=CAD",
  },
});

const { t } = useI18n();
const toast = useToast();
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

const formatUsdRounded = (value) =>
  new Intl.NumberFormat("en-US", {
    maximumFractionDigits: 0,
  }).format(value);

const usdFromCad = computed(() => {
  const cad = Number(cadAmount.value);
  const mid = Number(props.usdToCad);
  if (!Number.isFinite(cad) || cad <= 0 || !Number.isFinite(mid) || mid <= 0) {
    return null;
  }
  return Math.round(cad / mid);
});

const copyUsdResult = async () => {
  if (usdFromCad.value == null) return;
  const text = String(usdFromCad.value);
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
    toast.success(t("dashboard_exchange_copied"), {
      timeout: 2500,
      position: "bottom-right",
      rtl: true,
    });
  } catch (e) {
    toast.error(t("dashboard_exchange_copy_failed"), {
      timeout: 3000,
      position: "bottom-right",
      rtl: true,
    });
  }
};
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
          <div
            class="flex min-h-[42px] items-center justify-between gap-2 rounded-lg border border-slate-600 bg-slate-900 px-3 py-2"
            dir="ltr"
          >
            <p class="font-mono text-sm font-bold tabular-nums text-emerald-300">
              <template v-if="usdFromCad != null">{{ formatUsdRounded(usdFromCad) }} USD</template>
              <template v-else>—</template>
            </p>
            <button
              v-if="usdFromCad != null"
              type="button"
              class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded text-slate-400 hover:bg-slate-800 hover:text-white"
              :title="$t('dashboard_exchange_copy_usd')"
              :aria-label="$t('dashboard_exchange_copy_usd')"
              @click="copyUsdResult"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </button>
          </div>
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
