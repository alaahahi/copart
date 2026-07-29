<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/inertia-vue3';
import ModalAddVault from "@/Components/ModalAddVault.vue";
import ModalAddExpenseAccount from "@/Components/ModalAddExpenseAccount.vue";
import InputLabel from "@/Components/InputLabel.vue";
import axios from 'axios';
import ModalDelClient from "@/Components/ModalDelCar.vue";
import wallet from "@/Components/icon/wallet.vue";
import trash from "@/Components/icon/trash.vue";
import edit from "@/Components/icon/edit.vue";
import { computed, onMounted, ref, watch } from 'vue';
import { formatMoney } from "@/utils/formatMoney";
import SearchInput from "@/Components/SearchInput.vue";

const activeTab = ref('cash'); // cash | expenses

const showModalVault = ref(false);
const vaultModalMode = ref('create');
const vaultModalRef = ref(null);
const showModalDel = ref(false);
const showModalExpenseAccount = ref(false);
const expenseAccountModalRef = ref(null);

const vaults = ref([]);
const expenseAccounts = ref([]);
const suggestExpenseCode = ref('5101');
const suggestCommissionCode = ref('5201');
const expenseParentId = ref(null);
const formData = ref({});
const q = ref('');
const loading = ref(false);
const loadError = ref('');
const togglingIds = ref([]);

const filteredVaults = computed(() => {
  const term = String(q.value || '').trim().toLowerCase();
  if (!term) return vaults.value;
  return vaults.value.filter((v) => {
    const name = String(v.name || '').toLowerCase();
    const code = String(v.vault_code || v.code || '').toLowerCase();
    const type = String(v.vault_type || v.type || '').toLowerCase();
    return name.includes(term) || code.includes(term) || type.includes(term);
  });
});

const filteredExpenseAccounts = computed(() => {
  const term = String(q.value || '').trim().toLowerCase();
  if (!term) return expenseAccounts.value;
  return expenseAccounts.value.filter((a) => {
    const name = String(a.name || '').toLowerCase();
    const code = String(a.code || '').toLowerCase();
    return name.includes(term) || code.includes(term);
  });
});

async function loadVaults() {
  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await axios.get('/api/vaults', { params: { for_ui: 1 } });
    vaults.value = data.data || data.vaults || [];
  } catch (error) {
    loadError.value = error?.response?.data?.message || 'تعذر تحميل القاصات';
    console.error(error);
  } finally {
    loading.value = false;
  }
}

async function loadExpenseAccounts() {
  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await axios.get('/api/ledgerExpenseAccounts');
    expenseAccounts.value = data.accounts || [];
    suggestExpenseCode.value = data.suggest_expense_code || '5101';
    suggestCommissionCode.value = data.suggest_commission_code || '5201';
    const rawParent = data.expense_parent_id;
    const parsedParent = Number(rawParent);
    expenseParentId.value =
      Number.isFinite(parsedParent) && parsedParent > 0 ? parsedParent : null;
  } catch (error) {
    loadError.value = error?.response?.data?.message || 'تعذر تحميل حسابات المصاريف';
    console.error(error);
  } finally {
    loading.value = false;
  }
}

async function loadActiveTab() {
  if (activeTab.value === 'cash') {
    await loadVaults();
  } else {
    await loadExpenseAccounts();
  }
}

onMounted(loadActiveTab);
watch(activeTab, () => {
  q.value = '';
  loadActiveTab();
});

function openModalAddVault() {
  vaultModalMode.value = 'create';
  formData.value = { name: '', type: 'cash', show_in_accounting: true, notes: '' };
  showModalVault.value = true;
}

function openModalEditVault(row = {}) {
  vaultModalMode.value = 'edit';
  formData.value = {
    vault_id: row.vault_id || row.id,
    name: row.name,
    type: row.vault_type || row.type || 'cash',
    code: row.vault_code || row.code || '',
    show_in_accounting: row.show_in_accounting ?? row.show_in_dashboard ?? true,
    notes: row.notes || '',
  };
  showModalVault.value = true;
}

async function confirmVaultSave(V) {
  vaultModalRef.value?.setSaving?.(true);
  vaultModalRef.value?.setError?.('');
  try {
    if (vaultModalMode.value === 'edit' && V.vault_id) {
      await axios.post(`/api/vaults/${V.vault_id}`, V);
    } else {
      await axios.post('/api/vaults', V);
    }
    showModalVault.value = false;
    await loadVaults();
  } catch (error) {
    const msg = error?.response?.data?.message
      || error?.response?.data?.errors?.name?.[0]
      || 'تعذر حفظ القاصة';
    vaultModalRef.value?.setError?.(msg);
    console.error(error);
  } finally {
    vaultModalRef.value?.setSaving?.(false);
  }
}

