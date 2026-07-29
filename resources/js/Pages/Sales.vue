<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/inertia-vue3';
import show from "@/Components/icon/show.vue";
import trash from "@/Components/icon/trash.vue";
import edit from "@/Components/icon/edit.vue";
import ModalDelCar from "@/Components/ModalDelCar.vue";
import ModalEditCars from "@/Components/ModalEditCar_S.vue";
import InfiniteLoading from "v3-infinite-loading";
import "v3-infinite-loading/lib/style.css";
import debounce from 'lodash/debounce';
import { useToast } from "vue-toastification";
import axios from 'axios';
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ModelListSelect } from 'vue-search-select';
import 'vue-search-select/dist/VueSearchSelect.css';
import { erbilTransferSubtotal, syncSalesErbilFromPurchase } from "@/utils/carFields";
import { asNumber, formatMoney } from "@/utils/formatMoney";
import { carPaymentStatusMeta, canDeleteCar } from "@/utils/carPaymentStatus";
import CarsGridView from "@/Components/CarsGridView.vue";
import SearchInput from "@/Components/SearchInput.vue";

const props = defineProps({
  client: Array,
  auctions: { type: Array, default: () => [] },
});

const { t } = useI18n();
const toast = useToast();
const money = (v) => formatMoney(v, "$");

const CARS_VIEW_KEY = "sales-cars-view";
const carsViewMode = ref(
  typeof localStorage !== "undefined" && localStorage.getItem(CARS_VIEW_KEY) === "grid"
    ? "grid"
    : "list"
);
watch(carsViewMode, (mode) => {
  try {
    localStorage.setItem(CARS_VIEW_KEY, mode);
  } catch (_) {
    /* ignore quota / private mode */
  }
});
const setCarsViewMode = (mode) => {
  carsViewMode.value = mode === "grid" ? "grid" : "list";
};

const showModalEditCars = ref(false);
const showModalDelCar = ref(false);
const formData = ref({});
const car = ref([]);
const json = ref({});
const resetData = ref(false);
const user_id = ref('');
let page = 1;
let q = '';

const merchantOptions = computed(() => [
  { id: '', name: t('allOwners') },
  ...(props.client || []),
]);

watch(user_id, () => {
  refresh();
});

function openModalEditCars(form = {}) {
  formData.value = JSON.parse(JSON.stringify(form || {}));
  if (formData.value.shipping_dolar_s == 0) {
    formData.value.shipping_dolar_s = formData.value.shipping_dolar;
  }
  if (formData.value.coc_dolar_s == 0) {
    formData.value.coc_dolar_s = formData.value.coc_dolar;
  }
  if (formData.value.checkout_s == 0) {
    formData.value.checkout_s = formData.value.checkout;
  }
  if (formData.value.dinar_s == 0) {
    formData.value.dinar_s = formData.value.dinar;
  }
  syncSalesErbilFromPurchase(formData.value);
  showModalEditCars.value = true;
}

function openModalDelCar(form = {}) {
  formData.value = form;
  showModalDelCar.value = true;
}

const refresh = () => {
  page = 0;
  car.value.length = 0;
  resetData.value = !resetData.value;
};

const getResultsCar = async ($state) => {
  try {
    const response = await axios.get(`/getIndexCar`, {
      params: {
        limit: 100,
        page,
        q,
        user_id: user_id.value || '',
      },
    });

    json.value = response.data;

    if (json.value.data.length < 100) {
      car.value.push(...json.value.data);
      $state.complete();
    } else {
      car.value.push(...json.value.data);
      $state.loaded();
    }

    page++;
  } catch (error) {
    console.log(error);
  }
};

function confirmUpdateCar(V) {
  showModalEditCars.value = false;

  axios
    .post("/api/updateCarsS", V)
    .then(() => {
      toast.success("تم التعديل بنجاح", {
        timeout: 2000,
        position: "bottom-right",
        rtl: true,
      });
      refresh();
    })
    .catch(() => {
      toast.error("لم التعديل بنجاح", {
        timeout: 2000,
        position: "bottom-right",
        rtl: true,
      });
    });
}

function onAllocationReturned(updated) {
  if (!updated?.id) return;
  const idx = car.value.findIndex((c) => c.id === updated.id);
  if (idx >= 0) {
    car.value[idx] = {
      ...car.value[idx],
      payment_allocations: updated.payment_allocations,
      paid: updated.paid,
      discount: updated.discount,
      results: updated.results,
    };
  }
}

function confirmDelCar(V) {
  axios
    .post("/api/DelCar", V)
    .then(() => {
      showModalDelCar.value = false;
      toast.success("تم التعديل بنجاح وخصم المبلغ من دين الزبون", {
        timeout: 3000,
        position: "bottom-right",
        rtl: true,
      });
      refresh();
    })
    .catch((error) => {
      console.error(error);
    });
}

