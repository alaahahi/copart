<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import { Head, Link, useForm } from "@inertiajs/inertia-vue3";
import { ref } from "vue";
import { TailwindPagination } from "laravel-vue-pagination";

const laravelData = ref({});
const user_id = ref(0);
const searchTerm = ref('');
const showReceiveBtn = ref(0);
const getResults = async (page = 1) => {
  searchTerm.value = '';
  const response = await fetch(
    `/getIndexAppointment?page=${page}&user_id=${user_id.value}`
  );
  laravelData.value = await response.json();
};

getResults();

const props = defineProps({
  url: String,
  users:Array
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
      getResults();

};
const cancel = async (id) => {
  const response = await fetch(`/appointmentCancel?id=${id}`);
      getResults();

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
  getResults();
  showModal.value = false;
}

</script>

<template>
  <Head title="Dashboard" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight dark:text-gray-200">
         المواعد المحجوزة
      </h2>
    </template>
    <modal
      :show="showModal ? true : false"
      :data="showModal.toString()"
      @a="method1($event, arg1)"
      @close="showModal = false"
    >
      <template #header>
        <h3 class="text-center dark:text-gray-200">سجل الحجوزات</h3>
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

            <div className="flex items-center justify-between mb-6">
              <Link  v-if="$page.props.auth.user.type_id==1 || $page.props.auth.user.type_id==6"
                                            className="px-6 py-2 text-white bg-rose-500 rounded-md focus:outline-none"
                                            :href="route('hospitalAdd')">
                                            إنشاء حجز جديدة
              </Link>
             </div>
            </div>
             </div>
            <div class="flex flex-row">
              <div class="basis-1/2 px-4">
                <select @change="getResults()" v-model="user_id" id="default" class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 mb-6 text-sm rounded-lg focus:ring-red-500 focus:border-red-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-red-500 dark:focus:border-red-500">
                  <option value="0">الجميع</option>
                  <option v-for="(user, index) in users" :key="index" :value="user.id">{{ user.name }}</option>
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
                      @input="search(searchTerm)"
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
                      placeholder="بحث حسب رقم البطاقة "
                      required
                    />
                  </div>
                </form>
              </div>
            </div>

            <div class="overflow-x-auto shadow-md">
              <table class="w-full my-5">
                <thead
                  class="700 bg-rose-500 text-white text-center rounded-l-lg"
                >
                  <tr class="bg-rose-500 rounded-l-lg mb-2 sm:mb-0">
                    <th className="px-4 py-2 border dark:border-gray-900">التسلسل</th>
                    <th className="px-4 py-2 border dark:border-gray-900">الطبيب</th>
                    <th className="px-4 py-2 border dark:border-gray-900">رقم البطاقة</th>
                    <th className="px-4 py-2 border dark:border-gray-900">التاريخ والساعة</th>
                    <th className="px-4 py-2 border dark:border-gray-900">الحالة</th>
                    <th className="px-4 py-2 border dark:border-gray-900">تنفيذ</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="user in laravelData.data"
                    :key="user.id"
                    class="hover:bg-gray-100 text-center"
                  >
                  <td className="px-4 py-2 border dark:border-gray-900">{{ user.id }}</td>
                  <td className="px-4 py-2 border dark:border-gray-900">{{ user?.user?.name }}</td>
                  <td className="px-4 py-2 border dark:border-gray-900">{{ user.card_id }}</td>
                  <td className="px-4 py-2 border dark:border-gray-900">{{ user.start }}</td>
                  <th className="px-4 py-2 border dark:border-gray-900">{{ user.is_come==2 ? 'تم التأكيد':user.is_come==0 ? 'تم الإلغاء' : 'في الانتظار' }}</th>
                  <td className="px-4 py-2 border dark:border-gray-900">
                    <button 
                      @click="come(user.id)"
                      tabIndex="-1"
                      type="button"
                      className="mx-1 px-2 py-1 text-sm text-white bg-green-500 rounded"
                      v-if="user.is_come==1">
                      تأكيد الموعد
                    </button>
                    <button 
                      @click="cancel(user.id)"
                      tabIndex="-1"
                      type="button"
                      className="mx-1 px-2 py-1 my-1 text-sm text-white bg-red-500 rounded"
                      v-if="user.is_come==1">
                      إلغاء الموعد
                    </button>
                    <a 
                      v-if="user.is_come==1 || user.is_come==0"
                      tabIndex="-1"
                      type="button"
                      :href="route('hospitalEdit', user.id)"
                      className="mx-1 px-2 py-1 text-sm text-white bg-blue-500 rounded"
                      >
                      تعديل الموعد
                    </a>
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