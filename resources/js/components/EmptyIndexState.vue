<template>
  <div class="empty-state" role="status" aria-live="polite">
    <img :src="desertImage" alt="Sin datos" class="empty-illustration" />
    <p class="empty-title">{{ safeTitle }}</p>
    <p v-if="safeSubtitle" class="empty-subtitle">{{ safeSubtitle }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import desertImage from '../assets/desert2.png'

const props = defineProps({
  title: {
    type: String,
    default: 'Ups! sin datos por ahora.',
  },
  subtitle: {
    type: String,
    default: '',
  },
})

const safeTitle = computed(() => String(props.title || 'Ups! sin datos por ahora.').trim() || 'Ups! sin datos por ahora.')
const safeSubtitle = computed(() => String(props.subtitle || '').trim())
</script>

<style scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 28px 16px;
  border: 1px dashed #d1d5db;
  border-radius: 12px;
  background: #ffffff;
}

.empty-illustration {
  width: min(100%, 320px);
  height: auto;
  object-fit: contain;
  opacity: 0.95;
  animation: empty-rise-in 520ms ease-out 1 both;
}

.empty-title {
  margin: 0;
  color: #6b7280;
  font-size: 14px;
  font-weight: 600;
  text-align: center;
}

.empty-subtitle {
  margin: 0;
  color: #94a3b8;
  font-size: 13px;
  font-weight: 500;
  text-align: center;
}

@keyframes empty-rise-in {
  from {
    transform: translateY(14px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 0.95;
  }
}

@media (prefers-reduced-motion: reduce) {
  .empty-illustration {
    animation: none;
  }
}
</style>
