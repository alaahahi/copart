<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/inertia-vue3";
import { ref, computed, onMounted } from "vue";
import axios from "axios";

const activeTab = ref("migrations");

// ===== Migrations =====
const migrations = ref([]);
const loadingMigrations = ref(false);
const runningMigration = ref(null);
const migrationOutput = ref("");
const migrationWarning = ref(null);
const showExecutedMigrations = ref(false);
const migrationStats = ref({ total_pending: 0, total_executed: 0 });
const migrationMsg = ref("");

async function loadMigrations() {
  loadingMigrations.value = true;
  migrationMsg.value = "";
  try {
    const res = await axios.get("/api/sync-monitor/migrations", {
      params: { show_executed: showExecutedMigrations.value },
    });
    migrations.value = res.data.migrations || [];
    migrationStats.value = {
      total_pending: res.data.total_pending || 0,
      total_executed: res.data.total_executed || 0,
    };
  } catch (e) {
    migrationMsg.value = e.response?.data?.error || "تعذر تحميل المايجريشنز";
  } finally {
    loadingMigrations.value = false;
  }
}

async function runSpecificMigration(name, force = false) {
  runningMigration.value = name;
  migrationOutput.value = "";
  migrationWarning.value = null;
  migrationMsg.value = "";
  try {
    const res = await axios.post("/api/sync-monitor/run-migration", {
      migration_name: name,
      force,
    });
    if (res.data.success) {
      migrationOutput.value = res.data.output || "تم التنفيذ بنجاح";
      migrationMsg.value = "تم تنفيذ المايجريشن بنجاح";
      await loadMigrations();
    }
  } catch (e) {
    const data = e.response?.data;
    if (data?.warning) {
      migrationWarning.value = {
        migration: name,
        table: data.table,
        record_count: data.record_count,
        message: data.message,
      };
    } else {
      migrationMsg.value = data?.error || "فشل تنفيذ المايجريشن";
      migrationOutput.value = data?.output || "";
    }
  } finally {
    runningMigration.value = null;
  }
}

function forceRunMigration(name) {
  migrationWarning.value = null;
  runSpecificMigration(name, true);
}

// ===== Logs =====
const logEntries = ref([]);
const loadingLogs = ref(false);
const logInfo = ref({ size: 0, modified: null });
const logLevelFilter = ref("all");
const logLines = ref(300);
const logMsg = ref("");

async function loadLogs() {
  loadingLogs.value = true;
  logMsg.value = "";
  try {
    const res = await axios.get("/api/sync-monitor/logs", {
      params: { lines: logLines.value },
    });
    logEntries.value = res.data.entries || [];
    logInfo.value = { size: res.data.size || 0, modified: res.data.modified || null };
    if (res.data.message) logMsg.value = res.data.message;
  } catch (e) {
    logMsg.value = e.response?.data?.error || "تعذر تحميل اللوغ";
  } finally {
    loadingLogs.value = false;
  }
}

async function clearLogs() {
  if (!confirm("هل تريد تفريغ ملف اللوغ بالكامل؟")) return;
  try {
    await axios.post("/api/sync-monitor/clear-logs");
    logEntries.value = [];
    logMsg.value = "تم تفريغ اللوغ";
  } catch (e) {
    logMsg.value = e.response?.data?.error || "تعذر التفريغ";
  }
}

const filteredLogs = computed(() => {
  if (logLevelFilter.value === "all") return logEntries.value;
  return logEntries.value.filter((l) => l.level === logLevelFilter.value);
});

function levelClass(level) {
  switch (level) {
    case "error":
    case "critical":
    case "emergency":
    case "alert":
      return "bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200";
    case "warning":
      return "bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200";
    case "info":
    case "notice":
      return "bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200";
    default:
      return "bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200";
  }
}

const expanded = ref({});
function toggleStack(idx) {
  expanded.value[idx] = !expanded.value[idx];
}

