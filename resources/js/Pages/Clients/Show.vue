<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import { Head, Link, useForm } from "@inertiajs/inertia-vue3";
import { ref, computed } from "vue";
import { TailwindPagination } from "laravel-vue-pagination";
import TextInput from "@/Components/TextInput.vue";
import axios from "axios";
import ModalDelCar from "@/Components/ModalDelCar.vue";
import ModalEditCars from "@/Components/ModalEditCar_S.vue";
import ModalAddCarPayment from "@/Components/ModalAddCarPayment.vue";
import print from "@/Components/icon/print.vue";
import pay from "@/Components/icon/pay.vue";
import trash from "@/Components/icon/trash.vue";
import edit from "@/Components/icon/edit.vue";
import { erbilTransferSubtotal, syncSalesErbilFromPurchase } from "@/utils/carFields";

import { useToast } from "vue-toastification";
let toast = useToast();

// Backend sometimes returns numeric fields as strings; guard `.toFixed()` usages.
const asNumber = (v) => {
  if (v === null || v === undefined || v === "") return 0;
  if (typeof v === "number") return Number.isFinite(v) ? v : 0;
  const n = Number.parseFloat(String(v).replace(/,/g, ""));
  return Number.isFinite(n) ? n : 0;
};
const fixed = (v, digits = 0) => asNumber(v).toFixed(digits);

// اسم الحساب المحاسبي الحقيقي (صندوق دولار/دينار أو حساب القاصة) الذي ذهب إليه المبلغ،
// قادم من القيد المحاسبي (journal) المرتبط بالحركة أو بحركة الصندوق الأم لها.
const getMoneyAccountLabel = (tran) => tran?.money_account?.name_ar || tran?.money_account?.name || null;
const getMoneyAccountBadgeClass = (tran) => {
  const code = tran?.money_account?.code ?? '';
  if (code === '1100' || code === '1110') return 'money-account-badge money-account-badge--cash';
  if (code === '1120' || code === '1130') return 'money-account-badge money-account-badge--treasury';
  if (tran?.money_account) return 'money-account-badge money-account-badge--other';
  return 'money-account-badge money-account-badge--none';
};
let sums= ref(0);
let laravelData = ref({});
let isLoading = ref(0);
let from = ref(0);
let to = ref(0);
let showPaymentForm = ref(false);
let showModalEditCars = ref(false);
let showModalDelCar = ref(false);
let showModalAddCarPayment = ref(false);
let showErorrAmount = ref(false);
let showTransactions= ref(false);
let showComplatedCars = ref(false);
let total = ref(0);
let formData = ref({});
let discount= ref(0);
let note = ref('');
let amount = ref(0);

let client_Select = ref(0);
let showModalAddPayFromBalanceCar = ref(false);
let showModalDelPayFromBalanceCar = ref(false);



let getResults = async (page = 1) => {
  axios
    .get(`/api/getIndexAccountsSelas?page=${page}&user_id=${props.client_id}&from=${from.value}&to=${to.value}`)
    .then((response) => {
      laravelData.value = response.data;
      client_Select.value = response.data.client.id;
    })
    .catch((error) => {
      console.error(error);
    });
};
function calculateTotalFilteredAmount() {
  if(laravelData.value && laravelData.value.transactions && Array.isArray(laravelData.value.transactions)){
     const filteredTransactions = laravelData.value.transactions.filter(user =>
    user.type === 'out' && asNumber(user?.amount) < 0 && asNumber(user?.is_pay) === 1
  );
  
  const totalAmount = filteredTransactions.reduce((sum, user) => {
    const amount = asNumber(user?.amount);
    return sum + amount;
  }, 0);

  return {  totalAmount };
  }
  return { totalAmount: 0 };
}

/** Remaining = cars total − paid on cars − discounts (backend cars_need_paid when available). */
const clientBalanceUsd = computed(() => {
  if (laravelData.value?.cars_need_paid !== undefined && laravelData.value?.cars_need_paid !== null) {
    return asNumber(laravelData.value.cars_need_paid);
  }
  return (
    asNumber(laravelData.value?.cars_sum) -
    asNumber(laravelData.value?.cars_paid) -
    asNumber(laravelData.value?.cars_discount)
  );
});