function openModalDel(row = {}) {
  formData.value = row;
  showModalDel.value = true;
}

function confirmDel(V) {
  const vaultId = V?.vault_id || V?.id;
  if (!vaultId) return;
  axios.post(`/api/vaults/${vaultId}/delete`)
    .then(() => {
      showModalDel.value = false;
      return loadVaults();
    })
    .catch((error) => {
      const msg = error?.response?.data?.message || 'تعذر حذف القاصة';
      alert(msg);
      console.error(error);
    });
}

async function toggleAccounting(row) {
  const key = row.vault_id || row.id;
  if (!key || togglingIds.value.includes(key)) return;

  const next = !(row.show_in_accounting ?? row.show_in_dashboard ?? false);
  const prev = row.show_in_accounting ?? row.show_in_dashboard ?? false;
  togglingIds.value.push(key);
  row.show_in_accounting = next;
  row.show_in_dashboard = next;
  try {
    const response = await axios.post(`/api/vaults/${key}/toggleAccounting`, {
      show_in_accounting: next,
    });
    row.show_in_accounting = response.data.show_in_accounting;
    row.show_in_dashboard = response.data.show_in_accounting;
  } catch (error) {
    row.show_in_accounting = prev;
    row.show_in_dashboard = prev;
    console.error(error);
  } finally {
    togglingIds.value = togglingIds.value.filter((id) => id !== key);
  }
}

function openModalAddExpenseAccount() {
  showModalExpenseAccount.value = true;
}

async function confirmExpenseAccountSave(payload) {
  expenseAccountModalRef.value?.setSaving?.(true);
  expenseAccountModalRef.value?.setError?.('');
  try {
    await axios.post('/api/ledgerAccountStore', payload);
    showModalExpenseAccount.value = false;
    await loadExpenseAccounts();
  } catch (error) {
    const errors = error?.response?.data?.errors || {};
    const msg = error?.response?.data?.message
      || errors.parent_id?.[0]
      || errors.code?.[0]
      || errors.name_ar?.[0]
      || Object.values(errors)[0]?.[0]
      || 'تعذر إنشاء حساب المصروف';
    expenseAccountModalRef.value?.setError?.(msg);
    console.error(error);
  } finally {
    expenseAccountModalRef.value?.setSaving?.(false);
  }
}

function formatBalance(balance) {
  return `${formatMoney(balance, "$")} $`;
}

function vaultTypeLabel(type) {
  const map = {
    cash: 'نقد',
    bank: 'بنك',
    safe: 'خزنة',
    system: 'نظام',
  };
  return map[type] || type || '—';
}

function accountKindLabel(row) {
  if (row.kind === 'commission' || row.type === 'income') return 'عمولة';
  return 'مصروف';
}

function walletUserId(row) {
  return row.id || row.legacy_user_id || null;
}
</script>

