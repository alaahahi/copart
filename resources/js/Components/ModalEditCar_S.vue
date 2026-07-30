<script setup>
import { ref, computed, watch } from "vue";
import axios from "axios";
import Uploader from "vue-media-upload";
import { useToast } from "vue-toastification";
import { asNumber, formatNumber } from "@/utils/formatMoney";

const toast = useToast();
const emit = defineEmits(["a", "close", "allocation-returned"]);

const props = defineProps({
  show: Boolean,
  formData: Object,
  client: Array,
  auctions: { type: Array, default: () => [] },
  shippingRoutes: { type: Array, default: () => [] },
});

const activeTab = ref("details");
const returningIndex = ref(null);
const errors = ref({});

const fixed = (v, digits = 0) => formatNumber(v, { maxDecimals: digits });

watch(
  () => props.show,
  (open) => {
    if (open) {
      activeTab.value = "details";
      returningIndex.value = null;
    }
  }
);

const allocations = computed(() => {
  const raw = props.formData?.payment_allocations;
  if (Array.isArray(raw)) return raw;
  if (typeof raw === "string") {
    try {
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch (_) {
      return [];
    }
  }
  return [];
});

const allocationSourceLabel = (source) => {
  if (source === "from_balance") return "allocation_from_balance";
  if (source === "legacy_balance") return "allocation_legacy";
  return "allocation_direct";
};

const canReturnAllocation = (row) => {
  const source = row?.source;
  return source === "from_balance" || source === "legacy_balance";
};

function getTodayDate() {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, "0");
  const day = String(today.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function check_vin(v) {
  if (v) {
    axios
      .get(`/api/check_vin?car_vin=${v}`)
      .then((response) => {
        showErrorVin.value = response.data;
      })
      .catch((error) => {
        console.error(error);
      });
  }
}

let showClient = ref(false);
let showErrorVin = ref(false);
let exchangeRateError = ref(false);

function validateExchangeRate() {
  const input = props.formData.dolar_price_s;
  if (/^\d{6}$/.test(input)) {
    exchangeRateError.value = false;
  } else {
    exchangeRateError.value = true;
  }
}

function changeMedia() {}
function media() {}
function addMedia() {}

function removeMedia(removedImage) {
  axios
    .get("/api/carsAnnualImageDel?img_type=contract&name=" + removedImage.name)
    .then(() => {
      toast.success("تم  حذف الصورة بنجاح", {
        timeout: 5000,
        position: "bottom-right",
        rtl: true,
      });
    })
    .catch((error) => {
      console.error(error);
    });
}

async function returnAllocation(index) {
  if (!props.formData?.id || returningIndex.value !== null) return;
  if (!window.confirm("إعادة هذا المبلغ للرصيد؟")) return;

  returningIndex.value = index;
  try {
    const { data } = await axios.post("/api/ReturnCarAllocation", {
      id: props.formData.id,
      index,
    });
    if (props.formData && data) {
      props.formData.payment_allocations = data.payment_allocations ?? [];
      props.formData.paid = data.paid;
      props.formData.discount = data.discount;
      props.formData.results = data.results;
    }
    toast.success("تمت إعادة المبلغ للرصيد", {
      timeout: 2500,
      position: "bottom-right",
      rtl: true,
    });
    emit("allocation-returned", data);
  } catch (error) {
    const msg =
      error?.response?.data?.message || "تعذر إعادة المبلغ للرصيد";
    toast.error(msg, {
      timeout: 4000,
      position: "bottom-right",
      rtl: true,
    });
  } finally {
    returningIndex.value = null;
  }
}

function formatAllocDate(at) {
  if (!at) return "—";
  try {
    const d = new Date(at);
    if (Number.isNaN(d.getTime())) return String(at);
    return d.toLocaleString("ar-IQ");
  } catch (_) {
    return String(at);
  }
}
</script>
  <template>
  <Transition name="modal">
    <!-- No @click.self close: date/select/uploader interactions were closing the modal accidentally. -->
    <div v-if="show" class="car-modal-overlay">
      <div class="car-modal-panel">
        <!-- Header -->
        <div class="car-modal-header">
          <slot name="header">
            <h2 class="car-modal-title">
              <span class="car-modal-title-badge">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-5 h-5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 13l1.5-4.5A2 2 0 016.4 7h11.2a2 2 0 011.9 1.5L21 13m-18 0v5a1 1 0 001 1h1a1 1 0 001-1v-1h12v1a1 1 0 001 1h1a1 1 0 001-1v-5m-18 0h18" />
                  <circle cx="7" cy="16" r="0.5" fill="currentColor" />
                  <circle cx="17" cy="16" r="0.5" fill="currentColor" />
                </svg>
              </span>
              {{ $t("edit_car") }}
            </h2>
          </slot>
          <button type="button" class="car-modal-close" @click="$emit('close')" aria-label="close">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Body -->
        <div class="car-modal-body">
          <div class="car-tabs" role="tablist">
            <button
              type="button"
              role="tab"
              class="car-tab"
              :class="{ 'car-tab--active': activeTab === 'details' }"
              :aria-selected="activeTab === 'details'"
              @click="activeTab = 'details'"
            >
              {{ $t("edit_car") }}
            </button>
            <button
              type="button"
              role="tab"
              class="car-tab"
              :class="{ 'car-tab--active': activeTab === 'payments' }"
              :aria-selected="activeTab === 'payments'"
              @click="activeTab = 'payments'"
            >
              {{ $t("payments") }}
            </button>
          </div>

          <div v-show="activeTab === 'details'" class="car-tab-panel space-y-4">
          <!-- Section: التاجر -->
          <section class="car-section">
            <h3 class="car-section-title">
              <span class="car-section-dot bg-emerald-400"></span>
              {{ $t("car_owner") }}
            </h3>

            <div class="flex flex-col sm:flex-row sm:items-end gap-3">
              <div class="flex-1 min-w-0 car-select-wrap">
                <label class="car-label" for="color_id">{{ $t("car_owner") }}</label>
                <select
                  v-if="!showClient"
                  v-model="formData.client_id"
                  id="color_id"
                  class="car-input"
                  disabled>
                  <option selected disabled>
                    {{ $t("selectCustomer") }}
                  </option>
                  <option
                    v-for="(card, index) in client"
                    :key="index"
                    :value="card.id"
                    >
                    {{ card.name }}
                  </option>
                </select>
              </div>
            </div>
          </section>

          <!-- Section: بيانات السيارة -->
          <section class="car-section">
            <h3 class="car-section-title">
              <span class="car-section-dot bg-sky-400"></span>
              بيانات السيارة
            </h3>
            <div class="car-grid car-grid-5">
              <div>
                <label class="car-label" for="car_type">{{ $t("car_type") }}</label>
                <input
                  id="car_type"
                  type="text"
                  class="car-input"
                  v-model="formData.car_type"
                />
              </div>
              <div>
                <label class="car-label" for="year">{{ $t("year") }}</label>
                <input
                  id="year"
                  type="number"
                  class="car-input"
                  v-model="formData.year"
                />
              </div>
              <div>
                <label class="car-label" for="car_color">{{ $t("color") }}</label>
                <input
                  id="car_color"
                  type="text"
                  class="car-input"
                  v-model="formData.car_color"
                />
              </div>
              <div>
                <label class="car-label" for="vin">{{ $t("vin") }}</label>
                <input
                  id="vin"
                  type="text"
                  @change="check_vin(formData.vin)"
                  class="car-input"
                  v-model="formData.vin"
                />
                <p class="car-error" v-if="showErrorVin">رقم الشاصي مستخدم</p>
              </div>
              <div>
                <label class="car-label" for="car_number">{{ $t("car_number") }}</label>
                <input
                  id="car_number"
                  type="number"
                  class="car-input"
                  v-model="formData.car_number"
                />
              </div>
              <div>
                <label class="car-label" for="auction_id">{{ $t("auction") }}</label>
                <select id="auction_id" class="car-input" v-model="formData.auction_id">
                  <option :value="null">{{ $t("select_auction") }}</option>
                  <option v-for="a in auctions" :key="a.id" :value="a.id">
                    {{ a.name }}
                  </option>
                </select>
              </div>
            </div>
          </section>

          <!-- Section: تكاليف أمريكا -->
          <section class="car-section">
            <h3 class="car-section-title">
              <span class="car-section-dot bg-amber-400"></span>
              تكاليف أمريكا
            </h3>
            <div class="car-grid car-grid-4">
              <div>
                <label class="car-label" for="shipping_dolar">سعر السيارة امريكا</label>
                <input
                  id="shipping_dolar"
                  type="number"
                  class="car-input"
                  v-model="formData.shipping_dolar_s"
                />
              </div>
              <div>
                <label class="car-label" for="dinar">نقل امريكا</label>
                <input
                  id="dinar"
                  type="number"
                  class="car-input"
                  v-model="formData.dinar_s"
                />
              </div>
              <div>
                <label class="car-label" for="coc_dolar_s">ريكفري</label>
                <input
                  id="coc_dolar_s"
                  type="number"
                  class="car-input"
                  v-model="formData.coc_dolar_s"
                />
              </div>
              <div>
                <label class="car-label" for="checkout_s">مصاريف تصليح</label>
                <input
                  id="checkout_s"
                  type="number"
                  class="car-input"
                  v-model="formData.checkout_s"
                />
              </div>
            </div>
          </section>

          <!-- Section: نقل اربيل والجمرك -->
          <section class="car-section">
            <h3 class="car-section-title">
              <span class="car-section-dot bg-rose-400"></span>
              نقل اربيل والجمرك
            </h3>
            <div class="car-grid car-grid-4">
              <div>
                <label class="car-label" for="expenses">شحن اربيل وتخليص</label>
                <input
                  id="expenses"
                  type="number"
                  class="car-input"
                  v-model="formData.expenses_s"
                />
              </div>
              <div>
                <label class="car-label" for="erbil_clearance_s">تخليص</label>
                <input
                  id="erbil_clearance_s"
                  type="number"
                  class="car-input"
                  v-model="formData.erbil_clearance_s"
                />
              </div>
              <div>
                <label class="car-label" for="erbil_transfer_s">نقل</label>
                <input
                  id="erbil_transfer_s"
                  type="number"
                  class="car-input"
                  v-model="formData.erbil_transfer_s"
                />
              </div>
              <div>
                <label class="car-label" for="erbil_border_repair_s">تصليح حدود</label>
                <input
                  id="erbil_border_repair_s"
                  type="number"
                  class="car-input"
                  v-model="formData.erbil_border_repair_s"
                />
              </div>
              <div>
                <label class="car-label" for="erbil_customs_s">جمرك</label>
                <input
                  id="erbil_customs_s"
                  type="number"
                  class="car-input"
                  v-model="formData.erbil_customs_s"
                />
              </div>
              <div>
                <label class="car-label" for="commission_s">مصاريف اربيل</label>
                <input
                  id="commission_s"
                  type="number"
                  class="car-input"
                  v-model="formData.commission_s"
                />
              </div>
              <div>
                <label class="car-label" for="date">{{ $t("date") }}</label>
                <input
                  id="date"
                  type="date"
                  class="car-input"
                  v-model="formData.date"
                />
              </div>
              <div>
                <label class="car-label" for="shipping_route_id">{{ $t("shipping_route") }}</label>
                <select
                  id="shipping_route_id"
                  class="car-input"
                  v-model="formData.shipping_route_id"
                >
                  <option :value="null">{{ $t("select_shipping_route") }}</option>
                  <option
                    v-for="r in shippingRoutes"
                    :key="r.id"
                    :value="r.id"
                  >
                    {{ r.name }}
                  </option>
                </select>
              </div>
            </div>
          </section>

          <!-- Section: الصور -->
          <section class="car-section">
            <h3 class="car-section-title">
              <span class="car-section-dot bg-indigo-400"></span>
              الصور
            </h3>
            <Uploader
                :server="'/api/carsAnnualUpload?img_type=contract&carId='+formData.id"
                :is-invalid="errors?.media ? true : false"
                @change="changeMedia"
                @initMedia="media"
                location="/public/uploadsResized"
                :media="formData.car_images"
                @add="addMedia"
                @remove="removeMedia"
            />
            <p v-if="errors?.media" class="car-error">{{ errors?.media[0] }}</p>
          </section>

          <!-- Section: ملاحظات -->
          <section class="car-section">
            <h3 class="car-section-title">
              <span class="car-section-dot bg-slate-400"></span>
              {{ $t("note") }}
            </h3>
            <input
              id="note"
              type="text"
              class="car-input"
              v-model="formData.note"
            />
          </section>
          </div>

          <div v-show="activeTab === 'payments'" class="car-tab-panel">
            <section class="car-section">
              <h3 class="car-section-title">
                <span class="car-section-dot bg-amber-400"></span>
                {{ $t("payments") }}
                <span class="car-payments-paid ms-auto text-sm font-semibold text-emerald-300">
                  {{ $t("paid") }}: {{ fixed(formData.paid, 0) }}$
                </span>
              </h3>

              <div
                v-if="!allocations.length"
                class="rounded-lg border border-slate-600 bg-slate-900/80 px-4 py-8 text-center text-sm text-slate-300"
              >
                {{ $t("no_car_payments") }}
              </div>

              <ul v-else class="space-y-2">
                <li
                  v-for="(row, ai) in allocations"
                  :key="`alloc-${ai}-${row.transaction_id || row.at || ai}`"
                  class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2.5 text-start"
                >
                  <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                      <div class="text-sm font-semibold text-white">
                        {{ $t(allocationSourceLabel(row.source)) }}
                        <span class="ms-1 font-mono text-emerald-300">
                          {{ fixed(row.amount, 0) }}$
                        </span>
                        <span
                          v-if="asNumber(row.discount)"
                          class="ms-1 text-xs font-normal text-amber-300"
                        >
                          · {{ $t("discount") }} {{ fixed(row.discount, 0) }}
                        </span>
                      </div>
                      <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-slate-300">
                        <span v-if="row.transaction_id">
                          {{ $t("allocation_tx") }} #{{ row.transaction_id }}
                        </span>
                        <span>{{ formatAllocDate(row.at) }}</span>
                        <span v-if="row.by">{{ $t("user") }} #{{ row.by }}</span>
                        <span v-if="row.note" class="text-slate-400">{{ row.note }}</span>
                      </div>
                      <p
                        v-if="row.source === 'direct_payment'"
                        class="mt-1 text-[11px] text-sky-300"
                      >
                        {{ $t("allocation_delete_via_accounting") }}
                      </p>
                    </div>
                    <button
                      v-if="canReturnAllocation(row)"
                      type="button"
                      class="shrink-0 rounded-md bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-rose-500 disabled:opacity-50"
                      :disabled="returningIndex !== null"
                      @click="returnAllocation(ai)"
                    >
                      {{
                        returningIndex === ai
                          ? "…"
                          : $t("return_to_balance")
                      }}
                    </button>
                  </div>
                </li>
              </ul>
            </section>
          </div>
        </div>

        <!-- Footer -->
        <div class="car-modal-footer">
          <button
            class="car-btn car-btn-muted"
            @click="$emit('close')"
          >
            {{ $t("cancel") }}
          </button>
          <button
            class="car-btn car-btn-primary"
            @click="
              formData.date = formData.date
                ? formData.date
                : getTodayDate();
              $emit('a', formData);
              formData = '';
            "
            :disabled="(!formData.client_id)&&(!formData.client_name)">
            {{ $t("yes") }}
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>
  
  <style scoped>
/* ===== Car Add/Edit Modal — professional dark ERP theme ===== */
.car-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 9998;
  background-color: rgba(2, 6, 23, 0.72);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.car-modal-panel {
  width: 100%;
  max-width: 90vw;
  max-height: 92vh;
  display: flex;
  flex-direction: column;
  background: linear-gradient(180deg, #101828 0%, #0b1220 100%);
  border: 1px solid rgba(100, 116, 139, 0.35);
  border-radius: 1rem;
  box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.6);
  overflow: hidden;
  animation: car-modal-pop 0.2s ease-out;
}

@media (max-width: 640px) {
  .car-modal-panel {
    max-width: 96vw;
    max-height: 95vh;
  }
}

.car-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 1rem 1.5rem;
  border-bottom: 1px solid rgba(100, 116, 139, 0.35);
  background: linear-gradient(90deg, rgba(30, 41, 59, 0.6), rgba(15, 23, 42, 0.6));
  flex-shrink: 0;
}

.car-modal-title {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 1.15rem;
  font-weight: 700;
  color: #f1f5f9;
  margin: 0;
}

.car-modal-title-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.1rem;
  height: 2.1rem;
  border-radius: 0.65rem;
  background: rgba(56, 189, 248, 0.12);
  color: #38bdf8;
  flex-shrink: 0;
}

