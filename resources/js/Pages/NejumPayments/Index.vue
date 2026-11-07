<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/inertia-vue3";
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import { useI18n } from "vue-i18n";
import { formatMoney } from "@/utils/formatMoney";

const { t } = useI18n();

const lines = ref([]);
const summary = ref({
  count: 0,
  total_for_nejum: 0,
  total_kaml_must_pay: 0,
  total_kaml_payment: 0,
  total_customer_must_pay: 0,
  total_nejum_payments: 0,
});
const pagination = ref({ total: 0, page: 1, last_page: 1, per_page: 50 });
const loading = ref(false);
const saving = ref(false);
const lookingUp = ref(false);
const errorMsg = ref("");
const successMsg = ref("");
const showForm = ref(false);
const editingId = ref(null);

const filters = ref({
  from: "",
  to: "",
  line_type: "",
  q: "",
});

const emptyForm = () => ({
  line_type: "car",
  pr_date: today(),
  car_info: "",
  vin: "",
  lot: "",
  note: "",
  amount: 0,
  for_nejum: 0,
  nejum_shipping: 0,
  nejum_payments: 0,
  nejum_payment_date: "",
  nejum_note: "",
  transaction_fee: 0,
  commission_3mula: 0,
  kaml_must_pay: 0,
  kaml_payment: 0,
  kaml_payment_date: "",
  us_fee: 0,
  customer_commission_3mula: 0,
  customer_must_pay: 0,
  customer_name: "",
  customer_payment_date: "",
});

const form = ref(emptyForm());
const carLinked = ref(false);

function today() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

const money = (n) => formatMoney(Number(n) || 0, "$");

const kamlMustPayPreview = computed(() => {
  const forNejum = Number(form.value.for_nejum) || 0;
  const commission = Number(form.value.commission_3mula) || 0;
  return Math.round((forNejum + commission) * 10000) / 10000;
});

const customerMustPayPreview = computed(() => {
  const forNejum = Number(form.value.for_nejum) || 0;
  const commission =
    Number(form.value.customer_commission_3mula) ||
    Number(form.value.commission_3mula) ||
    0;
  const us = Number(form.value.us_fee) || 0;
  return Math.round((forNejum + commission + us) * 10000) / 10000;
});

