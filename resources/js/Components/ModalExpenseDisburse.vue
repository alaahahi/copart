<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
  show: Boolean,
  cashVaults: { type: Array, default: () => [] },
  accountName: { type: String, default: '' },
});

const emit = defineEmits(['close', 'save']);

const defaultForm = () => ({
  cash_vault_id: '',
  amount: '',
  currency: '$',
  memo: '',
  entry_date: new Date().toISOString().slice(0, 10),
});

const form = ref(defaultForm());
const saving = ref(false);
const errorMsg = ref('');

watch(
  () => props.show,
  (isOpen) => {
    if (isOpen) {
      errorMsg.value = '';
      saving.value = false;
      form.value = defaultForm();
      if (props.cashVaults?.length === 1) {
        form.value.cash_vault_id = props.cashVaults[0].vault_id;
      }
      if (props.accountName) {
        form.value.memo = `صرف مصروف — ${props.accountName}`;
      }
    }
  },
  { immediate: true }
);

const selectedVault = computed(() =>
  props.cashVaults.find((v) => Number(v.vault_id) === Number(form.value.cash_vault_id))
);

const canSubmit = () =>
  !!form.value.cash_vault_id
  && Number(form.value.amount) > 0
  && !!(form.value.memo && form.value.memo.trim())
  && !saving.value;

const submit = () => {
  if (!canSubmit()) return;
  errorMsg.value = '';
  emit('save', {
    cash_vault_id: Number(form.value.cash_vault_id),
    amount: Number(form.value.amount),
    currency: form.value.currency,
    memo: form.value.memo.trim(),
    entry_date: form.value.entry_date || null,
  });
};

const close = () => {
  if (saving.value) return;
  emit('close');
};

defineExpose({
  setSaving(v) { saving.value = !!v; },
  setError(msg) { errorMsg.value = msg || ''; },
});
</script>

<template>
  <Transition name="erp-modal">
    <div
      v-if="show"
      class="erp-modal-mask"
      role="dialog"
      aria-modal="true"
      aria-labelledby="expense-disburse-title"
      @click.self="close"
    >
      <div class="erp-modal-panel">
        <header class="erp-modal-header">
          <div class="erp-modal-header-text">
            <p class="erp-modal-eyebrow">قيد محاسبي صحيح</p>
            <h2 id="expense-disburse-title" class="erp-modal-title">صرف مصروف</h2>
            <p class="erp-modal-subtitle">
              مدين حساب المصروف · دائن القاصة النقدية — لا تحويل بين حسابات المصاريف
            </p>
          </div>
          <button type="button" class="erp-modal-close" aria-label="إغلاق" @click="close">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5" aria-hidden="true">
              <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
            </svg>
          </button>
        </header>

        <div class="erp-modal-body">
          <p v-if="errorMsg" class="erp-error">{{ errorMsg }}</p>

          <div class="erp-field">
            <label class="erp-label" for="disburse-vault">من القاصة النقدية</label>
            <select id="disburse-vault" v-model="form.cash_vault_id" class="erp-input">
              <option value="">اختر قاصة…</option>
              <option
                v-for="v in cashVaults"
                :key="v.vault_id"
                :value="v.vault_id"
              >
                {{ v.name }} ({{ Number(v.balance || 0).toLocaleString() }} $)
              </option>
            </select>
            <p v-if="selectedVault" class="erp-hint">
              رصيد القاصة: {{ Number(selectedVault.balance || 0).toLocaleString() }} $
            </p>
          </div>

          <div class="erp-field-grid">
            <div class="erp-field">
              <label class="erp-label" for="disburse-amount">المبلغ</label>
              <input
                id="disburse-amount"
                v-model="form.amount"
                type="number"
                min="0.01"
                step="0.01"
                class="erp-input"
                dir="ltr"
              />
            </div>
            <div class="erp-field">
              <label class="erp-label" for="disburse-currency">العملة</label>
              <select id="disburse-currency" v-model="form.currency" class="erp-input">
                <option value="$">دولار</option>
                <option value="IQD">دينار</option>
              </select>
            </div>
          </div>

          <div class="erp-field">
            <label class="erp-label" for="disburse-date">التاريخ</label>
            <input id="disburse-date" v-model="form.entry_date" type="date" class="erp-input" dir="ltr" />
          </div>

          <div class="erp-field">
            <label class="erp-label" for="disburse-memo">البيان</label>
            <textarea id="disburse-memo" v-model="form.memo" rows="2" class="erp-input" />
          </div>
        </div>

        <footer class="erp-modal-footer">
          <button type="button" class="erp-btn erp-btn-ghost" :disabled="saving" @click="close">إلغاء</button>
          <button type="button" class="erp-btn erp-btn-primary" :disabled="!canSubmit()" @click="submit">
            {{ saving ? 'جاري التسجيل…' : 'تأكيد الصرف' }}
          </button>
        </footer>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.erp-modal-mask {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(2, 6, 23, 0.72);
}

