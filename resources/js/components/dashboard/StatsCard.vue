<template>
  <div class="card stats-card">
    <div class="card-title">{{ title }}</div>
    <div class="stat-content">
      <div class="stat-value">{{ value }}</div>
      <div class="stat-sub">{{ subtitle }}</div>
      <div v-if="details" class="stat-details">{{ details }}</div>
    </div>
    <div v-if="$slots.actions" class="card-actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  value: { type: [String, Number], default: '—' },
  subtitle: { type: String, default: '' },
  details: { type: String, default: '' },
})
</script>

<style scoped>
.card {
  background: var(--bg-card);
  border: 1px solid #94a3b8;
  border-radius: 20px;
  padding: 24px;
  position: relative;
  min-height: 170px;
}

.card-title {
  position: absolute;
  top: -14px;
  left: 24px;
  background: var(--secondary);
  color: white;
  padding: 6px 16px;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 600;
}

.stat-content {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 8px;
  height: 100%;
}

.stat-value {
  font-size: 36px;
  line-height: 1;
  font-weight: 800;
  color: var(--text-main);
}

.stat-sub {
  font-size: 15px;
  color: var(--text-muted, #6b7280);
}

.stat-details {
  font-size: 13px;
  color: var(--text-muted, #6b7280);
}

.card-actions {
  margin-top: 14px;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
