<script setup>
import { computed, reactive, watch } from "vue";

const props = defineProps({
  show: { type: Boolean, default: false },
  mode: { type: String, default: "create" }, // create | edit
  account: { type: Object, default: null },
  parentOptions: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
});

const emit = defineEmits(["close", "submit"]);

const form = reactive({
  code: "",
  name_ar: "",
  name: "",
  type: "asset",
  currency: "multi",
  parent_id: "",
  is_active: true,
});

const isEdit = computed(() => props.mode === "edit");
const title = computed(() => (isEdit.value ? "تعديل حساب" : "إضافة حساب جديد"));
const canEditCode = computed(() => {
  if (!isEdit.value) return true;
  return !!props.account?.can_edit_code;
});

const filteredParents = computed(() => {
  const type = form.type;
  const selfId = props.account?.id;
  return (props.parentOptions || []).filter((p) => {
    if (p.type !== type) return false;
    if (selfId && p.id === selfId) return false;
    return true;
  });
});

watch(
  () => [props.show, props.account, props.mode],
  () => {
    if (!props.show) return;
    if (isEdit.value && props.account) {
      form.code = props.account.code || "";
      form.name_ar = props.account.name_ar || props.account.name || "";
      form.name = props.account.name_en || props.account.name || "";
      form.type = props.account.type || "asset";
      form.currency = props.account.currency || "multi";
      form.parent_id = props.account.parent_id || "";
      form.is_active = true;
    } else {
      form.code = "";
      form.name_ar = "";
      form.name = "";
      form.type = "asset";
      form.currency = "multi";
      form.parent_id = "";
      form.is_active = true;
    }
  },
  { immediate: true }
);

function onSubmit() {
  emit("submit", {
    code: form.code,
    name_ar: form.name_ar,
    name: form.name || form.name_ar,
    type: form.type,
    currency: form.currency === "multi" ? null : form.currency,
    parent_id: form.parent_id || null,
    is_active: form.is_active,
  });
}
</script>

<template>
  <Teleport to="body">
    <Transition name="coa-modal">
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
        role="dialog"
        aria-modal="true"
        @click.self="emit('close')"
      >
        <div class="w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
          <div class="flex items-start justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-700">
            <div>
              <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ title }}</h2>
              <p class="text-xs text-slate-500 dark:text-slate-400">شجرة الحسابات · قيد مزدوج</p>
            </div>
            <button
              type="button"
              class="rounded-lg px-2 py-1 text-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
              @click="emit('close')"
            >
              إغلاق
            </button>
          </div>

          <form class="space-y-3 p-4" @submit.prevent="onSubmit">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">الرمز *</label>
                <input
                  v-model="form.code"
                  type="text"
                  class="w-full rounded-lg border-slate-300 font-mono text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                  :disabled="!canEditCode || submitting"
                  placeholder="مثال: 5110"
                  required
                />
                <p v-if="isEdit && !canEditCode" class="mt-1 text-[11px] text-amber-600 dark:text-amber-400">
                  الرمز مقفل (حساب نظامي أو عليه قيود)
                </p>
              </div>
              <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">النوع *</label>
                <select
                  v-model="form.type"
                  class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                  :disabled="(isEdit && !canEditCode) || submitting"
                  required
                >
                  <option value="asset">أصول</option>
                  <option value="liability">خصوم</option>
                  <option value="equity">حقوق ملكية</option>
                  <option value="income">إيرادات</option>
                  <option value="expense">مصاريف</option>
                </select>
              </div>
            </div>

            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">الاسم العربي *</label>
              <input
                v-model="form.name_ar"
                type="text"
                class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                placeholder="اسم الحساب"
                :disabled="submitting"
                required
              />
            </div>

            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">الاسم الإنجليزي (اختياري)</label>
              <input
                v-model="form.name"
                type="text"
                class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                placeholder="English name"
                :disabled="submitting"
              />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">العملة</label>
                <select
                  v-model="form.currency"
                  class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                  :disabled="(isEdit && !canEditCode) || submitting"
                >
                  <option value="multi">متعدد / بدون تقييد</option>
                  <option value="$">USD</option>
                  <option value="IQD">IQD</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-300">الحساب الأب</label>
                <select
                  v-model="form.parent_id"
                  class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white"
                  :disabled="submitting"
                >
                  <option value="">— بدون أب (جذر) —</option>
                  <option v-for="p in filteredParents" :key="p.id" :value="p.id">
                    {{ p.code }} — {{ p.name }}
                  </option>
                </select>
              </div>
            </div>

            <label v-if="!isEdit" class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
              <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" :disabled="submitting" />
              حساب نشط
            </label>

            <div class="grid grid-cols-2 gap-2 w-full pt-2">
              <button
                type="button"
                class="w-full rounded-lg bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                :disabled="submitting"
                @click="emit('close')"
              >
                إلغاء
              </button>
              <button
                type="submit"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50"
                :disabled="submitting"
              >
                {{ submitting ? "جاري الحفظ..." : isEdit ? "حفظ التعديلات" : "إضافة الحساب" }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.coa-modal-enter-active,
.coa-modal-leave-active {
  transition: opacity 0.15s ease;
}
.coa-modal-enter-from,
.coa-modal-leave-to {
  opacity: 0;
}
</style>
