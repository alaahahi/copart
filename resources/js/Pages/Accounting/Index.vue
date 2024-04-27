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
import ModalAddGenExpenses from "@/Components/ModalAddGenExpenses.vue";
import ModalAddExpensesToMainBransh from "@/Components/ModalAddExpensesToMainBransh.vue";
import ModalExpensesFromOtherBransh from "@/Components/ModalExpensesFromOtherBransh.vue";


import ModalConvertDollarDinar from "@/Components/ModalConvertDollarDinar.vue";
import ModalConvertDinarDollar from "@/Components/ModalConvertDinarDollar.vue";
import ModalDel from "@/Components/ModalDel.vue";
import ModalUploader from "@/Components/ModalUploader.vue";



import axios from 'axios';
import show from "@/Components/icon/show.vue";
import imags from "@/Components/icon/imags.vue";
import trash from "@/Components/icon/trash.vue";
import edit from "@/Components/icon/edit.vue";
import print from "@/Components/icon/print.vue";

import InfiniteLoading from "v3-infinite-loading";
import "v3-infinite-loading/lib/style.css";
import debounce from 'lodash/debounce';


const laravelData = ref({});
const searchTerm = ref('');
let showModalAddSales = ref(false);
let showModaldebtSales = ref(false);
let showModalAddExpenses = ref(false);
let showModalAddGenExpenses = ref(false);
let showModalConvertDollarDinar = ref(false);
let showModalConvertDinarDollar = ref(false);
let showModalAddExpensesToMainBransh = ref(false);
let showModalExpensesFromOtherBransh = ref(false);
let showModalDel = ref(false);
let showModalUploader = ref(false);
let transactions= ref([]);
let expenses_type_id = ref(0);
let tranId =ref({});
let formData = ref({});
let GenExpenses = ref({});
let isLoading=ref(false);
let from = ref(getTodayDate());
let to = ref(getTodayDate());
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
let q = '';
let allTransfers=ref([]);

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
    onlineContracts.value=  response.data.data.onlineContracts
    howler.value=  response.data.data.howler
    shippingCoc.value=  response.data.data.shippingCoc
    border.value=  response.data.data.border
    iran.value=  response.data.data.iran
    dubai.value=  response.data.data.dubai
    debtOnlineContracts.value=  response.data.data.debtOnlineContracts
    onlineContractsDinar.value =response.data.data.onlineContractsDinar
    debtOnlineContractsDinar.value = response.data.data.debtOnlineContractsDinar
    allCars.value =response.data.data.allCars;
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
function openModalAddExpensesToMainBransh() {
  getTransfers();
  showModalAddExpensesToMainBransh.value = true;
}
function openModalExpensesFromOtherBransh() {
  getTransfers();
  showModalExpensesFromOtherBransh.value = true;
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
function openAddGenExpenses(v) {
    expenses_type_id.value=v
    getGenfirmExpenses()
    showModalAddGenExpenses.value = true;
}
function getGenfirmExpenses() {
  axios.get(`/api/getGenExpenses?expenses_type_id=${expenses_type_id.value}`)
  .then(response => {
    GenExpenses.value = response.data;

  })
  .catch(error => {

    errors.value = error.response.data.errors
  })


}

function conGenfirmExpenses(V) {
  axios.post(`/api/GenExpenses?amount=${V.amount??0}&expenses_type_id=${expenses_type_id.value}&factor=${V.factor??1}&note=${V.note??''}`)
  .then(response => {
    refresh();
    showModalAddGenExpenses.value = false;
    console.log(response.data);
    window.open(`/api/getIndexAccountsSelas?user_id=${response.data.morphed_id}&print=3&transactions_id=${response.data.id}`, '_blank');
    window.location.reload();
  })
  .catch(error => {

    errors.value = error.response.data.errors
  })


}
function getTransfers(){
  axios.get(`/api/transfers`)
  .then(response => {
    allTransfers.value = response.data
  })
  .catch(error => {

    errors.value = error.response.data.errors
  })
}
function conAddExpensesToMainBransh(V){
  axios.post(`/api/addTransfers?amount=${V.amount??0}&sender_note=${V.note??''}`)
  .then(response => {
    window.location.reload();

    showModalAddExpensesToMainBransh.value = false;
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
    

    <ModalAddGenExpenses
            :formData="formData"
            :show="showModalAddGenExpenses ? true : false"
            :expenses_type_id="expenses_type_id"
            :GenExpenses="GenExpenses"
            @a="conGenfirmExpenses($event)"
            @close="showModalAddGenExpenses = false"
            >
        <template #header>
          </template>
    </ModalAddGenExpenses>
    <ModalAddExpensesToMainBransh
            :formData="formData"
            :show="showModalAddExpensesToMainBransh ? true : false"
            :expenses_type_id="expenses_type_id"
            :allTransfers="allTransfers"
            @a="conAddExpensesToMainBransh($event)"
            @close="showModalAddExpensesToMainBransh = false"
            >
        <template #header>
          </template>
    </ModalAddExpensesToMainBransh>
    <ModalExpensesFromOtherBransh
      :formData="formData"
            :show="showModalExpensesFromOtherBransh ? true : false"
            :expenses_type_id="expenses_type_id"
            :GenExpenses="GenExpenses"
            :allTransfers="allTransfers"
            @a="conGenfirmExpenses($event)"
            @close="showModalExpensesFromOtherBransh = false">
        <template #header>
          </template>
    </ModalExpensesFromOtherBransh>
    <ModalAddSales
            :show="showModalAddSales ? true : false"
            :data="users"
            :accounts="accounts"
            @a="confirm($event)"
            @close="showModalAddSales = false"
            >
          <template #header>
            <h3 class="text-center">المحاسبة</h3>
            
           </template>
      </ModalAddSales>
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
            <h3 class="text-center">ادخال مصاريف</h3>
            
           </template>
      </ModalAddExpenses>
      <ModalConvertDollarDinar 
            :show="showModalConvertDollarDinar ? true : false"
            :boxes="boxes"
            @a="confirmConvertDollarDinar($event)"
            @close="showModalConvertDollarDinar = false"
            >
          <template #header>
            <h3 class="text-center">تحويل من الدولار الى دينار</h3>
            
           </template>
      </ModalConvertDollarDinar>
      <ModalConvertDinarDollar 
            :show="showModalConvertDinarDollar ? true : false"
            :boxes="boxes"
            @a="confirmConvertDinarDollar ($event)"
            @close="showModalConvertDinarDollar = false"
            >
          <template #header>
            <h3 class="text-center">تحويل من الدينار الى دولار</h3>
            
           </template>
      </ModalConvertDinarDollar>
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
            <div class="mt-4  mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">     
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-orange-100 bg-orange-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                              </svg>
                            </div>
                            <div class="mr-4" >
                              <h2 class="font-semibold ">{{ $t('capital') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{ updateResults(mainAccount) }}</p>
                            </div>
                          </div>
            
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-100 bg-red-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                            <div class="mr-4">
                              <h2 class="font-semibold">تحويلات كركوك</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{howler}}</p>
                            </div>
                          </div>
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-100 bg-red-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                            <div class="mr-4">
                              <h2 class="font-semibold">{{ $t('dubai') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{dubai}}</p>
                            </div>
                          </div>
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-100 bg-red-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                            <div class="mr-4">
                              <h2 class="font-semibold">{{ $t('iran') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{iran}}</p>
                            </div>
                          </div>
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-100 bg-red-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                            <div class="mr-4">
                              <h2 class="font-semibold">{{ $t('border') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{border}}</p>
                            </div>
                          </div>
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-100 bg-red-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                            <div class="mr-4">
                              <h2 class="font-semibold">{{ $t('shipping_coc') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{shippingCoc}}</p>
                            </div>
                          </div>
                         
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-orange-100 bg-orange-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                              </svg>
                            </div>
                            <div class="mr-4" >
                              <h2 class="font-semibold ">{{ $t('online_contracts') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{ onlineContracts }} دولار</p>
                            </div>
                          </div>
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-orange-100 bg-orange-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                              </svg>
                            </div>
                            <div class="mr-4" >
                              <h2 class="font-semibold ">{{ $t('debtOnlineContracts') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{ debtOnlineContracts }} دولار</p>
                            </div>
                          </div>
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-orange-100 bg-orange-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                              </svg>
                            </div>
                            <div class="mr-4" >
                              <h2 class="font-semibold ">{{ $t('online_contracts') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{ onlineContractsDinar }} دينار</p>
                            </div>
                          </div>
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-orange-100 bg-orange-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                              </svg>
                            </div>
                            <div class="mr-4" >
                              <h2 class="font-semibold ">{{ $t('debtOnlineContracts') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{ debtOnlineContractsDinar }} دينار</p>
                            </div>
                          </div>

                           
      
                        </div>

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
                            class="px-6 mb-12 py-2 mt-1 font-bold text-white bg-gray-500 rounded" style="width: 100%">
                            <span v-if="!isLoading">فلترة</span>
                            <span v-else>جاري الحفظ...</span>
                          </button>
              </div>
              <div class=" mr-5 print:hidden" >
                            <InputLabel for="pay" value="طباعة" />
                            <a
                            class="px-6 mb-12 py-2 mt-1 font-bold text-white bg-orange-500 rounded" style="display: block;text-align: center;"
                            :href="`/getIndexAccounting?user_id=${laravelData?.user?.id}&from=${from}&to=${to}&print=5`"
                            target="_blank"
                            >
                            
                            <span v-if="!isLoading">طباعة</span>
                            <span v-else>جاري الحفظ...</span>
                          </a>
              </div>

              <div class="mr-5">
                <InputLabel for="to" value="مصاريف" />
                          <Link
                          v-if="$page.props.auth.user.type_id!=6"
                            type="button"
                           href="/wallet?id=588"
                            style="min-width:150px;"
                            class="px-6 mb-12 py-2 font-bold text-white bg-red-500 rounded  w-full mt-1 text-center">
                              مصاريف الشركة
                          </Link>
              </div>

              
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 lg:gap-3">
              <div>
                          <button
                          v-if="$page.props.auth.user.type_id!=6"
                            type="button"
                            @click="openModalExpensesFromOtherBransh(1)"
                            style="min-width:150px;"
                            className="px-6 mb-12 py-2 font-bold text-white bg-red-500 rounded  w-full">
                              تحويلات كركوك
                          </button>
                          <button
                            v-if="$page.props.auth.user.type_id==6"
                            type="button"
                            @click="openModalAddExpensesToMainBransh(1)"
                            style="min-width:150px;"
                            className="px-6 mb-12 py-2 font-bold text-white bg-red-500 rounded  w-full">
                              تحويل لفرع أربيل
                          </button>
                        </div>
                        <div  v-if="$page.props.auth.user.type_id==1">
                          <button
                            type="button"
                            @click="openAddGenExpenses(2)"
                            style="min-width:150px;"
                            className="px-6 mb-12 text-center py-2 font-bold text-white bg-blue-600 rounded  w-full">
                            {{ $t('dubai') }}
                          </button>
                        </div>
                        <div  v-if="$page.props.auth.user.type_id==1">
                          <button
                            type="button"
                            @click="openAddGenExpenses(3)"
                            style="min-width:150px;"
                            className="px-6 mb-12 text-center w-full py-2 font-bold text-white bg-blue-600 rounded">
                            {{ $t('iran') }}
                          </button>
                        </div>
                       <div  v-if="$page.props.auth.user.type_id==1">
                          <button
                            type="button"
                            @click="openAddGenExpenses(4)"
                            style="min-width:150px;"
                            className="px-6 mb-12 w-full py-2 font-bold text-white bg-indigo-600 rounded">
                            {{ $t('border') }} 
                          </button>
                        </div> 
                        <div  v-if="$page.props.auth.user.type_id==1">
                          <button
                            type="button"
                            @click="openAddGenExpenses(5)"
                            style="min-width:150px;"
                            className="px-6 mb-12 w-full py-2 font-bold text-white bg-pink-600 rounded">
                            {{ $t('shipping_coc') }} 
                          </button>
                        </div>
                        <div>
                          <button
                            type="button"
                            @click="openConvertDollarDinar()"
                            style="min-width:150px;"
                            className="px-6 mb-12 w-full py-2 font-bold text-white bg-teal-500 rounded">
                             تحويل دولار الى دينار  
                          </button>
                        </div>
                        <div>
                          <button
                            type="button"
                            @click="openConvertDinarDollar()"
                            style="min-width:150px;"
                            className="px-6 mb-12 w-full py-2 font-bold text-white bg-yellow-500 rounded">
                             تحويل دينار الى دولار  
                          </button>
                        </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-5 lg:grid-cols-5 gap-3 lg:gap-3">
             


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
            </div>
           

            <div class="overflow-x-auto shadow-md mt-5">
              <table class="w-full text-right text-gray-500   dark:text-gray-400 text-center">
                <thead
                  class="text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 text-center"
                >
                  <tr class="rounded-l-lg mb-2 sm:mb-0">
                    <th className="px-2 py-2" style="width: 100px;">رقم الوصل
                    </th>
                    <!-- <th className="px-2 py-2">الحساب</th> -->
                    <th className="px-2 py-2" style="width: 180px;">التاريخ</th>
                    <th className="px-2 py-2">الوصف</th>
                    <th className="px-2 py-2">المبلغ</th>
                    <th className="px-2 py-2" style="width: 150px;">تنفيذ</th>
                    <th
                      scope="col"
                      class="px-1 py-2 text-base print:hidden" style="width: 100px;"
                    >
                      تخزين
                    </th>
                  </tr>
                </thead>
                <tbody>
         
                  <tr v-for="tran in transactions" :key="tran.id" 
                  :class="{
                    'bg-red-100 dark:bg-red-900': tran.type === 'out' || tran.type === 'outUserBox'|| tran.type === 'outUser'|| tran.type === 'debt',
                    'bg-green-100 dark:bg-green-900': tran.type === 'in' || tran.type === 'inUser'  || tran.type === 'inUserBox' 
                  }"
                  class="bg-white border-b dark:bg-gray-900 dark:border-gray-900 hover:bg-gray-50 dark:hover:bg-gray-600">
                  <td className="border dark:border-gray-800 text-center px-2 py-1">{{ tran.id }}</td>
                  <!-- <td className="border dark:border-gray-800 text-center px-2 py-1">{{ tran.morphed?.name }}</td> -->

                  
                  <td className="border dark:border-gray-800 text-center px-2 py-1">{{ tran?.created_at.slice(0, 19).replace("T", "  ") }}</td>
                  <th className="border dark:border-gray-800 text-center px-2 py-1">{{ tran.description }}</th>
                  <td className="border dark:border-gray-800 text-center px-2 py-1">{{ updateResults(tran.amount)+' '+tran.currency  }}</td>
                  <td className="border dark:border-gray-800 text-center px-2 py-1">
                    <button class="px-1 py-1 text-white bg-rose-500 rounded-md focus:outline-none" @click="openModalDel(tran)" >
                      <trash />
                    </button>

                    <button class="px-1 mx-2 py-1 text-white bg-purple-600 rounded-md focus:outline-none" @click="openModalUploader(tran)" >
                      <imags />
                    </button>
                      <a  target="_blank"
                      v-if="tran.type === 'out' || tran.type === 'outUser'|| tran.type === 'debt'"
                      style="display: inline-flex;"
                      :href="`/api/getIndexAccountsSelas?user_id=${boxes[0].id}&print=2&transactions_id=${tran.id}`"
                      tabIndex="1"
                      class="px-1 py-1  text-white  m-1 bg-green-500 rounded"
                      >
                      <print class="inline-flex" />
                      </a>
                      <a  target="_blank"
                      v-if="tran.type === 'in' || tran.type === 'inUser' "
                      style="display: inline-flex;"
                      :href="`/api/getIndexAccountsSelas?user_id=${boxes[0].id}&print=3&transactions_id=${tran.id}`"
                      tabIndex="1"
                      class="px-1 py-1  text-white  m-1 bg-green-500 rounded"
                      >
                      <print class="inline-flex" />
                      </a>
                  </td>
                  <td>
                    <a
                      v-for="(image, index) in tran.transactions_images"
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
</style>