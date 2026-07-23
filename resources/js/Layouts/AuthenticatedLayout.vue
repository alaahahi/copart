<script setup>
import { computed, onMounted, ref } from "vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import NavLink from "@/Components/NavLink.vue";
import ResponsiveNavLink from "@/Components/ResponsiveNavLink.vue";
import { Link, usePage } from "@inertiajs/inertia-vue3";
import { useI18n } from "vue-i18n";
import DarkModeToggle from "@/Components/DarkToggle.vue";

const showingNavigationDropdown = ref(false);
const page = usePage();
const i18n = useI18n();

const user = computed(() => page.props.value.auth?.user ?? {});

const switchLocale = (locale) => {
  i18n.locale.value = locale;
  localStorage.setItem("lang", locale);
};

onMounted(() => {
  const stored = localStorage.getItem("lang");
  if (stored) {
    i18n.locale.value = stored;
  }
});

const hasRole = (...roles) => roles.includes(Number(user.value.type_id));

const primaryItems = computed(() => [
  { key: "home", label: "home", href: route("dashboard"), active: route().current("dashboard"), show: true },
  { key: "purchases", label: "purchases", href: route("purchases"), active: route().current("purchases"), show: hasRole(1, 6) },
  { key: "sales", label: "sales", href: route("sales"), active: route().current("sales"), show: true },
  { key: "clients", label: "clients", href: route("clients"), active: route().current("clients"), show: hasRole(1, 6) },
  { key: "accounting", label: "accounting", href: route("accounting"), active: route().current("accounting"), show: hasRole(1, 6) },
  { key: "analytics", label: "Analytics", href: route("analytics"), active: route().current("analytics"), show: hasRole(1, 6) },
]);

const moreItems = computed(() => [
  { key: "treasury", label: "CompanyTreasury", href: route("company_treasury"), active: route().current("company_treasury"), show: true },
  { key: "ledger", label: "Ledger", href: route("ledger"), active: route().current("ledger"), show: hasRole(1, 6) },
  { key: "sync", label: "SyncMonitor", href: route("sync-monitor"), active: route().current("sync-monitor"), show: true },
]);

const visiblePrimaryItems = computed(() => primaryItems.value.filter((item) => item.show));
const visibleMoreItems = computed(() => moreItems.value.filter((item) => item.show));
const moreMenuActive = computed(() => visibleMoreItems.value.some((item) => item.active));
</script>