/** Payments received on wallet but not allocated to cars yet. */
const undistributedBalanceUsd = computed(() => {
  const paymentsReceived = asNumber(calculateTotalFilteredAmount().totalAmount) * -1;
  return paymentsReceived - asNumber(laravelData.value?.cars_paid);
});
const getResultsSelect = async (page = 1) => {

  axios
    .get(`/api/getIndexAccountsSelas?page=${page}&user_id=${client_Select.value}&from=${from.value}&to=${to.value}`)
    .then((response) => {
      laravelData.value = response.data;
      client_Select.value = response.data.client.id;
    })
    .catch((error) => {
      console.error(error);
    });
};
getResults();

// Do NOT auto-call checkClientBalance on load — the old frontend formula
// (payments − cars) never matches ledger AR, so every visit toasted
// "تم تصحيح الرصيد", synced the wallet, and re-fetched in a confusing loop.

const props = defineProps({
  url: String,
  clients: Array,
  client_id: String,
  client: Object,
  q:String,
  auctions: { type: Array, default: () => [] }
});

/** Opaque status backgrounds so text stays readable in light and dark mode. */
const carRowClass = (car) => {
  const qVal = (props.q || '').toString();
  const matchesSearch =
    qVal !== '' &&
    (String(car.vin || '').startsWith(qVal) ||
      String(car.car_number || '').startsWith(qVal));

  const base =
    'hover:brightness-95 dark:hover:brightness-110 transition-colors';

  if (matchesSearch) {
    return `${base} bg-amber-200 dark:bg-amber-800`;
  }
  if (car.results == 0 || car.results == 1) {
    return `${base} bg-rose-200 dark:bg-rose-900`;
  }
  if (car.results == 2) {
    return `${base} bg-emerald-200 dark:bg-emerald-900`;
  }
  return `${base} bg-white dark:bg-slate-900`;
};

const form = useForm();

let showModal = ref(false);


function method1(id) {
  form.get(route("sentToCourt", id));
  getResults();
  showModal.value = false;
}
function openModalDelCar(form = {}) {
  formData.value = form;
  showModalDelCar.value = true;
}
function openModalAddPayFromBalanceCar(form = {}) {
  formData.value = form;
  showModalAddPayFromBalanceCar.value = true;
}
function openModalDelPayFromBalanceCar(form = {}) {
  formData.value = form;
  showModalDelPayFromBalanceCar.value = true;
}
function openModalEditCars(form={}){
  // Clone so edits don't mutate the table row, and refreshes don't remount/close the modal mid-edit.
  formData.value = JSON.parse(JSON.stringify(form || {}));
  if(formData.value.shipping_dolar_s==0){
    formData.value.shipping_dolar_s=formData.value.shipping_dolar
  }
  if(formData.value.coc_dolar_s==0){
    formData.value.coc_dolar_s=formData.value.coc_dolar
  }
  if(formData.value.checkout_s==0){
    formData.value.checkout_s=formData.value.checkout
  }
  if(formData.value.dinar_s==0){
    formData.value.dinar_s=formData.value.dinar
  }
  syncSalesErbilFromPurchase(formData.value);
  showModalEditCars.value = true;
}

