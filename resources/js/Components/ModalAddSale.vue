<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  show: Boolean,
  company: Array,
  color:Array,
  carModel:Array,
  name:Array,
  client:Array,
  formData:Object
});
let showClient =  ref(false);

</script>
  <template>
    <Transition name="modal">
      <div v-if="show" class="modal-mask ">
        <div class="modal-wrapper  max-h-[80vh]">
          <div class="modal-container dark:bg-gray-900 overflow-auto  max-h-[80vh]">
            <div class="modal-header">
              <slot name="header"></slot>
            </div>
            <div class="modal-body">
              <h2 class="text-center dark:text-gray-200">
                {{ $t('sellCar') }}
              </h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-3 lg:gap-3">
                          <input
                          id="id"
                          type="text"
                          disabled
                          style="display: none;"
                          class=" mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                          v-model="formData.id" />

                        <div className="mb-4 mx-5">
                          <label  class="dark:text-gray-200" for="company_id" >{{ $t('company') }}</label>
                          <select
                            v-model="formData.company_id"
                            id="company_id"
                            disabled
                            class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            <option selected disabled>{{ $t('select_company') }}</option>
                            <option v-for="(card, index) in company" :key="index" :value="card.id">{{ card.name }}</option>
                          </select>
                        </div>
                        <div className="mb-4 mx-5">
                          <label  class="dark:text-gray-200" for="name_id" >{{ $t('name') }}</label>
                          <select
                            v-model="formData.name_id"
                            id="name_id"
                            disabled
                            class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            <option selected disabled>{{ $t('select_name') }}</option>
                            <template v-for="(card, index) in name" :key="index"  >
                              <option   :value="card.id"  v-if="card.company_id == formData.company_id">{{ card.name }}</option>

                            </template>
                          </select>
                        </div>
                        <div className="mb-4 mx-5">
                          <label  class="dark:text-gray-200" for="carmodel" >{{ $t('year') }}</label>
                          <select
                            v-model="formData.model_id"
                            id="carmodel"
                            disabled
                            class="pr-8  bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            <option selected disabled>{{ $t('select_year') }}</option>
                            <option v-for="(card, index) in carModel" :key="index" :value="card.id">{{ card.name }}</option>
                          </select>
                        </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-3 lg:gap-4">
     
                        <div className="mb-4 mx-5">
                          <label  class="dark:text-gray-200" for="color_id" >{{ $t('color') }}</label>
                          <select
                            v-model="formData.color_id"
                            id="color_id"
                            disabled
                            class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                            <option selected disabled>{{ $t('select_color') }}</option>
                            <option v-for="(card, index) in color" :key="index" :value="card.id">{{ card.name }}</option>
                          </select>
                        </div>
                        <div className="mb-4 mx-5">
                        <label  class="dark:text-gray-200" for="pin">{{ $t('vim') }}</label>
                        <input
                          id="pin"
                          type="text"
                          disabled
                          class="mt-1  block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                          v-model="formData.pin" />
                        </div>
                        <div className="mb-4 mx-5">
                          <label  class="dark:text-gray-200" for="purchase_data">{{ $t('purchaseDate') }}</label>
                        <input
                          id="purchase_data"
                          type="date"
                          disabled
                          style="padding-right: 0;"
                          class="mt-1 block text-end   w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                          v-model="formData.purchase_data" />
                        </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-3 lg:gap-3">
                        <div className="mb-4 mx-5">
                        <label  class="dark:text-gray-200" for="purchase_price" >{{ $t('purchase_price') }}</label>
                        <input
                          id="purchase_price"
                          type="number"
                          disabled
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                          v-model="formData.purchase_price" />
                        </div>
                        <div className="mb-4 mx-5">
                          <label  class="dark:text-gray-200" for="paid_amount">{{ $t('paidAmountToSupplier') }}</label>
                        <input
                          id="paid_amount"
                          type="number"
                          disabled
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                          v-model="formData.paid_amount" />
                        </div>
                        <div className="mb-4 mx-5">
                          <label  class="dark:text-gray-200" for="paid_amount">{{ $t('totalExpenses') }}</label>
                        <input
                          id="paid_amount"
                          type="number"
                          :value="formData.dubai_exp + formData.dubai_shipping + formData.erbil_exp + formData.erbil_shipping"
                          disabled
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                           />
                        </div>
                        </div>
                        <div className="mb-4 mx-5">
                          <label  class="dark:text-gray-200" for="pay_price">{{ $t('sellingPrice') }}</label>
                        <input
                          id="pay_price"
                          type="number"
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                          v-model="formData.pay_price" />
                        </div>
                        <div class="mb-4 mx-5">
                          <label  class="dark:text-gray-200" for="color_id">{{ $t('customer') }}</label>
                          <div class="relative">
                            <select
                              v-if="!showClient"
                              v-model="formData.client_id"
                              id="color_id"
                              class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                              <option selected disabled>   {{ $t('selectCustomer') }}</option>
                              <option v-for="(card, index) in client" :key="index" :value="card.id">{{ card.name }}</option>
                            </select>
                            <button
                              type="button"
                              @click="showClient=true;formData.client_name=''"
                              v-if="!showClient"
                              class="absolute left-0 top-0 h-full px-3 py-2 font-bold text-white bg-green-500 rounded-tl-lg rounded-bl-lg"
                            >
                            {{ $t('addCustomer') }}
                            </button>
                          </div>
                          <div class="relative">
                          <input
                          id="note"
                          v-if="showClient"
                          type="text"
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                          v-model="formData.client_name" />
                          <button
                              type="button"
                              @click="showClient=false;formData.client=''"
                              v-if="showClient"
                              class="absolute left-0 top-0 h-full px-3 py-2 font-bold text-white bg-pink-500 rounded-tl-lg rounded-bl-lg"
                            >
                            {{ $t('selectCustomer') }}

                            </button>
                          </div>
                        </div>
                        <div className="mb-4 mx-5"  v-if="showClient">
                          {{ $t('phoneNumber') }}
                        <input
                          id="note"
                          type="number"
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                          v-model="formData.client_phone" />
                        </div>
                        <div className="mb-4 mx-5">
                        <label  class="dark:text-gray-200" for="paid_amount_pay" >{{ $t('paid_amount') }}</label>
                        <input
                          id="paid_amount_pay"
                          type="number"
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                          v-model="formData.paid_amount_pay" />
                        </div>
                        <div className="mb-4 mx-5">
                        <label  class="dark:text-gray-200" for="note_pay" >{{ $t('note') }} </label>
                        <input
                          id="note_pay"
                          type="text"
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                          v-model="formData.note_pay" />
                        </div>
            </div>
  
            <div class="modal-footer my-2">
              <div class="flex flex-row">
                <div class="basis-1/2 px-4"> 
                  <button class="modal-default-button py-3  bg-gray-500 rounded"
                    @click="$emit('close');">{{ $t('cancel') }}</button>
                  </div>
                <div class="basis-1/2 px-4">
                  <button class="modal-default-button py-3  bg-rose-500 rounded col-6"  @click="$emit('a',formData);formData=''" :disabled="!(formData.paid_amount_pay && formData.pay_price )">{{ $t('yes') }}</button>
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