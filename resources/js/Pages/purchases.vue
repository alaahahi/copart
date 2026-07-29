<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/inertia-vue3';
import Modal from "@/Components/Modal.vue";
import ModalAddCar from "@/Components/ModalAddCars.vue";
import ModalEditCars from "@/Components/ModalEditCars.vue";
import ModalAddCarExpenses from "@/Components/ModalAddCarExpenses.vue";
import ModalAddCarPayment from "@/Components/ModalAddCarPayment.vue";
import ModalDelCar from "@/Components/ModalDelCar.vue";
import { useToast } from "vue-toastification";
import axios from 'axios';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import show from "@/Components/icon/show.vue";
import trash from "@/Components/icon/trash.vue";
import edit from "@/Components/icon/edit.vue";
import InfiniteLoading from "v3-infinite-loading";
import "v3-infinite-loading/lib/style.css";
import { erbilTransferSubtotal, ensureErbilFormFields } from "@/utils/carFields";
import { asNumber, formatMoney } from "@/utils/formatMoney";
import { carPaymentStatusMeta } from "@/utils/carPaymentStatus";
import { carProfit } from "@/utils/carProfit";
import debounce from 'lodash/debounce';
import SearchInput from "@/Components/SearchInput.vue";
import CarsGridView from "@/Components/CarsGridView.vue";
import PinOtpInput from "@/Components/PinOtpInput.vue";

defineProps({ client: Array, auctions: { type: Array, default: () => [] } });

const toast = useToast();
const money = (v) => formatMoney(v, "$");

/** 5-digit frontend gate; unlock expires after 30 minutes */
const PURCHASES_PIN = "12457";
const PIN_STORAGE_KEY = "purchases-pin-ok";
const PIN_OK_AT_KEY = "purchases-pin-ok-at";
const PIN_TTL_MS = 30 * 60 * 1000;
const pinUnlocked = ref(false);
const pinInput = ref("");
const pinStatus = ref("idle"); // idle | error | success
const pinOtpRef = ref(null);
let pinUnlockTimer = null;
let pinExpiryInterval = null;

function clearPinStorage() {
  try {
    sessionStorage.removeItem(PIN_STORAGE_KEY);
    sessionStorage.removeItem(PIN_OK_AT_KEY);
  } catch {
    /* ignore quota / private mode */
  }
}

function lockPinGate() {
  pinUnlocked.value = false;
  pinStatus.value = "idle";
  pinInput.value = "";
  clearPinStorage();
}

function isPinUnlockValid() {
  try {
    const raw = sessionStorage.getItem(PIN_OK_AT_KEY);
    const unlockedAt = raw ? Number(raw) : NaN;
    if (!Number.isFinite(unlockedAt) || unlockedAt <= 0) {
      clearPinStorage();
      return false;
    }
    if (Date.now() - unlockedAt >= PIN_TTL_MS) {
      clearPinStorage();
      return false;
    }
    return true;
  } catch {
    return false;
  }
}

function refreshPinUnlockState() {
  const ok = isPinUnlockValid();
  if (!ok && pinUnlocked.value) {
    lockPinGate();
    return;
  }
  pinUnlocked.value = ok;
}

function onVisibilityChange() {
  if (document.visibilityState === "visible") {
    refreshPinUnlockState();
  }
}

const CARS_VIEW_KEY = "purchases-cars-view";
const carsViewMode = ref(
  typeof localStorage !== "undefined" && localStorage.getItem(CARS_VIEW_KEY) === "grid"
    ? "grid"
    : "list"
);
watch(carsViewMode, (mode) => {
  try {
    localStorage.setItem(CARS_VIEW_KEY, mode);
  } catch (_) {
    /* ignore quota / private mode */
  }
});
const setCarsViewMode = (mode) => {
  carsViewMode.value = mode === "grid" ? "grid" : "list";
};

onMounted(() => {
  refreshPinUnlockState();
  document.addEventListener("visibilitychange", onVisibilityChange);
  pinExpiryInterval = setInterval(refreshPinUnlockState, 60 * 1000);
});

