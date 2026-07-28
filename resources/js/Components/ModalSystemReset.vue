<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  show: Boolean,
});

const emit = defineEmits(["close", "confirm"]);

const password = ref("");
const confirmation = ref("");
const acknowledged = ref(false);
const submitting = ref(false);

watch(
  () => props.show,
  (open) => {
    if (open) {
      password.value = "";
      confirmation.value = "";
      acknowledged.value = false;
      submitting.value = false;
    }
  }
);

const canSubmit = () => {
  const confirmOk =
    confirmation.value.trim() === "تصفير" ||
    confirmation.value.trim().toUpperCase() === "RESET";
  return (
    password.value.length > 0 &&
    confirmOk &&
    acknowledged.value &&
    !submitting.value
  );
};

function close() {
  if (submitting.value) return;
  emit("close");
}

function submit() {
  if (!canSubmit()) return;
  submitting.value = true;
  emit("confirm", {
    password: password.value,
    confirmation: confirmation.value.trim(),
    done: () => {
      submitting.value = false;
    },
  });
}
</script>

<template>
  <Teleport to="body">
    <Transition name="sys-reset">
      <div
        v-if="show"
        class="sys-reset-mask"
        role="dialog"
        aria-modal="true"
        @click.self="close"
      >
        <div class="sys-reset-panel">
          <header class="sys-reset-header">
            <div>
              <h3 class="text-lg font-bold text-rose-300">
                {{ $t("systemResetTitle") }}
              </h3>
              <p class="text-sm text-slate-300 mt-1 leading-relaxed">
                {{ $t("systemResetWarning") }}
              </p>
            </div>
            <button
              type="button"
              class="text-slate-400 hover:text-white text-xl leading-none px-2"
              :disabled="submitting"
              @click="close"
            >
              ×
            </button>
          </header>

          <div class="sys-reset-body space-y-4">
            <ul class="text-sm text-slate-200 list-disc pr-5 space-y-1">
              <li>{{ $t("systemResetClearsCars") }}</li>
              <li>{{ $t("systemResetClearsTraders") }}</li>
              <li>{{ $t("systemResetClearsWallets") }}</li>
              <li>{{ $t("systemResetClearsVaults") }}</li>
              <li>{{ $t("systemResetClearsPayments") }}</li>
            </ul>
            <p class="text-sm text-emerald-300">
              {{ $t("systemResetKeeps") }}
            </p>

            <div>
              <label class="block text-sm font-semibold text-slate-200 mb-1">
                {{ $t("systemResetPassword") }}
              </label>
              <input
                v-model="password"
                type="password"
                autocomplete="off"
                class="w-full rounded-lg bg-slate-950 border border-slate-600 text-white placeholder-slate-400 px-3 py-2"
                :placeholder="$t('systemResetPasswordPlaceholder')"
                @keydown.enter.prevent="submit"
              />
            </div>

            <div>
              <label class="block text-sm font-semibold text-slate-200 mb-1">
                {{ $t("systemResetConfirmWord") }}
              </label>
              <input
                v-model="confirmation"
                type="text"
                dir="rtl"
                class="w-full rounded-lg bg-slate-950 border border-slate-600 text-white placeholder-slate-400 px-3 py-2"
                :placeholder="$t('systemResetConfirmPlaceholder')"
                @keydown.enter.prevent="submit"
              />
              <p class="text-xs text-slate-400 mt-1">
                {{ $t("systemResetConfirmHint") }}
              </p>
            </div>

            <label
              class="flex items-start gap-3 rounded-lg border border-slate-700 bg-slate-950/70 px-3 py-3 cursor-pointer"
            >
              <input
                v-model="acknowledged"
                type="checkbox"
                class="mt-1 rounded border-slate-600 bg-slate-900 text-rose-500 focus:ring-rose-500/40"
              />
              <span class="text-sm text-slate-200 leading-relaxed">
                {{ $t("systemResetAcknowledge") }}
              </span>
            </label>
          </div>

          <footer class="sys-reset-footer">
            <button
              type="button"
              class="sys-reset-btn sys-reset-btn--ghost"
              :disabled="submitting"
              @click="close"
            >
              {{ $t("cancel") }}
            </button>
            <button
              type="button"
              class="sys-reset-btn sys-reset-btn--danger"
              :disabled="!canSubmit()"
              @click="submit"
            >
              {{ submitting ? $t("systemResetting") : $t("systemResetConfirmBtn") }}
            </button>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.sys-reset-mask {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background: rgba(2, 6, 23, 0.78);
  backdrop-filter: blur(4px);
}

.sys-reset-panel {
  width: min(100%, 32rem);
  max-height: min(92vh, 42rem);
  overflow: auto;
  background: #0f172a;
  color: #f8fafc;
  border: 1px solid #7f1d1d;
  border-radius: 1rem;
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.5);
}

.sys-reset-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.25rem 1.25rem 1rem;
  border-bottom: 1px solid #1e293b;
}

.sys-reset-body {
  padding: 1.25rem;
}

.sys-reset-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1rem 1.25rem 1.25rem;
  border-top: 1px solid #1e293b;
}

.sys-reset-btn {
  border-radius: 0.625rem;
  padding: 0.65rem 1.15rem;
  font-weight: 700;
  font-size: 0.9rem;
}

.sys-reset-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.sys-reset-btn--ghost {
  background: #1e293b;
  color: #e2e8f0;
  border: 1px solid #475569;
}

.sys-reset-btn--ghost:hover:not(:disabled) {
  background: #334155;
}

.sys-reset-btn--danger {
  background: #e11d48;
  color: #fff;
  border: 1px solid #be123c;
}

.sys-reset-btn--danger:hover:not(:disabled) {
  background: #f43f5e;
}

.sys-reset-enter-active,
.sys-reset-leave-active {
  transition: opacity 0.18s ease;
}
.sys-reset-enter-from,
.sys-reset-leave-to {
  opacity: 0;
}
</style>
