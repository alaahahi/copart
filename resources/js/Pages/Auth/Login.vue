<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/inertia-vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    // Default on: pairs with SESSION_LIFETIME=43200 + remember cookie (30 days).
    remember: true
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div v-if="status" class="mb-4 font-medium text-sm text-emerald-400">
            {{ status }}
        </div>

        <form class="login-form space-y-5" @submit.prevent="submit">
            <div>
                <InputLabel for="email" :value="$t('username')" />
                <TextInput
                    id="email"
                    type="text"
                    class="login-form__input mt-1.5 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    :placeholder="$t('username')"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" :value="$t('password')" />
                <TextInput
                    id="password"
                    type="password"
                    class="login-form__input mt-1.5 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    :placeholder="$t('password')"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="pt-0.5">
                <label class="login-form__remember flex items-center gap-2.5 cursor-pointer select-none">
                    <Checkbox name="remember" v-model:checked="form.remember" class="login-form__checkbox" />
                    <span class="text-sm font-medium text-white">{{ $t('remember') }}</span>
                </label>
            </div>

            <div class="pt-1">
                <button
                    type="submit"
                    class="login-form__submit w-full inline-flex items-center justify-center rounded-md px-4 py-3 text-sm font-semibold tracking-wide text-white transition duration-150 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-800 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="form.processing"
                >
                    {{ $t('login') }}
                </button>
            </div>
        </form>
    </GuestLayout>
</template>

<style scoped>
/* Scoped to Login only — do not change shared TextInput globally. */
.login-form :deep(.login-form__input) {
    background-color: #ffffff !important;
    color: #1a1a1a !important;
    border-color: #cbd5e1 !important;
    caret-color: #1a1a1a;
    box-shadow: none;
}

.login-form :deep(.login-form__input::placeholder) {
    color: #94a3b8 !important;
    opacity: 1;
}

.login-form :deep(.login-form__input:focus) {
    border-color: #38bdf8 !important;
    box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.35) !important;
    outline: none;
}

/* Chrome/Safari autofill keeps white field but can force pale text — lock contrast. */
.login-form :deep(.login-form__input:-webkit-autofill),
.login-form :deep(.login-form__input:-webkit-autofill:hover),
.login-form :deep(.login-form__input:-webkit-autofill:focus) {
    -webkit-text-fill-color: #1a1a1a !important;
    caret-color: #1a1a1a;
    box-shadow: 0 0 0 1000px #ffffff inset !important;
    transition: background-color 9999s ease-in-out 0s;
}

.login-form :deep(.login-form__checkbox) {
    border-color: #94a3b8;
    background-color: #ffffff;
    color: #0284c7;
}

.login-form :deep(.login-form__checkbox:focus) {
    border-color: #38bdf8;
    box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.35);
}

.login-form__submit {
    background-color: #0284c7;
    border: 1px solid #0369a1;
}

.login-form__submit:hover:not(:disabled) {
    background-color: #0369a1;
}

.login-form__submit:active:not(:disabled) {
    background-color: #075985;
}
</style>
