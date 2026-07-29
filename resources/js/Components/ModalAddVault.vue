<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
  show: Boolean,
  formData: {
    type: Object,
    default: () => ({}),
  },
  mode: {
    type: String,
    default: 'create', // create | edit
  },
});

const emit = defineEmits(['close', 'a']);

const vaultTypes = [
  { value: 'cash', label: 'نقد / صندوق' },
  { value: 'bank', label: 'بنك' },
  { value: 'safe', label: 'خزنة' },
];

const defaultForm = () => ({
  vault_id: null,
  name: '',
  code: '',
  type: 'cash',
  show_in_accounting: true,
  notes: '',
});

const form = ref(defaultForm());
const saving = ref(false);
const errorMsg = ref('');

const isEdit = computed(() => props.mode === 'edit');

watch(
  () => props.show,
  (isOpen) => {
    if (isOpen) {
      errorMsg.value = '';
      saving.value = false;
      form.value = {
        ...defaultForm(),
        ...(props.formData || {}),
        type: (props.formData && props.formData.type) || (props.formData && props.formData.vault_type) || 'cash',
        show_in_accounting: props.formData && props.formData.show_in_accounting !== undefined
          ? !!props.formData.show_in_accounting
          : true,
        vault_id: props.formData?.vault_id || props.formData?.id || null,
        notes: props.formData?.notes || '',
        code: props.formData?.vault_code || props.formData?.code || '',
      };
    }
  },
  { immediate: true }
);

const canSubmit = () => !!(form.value.name && form.value.name.trim()) && !saving.value;

const submit = () => {
  if (!canSubmit()) return;
  errorMsg.value = '';
  const payload = {
    name: form.value.name.trim(),
    type: form.value.type || 'cash',
    show_in_accounting: !!form.value.show_in_accounting,
    notes: form.value.notes ? String(form.value.notes).trim() : null,
  };
  if (form.value.code && String(form.value.code).trim()) {
    payload.code = String(form.value.code).trim();
  }
  if (isEdit.value) {
    payload.vault_id = form.value.vault_id;
  }
  emit('a', payload);
};

const close = () => emit('close');

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
      :aria-labelledby="isEdit ? 'edit-vault-title' : 'add-vault-title'"
      @click.self="close"
    >
      <div class="erp-modal-panel">
        <header class="erp-modal-header">
          <div class="erp-modal-header-text">
            <p class="erp-modal-eyebrow">قاصات النظام</p>
            <h2 :id="isEdit ? 'edit-vault-title' : 'add-vault-title'" class="erp-modal-title">
              {{ isEdit ? 'تعديل قاصة' : 'إضافة قاصة' }}
            </h2>
            <p class="erp-modal-subtitle">
              {{ isEdit
                ? 'تحديث بيانات القاصة النقدية وربطها بدليل الحسابات'
                : 'بدون محفظة — حساب نقدي في دليل الحسابات' }}
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
            <label class="erp-label" for="vault-name">اسم القاصة</label>
            <input
              id="vault-name"
              v-model="form.name"
              type="text"
              placeholder="مثال: مصاريف الحدود"
              class="erp-input"
              @keyup.enter="submit"
            />
          </div>

          <div class="erp-field-grid">
            <div class="erp-field">
              <label class="erp-label" for="vault-type">النوع</label>
              <select id="vault-type" v-model="form.type" class="erp-input">
                <option v-for="t in vaultTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
              </select>
            </div>

            <div class="erp-field">
              <label class="erp-label" for="vault-code">
                الرمز <span class="erp-optional">(اختياري — يُولَّد تلقائياً)</span>
              </label>
              <input
                id="vault-code"
                v-model="form.code"
                type="text"
                dir="ltr"
                placeholder="border-expenses"
                class="erp-input"
                :disabled="isEdit && form.code === 'mainBox'"
              />
            </div>
          </div>

          <div class="erp-toggle-row">
            <div class="erp-toggle-text">
              <span class="erp-toggle-title">عرض في المحاسبة</span>
              <span class="erp-toggle-hint">يظهر اختصاراً برتقالياً في صفحة المحاسبة لفتح دفتر القاصة</span>
            </div>
            <label class="erp-switch" :title="form.show_in_accounting ? 'معروضة في المحاسبة' : 'مخفية عن اختصارات المحاسبة'">
              <input type="checkbox" role="switch" v-model="form.show_in_accounting" />
              <span class="erp-switch-track" aria-hidden="true">
                <span class="erp-switch-thumb" />
              </span>
            </label>
          </div>

          <div class="erp-field">
            <label class="erp-label" for="vault-notes">ملاحظات <span class="erp-optional">(اختياري)</span></label>
            <textarea
              id="vault-notes"
              v-model="form.notes"
              rows="2"
              class="erp-input erp-textarea"
              placeholder="وصف مختصر للقاصة"
            />
          </div>
        </div>

        <footer class="erp-modal-footer">
          <button type="button" class="erp-btn erp-btn--ghost" @click="close">إلغاء</button>
          <button type="button" class="erp-btn erp-btn--primary" :disabled="!canSubmit()" @click="submit">
            {{ isEdit ? 'حفظ التعديلات' : 'إنشاء القاصة' }}
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
  z-index: 9998;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(2, 6, 23, 0.72);
  backdrop-filter: blur(4px);
}

