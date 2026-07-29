<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import { Head, Link, useForm } from "@inertiajs/inertia-vue3";
import { ref } from "vue";
import ModalAddSales from "@/Components/ModalAddSales.vue";
import ModalAddDebt from "@/Components/ModalAddDebt.vue";
import ModalAddExpenses from "@/Components/ModalAddExpenses.vue";
import TextInput from "@/Components/TextInput.vue";


import ModalConvertDollarDinar from "@/Components/ModalConvertDollarDinar.vue";
import ModalConvertDinarDollar from "@/Components/ModalConvertDinarDollar.vue";
import ModalDel from "@/Components/ModalDel.vue";
import ModalUploader from "@/Components/ModalUploader.vue";
import ModalAddExpenseAccount from "@/Components/ModalAddExpenseAccount.vue";



import axios from 'axios';
import show from "@/Components/icon/show.vue";
import imags from "@/Components/icon/imags.vue";
import trash from "@/Components/icon/trash.vue";
import edit from "@/Components/icon/edit.vue";
import print from "@/Components/icon/print.vue";

import InfiniteLoading from "v3-infinite-loading";
import "v3-infinite-loading/lib/style.css";
import debounce from 'lodash/debounce';
import { formatBaghdadTimestamp } from "@/utils/datetime";
import { formatNumber } from "@/utils/formatMoney";


const laravelData = ref({});
const searchTerm = ref('');
let showModalAddSales = ref(false);
let showModaldebtSales = ref(false);
let showModalAddExpenses = ref(false);
let showModalConvertDollarDinar = ref(false);
let showModalConvertDinarDollar = ref(false);
let showModalDel = ref(false);
let showModalUploader = ref(false);
let transactions= ref([]);
let tranId =ref({});
let formData = ref({});
let isLoading=ref(false);
let from = ref(getTodayDate());
let to = ref(getTodayDate());
let mainAccount= ref(0)
let allCars= ref(0)
let transactionInTodayDollar = ref(0)
let transactionInTodayDinar = ref(0)
let transactionOutTodayDollar = ref(0)
let transactionOutTodayDinar = ref(0)
let resetData = ref(false);
let user_id = 0;
let page = 1;
let q = '';

const editingDescriptionId = ref(null);
const descriptionDraft = ref('');
const isSavingDescription = ref(false);
const descriptionError = ref('');
const DESCRIPTION_MAX_LENGTH = 1000;

const refresh = () => {
  page = 0;
  transactions.value.length = 0;
  resetData.value = !resetData.value;


};
const debouncedGetResultsCar = debounce(refresh, 500);

const getResults = async ($state) => {
  try {
    const response = await axios.get(`/getIndexAccounting`, {
      params: {
        limit: 100,
        page: page,
        q: q,
        user_id: props.boxes[0].id,
        from:from.value,
        to: to.value
      }
    });

    const json = response.data;


    if (json.transactions.data.length < 100){
      transactions.value.push(...json.transactions.data);
      $state.complete();
    } 
    else {
      transactions.value.push(...json.transactions.data);
       $state.loaded();
    }

    laravelData.value = json;
    page++;
  } catch (error) {
    console.log(error);
    //$state.error();
  }
};
 
const getcountTotalInfo = async () => {
  axios.get('/api/totalInfo')
  .then(response => {
    const d = response.data?.data ?? {};
    const num = (v) => {
      const n = Number(v);
      return Number.isFinite(n) ? n : 0;
    };
    mainAccount.value = d.mainAccount;
    allCars.value = d.allCars;
    transactionInTodayDollar.value = num(d.transactionInTodayDollar);
    transactionOutTodayDollar.value = num(d.transactionOutTodayDollar);
    transactionInTodayDinar.value = num(d.transactionInTodayDinar);
    transactionOutTodayDinar.value = num(d.transactionOutTodayDinar);
  })
  .catch(error => {
    console.error(error);
  })
  
    
}
getcountTotalInfo()
function openAddSales() {
  showModalAddSales.value = true;
}
function opendebtSales() {
  showModaldebtSales.value = true;
}
function openAddExpenses(){
  showModalAddExpenses.value = true;
}
function openConvertDollarDinar(){
  showModalConvertDollarDinar.value = true;
}
function openConvertDinarDollar(){
  showModalConvertDinarDollar.value = true;
}
function openModalDel(tran){
  tranId.value = tran
  showModalDel.value = true;
}
function openModalUploader(tran){
  tranId.value = tran
  showModalUploader.value = true;
}

