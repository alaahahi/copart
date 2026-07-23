<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { ModelListSelect } from 'vue-search-select';
import 'vue-search-select/dist/VueSearchSelect.css';

const props = defineProps({
  show: Boolean,
  transaction: Object,
  walletUsers: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const selectedUserId = ref('');
const saving = ref(false);
const error = ref('');

watch(
  () => [props.show, props.transaction],
  () => {
    if (!props.show) return;
    selectedUserId.value = '';
    error.value = '';
  },
  { immediate: true }
);

async function save() {
  if (!selectedUserId.value) {
    error.value = 'اختر القاسة';
    return;
  }
  saving.value = true;
  error.value = '';
  try {
    await axios.post('/api/assignTransactionToWallet', {
      transaction_id: props.transaction.id,
      user_id: Number(selectedUserId.value),
    });
    emit('saved');
    emit('close');
  } catch (e) {
    error.value = e.response?.data?.message || 'تعذر إسناد الحركة';
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <Transition name="erp-modal">
    <div
      v-if="show"
      class="erp-modal-mask"
      role="dialog"
      aria-modal="true"
      aria-labelledby="assign-wallet-title"
      @click.self="emit('close')"
    >
      <div class="erp-modal-panel">
        <header class="erp-modal-header erp-modal-header--assign">
          <div class="erp-modal-header-text">
            <p class="erp-modal-eyebrow">المحاسبة</p>
            <h2 id="assign-wallet-title" class="erp-modal-title">إسناد السحب إلى قاسة</h2>
            <p class="erp-modal-subtitle">تحويل حركة السحب من الصندوق لتصبح سحباً من قاسة محددة</p>
          </div>
          <button
            type="button"
            class="erp-modal-close"
            aria-label="إغلاق"
            :disabled="saving"
            @click="emit('close')"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5" aria-hidden="true">
              <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
            </svg>
          </button>
        </header>

        <div class="erp-modal-body">
          <div v-if="transaction" class="erp-tx-summary">
            <div class="erp-tx-info">
              <span class="erp-tx-label">حركة رقم</span>
              <span class="erp-tx-id">#{{ transaction.id }}</span>
            </div>
            <div class="erp-tx-amount">
              {{ Math.abs(transaction.amount) }}
              <span class="erp-tx-currency">{{ transaction.currency ?? '$' }}</span>
            </div>
          </div>

          <section class="erp-field-group">
            <h3 class="erp-section-label">القاسة المستهدفة</h3>
            <div class="erp-field">
              <label class="erp-label" for="wallet_user_id">
                إسناد إلى قاسة
                <span class="erp-badge erp-badge--out">سحب</span>
              </label>
              <div class="erp-select-wrap">
                <ModelListSelect
                  id="wallet_user_id"
                  v-model="selectedUserId"
                  :list="walletUsers"
                  option-value="id"
                  option-text="name"
                  placeholder="ابحث عن القاسة..."
                />
              </div>
            </div>
          </section>

          <p class="erp-hint">
            ستُحوَّل الحركة لتظهر كسحب من القاسة المختارة (مثل وصل الصرف من القاسة) مع بقاء خصم الصندوق كما هو.
          </p>

          <p v-if="error" class="erp-alert" role="alert">{{ error }}</p>
        </div>

        <footer class="erp-modal-footer">
          <button type="button" class="erp-btn erp-btn--ghost" :disabled="saving" @click="emit('close')">
            تراجع
          </button>
          <button
            type="button"
            class="erp-btn erp-btn--primary"
            :disabled="saving"
            @click="save"
          >
            {{ saving ? 'جاري الحفظ...' : 'تأكيد' }}
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
  width: min(100%, 30rem);
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
  background: linear-gradient(180deg, #312e81 0%, #0f172a 100%);
}

.erp-modal-header-text {
  min-width: 0;
}

.erp-modal-eyebrow {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  color: #a5b4fc;
}

.erp-modal-title {
  margin: 0.25rem 0 0;
  font-size: 1.3rem;
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

.erp-modal-close:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.erp-modal-body {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
}

.erp-tx-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.9rem 1rem;
  border-radius: 0.875rem;
  background: #020617;
  border: 1px solid #1e293b;
}

.erp-tx-info {
  display: flex;
  align-items: baseline;
  gap: 0.4rem;
}

.erp-tx-label {
  font-size: 0.8rem;
  color: #94a3b8;
}

.erp-tx-id {
  font-size: 1rem;
  font-weight: 700;
  color: #f1f5f9;
}

.erp-tx-amount {
  font-size: 1.25rem;
  font-weight: 700;
  color: #fca5a5;
  display: flex;
  align-items: baseline;
  gap: 0.3rem;
}

.erp-tx-currency {
  font-size: 0.85rem;
  font-weight: 600;
  color: #94a3b8;
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

.erp-hint {
  margin: 0;
  font-size: 0.8rem;
  color: #94a3b8;
  line-height: 1.5;
}

.erp-alert {
  margin: 0;
  padding: 0.6rem 0.85rem;
  border-radius: 0.65rem;
  background: rgba(220, 38, 38, 0.12);
  border: 1px solid rgba(248, 113, 113, 0.35);
  color: #fca5a5;
  font-size: 0.85rem;
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

.erp-btn--ghost:hover:not(:disabled) {
  background: #1e293b;
}

.erp-btn--primary {
  border: 1px solid #4338ca;
  background: #4f46e5;
  color: #ffffff;
}

.erp-btn--primary:hover:not(:disabled) {
  background: #4338ca;
}

.erp-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Searchable "قاسة" select — themed to match the dark ERP inputs */
.erp-select-wrap :deep(.ui.search.selection.dropdown) {
  width: 100%;
  min-height: 2.75rem;
  padding: 0.4rem 0.85rem;
  border-radius: 0.75rem;
  border: 1px solid #475569;
  background: #020617;
  color: #f8fafc;
  font-size: 1rem;
  display: flex;
  align-items: center;
}

.erp-select-wrap :deep(.ui.search.selection.dropdown input.search) {
  color: #f8fafc;
}

.erp-select-wrap :deep(.ui.search.selection.dropdown .default.text),
.erp-select-wrap :deep(.ui.search.selection.dropdown .text) {
  color: #f8fafc;
}

.erp-select-wrap :deep(.ui.search.selection.dropdown > .search) {
  color: #94a3b8;
}

.erp-select-wrap :deep(.ui.search.selection.dropdown.active),
.erp-select-wrap :deep(.ui.search.selection.dropdown:focus) {
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
}

.erp-select-wrap :deep(.ui.search.selection.dropdown .menu) {
  max-height: 280px;
  background: #0f172a;
  border: 1px solid #334155;
  color: #f8fafc;
}

.erp-select-wrap :deep(.ui.search.selection.dropdown .menu .item) {
  color: #e2e8f0;
  border-top: 1px solid #1e293b;
}

.erp-select-wrap :deep(.ui.search.selection.dropdown .menu .item:hover),
.erp-select-wrap :deep(.ui.search.selection.dropdown .menu .item.selected) {
  background: #1e293b;
  color: #ffffff;
}

.erp-select-wrap :deep(.ui.search.selection.dropdown .menu .message) {
  color: #94a3b8;
}

.erp-select-wrap :deep(.ui.search.selection.dropdown i.dropdown.icon) {
  color: #94a3b8;
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
  .erp-modal-close {
    transition: none !important;
  }
}
</style>
