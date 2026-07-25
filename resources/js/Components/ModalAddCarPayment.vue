<script setup>
import { computed } from "vue";
import { useToast } from "vue-toastification";
import { asNumber, formatMoney } from "@/utils/formatMoney";

const toast = useToast();

const props = defineProps({
  show: Boolean,
  company: Array,
  color: Array,
  carModel: Array,
  name: Array,
  client: Array,
  user: Array,
  expenses: Array,
  formData: Object,
});

const emit = defineEmits(["close", "a"]);

const HAND_TOKEN = "بيد";

const total = computed(() => asNumber(props.formData?.total_s));
const paid = computed(() => asNumber(props.formData?.paid));
const discount = computed(() => asNumber(props.formData?.discount));
const remaining = computed(() => Math.max(0, total.value - (paid.value + discount.value)));

const paidPercent = computed(() => {
  if (total.value <= 0) return 0;
  const pct = ((paid.value + discount.value) / total.value) * 100;
  return Math.min(100, Math.max(0, Math.round(pct)));
});

const isHandPayment = computed(() => {
  const note = String(props.formData?.notePayment ?? "");
  return note.includes(HAND_TOKEN);
});

const noteExtra = computed({
  get() {
    return String(props.formData?.notePayment ?? "")
      .replace(new RegExp(`\\s*${HAND_TOKEN}\\s*`, "g"), " ")
      .trim();
  },
  set(val) {
    if (!props.formData) return;
    const extra = String(val ?? "").trim();
    if (isHandPayment.value) {
      props.formData.notePayment = extra ? ` ${HAND_TOKEN} ${extra}` : ` ${HAND_TOKEN} `;
    } else {
      props.formData.notePayment = extra;
    }
  },
});

const carTitle = computed(() => {
  const type = props.formData?.car_type ?? "";
  const model = props.formData?.car_model ?? props.formData?.model ?? "";
  return [type, model].filter(Boolean).join(" ").trim() || "—";
});

function calculateAmount() {
  const amount = remaining.value;
  if (asNumber(props.formData.amountPayment) > amount) {
    props.formData.amountPayment = amount;
    props.formData.discountPayment = 0;
    toast.info(" المبلغ اكبر من الدين المطلوب" + " " + amount, {
      timeout: 4000,
      position: "bottom-right",
      rtl: true,
    });
  }
}

function confirm() {
  if (!props.formData?.amountPayment) return;
  emit("a", props.formData);
}

function money(v) {
  return formatMoney(v, "$");
}
</script>