.car-modal-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.1rem;
  height: 2.1rem;
  border-radius: 0.6rem;
  color: #94a3b8;
  background: transparent;
  transition: background-color 0.15s ease, color 0.15s ease;
  flex-shrink: 0;
}
.car-modal-close:hover {
  background: rgba(148, 163, 184, 0.15);
  color: #f1f5f9;
}

.car-modal-body {
  flex: 1 1 auto;
  overflow-y: auto;
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
}

.car-tabs {
  display: flex;
  gap: 0.35rem;
  padding: 0.25rem;
  border-radius: 0.75rem;
  background: rgba(15, 23, 42, 0.85);
  border: 1px solid rgba(100, 116, 139, 0.45);
  flex-shrink: 0;
}

.car-tab {
  flex: 1 1 0;
  border-radius: 0.55rem;
  padding: 0.55rem 0.75rem;
  font-size: 0.85rem;
  font-weight: 700;
  color: #cbd5e1;
  background: transparent;
  transition: background-color 0.15s ease, color 0.15s ease;
}

.car-tab:hover {
  color: #f1f5f9;
  background: rgba(51, 65, 85, 0.55);
}

.car-tab--active {
  color: #fff;
  background: #0f766e;
}

.car-tab-panel {
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
}

.car-payments-paid {
  letter-spacing: 0.01em;
}

