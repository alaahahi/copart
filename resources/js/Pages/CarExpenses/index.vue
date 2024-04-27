<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/inertia-vue3';
import VueTailwindDatepicker from 'vue-tailwind-datepicker'
import ModalAddCarExpensesFav from "@/Components/ModalAddCarExpensesFav.vue";
import ModalAddCarExpenses from "@/Components/ModalAddCarExpenses.vue";
import ModalArchiveCar from "@/Components/ModalArchiveCar.vue";
import ModalArchiveCarBack from "@/Components/ModalArchiveCarBack.vue";
import ModalDelCar from "@/Components/ModalDelCar.vue";


import { useToast } from "vue-toastification";
import axios from 'axios';
import { ref } from 'vue';
import { useI18n } from "vue-i18n";
import newContracts from "@/Components/icon/new.vue";
import show from "@/Components/icon/show.vue";
import trash from "@/Components/icon/trash.vue";
import print from "@/Components/icon/print.vue";

import InfiniteLoading from "v3-infinite-loading";
import "v3-infinite-loading/lib/style.css";
import debounce from 'lodash/debounce';
const {t} = useI18n();
const props = defineProps({
  client:Array,
});
const formData = ref({});
const toast = useToast();
let searchTerm = ref('');
let showModalAddCarExpensesFav =  ref(false);
let showModalAddCarExpenses =  ref(false);
let showModalArchiveCarExpenses=  ref(false);
let showModalArchiveCarExpensesBack=  ref(false);
let showModalDelCar = ref(false);

let car = ref([]);
function openwModalAddCarExpensesFav(form={}) {
  showModalAddCarExpensesFav.value = true;
}
function openwshowModalAddCarExpenses(form={}) {
  formData.value=form
  showModalAddCarExpenses.value = true;
}
function openwshowModalArchiveCarExpenses(form={}) {
  formData.value=form
  showModalArchiveCarExpenses.value = true;
}
function openwshowModalArchiveCarExpensesBack(form={}) {
  formData.value=form
  showModalArchiveCarExpensesBack.value = true;
}
function openModalDelCar(form={}) {
  formData.value=form
  showModalDelCar.value = true;
}

const currentWork = ref(true);


let resetData = ref(false);
let user_id = 0;
let page = 1;
let q = '';
const refresh = () => {
  page = 0;
  car.value.length = 0;
  resetData.value = !resetData.value;
};
const debouncedGetResultsCar = debounce(refresh, 500); // Adjust the debounce delay (in milliseconds) as needed