.erp-modal-panel {
  width: min(30rem, 100%);
  max-height: min(92vh, 44rem);
  overflow: auto;
  border-radius: 0.85rem;
  background: #0f172a;
  border: 1px solid #334155;
  color: #f1f5f9;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.55);
}

.erp-modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 1.1rem 1.25rem 0.75rem;
  border-bottom: 1px solid #334155;
}

.erp-modal-eyebrow {
  margin: 0;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #94a3b8;
}

.erp-modal-title {
  margin: 0.2rem 0 0;
  font-size: 1.15rem;
  font-weight: 800;
  color: #fff;
}

.erp-modal-subtitle {
  margin: 0.35rem 0 0;
  font-size: 0.8rem;
  color: #cbd5e1;
  line-height: 1.45;
}

.erp-modal-close {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.45rem;
  border: 1px solid #475569;
  background: #1e293b;
  color: #e2e8f0;
  cursor: pointer;
}

.erp-modal-body {
  padding: 1rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.erp-field-grid {
  display: grid;
  grid-template-columns: 1fr 7rem;
  gap: 0.65rem;
}

.erp-field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.erp-label {
  font-size: 0.8rem;
  font-weight: 700;
  color: #e2e8f0;
}

.erp-hint {
  margin: 0;
  font-size: 0.75rem;
  color: #94a3b8;
}

.erp-input {
  width: 100%;
  border-radius: 0.5rem;
  border: 1px solid #475569;
  background: #020617;
  color: #fff;
  padding: 0.55rem 0.75rem;
  font-size: 0.9rem;
  outline: none;
}

.erp-input:focus {
  border-color: #34d399;
  box-shadow: 0 0 0 2px rgba(52, 211, 153, 0.25);
}

.erp-error {
  margin: 0;
  padding: 0.55rem 0.75rem;
  border-radius: 0.5rem;
  background: rgba(127, 29, 29, 0.45);
  border: 1px solid #9f1239;
  color: #fecdd3;
  font-size: 0.85rem;
  font-weight: 600;
}

.erp-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  padding: 0.85rem 1.25rem 1.15rem;
  border-top: 1px solid #334155;
}

.erp-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.4rem;
  padding: 0.45rem 1rem;
  border-radius: 0.5rem;
  font-weight: 700;
  font-size: 0.875rem;
  border: 0;
  cursor: pointer;
}

.erp-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.erp-btn-ghost {
  background: #334155;
  color: #e2e8f0;
}

.erp-btn-primary {
  background: #059669;
  color: #fff;
}

.erp-btn-primary:hover:not(:disabled) {
  background: #047857;
}

.erp-modal-enter-active,
.erp-modal-leave-active {
  transition: opacity 0.15s ease;
}
.erp-modal-enter-from,
.erp-modal-leave-to {
  opacity: 0;
}

@media (max-width: 480px) {
  .erp-field-grid {
    grid-template-columns: 1fr;
  }
}
</style>
