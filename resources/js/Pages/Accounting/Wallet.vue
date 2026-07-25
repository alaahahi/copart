<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import { Head, Link, useForm } from "@inertiajs/inertia-vue3";
import { ref, watch, computed } from "vue";
import ModalAddSales from "@/Components/ModalAddSales.vue";
import ModalAddExpensesWallet from "@/Components/ModalAddExpensesWallet.vue";
import TextInput from "@/Components/TextInput.vue";
import ModalConvertDollarDinar from "@/Components/ModalConvertDollarDinar.vue";
import ModalConvertDinarDollar from "@/Components/ModalConvertDinarDollar.vue";
import ModalDel from "@/Components/ModalDel.vue";
import ModalUploader from "@/Components/ModalUploader.vue";
import ModalEditTransaction from "@/Components/ModalEditTransaction.vue";
import ModalDriverLoan from "@/Components/ModalDriverLoan.vue";
import ModalDriverLoanRepayment from "@/Components/ModalDriverLoanRepayment.vue";


import axios from 'axios';
import show from "@/Components/icon/show.vue";
import pay from "@/Components/icon/pay.vue";
import trash from "@/Components/icon/trash.vue";
import edit from "@/Components/icon/edit.vue";
import imags from "@/Components/icon/imags.vue";
import print from "@/Components/icon/print.vue";

import InfiniteLoading from "v3-infinite-loading";
import "v3-infinite-loading/lib/style.css";
import debounce from 'lodash/debounce';
import { formatBaghdadTimestamp } from "@/utils/datetime";
import { formatMoney, formatNumber } from "@/utils/formatMoney";


const laravelData = ref({});
const searchTerm = ref('');
let showModalAddSales = ref(false);
let showModaldebtSales = ref(false);
let showModalAddExpensesWallet = ref(false);
let showModalConvertDollarDinar = ref(false);
let showModalConvertDinarDollar = ref(false);
let showModalDel = ref(false);
let showModalUploader = ref(false);
let showModalAddSalesAmanah = ref(false);
let showModaldebtSalesAmanah = ref(false);
let showModalEditTransaction = ref(false);
let tranIdForEdit = ref(null);
let tagOptions = ref([]);
let transactions= ref([]);
let activeTab = ref('payments');
let tagsLoaded = ref(false);
let tagsList = ref([]);
let selectedTagName = ref(null);
let transactionsByTag = ref([]);
let newTagName = ref('');
let driversSummary = ref([]);
let driversSummaryLoaded = ref(false);
let showModalDriverLoan = ref(false);
let showModalDriverLoanRepayment = ref(false);
let loanForRepayment = ref(null);
let loanTransactions = ref([]);
let loanTransactionsLoaded = ref(false);
let tranId =ref({});
let formData = ref({});
let isLoading=ref(false);
let from = ref('');
let to = ref('');
let tagFrom = ref('');
let tagTo = ref('');
let mainAccount= ref(0)
let onlineContracts= ref(0)
let howler= ref(0)
let shippingCoc= ref(0)
let border= ref(0)
let iran= ref(0)
let dubai= ref(0)
let debtOnlineContracts= ref(0)
let allCars= ref(0)
let onlineContractsDinar = ref(0)
let debtOnlineContractsDinar = ref(0)
let resetData = ref(false);
let user_id = 0;
let page = 1;
let q = ref('');
let qDriver = ref('');
let filterTag = ref('');
const refresh = () => {
  page = 0;
  transactions.value.length = 0;
  resetData.value = !resetData.value;
};
const debouncedGetResultsCar = debounce(refresh, 500);

