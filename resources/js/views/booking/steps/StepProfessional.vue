<script setup>
defineProps({
  professionals: { type: Array, required: true },
  selected: { type: Object, default: null },
})

const emit = defineEmits(['select'])
</script>

<template>
  <div class="step-card">
    <h2 class="step-title">Elige un profesional</h2>
    <p class="step-subtitle">Selecciona con quién quieres la consulta o elige "Cualquier profesional disponible".</p>

    <div class="professional-grid">
      <button
        class="professional-card any-professional"
        :class="{ selected: selected === null }"
        @click="emit('select', null)"
      >
        <span class="professional-card__initial">?</span>
        <div>
          <span class="professional-card__name">Cualquier profesional disponible</span>
          <span class="professional-card__meta">La clínica asignará tu profesional</span>
        </div>
      </button>

      <button
        v-for="prof in professionals"
        :key="prof.id"
        class="professional-card"
        :class="{ selected: selected?.id === prof.id }"
        @click="emit('select', prof)"
      >
        <span class="professional-card__initial">{{ prof.name.charAt(0).toUpperCase() }}</span>
        <div>
          <span class="professional-card__name">{{ prof.name }}</span>
        </div>
      </button>
    </div>

    <div v-if="professionals.length === 0" class="empty-state">
      No hay profesionales disponibles.
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

.professional-grid {
  display: grid;
  gap: 12px;
}

.professional-card {
  display: flex;
  align-items: center;
  gap: 14px;
  text-align: left;
  padding: 16px;
  border-radius: 18px;
  border: 2px solid rgba(17, 32, 59, 0.08);
  background: #fff;
  cursor: pointer;
  transition: all 0.15s;
  font-family: inherit;
  width: 100%;
}

.professional-card:hover {
  border-color: rgb(106, 48, 252);
  background: rgb(247, 243, 255);
}

.professional-card.selected {
  border-color: rgb(86, 39, 221);
  background: rgb(235, 226, 255);
}

.professional-card__initial {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 999px;
  background: rgba(106, 48, 252, 0.1);
  color: rgb(86, 39, 221);
  font-weight: 800;
  font-size: 1.1rem;
  flex-shrink: 0;
}

.professional-card__name {
  display: block;
  font-weight: 700;
  color: #11203b;
}

.professional-card__meta {
  display: block;
  font-size: 12px;
  color: #5e6b80;
  margin-top: 2px;
}

.any-professional .professional-card__initial {
  background: rgba(17, 32, 59, 0.06);
  color: #556176;
}

.empty-state {
  text-align: center;
  padding: 32px 0;
  color: #556176;
}
</style>