onUnmounted(() => {
  document.removeEventListener("visibilitychange", onVisibilityChange);
  if (pinExpiryInterval) clearInterval(pinExpiryInterval);
  if (pinUnlockTimer) clearTimeout(pinUnlockTimer);
});

function onPinComplete(code) {
  if (pinStatus.value === "success") return;
  const entered = String(code || "").trim();
  if (entered === PURCHASES_PIN) {
    pinStatus.value = "success";
    if (pinUnlockTimer) clearTimeout(pinUnlockTimer);
    pinUnlockTimer = setTimeout(() => {
      const unlockedAt = Date.now();
      pinUnlocked.value = true;
      pinInput.value = "";
      try {
        sessionStorage.setItem(PIN_STORAGE_KEY, "1");
        sessionStorage.setItem(PIN_OK_AT_KEY, String(unlockedAt));
      } catch {
        /* ignore quota / private mode */
      }
    }, 320);
    return;
  }
  pinStatus.value = "error";
  toast.error("رمز غير صحيح", {
    timeout: 3000,
    position: "bottom-right",
    rtl: true,
  });
  setTimeout(() => {
    pinInput.value = "";
    pinStatus.value = "idle";
    pinOtpRef.value?.clearAndFocus();
  }, 450);
}

const data = ref({});
const from = ref('');
const to = ref('');
const showModal = ref(false);
const showModalAddCarExpenses = ref(false);
const showModalCar = ref(false);
const showModalAddCarPayment = ref(false);
const showModalEditCars = ref(false);
const showModalDelCar = ref(false);
const formData = ref({});
const car = ref([]);
const json = ref({});
const resetData = ref(false);
const currentWork = ref(true);
let user_id = "";
let page = 1;
let q = '';

const kpiCars = computed(() => asNumber(json.value?.totalCars));
const kpiCosts = computed(() => asNumber(json.value?.resultsDollar));
const kpiSales = computed(() => asNumber(json.value?.resultsTotalS));
const kpiPaid = computed(() => asNumber(json.value?.resultsPaid));
const kpiDebt = computed(() => kpiSales.value - kpiPaid.value);
const kpiProfit = computed(() => asNumber(json.value?.resultsProfit));

const inputClass =
  "min-h-[42px] w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-500/20 dark:border-slate-600 dark:bg-slate-950 dark:text-white";

function openModalEditCars(form = {}) {
  formData.value = JSON.parse(JSON.stringify(form || {}));
  ensureErbilFormFields(formData.value, false);
  showModalEditCars.value = true;
}
function openModalDelCar(form = {}) {
  formData.value = form;
  showModalDelCar.value = true;
}
function openAddCar(form = {}) {
  formData.value = form;
  ensureErbilFormFields(formData.value, false);
  showModalCar.value = true;
}
function openAddCarPayment(form = {}) {
  formData.value = form;
  showModalAddCarPayment.value = true;
}

const refresh = () => {
  page = 0;
  car.value.length = 0;
  resetData.value = !resetData.value;
};

const getResultsCar = async ($state) => {
  try {
    const response = await axios.get(`/getIndexCar`, {
      params: {
        limit: 100,
        page,
        q,
        user_id,
        from: from.value,
        to: to.value,
      },
    });

    json.value = response.data;

    if (json.value.data.length < 100) {
      car.value.push(...json.value.data);
      $state.complete();
    } else {
      car.value.push(...json.value.data);
      $state.loaded();
    }
    page++;
  } catch (error) {
    console.log(error);
  }
};

function confirmExpensesCar(V) {
  axios
    .post('/api/confirmExpensesCar', V)
    .then(() => {
      showModalAddCarExpenses.value = false;
      toast.success("تم إضافة السيارة بنجاح ", {
        timeout: 3000,
        position: "bottom-right",
        rtl: true,
      });
      refresh();
    })
    .catch((error) => console.error(error));
}

function confirmCar(V) {
  axios
    .post('/api/addCars', V)
    .then(() => {
      showModalCar.value = false;
      refresh();
    })
    .catch((error) => {
      const msg =
        error?.response?.data?.errors?.vin?.[0] ||
        error?.response?.data?.message ||
        'تعذر إضافة السيارة';
      toast.error(msg, {
        timeout: 4000,
        position: 'bottom-right',
        rtl: true,
      });
      console.error(error);
    });
}

