<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/inertia-vue3';
import ModalExpenseDisburse from '@/Components/ModalExpenseDisburse.vue';
import ModalDelClient from '@/Components/ModalDelCar.vue';
import print from '@/Components/icon/print.vue';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { formatMoney } from '@/utils/formatMoney';

const props = defineProps({
  account: { type: Object, required: true },
  cashVaults: { type: Array, default: () => [] },
});

const account = ref({ ...props.account });
const rows = ref([]);
const openingBalance = ref(0);
const loading = ref(false);
const loadError = ref('');
const currency = ref('$');
const showCashModal = ref(false);
const cashModalMode = ref('disburse');
const cashModalRef = ref(null);
const showDeleteModal = ref(false);
const deleting = ref(false);
const flash = ref('');

const canDisburse = computed(() => account.value?.can_disburse !== false);
const canReceive = computed(() => account.value?.can_receive !== false);
const canDelete = computed(() => !!account.value?.can_delete);
const typeLabel = computed(() => (account.value?.type === 'income' ? 'إيراد' : 'مصروف'));
const pageTitle = computed(() => `${typeLabel.value} — ${account.value?.name || ''}`);

const fallbackPrintUserId = computed(() => {
  const first = (props.cashVaults || []).find((v) => v?.id);
  return first?.id ? Number(first.id) : 0;
});

async function loadLedger() {
  if (!account.value?.id) return;
  loading.value = true;
  loadError.value = '';
  try {
    const { data } = await axios.get('/api/ledgerAccount', {
      params: {
        account_id: account.value.id,
        currency: currency.value,
      },
    });
    rows.value = data.rows || [];
    openingBalance.value = data.opening_balance || 0;
    if (data.account) {
      account.value = {
        ...account.value,
        ...data.account,
        name: data.account.name || account.value.name,
        can_disburse: true,
        can_receive: true,
      };
    }
    const list = await axios.get('/api/ledgerExpenseAccounts', {
      params: { currency: currency.value },
    });
    const match = (list.data.accounts || []).find((a) => a.id === account.value.id);
    if (match) {
      account.value.balance = match.balance;
      account.value.balance_dinar = match.balance_dinar;
      account.value.can_disburse = match.can_disburse !== false;
      account.value.can_receive = match.can_receive !== false;
      account.value.can_delete = !!match.can_delete;
      account.value.has_movements = !!match.has_movements;
      account.value.type = match.type || account.value.type;
    }
  } catch (error) {
    loadError.value = error?.response?.data?.message || 'تعذر تحميل دفتر الحساب';
    console.error(error);
  } finally {
    loading.value = false;
  }
}

onMounted(loadLedger);

function openCashModal(mode) {
  cashModalMode.value = mode;
  showCashModal.value = true;
}

async function confirmCashMove(payload) {
  cashModalRef.value?.setSaving?.(true);
  cashModalRef.value?.setError?.('');
  const isReceive = cashModalMode.value === 'receive';
  const url = isReceive ? '/api/ledgerExpenseReceive' : '/api/ledgerExpenseDisburse';
  try {
    const { data } = await axios.post(url, {
      ...payload,
      expense_ledger_account_id: account.value.id,
    });
    showCashModal.value = false;
    flash.value = data.message || (isReceive ? 'تم تسجيل القبض' : 'تم تسجيل الصرف');
    if (data.expense_balance !== undefined) {
      account.value.balance = data.expense_balance;
    }
    if (data.expense_balance_dinar !== undefined) {
      account.value.balance_dinar = data.expense_balance_dinar;
    }
    account.value.can_delete = false;
    account.value.has_movements = true;
    await loadLedger();
  } catch (error) {
    const msg = error?.response?.data?.message
      || error?.response?.data?.errors?.amount?.[0]
      || (isReceive ? 'تعذر تسجيل القبض' : 'تعذر تسجيل الصرف');
    cashModalRef.value?.setError?.(msg);
    console.error(error);
  } finally {
    cashModalRef.value?.setSaving?.(false);
  }
}

async function confirmDelete() {
  if (!canDelete.value || deleting.value) return;
  deleting.value = true;
  try {
    await axios.post('/api/ledgerExpenseAccountDelete', { id: account.value.id });
    showDeleteModal.value = false;
    window.location.href = route('vaults');
  } catch (error) {
    flash.value = '';
    loadError.value = error?.response?.data?.message || 'تعذر حذف الحساب';
    showDeleteModal.value = false;
    console.error(error);
  } finally {
    deleting.value = false;
  }
}

