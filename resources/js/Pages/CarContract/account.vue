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



import axios from 'axios';
import show from "@/Components/icon/show.vue";
import imags from "@/Components/icon/imags.vue";
import trash from "@/Components/icon/trash.vue";
import edit from "@/Components/icon/edit.vue";

import InfiniteLoading from "v3-infinite-loading";
import "v3-infinite-loading/lib/style.css";
import debounce from 'lodash/debounce';

const laravelData = ref({});
const searchTerm = ref('');
let showModalAddSales = ref(false);
let showModaldebtSales = ref(false);
let showModalConvertDollarDinar = ref(false);
let showModalConvertDinarDollar = ref(false);
let showModalAddExpenses = ref(false);


let showModalDel = ref(false);
let showModalUploader = ref(false);
let transactions= ref([]);
let expenses_type_id = ref(0);
let tranId =ref({});
let formData = ref({});
let GenExpenses = ref({});
let isLoading=ref(false);
let from = ref(getFirstDayOfMonth());
let to = ref(getTodayDate());
let allContract= ref(0)
let sum_contract= ref(0)
let sum_contract_debit= ref(0)
let sum_contract_dinar= ref(0)
let sum_contract_debit_dinar= ref(0)
let sumTransactions= ref(0)
let sumTransactionsDinar= ref(0)
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

function openAddExpenses(){
  showModalAddExpenses.value = true;
}

