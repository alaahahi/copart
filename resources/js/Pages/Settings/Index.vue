<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import TagChipList from "@/Components/TagChipList.vue";
import ModalSystemReset from "@/Components/ModalSystemReset.vue";
import { Head, Link, usePage } from "@inertiajs/inertia-vue3";
import { ref, computed, onMounted } from "vue";
import axios from "axios";
import { useI18n } from "vue-i18n";
import { useToast } from "vue-toastification";
import {
  alternatePublicAsset,
  resolvePublicAsset,
} from "@/utils/resolvePublicAsset";

const { t } = useI18n();
const toast = useToast();
const page = usePage();

const props = defineProps({
  config: Object,
  waSources: {
    type: Array,
    default: () => [
      "contracts",
      "crm",
      "sales",
      "invoices",
      "support",
      "marketing",
      "appointments",
    ],
  },
});

const auctions = ref([]);
const auctionsLoading = ref(false);
const shippingRoutes = ref([]);
const shippingRoutesLoading = ref(false);

async function loadAuctions() {
  auctionsLoading.value = true;
  try {
    const { data } = await axios.get("/api/auctions");
    auctions.value = data || [];
  } catch (e) {
    toast.error(t("settingsFailed"));
  } finally {
    auctionsLoading.value = false;
  }
}

async function addAuction(name) {
  try {
    const { data } = await axios.post("/api/auctions", { name });
    auctions.value = [...auctions.value, data].sort((a, b) =>
      a.name.localeCompare(b.name)
    );
  } catch (e) {
    toast.error(
      e.response?.data?.message ||
        e.response?.data?.errors?.name?.[0] ||
        t("settingsFailed")
    );
  }
}

async function removeAuction(item) {
  try {
    await axios.post("/api/deleteAuction", { id: item.id });
    auctions.value = auctions.value.filter((a) => a.id !== item.id);
  } catch (e) {
    toast.error(e.response?.data?.message || t("settingsFailed"));
  }
}

async function loadShippingRoutes() {
  shippingRoutesLoading.value = true;
  try {
    const { data } = await axios.get("/api/shippingRoutes");
    shippingRoutes.value = data || [];
  } catch (e) {
    toast.error(t("settingsFailed"));
  } finally {
    shippingRoutesLoading.value = false;
  }
}

async function addShippingRoute(name) {
  try {
    const { data } = await axios.post("/api/shippingRoutes", { name });
    shippingRoutes.value = [...shippingRoutes.value, data].sort((a, b) =>
      a.name.localeCompare(b.name)
    );
  } catch (e) {
    toast.error(
      e.response?.data?.message ||
        e.response?.data?.errors?.name?.[0] ||
        t("settingsFailed")
    );
  }
}

async function removeShippingRoute(item) {
  try {
    await axios.post("/api/deleteShippingRoute", { id: item.id });
    shippingRoutes.value = shippingRoutes.value.filter((r) => r.id !== item.id);
  } catch (e) {
    toast.error(e.response?.data?.message || t("settingsFailed"));
  }
}

onMounted(() => {
  loadAuctions();
  loadShippingRoutes();
});

const logoFields = [
  { key: "receipt_logo_left_1", labelKey: "logoLeft1" },
  { key: "receipt_logo_left_2", labelKey: "logoLeft2" },
  { key: "receipt_logo_left_3", labelKey: "logoLeft3" },
  { key: "receipt_logo_haulf", labelKey: "logoHaulf" },
  { key: "receipt_logo_main", labelKey: "logoMain" },
];

const brandingPaths = ref({
  app_logo: props.config?.app_logo || "",
  app_cover: props.config?.app_cover || "",
});
const brandingFiles = ref({});
const brandingPreviews = ref({});
const brandingAltTried = ref({
  app_logo: false,
  app_cover: false,
});
const removeBranding = ref({
  app_logo: false,
  app_cover: false,
});
const logoInput = ref(null);
const coverInput = ref(null);

const form = ref({
  receipt_template: props.config?.receipt_template || "default",
  receipt_phone: props.config?.receipt_phone || "",
  receipt_address: props.config?.receipt_address || "",
  receipt_website: props.config?.receipt_website || "",
  first_title_ar: props.config?.first_title_ar || "",
  second_title_ar: props.config?.second_title_ar || "",
  wa_enabled: !!props.config?.wa_enabled,
  wa_base_host: props.config?.wa_base_host || "https://wa.intellij-app.com",
  wa_tenant: props.config?.wa_tenant || "",
  wa_source: props.config?.wa_source || "sales",
  wa_created_by: props.config?.wa_created_by || "copart-erp",
  wa_notify_debt: !!props.config?.wa_notify_debt,
  wa_notify_car_created: !!props.config?.wa_notify_car_created,
  wa_notify_payment: !!props.config?.wa_notify_payment,
});

const logoPaths = ref(
  Object.fromEntries(
    logoFields.map(({ key }) => [key, props.config?.[key] || ""])
  )
);

const logoFiles = ref({});
const logoPreviews = ref({});

