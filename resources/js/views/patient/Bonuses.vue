<template>
  <div class="bonuses-page">
    <h1>Mis bonos</h1>

    <div v-if="loading" class="loading">Cargando...</div>

    <div v-else-if="bonuses.length === 0" class="empty-state">
      <p>No tienes bonos registrados</p>
    </div>

    <div v-else class="bonus-list">
      <div v-for="bonus in bonuses" :key="bonus.id" class="bonus-card" @click="$router.push(`/patient/bonuses/${bonus.id}`)">
        <div class="bonus-info">
          <h3>{{ bonus.name }}</h3>
          <p class="bonus-type">{{ bonus.bonus_type?.description || 'Pack de sesiones' }}</p>
        </div>
        <div class="bonus-sessions">
          <span class="sessions-remaining">{{ bonus.remaining_sessions }}</span>
          <span class="sessions-label">/{{ bonus.total_sessions }}</span>
        </div>
        <span class="bonus-status" :class="bonus.status">{{ bonus.status }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import patientApi from '../../patient/services/patientApi'

const bonuses = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await patientApi.get('/bonuses')
    bonuses.value = data.bonuses
  } catch (e) {
    console.error('Error loading bonuses:', e)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.bonuses-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

h1 {
  font-size: 20px;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.loading {
  text-align: center;
  padding: 40px;
  color: #64748b;
}

.empty-state {
  text-align: center;
  padding: 40px;
  color: #64748b;
}

.bonus-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.bonus-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  cursor: pointer;
}

.bonus-card:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.bonus-info {
  flex: 1;
}

.bonus-info h3 {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.bonus-type {
  font-size: 13px;
  color: #64748b;
  margin: 2px 0 0;
}

.bonus-sessions {
  text-align: center;
}

.sessions-remaining {
  font-size: 24px;
  font-weight: 700;
  color: #6366f1;
}

.sessions-label {
  font-size: 14px;
  color: #94a3b8;
}

.bonus-status {
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.bonus-status.active { background: #d1fae5; color: #065f46; }
.bonus-status.last { background: #fef3c7; color: #92400e; }
.bonus-status.exhausted { background: #f1f5f9; color: #475569; }
.bonus-status.expired { background: #fee2e2; color: #991b1b; }
</style>