.car-modal-body::-webkit-scrollbar {
  width: 10px;
}
.car-modal-body::-webkit-scrollbar-track {
  background: transparent;
}
.car-modal-body::-webkit-scrollbar-thumb {
  background-color: rgba(100, 116, 139, 0.5);
  border-radius: 999px;
}

.car-modal-footer {
  display: grid;
  grid-template-columns: 1fr 1fr;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  padding: 1rem 1.5rem;
  border-top: 1px solid rgba(100, 116, 139, 0.35);
  background: rgba(15, 23, 42, 0.6);
  flex-shrink: 0;
}

.car-modal-footer .car-btn {
  width: 100%;
  min-width: 0;
}

.car-section {
  background: rgba(30, 41, 59, 0.45);
  border: 1px solid rgba(100, 116, 139, 0.3);
  border-radius: 0.85rem;
  padding: 1rem 1.1rem 1.2rem;
}

.car-section-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.95rem;
  font-weight: 700;
  color: #e2e8f0;
  margin: 0 0 0.85rem 0;
  padding-bottom: 0.6rem;
  border-bottom: 1px solid rgba(100, 116, 139, 0.25);
}

.car-section-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 999px;
  flex-shrink: 0;
}

.car-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.9rem 1rem;
}
@media (min-width: 640px) {
  .car-grid-4 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .car-grid-5 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (min-width: 1024px) {
  .car-grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .car-grid-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }
}

