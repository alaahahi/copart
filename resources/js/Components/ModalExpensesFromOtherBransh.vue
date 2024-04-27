<script setup>
import { ref } from 'vue';
import print from "@/Components/icon/print.vue";
import axios from 'axios';

const activeTab = ref('add'); // Set the default active tab

const setActiveTab = (tab) => {
  activeTab.value = tab;
};
const props = defineProps({
  show: Boolean,
  user:Array,
  allTransfers:Array,
  formData:Object
});
function confirmTransfers (tran){
  axios.post(`/api/confirmTransfers`,tran)
  .then(response => {
    window.location.reload();
  })
  .catch(error => {

  })
}
function cancelTransfers(tran){
  axios.post(`/api/cancelTransfers`,tran)
  .then(response => {
    window.location.reload();
  })
  .catch(error => {

  })
}
</script>
  <template>
    <Transition name="modal">
      <div v-if="show" class="modal-mask ">
        <div class="modal-wrapper  max-h-[80vh]">
          <div class="modal-container dark:bg-gray-900 overflow-auto  max-h-[80vh]">
            <div class="modal-header"><slot name="header"></slot>
            </div>
            <div class="modal-body">
              
              <div class="text-sm font-medium text-center text-gray-500 border-b border-gray-200 dark:text-gray-400 dark:border-gray-700">
                  <ul class="flex flex-wrap -mb-px">
                    <li class="mr-2" @click="setActiveTab('add')">
                      <button
                        class="inline-block p-4 border-b-2 border-transparent rounded-t-lg"
                        :class="activeTab == 'add'?'dark:text-blue-500 dark:border-blue-500' :'hover:text-gray-600 hover:border-gray-300'"
                      >
                        طلبات قيد التحويل
                      </button>
                    </li>
                    <li class="mr-2" @click="setActiveTab('record')">
                      <button
                        class="inline-block p-4 border-b-2 border-transparent rounded-t-lg "
                        :class="activeTab == 'record'? 'dark:text-blue-500 dark:border-blue-500' :'hover:text-gray-600 hover:border-gray-300'"
                      >
                        السجل
                      </button>
                    </li>
                  </ul>
                </div>
                <div v-if="activeTab =='add'"> 
                  <h2 class="text-center py-3">طلبات قيد التحويل</h2>
                  <template  v-for="expense in allTransfers" :key="expense.id">
                  <div  v-if="expense.stauts !='تم الأستلام'" id="alert-additional-content-4 my-3" class="p-4 mb-4 text-yellow-800 border border-yellow-300 rounded-lg bg-yellow-50 dark:bg-gray-800 dark:text-yellow-300 dark:border-yellow-800" role="alert">
                    <div class="flex items-center">
                      <svg class="flex-shrink-0 w-4 h-4 me-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                      </svg>
                      <span class="sr-only ">Info</span>
                      <h3 class="text-lg font-medium px-2">طلب حوالة من فرع كركوك</h3>
                      <h3 class="text-lg font-medium ">  مبلغ 
                        {{ expense.amount }} 
                        دولار
                      </h3>
                    </div>
                    <div class="mt-2 mb-4 text-sm px-4">
                     {{ expense.note }}
                    </div>
                    <label  class="dark:text-gray-200 px-4" for="note" >أجور الحوالة بالدولار</label>
                          <input
                            type="number"
                            class="mt-3 mx-3 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                            v-model="expense.inputValue"
                            />
                            <input
                            type="text"
                            class="mt-3 mx-3 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                            v-model="expense.receiver"
                            />
                    <div class="flex items-center py-3" v-if="expense.inputValue">
                      <svg class="flex-shrink-0 w-4 h-4 me-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
                      </svg>
                      <span class="sr-only ">Info</span>
                      <h3 class="text-lg font-medium px-2">  صافي الحوالة بعد خصم أجر التحويل</h3>
                      <h3 class="text-lg font-medium ">  مبلغ 
                        {{ expense.amount - expense.inputValue }} 
                        دولار
                      </h3>
                    </div>
                    <div class="flex mt-5" v-if="expense.inputValue">
                      <button @click="confirmTransfers(expense)" type="button" class="mx-2 text-white bg-yellow-800 hover:bg-yellow-900 focus:ring-4 focus:outline-none focus:ring-yellow-300 font-medium rounded-lg text-xs px-3 py-1.5 me-2 text-center inline-flex items-center dark:bg-yellow-300 dark:text-gray-800 dark:hover:bg-yellow-400 dark:focus:ring-yellow-800">
                       تأكيد الحوالات
                      </button >
                      <button @click="cancelTransfers(expense)" type="button" class="text-yellow-800 bg-transparent border border-yellow-800 hover:bg-yellow-900 hover:text-white focus:ring-4 focus:outline-none focus:ring-yellow-300 font-medium rounded-lg text-xs px-3 py-1.5 text-center dark:hover:bg-yellow-300 dark:border-yellow-300 dark:text-yellow-300 dark:hover:text-gray-800 dark:focus:ring-yellow-800" data-dismiss-target="#alert-additional-content-4" aria-label="Close" :disabled="!(formData.fee)">
                      إلغاء
                      </button>
                    </div>
                  </div>
                  </template>

               
                </div>

                <div v-else>
                  <h1 class="text-center dark:text-gray-200 mt-4"> سجل الحولات</h1>
                  <div class="relative overflow-x-auto shadow-md sm:rounded-lg mt-4 mb-5" >
                  <table class="w-full text-sm text-right text-gray-500 dark:text-gray-200 dark:text-gray-400 text-center">
                  <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 text-center" >
                  <tr  class="bg-rose-500 text-gray-100 rounded-l-lg mb-2 sm:mb-0">
                    <th className="px-1 py-2 text-base">رقم الوصل</th>
                    <th className="px-1 py-2 text-base">{{$t('date')}}</th>
                    <th className="px-1 py-2 text-base">المبلغ بالدولار</th>
                    <th className="px-1 py-2 text-base">أجور الحولات</th>
                    <th className="px-1 py-2 text-base">المبلغ بالدولار الصافي</th>
                    <th className="px-1 py-2 text-base">ملاحظة</th>
                    <th className="px-1 py-2 text-base">الحالة</th>

                    <!-- <th
                      scope="col"
                      class="px-1 py-2 text-base print:hidden"
                    >
                      تنفيذ
                    </th> -->
                  </tr>
                </thead>
                <tbody>
                  <template  v-for="expense in allTransfers" :key="expense.id">
                  <tr class="text-center">
                  <td className="px-4 py-2 border dark:border-gray-800 dark:text-gray-200">{{ expense.id }}</td>
                  <td className="px-4 py-2 border dark:border-gray-800 dark:text-gray-200">{{ expense?.created_at?.slice(0, 19).replace('T', ' ') }}</td>
                  <td className="px-4 py-2 border dark:border-gray-800 dark:text-gray-200">{{ expense.amount   }}</td>
                  <td className="px-4 py-2 border dark:border-gray-800 dark:text-gray-200">{{ expense.fee   }}</td>
                  <td className="px-4 py-2 border dark:border-gray-800 dark:text-gray-200">{{ expense.amount-expense.fee   }}</td>
                  <td className="px-4 py-2 border dark:border-gray-800 dark:text-gray-200">{{ expense.note   }}</td>
                  <td className="px-4 py-2 border dark:border-gray-800 dark:text-gray-200">{{ expense.stauts   }}</td>

             
                  </tr>
                  </template>
         
                </tbody>
              </table>
          </div>
                </div>


            </div>
  
            <div class="modal-footer my-2">
              <div class="flex flex-row">
                <div class="basis-1/2 px-4"> 
                  <button class="modal-default-button py-3  bg-gray-500 rounded"
                    @click="$emit('close');activeTab = 'add'">{{ $t('cancel') }}</button>
                  </div>
              <div class="basis-1/2 px-4">
                <button class="modal-default-button py-3  bg-rose-500 rounded col-6"  @click="$emit('a',formData);formData=''" :disabled="!(formData.amount)">{{ $t('yes') }}</button>
                </div>

            </div>
  
     
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </template>
  
  <style>
  .modal-mask {
    position: fixed;
    z-index: 9998;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: table;
    transition: opacity 0.3s ease;
  }
  
  .modal-wrapper {
    display: table-cell;
    vertical-align: middle;
  }
  
  .modal-container {
    width: 50%;
    min-width: 350px;
    margin: 0px auto;
    padding: 20px  30px;
    padding-bottom: 60px;
    background-color: #fff;
    border-radius: 2px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.33);
    transition: all 0.3s ease;
    border-radius: 10px;
  }
  
  .modal-header h3 {
    margin-top: 0;
    color: #42b983;
  }
  
  .modal-body {
    margin: 20px 0;
  }
  
  .modal-default-button {
    float: right;
    width: 100%;
    color: #fff;
  }
  
  /*
   * The following styles are auto-applied to elements with
   * transition="modal" when their visibility is toggled
   * by Vue.js.
   *
   * You can easily play with the modal transition by editing
   * these styles.
   */
  
  .modal-enter-from {
    opacity: 0;
  }
  
  .modal-leave-to {
    opacity: 0;
  }
  
  .modal-enter-from .modal-container,
  .modal-leave-to .modal-container {
    -webkit-transform: scale(1.1);
    transform: scale(1.1);
  }
  </style>