<template>
    <button @click="onBtn">Edit</button>
  </template>
  
  <script setup>
  import { defineProps } from 'vue';
  
  const { rowIndex, model, save, close } = defineProps(['rowIndex', 'model', 'save', 'close']);
  
  const onBtn = (e) => {
    const event = new CustomEvent('cell', { bubbles: true, detail: { row: model } });
    $el.dispatchEvent(event);
    e.stopPropagation();
    if (typeof close === 'function') {
      close();
    }
  };
  </script>
  