const getResults = async ($state) => {
  try {
    const params = {
      limit: 1000,
      page: page,
      q: q.value,
      user_id: props.boxes.id,
      type: 'wallet'
    };
    if (qDriver.value) params.q_driver = qDriver.value;
    if (filterTag.value) params.tag = filterTag.value;
    const response = await axios.get(`/getIndexAccounting`, { params });

    const json = response.data;


    if (json.transactions.data.length < 1000){
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
 

function loadTagOptionsIfNeeded() {
  if (!hasWalletTags.value) return;
  axios.get('/api/paymentTags').then((r) => {
    tagOptions.value = r.data || [];
  }).catch(() => {});
}

function prepareWalletModalData() {
  loadTagOptionsIfNeeded();
  loadDriversSummary();
}

function openAddSales() {
  prepareWalletModalData();
  showModalAddSales.value = true;
}
function opendebtSales() {
  showModaldebtSales.value = true;
}
function openAddExpenses(){
  prepareWalletModalData();
  showModalAddExpensesWallet.value = true;
}
function openAddSalesAmanah() {
  showModalAddSalesAmanah.value = true;
}
function opendebtSalesAmanah() {
  showModaldebtSalesAmanah.value = true;
}
function openConvertDollarDinar(){
  showModalConvertDollarDinar.value = true;
}
function openConvertDinarDollar(){
  showModalConvertDinarDollar.value = true;
}
function isAmanahTransaction(tran) {
  return tran?.type === 'inUserAmanah' || tran?.type === 'outUserAmanah';
}

function openModalDel(tran){
  if (isAmanahTransaction(tran)) {
    deleteAmanahTransaction(tran);
    return;
  }
  tranId.value = tran
  showModalDel.value = true;
}

function deleteAmanahTransaction(tran) {
  axios.post(`/api/delTransactions?id=${tran.id}`)
    .then(() => {
      transactions.value = transactions.value.filter((t) => t.id !== tran.id);
      showModalEditTransaction.value = false;
      refresh();
    })
    .catch((error) => {
      console.error(error);
    });
}
function openModalUploader(tran){
  tranId.value = tran
  showModalUploader.value = true;
}
function openModalEditTransaction(tran) {
  tranIdForEdit.value = tran;
  showModalEditTransaction.value = true;
}

const props = defineProps({
  url: String,
  boxes: Object,
});

const hasWalletTags = ref(!!props.boxes?.has_wallet_tags);
watch(() => props.boxes?.has_wallet_tags, (v) => { hasWalletTags.value = !!v; }, { immediate: true });

async function toggleWalletTagsFlag() {
  if (!props.boxes?.id) return;
  try {
    const { data } = await axios.post('/api/toggleWalletTags', {
      user_id: props.boxes.id,
      has_wallet_tags: !hasWalletTags.value,
    });
    hasWalletTags.value = data.has_wallet_tags;
  } catch (e) {
    console.error(e);
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
  V.id=props.boxes.id;
  axios.post('/api/receiptArrivedUser',V)
  .then(response => {
    showModalAddSales.value=false;
    refresh();
  })
  .catch(error => {
    errors.value = error.response.data.errors
  })
}
function confirmdebt(V) {
  V.id = props.boxes.id;
  axios.post('/api/salesDebtUser',V)
  .then(response => {
    showModaldebtSales.value=false;
    showModalAddExpensesWallet.value = false;
    refresh();
  })
  .catch(error => {

    errors.value = error.response?.data?.errors || error.response?.data?.message
  })
}
function confirmAmanah(V) {
  V.id=props.boxes.id;
  axios.post('/api/receiptArrivedUserAmanah',V)
  .then(response => {
    showModalAddSalesAmanah.value=false;
    refresh();
  })
  .catch(error => {
    errors.value = error.response.data.errors
  })
}
function confirmdebtAmanah(V) {
  V.id = props.boxes.id;
  axios.post('/api/salesDebtUserAmanah',V)
  .then(response => {
    showModaldebtSalesAmanah.value=false;
    refresh();
  })
  .catch(error => {

    errors.value = error.response?.data?.errors || error.response?.data?.message
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
  // في صفحة القاصة، أغلب الحركات المرتبطة بالمحاسبة يكون لها parent_id (حركة الصندوق الرئيسي)
  // حتى يتم حذف الحركة من صفحة المحاسبة أيضاً، نرسل رقم حركة الأصل إن وجد، وإلا نستخدم نفس رقم الحركة
  const targetId = id.parent_id && id.parent_id !== 0 ? id.parent_id : id.id;
  axios.post(`/api/delTransactions?id=${targetId}`)
  .then(response => {
    refresh();
    showModalDel.value=false;
  })
  .catch(error => {

    errors.value = error.response.data.errors
  })
}

function updateResults(input) {
  return formatNumber(input);
}

function getImageUrl(name) {
  // Provide the base URL for your images
  return `/public/uploadsResized/${name}`;
}

function getDownloadUrl(name) {
  // Provide the base URL for downloading images
  return `/public/uploads/${name}`;
}

// اسم الحساب المحاسبي الحقيقي (صندوق دولار/دينار أو حساب القاصة) الذي أثرت به الحركة،
// قادم من القيد المحاسبي (journal) المرتبط بالحركة أو بالحركة الأم لها.
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

// حساب الرصيد التراكمي
function calculateBalance(transaction, index) {
  let balance = 0;
  // نحتاج لحساب الرصيد من أقدم معاملة حتى هذه المعاملة
  // المعاملات مرتبة من الأحدث للأقدم، لذا نحتاج لعكس الترتيب في الحساب
  
  // إنشاء نسخة مرتبة من المعاملات حسب التاريخ والـ ID من الأقدم للأحدث
  const sortedTransactions = [...transactions.value]
    .filter(t => t && (t.currency ?? '$') === (transaction.currency ?? '$'))
    .sort((a, b) => {
      // الترتيب حسب التاريخ أولاً
      const dateA = new Date(a.created_at || a.created || 0);
      const dateB = new Date(b.created_at || b.created || 0);
      const dateDiff = dateA.getTime() - dateB.getTime();
      
      // إذا كانت التواريخ متساوية، نرتب حسب ID (الأقدم أولاً - ID أصغر)
      if (dateDiff === 0) {
        return (a.id || 0) - (b.id || 0);
      }
      return dateDiff;
    });
  
  // العثور على موضع هذه المعاملة في القائمة المرتبة
  const transactionId = transaction.id || 0;
  
  for (let i = 0; i < sortedTransactions.length; i++) {
    const tran = sortedTransactions[i];
    
    // نأخذ فقط معاملات الصندوق (ليس الأمانة) لحساب الرصيد
    if (tran.type === 'inUser') {
      balance += parseFloat(tran.amount) || 0;
    } else if (tran.type === 'outUser') {
      balance -= Math.abs(parseFloat(tran.amount) || 0);
    }
    // نتجاهل معاملات الأمانة لأنها لا تؤثر على balance
    
    // إذا وصلنا إلى هذه المعاملة، نتوقف
    if (tran.id === transactionId) {
      break;
    }
  }
  
  return balance;
}

function printAmanah() {
  if(props.boxes?.id) {
    window.open(`/getIndexAccounting?user_id=${props.boxes.id}&type=wallet&print=7`, '_blank');
  }
}

function printWallet() {
  if(props.boxes?.id) {
    window.open(`/getIndexAccounting?user_id=${props.boxes.id}&type=wallet&print=8`, '_blank');
  }
}

function setActiveTab(tab) {
  if (tab === 'tags' && !tagsLoaded.value) {
    axios.get('/api/paymentTags').then(r => {
      tagOptions.value = r.data || [];
      tagsList.value = r.data || [];
      tagsLoaded.value = true;
    });
  }
  activeTab.value = tab;
}

function addTag() {
  const name = newTagName.value.trim();
  if (!name) return;
  axios.post('/api/paymentTags', { name }).then(r => {
    tagsList.value = [...tagsList.value, r.data];
    tagOptions.value = [...tagOptions.value, r.data];
    newTagName.value = '';
  }).catch(() => {});
}

function deleteTag(tag) {
  if (!confirm('حذف التاغ؟ سيُزال التاغ من الدفعات المرتبطة.')) return;
  axios.post('/api/deletePaymentTag', { id: tag.id }).then(() => {
    tagsList.value = tagsList.value.filter(t => t.id !== tag.id);
    tagOptions.value = tagOptions.value.filter(t => t.id !== tag.id);
    if (selectedTagName.value === tag.name) {
      selectedTagName.value = null;
      transactionsByTag.value = [];
    }
  }).catch(() => {});
}

function selectTag(name) {
  selectedTagName.value = name;
  fetchTagTransactions();
}

function fetchTagTransactions() {
  if (!selectedTagName.value || !props.boxes?.id) {
    transactionsByTag.value = [];
    return;
  }
  const params = {
    user_id: props.boxes.id,
    type: 'wallet',
    tag: selectedTagName.value,
    limit: 1000,
  };
  if (tagFrom.value && tagTo.value) {
    params.from = tagFrom.value;
    params.to = tagTo.value;
  }
  axios.get('/getIndexAccounting', { params }).then(r => {
    transactionsByTag.value = r.data.transactions?.data || [];
  }).catch(() => { transactionsByTag.value = []; });
}

function loadDriversSummary() {
  if (driversSummaryLoaded.value) return;
  axios.get('/getIndexAccounting', { params: { user_id: props.boxes.id, type: 'wallet', group_by_driver: 1, limit: 1 } }).then(r => {
    driversSummary.value = r.data.drivers_summary || [];
    driversSummaryLoaded.value = true;
  }).catch(() => {});
}

function loadLoanTransactions() {
  if (loanTransactionsLoaded.value) return;
  axios.get('/getIndexAccounting', { params: { user_id: props.boxes.id, type: 'wallet', loans_only: 1, limit: 500 } }).then(r => {
    loanTransactions.value = r.data.transactions?.data || [];
    loanTransactionsLoaded.value = true;
  }).catch(() => {});
}

function openRepaymentModal(loanTran) {
  loanForRepayment.value = loanTran;
  showModalDriverLoanRepayment.value = true;
}

/** ملخص دفعات التاغ: مجموع عدد السيارات والرصيد (إيداع يزيد، سحب ينقص) */
const tagSummary = computed(() => {
  const list = transactionsByTag.value || [];
  let totalCars = 0;
  let balance = 0;
  for (const tran of list) {
    totalCars += Number(tran.details?.cars_count) || 0;
    balance += Number(tran.amount) || 0;
  }
  return { totalCars, balance };
});

const walletBalanceUsd = computed(
  () => Number(laravelData.value?.sumInTransactionsUser ?? 0) - Number(laravelData.value?.sumOutTransactionsUser ?? 0)
);
const walletBalanceIqd = computed(
  () => Number(laravelData.value?.sumInTransactionsDinarUser ?? 0) - Number(laravelData.value?.sumOutTransactionsDinarUser ?? 0)
);
const amanahBalanceUsd = computed(
  () => Number(laravelData.value?.sumInTransactionsUserAmanah ?? 0) - Number(laravelData.value?.sumOutTransactionsUserAmanah ?? 0)
);
const amanahBalanceIqd = computed(
  () => Number(laravelData.value?.sumInTransactionsDinarUserAmanah ?? 0) - Number(laravelData.value?.sumOutTransactionsDinarUserAmanah ?? 0)
);

function moneyClass(amount) {
  if (amount > 0) return 'text-emerald-700 dark:text-emerald-300';
  if (amount < 0) return 'text-rose-700 dark:text-rose-300';
  return 'text-slate-900 dark:text-white';
}

function printTagDetails() {
  if (!selectedTagName.value || !props.boxes?.id) return;
  const query = new URLSearchParams({
    user_id: String(props.boxes.id),
    type: 'wallet',
    tag: selectedTagName.value,
    print: '8',
  });
  if (tagFrom.value && tagTo.value) {
    query.set('from', tagFrom.value);
    query.set('to', tagTo.value);
  }
  window.open(`/getIndexAccounting?${query.toString()}`, '_blank');
}

</script>

<template>
  <Head :title="boxes?.name ? `قاصة — ${boxes.name}` : 'القاصة'" />
  <AuthenticatedLayout>
    <template #header />

    <ModalDel
      :show="showModalDel ? true : false"
      :formData="tranId"
      @a="delTransactions($event)"
      @close="showModalDel = false"
    >
      <template #header>
        <h2 class="mb-5 text-center text-slate-800 dark:text-slate-200">
          هل متأكد من الحذف؟
        </h2>
      </template>
    </ModalDel>

    <ModalUploader
      :show="showModalUploader ? true : false"
      :formData="tranId"
      @a="refresh()"
      @close="showModalUploader = false"
    >
      <template #header>
        <h2 class="mb-5 text-center text-slate-800 dark:text-slate-200">
          مرفقات الحركة
        </h2>
      </template>
    </ModalUploader>

    <ModalEditTransaction
      :show="showModalEditTransaction && !!tranIdForEdit"
      :transaction="tranIdForEdit || {}"
      :tagOptions="tagOptions"
      @saved="() => {}"
      @close="showModalEditTransaction = false"
    />

    <ModalDriverLoan
      :show="showModalDriverLoan"
      :box-id="boxes?.id"
      @saved="refresh(); loanTransactionsLoaded = false"
      @close="showModalDriverLoan = false"
    />
    <ModalDriverLoanRepayment
      :show="showModalDriverLoanRepayment"
      :loan-transaction="loanForRepayment"
      @saved="refresh(); loanTransactionsLoaded = false"
      @close="showModalDriverLoanRepayment = false; loanForRepayment = null"
    />

    <ModalAddSales
      :show="showModalAddSales ? true : false"
      :tagOptions="tagOptions"
      :showTagSelect="hasWalletTags"
      title="إيداع إلى القاصة"
      subtitle="تسجيل إيداع بالدولار أو الدينار"
      confirm-label="تأكيد الإيداع"
      @a="confirm($event)"
      @close="showModalAddSales = false"
    />

    <ModalAddExpensesWallet
      :show="showModalAddExpensesWallet ? true : false"
      :boxes="boxes"
      :tagOptions="tagOptions"
      :showTagSelect="hasWalletTags"
      title="سحب من القاصة"
      subtitle="تسجيل سحب بالدولار أو الدينار"
      @a="confirmdebt($event)"
      @close="showModalAddExpensesWallet = false"
    />
    <ModalAddSales
      :show="showModalAddSalesAmanah ? true : false"
      :tagOptions="tagOptions"
      title="أمانة - إيداع"
      subtitle="تسجيل أمانة واردة بالدولار أو الدينار"
      confirm-label="تأكيد الإيداع"
      @a="confirmAmanah($event)"
      @close="showModalAddSalesAmanah = false"
    />

    <ModalAddExpensesWallet
      :show="showModaldebtSalesAmanah ? true : false"
      :boxes="boxes"
      title="أمانة - سحب"
      subtitle="تسجيل سحب أمانة بالدولار أو الدينار"
      @a="confirmdebtAmanah($event)"
      @close="showModaldebtSalesAmanah = false"
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
      @a="confirmConvertDinarDollar($event)"
      @close="showModalConvertDinarDollar = false"
    />

    <div v-if="$page.props.success" class="mx-auto mb-4 max-w-9xl px-4 sm:px-6 lg:px-8">
      <div class="rounded-lg bg-emerald-50 p-3 text-center text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200" role="alert">
        {{ $page.props.success }}
      </div>
    </div>

    <div class="mx-auto max-w-9xl sm:px-6 lg:px-8">
      <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-700">
          <div>
            <h1 class="text-lg font-bold text-slate-900 dark:text-white">
              قاصة — {{ boxes?.name }}
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">حركات الصندوق والأمانة</p>
          </div>
          <div class="flex flex-wrap items-center gap-2 print:hidden">
            <template v-if="hasWalletTags">
              <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="activeTab === 'payments'
                  ? 'bg-slate-800 text-white dark:bg-slate-100 dark:text-slate-900'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                @click="setActiveTab('payments')"
              >
                الدفعات
              </button>
              <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="activeTab === 'tags'
                  ? 'bg-slate-800 text-white dark:bg-slate-100 dark:text-slate-900'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                @click="setActiveTab('tags')"
              >
                إدارة التاغات
              </button>
            </template>
            <button
              v-if="$page.props.auth.user.type_id==1 || $page.props.auth.user.type_id==2 || $page.props.auth.user.type_id==5"
              type="button"
              class="rounded-lg border px-3 py-1.5 text-xs font-medium transition"
              :class="hasWalletTags
                ? 'border-indigo-300 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:border-indigo-500/40 dark:bg-indigo-950/40 dark:text-indigo-300'
                : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
              @click="toggleWalletTagsFlag"
            >
              {{ hasWalletTags ? 'التاغات مفعّلة' : 'تفعيل التاغات' }}
            </button>
          </div>
        </div>

        <div v-if="!hasWalletTags || activeTab === 'payments'">
          <!-- KPI chips -->
          <div class="border-b border-slate-200 p-4 dark:border-slate-700">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
              <div class="rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                <div class="text-xs font-semibold text-slate-600 dark:text-slate-300">رصيد الصندوق · $</div>
                <div class="mt-1 font-mono text-lg font-bold tabular-nums" :class="moneyClass(walletBalanceUsd)">
                  {{ formatMoney(walletBalanceUsd, '$') }} <span class="text-sm font-normal text-slate-400">$</span>
                </div>
              </div>
              <div class="rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                <div class="text-xs font-semibold text-slate-600 dark:text-slate-300">رصيد الصندوق · د.ع</div>
                <div class="mt-1 font-mono text-lg font-bold tabular-nums" :class="moneyClass(walletBalanceIqd)">
                  {{ formatMoney(walletBalanceIqd, 'IQD') }} <span class="text-sm font-normal text-slate-400">د.ع</span>
                </div>
              </div>
              <div class="rounded-xl border border-sky-400 bg-white px-4 py-3 shadow-sm dark:border-sky-500/50 dark:bg-slate-800">
                <div class="text-xs font-semibold text-sky-800 dark:text-sky-300">أمانة · $</div>
                <div class="mt-1 font-mono text-lg font-bold tabular-nums text-sky-700 dark:text-sky-200">
                  {{ formatMoney(amanahBalanceUsd, '$') }} <span class="text-sm font-normal text-slate-400">$</span>
                </div>
              </div>
              <div class="rounded-xl border border-sky-400 bg-white px-4 py-3 shadow-sm dark:border-sky-500/50 dark:bg-slate-800">
                <div class="text-xs font-semibold text-sky-800 dark:text-sky-300">أمانة · د.ع</div>
                <div class="mt-1 font-mono text-lg font-bold tabular-nums text-sky-700 dark:text-sky-200">
                  {{ formatMoney(amanahBalanceIqd, 'IQD') }} <span class="text-sm font-normal text-slate-400">د.ع</span>
                </div>
              </div>
            </div>

            <!-- Compact action toolbar -->
            <div
              v-if="$page.props.auth.user.type_id==1 || $page.props.auth.user.type_id==2 || $page.props.auth.user.type_id==5"
              class="mt-4 flex flex-wrap gap-2 print:hidden"
            >
              <button
                type="button"
                class="min-h-[38px] rounded-lg bg-emerald-700 px-3.5 py-1.5 text-sm font-semibold text-white transition hover:bg-emerald-800"
                @click="openAddSales()"
              >
                {{ $t('receipt_voucher_add') }}
              </button>
              <button
                type="button"
                class="min-h-[38px] rounded-lg bg-rose-700 px-3.5 py-1.5 text-sm font-semibold text-white transition hover:bg-rose-800"
                @click="openAddExpenses()"
              >
                {{ $t('payment_voucher_withdraw') }}
              </button>
              <button
                type="button"
                class="min-h-[38px] rounded-lg border border-emerald-500/60 bg-emerald-50 px-3.5 py-1.5 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100 dark:border-emerald-500/40 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-950/60"
                @click="openAddSalesAmanah()"
              >
                أمانة — إيداع
              </button>
              <button
                type="button"
                class="min-h-[38px] rounded-lg border border-rose-500/60 bg-rose-50 px-3.5 py-1.5 text-sm font-semibold text-rose-800 transition hover:bg-rose-100 dark:border-rose-500/40 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-950/60"
                @click="opendebtSalesAmanah()"
              >
                أمانة — سحب
              </button>
              <span class="mx-1 hidden h-8 w-px self-center bg-slate-200 dark:bg-slate-700 sm:inline-block" aria-hidden="true" />
              <button
                type="button"
                class="min-h-[38px] rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                @click="printWallet()"
              >
                {{ $t('print') }} الصندوق
              </button>
              <button
                type="button"
                class="min-h-[38px] rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                @click="printAmanah()"
              >
                {{ $t('print') }} الأمانة
              </button>
              <button
                v-if="hasWalletTags"
                type="button"
                class="min-h-[38px] rounded-lg border border-violet-400/60 bg-violet-50 px-3.5 py-1.5 text-sm font-medium text-violet-800 transition hover:bg-violet-100 dark:border-violet-500/40 dark:bg-violet-950/40 dark:text-violet-300"
                @click="showModalDriverLoan = true"
              >
                قرض سائق
              </button>
            </div>
            <div
              v-else
              class="mt-4 flex flex-wrap gap-2 print:hidden"
            >
              <button
                type="button"
                class="min-h-[38px] rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                @click="printWallet()"
              >
                {{ $t('print') }} الصندوق
              </button>
              <button
                type="button"
                class="min-h-[38px] rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                @click="printAmanah()"
              >
                {{ $t('print') }} الأمانة
              </button>
            </div>
          </div>

          <!-- Filters -->
          <div class="border-b border-slate-200 p-4 dark:border-slate-700">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
              <div>
                <label for="search" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">بحث في الدفعات والأمانات</label>
                <TextInput
                  id="search"
                  v-model="q"
                  type="text"
                  class="mt-0 block w-full"
                  placeholder="رقم الوصل أو الوصف..."
                  @input="debouncedGetResultsCar"
                />
              </div>
              <template v-if="hasWalletTags">
                <div>
                  <label for="q_driver" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">بحث باسم السائق</label>
                  <TextInput
                    id="q_driver"
                    v-model="qDriver"
                    type="text"
                    class="mt-0 block w-full"
                    placeholder="اسم السائق..."
                    @input="debouncedGetResultsCar"
                  />
                </div>
                <div v-if="tagOptions.length">
                  <label for="filter_tag" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">فلتر التاغ</label>
                  <select
                    id="filter_tag"
                    v-model="filterTag"
                    class="mt-0 block w-full rounded-md border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200"
                    @change="debouncedGetResultsCar()"
                  >
                    <option value="">— الكل —</option>
                    <option v-for="t in tagOptions" :key="t.id" :value="t.name">{{ t.name }}</option>
                  </select>
                </div>
                <div class="flex flex-wrap items-end gap-2">
                  <button
                    type="button"
                    class="min-h-[42px] rounded-lg bg-slate-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                    @click="loadDriversSummary()"
                  >
                    ملخص السائقين
                  </button>
                  <button
                    type="button"
                    class="min-h-[42px] rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                    @click="loadLoanTransactions()"
                  >
                    قروض السائقين
                  </button>
                </div>
              </template>
            </div>
          </div>

          <!-- Driver loans panel -->
          <div v-if="hasWalletTags && loanTransactionsLoaded" class="border-b border-slate-200 p-4 dark:border-slate-700">
            <h4 class="mb-2 text-sm font-semibold text-slate-900 dark:text-slate-100">قروض السائقين</h4>
            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
              <table class="w-full text-right text-sm text-slate-800 dark:text-slate-200">
                <thead class="bg-slate-800 text-slate-100 dark:bg-slate-950">
                  <tr>
                    <th class="px-2 py-2 font-medium">رقم</th>
                    <th class="px-2 py-2 font-medium">السائق</th>
                    <th class="px-2 py-2 font-medium">التاريخ</th>
                    <th class="px-2 py-2 font-medium">المبلغ</th>
                    <th class="px-2 py-2 font-medium">تنفيذ</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                  <tr v-for="tran in loanTransactions" :key="tran.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-2 py-2">{{ tran.id }}</td>
                    <td class="px-2 py-2">{{ tran.details?.driver_name || '—' }}</td>
                    <td class="px-2 py-2">{{ formatBaghdadTimestamp(tran?.created_at) }}</td>
                    <td class="px-2 py-2 font-mono">{{ formatMoney(Math.abs(tran.amount), tran.currency ?? '$') }} {{ tran.currency ?? '$' }}</td>
                    <td class="px-2 py-2">
                      <button type="button" class="rounded bg-emerald-600 px-2 py-1 text-xs text-white hover:bg-emerald-700" @click="openRepaymentModal(tran)">تسجيل دفعة إرجاع</button>
                    </td>
                  </tr>
                  <tr v-if="loanTransactions.length === 0">
                    <td colspan="5" class="px-2 py-4 text-center text-slate-500 dark:text-slate-400">لا توجد قروض مسجلة</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Drivers summary -->
          <div v-if="hasWalletTags && driversSummary.length" class="border-b border-slate-200 p-4 dark:border-slate-700">
            <h4 class="mb-2 text-sm font-semibold text-slate-900 dark:text-slate-100">مجموع التوصيلات حسب السائق</h4>
            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
              <table class="w-full text-right text-sm text-slate-800 dark:text-slate-200">
                <thead class="bg-slate-800 text-slate-100 dark:bg-slate-950">
                  <tr>
                    <th class="px-2 py-2 font-medium">السائق</th>
                    <th class="px-2 py-2 font-medium">عدد الحركات</th>
                    <th class="px-2 py-2 font-medium">إجمالي إيداع</th>
                    <th class="px-2 py-2 font-medium">إجمالي سحب</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                  <tr v-for="row in driversSummary" :key="row.driver_name" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-2 py-2 font-medium">{{ row.driver_name }}</td>
                    <td class="px-2 py-2">{{ row.count }}</td>
                    <td class="px-2 py-2 font-mono font-bold text-emerald-700 dark:text-emerald-300">{{ formatMoney(row.total_in) }}</td>
                    <td class="px-2 py-2 font-mono font-bold text-rose-700 dark:text-rose-300">{{ formatMoney(row.total_out) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Transactions table (primary focus) -->
          <div class="p-4">
            <div class="relative overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
              <table class="w-full text-center text-sm text-slate-800 dark:text-slate-100">
                <thead>
                  <tr class="bg-slate-800 text-white dark:bg-slate-950 dark:text-white">
                    <th class="px-2 py-2.5 font-semibold">رقم الوصل</th>
                    <th class="px-2 py-2.5 font-semibold">{{ $t('accounting_account') }}</th>
                    <th class="px-2 py-2.5 font-semibold">{{ $t('date') }}</th>
                    <th class="px-2 py-2.5 font-semibold">{{ $t('description') }}</th>
                    <th v-if="hasWalletTags" class="px-2 py-2.5 font-semibold">{{ $t('tag') }}</th>
                    <th class="px-2 py-2.5 font-semibold">{{ $t('deposit_col') }}</th>
                    <th class="px-2 py-2.5 font-semibold">{{ $t('withdraw_col') }}</th>
                    <th class="px-2 py-2.5 font-semibold">{{ $t('balance') }}</th>
                    <th class="px-2 py-2.5 font-semibold">المرفقات</th>
                    <th class="px-2 py-2.5 font-semibold">{{ $t('execute') }}</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                  <tr
                    v-for="(tran, index) in transactions"
                    :key="tran.id"
                    :class="[
                      tran.type == 'inUserAmanah' ? 'bg-sky-50 dark:bg-slate-800 border-r-4 border-sky-500' :
                      tran.type == 'outUserAmanah' ? 'bg-amber-50 dark:bg-slate-800 border-r-4 border-amber-500' :
                      tran.type != 'inUser' ? 'bg-rose-50 dark:bg-slate-800 border-r-4 border-rose-500/70' : 'bg-emerald-50 dark:bg-slate-800 border-r-4 border-emerald-500/70',
                      'hover:bg-slate-100 dark:hover:bg-slate-700'
                    ]"
                  >
                    <td class="px-2 py-1.5 text-slate-800 dark:text-slate-100">
                      {{ tran.id }}
                      <span v-if="tran.type == 'inUserAmanah' || tran.type == 'outUserAmanah'" class="text-xs font-bold text-sky-700 dark:text-sky-300">(أمانة)</span>
                    </td>
                    <td class="px-2 py-1.5">
                      <span :class="getMoneyAccountBadgeClass(tran)">
                        {{ getMoneyAccountLabel(tran) ?? '—' }}
                      </span>
                    </td>
                    <td class="px-2 py-1.5 whitespace-nowrap text-slate-800 dark:text-slate-100">{{ formatBaghdadTimestamp(tran?.created_at) }}</td>
                    <td class="px-2 py-1.5 text-slate-900 dark:text-slate-100">{{ tran.description }}</td>
                    <td v-if="hasWalletTags" class="px-2 py-1.5 text-sm">
                      <span v-if="tran.tag" class="inline-block rounded bg-indigo-100 px-2 py-0.5 font-semibold text-indigo-800 dark:bg-slate-700 dark:text-white">{{ tran.tag }}</span>
                      <template v-if="tran.details && (tran.details.driver_name || tran.details.cmr || tran.details.entry_date || tran.details.cars_count)">
                        <div class="mt-1 text-xs text-slate-600 dark:text-slate-200">
                          <span v-if="tran.details.driver_name">{{ tran.details.driver_name }}</span>
                          <span v-if="tran.details.cmr"> · CMR: {{ tran.details.cmr }}</span>
                          <span v-if="tran.details.entry_date"> · {{ tran.details.entry_date }}</span>
                          <span v-if="tran.details.cars_count != null && tran.details.cars_count !== ''"> · {{ tran.details.cars_count }} سيارة</span>
                        </div>
                      </template>
                    </td>
                    <td class="px-2 py-1.5 font-mono font-bold text-emerald-700 dark:text-emerald-300">
                      <template v-if="tran.type == 'inUser' || tran.type == 'inUserAmanah'">
                        {{ formatMoney(tran.amount, tran.currency ?? '$') }} {{ tran.currency ?? '$' }}
                      </template>
                    </td>
                    <td class="px-2 py-1.5 font-mono font-bold text-rose-700 dark:text-rose-300">
                      <template v-if="tran.type == 'outUser' || tran.type == 'outUserAmanah'">
                        {{ formatMoney(tran.amount, tran.currency ?? '$') }} {{ tran.currency ?? '$' }}
                      </template>
                    </td>
                    <td class="px-2 py-1.5 font-mono font-semibold text-slate-900 dark:text-white">
                      <span v-if="tran.type == 'inUser' || tran.type == 'outUser'">
                        {{ updateResults(calculateBalance(tran, index)) }} {{ tran.currency ?? '$' }}
                      </span>
                      <span v-else class="text-slate-500 dark:text-slate-300">—</span>
                    </td>
                    <td class="px-2 py-1.5">
                      <div class="flex flex-wrap justify-center gap-1">
                        <a
                          v-for="(image, imgIdx) in tran.transactions_images || []"
                          :key="imgIdx"
                          :href="getDownloadUrl(image.name)"
                          target="_blank"
                          class="inline-block cursor-pointer rounded ring-1 ring-sky-500/60 dark:ring-sky-300"
                        >
                          <img
                            :src="getImageUrl(image.name)"
                            alt=""
                            class="inline rounded"
                            style="max-width: 50px; max-height: 50px;"
                          />
                        </a>
                        <span v-if="!tran.transactions_images || tran.transactions_images.length === 0" class="text-xs font-semibold text-slate-600 underline decoration-slate-400 dark:text-sky-300 dark:decoration-sky-300">
                          لا يوجد
                        </span>
                      </div>
                    </td>
                    <td class="px-2 py-1.5">
                      <div class="action-group">
                        <button
                          v-if="hasWalletTags"
                          class="action-btn bg-amber-600 hover:bg-amber-700"
                          title="تعديل الحركة"
                          @click="openModalEditTransaction(tran)"
                        >
                          <edit />
                        </button>
                        <button
                          class="action-btn action-btn--upload"
                          title="مرفقات الحركة"
                          @click="openModalUploader(tran)"
                        >
                          <imags />
                        </button>
                        <a
                          v-if="tran.type == 'outUser'"
                          :href="`/getIndexAccounting?user_id=${props.boxes.id}&print=10&transactions_id=${tran.id}`"
                          target="_blank"
                          class="action-btn action-btn--print"
                          title="طباعة سند الصرف"
                        >
                          <print />
                        </a>
                        <a
                          v-if="tran.type == 'inUser'"
                          :href="`/getIndexAccounting?user_id=${props.boxes.id}&print=9&transactions_id=${tran.id}`"
                          target="_blank"
                          class="action-btn action-btn--print"
                          title="طباعة سند القبض"
                        >
                          <print />
                        </a>
                        <a
                          v-if="tran.type == 'outUserAmanah'"
                          :href="`/getIndexAccounting?user_id=${props.boxes.id}&print=12&transactions_id=${tran.id}`"
                          target="_blank"
                          class="action-btn action-btn--print"
                          title="طباعة سند صرف أمانة"
                        >
                          <print />
                        </a>
                        <a
                          v-if="tran.type == 'inUserAmanah'"
                          :href="`/getIndexAccounting?user_id=${props.boxes.id}&print=11&transactions_id=${tran.id}`"
                          target="_blank"
                          class="action-btn action-btn--print"
                          title="طباعة سند قبض أمانة"
                        >
                          <print />
                        </a>
                        <button
                          class="action-btn action-btn--delete"
                          :title="isAmanahTransaction(tran) ? 'حذف الأمانة' : 'حذف الحركة'"
                          @click="isAmanahTransaction(tran) ? deleteAmanahTransaction(tran) : openModalDel(tran)"
                        >
                          <trash />
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="spaner">
              <InfiniteLoading :car="car" @infinite="getResults" :identifier="resetData" />
            </div>
          </div>
        </div>

        <!-- Tags tab -->
        <div v-if="hasWalletTags && activeTab === 'tags'" class="p-4">
          <div class="mb-4 flex flex-wrap items-center gap-2">
            <input
              v-model="newTagName"
              type="text"
              class="rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200"
              placeholder="اسم التاغ الجديد"
              @keyup.enter="addTag"
            />
            <button type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="addTag">إضافة تاغ</button>
          </div>
          <div v-if="!tagsLoaded" class="py-4 text-slate-500 dark:text-slate-400">جاري التحميل...</div>
          <div v-else class="space-y-4">
            <div class="flex flex-wrap gap-2">
              <button
                v-for="t in tagsList"
                :key="t.id"
                type="button"
                class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="selectedTagName === t.name
                  ? 'bg-indigo-600 text-white'
                  : 'bg-slate-100 text-slate-800 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700'"
                @click="selectTag(t.name)"
              >
                {{ t.name }}
                <span class="text-xs opacity-80 hover:opacity-100" title="حذف التاغ" @click.stop="deleteTag(t)">×</span>
              </button>
            </div>
            <div v-if="selectedTagName" class="mt-4">
              <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">دفعات تاغ: {{ selectedTagName }}</h3>
                <div class="flex flex-wrap items-center gap-2">
                  <input v-model="tagFrom" type="date" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200" />
                  <input v-model="tagTo" type="date" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200" />
                  <button type="button" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700" @click="fetchTagTransactions">فلترة</button>
                  <button type="button" class="rounded-lg bg-slate-600 px-3 py-1.5 text-sm text-white hover:bg-slate-700" @click="printTagDetails">طباعة تفاصيل التاغ</button>
                </div>
              </div>
              <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table class="w-full text-center text-sm text-slate-700 dark:text-slate-200">
                  <thead>
                    <tr class="bg-slate-800 text-slate-100 dark:bg-slate-950">
                      <th class="px-2 py-2">رقم</th>
                      <th class="px-2 py-2">التاريخ</th>
                      <th class="px-2 py-2">الوصف</th>
                      <th class="px-2 py-2">عدد السيارات</th>
                      <th class="px-2 py-2">المبلغ</th>
                      <th class="px-2 py-2">تنفيذ</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    <tr v-for="tran in transactionsByTag" :key="tran.id" class="hover:bg-slate-50 dark:hover:bg-slate-800">
                      <td class="px-2 py-1">{{ tran.id }}</td>
                      <td class="px-2 py-1">{{ formatBaghdadTimestamp(tran?.created_at) }}</td>
                      <td class="px-2 py-1">{{ tran.description }}</td>
                      <td class="px-2 py-1">{{ tran.details?.cars_count ?? '—' }}</td>
                      <td class="px-2 py-1 font-mono">{{ formatMoney(tran.amount, tran.currency ?? '$') }} {{ tran.currency ?? '$' }}</td>
                      <td class="px-2 py-1">
                        <button type="button" class="rounded bg-amber-600 px-2 py-1 text-xs text-white" @click="openModalEditTransaction(tran)">تعديل</button>
                        <button type="button" class="mr-1 rounded bg-rose-600 px-2 py-1 text-xs text-white" @click="openModalDel(tran); showModalEditTransaction = false">حذف</button>
                      </td>
                    </tr>
                    <tr v-if="transactionsByTag.length === 0">
                      <td colspan="6" class="px-2 py-4 text-slate-400">لا توجد دفعات لهذا التاغ</td>
                    </tr>
                    <tr v-else class="bg-slate-100 font-semibold text-slate-800 dark:bg-slate-800 dark:text-slate-200">
                      <td colspan="3" class="px-2 py-2 text-left">المجموع</td>
                      <td class="px-2 py-2">{{ tagSummary.totalCars }}</td>
                      <td class="px-2 py-2 font-mono">الرصيد: {{ formatMoney(tagSummary.balance, '$') }} $</td>
                      <td class="px-2 py-2">—</td>
                    </tr>
                  </tbody>
                </table>
              </div>
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
  background-color: #0f766e;
  color: #ffffff;
  border-color: #0d9488;
}

.money-account-badge--treasury {
  background-color: #334155;
  color: #ffffff;
  border-color: #475569;
}

.money-account-badge--other {
  background-color: #3730a3;
  color: #ffffff;
  border-color: #4f46e5;
}

.money-account-badge--none {
  background-color: #475569;
  color: #f1f5f9;
  border-color: #64748b;
}

.dark .money-account-badge--cash {
  background-color: #115e59;
  color: #ffffff;
  border-color: #0f766e;
}

.dark .money-account-badge--treasury {
  background-color: #334155;
  color: #ffffff;
  border-color: #475569;
}

.dark .money-account-badge--other {
  background-color: #312e81;
  color: #ffffff;
  border-color: #4338ca;
}

.dark .money-account-badge--none {
  background-color: #334155;
  color: #f8fafc;
  border-color: #475569;
}

.action-group {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 0.5rem;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  min-width: 2rem;
  min-height: 2rem;
  padding: 0.5rem;
  border-radius: 0.5rem;
  color: #fff;
  cursor: pointer;
  transition: transform 0.2s ease, filter 0.2s ease;
  border: none;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
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

.action-btn svg {
  width: 1.25rem;
  height: 1.25rem;
  display: block;
  stroke: currentColor;
  fill: none;
}

.action-btn--upload {
  background: linear-gradient(135deg, #0ea5e9, #0284c7);
}

.action-btn--print {
  background: linear-gradient(135deg, #10b981, #059669);
}

.action-btn--delete {
  background: linear-gradient(135deg, #f43f5e, #e11d48);
}
</style>