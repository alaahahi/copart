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
    ? 'block min-h-[48px] rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-base font-semibold text-indigo-700 shadow-sm transition duration-200 ease-out dark:border-indigo-500/30 dark:bg-indigo-500/15 dark:text-indigo-200'
    : 'block min-h-[48px] rounded-2xl border border-transparent px-4 py-3 text-base font-medium text-slate-700 transition duration-200 ease-out hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:text-slate-200 dark:hover:border-slate-700 dark:hover:bg-slate-800/80 dark:hover:text-white dark:focus-visible:ring-offset-slate-900'
);
</script>

<template>
    <Link :href="href" :method="method" :as="as" :class="classes" :aria-current="active ? 'page' : undefined">
        <slot />
    </Link>
</template>
