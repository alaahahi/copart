<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useToast } from "vue-toastification";
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from "vue-i18n";
import { Head, Link, usePage } from "@inertiajs/inertia-vue3";
import { debounce } from 'lodash';
import SearchInput from "@/Components/SearchInput.vue";
import { formatMoney } from "@/utils/formatMoney";

const auth = defineProps(['auth']);
const appName = usePage().props.value.appName;

const { t } = useI18n();
let userType = ref(auth.auth.user.type_id);

function selectUser() {
  return 'getIndexClients';
}

const laravelData = ref([]);
const merchantCredits = ref([]);
let controller = new AbortController();
const refreshing = ref(false);
const refreshingOps = ref(false);
const toast = useToast();
let showModal = ref(false);

function sendWhatsAppMessage(user) {
  const phoneNumber = user?.phone;
  if (!phoneNumber) {
    toast.error(t('waDebtNoPhone'));
    return;
  }

  axios
    .post(route('whatsapp.debt_notice'), {
      client_id: user.id,
      balance: user.balance,
    })
    .then(({ data }) => {
      if (data?.queued) {
        toast.success(data.message || t('waDebtQueued'));
        return;
      }
      // Integration off or skipped → legacy WhatsApp Web
      openWhatsAppWeb(phoneNumber);
    })
    .catch(() => {
      openWhatsAppWeb(phoneNumber);
    });
}

function openWhatsAppWeb(phoneNumber) {
  const normalized = String(phoneNumber).replace(/\D/g, '');
  const withCountry = normalized.startsWith('964') ? normalized : `964${normalized.replace(/^0/, '')}`;
  const message = `السلام عليكم: ${appName} - أربيل ,يرجى الأخذ بالعلم تسديد المبلغ المستحق عليكم في أقرب وقت ممكن. شكرا لتعاونكم  ..........   سڵاوی خواتان لێبێت: کۆمپانیای ${appName} - تکایە ئاگاداربن بە زووترین کات ئەو بڕە پارەیەی کە قەرزارن بیدەن. سوپاس بۆ هەماهەنگیت`;
  const whatsappURL = `https://api.whatsapp.com/send?phone=${withCountry}&text=${encodeURIComponent(message)}`;
  window.open(whatsappURL);
}

let searchTerm = ref('');
let mainAccount = ref(0);
let purchasesCost = ref(0);
let clientPaid = ref(0);
let clientDebit = ref(0);
let mainBoxDollar = ref(0);
let mainBoxDinar = ref(0);
let allCars = ref(0);
let transactionInTodayDollar = ref(0);
let transactionOutTodayDollar = ref(0);
let transactionInTodayDinar = ref(0);
let transactionOutTodayDinar = ref(0);

const recentOps = ref([]);
const now = ref(new Date());
let clockTimer = null;

const weatherTemp = ref(null);
const weatherCity = ref('');
const WEATHER_LS_KEY = 'dashboard-weather-cache';
const WEATHER_TTL_MS = 60 * 60 * 1000;

const fxSell = ref(null);
const fxBuy = ref(null);
const fxCadSell = ref(null);
const fxCadBuy = ref(null);
const fxCadAvailable = ref(false);
const fxCadNote = ref('');
const fxSource = ref('');
const fxUpdatedAt = ref(null);
const FX_LS_KEY = 'dashboard-exchange-rates-cache-v3';
const FX_TTL_MS = 60 * 60 * 1000;

/** dawn | day | sunset | night — local browser hour */
const dayPhase = computed(() => {
  const h = now.value.getHours();
  if (h >= 5 && h < 10) return 'dawn';
  if (h >= 10 && h < 16) return 'day';
  if (h >= 16 && h < 19) return 'sunset';
  return 'night';
});

const dayPhaseLabelKey = computed(() => {
  const map = {
    dawn: 'dashboard_phase_dawn',
    day: 'dashboard_phase_day',
    sunset: 'dashboard_phase_sunset',
    night: 'dashboard_phase_night',
  };
  return map[dayPhase.value] || 'dashboard_phase_day';
});

/** Sky band gradient class by local hour phase */
const skyPanelClass = computed(() => {
  const map = {
    dawn: 'dash-sky-dawn',
    day: 'dash-sky-day',
    sunset: 'dash-sky-sunset',
    night: 'dash-sky-night',
  };
  return map[dayPhase.value] || 'dash-sky-day';
});

const weatherTempLabel = computed(() => {
  if (weatherTemp.value === null || weatherTemp.value === undefined) return null;
  const n = Number(weatherTemp.value);
  if (Number.isNaN(n)) return null;
  return `${n.toFixed(n % 1 === 0 ? 0 : 1)}°C`;
});

const formatFxIqd = (value) => {
  if (value === null || value === undefined) return null;
  const n = Number(value);
  if (Number.isNaN(n)) return null;
  return new Intl.NumberFormat('en-US', {
    maximumFractionDigits: n % 1 === 0 ? 0 : 2,
  }).format(n);
};

/** CAD→USD (e.g. 0.71) — always show up to 4 decimals. */
const formatFxCadUsd = (value) => {
  if (value === null || value === undefined) return null;
  const n = Number(value);
  if (Number.isNaN(n)) return null;
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 4,
  }).format(n);
};

const fxHasRates = computed(
  () => formatFxIqd(fxSell.value) !== null && formatFxIqd(fxBuy.value) !== null
);

const fxHasCadRates = computed(
  () =>
    fxCadAvailable.value &&
    formatFxCadUsd(fxCadSell.value) !== null &&
    formatFxCadUsd(fxCadBuy.value) !== null
);

const clockTime = computed(() =>
  new Intl.DateTimeFormat('ar-IQ', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
    numberingSystem: 'latn',
  }).format(now.value)
);

