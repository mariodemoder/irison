<template>
  <div class="appointments-page">
    <div class="page-header">
      <h1>Mis citas</h1>
      <router-link to="/patient/appointments/request" class="request-btn">Solicitar cita</router-link>
    </div>

    <div class="tabs">
      <button :class="{ active: tab === 'upcoming' }" @click="tab = 'upcoming'">Próximas</button>
      <button :class="{ active: tab === 'history' }" @click="tab = 'history'">Historial</button>
    </div>

    <div v-if="loading" class="loading">Cargando...</div>

    <div v-else-if="tab === 'upcoming'">
      <div v-if="upcoming.length === 0" class="empty-state">
        <p>No tienes citas próximas</p>
        <router-link to="/patient/appointments/request" class="empty-link">Solicitar una cita</router-link>
      </div>
      <div v-else class="appointment-list">
        <div v-for="apt in upcoming" :key="apt.id" class="appointment-card" @click="$router.push(`/patient/appointments/${apt.id}`)">
          <div class="apt-date">
            <span class="apt-day">{{ formatDay(apt.start_time) }}</span>
            <span class="apt-month">{{ formatMonth(apt.start_time) }}</span>
          </div>
          <div class="apt-info">
            <p class="apt-time">{{ formatTime(apt.start_time) }}</p>
            <p class="apt-service">{{ apt.appointmentType?.name || apt.custom_type || 'N/A' }}</p>
            <p class="apt-professional">{{ apt.professional?.name || 'N/A' }}</p>
          </div>
          <span class="apt-status" :class="apt.status">{{ apt.status }}</span>
        </div>
      </div>
    </div>

    <div v-else>
      <div v-if="history.length === 0" class="empty-state">
        <p>No hay citas en el historial</p>
      </div>
      <div v-else class="appointment-list">
        <div v-for="apt in history" :key="apt.id" class="appointment-card" @click="$router.push(`/patient/appointments/${apt.id}`)">
          <div class="apt-date">
            <span class="apt-day">{{ formatDay(apt.start_time) }}</span>
            <span class="apt-month">{{ formatMonth(apt.start_time) }}</span>
          </div>
          <div class="apt-info">
            <p class="apt-time">{{ formatTime(apt.start_time) }}</p>
            <p class="apt-service">{{ apt.appointmentType?.name || apt.custom_type || 'N/A' }}</p>
            <p class="apt-professional">{{ apt.professional?.name || 'N/A' }}</p>
          </div>
          <span class="apt-status" :class="apt.status">{{ apt.status }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import patientApi from '../../patient/services/patientApi'

const tab = ref('upcoming')
const upcoming = ref([])
const history = ref([])
const loading = ref(true)

async function loadUpcoming() {
  try {
    const { data } = await patientApi.get('/appointments/upcoming')
    upcoming.value = data.appointments
  } catch (e) {
    console.error('Error loading upcoming appointments:', e)
  }
}

async function loadHistory() {
  try {
    const { data } = await patientApi.get('/appointments/history')
    history.value = data.data
  } catch (e) {
    console.error('Error loading appointment history:', e)
  }
}

onMounted(async () => {
  await loadUpcoming()
  loading.value = false
})

watch(tab, async (val) => {
  if (val === 'history' && history.value.length === 0) {
    await loadHistory()
  }
})

function formatDay(dt) { return new Date(dt).getDate() }
function formatMonth(dt) { return new Date(dt).toLocaleDateString('es', { month: 'short' }) }
function formatTime(dt) { return new Date(dt).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }) }
</script>

<style scoped>
.appointments-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.page-header h1 {
  font-size: 20px;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.request-btn {
  padding: 8px 16px;
  background: #6366f1;
  color: #ffffff;
  border-radius: 8px;
  text-decoration: none;
  font-size: 14px;
  font-weight: 600;
}

.tabs {
  display: flex;
  gap: 8px;
}

.tabs button {
  flex: 1;
  padding: 10px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #ffffff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  color: #64748b;
}

.tabs button.active {
  background: #6366f1;
  color: #ffffff;
  border-color: #6366f1;
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

.empty-link {
  color: #6366f1;
  text-decoration: none;
  font-weight: 600;
}

.appointment-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.appointment-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  cursor: pointer;
}

.appointment-card:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.apt-date {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px 12px;
  background: #f0f4ff;
  border-radius: 8px;
}

.apt-day {
  font-size: 20px;
  font-weight: 700;
  color: #6366f1;
}

.apt-month {
  font-size: 11px;
  color: #6366f1;
  text-transform: uppercase;
}

.apt-info {
  flex: 1;
}

.apt-time {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.apt-service,
.apt-professional {
  font-size: 13px;
  color: #64748b;
  margin: 2px 0 0;
}

.apt-status {
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.apt-status.scheduled {
  background: #fef3c7;
  color: #92400e;
}

.apt-status.confirmed {
  background: #d1fae5;
  color: #065f46;
}

.apt-status.completed {
  background: #f1f5f9;
  color: #475569;
}

.apt-status.cancelled {
  background: #fee2e2;
  color: #991b1b;
}
</style>