function confirmUpdateCar(V) {
  showModalEditCars.value = false;
  axios
    .post('/api/updateCarsP', V)
    .then(() => {
      showModal.value = false;
      toast.success("تم التعديل بنجاح", {
        timeout: 2000,
        position: "bottom-right",
        rtl: true,
      });
      refresh();
    })
    .catch(() => {
      showModal.value = false;
      toast.error("لم التعديل بنجاح", {
        timeout: 2000,
        position: "bottom-right",
        rtl: true,
      });
    });
}

function confirmDelCar(V) {
  axios
    .post('/api/DelCar', V)
    .then(() => {
      showModalDelCar.value = false;
      refresh();
    })
    .catch((error) => console.error(error));
}

function confirmAddPayment(V) {
  axios
    .get(`/api/addPaymentCar?car_id=${V.id}&discount=${V.discountPayment ?? 0}&amount=${V.amountPayment ?? 0}&note=${V.notePayment ?? ''}`)
    .then((response) => {
      showModalAddCarPayment.value = false;
      toast.success(" تم دفع مبلغ دولار " + V.amountPayment + " بنجاح ", {
        timeout: 3000,
        position: "bottom-right",
        rtl: true,
      });
      const transaction = response.data;
      window.open(
        `/api/getIndexAccountsSelas?user_id=${V.client.id}&print=2&transactions_id=${transaction.id}`,
        '_blank'
      );
    })
    .catch(() => {
      showModal.value = false;
      toast.error("لم التعديل بنجاح", {
        timeout: 2000,
        position: "bottom-right",
        rtl: true,
      });
    });
}

const debouncedGetResultsCar = debounce(refresh, 500);

/** Solid dark-safe row surfaces from payment amounts. */
function rowClass(row) {
  const { status } = carPaymentStatusMeta(row);
  if (status === "paid") {
    return "bg-emerald-100 text-slate-800 dark:bg-emerald-900 dark:text-slate-100";
  }
  if (status === "partially_paid") {
    return "bg-amber-100 text-slate-800 dark:bg-amber-900 dark:text-slate-100";
  }
  if (status === "unpaid" && asNumber(row?.total_s) > 0) {
    return "bg-rose-100 text-slate-800 dark:bg-rose-900 dark:text-slate-100";
  }
  return "bg-white text-slate-800 dark:bg-slate-900 dark:text-slate-100";
}

function rowProfit(row) {
  return carProfit(row);
}
</script>

