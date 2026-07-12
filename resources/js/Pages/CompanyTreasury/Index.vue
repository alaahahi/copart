<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ModalDel from "@/Components/ModalDel.vue";
import Modal from "@/Components/Modal.vue";
import { Head } from "@inertiajs/inertia-vue3";
import { ref, computed, watch, onMounted } from "vue";
import axios from "axios";
import InfiniteLoading from "v3-infinite-loading";
import "v3-infinite-loading/lib/style.css";

const PAGE_SIZE = 100;

const currency = ref("$");
const entries = ref([]);
const balanceUsd = ref(0);
const balanceIqd = ref(0);
const totalDebit = ref(0);
const totalCredit = ref(0);
const periodBalance = ref(0);
const totalEntries = ref(0);
const loading = ref(false);
const loadingMore = ref(false);
const saving = ref(false);
const errorMsg = ref("");
const successMsg = ref("");
const showModalDel = ref(false);
const entryToDelete = ref(null);
const showEditModal = ref(false);
const entryToEdit = ref(null);
const editError = ref("");
const editing = ref(false);
const showEntryPanel = ref(true);
const showFilterPanel = ref(false);
const showTrashPanel = ref(false);
const trashEntries = ref([]);
const loadingTrash = ref(false);
const restoringId = ref(null);
const togglingIds = ref([]);
const infiniteKey = ref(0);
let page = 1;
let listToken = 0;

const from = ref(getFirstDayOfMonth());
const to = ref(getTodayDate());

const form = ref({
  entry_type: "deposit",
  amount: "",
  entry_date: getTodayDate(),
  description: "",
  tag: "",
});

const editForm = ref({
  entry_type: "deposit",
  amount: "",
  entry_date: getTodayDate(),
  description: "",
  tag: "",
});

const tagOptions = ref([]);
const filterTag = ref("");
const showTagsPanel = ref(false);
const newTagName = ref("");
const savingTag = ref(false);

const currencyLabel = computed(() =>
  currency.value === "$" ? "USD" : "IQD"
);

const currencySymbol = computed(() =>
  currency.value === "$" ? "$" : " IQD"
);

