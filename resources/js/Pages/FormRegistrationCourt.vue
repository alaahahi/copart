<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import { Head, Link, useForm } from "@inertiajs/inertia-vue3";
import { ref } from "vue";
import { TailwindPagination } from "laravel-vue-pagination";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import axios from 'axios';
import show from "@/Components/icon/show.vue";
import pay from "@/Components/icon/pay.vue";
import trash from "@/Components/icon/trash.vue";
import edit from "@/Components/icon/edit.vue";
import ModalDelClient from "@/Components/ModalDelCar.vue";

const laravelData = ref({});
const user_id = ref(0);
const isLoading = ref(0);
const from = ref(0);
const to = ref(0);
const formData = ref({});


const showModalDelClient = ref(0);


const getResults = async (v,page = 1) => {
  axios.get(`/api/getIndexAccountsSelas?page=${page}&user_id=${user_id.value}&from=${from.value}&to=${to.value}`)
  .then(response => {
    laravelData.value =  response.data;
  })
  .catch(error => {
    console.error(error);
  })
}

const props = defineProps({
  url: String,
  users:Array
});


function openModalDelClient(form={}) {
  formData.value=form
  showModalDelClient.value = true;
}
function confirmDelClient(V) {
  axios.post('/api/deleteTransactions',V)
  .then(response => {
    showModalDelClient.value = false;
    getResults();

  })
  .catch(error => {
    console.error(error);
  })


}
let showModal = ref(false);



</script>

