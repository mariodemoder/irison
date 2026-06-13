<template>
  <button class="btn btn-sm" :disabled="disabled || saving" v-bind="$attrs">
    <slot>{{ saving ? savingText : label }}</slot>
  </button>
</template>

<script setup>
defineProps({
  disabled: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
  label: { type: String, default: 'Guardar' },
  savingText: { type: String, default: 'Guardando...' },
})
</script>