.car-label {
  display: block;
  margin-bottom: 0.35rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #94a3b8;
  letter-spacing: 0.01em;
}

.car-input,
.car-input.ui.fluid.dropdown {
  display: block;
  width: 100%;
  border-radius: 0.55rem;
  border: 1px solid rgba(100, 116, 139, 0.45);
  background-color: rgba(15, 23, 42, 0.7);
  color: #f1f5f9;
  padding: 0.55rem 0.75rem;
  font-size: 0.875rem;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
}
.car-input::placeholder {
  color: #64748b;
}
.car-input:focus {
  outline: none;
  border-color: #38bdf8;
  background-color: rgba(15, 23, 42, 0.95);
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.25);
}
.car-input:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.car-error {
  margin-top: 0.35rem;
  font-size: 0.75rem;
  color: #f87171;
}

.car-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  padding: 0.6rem 1.1rem;
  border-radius: 0.6rem;
  font-size: 0.85rem;
  font-weight: 700;
  color: #fff;
  transition: filter 0.15s ease, opacity 0.15s ease;
  white-space: nowrap;
}
.car-btn:hover { filter: brightness(1.08); }
.car-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.car-btn-primary { background-color: #e11d48; }
.car-btn-success { background-color: #10b981; }
.car-btn-muted { background-color: #475569; }

@keyframes car-modal-pop {
  from { opacity: 0; transform: scale(0.97); }
  to { opacity: 1; transform: scale(1); }
}

/*
   * The following styles are auto-applied to elements with
   * transition="modal" when their visibility is toggled
   * by Vue.js.
   */

.modal-enter-from {
  opacity: 0;
}

.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .car-modal-panel,
.modal-leave-to .car-modal-panel {
  -webkit-transform: scale(0.96);
  transform: scale(0.96);
}

/* Uploader component width fix inside dark panel */
:deep(.vue-media-upload) {
  width: 100%;
}
</style>
