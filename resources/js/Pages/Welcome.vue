<script setup>
import { Head, Link } from '@inertiajs/inertia-vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
    config:Object
})
</script>

<template>
    <Head title="Welcome" />
    <div class="relative flex items-top justify-center min-h-screen bg-gray-100 dark:bg-gray-900 sm:items-center sm:pt-0">
        <div v-if="canLogin" class=" fixed top-0 right-0 px-6 py-4 sm:block">
            <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="text-sm text-gray-700 dark:text-gray-500 underline">Dashboard</Link>

            <template v-else>
                <Link :href="route('login')" class="px-6 py-2 text-white bg-rose-500 rounded-md focus:outline-none">تسجيل الدخول</Link>

                <Link v-if="false" :href="route('register')" class="ml-4 text-sm text-gray-700 dark:text-gray-500 underline">تسجيل</Link>
            </template>
        </div>

        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8" style="border-radius: 25px;padding:0 ;background-color: #282634;">
            <img src="img/logo-color.png" alt="karbala"   style="margin: auto;width: 100%;border-radius: 25px;" />
      

        

            <!-- <div class="flex justify-center mt-4 sm:items-center ">
    

                <div class="ml-4 text-center text-white text-xl">
                    {{config['first_title_ar']}}
                    <br>
                    {{config['second_title_ar'] }}
                    <br>
                    {{config['third_title_ar'] }}
                    <br>
                </div>
            </div> -->
        </div>
    </div>
</template>
