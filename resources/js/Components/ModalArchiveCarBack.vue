v<script>
export default {
    props: {
      show: Boolean,
      formData:Object
    }
  }
  </script>
  
  <template>
    <Transition name="modal">
      <div v-if="show" class="modal-mask ">
        <div class="modal-wrapper  max-h-[80vh]">
          <div class="modal-container dark:bg-gray-900 overflow-auto  max-h-[80vh]">
            <div class="modal-header">
              <slot name="header"></slot>
              <h2 class="text-center py-5 dark:text-white">نقل الى قيد العمل للسيارة 
                {{formData.car_type + ' شانص'+ formData.vin +' رقم'+formData.car_number }}</h2>
            </div>
            
            <div class="modal-footer my-2">
              <div class="flex flex-row">
                <div class="basis-1/2 px-4">             <button
                    class="modal-default-button py-3  bg-gray-500 rounded"
                    @click="$emit('close');"
                  >تراجع</button></div>
              <div class="basis-1/2 px-4">              <button
                    class="modal-default-button py-3  bg-rose-500 rounded col-6"
                    @click="$emit('a',formData);"
                  >نعم</button>
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
    margin-bottom: 25px;
    color: #000 !important;
    margin: 20px;
    padding-bottom: 20px;
    font-size: 24px;
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