<template>
  <Head :title="$t('vaults')" />
  <AuthenticatedLayout>
    <ModalAddVault
      ref="vaultModalRef"
      :show="showModalVault"
      :formData="formData"
      :mode="vaultModalMode"
      @a="confirmVaultSave($event)"
      @close="showModalVault = false"
    />

    <ModalAddExpenseAccount
      ref="expenseAccountModalRef"
      :show="showModalExpenseAccount"
      :suggest-expense-code="suggestExpenseCode"
      :suggest-commission-code="suggestCommissionCode"
      :expense-parent-id="expenseParentId"
      @save="confirmExpenseAccountSave"
      @close="showModalExpenseAccount = false"
    />

    <ModalDelClient
      :show="showModalDel"
      :formData="formData"
      @a="confirmDel($event)"
      @close="showModalDel = false"
    >
      <template #header>
        <h2 class="mb-5 dark:text-white text-center">
          هل متأكد من حذف القاصة {{ formData.name }} ؟
        </h2>
      </template>
    </ModalDelClient>

    <div class="vaults-page py-6 sm:py-8">
      <div class="mx-auto sm:px-6 lg:px-8">
        <div class="vaults-card overflow-hidden shadow-sm sm:rounded-xl">
          <div class="p-4 sm:p-6">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
              <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ $t('vaults') }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                  القاصات = نقد فقط · المصاريف والعمولات = حسابات في دليل الحسابات
                </p>
              </div>
              <button
                v-if="activeTab === 'cash'"
                type="button"
                class="vaults-btn vaults-btn-primary"
                @click="openModalAddVault()"
              >
                إضافة قاصة نقدية
              </button>
              <button
                v-else
                type="button"
                class="vaults-btn vaults-btn-primary"
                @click="openModalAddExpenseAccount()"
              >
                إضافة حساب مصروف/عمولة
              </button>
            </div>

            <div class="mb-5 flex flex-wrap gap-2">
              <button
                type="button"
                class="vaults-tab"
                :class="{ active: activeTab === 'cash' }"
                @click="activeTab = 'cash'"
              >
                نقد / قاصات
              </button>
              <button
                type="button"
                class="vaults-tab"
                :class="{ active: activeTab === 'expenses' }"
                @click="activeTab = 'expenses'"
              >
                مصاريف وعمولات
              </button>
            </div>

            <div class="mb-5 max-w-md">
              <InputLabel for="vault-search" :value="$t('search')" class="mb-1" />
              <SearchInput
                id="vault-search"
                v-model="q"
                type="text"
                input-class="vaults-input"
                :placeholder="activeTab === 'cash' ? 'بحث بالاسم / الرمز / النوع' : 'بحث بالاسم / رمز الحساب'"
              />
            </div>

            <p v-if="loadError" class="mb-4 text-sm font-semibold text-rose-600 dark:text-rose-300">
              {{ loadError }}
            </p>
            <p v-else-if="loading" class="mb-4 text-sm text-slate-500 dark:text-slate-300">
              جاري التحميل…
            </p>

            <!-- Cash vaults -->
            <div v-if="activeTab === 'cash'" class="vaults-table-wrap relative overflow-x-auto rounded-lg">
              <table class="vaults-table w-full text-sm text-center">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>{{ $t('name') }}</th>
                    <th>النوع</th>
                    <th>الرصيد</th>
                    <th title="عرض اختصار القاصة في المحاسبة">في المحاسبة</th>
                    <th>{{ $t('execute') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(row, i) in filteredVaults"
                    :key="row.vault_id || row.id || i"
                    :class="Number(row.balance) <= 0 ? 'row-credit' : 'row-debit'"
                  >
                    <td>{{ i + 1 }}</td>
                    <td class="cell-name">
                      {{ row.name }}
                      <span class="vault-badge">قاصة نقدية</span>
                    </td>
                    <td>{{ vaultTypeLabel(row.vault_type || row.type) }}</td>
                    <td class="cell-balance" dir="ltr">{{ formatBalance(row.balance) }}</td>
                    <td>
                      <label
                        class="vaults-switch"
                        :title="(row.show_in_accounting ?? row.show_in_dashboard)
                          ? 'معروضة في المحاسبة'
                          : 'مخفية عن اختصارات المحاسبة'"
                      >
                        <input
                          type="checkbox"
                          role="switch"
                          :checked="!!(row.show_in_accounting ?? row.show_in_dashboard)"
                          :disabled="togglingIds.includes(row.vault_id || row.id)"
                          @change="toggleAccounting(row)"
                        />
                        <span class="vaults-switch-track" aria-hidden="true">
                          <span class="vaults-switch-thumb" />
                        </span>
                        <span class="sr-only">في المحاسبة</span>
                      </label>
                    </td>
                    <td>
                      <div class="vaults-actions">
                        <button
                          type="button"
                          class="action-btn action-edit"
                          title="تعديل القاصة"
                          @click="openModalEditVault(row)"
                        >
                          <edit />
                        </button>
                        <button
                          v-if="row.can_delete"
                          type="button"
                          class="action-btn action-del"
                          title="حذف القاصة"
                          @click="openModalDel(row)"
                        >
                          <trash />
                        </button>
                        <Link
                          v-if="walletUserId(row)"
                          class="action-btn action-wallet"
                          :href="route('wallet', { id: walletUserId(row) })"
                          title="حركة القاصة النقدية"
                        >
                          <wallet />
                        </Link>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!loading && !filteredVaults.length">
                    <td colspan="6" class="py-8 text-slate-500 dark:text-slate-300">
                      لا توجد قاصات نقدية
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Expense / commission COA -->
            <div v-else class="vaults-table-wrap relative overflow-x-auto rounded-lg">
              <p class="coa-hint">
                أرصدة من قيود اليومية · الصرف يتم من قاصة نقدية (مدين مصروف / دائن نقد)
              </p>
              <table class="vaults-table w-full text-sm text-center">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>الرمز</th>
                    <th>{{ $t('name') }}</th>
                    <th>التصنيف</th>
                    <th>الرصيد</th>
                    <th>{{ $t('execute') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="(row, i) in filteredExpenseAccounts"
                    :key="row.id || i"
                    :class="Number(row.balance) <= 0 ? 'row-credit' : 'row-debit'"
                  >
                    <td>{{ i + 1 }}</td>
                    <td class="cell-code" dir="ltr">{{ row.code }}</td>
                    <td class="cell-name">
                      {{ row.name }}
                      <span class="vault-badge badge-coa">حساب COA</span>
                    </td>
                    <td>{{ accountKindLabel(row) }}</td>
                    <td class="cell-balance" dir="ltr">{{ formatBalance(row.balance) }}</td>
                    <td>
                      <div class="vaults-actions">
                        <Link
                          class="action-btn action-wallet"
                          :href="route('expenseAccount', { id: row.id })"
                          :title="row.can_disburse ? 'دفتر المصروف / صرف' : 'دفتر الحساب'"
                        >
                          <wallet />
                        </Link>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!loading && !filteredExpenseAccounts.length">
                    <td colspan="6" class="py-8 text-slate-500 dark:text-slate-300">
                      لا توجد حسابات مصاريف/عمولات — أضف حساباً من الزر أعلاه
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.vaults-page {
  --c-bg: #ffffff;
  --c-border: #e2e8f0;
  --c-muted: #64748b;
  --c-head: #f1f5f9;
  --c-text: #0f172a;
}

:global(.dark) .vaults-page,
.dark .vaults-page {
  --c-bg: #0f172a;
  --c-border: #334155;
  --c-muted: #94a3b8;
  --c-head: #1e293b;
  --c-text: #f1f5f9;
}

.vaults-card {
  background: var(--c-bg);
  color: var(--c-text);
  border: 1px solid var(--c-border);
}

.vaults-tab {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.85rem;
  border-radius: 0.5rem;
  padding: 0.5rem 1rem;
  border: 1px solid var(--c-border);
  background: #f1f5f9;
  color: #334155;
  cursor: pointer;
  min-height: 2.35rem;
}

.dark .vaults-tab {
  background: #1e293b;
  color: #e2e8f0;
  border-color: #475569;
}

.vaults-tab.active {
  background: #059669;
  border-color: #059669;
  color: #fff;
}

.coa-hint {
  margin: 0;
  padding: 0.65rem 0.85rem;
  font-size: 0.8rem;
  color: var(--c-muted);
  border-bottom: 1px solid var(--c-border);
  background: var(--c-head);
}

.vaults-input {
  background: #f8fafc;
  border: 1px solid #cbd5e1;
  color: #0f172a;
  font-size: 0.875rem;
  border-radius: 0.5rem;
  padding-block: 0.55rem;
  padding-inline-end: 0.75rem;
  padding-inline-start: 0.75rem;
  outline: none;
}

.erp-search .vaults-input {
  padding-inline-start: 2.5rem;
}

.vaults-input:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
}

.dark .vaults-input {
  background: #020617;
  border-color: #475569;
  color: #fff;
}

.vaults-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  border-radius: 0.5rem;
  padding: 0.55rem 1rem;
  color: #fff;
  border: 0;
  cursor: pointer;
  min-height: 2.5rem;
}

.vaults-btn-primary {
  background: #059669;
}

.vaults-btn-primary:hover {
  background: #047857;
}

.vaults-table-wrap {
  border: 1px solid var(--c-border);
}

.vaults-table {
  border-collapse: collapse;
  color: var(--c-text);
}

.vaults-table thead th {
  background: var(--c-head);
  color: var(--c-text);
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.75rem 0.5rem;
  border-bottom: 1px solid var(--c-border);
  white-space: nowrap;
}

.vaults-table tbody td {
  padding: 0.65rem 0.5rem;
  border-bottom: 1px solid var(--c-border);
  vertical-align: middle;
}

.vaults-table tbody tr:hover {
  filter: brightness(1.03);
}

.dark .vaults-table tbody tr:hover {
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

.cell-code {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.vault-badge {
  display: inline-block;
  margin-inline-start: 0.35rem;
  padding: 0.1rem 0.4rem;
  border-radius: 0.35rem;
  font-size: 0.65rem;
  font-weight: 700;
  background: #0f766e;
  color: #ecfdf5;
  vertical-align: middle;
}

.badge-coa {
  background: #7c2d12;
  color: #ffedd5;
}

.cell-balance {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}

.vaults-actions {
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

.action-edit {
  background: #64748b;
}

.action-del {
  background: #ea580c;
}

.action-wallet {
  background: #581c87;
}

.vaults-switch {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  user-select: none;
}

.vaults-switch input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.vaults-switch-track {
  width: 2.6rem;
  height: 1.35rem;
  border-radius: 999px;
  background: #94a3b8;
  position: relative;
  transition: background 0.15s ease;
  display: inline-block;
}

.dark .vaults-switch-track {
  background: #475569;
}

.vaults-switch-thumb {
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

.vaults-switch input:checked + .vaults-switch-track {
  background: #16a34a;
}

.vaults-switch input:checked + .vaults-switch-track .vaults-switch-thumb {
  transform: translateX(1.2rem);
}

.vaults-switch input:disabled + .vaults-switch-track {
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
  .vaults-table thead th,
  .vaults-table tbody td {
    padding: 0.5rem 0.35rem;
    font-size: 0.75rem;
  }

  .action-btn {
    width: 1.85rem;
    height: 1.85rem;
  }
}
</style>
