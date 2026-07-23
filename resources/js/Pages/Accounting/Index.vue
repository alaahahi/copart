<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import { Head, Link, useForm } from "@inertiajs/inertia-vue3";
import { ref } from "vue";
import ModalAddSales from "@/Components/ModalAddSales.vue";
import ModalAddDebt from "@/Components/ModalAddDebt.vue";
import ModalAddExpenses from "@/Components/ModalAddExpenses.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";


import ModalConvertDollarDinar from "@/Components/ModalConvertDollarDinar.vue";
import ModalConvertDinarDollar from "@/Components/ModalConvertDinarDollar.vue";
import ModalDel from "@/Components/ModalDel.vue";
import ModalUploader from "@/Components/ModalUploader.vue";
import ModalAssignTransactionToWallet from "@/Components/ModalAssignTransactionToWallet.vue";



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


const laravelData = ref({});
const searchTerm = ref('');
let showModalAddSales = ref(false);
let showModaldebtSales = ref(false);
let showModalAddExpenses = ref(false);
let showModalConvertDollarDinar = ref(false);
let showModalConvertDinarDollar = ref(false);
let showModalDel = ref(false);
let showModalUploader = ref(false);
let showModalAssignToWallet = ref(false);
let tranForAssignWallet = ref(null);
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
    mainAccount.value = response.data.data.mainAccount;
    allCars.value =response.data.data.allCars;
    transactionInTodayDollar.value = response.data.data.transactionInTodayDollar
    transactionOutTodayDollar.value = response.data.data.transactionOutTodayDollar
    transactionInTodayDinar.value = response.data.data.transactionInTodayDinar
    transactionOutTodayDinar.value = response.data.data.transactionOutTodayDinar

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

function canAssignToWallet(tran) {
  if (!tran) {
    return false;
  }
  const parentId = Number(tran.parent_id ?? 0);
  if (parentId > 0) {
    return false;
  }
  return tran.type === 'debt' || tran.type === 'out';
}

function openAssignToWalletModal(tran) {
  tranForAssignWallet.value = tran;
  showModalAssignToWallet.value = true;
}

function onAssignToWalletSaved() {
  showModalAssignToWallet.value = false;
  tranForAssignWallet.value = null;
  refresh();
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
});
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
  // Ensure the input is a number
  if (typeof input !== 'number') {
    // Try converting the input to a number
    input = parseFloat(input) || 0;
  }
  
  // Use toLocaleString to format the number with commas
  return input.toLocaleString();
}

const IN_TYPES = ['in', 'inUser', 'inUserBox'];
const OUT_TYPES = ['out', 'outUser', 'outUserBox', 'debt'];

function formatAmount(tran, direction) {
  if (!tran) {
    return '';
  }
  const amount = Number(tran.amount);
  if (Number.isNaN(amount)) {
    return '';
  }
  if (direction === 'in' && IN_TYPES.includes(tran.type)) {
    return `${updateResults(Math.abs(amount))} ${tran.currency ?? '$'}`.trim();
  }
  if (direction === 'out' && OUT_TYPES.includes(tran.type)) {
    return `${updateResults(Math.abs(amount))} ${tran.currency ?? '$'}`.trim();
  }
  return '';
}

