<script setup>
import { ref, computed } from 'vue';


import { useToast } from "vue-toastification";
let toast = useToast();

const props = defineProps({
  show: Boolean,
  company: Array,
  color:Array,
  carModel:Array,
  name:Array,
  client:Array,
  user:Array,
  expenses:Array,
  formData:Object
});

function calculateAmount(){
  let amount =props.formData.total_s-(props.formData.paid+(props.formData.discount||0))
if(props.formData.amountPayment > amount){
  props.formData.amountPayment = amount
  props.formData.discountPayment = 0
    toast.info(" المبلغ اكبر من الدين المطلوب"+" "+amount, {
        timeout: 4000,
        position: "bottom-right",
        rtl: true,
      });
}

}
</script>
  <template>
    <Transition name="modal">
      <div v-if="show" class="modal-mask ">
        <div class="modal-wrapper  max-h-[80vh]">

          <div class="modal-container dark:bg-gray-900 overflow-auto  max-h-[80vh]">
            <div class="modal-header">
              <slot name="header">    
                </slot>
            <h4 class="text-center py-3">اكمال الدفع للسيارة {{ formData.car_type ?? ''}}</h4>
            </div>

            <div class="modal-body">
              <div class="grid grid-cols-4 md:grid-cols-4 lg:grid-cols-4 gap-3 lg:gap-3">
                <div className="mb-4 mx-5">
                  <label  class="dark:text-gray-200" for="user_id" >{{ $t('totalForCar') }}</label>
                  <input
                  id="id"
                  type="text"
                  style="display: none;"
                  disabled
                  v-model="formData.id" />

                  <input
                  id="id"
                  type="text"
                  disabled
                  class=" mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                  v-model="formData.total_s" />
                </div>
                <div className="mb-4 mx-5">
                  <label  class="dark:text-gray-200" for="user_id" >{{ $t('paid_amount') }}</label>
                  <input
                  id="id"
                  type="text"
                  disabled
                  class=" mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                  v-model="formData.paid" />
                </div>
                <div className="mb-4 mx-5">
                  <label  class="dark:text-gray-200" for="userId">{{ $t('debtRemaining') }}</label>
                  <input
                  id="id"
                  type="text"
                  disabled
                  :value="formData.total_s-(formData.paid+(formData.discount||0))"
                  class=" mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                   />
                </div>
                <div className="mb-4 mx-5">
                  <label  class="dark:text-gray-200" for="userId">{{ $t('discount') }}</label>
                  <input
                  id="id"
                  type="text"
                  disabled
                  v-model="formData.discount" 
                  class=" mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                   />
                </div>
              </div>

              <div>
              <div className="mb-4 mx-5">
              <label  class="dark:text-gray-200" for="amountPayment" >{{ $t('amount') }}</label>
              <input
                id="amountPayment"
                type="number"
                @input="calculateAmount"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                v-model="formData.amountPayment" />
              </div>
              <div className="mb-4 mx-5">
              <label  class="dark:text-gray-200" for="amountPayment" >{{ $t('discount') }}</label>
              <input
                id="discount"
                type="number"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                v-model="formData.discountPayment" />
              </div>
              <div className="mb-4 mx-5">
              <label  class="dark:text-gray-200" for="notePayment" >{{ $t('note') }} </label>
              <input
                id="notePayment"
                type="text"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                v-model="formData.notePayment" />
              </div>
              </div>
            </div>
  
            <div class="modal-footer my-2">
              <div class="flex flex-row">
                <div class="basis-1/2 px-4"> 
                  <button class="modal-default-button py-3  bg-gray-500 rounded"
                    @click="$emit('close');">{{ $t('cancel') }}</button>
                  </div>
                <div class="basis-1/2 px-4">
                <button class="modal-default-button py-3  bg-rose-500 rounded col-6"  @click="$emit('a',formData);formData={}" :disabled="!(formData.amountPayment)">{{ $t('yes') }}</button>
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