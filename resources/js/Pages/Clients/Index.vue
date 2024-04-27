<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, useForm } from '@inertiajs/inertia-vue3';
import { TailwindPagination } from 'laravel-vue-pagination';
import ModalAddCardUser from "@/Components/ModalAddCardUser.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import axios from 'axios';
import ModalAddClient from "@/Components/ModalAddClient.vue";
import ModalEditClient from "@/Components/ModalEditClient.vue";
import show from "@/Components/icon/show.vue";
import wallet from "@/Components/icon/wallet.vue";
import trash from "@/Components/icon/trash.vue";
import edit from "@/Components/icon/edit.vue";
import ModalDelClient from "@/Components/ModalDelCar.vue";
import InfiniteLoading from "v3-infinite-loading";
import "v3-infinite-loading/lib/style.css";

let showModalEditClient = ref(false);
let showModalAddClient = ref(false);
let showModalDelClient = ref(false);

import { ref, watch } from 'vue'; // Import ref and watch from Vue
import debounce from 'lodash/debounce'; // Import debounce function from Lodash

const laravelData = ref([]);
let formData = ref({});
let user_id = ref(0);
const from = ref(0);
const to = ref(0);
const q = ref('');
const isLoading = ref(0);
let resetData = ref(false);
let page = 1;
let json = ref({});
let  controller = new AbortController(); // Create a new AbortController


const refresh = () => {
  page = 1;
  laravelData.value.length = 0;
  resetData.value = !resetData.value;
};

const getResultsCar = async ($state) => {
  try {
    const response = await axios.get(`api/getIndexClients`, {
      params: {
        limit: 25,
        page: page,
        q: q.value,
        user_id: user_id.value,
        from: from.value,
        to: to.value
      },
      signal: controller.signal // Pass the signal to abort the request if needed
    });

    json.value = response.data;

    if (json.value.data.length < 25) {
      laravelData.value.push(...json.value.data);
      $state.complete();
    } else {
      laravelData.value.push(...json.value.data);
      $state.loaded();
    }

    page++;
  } catch (error) {
    console.error(error);
  }
};

// Function to abort the ongoing request
const abortRequest = () => {
  if (controller) {
    controller.abort(); // Abort previous request if it exists
  }
  controller = new AbortController(); // Create a new AbortController
};

// Watch for changes in q, user_id, from, and to
watch([q, user_id, from, to], () => {
  abortRequest(); // Abort previous request
  debouncedGetResultsCar(); // Call debounced function to fetch new results
});

// Watch for changes in isLoading
watch(isLoading, (newVal) => {
  if (newVal === 1) {
    // Handle loading state
    console.log('Loading data...');
  } else {
    // Handle loaded state
    console.log('Data loaded');
  }
});

const debouncedGetResultsCar = debounce(() => {
  isLoading.value = 1; // Set isLoading to 1 to indicate loading
  refresh(); // 
}, 500);

// Example usage:

 
function openModalAddClient(form = {}) {
  formData.value = form;
  showModalAddClient.value = true;
}
function openModalEditClient(form = {}) {
  formData.value = form;
  showModalEditClient.value = true;
}
function confirmAddClient(V) {
  axios.post('/api/clientsStore',V)
  .then(response => {
      window.location.reload();
  })
  .catch(error => {
    console.error(error);
  })
}
function confirmEditClient(V) {
  axios.post('/api/clientsEdit',V)
  .then(response => {
      window.location.reload();
  })
  .catch(error => {
    console.error(error);
  })
}
function openModalDelClient(form={}) {
  formData.value=form
  showModalDelClient.value = true;
}
function confirmDelClient(V) {
  axios.post('/api/delClient',V)
  .then(response => {
    showModalDelClient.value = false;
    refresh()

  })
  .catch(error => {
    console.error(error);
  })


}


