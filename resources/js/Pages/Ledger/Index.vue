<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ModalLedgerAccount from "@/Components/ModalLedgerAccount.vue";
import { Head, usePage } from "@inertiajs/inertia-vue3";
import { ref, computed, watch, onMounted } from "vue";
import axios from "axios";
import { formatMoney as formatMoneyAmount } from "@/utils/formatMoney";

const VALID_TABS = ["tree", "trial", "ledger", "journals", "transfer", "profits", "receipts", "purchases"];
const REPORT_TABS = ["tree", "trial", "ledger", "journals"];

const page = usePage();
const isAdmin = computed(() => Number(page.props.value?.auth?.user?.type_id) === 1);

const tab = ref("tree");
const currency = ref("$");
const from = ref(getFirstDayOfMonth());
const to = ref(getTodayDate());
const q = ref("");
const ledgerAccountQ = ref("");
const loading = ref(false);
const errorMsg = ref("");
const successMsg = ref("");

const treeGroups = ref([]);
const parentOptions = ref([]);
const trialRows = ref([]);
const totalDebit = ref(0);
const totalCredit = ref(0);

const selectedAccountId = ref(null);
const accountMeta = ref(null);
const openingBalance = ref(0);
const ledgerRows = ref([]);

const journals = ref([]);

const showAccountModal = ref(false);
const accountModalMode = ref("create");
const editingAccount = ref(null);
const savingAccount = ref(false);
const deactivatingId = ref(null);

/** قاصة استلام دفعات الزبائن — نقد فقط (from vaults table) */
const receiptsVaultId = ref("");
const receiptsVaultOptions = ref([]);
const receiptsVaultSaving = ref(false);
const receiptsVaultLabel = ref("");

/** قاصة صرف المشتريات — نقد فقط (from vaults table) */
const purchasesVaultId = ref("");
const purchasesVaultOptions = ref([]);
const purchasesVaultSaving = ref(false);
const purchasesVaultLabel = ref("");

const previousTab = ref("tree");

const TREE_COLLAPSE_KEY = "ledger_tree_collapsed_groups";
const collapsedGroups = ref(loadCollapsedGroups());

function loadCollapsedGroups() {
  try {
    const raw = localStorage.getItem(TREE_COLLAPSE_KEY);
    if (!raw) return {};
    const parsed = JSON.parse(raw);
    return parsed && typeof parsed === "object" ? parsed : {};
  } catch {
    return {};
  }
}

function persistCollapsedGroups() {
  try {
    localStorage.setItem(TREE_COLLAPSE_KEY, JSON.stringify(collapsedGroups.value));
  } catch {
    /* ignore quota / private mode */
  }
}

function isGroupCollapsed(type) {
  return !!collapsedGroups.value[type];
}

function toggleGroup(type) {
  collapsedGroups.value = {
    ...collapsedGroups.value,
    [type]: !collapsedGroups.value[type],
  };
  persistCollapsedGroups();
}

function expandAllGroups() {
  collapsedGroups.value = {};
  persistCollapsedGroups();
}

function collapseAllGroups() {
  const next = {};
  for (const g of treeGroups.value) {
    next[g.type] = true;
  }
  collapsedGroups.value = next;
  persistCollapsedGroups();
}

function groupAccent(type) {
  const map = {
    asset: "border-sky-500",
    liability: "border-amber-500",
    equity: "border-violet-500",
    income: "border-emerald-500",
    expense: "border-rose-500",
  };
  return map[type] || "border-slate-500";
}

// --- تحويل بين الحسابات ---
const transferAccounts = ref([]);
const transferLoading = ref(false);
const transferSubmitting = ref(false);
const transferForm = ref({
  from_user_id: "",
  to_user_id: "",
  amount: "",
  currency: "$",
  entry_date: getTodayDate(),
  notes: "",
});

// --- أرباح التجار ---
const profitsBalance = ref(0);
const profitsCurrency = ref("$");
const profitsEntries = ref([]);
const profitsLoading = ref(false);
const traderRows = ref([]);
const traderRowsLoading = ref(false);
const postForm = ref({
  client_id: "",
  amount: "",
  currency: "$",
  period_from: getFirstDayOfMonth(),
  period_to: getTodayDate(),
  entry_date: getTodayDate(),
  notes: "",
});
const withdrawForm = ref({
  amount: "",
  currency: "$",
  entry_date: getTodayDate(),
  notes: "",
});
const postSubmitting = ref(false);
const withdrawSubmitting = ref(false);

const currencyLabel = computed(() => {
  if (currency.value === "both") return "مزدوج";
  return currency.value === "$" ? "USD" : "IQD";
});

/** Trial / ledger statement need a concrete journal currency; `both` falls back to USD. */
const reportCurrency = computed(() => (currency.value === "IQD" ? "IQD" : "$"));

const reportCurrencyLabel = computed(() => (reportCurrency.value === "IQD" ? "IQD" : "USD"));

const treeAccountCount = computed(() =>
  treeGroups.value.reduce((n, g) => n + (g.accounts?.length || 0), 0)
);

const flatAccounts = computed(() => {
  const list = [];
  for (const g of treeGroups.value) {
    for (const a of g.accounts || []) {
      list.push({ ...a, type_label: typeLabel(a.type) });
    }
  }
  if (!list.length && parentOptions.value.length) {
    return parentOptions.value.map((a) => ({
      ...a,
      type_label: typeLabel(a.type),
      balance: null,
    }));
  }
  return list;
});

const filteredLedgerAccounts = computed(() => {
  const term = ledgerAccountQ.value.trim().toLowerCase();
  const all = flatAccounts.value;
  if (!term) return all;
  const filtered = all.filter((a) => {
    const hay = `${a.code || ""} ${a.name || ""} ${a.type_label || ""}`.toLowerCase();
    return hay.includes(term);
  });
  if (selectedAccountId.value) {
    const sel = all.find((a) => a.id === selectedAccountId.value);
    if (sel && !filtered.some((a) => a.id === sel.id)) {
      return [sel, ...filtered];
    }
  }
  return filtered;
});

const closingBalance = computed(() => {
  if (ledgerRows.value.length) {
    return ledgerRows.value[ledgerRows.value.length - 1].balance;
  }
  return openingBalance.value;
});

const trialBalanced = computed(
  () => Math.abs(Number(totalDebit.value) - Number(totalCredit.value)) < 0.005
);