function getAmountClass(direction) {
  return direction === 'in' ? 'amount-pill amount-pill--in dark:text-white text-xl font-bold' : 'amount-pill amount-pill--out dark:text-white text-xl font-bold';
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

// دالة للحصول على لون البرتقالي بناءً على الفهرس
function getOrangeColorClass(index) {
  const orangeColors = [
    'bg-orange-300',
    'bg-orange-400',
    'bg-orange-500',
    'bg-orange-600',
    'bg-orange-700',
    'bg-orange-800',
    'bg-orange-900'
  ];
  return orangeColors[index % orangeColors.length];
}
 
 
</script>

<template>
  <Head title="Dashboard" />
  <AuthenticatedLayout>
    <template #header>
 
    </template>
    <ModalAssignTransactionToWallet
      :show="showModalAssignToWallet"
      :transaction="tranForAssignWallet"
      :wallet-users="walletUsers"
      @saved="onAssignToWalletSaved"
      @close="showModalAssignToWallet = false; tranForAssignWallet = null"
    />

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
            :showExtendedFields="false"
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
            @a="confirmdebt($event)"
            @close="showModalAddExpenses = false"
            >
          <template #header>
            <h3 class="text-center text-gray-900 dark:text-gray-100 font-semibold">ادخال مصاريف</h3>
            
           </template>
      </ModalAddExpenses>
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
      <div class="max-w-9xl mx-auto sm:px-6 lg:px-8">
        <div class="overflow-hidden shadow-sm sm:rounded-lg">
          <div class=" border-b border-gray-200">
          

            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 lg:gap-3">
              <div class="pt-5  print:hidden">
              <button style=" width: 100%; margin-top: 4px;" v-if="$page.props.auth.user.type_id==1 || $page.props.auth.user.type_id==2 || $page.props.auth.user.type_id==5|| $page.props.auth.user.type_id==6" className="px-4 py-2 text-white bg-green-500 rounded-md focus:outline-none"
                                            @click="openAddSales()">
                                            وصل قبض
                                            (أضافة)
              </button>
              </div>

              <div class="pt-5  hidden">
              <button style=" width: 100%; margin-top: 4px;"  v-if="$page.props.auth.user.type_id==1 || $page.props.auth.user.type_id==2|| $page.props.auth.user.type_id==5" className="px-4 py-2 text-white bg-yellow-500 rounded-md focus:outline-none"
                                            @click="opendebtSales()">
                                             تحويل لحساب 
              </button>
              </div>
              
              <div class="pt-5  print:hidden">
              <button  style=" width: 100%; margin-top: 4px;"  v-if="$page.props.auth.user.type_id==1 || $page.props.auth.user.type_id==2|| $page.props.auth.user.type_id==5|| $page.props.auth.user.type_id==6" className="px-4 py-2 text-white bg-rose-500 rounded-md focus:outline-none"
                                            @click="openAddExpenses()">
                                             وصل صرف
                                             (سحب)

              </button>
       
              </div>
              
              <div class=" px-4">
                          <div >
                              <InputLabel for="from" value="من تاريخ" />
                              <TextInput
                                id="from"
                                type="date"
                                class="mt-1 block w-full"
                                v-model="from"
                                
                              />
                            </div>
              </div>
              <div class=" px-4">
                            <div >
                              <InputLabel for="to" value="حتى تاريخ" />
                              <TextInput
                                id="to"
                                type="date"
                                class="mt-1 block w-full"
                                v-model="to"
                              />
                            </div>
              </div>
              <div class=" mr-5 print:hidden">
                            <InputLabel for="pay" value="فلترة" />
                            <button
                            @click.prevent="refresh()"
                            class="px-6 mb-6 py-2 mt-1 font-bold text-white bg-gray-500 rounded" style="width: 100%">
                            <span v-if="!isLoading">فلترة</span>
                            <span v-else>جاري الحفظ...</span>
                          </button>
              </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-4 gap-3 lg:gap-3">
                        <div>
                          <button
                            type="button"
                            @click="openConvertDollarDinar()"
                            style="min-width:150px;"
                            className="px-6 mb-6 w-full py-2 font-bold text-white bg-teal-500 rounded">
                             تحويل دولار الى دينار  
                          </button>
                        </div>
                        <div>
                          <button
                            type="button"
                            @click="openConvertDinarDollar()"
                            style="min-width:150px;"
                            className="px-6 mb-6 w-full py-2 font-bold text-white bg-yellow-500 rounded">
                             تحويل دينار الى دولار  
                          </button>
                        </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 lg:gap-3" v-if="flaggedWallets && flaggedWallets.length > 0">
              <div v-for="(wallet, index) in flaggedWallets" :key="wallet.id">
                <Link
                  v-if="$page.props.auth.user.owner_id==1"
                  type="button"
                  :href="`/wallet?id=${wallet.id}`"
                  style="min-width:150px;"
                  :class="`px-6 mb-6 py-2 font-bold text-white ${getOrangeColorClass(index)} rounded w-full mt-1 text-center`">
                  {{ wallet.name }}
                </Link>
              </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-4 gap-3 lg:gap-3">
             


              <div class=" px-4">
                              <InputLabel for="to" value="رصيد الصندوق بالدولار" />
                              <TextInput
                                id="to"
                                type="number"
                                disabled
                                class="mt-1 block w-full"
                                :value="laravelData?.user?.wallet.balance"
                              />
              </div>
              <div class=" px-4">
                              <InputLabel for="to" value="رصيد الصندوق بالدينار العراقي" />
                              <TextInput
                                id="to"
                                type="number"
                                disabled
                                class="mt-1 block w-full"
                                :value="laravelData?.user?.wallet.balance_dinar"
                              />
              </div>
              <div class="relative w-full px-4">
                          <InputLabel for="to" value="بحث رقم الوصل او الوصف" />
                          <TextInput
                                id="q"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="q"
                                @input="debouncedGetResultsCar"                              />
             
              </div>
               <div class="relative w-full px-4">
                          <table class="w-full text-sm text-center text-gray-100 border border-gray-700 rounded-lg overflow-hidden bg-slate-900">
                            <thead class="bg-slate-800 text-gray-100">
                              <tr>
                                <th class="border p-2">العملة</th>
                                <th class="border p-2">الدخل</th>
                                <th class="border p-2">الخروج</th>
                                <th class="border p-2">الفرق</th>
                              </tr>
                            </thead>
                            <tbody>
                              <!-- دولار -->
                              <tr class="bg-slate-900">
                                <td class="border border-gray-700 p-2 font-bold text-emerald-300">دولار</td>
                                <td class="border border-gray-700 p-2 text-emerald-200 font-semibold">{{updateResults(transactionInTodayDollar)}}</td>
                                <td class="border border-gray-700 p-2 text-rose-200 font-semibold">{{updateResults(transactionOutTodayDollar)}}</td>
                                <td class="border border-gray-700 p-2 font-semibold">
                                  <span :class="(transactionInTodayDollar + transactionOutTodayDollar) > 0 ? 'text-emerald-300' : 'text-rose-300'" >{{updateResults(transactionInTodayDollar + transactionOutTodayDollar)}}</span>
                                </td>
                              </tr>
                              <!-- دينار -->
                              <tr class="bg-slate-900">
                                <td class="border border-gray-700 p-2 font-bold text-indigo-300">دينار</td>
                                <td class="border border-gray-700 p-2 text-emerald-200 font-semibold">{{updateResults(transactionInTodayDinar)}}</td>
                                <td class="border border-gray-700 p-2 text-rose-200 font-semibold">{{updateResults(transactionOutTodayDinar)}}</td>
                                <td class="border border-gray-700 p-2 font-semibold">
                                  <span :class="(transactionInTodayDinar + transactionOutTodayDinar) > 0 ? 'text-emerald-300' : 'text-rose-300'">{{updateResults(transactionInTodayDinar + transactionOutTodayDinar)}}</span>
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>

            </div>
         
         <div class="overflow-x-auto shadow-lg mt-5 rounded-lg">
              <table class="w-full text-right text-gray-100 dark:text-gray-100 text-center bg-slate-900 rounded-lg overflow-hidden">
                <thead class="uppercase bg-slate-800 text-gray-100 text-center">
                  <tr class="rounded-l-lg mb-2 sm:mb-0">
                    <th className="px-2 py-2" style="width: 180px;">الجهة
                    </th>
                    <th className="px-2 py-2" style="width: 160px;">الحساب المحاسبي</th>
                    <th className="px-2 py-2" style="width: 180px;">التاريخ</th>
                    <th className="px-2 py-2">الوصف</th>
                    <th className="px-2 py-2">ايداع</th>
                    <th className="px-2 py-2">سحب</th>
                    <th className="px-2 py-2" style="width: 200px;">تنفيذ</th>
                    <th
                      scope="col"
                      class="px-1 py-2 text-base print:hidden" style="width: 100px;"
                    >
                      تخزين
                    </th>
                  </tr>
                </thead>
                <tbody>
         
                  <tr
                    v-for="tran in transactions"
                    :key="tran.id"
                    :class="getRowClasses(tran)"
                  >
                    <td class="border border-transparent text-center px-2 py-1 whitespace-nowrap">
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

                    <td class="border border-transparent text-center px-2 py-1 whitespace-nowrap">
                      <span :class="getMoneyAccountBadgeClass(tran)">
                        {{ getMoneyAccountLabel(tran) ?? '—' }}
                      </span>
                    </td>

                    <td class="border border-transparent text-center px-2 py-1 whitespace-nowrap">
                      {{ formatBaghdadTimestamp(tran?.created_at) }}
                    </td>
                    <th class="border border-transparent text-center px-2 py-1 align-top">
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
                    <div v-else class="space-y-1 text-center">
                      <span class="block whitespace-pre-line leading-6 pt-5">{{ tran.description }}</span>
                      <span
                        v-if="tran._descriptionUpdated"
                        class="inline-flex items-center text-xs font-semibold text-green-600"
                      >
                        تم التحديث
                      </span>
                    </div>
                  </th>
                    <td class="border border-transparent text-center px-2 py-1">
                      <span v-if="formatAmount(tran, 'in')" :class="getAmountClass('in')">
                        {{ formatAmount(tran, 'in') }}
                      </span>
                    </td>
                    <td class="border border-transparent text-center px-2 py-1">
                      <span v-if="formatAmount(tran, 'out')" :class="getAmountClass('out')">
                        {{ formatAmount(tran, 'out') }}
                      </span>
                    </td>
                    <td class="border border-transparent text-center px-2 py-1">
                      <div class="action-group">
                        <button
                          class="action-btn action-btn--edit"
                          title="تعديل الوصف"
                          @click="startEditingDescription(tran)"
                          :disabled="isSavingDescription && editingDescriptionId === tran.id"
                        >
                          <edit class="w-4 h-4" />
                        </button>
                        <button
                          v-if="canAssignToWallet(tran)"
                          class="action-btn action-btn--wallet"
                          title="إسناد إلى قاسة"
                          @click="openAssignToWalletModal(tran)"
                        >
                          قاسة
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
                  <td>
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
  display: inline-block;
  padding: 0.25rem 0.6rem;
  border-radius: 9999px;
  font-weight: 600;
  font-size: 0.875rem;
}

.amount-pill--in {
  background-color: rgba(16, 185, 129, 0.15);
  color: #047857;
}

.amount-pill--out {
  background-color: rgba(239, 68, 68, 0.15);
  color: #b91c1c;
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

.action-btn--wallet {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  width: auto;
  min-width: 3rem;
  padding: 0 0.5rem;
  font-size: 0.7rem;
  font-weight: 700;
  box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.35);
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
  background-color: rgba(16, 185, 129, 0.18);
  color: #34d399;
  border-color: rgba(16, 185, 129, 0.35);
}

.money-account-badge--treasury {
  background-color: rgba(245, 158, 11, 0.18);
  color: #fbbf24;
  border-color: rgba(245, 158, 11, 0.35);
}

.money-account-badge--other {
  background-color: rgba(99, 102, 241, 0.18);
  color: #a5b4fc;
  border-color: rgba(99, 102, 241, 0.35);
}

.money-account-badge--none {
  background-color: rgba(148, 163, 184, 0.12);
  color: #94a3b8;
  border-color: rgba(148, 163, 184, 0.25);
}
</style>