<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import { Head, Link, useForm } from "@inertiajs/inertia-vue3";
import { ref } from "vue";
import ModalAddSales from "@/Components/ModalAddSales.vue";
 import ModalAddExpensesWallet from "@/Components/ModalAddExpensesWallet.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import ModalAddGenExpenses from "@/Components/ModalAddGenExpenses.vue";
import ModalConvertDollarDinar from "@/Components/ModalConvertDollarDinar.vue";
import ModalConvertDinarDollar from "@/Components/ModalConvertDinarDollar.vue";
import ModalDel from "@/Components/ModalDel.vue";


import axios from 'axios';
import show from "@/Components/icon/show.vue";
import pay from "@/Components/icon/pay.vue";
import trash from "@/Components/icon/trash.vue";
import edit from "@/Components/icon/edit.vue";

import InfiniteLoading from "v3-infinite-loading";
import "v3-infinite-loading/lib/style.css";
import debounce from 'lodash/debounce';


const laravelData = ref({});
const searchTerm = ref('');
let showModalAddSales = ref(false);
let showModaldebtSales = ref(false);
let showModalAddExpensesWallet = ref(false);
let showModalAddGenExpenses = ref(false);
let showModalConvertDollarDinar = ref(false);
let showModalConvertDinarDollar = ref(false);
let showModalDel = ref(false);
let transactions= ref([]);
let expenses_type_id = ref(0);
let tranId =ref({});
let formData = ref({});
let GenExpenses = ref({});
let isLoading=ref(false);
let from = ref('');
let to = ref('');
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
        user_id: props.boxes.id,
        from:from.value,
        to: to.value,
        type: 'wallet'
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
 

