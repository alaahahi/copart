<script setup>
import { usePage } from '@inertiajs/inertia-vue3';
import { computed } from 'vue';
import { resolvePublicAsset } from '@/utils/resolvePublicAsset';

const page = usePage();
const coverUrl = computed(() =>
  resolvePublicAsset(page.props.value.branding?.cover || '/img/logo-color.png')
);
const logoUrl = computed(() =>
  resolvePublicAsset(page.props.value.branding?.logo || '')
);
</script>

<template>
    <div
      class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-slate-900"
      :style="{ backgroundImage: `url('${coverUrl}')`, backgroundSize: 'cover', backgroundPosition: 'center' }"
    >
        <div
          v-if="logoUrl"
          class="mb-2 rounded-2xl bg-slate-900/70 border border-slate-700 px-4 py-3 backdrop-blur"
        >
          <img :src="logoUrl" alt="" class="max-h-14 max-w-[200px] object-contain" />
        </div>

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg dark:bg-slate-800 dark:border dark:border-slate-700">
            <slot />
        </div>
    </div>
</template>
