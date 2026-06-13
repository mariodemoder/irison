<script setup>
defineProps({
  services: { type: Array, required: true },
  selected: { type: Object, default: null },
})

const emit = defineEmits(['select'])
</script>

<template>
  <div class="step-card">
    <h2 class="step-title">Elige un servicio</h2>
    <p class="step-subtitle">Selecciona el tipo de consulta que necesitas.</p>

    <div class="service-grid">
      <button
        v-for="service in services"
        :key="service.id"
        class="service-card"
        :class="{ selected: selected?.id === service.id }"
        @click="emit('select', service)"
      >
        <span class="service-card__name">{{ service.name }}</span>
        <span class="service-card__meta">
          {{ service.duration_minutes }} min
          <template v-if="service.price"> · {{ Number(service.price).toFixed(2) }}€</template>
        </span>
        <p v-if="service.description" class="service-card__desc">{{ service.description }}</p>
      </button>
    </div>

    <div v-if="services.length === 0" class="empty-state">
      No hay servicios disponibles.
    </div>
  </div>
</template>

<style scoped>
.step-card {
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(17, 32, 59, 0.08);
  border-radius: 26px;
  padding: 28px;
  box-shadow: 0 14px 36px rgba(17, 32, 59, 0.06);
}

.step-title {
  margin: 0 0 4px;
  font-size: 1.3rem;
  font-weight: 800;
  letter-spacing: -0.03em;
}

.step-subtitle {
  margin: 0 0 20px;
  color: #556176;
}

.service-grid {
  display: grid;
  gap: 12px;
}

.service-card {
  display: flex;
  flex-direction: column;
  gap: 4px;
  text-align: left;
  padding: 18px;
  border-radius: 18px;
  border: 2px solid rgba(17, 32, 59, 0.08);
  background: #fff;
  cursor: pointer;
  transition: all 0.15s;
  font-family: inherit;
  width: 100%;
}

.service-card:hover {
  border-color: rgb(106, 48, 252);
  background: rgb(247, 243, 255);
}

.service-card.selected {
  border-color: rgb(86, 39, 221);
  background: rgb(235, 226, 255);
}

.service-card__name {
  font-size: 1.05rem;
  font-weight: 700;
  color: #11203b;
}

.service-card__meta {
  font-size: 13px;
  color: #5e6b80;
  font-weight: 600;
}

.service-card__desc {
  margin: 4px 0 0;
  font-size: 13px;
  color: #556176;
  line-height: 1.5;
}

.empty-state {
  text-align: center;
  padding: 32px 0;
  color: #556176;
}
</style>
