<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useToast } from "vue-toastification";
import axios from 'axios';
import { ref, watch } from 'vue';
import { useI18n } from "vue-i18n";
import { Head, Link, usePage } from "@inertiajs/inertia-vue3";
import { debounce } from 'lodash';

const auth = defineProps(['auth']);
const appName = usePage().props.value.appName;

const { t } = useI18n();
let userType = ref(auth.auth.user.type_id);

function selectUser() {
  return 'getIndexClients';
}

let data = ref({});
const laravelData = ref({});
let controller = new AbortController();

const getResults = async (page = 1) => {
  axios.get(`api/${selectUser(userType.value)}?page=${page}&q=debit&exclude_zero=1`)
    .then(response => {
      if (userType.value == 1 || userType.value == 6) {
        try {
          laravelData.value = response.data.data;
        } catch (error) {
          laravelData.value = response.data.data;
        }
      }
    })
    .catch(error => {
      console.error(error);
    });
};
getResults();

let expenses_type_id = ref(0);
const toast = useToast();
let showModal = ref(false);

function sendWhatsAppMessage(phoneNumber) {
  if (phoneNumber) {
    phoneNumber = '964' + phoneNumber;
    const message = `السلام عليكم: ${appName} - أربيل ,يرجى الأخذ بالعلم تسديد المبلغ المستحق عليكم في أقرب وقت ممكن. شكرا لتعاونكم  ..........   سڵاوی خواتان لێبێت: کۆمپانیای ${appName} - تکایە ئاگاداربن بە زووترین کات ئەو بڕە پارەیەی کە قەرزارن بیدەن. سوپاس بۆ هەماهەنگیت`;
    const whatsappURL = `https://api.whatsapp.com/send?phone=${phoneNumber}&text=${encodeURIComponent(message)}`;
    window.open(whatsappURL);
  }
}

let searchTerm = ref('');
let mainAccount = ref(0);
let howler = ref(0);
let shippingCoc = ref(0);
let border = ref(0);
let iran = ref(0);
let dubai = ref(0);
let purchasesCost = ref(0);
let clientPaid = ref(0);
let clientDebit = ref(0);
let mainBoxDollar = ref(0);
let mainBoxDinar = ref(0);
let allCars = ref(0);

function openModal() {
  showModal.value = true;
}

const formData = ref({});
const car = ref([]);

const debouncedGetResultsCarSearch = debounce(async (q = '', page = 1) => {
  if (!q) {
    q = 'debit';
  }
  try {
    const response = await axios.get(`api/${selectUser(userType.value)}?page=${page}&q=${q}`, {
      signal: controller.signal,
    });
    laravelData.value = response.data.data;
  } catch (error) {
    console.error(error);
  }
}, 300);

const getResultsCarSearch = (q = '', page = 1) => {
  debouncedGetResultsCarSearch(q, page);
};

const getcountTotalInfo = async () => {
  axios.get('/api/totalInfo', {
    headers: {
      Authorization: 'Bearer ' + auth.auth.accessToken,
    },
  })
    .then(response => {
      mainAccount.value = response.data.data.mainAccount;
      allCars.value = response.data.data.allCars;
      purchasesCost.value = response.data.data.purchasesCost;
      clientPaid.value = response.data.data.clientPaid;
      clientDebit.value = response.data.data.clientDebit;
      mainBoxDollar.value = response.data.data.mainBoxDollar;
      mainBoxDinar.value = response.data.data.mainBoxDinar;
    })
    .catch(error => {
      console.error(error);
    });
};

const abortRequest = () => {
  if (controller) {
    controller.abort();
  }
  controller = new AbortController();
};

watch([searchTerm], () => {
  abortRequest();
  debouncedGetResultsCarSearch();
});

getcountTotalInfo();

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

function updateResults(input) {
  if (typeof input !== 'number') {
    input = parseFloat(input) || 0;
  }
  return input.toLocaleString();
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
            <div class="relative">
              <div class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3.5">
                <svg
                  class="h-5 w-5 text-slate-400 dark:text-slate-500"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                  aria-hidden="true"
                >
                  <path
                    fill-rule="evenodd"
                    d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                    clip-rule="evenodd"
                  />
                </svg>
              </div>
              <input
                id="dashboard-search"
                v-model="searchTerm"
                type="search"
                :placeholder="$t('search_merchant')"
                autocomplete="off"
                class="block min-h-[44px] w-full rounded-xl border border-slate-300 bg-white py-2.5 ps-11 pe-4 text-sm text-slate-900 placeholder:text-slate-400 shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-indigo-400 dark:focus:ring-indigo-400/30"
                @input="getResultsCarSearch(searchTerm)"
              />
            </div>
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
                {{ updateResults(mainBoxDinar) }}
                <span class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $t('iqd') }}</span>
              </p>
            </div>
          </div>
        </div>

        <!-- Trader debts -->
        <section class="mt-6 sm:mt-8">
          <div class="mb-3 flex items-center justify-between gap-3 sm:mb-4">
            <h2 class="text-base font-bold text-slate-900 dark:text-white sm:text-lg">
              {{ $t('merchant_debts') }}
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 sm:text-sm">
              {{ $t('double_click_whatsapp') }}
            </p>
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
              @dblclick.prevent="sendWhatsAppMessage(user.phone)"
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
  .transition {
    transition: none !important;
  }
}
</style>
