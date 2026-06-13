<script setup>
defineProps({
  appointment: { type: Object, default: null },
})

const emit = defineEmits(['reset'])

function formatDateTime(dateTimeStr) {
  const d = new Date(dateTimeStr)
  const days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']
  const months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']
  const dayName = days[d.getDay()]
  const day = d.getDate()
  const month = months[d.getMonth()]
  const year = d.getFullYear()
  const hours = String(d.getHours()).padStart(2, '0')
  const mins = String(d.getMinutes()).padStart(2, '0')
  return `${dayName}, ${day} de ${month} de ${year} a las ${hours}:${mins}`
}
</script>

<template>
  <div class="step-card confirmation-card">
    <div class="confirmation-icon">✓</div>
    <h2 class="step-title">Reserva confirmada</h2>
    <p class="step-subtitle">Tu cita se ha creado correctamente.</p>

    <div v-if="appointment" class="confirmation-details">
      <div class="detail-row">
        <span class="detail-label">Clínica</span>
        <span class="detail-value">{{ appointment.appointment.clinic.name }}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Profesional</span>
        <span class="detail-value">{{ appointment.appointment.professional?.name || '—' }}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Fecha y hora</span>
        <span class="detail-value">{{ formatDateTime(appointment.appointment.start_time) }}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Paciente</span>
        <span class="detail-value">{{ appointment.appointment.patient.first_name }} {{ appointment.appointment.patient.last_name }}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Email</span>
        <span class="detail-value">{{ appointment.appointment.patient.email }}</span>
      </div>
    </div>

    <div class="confirmation-note">
      <p>Te hemos enviado un email de confirmación con los detalles de tu cita.</p>
      <p v-if="appointment?.confirmation_token" class="cancel-note">
        Si necesitas cancelar, puedes hacerlo desde el enlace incluido en el email.
      </p>
    </div>

    <button class="btn btn--solid booking-btn-main" @click="emit('reset')">
      Nueva reserva
    </button>
  </div>
</template>

<style scoped>
.step-card {
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(17, 32, 59, 0.08);
  border-radius: 26px;
  padding: 28px;
  box-shadow: 0 14px 36px rgba(17, 32, 59, 0.06);
  text-align: center;
}

.confirmation-card {
  text-align: center;
}

.confirmation-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 56px;
  height: 56px;
  border-radius: 999px;
  background: #22c55e;
  color: #fff;
  font-size: 1.6rem;
  font-weight: 800;
  margin-bottom: 16px;
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

.confirmation-details {
  text-align: left;
  background: rgba(17, 32, 59, 0.03);
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 20px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 0;
}

.detail-row + .detail-row {
  border-top: 1px solid rgba(17, 32, 59, 0.06);
}

.detail-label {
  font-size: 13px;
  color: #556176;
  font-weight: 600;
}

.detail-value {
  font-size: 13px;
  color: #11203b;
  font-weight: 700;
  text-align: right;
}

.confirmation-note {
  margin-bottom: 24px;
}

.confirmation-note p {
  color: #556176;
  font-size: 13px;
  line-height: 1.6;
}

.cancel-note {
  margin-top: 8px;
  font-size: 12px;
  color: #5e6b80;
}

.booking-btn-main {
  background: rgb(86, 39, 221);
  box-shadow: 0 12px 32px rgba(106, 48, 252, 0.3);
  width: auto;
  padding: 12px 24px;
}

.booking-btn-main:hover {
  background: rgb(106, 48, 252);
}
</style>