const gregorianDate = computed(() =>
  new Intl.DateTimeFormat('ar-IQ', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    numberingSystem: 'latn',
  }).format(now.value)
);

const hijriDate = computed(() => {
  try {
    return new Intl.DateTimeFormat('ar-SA-u-ca-islamic-umalqura', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      numberingSystem: 'latn',
    }).format(now.value);
  } catch {
    return new Intl.DateTimeFormat('ar-SA-u-ca-islamic', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      numberingSystem: 'latn',
    }).format(now.value);
  }
});

const authHeaders = () => ({
  Authorization: 'Bearer ' + auth.auth.accessToken,
});

function openModal() {
  showModal.value = true;
}

const formData = ref({});

/**
 * Load KPI + merchant debt cards from the same totalInfo payload
 * so the sum and the colored cards cannot diverge.
 */
const getcountTotalInfo = async () => {
  const response = await axios.get('/api/totalInfo', {
    headers: authHeaders(),
  });
  const payload = response.data.data || {};
  mainAccount.value = payload.mainAccount;
  allCars.value = payload.allCars;
  purchasesCost.value = payload.purchasesCost;
  clientPaid.value = payload.clientPaid;
  clientDebit.value = payload.clientDebit;
  mainBoxDollar.value = payload.mainBoxDollar;
  mainBoxDinar.value = payload.mainBoxDinar;
  transactionInTodayDollar.value = payload.transactionInTodayDollar ?? 0;
  transactionOutTodayDollar.value = payload.transactionOutTodayDollar ?? 0;
  transactionInTodayDinar.value = payload.transactionInTodayDinar ?? 0;
  transactionOutTodayDinar.value = payload.transactionOutTodayDinar ?? 0;

  // Only overwrite the cards when not mid-search
  if (!searchTerm.value && (userType.value == 1 || userType.value == 6)) {
    laravelData.value = Array.isArray(payload.merchantDebts)
      ? payload.merchantDebts
      : [];
    merchantCredits.value = Array.isArray(payload.merchantCredits)
      ? payload.merchantCredits
      : [];
  }
};

const loadRecentActivity = async () => {
  if (refreshingOps.value) return;
  refreshingOps.value = true;
  try {
    const response = await axios.get('/api/dashboardRecentActivity', {
      params: { limit: 12 },
      headers: authHeaders(),
    });
    recentOps.value = Array.isArray(response.data.data) ? response.data.data : [];
  } catch (error) {
    console.error(error);
    recentOps.value = [];
  } finally {
    refreshingOps.value = false;
  }
};

