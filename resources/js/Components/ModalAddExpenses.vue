<script setup>
/**
 * مودال وصل صرف / مصاريف — يعيد استخدام ModalAddSales بنفس الحقول والتنسيق.
 */
import ModalAddSales from '@/Components/ModalAddSales.vue';

defineProps({
  show: Boolean,
  boxes: Array,
  title: {
    type: String,
    default: 'وصل صرف',
  },
  subtitle: {
    type: String,
    default: 'سحب مبلغ من الصندوق بالدولار أو الدينار',
  },
});

const emit = defineEmits(['close', 'a']);
</script>

<template>
  <ModalAddSales
    :show="show"
    variant="payment"
    :title="title"
    :subtitle="subtitle"
    confirm-label="تأكيد الصرف"
    @a="emit('a', $event)"
    @close="emit('close')"
  />
</template>