function getTodayDate() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function getFirstDayOfMonth() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-01`;
}

function formatMoney(v) {
  return formatMoneyAmount(v, reportCurrency.value);
}

function formatGroupTotal(group) {
  if (currency.value === "both") {
    const usd = formatMoneyAmount(group.total, "$");
    const iqd = formatMoneyAmount(group.total_iqd ?? 0, "IQD");
    return `USD ${usd} · IQD ${iqd}`;
  }
  return `${formatMoney(group.total)} ${currencyLabel.value}`;
}

function accountRowBalanceLabel(acc) {
  if (currency.value === "both" && acc.currency == null && acc.balance_usd != null && acc.balance_iqd != null) {
    return `${formatMoneyAmount(acc.balance_usd, "$")} / ${formatMoneyAmount(acc.balance_iqd, "IQD")}`;
  }
  if (currency.value === "both" && acc.currency === "IQD") {
    return formatMoneyAmount(acc.balance, "IQD");
  }
  return formatMoney(acc.balance);
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

function tabBtnClass(name) {
  const active = tab.value === name;
  if (REPORT_TABS.includes(name)) {
    return active
      ? "bg-emerald-600 text-white shadow-sm"
      : "bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700";
  }
  return active
    ? "bg-sky-600 text-white shadow-sm"
    : "bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700";
}

function readQueryState() {
  try {
    const params = new URLSearchParams(window.location.search);
    const t = params.get("tab");
    if (VALID_TABS.includes(t)) {
      if ((t === "receipts" || t === "purchases") && !isAdmin.value) {
        tab.value = "tree";
      } else {
        tab.value = t;
      }
    }
    const a = params.get("account");
    if (a && !Number.isNaN(Number(a)) && Number(a) > 0) {
      selectedAccountId.value = Number(a);
    }
  } catch {
    /* ignore */
  }
}

function syncQuery() {
  try {
    const params = new URLSearchParams();
    params.set("tab", tab.value);
    if (selectedAccountId.value) {
      params.set("account", String(selectedAccountId.value));
    }
    if (currency.value && currency.value !== "$") {
      params.set("currency", currency.value);
    }
    const qs = params.toString();
    const url = qs ? `${window.location.pathname}?${qs}` : window.location.pathname;
    window.history.replaceState({}, "", url);
  } catch {
    /* ignore */
  }
}

function setTab(next) {
  if (!VALID_TABS.includes(next)) return;
  if ((next === "receipts" || next === "purchases") && !isAdmin.value) return;
  if (tab.value !== next && REPORT_TABS.includes(tab.value)) {
    previousTab.value = tab.value;
  }
  tab.value = next;
}

function goBackFromLedger() {
  const back = previousTab.value && previousTab.value !== "ledger" ? previousTab.value : "tree";
  setTab(back);
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
    parentOptions.value = data.parent_options || [];
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
      params: { currency: reportCurrency.value, from: from.value, to: to.value, q: q.value },
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
  if (showAccountModal.value) return;
  const id = Number(accountId);
  if (!id) return;
  if (tab.value !== "ledger") {
    previousTab.value = tab.value;
  }
  selectedAccountId.value = id;
  if (tab.value !== "ledger") {
    tab.value = "ledger";
  }
}

async function ensureAccountOptions() {
  if (treeGroups.value.length || parentOptions.value.length) return;
  await loadTree();
}

async function loadAccountLedger() {
  if (!selectedAccountId.value) {
    accountMeta.value = null;
    openingBalance.value = 0;
    ledgerRows.value = [];
    return;
  }
  loading.value = true;
  errorMsg.value = "";
  try {
    const { data } = await axios.get("/api/ledgerAccount", {
      params: {
        account_id: selectedAccountId.value,
        currency: reportCurrency.value,
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
      params: { currency: currency.value === "both" ? "both" : currency.value, limit: 80 },
    });
    journals.value = data.entries || [];
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || "تعذر تحميل القيود";
  } finally {
    loading.value = false;
  }
}

// --- تحويل بين الحسابات ---
async function loadTransferAccounts() {
  transferLoading.value = true;
  errorMsg.value = "";
  try {
    const { data } = await axios.get("/api/accountTransfer/accounts");
    transferAccounts.value = data.accounts || [];
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || "تعذر تحميل قائمة الحسابات";
  } finally {
    transferLoading.value = false;
  }
}

function accountBalanceLabel(userId, currencyCode) {
  const acc = transferAccounts.value.find((a) => a.id === userId);
  if (!acc) return "";
  const val = currencyCode === "IQD" ? acc.balance_dinar : acc.balance;
  return formatMoney(val);
}

async function submitTransfer() {
  errorMsg.value = "";
  if (!transferForm.value.from_user_id || !transferForm.value.to_user_id) {
    errorMsg.value = "اختر الحساب المرسل والمستقبل";
    return;
  }
  if (transferForm.value.from_user_id === transferForm.value.to_user_id) {
    errorMsg.value = "لا يمكن التحويل من وإلى نفس الحساب";
    return;
  }
  if (!transferForm.value.amount || Number(transferForm.value.amount) <= 0) {
    errorMsg.value = "أدخل مبلغاً صحيحاً أكبر من صفر";
    return;
  }

  transferSubmitting.value = true;
  try {
    const { data } = await axios.post("/api/accountTransfer", transferForm.value);
    flashSuccess(data.message || "تم تنفيذ التحويل بنجاح");
    transferForm.value.amount = "";
    transferForm.value.notes = "";
    await loadTransferAccounts();
  } catch (err) {
    errorMsg.value =
      err?.response?.data?.message ||
      Object.values(err?.response?.data?.errors || {})[0]?.[0] ||
      "تعذر تنفيذ التحويل";
  } finally {
    transferSubmitting.value = false;
  }
}

// --- أرباح التجار ---
async function loadProfitsSummary() {
  profitsLoading.value = true;
  errorMsg.value = "";
  try {
    const { data } = await axios.get("/api/traderProfits/summary", {
      params: { currency: profitsCurrency.value },
    });
    profitsBalance.value = data.balance || 0;
    profitsEntries.value = data.entries || [];
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || "تعذر تحميل ملخص حساب الأرباح";
  } finally {
    profitsLoading.value = false;
  }
}

async function loadTraderRows() {
  traderRowsLoading.value = true;
  try {
    const { data } = await axios.get("/api/analyticsDashboard", {
      params: {
        from: postForm.value.period_from,
        to: postForm.value.period_to,
        currency: postForm.value.currency,
      },
    });
    traderRows.value = data.data?.trader_profits || [];
  } catch (e) {
    // اختياري فقط لتعبئة القيم — لا نوقف الصفحة عند الفشل
  } finally {
    traderRowsLoading.value = false;
  }
}

function pickTraderRow(row) {
  postForm.value.client_id = row.client_id;
  postForm.value.amount = row.profit;
}

async function submitPostProfit() {
  errorMsg.value = "";
  if (!postForm.value.client_id) {
    errorMsg.value = "اختر التاجر أولاً";
    return;
  }
  if (!postForm.value.amount || Number(postForm.value.amount) <= 0) {
    errorMsg.value = "أدخل مبلغاً صحيحاً أكبر من صفر";
    return;
  }

  postSubmitting.value = true;
  try {
    const { data } = await axios.post("/api/traderProfits/post", postForm.value);
    flashSuccess(data.message || "تم ترحيل أرباح التاجر بنجاح");
    postForm.value.amount = "";
    postForm.value.notes = "";
    await loadProfitsSummary();
  } catch (err) {
    errorMsg.value =
      err?.response?.data?.message ||
      Object.values(err?.response?.data?.errors || {})[0]?.[0] ||
      "تعذر ترحيل أرباح التاجر";
  } finally {
    postSubmitting.value = false;
  }
}

async function submitWithdrawProfit() {
  errorMsg.value = "";
  if (!withdrawForm.value.amount || Number(withdrawForm.value.amount) <= 0) {
    errorMsg.value = "أدخل مبلغاً صحيحاً أكبر من صفر";
    return;
  }

  withdrawSubmitting.value = true;
  try {
    const { data } = await axios.post("/api/traderProfits/withdraw", withdrawForm.value);
    flashSuccess(data.message || "تم السحب من حساب الأرباح بنجاح");
    withdrawForm.value.amount = "";
    withdrawForm.value.notes = "";
    await loadProfitsSummary();
  } catch (err) {
    errorMsg.value =
      err?.response?.data?.message ||
      Object.values(err?.response?.data?.errors || {})[0]?.[0] ||
      "تعذر السحب من حساب الأرباح";
  } finally {
    withdrawSubmitting.value = false;
  }
}

async function deleteProfitEntry(entry) {
  const ok = window.confirm(`حذف هذه الحركة (${formatMoney(entry.amount)} ${entry.currency})؟`);
  if (!ok) return;

  try {
    const { data } = await axios.post("/api/traderProfits/delete", { id: entry.id });
    flashSuccess(data.message || "تم حذف الحركة بنجاح");
    await loadProfitsSummary();
  } catch (err) {
    errorMsg.value = err?.response?.data?.message || "تعذر حذف الحركة";
  }
}

function openCreateAccount() {
  accountModalMode.value = "create";
  editingAccount.value = null;
  showAccountModal.value = true;
  errorMsg.value = "";
}

function startEdit(acc, e) {
  e?.stopPropagation?.();
  accountModalMode.value = "edit";
  editingAccount.value = { ...acc };
  showAccountModal.value = true;
  errorMsg.value = "";
}

function closeAccountModal() {
  if (savingAccount.value) return;
  showAccountModal.value = false;
  editingAccount.value = null;
}

async function submitAccountModal(payload) {
  savingAccount.value = true;
  errorMsg.value = "";
  try {
    if (accountModalMode.value === "create") {
      const { data } = await axios.post("/api/ledgerAccountStore", payload);
      flashSuccess(data.message || "تم إضافة الحساب بنجاح");
    } else {
      const { data } = await axios.post("/api/ledgerAccountUpdate", {
        id: editingAccount.value.id,
        ...payload,
      });
      flashSuccess(data.message || "تم تحديث الحساب بنجاح");
    }
    showAccountModal.value = false;
    editingAccount.value = null;
    await loadTree();
  } catch (err) {
    const errors = err?.response?.data?.errors || {};
    errorMsg.value =
      err?.response?.data?.message ||
      errors.code?.[0] ||
      errors.name_ar?.[0] ||
      Object.values(errors)[0]?.[0] ||
      "تعذر حفظ الحساب";
  } finally {
    savingAccount.value = false;
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

async function loadReceiptsVault() {
  try {
    const { data } = await axios.get("/api/ledgerReceiptsVault");
    receiptsVaultOptions.value = data.vaults || [];
    receiptsVaultId.value = data.default_receipts_vault_id
      ? String(data.default_receipts_vault_id)
      : "";
    receiptsVaultLabel.value = data.vault?.name || "";
  } catch (e) {
    // Non-fatal — payment binding UI is admin convenience.
    console.warn("ledgerReceiptsVault", e);
  }
}

async function saveReceiptsVault() {
  if (!receiptsVaultId.value) return;
  receiptsVaultSaving.value = true;
  errorMsg.value = "";
  try {
    const { data } = await axios.post("/api/ledgerReceiptsVault", {
      default_receipts_vault_id: Number(receiptsVaultId.value),
    });
    receiptsVaultLabel.value = data.vault?.name || "";
    flashSuccess(data.message || "تم حفظ قاصة استلام الدفعات");
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || "تعذر حفظ قاصة استلام الدفعات";
  } finally {
    receiptsVaultSaving.value = false;
  }
}

async function loadPurchasesVault() {
  try {
    const { data } = await axios.get("/api/ledgerPurchasesVault");
    purchasesVaultOptions.value = data.vaults || [];
    purchasesVaultId.value = data.default_purchases_vault_id
      ? String(data.default_purchases_vault_id)
      : "";
    purchasesVaultLabel.value = data.vault?.name || "";
  } catch (e) {
    console.warn("ledgerPurchasesVault", e);
  }
}

async function savePurchasesVault() {
  if (!purchasesVaultId.value) return;
  purchasesVaultSaving.value = true;
  errorMsg.value = "";
  try {
    const { data } = await axios.post("/api/ledgerPurchasesVault", {
      default_purchases_vault_id: Number(purchasesVaultId.value),
    });
    purchasesVaultLabel.value = data.vault?.name || "";
    flashSuccess(data.message || "تم حفظ قاصة صرف المشتريات");
  } catch (e) {
    errorMsg.value = e?.response?.data?.message || "تعذر حفظ قاصة صرف المشتريات";
  } finally {
    purchasesVaultSaving.value = false;
  }
}

async function refresh() {
  if (tab.value === "tree") await loadTree();
  else if (tab.value === "trial") await loadTrial();
  else if (tab.value === "ledger") {
    await ensureAccountOptions();
    await loadAccountLedger();
  } else if (tab.value === "journals") await loadJournals();
  else if (tab.value === "transfer") await loadTransferAccounts();
  else if (tab.value === "receipts") await loadReceiptsVault();
  else if (tab.value === "purchases") await loadPurchasesVault();
  else if (tab.value === "profits") {
    await loadProfitsSummary();
    await loadTraderRows();
  }
}

watch(currency, () => {
  syncQuery();
  refresh();
});
watch([from, to], () => {
  if (tab.value === "trial" || tab.value === "ledger") refresh();
});
watch(tab, () => {
  showAccountModal.value = false;
  editingAccount.value = null;
  syncQuery();
  refresh();
});
watch(selectedAccountId, (id, prev) => {
  syncQuery();
  if (tab.value === "ledger" && id && id !== prev) {
    loadAccountLedger();
  }
});
watch(profitsCurrency, () => loadProfitsSummary());
watch([() => postForm.value.period_from, () => postForm.value.period_to, () => postForm.value.currency], () => {
  if (tab.value === "profits") loadTraderRows();
});

onMounted(() => {
  readQueryState();
  // currency from query (optional)
  try {
    const c = new URLSearchParams(window.location.search).get("currency");
    if (c === "IQD" || c === "$" || c === "both") currency.value = c;
  } catch {
    /* ignore */
  }
  refresh();
  if (isAdmin.value) {
    loadReceiptsVault();
    loadPurchasesVault();
  }
});
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
                <p class="text-sm text-slate-500 dark:text-slate-400">
                  تقارير محاسبة (شجرة · ميزان · كشف · يومية) · نقد وإعداد (تحويل · أرباح · قاصة استلام · قاصة مشتريات)
                </p>
              </div>
              <button
                type="button"
                class="rounded-lg bg-orange-500 px-4 py-2 text-sm font-semibold text-white hover:bg-orange-600"
                @click="refresh"
              >
                تحديث
              </button>
            </div>

            <!-- مجموعات التبويب: تقارير vs نقد/إعداد -->
            <div class="mt-4 flex flex-col gap-3">
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-300">تقارير</span>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold" :class="tabBtnClass('tree')" @click="setTab('tree')">
                  شجرة الحسابات
                </button>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold" :class="tabBtnClass('trial')" @click="setTab('trial')">
                  ميزان المراجعة
                </button>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold" :class="tabBtnClass('ledger')" @click="setTab('ledger')">
                  كشف حساب
                </button>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold" :class="tabBtnClass('journals')" @click="setTab('journals')">
                  اليومية
                </button>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-bold uppercase tracking-wide text-sky-600 dark:text-sky-300">نقد / إعداد</span>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold" :class="tabBtnClass('transfer')" @click="setTab('transfer')">
                  تحويل نقدي
                </button>
                <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold" :class="tabBtnClass('profits')" @click="setTab('profits')">
                  أرباح التجار
                </button>
                <button
                  v-if="isAdmin"
                  type="button"
                  class="rounded-lg px-3 py-1.5 text-sm font-semibold"
                  :class="tabBtnClass('receipts')"
                  @click="setTab('receipts')"
                >
                  قاصة استلام الدفعات
                </button>
                <button
                  v-if="isAdmin"
                  type="button"
                  class="rounded-lg px-3 py-1.5 text-sm font-semibold"
                  :class="tabBtnClass('purchases')"
                  @click="setTab('purchases')"
                >
                  قاصة صرف المشتريات
                </button>
              </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-5">
              <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-200">العملة</label>
                <select v-model="currency" class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                  <option value="$">USD</option>
                  <option value="IQD">IQD</option>
                  <option value="both">المزدوج</option>
                </select>
              </div>
              <div v-if="tab === 'trial' || tab === 'ledger'">
                <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-200">من</label>
                <input v-model="from" type="date" class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-white" />
              </div>
              <div v-if="tab === 'trial' || tab === 'ledger'">
                <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-200">إلى</label>
                <input v-model="to" type="date" class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-white" />
              </div>
              <div class="md:col-span-2" v-if="tab === 'trial' || tab === 'tree'">
                <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-200">بحث في الحسابات</label>
                <input
                  v-model="q"
                  type="text"
                  placeholder="رمز أو اسم الحساب — Enter للبحث"
                  class="w-full rounded-lg border-slate-300 bg-white text-slate-900 placeholder-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-white dark:placeholder-slate-400"
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

            <!-- شجرة الحسابات — مجموعات قابلة للطي + شبكة مضغوطة -->
            <template v-else-if="tab === 'tree'">
              <div class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <div class="flex flex-wrap items-center gap-2">
                    <button
                      type="button"
                      class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                      @click="expandAllGroups"
                    >
                      توسيع الكل
                    </button>
                    <button
                      type="button"
                      class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                      @click="collapseAllGroups"
                    >
                      طي الكل
                    </button>
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                      {{ treeAccountCount }} حساب
                    </span>
                  </div>
                  <button
                    type="button"
                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-bold text-white hover:bg-emerald-700"
                    @click="openCreateAccount"
                  >
                    + إضافة حساب
                  </button>
                </div>

                <div
                  v-for="group in treeGroups"
                  :key="group.type"
                  class="rounded-lg border border-slate-200 dark:border-slate-700"
                  :class="`border-r-4 ${groupAccent(group.type)}`"
                >
                  <button
                    type="button"
                    class="sticky top-0 z-10 flex w-full items-center justify-between gap-3 border-b border-slate-200 bg-slate-100/95 px-3 py-2 backdrop-blur-sm dark:border-slate-700 dark:bg-slate-800/95"
                    :aria-expanded="!isGroupCollapsed(group.type)"
                    @click="toggleGroup(group.type)"
                  >
                    <div class="flex min-w-0 items-center gap-2 text-right">
                      <span
                        class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded text-slate-500 transition-transform dark:text-slate-300"
                        :class="isGroupCollapsed(group.type) ? '-rotate-90' : ''"
                        aria-hidden="true"
                      >▼</span>
                      <span class="truncate text-sm font-bold text-slate-900 dark:text-white">{{ group.label }}</span>
                      <span class="shrink-0 rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                        {{ group.accounts?.length || 0 }}
                      </span>
                    </div>
                    <span class="shrink-0 font-mono text-xs font-semibold text-slate-700 dark:text-slate-100">
                      {{ formatGroupTotal(group) }}
                    </span>
                  </button>

                  <div
                    v-show="!isGroupCollapsed(group.type)"
                    class="grid grid-cols-1 gap-px bg-slate-100 p-px dark:bg-slate-800 md:grid-cols-2 xl:grid-cols-3"
                  >
                    <div
                      v-for="acc in group.accounts"
                      :key="acc.id"
                      class="group flex cursor-pointer items-center gap-2 bg-white px-2.5 py-1.5 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/80"
                      :style="{ paddingRight: `${0.625 + (acc.depth || 0) * 0.75}rem` }"
                      @click="openAccount(acc.id)"
                    >
                      <div class="min-w-0 flex-1 text-right">
                        <div class="flex items-center justify-end gap-1 truncate text-[13px] font-semibold leading-tight text-slate-900 dark:text-slate-100">
                          <span v-if="acc.depth" class="text-slate-400">↳</span>
                          <span class="truncate">{{ acc.name }}</span>
                          <span
                            v-if="acc.is_system"
                            class="shrink-0 rounded bg-slate-200 px-1 py-px text-[9px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                          >نظامي</span>
                        </div>
                        <div class="font-mono text-[10px] leading-tight text-slate-500 dark:text-slate-400">
                          {{ acc.code }}
                          <span v-if="acc.currency">· {{ acc.currency === '$' ? 'USD' : acc.currency }}</span>
                          <span v-else-if="currency === 'both'">· مزدوج</span>
                        </div>
                      </div>
                      <div class="flex shrink-0 items-center gap-0.5">
                        <span class="font-mono text-xs font-bold tabular-nums text-slate-800 dark:text-white">
                          {{ accountRowBalanceLabel(acc) }}
                        </span>
                        <button
                          type="button"
                          class="rounded px-1.5 py-0.5 text-[10px] font-semibold text-indigo-600 opacity-70 hover:bg-indigo-50 hover:opacity-100 group-hover:opacity-100 dark:text-indigo-400 dark:hover:bg-indigo-950/40"
                          title="تعديل الحساب"
                          @click="startEdit(acc, $event)"
                        >
                          تعديل
                        </button>
                        <button
                          v-if="!acc.is_system"
                          type="button"
                          class="rounded px-1.5 py-0.5 text-[10px] font-semibold text-rose-600 opacity-70 hover:bg-rose-50 hover:opacity-100 disabled:opacity-50 group-hover:opacity-100 dark:text-rose-400 dark:hover:bg-rose-950/40"
                          title="إيقاف الحساب"
                          :disabled="deactivatingId === acc.id"
                          @click="deactivateAccount(acc, $event)"
                        >
                          إيقاف
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <div v-if="!treeGroups.length" class="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center dark:border-slate-600">
                  <p class="font-semibold text-slate-700 dark:text-slate-200">لا توجد حسابات في شجرة الدليل</p>
                  <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    شجرة الحسابات (COA) فقط — أضف حساباً أو غيّر كلمة البحث. اضغط على أي صف لفتح كشف الحساب.
                  </p>
                </div>
                <p class="text-center text-[11px] text-slate-500 dark:text-slate-400">
                  دليل حسابات محاسبي · إضافة / تعديل / إيقاف · اضغط الصف لفتح كشف الحساب · الحسابات النظامية مقفلة
                </p>
              </div>

              <ModalLedgerAccount
                :show="showAccountModal"
                :mode="accountModalMode"
                :account="editingAccount"
                :parent-options="parentOptions"
                :submitting="savingAccount"
                @close="closeAccountModal"
                @submit="submitAccountModal"
              />
            </template>

            <template v-else-if="tab === 'trial'">
              <div class="mb-3 flex flex-wrap items-center gap-4 text-sm font-semibold">
                <span class="text-slate-700 dark:text-slate-100">إجمالي المدين: {{ formatMoney(totalDebit) }} {{ reportCurrencyLabel }}</span>
                <span class="text-slate-700 dark:text-slate-100">إجمالي الدائن: {{ formatMoney(totalCredit) }} {{ reportCurrencyLabel }}</span>
                <span
                  class="rounded px-2 py-0.5 text-xs font-bold"
                  :class="trialBalanced ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'"
                >
                  {{ trialBalanced ? 'متوازن' : 'غير متوازن' }}
                </span>
              </div>
              <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                ميزان المراجعة من قيود اليومية ضمن الفترة — اضغط الصف أو «حركة» لفتح كشف الحساب.
              </p>
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
                      class="cursor-pointer border-t border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-800/80"
                      @click="openAccount(row.id)"
                    >
                      <td class="px-3 py-2 font-mono">{{ row.code }}</td>
                      <td class="px-3 py-2 font-semibold">{{ row.name }}</td>
                      <td class="px-3 py-2">{{ typeLabel(row.type) }}</td>
                      <td class="px-3 py-2">{{ formatMoney(row.debit) }}</td>
                      <td class="px-3 py-2">{{ formatMoney(row.credit) }}</td>
                      <td class="px-3 py-2 font-bold">{{ formatMoney(row.balance) }}</td>
                      <td class="px-3 py-2">
                        <button
                          type="button"
                          class="rounded bg-emerald-600 px-3 py-1 text-xs font-semibold text-white"
                          @click.stop="openAccount(row.id)"
                        >
                          حركة
                        </button>
                      </td>
                    </tr>
                    <tr v-if="!trialRows.length">
                      <td colspan="7" class="px-3 py-10 text-slate-500 dark:text-slate-400">
                        <p class="font-semibold text-slate-700 dark:text-slate-200">لا توجد حركات ضمن الفلتر</p>
                        <p class="mt-1 text-sm">غيّر الفترة أو العملة أو البحث — الميزان يعرض فقط الحسابات التي لها قيود في الفترة.</p>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>

            <template v-else-if="tab === 'ledger'">
              <nav class="mb-3 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                <button type="button" class="font-semibold text-emerald-600 hover:underline dark:text-emerald-300" @click="setTab('tree')">
                  شجرة الحسابات
                </button>
                <span aria-hidden="true">/</span>
                <button
                  v-if="previousTab === 'trial'"
                  type="button"
                  class="font-semibold text-emerald-600 hover:underline dark:text-emerald-300"
                  @click="setTab('trial')"
                >
                  ميزان المراجعة
                </button>
                <span v-if="previousTab === 'trial'" aria-hidden="true">/</span>
                <span class="font-semibold text-slate-800 dark:text-slate-100">كشف حساب</span>
                <button
                  type="button"
                  class="mr-auto rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                  @click="goBackFromLedger"
                >
                  ← رجوع
                </button>
              </nav>

              <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="md:col-span-2">
                  <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-200">اختيار الحساب</label>
                  <select
                    :value="selectedAccountId || ''"
                    class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                    @change="selectedAccountId = Number($event.target.value) || null"
                  >
                    <option value="" disabled>اختر حساباً من دليل الحسابات…</option>
                    <option
                      v-for="acc in filteredLedgerAccounts"
                      :key="acc.id"
                      :value="acc.id"
                    >
                      {{ acc.code }} — {{ acc.name }} ({{ acc.type_label }})
                    </option>
                  </select>
                </div>
                <div>
                  <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-200">تصفية القائمة</label>
                  <input
                    v-model="ledgerAccountQ"
                    type="text"
                    placeholder="رمز أو اسم…"
                    class="w-full rounded-lg border-slate-300 bg-white text-slate-900 placeholder-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-white dark:placeholder-slate-400"
                  />
                </div>
              </div>

              <div
                v-if="accountMeta"
                class="mb-3 rounded-lg border border-slate-600 bg-slate-900 p-4 text-slate-100"
              >
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <div class="font-mono text-xs text-slate-300">{{ accountMeta.code }}</div>
                    <div class="text-lg font-bold text-white">{{ accountMeta.name }}</div>
                    <div class="mt-1 text-sm text-slate-300">
                      النوع: {{ typeLabel(accountMeta.type) }} · الفترة: {{ from }} → {{ to }}
                    </div>
                  </div>
                  <div class="text-left">
                    <div class="text-xs text-slate-400">الرصيد الختامي</div>
                    <div class="font-mono text-xl font-bold text-emerald-300">
                      {{ formatMoney(closingBalance) }} {{ reportCurrencyLabel }}
                    </div>
                    <div class="mt-1 text-xs text-slate-400">
                      افتتاحي: {{ formatMoney(openingBalance) }} {{ reportCurrencyLabel }}
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="!selectedAccountId" class="rounded-lg border border-dashed border-slate-300 px-4 py-12 text-center dark:border-slate-600">
                <p class="font-semibold text-slate-700 dark:text-slate-200">لم يُختر حساب بعد</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                  اختر حساباً من القائمة أعلاه، أو من شجرة الحسابات / ميزان المراجعة بنقرة واحدة.
                </p>
              </div>

              <div v-else class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
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
                      class="border-t border-slate-200 dark:border-slate-700 dark:text-slate-100"
                    >
                      <td class="px-3 py-2">{{ row.date }}</td>
                      <td class="px-3 py-2 font-mono text-xs">{{ row.voucher_no }}</td>
                      <td class="px-3 py-2 text-right">{{ row.memo }}</td>
                      <td class="px-3 py-2">{{ formatMoney(row.debit) }}</td>
                      <td class="px-3 py-2">{{ formatMoney(row.credit) }}</td>
                      <td class="px-3 py-2 font-bold">{{ formatMoney(row.balance) }}</td>
                    </tr>
                    <tr v-if="!ledgerRows.length">
                      <td colspan="6" class="px-3 py-10 text-slate-500 dark:text-slate-400">
                        <p class="font-semibold text-slate-700 dark:text-slate-200">لا توجد قيود لهذا الحساب في الفترة</p>
                        <p class="mt-1 text-sm">جرّب توسيع الفترة أو تأكد من العملة — الرصيد الافتتاحي يظهر أعلاه إن وُجد.</p>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </template>

            <template v-else-if="tab === 'journals'">
              <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                آخر قيود اليومية (مدين/دائن) من سجل القيود المحاسبية — ليست تحويلات نقدية مباشرة.
              </p>
              <div class="space-y-3">
                <div
                  v-for="entry in journals"
                  :key="entry.id"
                  class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                >
                  <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <div class="font-bold text-slate-900 dark:text-white">{{ entry.voucher_no }}</div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">{{ entry.entry_date }} · {{ entry.source }}</div>
                  </div>
                  <div class="mb-2 text-sm text-slate-600 dark:text-slate-300">{{ entry.memo }}</div>
                  <table class="w-full text-center text-xs">
                    <thead>
                      <tr class="text-slate-500 dark:text-slate-400">
                        <th class="py-1">الحساب</th>
                        <th class="py-1">مدين</th>
                        <th class="py-1">دائن</th>
                        <th class="py-1">عملة</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="(line, idx) in entry.lines"
                        :key="idx"
                        class="border-t border-slate-100 dark:border-slate-800 dark:text-slate-100"
                      >
                        <td class="py-1">{{ line.code }} — {{ line.account }}</td>
                        <td class="py-1">{{ formatMoney(line.debit) }}</td>
                        <td class="py-1">{{ formatMoney(line.credit) }}</td>
                        <td class="py-1">{{ line.currency }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div
                  v-if="!journals.length"
                  class="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center dark:border-slate-600"
                >
                  <p class="font-semibold text-slate-700 dark:text-slate-200">لا توجد قيود بعد</p>
                  <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    تظهر هنا القيود بعد عمليات الدفع والتحويل والمصاريف وغيرها.
                  </p>
                </div>
              </div>
            </template>

            <!-- تحويل نقدي بين القاصات فقط -->
            <template v-else-if="tab === 'transfer'">
              <div class="mx-auto max-w-3xl space-y-6">
                <div class="rounded-xl border border-sky-700/40 bg-slate-900 p-4 text-slate-100">
                  <h2 class="mb-2 text-base font-bold text-white">تحويل نقدي بين القاصات</h2>
                  <p class="text-sm text-slate-300">
                    لنقل نقد بين قاصات نقدية فقط (صندوق / بنك / خزنة).
                    <span class="font-semibold text-amber-300">ليس لتسجيل مصروف أو إيراد</span>
                    — المصاريف من شاشة القاصات/المصروف، والتقارير من تبويبات «تقارير».
                  </p>
                </div>

                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                  <form class="grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submitTransfer">
                    <div>
                      <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-200">من قاصة نقدية</label>
                      <select
                        v-model="transferForm.from_user_id"
                        class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                      >
                        <option value="" disabled>اختر القاصة المرسلة</option>
                        <option v-for="acc in transferAccounts" :key="acc.id" :value="acc.id">
                          {{ acc.name }}
                        </option>
                      </select>
                      <p v-if="transferForm.from_user_id" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        الرصيد: {{ accountBalanceLabel(transferForm.from_user_id, transferForm.currency) }} {{ transferForm.currency === '$' ? 'USD' : 'IQD' }}
                      </p>
                    </div>
                    <div>
                      <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-200">إلى قاصة نقدية</label>
                      <select
                        v-model="transferForm.to_user_id"
                        class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                      >
                        <option value="" disabled>اختر القاصة المستقبلة</option>
                        <option v-for="acc in transferAccounts" :key="acc.id" :value="acc.id">
                          {{ acc.name }}
                        </option>
                      </select>
                      <p v-if="transferForm.to_user_id" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        الرصيد: {{ accountBalanceLabel(transferForm.to_user_id, transferForm.currency) }} {{ transferForm.currency === '$' ? 'USD' : 'IQD' }}
                      </p>
                    </div>
                    <div>
                      <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-200">المبلغ</label>
                      <input
                        v-model="transferForm.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                        placeholder="0.00"
                      />
                    </div>
                    <div>
                      <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-200">العملة</label>
                      <select
                        v-model="transferForm.currency"
                        class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                      >
                        <option value="$">USD</option>
                        <option value="IQD">IQD</option>
                      </select>
                    </div>
                    <div>
                      <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-200">التاريخ</label>
                      <input
                        v-model="transferForm.entry_date"
                        type="date"
                        class="w-full rounded-lg border-slate-300 bg-white text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                      />
                    </div>
                    <div class="md:col-span-2">
                      <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-200">ملاحظات</label>
                      <input
                        v-model="transferForm.notes"
                        type="text"
                        class="w-full rounded-lg border-slate-300 bg-white text-slate-900 placeholder-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                        placeholder="سبب التحويل (اختياري)"
                      />
                    </div>
                    <div class="md:col-span-2">
                      <button
                        type="submit"
                        class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="transferSubmitting"
                      >
                        {{ transferSubmitting ? "جاري التنفيذ..." : "تنفيذ التحويل" }}
                      </button>
                    </div>
                  </form>
                </div>

                <div class="rounded-xl border border-slate-200 dark:border-slate-700">
                  <div class="border-b border-slate-200 bg-slate-100 px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                    <span class="text-sm font-bold text-slate-900 dark:text-white">أرصدة القاصات النقدية الحالية</span>
                  </div>
                  <div v-if="transferLoading" class="py-6 text-center text-slate-500">جاري التحميل...</div>
                  <table v-else class="w-full text-center text-sm">
                    <thead class="bg-slate-50 text-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
                      <tr>
                        <th class="px-3 py-2">القاصة</th>
                        <th class="px-3 py-2">USD</th>
                        <th class="px-3 py-2">IQD</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="acc in transferAccounts" :key="acc.id" class="border-t border-slate-100 dark:border-slate-800 dark:text-slate-100">
                        <td class="px-3 py-2 text-right font-semibold">{{ acc.name }}</td>
                        <td class="px-3 py-2 font-mono">{{ formatMoney(acc.balance) }}</td>
                        <td class="px-3 py-2 font-mono">{{ formatMoneyAmount(acc.balance_dinar, "IQD") }}</td>
                      </tr>
                      <tr v-if="!transferAccounts.length">
                        <td colspan="3" class="px-3 py-10 text-slate-500 dark:text-slate-400">
                          <p class="font-semibold text-slate-700 dark:text-slate-200">لا توجد قاصات نقدية</p>
                          <p class="mt-1 text-sm">أضف قاصة نقد/بنك/خزنة من شاشة القاصات أولاً.</p>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </template>

            <!-- أرباح التجار -->
            <template v-else-if="tab === 'profits'">
              <div class="mx-auto max-w-4xl space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-emerald-50 p-4 dark:border-slate-700 dark:bg-emerald-950/30">
                  <div>
                    <div class="text-sm text-slate-600 dark:text-slate-300">رصيد حساب أرباح التجار الحالي</div>
                    <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">
                      {{ formatMoney(profitsBalance) }} {{ profitsCurrency === '$' ? 'USD' : 'IQD' }}
                    </div>
                  </div>
                  <select
                    v-model="profitsCurrency"
                    class="rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                  >
                    <option value="$">USD</option>
                    <option value="IQD">IQD</option>
                  </select>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                  <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <h2 class="mb-3 text-base font-bold text-slate-900 dark:text-white">ترحيل أرباح تاجر</h2>

                    <div class="mb-3 grid grid-cols-2 gap-2">
                      <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">من تاريخ</label>
                        <input v-model="postForm.period_from" type="date" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white" />
                      </div>
                      <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">إلى تاريخ</label>
                        <input v-model="postForm.period_to" type="date" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white" />
                      </div>
                    </div>

                    <div class="mb-3 max-h-40 overflow-y-auto rounded-lg border border-slate-100 dark:border-slate-800">
                      <table class="w-full text-center text-xs">
                        <thead class="sticky top-0 bg-slate-50 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                          <tr>
                            <th class="px-2 py-1">التاجر</th>
                            <th class="px-2 py-1">الربح المحسوب</th>
                            <th class="px-2 py-1"></th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="row in traderRows" :key="row.client_id" class="border-t border-slate-100 dark:border-slate-800 dark:text-slate-200">
                            <td class="px-2 py-1 text-right">{{ row.trader }}</td>
                            <td class="px-2 py-1 font-mono" :class="row.profit >= 0 ? 'text-emerald-600' : 'text-rose-600'">{{ formatMoney(row.profit) }}</td>
                            <td class="px-2 py-1">
                              <button type="button" class="rounded bg-indigo-600 px-2 py-0.5 text-white" @click="pickTraderRow(row)">استخدام</button>
                            </td>
                          </tr>
                          <tr v-if="!traderRows.length && !traderRowsLoading">
                            <td colspan="3" class="px-2 py-3 text-slate-500">لا توجد أرباح محسوبة لهذه الفترة</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>

                    <form class="space-y-3" @submit.prevent="submitPostProfit">
                      <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">التاجر المختار (client_id)</label>
                        <input
                          v-model="postForm.client_id"
                          type="number"
                          class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                          placeholder="اختر تاجراً من الجدول أعلاه"
                        />
                      </div>
                      <div class="grid grid-cols-2 gap-2">
                        <div>
                          <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">المبلغ</label>
                          <input v-model="postForm.amount" type="number" min="0.01" step="0.01" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-950 dark:text-white" />
                        </div>
                        <div>
                          <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">العملة</label>
                          <select v-model="postForm.currency" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                            <option value="$">USD</option>
                            <option value="IQD">IQD</option>
                          </select>
                        </div>
                      </div>
                      <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">ملاحظات</label>
                        <input v-model="postForm.notes" type="text" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-950 dark:text-white" placeholder="اختياري" />
                      </div>
                      <button
                        type="submit"
                        class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50"
                        :disabled="postSubmitting"
                      >
                        {{ postSubmitting ? "جاري الترحيل..." : "ترحيل الأرباح" }}
                      </button>
                    </form>
                  </div>

                  <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <h2 class="mb-3 text-base font-bold text-slate-900 dark:text-white">سحب من حساب الأرباح</h2>
                    <form class="space-y-3" @submit.prevent="submitWithdrawProfit">
                      <div class="grid grid-cols-2 gap-2">
                        <div>
                          <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">المبلغ</label>
                          <input v-model="withdrawForm.amount" type="number" min="0.01" step="0.01" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-950 dark:text-white" />
                        </div>
                        <div>
                          <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">العملة</label>
                          <select v-model="withdrawForm.currency" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                            <option value="$">USD</option>
                            <option value="IQD">IQD</option>
                          </select>
                        </div>
                      </div>
                      <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">التاريخ</label>
                        <input v-model="withdrawForm.entry_date" type="date" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-950 dark:text-white" />
                      </div>
                      <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">ملاحظات</label>
                        <input v-model="withdrawForm.notes" type="text" class="w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-950 dark:text-white" placeholder="اختياري" />
                      </div>
                      <button
                        type="submit"
                        class="w-full rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50"
                        :disabled="withdrawSubmitting"
                      >
                        {{ withdrawSubmitting ? "جاري السحب..." : "سحب من الأرباح" }}
                      </button>
                    </form>

                    <p class="mt-3 text-xs text-slate-500">
                      السحب يخفض رصيد حساب الأرباح ويخفض صندوق النقد بنفس العملة (قيد: مدين حساب الأرباح / دائن الصندوق).
                    </p>
                  </div>
                </div>

                <div class="rounded-xl border border-slate-200 dark:border-slate-700">
                  <div class="border-b border-slate-200 bg-slate-100 px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
                    <span class="text-sm font-bold text-slate-900 dark:text-white">آخر حركات حساب الأرباح</span>
                  </div>
                  <div v-if="profitsLoading" class="py-6 text-center text-slate-500">جاري التحميل...</div>
                  <table v-else class="w-full text-center text-sm">
                    <thead class="bg-slate-50 text-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
                      <tr>
                        <th class="px-3 py-2">التاريخ</th>
                        <th class="px-3 py-2">النوع</th>
                        <th class="px-3 py-2">التاجر</th>
                        <th class="px-3 py-2">الفترة</th>
                        <th class="px-3 py-2">المبلغ</th>
                        <th class="px-3 py-2">ملاحظات</th>
                        <th class="px-3 py-2"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="entry in profitsEntries" :key="entry.id" class="border-t border-slate-100 dark:border-slate-800 dark:text-slate-200">
                        <td class="px-3 py-2 text-xs">{{ entry.created_at }}</td>
                        <td class="px-3 py-2">
                          <span
                            class="rounded px-2 py-0.5 text-xs font-semibold"
                            :class="entry.type === 'post' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'"
                          >
                            {{ entry.type === 'post' ? 'ترحيل' : 'سحب' }}
                          </span>
                        </td>
                        <td class="px-3 py-2">{{ entry.trader || '—' }}</td>
                        <td class="px-3 py-2 text-xs">{{ entry.period_from ? `${entry.period_from} → ${entry.period_to}` : '—' }}</td>
                        <td class="px-3 py-2 font-mono font-bold">{{ formatMoney(entry.amount) }} {{ entry.currency === '$' ? 'USD' : 'IQD' }}</td>
                        <td class="px-3 py-2 text-right text-xs text-slate-500">{{ entry.memo }}</td>
                        <td class="px-3 py-2">
                          <button type="button" class="rounded px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40" @click="deleteProfitEntry(entry)">
                            حذف
                          </button>
                        </td>
                      </tr>
                      <tr v-if="!profitsEntries.length">
                        <td colspan="7" class="px-3 py-8 text-slate-500">لا توجد حركات بعد</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </template>

            <!-- قاصة استلام الدفعات — نقد فقط / إعداد -->
            <template v-else-if="tab === 'receipts'">
              <div class="mx-auto max-w-2xl space-y-4">
                <div class="rounded-xl border border-sky-700/40 bg-slate-900 p-4 text-slate-100">
                  <h2 class="text-base font-bold text-white">قاصة استلام دفعات الزبائن</h2>
                  <p class="mt-2 text-sm text-slate-300">
                    إعداد تشغيلي: كل دفعات التجار/السيارات تُرحَّل نقداً إلى قاصة نقدية (صندوق/بنك/خزنة).
                    <span class="font-semibold text-amber-300">ليس تقريراً محاسبياً</span>
                    ولا علاقة له بشجرة الحسابات أو ميزان المراجعة.
                  </p>
                </div>

                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                  <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-200">
                    القاصة النقدية لاستلام الدفعات
                  </label>
                  <select
                    v-model="receiptsVaultId"
                    class="w-full rounded-lg border border-slate-300 bg-white text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                  >
                    <option
                      v-for="v in receiptsVaultOptions"
                      :key="v.id"
                      :value="String(v.id)"
                    >
                      {{ v.name }}{{ v.is_main_box ? ' (الصندوق)' : '' }}
                    </option>
                  </select>
                  <p v-if="receiptsVaultLabel" class="mt-2 text-xs text-emerald-600 dark:text-emerald-300">
                    الحالي: {{ receiptsVaultLabel }}
                  </p>
                  <button
                    type="button"
                    class="mt-4 w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                    :disabled="receiptsVaultSaving || !receiptsVaultId"
                    @click="saveReceiptsVault"
                  >
                    {{ receiptsVaultSaving ? 'جاري الحفظ...' : 'حفظ الربط' }}
                  </button>
                </div>

                <div
                  v-if="!receiptsVaultOptions.length"
                  class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center dark:border-slate-600"
                >
                  <p class="font-semibold text-slate-700 dark:text-slate-200">لا توجد قاصات نقدية</p>
                  <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">أضف قاصة نقد/بنك من شاشة القاصات أولاً.</p>
                </div>
              </div>
            </template>

            <!-- قاصة صرف المشتريات — نقد فقط / إعداد -->
            <template v-else-if="tab === 'purchases'">
              <div class="mx-auto max-w-2xl space-y-4">
                <div class="rounded-xl border border-amber-700/40 bg-slate-900 p-4 text-slate-100">
                  <h2 class="text-base font-bold text-white">قاصة صرف المشتريات</h2>
                  <p class="mt-2 text-sm text-slate-300">
                    إعداد تشغيلي: تكلفة شراء السيارات ومصاريف الشراء تُخصم من قاصة نقدية (صندوق/بنك/خزنة).
                    <span class="font-semibold text-amber-300">منفصلة عن قاصة استلام دفعات الزبائن</span>
                    — وليست تقريراً محاسبياً.
                  </p>
                </div>

                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                  <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-200">
                    القاصة النقدية لصرف تكلفة المشتريات
                  </label>
                  <select
                    v-model="purchasesVaultId"
                    class="w-full rounded-lg border border-slate-300 bg-white text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                  >
                    <option
                      v-for="v in purchasesVaultOptions"
                      :key="v.id"
                      :value="String(v.id)"
                    >
                      {{ v.name }}{{ v.is_main_box ? ' (الصندوق)' : '' }}
                    </option>
                  </select>
                  <p v-if="purchasesVaultLabel" class="mt-2 text-xs text-emerald-600 dark:text-emerald-300">
                    الحالي: {{ purchasesVaultLabel }}
                  </p>
                  <button
                    type="button"
                    class="mt-4 w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                    :disabled="purchasesVaultSaving || !purchasesVaultId"
                    @click="savePurchasesVault"
                  >
                    {{ purchasesVaultSaving ? 'جاري الحفظ...' : 'حفظ الربط' }}
                  </button>
                </div>

                <div
                  v-if="!purchasesVaultOptions.length"
                  class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center dark:border-slate-600"
                >
                  <p class="font-semibold text-slate-700 dark:text-slate-200">لا توجد قاصات نقدية</p>
                  <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">أضف قاصة نقد/بنك من شاشة القاصات أولاً.</p>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