<template>
  <Teleport to="body">
    <Transition name="pay-modal">
      <div
        v-if="show"
        class="fixed inset-0 z-[9998] flex items-center justify-center bg-slate-950/70 p-3 sm:p-4"
        role="dialog"
        aria-modal="true"
        dir="rtl"
        @click.self="emit('close')"
      >
        <!-- Always dark-safe tokens: teleported outside main, so app.css dark overrides may not apply -->
        <div
          class="flex w-full max-w-md max-h-[90vh] flex-col overflow-hidden rounded-xl border border-slate-600 bg-slate-900 text-slate-100 shadow-2xl"
        >
          <!-- Header -->
          <div class="shrink-0 border-b border-slate-700 px-4 py-3.5 sm:px-5">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                  {{ $t("complet_pay") }}
                </p>
                <h2 class="mt-0.5 truncate text-lg font-bold text-white">
                  {{ carTitle }}
                </h2>
              </div>
              <button
                type="button"
                class="rounded-lg px-2 py-1 text-sm text-slate-400 transition hover:bg-slate-800 hover:text-slate-200"
                :aria-label="$t('cancel')"
                @click="emit('close')"
              >
                ✕
              </button>
            </div>
          </div>

          <!-- Body -->
          <div class="flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-5">
            <input id="car-payment-id" type="hidden" disabled v-model="formData.id" />

            <!-- KPI strip: solid slate surfaces + semantic accent text (no pastel/white cards) -->
            <div class="grid grid-cols-3 gap-2">
              <div class="rounded-lg border border-slate-600 bg-slate-800 px-2.5 py-2.5">
                <p class="text-[10px] font-semibold text-slate-300">
                  {{ $t("totalForCar") }}
                </p>
                <p class="mt-1 font-mono text-sm font-bold tabular-nums text-sky-300 sm:text-base">
                  {{ money(total) }}
                  <span class="text-[10px] font-normal text-sky-400/80">$</span>
                </p>
              </div>
              <div class="rounded-lg border border-emerald-700/70 bg-slate-800 px-2.5 py-2.5">
                <p class="text-[10px] font-semibold text-emerald-400">
                  {{ $t("paid_amount") }}
                </p>
                <p class="mt-1 font-mono text-sm font-bold tabular-nums text-emerald-300 sm:text-base">
                  {{ money(paid) }}
                  <span class="text-[10px] font-normal text-emerald-400/80">$</span>
                </p>
              </div>
              <div class="rounded-lg border border-rose-700/70 bg-slate-800 px-2.5 py-2.5">
                <p class="text-[10px] font-semibold text-rose-400">
                  {{ $t("debtRemaining") }}
                </p>
                <p class="mt-1 font-mono text-sm font-bold tabular-nums text-rose-300 sm:text-base">
                  {{ money(remaining) }}
                  <span class="text-[10px] font-normal text-rose-400/80">$</span>
                </p>
              </div>
            </div>

            <!-- Progress -->
            <div>
              <div class="mb-1.5 flex items-center justify-between text-[11px] font-medium text-slate-300">
                <span>نسبة السداد</span>
                <span class="font-mono tabular-nums text-slate-100">{{ paidPercent }}%</span>
              </div>
              <div class="h-2.5 overflow-hidden rounded-full bg-slate-700">
                <div
                  class="h-full rounded-full bg-emerald-500 transition-all duration-300 ease-out"
                  :style="{ width: `${paidPercent}%` }"
                />
              </div>
            </div>

            <!-- Amount -->
            <div>
              <label
                for="amountPayment"
                class="mb-1.5 block text-xs font-semibold text-slate-200"
              >
                {{ $t("amount") }}
              </label>
              <div class="relative">
                <input
                  id="amountPayment"
                  v-model="formData.amountPayment"
                  type="number"
                  min="0"
                  step="0.01"
                  inputmode="decimal"
                  class="w-full rounded-lg border border-slate-600 bg-slate-950 py-2.5 pe-10 ps-3 font-mono text-base font-semibold tabular-nums text-white placeholder-slate-400 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
                  @input="calculateAmount"
                />
                <span
                  class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3 text-sm font-semibold text-slate-400"
                >
                  $
                </span>
              </div>
              <p class="mt-1 text-[11px] text-slate-400">
                الحد الأقصى: {{ money(remaining) }} $
              </p>
            </div>

            <!-- Hand / بيد -->
            <div v-if="isHandPayment">
              <label class="mb-1.5 block text-xs font-semibold text-slate-200">
                طريقة الدفع
              </label>
              <div
                class="inline-flex items-center gap-2 rounded-lg border border-emerald-700/60 bg-slate-800 px-3 py-2 text-sm font-semibold text-emerald-300"
              >
                <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true" />
                بيد (نقدي)
              </div>
            </div>

            <!-- Note -->
            <div>
              <label
                for="notePayment"
                class="mb-1.5 block text-xs font-semibold text-slate-200"
              >
                {{ $t("note") }}
              </label>
              <input
                id="notePayment"
                v-model="noteExtra"
                type="text"
                class="w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2.5 text-sm text-white placeholder-slate-400 shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-500/30"
                :placeholder="isHandPayment ? 'ملاحظة إضافية (اختياري)' : $t('note')"
              />
            </div>
          </div>

          <!-- Footer -->
          <div class="grid shrink-0 grid-cols-2 gap-2 border-t border-slate-700 bg-slate-950/50 px-4 py-3.5 sm:px-5">
            <button
              type="button"
              class="w-full rounded-lg border border-slate-600 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
              @click="emit('close')"
            >
              {{ $t("cancel") }}
            </button>
            <button
              type="button"
              class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="!formData.amountPayment"
              @click="confirm"
            >
              {{ $t("yes") }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.pay-modal-enter-active,
.pay-modal-leave-active {
  transition: opacity 0.2s ease;
}
.pay-modal-enter-active .max-w-md,
.pay-modal-leave-active .max-w-md {
  transition: transform 0.2s ease;
}
.pay-modal-enter-from,
.pay-modal-leave-to {
  opacity: 0;
}
.pay-modal-enter-from .max-w-md,
.pay-modal-leave-to .max-w-md {
  transform: scale(1.04);
}
</style>
