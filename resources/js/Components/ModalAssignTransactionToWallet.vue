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
  <Transition name="modal">
    <div v-if="show" class="modal-mask">
      <div class="modal-wrapper">
        <div class="modal-container bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
          <div class="modal-header text-gray-900 dark:text-gray-100">
            <slot name="header" />
          </div>
          <div class="modal-body text-gray-800 dark:text-gray-200">
            <p v-if="transaction" class="mb-4 text-sm text-gray-600 dark:text-gray-300">
              حركة رقم <strong>{{ transaction.id }}</strong>
              — {{ Math.abs(transaction.amount) }} {{ transaction.currency ?? '$' }}
            </p>
            <div class="mb-4 wallet-select-wrap">
              <label for="wallet_user_id" class="block text-sm font-medium text-gray-800 dark:text-gray-100 mb-1">
                إسناد إلى قاسة
              </label>
              <ModelListSelect
                id="wallet_user_id"
                v-model="selectedUserId"
                :list="walletUsers"
                option-value="id"
                option-text="name"
                placeholder="ابحث عن القاسة..."
              />
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              ستُحوَّل الحركة لتظهر كسحب من القاسة المختارة (مثل وصل الصرف من القاسة) مع بقاء خصم الصندوق كما هو.
            </p>
            <p v-if="error" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ error }}</p>
          </div>
          <div class="modal-footer my-2">
            <div class="flex flex-row">
              <div class="basis-1/2 px-4">
                <button
                  type="button"
                  class="modal-default-button py-3 bg-gray-500 rounded w-full"
                  :disabled="saving"
                  @click="$emit('close')"
                >
                  تراجع
                </button>
              </div>
              <div class="basis-1/2 px-4">
                <button
                  type="button"
                  class="modal-default-button py-3 bg-indigo-600 rounded w-full"
                  :disabled="saving"
                  @click="save"
                >
                  {{ saving ? 'جاري الحفظ...' : 'تأكيد' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-mask {
  position: fixed;
  z-index: 9998;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px 180px;
}

.modal-wrapper {
  width: 100%;
  max-width: min(900px, 100%);
}

.modal-container {
  padding: 28px 32px;
  border-radius: 12px;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.25);
  min-height: 280px;
}

.modal-body {
  margin: 16px 0;
}

.modal-default-button {
  color: #fff;
}

.wallet-select-wrap :deep(.ui.search.selection.dropdown) {
  width: 100%;
  min-height: 3rem;
  font-size: 1rem;
}

.wallet-select-wrap :deep(.ui.search.selection.dropdown .menu) {
  max-height: 320px;
}

:global(.dark) .wallet-select-wrap :deep(.ui.search.selection.dropdown) {
  background: rgb(55 65 81);
  color: rgb(243 244 246);
  border-color: rgb(75 85 99);
}

:global(.dark) .wallet-select-wrap :deep(.ui.search.selection.dropdown .menu) {
  background: rgb(31 41 55);
  color: rgb(243 244 246);
}

:global(.dark) .wallet-select-wrap :deep(.ui.search.selection.dropdown .menu .item) {
  color: rgb(243 244 246);
}

:global(.dark) .wallet-select-wrap :deep(.ui.search.selection.dropdown .menu .item:hover) {
  background: rgb(55 65 81);
}

@media (max-width: 1024px) {
  .modal-mask {
    padding: 12px;
  }
}
</style>
