<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/inertia-vue3";
import { ref, computed, onMounted } from "vue";
import axios from "axios";

const status = ref("pending");
const rows = ref([]);
const pendingCount = ref(0);
const loading = ref(false);
const actingId = ref(null);
const message = ref("");
const error = ref("");

const tabs = [
  { key: "pending", label: "بانتظار الموافقة" },
  { key: "approved", label: "موافق عليها" },
  { key: "rejected", label: "مرفوضة" },
];

const title = computed(() => {
  if (pendingCount.value > 0) {
    return `وارد Vinstack (${pendingCount.value})`;
  }
  return "وارد Vinstack";
});

async function load() {
  loading.value = true;
  error.value = "";
  try {
    const { data } = await axios.get("/api/vinstack-imports", {
      params: { status: status.value },
    });
    rows.value = data.data || [];
    pendingCount.value = data.meta?.pending_count ?? 0;
  } catch (e) {
    error.value = e.response?.data?.message || "تعذر تحميل القائمة";
  } finally {
    loading.value = false;
  }
}

function setTab(key) {
  status.value = key;
  load();
}

async function approve(row) {
  if (!confirm(`الموافقة على إدخال السيارة ${row.vin} للمخزون؟`)) {
    return;
  }
  actingId.value = row.id;
  message.value = "";
  error.value = "";
  try {
    const { data } = await axios.post(`/api/vinstack-imports/${row.id}/approve`);
    message.value = data.message || "تمت الموافقة";
    await load();
  } catch (e) {
    error.value = e.response?.data?.message || "فشلت الموافقة";
  } finally {
    actingId.value = null;
  }
}

async function reject(row) {
  const reason = prompt(`سبب رفض ${row.vin} (اختياري):`) ?? "";
  if (reason === null) {
    return;
  }
  actingId.value = row.id;
  message.value = "";
  error.value = "";
  try {
    const { data } = await axios.post(`/api/vinstack-imports/${row.id}/reject`, {
      reason: reason || null,
    });
    message.value = data.message || "تم الرفض";
    await load();
  } catch (e) {
    error.value = e.response?.data?.message || "فشل الرفض";
  } finally {
    actingId.value = null;
  }
}

onMounted(load);
</script>

<template>
  <Head :title="title" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
        موافقة واردات Vinstack
      </h2>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <div class="bg-white dark:bg-slate-900 shadow-sm sm:rounded-lg p-4 border border-slate-200 dark:border-slate-700">
          <p class="text-sm text-slate-600 dark:text-slate-300 mb-4">
            السيارات المُصدَّرة من vinstack تظهر هنا أولاً. بعد الموافقة تدخل للمخزون ويمكنك إكمال الأسعار/المحاسبة كالمعتاد.
          </p>

          <div class="flex flex-wrap gap-2 mb-4">
            <button
              v-for="tab in tabs"
              :key="tab.key"
              type="button"
              class="px-3 py-1.5 rounded-md text-sm border"
              :class="status === tab.key
                ? 'bg-teal-600 text-white border-teal-600'
                : 'bg-transparent text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-600'"
              @click="setTab(tab.key)"
            >
              {{ tab.label }}
              <span v-if="tab.key === 'pending' && pendingCount" class="ms-1 font-bold">({{ pendingCount }})</span>
            </button>
            <button
              type="button"
              class="ms-auto px-3 py-1.5 rounded-md text-sm border border-slate-300 dark:border-slate-600"
              :disabled="loading"
              @click="load"
            >
              تحديث
            </button>
          </div>

          <div v-if="message" class="mb-3 text-sm text-emerald-700 dark:text-emerald-300">{{ message }}</div>
          <div v-if="error" class="mb-3 text-sm text-rose-600 dark:text-rose-300">{{ error }}</div>
          <div v-if="loading" class="text-sm text-slate-500">جاري التحميل...</div>

          <div v-else-if="!rows.length" class="text-sm text-slate-500 py-8 text-center">
            لا توجد طلبات في هذه القائمة.
          </div>

          <div v-else class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="border-b border-slate-200 dark:border-slate-700 text-right text-slate-500">
                  <th class="py-2 px-2">VIN</th>
                  <th class="py-2 px-2">السيارة</th>
                  <th class="py-2 px-2">التاجر</th>
                  <th class="py-2 px-2">تفاصيل</th>
                  <th class="py-2 px-2">الوقت</th>
                  <th class="py-2 px-2">إجراء</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in rows"
                  :key="row.id"
                  class="border-b border-slate-100 dark:border-slate-800"
                >
                  <td class="py-3 px-2 font-mono dir-ltr text-left">{{ row.vin }}</td>
                  <td class="py-3 px-2">
                    <div>{{ [row.make, row.model, row.year].filter(Boolean).join(' ') || '—' }}</div>
                  </td>
                  <td class="py-3 px-2">
                    <div>{{ row.dealer_company || row.dealer_name || '—' }}</div>
                    <div class="text-xs text-slate-500 dir-ltr">{{ row.dealer_phone }}</div>
                  </td>
                  <td class="py-3 px-2 text-xs text-slate-600 dark:text-slate-300">
                    <div v-if="row.payload_summary.lot">Lot: {{ row.payload_summary.lot }}</div>
                    <div v-if="row.payload_summary.auction">مزاد: {{ row.payload_summary.auction }}</div>
                    <div v-if="row.payload_summary.destination">وجهة: {{ row.payload_summary.destination }}</div>
                    <div v-if="row.payload_summary.images_count">صور: {{ row.payload_summary.images_count }}</div>
                    <div v-if="row.reject_reason" class="text-rose-600">رفض: {{ row.reject_reason }}</div>
                    <div v-if="row.error_message" class="text-rose-600">خطأ: {{ row.error_message }}</div>
                    <div v-if="row.car_id">car #{{ row.car_id }}</div>
                  </td>
                  <td class="py-3 px-2 text-xs dir-ltr text-left">
                    <div>{{ row.created_at }}</div>
                    <div v-if="row.reviewed_at" class="text-slate-500">{{ row.reviewed_at }}</div>
                  </td>
                  <td class="py-3 px-2 whitespace-nowrap">
                    <template v-if="row.status === 'pending'">
                      <button
                        type="button"
                        class="px-2 py-1 rounded bg-teal-600 text-white text-xs me-1 disabled:opacity-50"
                        :disabled="actingId === row.id"
                        @click="approve(row)"
                      >
                        موافقة
                      </button>
                      <button
                        type="button"
                        class="px-2 py-1 rounded bg-rose-600 text-white text-xs disabled:opacity-50"
                        :disabled="actingId === row.id"
                        @click="reject(row)"
                      >
                        رفض
                      </button>
                    </template>
                    <span v-else class="text-xs text-slate-500">{{ row.status }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