function openAddCarPayment(form = {}) {
  formData.value = form;
  formData.value.notePayment=' بيد '
  showModalAddCarPayment.value = true;
}
function confirmDelCar(V) {
  axios
    .post("/api/DelCar", V)
    .then((response) => {
      showModalDelCar.value = false;
      window.location.reload();
    })
    .catch((error) => {
      console.error(error);
    });
}
function confirmAddPayFromBalanceCar(V) {
  V.balance  = (asNumber(calculateTotalFilteredAmount().totalAmount) * -1) - asNumber(laravelData.value?.cars_paid)
   axios
    .post("/api/AddPayFromBalanceCar", V)
    .then((response) => {
      showModalAddPayFromBalanceCar.value = false;
      window.location.reload();
    })
    .catch((error) => {
      console.error(error);
    });
}
function confirmDelPayFromBalanceCar(V) {
  axios
    .post("/api/DelPayFromBalanceCar", V)
    .then((response) => {
      showModalDelPayFromBalanceCar.value = false;
      window.location.reload();
    })
    .catch((error) => {
      console.error(error);
    });
}
function confirmUpdateCar(V) {
  showModalEditCars.value = false;

  axios
    .post("/api/updateCarsS", V)
    .then((response) => {
      showModal.value = false;
      toast.success("تم التعديل بنجاح", {
        timeout: 2000,
        position: "bottom-right",
        rtl: true,
      });
      getResultsSelect()

    })
    .catch((error) => {
      
       toast.error("لم التعديل بنجاح", {
        timeout: 2000,
        position: "bottom-right",
        rtl: true,
      });
      getResultsSelect()

    });
}
function confirmAddPayment(V) {
  if(!V.discountPayment){
    V.discountPayment=0
  }
  axios
    .get(
      `/api/addPaymentCar?car_id=${V.id}&discount=${V.discountPayment}&amount=${V.amountPayment ?? 0}&note=${
        V.notePayment ?? ""
      }`
    )
    .then((response) => {
      showModalAddCarPayment.value = false;
      toast.success(" تم دفع مبلغ دولار " + V.amountPayment + " بنجاح ", {
        timeout: 3000,
        position: "bottom-right",
        rtl: true,
      });
      getResultsSelect()
      let transaction=response.data
      window.open(`/api/getIndexAccountsSelas?user_id=${props.client_id}&print=2&transactions_id=${transaction.id}`, '_blank');
    })
    .catch((error) => {
      showModal.value = false;
      console.log(error)
      toast.error("لم التعديل بنجاح", {
        timeout: 2000,
        position: "bottom-right",
        rtl: true,
      });
    });
}
function confirmAddPaymentTotal(amount, client_Select,discount,note) {
  isLoading.value=true
  axios
    .get(
      `/api/addPaymentCarTotal?amount=${amount ?? 0}&discount=${discount ?? 0}&note=${note}&client_id=${ client_Select ?? 0}`
    )
    .then((response) => {
      showModalAddCarPayment.value = false;
      toast.success(" تم دفع مبلغ دولار " + amount + " بنجاح ", {
        timeout: 3000,
        position: "bottom-right",
        rtl: true,
      });
      showPaymentForm.value = false;
      isLoading.value=false
      getResultsSelect()
      resetValuse()
      
      let transaction=response.data

      window.open(`/api/getIndexAccountsSelas?user_id=${props.client_id}&print=2&transactions_id=${transaction.id}`, '_blank');
    })
    .catch((error) => {
      console.log(error)
      showModal.value = false;
      isLoading.value=false

      toast.error("لم التعديل بنجاح", {
        timeout: 2000,
        position: "bottom-right",
        rtl: true,
      });
    });
}
function resetValuse(){
      amount.value=0
      discount.value=0
      note.value='';
}
function showAddPaymentTotal(){
  showPaymentForm.value = true;
  showTransactions.value=false;
}
function hideAddPaymentTotal(){
  showPaymentForm.value = false;
}
function showTransactionsDiv(){
  showTransactions.value=true;
  showPaymentForm.value = false;
}
function hideTransactionsDiv(){
  showTransactions.value=false;
  
}

function calculateAmountDiscount (){
  let need_payment = clientBalanceUsd.value
  amount.value=need_payment- discount.value
}
function calculateAmount(){
  
  let need_payment = clientBalanceUsd.value
  console.log(need_payment)
  if(amount.value > need_payment){
    amount.value=need_payment
    showErorrAmount.value = true
    toast.info(" المبلغ اكبر من الدين المطلوب"+" "+amount.value, {
        timeout: 4000,
        position: "bottom-right",
        rtl: true,
      });
      
  }else{
    
    showErorrAmount.value = false
  }

}

function getImageUrl(name) {
      // Provide the base URL for your images
      return `/public/uploadsResized/${name}`;
    }
function getDownloadUrl(name) {
      // Provide the base URL for downloading images
      return `/public/uploads/${name}`;
    }

function checkClientBalance(_v) {
  // Disabled: auto-correction compared a wrong frontend formula to ledger AR
  // and rewrote wallets on every visit (balance-correct toast loop). This page
  // shows cars remaining (cars_need_paid), not wallet/ledger AR.
  return;
}

</script>

