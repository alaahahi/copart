<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/inertia-vue3';

const props = defineProps({
    href: String,
    active: Boolean,
    method: {
        type: String,
        default: 'get',
    },
    as: {
        type: String,
        default: 'a',
    },
});

const classes = computed(() => props.active
    ? 'inline-flex min-h-[44px] items-center justify-center rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold leading-5 text-indigo-700 shadow-sm outline-none transition duration-200 ease-out dark:border-indigo-500/30 dark:bg-indigo-500/15 dark:text-indigo-200'
    : 'inline-flex min-h-[44px] items-center justify-center rounded-xl border border-transparent px-4 py-2 text-sm font-medium leading-5 text-slate-600 outline-none transition duration-200 ease-out hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:text-slate-300 dark:hover:border-slate-700 dark:hover:bg-slate-800/80 dark:hover:text-white dark:focus-visible:ring-offset-slate-900'
);
</script>

<template>
    <Link :href="href" :method="method" :as="as" :class="classes" :aria-current="active ? 'page' : undefined">
        <slot />
    </Link>
</template>
