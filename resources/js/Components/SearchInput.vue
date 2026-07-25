<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  id: { type: String, default: undefined },
  placeholder: { type: String, default: '' },
  type: { type: String, default: 'search' },
  disabled: { type: Boolean, default: false },
  inputClass: { type: String, default: '' },
  autocomplete: { type: String, default: 'off' },
});

const emit = defineEmits(['update:modelValue', 'input']);

const mergedClass = computed(() =>
  [
    'erp-search__input block w-full min-h-[42px] rounded-lg border border-slate-300 bg-white py-2 text-sm text-slate-900 shadow-sm',
    'focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-500/20',
    'dark:border-slate-600 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-400',
    props.inputClass,
  ].join(' ')
);

function onInput(e) {
  emit('update:modelValue', e.target.value);
  emit('input', e);
}
</script>

<template>
  <div class="erp-search relative">
    <div
      class="erp-search__icon pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3"
      aria-hidden="true"
    >
      <svg class="h-5 w-5 text-slate-400 dark:text-slate-500" fill="currentColor" viewBox="0 0 20 20">
        <path
          fill-rule="evenodd"
          d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
          clip-rule="evenodd"
        />
      </svg>
    </div>
    <input
      :id="id"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :autocomplete="autocomplete"
      :class="mergedClass"
      @input="onInput"
    />
  </div>
</template>
