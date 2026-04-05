<template>
  <MainLayout>
    <div class="show-wrap">
      <div class="show-card">
        <div class="show-header">
          <div>
            <h1>Notificación</h1>
            <p class="form-sub">Detalle del email enviado o fallido</p>
          </div>
          <div class="header-actions">
            <button v-if="notificationData?.status === 'failed'" type="button" class="primary" :disabled="resending" @click="resendCurrent">
              {{ resending ? 'Reenviando...' : 'Reenviar' }}
            </button>
            <button type="button" class="muted back-btn" @click="goBack">Volver</button>
          </div>
        </div>

        <AppLoading v-if="loading" message="Cargando notificación..." />

        <div v-else-if="notificationData" class="details-grid">
          <div class="field"><label class="label">Fecha del intento</label><div class="value">{{ formatDate(notificationData.sent_at || notificationData.created_at) }}</div></div>
          <div class="field"><label class="label">Estado</label><div class="value"><span class="status" :class="notificationData.status">{{ statusLabel(notificationData.status) }}</span></div></div>

          <div class="field"><label class="label">Tipo</label><div class="value">{{ typeLabel(notificationData.reminder_type) }}</div></div>
          <div class="field"><label class="label">Canal</label><div class="value">{{ channelLabel(notificationData.channel) }}</div></div>

          <div class="field full"><label class="label">Email destino</label><div class="value">{{ notificationData.recipient_email || '—' }}</div></div>

          <div class="field full"><label class="label">Paciente</label><div class="value">{{ patientLabel }}</div></div>

          <div class="field"><label class="label">Cita</label><div class="value">{{ appointmentLabel }}</div></div>
          <div class="field"><label class="label">Clínica</label><div class="value">{{ notificationData.clinic?.name || '—' }}</div></div>

          <div class="field full"><label class="label">Error</label><div class="value">{{ notificationData.error_message || '—' }}</div></div>

          <div class="field full">
            <label class="label">Historial de intentos</label>
            <div class="history-list">
              <div v-for="attempt in notificationData.history || []" :key="attempt.id" class="history-row">
                <div>{{ formatDate(attempt.sent_at || attempt.created_at) }}</div>
                <div><span class="status" :class="attempt.status">{{ statusLabel(attempt.status) }}</span></div>
                <div>{{ attempt.recipient_email || '—' }}</div>
                <div>{{ attempt.error_message || '—' }}</div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="alert-subtle">No se encontró la notificación.</div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import { useToast } from 'vue-toastification'
import { goBackWithPriority } from '../../shared/navigationHelpers'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const loading = ref(false)
const resending = ref(false)
const notificationData = ref(null)

const patientLabel = computed(() => {
  const patient = notificationData.value?.patient
  if (!patient) return '—'
  const prefix = patient.counter ? `${patient.counter} · ` : ''
  return `${prefix}${patient.name || `Paciente #${patient.id}`}`
})

const appointmentLabel = computed(() => {
  const appointment = notificationData.value?.appointment
  if (!appointment?.id) return '—'
  return `Cita #${appointment.id} · ${formatDate(appointment.start_time)}`
})

function typeLabel(type) {
  if (type === '24h') return '24h antes'
  if (type === '2h') return '2h antes'
  return type || '—'
}

function statusLabel(status) {
  if (status === 'sent') return 'Enviado'
  if (status === 'failed') return 'Fallido'
  return status || '—'
}

function channelLabel(channel) {
  if (channel === 'email') return 'Email'
  return channel || '—'
}

function formatDate(value) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('es-ES', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
}

function goBack() {
  goBackWithPriority(router, {
    priorityPath: '/notifications',
    fallbackPath: '/notifications',
  })
}

async function load() {
  loading.value = true
  try {
    const res = await api.get(`/reminders/${route.params.id}`)
    notificationData.value = res.data || null
  } catch (e) {
    notificationData.value = null
    toast.error('Error cargando notificación')
  } finally {
    loading.value = false
  }
}

async function resendCurrent() {
  if (!notificationData.value?.id) return

  resending.value = true
  try {
    const res = await api.post(`/reminders/${notificationData.value.id}/resend`)
    toast.success(res.data?.message || 'Recordatorio reenviado correctamente')
    await load()
  } catch (e) {
    const message = e.response?.data?.message || 'No se pudo reenviar la notificación'
    toast.error(message)
  } finally {
    resending.value = false
  }
}

onMounted(async () => {
  await load()
})
</script>

<style scoped>
.show-wrap { display:flex; justify-content:center; padding:6px 0 }
.show-card { width:100%; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; box-shadow:0 10px 30px rgba(2,6,23,0.06) }
.show-header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px }
.show-header h1 { margin:0; font-size:22px }
.form-sub { color:#6b7280; font-size:13px; margin-top:4px }
.header-actions { display:flex; align-items:center; gap:8px }

.details-grid { display:grid; grid-template-columns:repeat(2, 1fr); gap:12px }
.field { display:flex; flex-direction:column }
.field.full { grid-column:1 / -1 }
.label { font-weight:600; margin-bottom:6px }
.value { padding:10px; border:1px solid #e5e7eb; border-radius:8px; background:#fff; word-break:break-word }

.history-list { display:flex; flex-direction:column; gap:8px }
.history-row { display:grid; grid-template-columns:1.1fr .8fr 1.3fr 2fr; gap:10px; padding:10px; border:1px solid #e5e7eb; border-radius:8px; background:#f8fafc; font-size:13px }

.status { display:inline-flex; align-items:center; padding:5px 8px; border-radius:9999px; font-weight:700; font-size:11px }
.status.sent { background:#dcfce7; color:#166534 }
.status.failed { background:#fee2e2; color:#991b1b }

.alert-subtle { background:#f8fafc; border:1px solid #e6edf3; padding:10px; border-radius:8px; color:#334155; font-size:14px }

@media (max-width: 768px) {
  .details-grid { grid-template-columns:1fr }
  .history-row { grid-template-columns:1fr }
}
</style>
