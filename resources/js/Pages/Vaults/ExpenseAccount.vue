<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/inertia-vue3';
import ModalExpenseDisburse from '@/Components/ModalExpenseDisburse.vue';
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
const showDisburse = ref(false);
const disburseModalRef = ref(null);
const flash = ref('');

const canDisburse = computed(() => !!account.value?.can_disburse);

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
        can_disburse: account.value.type === 'expense' || data.account.type === 'expense',
      };
    }
    // Refresh balances
    const list = await axios.get('/api/ledgerExpenseAccounts', {
      params: { currency: currency.value },
    });
    const match = (list.data.accounts || []).find((a) => a.id === account.value.id);
    if (match) {
      account.value.balance = match.balance;
      account.value.balance_dinar = match.balance_dinar;
      account.value.can_disburse = !!match.can_disburse;
    }
  } catch (error) {
    loadError.value = error?.response?.data?.message || 'تعذر تحميل دفتر الحساب';
    console.error(error);
  } finally {
    loading.value = false;
  }
}

onMounted(loadLedger);

async function confirmDisburse(payload) {
  disburseModalRef.value?.setSaving?.(true);
  disburseModalRef.value?.setError?.('');
  try {
    const { data } = await axios.post('/api/ledgerExpenseDisburse', {
      ...payload,
      expense_ledger_account_id: account.value.id,
    });
    showDisburse.value = false;
    flash.value = data.message || 'تم صرف المصروف';
    if (data.expense_balance !== undefined) {
      account.value.balance = data.expense_balance;
    }
    if (data.expense_balance_dinar !== undefined) {
      account.value.balance_dinar = data.expense_balance_dinar;
    }
    await loadLedger();
  } catch (error) {
    const msg = error?.response?.data?.message
      || error?.response?.data?.errors?.amount?.[0]
      || 'تعذر صرف المصروف';
    disburseModalRef.value?.setError?.(msg);
    console.error(error);
  } finally {
    disburseModalRef.value?.setSaving?.(false);
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
</script>

<template>
  <Head :title="`مصروف — ${account.name}`" />
  <AuthenticatedLayout>
    <ModalExpenseDisburse
      ref="disburseModalRef"
      :show="showDisburse"
      :cash-vaults="cashVaults"
      :account-name="account.name"
      @save="confirmDisburse"
      @close="showDisburse = false"
    />

    <div class="exp-page py-6 sm:py-8">
      <div class="mx-auto sm:px-6 lg:px-8">
        <div class="exp-card overflow-hidden shadow-sm sm:rounded-xl">
          <div class="p-4 sm:p-6">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
              <div>
                <Link :href="route('vaults')" class="exp-back print:hidden">← العودة للقاصات / المصاريف</Link>
                <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">
                  {{ account.name }}
                  <span class="exp-code" dir="ltr">{{ account.code }}</span>
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">
                  حساب في دليل الحسابات
                  <template v-if="account.type === 'expense'">(مصروف)</template>
                  <template v-else>(إيراد/عمولة)</template>
                  — الرصيد من قيود اليومية
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
                  v-if="canDisburse"
                  type="button"
                  class="exp-btn exp-btn-primary"
                  @click="showDisburse = true"
                >
                  صرف مصروف
                </button>
              </div>
            </div>

            <div class="exp-kpis mb-5">
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
                  </tr>
                  <tr v-if="!loading && !rows.length">
                    <td colspan="6" class="py-8 text-slate-500 dark:text-slate-300">
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

            <p v-if="canDisburse" class="mt-4 text-xs text-slate-500 dark:text-slate-400 print:hidden">
              صرف مصروف = اختيار قاصة نقدية + مبلغ ← قيد: مدين هذا الحساب · دائن القاصة. لا يوجد تحويل بين حسابات المصاريف.
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
  font-size: 0.85rem;
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

.exp-btn-primary {
  background: #059669;
}

.exp-btn-primary:hover {
  background: #047857;
}

.exp-btn-print {
  background: #ea580c;
}

.exp-btn-print:hover {
  background: #c2410c;
}

.exp-kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
  gap: 0.75rem;
}

.exp-kpi {
  border: 1px solid var(--c-border);
  border-radius: 0.65rem;
  padding: 0.85rem 1rem;
  background: var(--c-head);
}

.exp-kpi-label {
  display: block;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--c-muted);
  margin-bottom: 0.25rem;
}

.exp-kpi-value {
  font-size: 1.25rem;
  font-weight: 800;
  color: #059669;
  font-variant-numeric: tabular-nums;
}

.dark .exp-kpi-value {
  color: #6ee7b7;
}

.exp-kpi-value.muted {
  font-size: 1rem;
  color: var(--c-muted);
}

.exp-table-wrap {
  border: 1px solid var(--c-border);
}

.exp-table {
  border-collapse: collapse;
  color: var(--c-text);
}

.exp-table thead th {
  background: var(--c-head);
  padding: 0.7rem 0.5rem;
  font-size: 0.8rem;
  font-weight: 700;
  border-bottom: 1px solid var(--c-border);
}

.exp-table tbody td {
  padding: 0.6rem 0.5rem;
  border-bottom: 1px solid var(--c-border);
  vertical-align: middle;
}

.exp-table tfoot td {
  padding: 0.65rem 0.5rem;
  border-top: 2px solid var(--c-border);
  background: var(--c-head);
  vertical-align: middle;
}

@media print {
  @page {
    size: A4;
    margin: 12mm 10mm;
  }

  .exp-page {
    --c-bg: #ffffff;
    --c-border: #cbd5e1;
    --c-head: #f1f5f9;
    --c-text: #0f172a;
    --c-muted: #475569;
    padding: 0 !important;
    background: #fff !important;
    color: #0f172a !important;
  }

  .exp-card {
    border: none !important;
    box-shadow: none !important;
    border-radius: 0 !important;
    overflow: visible !important;
  }

  .exp-kpi {
    background: #fff !important;
    border-color: #94a3b8 !important;
    break-inside: avoid;
  }

  .exp-kpi-value {
    color: #047857 !important;
  }

  .exp-kpi-value.muted {
    color: #334155 !important;
  }

  .exp-table thead th {
    background: #e2e8f0 !important;
    color: #0f172a !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .exp-table tbody td,
  .exp-table tfoot td {
    color: #0f172a !important;
  }

  .exp-table-wrap {
    overflow: visible !important;
    border-color: #94a3b8 !important;
  }
}
</style>
