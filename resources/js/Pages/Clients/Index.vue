<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/inertia-vue3';
import ModalAddClient from "@/Components/ModalAddClient.vue";
import ModalEditClient from "@/Components/ModalEditClient.vue";
import InputLabel from "@/Components/InputLabel.vue";
import TextInput from "@/Components/TextInput.vue";
import axios from 'axios';
import ModalDelClient from "@/Components/ModalDelCar.vue";
import InfiniteLoading from "v3-infinite-loading";
import "v3-infinite-loading/lib/style.css";
import show from "@/Components/icon/show.vue";
import wallet from "@/Components/icon/wallet.vue";
import trash from "@/Components/icon/trash.vue";
import edit from "@/Components/icon/edit.vue";
import { ref, watch } from 'vue';
import debounce from 'lodash/debounce';

let showModalEditClient = ref(false);
let showModalAddClient = ref(false);
let showModalDelClient = ref(false);

const laravelData = ref([]);
let formData = ref({});
let user_id = ref(0);
const from = ref(0);
const to = ref(0);
const q = ref('');
const category = ref('0');
const isLoading = ref(0);
let resetData = ref(false);
let page = 1;
let json = ref({});
let controller = new AbortController();
const togglingIds = ref([]);
/** Active list tab: all clients vs clients flagged for accounting (قاسة / عرض بالمحاسبة). */
const activeTab = ref('all');

const refresh = () => {
  page = 1;
  laravelData.value.length = 0;
  resetData.value = !resetData.value;
};

const effectiveQ = () => {
  if (activeTab.value === 'qasa') {
    return 'show_in_dashboard';
  }
  if (category.value && category.value !== '0') {
    return category.value;
  }
  return q.value;
};

const getResultsCar = async ($state) => {
  try {
    const response = await axios.get(`api/getIndexClients`, {
      params: {
        limit: 25,
        page: page,
        q: effectiveQ(),
        user_id: user_id.value,
        from: from.value,
        to: to.value
      },
      signal: controller.signal
    });

    json.value = response.data;

    if (json.value.data.length < 25) {
      laravelData.value.push(...json.value.data);
      $state.complete();
    } else {
      laravelData.value.push(...json.value.data);
      $state.loaded();
    }

    page++;
  } catch (error) {
    if (error?.name !== 'CanceledError' && error?.code !== 'ERR_CANCELED') {
      console.error(error);
    }
  }
};

const abortRequest = () => {
  if (controller) {
    controller.abort();
  }
  controller = new AbortController();
};

watch([q, user_id, from, to, category, activeTab], () => {
  abortRequest();
  debouncedGetResultsCar();
});

watch(isLoading, (newVal) => {
  if (newVal === 1) {
    console.log('Loading data...');
  } else {
    console.log('Data loaded');
  }
});

const debouncedGetResultsCar = debounce(() => {
  isLoading.value = 1;
  refresh();
}, 500);

function setTab(tab) {
  if (activeTab.value === tab) return;
  activeTab.value = tab;
}

function openModalAddClient() {
  // show_in_dashboard defaults to false for every newly added merchant.
  formData.value = { name: '', phone: '', show_in_dashboard: false };
  showModalAddClient.value = true;
}
function openModalEditClient(form = {}) {
  formData.value = form;
  showModalEditClient.value = true;
}
function confirmAddClient(V) {
  axios.post('/api/clientsStore', V)
    .then(() => {
      window.location.reload();
    })
    .catch((error) => {
      console.error(error);
    });
}
function confirmEditClient(V) {
  axios.post('/api/clientsEdit', V)
    .then(() => {
      window.location.reload();
    })
    .catch((error) => {
      console.error(error);
    });
}
function openModalDelClient(form = {}) {
  formData.value = form;
  showModalDelClient.value = true;
}
function confirmDelClient(V) {
  axios.post('/api/delClient', V)
    .then(() => {
      showModalDelClient.value = false;
      refresh();
    })
    .catch((error) => {
      console.error(error);
    });
}

/**
 * Toggle show_in_dashboard — used by Accounting page to list client "قاسة" wallets.
 * Field name is historical; UI label is "عرض بالمحاسبة".
 */
