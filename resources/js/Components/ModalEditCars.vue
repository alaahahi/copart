<script setup>
import { ref, computed } from "vue";
import { ModelListSelect } from "vue-search-select"
  // Import everythModelSelecting
import "vue-search-select/dist/VueSearchSelect.css"

const props = defineProps({
  show: Boolean,
  formData: Object,
  client: Array,
  auctions: { type: Array, default: () => [] },
});
function getTodayDate() {
  const today = new Date();
  const year = today.getFullYear();
  const month = String(today.getMonth() + 1).padStart(2, "0");
  const day = String(today.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}
function check_vin(v){
  if(v){
    axios.get(`/api/check_vin?car_vin=${v}`)
  .then(response => {
    showErrorVin.value =  response.data;
  })
  .catch(error => {
    console.error(error);
  })
  }
}
let showClient = ref(false);
let showErrorVin = ref(false);
let exchangeRateError= ref(false);
function validateExchangeRate(v) {
      const input = props.formData.dolar_price;
      if (/^\d{6}$/.test(input)) {
        exchangeRateError.value = false;
      } else {
        exchangeRateError.value = true;
      }
    }
</script>
  <template>
  <Transition name="modal">
    <div v-if="show" class="car-modal-overlay" @click.self="$emit('close')">
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
          <!-- Section: التاجر -->
          <section class="car-section" v-if="formData.results==0">
            <h3 class="car-section-title">
              <span class="car-section-dot bg-emerald-400"></span>
              {{ $t("car_owner") }}
            </h3>

            <div v-if="!showClient" class="flex flex-col sm:flex-row sm:items-end gap-3">
              <div class="flex-1 min-w-0 car-select-wrap">
                <label class="car-label" for="color_id">{{ $t("car_owner") }}</label>
                <ModelListSelect
                  optionValue="id"
                  optionText="name"
                  v-model="formData.client_id"
                  :list="client"
                  :placeholder="$t('selectCustomer')">
                </ModelListSelect>
              </div>
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="car-label" for="edit_client_name">{{ $t("car_owner") }}</label>
                <input
                  id="edit_client_name"
                  type="text"
                  class="car-input"
                  v-model="formData.client_name"
                />
              </div>
              <div class="sm:col-span-2">
                <button
                  type="button"
                  @click="
                    showClient = false;
                    formData.client = '';
                  "
                  class="car-btn car-btn-muted"
                >
                  {{ $t("selectCustomer") }}
                </button>
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
                  v-model="formData.shipping_dolar"
                />
              </div>
              <div>
                <label class="car-label" for="dinar">نقل امريكا</label>
                <input
                  id="dinar"
                  type="number"
                  class="car-input"
                  v-model="formData.dinar"
                />
              </div>
              <div>
                <label class="car-label" for="coc_dolar">ريكفري</label>
                <input
                  id="coc_dolar"
                  type="number"
                  class="car-input"
                  v-model="formData.coc_dolar"
                />
              </div>
              <div>
                <label class="car-label" for="checkout">مصاريف تصليح</label>
                <input
                  id="checkout"
                  type="number"
                  class="car-input"
                  v-model="formData.checkout"
                />
              </div>
            </div>
          </section>

          <!-- Section: نقل/تكاليف أربيل والجمرك -->
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
                  v-model="formData.expenses"
                />
              </div>
              <div>
                <label class="car-label" for="erbil_clearance">تخليص</label>
                <input
                  id="erbil_clearance"
                  type="number"
                  class="car-input"
                  v-model="formData.erbil_clearance"
                />
              </div>
              <div>
                <label class="car-label" for="erbil_transfer">نقل</label>
                <input
                  id="erbil_transfer"
                  type="number"
                  class="car-input"
                  v-model="formData.erbil_transfer"
                />
              </div>
              <div>
                <label class="car-label" for="erbil_border_repair">تصليح حدود</label>
                <input
                  id="erbil_border_repair"
                  type="number"
                  class="car-input"
                  v-model="formData.erbil_border_repair"
                />
              </div>
              <div>
                <label class="car-label" for="erbil_customs">جمرك</label>
                <input
                  id="erbil_customs"
                  type="number"
                  class="car-input"
                  v-model="formData.erbil_customs"
                />
              </div>
              <div>
                <label class="car-label" for="commission">مصاريف اربيل</label>
                <input
                  id="commission"
                  type="number"
                  class="car-input"
                  v-model="formData.commission"
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
            </div>
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
  :deep(.ui.fluid.search.selection.dropdown){
    justify-content: revert;
    display: flex;
    min-height: 40px;
    border-radius: 0.55rem;
    border: 1px solid rgba(100, 116, 139, 0.45);
  }
  :deep(.ui.dropdown .menu .selected.item){
    background-color: #e012035d;
  }
  :deep(.ui.dropdown .menu>.item) {
    text-align: right;
  }

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
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid rgba(100, 116, 139, 0.35);
  background: rgba(15, 23, 42, 0.6);
  flex-shrink: 0;
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
</style>
