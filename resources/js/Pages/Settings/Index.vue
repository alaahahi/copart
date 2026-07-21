<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/inertia-vue3";
import { ref } from "vue";
import axios from "axios";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
  config: Object,
});

const form = ref({
  receipt_template: props.config?.receipt_template || "default",
  receipt_phone: props.config?.receipt_phone || "",
  receipt_address: props.config?.receipt_address || "",
  receipt_website: props.config?.receipt_website || "",
  first_title_ar: props.config?.first_title_ar || "",
  second_title_ar: props.config?.second_title_ar || "",
});

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

async function save() {
  saving.value = true;
  errorMsg.value = "";
  successMsg.value = "";
  try {
    await axios.post(route("settings.update"), form.value);
    successMsg.value = t("settingsSaved");
  } catch (e) {
    errorMsg.value = e.response?.data?.message || t("settingsFailed");
  } finally {
    saving.value = false;
  }
}

function preview(type) {
  window.open(
    `${route("settings.receipt_preview")}?type=${type}`,
    "_blank"
  );
}
</script>

<template>
  <Head :title="$t('settings')" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
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
                <div class="font-bold dark:text-gray-100">{{ $t(tpl.titleKey) }}</div>
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
            بيانات التذييل (قالب MKL)
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

          <p class="text-xs text-gray-500 dark:text-gray-400 mt-4">
            ضع شعارات الشركاء (Copart, AA, ...) في المجلد
            <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">public/img/receipt/</code>
          </p>

          <button
            type="button"
            class="mt-6 px-6 py-2.5 rounded-lg bg-blue-600 text-white font-bold hover:bg-blue-700 disabled:opacity-60"
            :disabled="saving"
            @click="save"
          >
            {{ saving ? $t("saving") : $t("saveSettings") }}
          </button>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
