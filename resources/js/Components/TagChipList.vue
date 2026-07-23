<script setup>
/**
 * Reusable "chip list" manager: shows existing items as removable chips and
 * an input + add button to append a new one. Presentational only — the
 * parent owns the data/API calls (see Settings/Index.vue's "المزادات"
 * section) so this same pattern can be reused for other simple named lists.
 */
import { ref } from "vue";

const props = defineProps({
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  placeholder: { type: String, default: "" },
  addLabel: { type: String, default: "إضافة" },
  emptyLabel: { type: String, default: "لا توجد عناصر" },
  itemKey: { type: String, default: "id" },
  itemLabel: { type: String, default: "name" },
});

const emit = defineEmits(["add", "remove"]);

const newName = ref("");

function submit() {
  const name = newName.value.trim();
  if (!name) return;
  emit("add", name);
  newName.value = "";
}
</script>

<template>
  <div class="tag-chip-list">
    <div class="tag-chip-row">
      <span v-for="item in items" :key="item[itemKey]" class="tag-chip">
        {{ item[itemLabel] }}
        <button
          type="button"
          class="tag-chip-remove"
          :aria-label="`حذف ${item[itemLabel]}`"
          @click="emit('remove', item)"
        >
          &times;
        </button>
      </span>
      <span v-if="!loading && !items.length" class="tag-chip-empty">{{ emptyLabel }}</span>
    </div>
    <div class="tag-chip-input-row">
      <input
        v-model="newName"
        type="text"
        class="tag-chip-input"
        :placeholder="placeholder"
        @keyup.enter="submit"
      />
      <button type="button" class="tag-chip-add-btn" @click="submit">
        {{ addLabel }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.tag-chip-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.tag-chip-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  min-height: 2.25rem;
}

.tag-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.35rem 0.75rem;
  border-radius: 999px;
  font-size: 0.8rem;
  font-weight: 600;
  color: #e2e8f0;
  background: rgba(56, 189, 248, 0.14);
  border: 1px solid rgba(56, 189, 248, 0.35);
  white-space: nowrap;
}

.tag-chip-remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.1rem;
  height: 1.1rem;
  line-height: 1;
  border-radius: 999px;
  color: #94a3b8;
  font-size: 0.85rem;
  transition: background-color 0.15s ease, color 0.15s ease;
}
.tag-chip-remove:hover {
  background: rgba(248, 113, 113, 0.2);
  color: #f87171;
}

.tag-chip-empty {
  font-size: 0.8rem;
  color: #64748b;
  padding: 0.35rem 0;
}

.tag-chip-input-row {
  display: flex;
  gap: 0.5rem;
}

.tag-chip-input {
  flex: 1 1 auto;
  min-width: 0;
  border-radius: 0.55rem;
  border: 1px solid rgba(100, 116, 139, 0.45);
  background-color: rgba(15, 23, 42, 0.7);
  color: #f1f5f9;
  padding: 0.55rem 0.75rem;
  font-size: 0.875rem;
}
.tag-chip-input::placeholder {
  color: #64748b;
}
.tag-chip-input:focus {
  outline: none;
  border-color: #38bdf8;
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.25);
}

.tag-chip-add-btn {
  flex-shrink: 0;
  padding: 0.55rem 1.1rem;
  border-radius: 0.55rem;
  font-size: 0.85rem;
  font-weight: 700;
  color: #fff;
  background-color: #0ea5e9;
  transition: filter 0.15s ease;
}
.tag-chip-add-btn:hover {
  filter: brightness(1.08);
}
</style>
