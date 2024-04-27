<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Head, Link, useForm } from '@inertiajs/inertia-vue3';
import { ref } from 'vue';
import { TailwindPagination } from 'laravel-vue-pagination';
import ModalAddNameCompany from "@/Components/ModalAddNameCompany.vue";
import ModalAddColor from "@/Components/ModalAddColor.vue";
import ModalAddCarModel from "@/Components/ModalAddCarModel.vue";

const laravelData = ref({});
const companyName = ref({});
const companyModel = ref({});
const companyColor = ref({});

const getResults = async (page = 1) => {
    const response = await fetch(`/getIndexCompany?page=${page}`);
    laravelData.value = await response.json();
}
const getResultsName = async (page = 1) => {
    const response = await fetch(`/getIndexName?page=${page}`);
    companyName.value = await response.json();
}
const getResultsModel = async (page = 1) => {
    const response = await fetch(`/getIndexModel?page=${page}`);
    companyModel.value = await response.json();
}
const getResultsColor = async (page = 1) => {
    const response = await fetch(`/getIndexColor?page=${page}`);
    companyColor.value = await response.json();
}
const searchTerm = ref('');
const formData = ref({});
const formDataCarModel = ref({});
const formDataColor = ref({});
getResults();
getResultsName();
getResultsModel();
getResultsColor();

const props = defineProps({
    url:String,
    company:Array,
});
const search =async (q) => {
    laravelData.value=[]
    const response = await fetch(`/livesearch?q=${q}`);
    laravelData.value = await $data;
};
const form = useForm();

const  results = (id) => {
    if(id==0){
        return 'إنتظار تسليم الصندوق';
    }
    if(id==1){
        return 'تم التسليم';
    }
    if(id==2){
        return 'مكتمل';
    }
}
let showModal =  ref(false);
let showModalCarModel =  ref(false);
let showModalColor =  ref(false);

const deleteApi = async (id,name) => {
  try {
    fetch(`/${name}/${id}`);
    getResults();
    getResultsName();
    getResultsModel();
    getResultsColor();
    // Handle success, e.g., display a success message or update component state
  } catch (error) {
    // Handle error, e.g., display an error message or handle error state
    console.error(error);
  }
};
function openAddCarModel(form={}) {
    formDataCarModel.value=form
    showModalCarModel.value = true;
}
function openAddColor(form={}) {
    formDataColor.value=form
    showModalColor.value = true;
}
function openAddNameCompany(form={}) {
    formData.value=form
    showModal.value = true;
}

