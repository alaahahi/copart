<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/inertia-vue3";
import { ref, computed, onMounted, watch } from "vue";
import axios from "axios";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const loading = ref(false);
const errorMsg = ref("");
const data = ref(null);

const from = ref(getFirstDayOfMonth());
const to = ref(getTodayDate());
const currency = ref("$");
const clientId = ref("");
const results = ref("");

const currencyLabel = computed(() => (currency.value === "$" ? "USD" : "IQD"));
const currencySymbol = computed(() => (currency.value === "$" ? "$" : " IQD"));

const kpis = computed(() => data.value?.kpis || {});
const mom = computed(() => data.value?.mom || {});
const traders = computed(() => data.value?.traders || []);
const traderProfits = computed(() => data.value?.trader_profits || []);
const carPricing = computed(() => data.value?.car_pricing || {});
const expenses = computed(() => data.value?.expenses || {});
const aging = computed(() => data.value?.aging || {});
const alerts = computed(() => data.value?.alerts || []);
const cash = computed(() => data.value?.cash || {});

const maxExpenseType = computed(() => {
  const rows = expenses.value?.by_type || [];
  return Math.max(1, ...rows.map((r) => Number(r.amount) || 0));
});

const maxMonthly = computed(() => {
  const rows = expenses.value?.monthly_trend || [];
  return Math.max(1, ...rows.map((r) => Number(r.total) || 0));
});

const maxAging = computed(() => {
  const b = aging.value?.buckets || {};
  return Math.max(1, Number(b["0_30"] || 0), Number(b["31_60"] || 0), Number(b["61_90"] || 0), Number(b["90_plus"] || 0));
});

const marginBucketTotal = computed(() => {
  const b = carPricing.value?.margin_buckets || {};
  return Math.max(1, (b.loss || 0) + (b["0_10"] || 0) + (b["10_20"] || 0) + (b["20_plus"] || 0));
});

