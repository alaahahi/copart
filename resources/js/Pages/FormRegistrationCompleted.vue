<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import { Head, Link, useForm } from "@inertiajs/inertia-vue3";
import { ref } from "vue";
import { TailwindPagination } from "laravel-vue-pagination";

const car = ref({});
const type = ref('');
const searchTerm = ref('');


const showReceiveBtn = ref(0);

const getResultsCar = async (page = 1) => {
    const response = await fetch(`/getIndexCar?page=${page}&type=${type.value}`);
    car.value = await response.json();
}
getResultsCar();

const props = defineProps({
  url: String,
  users:Array
});
const getResultsCarSearch = async (q='',page = 1) => {
    const response = await fetch(`/getIndexCarSearch?page=${page}&q=${q}`);
    car.value = await response.json();
    type.value = '';
}
const form = useForm();

let showModal = ref(false);
const receive = async (id) => {
  const response = await fetch(`/receiveCard?id=${id}`);

      getResultsCar();

};


const results = (id) => {
  if(id==0){
        return 'إنتظار تسليم الصندوق';
    }
    if(id==1){
        return 'تم التسليم';
    }
  if (id == 2) {
    return "مكتمل";
  }
};
function sendToCourt(id) {
  showModal.value = id;
}
function method1(id) {
  form.get(route("sentToCourt", id));
  getResultsCar();
  showModal.value = false;
}
</script>

<template>
  <Head title="Dashboard" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight dark:text-gray-200">
           جميع السيارات 
      </h2>
    </template>
    <modal
      :show="showModal ? true : false"
      :data="showModal.toString()"
      @a="method1($event, arg1)"
      @close="showModal = false"
    >
      <template #header>
        <h3 class="text-center dark:text-gray-200">إدارة الاستمارات</h3>
      </template>
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
            <div class="flex flex-row">
              <div class="basis-1/2 px-4">
                <select @change="getResultsCar()" v-model="type" id="default" class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 mb-6 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-red-500 dark:focus:border-red-500">
                  <option value="">الجميع</option>
                  <option value="2">تم بيعها</option>
                  <option value="1">تم بيعها  مع وجود دين</option>
                  <option value="0">في المستودع</option>
                </select>
              </div>
              <div class="basis-1/2 px-4">
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
                        class="w-5 h-5 text-gray-500 dark:text-gray-400"
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
                      v-model="searchTerm"
                      @input="getResultsCarSearch(searchTerm)"
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
                      placeholder=" بحث حسب رقم pin  أو اسم السيارة أو اسم الزبون"
                      required
                    />
                  </div>
                </form>
              </div>
            </div>
            <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                          <table class="w-full text-sm text-right text-gray-500 dark:text-gray-400">
                              <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400" >
                                  <tr>
                                      <th scope="col" class="px-1 py-3 text-base" >
                                          تسلسل
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                          رقم المحرك
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">{{ $t('name') }}</th>
                                      <th scope="col" class="px-1 py-3 text-base">{{ $t('company') }}</th>
                                      <th scope="col" class="px-1 py-3 text-base">{{ $t('color') }}</th>
                                      <th scope="col" class="px-1 py-3 text-base">{{ $t('year') }}</th>
                                      <th scope="col" class="px-1 py-3 text-base">{{ $t('dubai_expenses') }}</th>
                                      <th scope="col" class="px-1 py-3 text-base">{{ $t('erbil_expenses') }}</th>
                                      <th scope="col" class="px-1 py-3 text-base">{{ $t('dubai_shipping') }}</th>
                                      <th scope="col" class="px-1 py-3 text-base">{{ $t('erbil_shipping') }}</th>
                                      <th scope="col" class="px-1 py-3 text-base">{{ $t('purchase_price') }}</th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                           المبلغ متبقي للمورد
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                          مجموع الكلفة
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                          سعر المبيع
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                          الزبون
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                          متبقي
                                      </th>
                                      <th scope="col" class="px-1 py-3 text-base">
                                          الربح
                                      </th>
                                  </tr>
                              </thead>
                              <tbody>
                                <tr v-for="car in car.data" :key="car.id" :class="car.results == 0?'bg-gray-100':car.results == 1 ?'bg-red-100':car.results == 2 ?'bg-green-100':''"  class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.no }}</td>
                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.pin }}</td>
                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.name?.name}}</td>
                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.company?.name}}</td>
                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.color?.name }}</td>
                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.carmodel?.name }}</td>

                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.dubai_exp }}</td>
                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.erbil_exp }}</td>
                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.dubai_shipping }}</td>
                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.erbil_shipping }}</td>
                                    
                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.purchase_price }}</td> 
                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.purchase_price  - car.paid_amount  }}</td>
                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.paid_amount + car.erbil_exp+car.erbil_shipping+car.dubai_exp+car.dubai_shipping }}</td>
                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.pay_price }}</td> 
                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.client?.name }}</td>
                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.results != 0 ? car.pay_price-car.paid_amount_pay :'' }}</td>
                                    <td className="px-4 py-2 border dark:border-gray-900 text-base">{{ car.results != 0  ? car.pay_price -(car.purchase_price + car.erbil_exp+car.erbil_shipping+car.dubai_exp+car.dubai_shipping):''   }}</td>
                                </tr>
                              </tbody>
                          </table>
                        </div>
            <div class="mt-3 text-center" style="direction: ltr;">
              <TailwindPagination
                :data="car"
                @pagination-change-page="getResultsCar"
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