</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
      <ModalEditClient
            :show="showModalAddClient"
            :formData="formData"

            @a="confirmAddClient($event)"
            @close="showModalAddClient = false"
            >
        <template #header>
          </template>
    </ModalEditClient>
    <ModalDelClient
            :show="showModalDelClient ? true : false"
            :formData="formData"
            @a="confirmDelClient($event)"
            @close="showModalDelClient = false"
            >
          <template #header>
            <h2 class=" mb-5 dark:text-white text-center">

          هل متأكد من حذف التاجر
           {{ formData.name  }}
          ؟
          </h2>
          </template>
    </ModalDelClient>
    <ModalEditClient
            :show="showModalEditClient"
            :formData="formData"

            @a="confirmEditClient($event)"
            @close="showModalEditClient = false"
            >
        <template #header>
          </template>
    </ModalEditClient>
            <div class="py-12">
                <div class=" mx-auto sm:px-6 lg:px-8">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6  dark:bg-gray-900">
                            <div class="grid grid-cols-2 md:grid-cols-5 lg:grid-cols-7 gap-2 lg:gap-1">
                        <div>
                          <InputLabel for="from" :value="$t('search')" class="mb-1" />

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
                          <InputLabel for="from" value="تحديد الفئة" class="mb-1" />

                            <select  v-model="q" id="default" class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 mb-6 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-red-500 dark:focus:border-red-500">
                              <option value="0">{{ $t("allOwners") }}</option>
                              <option value="debit">يوجد دين</option>
                            </select>
                        </div>
                        <div class="text-center px-4">
                          <InputLabel for="pay" value="اضافة" class="mb-1" />

                            <button
                                    className="px-6 mb-12 py-2 mt-1 font-bold text-white bg-red-500 rounded"
                                    @click="openModalAddClient()">
                                    {{ $t('addCustomer') }}
                            </button>
                        </div>
                        <div class=" px-4">
                          <div className="mb-4">
                              <InputLabel for="from" :value="$t('from_date')" />
                              <TextInput
                                id="from"
                                type="date"
                                class="mt-1 block w-full"
                                v-model="from"
                                
                              />
                            </div>
                          </div>
                          <div class=" px-4">
                            <div className="mb-4">
                              <InputLabel for="to" :value="$t('to_date')" />
                              <TextInput
                                id="to"
                                type="date"
                                class="mt-1 block w-full"
                                v-model="to"
                              />
                            </div>
                        </div>
                      
                        <div className="mb-4  mr-5 print:hidden" >
                            <InputLabel for="pay" value="طباعة" />
                            <a
                            target="_blank"
                            :href="`api/getIndexClients?from=${from}&to=${to}&print=1&q=${q}`"
                            class="px-6 mb-12 py-2 mt-1 font-bold text-white bg-orange-500 rounded d-block" style="width: 100%;display: block;text-align: center;">
                            <span>طباعة</span>
                          </a>
                        </div>

                      </div>

                 
                      <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                        <table class="w-full text-sm text-right text-gray-500 dark:text-gray-200 dark:text-gray-400 text-center">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 text-center" >
                        <tr  class="rounded-l-lg mb-2 sm:mb-0">
                                        <th className="px-1 py-2 text-base">#</th>
                                        <th className="px-1 py-2 text-base">{{ $t('name') }}</th>
                                        <th className="px-1 py-2 text-base">{{ $t('phoneNumber') }}</th>
                                        <th className="px-1 py-2 text-base">السيارات</th>

                                        <th className="px-1 py-2 text-base"> السيارات غير مدفوع</th>
                                        <th className="px-1 py-2 text-base"> السيارات مدفوع</th>
                                        <th className="px-1 py-2 text-base">العقود الالكترونية المنجزة</th>
                                        <th className="px-1 py-2 text-base">العقود الالكترونية غير المنجزة</th>
                                        <th className="px-1 py-2 text-base">{{ $t('debt') }}</th>
                                        <th className="px-1 py-2 text-base">{{ $t('execute') }}</th>       
                                    </tr>
                                </thead>
                                <tbody class="flex-1 sm:flex-none dark:bg-gray-700 dark:text-gray-200">
                                
                                    <tr v-for="(user,i) in laravelData" :key="user?.id"  class="border-b border-white dark:bg-gray-900 dark:border-gray-900 hover:bg-gray-50 dark:hover:bg-gray-600 "  :class="user.balance <= 0 ?'bg-green-100 dark:bg-green-900':'bg-red-100 dark:bg-red-900'"  >
                                     <template v-if="user?.id">
                                      <td className="border border-white  dark:border-gray-800 text-center px-4 py-2">{{i}}</td>
                                        <td className="border border-white dark:border-gray-800 text-center  dark:text-gray-200 text-black px-1 py-2 " style="font-weight: bold;font-size: 16px;">{{user.name}}</td>
                                        <td className="border border-white  dark:border-gray-800 text-center px-4 py-2">{{user.phone}}</td>
                                        <td className="border border-white  dark:border-gray-800 text-center px-4 py-2">{{user.car_count}}</td>

                                        <td className="border border-white  dark:border-gray-800 text-center px-4 py-2">{{user.car_count-user.car_count_completed}}</td>
                                        <td className="border border-white  dark:border-gray-800 text-center px-4 py-2">{{user.car_count_completed}}</td>
                                        <td className="border border-white  dark:border-gray-800 text-center px-4 py-2">{{user.contract_count}}</td>
                                        <td className="border border-white  dark:border-gray-800 text-center px-4 py-2">{{user.car_count-user.contract_count}}</td>
                                        <td className="border border-white  dark:border-gray-800 text-center px-4 py-2">{{user.balance}} $</td>
                                        <td className="border border-white  dark:border-gray-800 text-center px-4 py-2"  style="min-height: 42px;">
                          
                                        <Link
                                          style="display:inline-flex;"
                                          v-if="user.car_count"
                                          className="px-1 py-1  text-white mx-1 bg-blue-500 rounded d-inline-block"
                                          :href="route('showClients', user.id)">
                                        <show />
                                        </Link>

                                        <button
                                          tabIndex="1"
                                          
                                          class="px-1 py-1  text-white mx-1 bg-slate-500 rounded"
                                          @click="openModalEditClient(user)"
                                        >
                                        <edit />
                                        </button>

                                        <button
                                          tabIndex="1"
                                          v-if="false"
                                          class="px-1 py-1  text-white mx-1 bg-orange-500 rounded"
                                          @click="openModalDelClient(user)"
                                        >
                                          <trash />
                                        </button>
                                        <Link
                                          style="display:inline-flex;"
                                          className="px-1 py-1  text-white mx-1 bg-purple-900 rounded d-inline-block"
                                          :href="route('wallet',{ 'id':user.id})">
                                        <wallet />
                                        </Link>

    
                                        </td>
                                     </template>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                            <div class="mt-3 text-center" style="direction: ltr;">
                              <div class="spaner">
                                <InfiniteLoading :laravelData="laravelData" @infinite="getResultsCar" :identifier="resetData" />
                              </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
</template>
<style>
.sr-only{
    display: none;
}
</style>