function getTodayDate() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function getFirstDayOfMonth() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-01`;
}

function getDateMonthsAgo(months) {
  const d = new Date();
  d.setMonth(d.getMonth() - months);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function setFilterThisMonth() {
  from.value = getFirstDayOfMonth();
  to.value = getTodayDate();
}

function setFilterThreeMonths() {
  from.value = getDateMonthsAgo(3);
  to.value = getTodayDate();
}

function resetEntries() {
  listToken++;
  page = 1;
  entries.value = [];
  totalEntries.value = 0;
  infiniteKey.value++;
}

function entryTimeOf(row) {
  return row?.entry_time || fmtTimeLite(row?.created_at) || fmtTimeLite(row?.updated_at) || "";
}

function deletedTimeOf(row) {
  return row?.deleted_time || fmtTimeLite(row?.deleted_at) || "";
}

function fmt(n, cur = currency.value) {
  const v = Number(n) || 0;
  return cur === "$"
    ? v.toLocaleString("en-US", { minimumFractionDigits: 0, maximumFractionDigits: 2 })
    : v.toLocaleString("en-US", { maximumFractionDigits: 0 });
}

function fmtCell(n) {
  const v = Number(n) || 0;
  if (v === 0) return "—";
  return fmt(v);
}

function fmtDateLite(value) {
  if (!value) return "—";
  const s = String(value);
  const iso = s.match(/^(\d{4}-\d{2}-\d{2})/);
  if (iso) return iso[1];
  const d = new Date(s);
  if (Number.isNaN(d.getTime())) return s.substring(0, 10);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

function fmtTimeLite(value) {
  if (!value) return "";
  const s = String(value);
  const match = s.match(/(?:T|\s)(\d{2}:\d{2})/);
  return match ? match[1] : "";
}

function flashSuccess(msg) {
  successMsg.value = msg;
  setTimeout(() => { successMsg.value = ""; }, 2800);
}

async function loadTags() {
  try {
    const res = await axios.get("/api/paymentTags");
    tagOptions.value = res.data || [];
  } catch {
    tagOptions.value = [];
  }
}

async function addTag() {
  const name = newTagName.value.trim();
  if (!name) return;
  savingTag.value = true;
  try {
    const res = await axios.post("/api/paymentTags", { name });
    tagOptions.value = [...tagOptions.value, res.data];
    newTagName.value = "";
    flashSuccess("تم إضافة التاغ | Tag added");
  } catch (e) {
    errorMsg.value = e.response?.data?.message || "تعذر إضافة التاغ | Failed to add tag";
  } finally {
    savingTag.value = false;
  }
}

async function deleteTag(tag) {
  if (!confirm(`حذف التاغ "${tag.name}"؟`)) return;
  try {
    await axios.post("/api/deletePaymentTag", { id: tag.id });
    tagOptions.value = tagOptions.value.filter((t) => t.id !== tag.id);
    if (form.value.tag === tag.name) form.value.tag = "";
    if (editForm.value.tag === tag.name) editForm.value.tag = "";
    if (filterTag.value === tag.name) {
      filterTag.value = "";
      resetEntries();
    }
    flashSuccess("تم حذف التاغ | Tag deleted");
  } catch (e) {
    errorMsg.value = e.response?.data?.message || "تعذر حذف التاغ | Failed to delete tag";
  }
}

function printTreasury(entryId = null) {
  const query = new URLSearchParams({
    currency: currency.value,
    from: from.value,
    to: to.value,
  });
  if (filterTag.value) query.set("tag", filterTag.value);
  if (entryId) query.set("entry_id", String(entryId));
  window.open(`/company_treasury/print?${query.toString()}`, "_blank");
}

async function loadSummary() {
  const res = await axios.get("/api/companyTreasurySummary");
  balanceUsd.value = res.data.balance_usd ?? 0;
  balanceIqd.value = res.data.balance_iqd ?? 0;
}

async function loadEntriesPage($state) {
  const token = listToken;
  const requestPage = page;

  if (requestPage === 1) {
    loading.value = true;
    errorMsg.value = "";
  } else {
    loadingMore.value = true;
  }

  try {
    const params = {
      currency: currency.value,
      from: from.value,
      to: to.value,
      page: requestPage,
      limit: PAGE_SIZE,
    };
    if (filterTag.value) params.tag = filterTag.value;

    const res = await axios.get("/api/companyTreasuryEntries", { params });

    if (token !== listToken) {
      $state.complete();
      return;
    }

    const batch = res.data.entries ?? [];
    const lastPage = res.data.pagination?.last_page ?? 1;

    entries.value.push(...batch);
    totalDebit.value = res.data.total_debit ?? 0;
    totalCredit.value = res.data.total_credit ?? 0;
    periodBalance.value = res.data.period_balance ?? 0;
    totalEntries.value = res.data.pagination?.total ?? entries.value.length;

    if (requestPage >= lastPage || batch.length === 0) {
      $state.complete();
    } else {
      page = requestPage + 1;
      $state.loaded();
    }
  } catch (e) {
    if (token === listToken && requestPage === 1) {
      errorMsg.value = e.response?.data?.message || "تعذر تحميل القاصة | Failed to load";
    }
    if (token === listToken) {
      $state.error();
    }
  } finally {
    if (token === listToken) {
      loading.value = false;
      loadingMore.value = false;
    }
  }
}

async function refreshAll() {
  await loadSummary();
  resetEntries();
}

async function submitEntry() {
  if (!form.value.amount || Number(form.value.amount) <= 0) {
    errorMsg.value = "أدخل مبلغاً صحيحاً | Enter a valid amount";
    return;
  }
  saving.value = true;
  errorMsg.value = "";
  const wasDeposit = form.value.entry_type === "deposit";
  try {
    await axios.post("/api/companyTreasuryStore", {
      entry_type: form.value.entry_type,
      amount: form.value.amount,
      currency: currency.value,
      entry_date: form.value.entry_date,
      description: form.value.description,
      tag: form.value.tag || null,
    });
    form.value.amount = "";
    form.value.description = "";
    flashSuccess(
      wasDeposit
        ? "تم الإيداع بنجاح | Deposit saved"
        : "تم السحب بنجاح | Withdrawal saved"
    );
    await refreshAll();
  } catch (e) {
    errorMsg.value = e.response?.data?.message || "تعذر حفظ الحركة | Save failed";
  } finally {
    saving.value = false;
  }
}

function setEntryType(type) {
  form.value.entry_type = type;
}

function openDelete(entry) {
  entryToDelete.value = entry;
  showModalDel.value = true;
}

function openEdit(entry) {
  entryToEdit.value = entry;
  const isDeposit = Number(entry.debit) > 0;
  editForm.value = {
    entry_type: isDeposit ? "deposit" : "withdraw",
    amount: isDeposit ? entry.debit : entry.credit,
    entry_date: entry.entry_date || getTodayDate(),
    description: entry.description ?? "",
    tag: entry.tag ?? "",
  };
  editError.value = "";
  showEditModal.value = true;
}

async function confirmEdit() {
  if (!entryToEdit.value) return;
  if (!editForm.value.amount || Number(editForm.value.amount) <= 0) {
    editError.value = "أدخل مبلغاً صحيحاً | Enter a valid amount";
    return;
  }
  editing.value = true;
  editError.value = "";
  try {
    await axios.post("/api/companyTreasuryUpdate", {
      id: entryToEdit.value.id,
      entry_type: editForm.value.entry_type,
      amount: editForm.value.amount,
      entry_date: editForm.value.entry_date,
      description: editForm.value.description,
      tag: editForm.value.tag || null,
    });
    showEditModal.value = false;
    entryToEdit.value = null;
    flashSuccess("تم التعديل | Updated");
    await refreshAll();
  } catch (e) {
    editError.value = e.response?.data?.message || "تعذر التعديل | Update failed";
  } finally {
    editing.value = false;
  }
}

async function confirmDelete() {
  if (!entryToDelete.value) return;
  try {
    await axios.post(`/api/companyTreasuryDelete?id=${entryToDelete.value.id}`);
    showModalDel.value = false;
    entryToDelete.value = null;
    flashSuccess("تم النقل إلى المحذوفات | Moved to trash");
    await refreshAll();
    if (showTrashPanel.value) {
      await loadTrash();
    }
  } catch (e) {
    errorMsg.value = e.response?.data?.message || "تعذر الحذف | Delete failed";
  }
}

async function loadTrash() {
  loadingTrash.value = true;
  try {
    const res = await axios.get("/api/companyTreasuryTrash", {
      params: { currency: currency.value, limit: 50 },
    });
    trashEntries.value = res.data.entries ?? [];
  } catch (e) {
    errorMsg.value = e.response?.data?.message || "تعذر تحميل المحذوفات | Failed to load trash";
  } finally {
    loadingTrash.value = false;
  }
}

async function toggleTrashPanel() {
  showTrashPanel.value = !showTrashPanel.value;
  if (showTrashPanel.value) {
    await loadTrash();
  }
}

async function restoreEntry(entry) {
  if (restoringId.value) return;
  restoringId.value = entry.id;
  errorMsg.value = "";
  try {
    await axios.post(`/api/companyTreasuryRestore?id=${entry.id}`);
    flashSuccess("تم الاسترجاع | Restored");
    await refreshAll();
    await loadTrash();
  } catch (e) {
    errorMsg.value = e.response?.data?.message || "تعذر الاسترجاع | Restore failed";
  } finally {
    restoringId.value = null;
  }
}

async function toggleSettled(row) {
  if (togglingIds.value.includes(row.id)) return;
  togglingIds.value = [...togglingIds.value, row.id];
  const next = !row.is_settled;
  try {
    const { data } = await axios.post("/api/companyTreasuryToggleSettled", {
      id: row.id,
      is_settled: next,
    });
    row.is_settled = data.is_settled;
  } catch (e) {
    errorMsg.value = e.response?.data?.message || "تعذر تحديث الحالة | Failed to update status";
  } finally {
    togglingIds.value = togglingIds.value.filter((id) => id !== row.id);
  }
}

function onFormKeydown(e) {
  if (e.key === "Enter" && !saving.value) submitEntry();
}

watch(currency, () => {
  resetEntries();
  if (showTrashPanel.value) {
    loadTrash();
  }
});

watch([from, to, filterTag], () => resetEntries());

onMounted(async () => {
  await Promise.all([loadSummary(), loadTags()]);
});
</script>

<template>
  <Head title="قاصة الشركة | Company Treasury" />
  <AuthenticatedLayout>
    <ModalDel
      :show="showModalDel"
      :formData="entryToDelete"
      @a="confirmDelete()"
      @close="showModalDel = false"
    >
      <template #header>
        <h2 class="mb-2 dark:text-white text-center text-lg font-bold">نقل إلى المحذوفات؟</h2>
        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
          يمكن استرجاع الحركة لاحقاً من قسم المحذوفات
        </p>
        <p class="text-center text-xs text-gray-400 dark:text-gray-500 mt-1">Move to trash — can be restored later</p>
      </template>
    </ModalDel>

    <Modal :show="showEditModal" @close="showEditModal = false">
      <template #header>
        <h2 class="text-lg font-bold dark:text-white text-center mb-1">تعديل الحركة</h2>
        <p class="text-center text-sm text-gray-500 dark:text-gray-400">Edit Entry</p>
      </template>
      <template #body>
        <div class="edit-form space-y-3 px-1">
          <div class="composer-type">
            <button
              type="button"
              class="type-btn type-deposit"
              :class="{ active: editForm.entry_type === 'deposit' }"
              @click="editForm.entry_type = 'deposit'"
            >
              <span>إيداع</span>
              <span class="en">Deposit</span>
            </button>
            <button
              type="button"
              class="type-btn type-withdraw"
              :class="{ active: editForm.entry_type === 'withdraw' }"
              @click="editForm.entry_type = 'withdraw'"
            >
              <span>سحب</span>
              <span class="en">Withdraw</span>
            </button>
          </div>
          <div>
            <label class="field-label">المبلغ <span class="en">Amount</span></label>
            <input v-model="editForm.amount" type="number" min="0" step="any" class="field-input field-input-lg w-full" />
          </div>
          <div>
            <label class="field-label">التاريخ <span class="en">Date</span></label>
            <input v-model="editForm.entry_date" type="date" class="field-input w-full" />
          </div>
          <div>
            <label class="field-label">البيان <span class="en">Description</span></label>
            <input v-model="editForm.description" type="text" class="field-input w-full" />
          </div>
          <div>
            <label class="field-label">التاغ <span class="en">Tag</span></label>
            <select v-model="editForm.tag" class="field-input w-full">
              <option value="">— بدون تاغ —</option>
              <option v-for="t in tagOptions" :key="t.id" :value="t.name">{{ t.name }}</option>
            </select>
          </div>
          <p v-if="editError" class="composer-error">{{ editError }}</p>
        </div>
      </template>
      <template #footer>
        <div class="flex flex-row gap-2 w-full px-2">
          <button type="button" class="flex-1 py-2.5 rounded-lg bg-gray-500 text-white font-bold" @click="showEditModal = false">
            <span>إلغاء</span>
            <span class="en block text-xs opacity-80">Cancel</span>
          </button>
          <button
            type="button"
            class="flex-1 py-2.5 rounded-lg bg-blue-600 text-white font-bold"
            :disabled="editing"
            @click="confirmEdit"
          >
            <span>{{ editing ? "..." : "حفظ" }}</span>
            <span class="en block text-xs opacity-80">Save</span>
          </button>
        </div>
      </template>
    </Modal>

    <div class="treasury-app" dir="rtl">
      <!-- Toast -->
      <Transition name="toast">
        <div v-if="successMsg" class="treasury-toast">{{ successMsg }}</div>
      </Transition>

      <!-- App shell -->
      <div class="treasury-shell">
        <!-- Top bar -->
        <header class="treasury-topbar">
          <div class="treasury-brand">
            <div class="treasury-brand-icon">₵</div>
            <div>
              <h1 class="treasury-title">قاصة الشركة</h1>
              <p class="treasury-subtitle">Company Treasury</p>
            </div>
          </div>

          <div class="topbar-right">
            <div class="topbar-actions">
              <button
                type="button"
                class="btn-ghost"
                :class="{ 'btn-ghost-active': showFilterPanel }"
                @click="showFilterPanel = !showFilterPanel"
              >
                <span>{{ showFilterPanel ? "إخفاء الفلتر" : "فلتر" }}</span>
                <span class="en">{{ showFilterPanel ? "Hide Filter" : "Filter" }}</span>
              </button>
              <button type="button" class="btn-ghost" :disabled="loading" @click="refreshAll">
                <span>تحديث</span>
                <span class="en">Refresh</span>
              </button>
              <button type="button" class="btn-ghost" @click="printTreasury()">
                <span>طباعة</span>
                <span class="en">Print</span>
              </button>
              <button
                type="button"
                class="btn-ghost"
                :class="{ 'btn-ghost-active': showTagsPanel }"
                @click="showTagsPanel = !showTagsPanel"
              >
                <span>{{ showTagsPanel ? "إخفاء التاغات" : "التاغات" }}</span>
                <span class="en">{{ showTagsPanel ? "Hide Tags" : "Tags" }}</span>
              </button>
              <button
                type="button"
                class="btn-ghost"
                :class="{ 'btn-ghost-active': showTrashPanel }"
                @click="toggleTrashPanel"
              >
                <span>{{ showTrashPanel ? "إخفاء المحذوفات" : "المحذوفات" }}</span>
                <span class="en">{{ showTrashPanel ? "Hide Trash" : "Trash" }}</span>
              </button>
              <button
                type="button"
                class="btn-primary"
                @click="showEntryPanel = !showEntryPanel"
              >
                <span>{{ showEntryPanel ? "إخفاء الإدخال" : "حركة جديدة" }}</span>
                <span class="en">{{ showEntryPanel ? "Hide Form" : "New Entry" }}</span>
              </button>
            </div>

            <div class="treasury-segment" role="tablist">
              <button
                type="button"
                role="tab"
                class="treasury-segment-btn"
                :class="{ active: currency === '$' }"
                @click="currency = '$'"
              >
                <span>دولار</span>
                <span class="en">USD</span>
              </button>
              <button
                type="button"
                role="tab"
                class="treasury-segment-btn"
                :class="{ active: currency === 'IQD' }"
                @click="currency = 'IQD'"
              >
                <span>دينار</span>
                <span class="en">IQD</span>
              </button>
            </div>
          </div>
        </header>

        <Transition name="slide-filter">
          <section v-show="showFilterPanel" class="treasury-filter-bar">
            <div class="toolbar-dates">
              <label class="field-label">من <span class="en">From</span></label>
              <input v-model="from" type="date" class="field-input" />
              <label class="field-label">إلى <span class="en">To</span></label>
              <input v-model="to" type="date" class="field-input" />
            </div>
            <div class="filter-quick">
              <button type="button" class="btn-quick" @click="setFilterThisMonth">
                <span>هذا الشهر</span>
                <span class="en">This Month</span>
              </button>
              <button type="button" class="btn-quick" @click="setFilterThreeMonths">
                <span>3 أشهر</span>
                <span class="en">3 Months</span>
              </button>
            </div>
            <div class="filter-tag">
              <label class="field-label">التاغ <span class="en">Tag</span></label>
              <select v-model="filterTag" class="field-input">
                <option value="">الكل · All</option>
                <option v-for="t in tagOptions" :key="t.id" :value="t.name">{{ t.name }}</option>
              </select>
            </div>
            <p class="filter-hint">{{ from }} — {{ to }}</p>
          </section>
        </Transition>

        <Transition name="slide-filter">
          <section v-show="showTagsPanel" class="treasury-tags-bar">
            <div class="tags-add">
              <input
                v-model="newTagName"
                type="text"
                class="field-input"
                placeholder="تاغ جديد · New tag"
                @keydown.enter.prevent="addTag"
              />
              <button type="button" class="btn-quick" :disabled="savingTag" @click="addTag">
                <span>{{ savingTag ? "..." : "إضافة" }}</span>
                <span class="en">Add</span>
              </button>
            </div>
            <div v-if="tagOptions.length" class="tags-list">
              <span
                v-for="t in tagOptions"
                :key="t.id"
                class="tag-chip"
              >
                {{ t.name }}
                <button type="button" class="tag-chip-del" title="حذف" @click="deleteTag(t)">×</button>
              </span>
            </div>
            <p v-else class="filter-hint">لا توجد تاغات · No tags yet</p>
          </section>
        </Transition>

        <!-- Balance cards -->
        <section class="treasury-balances">
          <div class="balance-card balance-usd" :class="{ dimmed: currency !== '$' }">
            <span class="balance-label">رصيد الدولار <span class="en">USD Balance</span></span>
            <span class="balance-value">{{ fmt(balanceUsd, "$") }} <small>$</small></span>
          </div>
          <div class="balance-card balance-iqd" :class="{ dimmed: currency !== 'IQD' }">
            <span class="balance-label">رصيد الدينار <span class="en">IQD Balance</span></span>
            <span class="balance-value">{{ fmt(balanceIqd, "IQD") }} <small>IQD</small></span>
          </div>
          <div class="balance-card balance-active">
            <span class="balance-label">رصيد الفترة <span class="en">Period Balance</span></span>
            <span class="balance-value">{{ fmt(periodBalance) }}<small>{{ currencySymbol }}</small></span>
          </div>
        </section>

        <!-- Entry composer -->
        <Transition name="slide-composer">
          <section v-show="showEntryPanel" class="treasury-composer" @keydown="onFormKeydown">
            <div class="composer-type">
              <button
                type="button"
                class="type-btn type-deposit"
                :class="{ active: form.entry_type === 'deposit' }"
                @click="setEntryType('deposit')"
              >
                <span>إيداع</span>
                <span class="en">Deposit</span>
                <small class="hint">مدين · Debit</small>
              </button>
              <button
                type="button"
                class="type-btn type-withdraw"
                :class="{ active: form.entry_type === 'withdraw' }"
                @click="setEntryType('withdraw')"
              >
                <span>سحب</span>
                <span class="en">Withdraw</span>
                <small class="hint">دائن · Credit</small>
              </button>
            </div>

            <div class="composer-fields">
              <div class="composer-field composer-amount">
                <label class="field-label">المبلغ <span class="en">Amount</span></label>
                <input
                  v-model="form.amount"
                  type="number"
                  min="0"
                  step="any"
                  class="field-input field-input-lg"
                  placeholder="0"
                  autofocus
                />
              </div>
              <div class="composer-field">
                <label class="field-label">التاريخ <span class="en">Date</span></label>
                <input v-model="form.entry_date" type="date" class="field-input" />
              </div>
              <div class="composer-field composer-desc">
                <label class="field-label">البيان <span class="en">Description</span></label>
                <input
                  v-model="form.description"
                  type="text"
                  class="field-input"
                  placeholder="وصف الحركة · Entry note"
                />
              </div>
              <div class="composer-field">
                <label class="field-label">التاغ <span class="en">Tag</span></label>
                <select v-model="form.tag" class="field-input">
                  <option value="">— بدون تاغ —</option>
                  <option v-for="t in tagOptions" :key="t.id" :value="t.name">{{ t.name }}</option>
                </select>
              </div>
              <button
                type="button"
                class="btn-submit"
                :class="form.entry_type === 'deposit' ? 'is-deposit' : 'is-withdraw'"
                :disabled="saving"
                @click="submitEntry"
              >
                <span v-if="saving">جاري الحفظ · Saving…</span>
                <template v-else>
                  <span>{{ form.entry_type === "deposit" ? "تأكيد الإيداع" : "تأكيد السحب" }}</span>
                  <span class="en">{{ form.entry_type === "deposit" ? "Confirm Deposit" : "Confirm Withdraw" }}</span>
                </template>
              </button>
            </div>

            <p v-if="errorMsg" class="composer-error">{{ errorMsg }}</p>
          </section>
        </Transition>

        <!-- Ledger table -->
        <section class="treasury-ledger">
          <div class="ledger-header">
            <h2>سجل الحركات · {{ currencyLabel }}</h2>
            <span class="ledger-count">
              {{ entries.length }} / {{ totalEntries }} حركة · loaded
            </span>
          </div>

          <div class="ledger-wrap" :class="{ 'is-loading': loading && !entries.length }">
            <div v-if="loading && !entries.length" class="ledger-overlay">
              <div class="spinner"></div>
              <span>تحميل · Loading</span>
            </div>

            <table class="ledger-table">
              <colgroup>
                <col class="col-status" />
                <col class="col-num" />
                <col class="col-date" />
                <col class="col-desc" />
                <col class="col-tag" />
                <col class="col-debit" />
                <col class="col-credit" />
                <col class="col-balance" />
                <col class="col-action" />
              </colgroup>
              <thead>
                <tr class="ledger-head-row">
                  <th class="col-status"></th>
                  <th class="col-num">#</th>
                  <th class="col-date">التاريخ <span class="en">Date</span></th>
                  <th class="col-desc">البيان <span class="en">Description</span></th>
                  <th class="col-tag">التاغ <span class="en">Tag</span></th>
                  <th class="col-debit">مدين <span class="en">Debit</span></th>
                  <th class="col-credit">دائن <span class="en">Credit</span></th>
                  <th class="col-balance">الرصيد <span class="en">Balance</span></th>
                  <th class="col-action"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!loading && !entries.length" class="empty-row">
                  <td colspan="9">
                    <div class="empty-state">
                      <span>لا توجد حركات</span>
                      <span class="en">No entries in this period</span>
                    </div>
                  </td>
                </tr>
                <tr
                  v-for="(row, idx) in entries"
                  :key="row.id"
                  class="data-row"
                  :class="[
                    Number(row.debit) > 0 ? 'row-deposit' : 'row-withdraw',
                    row.is_settled ? 'row-settled' : '',
                  ]"
                >
                  <td class="col-status">
                    <button
                      type="button"
                      class="status-dot"
                      :class="{
                        'is-settled': row.is_settled,
                        'is-pending': !row.is_settled,
                        toggling: togglingIds.includes(row.id),
                      }"
                      :title="row.is_settled ? 'مؤكد — اضغط للإلغاء' : 'غير مؤكد — اضغط للتأكيد'"
                      :aria-label="row.is_settled ? 'مؤكد' : 'غير مؤكد'"
                      :disabled="togglingIds.includes(row.id)"
                      @click="toggleSettled(row)"
                    />
                  </td>
                  <td class="col-num">{{ idx + 1 }}</td>
                  <td class="col-date">
                    <span class="date-main">{{ fmtDateLite(row.entry_date) }}</span>
                    <span v-if="entryTimeOf(row)" class="date-time-lite">{{ entryTimeOf(row) }}</span>
                  </td>
                  <td class="col-desc">{{ row.description || "—" }}</td>
                  <td class="col-tag">
                    <span v-if="row.tag" class="tag-badge">{{ row.tag }}</span>
                    <span v-else>—</span>
                  </td>
                  <td class="col-debit">{{ fmtCell(row.debit) }}</td>
                  <td class="col-credit">{{ fmtCell(row.credit) }}</td>
                  <td class="col-balance">{{ fmt(row.balance) }}</td>
                  <td class="col-action">
                    <div class="action-btns">
                      <button type="button" class="btn-print" title="Print" @click="printTreasury(row.id)">طباعة</button>
                      <button type="button" class="btn-edit" title="Edit" @click="openEdit(row)">تعديل</button>
                      <button type="button" class="btn-delete" title="Delete" @click="openDelete(row)">حذف</button>
                    </div>
                  </td>
                </tr>
              </tbody>
              <tfoot v-if="entries.length && !loading">
                <tr class="totals-row">
                  <td class="col-status"></td>
                  <td class="col-num"></td>
                  <td class="col-date"></td>
                  <td class="col-desc totals-label">المجموع · Total</td>
                  <td class="col-tag"></td>
                  <td class="col-debit">{{ fmt(totalDebit) }}</td>
                  <td class="col-credit">{{ fmt(totalCredit) }}</td>
                  <td class="col-balance">{{ fmt(periodBalance) }}</td>
                  <td class="col-action"></td>
                </tr>
              </tfoot>
            </table>

            <InfiniteLoading
              :key="`${currency}-${from}-${to}-${filterTag}-${infiniteKey}`"
              @infinite="loadEntriesPage"
            >
              <template #complete>
                <div v-if="entries.length" class="load-more-hint">تم تحميل الكل · All loaded</div>
              </template>
              <template #error>
                <div class="load-more-hint load-more-error">تعذر التحميل · Load error</div>
              </template>
            </InfiniteLoading>
            <div v-if="loadingMore" class="load-more-hint">
              <div class="spinner spinner-sm"></div>
              تحميل المزيد · Loading more…
            </div>
          </div>
        </section>

        <!-- Trash panel -->
        <Transition name="panel-slide">
          <section v-if="showTrashPanel" class="treasury-trash">
            <div class="ledger-header">
              <h2>المحذوفات · {{ currencyLabel }}</h2>
              <span class="ledger-count">{{ trashEntries.length }} حركة · entries</span>
            </div>
            <div class="ledger-wrap" :class="{ 'is-loading': loadingTrash }">
              <div v-if="loadingTrash" class="ledger-overlay">
                <div class="spinner"></div>
                <span>تحميل · Loading</span>
              </div>
              <table class="ledger-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>تاريخ الحذف <span class="en">Deleted</span></th>
                    <th>التاريخ <span class="en">Date</span></th>
                    <th>البيان <span class="en">Description</span></th>
                    <th class="col-debit">مدين <span class="en">Debit</span></th>
                    <th class="col-credit">دائن <span class="en">Credit</span></th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!loadingTrash && !trashEntries.length" class="empty-row">
                    <td colspan="7">
                      <div class="empty-state">
                        <span>لا توجد حركات محذوفة</span>
                        <span class="en">No deleted entries</span>
                      </div>
                    </td>
                  </tr>
                  <tr
                    v-for="(row, idx) in trashEntries"
                    :key="row.id"
                    class="data-row row-trash"
                  >
                    <td class="col-num">{{ idx + 1 }}</td>
                    <td class="col-date">
                      <span class="date-main">{{ fmtDateLite(row.deleted_at) }}</span>
                      <span v-if="deletedTimeOf(row)" class="date-time-lite">{{ deletedTimeOf(row) }}</span>
                    </td>
                    <td class="col-date">
                      <span class="date-main">{{ fmtDateLite(row.entry_date) }}</span>
                      <span v-if="entryTimeOf(row)" class="date-time-lite">{{ entryTimeOf(row) }}</span>
                    </td>
                    <td class="col-desc">{{ row.description || "—" }}</td>
                    <td class="col-debit">{{ fmtCell(row.debit) }}</td>
                    <td class="col-credit">{{ fmtCell(row.credit) }}</td>
                    <td class="col-action">
                      <button
                        type="button"
                        class="btn-restore"
                        :disabled="restoringId === row.id"
                        @click="restoreEntry(row)"
                      >
                        {{ restoringId === row.id ? "..." : "استرجاع" }}
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </Transition>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.treasury-app {
  min-height: calc(100vh - 4rem);
  background: linear-gradient(160deg, #f0f4f8 0%, #e8eef5 40%, #f5f7fa 100%);
  padding: 0.4rem 0.75rem;
}
.dark .treasury-app {
  background: linear-gradient(160deg, #0f1419 0%, #111827 50%, #0f172a 100%);
}

.treasury-shell {
  max-width: 100%;
  width: 100%;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

/* Toast */
.treasury-toast {
  position: fixed;
  top: 1rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 100;
  background: #065f46;
  color: #fff;
  padding: 0.65rem 1.25rem;
  border-radius: 999px;
  font-size: 0.875rem;
  font-weight: 600;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
  white-space: nowrap;
}
.toast-enter-active, .toast-leave-active { transition: all 0.35s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateX(-50%) translateY(-12px); }

/* Top bar */
.treasury-topbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  background: #fff;
  border-radius: 16px;
  padding: 0.85rem 1rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 4px 16px rgba(0, 0, 0, 0.04);
  border: 1px solid rgba(0, 0, 0, 0.06);
}
.dark .treasury-topbar {
  background: #1e293b;
  border-color: #334155;
}
.topbar-right {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.65rem;
  margin-right: auto;
}
.topbar-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
}
.treasury-filter-bar {
  background: #fff;
  border-radius: 14px;
  padding: 0.75rem 1rem;
  border: 1px solid rgba(0, 0, 0, 0.06);
}
.dark .treasury-filter-bar {
  background: #1e293b;
  border-color: #334155;
}

.treasury-brand {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}
.treasury-brand-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: linear-gradient(135deg, #059669, #047857);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.35rem;
  font-weight: 800;
}
.treasury-title {
  font-size: 1.15rem;
  font-weight: 800;
  color: #111827;
  line-height: 1.2;
  margin: 0;
}
.dark .treasury-title { color: #f9fafb; }
.treasury-subtitle {
  font-size: 0.75rem;
  color: #6b7280;
  margin: 0;
  font-weight: 500;
}

/* Currency segment */
.treasury-segment {
  display: flex;
  background: #f3f4f6;
  border-radius: 12px;
  padding: 4px;
  gap: 4px;
}
.dark .treasury-segment { background: #0f172a; }
.treasury-segment-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0.45rem 1.1rem;
  border-radius: 9px;
  border: none;
  background: transparent;
  cursor: pointer;
  font-weight: 700;
  font-size: 0.85rem;
  color: #4b5563;
  transition: all 0.2s ease;
  line-height: 1.15;
}
.treasury-segment-btn .en {
  font-size: 0.65rem;
  font-weight: 600;
  opacity: 0.7;
  letter-spacing: 0.04em;
}
.treasury-segment-btn.active {
  background: #fff;
  color: #047857;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}
.dark .treasury-segment-btn.active {
  background: #334155;
  color: #6ee7b7;
}

/* Balances */
.treasury-balances {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.4rem;
}
@media (max-width: 640px) {
  .treasury-balances { grid-template-columns: 1fr; }
}
.balance-card {
  background: #fff;
  border-radius: 10px;
  padding: 0.45rem 0.65rem;
  border: 1px solid rgba(0, 0, 0, 0.06);
  transition: opacity 0.25s, transform 0.25s;
}
.dark .balance-card { background: #1e293b; border-color: #334155; }
.balance-card.dimmed { opacity: 0.55; transform: scale(0.98); }
.balance-usd { border-right: 4px solid #059669; }
.balance-iqd { border-right: 4px solid #d97706; }
.balance-active { border-right: 4px solid #2563eb; }
.balance-label {
  display: block;
  font-size: 0.62rem;
  color: #6b7280;
  margin-bottom: 0.12rem;
}
.balance-label .en { opacity: 0.75; margin-right: 0.25rem; }
.balance-value {
  font-size: 1.05rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  color: #111827;
}
.dark .balance-value { color: #f9fafb; }
.balance-value small {
  font-size: 0.65rem;
  font-weight: 600;
  opacity: 0.65;
  margin-right: 2px;
}

/* Toolbar — removed, actions in topbar */
.toolbar-dates {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 0.5rem;
}
.filter-hint {
  margin: 0.45rem 0 0;
  font-size: 0.72rem;
  color: #6b7280;
  font-weight: 600;
}
.filter-quick {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-top: 0.5rem;
}
.filter-tag {
  margin-top: 0.5rem;
  max-width: 220px;
}
.treasury-tags-bar {
  padding: 0.65rem 0.85rem;
  background: #f8fafc;
  border-bottom: 1px solid #e5e7eb;
}
.dark .treasury-tags-bar { background: #0f172a; border-color: #334155; }
.tags-add {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  align-items: center;
}
.tags-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-top: 0.55rem;
}
.tag-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  background: #e0e7ff;
  color: #3730a3;
  font-size: 0.72rem;
  font-weight: 700;
}
.dark .tag-chip { background: #312e81; color: #e0e7ff; }
.tag-chip-del {
  border: none;
  background: transparent;
  color: inherit;
  cursor: pointer;
  font-size: 0.9rem;
  line-height: 1;
  padding: 0 0.1rem;
}
.tag-badge {
  display: inline-block;
  padding: 0.18rem 0.55rem;
  border-radius: 999px;
  background: #e0e7ff;
  color: #3730a3;
  font-size: 0.8125rem;
  font-weight: 700;
}
.dark .tag-badge { background: #312e81; color: #e0e7ff; }
.col-tag {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 0;
  font-size: 0.8125rem;
}
.btn-print {
  padding: 0.18rem 0.2rem;
  border-radius: 4px;
  border: 1px solid #d1d5db;
  background: #fff;
  font-size: 0.65rem;
  font-weight: 700;
  color: #374151;
  cursor: pointer;
  width: 100%;
  line-height: 1.2;
}
.btn-print:hover { background: #f3f4f6; border-color: #6366f1; color: #4338ca; }
.dark .btn-print { background: #1e293b; border-color: #475569; color: #e5e7eb; }
.btn-quick {
  padding: 0.35rem 0.65rem;
  border-radius: 8px;
  border: 1px solid #d1d5db;
  background: #fff;
  font-size: 0.72rem;
  font-weight: 700;
  color: #374151;
  cursor: pointer;
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  line-height: 1.1;
}
.btn-quick:hover { background: #f3f4f6; border-color: #059669; color: #047857; }
.dark .btn-quick { background: #0f172a; border-color: #475569; color: #e5e7eb; }
.load-more-hint {
  text-align: center;
  padding: 0.65rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #6b7280;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}
.load-more-error { color: #b91c1c; }
.spinner-sm {
  width: 18px;
  height: 18px;
  border-width: 2px;
}
.toolbar-actions {
  display: flex;
  gap: 0.5rem;
}
.btn-ghost-active {
  background: #dbeafe !important;
  color: #1d4ed8 !important;
}
.dark .btn-ghost-active {
  background: #1e3a5f !important;
  color: #93c5fd !important;
}

/* Bilingual buttons */
.en {
  display: block;
  font-size: 0.62rem;
  font-weight: 600;
  opacity: 0.72;
  letter-spacing: 0.03em;
  line-height: 1;
  margin-top: 2px;
}

.btn-ghost, .btn-primary, .btn-submit, .btn-delete, .type-btn {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  line-height: 1.15;
  cursor: pointer;
  border: none;
  transition: all 0.18s ease;
}
.btn-ghost {
  padding: 0.45rem 0.75rem;
  border-radius: 10px;
  background: #f3f4f6;
  color: #374151;
  font-weight: 700;
  font-size: 0.75rem;
}
.btn-ghost:hover:not(:disabled) { background: #e5e7eb; }
.dark .btn-ghost { background: #334155; color: #e5e7eb; }
.btn-primary {
  padding: 0.45rem 0.85rem;
  border-radius: 10px;
  background: #059669;
  color: #fff;
  font-weight: 700;
  font-size: 0.75rem;
}
.btn-primary:hover { background: #047857; }
.dark .btn-primary { background: #059669; }

/* Composer */
.treasury-composer {
  background: #fff;
  border-radius: 16px;
  padding: 1rem;
  border: 1px solid rgba(0, 0, 0, 0.06);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}
.dark .treasury-composer { background: #1e293b; border-color: #334155; }
.slide-filter-enter-active, .slide-filter-leave-active,
.slide-composer-enter-active, .slide-composer-leave-active {
  transition: all 0.28s ease;
  overflow: hidden;
}
.slide-filter-enter-from, .slide-filter-leave-to {
  opacity: 0;
  max-height: 0;
  margin: 0;
  padding: 0;
  border: none;
}
.slide-filter-enter-to, .slide-filter-leave-from { max-height: 120px; }
.slide-composer-enter-from, .slide-composer-leave-to {
  opacity: 0;
  max-height: 0;
  padding-top: 0;
  padding-bottom: 0;
  margin: 0;
}
.slide-composer-enter-to, .slide-composer-leave-from { max-height: 320px; }

.composer-type {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 0.85rem;
}
.type-btn {
  flex: 1;
  padding: 0.65rem;
  border-radius: 12px;
  border: 2px solid #e5e7eb;
  background: #fafafa;
  font-weight: 700;
  font-size: 0.85rem;
}
.dark .type-btn { background: #0f172a; border-color: #334155; color: #e5e7eb; }
.type-btn .hint {
  font-size: 0.6rem;
  font-weight: 600;
  opacity: 0.6;
  margin-top: 2px;
}
.type-deposit.active {
  border-color: #059669;
  background: #ecfdf5;
  color: #047857;
}
.type-withdraw.active {
  border-color: #dc2626;
  background: #fef2f2;
  color: #b91c1c;
}

.composer-fields {
  display: grid;
  grid-template-columns: 140px 150px 1fr auto;
  gap: 0.65rem;
  align-items: end;
}
@media (max-width: 768px) {
  .composer-fields { grid-template-columns: 1fr 1fr; }
  .composer-desc { grid-column: 1 / -1; }
  .btn-submit { grid-column: 1 / -1; }
}

.field-label {
  display: block;
  font-size: 0.7rem;
  font-weight: 700;
  color: #6b7280;
  margin-bottom: 0.3rem;
}
.field-label .en { display: inline; font-size: 0.62rem; margin-right: 0.2rem; }
.field-input {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 10px;
  padding: 0.5rem 0.65rem;
  font-size: 0.875rem;
  background: #fff;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.field-input:focus {
  outline: none;
  border-color: #059669;
  box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
}
.dark .field-input {
  background: #0f172a;
  border-color: #475569;
  color: #f9fafb;
}
.field-input-lg {
  font-size: 1.15rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

.btn-submit {
  padding: 0.65rem 1.1rem;
  border-radius: 12px;
  color: #fff;
  font-weight: 800;
  font-size: 0.82rem;
  min-width: 120px;
  min-height: 52px;
}
.btn-submit.is-deposit { background: linear-gradient(135deg, #059669, #047857); }
.btn-submit.is-withdraw { background: linear-gradient(135deg, #dc2626, #b91c1c); }
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-submit:not(:disabled):hover { filter: brightness(1.06); transform: translateY(-1px); }

.composer-error {
  margin-top: 0.65rem;
  padding: 0.5rem 0.75rem;
  background: #fef2f2;
  color: #b91c1c;
  border-radius: 8px;
  font-size: 0.8rem;
  font-weight: 600;
}

/* Ledger */
.treasury-ledger {
  background: #fff;
  border-radius: 12px;
  border: 1px solid rgba(0, 0, 0, 0.06);
  overflow: hidden;
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
}
.dark .treasury-ledger { background: #1e293b; border-color: #334155; }

.ledger-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.35rem 0.65rem;
  border-bottom: 1px solid #e5e7eb;
  background: #fafafa;
}
.dark .ledger-header { background: #0f172a; border-color: #334155; }
.ledger-header h2 {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 800;
  color: #111827;
}
.dark .ledger-header h2 { color: #f9fafb; }
.ledger-count {
  font-size: 0.8125rem;
  color: #6b7280;
  font-weight: 600;
}

.ledger-wrap {
  position: relative;
  overflow-x: hidden;
  overflow-y: auto;
  flex: 1;
  max-height: calc(100vh - 14rem);
  min-height: 280px;
}
.ledger-overlay {
  position: absolute;
  inset: 0;
  background: rgba(255, 255, 255, 0.75);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  z-index: 5;
  font-size: 0.8rem;
  font-weight: 600;
  color: #374151;
}
.dark .ledger-overlay { background: rgba(15, 23, 42, 0.8); color: #e5e7eb; }
.spinner {
  width: 28px;
  height: 28px;
  border: 3px solid #e5e7eb;
  border-top-color: #059669;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.ledger-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
  line-height: 1.35;
  font-variant-numeric: tabular-nums;
  table-layout: fixed;
}
.ledger-table col.col-status { width: 2.75%; }
.ledger-table col.col-num { width: 2.75%; }
.ledger-table col.col-date { width: 9%; }
.ledger-table col.col-desc { width: 20%; }
.ledger-table col.col-tag { width: 11%; }
.ledger-table col.col-debit { width: 10.5%; }
.ledger-table col.col-credit { width: 10.5%; }
.ledger-table col.col-balance { width: 10.5%; }
.ledger-table col.col-action { width: 12.5%; }
.ledger-table thead {
  position: sticky;
  top: 0;
  z-index: 2;
}
.ledger-table th {
  background: #374151;
  color: #fff;
  padding: 0.4rem 0.3rem;
  font-weight: 700;
  font-size: 0.75rem;
  text-align: center;
  border: 1px solid #4b5563;
  white-space: normal;
  line-height: 1.2;
}
.ledger-table th .en {
  display: inline;
  font-size: 0.6875rem;
  opacity: 0.75;
  margin-right: 3px;
}
.ledger-table td {
  padding: 0.42rem 0.45rem;
  border: 1px solid #d1d5db;
  vertical-align: middle;
  font-size: 0.9375rem;
}
.dark .ledger-table td { border-color: #475569; color: #e5e7eb; }
.totals-label {
  text-align: right;
  font-weight: 800;
}

/* إيداع = خلفية خضراء | سحب = خلفية حمراء */
.row-deposit { background: #ecfdf5; }
.row-withdraw { background: #fef2f2; }
.dark .row-deposit { background: rgba(6, 95, 70, 0.22); }
.dark .row-withdraw { background: rgba(153, 27, 27, 0.2); }
.row-deposit:hover { background: #d1fae5 !important; }
.row-withdraw:hover { background: #fee2e2 !important; }
.dark .row-deposit:hover { background: rgba(6, 95, 70, 0.35) !important; }
.dark .row-withdraw:hover { background: rgba(153, 27, 27, 0.32) !important; }

.treasury-trash {
  background: #fffbeb;
  border-radius: 12px;
  border: 1px solid #fcd34d;
  overflow: hidden;
}
.dark .treasury-trash {
  background: rgba(120, 53, 15, 0.15);
  border-color: #92400e;
}
.row-trash { background: #fffbeb; }
.dark .row-trash { background: rgba(120, 53, 15, 0.12); }
.row-trash:hover { background: #fef3c7 !important; }
.dark .row-trash:hover { background: rgba(120, 53, 15, 0.22) !important; }

.btn-restore {
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
  background: transparent;
  color: #b45309;
  font-size: 0.62rem;
  font-weight: 700;
  border: none;
  cursor: pointer;
}
.btn-restore:hover:not(:disabled) { background: rgba(254, 243, 199, 0.9); }
.btn-restore:disabled { opacity: 0.5; cursor: wait; }

.panel-slide-enter-active, .panel-slide-leave-active {
  transition: all 0.28s ease;
  overflow: hidden;
}
.panel-slide-enter-from, .panel-slide-leave-to {
  opacity: 0;
  max-height: 0;
  margin: 0;
  padding: 0;
  border: none;
}
.panel-slide-enter-to, .panel-slide-leave-from { max-height: 480px; }

.col-num { text-align: center; color: #6b7280; font-size: 0.8125rem; font-weight: 600; }
.col-date { text-align: center; white-space: nowrap; font-size: 0.8125rem; line-height: 1.25; font-weight: 600; }
.date-main { display: block; color: #111827; }
.dark .date-main { color: #f3f4f6; }
.date-time-lite {
  display: block;
  font-size: 0.75rem;
  font-weight: 500;
  color: #6b7280;
  margin-top: 2px;
}
.dark .date-time-lite { color: #94a3b8; }
.col-desc {
  text-align: right;
  max-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.875rem;
}
.col-debit { text-align: center; font-weight: 700; color: #047857; font-size: 0.8125rem; }
.col-credit { text-align: center; font-weight: 700; color: #b91c1c; font-size: 0.8125rem; }
.col-balance { text-align: center; font-weight: 800; color: #1e40af; font-size: 0.8125rem; }
.dark .col-balance { color: #93c5fd; }
.col-action {
  width: auto;
  padding: 0.2rem 0.15rem !important;
  vertical-align: middle;
}
.col-status { width: auto; text-align: center; padding: 0.25rem !important; }

.status-dot {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  min-width: 22px;
  min-height: 22px;
  border-radius: 50%;
  border: 2px solid rgba(255, 255, 255, 0.85);
  cursor: pointer;
  padding: 0;
  transition: background-color 0.25s ease, transform 0.2s ease, box-shadow 0.25s ease;
}
.status-dot.is-pending {
  background: #ef4444;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.28);
}
.status-dot.is-settled {
  background: #22c55e;
  box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.28);
}
.status-dot:hover:not(:disabled) {
  transform: scale(1.12);
}
.status-dot:active:not(:disabled) {
  transform: scale(0.92);
}
.status-dot.toggling {
  animation: dot-pop 0.35s ease;
}
.status-dot:disabled {
  opacity: 0.65;
  cursor: wait;
}
@keyframes dot-pop {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.25); }
}
@media (prefers-reduced-motion: reduce) {
  .status-dot { transition: none; }
  .status-dot.toggling { animation: none; }
}

.row-settled { opacity: 0.92; }
.row-settled.row-deposit { background: #d1fae5 !important; }
.row-settled.row-withdraw { background: #fee2e2 !important; }
.dark .row-settled.row-deposit { background: rgba(6, 95, 70, 0.32) !important; }
.dark .row-settled.row-withdraw { background: rgba(153, 27, 27, 0.28) !important; }

.totals-row td {
  background: #e5e7eb !important;
  font-weight: 800;
  font-size: 0.9375rem;
  padding: 0.45rem 0.45rem !important;
  border-top: 2px solid #374151;
}
.dark .totals-row td { background: #334155 !important; }

.btn-delete {
  padding: 0.18rem 0.2rem;
  border-radius: 4px;
  background: transparent;
  color: #dc2626;
  font-size: 0.65rem;
  font-weight: 700;
  border: none;
  cursor: pointer;
  width: 100%;
  line-height: 1.2;
}
.btn-delete:hover { background: rgba(254, 226, 226, 0.8); }
.action-btns {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  justify-content: center;
  gap: 2px;
  min-width: 0;
}
.btn-edit {
  padding: 0.18rem 0.2rem;
  border-radius: 4px;
  background: transparent;
  color: #2563eb;
  font-size: 0.65rem;
  font-weight: 700;
  border: none;
  cursor: pointer;
  width: 100%;
  line-height: 1.2;
}
.btn-edit:hover { background: rgba(219, 234, 254, 0.8); }

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.15rem;
  padding: 1.5rem;
  color: #9ca3af;
  font-weight: 600;
  font-size: 0.75rem;
}
</style>
