<script setup>
import { ref, watch, nextTick, onMounted } from 'vue';

const props = defineProps({
  length: { type: Number, default: 5 },
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
      'pin-otp--error': status === 'error',
      'pin-otp--success': status === 'success',
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
      class="pin-otp__digit h-12 w-10 rounded-lg text-center text-xl font-bold shadow-sm sm:h-14 sm:w-11 sm:text-2xl"
      :class="{
        'cursor-not-allowed opacity-90': disabled || status === 'success',
      }"
      @input="onInput(index, $event)"
      @keydown="onKeydown(index, $event)"
      @paste="onPaste"
      @focus="onFocus"
    />
  </div>
</template>

<style scoped>
/*
 * Always light boxes + dark digits — beats html.dark main input { color: white; bg: navy }.
 * Focus uses border/ring only (never dark-blue fill that hides digits in other states).
 */
.pin-otp__digit {
  appearance: none;
  -webkit-appearance: none;
  background-color: #ffffff !important;
  border: 2px solid #94a3b8 !important;
  color: #111111 !important;
  -webkit-text-fill-color: #111111 !important;
  caret-color: #111111 !important;
  outline: none !important;
  box-shadow: none !important;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.pin-otp__digit:hover:not(:disabled) {
  border-color: #64748b !important;
}

.pin-otp__digit:focus {
  background-color: #ffffff !important;
  border-color: #3b82f6 !important;
  color: #111111 !important;
  -webkit-text-fill-color: #111111 !important;
  caret-color: #111111 !important;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.35) !important;
}

.pin-otp--error .pin-otp__digit,
.pin-otp--error .pin-otp__digit:focus {
  border-color: #f43f5e !important;
  box-shadow: 0 0 0 3px rgba(244, 63, 94, 0.3) !important;
}

.pin-otp--success .pin-otp__digit,
.pin-otp--success .pin-otp__digit:focus {
  border-color: #10b981 !important;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3) !important;
}

.pin-otp__digit:disabled {
  background-color: #f8fafc !important;
  color: #111111 !important;
  -webkit-text-fill-color: #111111 !important;
}

/* Chrome/Safari autofill can force pale text on dark theme — lock contrast */
.pin-otp__digit:-webkit-autofill,
.pin-otp__digit:-webkit-autofill:hover,
.pin-otp__digit:-webkit-autofill:focus {
  -webkit-text-fill-color: #111111 !important;
  caret-color: #111111 !important;
  box-shadow: 0 0 0 1000px #ffffff inset !important;
  transition: background-color 9999s ease-in-out 0s;
}
</style>
