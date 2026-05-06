<template>
  <div class="info-hint" :class="[`align-${align}`]" role="note" aria-live="polite">
    <span class="icon-wrap" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="9" />
        <path d="M12 10V16" />
        <circle cx="12" cy="7.5" r="0.9" fill="currentColor" stroke="none" />
      </svg>
    </span>
    <span class="text">
      <slot>{{ message }}</slot>
    </span>
  </div>
</template>

<script setup>
const props = defineProps({
  message: {
    type: String,
    default: '',
  },
  align: {
    type: String,
    default: 'left',
    validator: (value) => ['left', 'right', 'center'].includes(value),
  },
})
</script>

<style scoped>
.info-hint {
  display: inline-flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 12px;
  line-height: 1.35;
  color: #475569;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 6px 10px;
}

.icon-wrap {
  width: 16px;
  height: 16px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #64748b;
  flex-shrink: 0;
  margin-top: 1px;
}

.icon-wrap svg {
  width: 16px;
  height: 16px;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.text { display: inline }

.align-left { text-align: left }
.align-right { text-align: right }
.align-center { text-align: center }
</style>
