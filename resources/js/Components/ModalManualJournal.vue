<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  show: { type: Boolean, default: false },
  accounts: { type: Array, default: () => [] },
  presetDebitId: { type: [Number, String], default: null },
  presetCreditId: { type: [Number, String], default: null },
  defaultCurrency: { type: String, default: "$" },
});

const emit = defineEmits(["close", "save"]);

const form = ref({
  debit_account_id: "",
  credit_account_id: "",
  amount: "",
  currency: "$",
  entry_date: new Date().toISOString().slice(0, 10),
  memo: "",
});
const saving = ref(false);
const errorMsg = ref("");

const accountOptions = computed(() => props.accounts || []);

const debitLabel = computed(() => {
  const a = accountOptions.value.find((x) => Number(x.id) === Number(form.value.debit_account_id));
  return a ? `${a.code} — ${a.name}` : "";
});

const creditLabel = computed(() => {
  const a = accountOptions.value.find((x) => Number(x.id) === Number(form.value.credit_account_id));
  return a ? `${a.code} — ${a.name}` : "";
});

watch(
  () => props.show,
  (open) => {
    if (!open) return;
    errorMsg.value = "";
    saving.value = false;
    const cur = props.defaultCurrency === "IQD" ? "IQD" : "$";
    form.value = {
      debit_account_id: props.presetDebitId ? Number(props.presetDebitId) : "",
      credit_account_id: props.presetCreditId ? Number(props.presetCreditId) : "",
      amount: "",
      currency: cur,
      entry_date: new Date().toISOString().slice(0, 10),
      memo: "",
    };
  }
);

function canSubmit() {
  return (
    !!form.value.debit_account_id &&
    !!form.value.credit_account_id &&
    Number(form.value.debit_account_id) !== Number(form.value.credit_account_id) &&
    Number(form.value.amount) > 0 &&
    !!(form.value.memo && form.value.memo.trim()) &&
    !saving.value
  );
}

function submit() {
  if (!canSubmit()) return;
  errorMsg.value = "";
  emit("save", {
    debit_account_id: Number(form.value.debit_account_id),
    credit_account_id: Number(form.value.credit_account_id),
    amount: Number(form.value.amount),
    currency: form.value.currency,
    entry_date: form.value.entry_date || null,
    memo: form.value.memo.trim(),
  });
}

function close() {
  if (saving.value) return;
  emit("close");
}

defineExpose({
  setSaving(v) {
    saving.value = !!v;
  },
  setError(msg) {
    errorMsg.value = msg || "";
  },
});
</script>

