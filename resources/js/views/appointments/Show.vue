<template>
  <MainLayout>
    <div class="show-wrapper">
      <div class="show-card">
        <div class="show-header">
          <h1>Cita</h1>
          <div class="form-sub">Detalle de la cita</div>
        </div>

        <div v-if="loading">Cargando...</div>

        <div v-else>
          <div class="field full">
            <label class="label">Paciente</label>
            <div class="value"><router-link :to="`/patients/${appointment.patient_id}`">{{ appointment.patient?.name ?? ('Paciente #' + appointment.patient_id) }}</router-link></div>
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

            <button class="muted" @click.prevent="appointmentCancel" :disabled="cancelling">
              <IconCancel />
              Cancelar
            </button>
            <button class="muted" @click="back">Volver</button>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import IconCancel from '../../components/icons/IconCancel.vue'
import { useToast } from 'vue-toastification'
import Swal from 'sweetalert2'

const route = useRoute()
const router = useRouter()
const appointment = ref({})
const loading = ref(false)
const cancelling = ref(false)
const canReprogram = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await api.get(`/appointments/${route.params.id}`)
    appointment.value = res.data
  } catch (e) {
    console.error('Error cargando cita', e)
    if (e.response && e.response.status === 404) router.push('/appointments/day')
  } finally {
    loading.value = false
  }
}

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
  Swal.fire({
    title: '¿Cancelar esta cita?',
    text: 'Esta acción no se puede deshacer.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, cancelar',
    cancelButtonText: 'No, mantener',
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        cancelling.value = true
        await api.post(`/appointments/${route.params.id}/cancel`)
        toast.success('Cita cancelada')
        await load()
      } catch (e) {
        toast.error('Error cancelando la cita')
      } finally {
        cancelling.value = false
      }
    }
  })
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
.field { margin-top:12px }
.label { font-weight:600; margin-bottom:6px }
.value { padding:10px; background:#f8fafc; border-radius:8px }
.actions { display:flex; gap:12px; margin-top:16px }
.primary { padding: 8px 16px; font-size: 14px; border-radius: 9999px; border: 2px solid #3b82f6; color: #3b82f6; background: #ffffff; font-weight: 600 }
.muted { padding:8px 14px; border-radius:9999px; border:1px solid #e5e7eb; background:#fff }

/* Alinear icono y texto en botones */
.actions button { display:inline-flex; align-items:center; gap:8px }

.icon-cancel { width:16px; height:16px; margin-right:8px; vertical-align:middle; color:#ef4444 }
.icon-cancel circle { stroke: currentColor; stroke-width:1.5 }
.icon-cancel path { stroke: currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round }
</style>
