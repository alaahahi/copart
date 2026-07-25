<script setup>
/**
 * مودال سحب من القاصة — نفس مكوّن وصل الصرف (ModalAddSales)، يختلف العنوان فقط.
 */
import ModalAddSales from '@/Components/ModalAddSales.vue';

defineProps({
  show: Boolean,
  boxes: Array,
  sum_transactions: [Number, String],
  sum_transactions_dinar: [Number, String],
  tagOptions: { type: Array, default: () => [] },
  showTagSelect: { type: Boolean, default: false },
  title: {
    type: String,
    default: 'سحب من القاصة',
  },
  subtitle: {
    type: String,
    default: 'تسجيل سحب بالدولار أو الدينار',
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
    :tag-options="tagOptions"
    :show-tag-select="showTagSelect"
    confirm-label="تأكيد السحب"
    @a="emit('a', $event)"
    @close="emit('close')"
  />
</template>
