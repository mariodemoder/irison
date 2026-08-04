<script setup>
import { computed } from 'vue'

const props = defineProps({
  type: { type: String, default: 'button' },
  variant: { type: String, default: 'outline' },
  size: { type: String, default: 'sm' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})

const classes = computed(() => {
  const list = ['btn', `btn-${props.size}`]
  if (props.variant) list.push(`btn-${props.variant}`)
  return list
})
</script>

<template>
  <button
    :type="type"
    :class="classes"
    :disabled="disabled || loading"
    v-bind="$attrs"
  >
    <slot />
  </button>
</template>