const saving = ref(false);
const successMsg = ref("");
const errorMsg = ref("");

const templates = [
  {
    id: "default",
    titleKey: "templateDefault",
    descKey: "templateDefaultDesc",
  },
  {
    id: "mkl_usd",
    titleKey: "templateMklUsd",
    descKey: "templateMklUsdDesc",
  },
];

const waQueueUrlPreview = computed(() => {
  const base = String(form.value.wa_base_host || "").replace(/\/+$/, "");
  const tenant = String(form.value.wa_tenant || "").trim();
  if (!base || !tenant) {
    return "https://wa.intellij-app.com/{tenant}/api/v1/queue";
  }
  return `${base}/${tenant}/api/v1/queue`;
});

const waCurlExample = computed(() => {
  const url = waQueueUrlPreview.value;
  return `curl -X POST "${url}" \\
  -H "Content-Type: application/json" \\
  -H "Accept: application/json" \\
  -d '{
    "phone": "+9647xxxxxxxx",
    "message": "نص الرسالة",
    "source": "${form.value.wa_source || "sales"}",
    "event": "debt_notice",
    "created_by": "${form.value.wa_created_by || "copart-erp"}",
    "unique_key": "debt_notice:123:2026-07-25",
    "priority": 5
  }'`;
});

function onLogoChange(field, event) {
  const file = event.target.files?.[0];
  if (!file) return;
  logoFiles.value[field] = file;
  logoPreviews.value[field] = URL.createObjectURL(file);
}

/** Match ERP static paths under /public (legacy /img/... and /storage/... still work). */
function logoSrc(field) {
  return logoPreviews.value[field] || resolvePublicAsset(logoPaths.value[field]) || "";
}

function onBrandingChange(field, event) {
  const file = event.target.files?.[0];
  if (!file) return;
  brandingFiles.value[field] = file;
  brandingPreviews.value[field] = URL.createObjectURL(file);
  removeBranding.value[field] = false;
  brandingAltTried.value[field] = false;
}

function brandingSrc(field) {
  if (removeBranding.value[field] && !brandingPreviews.value[field]) {
    return "";
  }
  return brandingPreviews.value[field] || resolvePublicAsset(brandingPaths.value[field]) || "";
}

function onBrandingImgError(field) {
  if (brandingPreviews.value[field]) return;
  if (brandingAltTried.value[field]) return;
  const alt = alternatePublicAsset(brandingPaths.value[field] || brandingSrc(field));
  if (alt) {
    brandingAltTried.value[field] = true;
    brandingPaths.value[field] = alt;
  }
}

function clearBranding(field) {
  brandingFiles.value[field] = null;
  if (brandingPreviews.value[field]) {
    URL.revokeObjectURL(brandingPreviews.value[field]);
  }
  brandingPreviews.value[field] = "";
  removeBranding.value[field] = true;
}

async function save() {
  saving.value = true;
  errorMsg.value = "";
  successMsg.value = "";
  try {
    const formData = new FormData();
    Object.entries(form.value).forEach(([key, val]) => {
      if (typeof val === "boolean") {
        formData.append(key, val ? "1" : "0");
      } else {
        formData.append(key, val ?? "");
      }
    });
    logoFields.forEach(({ key }) => {
      if (logoFiles.value[key]) {
        formData.append(key, logoFiles.value[key]);
      }
    });
    ["app_logo", "app_cover"].forEach((key) => {
      if (brandingFiles.value[key]) {
        formData.append(key, brandingFiles.value[key]);
      }
      if (removeBranding.value[key]) {
        formData.append(`remove_${key}`, "1");
      }
    });

    const { data } = await axios.post(route("settings.update"), formData);

    if (data.config) {
      logoFields.forEach(({ key }) => {
        if (data.config[key]) {
          logoPaths.value[key] = data.config[key];
        }
      });
      brandingPaths.value.app_logo = data.config.app_logo || "";
      brandingPaths.value.app_cover = data.config.app_cover || "";
      brandingAltTried.value = { app_logo: false, app_cover: false };
      // Keep header / login branding in sync without a full reload.
      if (!page.props.value.branding) {
        page.props.value.branding = {};
      }
      page.props.value.branding.logo = data.config.app_logo || null;
      page.props.value.branding.cover = data.config.app_cover || null;
      if (data.config.first_title_ar) {
        form.value.first_title_ar = data.config.first_title_ar;
        page.props.value.appName = data.config.first_title_ar;
      }
      form.value.wa_enabled = !!data.config.wa_enabled;
      form.value.wa_base_host =
        data.config.wa_base_host || form.value.wa_base_host;
      form.value.wa_tenant = data.config.wa_tenant || "";
      form.value.wa_source = data.config.wa_source || "sales";
      form.value.wa_created_by = data.config.wa_created_by || "copart-erp";
      form.value.wa_notify_debt = !!data.config.wa_notify_debt;
      form.value.wa_notify_car_created = !!data.config.wa_notify_car_created;
      form.value.wa_notify_payment = !!data.config.wa_notify_payment;
    }

    logoFiles.value = {};
    logoPreviews.value = {};
    brandingFiles.value = {};
    Object.values(brandingPreviews.value).forEach((url) => {
      if (url) URL.revokeObjectURL(url);
    });
    brandingPreviews.value = {};
    removeBranding.value = { app_logo: false, app_cover: false };
    if (logoInput.value) logoInput.value.value = "";
    if (coverInput.value) coverInput.value.value = "";
    successMsg.value = t("settingsSaved");
  } catch (e) {
    errorMsg.value =
      e.response?.data?.message ||
      Object.values(e.response?.data?.errors || {})?.[0]?.[0] ||
      t("settingsFailed");
  } finally {
    saving.value = false;
  }
}

