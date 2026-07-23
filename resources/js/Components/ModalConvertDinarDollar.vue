<script setup>
import { computed, ref } from 'vue';

defineProps({
  show: Boolean,
  boxes: Array,
});

const emit = defineEmits(['close', 'a']);

const form = ref({
  exchangeRate: '',
  amountDinar: '',
  amountResultDollar: '',
});

const exchangeRateError = ref(false);

const canSubmit = computed(() => {
  return (
    !!form.value.amountDinar &&
    !!form.value.exchangeRate &&
    !exchangeRateError.value
  );
});

function validateExchangeRate() {
  const input = String(form.value.exchangeRate ?? '');
  exchangeRateError.value = input !== '' && !/^\d{6}$/.test(input);
}

function calculateAmountDollarDinar() {
  validateExchangeRate();
  const rate = Number(form.value.exchangeRate);
  const iqd = Number(form.value.amountDinar);
  if (!rate || !iqd || exchangeRateError.value) {
    form.value.amountResultDollar = '';
    return;
  }
  form.value.amountResultDollar = Math.floor(iqd / (rate / 100));
}

const resetForm = () => {
  form.value = {
    exchangeRate: '',
    amountDinar: '',
    amountResultDollar: '',
  };
  exchangeRateError.value = false;
};

const submit = () => {
  validateExchangeRate();
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
      aria-labelledby="convert-iqd-title"
      @click.self="emit('close')"
    >
      <div class="erp-modal-panel">
        <header class="erp-modal-header">
          <div class="erp-modal-header-text">
            <p class="erp-modal-eyebrow">صرف العملات</p>
            <h2 id="convert-iqd-title" class="erp-modal-title">تحويل دينار → دولار</h2>
            <p class="erp-modal-subtitle">سحب من صندوق الدينار وإضافة إلى صندوق الدولار</p>
          </div>
          <button type="button" class="erp-modal-close" aria-label="إغلاق" @click="emit('close')">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5" aria-hidden="true">
              <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
            </svg>
          </button>
        </header>

        <div class="erp-modal-body">
          <div class="erp-flow" aria-hidden="true">
            <span class="erp-flow-chip">د.ع</span>
            <span class="erp-flow-arrow">←</span>
            <span class="erp-flow-chip erp-flow-chip--accent">سعر</span>
            <span class="erp-flow-arrow">←</span>
            <span class="erp-flow-chip">$</span>
          </div>

          <div class="erp-field">
            <label class="erp-label" for="iqd-rate">سعر الصرف لكل 100$</label>
            <input
              id="iqd-rate"
              v-model="form.exchangeRate"
              type="number"
              inputmode="numeric"
              placeholder="مثال: 150000"
              class="erp-input"
              :class="{ 'erp-input--error': exchangeRateError }"
              @input="calculateAmountDollarDinar"
            />
            <p v-if="exchangeRateError" class="erp-hint erp-hint--error" role="alert">
              أدخل رقماً من 6 خانات فقط
            </p>
            <p v-else class="erp-hint">سعر 100 دولار بالدينار العراقي</p>
          </div>

          <div class="erp-field">
            <label class="erp-label" for="iqd-amount">
              المبلغ بالدينار
              <span class="erp-badge erp-badge--out">من الصندوق</span>
            </label>
            <div class="erp-input-wrap">
              <span class="erp-input-prefix" aria-hidden="true">د.ع</span>
              <input
                id="iqd-amount"
                v-model="form.amountDinar"
                type="number"
                min="0"
                step="1"
                inputmode="numeric"
                placeholder="0"
                class="erp-input erp-input--with-prefix"
                @input="calculateAmountDollarDinar"
              />
            </div>
          </div>

          <div class="erp-result">
            <div class="erp-result-label">
              الناتج بالدولار
              <span class="erp-badge erp-badge--in">إلى الصندوق</span>
            </div>
            <div class="erp-result-value">
              <input
                id="iqd-result-usd"
                v-model="form.amountResultDollar"
                type="number"
                inputmode="decimal"
                class="erp-input erp-input--result"
                aria-label="المبلغ الناتج بالدولار"
              />
              <span class="erp-result-unit">$</span>
            </div>
          </div>
        </div>

        <footer class="erp-modal-footer">
          <button type="button" class="erp-btn erp-btn--ghost" @click="emit('close')">تراجع</button>
          <button
            type="button"
            class="erp-btn erp-btn--primary"
            :disabled="!canSubmit"
            @click="submit"
          >
            تنفيذ التحويل
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
  width: min(100%, 28rem);
  max-height: min(90vh, 42rem);
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
  transition: background 200ms ease;
}

.erp-modal-close:hover {
  background: #334155;
}

.erp-modal-body {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.erp-flow {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.5rem;
  border-radius: 0.75rem;
  background: #020617;
  border: 1px solid #1e293b;
}

.erp-flow-chip {
  min-width: 2.5rem;
  padding: 0.35rem 0.6rem;
  text-align: center;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 700;
  background: #1e293b;
  color: #e2e8f0;
  border: 1px solid #334155;
}

.erp-flow-chip--accent {
  background: #0369a1;
  border-color: #0284c7;
  color: #fff;
}

.erp-flow-arrow {
  color: #64748b;
  font-weight: 700;
}

.erp-field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.erp-label {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: #f1f5f9;
}

.erp-badge {
  display: inline-flex;
  align-items: center;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 700;
}

.erp-badge--out {
  background: rgba(220, 38, 38, 0.2);
  color: #fca5a5;
  border: 1px solid rgba(248, 113, 113, 0.35);
}

.erp-badge--in {
  background: rgba(22, 163, 74, 0.2);
  color: #86efac;
  border: 1px solid rgba(74, 222, 128, 0.35);
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
  transition: border-color 200ms ease, box-shadow 200ms ease;
}

.erp-input--with-prefix {
  padding-inline-start: 2.75rem;
}

.erp-input--error {
  border-color: #ef4444;
}

.erp-input:focus {
  outline: none;
  border-color: #38bdf8;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.25);
}

.erp-hint {
  margin: 0;
  font-size: 0.8rem;
  color: #94a3b8;
  line-height: 1.4;
}

.erp-hint--error {
  color: #fca5a5;
}

.erp-result {
  padding: 1rem;
  border-radius: 0.875rem;
  background: linear-gradient(145deg, #0c4a6e 0%, #0f172a 70%);
  border: 1px solid #0369a1;
}

.erp-result-label {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.65rem;
  font-size: 0.875rem;
  font-weight: 700;
  color: #bae6fd;
}

.erp-result-value {
  position: relative;
}

.erp-input--result {
  padding-inline-end: 2.5rem;
  font-size: 1.25rem;
  font-weight: 700;
  border-color: #0284c7;
  background: #020617;
}

.erp-result-unit {
  position: absolute;
  inset-inline-end: 0.85rem;
  top: 50%;
  transform: translateY(-50%);
  font-size: 0.95rem;
  font-weight: 700;
  color: #7dd3fc;
  pointer-events: none;
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

  .erp-btn {
    min-width: 8.5rem;
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

.erp-btn--primary {
  border: 1px solid #0369a1;
  background: #0284c7;
  color: #ffffff;
}

.erp-btn--primary:hover:not(:disabled) {
  background: #0369a1;
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
