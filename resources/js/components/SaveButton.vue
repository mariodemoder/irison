<script setup>
import BaseButton from './BaseButton.vue'
import SpinnerCircleIcon from './icons/SpinnerCircleIcon.vue'

defineProps({
  disabled: { type: Boolean, default: false },
  saving: { type: Boolean, default: false },
  label: { type: String, default: 'Guardar' },
  savingText: { type: String, default: 'Guardando...' },
  type: { type: String, default: 'submit' },
})
</script>

<template>
  <BaseButton
    :type="type"
    variant="primary"
    size="md"
    class="save-button"
    :disabled="disabled"
    :loading="saving"
  >
    <span v-if="saving" class="btn-icon save-button__spinner">
      <SpinnerCircleIcon />
    </span>
    <slot>{{ saving ? savingText : label }}</slot>
  </BaseButton>
</template>

<style scoped>
.save-button__spinner {
  display: inline-flex;
}
</style>