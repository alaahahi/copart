<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/inertia-vue3';

import { Link } from '@inertiajs/inertia-vue3';
import show from "@/Components/icon/show.vue";
import pay from "@/Components/icon/pay.vue";
import trash from "@/Components/icon/trash.vue";
import edit from "@/Components/icon/edit.vue";

import ModalAddCarPayment from "@/Components/ModalAddCarPayment.vue";
import ModalDelCar from "@/Components/ModalDelCar.vue";
import ModalEditCars from "@/Components/ModalEditCar_S.vue";
import InfiniteLoading from "v3-infinite-loading";
import "v3-infinite-loading/lib/style.css";
import debounce from 'lodash/debounce';
import { useToast } from "vue-toastification";
import axios from 'axios';
import { ref } from 'vue';
import { useI18n } from "vue-i18n";
const {t} = useI18n();
const props = defineProps({
  client:Array,
});

const toast = useToast();
let showModal = ref(false);
let showModalCar =  ref(false);
let showModalCarSale =  ref(false);
let showModalAddCarPayment =  ref(false);
let showModalEditCars=ref(false);
let showModalDelCar =  ref(false);
let mainAccount= ref(0)
let allCars= ref(0)

function openModalEditCars(form={}){
  formData.value=form
  if(formData.value.dinar_s==0){
    formData.value.dinar_s=formData.value.dinar
  }
  if(formData.value.expenses_s==0){
    formData.value.expenses_s=formData.value.expenses
  }
  showModalEditCars.value = true;
}
function openModalDelCar(form={}) {
  formData.value=form
  showModalDelCar.value = true;
}


function openAddCarPayment(form={}) {
    formData.value=form
    formData.value.notePayment=' بيد '
    showModalAddCarPayment.value = true;
}
const formData = ref({});
const car = ref([]);

let resetData = ref(false);
let user_id = 0;
let page = 1;
let q = '';
const refresh = () => {
  page = 0;
  car.value.length = 0;
  resetData.value = !resetData.value;


};
const getResultsCar = async ($state) => {
  console.log($state)
  try {


    const response = await axios.get(`/getIndexCar`, {
      params: {
        limit: 100,
        page: page,
        q: q,
        user_id: user_id
      }
    });

    const json = response.data;


    if (json.data.length < 100){
      car.value.push(...json.data);
      $state.complete();
    } 
    else {
      car.value.push(...json.data);
       $state.loaded();
    }


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
  })
  .catch(error => {
    console.error(error);
  })
  
    
}
getcountTotalInfo()
 
 
function confirmUpdateCar(V) {
  showModalEditCars.value = false;

  axios.post('/api/updateCarsS',V)
  .then(response => {
    showModal.value = false;
    toast.success("تم التعديل بنجاح", {
        timeout: 2000,
        position: "bottom-right",
        rtl: true

      });

      getcountTotalInfo()
      refresh();

  })
  .catch(error => {
    showModal.value = false;

    toast.error("لم التعديل بنجاح", {
        timeout: 2000,
        position: "bottom-right",
        rtl: true

      });

  })
}

 


function confirmDelCar(V) {
  axios.post('/api/DelCar',V)
  .then(response => {
    showModalDelCar.value = false;
    toast.success("تم التعديل بنجاح وخصم المبلغ من دين الزبون", {
        timeout: 3000,
        position: "bottom-right",
        rtl: true

      });
    refresh();
  })
  .catch(error => {
    console.error(error);
  })


}


function confirmAddPayment(V) {
  axios.get(`/api/addPaymentCar?car_id=${V.id}&discount=${V.discountPayment??0}&amount=${V.amountPayment??0}&note=${V.notePayment??''}`)
  .then(response => {
    refresh();

    showModalAddCarPayment.value = false;
    toast.success( " تم دفع مبلغ دولار "+V.amountPayment+" بنجاح ", {
        timeout: 3000,
        position: "bottom-right",
        rtl: true

      });
      let transaction=response.data
      window.open(`/api/getIndexAccountsSelas?user_id=${V.client.id}&print=2&transactions_id=${transaction.id}`, '_blank');


  })
  .catch(error => {
    showModal.value = false;

    toast.error("لم التعديل بنجاح", {
        timeout: 2000,
        position: "bottom-right",
        rtl: true

      });

  })
}