const debouncedGetResultsCar = debounce(refresh, 500);

function getImageUrl(name) {
  return `/public/uploadsResized/${name}`;
}
function getDownloadUrl(name) {
  return `/public/uploads/${name}`;
}

/** Solid dark-safe row surfaces from payment amounts. */
function rowClass(row) {
  const { status } = carPaymentStatusMeta(row);
  if (status === "paid") {
    return "bg-emerald-100 text-slate-800 dark:bg-emerald-900 dark:text-slate-100";
  }
  if (status === "partially_paid") {
    return "bg-amber-100 text-slate-800 dark:bg-amber-900 dark:text-slate-100";
  }
  if (status === "unpaid" && asNumber(row?.total_s) > 0) {
    return "bg-rose-100 text-slate-800 dark:bg-rose-900 dark:text-slate-100";
  }
  return "bg-white text-slate-800 dark:bg-slate-900 dark:text-slate-100";
}
</script>

<template>
  <Head :title="$t('sales')" />

  <ModalEditCars
    :formData="formData"
    :show="showModalEditCars ? true : false"
    :client="client"
    :auctions="auctions"
    @a="confirmUpdateCar($event)"
    @allocation-returned="onAllocationReturned"
    @close="showModalEditCars = false"
  >
    <template #header />
  </ModalEditCars>

  <ModalDelCar
    :show="showModalDelCar ? true : false"
    :formData="formData"
    @a="confirmDelCar($event)"
    @close="showModalDelCar = false"
  >
    <template #header>
      <h2 class="mb-5 text-center text-slate-800 dark:text-slate-200">
        هل متأكد من حذف السيارة؟
      </h2>
    </template>
  </ModalDelCar>

  <AuthenticatedLayout>
    <div
      v-if="$page.props.auth.user.type_id == 1 || $page.props.auth.user.type_id == 6"
      class="py-4 sm:py-6"
    >
      <div class="mx-auto max-w-9xl px-3 sm:px-6 lg:px-8">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <!-- Header + filters -->
          <div class="border-b border-slate-200 p-4 dark:border-slate-700">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
              <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">
                {{ $t("sales") }}
              </h1>
              <div
                class="inline-flex rounded-lg border border-slate-300 bg-slate-100 p-0.5 shadow-sm dark:border-slate-600 dark:bg-slate-800"
                role="group"
                :aria-label="$t('view_mode')"
              >
                <button
                  type="button"
                  class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold transition"
                  :class="
                    carsViewMode === 'list'
                      ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white'
                      : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100'
                  "
                  :aria-pressed="carsViewMode === 'list'"
                  @click="setCarsViewMode('list')"
                >
                  <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M3 5h14a1 1 0 110 2H3a1 1 0 110-2zm0 4h14a1 1 0 110 2H3a1 1 0 110-2zm0 4h14a1 1 0 110 2H3a1 1 0 110-2z" />
                  </svg>
                  {{ $t("view_list") }}
                </button>
                <button
                  type="button"
                  class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold transition"
                  :class="
                    carsViewMode === 'grid'
                      ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white'
                      : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100'
                  "
                  :aria-pressed="carsViewMode === 'grid'"
                  @click="setCarsViewMode('grid')"
                >
                  <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M3 3h6v6H3V3zm8 0h6v6h-6V3zM3 11h6v6H3v-6zm8 0h6v6h-6v-6z" />
                  </svg>
                  {{ $t("view_grid") }}
                </button>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <SearchInput
                v-model="q"
                :placeholder="$t('search')"
                @input="debouncedGetResultsCar"
              />

              <div class="sales-merchant-select">
                <ModelListSelect
                  v-model="user_id"
                  option-value="id"
                  option-text="name"
                  :list="merchantOptions"
                  :placeholder="$t('selectCustomer')"
                />
              </div>
            </div>
          </div>

          <div class="p-4">
            <!-- Grid view: shared CarsGridView -->
            <CarsGridView
              v-if="carsViewMode === 'grid'"
              :cars="car"
              variant="sales"
            >
              <template #actions="{ car: row }">
                <button
                  type="button"
                  class="inline-flex items-center rounded-md bg-slate-600 px-1.5 py-0.5 text-white hover:bg-slate-700"
                  :title="$t('edit')"
                  @click="openModalEditCars(row)"
                >
                  <edit />
                </button>
                <button
                  v-if="canDeleteCar(row)"
                  type="button"
                  class="inline-flex items-center rounded-md bg-orange-500 px-1.5 py-0.5 text-white hover:bg-orange-600"
                  :title="$t('trash')"
                  @click="openModalDelCar(row)"
                >
                  <trash />
                </button>
                <Link
                  class="inline-flex items-center rounded-md bg-sky-600 px-1.5 py-0.5 text-white hover:bg-sky-700"
                  :href="route('showClients', row.client?.id)"
                >
                  <show />
                </Link>
              </template>
            </CarsGridView>

            <!-- List / table view -->
            <div
              v-else
              class="relative overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700"
            >
              <table class="w-full text-center text-sm text-slate-800 dark:text-slate-100">
                <thead>
                  <tr class="bg-slate-800 text-slate-100 dark:bg-slate-950">
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("no") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("car_owner") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("car_type") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("year") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("color") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("vin") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("car_number") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("car_price_usa") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("transfer_usa") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("recovery") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("repair_expenses") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("transfer_erbil") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("erbil_expenses") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("total") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("paid") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("remaining") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("date") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("note") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold" style="min-width: 140px">{{ $t("execute") }}</th>
                    <th class="px-2 py-3 text-xs font-semibold">{{ $t("storage") }}</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                  <tr
                    v-for="(row, index) in car"
                    :key="row.id"
                    :class="rowClass(row)"
                    class="border-b border-slate-200 hover:brightness-95 dark:border-slate-700 dark:hover:brightness-110"
                  >
                    <td class="px-1 py-2 tabular-nums">{{ index + 1 }}</td>
                    <td class="px-1 py-2 font-semibold text-slate-900 dark:text-white">{{ row.client?.name }}</td>
                    <td class="px-1 py-2">{{ row.car_type }}</td>
                    <td class="px-1 py-2">{{ row.year }}</td>
                    <td class="px-1 py-2">{{ row.car_color }}</td>
                    <td class="px-1 py-2 font-mono text-xs">{{ row.vin }}</td>
                    <td class="px-1 py-2">{{ row.car_number }}</td>
                    <td class="px-1 py-2 tabular-nums">{{ money(row.shipping_dolar_s) }}</td>
                    <td class="px-1 py-2 tabular-nums">{{ money(row.dinar_s) }}</td>
                    <td class="px-1 py-2 tabular-nums">{{ money(row.coc_dolar_s) }}</td>
                    <td class="px-1 py-2 tabular-nums">{{ money(row.checkout_s) }}</td>
                    <td class="px-1 py-2 tabular-nums">{{ money(erbilTransferSubtotal(row, true)) }}</td>
                    <td class="px-1 py-2 tabular-nums">{{ money(row.commission_s ?? 0) }}</td>
                    <td class="px-1 py-2 font-semibold tabular-nums">{{ money(row.total_s) }}</td>
                    <td class="px-1 py-2 tabular-nums text-emerald-700 dark:text-emerald-300">{{ money(row.paid) }}</td>
                    <td class="px-1 py-2 tabular-nums text-amber-700 dark:text-amber-300">
                      {{ money(asNumber(row.total_s) - asNumber(row.paid) - asNumber(row.discount)) }}
                    </td>
                    <td class="px-1 py-2 whitespace-nowrap">{{ row.date }}</td>
                    <td class="max-w-[140px] truncate px-1 py-2" :title="row.note">{{ row.note }}</td>
                    <td class="px-1 py-2">
                      <div class="flex items-center justify-center gap-1">
                        <button
                          type="button"
                          class="rounded bg-slate-600 px-1.5 py-1 text-white hover:bg-slate-700"
                          @click="openModalEditCars(row)"
                        >
                          <edit />
                        </button>
                        <button
                          v-if="canDeleteCar(row)"
                          type="button"
                          class="rounded bg-orange-500 px-1.5 py-1 text-white hover:bg-orange-600"
                          @click="openModalDelCar(row)"
                        >
                          <trash />
                        </button>
                        <Link
                          class="inline-flex rounded bg-sky-600 px-1.5 py-1 text-white hover:bg-sky-700"
                          :href="route('showClients', row.client?.id)"
                        >
                          <show />
                        </Link>
                      </div>
                    </td>
                    <td class="px-1 py-2">
                      <a
                        v-for="(image, imgIndex) in row.car_images"
                        :key="imgIndex"
                        :href="getDownloadUrl(image.name)"
                        target="_blank"
                        class="inline-block"
                      >
                        <img
                          :src="getImageUrl(image.name)"
                          alt=""
                          class="inline max-h-[50px] max-w-[100px] px-0.5"
                        />
                      </a>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="spaner mt-2">
              <InfiniteLoading :car="car" :identifier="resetData" @infinite="getResultsCar" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.sales-merchant-select :deep(.ui.fluid.dropdown),
.sales-merchant-select :deep(.ui.search.dropdown) {
  min-height: 42px;
  border-radius: 0.5rem;
  border-color: rgb(203 213 225);
}
</style>
