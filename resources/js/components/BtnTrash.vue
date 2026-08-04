<script setup>
import BaseButton from './BaseButton.vue'
import { computed } from 'vue'

const props = defineProps({
  disabled: { type: Boolean, default: false },
  variant: { type: String, default: 'item' }, // 'item' = blue outline | 'danger' = red border
})

const btnVariant = computed(() => (props.variant === 'danger' ? 'danger' : 'outline'))
</script>

<template>
  <BaseButton type="button" :variant="btnVariant" :disabled="disabled" class="btn-trash">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="btn-icon btn-trash__icon">
      <polyline points="3 6 5 6 21 6" />
      <path d="M19 6l-1 14H6L5 6" />
      <path d="M10 11v6" />
      <path d="M14 11v6" />
      <path d="M9 6V4h6v2" />
    </svg>
    <slot />
  </BaseButton>
</template>

<style scoped>
.btn-trash__icon {
  width: 14px;
  height: 14px;
}
</style>