function readWeatherLocalCache({ allowStale = false } = {}) {
  try {
    const raw = localStorage.getItem(WEATHER_LS_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object' || !parsed.ts) return null;
    const fresh = Date.now() - Number(parsed.ts) <= WEATHER_TTL_MS;
    if (!fresh && !allowStale) return null;
    return { ...parsed, fresh };
  } catch {
    return null;
  }
}

function writeWeatherLocalCache(data) {
  try {
    localStorage.setItem(
      WEATHER_LS_KEY,
      JSON.stringify({
        ts: Date.now(),
        temperature: data?.temperature ?? null,
        city: data?.city ?? '',
      })
    );
  } catch {
    /* ignore quota / private mode */
  }
}

const loadWeather = async () => {
  const cached = readWeatherLocalCache();
  if (cached?.fresh && cached.temperature != null) {
    weatherTemp.value = cached.temperature;
    weatherCity.value = cached.city || '';
    return;
  }

  const stale = readWeatherLocalCache({ allowStale: true });
  if (stale?.temperature != null) {
    weatherTemp.value = stale.temperature;
    weatherCity.value = stale.city || '';
  }

  try {
    const response = await axios.get('/api/dashboardWeather', {
      headers: authHeaders(),
    });
    const data = response.data?.data || {};
    if (data.temperature != null && !Number.isNaN(Number(data.temperature))) {
      weatherTemp.value = Number(data.temperature);
      weatherCity.value = data.city || '';
      writeWeatherLocalCache(data);
    }
  } catch (error) {
    console.error(error);
    /* keep stale temp if any — never block the clock */
  }
};

function readFxLocalCache({ allowStale = false } = {}) {
  try {
    const raw = localStorage.getItem(FX_LS_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object' || !parsed.ts) return null;
    const fresh = Date.now() - Number(parsed.ts) <= FX_TTL_MS;
    if (!fresh && !allowStale) return null;
    return { ...parsed, fresh };
  } catch {
    return null;
  }
}

function writeFxLocalCache(data) {
  try {
    localStorage.setItem(
      FX_LS_KEY,
      JSON.stringify({
        ts: Date.now(),
        usd_to_iqd_sell: data?.usd_to_iqd_sell ?? null,
        usd_to_iqd_buy: data?.usd_to_iqd_buy ?? null,
        cad_to_usd_sell: data?.cad_to_usd_sell ?? null,
        cad_to_usd_buy: data?.cad_to_usd_buy ?? null,
        cad_available: data?.cad_available ?? false,
        cad_note: data?.cad_note || '',
        source: data?.source || '',
        updated_at: data?.updated_at || null,
      })
    );
  } catch {
    /* ignore quota / private mode */
  }
}

function applyFxData(data) {
  if (!data || data.usd_to_iqd_sell == null || data.usd_to_iqd_buy == null) return false;
  const sell = Number(data.usd_to_iqd_sell);
  const buy = Number(data.usd_to_iqd_buy);
  if (Number.isNaN(sell) || Number.isNaN(buy)) return false;
  fxSell.value = sell;
  fxBuy.value = buy;

  const cadSell = data.cad_to_usd_sell != null ? Number(data.cad_to_usd_sell) : null;
  const cadBuy = data.cad_to_usd_buy != null ? Number(data.cad_to_usd_buy) : null;
  const cadOk =
    data.cad_available === true &&
    cadSell != null &&
    cadBuy != null &&
    !Number.isNaN(cadSell) &&
    !Number.isNaN(cadBuy) &&
    cadSell > 0 &&
    cadBuy > 0;
  fxCadAvailable.value = cadOk;
  fxCadSell.value = cadOk ? cadSell : null;
  fxCadBuy.value = cadOk ? cadBuy : null;
  fxCadNote.value = cadOk ? '' : data.cad_note || '';

  fxSource.value = data.source || '';
  fxUpdatedAt.value = data.updated_at || null;
  return true;
}

const loadExchangeRates = async () => {
  const cached = readFxLocalCache();
  if (cached?.fresh && applyFxData(cached)) {
    return;
  }

  const stale = readFxLocalCache({ allowStale: true });
  if (stale) {
    applyFxData(stale);
  }

  try {
    const response = await axios.get('/api/dashboardExchangeRates', {
      headers: authHeaders(),
    });
    const data = response.data?.data || {};
    if (applyFxData(data)) {
      writeFxLocalCache(data);
    }
  } catch (error) {
    console.error(error);
    /* keep stale rates if any */
  }
};

const debouncedGetResultsCarSearch = debounce(async (q = '', page = 1) => {
  const term = (q || '').trim();
  try {
    const params = new URLSearchParams({
      page: String(page),
      q: term || 'debit',
    });
    if (!term) {
      params.set('exclude_zero', '0');
    }
    const response = await axios.get(`api/${selectUser()}?${params.toString()}`, {
      signal: controller.signal,
      headers: authHeaders(),
    });
    laravelData.value = Array.isArray(response.data.data) ? response.data.data : [];
  } catch (error) {
    if (error?.name !== 'CanceledError' && error?.code !== 'ERR_CANCELED') {
      console.error(error);
    }
  }
}, 300);

const getResultsCarSearch = (q = '', page = 1) => {
  debouncedGetResultsCarSearch(q, page);
};

const abortRequest = () => {
  if (controller) {
    controller.abort();
  }
  controller = new AbortController();
};

/** إعادة تحميل الدين + البطاقات من نفس المصدر (بدون كاش قائمة منفصل) */
const refreshMerchantDebts = async () => {
  if (refreshing.value) return;
  refreshing.value = true;
  abortRequest();
  try {
    searchTerm.value = '';
    await Promise.all([getcountTotalInfo(), loadRecentActivity()]);
  } catch (error) {
    console.error(error);
    toast.error(t('no_debts_to_show'));
  } finally {
    refreshing.value = false;
  }
};

watch(searchTerm, (term) => {
  abortRequest();
  if (!term) {
    // Cleared search → restore unified KPI+list source
    getcountTotalInfo().catch((e) => console.error(e));
    return;
  }
  debouncedGetResultsCarSearch(term);
});

getcountTotalInfo().catch((e) => console.error(e));
loadRecentActivity().catch((e) => console.error(e));
loadWeather().catch((e) => console.error(e));
loadExchangeRates().catch((e) => console.error(e));

onMounted(() => {
  clockTimer = window.setInterval(() => {
    now.value = new Date();
  }, 1000);
});

onUnmounted(() => {
  if (clockTimer) {
    window.clearInterval(clockTimer);
  }
});

function changeColor(total) {
  const balance = parseFloat(total) || 0;

  if (balance < 0) {
    return 'bg-emerald-600 hover:bg-emerald-500 dark:bg-emerald-700 dark:hover:bg-emerald-600';
  }
  if (balance >= 30000) {
    return 'bg-red-600 hover:bg-red-500 dark:bg-red-700 dark:hover:bg-red-600';
  }
  if (balance >= 25000) {
    return 'bg-rose-600 hover:bg-rose-500 dark:bg-rose-700 dark:hover:bg-rose-600';
  }
  if (balance >= 20000) {
    return 'bg-fuchsia-600 hover:bg-fuchsia-500 dark:bg-fuchsia-700 dark:hover:bg-fuchsia-600';
  }
  if (balance >= 15000) {
    return 'bg-indigo-600 hover:bg-indigo-500 dark:bg-indigo-700 dark:hover:bg-indigo-600';
  }
  if (balance >= 10000) {
    return 'bg-cyan-600 hover:bg-cyan-500 dark:bg-cyan-700 dark:hover:bg-cyan-600';
  }
  if (balance >= 1000) {
    return 'bg-teal-600 hover:bg-teal-500 dark:bg-teal-700 dark:hover:bg-teal-600';
  }
  return 'bg-amber-600 hover:bg-amber-500 dark:bg-amber-700 dark:hover:bg-amber-600';
}

/** Display prepaid credit as a positive USD amount (ledger AR is negative). */
function creditDisplayAmount(balance) {
  const n = typeof balance === 'number' ? balance : parseFloat(balance) || 0;
  return updateResults(Math.abs(n));
}

function updateResults(input) {
  const n = typeof input === 'number' ? input : parseFloat(input) || 0;
  return formatMoney(n, '$');
}

function moneyLabel(amount, currency = '$') {
  const code = currency === 'IQD' || currency === 'iqd' ? 'IQD' : '$';
  const suffix = code === 'IQD' ? t('iqd') : t('usd');
  return `${formatMoney(amount, code)} ${suffix}`;
}

function directionClass(direction) {
  if (direction === 'in') {
    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300';
  }
  if (direction === 'out') {
    return 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300';
  }
  return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
}
</script>

<template>
  <Head title="Dashboard" />
  <AuthenticatedLayout>
    <div
      v-if="$page.props.auth.user.type_id == 1 || $page.props.auth.user.type_id == 6"
      class="py-4 sm:py-6"
    >
      <div class="mx-auto max-w-9xl px-3 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-5 flex flex-col gap-4 sm:mb-6 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
              {{ $t('dashboard') }}
            </h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
              {{ $t('dashboard_subtitle') }}
            </p>
          </div>

          <form class="w-full sm:max-w-sm" @submit.prevent>
            <label for="dashboard-search" class="sr-only">{{ $t('search_traders') }}</label>
            <SearchInput
              id="dashboard-search"
              v-model="searchTerm"
              :placeholder="$t('search_merchant')"
              input-class="min-h-[44px] rounded-xl border-slate-300 py-2.5 pe-4 placeholder:text-slate-400 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500/30 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400/30"
            />
          </form>
        </div>

        <!-- KPI row (capital intentionally omitted) -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 sm:gap-4">
          <div
            class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition duration-200 dark:border-slate-700/80 dark:bg-slate-900/80 sm:p-5"
          >
            <div
              class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400"
              aria-hidden="true"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <div class="min-w-0">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {{ $t('merchant_debt') }}
              </p>
              <p class="mt-1 truncate text-lg font-bold tabular-nums text-slate-900 dark:text-white sm:text-xl">
                {{ updateResults(clientDebit) }}
                <span class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $t('usd') }}</span>
              </p>
            </div>
          </div>

          <div
            class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition duration-200 dark:border-slate-700/80 dark:bg-slate-900/80 sm:p-5"
          >
            <div
              class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400"
              aria-hidden="true"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="min-w-0">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {{ $t('analytics_cash_box') }}
              </p>
              <p class="mt-1 truncate text-lg font-bold tabular-nums text-slate-900 dark:text-white sm:text-xl">
                {{ updateResults(mainBoxDollar) }}
                <span class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $t('usd') }}</span>
              </p>
            </div>
          </div>

          <div
            class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition duration-200 dark:border-slate-700/80 dark:bg-slate-900/80 sm:col-span-2 lg:col-span-1 sm:p-5"
          >
            <div
              class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-950/50 dark:text-sky-400"
              aria-hidden="true"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
              </svg>
            </div>
            <div class="min-w-0">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {{ $t('analytics_cash_box') }}
              </p>
              <p class="mt-1 truncate text-lg font-bold tabular-nums text-slate-900 dark:text-white sm:text-xl">
                {{ formatMoney(mainBoxDinar, 'IQD') }}
                <span class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $t('iqd') }}</span>
              </p>
            </div>
          </div>
        </div>

        <!-- Trader debts -->
        <section class="mt-6 sm:mt-8">
          <div class="mb-3 flex flex-wrap items-center justify-between gap-3 sm:mb-4">
            <h2 class="text-base font-bold text-slate-900 dark:text-white sm:text-lg">
              {{ $t('merchant_debts') }}
            </h2>
            <div class="flex items-center gap-3">
              <p class="text-xs text-slate-500 dark:text-slate-400 sm:text-sm">
                {{ $t('double_click_whatsapp') }}
              </p>
              <button
                type="button"
                class="inline-flex min-h-[40px] items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                :disabled="refreshing"
                :title="$t('refresh')"
                @click="refreshMerchantDebts"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-4 w-4"
                  :class="{ 'animate-spin': refreshing }"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                  stroke-width="2"
                  aria-hidden="true"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                {{ $t('refresh') }}
              </button>
            </div>
          </div>

          <div
            v-if="!laravelData || !laravelData.length"
            class="rounded-2xl border border-dashed border-slate-300 bg-white/60 px-4 py-12 text-center dark:border-slate-700 dark:bg-slate-900/40"
          >
            <p class="text-sm font-medium text-slate-600 dark:text-slate-400">
              {{ $t('no_debts_to_show') }}
            </p>
          </div>

          <div
            v-else
            class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 sm:gap-4"
          >
            <Link
              v-for="(user, i) in laravelData"
              :key="user.id || i"
              :href="route('showClients', { id: user.id, q: searchTerm })"
              class="group flex min-h-[72px] items-center gap-3 rounded-2xl p-4 text-white shadow-sm transition duration-200 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-100 dark:focus-visible:ring-offset-[#0b1220]"
              :class="changeColor(user.balance)"
              @dblclick.prevent="sendWhatsAppMessage(user)"
            >
              <div
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm transition group-hover:bg-white/25"
                aria-hidden="true"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <h3 class="truncate text-sm font-bold leading-tight text-white sm:text-base">
                  {{ user.name }}
                </h3>
                <p class="mt-1 text-sm font-semibold tabular-nums text-white/95">
                  ${{ updateResults(user.balance) }}
                </p>
              </div>
            </Link>
          </div>
        </section>

        <!-- Trader prepaid / undistributed credit -->
        <section class="mt-6 sm:mt-8">
          <div class="mb-3 sm:mb-4">
            <h2 class="text-base font-bold text-slate-900 dark:text-white sm:text-lg">
              {{ $t('merchant_credits') }}
            </h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400 sm:text-sm">
              {{ $t('merchant_credits_hint') }}
            </p>
          </div>

          <div
            v-if="!merchantCredits || !merchantCredits.length"
            class="rounded-2xl border border-dashed border-slate-300 bg-white/60 px-4 py-8 text-center dark:border-slate-700 dark:bg-slate-900/40"
          >
            <p class="text-sm font-medium text-slate-600 dark:text-slate-400">
              {{ $t('no_credits_to_show') }}
            </p>
          </div>

          <div
            v-else
            class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 sm:gap-4"
          >
            <Link
              v-for="(user, i) in merchantCredits"
              :key="'credit-' + (user.id || i)"
              :href="route('showClients', { id: user.id, q: searchTerm })"
              class="group flex min-h-[72px] items-center gap-3 rounded-2xl bg-emerald-600 p-4 text-white shadow-sm transition duration-200 ease-out hover:bg-emerald-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-100 dark:bg-emerald-700 dark:hover:bg-emerald-600 dark:focus-visible:ring-offset-[#0b1220]"
            >
              <div
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm transition group-hover:bg-white/25"
                aria-hidden="true"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <h3 class="truncate text-sm font-bold leading-tight text-white sm:text-base">
                  {{ user.name }}
                </h3>
                <p class="mt-1 text-sm font-semibold tabular-nums text-white/95">
                  ${{ creditDisplayAmount(user.balance) }}
                </p>
              </div>
            </Link>
          </div>
        </section>

        <!-- Useful widgets: clock + exchange + today cash -->
        <section class="mt-6 sm:mt-8">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-3 sm:gap-4">
            <!-- Live clock + sky / weather — full-card phase gradient -->
            <div
              class="dash-sky-card relative min-w-0 overflow-hidden rounded-2xl border border-white/15 p-4 pb-36 shadow-sm sm:p-5 sm:pb-36"
              :class="skyPanelClass"
              :aria-label="weatherTempLabel || $t(dayPhaseLabelKey)"
            >
              <!-- Night stars (full card) -->
              <div
                v-if="dayPhase === 'night'"
                class="pointer-events-none absolute inset-0 z-0"
                aria-hidden="true"
              >
                <span class="dash-star absolute start-[8%] top-[10%] h-1.5 w-1.5 rounded-full bg-white" />
                <span class="dash-star dash-star-delay absolute start-[18%] top-[42%] h-1 w-1 rounded-full bg-white" />
                <span class="dash-star absolute start-[28%] top-[78%] h-[3px] w-[3px] rounded-full bg-sky-100" />
                <span class="dash-star dash-star-delay-2 absolute start-[38%] top-[16%] h-1.5 w-1.5 rounded-full bg-white" />
                <span class="dash-star absolute start-[48%] top-[55%] h-1 w-1 rounded-full bg-slate-100" />
                <span class="dash-star dash-star-delay absolute start-[58%] top-[8%] h-[3px] w-[3px] rounded-full bg-white" />
                <span class="dash-star absolute start-[68%] top-[36%] h-1.5 w-1.5 rounded-full bg-sky-50" />
                <span class="dash-star dash-star-delay-2 absolute start-[78%] top-[68%] h-1 w-1 rounded-full bg-white" />
                <span class="dash-star absolute end-[10%] top-[14%] h-1.5 w-1.5 rounded-full bg-white" />
                <span class="dash-star dash-star-delay absolute end-[18%] top-[48%] h-[3px] w-[3px] rounded-full bg-sky-100" />
                <span class="dash-star absolute end-[6%] top-[82%] h-1 w-1 rounded-full bg-white" />
                <span class="dash-star dash-star-delay-2 absolute end-[30%] top-[28%] h-1.5 w-1.5 rounded-full bg-white" />
                <span class="dash-star absolute start-[12%] top-[62%] h-[2px] w-[2px] rounded-full bg-white" />
                <span class="dash-star dash-star-delay absolute start-[88%] top-[58%] h-[2px] w-[2px] rounded-full bg-sky-50" />
              </div>

              <div class="relative z-10">
                <p class="text-xs font-semibold uppercase tracking-wide text-white/85">
                  {{ $t('dashboard_clock') }}
                </p>

                <!-- Top row: dates ↔ large clock (RTL: dates start / clock end) -->
                <div class="mt-2 flex items-start justify-between gap-3">
                  <div class="min-w-0 space-y-0.5">
                    <p class="text-sm leading-snug text-white">
                      <span class="text-xs text-white/75">{{ $t('dashboard_gregorian_date') }}:</span>
                      {{ gregorianDate }}
                    </p>
                    <p class="text-sm leading-snug text-white">
                      <span class="text-xs text-white/75">{{ $t('dashboard_hijri_date') }}:</span>
                      {{ hijriDate }}
                    </p>
                  </div>

                  <p
                    class="shrink-0 self-center font-mono text-3xl font-bold tabular-nums tracking-tight text-white sm:text-4xl"
                    dir="ltr"
                  >
                    {{ clockTime }}
                  </p>
                </div>

                <!-- Weather: physical bottom-RIGHT (away from sun) -->
                <div class="relative z-10 mt-4 min-h-[5.5rem]">
                  <div class="dash-weather-temp absolute bottom-0 right-0 z-10 min-w-0 max-w-[48%] text-end">
                    <p class="truncate text-[11px] font-semibold uppercase tracking-wider text-white/90">
                      {{ $t(dayPhaseLabelKey) }}
                    </p>
                    <p
                      v-if="weatherCity"
                      class="mt-0.5 truncate text-[11px] font-medium text-white/80"
                      :title="weatherCity"
                    >
                      {{ weatherCity }}
                    </p>
                    <p
                      v-if="weatherTempLabel"
                      class="mt-1.5 font-mono text-2xl font-bold tabular-nums tracking-tight text-white sm:text-3xl"
                      dir="ltr"
                    >
                      {{ weatherTempLabel }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- ONE celestial: physical bottom-LEFT empty corner (not under °C) -->
              <div
                class="dash-celestial pointer-events-none absolute z-[1]"
                :class="`dash-celestial--${dayPhase}`"
                style="bottom: 0.75rem; left: 0.75rem; right: auto;"
                aria-hidden="true"
              >
                <!-- Dawn / soft sun -->
                <svg
                  v-if="dayPhase === 'dawn'"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 48 48"
                  class="h-32 w-32"
                >
                  <defs>
                    <radialGradient id="dashDawnGlow" cx="50%" cy="55%" r="50%">
                      <stop offset="0%" stop-color="#fde68a" stop-opacity="0.95" />
                      <stop offset="100%" stop-color="#fbbf24" stop-opacity="0" />
                    </radialGradient>
                  </defs>
                  <circle cx="24" cy="26" r="16" fill="url(#dashDawnGlow)" />
                  <circle cx="24" cy="26" r="8" fill="#fbbf24" />
                  <path d="M8 34h32" stroke="#fcd34d" stroke-width="2" stroke-linecap="round" opacity="0.7" />
                  <path d="M12 38h24" stroke="#fde68a" stroke-width="1.5" stroke-linecap="round" opacity="0.5" />
                </svg>

                <!-- Bright day sun -->
                <svg
                  v-else-if="dayPhase === 'day'"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 48 48"
                  class="h-32 w-32"
                >
                  <g stroke="#fde047" stroke-width="2.2" stroke-linecap="round">
                    <path d="M24 6v4M24 38v4M6 24h4M38 24h4M11 11l2.8 2.8M34.2 34.2L37 37M37 11l-2.8 2.8M11 37l2.8-2.8" />
                  </g>
                  <circle cx="24" cy="24" r="9" fill="#fde047" stroke="#facc15" stroke-width="1.5" />
                </svg>

                <!-- Sunset -->
                <svg
                  v-else-if="dayPhase === 'sunset'"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 48 48"
                  class="h-32 w-32"
                >
                  <defs>
                    <linearGradient id="dashSunsetSky" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stop-color="#fdba74" stop-opacity="0.45" />
                      <stop offset="100%" stop-color="#ea580c" stop-opacity="0" />
                    </linearGradient>
                  </defs>
                  <rect x="4" y="8" width="40" height="22" rx="4" fill="url(#dashSunsetSky)" />
                  <circle cx="24" cy="28" r="9" fill="#fb923c" />
                  <path d="M6 34c6-4 12-4 18 0s12 4 18 0" fill="none" stroke="#fdba74" stroke-width="2" stroke-linecap="round" />
                  <path d="M4 38h40" stroke="#f97316" stroke-width="2.5" stroke-linecap="round" opacity="0.9" />
                </svg>

                <!-- Night moon -->
                <svg
                  v-else
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 48 48"
                  class="h-32 w-32"
                >
                  <defs>
                    <radialGradient id="dashMoonGlow" cx="42%" cy="38%" r="55%">
                      <stop offset="0%" stop-color="#f1f5f9" stop-opacity="1" />
                      <stop offset="55%" stop-color="#cbd5e1" stop-opacity="0.95" />
                      <stop offset="100%" stop-color="#64748b" stop-opacity="0.35" />
                    </radialGradient>
                  </defs>
                  <path
                    d="M30 8.5a14 14 0 1 0 9.5 24.2A12 12 0 1 1 30 8.5Z"
                    fill="url(#dashMoonGlow)"
                  />
                  <circle cx="36" cy="12" r="1.2" fill="#f8fafc" opacity="0.85" />
                  <circle cx="40" cy="18" r="0.8" fill="#e2e8f0" opacity="0.7" />
                </svg>
              </div>
            </div>

            <!-- Exchange rates (USD → IQD, CAD → USD) -->
            <div
              class="min-w-0 rounded-2xl border border-slate-600 bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-950/40 p-4 shadow-sm sm:p-5"
              :aria-label="$t('dashboard_exchange_rate')"
            >
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-wide text-slate-200">
                    {{ $t('dashboard_exchange_rate') }}
                  </p>
                  <p class="mt-0.5 text-[11px] font-medium text-emerald-300/90">
                    {{ $t('dashboard_exchange_usd_to_iqd') }} · {{ $t('dashboard_exchange_cad_to_usd') }}
                  </p>
                </div>
                <div
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-emerald-500/40 bg-emerald-500/15 text-emerald-300"
                  aria-hidden="true"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h10M7 12h6M7 17h10" />
                    <circle cx="17" cy="12" r="2.2" />
                    <path stroke-linecap="round" d="M4 4.5v15" />
                  </svg>
                </div>
              </div>

              <template v-if="fxHasRates">
                <div class="mt-3 space-y-2.5">
                  <!-- USA / USD -->
                  <div class="rounded-xl border border-slate-600 bg-slate-800/90 px-3 py-2.5">
                    <div class="flex items-center gap-2">
                      <span
                        class="inline-flex h-6 w-6 shrink-0 overflow-hidden rounded-full border border-slate-500 shadow-sm"
                        :title="$t('dashboard_exchange_usa')"
                        aria-hidden="true"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 60" class="h-full w-full">
                          <rect width="60" height="60" fill="#b22234" />
                          <rect y="4.6" width="60" height="4.6" fill="#fff" />
                          <rect y="13.8" width="60" height="4.6" fill="#fff" />
                          <rect y="23" width="60" height="4.6" fill="#fff" />
                          <rect y="32.2" width="60" height="4.6" fill="#fff" />
                          <rect y="41.4" width="60" height="4.6" fill="#fff" />
                          <rect y="50.6" width="60" height="4.6" fill="#fff" />
                          <rect width="28" height="27.6" fill="#3c3b6e" />
                        </svg>
                      </span>
                      <p class="text-[11px] font-semibold uppercase tracking-wide text-sky-300">
                        {{ $t('dashboard_exchange_usa') }} · {{ $t('dashboard_exchange_usd_to_iqd') }}
                      </p>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                      <div>
                        <p class="text-[11px] text-slate-200">
                          {{ $t('dashboard_exchange_sell') }}
                        </p>
                        <p class="mt-0.5 font-mono text-lg font-bold tabular-nums text-white" dir="ltr">
                          {{ formatFxIqd(fxSell) }}
                        </p>
                      </div>
                      <div>
                        <p class="text-[11px] text-slate-200">
                          {{ $t('dashboard_exchange_buy') }}
                        </p>
                        <p class="mt-0.5 font-mono text-lg font-bold tabular-nums text-emerald-300" dir="ltr">
                          {{ formatFxIqd(fxBuy) }}
                        </p>
                      </div>
                    </div>
                  </div>

                  <!-- Canada / CAD -->
                  <div class="rounded-xl border border-slate-600 bg-slate-800/90 px-3 py-2.5">
                    <div class="flex items-center gap-2">
                      <span
                        class="inline-flex h-6 w-6 shrink-0 overflow-hidden rounded-full border border-slate-500 shadow-sm"
                        :title="$t('dashboard_exchange_canada')"
                        aria-hidden="true"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 60" class="h-full w-full">
                          <rect width="60" height="60" fill="#fff" />
                          <rect width="14" height="60" fill="#d52b1e" />
                          <rect x="46" width="14" height="60" fill="#d52b1e" />
                          <path
                            fill="#d52b1e"
                            d="M30 14l2.2 7.2h7.6l-6.1 4.5 2.3 7.2L30 28.4l-6 4.5 2.3-7.2-6.1-4.5h7.6z"
                          />
                        </svg>
                      </span>
                      <p class="text-[11px] font-semibold uppercase tracking-wide text-rose-300">
                        {{ $t('dashboard_exchange_canada') }} · {{ $t('dashboard_exchange_cad_to_usd') }}
                      </p>
                    </div>
                    <template v-if="fxHasCadRates">
                      <div class="mt-2 grid grid-cols-2 gap-2">
                        <div>
                          <p class="text-[11px] text-slate-200">
                            {{ $t('dashboard_exchange_sell') }}
                          </p>
                          <p class="mt-0.5 font-mono text-lg font-bold tabular-nums text-white" dir="ltr">
                            {{ formatFxCadUsd(fxCadSell) }}
                          </p>
                        </div>
                        <div>
                          <p class="text-[11px] text-slate-200">
                            {{ $t('dashboard_exchange_buy') }}
                          </p>
                          <p class="mt-0.5 font-mono text-lg font-bold tabular-nums text-emerald-300" dir="ltr">
                            {{ formatFxCadUsd(fxCadBuy) }}
                          </p>
                        </div>
                      </div>
                    </template>
                    <p
                      v-else
                      class="mt-2 text-[11px] font-medium text-slate-300"
                    >
                      {{ fxCadNote || $t('dashboard_exchange_cad_unavailable') }}
                    </p>
                  </div>
                </div>
              </template>
              <p
                v-else
                class="mt-4 text-sm font-medium text-slate-300"
              >
                {{ $t('dashboard_exchange_unavailable') }}
              </p>

              <p
                v-if="fxSource"
                class="mt-3 truncate text-[11px] text-slate-400"
                :title="fxSource"
              >
                {{ $t('dashboard_exchange_source') }}: {{ fxSource }}
              </p>
            </div>

            <!-- Today's cash movement -->
            <div
              class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700/80 dark:bg-slate-900/80 sm:p-5"
            >
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {{ $t('dashboard_today_cash') }}
              </p>
              <div class="mt-3 grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-slate-600 bg-slate-800 px-3 py-2.5">
                  <p class="text-xs font-medium text-emerald-300">
                    {{ $t('dashboard_cash_in') }} ($)
                  </p>
                  <p class="mt-1 text-base font-bold tabular-nums text-white">
                    {{ formatMoney(transactionInTodayDollar, '$') }}
                  </p>
                </div>
                <div class="rounded-xl border border-slate-600 bg-slate-800 px-3 py-2.5">
                  <p class="text-xs font-medium text-rose-300">
                    {{ $t('dashboard_cash_out') }} ($)
                  </p>
                  <p class="mt-1 text-base font-bold tabular-nums text-white">
                    {{ formatMoney(transactionOutTodayDollar, '$') }}
                  </p>
                </div>
                <div class="rounded-xl border border-slate-600 bg-slate-800 px-3 py-2.5">
                  <p class="text-xs font-medium text-emerald-300">
                    {{ $t('dashboard_cash_in') }} ({{ $t('iqd') }})
                  </p>
                  <p class="mt-1 text-base font-bold tabular-nums text-white">
                    {{ formatMoney(transactionInTodayDinar, 'IQD') }}
                  </p>
                </div>
                <div class="rounded-xl border border-slate-600 bg-slate-800 px-3 py-2.5">
                  <p class="text-xs font-medium text-rose-300">
                    {{ $t('dashboard_cash_out') }} ({{ $t('iqd') }})
                  </p>
                  <p class="mt-1 text-base font-bold tabular-nums text-white">
                    {{ formatMoney(transactionOutTodayDinar, 'IQD') }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Recent system operations -->
        <section class="mt-6 sm:mt-8">
          <div class="mb-3 flex flex-wrap items-center justify-between gap-3 sm:mb-4">
            <div>
              <h2 class="text-base font-bold text-slate-900 dark:text-white sm:text-lg">
                {{ $t('dashboard_recent_ops') }}
              </h2>
              <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400 sm:text-sm">
                {{ $t('dashboard_recent_ops_hint') }}
              </p>
            </div>
            <button
              type="button"
              class="inline-flex min-h-[40px] items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
              :disabled="refreshingOps"
              :title="$t('refresh')"
              @click="loadRecentActivity"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4"
                :class="{ 'animate-spin': refreshingOps }"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
              >
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
              {{ $t('refresh') }}
            </button>
          </div>

          <div
            v-if="!recentOps.length"
            class="rounded-2xl border border-dashed border-slate-300 bg-white/60 px-4 py-12 text-center dark:border-slate-700 dark:bg-slate-900/40"
          >
            <p class="text-sm font-medium text-slate-600 dark:text-slate-400">
              {{ $t('dashboard_no_ops') }}
            </p>
          </div>

          <div
            v-else
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700/80 dark:bg-slate-900/80"
          >
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800/80">
                  <tr class="text-start text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <th class="px-3 py-3 sm:px-4">{{ $t('dashboard_col_time') }}</th>
                    <th class="px-3 py-3 sm:px-4">{{ $t('dashboard_col_type') }}</th>
                    <th class="px-3 py-3 sm:px-4">{{ $t('dashboard_col_party') }}</th>
                    <th class="px-3 py-3 sm:px-4">{{ $t('dashboard_col_amount') }}</th>
                    <th class="px-3 py-3 sm:px-4">{{ $t('dashboard_col_dir') }}</th>
                    <th class="px-3 py-3 sm:px-4"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr
                    v-for="op in recentOps"
                    :key="op.id"
                    class="text-slate-800 transition hover:bg-slate-50/80 dark:text-slate-100 dark:hover:bg-slate-800/50"
                  >
                    <td class="whitespace-nowrap px-3 py-3 tabular-nums text-slate-600 dark:text-slate-300 sm:px-4" dir="ltr">
                      {{ op.time || op.entry_date || '—' }}
                    </td>
                    <td class="px-3 py-3 sm:px-4">
                      <span class="font-medium">{{ op.type_label }}</span>
                      <span
                        v-if="op.voucher_no"
                        class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400"
                        dir="ltr"
                      >
                        {{ op.voucher_no }}
                      </span>
                    </td>
                    <td class="max-w-[220px] truncate px-3 py-3 sm:max-w-xs sm:px-4" :title="op.party || op.memo">
                      {{ op.party || op.memo || '—' }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-3 font-semibold tabular-nums sm:px-4">
                      {{ moneyLabel(op.amount, op.currency) }}
                    </td>
                    <td class="px-3 py-3 sm:px-4">
                      <span
                        class="inline-flex rounded-lg px-2 py-0.5 text-xs font-semibold"
                        :class="directionClass(op.direction)"
                      >
                        {{ op.direction_label }}
                      </span>
                    </td>
                    <td class="px-3 py-3 text-end sm:px-4">
                      <Link
                        :href="op.link || route('ledger')"
                        class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                      >
                        {{ $t('dashboard_open_ledger') }}
                      </Link>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style>
.Vue-Toastification__container {
  width: unset !important;
}

.duet-date__dialog {
  direction: ltr;
  right: 0;
  top: 44px;
}

.header-rgRow {
  text-align: center;
}

.rgRow > div {
  text-align: center !important;
}

.rgCell.disabled {
  background-color: unset !important;
}

.rgCell {
  padding-top: 7px !important;
}

body::-webkit-scrollbar {
  width: 12px;
}

body::-webkit-scrollbar-track {
  background: #f1f1f1;
}

body::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 6px;
}

body {
  scrollbar-width: thin;
  scrollbar-color: #888 #f1f1f1;
}

@media (prefers-reduced-motion: reduce) {
  .group,
  .transition,
  .animate-spin,
  .dash-star,
  .dash-celestial {
    transition: none !important;
    animation: none !important;
  }
}

/* Full-card sky gradients (dark-safe, high-contrast white text) */
.dash-sky-dawn {
  background: linear-gradient(145deg, #312e81 0%, #4338ca 45%, #b45309 100%);
}

.dash-sky-day {
  background: linear-gradient(145deg, #0369a1 0%, #0284c7 48%, #0891b2 100%);
}

.dash-sky-sunset {
  background: linear-gradient(145deg, #c2410c 0%, #be123c 55%, #9f1239 100%);
}

.dash-sky-night {
  background: linear-gradient(155deg, #1e1b4b 0%, #0f172a 55%, #020617 100%);
}

/* Physical corners — never use start/end (RTL would flip sun onto °C) */
.dash-sky-card {
  position: relative;
}

.dash-weather-temp {
  position: absolute;
  bottom: 0;
  right: 0;
  left: auto;
}

.dash-celestial {
  position: absolute !important;
  bottom: 0.75rem !important;
  left: 0.75rem !important;
  right: auto !important;
  inset-inline-start: auto !important;
  inset-inline-end: auto !important;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  padding: 0.2rem;
  transform-origin: center;
  animation: dash-celestial-breathe 2.5s ease-in-out infinite;
}

.dash-celestial--dawn {
  filter: drop-shadow(0 0 10px rgba(251, 191, 36, 0.55));
}

.dash-celestial--day {
  filter: drop-shadow(0 0 12px rgba(253, 224, 71, 0.55));
}

.dash-celestial--sunset {
  filter: drop-shadow(0 0 12px rgba(251, 146, 60, 0.55));
}

.dash-celestial--night {
  filter: drop-shadow(0 0 14px rgba(186, 230, 253, 0.35))
    drop-shadow(0 0 6px rgba(226, 232, 240, 0.45));
}

@keyframes dash-celestial-breathe {
  0%,
  100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.15);
  }
}

.dash-star {
  opacity: 0.95;
  animation: dash-twinkle 2.4s ease-in-out infinite;
  box-shadow:
    0 0 4px 1px rgba(255, 255, 255, 0.95),
    0 0 10px 2px rgba(186, 230, 253, 0.75),
    0 0 18px 3px rgba(255, 255, 255, 0.35);
}

.dash-star-delay {
  animation-delay: 0.9s;
}

.dash-star-delay-2 {
  animation-delay: 1.7s;
}

@keyframes dash-twinkle {
  0%,
  100% {
    opacity: 0.7;
    transform: scale(0.95);
  }
  50% {
    opacity: 1;
    transform: scale(1.35);
  }
}
</style>