function formatBal(v) {
  return `${formatMoney(v, currency.value === 'IQD' ? 'IQD' : '$')} ${currency.value === 'IQD' ? 'د.ع' : '$'}`;
}

const currencyLabel = computed(() => (currency.value === 'IQD' ? 'دينار' : 'دولار'));

const totalDebit = computed(() =>
  rows.value.reduce((sum, row) => sum + (Number(row.debit) || 0), 0)
);
const totalCredit = computed(() =>
  rows.value.reduce((sum, row) => sum + (Number(row.credit) || 0), 0)
);

function printLedger() {
  window.print();
}

/** وصل صرف (print=3) when line is Dr; وصل قبض (print=2) when Cr. */
function rowVoucherHref(row) {
  const txId = Number(row?.transaction_id || 0);
  if (!txId) return null;
  const userId = Number(row?.print_user_id || fallbackPrintUserId.value || 0);
  if (!userId) return null;
  const print = Number(row?.print) === 2 ? 2 : 3;
  return `/api/getIndexAccountsSelas?user_id=${userId}&print=${print}&transactions_id=${txId}`;
}

function rowVoucherTitle(row) {
  return row?.voucher_kind === 'receipt' ? 'طباعة وصل قبض' : 'طباعة وصل صرف';
}
</script>