async function toggleShowInDashboardQuick(user) {
  if (togglingIds.value.includes(user.id)) return;
  const next = !(user.show_in_dashboard || false);
  const prev = user.show_in_dashboard || false;
  togglingIds.value.push(user.id);
  user.show_in_dashboard = next;
  try {
    const response = await axios.post('/api/toggleShowInDashboard', {
      client_id: user.id,
      show_in_dashboard: next,
    });
    user.show_in_dashboard = response.data.show_in_dashboard;
    // If viewing قاسة tab and user turned off, drop from current list
    if (activeTab.value === 'qasa' && !user.show_in_dashboard) {
      laravelData.value = laravelData.value.filter((u) => u.id !== user.id);
    }
  } catch (error) {
    user.show_in_dashboard = prev;
    console.error(error);
  } finally {
    togglingIds.value = togglingIds.value.filter((id) => id !== user.id);
  }
}

function formatBalance(balance) {
  const n = Number(balance) || 0;
  return `${n.toLocaleString('en-US')} $`;
}

function unpaidCars(user) {
  return (Number(user.car_count) || 0) - (Number(user.car_count_completed) || 0);
}
</script>

<template>
  <Head title="التجار" />
  <AuthenticatedLayout>
    <ModalAddClient
      :show="showModalAddClient"
      :formData="formData"
      @a="confirmAddClient($event)"
      @close="showModalAddClient = false"
    />

    <ModalDelClient
      :show="showModalDelClient ? true : false"
      :formData="formData"
      @a="confirmDelClient($event)"
      @close="showModalDelClient = false"
    >
      <template #header>
        <h2 class="mb-5 dark:text-white text-center">
          هل متأكد من حذف التاجر
          {{ formData.name }}
          ؟
        </h2>
      </template>
    </ModalDelClient>

    <ModalEditClient
      :show="showModalEditClient"
      :formData="formData"
      @a="confirmEditClient($event)"
      @close="showModalEditClient = false"
    />

    <div class="clients-page py-6 sm:py-8">
      <div class="mx-auto sm:px-6 lg:px-8">
        <div class="clients-card overflow-hidden shadow-sm sm:rounded-xl">
          <div class="p-4 sm:p-6">
            <!-- Tabs: الكل | قاسة -->
            <div class="clients-tabs mb-5" role="tablist" aria-label="عرض التجار">
              <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'all'"
                class="clients-tab"
                :class="{ 'is-active': activeTab === 'all' }"
                @click="setTab('all')"
              >
                الكل
              </button>
              <button
                type="button"
                role="tab"
                :aria-selected="activeTab === 'qasa'"
                class="clients-tab"
                :class="{ 'is-active': activeTab === 'qasa' }"
                @click="setTab('qasa')"
                title="التجار المعروضون في صفحة المحاسبة (قاسة)"
              >
                قاسة
              </button>
              <Link
                :href="route('company_treasury')"
                class="clients-tab clients-tab-link"
                title="فتح قاسة الشركة"
              >
                قاسة الشركة
              </Link>
            </div>

            <!-- Filters -->
            <div class="clients-filters grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 mb-5">
              <div class="lg:col-span-2">
                <InputLabel for="simple-search" :value="$t('search')" class="mb-1" />
                <div class="relative">
                  <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-5 w-5 text-gray-400 dark:text-slate-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                      <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                  </div>
                  <input
                    id="simple-search"
                    v-model="q"
                    type="text"
                    class="clients-input w-full pl-10"
                    placeholder="بحث بالاسم / الهاتف / الشاصي"
                    :disabled="activeTab === 'qasa'"
                  />
                </div>
              </div>

              <div>
                <InputLabel for="category" value="تحديد الفئة" class="mb-1" />
                <select
                  id="category"
                  v-model="category"
                  class="clients-input w-full pr-8"
                  :disabled="activeTab === 'qasa'"
                >
                  <option value="0">{{ $t("allOwners") }}</option>
                  <option value="debit">يوجد دين</option>
                  <option value="box_movement">حركة على القاسة</option>
                </select>
              </div>

              <div>
                <InputLabel for="from" :value="$t('from_date')" class="mb-1" />
                <TextInput id="from" type="date" class="mt-0 block w-full clients-input-reset" v-model="from" />
              </div>

              <div>
                <InputLabel for="to" :value="$t('to_date')" class="mb-1" />
                <TextInput id="to" type="date" class="mt-0 block w-full clients-input-reset" v-model="to" />
              </div>

              <div class="flex flex-wrap items-end gap-2">
                <button type="button" class="clients-btn clients-btn-primary flex-1 min-w-[7rem]" @click="openModalAddClient()">
                  {{ $t('addCustomer') }}
                </button>
                <a
                  target="_blank"
                  :href="`api/getIndexClients?from=${from}&to=${to}&print=1&q=${effectiveQ()}`"
                  class="clients-btn clients-btn-print flex-1 min-w-[7rem] text-center print:hidden"
                >
                  طباعة
                </a>
              </div>
            </div>

            <!-- Table -->
            <div class="clients-table-wrap relative overflow-x-auto rounded-lg">
              <table class="clients-table w-full text-sm text-center">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>{{ $t('name') }}</th>
                    <th>{{ $t('phoneNumber') }}</th>
                    <th>السيارات</th>
                    <th>غير مدفوع</th>
                    <th>مدفوع</th>
                    <th>{{ $t('debt') }}</th>
                    <th title="عرض محفظة التاجر في صفحة المحاسبة">عرض بالمحاسبة</th>
                    <th>{{ $t('execute') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(user, i) in laravelData"
                    :key="user?.id || i"
                    :class="Number(user.balance) <= 0 ? 'row-credit' : 'row-debit'"
                  >
                    <template v-if="user?.id">
                      <td>{{ i + 1 }}</td>
                      <td class="cell-name">{{ user.name }}</td>
                      <td dir="ltr">{{ user.phone || '—' }}</td>
                      <td>{{ user.car_count ?? 0 }}</td>
                      <td>{{ unpaidCars(user) }}</td>
                      <td>{{ user.car_count_completed ?? 0 }}</td>
                      <td class="cell-balance" dir="ltr">{{ formatBalance(user.balance) }}</td>
                      <td>
                        <label class="clients-switch" :title="user.show_in_dashboard ? 'معروض في المحاسبة' : 'إخفاء من المحاسبة'">
                          <input
                            type="checkbox"
                            role="switch"
                            :checked="!!user.show_in_dashboard"
                            :disabled="togglingIds.includes(user.id)"
                            @change="toggleShowInDashboardQuick(user)"
                          />
                          <span class="clients-switch-track" aria-hidden="true">
                            <span class="clients-switch-thumb" />
                          </span>
                          <span class="sr-only">عرض بالمحاسبة</span>
                        </label>
                      </td>
                      <td>
                        <div class="clients-actions">
                          <Link
                            v-if="user.car_count"
                            class="action-btn action-view"
                            :href="route('showClients', user.id)"
                            title="عرض"
                          >
                            <show />
                          </Link>
                          <button type="button" class="action-btn action-edit" title="تعديل" @click="openModalEditClient(user)">
                            <edit />
                          </button>
                          <button
                            v-if="!user.car_count"
                            type="button"
                            class="action-btn action-del"
                            title="حذف"
                            @click="openModalDelClient(user)"
                          >
                            <trash />
                          </button>
                          <Link
                            class="action-btn action-wallet"
                            :href="route('wallet', { id: user.id })"
                            title="المحفظة"
                          >
                            <wallet />
                          </Link>
                        </div>
                      </td>
                    </template>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="mt-3 text-center" style="direction: ltr;">
              <div class="spaner">
                <InfiniteLoading :laravelData="laravelData" @infinite="getResultsCar" :identifier="resetData" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.clients-page {
  --c-bg: #ffffff;
  --c-border: #e2e8f0;
  --c-muted: #64748b;
  --c-head: #f1f5f9;
  --c-text: #0f172a;
  --c-accent: #dc2626;
  --c-accent-2: #ea580c;
  --c-row-hover: #f8fafc;
}

:global(.dark) .clients-page,
.dark .clients-page {
  --c-bg: #0f172a;
  --c-border: #334155;
  --c-muted: #94a3b8;
  --c-head: #1e293b;
  --c-text: #f1f5f9;
  --c-row-hover: #1e293b;
}

.clients-card {
  background: var(--c-bg);
  color: var(--c-text);
  border: 1px solid var(--c-border);
}

.clients-tabs {
  display: inline-flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  padding: 0.3rem;
  border-radius: 0.75rem;
  background: var(--c-head);
  border: 1px solid var(--c-border);
}

.clients-tab {
  border: 0;
  background: transparent;
  color: var(--c-muted);
  font-weight: 600;
  font-size: 0.9rem;
  padding: 0.45rem 1rem;
  border-radius: 0.55rem;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
}

.clients-tab:hover {
  color: var(--c-text);
  background: rgba(148, 163, 184, 0.15);
}

.clients-tab.is-active {
  background: var(--c-accent);
  color: #fff;
}

.clients-tab-link {
  border: 1px solid var(--c-border);
  background: transparent;
}

.clients-tab-link:hover {
  border-color: var(--c-accent-2);
  color: var(--c-accent-2);
}

.clients-input {
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  color: #0f172a;
  font-size: 0.875rem;
  border-radius: 0.5rem;
  padding: 0.55rem 0.75rem;
  outline: none;
}

.clients-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
}

.dark .clients-input {
  background: #020617;
  border-color: #475569;
  color: #fff;
}

.clients-input:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.clients-input-reset :deep(input),
:deep(.clients-input-reset) {
  border-radius: 0.5rem !important;
}

.clients-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  border-radius: 0.5rem;
  padding: 0.55rem 1rem;
  color: #fff;
  text-decoration: none;
  border: 0;
  cursor: pointer;
  min-height: 2.5rem;
}