function preview(type) {
  window.open(`${route("settings.receipt_preview")}?type=${type}`, "_blank");
}

const showSystemReset = ref(false);

// ─── Ledger maintenance (إصلاح نقل السيارات) ───────────────────────────────
const repairBusy = ref(false);
const repairRepost = ref(true);
const repairOutput = ref("");
const repairMessage = ref("");
const repairError = ref("");
const repairLastResult = ref(null);

async function runRepairBadCarTransfers(execute) {
  if (repairBusy.value) return;
  repairBusy.value = true;
  repairError.value = "";
  repairMessage.value = "";
  try {
    if (execute) {
      const cash = repairLastResult.value?.details?.cash_hits?.length || 0;
      const rev = repairLastResult.value?.details?.revenue_hits?.length || 0;
      if (
        !confirm(
          cash || rev
            ? `تنفيذ الإصلاح؟ سيتم إلغاء ${cash + rev} قيد خاطئ${repairRepost.value ? " وإعادة ترحيل الذمم" : " بدون إعادة ترحيل"}.`
            : "لم تُعرض معاينة بعد — المتابعة قد تلغي قيوداً إن وُجدت. تنفيذ؟"
        )
      ) {
        return;
      }
      const { data } = await axios.post("/api/ledgerRepairBadCarTransfers", {
        execute: 1,
        no_repost: repairRepost.value ? 0 : 1,
      });
      repairMessage.value = data.message || "تم التنفيذ";
      repairOutput.value = data.output || JSON.stringify(data.result || {}, null, 2);
      repairLastResult.value = data.result || null;
      toast.success(repairMessage.value);
    } else {
      const { data } = await axios.get("/api/ledgerRepairBadCarTransfers", {
        params: { no_repost: repairRepost.value ? 0 : 1 },
      });
      repairMessage.value = data.message || "معاينة";
      repairOutput.value = data.output || JSON.stringify(data.result || {}, null, 2);
      repairLastResult.value = data.result || null;
      toast.info(repairMessage.value);
    }
  } catch (e) {
    repairError.value =
      e.response?.data?.message || t("settingsFailed");
    toast.error(repairError.value);
  } finally {
    repairBusy.value = false;
  }
}

async function confirmSystemReset({ password, confirmation, done }) {
  try {
    const { data } = await axios.post(route("settings.reset"), {
      password,
      confirmation,
    });
    toast.success(data?.message || t("systemResetSuccess"));
    showSystemReset.value = false;
    window.setTimeout(() => window.location.reload(), 900);
  } catch (e) {
    const msg =
      e.response?.data?.errors?.password?.[0] ||
      e.response?.data?.errors?.confirmation?.[0] ||
      e.response?.data?.message ||
      t("systemResetFailed");
    toast.error(msg);
  } finally {
    if (typeof done === "function") done();
  }
}

// ─── Database Insights ───────────────────────────────────────────────────────
const dbData    = ref(null);
const dbLoading = ref(false);
const vacuuming = ref(false);
const dbColors  = ['#6366f1','#22c55e','#f59e0b','#3b82f6','#ec4899','#14b8a6','#fb923c','#a855f7','#ef4444','#64748b'];

const dbTopTables = computed(() => (dbData.value?.tables ?? []).slice(0, 10));

const dbChartGradient = computed(() => {
  const tables = dbTopTables.value;
  if (!tables.length) return '#334155';
  const total = dbData.value?.db_size ?? tables.reduce((s, r) => s + (r.size_bytes ?? 0), 0);
  let offset = 0;
  const stops = [];
  tables.forEach((row, idx) => {
    const pct = total > 0 ? ((row.size_bytes ?? 0) / total) * 100 : 0;
    const color = dbColors[idx % dbColors.length];
    stops.push(`${color} ${offset.toFixed(2)}%`);
    offset += pct;
    stops.push(`${color} ${offset.toFixed(2)}%`);
  });
  if (offset < 100) { stops.push(`#334155 ${offset.toFixed(2)}%`, '#334155 100%'); }
  return `conic-gradient(${stops.join(', ')})`;
});

function formatDbBytes(bytes) {
  if (bytes == null) return '—';
  if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
  if (bytes >= 1048576)    return (bytes / 1048576).toFixed(1) + ' MB';
  if (bytes >= 1024)       return (bytes / 1024).toFixed(0) + ' KB';
  return bytes + ' B';
}

