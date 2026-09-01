<template>
  <div class="detail-page">
    <button class="back-btn" @click="$router.back()">← Volver</button>

    <div v-if="loading" class="loading">Cargando...</div>

    <div v-else-if="bonus" class="detail-card">
      <div class="detail-header">
        <h2>{{ bonus.name }}</h2>
        <span class="detail-status" :class="bonus.status">{{ bonus.status }}</span>
      </div>

      <div class="sessions-overview">
        <div class="session-stat">
          <span class="stat-value">{{ bonus.remaining_sessions }}</span>
          <span class="stat-label">restantes</span>
        </div>
        <div class="session-stat">
          <span class="stat-value">{{ bonus.total_sessions }}</span>
          <span class="stat-label">totales</span>
        </div>
      </div>

      <div class="detail-info">
        <div class="info-row" v-if="bonus.expires_at">
          <span class="info-label">Expira</span>
          <span class="info-value">{{ bonus.expires_at }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Precio</span>
          <span class="info-value">€{{ bonus.price }}</span>
        </div>
      </div>

      <div v-if="bonus.session_lines?.length" class="session-lines">
        <h3>Detalle por servicio</h3>
        <div v-for="line in bonus.session_lines" :key="line.id" class="line-item">
          <span class="line-name">{{ line.bonus_type?.description || 'Servicio' }}</span>
          <span class="line-sessions">{{ line.remaining_quantity }}/{{ line.quantity }}</span>
        </div>
      </div>

      <div v-if="bonus.usages?.length" class="usage-history">
        <h3>Historial de uso</h3>
        <div v-for="usage in bonus.usages" :key="usage.id" class="usage-item">
          <span class="usage-date">{{ new Date(usage.used_at).toLocaleDateString('es') }}</span>
          <span class="usage-appointment">Cita #{{ usage.appointment_id }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import patientApi from '../../patient/services/patientApi'

const route = useRoute()
const bonus = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await patientApi.get(`/bonuses/${route.params.id}`)
    bonus.value = data.bonus
  } catch (e) {
    console.error('Error loading bonus:', e)
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.detail-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.back-btn {
  align-self: flex-start;
  padding: 8px 12px;
  border: none;
  background: none;
  color: #6366f1;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.loading {
  text-align: center;
  padding: 40px;
  color: #64748b;
}

.detail-card {
  background: #ffffff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.detail-header h2 {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.detail-status {
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.detail-status.active { background: #d1fae5; color: #065f46; }
.detail-status.last { background: #fef3c7; color: #92400e; }

.sessions-overview {
  display: flex;
  gap: 32px;
  margin-bottom: 20px;
}

.session-stat {
  display: flex;
  flex-direction: column;
}

.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: #6366f1;
}

.stat-label {
  font-size: 13px;
  color: #64748b;
}

.detail-info {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 20px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  padding-bottom: 12px;
  border-bottom: 1px solid #f1f5f9;
}

.info-label {
  font-size: 14px;
  color: #64748b;
}

.info-value {
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
}

.session-lines,
.usage-history {
  margin-top: 20px;
}

.session-lines h3,
.usage-history h3 {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
  margin: 0 0 12px;
}

.line-item,
.usage-item {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid #f1f5f9;
  font-size: 14px;
}

.line-name,
.usage-date {
  color: #1e293b;
}

.line-sessions,
.usage-appointment {
  color: #64748b;
}
</style>
