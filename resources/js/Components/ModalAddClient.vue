<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  show: Boolean,
  formData: Object,
});

const emit = defineEmits(['close', 'a']);

const defaultForm = () => ({
  name: '',
  phone: '',
  // عرض بالمحاسبة (قاسة) — hidden from accounting by default until explicitly enabled.
  show_in_dashboard: false,
});

const form = ref(defaultForm());

// Re-seed the form every time the modal is (re)opened so stale data never lingers.
watch(
  () => props.show,
  (isOpen) => {
    if (isOpen) {
      form.value = {
        ...defaultForm(),
        ...(props.formData || {}),
        show_in_dashboard: !!(props.formData && props.formData.show_in_dashboard),
      };
    }
  },
  { immediate: true }
);

const canSubmit = () => !!(form.value.name && form.value.name.trim());

const submit = () => {
  if (!canSubmit()) return;
  emit('a', { ...form.value });
};

const close = () => emit('close');
</script>

<template>
  <Transition name="erp-modal">
    <div
      v-if="show"
      class="erp-modal-mask"
      role="dialog"
      aria-modal="true"
      aria-labelledby="add-client-title"
      @click.self="close"
    >
      <div class="erp-modal-panel">
        <header class="erp-modal-header">
          <div class="erp-modal-header-text">
            <p class="erp-modal-eyebrow">التجار</p>
            <h2 id="add-client-title" class="erp-modal-title">إضافة تاجر جديد</h2>
            <p class="erp-modal-subtitle">أدخل بيانات التاجر لإضافته إلى القائمة</p>
          </div>
          <button type="button" class="erp-modal-close" aria-label="إغلاق" @click="close">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5" aria-hidden="true">
              <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
            </svg>
          </button>
        </header>

        <div class="erp-modal-body">
          <div class="erp-field">
            <label class="erp-label" for="add-client-name">{{ $t('name') }}</label>
            <input
              id="add-client-name"
              v-model="form.name"
              type="text"
              placeholder="اسم التاجر"
              class="erp-input"
              @keyup.enter="submit"
            />
          </div>

          <div class="erp-field">
            <label class="erp-label" for="add-client-phone">{{ $t('phone') }}</label>
            <input
              id="add-client-phone"
              v-model="form.phone"
              type="text"
              dir="ltr"
              placeholder="07xxxxxxxxx"
              class="erp-input"
              @keyup.enter="submit"
            />
          </div>

          <div class="erp-toggle-row">
            <div class="erp-toggle-text">
              <span class="erp-toggle-title">عرض بالمحاسبة</span>
              <span class="erp-toggle-hint">إظهار محفظة التاجر في صفحة المحاسبة (قاسة). يمكن تفعيلها لاحقاً من قائمة التجار.</span>
            </div>
            <label class="erp-switch" :title="form.show_in_dashboard ? 'معروض في المحاسبة' : 'مخفي عن المحاسبة'">
              <input type="checkbox" role="switch" v-model="form.show_in_dashboard" />
              <span class="erp-switch-track" aria-hidden="true">
                <span class="erp-switch-thumb" />
              </span>
            </label>
          </div>
        </div>

        <footer class="erp-modal-footer">
          <button type="button" class="erp-btn erp-btn--ghost" @click="close">{{ $t('cancel') }}</button>
          <button type="button" class="erp-btn erp-btn--primary" :disabled="!canSubmit()" @click="submit">
            إضافة التاجر
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

.erp-input:focus {
  outline: none;
  border-color: #38bdf8;
  box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.25);
}

.erp-toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.9rem 1rem;
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

.erp-switch input:focus-visible + .erp-switch-track {
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.35);
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
