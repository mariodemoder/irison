<template>
  <MainLayout>
    <div class="show-wrapper">
      <div class="show-card">
        <div class="show-header">
          <h1>Cita</h1>
          <div class="header-right">
            <h2 class="status-wrap"><span class="status" :class="appointment.status">{{ statusLabel(appointment.status) }}</span></h2>
            <div class="actions header-actions">
              <button v-if="isEdit && isFutureAppointment" type="button" class="muted" @click.prevent="goReprogram" :disabled="submitting">
                Reprogramar
              </button>
              <button v-if="isEdit && !isCanceled" type="button" class="muted" @click.prevent="appointmentCancel" :disabled="submitting">
                <IconCancel />
                Cancelar Cita
              </button>
            </div>
          </div>
        </div>
        <div v-if="loading">Cargando...</div>

        <div v-else>
          <div class="field full">
            <label class="label">Paciente</label>
            <div class="value"><router-link :to="`/patients/${appointment.patient_id}`">{{ appointment.patient?.name || appointment.patient_name || appointment.patient?.full_name || ('Paciente #' + appointment.patient_id) }}</router-link></div>
          </div>

          <div class="field">
            <label class="label">Inicio</label>
            <div class="value">{{ appointment.start_time }}</div>
          </div>

          <div class="field">
            <label class="label">Fin</label>
            <div class="value">{{ appointment.end_time }}</div>
          </div>

          <div class="field full">
            <label class="label">Notas</label>
            <div class="value">{{ appointment.notes ?? '—' }}</div>
          </div>
          <div class="actions">
            <template v-if="appointment.status === 'canceled'">
              <button class="primary" :disabled="!canReprogram" @click.prevent="goReprogram">Reprogramar</button>
              <div v-if="!canReprogram" class="field-error" style="margin-left:8px">La reprogramación sólo está permitida con al menos 2 horas de antelación.</div>
            </template>
            <template v-else>
              <router-link :to="`/appointments/${appointment.id}/edit`" class="primary">Editar</router-link>
            </template>
            <button class="muted" @click="back">Volver</button>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import IconCancel from '../../components/icons/IconCancel.vue'
import { useToast } from 'vue-toastification'
import Swal from 'sweetalert2'
import { statusLabel } from '../../shared/appointmentHelpers'
import { appointmentCancelShared } from '../../shared/formHelpers'

const route = useRoute()
const router = useRouter()
const appointment = ref({})
const loading = ref(false)
const cancelling = ref(false)
const canReprogram = ref(false)
const isEdit = ref(!!route.params.id)
const isCanceled = computed(() => appointment.value && (appointment.value.status === 'canceled' || appointment.value.status === 'cancelled'))
const isFutureAppointment = computed(() => {
  try {
    const t = appointment.value.start_time
    if (!t) return false
    const dt = new Date(t)
    return dt.getTime() > Date.now()
  } catch (e) {
    return false
  }
})

async function load() {
  loading.value = true
  try {
  const res = await api.get(`/appointments/${route.params.id}`)
  appointment.value = (res.data && res.data.data) ? res.data.data : res.data
    // If API didn't include the related patient, fetch it using patient_id
    if ((!appointment.value.patient || Object.keys(appointment.value.patient).length === 0) && appointment.value.patient_id) {
      try {
        const pres = await api.get(`/patients/${appointment.value.patient_id}`)
        appointment.value.patient = (pres.data && pres.data.data) ? pres.data.data : pres.data
      } catch (e) {
        // ignore patient fetch errors, keep appointment as-is
        console.warn('No se pudo cargar patient relacionado', e)
      }
    }
  } catch (e) {
    console.error('Error cargando cita', e)
    if (e.response && e.response.status === 404) router.push('/appointments/day')
  } finally {
    loading.value = false
  }
}

// statusLabel moved to shared/appointmentHelpers

onMounted(() => load())

// recompute permission when appointment changes
function computeReprogramAllowance() {
  if (!appointment.value || !appointment.value.start_time) {
    canReprogram.value = false
    return
  }
  const start = new Date(appointment.value.start_time).getTime()
  const threshold = start - (1 * 60 * 60 * 1000) // 1 hours before
  canReprogram.value = Date.now() < threshold
}

// watch for loaded appointment
;(() => {
  const origLoad = load
  load = async () => {
    await origLoad()
    computeReprogramAllowance()
  }
})()

function back() {
  router.push('/appointments/day')
}

async function cancel() {
  cancelling.value = true
  try {
    const toast = useToast()
    await api.post(`/appointments/${route.params.id}/cancel`)
    toast.success('Cita cancelada')
    await load()
  } catch (e) {
    console.error('Error cancelando cita', e)
  } finally {
    cancelling.value = false
  }
}

function appointmentCancel() {
  const toast = useToast()
  appointmentCancelShared(route.params.id, { api, toast, onSuccess: async () => { cancelling.value = true; await load(); cancelling.value = false } }).catch(() => {})
}

function goReprogram() {
  router.push({ path: `/appointments/${appointment.value.id}/edit`, query: { mode: 'reprogram' } })
}
</script>

<style scoped>
.show-wrapper { display:flex; justify-content:center; padding:24px }
.show-card { width:100%; max-width:760px; background: #fff; border-radius:12px; box-shadow: 0 10px 30px rgba(2,6,23,0.06); padding:24px }
.show-header h1 { margin:0; font-size:22px }
.form-sub { color:#6b7280; font-size:13px; margin-top:6px }

/* Header layout: title left, status+actions right on one line */
.show-header { display:flex; justify-content:space-between; align-items:center; gap:12px }
.header-right { display:flex; align-items:center; gap:12px }
.status-wrap { margin:0 }
.header-actions { display:flex; gap:8px; align-items:center; justify-content:flex-end }
.field { margin-top:12px }
.label { font-weight:600; margin-bottom:6px }
.value { padding:10px; background:#f8fafc; border-radius:8px }
.actions { display:flex; gap:12px; margin-top:16px }
.primary { padding: 8px 16px; font-size: 14px; border-radius: 9999px; border: 2px solid #3b82f6; color: #3b82f6; background: #ffffff; font-weight: 600 }
.muted { padding:8px 14px; border-radius:9999px; border:1px solid #e5e7eb; background:#fff }

.status { padding:6px 10px; border-radius:9999px; font-weight:700; text-transform:capitalize }
.status.canceled { background:#fff4f4; color:#da7a7a }
.status.scheduled { background:#eef2ff; color:#1e3a8a }
.status.completed { background:#dcfce7; color:#166534 }

/* Alinear icono y texto en botones */
.actions button { display:inline-flex; align-items:center; gap:8px }

.icon-cancel circle { stroke: currentColor; stroke-width:2.5 }
.icon-cancel path { stroke: currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round }
</style>