.clients-btn-primary {
  background: #dc2626;
}

.clients-btn-primary:hover {
  background: #b91c1c;
}

.clients-btn-print {
  background: #ea580c;
}

.clients-btn-print:hover {
  background: #c2410c;
}

.clients-table-wrap {
  border: 1px solid var(--c-border);
}

.clients-table {
  border-collapse: collapse;
  color: var(--c-text);
}

.clients-table thead th {
  background: var(--c-head);
  color: var(--c-text);
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: none;
  letter-spacing: 0;
  padding: 0.75rem 0.5rem;
  border-bottom: 1px solid var(--c-border);
  white-space: nowrap;
}

.clients-table tbody td {
  padding: 0.65rem 0.5rem;
  border-bottom: 1px solid var(--c-border);
  vertical-align: middle;
}

.clients-table tbody tr:hover {
  filter: brightness(1.03);
}

.dark .clients-table tbody tr:hover {
  filter: brightness(1.12);
}

.row-credit {
  background: #dcfce7;
}

.row-debit {
  background: #fee2e2;
}

.dark .row-credit {
  background: rgba(6, 78, 59, 0.55);
}

.dark .row-debit {
  background: rgba(127, 29, 29, 0.45);
}

.cell-name {
  font-weight: 700;
  font-size: 0.95rem;
}

