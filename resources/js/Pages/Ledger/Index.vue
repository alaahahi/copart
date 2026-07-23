<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/inertia-vue3";
import { ref, computed, watch, onMounted } from "vue";
import axios from "axios";

const tab = ref("tree");
const currency = ref("$");
const from = ref(getFirstDayOfMonth());
const to = ref(getTodayDate());
const q = ref("");
const loading = ref(false);
const errorMsg = ref("");
const successMsg = ref("");

const treeGroups = ref([]);
const trialRows = ref([]);
const totalDebit = ref(0);
const totalCredit = ref(0);

const selectedAccountId = ref(null);
const accountMeta = ref(null);
const openingBalance = ref(0);
const ledgerRows = ref([]);

const journals = ref([]);

const editingId = ref(null);
const editNameAr = ref("");
const savingEdit = ref(false);
const deactivatingId = ref(null);

const currencyLabel = computed(() => (currency.value === "$" ? "USD" : "IQD"));

function getTodayDate() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function getFirstDayOfMonth() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-01`;
}

function formatMoney(v) {
  const n = Number(v || 0);
  return currency.value === "$"
    ? n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : n.toLocaleString(undefined, { maximumFractionDigits: 0 });
}

function typeLabel(type) {
  const map = {
    asset: "أصول",
    liability: "خصوم",
    equity: "حقوق ملكية",
    income: "إيرادات",
    expense: "مصاريف",
  };
  return map[type] || type;
}

function flashSuccess(msg) {
  successMsg.value = msg;
  errorMsg.value = "";
  setTimeout(() => {
    if (successMsg.value === msg) successMsg.value = "";
  }, 3500);
}

async function loadTree() {
  loading.value = true;
  errorMsg.value = "";
  try {
    const { data } = await axios.get("/api/ledgerChartOfAccounts", {
      params: { currency: currency.value, q: q.value },
    });
    treeGroups.value = data.groups || [];
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || "تعذر تحميل شجرة الحسابات";
  } finally {
    loading.value = false;
  }
}

async function loadTrial() {
  loading.value = true;
  errorMsg.value = "";
  try {
    const { data } = await axios.get("/api/ledgerTrialBalance", {
      params: { currency: currency.value, from: from.value, to: to.value, q: q.value },
    });
    trialRows.value = data.rows || [];
    totalDebit.value = data.total_debit || 0;
    totalCredit.value = data.total_credit || 0;
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || "تعذر تحميل ميزان المراجعة";
  } finally {
    loading.value = false;
  }
}

async function openAccount(accountId) {
  if (editingId.value) return;
  selectedAccountId.value = accountId;
  tab.value = "ledger";
  await loadAccountLedger();
}

async function loadAccountLedger() {
  if (!selectedAccountId.value) return;
  loading.value = true;
  errorMsg.value = "";
  try {
    const { data } = await axios.get("/api/ledgerAccount", {
      params: {
        account_id: selectedAccountId.value,
        currency: currency.value,
        from: from.value,
        to: to.value,
      },
    });
    accountMeta.value = data.account;
    openingBalance.value = data.opening_balance || 0;
    ledgerRows.value = data.rows || [];
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || "تعذر تحميل حركة الحساب";
  } finally {
    loading.value = false;
  }
}

async function loadJournals() {
  loading.value = true;
  errorMsg.value = "";
  try {
    const { data } = await axios.get("/api/ledgerJournals", {
      params: { currency: currency.value, limit: 80 },
    });
    journals.value = data.entries || [];
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || "تعذر تحميل القيود";
  } finally {
    loading.value = false;
  }
}

function startEdit(acc, e) {
  e?.stopPropagation?.();
  editingId.value = acc.id;
  editNameAr.value = acc.name_ar || acc.name || "";
  errorMsg.value = "";
}

function cancelEdit(e) {
  e?.stopPropagation?.();
  editingId.value = null;
  editNameAr.value = "";
}

async function saveEdit(acc, e) {
  e?.stopPropagation?.();
  const nameAr = (editNameAr.value || "").trim();
  if (!nameAr) {
    errorMsg.value = "الاسم العربي للحساب مطلوب";
    return;
  }

  savingEdit.value = true;
  errorMsg.value = "";
  try {
    const { data } = await axios.post("/api/ledgerAccountUpdate", {
      id: acc.id,
      name_ar: nameAr,
      name: nameAr,
    });
    editingId.value = null;
    editNameAr.value = "";
    flashSuccess(data.message || "تم تحديث اسم الحساب بنجاح");
    await loadTree();
  } catch (err) {
    errorMsg.value =
      err?.response?.data?.message ||
      err?.response?.data?.errors?.name_ar?.[0] ||
      "تعذر تحديث اسم الحساب";
  } finally {
    savingEdit.value = false;
  }
}

async function deactivateAccount(acc, e) {
  e?.stopPropagation?.();
  if (acc.is_system) {
    errorMsg.value = "لا يمكن حذف أو إيقاف الحسابات النظامية";
    return;
  }

  const ok = window.confirm(
    `إيقاف الحساب «${acc.name}»؟\nلن يظهر في شجرة الحسابات، والقيود السابقة تبقى محفوظة.`
  );
  if (!ok) return;

  deactivatingId.value = acc.id;
  errorMsg.value = "";
  try {
    const { data } = await axios.post("/api/ledgerAccountDeactivate", { id: acc.id });
    flashSuccess(data.message || "تم إيقاف الحساب بنجاح");
    await loadTree();
  } catch (err) {
    errorMsg.value = err?.response?.data?.message || "تعذر إيقاف الحساب";
  } finally {
    deactivatingId.value = null;
  }
}

async function refresh() {
  if (tab.value === "tree") await loadTree();
  else if (tab.value === "trial") await loadTrial();
  else if (tab.value === "ledger") await loadAccountLedger();
  else await loadJournals();
}

watch(currency, () => refresh());
watch([from, to], () => {
  if (tab.value === "trial" || tab.value === "ledger") refresh();
});
watch(tab, () => {
  editingId.value = null;
  refresh();
});

onMounted(() => refresh());
</script>

<template>
  <Head title="دفتر الأستاذ" />
  <AuthenticatedLayout>
    <div class="py-6">
      <div class="mx-auto max-w-8xl sm:px-6 lg:px-8">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
          <div class="border-b border-slate-200 p-4 dark:border-slate-700">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">دفتر الأستاذ</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">شجرة الحسابات · ميزان · حركة · قيود</p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button
                  type="button"
                  class="rounded-lg px-4 py-2 text-sm font-semibold"
                  :class="tab === 'tree' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'"
                  @click="tab = 'tree'"
                >
                  شجرة الحسابات
                </button>
                <button
                  type="button"
                  class="rounded-lg px-4 py-2 text-sm font-semibold"
                  :class="tab === 'trial' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'"
                  @click="tab = 'trial'"
                >
                  ميزان المراجعة
                </button>
                <button
                  type="button"
                  class="rounded-lg px-4 py-2 text-sm font-semibold"
                  :class="tab === 'journals' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'"
                  @click="tab = 'journals'"
                >
                  آخر القيود
                </button>
                <button
                  type="button"
                  class="rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white"
                  @click="refresh"
                >
                  تحديث
                </button>
              </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-5">
              <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">العملة</label>
                <select v-model="currency" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                  <option value="$">USD</option>
                  <option value="IQD">IQD</option>
                </select>
              </div>
              <div v-if="tab === 'trial' || tab === 'ledger'">
                <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">من</label>
                <input v-model="from" type="date" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-950 dark:text-white" />
              </div>
              <div v-if="tab === 'trial' || tab === 'ledger'">
                <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">إلى</label>
                <input v-model="to" type="date" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-950 dark:text-white" />
              </div>
              <div class="md:col-span-2" v-if="tab === 'trial' || tab === 'tree'">
                <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">بحث</label>
                <input
                  v-model="q"
                  type="text"
                  placeholder="رمز أو اسم الحساب"
                  class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                  @keyup.enter="refresh"
                />
              </div>
            </div>

            <div v-if="errorMsg" class="mt-3 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
              {{ errorMsg }}
            </div>
            <div v-if="successMsg" class="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
              {{ successMsg }}
            </div>
          </div>

          <div class="p-4">
            <div v-if="loading" class="py-10 text-center text-slate-500">جاري التحميل...</div>

            <!-- شجرة بسيطة جداً -->
            <template v-else-if="tab === 'tree'">
              <div class="mx-auto max-w-2xl space-y-4">
                <div
                  v-for="group in treeGroups"
                  :key="group.type"
                  class="rounded-xl border border-slate-200 dark:border-slate-700"
                >
                  <div class="flex items-center justify-between border-b border-slate-200 bg-slate-100 px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                    <span class="text-base font-bold text-slate-900 dark:text-white">{{ group.label }}</span>
                    <span class="font-mono text-sm font-semibold text-slate-700 dark:text-slate-200">
                      {{ formatMoney(group.total) }} {{ currencyLabel }}
                    </span>
                  </div>
                  <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li
                      v-for="acc in group.accounts"
                      :key="acc.id"
                      class="px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50"
                      :class="editingId === acc.id ? '' : 'cursor-pointer'"
                      @click="openAccount(acc.id)"
                    >
                      <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0 flex-1 text-right">
                          <template v-if="editingId === acc.id">
                            <div class="flex flex-wrap items-center justify-end gap-2" @click.stop>
                              <input
                                v-model="editNameAr"
                                type="text"
                                class="min-w-[10rem] flex-1 rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                                placeholder="الاسم العربي"
                                @keyup.enter="saveEdit(acc)"
                                @keyup.escape="cancelEdit"
                              />
                              <button
                                type="button"
                                class="rounded bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white disabled:opacity-50"
                                :disabled="savingEdit"
                                @click="saveEdit(acc, $event)"
                              >
                                حفظ
                              </button>
                              <button
                                type="button"
                                class="rounded bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200"
                                :disabled="savingEdit"
                                @click="cancelEdit($event)"
                              >
                                إلغاء
                              </button>
                            </div>
                          </template>
                          <template v-else>
                            <div class="truncate font-semibold text-slate-900 dark:text-slate-100">
                              {{ acc.name }}
                              <span
                                v-if="acc.is_system"
                                class="mr-1 text-[10px] font-normal text-slate-400"
                              >نظامي</span>
                            </div>
                            <div class="font-mono text-xs text-slate-500">{{ acc.code }}</div>
                          </template>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                          <div
                            v-if="editingId !== acc.id"
                            class="font-mono text-sm font-bold text-slate-800 dark:text-white"
                          >
                            {{ formatMoney(acc.balance) }}
                          </div>
                          <button
                            v-if="editingId !== acc.id"
                            type="button"
                            class="rounded px-2 py-1 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-950/40"
                            title="تعديل الاسم"
                            @click="startEdit(acc, $event)"
                          >
                            تعديل
                          </button>
                          <button
                            v-if="editingId !== acc.id && !acc.is_system"
                            type="button"
                            class="rounded px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 disabled:opacity-50 dark:text-rose-400 dark:hover:bg-rose-950/40"
                            title="إيقاف الحساب"
                            :disabled="deactivatingId === acc.id"
                            @click="deactivateAccount(acc, $event)"
                          >
                            إيقاف
                          </button>
                        </div>
                      </div>
                    </li>
                  </ul>
                </div>
                <div v-if="!treeGroups.length" class="py-10 text-center text-slate-500">لا توجد حسابات</div>
                <p class="text-center text-xs text-slate-500">اضغط على أي حساب لعرض حركته · تعديل لتغيير الاسم · إيقاف لإخفاء الحساب غير النظامي</p>
              </div>
            </template>

            <template v-else-if="tab === 'trial'">
              <div class="mb-3 flex flex-wrap gap-4 text-sm font-semibold">
                <span class="text-slate-700 dark:text-slate-200">إجمالي المدين: {{ formatMoney(totalDebit) }} {{ currencyLabel }}</span>
                <span class="text-slate-700 dark:text-slate-200">إجمالي الدائن: {{ formatMoney(totalCredit) }} {{ currencyLabel }}</span>
              </div>
              <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                <table class="w-full text-center text-sm">
                  <thead class="bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-white">
                    <tr>
                      <th class="px-3 py-2">الرمز</th>
                      <th class="px-3 py-2">الحساب</th>
                      <th class="px-3 py-2">النوع</th>
                      <th class="px-3 py-2">مدين</th>
                      <th class="px-3 py-2">دائن</th>
                      <th class="px-3 py-2">الرصيد</th>
                      <th class="px-3 py-2">عرض</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="row in trialRows"
                      :key="row.id"
                      class="border-t border-slate-200 dark:border-slate-700 dark:text-slate-200"
                    >
                      <td class="px-3 py-2 font-mono">{{ row.code }}</td>
                      <td class="px-3 py-2 font-semibold">{{ row.name }}</td>
                      <td class="px-3 py-2">{{ typeLabel(row.type) }}</td>
                      <td class="px-3 py-2">{{ formatMoney(row.debit) }}</td>
                      <td class="px-3 py-2">{{ formatMoney(row.credit) }}</td>
                      <td class="px-3 py-2 font-bold">{{ formatMoney(row.balance) }}</td>
                      <td class="px-3 py-2">
                        <button type="button" class="rounded bg-blue-600 px-3 py-1 text-xs font-semibold text-white" @click="openAccount(row.id)">
                          حركة
                        </button>
                      </td>
                    </tr>
                    <tr v-if="!trialRows.length">
                      <td colspan="7" class="px-3 py-8 text-slate-500">لا توجد حركات ضمن الفلتر</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>

            <template v-else-if="tab === 'ledger'">
              <div class="mb-3">
                <button
                  type="button"
                  class="mb-2 text-sm font-semibold text-indigo-600 dark:text-indigo-400"
                  @click="tab = 'tree'"
                >
                  ← رجوع للشجرة
                </button>
              </div>
              <div v-if="accountMeta" class="mb-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/60">
                <div class="font-bold text-slate-900 dark:text-white">
                  {{ accountMeta.code }} — {{ accountMeta.name }}
                </div>
                <div class="text-sm text-slate-600 dark:text-slate-300">
                  رصيد افتتاحي: {{ formatMoney(openingBalance) }} {{ currencyLabel }}
                </div>
              </div>
              <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                <table class="w-full text-center text-sm">
                  <thead class="bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-white">
                    <tr>
                      <th class="px-3 py-2">التاريخ</th>
                      <th class="px-3 py-2">رقم القيد</th>
                      <th class="px-3 py-2">البيان</th>
                      <th class="px-3 py-2">مدين</th>
                      <th class="px-3 py-2">دائن</th>
                      <th class="px-3 py-2">الرصيد</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="row in ledgerRows"
                      :key="row.id"
                      class="border-t border-slate-200 dark:border-slate-700 dark:text-slate-200"
                    >
                      <td class="px-3 py-2">{{ row.date }}</td>
                      <td class="px-3 py-2 font-mono text-xs">{{ row.voucher_no }}</td>
                      <td class="px-3 py-2 text-right">{{ row.memo }}</td>
                      <td class="px-3 py-2">{{ formatMoney(row.debit) }}</td>
                      <td class="px-3 py-2">{{ formatMoney(row.credit) }}</td>
                      <td class="px-3 py-2 font-bold">{{ formatMoney(row.balance) }}</td>
                    </tr>
                    <tr v-if="!ledgerRows.length">
                      <td colspan="6" class="px-3 py-8 text-slate-500">لا توجد قيود لهذا الحساب</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>

            <template v-else>
              <div class="space-y-3">
                <div
                  v-for="entry in journals"
                  :key="entry.id"
                  class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                >
                  <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <div class="font-bold text-slate-900 dark:text-white">{{ entry.voucher_no }}</div>
                    <div class="text-sm text-slate-500">{{ entry.entry_date }} · {{ entry.source }}</div>
                  </div>
                  <div class="mb-2 text-sm text-slate-600 dark:text-slate-300">{{ entry.memo }}</div>
                  <table class="w-full text-center text-xs">
                    <thead>
                      <tr class="text-slate-500">
                        <th class="py-1">الحساب</th>
                        <th class="py-1">مدين</th>
                        <th class="py-1">دائن</th>
                        <th class="py-1">عملة</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(line, idx) in entry.lines" :key="idx" class="border-t border-slate-100 dark:border-slate-800 dark:text-slate-200">
                        <td class="py-1">{{ line.code }} — {{ line.account }}</td>
                        <td class="py-1">{{ formatMoney(line.debit) }}</td>
                        <td class="py-1">{{ formatMoney(line.credit) }}</td>
                        <td class="py-1">{{ line.currency }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-if="!journals.length" class="py-8 text-center text-slate-500">لا توجد قيود بعد</div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