<template>
  <div>
    <div class="min-h-screen bg-slate-100 text-slate-900 dark:bg-[#0b1220] dark:text-slate-100">
      <nav class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/95 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-white/85 dark:border-slate-800 dark:bg-slate-900/95 print:hidden">
        <div class="max-w-8xl mx-auto px-4 sm:px-4 lg:px-6">
          <div class="flex min-h-[72px] items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3 lg:gap-5">
              <Link
                :href="route('dashboard')"
                class="inline-flex min-h-[44px] items-center rounded-2xl px-3 py-2 text-right transition hover:bg-slate-100 dark:hover:bg-slate-800/80"
              >
                <div class="flex flex-col leading-tight">
                  <span class="text-sm font-bold text-slate-900 dark:text-white">
                    {{ $page.props.appName }}
                  </span>
                  <span class="text-xs text-slate-500 dark:text-slate-400">
                    Shipping ERP
                  </span>
                </div>
              </Link>

              <div class="hidden lg:flex lg:items-center lg:gap-1 xl:gap-2">
                <NavLink
                  v-for="item in visiblePrimaryItems"
                  :key="item.key"
                  :href="item.href"
                  :active="item.active"
                  class="whitespace-nowrap"
                >
                  {{ $t(item.label) }}
                </NavLink>

                <div v-if="visibleMoreItems.length" class="relative ms-1">
                  <Dropdown align="left" width="56">
                    <template #trigger>
                      <button
                        type="button"
                        class="inline-flex min-h-[44px] items-center justify-center gap-1.5 whitespace-nowrap rounded-xl border px-4 py-2 text-sm font-semibold outline-none transition duration-200 ease-out focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900"
                        :class="moreMenuActive
                          ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm dark:border-white dark:bg-white dark:text-slate-900'
                          : 'border-transparent text-slate-600 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-300 dark:hover:border-slate-700 dark:hover:bg-slate-800/80 dark:hover:text-white'"
                        :aria-expanded="undefined"
                        aria-haspopup="true"
                      >
                        {{ $t("more") }}
                        <svg class="h-4 w-4 opacity-80" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                          <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                      </button>
                    </template>
                    <template #content>
                      <div class="py-1">
                        <DropdownLink
                          v-for="item in visibleMoreItems"
                          :key="item.key"
                          :href="item.href"
                          :active="item.active"
                        >
                          {{ $t(item.label) }}
                        </DropdownLink>
                      </div>
                    </template>
                  </Dropdown>
                </div>
              </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:gap-2 lg:gap-3">
              <div class="rounded-2xl border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <DarkModeToggle />
              </div>

              <div class="relative">
                <Dropdown align="right" width="48">
                  <template #trigger>
                    <button
                      type="button"
                      class="inline-flex min-h-[44px] items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                      {{ $t("lang") }}
                      <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                      </svg>
                    </button>
                  </template>
                  <template #content>
                    <DropdownLink @click="switchLocale('ar')" as="button">عربي</DropdownLink>
                    <DropdownLink @click="switchLocale('en')" as="button">English</DropdownLink>
                    <DropdownLink @click="switchLocale('kr')" as="button">كردي</DropdownLink>
                  </template>
                </Dropdown>
              </div>

              <div class="relative">
                <Dropdown align="right" width="48">
                  <template #trigger>
                    <button
                      type="button"
                      class="inline-flex min-h-[44px] items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-2 text-right text-sm shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800"
                    >
                      <span class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-200">
                        {{ (user.name || 'U').slice(0, 1) }}
                      </span>
                      <span class="flex flex-col leading-tight">
                        <span class="font-semibold text-slate-800 dark:text-white">
                          {{ user.name }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                          {{ user.email }}
                        </span>
                      </span>
                      <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                      </svg>
                    </button>
                  </template>

                  <template #content>
                    <DropdownLink v-if="Number(user.type_id) === 1" :href="route('settings')">
                      {{ $t("settings") }}
                    </DropdownLink>
                    <DropdownLink :href="route('logout')" method="post" as="button">
                      {{ $t("logout") }}
                    </DropdownLink>
                  </template>
                </Dropdown>
              </div>
            </div>

            <div class="flex items-center gap-2 sm:hidden">
              <div class="rounded-2xl border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <DarkModeToggle />
              </div>
              <button
                @click="showingNavigationDropdown = !showingNavigationDropdown"
                type="button"
                class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                :aria-expanded="showingNavigationDropdown ? 'true' : 'false'"
                aria-label="فتح أو إغلاق قائمة التنقل"
              >
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                  <path
                    :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h16"
                  />
                  <path
                    :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <div v-show="showingNavigationDropdown" class="sm:hidden border-t border-slate-200 bg-white/95 px-4 pb-4 pt-4 dark:border-slate-800 dark:bg-slate-900/95">
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center gap-3">
              <span class="flex h-11 w-11 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-200">
                {{ (user.name || 'U').slice(0, 1) }}
              </span>
              <div class="min-w-0">
                <div class="truncate text-sm font-bold text-slate-900 dark:text-white">
                  {{ user.name }}
                </div>
                <div class="truncate text-xs text-slate-500 dark:text-slate-400">
                  {{ user.email }}
                </div>
              </div>
            </div>

            <div class="mt-4 space-y-4">
              <div>
                <div class="mb-2 px-1 text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                  Main
                </div>
                <div class="space-y-2">
                  <ResponsiveNavLink
                    v-for="item in visiblePrimaryItems"
                    :key="item.key"
                    :href="item.href"
                    :active="item.active"
                  >
                    {{ $t(item.label) }}
                  </ResponsiveNavLink>
                </div>
              </div>

              <div class="border-t border-slate-200 pt-4 dark:border-slate-800">
                <div class="mb-2 px-1 text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                  {{ $t("more") }}
                </div>
                <div class="space-y-2">
                  <ResponsiveNavLink
                    v-for="item in visibleMoreItems"
                    :key="item.key"
                    :href="item.href"
                    :active="item.active"
                  >
                    {{ $t(item.label) }}
                  </ResponsiveNavLink>
                </div>
              </div>

              <div class="border-t border-slate-200 pt-4 dark:border-slate-800">
                <div class="mb-3 px-1 text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                  Preferences
                </div>
                <div class="grid grid-cols-3 gap-2">
                  <button type="button" class="rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" @click="switchLocale('ar')">AR</button>
                  <button type="button" class="rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" @click="switchLocale('en')">EN</button>
                  <button type="button" class="rounded-2xl border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" @click="switchLocale('kr')">KR</button>
                </div>

                <div class="mt-3 space-y-2">
                  <ResponsiveNavLink
                    v-if="Number(user.type_id) === 1"
                    :href="route('settings')"
                    :active="route().current('settings')"
                  >
                    {{ $t("settings") }}
                  </ResponsiveNavLink>
                  <ResponsiveNavLink
                    :href="route('logout')"
                    method="post"
                    as="button"
                  >
                    {{ $t("logout") }}
                  </ResponsiveNavLink>
                </div>
              </div>
            </div>
          </div>
        </div>
      </nav>

      <header class="border-b border-slate-200/70 bg-white/80 shadow-sm dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-200" v-if="$slots.header">
        <div class="max-w-7xl mx-auto px-4 py-5 sm:px-6 lg:px-8">
          <slot name="header" />
        </div>
      </header>

      <main class="min-h-[calc(100vh-4.5rem)] bg-slate-100 dark:bg-[#0b1220]">
        <slot />
      </main>
    </div>
  </div>
</template>

<style>
.max-w-8xl {
  max-width: 95rem;
}
</style>