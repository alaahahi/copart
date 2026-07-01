<script setup>
import { ref, computed, watch } from 'vue';
import { buildWalletTransactionNote } from '@/utils/walletTransactionNote';

const emit = defineEmits(['a', 'close']);

const props = defineProps({
  show: Boolean,
  boxes: Array,
  sum_transactions: Intl,
  sum_transactions_dinar: Intl,
  tagOptions: { type: Array, default: () => [] },
  driverSuggestions: { type: Array, default: () => [] },
  showExtendedFields: { type: Boolean, default: false },
  showTagSelect: { type: Boolean, default: false },
});

const noteManuallyEdited = ref(false);

const form = ref({
  id: props.boxes?.id,
  date: getTodayDate(),
  cars_count: '',
  cmr: '',
  driver_name: '',
  entry_date: '',
  tag: '',
  note: '',
});

const shouldAutoComposeNote = computed(
  () => props.showExtendedFields || props.showTagSelect
);

const composedNote = computed(() => {
  if (!shouldAutoComposeNote.value) {
    return '';
  }
  return buildWalletTransactionNote(form.value);
});

watch(composedNote, (val) => {
  if (!shouldAutoComposeNote.value || noteManuallyEdited.value) {
    return;
  }
  form.value.note = val;
});

watch(
  () => props.show,
  (open) => {
    if (open) {
      noteManuallyEdited.value = false;
    }
  }
);

function onNoteInput() {
  noteManuallyEdited.value = true;
}

function submitForm() {
  const payload = { ...form.value };
  if (shouldAutoComposeNote.value) {
    const built = buildWalletTransactionNote(payload);
    if (built) {
      payload.note = built;
    }
  }
  emit('a', payload);
  restform();
}

function getTodayDate() {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const day = String(today.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

const restform = () => {
  noteManuallyEdited.value = false;
  form.value = {
    id: props.boxes?.id,
    date: getTodayDate(),
    cars_count: '',
    cmr: '',
    driver_name: '',
    entry_date: '',
    tag: '',
    note: '',
  };
};

</script>
  
  <template>  
    <Transition name="modal">
      <div v-if="show" class="modal-mask">
        <div class="modal-wrapper">
          <div class="modal-container bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
            <div class="modal-header text-gray-900 dark:text-gray-100">
              <slot name="header"></slot>
            </div>
            <div class="modal-body text-gray-800 dark:text-gray-200">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        <div className="mb-4 mx-5">
                        <label for="amountDollar" class="text-gray-800 dark:text-gray-100">المبلغ بالدولار</label>
                        <input
                          id="amountDollar"
                          type="number"
                          class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                          v-model="form.amountDollar" />
                        </div>
                        <div className="mb-4 mx-5">
                        <label for="amountDinar" class="text-gray-800 dark:text-gray-100">المبلغ بالدينار العراقي</label>
                        <input
                          id="amountDinar"
                          type="number"
                          class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                          v-model="form.amountDinar" />
                        </div>
                        <div className="mb-4 mx-5">
                          <label for="card" class="text-gray-800 dark:text-gray-100">التاريخ</label>
                          <input
                          id="card"
                          type="date"
                          class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                          v-model="form.date"   />
                        </div>

                        <div v-if="showTagSelect" className="mb-4 mx-5 lg:col-span-2">
                          <label for="tag_select" class="text-gray-800 dark:text-gray-100">التاغ</label>
                          <input v-if="!tagOptions.length" id="tag_select" type="text" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm" v-model="form.tag" placeholder="اختياري" />
                          <select v-else id="tag_select" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm" v-model="form.tag">
                            <option value="">— بدون تاغ —</option>
                            <option v-for="t in tagOptions" :key="t.id" :value="t.name">{{ t.name }}</option>
                          </select>
                        </div>

                        <template v-if="showExtendedFields">
                          <div className="mb-4 mx-5">
                            <label for="driver_name" class="text-gray-800 dark:text-gray-100">اسم السائق</label>
                            <input
                              id="driver_name"
                              type="text"
                              list="wallet_driver_suggestions"
                              autocomplete="off"
                              class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm"
                              v-model="form.driver_name"
                            />
                            <datalist id="wallet_driver_suggestions">
                              <option v-for="name in driverSuggestions" :key="name" :value="name" />
                            </datalist>
                          </div>
                          <div className="mb-4 mx-5">
                            <label for="cmr" class="text-gray-800 dark:text-gray-100">رقم CMR</label>
                            <input id="cmr" type="text" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm" v-model="form.cmr" />
                          </div>
                          <div className="mb-4 mx-5">
                            <label for="cars_count" class="text-gray-800 dark:text-gray-100">عدد السيارات</label>
                            <input id="cars_count" type="number" min="0" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm" v-model="form.cars_count" />
                          </div>
                          <div className="mb-4 mx-5">
                            <label for="entry_date" class="text-gray-800 dark:text-gray-100">تاريخ الدخول</label>
                            <input id="entry_date" type="date" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded-md shadow-sm" v-model="form.entry_date" />
                          </div>
                        </template>

                        <div className="mb-4 mx-5" :class="showExtendedFields ? 'lg:col-span-2' : ''">
                        <label for="note" class="text-gray-800 dark:text-gray-100">الوصف / ملاحظة</label>
                        <input
                          id="note"
                          type="text"
                          class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                          v-model="form.note"
                          @input="onNoteInput" />
                        <p v-if="shouldAutoComposeNote && composedNote" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                          يُحدَّث تلقائياً من بيانات السائق والشحنة والتاغ
                        </p>
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
                <button class="modal-default-button py-3  bg-rose-500 rounded col-6" @click="submitForm">نعم</button>
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
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 12px;
    overflow-y: auto;
    transition: opacity 0.3s ease;
  }
  
  .modal-wrapper {
    width: 100%;
    max-width: min(720px, 96vw);
    margin: auto;
  }
  
  .modal-container {
    width: 100%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    margin: 0px auto;
    padding: 28px 32px;
    padding-bottom: 24px;
    border-radius: 12px;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.25);
    transition: all 0.3s ease;
  }
  
  .modal-header h3 {
    margin-top: 0;
    color: #42b983;
  }
  
  .modal-body {
    margin: 20px 0;
    overflow-y: auto;
    flex: 1;
    min-height: 0;
    -webkit-overflow-scrolling: touch;
  }

  @media (max-width: 768px) {
    .modal-mask {
      padding: 8px;
      align-items: flex-start;
    }

    .modal-container {
      padding: 16px;
      max-height: 95vh;
    }

    .modal-body .mx-5 {
      margin-left: 0.5rem;
      margin-right: 0.5rem;
    }
  }
  
  .modal-footer {
    flex-shrink: 0;
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