function openAddSales() {
  showModalAddSales.value = true;
}
function opendebtSales() {
  showModaldebtSales.value = true;
}
function openAddExpenses(){
  showModalAddExpensesWallet.value = true;
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

const props = defineProps({
  url: String,
  boxes:Object,
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
  V.id=props.boxes.id;
  axios.post('/api/receiptArrivedUser',V)
  .then(response => {
    showModalAddSales.value=false;
    window.location.reload();

  })
  .catch(error => {

    errors.value = error.response.data.errors
  })
}
function confirmdebt(V) {
  axios.post('/api/salesDebtUser',V)
  .then(response => {
    showModaldebtSales.value=false;
    showModalAddExpensesWallet.value = false;
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

function updateResults(input) {
  // Ensure the input is a number
  if (typeof input !== 'number') {
    // Try converting the input to a number
    input = parseFloat(input) || 0;
  }
  
  // Use toLocaleString to format the number with commas
  return input.toLocaleString();
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

    <ModalAddSales
            :show="showModalAddSales ? true : false"
            @a="confirm($event)"
            @close="showModalAddSales = false"
            >
          <template #header>
            <h3 class="text-center">المحاسبة</h3>
            
           </template>
      </ModalAddSales>
 
      <ModalAddExpensesWallet 
            :show="showModalAddExpensesWallet ? true : false"
            :boxes="boxes"
            :sum_transactions="laravelData.sum_transactions"
            :sum_transactions_dinar="laravelData.sum_transactions_dinar"
            @a="confirmdebt($event)"
            @close="showModalAddExpensesWallet = false"
            >
          <template #header>
            <h3 class="text-center"> سحب  من القاسه</h3>
            
           </template>
      </ModalAddExpensesWallet>
      <ModalConvertDollarDinar 
            :show="showModalConvertDollarDinar ? true : false"
            :boxes="boxes"
            @a="confirmConvertDollarDinar($event)"
            @close="showModalConvertDollarDinar = false"
            >
          <template #header>
            <h3 class="text-center">تحويل من الدولار للدينار</h3>
            
           </template>
      </ModalConvertDollarDinar>
      <ModalConvertDinarDollar 
            :show="showModalConvertDinarDollar ? true : false"
            :boxes="boxes"
            @a="confirmConvertDinarDollar ($event)"
            @close="showModalConvertDinarDollar = false"
            >
          <template #header>
            <h3 class="text-center">تحويل من الدينار للدولار</h3>
            
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
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 lg:gap-3">
              <div class="pt-5  print:hidden">
              <button style=" width: 100%; margin-top: 4px;" v-if="$page.props.auth.user.type_id==1 || $page.props.auth.user.type_id==2 || $page.props.auth.user.type_id==5" className="px-4 py-2 text-white bg-green-800 rounded-md focus:outline-none"
                                            @click="openAddSales()">
                                            وصل قبض
                                            (أضافة)
              </button>
              </div>

              <div class="pt-5  print:hidden">
              <button  style=" width: 100%; margin-top: 4px;"  v-if="$page.props.auth.user.type_id==1 || $page.props.auth.user.type_id==2|| $page.props.auth.user.type_id==5" className="px-4 py-2 text-white bg-red-800 rounded-md focus:outline-none"
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
                            @click.prevent="refresh()"
                            class="px-6 mb-12 py-2 mt-1 font-bold text-white bg-gray-500 rounded" style="width: 100%">
                            <span v-if="!isLoading">فلترة</span>
                            <span v-else>جاري الحفظ...</span>
                          </button>
              </div>
              <div className=" mr-5 print:hidden" >
                            <InputLabel for="pay" value="طباعة" />
                            <a
                            class="px-6 mb-12 py-2 mt-1 font-bold text-white bg-orange-500 rounded" style="display: block;text-align: center;"
                            :href="`/getIndexAccounting?user_id=${laravelData?.user?.id}&from=${from}&to=${to}&print=1`"
                            target="_blank"
                            >
                            
                            <span v-if="!isLoading">طباعة</span>
                            <span v-else>جاري الحفظ...</span>
                          </a>
              </div>

              
            </div>
            <div class="grid grid-cols-4 md:grid-cols-4 lg:grid-cols-7 gap-3 lg:gap-3" v-if="false">
                        <div>
                          <button
                            type="button"
                            @click="openConvertDollarDinar()"
                            style="min-width:150px;"
                            className="px-6 mb-12 w-full py-2 font-bold text-white bg-teal-500 rounded">
                             تحويل دولار دينار  
                          </button>
                        </div>
                        <div>
                          <button
                            type="button"
                            @click="openConvertDinarDollar()"
                            style="min-width:150px;"
                            className="px-6 mb-12 w-full py-2 font-bold text-white bg-yellow-500 rounded">
                             تحويل دينار دولار  
                          </button>
                        </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-5 lg:grid-cols-5 gap-3 lg:gap-3">
              <div class=" px-4">
                            <div >
                              <InputLabel for="to" :value="`حساب ${boxes.name} بالدولار`" />
                              <TextInput
                                id="to"
                                type="number"
                                disabled
                                class="mt-1 block w-full"
                                :value="laravelData.sumInTransactionsUser-laravelData.sumOutTransactionsUser"
                              />
                            </div>
              </div>


              <div class=" px-4">
                            <div >
                              <InputLabel for="to" :value="`حساب ${boxes.name} بالدينار العراقي`" />
                              <TextInput
                                id="to"
                                type="number"
                                disabled
                                class="mt-1 block w-full"
                                :value="laravelData.sumInTransactionsDinarUser-laravelData.sumOutTransactionsDinarUser"
                              />
                            </div>
              </div>
      
            </div>
          


            <div class="overflow-x-auto shadow-md mt-5">
              <table class="w-full text-right text-gray-500   dark:text-gray-400 text-center">
                <thead
                  class="text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 text-center"
                >
                  <tr class="rounded-l-lg mb-2 sm:mb-0">
                    <th className="px-2 py-2">رقم الوصل</th>
                    <!-- <th className="px-2 py-2">الحساب</th> -->
                    <th className="px-2 py-2">التاريخ</th>
                    <th className="px-2 py-2">الوصف</th>
                    <th className="px-2 py-2">المبلغ</th>
                    <th className="px-2 py-2">تنفيذ</th>

                  </tr>
                </thead>
                <tbody>
         
                  <tr v-for="tran in   transactions" :key="tran.id" :class="tran.type != 'inUser' ? 'bg-red-100 dark:bg-red-900':'bg-green-100 dark:bg-green-900'"  class="bg-white border-b dark:bg-gray-900 dark:border-gray-900 hover:bg-gray-50 dark:hover:bg-gray-600">

                  <td className="border dark:border-gray-800 text-center px-2 py-1">{{ tran.id }}</td>
                  <!-- <td className="border dark:border-gray-800 text-center px-2 py-1">{{ tran.morphed?.name }}</td> -->

                  
                  <td className="border dark:border-gray-800 text-center px-2 py-1">{{ tran?.created_at.slice(0, 19).replace("T", "  ") }}</td>
                  <th className="border dark:border-gray-800 text-center px-2 py-1">{{ tran.description }}</th>
                  <td className="border dark:border-gray-800 text-center px-2 py-1">{{ tran.amount+' '+tran.currency  }}</td>
                  <td className="border dark:border-gray-800 text-center px-2 py-1">
                 
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