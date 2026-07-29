<script setup>
import { computed } from "vue";
import { useI18n } from "vue-i18n";

const props = defineProps({
  url: {
    type: String,
    default: "https://intellij-app.com/",
  },
  brand: {
    type: String,
    default: "IntelliJ App",
  },
});

const { t } = useI18n();
const year = computed(() => new Date().getFullYear());
</script>

<template>
  <p
    class="intellij-credit text-center text-xs text-slate-500 dark:text-slate-400 print:hidden"
    dir="rtl"
  >
    © {{ year }} · {{ t("poweredBy") }}
    <a
      :href="url"
      target="_blank"
      rel="noopener noreferrer"
      class="font-medium text-slate-600 underline-offset-2 transition hover:text-emerald-600 hover:underline dark:text-slate-300 dark:hover:text-emerald-300"
    >
      {{ brand }}
    </a>
  </p>
</template>
