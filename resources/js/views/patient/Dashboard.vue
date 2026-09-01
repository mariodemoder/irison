<template>
  <div class="dashboard">
    <div class="welcome-section">
      <h1>Hola, {{ patient?.first_name || 'Paciente' }}</h1>
      <p>Bienvenido a tu portal</p>
    </div>

    <div v-if="loading" class="loading">Cargando...</div>

    <div v-else class="dashboard-cards">
      <!-- Next Appointment -->
      <div class="card" v-if="data?.next_appointment">
        <div class="card-header">
          <h3>Próxima cita</h3>
          <router-link to="/patient/appointments" class="card-link">Ver todas</router-link>
        </div>
        <div class="appointment-info">
          <div class="appointment-date">
            <span class="date-day">{{ formatDay(data.next_appointment.start_time) }}</span>
            <span class="date-month">{{ formatMonth(data.next_appointment.start_time) }}</span>
          </div>
          <div class="appointment-details">
            <p class="appointment-time">{{ formatTime(data.next_appointment.start_time) }}</p>
            <p class="appointment-service">{{ data.next_appointment.service_name }}</p>
            <p class="appointment-professional">{{ data.next_appointment.professional_name }}</p>
          </div>
        </div>
      </div>

      <div class="card empty-card" v-else>
        <p>No tienes próximas citas</p>
        <router-link to="/patient/appointments/request" class="card-btn">Solicitar cita</router-link>
      </div>

      <!-- Bonuses -->
      <div class="card" v-if="data?.bonuses_summary?.active_count > 0">
        <div class="card-header">
          <h3>Mis bonos</h3>
          <router-link to="/patient/bonuses" class="card-link">Ver todos</router-link>
        </div>
        <div class="bonus-summary">
          <div class="bonus-stat">
            <span class="stat-value">{{ data.bonuses_summary.active_count }}</span>
            <span class="stat-label">bonos activos</span>
          </div>
          <div class="bonus-stat">
            <span class="stat-value">{{ data.bonuses_summary.total_remaining_sessions }}</span>
            <span class="stat-label">sesiones restantes</span>
          </div>
        </div>
        <div v-if="data.bonuses_summary.expiring_soon?.length" class="expiring-warning">
          <p v-for="bonus in data.bonuses_summary.expiring_soon" :key="bonus.id">
            ⚠️ {{ bonus.name }} vence el {{ bonus.expires_at }}
          </p>
        </div>
      </div>

      <!-- Pending Payments -->
      <div class="card" v-if="data?.pending_payments?.count > 0">
        <div class="card-header">
          <h3>Pagos pendientes</h3>
          <router-link to="/patient/payments" class="card-link">Ver todos</router-link>
        </div>
        <div class="pending-amount">
          <span class="amount">€{{ data.pending_payments.total_amount }}</span>
          <span class="amount-label">{{ data.pending_payments.count }} pago(s) pendiente(s)</span>
        </div>
      </div>

      <!-- Pending Consents -->
      <div class="card" v-if="data?.pending_consents?.count > 0">
        <div class="card-header">
          <h3>Consentimientos</h3>
          <router-link to="/patient/consents" class="card-link">Ver todos</router-link>
        </div>
        <div class="pending-consents">
          <p>{{ data.pending_consents.count }} consentimiento(s) pendiente(s) de firma</p>
          <div v-for="consent in data.pending_consents.items" :key="consent.id" class="consent-item">
            <span>{{ consent.template_name }}</span>
            <router-link :to="`/patient/consents/${consent.id}`" class="consent-link">Revisar</router-link>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="quick-actions">
        <router-link to="/patient/appointments/request" class="action-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          Solicitar cita
        </router-link>
        <router-link to="/patient/notifications" class="action-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"/><path d="M10 17a2 2 0 0 0 4 0"/></svg>
          Notificaciones
          <span v-if="data?.notifications?.unread_count" class="badge">{{ data.notifications.unread_count }}</span>
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import patientApi from '../../patient/services/patientApi'
import { usePatientAuth } from '../../patient/composables/usePatientAuth'

const { patient } = usePatientAuth()
const data = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const response = await patientApi.get('/dashboard')
    data.value = response.data
  } catch (e) {
    console.error('Error loading dashboard:', e)
  } finally {
    loading.value = false
  }
})

function formatDay(datetime) {
  return new Date(datetime).getDate()
}

function formatMonth(datetime) {
  return new Date(datetime).toLocaleDateString('es', { month: 'short' })
}

function formatTime(datetime) {
  return new Date(datetime).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' })
}
</script>

<style scoped>
.dashboard {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.welcome-section h1 {
  font-size: 24px;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.welcome-section p {
  font-size: 14px;
  color: #64748b;
  margin: 4px 0 0;
}

.loading {
  text-align: center;
  padding: 40px;
  color: #64748b;
}

.dashboard-cards {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.card {
  background: #ffffff;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.card-header h3 {
  font-size: 15px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.card-link {
  font-size: 13px;
  color: #6366f1;
  text-decoration: none;
}

.appointment-info {
  display: flex;
  gap: 16px;
}

.appointment-date {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px 12px;
  background: #f0f4ff;
  border-radius: 8px;
}

.date-day {
  font-size: 24px;
  font-weight: 700;
  color: #6366f1;
}

.date-month {
  font-size: 12px;
  color: #6366f1;
  text-transform: uppercase;
}

.appointment-details {
  flex: 1;
}

.appointment-time {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.appointment-service,
.appointment-professional {
  font-size: 13px;
  color: #64748b;
  margin: 2px 0 0;
}

.empty-card {
  text-align: center;
  color: #64748b;
}

.card-btn {
  display: inline-block;
  margin-top: 12px;
  padding: 8px 16px;
  background: #6366f1;
  color: #ffffff;
  border-radius: 8px;
  text-decoration: none;
  font-size: 14px;
  font-weight: 600;
}

.bonus-summary {
  display: flex;
  gap: 24px;
}

.bonus-stat {
  display: flex;
  flex-direction: column;
}

.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #6366f1;
}

.stat-label {
  font-size: 13px;
  color: #64748b;
}

.expiring-warning {
  margin-top: 12px;
  padding: 8px 12px;
  background: #fffbeb;
  border-radius: 8px;
  font-size: 13px;
  color: #92400e;
}

.expiring-warning p {
  margin: 2px 0;
}

.pending-amount {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.amount {
  font-size: 28px;
  font-weight: 700;
  color: #dc2626;
}

.amount-label {
  font-size: 13px;
  color: #64748b;
}

.pending-consents p {
  font-size: 14px;
  color: #64748b;
  margin: 0 0 8px;
}

.consent-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-top: 1px solid #f1f5f9;
  font-size: 14px;
}

.consent-link {
  color: #6366f1;
  text-decoration: none;
  font-weight: 600;
}

.quick-actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.action-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 16px;
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  text-decoration: none;
  color: #1e293b;
  font-size: 13px;
  font-weight: 600;
  position: relative;
}

.action-btn svg {
  width: 24px;
  height: 24px;
  color: #6366f1;
}

.badge {
  position: absolute;
  top: 8px;
  right: 8px;
  background: #dc2626;
  color: #ffffff;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 10px;
}
</style>