async function load(page = 1) {
  loading.value = true;
  errorMsg.value = "";
  try {
    const { data } = await axios.get("/api/nejumPayments", {
      params: {
        ...filters.value,
        page,
        limit: 50,
      },
    });
    lines.value = data.lines || [];
    summary.value = data.summary || summary.value;
    pagination.value = data.pagination || pagination.value;
  } catch (e) {
    errorMsg.value =
      e.response?.data?.message || t("nejumPaymentsLoadFailed");
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  editingId.value = null;
  form.value = emptyForm();
  carLinked.value = false;
  showForm.value = true;
  successMsg.value = "";
  errorMsg.value = "";
}

function openEdit(row) {
  editingId.value = row.id;
  form.value = {
    line_type: row.line_type || "car",
    pr_date: row.pr_date || "",
    car_info: row.car_info || "",
    vin: row.vin || "",
    lot: row.lot || "",
    note: row.note || "",
    amount: row.amount || 0,
    for_nejum: row.for_nejum || 0,
    nejum_shipping: row.nejum_shipping || 0,
    nejum_payments: row.nejum_payments || 0,
    nejum_payment_date: row.nejum_payment_date || "",
    nejum_note: row.nejum_note || "",
    transaction_fee: row.transaction_fee || 0,
    commission_3mula: row.commission_3mula || 0,
    kaml_must_pay: row.kaml_must_pay || 0,
    kaml_payment: row.kaml_payment || 0,
    kaml_payment_date: row.kaml_payment_date || "",
    us_fee: row.us_fee || 0,
    customer_commission_3mula: row.customer_commission_3mula || 0,
    customer_must_pay: row.customer_must_pay || 0,
    customer_name: row.customer_name || "",
    customer_payment_date: row.customer_payment_date || "",
  };
  carLinked.value = !!row.car_id;
  showForm.value = true;
  successMsg.value = "";
  errorMsg.value = "";
}

function applyComputed() {
  if (!form.value.for_nejum && form.value.amount) {
    form.value.for_nejum = Number(form.value.amount) || 0;
  }
  form.value.kaml_must_pay = kamlMustPayPreview.value;
  if (!form.value.customer_commission_3mula) {
    form.value.customer_commission_3mula = Number(form.value.commission_3mula) || 0;
  }
  form.value.customer_must_pay = customerMustPayPreview.value;
}

async function lookupCar() {
  if (!form.value.vin && !form.value.lot) return;
  lookingUp.value = true;
  try {
    const { data } = await axios.get("/api/nejumPaymentsLookupCar", {
      params: { vin: form.value.vin || "", lot: form.value.lot || "" },
    });
    if (data.found && data.car) {
      carLinked.value = true;
      form.value.vin = data.car.vin || form.value.vin;
      form.value.car_info = data.car.car_info || form.value.car_info;
      successMsg.value = t("nejumPaymentsCarLinked");
    } else {
      carLinked.value = false;
      successMsg.value = t("nejumPaymentsCarNotFound");
    }
  } catch (e) {
    carLinked.value = false;
    errorMsg.value = e.response?.data?.message || t("nejumPaymentsLookupFailed");
  } finally {
    lookingUp.value = false;
  }
}

async function save() {
  saving.value = true;
  errorMsg.value = "";
  successMsg.value = "";
  applyComputed();
  try {
    if (editingId.value) {
      await axios.post(`/api/nejumPayments/${editingId.value}`, form.value);
      successMsg.value = t("nejumPaymentsUpdated");
    } else {
      await axios.post("/api/nejumPayments", form.value);
      successMsg.value = t("nejumPaymentsCreated");
    }
    showForm.value = false;
    await load(pagination.value.page || 1);
  } catch (e) {
    errorMsg.value =
      e.response?.data?.message ||
      Object.values(e.response?.data?.errors || {})?.[0]?.[0] ||
      t("nejumPaymentsSaveFailed");
  } finally {
    saving.value = false;
  }
}

async function removeLine(row) {
  if (!confirm(t("nejumPaymentsConfirmDelete"))) return;
  try {
    await axios.post(`/api/nejumPayments/${row.id}/delete`);
    successMsg.value = t("nejumPaymentsDeleted");
    await load(pagination.value.page || 1);
  } catch (e) {
    errorMsg.value = e.response?.data?.message || t("nejumPaymentsDeleteFailed");
  }
}

onMounted(() => load(1));
</script>

<template>
  <Head :title="$t('nejumPaymentsTitle')" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-100 leading-tight">
        {{ $t("nejumPaymentsTitle") }}
      </h2>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-[1600px] space-y-4 px-4 sm:px-6 lg:px-8">
        <div
          v-if="successMsg"
          class="rounded-lg border border-emerald-600/50 bg-emerald-900/80 px-4 py-3 text-sm font-semibold text-emerald-100"
        >
          {{ successMsg }}
        </div>
        <div
          v-if="errorMsg"
          class="rounded-lg border border-rose-600/50 bg-rose-900/80 px-4 py-3 text-sm font-semibold text-rose-100"
        >
          {{ errorMsg }}
        </div>

        <!-- KPI -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <div class="rounded-xl border border-slate-700 bg-slate-900 p-4">
            <p class="text-xs text-slate-300">{{ $t("nejumPaymentsTotalForNejum") }}</p>
            <p class="mt-1 text-lg font-bold tabular-nums text-sky-300">{{ money(summary.total_for_nejum) }}</p>
          </div>
          <div class="rounded-xl border border-slate-700 bg-slate-900 p-4">
            <p class="text-xs text-slate-300">{{ $t("nejumPaymentsTotalKamlMust") }}</p>
            <p class="mt-1 text-lg font-bold tabular-nums text-amber-300">{{ money(summary.total_kaml_must_pay) }}</p>
          </div>
          <div class="rounded-xl border border-slate-700 bg-slate-900 p-4">
            <p class="text-xs text-slate-300">{{ $t("nejumPaymentsTotalKamlPaid") }}</p>
            <p class="mt-1 text-lg font-bold tabular-nums text-emerald-300">{{ money(summary.total_kaml_payment) }}</p>
          </div>
          <div class="rounded-xl border border-slate-700 bg-slate-900 p-4">
            <p class="text-xs text-slate-300">{{ $t("nejumPaymentsTotalCustomerMust") }}</p>
            <p class="mt-1 text-lg font-bold tabular-nums text-violet-300">{{ money(summary.total_customer_must_pay) }}</p>
          </div>
        </div>

        <!-- Filters -->
        <div class="rounded-xl border border-slate-700 bg-slate-900 p-4">
          <div class="grid gap-3 md:grid-cols-5">
            <input v-model="filters.from" type="date" class="rounded-lg border border-slate-600 bg-slate-950 text-white" />
            <input v-model="filters.to" type="date" class="rounded-lg border border-slate-600 bg-slate-950 text-white" />
            <select v-model="filters.line_type" class="rounded-lg border border-slate-600 bg-slate-950 text-white">
              <option value="">{{ $t("nejumPaymentsAllTypes") }}</option>
              <option value="car">{{ $t("nejumPaymentsTypeCar") }}</option>
              <option value="shipping">{{ $t("nejumPaymentsTypeShipping") }}</option>
            </select>
            <input
              v-model="filters.q"
              type="search"
              class="rounded-lg border border-slate-600 bg-slate-950 text-white placeholder-slate-400"
              :placeholder="$t('nejumPaymentsSearch')"
            />
            <div class="flex gap-2">
              <button
                type="button"
                class="flex-1 rounded-lg bg-sky-600 px-3 py-2 text-sm font-bold text-white hover:bg-sky-500"
                :disabled="loading"
                @click="load(1)"
              >
                {{ $t("filter") }}
              </button>
              <button
                type="button"
                class="flex-1 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-500"
                @click="openCreate"
              >
                {{ $t("nejumPaymentsAdd") }}
              </button>
            </div>
          </div>
        </div>

        <!-- Form -->
        <div v-if="showForm" class="rounded-xl border border-slate-700 bg-slate-900 p-5 space-y-4">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-lg font-bold text-white">
              {{ editingId ? $t("nejumPaymentsEdit") : $t("nejumPaymentsAdd") }}
            </h3>
            <button type="button" class="text-sm text-slate-300 hover:text-white" @click="showForm = false">
              {{ $t("cancel") }}
            </button>
          </div>

          <div class="grid gap-3 md:grid-cols-4">
            <label class="text-sm text-slate-200">
              {{ $t("nejumPaymentsType") }}
              <select v-model="form.line_type" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white">
                <option value="car">{{ $t("nejumPaymentsTypeCar") }}</option>
                <option value="shipping">{{ $t("nejumPaymentsTypeShipping") }}</option>
              </select>
            </label>
            <label class="text-sm text-slate-200">
              {{ $t("nejumPaymentsDate") }}
              <input v-model="form.pr_date" type="date" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
            </label>
            <label class="text-sm text-slate-200 md:col-span-2">
              {{ $t("nejumPaymentsCarInfo") }}
              <input v-model="form.car_info" type="text" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
            </label>
            <label class="text-sm text-slate-200">
              VIN
              <input v-model="form.vin" type="text" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" @blur="lookupCar" />
            </label>
            <label class="text-sm text-slate-200">
              LOT
              <input v-model="form.lot" type="text" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" @blur="lookupCar" />
            </label>
            <div class="flex items-end gap-2">
              <button
                type="button"
                class="rounded-lg border border-sky-500 px-3 py-2 text-sm font-semibold text-sky-300 hover:bg-sky-900/40"
                :disabled="lookingUp"
                @click="lookupCar"
              >
                {{ lookingUp ? "..." : $t("nejumPaymentsLookupCar") }}
              </button>
              <span v-if="carLinked" class="text-xs font-semibold text-emerald-300">{{ $t("nejumPaymentsLinked") }}</span>
            </div>
            <label class="text-sm text-slate-200">
              {{ $t("nejumPaymentsNote") }}
              <input v-model="form.note" type="text" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
            </label>
          </div>

          <div class="grid gap-4 lg:grid-cols-3">
            <fieldset class="rounded-lg border border-slate-700 p-3 space-y-2">
              <legend class="px-1 text-sm font-bold text-sky-300">Nejum</legend>
              <label class="block text-xs text-slate-200">{{ $t("nejumPaymentsAmount") }}
                <input v-model.number="form.amount" type="number" step="0.01" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
              </label>
              <label class="block text-xs text-slate-200">{{ $t("nejumPaymentsForNejum") }}
                <input v-model.number="form.for_nejum" type="number" step="0.01" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
              </label>
              <label class="block text-xs text-slate-200">{{ $t("nejumPaymentsShipping") }}
                <input v-model.number="form.nejum_shipping" type="number" step="0.01" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
              </label>
              <label class="block text-xs text-slate-200">{{ $t("nejumPaymentsPaid") }}
                <input v-model.number="form.nejum_payments" type="number" step="0.01" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
              </label>
              <label class="block text-xs text-slate-200">{{ $t("nejumPaymentsPayDate") }}
                <input v-model="form.nejum_payment_date" type="date" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
              </label>
            </fieldset>

            <fieldset class="rounded-lg border border-slate-700 p-3 space-y-2">
              <legend class="px-1 text-sm font-bold text-amber-300">Kaml Kamal</legend>
              <label class="block text-xs text-slate-200">Transaction
                <input v-model.number="form.transaction_fee" type="number" step="0.01" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
              </label>
              <label class="block text-xs text-slate-200">3MULA
                <input v-model.number="form.commission_3mula" type="number" step="0.01" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
              </label>
              <label class="block text-xs text-slate-200">{{ $t("nejumPaymentsMustPay") }}
                <input v-model.number="form.kaml_must_pay" type="number" step="0.01" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
              </label>
              <p class="text-[11px] text-slate-400">≈ {{ money(kamlMustPayPreview) }}</p>
              <label class="block text-xs text-slate-200">{{ $t("nejumPaymentsPayment") }}
                <input v-model.number="form.kaml_payment" type="number" step="0.01" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
              </label>
              <label class="block text-xs text-slate-200">{{ $t("nejumPaymentsPayDate") }}
                <input v-model="form.kaml_payment_date" type="date" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
              </label>
            </fieldset>

            <fieldset class="rounded-lg border border-slate-700 p-3 space-y-2">
              <legend class="px-1 text-sm font-bold text-violet-300">{{ $t("nejumPaymentsCustomers") }}</legend>
              <label class="block text-xs text-slate-200">{{ $t("nejumPaymentsCustomerName") }}
                <input v-model="form.customer_name" type="text" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
              </label>
              <label class="block text-xs text-slate-200">US
                <input v-model.number="form.us_fee" type="number" step="0.01" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
              </label>
              <label class="block text-xs text-slate-200">3MULA
                <input v-model.number="form.customer_commission_3mula" type="number" step="0.01" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
              </label>
              <label class="block text-xs text-slate-200">{{ $t("nejumPaymentsMustPay") }}
                <input v-model.number="form.customer_must_pay" type="number" step="0.01" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
              </label>
              <p class="text-[11px] text-slate-400">≈ {{ money(customerMustPayPreview) }}</p>
              <label class="block text-xs text-slate-200">{{ $t("nejumPaymentsPayDate") }}
                <input v-model="form.customer_payment_date" type="date" class="mt-1 w-full rounded-lg border border-slate-600 bg-slate-950 text-white" />
              </label>
            </fieldset>
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-600"
              @click="applyComputed"
            >
              {{ $t("nejumPaymentsRecalc") }}
            </button>
            <button
              type="button"
              class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-500 disabled:opacity-60"
              :disabled="saving"
              @click="save"
            >
              {{ saving ? $t("saving") : $t("save") }}
            </button>
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto rounded-xl border border-slate-700 bg-slate-900">
          <table class="min-w-[1400px] w-full text-sm">
            <thead class="bg-slate-800 text-slate-200">
              <tr>
                <th class="px-3 py-2 text-start">#</th>
                <th class="px-3 py-2 text-start">{{ $t("nejumPaymentsDate") }}</th>
                <th class="px-3 py-2 text-start">{{ $t("nejumPaymentsType") }}</th>
                <th class="px-3 py-2 text-start">{{ $t("nejumPaymentsCarInfo") }}</th>
                <th class="px-3 py-2 text-start">VIN</th>
                <th class="px-3 py-2 text-start">LOT</th>
                <th class="px-3 py-2 text-end">{{ $t("nejumPaymentsAmount") }}</th>
                <th class="px-3 py-2 text-end">{{ $t("nejumPaymentsForNejum") }}</th>
                <th class="px-3 py-2 text-end">Txn</th>
                <th class="px-3 py-2 text-end">3MULA</th>
                <th class="px-3 py-2 text-end">{{ $t("nejumPaymentsMustPay") }}</th>
                <th class="px-3 py-2 text-end">{{ $t("nejumPaymentsPayment") }}</th>
                <th class="px-3 py-2 text-start">{{ $t("nejumPaymentsCustomerName") }}</th>
                <th class="px-3 py-2 text-end">{{ $t("nejumPaymentsCustomerMust") }}</th>
                <th class="px-3 py-2 text-start"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="15" class="px-3 py-8 text-center text-slate-400">...</td>
              </tr>
              <tr v-else-if="!lines.length">
                <td colspan="15" class="px-3 py-8 text-center text-slate-400">{{ $t("nejumPaymentsEmpty") }}</td>
              </tr>
              <tr
                v-for="row in lines"
                :key="row.id"
                class="border-t border-slate-800 text-slate-100 hover:bg-slate-800/60"
              >
                <td class="px-3 py-2 tabular-nums">{{ row.id }}</td>
                <td class="px-3 py-2 whitespace-nowrap">{{ row.pr_date || "—" }}</td>
                <td class="px-3 py-2">
                  <span
                    class="rounded px-1.5 py-0.5 text-[11px] font-semibold"
                    :class="row.line_type === 'shipping' ? 'bg-sky-900 text-sky-200' : 'bg-emerald-900 text-emerald-200'"
                  >
                    {{ row.line_type === "shipping" ? $t("nejumPaymentsTypeShipping") : $t("nejumPaymentsTypeCar") }}
                  </span>
                </td>
                <td class="px-3 py-2 max-w-[220px] truncate" :title="row.car_info">{{ row.car_info || "—" }}</td>
                <td class="px-3 py-2 font-mono text-xs" dir="ltr">{{ row.vin || "—" }}</td>
                <td class="px-3 py-2 font-mono text-xs" dir="ltr">{{ row.lot || "—" }}</td>
                <td class="px-3 py-2 text-end tabular-nums">{{ money(row.amount) }}</td>
                <td class="px-3 py-2 text-end tabular-nums text-sky-300">{{ money(row.for_nejum) }}</td>
                <td class="px-3 py-2 text-end tabular-nums">{{ money(row.transaction_fee) }}</td>
                <td class="px-3 py-2 text-end tabular-nums">{{ money(row.commission_3mula) }}</td>
                <td class="px-3 py-2 text-end tabular-nums text-amber-300">{{ money(row.kaml_must_pay) }}</td>
                <td class="px-3 py-2 text-end tabular-nums text-emerald-300">{{ money(row.kaml_payment) }}</td>
                <td class="px-3 py-2">{{ row.customer_name || "—" }}</td>
                <td class="px-3 py-2 text-end tabular-nums text-violet-300">{{ money(row.customer_must_pay) }}</td>
                <td class="px-3 py-2 whitespace-nowrap">
                  <button type="button" class="me-2 text-sky-300 hover:underline" @click="openEdit(row)">{{ $t("edit") }}</button>
                  <button type="button" class="text-rose-300 hover:underline" @click="removeLine(row)">{{ $t("delete") }}</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="pagination.last_page > 1" class="flex items-center justify-center gap-3">
          <button
            type="button"
            class="rounded-lg border border-slate-600 px-3 py-1.5 text-sm text-slate-200 disabled:opacity-40"
            :disabled="pagination.page <= 1 || loading"
            @click="load(pagination.page - 1)"
          >
            ‹
          </button>
          <span class="text-sm text-slate-300">{{ pagination.page }} / {{ pagination.last_page }}</span>
          <button
            type="button"
            class="rounded-lg border border-slate-600 px-3 py-1.5 text-sm text-slate-200 disabled:opacity-40"
            :disabled="pagination.page >= pagination.last_page || loading"
            @click="load(pagination.page + 1)"
          >
            ›
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