function getTodayDate() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function getFirstDayOfMonth() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-01`;
}

function getDateMonthsAgo(months) {
  const d = new Date();
  d.setMonth(d.getMonth() - months);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function setThisMonth() {
  from.value = getFirstDayOfMonth();
  to.value = getTodayDate();
  load();
}

function setThreeMonths() {
  from.value = getDateMonthsAgo(3);
  to.value = getTodayDate();
  load();
}

function setYear() {
  const d = new Date();
  from.value = `${d.getFullYear()}-01-01`;
  to.value = getTodayDate();
  load();
}

function formatMoney(v) {
  const n = Number(v || 0);
  return currency.value === "$"
    ? n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : n.toLocaleString(undefined, { maximumFractionDigits: 0 });
}

function formatPct(v) {
  const n = Number(v || 0);
  const sign = n > 0 ? "+" : "";
  return `${sign}${n.toFixed(1)}%`;
}

function momClass(pct) {
  if (pct > 0) return "text-emerald-600 dark:text-emerald-400";
  if (pct < 0) return "text-rose-600 dark:text-rose-400";
  return "text-slate-500 dark:text-slate-400";
}

function alertClass(level) {
  if (level === "danger") return "border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200";
  if (level === "warning") return "border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200";
  return "border-sky-300 bg-sky-50 text-sky-900 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200";
}

function filterParams() {
  const params = {
    from: from.value,
    to: to.value,
    currency: currency.value,
  };
  if (clientId.value !== "" && clientId.value !== null) params.client_id = clientId.value;
  if (results.value !== "" && results.value !== null) params.results = results.value;
  return params;
}

async function load() {
  loading.value = true;
  errorMsg.value = "";
  try {
    const { data: res } = await axios.get("/api/analyticsDashboard", { params: filterParams() });
    data.value = res.data || null;
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || t("analytics_load_error");
    data.value = null;
  } finally {
    loading.value = false;
  }
}

function exportTraders() {
  const q = new URLSearchParams(filterParams()).toString();
  window.location.href = `/api/analyticsExportTraders?${q}`;
}

onMounted(load);

watch([currency], () => load());
</script>

<template>
  <Head :title="$t('Analytics')" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-slate-800 dark:text-slate-100">
        {{ $t("Analytics") }}
      </h2>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-8xl space-y-6 px-4 sm:px-6 lg:px-8">
        <!-- Filters -->
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
          <div class="flex flex-wrap items-end gap-3">
            <label class="flex min-w-[140px] flex-col gap-1 text-sm">
              <span class="text-slate-500 dark:text-slate-400">{{ $t("analytics_from") }}</span>
              <input v-model="from" type="date" class="rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950" />
            </label>
            <label class="flex min-w-[140px] flex-col gap-1 text-sm">
              <span class="text-slate-500 dark:text-slate-400">{{ $t("analytics_to") }}</span>
              <input v-model="to" type="date" class="rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950" />
            </label>
            <label class="flex min-w-[120px] flex-col gap-1 text-sm">
              <span class="text-slate-500 dark:text-slate-400">{{ $t("analytics_currency") }}</span>
              <select v-model="currency" class="rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="$">USD</option>
                <option value="IQD">IQD</option>
              </select>
            </label>
            <label class="flex min-w-[180px] flex-1 flex-col gap-1 text-sm">
              <span class="text-slate-500 dark:text-slate-400">{{ $t("analytics_trader") }}</span>
              <select v-model="clientId" class="rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">{{ $t("analytics_all_traders") }}</option>
                <option v-for="tr in traders" :key="tr.id" :value="tr.id">{{ tr.name }}</option>
              </select>
            </label>
            <label class="flex min-w-[140px] flex-col gap-1 text-sm">
              <span class="text-slate-500 dark:text-slate-400">{{ $t("analytics_car_status") }}</span>
              <select v-model="results" class="rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">{{ $t("analytics_all_status") }}</option>
                <option value="0">{{ $t("analytics_unpaid") }}</option>
                <option value="1">{{ $t("analytics_paid") }}</option>
                <option value="2">{{ $t("analytics_completed") }}</option>
              </select>
            </label>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 dark:bg-sky-600 dark:hover:bg-sky-500" @click="load">
                {{ $t("analytics_apply") }}
              </button>
              <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700" @click="setThisMonth">{{ $t("analytics_this_month") }}</button>
              <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700" @click="setThreeMonths">3M</button>
              <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700" @click="setYear">YTD</button>
            </div>
          </div>
          <p v-if="errorMsg" class="mt-3 text-sm text-rose-600 dark:text-rose-400">{{ errorMsg }}</p>
          <p v-if="loading" class="mt-3 text-sm text-slate-500">{{ $t("analytics_loading") }}</p>
        </section>

        <!-- Alerts -->
        <section v-if="alerts.length" class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
          <div
            v-for="(a, idx) in alerts"
            :key="idx"
            class="rounded-xl border px-4 py-3 text-sm"
            :class="alertClass(a.level)"
          >
            {{ a.message }}
          </div>
        </section>

        <!-- KPI cards -->
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4">
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $t("analytics_sales") }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ formatMoney(kpis.sales) }}<span class="text-sm font-normal text-slate-400">{{ currencySymbol }}</span></div>
            <div class="mt-1 text-xs" :class="momClass(mom.sales?.pct)">{{ formatPct(mom.sales?.pct) }} {{ $t("analytics_vs_prev") }}</div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $t("analytics_cost") }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ formatMoney(kpis.cost) }}<span class="text-sm font-normal text-slate-400">{{ currencySymbol }}</span></div>
            <div class="mt-1 text-xs" :class="momClass(mom.cost?.pct)">{{ formatPct(mom.cost?.pct) }} {{ $t("analytics_vs_prev") }}</div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $t("analytics_net_profit") }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums" :class="(kpis.net_profit || 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
              {{ formatMoney(kpis.net_profit) }}<span class="text-sm font-normal text-slate-400">{{ currencySymbol }}</span>
            </div>
            <div class="mt-1 text-xs" :class="momClass(mom.net_profit?.pct)">{{ formatPct(mom.net_profit?.pct) }} {{ $t("analytics_vs_prev") }}</div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $t("analytics_margin") }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ Number(kpis.margin_pct || 0).toFixed(1) }}%</div>
            <div class="mt-1 text-xs" :class="momClass(mom.margin_pct?.pct)">{{ formatPct(mom.margin_pct?.pct) }} {{ $t("analytics_vs_prev") }}</div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $t("analytics_receivables") }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ formatMoney(kpis.receivables) }}<span class="text-sm font-normal text-slate-400">{{ currencySymbol }}</span></div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $t("analytics_cash_box") }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ formatMoney(kpis.cash_box) }}<span class="text-sm font-normal text-slate-400">{{ currencySymbol }}</span></div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $t("analytics_period_expenses") }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ formatMoney(kpis.period_expenses) }}<span class="text-sm font-normal text-slate-400">{{ currencySymbol }}</span></div>
            <div class="mt-1 text-xs" :class="momClass(mom.period_expenses?.pct)">{{ formatPct(mom.period_expenses?.pct) }} {{ $t("analytics_vs_prev") }}</div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $t("analytics_cars_count") }}</div>
            <div class="mt-1 text-2xl font-semibold tabular-nums">{{ kpis.cars_count || 0 }}</div>
            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
              {{ $t("analytics_unpaid") }}: {{ kpis.cars_unpaid || 0 }} · {{ $t("analytics_paid") }}: {{ kpis.cars_paid || 0 }}
            </div>
          </div>
        </section>

        <!-- Cash + Treasury -->
        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <div class="text-xs text-slate-500">{{ $t("analytics_cash_usd") }}</div>
            <div class="mt-1 text-xl font-semibold tabular-nums">{{ formatMoney(cash.cash_usd) }} $</div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <div class="text-xs text-slate-500">{{ $t("analytics_cash_iqd") }}</div>
            <div class="mt-1 text-xl font-semibold tabular-nums">{{ Number(cash.cash_iqd || 0).toLocaleString() }} IQD</div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <div class="text-xs text-slate-500">{{ $t("analytics_treasury_usd") }}</div>
            <div class="mt-1 text-xl font-semibold tabular-nums">{{ formatMoney(cash.treasury_usd) }} $</div>
            <div v-if="cash.ops_treasury_usd !== null && cash.ops_treasury_usd !== undefined" class="mt-1 text-xs text-slate-500">
              {{ $t("analytics_ops_treasury") }}: {{ formatMoney(cash.ops_treasury_usd) }} $
            </div>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <div class="text-xs text-slate-500">{{ $t("analytics_treasury_iqd") }}</div>
            <div class="mt-1 text-xl font-semibold tabular-nums">{{ Number(cash.treasury_iqd || 0).toLocaleString() }} IQD</div>
            <div v-if="cash.ops_treasury_iqd !== null && cash.ops_treasury_iqd !== undefined" class="mt-1 text-xs text-slate-500">
              {{ $t("analytics_ops_treasury") }}: {{ Number(cash.ops_treasury_iqd || 0).toLocaleString() }} IQD
            </div>
          </div>
        </section>

        <!-- Trader profits -->
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
              <h3 class="text-lg font-semibold">{{ $t("analytics_trader_profits") }}</h3>
              <p class="text-sm text-slate-500 dark:text-slate-400">{{ $t("analytics_trader_profits_hint") }}</p>
            </div>
            <button
              type="button"
              class="rounded-xl border border-emerald-600 px-4 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50 dark:border-emerald-500 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
              @click="exportTraders"
            >
              {{ $t("analytics_export_excel") }}
            </button>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="border-b border-slate-200 text-right text-slate-500 dark:border-slate-700 dark:text-slate-400">
                  <th class="px-2 py-2 font-medium">{{ $t("analytics_trader") }}</th>
                  <th class="px-2 py-2 font-medium">{{ $t("analytics_cars_count") }}</th>
                  <th class="px-2 py-2 font-medium">{{ $t("analytics_sales") }}</th>
                  <th class="px-2 py-2 font-medium">{{ $t("analytics_cost") }}</th>
                  <th class="px-2 py-2 font-medium">{{ $t("analytics_net_profit") }}</th>
                  <th class="px-2 py-2 font-medium min-w-[140px]">{{ $t("analytics_chart") }}</th>
                  <th class="px-2 py-2 font-medium">{{ $t("analytics_margin") }}</th>
                  <th class="px-2 py-2 font-medium">{{ $t("analytics_paid") }}</th>
                  <th class="px-2 py-2 font-medium">{{ $t("analytics_remaining") }}</th>
                  <th class="px-2 py-2 font-medium">{{ $t("analytics_ledger_balance") }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!traderProfits.length">
                  <td colspan="10" class="px-2 py-6 text-center text-slate-500">{{ $t("analytics_no_data") }}</td>
                </tr>
                <tr
                  v-for="row in traderProfits"
                  :key="row.client_id"
                  class="border-b border-slate-100 dark:border-slate-800/80"
                >
                  <td class="px-2 py-2 font-medium">{{ row.trader }}</td>
                  <td class="px-2 py-2 tabular-nums">{{ row.cars_count }}</td>
                  <td class="px-2 py-2 tabular-nums">{{ formatMoney(row.sales) }}</td>
                  <td class="px-2 py-2 tabular-nums">{{ formatMoney(row.cost) }}</td>
                  <td class="px-2 py-2 tabular-nums" :class="row.profit >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                    {{ formatMoney(row.profit) }}
                  </td>
                  <td class="px-2 py-2">
                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                      <div
                        class="h-full rounded-full transition-all"
                        :class="row.bar_positive ? 'bg-emerald-500' : 'bg-rose-500'"
                        :style="{ width: `${row.bar_pct || 0}%` }"
                      />
                    </div>
                  </td>
                  <td class="px-2 py-2 tabular-nums">{{ Number(row.margin_pct || 0).toFixed(1) }}%</td>
                  <td class="px-2 py-2 tabular-nums">{{ formatMoney(row.paid) }}</td>
                  <td class="px-2 py-2 tabular-nums">{{ formatMoney(row.remaining) }}</td>
                  <td class="px-2 py-2 tabular-nums">{{ formatMoney(row.ledger_balance) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Car pricing -->
        <section class="grid gap-4 xl:grid-cols-2">
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <h3 class="mb-3 text-lg font-semibold">{{ $t("analytics_car_pricing") }}</h3>
            <div class="grid grid-cols-3 gap-3">
              <div>
                <div class="text-xs text-slate-500">{{ $t("analytics_avg_sale") }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ formatMoney(carPricing.avg_sale) }}</div>
              </div>
              <div>
                <div class="text-xs text-slate-500">{{ $t("analytics_avg_cost") }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ formatMoney(carPricing.avg_cost) }}</div>
              </div>
              <div>
                <div class="text-xs text-slate-500">{{ $t("analytics_avg_profit") }}</div>
                <div class="text-lg font-semibold tabular-nums">{{ formatMoney(carPricing.avg_profit) }}</div>
              </div>
            </div>

            <div class="mt-5 space-y-2">
              <div class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ $t("analytics_margin_buckets") }}</div>
              <div v-for="item in [
                { key: 'loss', label: $t('analytics_bucket_loss'), val: carPricing.margin_buckets?.loss || 0, color: 'bg-rose-500' },
                { key: '0_10', label: '0–10%', val: carPricing.margin_buckets?.['0_10'] || 0, color: 'bg-amber-500' },
                { key: '10_20', label: '10–20%', val: carPricing.margin_buckets?.['10_20'] || 0, color: 'bg-sky-500' },
                { key: '20_plus', label: '20%+', val: carPricing.margin_buckets?.['20_plus'] || 0, color: 'bg-emerald-500' },
              ]" :key="item.key" class="flex items-center gap-3 text-sm">
                <div class="w-20 shrink-0 text-slate-500">{{ item.label }}</div>
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                  <div class="h-full rounded-full" :class="item.color" :style="{ width: `${(item.val / marginBucketTotal) * 100}%` }" />
                </div>
                <div class="w-10 tabular-nums text-left">{{ item.val }}</div>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <h3 class="mb-3 text-lg font-semibold">{{ $t("analytics_top_worst") }}</h3>
            <div class="grid gap-4 md:grid-cols-2">
              <div>
                <div class="mb-2 text-sm font-medium text-emerald-600 dark:text-emerald-400">{{ $t("analytics_top_cars") }}</div>
                <ul class="space-y-2 text-sm">
                  <li v-for="c in (carPricing.top_cars || [])" :key="'t'+c.id" class="flex justify-between gap-2 border-b border-slate-100 pb-1 dark:border-slate-800">
                    <span class="truncate">{{ c.label }}</span>
                    <span class="tabular-nums text-emerald-600 dark:text-emerald-400">{{ formatMoney(c.profit) }}</span>
                  </li>
                  <li v-if="!(carPricing.top_cars || []).length" class="text-slate-500">{{ $t("analytics_no_data") }}</li>
                </ul>
              </div>
              <div>
                <div class="mb-2 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $t("analytics_worst_cars") }}</div>
                <ul class="space-y-2 text-sm">
                  <li v-for="c in (carPricing.worst_cars || [])" :key="'w'+c.id" class="flex justify-between gap-2 border-b border-slate-100 pb-1 dark:border-slate-800">
                    <span class="truncate">{{ c.label }}</span>
                    <span class="tabular-nums text-rose-600 dark:text-rose-400">{{ formatMoney(c.profit) }}</span>
                  </li>
                  <li v-if="!(carPricing.worst_cars || []).length" class="text-slate-500">{{ $t("analytics_no_data") }}</li>
                </ul>
              </div>
            </div>
          </div>
        </section>

        <!-- Expenses -->
        <section class="grid gap-4 xl:grid-cols-2">
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <h3 class="mb-3 text-lg font-semibold">{{ $t("analytics_expenses") }}</h3>
            <div class="mb-4 grid grid-cols-2 gap-3">
              <div>
                <div class="text-xs text-slate-500">{{ $t("analytics_gen_expenses") }}</div>
                <div class="text-xl font-semibold tabular-nums">{{ formatMoney(expenses.general_total) }}</div>
              </div>
              <div>
                <div class="text-xs text-slate-500">{{ $t("analytics_car_expenses") }}</div>
                <div class="text-xl font-semibold tabular-nums">{{ formatMoney(expenses.car_total) }}</div>
              </div>
            </div>
            <div class="space-y-2">
              <div
                v-for="row in (expenses.by_type || [])"
                :key="row.type_id"
                class="flex items-center gap-3 text-sm"
              >
                <div class="w-24 shrink-0 text-slate-500">{{ row.label || row.key }}</div>
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                  <div class="h-full rounded-full bg-indigo-500" :style="{ width: `${(Number(row.amount) / maxExpenseType) * 100}%` }" />
                </div>
                <div class="w-24 tabular-nums text-left">{{ formatMoney(row.amount) }}</div>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
            <h3 class="mb-3 text-lg font-semibold">{{ $t("analytics_expense_trend") }}</h3>
            <div class="space-y-2">
              <div
                v-for="m in (expenses.monthly_trend || [])"
                :key="m.month"
                class="flex items-center gap-3 text-sm"
              >
                <div class="w-16 shrink-0 tabular-nums text-slate-500">{{ m.label }}</div>
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                  <div class="h-full rounded-full bg-violet-500" :style="{ width: `${(Number(m.total) / maxMonthly) * 100}%` }" />
                </div>
                <div class="w-24 tabular-nums text-left">{{ formatMoney(m.total) }}</div>
              </div>
              <p v-if="!(expenses.monthly_trend || []).length" class="text-sm text-slate-500">{{ $t("analytics_no_data") }}</p>
            </div>
          </div>
        </section>

        <!-- Aging -->
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
          <h3 class="mb-1 text-lg font-semibold">{{ $t("analytics_ar_aging") }}</h3>
          <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
            {{ $t("analytics_ar_aging_hint") }}
            · {{ $t("analytics_ledger_balance") }}: {{ formatMoney(aging.ledger_receivables) }}{{ currencySymbol }}
          </p>
          <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div v-for="item in [
              { key: '0_30', label: '0–30' },
              { key: '31_60', label: '31–60' },
              { key: '61_90', label: '61–90' },
              { key: '90_plus', label: '90+' },
            ]" :key="item.key" class="rounded-xl border border-slate-100 p-3 dark:border-slate-800">
              <div class="text-xs text-slate-500">{{ item.label }} {{ $t("analytics_days") }}</div>
              <div class="mt-1 text-lg font-semibold tabular-nums">{{ formatMoney(aging.buckets?.[item.key]) }}</div>
              <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                <div class="h-full rounded-full bg-orange-500" :style="{ width: `${((aging.buckets?.[item.key] || 0) / maxAging) * 100}%` }" />
              </div>
              <div class="mt-1 text-xs text-slate-500">{{ aging.counts?.[item.key] || 0 }} {{ $t("analytics_cars_count") }}</div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
