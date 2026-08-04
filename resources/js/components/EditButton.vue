<script setup>
import BaseButton from './BaseButton.vue'

defineProps({
  disabled: { type: Boolean, default: false },
  label: { type: String, default: 'Editar' },
  to: { type: [String, Object], default: null },
})
</script>

<template>
  <RouterLink
    v-if="to"
    :to="to"
    class="btn btn-sm btn-edit"
    v-bind="$attrs"
  >
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="btn-icon">
      <path d="M12 20h9" />
      <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
    </svg>
    <slot>{{ label }}</slot>
  </RouterLink>
  <BaseButton
    v-else
    variant="edit"
    :disabled="disabled"
    v-bind="$attrs"
  >
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="btn-icon">
      <path d="M12 20h9" />
      <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
    </svg>
    <slot>{{ label }}</slot>
  </BaseButton>
</template>