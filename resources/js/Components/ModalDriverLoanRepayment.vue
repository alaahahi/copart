<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  show: Boolean,
  loanTransaction: Object,
});

const emit = defineEmits(['close', 'saved']);

const form = ref({
  amountDollar: '',
  amountDinar: '',
  date: getTodayDate(),
});

const saving = ref(false);
const error = ref('');

function getTodayDate() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

watch(() => props.show, (v) => { if (v) { form.value.date = getTodayDate(); error.value = ''; } });

async function save() {
  if (!form.value.amountDollar && !form.value.amountDinar) { error.value = 'المبلغ مطلوب'; return; }
  saving.value = true;
  error.value = '';
  try {
    await axios.post('/api/createDriverLoanRepayment', {
      parent_id: props.loanTransaction?.id,
      amountDollar: form.value.amountDollar ? parseFloat(form.value.amountDollar) : 0,
      amountDinar: form.value.amountDinar ? parseFloat(form.value.amountDinar) : 0,
      date: form.value.date,
    });
    emit('saved');
    emit('close');
  } catch (e) {
    error.value = e.response?.data?.message || e.message || 'حدث خطأ';
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <Transition name="modal">
    <div v-if="show && loanTransaction" class="modal-mask">
      <div class="modal-wrapper">
        <div class="modal-container bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
          <div class="modal-header text-center py-4 dark:text-gray-300">دفعة إرجاع قرض - {{ loanTransaction.details?.driver_name || 'سائق' }}</div>
          <div class="modal-body px-5 pb-4 space-y-3">
            <p v-if="error" class="text-red-600 dark:text-red-400 text-sm">{{ error }}</p>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium dark:text-gray-200">المبلغ بالدولار</label>
                <input v-model="form.amountDollar" type="number" min="0" step="0.01" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium dark:text-gray-200">المبلغ بالدينار</label>
                <input v-model="form.amountDinar" type="number" min="0" step="0.01" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium dark:text-gray-200">التاريخ</label>
              <input v-model="form.date" type="date" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm" />
            </div>
          </div>
          <div class="modal-footer flex gap-2 justify-center py-4 border-t dark:border-gray-700">
            <button type="button" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600" @click="$emit('close')">إلغاء</button>
            <button type="button" class="px-4 py-2 bg-rose-500 text-white rounded hover:bg-rose-600 disabled:opacity-50" :disabled="saving" @click="save">{{ saving ? 'جاري الحفظ...' : 'تسجيل الدفعة' }}</button>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-mask { position: fixed; z-index: 9998; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); display: table; transition: opacity 0.3s ease; }
.modal-wrapper { display: table-cell; vertical-align: middle; }
.modal-container { width: 90%; max-width: 380px; margin: 0 auto; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.33); }
.modal-header { font-weight: 700; }
</style>