const debouncedGetResultsCar = debounce(refresh, 500); // Adjust the debounce delay (in milliseconds) as needed
function getImageUrl(name) {
      // Provide the base URL for your images
      return `/public/uploadsResized/${name}`;
    }
function getDownloadUrl(name) {
      // Provide the base URL for downloading images
      return `/public/uploads/${name}`;
    }
</script>

<template>
    <Head title="Dashboard" />
  
    <ModalEditCars
            :formData="formData"
            :show="showModalEditCars ? true : false"
            :client="client"
            @a="confirmUpdateCar($event)"
            @close="showModalEditCars = false"
            >
        <template #header>
          </template>
    </ModalEditCars>




    <ModalAddCarPayment
            :formData="formData"
            :show="showModalAddCarPayment ? true : false"
            @a="confirmAddPayment($event)"
            @close="showModalAddCarPayment = false"
            >
        <template #header>
          </template>
    </ModalAddCarPayment>

    <ModalDelCar
            :show="showModalDelCar ? true : false"
            :formData="formData"
            @a="confirmDelCar($event)"
            @close="showModalDelCar = false"
            >
          <template #header>
            <h2 class=" mb-5 dark:text-white text-center">

          هل متأكد من حذف السيارة
          ؟
          </h2>
          </template>
    </ModalDelCar>

    <AuthenticatedLayout>
        <div class="py-2" v-if="$page.props.auth.user.type_id==1||$page.props.auth.user.type_id==6">
        <div class="max-w-9xl mx-auto sm:px-6 lg:px-8 ">
            <div class="bg-white overflow-hidden shadow-sm ">
                <div class="p-6  dark:bg-gray-900">
                    <div class="flex flex-col">
                      <div class="grid grid-cols-2 md:grid-cols-5 lg:grid-cols-5 gap-2 lg:gap-1">
                        <div>
                          <form class="flex items-center max-w-5xl">
                            <label  class="dark:text-gray-200" for="simple-search"  ></label>
                            <div class="relative w-full">
                              <div
                                class="
                                  absolute
                                  inset-y-0
                                  left-0
                                  flex
                                  items-center
                                  pl-3
                                  pointer-events-none
                                "
                              >
                                <svg
                                  aria-hidden="true"
                                  class="w-5 h-5 text-gray-500 dark:text-gray-200 dark:text-gray-400"
                                  fill="currentColor"
                                  viewBox="0 0 20 20"
                                  xmlns="http://www.w3.org/2000/svg"
                                >
                                  <path
                                    fill-rule="evenodd"
                                    d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                    clip-rule="evenodd"
                                  ></path>
                                </svg>
                              </div>
                              <input
                                v-model="q"
                                @input="debouncedGetResultsCar"
                                type="text"
                                id="simple-search"
                                class="
                                  bg-gray-50
                                  border border-gray-300
                                  text-gray-900 text-sm
                                  rounded-lg
                                  focus:ring-blue-500 focus:border-blue-500
                                  block
                                  w-full
                                  pl-10
                                  p-2.5
                                  dark:bg-gray-700
                                  dark:border-gray-600
                                  dark:placeholder-gray-400
                                  dark:text-white
                                  dark:focus:ring-blue-500
                                  dark:focus:border-blue-500
                                "
                                placeholder="بحث"
                                required
                              />
                            </div>
                          </form>
                        </div>
        
                        <div>
                            <select @change="refresh()" v-model="user_id" id="default" class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 mb-6 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-red-500 dark:focus:border-red-500">
                              <option value="0" disabled> {{ $t("selectCustomer") }}</option>
                              <option value="">{{ $t("allOwners") }}</option>
                              <option v-for="(user, index) in client" :key="index" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                        <!-- <div class="text-center">
                          <button
                            type="button"
                            @click="openAddToBox()"
                            style="min-width:150px;"
                            className="px-6 mb-12 mx-2 py-2 font-bold text-white bg-purple-600 rounded">
                            {{ $t('addToTheFund') }}  
                          </button>
                        </div>
                        <div  class="text-center">
                          <button
                            type="button"
                            @click="openAddFromBox()"
                            style="min-width:150px;"
                            className="px-6 mb-12 mx-2 py-2 font-bold text-white bg-pink-600 rounded">
                            {{ $t('withdrawFromTheFund') }}   
                          </button>
                        </div> -->

                      </div>
                      <div>
                        <div>
       

                        </div>
                        <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                          <table class="w-full text-sm text-right text-gray-500 dark:text-gray-200 dark:text-gray-400 text-center">
                              <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 text-center" >
                                  <tr>
                                      <th scope="col" class="px-1 py-3 text-base	">
                                        {{ $t('car_owner') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('car_type') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('year') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('color') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('vin') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('car_number') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('dinar') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('dolar_price') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('dolar_custom') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('note') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('shipping_dolar') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('coc_dolar') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('checkout') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('expenses') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('total') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('paid') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('discount') }}
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                        {{ $t('date') }}
                                      </th>
                
                                      <th scope="col" class="px-1 py-3 text-base" style="width: 180px;">
                                        {{ $t('execute') }}
                                      </th>
                                      <th class="px-1 py-3 text-base">تخزين</th>
                                  </tr>
                              </thead>
                              <tbody>


                                <tr v-for="car in car" :key="car.id" :class="car.results == 0 ?'':car.results == 1 ?'bg-red-100 dark:bg-red-900':'bg-green-100 dark:bg-green-900'"  class="bg-white border-b dark:bg-gray-900 dark:border-gray-900 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <td className="border dark:border-gray-800 text-center  dark:text-gray-200 text-black px-1 py-2 " style="font-weight: bold;font-size: 16px;">{{ car.client?.name }}</td>
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ car.car_type}}</td>
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ car.year}}</td>
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ car.car_color }}</td>
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ car.vin }}</td>
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ car.car_number }}</td> 
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ car.dinar_s  }}</td>
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ car.dolar_price_s}}</td>
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ ((car.dinar_s/car.dolar_price_s)*100)?.toFixed(0)||0}}</td> 
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ car.note }}</td>
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ car.shipping_dolar_s}}</td>
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ car.coc_dolar_s  }}</td>
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ car.checkout_s}}</td>
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ car.expenses_s}}</td>
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ (car.total_s).toFixed(0) }}</td>
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ car.paid}}</td>
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ car.discount}}</td>

                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">{{ car.date  }}</td>
                                     <td className="border dark:border-gray-800 text-start px-1 py-2">
                                    <button
                                      tabIndex="1"
                                      
                                      class="px-1 py-1  text-white mx-1 bg-slate-500 rounded"
                                      @click="openModalEditCars(car)"
                                    >
                                     <edit />
                                    </button>
                                    <button
                                      tabIndex="1"
                                      
                                      class="px-1 py-1  text-white mx-1 bg-orange-500 rounded"
                                      @click="openModalDelCar(car)"
                                    >
                                      <trash />
                                    </button>
                                    <button
                                      v-if="car.total_s != (car.paid+ car.discount)"
                                      tabIndex="1"
                                      class="px-1 py-1  text-white mx-1 bg-green-500 rounded"
                                      @click="openAddCarPayment(car)"
                                    >
                                     <pay />
                                    </button>
                                    <Link
                                      style="display:inline-flex;"
                                      className="px-1 py-1  text-white mx-1 bg-blue-500 rounded d-inline-block"
                                      :href="route('showClients',car.client?.id)">
                                    <show />
                                    </Link>
                                    <!-- 
          
                                    <button
                                      tabIndex="1"
                                      class="px-4 py-1  text-white mx-1 bg-purple-500 rounded"
                                      v-if="car.results == 0"
                                      @click="openSaleCar(car)"
                                    >
                                      {{ $t('sell') }}
                                    </button>
                                    <button
                                      tabIndex="1"
                                      class="px-1 py-1  text-white mx-1 bg-blue-600 rounded"
                                      @click="openAddExpenses(car)"
                                    >
                                      {{ $t('expenses') }}
                                    </button>
                                    <button
                                      tabIndex="1"
                                      class="px-1 py-1  text-white mx-1 bg-green-500 rounded"
                                      v-if="car.results != 0 && (car.pay_price - car.paid_amount_pay == 0)"
                                      @click="openAddCarPayment(car)"
                                    >
                                      {{ $t('view_payments') }}
                                    </button>
                                    <button
                                      tabIndex="1"
                                      class="px-1 py-1 text-base text-white mx-1 bg-red-700 rounded"
                                      v-if="car.results == 1 && (car.pay_price - car.paid_amount_pay != 0)"
                                      @click="openAddCarPayment(car)"
                                    >
                                      {{ $t('add_payment') }}
                                    </button>

                                    -->

                                    </td>
                                    <td className="border dark:border-gray-800 text-center px-1 py-2 ">
                                      
                                      <a
                                      v-for="(image, index) in car.car_images"
                                      :key="index"
                                      :href="getDownloadUrl(image.name)"
                                      style="cursor: pointer;"
                                      target="_blank"
                                    >
                                      <img :src="getImageUrl(image.name)" alt="" class="px-1" style="max-width: 100px;max-height: 50px;display: inline;" />
                                    </a>

                                    </td>
                                </tr>
                              </tbody>
                          </table>

                        </div>

                      </div>
                      <div class="spaner">
                        <InfiniteLoading :car="car" @infinite="getResultsCar" :identifier="resetData" />

                      </div>
            
                      <div>
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4 lg:grid-cols-5 xl:grid-cols-7">     
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-orange-100 bg-orange-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                              </svg>
                            </div>
                            <div class="mr-4" >
                              <h2 class="font-semibold ">{{ $t('capital') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{ mainAccount }}</p>
                            </div>
                          </div>
                          <!-- <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-100 bg-red-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                      
                            <div class="mr-4">
                              <h2 class="font-semibold"> {{ $t('fundIncome') }} </h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{ inAccount.wallet?.balance }}</p>
                            </div>
                          </div> -->
                          <!-- <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-100 bg-red-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                      
                            <div class="mr-4">
                              <h2 class="font-semibold">{{ $t('cash_out') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{ outAccount.wallet?.balance }}</p>
                            </div>
                          </div>
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-100 bg-red-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                      
                            <div class="mr-4">
                              <h2 class="font-semibold">{{ $t('debt_to_fund') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{ debtAccount.wallet?.balance }}</p>
                            </div>
                          </div>
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-100 bg-red-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                      
                            <div class="mr-4">
                              <h2 class="font-semibold">{{ $t('transfer') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{ transfersAccount.wallet?.balance }}</p>
                            </div>
                          </div>
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-100 bg-red-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                            <div class="mr-4">
                              <h2 class="font-semibold">{{ $t('total_car_count') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{carCount}}</p>
                            </div>
                          </div> -->
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-100 bg-red-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                            <div class="mr-4">
                              <h2 class="font-semibold">{{ $t('all_cars') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{allCars}}</p>
                            </div>
                          </div>
 
                          <!-- <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-100 bg-red-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                      
                            <div class="mr-4">
                              <h2 class="font-semibold">{{ $t('supplier_debt') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{ debtSupplier.wallet?.balance }}</p>
                            </div>
                          </div>
                          <div class="flex items-start rounded-xl dark:bg-gray-600 dark:text-gray-300 bg-white p-4 shadow-lg">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-100 bg-red-50">
                              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                              </svg>
                            </div>
                      
                            <div class="mr-4">
                              <h2 class="font-semibold">{{ $t('supplier_payments') }}</h2>
                              <p class="mt-2 text-sm text-gray-500 dark:text-gray-200">{{ outSupplier.wallet?.balance }}</p>
                            </div>
                          </div> -->
                        </div>
                      </div>
                      </div>
                    </div>
                    </div>
                </div>
            </div>
        <div >
        <!-- <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="p-6  dark:bg-gray-900" style="border-radius: 8px;">
                  <div class="flex flex-row">
                                    <div class="basis-1/4">
                                      <button
                                        type="button"
                                        @click="getcountComp()"
                                        style="width: 70%;"
                                        className="px-6 mb-12 mx-2 py-2 font-bold text-white bg-rose-500 rounded"
                                      >
                                      فلترة
                                      </button>
                                    </div>
                                    <div class="basis-3/4" style="direction: ltr;">
                                      <vue-tailwind-datepicker overlay :options="options" :disable-date="dDate"  i18n="ar"  as-single use-range v-model="dateValue" />
                                    </div>
                  </div>
                  <div class="flex pt-5 items-center">
                  <div class="mx-auto container align-middle">
                        <div class="grid grid-cols-2 gap-2" style="display: flow-root;">
                          <div class="shadow rounded-lg py-3 px-5 bg-white" >
                            <div class="flex flex-row justify-between items-center">
                              <div>
                                <h6 class="text-2xl">المعاملات المنجزة </h6>
                                <h4 class="text-black text-4xl font-bold text-rigth">{{countComp}}</h4>
                              </div>
                              <div>
                                <svg
                                  xmlns="http://www.w3.org/2000/svg"
                                  class="h-12 w-12"
                                  fill="none"
                                  viewBox="0 0 24 24"
                                  stroke="#14B8A6"
                                  stroke-width="2"
                                >
                                  <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"
                                  />
                                </svg>
                              </div>
                            </div>
                            <div class="text-left flex flex-row justify-start items-center">
                              <span class="mr-1">
                                <svg
                                  xmlns="http://www.w3.org/2000/svg"
                                  class="h-6 w-6"
                                  fill="none"
                                  viewBox="0 0 24 24"
                                  stroke="#14B8A6"
                                  stroke-width="2"
                                >
                                  <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                                  />
                                </svg>
                              </span>
                             
                            </div>
                          </div>
                          <div class="shadow rounded-lg py-3 px-5 bg-white" v-if="false">
                            <div class="flex flex-row justify-between items-center">
                              <div>
                                <h6 class="text-2xl">Serials viewed</h6>
                                <h4 class="text-black text-4xl font-bold text-left">41</h4>
                              </div>
                              <div>
                                <svg
                                  xmlns="http://www.w3.org/2000/svg"
                                  class="h-12 w-12"
                                  fill="none"
                                  viewBox="0 0 24 24"
                                  stroke="#EF4444"
                                  stroke-width="2"
                                >
                                  <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"
                                  />
                                </svg>
                              </div>
                            </div>
                            <div class="text-left flex flex-row justify-start items-center">
                              <span class="mr-1">
                                <svg
                                  xmlns="http://www.w3.org/2000/svg"
                                  class="h-6 w-6"
                                  fill="none"
                                  viewBox="0 0 24 24"
                                  stroke="#EF4444"
                                  stroke-width="{2}"
                                >
                                  <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"
                                  />
                                </svg>
                              </span>
                              <p><span class="text-red-500 font-bold">12%</span> in 7 days</p>
                            </div>
                          </div>
                        </div>
                      </div>
                  </div>
                </div>
                    </div> -->
    </div>   
    </AuthenticatedLayout>
</template>