<template>
  <Head :title="pageTitle" />
  <AuthenticatedLayout>
    <ModalExpenseDisburse
      ref="cashModalRef"
      :show="showCashModal"
      :mode="cashModalMode"
      :cash-vaults="cashVaults"
      :account-name="account.name"
      :account-type="account.type"
      @save="confirmCashMove"
      @close="showCashModal = false"
    />

    <ModalDelClient
      :show="showDeleteModal"
      :formData="account"
      @a="confirmDelete"
      @close="showDeleteModal = false"
    >
      <template #header>
        <h2 class="mb-5 text-center text-white">
          هل متأكد من حذف الحساب «{{ account.name }}» ({{ account.code }})؟
        </h2>
        <p class="mb-2 text-center text-sm text-slate-300">
          يُحذف فقط إن لم تكن عليه أي حركات في دفتر اليومية.
        </p>
      </template>
    </ModalDelClient>

    <div class="exp-page py-4 sm:py-6">
      <div class="exp-shell mx-auto w-full px-3 sm:px-5 lg:px-6">
        <div class="exp-card overflow-hidden shadow-sm sm:rounded-xl">
          <div class="p-4 sm:p-6 lg:p-8">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <Link :href="route('vaults')" class="exp-back print:hidden">← العودة للقاصات / المصاريف</Link>
                <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white sm:text-3xl">
                  {{ account.name }}
                  <span class="exp-code" dir="ltr">{{ account.code }}</span>
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                  حساب في دليل الحسابات ({{ typeLabel }}) — الرصيد من قيود اليومية
                </p>
                <p class="exp-print-meta mt-1 hidden text-xs text-slate-600 print:block" dir="rtl">
                  العملة: {{ currencyLabel }}
                  · عدد الحركات: {{ rows.length }}
                </p>
              </div>
              <div class="flex flex-wrap items-center gap-2 print:hidden">
                <select v-model="currency" class="exp-select" @change="loadLedger">
                  <option value="$">دولار</option>
                  <option value="IQD">دينار</option>
                </select>
                <button
                  type="button"
                  class="exp-btn exp-btn-print"
                  :title="$t('print')"
                  @click="printLedger"
                >
                  {{ $t('print') }}
                </button>
                <button
                  v-if="canReceive"
                  type="button"
                  class="exp-btn exp-btn-receive"
                  @click="openCashModal('receive')"
                >
                  وصل قبض
                </button>
                <button
                  v-if="canDisburse"
                  type="button"
                  class="exp-btn exp-btn-primary"
                  @click="openCashModal('disburse')"
                >
                  وصل صرف
                </button>
                <button
                  v-if="canDelete"
                  type="button"
                  class="exp-btn exp-btn-danger"
                  :disabled="deleting"
                  @click="showDeleteModal = true"
                >
                  حذف الحساب
                </button>
              </div>
            </div>

            <div class="exp-kpis mb-6">
              <div class="exp-kpi">
                <span class="exp-kpi-label">رصيد الحساب</span>
                <span class="exp-kpi-value" dir="ltr">
                  {{ formatBal(currency === 'IQD' ? account.balance_dinar : account.balance) }}
                </span>
              </div>
              <div class="exp-kpi">
                <span class="exp-kpi-label">افتتاحي (فلتر)</span>
                <span class="exp-kpi-value muted" dir="ltr">{{ formatBal(openingBalance) }}</span>
              </div>
            </div>

            <p v-if="flash" class="mb-4 text-sm font-semibold text-emerald-600 dark:text-emerald-300 print:hidden">
              {{ flash }}
            </p>
            <p v-if="loadError" class="mb-4 text-sm font-semibold text-rose-600 dark:text-rose-300 print:hidden">
              {{ loadError }}
            </p>
            <p v-else-if="loading" class="mb-4 text-sm text-slate-500 dark:text-slate-300 print:hidden">
              جاري التحميل…
            </p>

            <div class="exp-table-wrap overflow-x-auto rounded-lg">
              <table class="exp-table w-full text-sm text-center">
                <thead>
                  <tr>
                    <th>التاريخ</th>
                    <th>السند</th>
                    <th>البيان</th>
                    <th>مدين</th>
                    <th>دائن</th>
                    <th>الرصيد</th>
                    <th class="print:hidden">تنفيذ</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in rows" :key="row.id">
                    <td dir="ltr">{{ row.date || '—' }}</td>
                    <td dir="ltr">{{ row.voucher_no || '—' }}</td>
                    <td class="text-start">{{ row.memo || '—' }}</td>
                    <td dir="ltr">{{ Number(row.debit) ? formatMoney(row.debit) : '—' }}</td>
                    <td dir="ltr">{{ Number(row.credit) ? formatMoney(row.credit) : '—' }}</td>
                    <td class="font-semibold" dir="ltr">{{ formatMoney(row.balance) }}</td>
                    <td class="print:hidden">
                      <a
                        v-if="rowVoucherHref(row)"
                        :href="rowVoucherHref(row)"
                        target="_blank"
                        rel="noopener"
                        class="exp-row-print"
                        :title="rowVoucherTitle(row)"
                      >
                        <print class="exp-row-print-icon" />
                        <span>{{ row.voucher_kind === 'receipt' ? 'وصل قبض' : 'وصل صرف' }}</span>
                      </a>
                      <span v-else class="text-slate-400 dark:text-slate-500" title="لا توجد حركة مالية مرتبطة">—</span>
                    </td>
                  </tr>
                  <tr v-if="!loading && !rows.length">
                    <td colspan="7" class="py-10 text-slate-500 dark:text-slate-300">
                      لا توجد حركات على هذا الحساب بعد
                    </td>
                  </tr>
                </tbody>
                <tfoot v-if="rows.length" class="exp-print-totals hidden print:table-footer-group">
                  <tr>
                    <td colspan="3" class="text-start font-bold">المجموع</td>
                    <td class="font-bold" dir="ltr">{{ formatMoney(totalDebit) }}</td>
                    <td class="font-bold" dir="ltr">{{ formatMoney(totalCredit) }}</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <p class="mt-4 text-xs text-slate-500 dark:text-slate-400 print:hidden">
              وصل صرف = مدين هذا الحساب · دائن القاصة.
              وصل قبض = مدين القاصة · دائن هذا الحساب
              <template v-if="account.type === 'expense'"> (استرداد/تخفيض مصروف)</template>
              <template v-else> (تسجيل إيراد)</template>.
            </p>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.exp-page {
  --c-bg: #ffffff;
  --c-border: #e2e8f0;
  --c-head: #f1f5f9;
  --c-text: #0f172a;
  --c-muted: #64748b;
  width: 100%;
}

.exp-shell {
  max-width: min(100%, 96rem);
}

:global(.dark) .exp-page,
.dark .exp-page {
  --c-bg: #0f172a;
  --c-border: #334155;
  --c-head: #1e293b;
  --c-text: #f1f5f9;
  --c-muted: #94a3b8;
}

.exp-card {
  background: var(--c-bg);
  color: var(--c-text);
  border: 1px solid var(--c-border);
}

.exp-back {
  font-size: 0.85rem;
  font-weight: 600;
  color: #0d9488;
  text-decoration: none;
}

.dark .exp-back {
  color: #5eead4;
}

.exp-code {
  display: inline-block;
  margin-inline-start: 0.4rem;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--c-muted);
}

.exp-select {
  border-radius: 0.5rem;
  border: 1px solid #cbd5e1;
  background: #f8fafc;
  color: #0f172a;
  padding: 0.45rem 0.65rem;
  font-weight: 600;
  min-height: 2.4rem;
}

.dark .exp-select {
  background: #020617;
  border-color: #475569;
  color: #fff;
}

