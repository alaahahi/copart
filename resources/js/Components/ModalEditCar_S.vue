<script setup>
import { ref, computed } from "vue";
import axios from 'axios';
import Uploader  from 'vue-media-upload';
import { useToast } from "vue-toastification";

const toast = useToast();




const props = defineProps({
  show: Boolean,
  formData: Object,
  client: Array,
});

function getTodayDate() {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, "0");
  const day = String(today.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}
function check_vin(v){
  if(v){
    axios.get(`/api/check_vin?car_vin=${v}`)
  .then(response => {
    showErrorVin.value =  response.data;
  })
  .catch(error => {
    console.error(error);
  })
  }
}
let showClient = ref(false);
let showErrorVin = ref(false);
let exchangeRateError= ref(false);
function validateExchangeRate(v) {
      const input = props.formData.dolar_price_s;
      if (/^\d{6}$/.test(input)) {
        exchangeRateError.value = false;
      } else {
        exchangeRateError.value = true;
      }
    }

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
</script>
  <template>
  <Transition name="modal">
    <div v-if="show" class="modal-mask">
      <div class="modal-wrapper  max-h-[80vh]">
        <div class="modal-container dark:bg-gray-900 overflow-auto  max-h-[80vh]">
          <div class="modal-header">
            <slot name="header">
              <h2 class="text-center dark:text-gray-200">
                {{ $t("edit_car") }}
              </h2>
            </slot>
          </div>
          <div class="modal-body">
            <div
              class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-1 gap-1 lg:gap-2"
            >
              <div class="mb-4 mx-1">
                <label class="dark:text-gray-200" for="color_id">{{
                  $t("car_owner")
                }}</label>
                <div class="relative">
                  <select
                    v-if="!showClient"
                    v-model="formData.client_id"
                    id="color_id"
                    class="pr-8 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                    disabled>
                    <option selected disabled>
                      {{ $t("selectCustomer") }}
                    </option>
                    <option
                      v-for="(card, index) in client"
                      :key="index"
                      :value="card.id"
                      >
                      {{ card.name }}
                    </option>
                  </select>
                
                </div>
         
              </div>
    
            </div>
            <div
              class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-1 lg:gap-2"
            >
              <div className="mb-4 mx-1">
                <label class="dark:text-gray-200" for="pin">
                  {{ $t("car_type") }}</label
                >
                <input
                  id="car_type"
                  type="text"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                  v-model="formData.car_type"
                />
              </div>
              <div className="mb-4 mx-1">
                <label class="dark:text-gray-200" for="pin">
                  {{ $t("year") }}</label
                >
                <input
                  id="year"
                  type="number"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                  v-model="formData.year"
                />
              </div>
              <div className="mb-4 mx-1">
                <label class="dark:text-gray-200" for="pin">
                  {{ $t("color") }}</label
                >
                <input
                  id="car_color"
                  type="text"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                  v-model="formData.car_color"
                />
              </div>

              <div className="mb-4 mx-1">
                <label class="dark:text-gray-200" for="pin">
                  {{ $t("vin") }}</label
                >
                <input
                  id="vin"
                  type="text"
                  @change="check_vin(formData.vin)"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                  v-model="formData.vin"
                />
                <div class="text-red-700" v-if="showErrorVin">
                  رقم الشاصي مستخدم
                </div>
              </div>
              <div className="mb-4 mx-1">
                <label class="dark:text-gray-200" for="car_number">
                  {{ $t("car_number") }}</label
                >
                <input
                  id="car_number"
                  type="number"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                  v-model="formData.car_number"
                />
              </div>
              <div className="mb-4 mx-1">
                <label class="dark:text-gray-200" for="expenses">
                  {{ $t("expenses") }}</label
                >
                <input
                  id="expenses"
                  type="number"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                  v-model="formData.expenses_s"
                />
              </div>
              <div className="mb-4 mx-1">
                <label class="dark:text-gray-200" for="dinar_s">
                  {{ $t("dinar") }}</label
                >
                <input
                  id="dinar_s"
                  type="number"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                  v-model="formData.dinar_s"
                />
              </div>
              <div className="mb-4 mx-1">
                <label class="dark:text-gray-200" for="dolar_price_s">
                  {{ $t("dolar_price") }}</label
                >
                <input
                  id="dolar_price_s"
                  @change="validateExchangeRate"
                  type="number"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                  v-model="formData.dolar_price_s"
                />
                <div v-if="exchangeRateError" class="text-red-500">
                          مطلوب رقم من 6 خانة فقط
                </div>
              </div>
              <div className="mb-4 mx-1">
                <label class="dark:text-gray-200" for="shipping_dolar_s">
                  {{ $t("shipping_dolar") }}</label
                >
                <input
                  id="shipping_dolar"
                  type="number"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                  v-model="formData.shipping_dolar_s"
                />
              </div>
              <div className="mb-4 mx-1">
                <label class="dark:text-gray-200" for="coc_dolar_s">
                  {{ $t("coc_dolar") }}</label
                >
                <input
                  id="coc_dolar_s"
                  type="number"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                  v-model="formData.coc_dolar_s"
                />
              </div>
              <div className="mb-4 mx-1">
                <label class="dark:text-gray-200" for="checkout_s">
                  {{ $t("checkout") }}</label
                >
                <input
                  id="checkout_s"
                  type="number"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                  v-model="formData.checkout_s"
                />
              </div>
              <!-- <div className="mb-4 mx-1">
                <label class="dark:text-gray-200" for="paid">
                  {{ $t("paid") }}</label
                >
                <input
                  id="paid"
                  type="number"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                  v-model="formData.paid"
                />
              </div> -->
              <div className="mb-4 mx-1">
                <label class="dark:text-gray-200" for="date">
                  {{ $t("date") }}</label
                >
                <input
                  id="date"
                  type="date"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                  v-model="formData.date"
                />
              </div>
            </div>

            <div className="mb-4 mx-1">
              <label class="dark:text-gray-200" for="note">
                {{$t("note")}}
              </label>
              <input
                id="note"
                type="text"
                class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                v-model="formData.note"
              />
            </div>
          </div>

          <div class="modal-footer my-2">
    

            <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-1 gap-1 lg:gap-2">
              <div class="mb-4">
                <label class="form-label">الصور</label>
                <div class="mt-3">
                    <Uploader 
                        :server="'/api/carsAnnualUpload?img_type=contract&carId='+formData.id"
                        :is-invalid="errors?.media ? true : false"
                        @change="changeMedia"
                        @initMedia="media"
                        location="/public/uploadsResized"
                        :media="formData.car_images"
                        @add="addMedia"
                        @remove="removeMedia"
                    />
                </div>
                <p v-if="errors?.media" class="text-danger">{{ errors?.media[0] }}</p>
            </div>
             </div>

             <div class="flex flex-row">
              <div class="basis-1/2 px-4">
                <button
                  class="modal-default-button py-3 bg-gray-500 rounded"
                  @click="$emit('close')"
                >
                  {{ $t("cancel") }}
                </button>
              </div>
              <div class="basis-1/2 px-4">
                <button
                  class="modal-default-button py-3 bg-rose-500 rounded col-6"
                  @click="
                    formData.date = formData.date
                      ? formData.date
                      : getTodayDate();
                    $emit('a', formData);
                    formData = '';
                  "
                  :disabled="(!formData.client_id)&&(!formData.client_name)">
                  {{ $t("yes") }}
                </button>
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
  padding: 20px 30px;
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