<script setup>
import { Link } from '@inertiajs/inertia-vue3';
</script>

<template>
    <Link class="block w-full px-4 py-2 border dark:border-gray-900 text-left text-sm leading-5 text-gray-700  focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out">
        <slot />
    </Link>
</template>