const props = defineProps({
  url: String,
  users:Array,
  accounts:Array,
  boxes:Array,
  flaggedWallets: {
    type: Array,
    default: () => []
  },
  walletUsers: {
    type: Array,
    default: () => []
  },
  expenseShortcuts: {
    type: Array,
    default: () => []
  },
  suggestExpenseCode: {
    type: String,
    default: '5101'
  },
  suggestCommissionCode: {
    type: String,
    default: '5201'
  },
  expenseParentId: {
    type: [Number, String],
    default: null
  },
});

const showModalExpenseAccount = ref(false);
const expenseAccountModalRef = ref(null);
const localExpenseShortcuts = ref([...(props.expenseShortcuts || [])]);
const localSuggestExpenseCode = ref(props.suggestExpenseCode || '5101');
const localSuggestCommissionCode = ref(props.suggestCommissionCode || '5201');
const localExpenseParentId = ref(props.expenseParentId);

function openModalAddExpenseAccount() {
  showModalExpenseAccount.value = true;
}

async function refreshExpenseShortcuts() {
  try {
    const { data } = await axios.get('/api/ledgerExpenseAccounts');
    localExpenseShortcuts.value = data.accounts || [];
    localSuggestExpenseCode.value = data.suggest_expense_code || '5101';
    localSuggestCommissionCode.value = data.suggest_commission_code || '5201';
    const rawParent = data.expense_parent_id;
    const parsedParent = Number(rawParent);
    localExpenseParentId.value =
      Number.isFinite(parsedParent) && parsedParent > 0 ? parsedParent : null;
  } catch (error) {
    console.error(error);
  }
}

async function confirmExpenseAccountSave(payload) {
  expenseAccountModalRef.value?.setSaving?.(true);
  expenseAccountModalRef.value?.setError?.('');
  try {
    await axios.post('/api/ledgerAccountStore', payload);
    showModalExpenseAccount.value = false;
    await refreshExpenseShortcuts();
  } catch (error) {
    const errors = error?.response?.data?.errors || {};
    const msg = error?.response?.data?.message
      || errors.parent_id?.[0]
      || errors.code?.[0]
      || errors.name_ar?.[0]
      || Object.values(errors)[0]?.[0]
      || 'تعذر إنشاء حساب المصروف';
    expenseAccountModalRef.value?.setError?.(msg);
    console.error(error);
  } finally {
    expenseAccountModalRef.value?.setSaving?.(false);
  }
}

const search = async (q) => {
  user_id.value=0;
  laravelData.value = [];
  const response = await fetch(`/livesearchAppointment?q=${q}`);
  laravelData.value = await response.json();
};
const form = useForm();

let showModal = ref(false);
const come = async (id) => {
  const response = await fetch(`/appointmentCome?id=${id}`);
  refresh();

};
const cancel = async (id) => {
  const response = await fetch(`/appointmentCancel?id=${id}`);
  refresh();

};

 
 
const errors = ref(0);
 
 
 
function confirm(V) {
  axios.post('/api/receiptArrived',V)
  .then(response => {
    showModalAddSales.value=false;
    //getResults();
    window.location.reload();
  })
  .catch(error => {

    errors.value = error.response.data.errors
  })
}
function confirmdebt(V) {
  axios.post('/api/salesDebt',V)
  .then(response => {
    refresh();
    showModaldebtSales.value=false;
    showModalAddExpenses.value = false;
    window.location.reload();

  })
  .catch(error => {

    errors.value = error.response.data.errors
  })
}
function confirmConvertDollarDinar(V) {
  axios.post('/api/convertDollarDinar',V)
  .then(response => {
    refresh();
    showModalConvertDollarDinar.value=false;

  })
  .catch(error => {

    errors.value = error.response.data.errors
  })
}
function confirmConvertDinarDollar(V) {
  axios.post('/api/convertDinarDollar',V)
  .then(response => {
    refresh();
    showModalConvertDinarDollar.value=false;

  })
  .catch(error => {

    errors.value = error.response.data.errors
  })
}