<template>
  <Head title="Dashboard" />
  <AuthenticatedLayout>
    <ModalDelClient
            :show="showModalDelClient ? true : false"
            :formData="formData"
            @a="confirmDelClient($event)"
            @close="showModalDelClient = false"
            >
          <template #header>
            <h2 class=" mb-5 dark:text-white text-center">

          هل متأكد من حذف العملية رقم
           {{ formData.id  }}
           بقيمة 
           {{ formData.amount }}
          ؟
          </h2>
          </template>
    </ModalDelClient>

    <modal
      :show="showModal ? true : false"
      :data="showModal.toString()"
      @a="method1($event, arg1)"
      @close="showModal = false"
    >
    </modal>
    <div v-if="$page.props.success">
      <div
        id="alert-2"
        class="p-4 mb-4 bg-red-100 rounded-lg dark:bg-red-200 text-center"
        role="alert"
      >
        <div class="ml-3 text-sm font-medium text-red-700 dark:text-red-800">
          {{ $page.props.success }}
        </div>
      </div>
    </div>
    <div class="py-12">
      <div class="max-w-9xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6  dark:bg-gray-900">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2 lg:gap-1">
              <div class=" px-4">
                <InputLabel for="to" class="mb-1" value="كشف حساب" />
                <select @change="getResults()" v-model="user_id" id="default" class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 mb-6 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-red-500 dark:focus:border-red-500">
                  <option value="0" disabled>{{ $t('select_account') }}</option>
                  <option v-for="(user, index) in users" :key="index" :value="user.id">{{ user.name }}</option>
                </select>
              </div>
              <div className="px-4">
                              <InputLabel for="totalAmount" :value="$t('Total_in_dollars')" />
                              <TextInput
                                id="totalAmount"
                                type="text"
                                class="mt-1 block w-full"
                                v-model="laravelData.totalAmount"
                                disabled
                              />
                            </div>
              <div class=" px-4">
                <div className="mb-4 mx-5">
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
                  <div className="mb-4 mx-5">
                    <InputLabel for="to" :value="$t('to_date')" />
                    <TextInput
                      id="to"
                      type="date"
                      class="mt-1 block w-full"
                      v-model="to"
                    />
                  </div>
              </div>
              <div className="mb-4  mr-5 print:hidden">
                  <InputLabel for="pay" value="فلترة" />
                  <button
                  @click.prevent="getResults()"
                  :disabled="isLoading || !parseInt(user_id)"
                  class="px-6 mb-12 py-2 mt-1 font-bold text-white bg-gray-500 rounded" style="width: 100%">
                  <span v-if="!isLoading">فلترة</span>
                  <span v-else>جاري الحفظ...</span>
                </button>
              </div>
              <div className="mb-4  mr-5 print:hidden" >
                  <InputLabel for="pay" value="طباعة" />
                  <button
                  @click.prevent="confirmAddPaymentTotal(total,client_id)"
                  :disabled="isLoading || !parseInt(user_id)"
                  class="px-6 mb-12 py-2 mt-1 font-bold text-white bg-orange-500 rounded" style="width: 100%">
                  <span v-if="!isLoading">طباعة</span>
                  <span v-else>جاري الحفظ...</span>
                </button>
              </div>
            </div>
            <div class="relative overflow-x-auto shadow-md sm:rounded-lg mt-4">
                  <table class="w-full text-sm text-right text-gray-500 dark:text-gray-200 dark:text-gray-400 text-center">
                  <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 text-center" >
                  <tr  class="bg-rose-500 text-gray-100 rounded-l-lg mb-2 sm:mb-0">
                    <th className="px-1 py-2 text-base">#</th>
                    <th className="px-1 py-2 text-base">{{$t('no')}}</th>
                    <th className="px-1 py-2 text-base">{{$t('type')}}</th>
                    <th className="px-1 py-2 text-base">{{$t('date')}}</th>
                    <th className="px-1 py-2 text-base">{{$t('description')}}</th>
                    <th className="px-1 py-2 text-base">{{$t('amount')}}</th>
                    <th
                      scope="col"
                      class="px-1 py-2 text-base print:hidden"
                      style="width: 250px"
                    >
                      {{ $t("execute") }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(user,i) in laravelData.transactions"
                    :key="user.id"  class="text-center">
                  <td className="px-4 py-2 border dark:border-gray-800 dark:text-gray-200">{{i }}</td>
                  <td className="px-4 py-2 border dark:border-gray-800 dark:text-gray-200">{{ user.id }}</td>
                  <td className="px-4 py-2 border dark:border-gray-800 dark:text-gray-200">{{ user.type }}</td>
                  <td className="px-4 py-2 border dark:border-gray-800 dark:text-gray-200">{{ user.created }}</td>
                  <td className="px-4 py-2 border dark:border-gray-800 dark:text-gray-200">{{ user.description }}</td>
                  <td className="px-4 py-2 border dark:border-gray-800 dark:text-gray-200">{{ user.amount  }}</td>
                  <td className="px-4 py-2 border dark:border-gray-800 dark:text-gray-200">  
                    <a v-if="user.type =='out' && user.amount<0" target="_blank"
                    :href="`/api/getIndexAccountsSelas?user_id=${laravelData.client.id}&from=${from}&to=${to}&print=2&transactions_id=${user.id}`"
                    tabIndex="1"
                    class="px-4 py-1  text-white  m-1 bg-green-500 rounded"
                    >
                    طباعة وصل قبض
                    </a>
                    <a v-if="user.type =='in' && user.amount" target="_blank"
                    :href="`/api/getIndexAccountsSelas?user_id=${laravelData.client.id}&from=${from}&to=${to}&print=3&transactions_id=${user.id}`"
                    tabIndex="1"
                    class="px-4 py-1  text-white  m-1 bg-purple-500 rounded"
                    >
                    طباعة وصل دفع
                    </a>
                    <button
                      tabIndex="1"
                      class="px-1 py-1  text-white mx-1 bg-orange-500 rounded"
                      @click="openModalDelClient(user)"
                    >
                      <trash />
                    </button>
                  </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="mt-3 text-center" style="direction: ltr;">
              <TailwindPagination
                :data="laravelData"
                @pagination-change-page="getResults"
                :limit ="10"
              />
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