function confirm(V) {
  fetch(`/addName?company_id=${V.company_id}&name=${V.name}&name_en=${V.name_en}&id=${V.id??0}`)
    .then(() => {
      showModal.value = false;
      getResultsName();
    })
    .catch((error) => {
      console.error(error);
    });
}
function confirmCarModel(V) {
  fetch(`/addCarModel?name=${V.name}&id=${V.id??0}`)
    .then(() => {
    showModalCarModel.value = false;
      getResultsModel();
    })
    .catch((error) => {
      console.error(error);
    });
}
function confirmColor(V) {
  fetch(`/addColor?name=${V.name}&name_en=${V.name_en}&id=${V.id??0}`)
    .then(() => {
      showModalColor.value = false;
      getResultsColor();
    })
    .catch((error) => {
      console.error(error);
    });
}
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight dark:text-gray-200">
                إدارة إعدادات السيارات
            </h2>
        </template>

        <div v-if="$page.props.success">
            <div id="alert-2" class=" p-4 mb-4 bg-red-100 rounded-lg dark:bg-red-200 text-center" role="alert" >
            <div class="ml-3 text-sm font-medium text-red-700 dark:text-red-800">
                {{ $page.props.success }}
            </div>
            </div>
        </div>
        <ModalAddNameCompany
            :show="showModal ? true : false"
            :formData="formData"
            :data="laravelData.data"
            @a="confirm($event)"
            @close="showModal = false"
            >
        <template #header>
    ْ      </template>
        </ModalAddNameCompany>
        <ModalAddCarModel
            :show="showModalCarModel ? true : false"
            :formData="formDataCarModel"
            :data="laravelData.data"
            @a="confirmCarModel($event)"
            @close="showModalCarModel = false"
            >
        <template #header>
          </template>
        </ModalAddCarModel>
        <ModalAddColor
            :show="showModalColor ? true : false"
            :formData="formDataColor"
            :data="laravelData.data"
            @a="confirmColor($event)"
            @close="showModalColor = false"
            >
        <template #header>
         </template>
        </ModalAddColor>
        <div class="py-12">
            <div class="max-w-9xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6  dark:bg-gray-900">
                        <div class="flex flex-row">
                        <div class="basis-1/2">
                            <div className="flex items-center justify-between mb-6">
                                        <Link 
                                            className="px-6 py-2 text-white bg-rose-500 rounded-md focus:outline-none"
                                            :href="route('addCompany')">
                                            إنشاء شركة جديدة
                                        </Link>
                                    </div>
                            </div>
                            <div class="basis-1/2">
                                <form class="flex items-center max-w-5xl">   
                                    <label  class="dark:text-gray-200" for="simple-search"  ></label>
                                    <div class="relative w-full">
                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path></svg>
                                        </div>
                                        <input   v-model="searchTerm" @input="search(searchTerm)" type="text" id="simple-search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5  dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="بحث حسب الشركة" required>
                                    </div>
                                    </form>
                            </div>
                </div>
                   

        

                        <div class="overflow-x-auto shadow-md ">
                        <table  class="w-full my-5">      
                            <thead class="700 bg-rose-500 text-white text-center rounded-l-lg">
                                <tr  class="bg-rose-500  rounded-l-lg  mb-2 sm:mb-0">
                                    <th className="px-4 py-2 border dark:border-gray-900">{{ $t('name') }}</th>
                                    <th className="px-4 py-2 border dark:border-gray-900">{{ $t('english_name') }}</th>
                                    <th className="px-4 py-2 border dark:border-gray-900  w-40">تنفيذ</th>          
                                </tr>
                            </thead>
                            <tbody class="flex-1 sm:flex-none dark:bg-gray-700 dark:text-gray-200">
                              
                                <tr v-for="user in laravelData.data" :key="user.id"  class="mb-2 sm:mb-0   text-center">
                                    <td className="px-4 py-2 border dark:border-gray-900 td">{{ user.name}}</td>
                                    <td className="px-4 py-2 border dark:border-gray-900">{{ user.name_en }}</td>
                                    <td className="border px-2 py-2 dark:border-gray-900" >
                                        <Link
                                            tabIndex="1"
                                            className="px-2 py-1 text-sm text-white mx-1 bg-slate-500 rounded"
                                            :href="route('companyEdit', user.id)"
                                           
                                        >
                                            تعديل
                                        </Link>
                                        <button
                                            tabIndex="1"
                                            className="px-2 py-1 text-sm text-white mx-1 bg-red-500 rounded"
                                            @click="deleteApi(user.id,'delCompany')"                                           
                                        >
                                            حذف
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                        <div class="mt-3  text-center"  style="direction: ltr;">
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

        <div class="py-12">
            <div class="max-w-9xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6  dark:bg-gray-900">
                        <div class="flex flex-row">
                        <div class="basis-1/2">
                            <div className="flex items-center justify-between mb-6">
                                        <button 
                                            className="px-6 py-2 text-white bg-green-500 rounded-md focus:outline-none"
                                            @click="openAddNameCompany()">
                                            إنشاء اسم جديدة
                                        </button>
                                    </div>
                            </div>
                            <div class="basis-1/2">
                                <form class="flex items-center max-w-5xl">   
                                    <label  class="dark:text-gray-200" for="simple-search"  ></label>
                                    <div class="relative w-full">
                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path></svg>
                                        </div>
                                        <input   v-model="searchTerm" @input="search(searchTerm)" type="text" id="simple-search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5  dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="بحث حسب اسم السيارة" required>
                                    </div>
                                    </form>
                            </div>
                </div>
                   

        

                        <div class="overflow-x-auto shadow-md ">
                        <table  class="w-full my-5">      
                            <thead class="700 bg-green-500 text-white text-center rounded-l-lg">
                                <tr  class="bg-green-500  rounded-l-lg  mb-2 sm:mb-0">
                                    <th className="px-4 py-2 border dark:border-gray-900">{{ $t('name') }}</th>
                                    <th className="px-4 py-2 border dark:border-gray-900">{{ $t('english_name') }}</th>
                                    <th className="px-4 py-2 border dark:border-gray-900">{{ $t('company') }}</th>
                                    <th className="px-4 py-2 border dark:border-gray-900  w-40">تنفيذ</th>          
                                </tr>
                            </thead>
                            <tbody class="flex-1 sm:flex-none dark:bg-gray-700 dark:text-gray-200">
                              
                                <tr v-for="user in companyName.data" :key="user.id"  class="mb-2 sm:mb-0   text-center">
                                    <td className="px-4 py-2 border dark:border-gray-900 td">{{ user.name}}</td>
                                    <td className="px-4 py-2 border dark:border-gray-900">{{ user.name_en }}</td>
                                    <td className="px-4 py-2 border dark:border-gray-900">{{ user.company?.name }}</td>
                                    <td className="border px-2 py-2 dark:border-gray-900" >
                                        <button
                                            tabIndex="1"
                                            className="px-2 py-1 text-sm text-white mx-1 bg-slate-500 rounded"
                                            @click="openAddNameCompany(user)"
                                           
                                        >
                                            تعديل
                                        </button>
                                        <button
                                            tabIndex="1"
                                            className="px-2 py-1 text-sm text-white mx-1 bg-green-500 rounded"
                                            @click="deleteApi(user.id,'delName')"
                                        >
                                            حذف
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                        <div class="mt-3  text-center"  style="direction: ltr;">
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

        <div class="py-12">
            <div class="max-w-9xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6  dark:bg-gray-900">
                        <div class="flex flex-row">
                        <div class="basis-1/2">
                            <div className="flex items-center justify-between mb-6">
                                        <button 
                                            className="px-6 py-2 text-white bg-blue-500 rounded-md focus:outline-none"
                                            @click="openAddCarModel()">
                                            إنشاء سنة جديدة
                                        </button>
                                    </div>
                            </div>
                            <div class="basis-1/2">
                                <form class="flex items-center max-w-5xl">   
                                    <label  class="dark:text-gray-200" for="simple-search"  ></label>
                                    <div class="relative w-full">
                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path></svg>
                                        </div>
                                        <input   v-model="searchTerm" @input="search(searchTerm)" type="text" id="simple-search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5  dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="بحث حسب  سنة الصنع" required>
                                    </div>
                                    </form>
                            </div>
                </div>
                   

        

                        <div class="overflow-x-auto shadow-md ">
                        <table  class="w-full my-5">      
                            <thead class="700 bg-blue-500 text-white text-center rounded-l-lg">
                                <tr  class="bg-blue-500  rounded-l-lg  mb-2 sm:mb-0">
                                    <th className="px-4 py-2 border dark:border-gray-900">{{ $t('name') }}</th>
                                    <th className="px-4 py-2 border dark:border-gray-900  w-40">تنفيذ</th>          
                                </tr>
                            </thead>
                            <tbody class="flex-1 sm:flex-none dark:bg-gray-700 dark:text-gray-200">
                              
                                <tr v-for="user in companyModel.data" :key="user.id"  class="mb-2 sm:mb-0   text-center">
                                    <td className="px-4 py-2 border dark:border-gray-900 td">{{ user.name}}</td>
                                    <td className="border px-2 py-2 dark:border-gray-900" >
                                        <button
                                            tabIndex="1"
                                            className="px-2 py-1 text-sm text-white mx-1 bg-slate-500 rounded"
                                           @click="openAddCarModel(user)"
                                           
                                        >
                                            تعديل
                                        </button>
                                        <button
                                            tabIndex="1"
                                            className="px-2 py-1 text-sm text-white mx-1 bg-blue-500 rounded"
                                            @click="deleteApi(user.id,'delModel')"
                                        >
                                            حذف
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                        <div class="mt-3  text-center"  style="direction: ltr;">
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

        <div class="py-12">
            <div class="max-w-9xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6  dark:bg-gray-900">
                        <div class="flex flex-row">
                        <div class="basis-1/2">
                            <div className="flex items-center justify-between mb-6">
                                        <button 
                                            className="px-6 py-2 text-white bg-purple-500 rounded-md focus:outline-none"
                                            @click="openAddColor()">
                                            إنشاء لون جديدة
                                        </button>
                                    </div>
                            </div>
                            <div class="basis-1/2">
                                <form class="flex items-center max-w-5xl">   
                                    <label  class="dark:text-gray-200" for="simple-search"  ></label>
                                    <div class="relative w-full">
                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path></svg>
                                        </div>
                                        <input   v-model="searchTerm" @input="search(searchTerm)" type="text" id="simple-search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5  dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="بحث حسب اسم اللون" required>
                                    </div>
                                    </form>
                            </div>
                </div>
                   

        

                        <div class="overflow-x-auto shadow-md ">
                        <table  class="w-full my-5">      
                            <thead class="700 bg-purple-500 text-white text-center rounded-l-lg">
                                <tr  class="bg-purple-500  rounded-l-lg  mb-2 sm:mb-0">
                                    <th className="px-4 py-2 border dark:border-gray-900">{{ $t('name') }}</th>
                                    <th className="px-4 py-2 border dark:border-gray-900">{{ $t('english_name') }}</th>
                                    <th className="px-4 py-2 border dark:border-gray-900  w-40">تنفيذ</th>          
                                </tr>
                            </thead>
                            <tbody class="flex-1 sm:flex-none dark:bg-gray-700 dark:text-gray-200">
                              
                                <tr v-for="user in companyColor.data" :key="user.id"  class="mb-2 sm:mb-0   text-center">
                                    <td className="px-4 py-2 border dark:border-gray-900 td">{{ user.name}}</td>
                                    <td className="px-4 py-2 border dark:border-gray-900">{{ user.name_en }}</td>
                                    <td className="border px-2 py-2 dark:border-gray-900" >
                                        <button
                                            tabIndex="1"
                                            className="px-2 py-1 text-sm text-white mx-1 bg-slate-500 rounded"
                                           @click="openAddColor(user)"
                                           
                                        >
                                            تعديل
                                        </button>
                                        <button
                                            tabIndex="1"
                                            className="px-2 py-1 text-sm text-white mx-1 bg-purple-500 rounded"
                                            @click="deleteApi(user.id,'delColor')"
                                        >
                                            حذف
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                        <div class="mt-3  text-center"  style="direction: ltr;">
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