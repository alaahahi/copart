<script setup>
import { ref, computed } from "vue";
import axios from 'axios';
import Uploader  from 'vue-media-upload';
import { useToast } from "vue-toastification";
import { ModelListSelect } from "vue-search-select"
import "vue-search-select/dist/VueSearchSelect.css"
const toast = useToast();

const props = defineProps({
  show: Boolean,
  formData: Object,
  client: Array,
  saveCar: Boolean,
});
let showClient = ref(false);


function removeMedia(removedImage){
              axios.get('/api/carsAnnualImageDel?name='+removedImage.name)
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
               تعديل معلومات
              </h2>
            </slot>
          </div>
          <div class="modal-body">
            <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-1 gap-1 lg:gap-2">
              <div class="mb-4 mx-1">
                <label class="dark:text-gray-200" for="color_id">
                 صاحب السيارة
                </label>
                <div class="relative">
                  <ModelListSelect
                  v-if="!showClient"
                  optionValue="id"
                  optionText="name"
                  v-model="formData.client_id"
                  :list="client"
                  placeholder="تحديد صاحب السيارة">
                </ModelListSelect>
                  <button
                    type="button"
                    @click="
                      showClient = true;
                      formData.client_name = '';
                      formData.client_id='';
                    "
                    v-if="!showClient"
                    class="absolute left-0 top-0 h-full px-3 py-2 font-bold text-white bg-green-500 rounded-tl-lg rounded-bl-lg"
                  >
                   إضافة صاحب سيارة
                  </button>
                </div>
                <div class="relative">
                  <input
                    id="note"
                    v-if="showClient"
                    type="text"
                    class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                    v-model="formData.client_name"
                  />
                  <button
                    type="button"
                    @click="
                      showClient = false;
                      formData.client = '';
                    "
                    v-if="showClient"
                    class="absolute left-0 top-0 h-full px-3 py-2 font-bold text-white bg-pink-500 rounded-tl-lg rounded-bl-lg"
                  >
                  تحديد صاحب السيارة

                  </button>
                </div>
              </div>
              <div className="mb-4 mx-1" v-if="showClient">
                <label class="dark:text-gray-200" for="number">
                {{ $t("phoneNumber") }}
              </label>
                <input
                  id="number"
                  type="number"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                  v-model="formData.client_phone"
                />
              </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-1 lg:gap-2">  
              <div className="mb-4 mx-1">
                <label class="dark:text-gray-200" for="car_number">
                  {{ $t("car_number") }}</label
                >
                <input
                  id="car_number"
                  type="text"
                  class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm dark:bg-gray-700 dark:text-gray-200 dark:border-gray-900"
                  v-model="formData.car_number"
                />
              </div>

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
            </div>
            <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-1 gap-1 lg:gap-2">

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
           
            <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-1 gap-1 lg:gap-2">
              <div class="m-4">
                <label class="form-label">الصور</label>
                <div>
                    <Uploader 
                        :server="'/api/carsAnnualUpload?carId='+formData.id"
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
          </div>

          <div class="modal-footer my-2">
            <div class="flex flex-row">
              <div class="basis-1/2 px-4">
                <button
                  class="modal-default-button py-3 bg-gray-500 rounded"
                  @click="$emit('close')"
                >
                  اغلاق
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
                    showClient=false;
                  "
                  :disabled="(!formData.client_id)&&(!formData.client_name)">
                حفظ المعلومات    
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
    .ui.fluid.search.selection.dropdown{
    justify-content: revert;
    display: flex;
    min-height: 40px;
  }
  .ui.dropdown .menu .selected.item{
    background-color: #e012035d;
  }
  .ui.dropdown .menu>.item {
    text-align: right;
  }

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