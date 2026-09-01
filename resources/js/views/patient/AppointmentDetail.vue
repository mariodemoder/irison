<template>
  <div class="detail-page">
    <button class="back-btn" @click="$router.back()">← Volver</button>

    <div v-if="loading" class="loading">Cargando...</div>

    <div v-else-if="appointment" class="detail-card">
      <div class="detail-header">
        <div class="detail-date">
          <span class="date-day">{{ formatDay(appointment.start_time) }}</span>
          <span class="date-month">{{ formatMonth(appointment.start_time) }}</span>
        </div>
        <span class="detail-status" :class="appointment.status">{{ appointment.status }}</span>
      </div>

      <div class="detail-info">
        <div class="info-row">
          <span class="info-label">Hora</span>
          <span class="info-value">{{ formatTime(appointment.start_time) }} - {{ formatTime(appointment.end_time) }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Servicio</span>
          <span class="info-value">{{ appointment.appointmentType?.name || appointment.custom_type || 'N/A' }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Profesional</span>
          <span class="info-value">{{ appointment.professional?.name || 'N/A' }}</span>
        </div>
        <div class="info-row" v-if="appointment.clinic">
          <span class="info-label">Clínica</span>
          <span class="info-value">{{ appointment.clinic.name }}</span>
        </div>
        <div class="info-row" v-if="appointment.notes">
          <span class="info-label">Notas</span>
          <span class="info-value">{{ appointment.notes }}</span>
        </div>
      </div>

      <div v-if="canCancel" class="detail-actions">
        <button class="cancel-btn" @click="handleCancel" :disabled="cancelling">
          {{ cancelling ? 'Cancelando...' : 'Cancelar cita' }}
        </button>
      </div>

      <div v-if="canReschedule" class="detail-actions">
        <router-link :to="`/patient/appointments/request?reschedule=${appointment.id}`" class="reschedule-btn">
          Reprogramar
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import patientApi from '../../patient/services/patientApi'
import { usePatientAuth } from '../../patient/composables/usePatientAuth'

const route = useRoute()
const router = useRouter()
const { portalSettings } = usePatientAuth()
const appointment = ref(null)
const loading = ref(true)
const cancelling = ref(false)

onMounted(async () => {
  try {
    const { data } = await patientApi.get(`/appointments/${route.params.id}`)
    appointment.value = data.appointment
  } catch (e) {
    console.error('Error loading appointment:', e)
  } finally {
    loading.value = false
  }
})

// Antelación mínima (horas) para cancelar/reprogramar desde el portal
// (política configurable por clínica; por defecto 24h).
const cancellationMs = computed(() => {
  const hours = portalSettings.value?.cancellation_hours ?? 24
  return hours * 60 * 60 * 1000
})

const canCancel = computed(() => {
  if (!appointment.value) return false
  return ['scheduled', 'confirmed'].includes(appointment.value.status) &&
    new Date(appointment.value.start_time) > new Date(Date.now() + cancellationMs.value)
})

const canReschedule = computed(() => {
  if (!appointment.value) return false
  return ['scheduled', 'confirmed'].includes(appointment.value.status) &&
    new Date(appointment.value.start_time) > new Date(Date.now() + cancellationMs.value)
})

async function handleCancel() {
  if (!confirm('¿Estás seguro de que deseas cancelar esta cita?')) return

  cancelling.value = true
  try {
    await patientApi.post(`/appointments/${route.params.id}/cancel`)
    router.push('/patient/appointments')
  } catch (e) {
    alert(e?.response?.data?.message || 'Error al cancelar la cita.')
  } finally {
    cancelling.value = false
  }
}

function formatDay(dt) { return new Date(dt).getDate() }
function formatMonth(dt) { return new Date(dt).toLocaleDateString('es', { month: 'short' }) }
function formatTime(dt) { return new Date(dt).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }) }
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

.detail-date {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 12px 16px;
  background: #f0f4ff;
  border-radius: 10px;
}

.date-day {
  font-size: 28px;
  font-weight: 700;
  color: #6366f1;
}

.date-month {
  font-size: 13px;
  color: #6366f1;
  text-transform: uppercase;
}

.detail-status {
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.detail-status.scheduled { background: #fef3c7; color: #92400e; }
.detail-status.confirmed { background: #d1fae5; color: #065f46; }

.detail-info {
  display: flex;
  flex-direction: column;
  gap: 12px;
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

.detail-actions {
  margin-top: 20px;
}

.cancel-btn,
.reschedule-btn {
  width: 100%;
  padding: 12px;
  border: none;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  text-align: center;
  text-decoration: none;
  display: block;
}

.cancel-btn {
  background: #fee2e2;
  color: #991b1b;
}

.reschedule-btn {
  background: #6366f1;
  color: #ffffff;
}
</style>