.erp-modal-panel {
  width: min(100%, 42rem); /* ~672px — max-w-2xl */
  max-height: min(92vh, 40rem);
  overflow: auto;
  background: #0f172a;
  color: #f8fafc;
  border: 1px solid #334155;
  border-radius: 1rem;
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.45);
}

.erp-modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1rem 1.25rem 0.85rem;
  border-bottom: 1px solid #1e293b;
  background: linear-gradient(180deg, #1e3a5f 0%, #0f172a 100%);
}

.erp-modal-eyebrow {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  color: #93c5fd;
}

.erp-modal-title {
  margin: 0.25rem 0 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: #f8fafc;
  line-height: 1.3;
}

.erp-modal-subtitle {
  margin: 0.25rem 0 0;
  font-size: 0.8125rem;
  color: #cbd5e1;
  line-height: 1.45;
}

.erp-modal-close {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 0.75rem;
  border: 1px solid #334155;
  background: #1e293b;
  color: #e2e8f0;
  cursor: pointer;
  transition: background 200ms ease;
}

.erp-modal-close:hover {
  background: #334155;
}

.erp-modal-body {
  padding: 1rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.erp-error {
  margin: 0;
  padding: 0.65rem 0.9rem;
  border-radius: 0.75rem;
  background: #450a0a;
  border: 1px solid #9f1239;
  color: #fecdd3;
  font-size: 0.875rem;
}

.erp-field-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
}

@media (min-width: 640px) {
  .erp-field-grid {
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem 1rem;
  }
}

.erp-field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.erp-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #f1f5f9;
}

.erp-optional {
  font-weight: 400;
  color: #94a3b8;
  font-size: 0.75rem;
}

.erp-input {
  width: 100%;
  min-height: 2.5rem;
  padding: 0.55rem 0.8rem;
  border-radius: 0.75rem;
  border: 1px solid #475569;
  background: #020617;
  color: #f8fafc;
  font-size: 0.95rem;
  transition: border-color 200ms ease, box-shadow 200ms ease;
}

.erp-textarea {
  min-height: 3.25rem;
  resize: vertical;
}

.erp-input:focus {
  outline: none;
  border-color: #38bdf8;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.25);
}

.erp-input:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.erp-toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.7rem 0.9rem;
  border-radius: 0.875rem;
  background: #1e293b;
  border: 1px solid #334155;
}

.erp-toggle-text {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.erp-toggle-title {
  font-size: 0.9rem;
  font-weight: 700;
  color: #f1f5f9;
}

.erp-toggle-hint {
  font-size: 0.75rem;
  line-height: 1.4;
  color: #94a3b8;
}

.erp-switch {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  cursor: pointer;
  user-select: none;
}

.erp-switch input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.erp-switch-track {
  width: 2.75rem;
  height: 1.5rem;
  border-radius: 999px;
  background: #475569;
  position: relative;
  transition: background 0.15s ease;
  display: inline-block;
}

.erp-switch-thumb {
  position: absolute;
  top: 0.15rem;
  left: 0.15rem;
  width: 1.2rem;
  height: 1.2rem;
  border-radius: 999px;
  background: #fff;
  transition: transform 0.15s ease;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);
}

.erp-switch input:checked + .erp-switch-track {
  background: #16a34a;
}

.erp-switch input:checked + .erp-switch-track .erp-switch-thumb {
  transform: translateX(1.25rem);
}

.erp-modal-footer {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  width: 100%;
  padding: 0.85rem 1.25rem 1.1rem;
  border-top: 1px solid #1e293b;
  background: #020617;
}

.erp-modal-footer .erp-btn {
  width: 100%;
  min-width: 0;
}

.erp-btn {
  min-height: 2.75rem;
  padding: 0.7rem 1.25rem;
  border-radius: 0.75rem;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  transition: background 200ms ease, opacity 200ms ease, transform 150ms ease;
}

.erp-btn:active:not(:disabled) {
  transform: scale(0.98);
}

.erp-btn--ghost {
  border: 1px solid #475569;
  background: transparent;
  color: #e2e8f0;
}

.erp-btn--ghost:hover {
  background: #1e293b;
}

.erp-btn--primary {
  border: 1px solid #047857;
  background: #059669;
  color: #ffffff;
}

.erp-btn--primary:hover:not(:disabled) {
  background: #047857;
}

.erp-btn--primary:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.erp-modal-enter-active,
.erp-modal-leave-active {
  transition: opacity 220ms ease;
}

.erp-modal-enter-active .erp-modal-panel,
.erp-modal-leave-active .erp-modal-panel {
  transition: transform 220ms ease, opacity 220ms ease;
}

.erp-modal-enter-from,
.erp-modal-leave-to {
  opacity: 0;
}

.erp-modal-enter-from .erp-modal-panel,
.erp-modal-leave-to .erp-modal-panel {
  transform: translateY(0.5rem) scale(0.98);
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .erp-modal-enter-active,
  .erp-modal-leave-active,
  .erp-modal-enter-active .erp-modal-panel,
  .erp-modal-leave-active .erp-modal-panel,
  .erp-btn,
  .erp-modal-close,
  .erp-input {
    transition: none !important;
  }
}
</style>
