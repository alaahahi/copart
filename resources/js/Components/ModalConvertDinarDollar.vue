<script setup>
import { ref } from 'vue';

import { useToast } from "vue-toastification";
let toast = useToast();

const props = defineProps({
  show: Boolean,
  boxes: Array,
});
const form = ref({
  user: {
    percentage:0,
  },
  amount: 0,
  exchangeRate:1

});

const restform =()=>{
  form.value = {
  user: {
    percentage:0,
  },
  amount: 0,

};
}
let exchangeRateError= ref(false);
function validateExchangeRate() {
      const input = form.value.exchangeRate;
      if (/^\d{6}$/.test(input)) {
        exchangeRateError.value = false;
      } else {
        exchangeRateError.value = true;
      }
    }
function calculateAmountDollarDinar (){
  validateExchangeRate()
    form.value.amountResultDollar = Math.floor(form.value.amountDinar/(form.value.exchangeRate/100))



}

</script>
  
  <template>  
    <Transition name="modal">
      <div v-if="show" class="modal-mask ">
        <div class="modal-wrapper  max-h-[80vh]">
          <div class="modal-container">
            <div class="modal-header">
              <slot name="header"></slot>
            </div>
            <div class="modal-body">
                        <div>
                        <div className="my-4 mx-5">
                        <label for="amountDinar" >سعر الصرف 100$</label>
                        <input
                          id="amountDinar"
                          type="number"
                          @input="calculateAmountDollarDinar()"
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                          v-model="form.exchangeRate" />
                          <div v-if="exchangeRateError" class="text-red-500">
                          مطلوب رقم من 6 خانة فقط
                          </div>
                        </div>
                        
                        <div className="my-4 mx-5">
                        <label for="amountDinar" >المبلغ بالدينار العراقي
                          (المبلغ المسحوب من الصندوق بالدينار العراقي)
                        </label>
                        <input
                          id="amountDinar"
                          type="number"
                          @input="calculateAmountDollarDinar()"
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                          v-model="form.amountDinar" />
                        </div>
                  
                        <div className="mb-y mx-5">
                        <label for="amountDinar" >المبلغ  بالدولار
                          (المبلغ المضاف للصندوق بالدولار)
                        </label>
                        <input
                          id="amountDinar"
                          type="number"
                          class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                          v-model="form.amountResultDollar" />
                        </div>
               
                        </div>
        
            </div>
  
            <div class="modal-footer my-2">
              <div class="flex flex-row">
                <div class="basis-1/2 px-4"> 
                  <button class="modal-default-button py-3  bg-gray-500 rounded"
                    @click="$emit('close');">تراجع</button>
                  </div>
              <div class="basis-1/2 px-4">
                <button class="modal-default-button py-3  bg-rose-500 rounded col-6"  @click="$emit('a',form);restform();"  >نعم</button>
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