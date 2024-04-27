<script setup>
import { ref, watch } from "vue";
import axios from 'axios';
import Uploader  from 'vue-media-upload';
import { ModelListSelect } from "vue-search-select"
import { useToast } from "vue-toastification";

import "vue-search-select/dist/VueSearchSelect.css"

const props = defineProps({
  show: Boolean,
  formData: Object,
  client: Array,
  saveCar: Boolean,
});


function getTodayDate() {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, "0");
  const day = String(today.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

let carsidSelected = ref(0);
let showClient = ref(true);
let cars = ref([]);
watch(carsidSelected, (newValue, oldValue) => {
  axios.get('/api/getIndexCar', {
      params: {
        limit: 1000,
        user_id: newValue,
        car_have_expenses:0

      }
    })
    .then(response => {
              cars.value = response.data.data;
            })
    .catch(error => {
      console.error(error);
    })

});

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
    <div v-if="show" class="modal-mask" >
      <div class="modal-wrapper  max-h-[80vh]">
        <div class="modal-container dark:bg-gray-900 overflow-auto  max-h-[80vh]">
          <div class="modal-header">
            <slot name="header">
              <h2 class="text-center dark:text-gray-200">
                {{ $t("add_car") }}
              </h2>
            </slot>
          </div>
          <div class="modal-body ">
            <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-1 gap-1 lg:gap-2" v-if="!formData.id">
              <div class="mb-4 mx-1">
                <label class="dark:text-gray-200" for="color_id">
                 صاحب السيارة
                </label>
                <div class="relative">
                  <ModelListSelect
                  optionValue="id"
                  optionText="name"
                  v-model="carsidSelected"
                  :list="client"
                  placeholder="تحديد صاحب السيارة">
                </ModelListSelect>
                </div>
              </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-1 gap-1 lg:gap-2" v-if="cars[0]">  
              <div class="mb-4 mx-1">
                <label class="dark:text-gray-200" for="color_id">
                 اختر السيارة
                </label>
                <div class="relative">
                  <ModelListSelect
                  optionValue="id"
                  :customText="car => `${car.car_type} - ${car.car_color} -كاتي  ${car.car_number}-شانصى ${car.vin}`"
                  v-model="formData.carId"
                  :list="cars"
                  placeholder=" اختر السيارة">
                  </ModelListSelect>
                </div>
            
              </div>

            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-1 gap-1 lg:gap-2 mt-5" v-if="!saveCar">  
                <button
                  class="modal-default-button py-3 bg-blue-500 rounded col-6"
                  @click="formData.date = formData.date ? formData.date : getTodayDate(); $emit('a', formData);carsidSelected=0"
                  :disabled="(!carsidSelected)&&(!formData.carId)">
                  إضافة ومتابعة
                </button>
            </div>
          </div>
          <div class="modal-footer my-2">
            <div class="flex flex-row  m-auto">
               <button
                  class="modal-default-button py-3 bg-rose-500 rounded col-6"
                  @click=" $emit('close'); carsidSelected = ''">
                  إغلاق
                </button>
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