function formatSize(bytes) {
  if (!bytes) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${(bytes / Math.pow(k, i)).toFixed(1)} ${sizes[i]}`;
}

function switchTab(tab) {
  activeTab.value = tab;
  if (tab === "logs" && !logEntries.value.length) loadLogs();
}

onMounted(loadMigrations);
</script>

<template>
  <Head title="مراقب المزامنة | Sync Monitor" />
  <AuthenticatedLayout>
    <div class="max-w-6xl mx-auto py-6 px-4" dir="rtl">
      <h1 class="text-2xl font-bold mb-6 dark:text-gray-100">🛠️ أدوات النظام</h1>

      <!-- Tabs -->
      <div class="flex border-b border-gray-200 dark:border-gray-700 mb-6">
        <button
          @click="switchTab('migrations')"
          :class="[
            'px-6 py-3 text-sm font-medium border-b-2 transition-colors',
            activeTab === 'migrations'
              ? 'border-orange-500 text-orange-600 dark:text-orange-400'
              : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400',
          ]"
        >
          📦 المايجريشنز
        </button>
        <button
          @click="switchTab('logs')"
          :class="[
            'px-6 py-3 text-sm font-medium border-b-2 transition-colors',
            activeTab === 'logs'
              ? 'border-orange-500 text-orange-600 dark:text-orange-400'
              : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400',
          ]"
        >
          📋 اللوغ
        </button>
      </div>

      <!-- ===== Migrations Tab ===== -->
      <div v-if="activeTab === 'migrations'" class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
          <div>
            <h3 class="text-xl font-semibold dark:text-gray-200 mb-1">📦 إدارة المايجريشنز</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">تنفيذ مايجريشن محدد من قاعدة البيانات</p>
          </div>
          <div class="flex gap-2 items-center">
            <label class="flex items-center text-sm text-gray-700 dark:text-gray-300">
              <input type="checkbox" v-model="showExecutedMigrations" @change="loadMigrations" class="ml-2" />
              إظهار المنفذة
            </label>
            <button
              @click="loadMigrations"
              :disabled="loadingMigrations"
              class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
            >
              {{ loadingMigrations ? "⏳ جاري..." : "🔄 تحديث" }}
            </button>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-6">
          <div class="bg-orange-50 dark:bg-orange-900/30 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-orange-600">{{ migrationStats.total_pending }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">معلّقة / معروضة</div>
          </div>
          <div class="bg-green-50 dark:bg-green-900/30 rounded-lg p-4 text-center">
            <div class="text-2xl font-bold text-green-600">{{ migrationStats.total_executed }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">منفّذة</div>
          </div>
        </div>

        <div v-if="migrationMsg" class="mb-4 p-3 rounded bg-gray-100 dark:bg-gray-700 text-sm dark:text-gray-200">
          {{ migrationMsg }}
        </div>

        <div v-if="loadingMigrations" class="text-center py-12 text-gray-500">⏳ جاري التحميل...</div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead>
              <tr class="text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                <th class="px-4 py-3">المايجريشن</th>
                <th class="px-4 py-3">التاريخ</th>
                <th class="px-4 py-3">الحالة</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr
                v-for="migration in migrations"
                :key="migration.file"
                :class="{ 'opacity-60': migration.executed }"
              >
                <td class="px-4 py-3">
                  <div class="font-medium dark:text-gray-200">{{ migration.name }}</div>
                  <div class="text-xs text-gray-400">{{ migration.file }}</div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ migration.date }}</td>
                <td class="px-4 py-3">
                  <span
                    v-if="migration.executed"
                    class="px-2 py-1 text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded-full"
                    >منفّذة</span
                  >
                  <span
                    v-else
                    class="px-2 py-1 text-xs bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 rounded-full"
                    >معلّقة</span
                  >
                </td>
                <td class="px-4 py-3">
                  <button
                    v-if="!migration.executed"
                    @click="runSpecificMigration(migration.name)"
                    :disabled="runningMigration === migration.name"
                    class="px-3 py-1.5 bg-green-600 text-white text-sm rounded hover:bg-green-700 disabled:opacity-50"
                  >
                    {{ runningMigration === migration.name ? "⏳" : "▶️ تنفيذ" }}
                  </button>
                </td>
              </tr>
              <tr v-if="migrations.length === 0">
                <td colspan="4" class="px-4 py-8 text-center text-gray-500">لا توجد مايجريشنز</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Warning -->
        <div
          v-if="migrationWarning"
          class="mt-6 bg-yellow-50 dark:bg-yellow-900/40 border-r-4 border-yellow-500 p-4 rounded-lg"
        >
          <h4 class="font-bold text-yellow-800 dark:text-yellow-200 mb-2">⚠️ تحذير: يوجد بيانات</h4>
          <p class="text-sm text-yellow-700 dark:text-yellow-300 mb-3">{{ migrationWarning.message }}</p>
          <div class="flex gap-2">
            <button
              @click="forceRunMigration(migrationWarning.migration)"
              class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm"
            >
              تنفيذ إجباري (حذف البيانات)
            </button>
            <button
              @click="migrationWarning = null"
              class="px-4 py-2 bg-gray-300 dark:bg-gray-600 dark:text-gray-100 rounded text-sm"
            >
              إلغاء
            </button>
          </div>
        </div>

        <!-- Output -->
        <div v-if="migrationOutput" class="mt-6 border border-gray-200 dark:border-gray-600 rounded-lg">
          <div class="flex justify-between items-center px-4 py-2 bg-gray-50 dark:bg-gray-700">
            <h4 class="font-semibold dark:text-gray-200">📋 نتيجة التنفيذ</h4>
            <button @click="migrationOutput = ''" class="text-sm text-gray-500 hover:text-gray-700">✕</button>
          </div>
          <pre class="text-xs font-mono bg-gray-900 text-green-400 p-4 rounded-b overflow-x-auto max-h-64 overflow-y-auto">{{ migrationOutput }}</pre>
        </div>
      </div>

      <!-- ===== Logs Tab ===== -->
      <div v-if="activeTab === 'logs'" class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
          <div>
            <h3 class="text-xl font-semibold dark:text-gray-200 mb-1">📋 سجل الأخطاء (laravel.log)</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">
              <span v-if="logInfo.modified">آخر تعديل: {{ logInfo.modified }} · </span>
              الحجم: {{ formatSize(logInfo.size) }}
            </p>
          </div>
          <div class="flex gap-2 items-center flex-wrap">
            <select
              v-model="logLevelFilter"
              class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded"
            >
              <option value="all">كل المستويات</option>
              <option value="error">أخطاء</option>
              <option value="warning">تحذيرات</option>
              <option value="info">معلومات</option>
            </select>
            <select
              v-model.number="logLines"
              @change="loadLogs"
              class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded"
            >
              <option :value="100">100 سطر</option>
              <option :value="300">300 سطر</option>
              <option :value="1000">1000 سطر</option>
            </select>
            <button
              @click="loadLogs"
              :disabled="loadingLogs"
              class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
            >
              {{ loadingLogs ? "⏳" : "🔄 تحديث" }}
            </button>
            <button @click="clearLogs" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
              🗑️ تفريغ
            </button>
          </div>
        </div>

        <div v-if="logMsg" class="mb-4 p-3 rounded bg-gray-100 dark:bg-gray-700 text-sm dark:text-gray-200">
          {{ logMsg }}
        </div>

        <div v-if="loadingLogs" class="text-center py-12 text-gray-500">⏳ جاري التحميل...</div>

        <div v-else class="space-y-2">
          <div
            v-for="(log, idx) in filteredLogs"
            :key="idx"
            class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden"
          >
            <div
              class="flex items-start gap-3 p-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50"
              @click="log.stack && toggleStack(idx)"
            >
              <span :class="['px-2 py-0.5 rounded text-xs font-bold uppercase shrink-0', levelClass(log.level)]">
                {{ log.level }}
              </span>
              <span class="text-xs text-gray-400 shrink-0 font-mono">{{ log.time }}</span>
              <span class="text-sm dark:text-gray-200 break-all flex-1">{{ log.message }}</span>
              <span v-if="log.stack" class="text-gray-400 text-xs shrink-0">{{ expanded[idx] ? "▲" : "▼" }}</span>
            </div>
            <pre
              v-if="log.stack && expanded[idx]"
              class="text-xs font-mono bg-gray-900 text-gray-300 p-3 overflow-x-auto max-h-80 overflow-y-auto whitespace-pre-wrap"
            >{{ log.stack }}</pre>
          </div>
          <div v-if="filteredLogs.length === 0" class="text-center py-12 text-gray-500">لا توجد سجلات</div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