const getResults = async ($state) => {
  try {
    const response = await axios.get(`/api/getListTransactionsContract`, {
      params: {
        limit: 100,
        page: page,
        q: q,
        from:from.value,
        to: to.value
      }
    });

    const json = response.data;


    if (json.data.length < 100){
      transactions.value.push(...json.data);
      $state.complete();
    } 
    else {
      transactions.value.push(...json.data);
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
  axios.get(`/api/totalInfoContract?from=${from.value}&to=${to.value}`)
  .then(response => {
    allContract.value=response.data.contract
    sum_contract.value=response.data.sum_contract

    sum_contract_debit.value=response.data.sum_contract_debit

    sum_contract_dinar.value=response.data.sum_contract_dinar

    sum_contract_debit_dinar.value=response.data.sum_contract_debit_dinar

    sumTransactions.value = response.data.sumTransactions

    sumTransactionsDinar.value=response.data.sumTransactionsDinar

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


 
 
const errors = ref(0);
 
 
 
function confirm(V) {
  axios.post('/api/addToBoxContract',V)
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
  axios.post('/api/DropFromBoxContract',V)
  .then(response => {
    showModaldebtSales.value=false;
    showModalAddExpenses.value = false;
    window.location.reload();

  })
  .catch(error => {

    errors.value = error.response.data.errors
  })
}
function confirmConvertDollarDinar(V) {
  axios.post('/api/convertDollarDinarContract',V)
  .then(response => {
    getcountTotalInfo()
    refresh();
    showModalConvertDollarDinar.value=false;

  })
  .catch(error => {

    errors.value = error.response.data.errors
  })
}
function confirmConvertDinarDollar(V) {
  axios.post('/api/convertDinarDollarContract',V)
  .then(response => {
    getcountTotalInfo()
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
function getFirstDayOfMonth() {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const firstDayOfMonth = '01'; // Set day to 01 for the first day of the month
  return `${year}-${month}-${firstDayOfMonth}`;
}
function delTransactions(id){
  axios.post(`/api/delTransactionsContract?id=${id.id}`)
  .then(response => {
    refresh();
    getcountTotalInfo()
    showModalDel.value=false;
  })
  .catch(error => {

    errors.value = error.response.data.errors
  })
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
function updateResults(input) {
  // Ensure the input is a number
  if (typeof input !== 'number') {
    // Try converting the input to a number
    input = parseFloat(input) || 0;
  }
  
  // Use toLocaleString to format the number with commas
  return input.toLocaleString();
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
            <div class="mt-4  mb-4 grid grid-cols-1 gap-4 sm:grid-cols-4 lg:grid-cols-5 xl:grid-cols-7">
                <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-orange-100 bg-orange-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                              </svg>
                            </div>
                            <div class="mr-4" >
                              <h2 class="font-semibold ">جميع العقود</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{allContract}}</p>
                            </div>
                          </div>
            
                          
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-orange-100 bg-orange-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                              </svg>
                            </div>
                            <div class="mr-4" >
                              <h2 class="font-semibold ">العقود بالدولار</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{sum_contract}}</p>
                            </div>
                          </div>
            
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-100 bg-red-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                            <div class="mr-4">
                              <h2 class="font-semibold">دين العقود بالدولار</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{sum_contract_debit}}</p>
                            </div>
                          </div>
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-100 bg-red-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                            <div class="mr-4">
                              <h2 class="font-semibold">العقود بالدينار</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{sum_contract_dinar}}</p>
                            </div>
                          </div>
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-100 bg-red-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                            <div class="mr-4">
                              <h2 class="font-semibold">دين العقود بالدينار</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{sum_contract_debit_dinar}}</p>
                            </div>
                          </div>
                        </div>

            <div class="grid grid-cols-4 md:grid-cols-4 lg:grid-cols-7 gap-3 lg:gap-3" >
              <div class="pt-5  print:hidden">
              <button style=" width: 100%; margin-top: 4px;" v-if="$page.props.auth.user.type_id==8" className="px-4 py-2 text-white bg-green-500 rounded-md focus:outline-none"
                                            @click="openAddSales()">
                                            وصل قبض
                                            (أضافة)
              </button>
              </div>

    
              
              <div class="pt-5  print:hidden">
              <button  style=" width: 100%; margin-top: 4px;"  v-if="$page.props.auth.user.type_id==8" className="px-4 py-2 text-white bg-rose-500 rounded-md focus:outline-none"
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
              <div className=" mr-5 print:hidden">
                            <InputLabel for="pay" value="فلترة" />
                            <button
                            @click.prevent="refresh();getcountTotalInfo()"
                            class="px-6 mb-12 py-2 mt-1 font-bold text-white bg-gray-500 rounded" style="width: 100%">
                            <span v-if="!isLoading">فلترة</span>
                            <span v-else>جاري الحفظ...</span>
                          </button>
              </div>
              <div className=" mr-5 print:hidden"  >
                            <InputLabel for="pay" value="تقرير مصاريف" />
                            <a
                            class="px-6 mb-12 py-2 mt-1 font-bold text-white bg-orange-500 rounded" style="display: block;text-align: center;"
                            :href="`api/contract_account_report?type=سحب دفعة &from=${from}&to=${to}&print=1`"
                            target="_blank"
                            >
                            
                            <span v-if="!isLoading">طباعة</span>
                            <span v-else>جاري الحفظ...</span>
                          </a>
              </div>
              <div className=" mr-5 print:hidden"  >
                            <InputLabel for="pay" value="تقرير العقود" />
                            <a
                            class="px-6 mb-12 py-2 mt-1 font-bold text-white bg-blue-500 rounded" style="display: block;text-align: center;"
                            :href="`api/contract_account_report?type=contract-report&from=${from}&to=${to}&print=2`"
                            target="_blank"
                            >
                            
                            <span v-if="!isLoading">طباعة</span>
                            <span v-else>جاري الحفظ...</span>
                          </a>
              </div>

              
            </div>
            <div class="grid grid-cols-4 md:grid-cols-4 lg:grid-cols-7 gap-3 lg:gap-3">
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
              <div >
                              <InputLabel for="to" value="رصيد الصندوق بالدولار" />
                              <TextInput
                                id="to"
                                type="number"
                                disabled
                                class="mt-1 block w-full"
                                :value="sumTransactions"
                              />
                </div>
                <div class="relative w-full">
                          <InputLabel for="to" value="بحث رقم الوصل او الوصف" />
                          <TextInput
                                id="q"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="q"
                                @input="debouncedGetResultsCar"                              />
             
                </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-5 lg:grid-cols-5 gap-3 lg:gap-3 pt-3">
              <div >
                              <InputLabel for="to" value="رصيد الصندوق بالدينار العراقي" />
                              <TextInput
                                id="to"
                                type="number"
                                disabled
                                class="mt-1 block w-full"
                                :value="sumTransactionsDinar"
                              />
                </div>
            </div>
            <!-- <div class="flex flex-row">
              <div class="basis-1/2 ">
                <select @change="getResults()" v-model="user_id" id="default" class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 mb-6 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-red-500 dark:focus:border-red-500">
                  <option value="0">الجميع</option>
                  <option v-for="(user, index) in users" :key="index" :value="user.id">{{ user.name }}</option>
                </select>
              </div>
            </div> -->

            <div class="overflow-x-auto shadow-md mt-5">
              <table class="w-full text-right text-gray-500   dark:text-gray-400 text-center">
                <thead
                  class="text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 text-center"
                >
                  <tr class="rounded-l-lg mb-2 sm:mb-0">
                    <th className="px-2 py-2" style="width: 100px;">رقم الوصل</th>
                    <!-- <th className="px-2 py-2">الحساب</th> -->
                    <th className="px-2 py-2" style="width: 180px;">التاريخ</th>
                    <th className="px-2 py-2">الوصف</th>
                    <th className="px-2 py-2">المبلغ</th>
                    <th className="px-2 py-2" style="width: 100px;">تنفيذ</th>
                    <th v-if="false"
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
                    'bg-red-100 dark:bg-red-900': tran.type === 'out' || tran.type === 'outUser'|| tran.type === 'debt',
                    'bg-green-100 dark:bg-green-900': tran.type === 'in' || tran.type === 'inUser' 
                  }"
                  class="bg-white border-b dark:bg-gray-900 dark:border-gray-900 hover:bg-gray-50 dark:hover:bg-gray-600">
                  <td className="border dark:border-gray-800 text-center px-2 py-1">{{ tran.id }}</td>
                  <!-- <td className="border dark:border-gray-800 text-center px-2 py-1">{{ tran.morphed?.name }}</td> -->

                  
                  <td className="border dark:border-gray-800 text-center px-2 py-1">{{ tran?.created_at.slice(0, 19).replace("T", "  ") }}</td>
                  <th className="border dark:border-gray-800 text-center px-2 py-1">{{ tran.description }}</th>
                  <td className="border dark:border-gray-800 text-center px-2 py-1">{{ tran.amount+' '+tran.currency  }}</td>
                  <td className="border dark:border-gray-800 text-center px-2 py-1">
                    <button class="px-1 py-1 text-white bg-rose-500 rounded-md focus:outline-none" @click="openModalDel(tran)" >
                      <trash />
                    </button>

                    <button class="px-1 mx-2 py-1 text-white bg-purple-600 rounded-md focus:outline-none" v-if="false" @click="openModalUploader(tran)" >
                      <imags />
                    </button>
                    
                  </td>
                  <td v-if="false">
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