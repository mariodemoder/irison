<template>
  <div class="empty-state" role="status" aria-live="polite">
    <img :src="desertImage" alt="Sin datos" class="empty-illustration" />
    <p class="empty-title">Ups! sin datos por ahora.</p>
  </div>
</template>

<script setup>
import desertImage from '../assets/desert2.png'
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
}

.empty-title {
  margin: 0;
  color: #6b7280;
  font-size: 14px;
  font-weight: 600;
  text-align: center;
}
</style>
