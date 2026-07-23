<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import TagChipList from "@/Components/TagChipList.vue";
import { Head } from "@inertiajs/inertia-vue3";
import { ref, onMounted } from "vue";
import axios from "axios";
import { useI18n } from "vue-i18n";
import { useToast } from "vue-toastification";

const { t } = useI18n();
const toast = useToast();

const props = defineProps({
  config: Object,
});

// المزادات (Auction houses e.g. Copart / IAAI / Manheim) — managed here as a
// reusable chip list; the same list populates the المزاد select on the car
// add/edit forms (purchases/Sales/Clients pages).
const auctions = ref([]);
const auctionsLoading = ref(false);

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
    toast.error(e.response?.data?.message || e.response?.data?.errors?.name?.[0] || t("settingsFailed"));
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

onMounted(loadAuctions);

const logoFields = [
  { key: "receipt_logo_left_1", labelKey: "logoLeft1" },
  { key: "receipt_logo_left_2", labelKey: "logoLeft2" },
  { key: "receipt_logo_left_3", labelKey: "logoLeft3" },
  { key: "receipt_logo_haulf", labelKey: "logoHaulf" },
  { key: "receipt_logo_main", labelKey: "logoMain" },
];

const form = ref({
  receipt_template: props.config?.receipt_template || "default",
  receipt_phone: props.config?.receipt_phone || "",
  receipt_address: props.config?.receipt_address || "",
  receipt_website: props.config?.receipt_website || "",
  first_title_ar: props.config?.first_title_ar || "",
  second_title_ar: props.config?.second_title_ar || "",
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

function onLogoChange(field, event) {
  const file = event.target.files?.[0];
  if (!file) return;
  logoFiles.value[field] = file;
  logoPreviews.value[field] = URL.createObjectURL(file);
}

function logoSrc(field) {
  return logoPreviews.value[field] || logoPaths.value[field] || "";
}

async function save() {
  saving.value = true;
  errorMsg.value = "";
  successMsg.value = "";
  try {
    const formData = new FormData();
    Object.entries(form.value).forEach(([key, val]) => {
      formData.append(key, val ?? "");
    });
    logoFields.forEach(({ key }) => {
      if (logoFiles.value[key]) {
        formData.append(key, logoFiles.value[key]);
      }
    });

    const { data } = await axios.post(route("settings.update"), formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    if (data.config) {
      logoFields.forEach(({ key }) => {
        if (data.config[key]) {
          logoPaths.value[key] = data.config[key];
        }
      });
    }

    logoFiles.value = {};
    logoPreviews.value = {};
    successMsg.value = t("settingsSaved");
  } catch (e) {
    errorMsg.value = e.response?.data?.message || t("settingsFailed");
  } finally {
    saving.value = false;
  }
}

function preview(type) {
  window.open(`${route("settings.receipt_preview")}?type=${type}`, "_blank");
}
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
          v-if="successMsg"
          class="rounded-lg bg-green-100 text-green-800 px-4 py-3 text-sm font-semibold"
        >
          {{ successMsg }}
        </div>
        <div
          v-if="errorMsg"
          class="rounded-lg bg-red-100 text-red-800 px-4 py-3 text-sm font-semibold"
        >
          {{ errorMsg }}
        </div>

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
              <label
                class="block text-sm font-semibold mb-2 dark:text-gray-300"
              >
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
              <label class="block text-sm font-semibold mb-1 dark:text-gray-300"
                >{{ $t("footerPhone") }}</label
              >
              <input
                v-model="form.receipt_phone"
                type="text"
                class="w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"
                placeholder="+964 750 468 0510 / 750 705 3555"
              />
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1 dark:text-gray-300"
                >{{ $t("footerAddress") }}</label
              >
              <input
                v-model="form.receipt_address"
                type="text"
                class="w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"
                placeholder="100 M road near Hanouf motel"
              />
            </div>
            <div>
              <label class="block text-sm font-semibold mb-1 dark:text-gray-300"
                >{{ $t("footerWebsite") }}</label
              >
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
            class="mt-6 px-6 py-2.5 rounded-lg bg-blue-600 text-white font-bold hover:bg-blue-700 disabled:opacity-60"
            :disabled="saving"
            @click="save"
          >
            {{ saving ? $t("saving") : $t("saveSettings") }}
          </button>
        </section>

        <section class="bg-slate-900 shadow rounded-xl p-6 border border-slate-700/60">
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
      </div>
    </div>
  </AuthenticatedLayout>
</template>