<template>
  <Teleport to="body">
    <Transition name="erp-modal">
      <div
        v-if="show"
        class="erp-mj-mask"
        role="dialog"
        aria-modal="true"
        aria-labelledby="manual-journal-title"
        @click.self="close"
      >
        <div class="erp-mj-panel">
          <header class="erp-mj-header">
            <div>
              <p class="erp-mj-eyebrow">قيد يدوي · حركة بين حسابات الدليل</p>
              <h2 id="manual-journal-title" class="erp-mj-title">حركة بين الحسابات</h2>
              <p class="erp-mj-subtitle">
                اختر <strong class="text-sky-300">حساب مدين</strong> و
                <strong class="text-emerald-300">حساب دائن</strong> صراحةً — قيد متوازن واحد.
              </p>
            </div>
            <button type="button" class="erp-mj-close" aria-label="إغلاق" @click="close">×</button>
          </header>

          <div class="erp-mj-body">
            <p v-if="errorMsg" class="erp-mj-error">{{ errorMsg }}</p>

            <label class="erp-mj-field">
              <span class="erp-mj-label">حساب مدين *</span>
              <select v-model="form.debit_account_id" class="erp-mj-input" :disabled="saving">
                <option value="" disabled>الحساب الذي يُدان…</option>
                <option v-for="acc in accountOptions" :key="'d-' + acc.id" :value="acc.id">
                  {{ acc.code }} — {{ acc.name }}
                </option>
              </select>
              <span v-if="debitLabel" class="erp-mj-hint">مدين: {{ debitLabel }}</span>
            </label>

            <label class="erp-mj-field">
              <span class="erp-mj-label">حساب دائن *</span>
              <select v-model="form.credit_account_id" class="erp-mj-input" :disabled="saving">
                <option value="" disabled>الحساب الذي يُدان له…</option>
                <option v-for="acc in accountOptions" :key="'c-' + acc.id" :value="acc.id">
                  {{ acc.code }} — {{ acc.name }}
                </option>
              </select>
              <span v-if="creditLabel" class="erp-mj-hint">دائن: {{ creditLabel }}</span>
            </label>

            <div class="erp-mj-grid">
              <label class="erp-mj-field">
                <span class="erp-mj-label">المبلغ *</span>
                <input
                  v-model="form.amount"
                  type="number"
                  min="0.01"
                  step="0.01"
                  class="erp-mj-input"
                  placeholder="0.00"
                  :disabled="saving"
                />
              </label>
              <label class="erp-mj-field">
                <span class="erp-mj-label">العملة *</span>
                <select v-model="form.currency" class="erp-mj-input" :disabled="saving">
                  <option value="$">USD</option>
                  <option value="IQD">IQD</option>
                </select>
              </label>
            </div>

            <label class="erp-mj-field">
              <span class="erp-mj-label">تاريخ القيد</span>
              <input v-model="form.entry_date" type="date" class="erp-mj-input" :disabled="saving" />
            </label>

            <label class="erp-mj-field">
              <span class="erp-mj-label">البيان *</span>
              <input
                v-model="form.memo"
                type="text"
                class="erp-mj-input"
                placeholder="سبب الحركة بين الحسابين"
                maxlength="500"
                :disabled="saving"
              />
            </label>
          </div>

          <footer class="erp-mj-footer">
            <button type="button" class="erp-mj-btn erp-mj-btn-ghost" :disabled="saving" @click="close">
              إلغاء
            </button>
            <button
              type="button"
              class="erp-mj-btn erp-mj-btn-confirm"
              :disabled="!canSubmit()"
              @click="submit"
            >
              {{ saving ? "جاري الترحيل…" : "ترحيل القيد" }}
            </button>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.erp-mj-mask {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(2, 6, 23, 0.72);
}
.erp-mj-panel {
  width: min(34rem, 100%);
  max-height: min(92vh, 44rem);
  overflow: auto;
  border-radius: 0.85rem;
  background: #0f172a;
  border: 1px solid #334155;
  color: #f1f5f9;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.55);
}
.erp-mj-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 1.1rem 1.25rem 0.75rem;
  border-bottom: 1px solid #334155;
}
.erp-mj-eyebrow {
  margin: 0;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #94a3b8;
}
.erp-mj-title {
  margin: 0.2rem 0 0;
  font-size: 1.15rem;
  font-weight: 800;
  color: #fff;
}
.erp-mj-subtitle {
  margin: 0.35rem 0 0;
  font-size: 0.8rem;
  color: #cbd5e1;
  line-height: 1.45;
}
.erp-mj-close {
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  border-radius: 0.45rem;
  border: 1px solid #475569;
  background: #1e293b;
  color: #e2e8f0;
  font-size: 1.25rem;
  line-height: 1;
  cursor: pointer;
}
.erp-mj-body {
  padding: 1rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}
.erp-mj-grid {
  display: grid;
  grid-template-columns: 1fr 7rem;
  gap: 0.65rem;
}
.erp-mj-field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.erp-mj-label {
  font-size: 0.8rem;
  font-weight: 700;
  color: #e2e8f0;
}
.erp-mj-hint {
  font-size: 0.72rem;
  color: #94a3b8;
}
.erp-mj-input {
  width: 100%;
  border-radius: 0.5rem;
  border: 1px solid #475569;
  background: #020617;
  color: #fff;
  padding: 0.55rem 0.75rem;
  font-size: 0.9rem;
  outline: none;
}
.erp-mj-input:focus {
  border-color: #34d399;
  box-shadow: 0 0 0 2px rgba(52, 211, 153, 0.25);
}
.erp-mj-error {
  margin: 0;
  padding: 0.55rem 0.75rem;
  border-radius: 0.5rem;
  background: rgba(127, 29, 29, 0.45);
  border: 1px solid #9f1239;
  color: #fecdd3;
  font-size: 0.85rem;
  font-weight: 600;
}
.erp-mj-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  padding: 0.85rem 1.25rem 1.15rem;
  border-top: 1px solid #334155;
}
.erp-mj-btn {
  min-height: 2.4rem;
  padding: 0.45rem 1rem;
  border-radius: 0.5rem;
  font-weight: 700;
  font-size: 0.875rem;
  border: 0;
  cursor: pointer;
}
.erp-mj-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.erp-mj-btn-ghost {
  background: #334155;
  color: #e2e8f0;
}
.erp-mj-btn-confirm {
  background: #059669;
  color: #fff;
}
.erp-modal-enter-active,
.erp-modal-leave-active {
  transition: opacity 0.18s ease;
}
.erp-modal-enter-from,
.erp-modal-leave-to {
  opacity: 0;
}
</style>
