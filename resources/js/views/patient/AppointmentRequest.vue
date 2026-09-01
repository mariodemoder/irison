<template>
  <div class="request-page">
    <button class="back-btn" @click="$router.back()">← Volver</button>
    <h1>Solicitar cita</h1>

    <form @submit.prevent="handleSubmit" class="request-form">
      <div v-if="message" class="success-message">{{ message }}</div>
      <div v-if="error" class="error-message">{{ error }}</div>

      <div class="form-group">
        <label for="preferred_date">Fecha preferida</label>
        <input id="preferred_date" v-model="form.preferred_date" type="date" required :min="minDate" :max="maxDate" />
        <span v-if="maxDate" class="field-hint">
          Puedes solicitar cita hasta el {{ maxDate }} (horizonte máximo de reserva).
        </span>
      </div>

      <div class="form-group">
        <label for="preferred_time">Hora preferida</label>
        <input id="preferred_time" v-model="form.preferred_time" type="time" required />
      </div>

      <div class="form-group">
        <label for="service_name">Servicio</label>
        <input id="service_name" v-model="form.service_name" type="text" placeholder="Ej: Fisioterapia" />
      </div>

      <div class="form-group">
        <label for="notes">Notas</label>
        <textarea id="notes" v-model="form.notes" rows="3" placeholder="Describe brevemente tu consulta..."></textarea>
      </div>

      <button type="submit" class="submit-btn" :disabled="submitting">
        {{ submitting ? 'Enviando...' : 'Enviar solicitud' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import patientApi from '../../patient/services/patientApi'
import { usePatientAuth } from '../../patient/composables/usePatientAuth'

const router = useRouter()
const { portalSettings } = usePatientAuth()

const form = ref({
  preferred_date: '',
  preferred_time: '',
  service_name: '',
  notes: '',
})

const submitting = ref(false)
const error = ref('')
const message = ref('')

const minDate = computed(() => {
  const today = new Date()
  return today.toISOString().split('T')[0]
})

const maxDate = computed(() => {
  const days = portalSettings.value?.max_horizon_days
  if (!days) return ''
  const d = new Date()
  d.setDate(d.getDate() + days)
  return d.toISOString().split('T')[0]
})

async function handleSubmit() {
  submitting.value = true
  error.value = ''
  message.value = ''

  try {
    await patientApi.post('/appointments/requests', form.value)
    message.value = 'Solicitud enviada correctamente. La clínica confirmará tu cita.'
    setTimeout(() => router.push('/patient/appointments'), 2000)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Error al enviar la solicitud.'
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.request-page {
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

h1 {
  font-size: 20px;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.request-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.success-message {
  padding: 12px;
  border-radius: 8px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #16a34a;
  font-size: 14px;
}

.error-message {
  padding: 12px;
  border-radius: 8px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  font-size: 14px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}

.field-hint {
  font-size: 12px;
  color: #6b7280;
}

.form-group input,
.form-group textarea {
  padding: 10px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 15px;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.submit-btn {
  padding: 12px;
  border: none;
  border-radius: 8px;
  background: #6366f1;
  color: #ffffff;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
}

.submit-btn:hover:not(:disabled) {
  background: #4f46e5;
}

.submit-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
