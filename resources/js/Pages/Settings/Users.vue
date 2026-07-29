<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm, usePage } from "@inertiajs/inertia-vue3";
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";
import { useToast } from "vue-toastification";

const { t } = useI18n();
const toast = useToast();
const page = usePage();

const props = defineProps({
  users: { type: Object, required: true },
  userTypes: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({ q: "" }) },
  authUserId: { type: Number, default: null },
});

const search = ref(props.filters?.q || "");
const modal = ref(null); // 'create' | 'edit' | 'password' | 'delete' | null
const selected = ref(null);
const saving = ref(false);

/** Settings UI: مدير (admin) only — never حساب/محاسبة */
const managerTypes = computed(() =>
  (props.userTypes || []).filter((type) => type.name === "admin")
);

const flashSuccess = computed(() => page.props.value?.flash?.success || "");

watch(
  flashSuccess,
  (msg) => {
    if (msg) toast.success(msg);
  },
  { immediate: true }
);

const createForm = useForm({
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  type_id: "",
  phone: "",
});

const editForm = useForm({
  name: "",
  email: "",
  type_id: "",
  phone: "",
  is_band: false,
});

const passwordForm = useForm({
  password: "",
  password_confirmation: "",
});

const deleteForm = useForm({});

function typeLabel(name) {
  if (!name) return "—";
  const key = `userType_${name}`;
  const translated = t(key);
  return translated === key ? name : translated;
}

function openCreate() {
  createForm.reset();
  createForm.clearErrors();
  createForm.type_id = managerTypes.value[0]?.id || "";
  modal.value = "create";
}

function openEdit(user) {
  selected.value = user;
  editForm.clearErrors();
  editForm.name = user.name || "";
  editForm.email = user.email || "";
  editForm.type_id = user.type_id;
  editForm.phone = user.phone || "";
  editForm.is_band = !!user.is_band;
  modal.value = "edit";
}

function openPassword(user) {
  selected.value = user;
  passwordForm.reset();
  passwordForm.clearErrors();
  modal.value = "password";
}

function openDelete(user) {
  selected.value = user;
  deleteForm.clearErrors();
  modal.value = "delete";
}

function closeModal() {
  modal.value = null;
  selected.value = null;
}

function submitCreate() {
  saving.value = true;
  createForm.post(route("settings.users.store"), {
    preserveScroll: true,
    onSuccess: () => closeModal(),
    onFinish: () => {
      saving.value = false;
    },
  });
}

function submitEdit() {
  if (!selected.value) return;
  saving.value = true;
  editForm.put(route("settings.users.update", selected.value.id), {
    preserveScroll: true,
    onSuccess: () => closeModal(),
    onFinish: () => {
      saving.value = false;
    },
  });
}

function submitPassword() {
  if (!selected.value) return;
  saving.value = true;
  passwordForm.put(route("settings.users.password", selected.value.id), {
    preserveScroll: true,
    onSuccess: () => closeModal(),
    onFinish: () => {
      saving.value = false;
    },
  });
}

function submitDelete() {
  if (!selected.value) return;
  saving.value = true;
  deleteForm.delete(route("settings.users.destroy", selected.value.id), {
    preserveScroll: true,
    onSuccess: () => closeModal(),
    onError: (errors) => {
      const msg =
        errors?.user ||
        Object.values(errors || {})?.[0] ||
        t("userDeleteFailed");
      toast.error(Array.isArray(msg) ? msg[0] : msg);
    },
    onFinish: () => {
      saving.value = false;
    },
  });
}

function doSearch() {
  window.location.href = route("settings.users", {
    q: search.value || undefined,
  });
}
</script>