<template>
  <Head :title="$t('purchases')" />

  <ModalAddCarExpenses
    :formData="formData"
    :show="showModalAddCarExpenses ? true : false"
    :currentWork="currentWork"
    @a="confirmExpensesCar($event)"
    @close="showModalAddCarExpenses = false"
  >
    <template #header />
  </ModalAddCarExpenses>

  <Modal
    :data="data"
    :show="showModal ? true : false"
    @a="confirmUpdateCar($event)"
    @close="showModal = false"
  >
    <template #header>
      <h2 class="text-center text-lg">هل متأكد من تعديل البيانات</h2>
    </template>
  </Modal>

  <ModalAddCar
    :formData="formData"
    :show="showModalCar ? true : false"
    :client="client"
    :auctions="auctions"
    @a="confirmCar($event)"
    @close="showModalCar = false"
  >
    <template #header />
  </ModalAddCar>

  <ModalEditCars
    :formData="formData"
    :show="showModalEditCars ? true : false"
    :client="client"
    :auctions="auctions"
    @a="confirmUpdateCar($event)"
    @close="showModalEditCars = false"
  >
    <template #header />
  </ModalEditCars>

  <ModalAddCarPayment
    :formData="formData"
    :show="showModalAddCarPayment ? true : false"
    @a="confirmAddPayment($event)"
    @close="showModalAddCarPayment = false"
  >
    <template #header />
  </ModalAddCarPayment>

  <ModalDelCar
    :show="showModalDelCar ? true : false"
    :formData="formData"
    @a="confirmDelCar($event)"
    @close="showModalDelCar = false"
  >
    <template #header>
      <h2 class="mb-5 text-center text-slate-800 dark:text-slate-200">
        هل متأكد من حذف السيارة؟
      </h2>
    </template>
  </ModalDelCar>

  <AuthenticatedLayout>
    <!-- PIN unlock gate (frontend only, per-tab; expires after 30 minutes) -->
    <section
      v-if="!pinUnlocked"
      class="flex min-h-[70vh] items-center justify-center px-4 py-10"
    >
      <div
        class="w-full max-w-md rounded-xl border border-slate-600 bg-slate-900 p-6 shadow-xl sm:p-8"
      >
        <h1 class="mb-2 text-center text-xl font-bold text-white sm:text-2xl">
          {{ $t("purchases") }}
        </h1>
        <p class="mb-6 text-center text-sm text-slate-200">
          أدخل رمز الدخول لعرض المشتريات
        </p>
        <div class="space-y-4">
          <div>
            <p
              id="purchases-pin-label"
              class="mb-3 text-center text-sm font-medium text-slate-200"
            >
              رمز الدخول
            </p>
            <PinOtpInput
              ref="pinOtpRef"
              v-model="pinInput"
              :length="5"
              :status="pinStatus"
              :disabled="pinStatus === 'success'"
              aria-label-prefix="خانة رمز الدخول"
              @complete="onPinComplete"
            />
            <p
              v-if="pinStatus === 'error'"
              class="mt-3 text-center text-sm text-rose-300"
              role="alert"
            >
              رمز غير صحيح
            </p>
          </div>
        </div>
      </div>
    </section>

    <section
      v-else-if="$page.props.auth.user.type_id == 1 || $page.props.auth.user.type_id == 6"
      class="py-4 sm:py-6"
    >
      <div class="mx-auto max-w-9xl px-3 sm:px-6 lg:px-8">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <div class="border-b border-slate-200 p-4 dark:border-slate-700">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
              <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                {{ $t("purchases") }}
              </h1>
              <div class="flex flex-wrap items-center gap-2">
                <div
                  class="inline-flex rounded-lg border border-slate-300 bg-slate-100 p-0.5 shadow-sm dark:border-slate-600 dark:bg-slate-800"
                  role="group"
                  :aria-label="$t('view_mode')"
                >
                  <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold transition"
                    :class="
                      carsViewMode === 'list'
                        ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white'
                        : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100'
                    "
                    :aria-pressed="carsViewMode === 'list'"
                    @click="setCarsViewMode('list')"
                  >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path d="M3 5h14a1 1 0 110 2H3a1 1 0 110-2zm0 4h14a1 1 0 110 2H3a1 1 0 110-2zm0 4h14a1 1 0 110 2H3a1 1 0 110-2z" />
                    </svg>
                    {{ $t("view_list") }}
                  </button>
                  <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold transition"
                    :class="
                      carsViewMode === 'grid'
                        ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white'
                        : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100'
                    "
                    :aria-pressed="carsViewMode === 'grid'"
                    @click="setCarsViewMode('grid')"
                  >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                      <path d="M3 3h6v6H3V3zm8 0h6v6h-6V3zM3 11h6v6H3v-6zm8 0h6v6h-6v-6z" />
                    </svg>
                    {{ $t("view_grid") }}
                  </button>
                </div>
                <button
                  type="button"
                  class="inline-flex min-h-[42px] items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                  @click="openAddCar()"
                >
                  {{ $t("addCar") }}
                </button>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
              <div class="lg:col-span-2">
                <SearchInput
                  v-model="q"
                  :placeholder="$t('search')"
                  @input="debouncedGetResultsCar"
                />
              </div>

              <select v-model="user_id" :class="inputClass" @change="refresh()">
                <option value="" disabled>{{ $t("selectCustomer") }}</option>
                <option value="">{{ $t("allOwners") }}</option>
                <option v-for="(user, index) in client" :key="index" :value="user.id">{{ user.name }}</option>
              </select>

              <input v-model="from" type="date" :class="inputClass" :aria-label="$t('from_date')" />
              <input v-model="to" type="date" :class="inputClass" :aria-label="$t('to_date')" />

              <button
                type="button"
                class="min-h-[42px] rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                @click.prevent="refresh()"
              >
                {{ $t("filter") }}
              </button>
            </div>
          </div>

          <!-- Single KPI strip — net profit = Σ(total_s − total), same as table rows -->
          <div class="border-b border-slate-200 p-4 dark:border-slate-700">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
              <div class="rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                <div class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $t("cars_with_filter") }}</div>
                <div class="mt-1 font-mono text-lg font-bold tabular-nums text-slate-900 dark:text-white">{{ kpiCars }}</div>
              </div>
              <div class="rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                <div class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $t("total_costs_usd") }}</div>
                <div class="mt-1 font-mono text-lg font-bold tabular-nums text-slate-900 dark:text-white">{{ money(kpiCosts) }}</div>
              </div>
              <div class="rounded-xl border border-sky-400 bg-white px-4 py-3 shadow-sm dark:border-sky-500/50 dark:bg-slate-800">
                <div class="text-xs font-semibold text-sky-800 dark:text-sky-300">{{ $t("total_sales_usd") }}</div>
                <div class="mt-1 font-mono text-lg font-bold tabular-nums text-sky-700 dark:text-sky-200">{{ money(kpiSales) }}</div>
              </div>
              <div class="rounded-xl border border-emerald-400 bg-white px-4 py-3 shadow-sm dark:border-emerald-500/50 dark:bg-slate-800">
                <div class="text-xs font-semibold text-emerald-800 dark:text-emerald-300">{{ $t("total_paid_usd") }}</div>
                <div class="mt-1 font-mono text-lg font-bold tabular-nums text-emerald-700 dark:text-emerald-200">{{ money(kpiPaid) }}</div>
              </div>
              <div class="rounded-xl border border-amber-400 bg-white px-4 py-3 shadow-sm dark:border-amber-500/50 dark:bg-slate-800">
                <div class="text-xs font-semibold text-amber-800 dark:text-amber-300">{{ $t("total_debt_usd") }}</div>
                <div class="mt-1 font-mono text-lg font-bold tabular-nums text-amber-700 dark:text-amber-200">{{ money(kpiDebt) }}</div>
              </div>
              <div class="rounded-xl border border-indigo-400 bg-white px-4 py-3 shadow-sm dark:border-indigo-500/50 dark:bg-slate-800">
                <div class="text-xs font-semibold text-indigo-800 dark:text-indigo-300">{{ $t("analytics_net_profit") }}</div>
                <div
                  class="mt-1 font-mono text-lg font-bold tabular-nums"
                  :class="kpiProfit >= 0 ? 'text-emerald-700 dark:text-emerald-200' : 'text-rose-700 dark:text-rose-300'"
                >
                  {{ money(kpiProfit) }}
                </div>
                <div class="mt-0.5 text-[10px] font-medium text-slate-500 dark:text-slate-400">
                  {{ $t("profit_sales_only_hint") }}
                </div>
              </div>
            </div>
          </div>

          <div class="p-4">
            <CarsGridView
              v-if="carsViewMode === 'grid'"
              :cars="car"
              variant="purchase"
            >
              <template #actions="{ car: row }">
                <button
                  type="button"
                  class="inline-flex items-center rounded-md bg-slate-600 px-1.5 py-0.5 text-white hover:bg-slate-700"
                  :title="$t('edit')"
                  @click="openModalEditCars(row)"
                >
                  <edit />
                </button>
                <button
                  type="button"
                  class="inline-flex items-center rounded-md bg-orange-500 px-1.5 py-0.5 text-white hover:bg-orange-600"
                  :title="$t('trash')"
                  @click="openModalDelCar(row)"
                >
                  <trash />
                </button>
                <Link
                  class="inline-flex items-center rounded-md bg-sky-600 px-1.5 py-0.5 text-white hover:bg-sky-700"
                  :href="route('showClients', row?.client?.id || 0)"
                >
                  <show />
                </Link>
              </template>
            </CarsGridView>

            <!-- List / table -->
            <div
              v-else
              class="relative overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700"
            >
              <table class="w-full text-center text-sm text-slate-800 dark:text-slate-100">
                <thead>
                  <tr class="bg-slate-800 text-slate-100 dark:bg-slate-950">
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("no") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("car_owner") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("car_type") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("year") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("color") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("vin") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("car_number") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("car_price_usa") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("transfer_usa") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("recovery") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("repair_expenses") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("transfer_erbil") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("erbil_expenses") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("total") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("paid") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("profit") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("date") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("note") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold" style="min-width: 140px">{{ $t("execute") }}</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                  <tr
                    v-for="(row, index) in car"
                    :key="row.id"
                    :class="rowClass(row)"
                    class="border-b border-slate-200 hover:brightness-95 dark:border-slate-700 dark:hover:brightness-110"
                  >
                    <td class="px-1 py-2 tabular-nums">{{ index + 1 }}</td>
                    <td class="px-1 py-2 font-semibold text-slate-900 dark:text-white">{{ row.client?.name }}</td>
                    <td class="px-1 py-2">{{ row.car_type }}</td>
                    <td class="px-1 py-2">{{ row.year }}</td>
                    <td class="px-1 py-2">{{ row.car_color }}</td>
                    <td class="px-1 py-2 font-mono text-xs">{{ row.vin }}</td>
                    <td class="px-1 py-2">{{ row.car_number }}</td>
                    <td class="px-1 py-2 tabular-nums">{{ money(row.shipping_dolar) }}</td>
                    <td class="px-1 py-2 tabular-nums">{{ money(row.dinar) }}</td>
                    <td class="px-1 py-2 tabular-nums">{{ money(row.coc_dolar) }}</td>
                    <td class="px-1 py-2 tabular-nums">{{ money(row.checkout) }}</td>
                    <td class="px-1 py-2 tabular-nums">{{ money(erbilTransferSubtotal(row, false)) }}</td>
                    <td class="px-1 py-2 tabular-nums">{{ money(row.commission ?? 0) }}</td>
                    <td class="px-1 py-2 font-semibold tabular-nums">{{ money(row.total) }}</td>
                    <td class="px-1 py-2 tabular-nums text-emerald-700 dark:text-emerald-300">{{ money(row.paid) }}</td>
                    <td
                      class="px-1 py-2 font-semibold tabular-nums"
                      :class="
                        rowProfit(row) == null
                          ? 'text-slate-500 dark:text-slate-400'
                          : rowProfit(row) >= 0
                            ? 'text-emerald-700 dark:text-emerald-300'
                            : 'text-rose-700 dark:text-rose-300'
                      "
                    >
                      <template v-if="rowProfit(row) == null">{{ $t("profit_not_calculated") }}</template>
                      <template v-else>{{ money(rowProfit(row)) }}</template>
                    </td>
                    <td class="whitespace-nowrap px-1 py-2">{{ row.date }}</td>
                    <td class="max-w-[140px] truncate px-1 py-2" :title="row.note">{{ row.note }}</td>
                    <td class="px-1 py-2">
                      <div class="flex items-center justify-center gap-1">
                        <button
                          type="button"
                          class="rounded bg-slate-600 px-1.5 py-1 text-white hover:bg-slate-700"
                          @click="openModalEditCars(row)"
                        >
                          <edit />
                        </button>
                        <button
                          type="button"
                          class="rounded bg-orange-500 px-1.5 py-1 text-white hover:bg-orange-600"
                          @click="openModalDelCar(row)"
                        >
                          <trash />
                        </button>
                        <Link
                          class="inline-flex rounded bg-sky-600 px-1.5 py-1 text-white hover:bg-sky-700"
                          :href="route('showClients', row?.client?.id || 0)"
                        >
                          <show />
                        </Link>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="spaner mt-2">
              <InfiniteLoading :car="car" :identifier="resetData" @infinite="getResultsCar" />
            </div>
          </div>
        </div>
      </div>
    </section>
  </AuthenticatedLayout>
</template>