async function loadDbInsights() {
  if (dbLoading.value) return;
  dbLoading.value = true;
  try {
    const { data } = await axios.get(route('settings.db.insights'));
    dbData.value = data;
  } catch {
    toast.error('تعذّر تحميل معلومات قاعدة البيانات');
  } finally {
    dbLoading.value = false;
  }
}

async function runVacuum() {
  vacuuming.value = true;
  try {
    const { data } = await axios.post(route('settings.db.vacuum'));
    toast.success(data.message + (data.saved ? ` | وُفِّر: ${formatDbBytes(data.saved)}` : ''));
    await loadDbInsights();
  } catch {
    toast.error('فشل VACUUM');
  } finally {
    vacuuming.value = false;
  }
}

onMounted(loadDbInsights);
</script>

<template>
  <Head :title="$t('settings')" />

  <AuthenticatedLayout>
    <template #header>
      <h2
        class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight"
      >
        {{ $t("settings") }} — {{ $t("receiptTemplates") }}
      </h2>
    </template>

    <div class="py-8">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div
          v-if="Number($page.props.auth.user.type_id) === 1"
          class="bg-slate-900 shadow rounded-xl p-5 border border-slate-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
        >
          <div>
            <h3 class="text-lg font-bold text-white">
              {{ $t("userManagement") }}
            </h3>
            <p class="text-sm text-slate-300 mt-1">
              {{ $t("userManagementHint") }}
            </p>
          </div>
          <Link
            :href="route('settings.users')"
            class="inline-flex justify-center px-5 py-2.5 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-500"
          >
            {{ $t("manageUsers") }}
          </Link>
        </div>

        <div
          v-if="successMsg"
          class="rounded-lg bg-emerald-900/80 text-emerald-100 border border-emerald-600/50 px-4 py-3 text-sm font-semibold"
        >
          {{ successMsg }}
        </div>
        <div
          v-if="errorMsg"
          class="rounded-lg bg-rose-900/80 text-rose-100 border border-rose-600/50 px-4 py-3 text-sm font-semibold"
        >
          {{ errorMsg }}
        </div>

        <section
          class="bg-slate-900 shadow rounded-xl p-6 border border-slate-700"
        >
          <h3 class="text-lg font-bold text-white mb-1">
            {{ $t("brandingTitle") }}
          </h3>
          <p class="text-sm text-slate-300 mb-5">
            {{ $t("brandingHint") }}
          </p>

          <div class="mb-5">
            <label class="block text-sm font-semibold text-slate-200 mb-2" for="first_title_ar">
              {{ $t("companyName") }}
            </label>
            <input
              id="first_title_ar"
              v-model="form.first_title_ar"
              type="text"
              class="w-full rounded-lg bg-slate-950 border border-slate-600 text-white placeholder-slate-400"
              :placeholder="$t('companyNameHint')"
            />
          </div>

          <div class="grid gap-5 sm:grid-cols-2 mb-5">
            <div
              class="rounded-lg border border-slate-700 bg-slate-950/60 p-4"
            >
              <label class="block text-sm font-semibold text-slate-200 mb-2">
                {{ $t("appLogo") }}
              </label>
              <div
                class="mb-3 flex items-center justify-center rounded-lg bg-slate-800 border border-slate-700 min-h-[96px] p-3"
              >
                <img
                  v-if="brandingSrc('app_logo')"
                  :src="brandingSrc('app_logo')"
                  alt=""
                  class="max-h-20 max-w-full object-contain"
                  @error="onBrandingImgError('app_logo')"
                />
                <span v-else class="text-sm text-slate-400">{{
                  $t("noImage")
                }}</span>
              </div>
              <input
                ref="logoInput"
                type="file"
                accept="image/jpeg,image/png,image/webp,image/svg+xml,.svg"
                class="block w-full text-sm text-slate-300 file:me-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-emerald-900/50 file:text-emerald-200"
                @change="onBrandingChange('app_logo', $event)"
              />
              <p class="mt-1 text-xs text-slate-400">{{ $t("appLogoHint") }}</p>
              <div class="mt-3 flex flex-wrap gap-2">
                <button
                  type="button"
                  class="px-3 py-1.5 rounded-lg bg-slate-700 text-slate-100 text-xs font-semibold hover:bg-slate-600"
                  @click="logoInput?.click()"
                >
                  {{ brandingSrc("app_logo") ? $t("replaceImage") : $t("uploadImage") }}
                </button>
                <button
                  v-if="brandingSrc('app_logo') || brandingPaths.app_logo"
                  type="button"
                  class="px-3 py-1.5 rounded-lg bg-rose-700 text-rose-100 text-xs font-semibold hover:bg-rose-600"
                  @click="clearBranding('app_logo')"
                >
                  {{ $t("removeImage") }}
                </button>
              </div>
            </div>

            <div
              class="rounded-lg border border-slate-700 bg-slate-950/60 p-4"
            >
              <label class="block text-sm font-semibold text-slate-200 mb-2">
                {{ $t("appCover") }}
              </label>
              <div
                class="mb-3 flex items-center justify-center overflow-hidden rounded-lg bg-slate-800 border border-slate-700 min-h-[96px]"
              >
                <img
                  v-if="brandingSrc('app_cover')"
                  :src="brandingSrc('app_cover')"
                  alt=""
                  class="w-full h-28 object-cover"
                  @error="onBrandingImgError('app_cover')"
                />
                <span v-else class="text-sm text-slate-400 p-3">{{
                  $t("noImage")
                }}</span>
              </div>
              <input
                ref="coverInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="block w-full text-sm text-slate-300 file:me-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-emerald-900/50 file:text-emerald-200"
                @change="onBrandingChange('app_cover', $event)"
              />
              <p class="mt-1 text-xs text-slate-400">{{ $t("appCoverHint") }}</p>
              <div class="mt-3 flex flex-wrap gap-2">
                <button
                  type="button"
                  class="px-3 py-1.5 rounded-lg bg-slate-700 text-slate-100 text-xs font-semibold hover:bg-slate-600"
                  @click="coverInput?.click()"
                >
                  {{ brandingSrc("app_cover") ? $t("replaceImage") : $t("uploadImage") }}
                </button>
                <button
                  v-if="brandingSrc('app_cover') || brandingPaths.app_cover"
                  type="button"
                  class="px-3 py-1.5 rounded-lg bg-rose-700 text-rose-100 text-xs font-semibold hover:bg-rose-600"
                  @click="clearBranding('app_cover')"
                >
                  {{ $t("removeImage") }}
                </button>
              </div>
            </div>
          </div>

          <button
            type="button"
            class="px-6 py-2.5 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-500 disabled:opacity-60"
            :disabled="saving"
            @click="save"
          >
            {{ saving ? $t("saving") : $t("saveSettings") }}
          </button>
        </section>

        <section class="bg-white dark:bg-gray-900 shadow rounded-xl p-6">
          <h3 class="text-lg font-bold mb-4 dark:text-gray-100">
            {{ $t("receiptTemplates") }}
          </h3>

          <div class="space-y-3 mb-6">
            <label
              v-for="tpl in templates"
              :key="tpl.id"
              class="flex items-start gap-3 p-4 border rounded-lg cursor-pointer transition"
              :class="
                form.receipt_template === tpl.id
                  ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/30'
                  : 'border-gray-200 dark:border-gray-700'
              "
            >
              <input
                v-model="form.receipt_template"
                type="radio"
                :value="tpl.id"
                class="mt-1"
              />
              <div>
                <div class="font-bold dark:text-gray-100">
                  {{ $t(tpl.titleKey) }}
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                  {{ $t(tpl.descKey) }}
                </div>
              </div>
            </label>
          </div>

          <div class="flex flex-wrap gap-2 mb-6">
            <button
              type="button"
              class="px-4 py-2 rounded-lg bg-gray-700 text-white text-sm font-semibold hover:bg-gray-800"
              @click="preview('receipt')"
            >
              {{ $t("previewReceipt") }}
            </button>
            <button
              type="button"
              class="px-4 py-2 rounded-lg bg-gray-700 text-white text-sm font-semibold hover:bg-gray-800"
              @click="preview('payment')"
            >
              {{ $t("previewPayment") }}
            </button>
          </div>

          <h4 class="font-bold mb-3 dark:text-gray-100">
            {{ $t("receiptLogos") }}
          </h4>
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            {{ $t("receiptLogosHint") }}
          </p>

          <div class="grid gap-4 sm:grid-cols-2 mb-6">
            <div
              v-for="logo in logoFields"
              :key="logo.key"
              class="border border-gray-200 dark:border-gray-700 rounded-lg p-3"
            >
              <label class="block text-sm font-semibold mb-2 dark:text-gray-300">
                {{ $t(logo.labelKey) }}
              </label>
              <div
                v-if="logoSrc(logo.key)"
                class="mb-2 flex items-center justify-center bg-gray-50 dark:bg-gray-800 rounded p-2 min-h-[60px]"
              >
                <img
                  :src="logoSrc(logo.key)"
                  alt=""
                  class="max-h-14 max-w-full object-contain"
                />
              </div>
              <input
                type="file"
                accept="image/*"
                class="block w-full text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-blue-50 file:text-blue-700 dark:file:bg-blue-900/40 dark:file:text-blue-200"
                @change="onLogoChange(logo.key, $event)"
              />
            </div>
          </div>

          <h4 class="font-bold mb-3 dark:text-gray-100">
            {{ $t("receiptFooter") }}
          </h4>
          <div class="grid gap-4">
            <div>
              <label class="block text-sm font-semibold mb-1 dark:text-gray-300">{{
                $t("footerPhone")
              }}</label>
              <input
                v-model="form.receipt_phone"
                type="text"
                class="w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"
                placeholder="+964 750 468 0510 / 750 705 3555"
              />
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1 dark:text-gray-300">{{
                $t("footerAddress")
              }}</label>
              <input
                v-model="form.receipt_address"
                type="text"
                class="w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"
                placeholder="100 M road near Hanouf motel"
              />
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1 dark:text-gray-300">{{
                $t("footerWebsite")
              }}</label>
              <input
                v-model="form.receipt_website"
                type="text"
                class="w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"
                placeholder="Mklmersin.com"
              />
            </div>
          </div>

          <button
            type="button"
            class="mt-6 px-6 py-2.5 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-700 disabled:opacity-60"
            :disabled="saving"
            @click="save"
          >
            {{ saving ? $t("saving") : $t("saveSettings") }}
          </button>
        </section>

        <section
          class="bg-slate-900 shadow rounded-xl p-6 border border-slate-700"
        >
          <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
              <h3 class="text-lg font-bold text-white">
                {{ $t("waQueueTitle") }}
              </h3>
              <p class="text-sm text-slate-300 mt-1">
                {{ $t("waQueueSubtitle") }}
              </p>
            </div>
            <label
              class="inline-flex items-center gap-2 cursor-pointer select-none"
            >
              <span class="text-sm font-semibold text-slate-200">{{
                $t("waEnabled")
              }}</span>
              <input
                v-model="form.wa_enabled"
                type="checkbox"
                class="rounded border-slate-600 bg-slate-950 text-emerald-500 focus:ring-emerald-500/40"
              />
            </label>
          </div>

          <div class="grid gap-4 sm:grid-cols-2 mb-4">
            <div class="sm:col-span-2">
              <label class="block text-sm font-semibold mb-1 text-slate-200">
                {{ $t("waBaseHost") }}
              </label>
              <input
                v-model="form.wa_base_host"
                type="url"
                dir="ltr"
                class="w-full rounded-lg bg-slate-950 border border-slate-600 text-white placeholder-slate-400"
                placeholder="https://wa.intellij-app.com"
              />
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1 text-slate-200">
                {{ $t("waTenant") }}
              </label>
              <input
                v-model="form.wa_tenant"
                type="text"
                dir="ltr"
                class="w-full rounded-lg bg-slate-950 border border-slate-600 text-white placeholder-slate-400"
                placeholder="kaml-kamal"
              />
              <p class="mt-1 text-xs text-slate-400">{{ $t("waTenantHint") }}</p>
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1 text-slate-200">
                {{ $t("waSource") }}
              </label>
              <select
                v-model="form.wa_source"
                class="w-full rounded-lg bg-slate-950 border border-slate-600 text-white"
              >
                <option v-for="src in waSources" :key="src" :value="src">
                  {{ src }}
                </option>
              </select>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-semibold mb-1 text-slate-200">
                {{ $t("waCreatedBy") }}
              </label>
              <input
                v-model="form.wa_created_by"
                type="text"
                dir="ltr"
                class="w-full rounded-lg bg-slate-950 border border-slate-600 text-white placeholder-slate-400"
                placeholder="copart-erp"
              />
            </div>
          </div>

          <div
            class="rounded-lg border border-slate-700 bg-slate-950/80 px-3 py-2 mb-5"
          >
            <p class="text-xs text-slate-400 mb-1">
              {{ $t("waQueueUrlPreview") }}
            </p>
            <code class="text-sky-300 text-sm break-all" dir="ltr">{{
              waQueueUrlPreview
            }}</code>
          </div>

          <h4 class="font-bold text-white mb-2">{{ $t("waEventsTitle") }}</h4>
          <div class="space-y-3 mb-5">
            <label
              class="flex items-center justify-between gap-3 rounded-lg border border-slate-700 bg-slate-950/60 px-4 py-3 cursor-pointer"
            >
              <div>
                <div class="font-semibold text-slate-100">
                  {{ $t("waNotifyDebt") }}
                </div>
                <div class="text-xs text-slate-400" dir="ltr">
                  event: debt_notice
                </div>
              </div>
              <input
                v-model="form.wa_notify_debt"
                type="checkbox"
                class="rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/40"
              />
            </label>
            <label
              class="flex items-center justify-between gap-3 rounded-lg border border-slate-700 bg-slate-950/60 px-4 py-3 cursor-pointer"
            >
              <div>
                <div class="font-semibold text-slate-100">
                  {{ $t("waNotifyCarCreated") }}
                </div>
                <div class="text-xs text-slate-400" dir="ltr">
                  event: car_created
                </div>
              </div>
              <input
                v-model="form.wa_notify_car_created"
                type="checkbox"
                class="rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/40"
              />
            </label>
            <label
              class="flex items-center justify-between gap-3 rounded-lg border border-slate-700 bg-slate-950/60 px-4 py-3 cursor-pointer"
            >
              <div>
                <div class="font-semibold text-slate-100">
                  {{ $t("waNotifyPayment") }}
                </div>
                <div class="text-xs text-slate-400" dir="ltr">
                  event: payment_received
                </div>
              </div>
              <input
                v-model="form.wa_notify_payment"
                type="checkbox"
                class="rounded border-slate-600 bg-slate-900 text-emerald-500 focus:ring-emerald-500/40"
              />
            </label>
          </div>

          <details
            class="rounded-lg border border-slate-700 bg-slate-950/70 p-4 mb-5"
          >
            <summary class="cursor-pointer font-semibold text-slate-100">
              {{ $t("waHowToLink") }}
            </summary>
            <div class="mt-3 space-y-2 text-sm text-slate-300 leading-relaxed">
              <p>{{ $t("waHowToLinkP1") }}</p>
              <p>{{ $t("waHowToLinkP2") }}</p>
              <p>{{ $t("waHowToLinkP3") }}</p>
              <pre
                class="mt-3 overflow-x-auto rounded-lg bg-slate-950 border border-slate-700 p-3 text-xs text-emerald-300"
                dir="ltr"
              >{{ waCurlExample }}</pre>
            </div>
          </details>

          <button
            type="button"
            class="px-6 py-2.5 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-500 disabled:opacity-60"
            :disabled="saving"
            @click="save"
          >
            {{ saving ? $t("saving") : $t("saveSettings") }}
          </button>
        </section>

        <section
          class="bg-slate-900 shadow rounded-xl p-6 border border-slate-700/60"
        >
          <h3 class="text-lg font-bold mb-1 text-slate-100">
            {{ $t("auctions") }}
          </h3>
          <p class="text-sm text-slate-400 mb-4">
            {{ $t("auction") }} — Copart / IAAI / Manheim ...
          </p>

          <TagChipList
            :items="auctions"
            :loading="auctionsLoading"
            :placeholder="$t('auction_name_placeholder')"
            :add-label="$t('add_auction')"
            :empty-label="$t('no_auctions')"
            @add="addAuction"
            @remove="removeAuction"
          />
        </section>

        <section
          class="bg-slate-900 shadow rounded-xl p-6 border border-slate-700/60"
        >
          <h3 class="text-lg font-bold mb-1 text-slate-100">
            {{ $t("shipping_routes") }}
          </h3>
          <p class="text-sm text-slate-400 mb-4">
            {{ $t("shipping_route") }}
          </p>

          <TagChipList
            :items="shippingRoutes"
            :loading="shippingRoutesLoading"
            :placeholder="$t('shipping_route_name_placeholder')"
            :add-label="$t('add_shipping_route')"
            :empty-label="$t('no_shipping_routes')"
            @add="addShippingRoute"
            @remove="removeShippingRoute"
          />
        </section>

        <section
          v-if="Number($page.props.auth.user.type_id) === 1 || Number($page.props.auth.user.type_id) === 6"
          class="bg-slate-900 shadow rounded-xl p-6 border border-amber-600/40"
        >
          <h3 class="text-lg font-bold mb-1 text-amber-300">
            {{ $t("ledger_maintenance_title") }}
          </h3>
          <p class="text-sm text-slate-300 mb-4 leading-relaxed">
            {{ $t("ledger_maintenance_hint") }}
          </p>

          <label class="mb-4 flex cursor-pointer items-center gap-2 text-sm text-slate-200">
            <input
              v-model="repairRepost"
              type="checkbox"
              class="h-4 w-4 rounded border-slate-600 bg-slate-950 text-emerald-500 focus:ring-emerald-500"
            />
            {{ $t("ledger_maintenance_repost") }}
          </label>

          <div class="mb-4 flex flex-wrap gap-2">
            <button
              type="button"
              class="min-h-[42px] rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-500 disabled:opacity-50"
              :disabled="repairBusy"
              @click="runRepairBadCarTransfers(false)"
            >
              {{ repairBusy ? $t("ledger_maintenance_running") : $t("ledger_maintenance_preview") }}
            </button>
            <button
              type="button"
              class="min-h-[42px] rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 disabled:opacity-50"
              :disabled="repairBusy"
              @click="runRepairBadCarTransfers(true)"
            >
              {{ $t("ledger_maintenance_execute") }}
            </button>
          </div>

          <p v-if="repairMessage" class="mb-2 text-sm font-semibold text-emerald-300">
            {{ repairMessage }}
          </p>
          <p v-if="repairError" class="mb-2 text-sm font-semibold text-rose-300">
            {{ repairError }}
          </p>

          <label class="mb-1 block text-xs font-semibold text-slate-300">
            {{ $t("ledger_maintenance_output") }}
          </label>
          <pre
            class="max-h-80 overflow-auto rounded-lg border border-slate-600 bg-slate-950 p-3 font-mono text-xs leading-relaxed text-slate-100 whitespace-pre-wrap"
            dir="ltr"
          >{{ repairOutput || $t("ledger_maintenance_output_empty") }}</pre>
        </section>

        <section
          v-if="Number($page.props.auth.user.type_id) === 1"
          class="bg-slate-900 shadow rounded-xl p-6 border border-rose-700/50"
        >
          <h3 class="text-lg font-bold mb-1 text-rose-300">
            {{ $t("systemResetDangerZone") }}
          </h3>
          <p class="text-sm text-slate-300 mb-4 leading-relaxed">
            {{ $t("systemResetDangerHint") }}
          </p>
          <button
            type="button"
            class="px-6 py-2.5 rounded-lg bg-rose-600 text-white font-bold hover:bg-rose-500"
            @click="showSystemReset = true"
          >
            {{ $t("systemResetButton") }}
          </button>
        </section>
      </div>
    </div>

    <!-- ─── Database Insights ─── -->
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 space-y-4">
      <!-- Vacuum hint -->
      <div v-if="dbData && dbData.free_bytes > 10 * 1024 * 1024"
        class="flex flex-wrap items-center gap-3 p-4 rounded-xl border border-amber-500/40 bg-amber-900/20">
        <span class="text-amber-300 font-medium flex-1">
          ⚠️ {{ formatDbBytes(dbData.free_bytes) }} مساحة حرة — شغّل VACUUM لتصغير الملف
        </span>
        <button :disabled="vacuuming" @click="runVacuum"
          class="px-4 py-2 bg-amber-500 text-white rounded-lg font-bold hover:bg-amber-600 disabled:opacity-60 flex items-center gap-2">
          <span v-if="vacuuming" class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full"></span>
          {{ vacuuming ? 'جارٍ التنفيذ…' : '🗜️ VACUUM' }}
        </button>
      </div>

      <section class="bg-slate-900 shadow rounded-xl p-6 border border-slate-700/60">
        <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
          <div>
            <h3 class="text-lg font-bold text-slate-100">🗄️ تخزين قاعدة البيانات</h3>
            <p class="text-sm text-slate-400">توزيع الحجم على مستوى الجداول</p>
          </div>
          <button @click="loadDbInsights" :disabled="dbLoading"
            class="px-3 py-1.5 text-sm bg-slate-700 text-slate-200 rounded-lg hover:bg-slate-600 disabled:opacity-60">
            {{ dbLoading ? '…' : '↻ تحديث' }}
          </button>
        </div>

        <div v-if="dbLoading" class="flex justify-center py-8 text-slate-400">
          <span class="animate-spin inline-block w-6 h-6 border-2 border-indigo-400 border-t-transparent rounded-full"></span>
        </div>

        <template v-else-if="dbData">
          <!-- Stats -->
          <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="bg-slate-800 rounded-lg p-3 text-center">
              <p class="text-xs text-slate-400 mb-1">الإجمالي</p>
              <p class="font-bold text-slate-100">{{ formatDbBytes(dbData.db_size) }}</p>
            </div>
            <div class="bg-slate-800 rounded-lg p-3 text-center">
              <p class="text-xs text-slate-400 mb-1">مستخدم</p>
              <p class="font-bold text-slate-100">{{ formatDbBytes(dbData.used_bytes) }}</p>
            </div>
            <div class="bg-slate-800 rounded-lg p-3 text-center">
              <p class="text-xs text-slate-400 mb-1">حر</p>
              <p class="font-bold text-emerald-400">{{ formatDbBytes(dbData.free_bytes) }}</p>
            </div>
          </div>

          <!-- Chart + list -->
          <div class="flex flex-col lg:flex-row gap-6 items-start">
            <div class="flex-shrink-0 flex justify-center">
              <div class="relative w-36 h-36">
                <div class="w-36 h-36 rounded-full" :style="{ background: dbChartGradient }"></div>
                <div class="absolute inset-0 flex items-center justify-center">
                  <div class="w-20 h-20 rounded-full bg-slate-900 border border-slate-700 flex items-center justify-center">
                    <span class="text-xs text-slate-400 text-center leading-tight">{{ dbTopTables.length }}<br>جدول</span>
                  </div>
                </div>
              </div>
            </div>
            <div class="flex-1 space-y-2">
              <div v-for="(row, idx) in dbTopTables" :key="row.name"
                class="flex items-center gap-2 text-sm">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ background: dbColors[idx % dbColors.length] }"></span>
                <code class="flex-1 text-slate-300 text-xs truncate">{{ row.name }}</code>
                <span class="text-slate-400 text-xs">{{ (row.rows ?? 0).toLocaleString() }} صف</span>
                <span class="font-semibold text-slate-100 w-16 text-right">{{ formatDbBytes(row.size_bytes) }}</span>
                <span class="text-slate-500 text-xs w-10 text-right">{{ row.percent != null ? row.percent.toFixed(1) + '%' : '' }}</span>
              </div>
            </div>
          </div>
        </template>

        <div v-else class="text-center py-6">
          <button @click="loadDbInsights" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">
            تحميل المعلومات
          </button>
        </div>
      </section>
    </div>

    <ModalSystemReset
      :show="showSystemReset"
      @close="showSystemReset = false"
      @confirm="confirmSystemReset"
    />
  </AuthenticatedLayout>
</template>
