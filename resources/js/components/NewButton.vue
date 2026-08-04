<script setup>
import BaseButton from './BaseButton.vue'

defineProps({
  disabled: { type: Boolean, default: false },
  label: { type: String, default: 'Nuevo' },
  to: { type: [String, Object], default: null },
  type: { type: String, default: 'button' },
})
</script>

<template>
  <RouterLink
    v-if="to"
    :to="to"
    class="btn btn-sm btn-outline"
    v-bind="$attrs"
  >
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="btn-icon">
      <circle cx="12" cy="12" r="10" />
      <path d="M12 8v8" />
      <path d="M8 12h8" />
    </svg>
    <slot>{{ label }}</slot>
  </RouterLink>
  <BaseButton
    v-else
    variant="outline"
    :type="type"
    :disabled="disabled"
    v-bind="$attrs"
  >
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="btn-icon">
      <circle cx="12" cy="12" r="10" />
      <path d="M12 8v8" />
      <path d="M8 12h8" />
    </svg>
    <slot>{{ label }}</slot>
  </BaseButton>
</template>