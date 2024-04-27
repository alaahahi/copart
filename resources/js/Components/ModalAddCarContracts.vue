<script setup>
import { ref, computed } from 'vue';
import { useToast } from "vue-toastification";
import Uploader  from 'vue-media-upload';


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
let disabled =  ref(false);
function removeMedia(removedImage){
          axios.get('/api/carsAnnualImageDel?img_type=contract&name='+removedImage.name)
        .then(response => {
          toast.success("تم  حذف الصورة بنجاح", {
              timeout: 5000,
              position: "bottom-right",
              rtl: true

            });
        })
        .catch(error => {
          console.error(error);
        })
}

function calculateAmount(){
  let amount =props.formData.prices
if(props.formData.paids > amount){
  props.formData.paids = props.formData.prices
    toast.info(" المبلغ اكبر من الدين المطلوب"+" "+props.formData.prices, {
        timeout: 4000,
        position: "bottom-right",
        rtl: true,
      });
}
if(props.formData.prices>=300){
  toast.warning(" المبلغ اكبر من الحد الطبيعي بالدولار"+" "+300, {
        timeout: 10000,
        position: "bottom-right",
        rtl: true,
      })
      disabled=true
}else{
  disabled=false
}
}
function calculateAmountDinars(){
  let amount =props.formData.price_dinars
if(props.formData.paid_dinars > amount){
  props.formData.paid_dinars = props.formData.price_dinars
    toast.info(" المبلغ اكبر من الدين المطلوب"+" "+props.formData.price_dinars, {
        timeout: 4000,
        position: "bottom-right",
        rtl: true,
      });
}
if(props.formData.price_dinars>=500000){
  toast.warning("  المبلغ اكبر من الحد الطبيعي بالدينار"+" "+500000, {
        timeout: 10000,
        position: "bottom-right",
        rtl: true,
      })
      disabled=true

}else{
  disabled=false

}
}
</script>
  <template>
    <Transition name="modal">
      <div v-if="show" class="modal-mask ">
        <div class="modal-wrapper  max-h-[80vh]">
          <div class="modal-container dark:bg-gray-900 overflow-auto  max-h-[80vh]">
            <div class="modal-header text-center py-4 dark:text-gray-300">  
              إضافة عقد الكتروني للسيارة {{ formData.car_type }} {{ formData.year }} {{  formData.car_color }} رقم شانصى {{ formData.vin }}
              <slot name="header"></slot>
            </div>

            <div class="modal-body">
    
              <div>
              <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-2 gap-3 lg:gap-2">
              <div className="mb-4 mx-5">
              <label  class="dark:text-gray-200" for="amountTotal" >سعر العقد بالدولار</label>
              <input
                id="amountTotal"
                type="number"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                v-model="formData.prices" />
              </div>
              <div className="mb-4 mx-5">
              <label  class="dark:text-gray-200" for="amountTotal" >سعر العقد بالدينار</label>
              <input
                id="amountTotal"
                type="number"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                v-model="formData.price_dinars" />
              </div>
              </div>
              <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-2 gap-3 lg:gap-2">
              <div className="mb-4 mx-5">
              <label  class="dark:text-gray-200" for="amountPayment" >المبلغ المدفع بالدولار</label>
              <input
                id="amountPayment"
                type="number"
                @input="calculateAmount"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                v-model="formData.paids" />
              </div>
              <div className="mb-4 mx-5">
              <label  class="dark:text-gray-200" for="amountPayment" >المبلغ المدفع بالدينار</label>
              <input
                id="amountPayment"
                type="number"
                @input="calculateAmountDinars"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                v-model="formData.paid_dinars" />
              </div>
              </div>
              <div className="mb-4 mx-5">
              <label  class="dark:text-gray-200" for="notePayment" >{{ $t('phone') }} </label>
              <input
                id="notePayment"
                type="text"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                v-model="formData.phone" />
              </div>
              <div className="mb-4 mx-5">
              <label  class="dark:text-gray-200" for="notePayment" >{{ $t('note') }} </label>
              <input
                id="notePayment"
                type="text"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900 "
                v-model="formData.note" />
              </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-1 gap-1 lg:gap-2">
              <div class="mb-4">
                <label class="form-label">الصور</label>
                <div class="mt-3">
                    <Uploader 
                        :server="'/api/carsAnnualUpload?img_type=contract&carId='+formData.id"
                        :is-invalid="errors?.media ? true : false"
                        @change="changeMedia"
                        location="/storage/posts/media"
                        @init="initMedia"
                        @add="addMedia"
                        @remove="removeMedia"
                    />
                </div>
                <p v-if="errors?.media" class="text-danger">{{ errors?.media[0] }}</p>
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
                <button class="modal-default-button py-3  bg-rose-500 rounded col-6"  @click="$emit('a',formData);formData=''" :disabled="disabled">{{ $t('yes') }}</button>
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