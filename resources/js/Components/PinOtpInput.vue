<script setup>
import { ref, watch, nextTick, onMounted } from 'vue';

const props = defineProps({
  length: { type: Number, default: 6 },
  modelValue: { type: String, default: '' },
  /** idle | error | success */
  status: { type: String, default: 'idle' },
  disabled: { type: Boolean, default: false },
  autofocus: { type: Boolean, default: true },
  ariaLabelPrefix: { type: String, default: 'رقم' },
});

const emit = defineEmits(['update:modelValue', 'complete']);

const digits = ref(Array.from({ length: props.length }, () => ''));
const inputRefs = ref([]);
let completing = false;

function setInputRef(el, index) {
  if (el) inputRefs.value[index] = el;
}

function syncFromModel(value) {
  const clean = String(value || '').replace(/\D/g, '').slice(0, props.length);
  digits.value = Array.from({ length: props.length }, (_, i) => clean[i] || '');
}

watch(
  () => props.modelValue,
  (v) => {
    const joined = digits.value.join('');
    if (String(v || '') !== joined) syncFromModel(v);
  }
);

watch(
  () => props.length,
  () => {
    digits.value = Array.from({ length: props.length }, (_, i) => digits.value[i] || '');
  }
);

function emitValue() {
  const code = digits.value.join('');
  emit('update:modelValue', code);
  if (code.length === props.length && !completing) {
    completing = true;
    emit('complete', code);
    nextTick(() => {
      completing = false;
    });
  }
}

function focusAt(index) {
  const el = inputRefs.value[index];
  if (el && typeof el.focus === 'function') {
    el.focus();
    el.select?.();
  }
}

function clearAndFocus() {
  digits.value = Array.from({ length: props.length }, () => '');
  emit('update:modelValue', '');
  nextTick(() => focusAt(0));
}

function onInput(index, event) {
  if (props.disabled || props.status === 'success') return;
  const raw = String(event.target.value || '').replace(/\D/g, '');
  if (!raw) {
    digits.value[index] = '';
    emitValue();
    return;
  }
  // Take last typed digit (handles overwrite)
  const char = raw.slice(-1);
  digits.value[index] = char;
  event.target.value = char;
  emitValue();
  if (index < props.length - 1) {
    nextTick(() => focusAt(index + 1));
  }
}

function onKeydown(index, event) {
  if (props.disabled || props.status === 'success') return;
  if (event.key === 'Backspace') {
    if (digits.value[index]) {
      digits.value[index] = '';
      emit('update:modelValue', digits.value.join(''));
    } else if (index > 0) {
      digits.value[index - 1] = '';
      emit('update:modelValue', digits.value.join(''));
      nextTick(() => focusAt(index - 1));
    }
    event.preventDefault();
    return;
  }
  if (event.key === 'ArrowLeft' && index > 0) {
    event.preventDefault();
    focusAt(index - 1);
  } else if (event.key === 'ArrowRight' && index < props.length - 1) {
    event.preventDefault();
    focusAt(index + 1);
  }
}

function onPaste(event) {
  if (props.disabled || props.status === 'success') return;
  event.preventDefault();
  const pasted = String(event.clipboardData?.getData('text') || '')
    .replace(/\D/g, '')
    .slice(0, props.length);
  if (!pasted) return;
  digits.value = Array.from({ length: props.length }, (_, i) => pasted[i] || '');
  emitValue();
  const nextEmpty = digits.value.findIndex((d) => !d);
  nextTick(() => focusAt(nextEmpty === -1 ? props.length - 1 : nextEmpty));
}

function onFocus(event) {
  event.target.select?.();
}

onMounted(() => {
  syncFromModel(props.modelValue);
  if (props.autofocus) {
    nextTick(() => focusAt(0));
  }
});

defineExpose({ clearAndFocus, focusAt });
</script>

<template>
  <div
    class="pin-otp flex justify-center gap-2 sm:gap-2.5"
    dir="ltr"
    role="group"
    :aria-label="ariaLabelPrefix"
    :class="{
      'animate-pin-shake': status === 'error',
    }"
  >
    <input
      v-for="(_, index) in length"
      :key="index"
      :ref="(el) => setInputRef(el, index)"
      type="text"
      inputmode="numeric"
      pattern="[0-9]*"
      autocomplete="one-time-code"
      maxlength="1"
      :disabled="disabled || status === 'success'"
      :aria-label="`${ariaLabelPrefix} ${index + 1} من ${length}`"
      :value="digits[index]"
      class="h-12 w-10 rounded-lg border-2 !bg-white text-center text-xl font-bold !text-slate-900 shadow-sm outline-none transition focus:ring-2 sm:h-14 sm:w-11 sm:text-2xl"
      :class="{
        'border-slate-300 focus:border-sky-500 focus:ring-sky-500/30': status === 'idle',
        'border-rose-500 focus:border-rose-500 focus:ring-rose-500/30': status === 'error',
        'border-emerald-500 focus:border-emerald-500 focus:ring-emerald-500/30': status === 'success',
        'cursor-not-allowed opacity-90': disabled || status === 'success',
      }"
      @input="onInput(index, $event)"
      @keydown="onKeydown(index, $event)"
      @paste="onPaste"
      @focus="onFocus"
    />
  </div>
</template>