.cell-balance {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.clients-actions {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2rem;
  height: 2rem;
  border-radius: 0.4rem;
  color: #fff;
  border: 0;
  cursor: pointer;
  padding: 0;
  text-decoration: none;
}

.action-view {
  background: #3b82f6;
}

.action-edit {
  background: #64748b;
}

.action-del {
  background: #ea580c;
}

.action-wallet {
  background: #581c87;
}

.clients-switch {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  user-select: none;
}

.clients-switch input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.clients-switch-track {
  width: 2.6rem;
  height: 1.35rem;
  border-radius: 999px;
  background: #94a3b8;
  position: relative;
  transition: background 0.15s ease;
  display: inline-block;
}

.dark .clients-switch-track {
  background: #475569;
}

.clients-switch-thumb {
  position: absolute;
  top: 0.15rem;
  left: 0.15rem;
  width: 1.05rem;
  height: 1.05rem;
  border-radius: 999px;
  background: #fff;
  transition: transform 0.15s ease;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
}

.clients-switch input:checked + .clients-switch-track {
  background: #16a34a;
}

.clients-switch input:checked + .clients-switch-track .clients-switch-thumb {
  transform: translateX(1.2rem);
}

.clients-switch input:disabled + .clients-switch-track {
  opacity: 0.55;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

@media (max-width: 640px) {
  .clients-table thead th,
  .clients-table tbody td {
    padding: 0.5rem 0.35rem;
    font-size: 0.75rem;
  }

  .action-btn {
    width: 1.85rem;
    height: 1.85rem;
  }
}
</style>
