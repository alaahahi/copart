<script setup>
import { computed, ref } from 'vue';

defineProps({
  show: Boolean,
  data: Array,
  accounts: Array,
  tagOptions: Array,
  showExtendedFields: Boolean,
  showTagSelect: Boolean,
  title: {
    type: String,
    default: 'وصل قبض',
  },
  subtitle: {
    type: String,
    default: 'إضافة مبلغ إلى الصندوق بالدولار أو الدينار',
  },
});

const emit = defineEmits(['close', 'a']);

function getTodayDate() {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const day = String(today.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

const form = ref({
  amountDollar: '',
  amountDinar: '',
  amountNote: '',
  date: getTodayDate(),
  tag: '',
});

const canSubmit = computed(
  () => !!(form.value.amountDollar || form.value.amountDinar)
);

const resetForm = () => {
  form.value = {
    amountDollar: '',
    amountDinar: '',
    amountNote: '',
    date: getTodayDate(),
    tag: '',
  };
};

const submit = () => {
  if (!canSubmit.value) return;
  emit('a', { ...form.value });
  resetForm();
};
</script>

<template>
  <Transition name="erp-modal">
    <div
      v-if="show"
      class="erp-modal-mask"
      role="dialog"
      aria-modal="true"
      aria-labelledby="receipt-modal-title"
      @click.self="emit('close')"
    >
      <div class="erp-modal-panel">
        <header class="erp-modal-header erp-modal-header--receipt">
          <div class="erp-modal-header-text">
            <p class="erp-modal-eyebrow">المحاسبة</p>
            <h2 id="receipt-modal-title" class="erp-modal-title">{{ title }}</h2>
            <p class="erp-modal-subtitle">{{ subtitle }}</p>
          </div>
          <button
            type="button"
            class="erp-modal-close"
            aria-label="إغلاق"
            @click="emit('close')"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5" aria-hidden="true">
              <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
            </svg>
          </button>
        </header>

        <div class="erp-modal-body">
          <section class="erp-field-group">
            <h3 class="erp-section-label">المبالغ</h3>
            <div class="erp-grid-2">
              <div class="erp-field">
                <label class="erp-label" for="receipt-usd">المبلغ بالدولار</label>
                <div class="erp-input-wrap">
                  <span class="erp-input-prefix" aria-hidden="true">$</span>
                  <input
                    id="receipt-usd"
                    v-model="form.amountDollar"
                    type="number"
                    min="0"
                    step="0.01"
                    inputmode="decimal"
                    placeholder="0"
                    class="erp-input erp-input--with-prefix"
                  />
                </div>
              </div>
              <div class="erp-field">
                <label class="erp-label" for="receipt-iqd">المبلغ بالدينار</label>
                <div class="erp-input-wrap">
                  <span class="erp-input-prefix" aria-hidden="true">د.ع</span>
                  <input
                    id="receipt-iqd"
                    v-model="form.amountDinar"
                    type="number"
                    min="0"
                    step="1"
                    inputmode="numeric"
                    placeholder="0"
                    class="erp-input erp-input--with-prefix"
                  />
                </div>
              </div>
            </div>
          </section>

          <section class="erp-field-group">
            <h3 class="erp-section-label">التفاصيل</h3>
            <div class="erp-grid-2">
              <div class="erp-field erp-field--span">
                <label class="erp-label" for="receipt-note">ملاحظة</label>
                <input
                  id="receipt-note"
                  v-model="form.amountNote"
                  type="text"
                  placeholder="اختياري"
                  class="erp-input"
                />
              </div>
              <div class="erp-field">
                <label class="erp-label" for="receipt-date">التاريخ</label>
                <input
                  id="receipt-date"
                  v-model="form.date"
                  type="date"
                  class="erp-input"
                />
              </div>
            </div>
          </section>
        </div>

        <footer class="erp-modal-footer">
          <button type="button" class="erp-btn erp-btn--ghost" @click="emit('close')">
            تراجع
          </button>
          <button
            type="button"
            class="erp-btn erp-btn--success"
            :disabled="!canSubmit"
            @click="submit"
          >
            تأكيد القبض
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
  width: min(100%, 34rem);
  max-height: min(90vh, 40rem);
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
  padding: 1.25rem 1.25rem 1rem;
  border-bottom: 1px solid #1e293b;
  background: linear-gradient(180deg, #14532d 0%, #0f172a 100%);
}

.erp-modal-header-text {
  min-width: 0;
}

.erp-modal-eyebrow {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  color: #86efac;
}

.erp-modal-title {
  margin: 0.25rem 0 0;
  font-size: 1.35rem;
  font-weight: 700;
  color: #f8fafc;
  line-height: 1.3;
}

.erp-modal-subtitle {
  margin: 0.35rem 0 0;
  font-size: 0.875rem;
  color: #cbd5e1;
  line-height: 1.5;
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
  transition: background 200ms ease, border-color 200ms ease;
}

.erp-modal-close:hover {
  background: #334155;
  border-color: #64748b;
}

.erp-modal-body {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.erp-field-group {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.erp-section-label {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #94a3b8;
}

.erp-grid-2 {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.875rem;
}

@media (min-width: 640px) {
  .erp-grid-2 {
    grid-template-columns: 1fr 1fr;
  }

  .erp-field--span {
    grid-column: 1 / -1;
  }
}

.erp-field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.erp-label {
  font-size: 0.875rem;
  font-weight: 600;
  color: #f1f5f9;
}

.erp-input-wrap {
  position: relative;
}

.erp-input-prefix {
  position: absolute;
  inset-inline-start: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.8rem;
  font-weight: 700;
  color: #94a3b8;
  pointer-events: none;
}

.erp-input {
  width: 100%;
  min-height: 2.75rem;
  padding: 0.65rem 0.85rem;
  border-radius: 0.75rem;
  border: 1px solid #475569;
  background: #020617;
  color: #f8fafc;
  font-size: 1rem;
  line-height: 1.5;
  transition: border-color 200ms ease, box-shadow 200ms ease;
}

.erp-input--with-prefix {
  padding-inline-start: 2.75rem;
}

.erp-input::placeholder {
  color: #64748b;
}

.erp-input:focus {
  outline: none;
  border-color: #38bdf8;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.25);
}

.erp-modal-footer {
  display: flex;
  flex-direction: column-reverse;
  gap: 0.75rem;
  padding: 1rem 1.25rem 1.25rem;
  border-top: 1px solid #1e293b;
  background: #020617;
}

@media (min-width: 480px) {
  .erp-modal-footer {
    flex-direction: row;
    justify-content: flex-end;
  }
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

.erp-btn--success {
  border: 1px solid #15803d;
  background: #16a34a;
  color: #ffffff;
}

.erp-btn--success:hover:not(:disabled) {
  background: #15803d;
}

.erp-btn--success:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

@media (min-width: 480px) {
  .erp-btn {
    min-width: 8.5rem;
  }
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