const getResultsCar = async ($state) => {
  try {
    const response = await axios.get(`/getIndexCar`, {
      params: {
        limit: 100,
        page: page,
        q: q,
        user_id: user_id,
        car_have_expenses:currentWork.value?1:2
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

 

function confirmExpensesCar(V) { 
  axios.post('/api/confirmExpensesCar',V)
  .then(response => {
    showModalAddCarExpenses.value = false;
    toast.success( "تم إضافة السيارة بنجاح ", {
        timeout: 3000,
        position: "bottom-right",
        rtl: true

      });


      refresh()

  })
  .catch(error => {
    console.error(error);
  })
}


function getTodayDate() {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const day = String(today.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}
function  calculateSum(carexpenses) {
      // Use reduce to sum up carexpenses.amount_dollar
      return carexpenses.reduce((sum, expense) => sum + (expense.amount_dollar || 0), 0);
    }
function  calculateSumDinar(carexpenses) {
      // Use reduce to sum up carexpenses.amount_dollar
      return carexpenses.reduce((sum, expense) => sum + (expense.amount_dinar || 0), 0);
    }

    function confirmCar (car){
  axios.post('/api/addCarFavorite',car)
  .then(response => {
    showModalAddCarExpenses.value = false;
    toast.success( "تم إضافة السيارة بنجاح ", {
        timeout: 3000,
        position: "bottom-right",
        rtl: true

      });
      refresh()
      showModalAddCarExpensesFav.value = false;

  })
  .catch(error => {
    console.error(error);
  })
}

function confirmArchiveCar(car){
  axios.post('/api/confirmArchiveCar',car)
  .then(response => {
    showModalAddCarExpenses.value = false;
    toast.success( "تم نقل السيارة بنجاح ", {
        timeout: 3000,
        position: "bottom-right",
        rtl: true

      });
      refresh()
      showModalArchiveCarExpenses.value = false;

  })
  .catch(error => {
    console.error(error);
  })
}
function confirmArchiveCarBack(car){
  axios.post('/api/confirmArchiveCarBack',car)
  .then(response => {
    showModalArchiveCarExpensesBack.value = false;
    toast.success( "تم نقل السيارة بنجاح ", {
        timeout: 3000,
        position: "bottom-right",
        rtl: true

      });
      refresh()
      showModalArchiveCarExpenses.value = false;

  })
  .catch(error => {
    console.error(error);
  })
}
function swiptab(tab){
  currentWork.value=tab
  refresh()

}


function confirmDelCarFav(V) {
  axios.post('/api/confirmDelCarFav',V)
  .then(response => {
    showModalDelCar.value = false;
    toast.success("حذف السيارة بنجاح", {
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

</script>

<template>
    <Head title="Dashboard" />
    <ModalArchiveCar
            :formData="formData"
            :show="showModalArchiveCarExpenses ? true : false"
            @a="confirmArchiveCar($event)"
            @close="showModalArchiveCarExpenses = false"
            >
        <template #header>
          </template>
    </ModalArchiveCar>
    <ModalArchiveCarBack
            :formData="formData"
            :show="showModalArchiveCarExpensesBack ? true : false"
            @a="confirmArchiveCarBack($event)"
            @close="showModalArchiveCarExpensesBack = false"
            >
        <template #header>
          </template>
    </ModalArchiveCarBack>
    <ModalAddCarExpensesFav
            :formData="formData"
            :show="showModalAddCarExpensesFav ? true : false"
            :client="client"
            @a="confirmCar($event)"
            @close="showModalAddCarExpensesFav = false"
            >
        <template #header>
          </template>
    </ModalAddCarExpensesFav>
    <ModalAddCarExpenses
            :formData="formData"
            :show="showModalAddCarExpenses ? true : false"
            :currentWork="currentWork"
            @a="confirmExpensesCar($event)"
            @close="showModalAddCarExpenses = false"
            >
        <template #header>
          </template>
    </ModalAddCarExpenses>
    <ModalDelCar
      :show="showModalDelCar ? true : false"
      :formData="formData"
      @a="confirmDelCarFav($event)"
      @close="showModalDelCar = false"
    >
      <template #header>
        <h2 class="mb-5 dark:text-gray-400 text-center">
          هل متأكد من حذف السيارة ؟
        </h2>
      </template>
    </ModalDelCar>

    <AuthenticatedLayout>
        <div class="py-2" v-if="$page.props.auth.user.type_id==1||$page.props.auth.user.type_id==7">
          <ul class="sm:px-6 lg:px-8 text-sm font-medium text-center text-gray-500 rounded-lg  flex dark:divide-gray-700 dark:text-gray-400">
            <li class="w-full">
                <button @click="swiptab(true)" class="inline-block w-full p-4 border-r border-gray-200 dark:border-gray-700 hover:text-gray-700 hover:bg-gray-50  focus:outline-none  dark:text-white" :class="!currentWork ? 'dark:bg-gray-800 dark:hover:bg-gray-700':'bg-white  dark:bg-gray-900'" >قيد العمل </button>
            </li>
            <li class="w-full">
                <button @click="swiptab(false)" class="inline-block w-full p-4 border-r border-gray-200 dark:border-gray-700 hover:text-gray-700 hover:bg-gray-50  focus:outline-none  dark:text-white" :class="currentWork ? 'dark:bg-gray-800 dark:hover:bg-gray-700':'bg-white  dark:bg-gray-900'" >السيارة المكتملة</button>
            </li>
     
          </ul>

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
                        <div class="text-center">
                            <button
                              type="button"
                              @click="openwModalAddCarExpensesFav()"
                              style="min-width:150px;"
                              className="px-6 mb-12 mx-2 py-2 font-bold text-white bg-green-500 rounded">
                              {{ $t('addCar') }} 
                            </button>
                          </div>
                        <!-- <div>
                          <button
                            type="button"
                            @click="openAddGenExpenses()"
                            style="min-width:150px;"
                            className="px-6 mb-12 mx-2 py-2 font-bold text-white bg-red-500 rounded">
                               {{ $t('genExpenses') }}
                          </button>
                        </div> -->
                        <!-- <div>
                          <button
                            type="button"
                            @click="openAddCar()"
                            style="min-width:150px;"
                            className="px-6 mb-12 mx-2 py-2 font-bold text-white bg-green-500 rounded">
                            {{ $t('addCar') }} 
                          </button>
                        </div> -->
                        <!-- <div>
                          <a
                            type="button"
                            :href="route('FormRegistrationCompleted')"
                            style="min-width:150px;"
                            className="px-6 mb-12 text-center mx-2 py-2 font-bold text-white bg-blue-600 rounded">
                            {{ $t('allCars') }}
                          </a>
                        </div> -->
                        <!-- <div>
                          <button
                            type="button"
                            @click="openAddTransfers()"
                            style="min-width:150px;"
                            className="px-6 mb-12 mx-2 py-2 font-bold text-white bg-indigo-600 rounded">
                            {{ $t('transfers') }} 
                          </button>
                        </div> -->
                        <div>
                            <select @change="refresh()" v-model="user_id" id="default" class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 mb-6 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-red-500 dark:focus:border-red-500">
                              <option value="0" disabled> {{ $t("selectCustomer") }}</option>
                              <option value="">{{ $t("allOwners") }}</option>
                              <option v-for="(user, index) in client" :key="index" :value="user.id">{{ user.name }}</option>
                            </select>
                        </div>
                        <!--         
                        <div class="text-center">
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
                        <div class="overflow-x-auto shadow-md sm:rounded-lg mt-4 mb-5">
                        <table class="w-full text-sm text-right text-gray-500 dark:text-gray-200 dark:text-gray-400 text-center divide-y divide-gray-200 dark:divide-gray-800">
                          <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 text-center">
                            <tr class="bg-emerald-600 text-gray-100">
                                      <th scope="col" class="px-3 py-2 sm:px-4 sm:py-2	" >
                                        {{ $t('no') }}  
                                      </th>
                                      <th scope="col" class="px-3 py-2 sm:px-4 sm:py-2	">
                                        {{ $t('car_owner') }}
                                      </th>
                                      <th scope="col" class="px-3 py-2 sm:px-4 sm:py-2">
                                        {{ $t('car_type') }}
                                      </th>
                                      <th scope="col" class="px-3 py-2 sm:px-4 sm:py-2">
                                        {{ $t('year') }}
                                      </th>
                                      <th scope="col" class="px-3 py-2 sm:px-4 sm:py-2">
                                        {{ $t('color') }}
                                      </th>
                                      <th scope="col" class="px-3 py-2 sm:px-4 sm:py-2">
                                        {{ $t('vin') }}
                                      </th>
                                      <th scope="col" class="px-3 py-2 sm:px-4 sm:py-2">
                                        {{ $t('car_number') }}
                                      </th>
                                      <th scope="col" class="px-3 py-2 sm:px-4 sm:py-2">
                                        مدفوع دولار
                                      </th>
                                      <th scope="col" class="px-3 py-2 sm:px-4 sm:py-2">
                                        مدفوع دينار
                                      </th>
                                      <th scope="col" class="px-3 py-2 sm:px-4 sm:py-2" style="width: 240px;">
                                        {{ $t('execute') }}
                                      </th>
                                  </tr>
                              </thead>
                              <tbody>


                                <tr v-for="car in car" :key="car.id" :class="car.results == 0 ?'':car.results == 1 ?'bg-red-100 dark:bg-red-900':'bg-green-100 dark:bg-green-900'"  class="bg-white border-b dark:bg-gray-900 dark:border-gray-900 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <td className="px-3 py-2 sm:px-4 sm:py-2 text-center">{{ car.no }}</td>
                                    <td className="px-3 py-2 sm:px-4 sm:py-2 text-center">{{ car.client?.name }}</td>
                                    <td className="px-3 py-2 sm:px-4 sm:py-2 text-center">{{ car.car_type}}</td>
                                    <td className="px-3 py-2 sm:px-4 sm:py-2 text-center">{{ car.year}}</td>
                                    <td className="px-3 py-2 sm:px-4 sm:py-2 text-center">{{ car.car_color }}</td>
                                    <td className="px-3 py-2 sm:px-4 sm:py-2 text-center">{{ car.vin }}</td>
                                    <td className="px-3 py-2 sm:px-4 sm:py-2 text-center">{{ car.car_number }}</td> 
                                    <td className="px-3 py-2 sm:px-4 sm:py-2 text-center">{{ calculateSum(car.carexpenses) }}</td>
                                    <td className="px-3 py-2 sm:px-4 sm:py-2 text-center">{{ calculateSumDinar(car.carexpenses) }}</td>
                                    <td className="px-3 py-2 sm:px-4 sm:py-2 text-center">
                                    <button
                                      tabIndex="1"
                                      class="px-2 py-1  text-white mx-1 bg-emerald-500 rounded"
                                      @click="openwshowModalAddCarExpenses(car)"
                                    >
                                     <newContracts v-if="currentWork" />
                                     <show v-else />
                                    </button>
                                    <button
                                    v-if="currentWork"
                                      tabIndex="1"
                                      class="px-2 py-1  text-white mx-1 bg-pink-600 rounded mt-3 sm:mt-0"
                                      @click="openwshowModalArchiveCarExpenses(car)"
                                    >
                                    <svg class="w-6 h-6 text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                      <path stroke="currentColor" stroke-linejoin="round" stroke-width="2" d="M8 8v1h4V8m4 7H4a1 1 0 0 1-1-1V5h14v9a1 1 0 0 1-1 1ZM2 1h16a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1Z"></path>
                                    </svg>
                                    </button>
                                    <button
                                    v-if="!currentWork"
                                      tabIndex="1"
                                      class="px-2 py-1  text-white mx-1 bg-pink-600 rounded mt-3 sm:mt-0"
                                      @click="openwshowModalArchiveCarExpensesBack(car)"
                                    >
                                    <svg class="w-6 h-6 text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 16 16">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h11m0 0-4-4m4 4-4 4m-5 3H3a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h3"></path>
                                    </svg>
                                    </button>
                                    <button
                                    v-if="currentWork"
                                      tabIndex="1"
                                      
                                      class="px-2 py-1  text-white mx-1 bg-orange-500 rounded  mt-3 sm:mt-0"
                                      @click="openModalDelCar(car)"
                                    >
                                      <trash />
                                    </button>
                                    <a  target="_blank"
                                      style="display: inline-flex;"
                                      :href="`/api/getIndexExpensesPrint?car_id=${car.id}`"
                                      tabIndex="1"
                                      class="px-2 py-1  text-white  m-1 bg-blue-500 rounded"
                                      >
                                      <print />
                                      </a>

                                    </td> 
                                </tr>
                              </tbody>
                          </table>
                        </div>
                        <div class="spaner">
                          <InfiniteLoading :car="car" @infinite="getResultsCar" :identifier="resetData" />

                      </div>
                      </div>

                      </div>
                    </div>
                    </div>
                </div>
            </div>
        <div >
  
    </div>   
    </AuthenticatedLayout>
</template>