<script setup>
import { computed, ref, watch } from "vue";

const props = defineProps({
  show: { type: Boolean, default: false },
  accounts: { type: Array, default: () => [] },
  presetAccountId: { type: [Number, String], default: null },
  defaultCurrency: { type: String, default: "$" },
});

const emit = defineEmits(["close", "save"]);

const form = ref({
  ledger_account_id: "",
  amount: "",
  currency: "$",
  entry_date: new Date().toISOString().slice(0, 10),
  memo: "",
});
const saving = ref(false);
const errorMsg = ref("");

const accountOptions = computed(() =>
  (props.accounts || []).filter((a) => a && a.code !== "3900")
);

const selectedAccount = computed(() =>
  accountOptions.value.find((a) => Number(a.id) === Number(form.value.ledger_account_id))
);

const natureHint = computed(() => {
  const t = selectedAccount.value?.type;
  if (!t) return "اختر الحساب — الاتجاه يُحدَّد تلقائياً حسب نوعه (أصل/مصروف أو التزام/إيراد/حقوق ملكية).";
  if (t === "asset" || t === "expense") {
    return "أصل / مصروف: مدين الحساب · دائن رأس المال الافتتاحي (3900)";
  }
  return "التزام / إيراد / حقوق ملكية: مدين رأس المال الافتتاحي (3900) · دائن الحساب";
});

watch(
  () => props.show,
  (open) => {
    if (!open) return;
    errorMsg.value = "";
    saving.value = false;
    const cur = props.defaultCurrency === "IQD" ? "IQD" : "$";
    form.value = {
      ledger_account_id: props.presetAccountId ? Number(props.presetAccountId) : "",
      amount: "",
      currency: cur,
      entry_date: new Date().toISOString().slice(0, 10),
      memo: "",
    };
  }
);

function canSubmit() {
  return (
    !!form.value.ledger_account_id &&
    Number(form.value.amount) > 0 &&
    !saving.value
  );
}

function submit() {
  if (!canSubmit()) return;
  errorMsg.value = "";
  emit("save", {
    ledger_account_id: Number(form.value.ledger_account_id),
    amount: Number(form.value.amount),
    currency: form.value.currency,
    entry_date: form.value.entry_date || null,
    memo: (form.value.memo || "").trim(),
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
        class="erp-ob-mask"
        role="dialog"
        aria-modal="true"
        aria-labelledby="opening-balance-title"
        @click.self="close"
      >
        <div class="erp-ob-panel">
          <header class="erp-ob-header">
            <div>
              <p class="erp-ob-eyebrow">قيد مزدوج · رأس المال الافتتاحي 3900</p>
              <h2 id="opening-balance-title" class="erp-ob-title">رصيد افتتاحي</h2>
              <p class="erp-ob-subtitle">{{ natureHint }}</p>
            </div>
            <button type="button" class="erp-ob-close" aria-label="إغلاق" @click="close">×</button>
          </header>

          <div class="erp-ob-body">
            <p v-if="errorMsg" class="erp-ob-error">{{ errorMsg }}</p>

            <label class="erp-ob-field">
              <span class="erp-ob-label">الحساب *</span>
              <select v-model="form.ledger_account_id" class="erp-ob-input" :disabled="saving">
                <option value="" disabled>اختر حساباً من الدليل…</option>
                <option v-for="acc in accountOptions" :key="acc.id" :value="acc.id">
                  {{ acc.code }} — {{ acc.name }}
                </option>
              </select>
            </label>

            <div class="erp-ob-grid">
              <label class="erp-ob-field">
                <span class="erp-ob-label">المبلغ *</span>
                <input
                  v-model="form.amount"
                  type="number"
                  min="0.01"
                  step="0.01"
                  class="erp-ob-input"
                  placeholder="0.00"
                  :disabled="saving"
                />
              </label>
              <label class="erp-ob-field">
                <span class="erp-ob-label">العملة *</span>
                <select v-model="form.currency" class="erp-ob-input" :disabled="saving">
                  <option value="$">USD</option>
                  <option value="IQD">IQD</option>
                </select>
              </label>
            </div>

            <label class="erp-ob-field">
              <span class="erp-ob-label">تاريخ القيد</span>
              <input v-model="form.entry_date" type="date" class="erp-ob-input" :disabled="saving" />
            </label>

            <label class="erp-ob-field">
              <span class="erp-ob-label">ملاحظة (اختياري)</span>
              <input
                v-model="form.memo"
                type="text"
                class="erp-ob-input"
                placeholder="مثال: رصيد بداية السنة"
                maxlength="500"
                :disabled="saving"
              />
            </label>
          </div>

          <footer class="erp-ob-footer">
            <button type="button" class="erp-ob-btn erp-ob-btn-ghost" :disabled="saving" @click="close">
              إلغاء
            </button>
            <button
              type="button"
              class="erp-ob-btn erp-ob-btn-confirm"
              :disabled="!canSubmit()"
              @click="submit"
            >
              {{ saving ? "جاري الترحيل…" : "ترحيل الرصيد الافتتاحي" }}
            </button>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.erp-ob-mask {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(2, 6, 23, 0.72);
}
.erp-ob-panel {
  width: min(32rem, 100%);
  max-height: min(92vh, 40rem);
  overflow: auto;
  border-radius: 0.85rem;
  background: #0f172a;
  border: 1px solid #334155;
  color: #f1f5f9;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.55);
}
.erp-ob-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 1.1rem 1.25rem 0.75rem;
  border-bottom: 1px solid #334155;
}
.erp-ob-eyebrow {
  margin: 0;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #94a3b8;
}
.erp-ob-title {
  margin: 0.2rem 0 0;
  font-size: 1.15rem;
  font-weight: 800;
  color: #fff;
}
.erp-ob-subtitle {
  margin: 0.35rem 0 0;
  font-size: 0.8rem;
  color: #cbd5e1;
  line-height: 1.45;
}
.erp-ob-close {
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
.erp-ob-body {
  padding: 1rem 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}
.erp-ob-grid {
  display: grid;
  grid-template-columns: 1fr 7rem;
  gap: 0.65rem;
}
.erp-ob-field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}
.erp-ob-label {
  font-size: 0.8rem;
  font-weight: 700;
  color: #e2e8f0;
}
.erp-ob-input {
  width: 100%;
  border-radius: 0.5rem;
  border: 1px solid #475569;
  background: #020617;
  color: #fff;
  padding: 0.55rem 0.75rem;
  font-size: 0.9rem;
  outline: none;
}
.erp-ob-input:focus {
  border-color: #34d399;
  box-shadow: 0 0 0 2px rgba(52, 211, 153, 0.25);
}
.erp-ob-error {
  margin: 0;
  padding: 0.55rem 0.75rem;
  border-radius: 0.5rem;
  background: rgba(127, 29, 29, 0.45);
  border: 1px solid #9f1239;
  color: #fecdd3;
  font-size: 0.85rem;
  font-weight: 600;
}
.erp-ob-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  padding: 0.85rem 1.25rem 1.15rem;
  border-top: 1px solid #334155;
}
.erp-ob-btn {
  min-height: 2.4rem;
  padding: 0.45rem 1rem;
  border-radius: 0.5rem;
  font-weight: 700;
  font-size: 0.875rem;
  border: 0;
  cursor: pointer;
}
.erp-ob-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.erp-ob-btn-ghost {
  background: #334155;
  color: #e2e8f0;
}
.erp-ob-btn-confirm {
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
