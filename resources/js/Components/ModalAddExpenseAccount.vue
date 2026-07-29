<script setup>
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
  show: Boolean,
  suggestExpenseCode: { type: String, default: '5101' },
  suggestCommissionCode: { type: String, default: '5201' },
  expenseParentId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'save']);

const defaultForm = () => ({
  name_ar: '',
  code: '',
  kind: 'expense', // expense | commission — both COA type=expense
  notes: '',
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
      form.value.code = props.suggestExpenseCode || '5101';
    }
  },
  { immediate: true }
);

watch(
  () => form.value.kind,
  (kind) => {
    form.value.code = kind === 'commission'
      ? (props.suggestCommissionCode || '5201')
      : (props.suggestExpenseCode || '5101');
  }
);

const title = computed(() =>
  form.value.kind === 'commission'
    ? t('add_commission_account_title')
    : t('add_expense_account_title')
);

const canSubmit = () =>
  !!(form.value.name_ar && form.value.name_ar.trim())
  && !!(form.value.code && form.value.code.trim())
  && !saving.value;

const submit = () => {
  if (!canSubmit()) return;
  errorMsg.value = '';
  const nameAr = form.value.name_ar.trim();
  const parentRaw = props.expenseParentId;
  const parentId = Number(parentRaw);
  const payload = {
    code: String(form.value.code).trim().toUpperCase(),
    name_ar: nameAr,
    name: nameAr,
    type: 'expense',
    currency: null,
    is_active: true,
    show_in_accounting: true,
  };
  // Only send a real integer parent; backend defaults expense/commission under 5100
  if (Number.isFinite(parentId) && parentId > 0) {
    payload.parent_id = parentId;
  }
  emit('save', payload);
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
      aria-labelledby="add-expense-account-title"
      @click.self="close"
    >
      <div class="erp-modal-panel">
        <header class="erp-modal-header">
          <div class="erp-modal-header-text">
            <p class="erp-modal-eyebrow">{{ $t('coa_eyebrow') }}</p>
            <h2 id="add-expense-account-title" class="erp-modal-title">{{ title }}</h2>
            <p class="erp-modal-subtitle">
              {{ $t('expense_account_modal_subtitle') }}
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
            <span class="erp-label">{{ $t('classification') }}</span>
            <div class="kind-row">
              <label class="kind-chip" :class="{ active: form.kind === 'expense' }">
                <input v-model="form.kind" type="radio" value="expense" class="sr-only" />
                {{ $t('expense_kind') }}
              </label>
              <label class="kind-chip" :class="{ active: form.kind === 'commission' }">
                <input v-model="form.kind" type="radio" value="commission" class="sr-only" />
                {{ $t('commission_kind') }}
              </label>
            </div>
          </div>

          <div class="erp-field">
            <label class="erp-label" for="exp-acc-name">{{ $t('name') }}</label>
            <input
              id="exp-acc-name"
              v-model="form.name_ar"
              type="text"
              :placeholder="form.kind === 'commission' ? $t('commission_kind') : $t('expense_kind')"
              class="erp-input"
              @keyup.enter="submit"
            />
          </div>

          <div class="erp-field">
            <label class="erp-label" for="exp-acc-code">{{ $t('account_code') }}</label>
            <input
              id="exp-acc-code"
              v-model="form.code"
              type="text"
              dir="ltr"
              class="erp-input"
              @keyup.enter="submit"
            />
          </div>
        </div>

        <footer class="erp-modal-footer">
          <button type="button" class="erp-btn erp-btn-ghost" :disabled="saving" @click="close">{{ $t('cancel') }}</button>
          <button type="button" class="erp-btn erp-btn-primary" :disabled="!canSubmit()" @click="submit">
            {{ saving ? $t('saving') : $t('create_account') }}
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
  width: min(28rem, 100%);
  max-height: min(90vh, 40rem);
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

.erp-input::placeholder {
  color: #94a3b8;
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

.kind-row {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.kind-chip {
  flex: 1;
  min-width: 6rem;
  text-align: center;
  padding: 0.55rem 0.75rem;
  border-radius: 0.5rem;
  border: 1px solid #475569;
  background: #1e293b;
  color: #cbd5e1;
  font-weight: 700;
  font-size: 0.85rem;
  cursor: pointer;
}

.kind-chip.active {
  background: #065f46;
  border-color: #059669;
  color: #ecfdf5;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
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
</style>
