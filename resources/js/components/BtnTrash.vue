<template>
  <button class="btn btn-sm btn-trash" type="button" :disabled="disabled">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="btn-trash__icon">
      <polyline points="3 6 5 6 21 6" />
      <path d="M19 6l-1 14H6L5 6" />
      <path d="M10 11v6" />
      <path d="M14 11v6" />
      <path d="M9 6V4h6v2" />
    </svg>
    <slot />
  </button>
</template>

<script setup>
defineProps({
  disabled: {
    type: Boolean,
    default: false,
  },
})
</script>

<style scoped>
.btn-trash {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}
.btn-trash__icon {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
}
</style>