<template>
  <Head title="Dashboard" />
  <AuthenticatedLayout>
    <ModalEditCars
      :formData="formData"
      :show="showModalEditCars ? true : false"
      :client="clients"
      :auctions="auctions"
      @a="confirmUpdateCar($event)"
      @close="showModalEditCars = false"
    >
      <template #header> </template>
    </ModalEditCars>
    <ModalAddCarPayment
      :formData="formData"
      :show="showModalAddCarPayment ? true : false"
      @a="confirmAddPayment($event)"
      @close="showModalAddCarPayment = false"
    >
      <template #header> </template>
    </ModalAddCarPayment>

    <ModalDelCar
      :show="showModalDelCar ? true : false"
      :formData="formData"
      @a="confirmDelCar($event)"
      @close="showModalDelCar = false"
    >
      <template #header>
        <h2 class="mb-5 dark:text-gray-400 text-center">
          هل متأكد من حذف السيارة ؟
        </h2>
      </template>
    </ModalDelCar>

    <ModalDelCar
      :show="showModalAddPayFromBalanceCar ? true : false"
      :formData="formData"
      @a="confirmAddPayFromBalanceCar($event)"
      @close="showModalAddPayFromBalanceCar = false"
    >
      <template #header>
        <h2 class="mb-5 dark:text-gray-400 text-center">
          هل متأكد من دفع 
          {{ formData.car_type }}
          السيارة ؟
          من الرصيد
        </h2>
      </template>
    </ModalDelCar>

    <ModalDelCar
      :show="showModalDelPayFromBalanceCar ? true : false"
      :formData="formData"
      @a="confirmDelPayFromBalanceCar($event)"
      @close="showModalDelPayFromBalanceCar = false"
    >
      <template #header>
        <h2 class="mb-5 dark:text-gray-400 text-center">
          هل متأكد من اعادة دفعة السيارة
          {{ formData.car_type }}
          للرصيد ؟
        </h2>
      </template>
    </ModalDelCar>
    <modal
      :show="showModal ? true : false"
      :data="showModal.toString()"
      @a="method1($event, arg1)"
      @close="showModal = false"
    >
    </modal>
    <div v-if="$page.props.success" class="mx-auto max-w-8xl px-4 pt-4 sm:px-6 lg:px-8">
      <div
        id="alert-2"
        class="rounded-xl border border-red-200 bg-red-50 p-4 text-center dark:border-red-800 dark:bg-red-950/40"
        role="alert"
      >
        <div class="text-sm font-medium text-red-700 dark:text-red-300">
          {{ $page.props.success }}
        </div>
      </div>
    </div>

    <div
      class="py-6"
      v-if="$page.props.auth.user.type_id == 1 || $page.props.auth.user.type_id == 6"
    >
      <div class="mx-auto max-w-8xl sm:px-4 lg:px-6">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <!-- Filters toolbar -->
          <div class="border-b border-slate-200 p-4 dark:border-slate-700">
            <div class="flex flex-wrap items-end gap-3">
              <div class="min-w-[180px] flex-1">
                <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">
                  {{ $t("Account") }}
                </label>
                <select
                  id="default"
                  v-model="client_Select"
                  class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                  @change="getResultsSelect()"
                >
                  <option value="undefined" disabled>
                    {{ $t("selectCustomer") }}
                  </option>
                  <template v-for="(user, index) in clients" :key="index">
                    <option
                      v-if="user.wallet.balance > 0 || user.id == client_Select"
                      :value="user.id"
                    >
                      {{ user.name }}
                    </option>
                  </template>
                </select>
              </div>

              <div class="min-w-[200px]">
                <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">
                  فلترة السيارات المكتملة
                </label>
                <label
                  for="bordered-checkbox-1"
                  class="flex min-h-[42px] cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 dark:border-slate-600 dark:bg-slate-950"
                >
                  <input
                    id="bordered-checkbox-1"
                    type="checkbox"
                    name="bordered-checkbox"
                    :value="showComplatedCars"
                    :checked="!showComplatedCars"
                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                    @change="showComplatedCars == true ? (showComplatedCars = false) : (showComplatedCars = true)"
                  />
                  <span class="text-sm text-slate-700 dark:text-slate-200">
                    {{ showComplatedCars == false ? "تم الفلتر" : "تم عرض جميع السيارة" }}
                  </span>
                </label>
              </div>

              <div class="min-w-[150px]">
                <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">
                  {{ $t("from_date") }}
                </label>
                <TextInput id="from" v-model="from" type="date" class="mt-0 block w-full" />
              </div>

              <div class="min-w-[150px]">
                <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">
                  {{ $t("to_date") }}
                </label>
                <TextInput id="to" v-model="to" type="date" class="mt-0 block w-full" />
              </div>

              <div class="flex flex-wrap gap-2 print:hidden">
                <button
                  type="button"
                  class="min-h-[42px] rounded-lg bg-slate-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                  @click.prevent="getResults()"
                >
                  فلترة
                </button>
                <a
                  :href="`/api/getIndexAccountsSelas?user_id=${client_Select}&from=${from}&to=${to}&print=1&showComplatedCars=${showComplatedCars ? 0 : 1}`"
                  target="_blank"
                  class="inline-flex min-h-[42px] items-center rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-orange-600"
                >
                  طباعة
                </a>
                <a
                  :href="`/api/getIndexAccountsSelas?user_id=${client_Select}&from=${from}&to=${to}&print=1&printExcel=1&showComplatedCars=${showComplatedCars ? 0 : 1}`"
                  target="_blank"
                  class="inline-flex min-h-[42px] items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                >
                  Excel
                </a>
              </div>
            </div>
          </div>

          <!-- KPI summary chips — use car.paid / need_paid, not wallet-derived math -->
          <div class="border-b border-slate-200 p-4 dark:border-slate-700">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6">
              <div class="rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                <div class="text-xs font-semibold text-slate-600 dark:text-slate-300">مجموع السيارات</div>
                <div class="mt-1 font-mono text-lg font-bold text-slate-900 dark:text-white">
                  {{ laravelData.car_total ?? 0 }}
                </div>
              </div>
              <div class="rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                <div class="text-xs font-semibold text-slate-600 dark:text-slate-300">{{ $t("Total_in_dollars") }}</div>
                <div class="mt-1 font-mono text-lg font-bold text-sky-700 dark:text-sky-300">
                  {{ laravelData?.cars_sum ?? 0 }}
                </div>
              </div>
              <div class="rounded-xl border border-emerald-400 bg-white px-4 py-3 shadow-sm dark:border-emerald-500/50 dark:bg-slate-800">
                <div class="text-xs font-semibold text-emerald-800 dark:text-emerald-300">مجموع المدفوع بالدولار</div>
                <div class="mt-1 font-mono text-lg font-bold text-emerald-700 dark:text-emerald-200">
                  {{ Math.abs(asNumber(laravelData?.cars_paid)) }}
                </div>
              </div>
              <div class="rounded-xl border border-amber-400 bg-white px-4 py-3 shadow-sm dark:border-amber-500/50 dark:bg-slate-800">
                <div class="text-xs font-semibold text-amber-800 dark:text-amber-300">مجموع الخصومات بالدولار</div>
                <div class="mt-1 font-mono text-lg font-bold text-amber-700 dark:text-amber-200">
                  {{ laravelData?.cars_discount ?? 0 }}
                </div>
              </div>
              <div class="rounded-xl border border-indigo-400 bg-white px-4 py-3 shadow-sm dark:border-indigo-500/50 dark:bg-slate-800">
                <div class="text-xs font-semibold text-indigo-800 dark:text-indigo-300">المتبقي على السيارات ($)</div>
                <div
                  class="mt-1 font-mono text-lg font-bold"
                  :class="clientBalanceUsd > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-indigo-700 dark:text-indigo-200'"
                >
                  {{ clientBalanceUsd }}
                </div>
              </div>
              <div
                v-if="undistributedBalanceUsd != 0"
                class="rounded-xl border border-rose-400 bg-white px-4 py-3 shadow-sm dark:border-rose-500/50 dark:bg-slate-800"
              >
                <div class="text-xs font-semibold text-rose-800 dark:text-rose-300">الرصيد غير موزع بالدولار</div>
                <div class="mt-1 font-mono text-lg font-bold text-rose-700 dark:text-rose-200">
                  {{ undistributedBalanceUsd }}
                </div>
              </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2 print:hidden">
              <button
                v-if="!showPaymentForm"
                type="button"
                :disabled="isLoading"
                class="min-h-[42px] rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                @click.prevent="showAddPaymentTotal()"
              >
                اضافة دفعة
              </button>
              <button
                v-if="showPaymentForm"
                type="button"
                :disabled="isLoading"
                class="min-h-[42px] rounded-lg bg-rose-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-600 disabled:opacity-50"
                @click.prevent="hideAddPaymentTotal()"
              >
                اخفاء دفعة
              </button>
              <button
                v-if="!showTransactions"
                type="button"
                :disabled="isLoading"
                class="min-h-[42px] rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-violet-700 disabled:opacity-50"
                @click.prevent="showTransactionsDiv()"
              >
                عرض الدفعات
              </button>
              <button
                v-if="showTransactions"
                type="button"
                class="min-h-[42px] rounded-lg bg-rose-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-600"
                @click.prevent="hideTransactionsDiv()"
              >
                اخفاء الدفعات
              </button>
            </div>
          </div>

          <!-- Add payment form -->
          <div
            v-if="showPaymentForm"
            class="border-b border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950/60"
          >
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
              <div v-if="false">
                <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">الخصم</label>
                <TextInput
                  id="discount"
                  v-model="discount"
                  type="number"
                  class="mt-0 block w-full"
                  @input="calculateAmountDiscount"
                />
              </div>
              <div>
                <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">
                  المبلغ بالدولار المراد دفعه
                </label>
                <TextInput id="percentage" v-model="amount" type="number" class="mt-0 block w-full" />
              </div>
              <div>
                <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-200">ملاحظة</label>
                <TextInput id="discount-note" v-model="note" type="text" class="mt-0 block w-full" />
              </div>
              <div class="flex items-end print:hidden">
                <button
                  type="button"
                  :disabled="isLoading"
                  class="min-h-[42px] w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                  @click.prevent="confirmAddPaymentTotal(amount, client_Select, discount, note)"
                >
                  <span v-if="showErorrAmount" class="text-amber-100">يرجى مراجعة المبلغ ل</span>
                  <span v-if="!isLoading">دفع</span>
                  <span v-else>جاري الطباعة...</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Payments table -->
          <div v-if="showTransactions" class="border-b border-slate-200 p-4 dark:border-slate-700">
            <div class="relative overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
              <table class="w-full text-center text-sm text-slate-700 dark:text-slate-200">
                <thead>
                  <tr class="bg-slate-800 text-slate-100 dark:bg-slate-950">
                    <th class="px-3 py-2.5 text-sm font-semibold">#</th>
                    <th class="px-3 py-2.5 text-sm font-semibold">{{ $t("date") }}</th>
                    <th class="px-3 py-2.5 text-sm font-semibold">{{ $t("description") }}</th>
                    <th class="px-3 py-2.5 text-sm font-semibold">الحساب</th>
                    <th class="px-3 py-2.5 text-sm font-semibold">{{ $t("amount") }}</th>
                    <th class="px-3 py-2.5 text-sm font-semibold print:hidden" style="width: 250px">
                      {{ $t("execute") }}
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                  <tr class="bg-slate-50 dark:bg-slate-900/50">
                    <td colspan="5"></td>
                    <td class="px-3 py-2 print:hidden">
                      <a
                        target="_blank"
                        :href="`/api/getIndexAccountsSelas?user_id=${laravelData.client.id}&from=${from}&to=${to}&print=4`"
                        tabindex="1"
                        class="inline-flex items-center gap-1 rounded-lg bg-sky-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-sky-700"
                      >
                        جميع الدفعات
                        <print />
                      </a>
                    </td>
                  </tr>
                  <template v-for="user in laravelData.transactions" :key="user.id">
                    <tr
                      v-if="user.type == 'out' && user.amount < 0 && user.is_pay == 1"
                      class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                    >
                      <td class="px-3 py-2">{{ user.id }}</td>
                      <td class="px-3 py-2">{{ user.created }}</td>
                      <td class="px-3 py-2">{{ user.description }}</td>
                      <td class="px-3 py-2">
                        <span :class="getMoneyAccountBadgeClass(user)">
                          {{ getMoneyAccountLabel(user) ?? '—' }}
                        </span>
                      </td>
                      <td class="px-3 py-2 font-mono">{{ user.amount * -1 }}</td>
                      <td class="px-3 py-2 print:hidden">
                        <a
                          v-if="user.type == 'out' && user.amount < 0"
                          target="_blank"
                          :href="`/api/getIndexAccountsSelas?user_id=${laravelData.client.id}&from=${from}&to=${to}&print=2&transactions_id=${user.id}`"
                          tabindex="1"
                          class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-1.5 text-white hover:bg-emerald-700"
                        >
                          <print />
                        </a>
                      </td>
                    </tr>
                  </template>
                  <tr class="bg-slate-100 font-semibold dark:bg-slate-800">
                    <td class="px-3 py-2">مجموع الخصومات</td>
                    <td class="px-3 py-2 font-mono">{{ laravelData?.cars_discount }}</td>
                    <td class="px-3 py-2">مجموع الدفعات</td>
                    <td class="px-3 py-2 font-mono">
                      {{ asNumber(calculateTotalFilteredAmount().totalAmount) * -1 }}
                    </td>
                    <td class="px-3 py-2">
                      النتاتج :
                      {{ (asNumber(calculateTotalFilteredAmount().totalAmount) * -1) - asNumber(laravelData?.cars_discount) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Cars table -->
          <div class="p-4">
            <div class="relative overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
              <table class="w-full min-w-[1400px] text-center text-sm text-slate-700 dark:text-slate-200">
                <thead>
                  <tr class="bg-slate-800 text-slate-100 dark:bg-slate-950">
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold">{{ $t("no") }}</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold">{{ $t("car_type") }}</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold">{{ $t("year") }}</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold">{{ $t("color") }}</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold">{{ $t("vin") }}</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold">{{ $t("car_number") }}</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold print:hidden">{{ $t("note") }}</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold">سعر السيارة امريكا</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold">نقل امريكا</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold">ريكفري</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold">مصاريف تصليح</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold">نقل اربيل</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold">مصاريف اربيل</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold">{{ $t("total") }}</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold">{{ $t("paid") }}</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold">المتبقي</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold">{{ $t("date") }}</th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold print:hidden" style="width: 250px">
                      {{ $t("execute") }}
                    </th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold print:hidden" style="width: 100px">
                      تخزين
                    </th>
                    <th scope="col" class="whitespace-nowrap px-2 py-2.5 text-sm font-semibold print:hidden" style="width: 120px">
                      الرصيد
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                  <tr
                    v-for="(car, i) in laravelData.data"
                    v-show="(car.results == 2 && showComplatedCars) || car.results != 2"
                    :key="car.id"
                    :class="carRowClass(car)"
                  >
                    <td class="px-2 py-1.5 text-slate-900 dark:text-slate-100">{{ i + 1 }}</td>
                    <td class="px-2 py-1.5 text-slate-900 dark:text-slate-100">{{ car.car_type }}</td>
                    <td class="px-2 py-1.5 text-slate-900 dark:text-slate-100">{{ car.year }}</td>
                    <td class="px-2 py-1.5 text-slate-900 dark:text-slate-100">{{ car.car_color }}</td>
                    <td class="px-2 py-1.5 font-mono text-xs text-slate-900 dark:text-slate-100">{{ car.vin }}</td>
                    <td class="px-2 py-1.5 text-slate-900 dark:text-slate-100">{{ car.car_number }}</td>
                    <td class="px-2 py-1.5 print:hidden text-slate-900 dark:text-slate-100">{{ car.note }}</td>
                    <td class="px-2 py-1.5 font-mono text-slate-900 dark:text-slate-100">{{ car.shipping_dolar_s }}</td>
                    <td class="px-2 py-1.5 font-mono text-slate-900 dark:text-slate-100">{{ car.dinar_s }}</td>
                    <td class="px-2 py-1.5 font-mono text-slate-900 dark:text-slate-100">{{ car.coc_dolar_s }}</td>
                    <td class="px-2 py-1.5 font-mono text-slate-900 dark:text-slate-100">{{ car.checkout_s }}</td>
                    <td class="px-2 py-1.5 font-mono text-slate-900 dark:text-slate-100">{{ erbilTransferSubtotal(car, true) }}</td>
                    <td class="px-2 py-1.5 font-mono text-slate-900 dark:text-slate-100">{{ car.commission_s ?? 0 }}</td>
                    <td class="px-2 py-1.5 font-mono font-semibold text-slate-900 dark:text-white">{{ fixed(car.total_s, 0) }}</td>
                    <td class="px-2 py-1.5 font-mono font-semibold text-emerald-800 dark:text-emerald-300">{{ car.paid }}</td>
                    <td
                      class="px-2 py-1.5 font-mono font-semibold"
                      :class="(asNumber(car.total_s) - asNumber(car.paid)) > 0 ? 'text-rose-800 dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-300'"
                    >
                      {{ fixed(asNumber(car.total_s) - asNumber(car.paid), 0) }}
                    </td>
                    <td class="px-2 py-1.5 whitespace-nowrap text-slate-900 dark:text-slate-100">{{ car.date }}</td>
                    <td class="px-2 py-1.5 text-start print:hidden">
                      <button
                        tabindex="1"
                        class="mx-0.5 rounded-lg bg-slate-500 px-1.5 py-1 text-white hover:bg-slate-600"
                        @click="openModalEditCars(car)"
                      >
                        <edit />
                      </button>
                      <button
                        tabindex="1"
                        class="mx-0.5 rounded-lg bg-orange-500 px-1.5 py-1 text-white hover:bg-orange-600"
                        @click="openModalDelCar(car)"
                      >
                        <trash />
                      </button>
                      <button
                        v-if="car.total_s != car.paid + car.discount"
                        tabindex="1"
                        class="mx-0.5 rounded-lg bg-emerald-600 px-1.5 py-1 text-white hover:bg-emerald-700"
                        @click="openAddCarPayment(car)"
                      >
                        <pay />
                      </button>
                    </td>
                    <td class="px-2 py-1.5 text-start print:hidden">
                      <a
                        v-for="(image, index) in car.car_images"
                        :key="index"
                        :href="getDownloadUrl(image.name)"
                        style="cursor: pointer"
                        target="_blank"
                      >
                        <img
                          :src="getImageUrl(image.name)"
                          alt=""
                          class="inline px-1"
                          style="max-width: 80px; max-height: 50px"
                        />
                      </a>
                    </td>
                    <td class="px-2 py-1.5 text-start print:hidden">
                      <button
                        tabindex="1"
                        style="min-width: 100px"
                        class="mx-0.5 rounded-lg bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-700"
                        v-if="(asNumber(calculateTotalFilteredAmount().totalAmount) * -1) - asNumber(laravelData?.cars_paid) != 0"
                        @click="openModalAddPayFromBalanceCar(car)"
                      >
                        دفع من الرصيد
                      </button>
                      <button
                        tabindex="1"
                        style="min-width: 100px"
                        v-if="
                          (asNumber(calculateTotalFilteredAmount().totalAmount) * -1) - asNumber(laravelData?.cars_sum) != 0 &&
                          asNumber(car.paid)
                        "
                        class="mx-0.5 mt-1 rounded-lg bg-rose-600 px-2 py-1 text-xs font-semibold text-white hover:bg-rose-700"
                        @click="openModalDelPayFromBalanceCar(car)"
                      >
                        اعادة للرصيد
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="mt-4 text-center" style="direction: ltr">
              <TailwindPagination
                :data="laravelData"
                :limit="2"
                @pagination-change-page="getResults"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div
      class="mx-auto hidden max-w-7xl px-4 text-slate-600 dark:text-slate-400 sm:px-6 lg:px-8 print:block"
    >
      <div class="flex flex-row">
        <div class="basis-1/2">
          توقيع صاحب الحساب
          <br />
          {{ laravelData.client?.name }}
        </div>
        <div class="basis-1/2 text-center">توقيع قسم المحاسبة</div>
        <div class="basis-1/2 text-end">توقيع المدير</div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style>
.td {
  max-width: 200px; /* can be 100% ellipsis will happen when contents exceed it */
  text-overflow: ellipsis;
  overflow: hidden;
  white-space: nowrap;
}

.money-account-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.3rem 0.75rem;
  border-radius: 999px;
  font-weight: 700;
  font-size: 0.78rem;
  white-space: nowrap;
  border: 1px solid transparent;
}

.money-account-badge--cash {
  background-color: rgba(16, 185, 129, 0.18);
  color: #047857;
  border-color: rgba(16, 185, 129, 0.35);
}

.money-account-badge--treasury {
  background-color: rgba(245, 158, 11, 0.18);
  color: #b45309;
  border-color: rgba(245, 158, 11, 0.35);
}

.money-account-badge--other {
  background-color: rgba(99, 102, 241, 0.18);
  color: #4338ca;
  border-color: rgba(99, 102, 241, 0.35);
}

.money-account-badge--none {
  background-color: rgba(148, 163, 184, 0.12);
  color: #64748b;
  border-color: rgba(148, 163, 184, 0.25);
}

.dark .money-account-badge--cash {
  color: #34d399;
}

.dark .money-account-badge--treasury {
  color: #fbbf24;
}

.dark .money-account-badge--other {
  color: #a5b4fc;
}

.dark .money-account-badge--none {
  color: #94a3b8;
}
</style>