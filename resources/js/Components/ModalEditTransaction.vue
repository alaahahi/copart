<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  show: Boolean,
  transaction: Object,
  tagOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const form = ref({
  description: '',
  tag: '',
  cars_count: '',
  cmr: '',
  driver_name: '',
  entry_date: '',
});

const saving = ref(false);
const error = ref('');

watch(
  () => [props.show, props.transaction],
  () => {
    if (!props.show || !props.transaction) return;
    const t = props.transaction;
    const d = t.details || {};
    form.value = {
      description: t.description || '',
      tag: t.tag || '',
      cars_count: d.cars_count ?? '',
      cmr: d.cmr ?? '',
      driver_name: d.driver_name ?? '',
      entry_date: d.entry_date ?? '',
    };
    error.value = '';
  },
  { immediate: true }
);

async function save() {
  saving.value = true;
  error.value = '';
  try {
    const payload = {
      transaction_id: props.transaction.id,
      description: form.value.description.trim() || undefined,
      tag: form.value.tag ? form.value.tag.trim() : null,
      details: {
        cars_count: form.value.cars_count || undefined,
        cmr: form.value.cmr || undefined,
        driver_name: form.value.driver_name || undefined,
        entry_date: form.value.entry_date || undefined,
      },
    };
    const { data } = await axios.post('/api/updateTransaction', payload);
    Object.assign(props.transaction, {
      description: data.transaction.description,
      tag: data.transaction.tag,
      details: data.transaction.details,
    });
    emit('saved', data.transaction);
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
    <div v-if="show" class="modal-mask">
      <div class="modal-wrapper max-h-[90vh]">
        <div class="modal-container bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 max-h-[90vh] overflow-auto">
          <div class="modal-header text-center py-4 dark:text-gray-300">تعديل الحركة</div>
          <div class="modal-body px-5 pb-4">
            <p v-if="error" class="text-red-600 dark:text-red-400 text-sm mb-2">{{ error }}</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div class="md:col-span-2">
                <label class="block text-sm font-medium dark:text-gray-200">الوصف</label>
                <input v-model="form.description" type="text" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium dark:text-gray-200">التاغ</label>
                <input v-if="!tagOptions.length" v-model="form.tag" type="text" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm" />
                <select v-else v-model="form.tag" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm">
                  <option value="">— بدون تاغ —</option>
                  <option v-for="t in tagOptions" :key="t.id" :value="t.name">{{ t.name }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium dark:text-gray-200">عدد السيارات</label>
                <input v-model="form.cars_count" type="number" min="0" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium dark:text-gray-200">رقم CMR</label>
                <input v-model="form.cmr" type="text" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium dark:text-gray-200">اسم السائق</label>
                <input v-model="form.driver_name" type="text" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium dark:text-gray-200">تاريخ الدخول</label>
                <input v-model="form.entry_date" type="date" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-md shadow-sm" />
              </div>
            </div>
          </div>
          <div class="modal-footer flex gap-2 justify-center py-4 border-t dark:border-gray-700">
            <button type="button" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600" @click="$emit('close')">إلغاء</button>
            <button type="button" class="px-4 py-2 bg-rose-500 text-white rounded hover:bg-rose-600 disabled:opacity-50" :disabled="saving" @click="save">{{ saving ? 'جاري الحفظ...' : 'حفظ' }}</button>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-mask { position: fixed; z-index: 9998; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); display: table; transition: opacity 0.3s ease; }
.modal-wrapper { display: table-cell; vertical-align: middle; }
.modal-container { width: 90%; max-width: 500px; margin: 0 auto; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.33); }
.modal-header { font-weight: 700; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-container, .modal-leave-to .modal-container { transform: scale(0.95); }
</style>
