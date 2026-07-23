<script setup>
import { Link } from '@inertiajs/inertia-vue3';

defineProps({
  href: {
    type: String,
    default: null,
  },
  active: {
    type: Boolean,
    default: false,
  },
  method: {
    type: String,
    default: 'get',
  },
  as: {
    type: String,
    default: 'a',
  },
});
</script>

<template>
  <Link
    :href="href || '#'"
    :method="method"
    :as="as"
    class="block w-full px-4 py-2.5 text-left text-sm leading-5 transition duration-150 ease-in-out focus:outline-none"
    :class="active
      ? 'bg-indigo-50 font-bold text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-200'
      : 'text-gray-700 hover:bg-gray-100 dark:text-slate-200 dark:hover:bg-slate-800'"
  >
    <slot />
  </Link>
</template>