<template>
  <Head :title="$t('userManagement')" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2
          class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight"
        >
          {{ $t("settings") }} — {{ $t("userManagement") }}
        </h2>
        <Link
          :href="route('settings')"
          class="px-4 py-2 rounded-lg bg-slate-700 text-slate-100 text-sm font-semibold hover:bg-slate-600"
        >
          {{ $t("backToSettings") }}
        </Link>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-5">
        <section
          class="bg-slate-900 shadow rounded-xl p-5 sm:p-6 border border-slate-700"
        >
          <div
            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5"
          >
            <div>
              <h3 class="text-lg font-bold text-white">
                {{ $t("userManagement") }}
              </h3>
              <p class="text-sm text-slate-300 mt-1">
                {{ $t("userManagementHint") }}
              </p>
            </div>
            <button
              type="button"
              class="px-4 py-2.5 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-500"
              @click="openCreate"
            >
              {{ $t("addUser") }}
            </button>
          </div>

          <form
            class="flex flex-col sm:flex-row gap-2 mb-4"
            @submit.prevent="doSearch"
          >
            <input
              v-model="search"
              type="search"
              class="flex-1 rounded-lg border border-slate-600 bg-slate-950 text-white placeholder-slate-400 px-3 py-2 text-sm focus:ring-emerald-500/40 focus:border-emerald-500"
              :placeholder="$t('searchUsers')"
            />
            <button
              type="submit"
              class="px-4 py-2 rounded-lg bg-slate-700 text-slate-100 text-sm font-semibold hover:bg-slate-600"
            >
              {{ $t("filter") }}
            </button>
          </form>

          <div class="overflow-x-auto rounded-lg border border-slate-700">
            <table class="w-full text-sm text-center">
              <thead class="bg-slate-800 text-slate-100">
                <tr>
                  <th class="px-3 py-2.5 border-b border-slate-700">#</th>
                  <th class="px-3 py-2.5 border-b border-slate-700">
                    {{ $t("name") }}
                  </th>
                  <th class="px-3 py-2.5 border-b border-slate-700">
                    {{ $t("username") }}
                  </th>
                  <th class="px-3 py-2.5 border-b border-slate-700">
                    {{ $t("userRole") }}
                  </th>
                  <th class="px-3 py-2.5 border-b border-slate-700">
                    {{ $t("status") }}
                  </th>
                  <th class="px-3 py-2.5 border-b border-slate-700">
                    {{ $t("actions") }}
                  </th>
                </tr>
              </thead>
              <tbody class="bg-slate-900 text-slate-100">
                <tr v-if="!users.data?.length">
                  <td colspan="6" class="px-3 py-8 text-slate-400">
                    {{ $t("noUsers") }}
                  </td>
                </tr>
                <tr
                  v-for="user in users.data"
                  :key="user.id"
                  class="border-t border-slate-800 hover:bg-slate-800/50"
                >
                  <td class="px-3 py-2.5">{{ user.id }}</td>
                  <td class="px-3 py-2.5 font-semibold">{{ user.name }}</td>
                  <td class="px-3 py-2.5" dir="ltr">{{ user.email }}</td>
                  <td class="px-3 py-2.5">
                    <span
                      class="inline-flex px-2 py-0.5 rounded bg-slate-800 text-sky-300 border border-slate-600 text-xs font-semibold"
                    >
                      {{ typeLabel(user.type_name) }}
                    </span>
                  </td>
                  <td class="px-3 py-2.5">
                    <span
                      v-if="user.is_band"
                      class="inline-flex px-2 py-0.5 rounded bg-rose-900/60 text-rose-300 border border-rose-700/50 text-xs font-semibold"
                    >
                      {{ $t("userBanned") }}
                    </span>
                    <span
                      v-else
                      class="inline-flex px-2 py-0.5 rounded bg-emerald-900/50 text-emerald-300 border border-emerald-700/40 text-xs font-semibold"
                    >
                      {{ $t("userActive") }}
                    </span>
                  </td>
                  <td class="px-3 py-2.5">
                    <div class="flex flex-wrap items-center justify-center gap-1.5">
                      <button
                        type="button"
                        class="px-2 py-1 rounded bg-slate-700 text-slate-100 text-xs font-semibold hover:bg-slate-600"
                        @click="openEdit(user)"
                      >
                        {{ $t("edit") }}
                      </button>
                      <button
                        type="button"
                        class="px-2 py-1 rounded bg-sky-800 text-sky-100 text-xs font-semibold hover:bg-sky-700"
                        @click="openPassword(user)"
                      >
                        {{ $t("changePassword") }}
                      </button>
                      <button
                        v-if="!user.is_self && !user.is_system_vault"
                        type="button"
                        class="px-2 py-1 rounded bg-rose-800 text-rose-100 text-xs font-semibold hover:bg-rose-700"
                        @click="openDelete(user)"
                      >
                        {{ $t("delete") }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div
            v-if="users.last_page > 1"
            class="mt-4 flex flex-wrap items-center justify-center gap-2"
            dir="ltr"
          >
            <Link
              v-for="(link, idx) in users.links"
              :key="idx"
              :href="link.url || '#'"
              class="px-3 py-1.5 rounded text-sm border"
              :class="
                link.active
                  ? 'bg-emerald-600 border-emerald-500 text-white'
                  : link.url
                    ? 'bg-slate-800 border-slate-600 text-slate-200 hover:bg-slate-700'
                    : 'bg-slate-900 border-slate-800 text-slate-600 pointer-events-none'
              "
              v-html="link.label"
            />
          </div>
        </section>
      </div>
    </div>

    <!-- Create / Edit / Password / Delete modals — dark-safe (teleported) -->
    <Teleport to="body">
      <div
        v-if="modal"
        class="fixed inset-0 z-[9998] flex items-center justify-center bg-slate-950/70 p-3 sm:p-4"
        role="dialog"
        aria-modal="true"
        dir="rtl"
        @click.self="closeModal"
      >
        <div
          class="flex w-full max-w-md max-h-[90vh] flex-col overflow-hidden rounded-xl border border-slate-600 bg-slate-900 text-slate-100 shadow-2xl"
        >
          <div class="shrink-0 border-b border-slate-700 px-4 py-3.5 sm:px-5">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                  {{ $t("userManagement") }}
                </p>
                <h2 class="mt-0.5 text-lg font-bold text-white">
                  <template v-if="modal === 'create'">{{ $t("addUser") }}</template>
                  <template v-else-if="modal === 'edit'">{{ $t("editUser") }}</template>
                  <template v-else-if="modal === 'password'">{{
                    $t("changePassword")
                  }}</template>
                  <template v-else>{{ $t("deleteUser") }}</template>
                </h2>
              </div>
              <button
                type="button"
                class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white"
                @click="closeModal"
              >
                ✕
              </button>
            </div>
          </div>

          <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-5 space-y-3">
            <!-- Create -->
            <template v-if="modal === 'create'">
              <div>
                <label class="block text-sm font-semibold text-slate-200 mb-1">{{
                  $t("name")
                }}</label>
                <input
                  v-model="createForm.name"
                  type="text"
                  class="w-full rounded-lg border border-slate-600 bg-slate-950 text-white placeholder-slate-400 px-3 py-2 text-sm"
                />
                <p v-if="createForm.errors.name" class="mt-1 text-xs text-rose-300">
                  {{ createForm.errors.name }}
                </p>
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-200 mb-1">{{
                  $t("username")
                }}</label>
                <input
                  v-model="createForm.email"
                  type="text"
                  dir="ltr"
                  class="w-full rounded-lg border border-slate-600 bg-slate-950 text-white placeholder-slate-400 px-3 py-2 text-sm"
                />
                <p v-if="createForm.errors.email" class="mt-1 text-xs text-rose-300">
                  {{ createForm.errors.email }}
                </p>
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-200 mb-1">{{
                  $t("password")
                }}</label>
                <input
                  v-model="createForm.password"
                  type="password"
                  class="w-full rounded-lg border border-slate-600 bg-slate-950 text-white placeholder-slate-400 px-3 py-2 text-sm"
                />
                <p v-if="createForm.errors.password" class="mt-1 text-xs text-rose-300">
                  {{ createForm.errors.password }}
                </p>
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-200 mb-1">{{
                  $t("confirmPassword")
                }}</label>
                <input
                  v-model="createForm.password_confirmation"
                  type="password"
                  class="w-full rounded-lg border border-slate-600 bg-slate-950 text-white placeholder-slate-400 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-200 mb-1">{{
                  $t("userRole")
                }}</label>
                <select
                  v-model="createForm.type_id"
                  class="w-full rounded-lg border border-slate-600 bg-slate-950 text-white px-3 py-2 text-sm"
                >
                  <option
                    v-for="type in managerTypes"
                    :key="type.id"
                    :value="type.id"
                  >
                    {{ typeLabel(type.name) }}
                  </option>
                </select>
                <p v-if="createForm.errors.type_id" class="mt-1 text-xs text-rose-300">
                  {{ createForm.errors.type_id }}
                </p>
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-200 mb-1">{{
                  $t("phone")
                }}</label>
                <input
                  v-model="createForm.phone"
                  type="text"
                  class="w-full rounded-lg border border-slate-600 bg-slate-950 text-white placeholder-slate-400 px-3 py-2 text-sm"
                />
              </div>
            </template>

            <!-- Edit -->
            <template v-else-if="modal === 'edit'">
              <div>
                <label class="block text-sm font-semibold text-slate-200 mb-1">{{
                  $t("name")
                }}</label>
                <input
                  v-model="editForm.name"
                  type="text"
                  class="w-full rounded-lg border border-slate-600 bg-slate-950 text-white placeholder-slate-400 px-3 py-2 text-sm"
                />
                <p v-if="editForm.errors.name" class="mt-1 text-xs text-rose-300">
                  {{ editForm.errors.name }}
                </p>
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-200 mb-1">{{
                  $t("username")
                }}</label>
                <input
                  v-model="editForm.email"
                  type="text"
                  dir="ltr"
                  class="w-full rounded-lg border border-slate-600 bg-slate-950 text-white placeholder-slate-400 px-3 py-2 text-sm"
                />
                <p v-if="editForm.errors.email" class="mt-1 text-xs text-rose-300">
                  {{ editForm.errors.email }}
                </p>
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-200 mb-1">{{
                  $t("userRole")
                }}</label>
                <select
                  v-model="editForm.type_id"
                  class="w-full rounded-lg border border-slate-600 bg-slate-950 text-white px-3 py-2 text-sm"
                >
                  <option
                    v-for="type in managerTypes"
                    :key="type.id"
                    :value="type.id"
                  >
                    {{ typeLabel(type.name) }}
                  </option>
                </select>
                <p v-if="editForm.errors.type_id" class="mt-1 text-xs text-rose-300">
                  {{ editForm.errors.type_id }}
                </p>
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-200 mb-1">{{
                  $t("phone")
                }}</label>
                <input
                  v-model="editForm.phone"
                  type="text"
                  class="w-full rounded-lg border border-slate-600 bg-slate-950 text-white placeholder-slate-400 px-3 py-2 text-sm"
                />
              </div>
              <label
                class="flex items-center gap-2 text-sm text-slate-200 cursor-pointer"
              >
                <input
                  v-model="editForm.is_band"
                  type="checkbox"
                  class="rounded border-slate-600 bg-slate-900 text-rose-500 focus:ring-rose-500/40"
                />
                {{ $t("banUser") }}
              </label>
            </template>

            <!-- Password -->
            <template v-else-if="modal === 'password'">
              <p class="text-sm text-slate-300 mb-2">
                {{ selected?.name }}
                <span class="text-slate-400" dir="ltr">({{ selected?.email }})</span>
              </p>
              <div>
                <label class="block text-sm font-semibold text-slate-200 mb-1">{{
                  $t("newPassword")
                }}</label>
                <input
                  v-model="passwordForm.password"
                  type="password"
                  class="w-full rounded-lg border border-slate-600 bg-slate-950 text-white placeholder-slate-400 px-3 py-2 text-sm"
                />
                <p
                  v-if="passwordForm.errors.password"
                  class="mt-1 text-xs text-rose-300"
                >
                  {{ passwordForm.errors.password }}
                </p>
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-200 mb-1">{{
                  $t("confirmPassword")
                }}</label>
                <input
                  v-model="passwordForm.password_confirmation"
                  type="password"
                  class="w-full rounded-lg border border-slate-600 bg-slate-950 text-white placeholder-slate-400 px-3 py-2 text-sm"
                />
              </div>
            </template>

            <!-- Delete -->
            <template v-else>
              <p class="text-sm text-slate-200 leading-relaxed">
                {{ $t("confirmDeleteUser") }}
              </p>
              <p class="text-sm font-semibold text-white">
                {{ selected?.name }}
                <span class="text-slate-400 font-normal" dir="ltr"
                  >({{ selected?.email }})</span
                >
              </p>
            </template>
          </div>

          <div
            class="shrink-0 border-t border-slate-700 px-4 py-3 sm:px-5 flex gap-2"
          >
            <button
              type="button"
              class="flex-1 py-2.5 rounded-lg bg-slate-700 text-slate-100 font-semibold hover:bg-slate-600"
              @click="closeModal"
            >
              {{ $t("cancel") }}
            </button>
            <button
              v-if="modal === 'create'"
              type="button"
              class="flex-1 py-2.5 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-500 disabled:opacity-60"
              :disabled="saving || createForm.processing"
              @click="submitCreate"
            >
              {{ $t("save") }}
            </button>
            <button
              v-else-if="modal === 'edit'"
              type="button"
              class="flex-1 py-2.5 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-500 disabled:opacity-60"
              :disabled="saving || editForm.processing"
              @click="submitEdit"
            >
              {{ $t("save") }}
            </button>
            <button
              v-else-if="modal === 'password'"
              type="button"
              class="flex-1 py-2.5 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-500 disabled:opacity-60"
              :disabled="saving || passwordForm.processing"
              @click="submitPassword"
            >
              {{ $t("changePassword") }}
            </button>
            <button
              v-else
              type="button"
              class="flex-1 py-2.5 rounded-lg bg-rose-700 text-white font-bold hover:bg-rose-600 disabled:opacity-60"
              :disabled="saving || deleteForm.processing"
              @click="submitDelete"
            >
              {{ $t("delete") }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AuthenticatedLayout>
</template>
