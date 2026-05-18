<template>
  <div
    v-if="visible"
    class="error-alert"
    :class="`error-alert--${variant}`"
    role="alert"
    aria-live="assertive"
  >
    <div class="error-alert__icon" aria-hidden="true">
      <slot name="icon">!</slot>
    </div>

    <div class="error-alert__body">
      <div v-if="title" class="error-alert__title">{{ title }}</div>
      <div class="error-alert__message">
        <slot>{{ message }}</slot>
      </div>

      <ul v-if="normalizedDetails.length" class="error-alert__details">
        <li v-for="(detail, index) in normalizedDetails" :key="index">
          {{ detail }}
        </li>
      </ul>

      <div v-if="$slots.actions" class="error-alert__actions">
        <slot name="actions" />
      </div>
    </div>

    <button v-if="dismissible" type="button" class="error-alert__dismiss" @click="emit('dismiss')" aria-label="Cerrar">
      ×
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const emit = defineEmits(['dismiss'])

const props = defineProps({
  variant: {
    type: String,
    default: 'error',
    validator: (value) => ['error', 'warning', 'info'].includes(value),
  },
  title: {
    type: String,
    default: '',
  },
  message: {
    type: String,
    default: '',
  },
  details: {
    type: Array,
    default: () => [],
  },
  dismissible: {
    type: Boolean,
    default: false,
  },
  visible: {
    type: Boolean,
    default: true,
  },
})

const normalizedDetails = computed(() => {
  return Array.isArray(props.details)
    ? props.details.map((detail) => String(detail || '').trim()).filter(Boolean)
    : []
})
</script>

<style scoped>
.error-alert {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  padding: 14px 16px;
  border-radius: 14px;
  border: 1px solid transparent;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}

.error-alert--error {
  background: linear-gradient(180deg, #fff5f5 0%, #fffafa 100%);
  border-color: #fecaca;
  color: #7f1d1d;
}

.error-alert--warning {
  background: linear-gradient(180deg, #fffaf0 0%, #fffdf7 100%);
  border-color: #fed7aa;
  color: #7c2d12;
}

.error-alert--info {
  background: linear-gradient(180deg, #eff6ff 0%, #f8fbff 100%);
  border-color: #bfdbfe;
  color: #1e3a8a;
}

.error-alert__icon {
  width: 28px;
  height: 28px;
  flex: 0 0 auto;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 16px;
  line-height: 1;
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid currentColor;
}

.error-alert__body {
  min-width: 0;
  flex: 1 1 auto;
}

.error-alert__title {
  font-weight: 800;
  font-size: 14px;
  margin-bottom: 4px;
}

.error-alert__message {
  font-size: 14px;
  line-height: 1.5;
}

.error-alert__details {
  margin: 10px 0 0;
  padding-left: 18px;
  font-size: 13px;
  line-height: 1.45;
}

.error-alert__actions {
  margin-top: 12px;
}

.error-alert__dismiss {
  appearance: none;
  border: none;
  background: transparent;
  color: inherit;
  font-size: 20px;
  line-height: 1;
  cursor: pointer;
  padding: 0 2px;
}
</style>