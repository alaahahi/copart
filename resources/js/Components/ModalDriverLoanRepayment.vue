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
    <div v-if="show && loanTransaction" class="modal-mask" role="dialog" aria-modal="true">
      <div class="modal-wrapper">
        <!-- Forced dark-safe panel (teleported / outside main CSS scope) -->
        <div class="modal-container border border-slate-600 bg-slate-900 text-slate-100">
          <div class="modal-header py-4 text-center text-white">
            دفعة إرجاع قرض - {{ loanTransaction.details?.driver_name || 'سائق' }}
          </div>
          <div class="modal-body space-y-3 px-5 pb-4">
            <p v-if="error" class="text-sm text-rose-400">{{ error }}</p>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-200">المبلغ بالدولار</label>
                <input
                  v-model="form.amountDollar"
                  type="number"
                  min="0"
                  step="0.01"
                  class="mt-1 block w-full rounded-md border border-slate-600 bg-slate-950 text-white placeholder-slate-400 shadow-sm focus:border-emerald-500 focus:ring-emerald-500/30"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-200">المبلغ بالدينار</label>
                <input
                  v-model="form.amountDinar"
                  type="number"
                  min="0"
                  step="0.01"
                  class="mt-1 block w-full rounded-md border border-slate-600 bg-slate-950 text-white placeholder-slate-400 shadow-sm focus:border-emerald-500 focus:ring-emerald-500/30"
                />
              </div>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-200">التاريخ</label>
              <input
                v-model="form.date"
                type="date"
                class="mt-1 block w-full rounded-md border border-slate-600 bg-slate-950 text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500/30"
              />
            </div>
          </div>
          <div class="modal-footer grid w-full grid-cols-2 gap-2 border-t border-slate-700 py-4">
            <button
              type="button"
              class="w-full rounded-lg border border-slate-600 bg-slate-800 px-4 py-2 font-semibold text-slate-200 hover:bg-slate-700"
              @click="$emit('close')"
            >
              إلغاء
            </button>
            <button
              type="button"
              class="w-full rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
              :disabled="saving"
              @click="save"
            >
              {{ saving ? 'جاري الحفظ...' : 'تسجيل الدفعة' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-mask { position: fixed; z-index: 9998; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(2, 6, 23, 0.7); display: table; transition: opacity 0.3s ease; }
.modal-wrapper { display: table-cell; vertical-align: middle; }
.modal-container { width: 90%; max-width: 380px; margin: 0 auto; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.33); }
.modal-header { font-weight: 700; }
</style>