.exp-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  border-radius: 0.5rem;
  padding: 0.55rem 1rem;
  border: 0;
  cursor: pointer;
  min-height: 2.5rem;
  color: #fff;
}

.exp-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.exp-btn-primary {
  background: #059669;
}

.exp-btn-primary:hover:not(:disabled) {
  background: #047857;
}

.exp-btn-receive {
  background: #0284c7;
}

.exp-btn-receive:hover:not(:disabled) {
  background: #0369a1;
}

.exp-btn-danger {
  background: #e11d48;
}

.exp-btn-danger:hover:not(:disabled) {
  background: #be123c;
}

.exp-btn-print {
  background: #ea580c;
}

.exp-btn-print:hover {
  background: #c2410c;
}

.exp-row-print {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  padding: 0.35rem 0.65rem;
  border-radius: 0.45rem;
  background: #ea580c;
  color: #fff !important;
  font-size: 0.75rem;
  font-weight: 700;
  text-decoration: none;
  min-height: 2rem;
  white-space: nowrap;
}

.exp-row-print:hover {
  background: #c2410c;
}

.exp-row-print-icon {
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
}

.exp-kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
  gap: 1rem;
}

.exp-kpi {
  border: 1px solid var(--c-border);
  border-radius: 0.65rem;
  padding: 1rem 1.25rem;
  background: var(--c-head);
}

.exp-kpi-label {
  display: block;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--c-muted);
  margin-bottom: 0.35rem;
}

.exp-kpi-value {
  font-size: 1.45rem;
  font-weight: 800;
  color: #059669;
  font-variant-numeric: tabular-nums;
}

.dark .exp-kpi-value {
  color: #6ee7b7;
}

.exp-kpi-value.muted {
  font-size: 1.1rem;
  color: var(--c-muted);
}

.exp-table-wrap {
  border: 1px solid var(--c-border);
  min-height: 18rem;
}

.exp-table {
  border-collapse: collapse;
  color: var(--c-text);
}

.exp-table thead th {
  background: var(--c-head);
  padding: 0.85rem 0.65rem;
  font-size: 0.85rem;
  font-weight: 700;
  border-bottom: 1px solid var(--c-border);
}

.exp-table tbody td {
  padding: 0.75rem 0.65rem;
  border-bottom: 1px solid var(--c-border);
  vertical-align: middle;
}

.exp-table tfoot td {
  padding: 0.75rem 0.65rem;
  border-top: 2px solid var(--c-border);
  background: var(--c-head);
  vertical-align: middle;
}

@media print {
  @page {
    size: A4;
    margin: 12mm 10mm;
  }

  :global(.dark) .exp-page,
  .dark .exp-page,
  .exp-page {
    --c-bg: #ffffff;
    --c-border: #cbd5e1;
    --c-head: #f1f5f9;
    --c-text: #0f172a;
    --c-muted: #475569;
    padding: 0 !important;
    background: #ffffff !important;
    color: #0f172a !important;
  }

  .exp-shell {
    max-width: none !important;
    padding: 0 !important;
  }

  .exp-card {
    background: #ffffff !important;
    color: #0f172a !important;
    border: none !important;
    box-shadow: none !important;
    border-radius: 0 !important;
    overflow: visible !important;
  }

  .exp-page h1,
  .exp-page p,
  .exp-print-meta,
  .exp-code,
  .exp-kpi-label {
    color: #0f172a !important;
  }

  .exp-kpi-label {
    color: #475569 !important;
  }

  .exp-kpi {
    background: #ffffff !important;
    border: 1px solid #94a3b8 !important;
    break-inside: avoid;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .dark .exp-kpi-value,
  .exp-kpi-value {
    color: #047857 !important;
  }

  .exp-kpi-value.muted {
    color: #334155 !important;
  }

  .exp-table {
    color: #0f172a !important;
  }

  .exp-table thead th {
    background: #e2e8f0 !important;
    color: #0f172a !important;
    border-bottom: 1px solid #94a3b8 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .exp-table tbody td,
  .exp-table tfoot td {
    background: #ffffff !important;
    color: #0f172a !important;
    border-bottom-color: #cbd5e1 !important;
  }

  .exp-table tfoot td {
    background: #f1f5f9 !important;
    border-top: 2px solid #64748b !important;
    font-weight: 700;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .exp-table-wrap {
    overflow: visible !important;
    border: 1px solid #94a3b8 !important;
    background: #ffffff !important;
    min-height: 0 !important;
  }
}
</style>