function getTodayDate() {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const day = String(today.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}
function delTransactions(id){
  axios.post(`/api/delTransactions?id=${id.id}`)
  .then(response => {
    refresh();
    getcountTotalInfo();
    showModalDel.value=false;
  })
  .catch(error => {

    errors.value = error.response.data.errors
  })
}

function getImageUrl(name) {
      // Provide the base URL for your images
      return `/public/uploadsResized/${name}`;
    }
function getDownloadUrl(name) {
      // Provide the base URL for downloading images
      return `/public/uploads/${name}`;
    }
function UpdatePage (){
  refresh();
}
function updateResults(input) {
  return formatNumber(input);
}

function safeNum(v) {
  const n = Number(v);
  return Number.isFinite(n) ? n : 0;
}

const todayDiffDollar = () => safeNum(transactionInTodayDollar.value) + safeNum(transactionOutTodayDollar.value);
const todayDiffDinar = () => safeNum(transactionInTodayDinar.value) + safeNum(transactionOutTodayDinar.value);

const IN_TYPES = ['in', 'inUser', 'inUserBox'];
const OUT_TYPES = ['out', 'outUser', 'outUserBox', 'debt'];

function getAmountParts(tran, direction) {
  if (!tran) {
    return null;
  }
  const amount = Number(tran.amount);
  if (Number.isNaN(amount)) {
    return null;
  }
  if (direction === 'in' && IN_TYPES.includes(tran.type)) {
    return {
      value: updateResults(Math.abs(amount)),
      currency: tran.currency ?? '$',
    };
  }
  if (direction === 'out' && OUT_TYPES.includes(tran.type)) {
    return {
      value: updateResults(Math.abs(amount)),
      currency: tran.currency ?? '$',
    };
  }
  return null;
}

function getAmountClass(direction) {
  return direction === 'in'
    ? 'amount-pill amount-pill--in'
    : 'amount-pill amount-pill--out';
}

function getRowClasses(tran) {
  const base = [
    'transition-colors',
    'duration-150',
    'border-b',
    'border-transparent',
    'hover:shadow-md',
  ];

  if (!tran) {
    return base;
  }

  if (IN_TYPES.includes(tran.type)) {
    base.push(
      'bg-gradient-to-l',
      'from-emerald-700',
      'to-emerald-600',
      'text-white',
      'hover:from-emerald-600',
      'hover:to-emerald-500'
    );
  } else if (OUT_TYPES.includes(tran.type)) {
    base.push(
      'bg-gradient-to-l',
      'from-rose-700',
      'to-rose-600',
      'text-white',
      'hover:from-rose-600',
      'hover:to-rose-500'
    );
  } else {
    base.push(
      'bg-slate-800',
      'text-white',
      'hover:bg-slate-700'
    );
  }

  return base;
}

// اسم الحساب المحاسبي الحقيقي (صندوق دولار/دينار أو حساب القاصة) الذي أثرت به الحركة،
// قادم من القيد المحاسبي (journal) المرتبط بالحركة أو بالحركة الأم لها — وليس تخمينًا في الواجهة.
function getMoneyAccountLabel(tran) {
  return tran?.money_account?.name_ar || tran?.money_account?.name || null;
}

function getMoneyAccountBadgeClass(tran) {
  const code = tran?.money_account?.code ?? '';
  if (code === '1100' || code === '1110') {
    return 'money-account-badge money-account-badge--cash';
  }
  if (code === '1120' || code === '1130') {
    return 'money-account-badge money-account-badge--treasury';
  }
  if (tran?.money_account) {
    return 'money-account-badge money-account-badge--other';
  }
  return 'money-account-badge money-account-badge--none';
}

function getAccountLink(tran) {
  if (!tran) {
    return null;
  }

  const type = tran.morphed_type ?? '';
  const id = tran.morphed?.id ?? tran.morphed_id;
  const tranType = tran.type ?? '';

  if (!id) {
    return null;
  }

  const isBoxTransfer = tranType === 'inUserBox' || tranType === 'outUserBox';

  if (isBoxTransfer) {
    return route('wallet', { id });
  }

  if (type.includes('User')) {
    return route('showClients', { id, q: '' });
  }

  if (type.includes('Wallet')) {
    return route('wallet', { id });
  }

  return null;
}

function startEditingDescription(tran) {
  if (!tran || isSavingDescription.value) {
    return;
  }
  editingDescriptionId.value = tran.id;
  descriptionDraft.value = tran.description ?? '';
  descriptionError.value = '';
}

function cancelEditingDescription() {
  if (isSavingDescription.value) {
    return;
  }
  editingDescriptionId.value = null;
  descriptionDraft.value = '';
  descriptionError.value = '';
}

async function saveDescription(tran) {
  if (!tran || editingDescriptionId.value !== tran.id) {
    return;
  }

  const trimmed = descriptionDraft.value ? descriptionDraft.value.trim() : '';

  if (!trimmed) {
    descriptionError.value = 'الوصف مطلوب';
    return;
  }

  if (trimmed.length > DESCRIPTION_MAX_LENGTH) {
    descriptionError.value = `الوصف يجب ألا يتجاوز ${DESCRIPTION_MAX_LENGTH} حرفًا`;
    return;
  }

  isSavingDescription.value = true;
  descriptionError.value = '';

  try {
    await axios.post('/api/updateTransactionDescription', {
      transaction_id: tran.id,
      description: trimmed,
    });

    tran.description = trimmed;
    tran._descriptionUpdated = true;
    setTimeout(() => {
      if (tran) {
        tran._descriptionUpdated = false;
      }
    }, 3000);

    cancelEditingDescription();
  } catch (error) {
    if (error.response?.data?.errors?.description?.length) {
      descriptionError.value = error.response.data.errors.description[0];
    } else if (error.response?.data?.message) {
      descriptionError.value = error.response.data.message;
    } else {
      descriptionError.value = 'حدث خطأ أثناء حفظ الوصف';
    }
  } finally {
    isSavingDescription.value = false;
  }
}

</script>

<template>
  <Head title="Dashboard" />
  <AuthenticatedLayout>
    <template #header>
 
    </template>
    <ModalDel
            :show="showModalDel ? true : false"
            :formData="tranId"
            @a="delTransactions($event)"
            @close="showModalDel = false"
            >
          <template #header>
            <h2 class=" mb-5 dark:text-white text-center">

          هل متأكد من الحذف 
          ؟
          </h2>
          </template>
    </ModalDel>
    <ModalUploader
            :show="showModalUploader ? true : false"
            :formData="tranId"
            @a="UpdatePage($event)"
            @close="showModalUploader = false"
            >
          <template #header>
            <h2 class=" mb-5 dark:text-white text-center">

            تحميل ملفات
          </h2>
          </template>
    </ModalUploader>
    

    <ModalAddSales
            :show="showModalAddSales ? true : false"
            :data="users"
            :accounts="accounts"
            title="وصل قبض"
            subtitle="إضافة مبلغ إلى الصندوق بالدولار أو الدينار"
            @a="confirm($event)"
            @close="showModalAddSales = false"
            />
      <ModalAddDebt
            :show="showModaldebtSales ? true : false"
            :data="users"
            :accounts="accounts"
            @a="confirmdebt($event)"
            @close="showModaldebtSales = false"
            >
          <template #header>
            
           </template>
      </ModalAddDebt>
      <ModalAddExpenses
            :show="showModalAddExpenses ? true : false"
            :boxes="boxes"
            title="وصل صرف"
            subtitle="سحب مبلغ من الصندوق بالدولار أو الدينار"
            @a="confirmdebt($event)"
            @close="showModalAddExpenses = false"
            />
      <ModalConvertDollarDinar 
            :show="showModalConvertDollarDinar ? true : false"
            :boxes="boxes"
            @a="confirmConvertDollarDinar($event)"
            @close="showModalConvertDollarDinar = false"
            />
      <ModalConvertDinarDollar 
            :show="showModalConvertDinarDollar ? true : false"
            :boxes="boxes"
            @a="confirmConvertDinarDollar ($event)"
            @close="showModalConvertDinarDollar = false"
            />
    <ModalAddExpenseAccount
      ref="expenseAccountModalRef"
      :show="showModalExpenseAccount"
      :suggest-expense-code="localSuggestExpenseCode"
      :suggest-commission-code="localSuggestCommissionCode"
      :expense-parent-id="localExpenseParentId"
      @save="confirmExpenseAccountSave"
      @close="showModalExpenseAccount = false"
    />
    <div v-if="$page.props.success">
      <div
        id="alert-2"
        class="p-4 mb-4 bg-red-100 rounded-lg dark:bg-red-200 text-center"
        role="alert"
      >
        <div class="ml-3 font-medium text-red-700 dark:text-red-800">
          {{ $page.props.success }}
        </div>
      </div>
    </div>
    <div>
      <div class="mx-auto max-w-9xl sm:px-6 lg:px-8">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <div class="border-b border-slate-200 dark:border-slate-700">

            <!-- Row 1: compact actions + converts + wallets + dates + filter -->
            <div class="flex flex-wrap items-end gap-2 border-b border-slate-200 p-3 dark:border-slate-700 print:hidden">
              <button
                v-if="$page.props.auth.user.type_id==1 || $page.props.auth.user.type_id==2 || $page.props.auth.user.type_id==5 || $page.props.auth.user.type_id==6"
                type="button"
                class="min-h-[38px] rounded-lg bg-emerald-700 px-3.5 py-1.5 text-sm font-semibold text-white transition hover:bg-emerald-800"
                @click="openAddSales()"
              >
                {{ $t('receipt_voucher_add') }}
              </button>

              <button
                v-if="false"
                type="button"
                class="min-h-[38px] rounded-lg bg-amber-600 px-3.5 py-1.5 text-sm font-semibold text-white transition hover:bg-amber-700"
                @click="opendebtSales()"
              >
                تحويل لحساب
              </button>

              <button
                v-if="$page.props.auth.user.type_id==1 || $page.props.auth.user.type_id==2 || $page.props.auth.user.type_id==5 || $page.props.auth.user.type_id==6"
                type="button"
                class="min-h-[38px] rounded-lg bg-rose-700 px-3.5 py-1.5 text-sm font-semibold text-white transition hover:bg-rose-800"
                @click="openAddExpenses()"
              >
                {{ $t('payment_voucher_withdraw') }}
              </button>

              <span class="mx-0.5 hidden h-8 w-px self-center bg-slate-200 dark:bg-slate-700 sm:inline-block" aria-hidden="true" />

              <button
                type="button"
                class="min-h-[38px] rounded-lg border border-sky-500/60 bg-sky-50 px-3.5 py-1.5 text-sm font-semibold text-sky-800 transition hover:bg-sky-100 dark:border-sky-500/40 dark:bg-sky-950/40 dark:text-sky-300 dark:hover:bg-sky-950/60"
                @click="openConvertDollarDinar()"
              >
                {{ $t('convert_usd_to_iqd') }}
              </button>
              <button
                type="button"
                class="min-h-[38px] rounded-lg border border-amber-500/60 bg-amber-50 px-3.5 py-1.5 text-sm font-semibold text-amber-900 transition hover:bg-amber-100 dark:border-amber-500/40 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-950/60"
                @click="openConvertDinarDollar()"
              >
                {{ $t('convert_iqd_to_usd') }}
              </button>

              <!-- Cash-vault (orange) shortcuts removed from Accounting — use /vaults. Keep COA expense/commission chips only. -->
              <template v-if="$page.props.auth.user.owner_id==1">
                <span class="mx-0.5 hidden h-8 w-px self-center bg-slate-200 dark:bg-slate-700 sm:inline-block" aria-hidden="true" />
                <Link
                  v-for="acc in localExpenseShortcuts"
                  :key="'exp-' + acc.id"
                  :href="`/expense-account?id=${acc.id}`"
                  class="min-h-[38px] inline-flex items-center rounded-lg border border-violet-600 bg-violet-700 px-3.5 py-1.5 text-sm font-semibold text-white transition hover:bg-violet-800 dark:border-violet-500 dark:bg-violet-800 dark:text-white dark:hover:bg-violet-700"
                  :title="`${$t('expenses_and_commissions')} — ${acc.name}`"
                >
                  {{ acc.name }}
                </Link>
                <button
                  type="button"
                  class="min-h-[38px] inline-flex items-center rounded-lg border border-violet-500/70 bg-violet-950/40 px-3.5 py-1.5 text-sm font-semibold text-violet-100 transition hover:bg-violet-900/60 dark:border-violet-400/50 dark:bg-violet-950 dark:text-violet-100 dark:hover:bg-violet-900"
                  :title="$t('add_expense_commission_account')"
                  @click="openModalAddExpenseAccount()"
                >
                  + {{ $t('add_expense_commission_account') }}
                </button>
              </template>

              <span class="mx-0.5 hidden h-8 w-px self-center bg-slate-200 dark:bg-slate-700 sm:inline-block" aria-hidden="true" />

              <div class="flex min-w-[8.5rem] flex-col gap-0.5">
                <label for="from" class="text-xs font-semibold text-slate-600 dark:text-slate-200">{{ $t('from_date') }}</label>
                <TextInput
                  id="from"
                  type="date"
                  class="mt-0 block w-full min-h-[38px] bg-white text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                  v-model="from"
                />
              </div>
              <div class="flex min-w-[8.5rem] flex-col gap-0.5">
                <label for="to" class="text-xs font-semibold text-slate-600 dark:text-slate-200">{{ $t('until_date') }}</label>
                <TextInput
                  id="to"
                  type="date"
                  class="mt-0 block w-full min-h-[38px] bg-white text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                  v-model="to"
                />
              </div>
              <button
                type="button"
                class="min-h-[38px] rounded-lg bg-slate-600 px-4 py-1.5 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600"
                @click.prevent="refresh()"
              >
                <span v-if="!isLoading">{{ $t('filter') }}</span>
                <span v-else>{{ $t('saving') }}</span>
              </button>
            </div>

            <!-- Row 2: balances + search + daily summary -->
            <div class="grid grid-cols-1 gap-3 p-3 sm:grid-cols-2 lg:grid-cols-4">
              <div class="rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                <div class="text-xs font-semibold text-slate-600 dark:text-slate-200">{{ $t('cash_balance_usd') }}</div>
                <div class="mt-1 font-mono text-lg font-bold tabular-nums text-emerald-700 dark:text-emerald-300">
                  {{ updateResults(laravelData?.user?.balance ?? laravelData?.user?.wallet?.balance ?? 0) }}
                  <span class="text-sm font-normal text-slate-400">$</span>
                </div>
              </div>
              <div class="rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                <div class="text-xs font-semibold text-slate-600 dark:text-slate-200">{{ $t('cash_balance_iqd') }}</div>
                <div class="mt-1 font-mono text-lg font-bold tabular-nums text-sky-700 dark:text-sky-300">
                  {{ updateResults(laravelData?.user?.balance_dinar ?? laravelData?.user?.wallet?.balance_dinar ?? 0) }}
                  <span class="text-sm font-normal text-slate-400">د.ع</span>
                </div>
              </div>
              <div class="flex flex-col justify-end">
                <label for="q" class="mb-1 text-xs font-semibold text-slate-600 dark:text-slate-200">{{ $t('search_voucher_or_desc') }}</label>
                <TextInput
                  id="q"
                  type="text"
                  class="mt-0 block w-full min-h-[42px] bg-white text-slate-900 placeholder-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-white dark:placeholder-slate-400"
                  v-model="q"
                  @input="debouncedGetResultsCar"
                />
              </div>
              <div class="overflow-hidden rounded-lg border border-slate-700 bg-slate-900">
                <table class="w-full text-center text-sm text-slate-100">
                  <thead class="bg-slate-800 text-slate-100">
                    <tr>
                      <th class="border border-slate-700 px-2 py-1.5">{{ $t('currency') }}</th>
                      <th class="border border-slate-700 px-2 py-1.5">{{ $t('income') }}</th>
                      <th class="border border-slate-700 px-2 py-1.5">{{ $t('outcome') }}</th>
                      <th class="border border-slate-700 px-2 py-1.5">{{ $t('difference') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr class="bg-slate-900">
                      <td class="border border-slate-700 px-2 py-1.5 font-bold text-emerald-300">{{ $t('usd') }}</td>
                      <td class="border border-slate-700 px-2 py-1.5 font-semibold text-emerald-200">{{ updateResults(transactionInTodayDollar) }}</td>
                      <td class="border border-slate-700 px-2 py-1.5 font-semibold text-rose-200">{{ updateResults(transactionOutTodayDollar) }}</td>
                      <td class="border border-slate-700 px-2 py-1.5 font-semibold">
                        <span :class="todayDiffDollar() > 0 ? 'text-emerald-300' : 'text-rose-300'">{{ updateResults(todayDiffDollar()) }}</span>
                      </td>
                    </tr>
                    <tr class="bg-slate-900">
                      <td class="border border-slate-700 px-2 py-1.5 font-bold text-indigo-300">{{ $t('iqd') }}</td>
                      <td class="border border-slate-700 px-2 py-1.5 font-semibold text-emerald-200">{{ updateResults(transactionInTodayDinar) }}</td>
                      <td class="border border-slate-700 px-2 py-1.5 font-semibold text-rose-200">{{ updateResults(transactionOutTodayDinar) }}</td>
                      <td class="border border-slate-700 px-2 py-1.5 font-semibold">
                        <span :class="todayDiffDinar() > 0 ? 'text-emerald-300' : 'text-rose-300'">{{ updateResults(todayDiffDinar()) }}</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

         <div class="overflow-x-auto shadow-lg mx-3 mb-3 mt-2 rounded-lg">
              <table class="w-full text-right text-gray-100 dark:text-gray-100 text-center bg-slate-900 rounded-lg overflow-hidden">
                <thead class="uppercase bg-slate-800 text-gray-100 text-center">
                  <tr class="rounded-l-lg mb-2 sm:mb-0">
                    <th className="px-2 py-2" style="width: 180px;">{{ $t('party') }}
                    </th>
                    <th className="px-2 py-2" style="width: 160px;">{{ $t('accounting_account') }}</th>
                    <th className="px-2 py-2" style="width: 180px;">{{ $t('date') }}</th>
                    <th className="px-2 py-2">{{ $t('description') }}</th>
                    <th className="px-2 py-2">{{ $t('deposit_col') }}</th>
                    <th className="px-2 py-2">{{ $t('withdraw_col') }}</th>
                    <th className="px-2 py-2" style="width: 200px;">{{ $t('execute') }}</th>
                    <th
                      scope="col"
                      class="px-1 py-2 text-base print:hidden" style="width: 100px;"
                    >
                      {{ $t('storage') }}
                    </th>
                  </tr>
                </thead>
                <tbody>
         
                  <tr
                    v-for="tran in transactions"
                    :key="tran.id"
                    :class="getRowClasses(tran)"
                  >
                    <td class="border border-transparent text-center px-2 py-1 align-middle whitespace-nowrap">
                      <Link
                        v-if="getAccountLink(tran)"
                        :href="getAccountLink(tran)"
                        class="account-link"
                      >
                        {{ tran.morphed?.name ?? '—' }}
                      </Link>
                      <span v-else>
                        {{ tran.morphed?.name ?? '—' }}
                      </span>
                    </td>

                    <td class="border border-transparent text-center px-2 py-1 align-middle whitespace-nowrap">
                      <span :class="getMoneyAccountBadgeClass(tran)">
                        {{ getMoneyAccountLabel(tran) ?? '—' }}
                      </span>
                    </td>

                    <td class="border border-transparent text-center px-2 py-1 align-middle whitespace-nowrap">
                      {{ formatBaghdadTimestamp(tran?.created_at) }}
                    </td>
                    <td class="border border-transparent text-center px-2 py-1 align-middle">
                    <div v-if="editingDescriptionId === tran.id" class="space-y-2 text-right">
                      <textarea
                        v-model="descriptionDraft"
                        class="w-full rounded border border-gray-300 dark:border-gray-700 dark:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm leading-6 p-2"
                        rows="3"
                        :maxlength="DESCRIPTION_MAX_LENGTH"
                        placeholder="اكتب الوصف الجديد هنا"
                      ></textarea>
                      <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>الحد الأقصى {{ DESCRIPTION_MAX_LENGTH }} حرفًا</span>
                        <span :class="descriptionDraft.length > DESCRIPTION_MAX_LENGTH ? 'text-red-500' : ''">
                          {{ descriptionDraft.length }}/{{ DESCRIPTION_MAX_LENGTH }}
                        </span>
                      </div>
                      <p v-if="descriptionError" class="text-xs text-red-500">{{ descriptionError }}</p>
                      <div class="flex justify-end gap-2">
                        <button
                          type="button"
                          class="px-3 py-1 text-sm font-semibold rounded bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200"
                          @click="cancelEditingDescription"
                          :disabled="isSavingDescription"
                        >
                          إلغاء
                        </button>
                        <button
                          type="button"
                          class="px-3 py-1 text-sm font-semibold text-white bg-green-600 rounded disabled:opacity-70"
                          @click="saveDescription(tran)"
                          :disabled="isSavingDescription"
                        >
                          <span v-if="isSavingDescription">جارٍ الحفظ...</span>
                          <span v-else>حفظ</span>
                        </button>
                      </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center gap-1 text-center">
                      <span class="whitespace-pre-line leading-snug">{{ tran.description }}</span>
                      <span
                        v-if="tran._descriptionUpdated"
                        class="inline-flex items-center text-xs font-semibold text-emerald-300"
                      >
                        تم التحديث
                      </span>
                    </div>
                  </td>
                    <td class="border border-transparent text-center px-2 py-1 align-middle">
                      <span
                        v-for="parts in [getAmountParts(tran, 'in')].filter(Boolean)"
                        :key="`in-${tran.id}`"
                        :class="getAmountClass('in')"
                      >
                        <span class="tabular-nums">{{ parts.value }}</span>
                        <span class="amount-pill__currency">{{ parts.currency }}</span>
                      </span>
                    </td>
                    <td class="border border-transparent text-center px-2 py-1 align-middle">
                      <span
                        v-for="parts in [getAmountParts(tran, 'out')].filter(Boolean)"
                        :key="`out-${tran.id}`"
                        :class="getAmountClass('out')"
                      >
                        <span class="tabular-nums">{{ parts.value }}</span>
                        <span class="amount-pill__currency">{{ parts.currency }}</span>
                      </span>
                    </td>
                    <td class="border border-transparent text-center px-2 py-1 align-middle">
                      <div class="action-group">
                        <button
                          class="action-btn action-btn--edit"
                          title="تعديل الوصف"
                          @click="startEditingDescription(tran)"
                          :disabled="isSavingDescription && editingDescriptionId === tran.id"
                        >
                          <edit class="w-4 h-4" />
                        </button>
                        <button class="action-btn action-btn--delete" @click="openModalDel(tran)" title="حذف الحركة">
                          <trash />
                        </button>
                        <button class="action-btn action-btn--upload" @click="openModalUploader(tran)" title="مرفقات الحركة">
                          <imags />
                        </button>
                        <a
                          v-if="tran.type === 'out' || tran.type === 'outUser' || tran.type === 'debt'"
                          :href="`/api/getIndexAccountsSelas?user_id=${boxes[0].id}&print=2&transactions_id=${tran.id}`"
                          target="_blank"
                          class="action-btn action-btn--print"
                          title="طباعة سند الصرف"
                        >
                          <print class="inline-flex" />
                        </a>
                        <a
                          v-if="tran.type === 'in' || tran.type === 'inUser'"
                          :href="`/api/getIndexAccountsSelas?user_id=${boxes[0].id}&print=3&transactions_id=${tran.id}`"
                          target="_blank"
                          class="action-btn action-btn--print"
                          title="طباعة سند القبض"
                        >
                          <print class="inline-flex" />
                        </a>
                        <a
                          v-if="tran.type === 'inUserBox'"
                          :href="`/api/getIndexAccountsSelas?user_id=${tran.morphed_id}&print=3&transactions_id=${tran.id}`"
                          target="_blank"
                          class="action-btn action-btn--print"
                          title="طباعة سند القبض"
                        >
                          <print class="inline-flex" />
                        </a>
                        <a
                          v-if="tran.type === 'outUserBox'"
                          :href="`/api/getIndexAccountsSelas?user_id=${tran.morphed_id}&print=2&transactions_id=${tran.id}`"
                          target="_blank"
                          class="action-btn action-btn--print"
                          title="طباعة سند الصرف"
                        >
                          <print class="inline-flex" />
                        </a>
                      </div>
                   </td>
                  <td class="border border-transparent text-center px-1 py-1 align-middle print:hidden">
                    <a
                      v-for="(image, index) in (tran.transactions_images || [])"
                      :key="index"
                      :href="getDownloadUrl(image.name)"
                      style="cursor: pointer;"
                      target="_blank">
                      <img :src="getImageUrl(image.name)" alt="" class="px-1" style="max-width: 80px;max-height: 50px;display: inline;" />
                    </a>
                  </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="spaner">
                          <InfiniteLoading :transactions="transactions" @infinite="getResults" :identifier="resetData" />
            </div>
          </div>
        </div>
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

.amount-pill {
  display: inline-flex;
  flex-direction: row;
  align-items: baseline;
  justify-content: center;
  gap: 0.3rem;
  line-height: 1.2;
  padding: 0.2rem 0.6rem;
  border-radius: 0.5rem;
  font-weight: 700;
  font-size: 0.875rem;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  vertical-align: middle;
}

.amount-pill__currency {
  font-size: 0.75rem;
  font-weight: 600;
  opacity: 0.9;
}

.amount-pill--in {
  background-color: rgba(6, 95, 70, 0.55);
  color: #a7f3d0;
  border: 1px solid rgba(52, 211, 153, 0.4);
}

.amount-pill--out {
  background-color: rgba(136, 19, 55, 0.55);
  color: #fecdd3;
  border: 1px solid rgba(251, 113, 133, 0.4);
}

.action-group {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 0.35rem;
}

.action-btn {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 0.5rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  transition: transform 0.2s ease, filter 0.2s ease;
}

.action-btn:hover {
  transform: translateY(-1px);
  filter: brightness(1.05);
}

.action-btn:disabled,
.action-btn[disabled] {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-btn--edit {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.action-btn--delete {
  background: linear-gradient(135deg, #f43f5e, #e11d48);
}

.action-btn--upload {
  background: linear-gradient(135deg, #8b5cf6, #7c3aed);
}

.action-btn--print {
  background: linear-gradient(135deg, #22c55e, #16a34a);
}

.account-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.35rem 0.9rem;
  border-radius: 999px;
  font-weight: 700;
  color: #f8fafc;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.35), rgba(14, 165, 233, 0.45));
  border: 1px solid rgba(148, 163, 184, 0.45);
  box-shadow: 0 10px 24px -14px rgba(14, 116, 144, 0.7);
  transition: transform 0.15s ease, box-shadow 0.15s ease, filter 0.15s ease;
}

.account-link:hover {
  transform: translateY(-1px);
  box-shadow: 0 18px 32px -18px rgba(59, 130, 246, 0.7);
  filter: brightness(1.08);
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
  background-color: #115e59;
  color: #ffffff;
  border-color: #0f766e;
}

.money-account-badge--treasury {
  background-color: #334155;
  color: #ffffff;
  border-color: #475569;
}

.money-account-badge--other {
  background-color: #312e81;
  color: #ffffff;
  border-color: #4338ca;
}

.money-account-badge--none {
  background-color: #334155;
  color: #f8fafc;
  border-color: #475569;
}
</style>