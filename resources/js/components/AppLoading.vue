<template>
  <div class="app-loading" :class="{ compact }" role="status" aria-live="polite">
    <component :is="spinnerIcon" class="spinner" />
    <span class="message">{{ message }}</span>
  </div>
</template>

<script setup>
import spinnerIcon from '../shared/loadingSpinnerIcon'

defineProps({
  message: {
    type: String,
    default: 'Cargando...',
  },
  compact: {
    type: Boolean,
    default: false,
  },
})
</script>

<style scoped>
.app-loading {
  min-height: 120px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: #4b5563;
  font-size: 14px;
  font-weight: 600;
}

.app-loading.compact {
  min-height: 44px;
  justify-content: flex-start;
  padding: 4px 0;
}

.spinner {
  width: 24px;
  height: 24px;
  animation: spin 0.9s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
