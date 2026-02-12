<template>
  <MainLayout>
    <div class="form-wrapper">
      <div class="form-card">
        <div class="form-header">
          <h1>{{ isEdit ? 'Editar cita' : 'Nueva cita' }}</h1>
          <p class="form-sub">{{ isEdit ? 'Modifica la fecha, hora y notas de la cita.' : 'Crea una nueva cita.' }}</p>
        </div>

        <form class="grid-form" @submit.prevent="submit">
          <div class="field">
            <label class="label">Paciente</label>
            <select v-model="form.patient_id" class="input" :disabled="isCanceled && mode !== 'reprogram'">
              <option value="" disabled>Selecciona un paciente</option>
              <option v-for="p in patients" :key="p.id" :value="p.id">{{ p.name }}{{ p.nif ? (' — ' + p.nif) : '' }}</option>
            </select>
            <div v-if="errors.patient_id" class="field-error">{{ errors.patient_id[0] }}</div>
          </div>
          <div class="field">
            <label class="label">Estado</label>
            <select v-model="form.status" class="input" :disabled="isCanceled">
              <option value="" disabled>Selecciona un estado</option>
              <option value="scheduled">Programada</option>
              <option value="completed">Completada</option>
              <option value="canceled">Canceled</option>
            </select>
            <div v-if="errors.status" class="field-error">{{ errors.status[0] }}</div>
          </div>
          <div class="field">
            <label class="label">Inicio</label>
            <input v-model="form.start_time" type="datetime-local" class="input" :disabled="isCanceled && mode !== 'reprogram'" />
            <div v-if="errors.start_time" class="field-error">{{ errors.start_time[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Fin</label>
            <input v-model="form.end_time" type="datetime-local" class="input" />
            <div v-if="errors.end_time" class="field-error">{{ errors.end_time[0] }}</div>
          </div>

          <div class="field full">
            <label class="label">Notas</label>
            <textarea v-model="form.notes" class="textarea" rows="4" :disabled="isCanceled && mode !== 'reprogram'"></textarea>
            <div v-if="errors.notes" class="field-error">{{ errors.notes[0] }}</div>
          </div>

          <div class="actions full">
            <button class="primary" type="submit" :disabled="submitting">Guardar</button>
            <button type="button" class="muted" @click.prevent="appointmentCancel" :disabled="submitting">
              <IconCancel />
              Cancelar
            </button>
            <button type="button" class="muted" @click.prevent="cancel">Volver</button>
          </div>
        </form>
      </div>
    </div>



  </MainLayout>
</template>


<script setup>
import { reactive, ref, onMounted, watch, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import IconCancel from '../../components/icons/IconCancel.vue'
import { useToast } from 'vue-toastification'
import Swal from 'sweetalert2'

const router = useRouter()
const route = useRoute()
const isEdit = ref(false)
const mode = ref(route.query.mode || null)
const form = reactive({ patient_id: '', status: 'scheduled', start_time: '', end_time: '', notes: '' })
const isCanceled = ref(false)
const originalStart = ref(null)
const canReprogramInForm = ref(false)
const errors = reactive({})
const submitting = ref(false)
const loading = ref(false)


async function loadPatients() {
  try {
    const res = await api.get('/patients', { params: { per_page: 200 } })
    patients.value = Array.isArray(res.data.data) ? res.data.data : (res.data || [])
  } catch (e) {
    patients.value = []
  }
}

function cancel() {
  const from = route.query.from
  const id = route.params.id
  if (from === 'day') {
    router.push('/appointments/day')
    return
  }
  if (from === 'show' && id) {
    router.push(`/appointments/${id}`)
    return
  }
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push('/appointments/day')
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
        await api.post(`/appointments/${route.params.id}/cancel`)
        toast.success('Cita cancelada')
        router.push('/appointments/day')
      } catch (e) {
        toast.error('Error cancelando la cita')
      }
    }
  })
 
}
async function loadForEdit(id) {
  loading.value = true
  try {
    const res = await api.get(`/appointments/${id}`)
    const data = res.data
    form.patient_id = data.patient_id || ''
    form.status = data.status || 'scheduled'
    isCanceled.value = (data.status === 'canceled' || data.status === 'cancelled')
    originalStart.value = data.start_time || null
    // allow reprogram only if now < start_time - 2 hours
    if (originalStart.value) {
      const startMs = new Date(originalStart.value).getTime()
      canReprogramInForm.value = Date.now() < (startMs - (2 * 60 * 60 * 1000))
    } else {
      canReprogramInForm.value = false
    }
    // backend returns ISO datetime; convert to local input format
    form.start_time = data.start_time ? new Date(data.start_time).toISOString().slice(0,16) : ''
    form.end_time = data.end_time ? new Date(data.end_time).toISOString().slice(0,16) : ''
    form.notes = data.notes || ''
  } catch (e) {
    console.error('Error cargando cita para edición', e)
    if (e.response && e.response.status === 404) router.push('/appointments/day')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  const id = route.params.id
  if (id) {
    isEdit.value = true
    loadForEdit(id)
  }
  loadPatients()
})

// keep mode in sync with route query
watch(() => route.query.mode, (m) => { mode.value = m || null })

watch(() => route.params.id, (id) => {
  if (id) {
    isEdit.value = true
    Object.keys(errors).forEach(k => delete errors[k])
    loadForEdit(id)
  } else {
    isEdit.value = false
    form.patient_id = ''
    form.status = 'scheduled'
    form.start_time = ''
    form.end_time = ''
    form.notes = ''
    Object.keys(errors).forEach(k => delete errors[k])
  }
})

async function submit() {
  submitting.value = true
  Object.keys(errors).forEach(k => delete errors[k])
  // If trying to reprogram a canceled appointment, ensure it's allowed
  if (isCanceled.value && mode.value === 'reprogram' && !canReprogramInForm.value) {
    errors.general = ['Reprogramación no permitida fuera del plazo de 2 horas antes del inicio']
    submitting.value = false
    return
  }
  try {
    const toast = useToast()
    const payload = {
      patient_id: form.patient_id,
      status: form.status,
      start_time: form.start_time,
      end_time: form.end_time,
      notes: form.notes,
    }

    // If reprogramming a canceled appointment, force status -> scheduled
    if (isCanceled.value && mode.value === 'reprogram') {
      payload.status = 'scheduled'
    }

    if (isEdit.value && route.params.id) {
      await api.patch(`/appointments/${route.params.id}`, payload)
      toast.success('Cita actualizada')
      router.push('/appointments/day')
    } else {
      await api.post('/appointments', payload)
      toast.success('Cita creada')
      router.push('/appointments/day')
    }
  } catch (e) {
    if (e.response) {
      const status = e.response.status
      const data = e.response.data || {}
      if (status === 422) {
        const eobj = data.errors || {}
        Object.assign(errors, eobj)
      } else {
        errors.general = [data.message || 'Error desconocido']
      }
    } else {
      errors.general = ['Error de red o servidor']
    }
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.form-wrapper { display:flex; justify-content:center; padding:24px }
.form-card { width:100%; max-width:760px; background: #fff; border-radius:12px; box-shadow: 0 10px 30px rgba(2,6,23,0.06); padding:24px }
.form-header h1 { margin:0; font-size:22px }
.form-sub { color:#6b7280; font-size:13px; margin-top:6px }

.grid-form { display:grid; grid-template-columns: repeat(2, 1fr); gap:12px }
.grid-form .full { grid-column: 1 / -1 }
.field { display:flex; flex-direction:column }
.label { font-weight:600; margin-bottom:6px }
.input, .textarea { padding:12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px }
.textarea { resize:vertical }
.field-error { color:#b91c1c; font-size:13px; margin-top:6px }

.actions { display:flex; gap:12px; align-items:center }
.actions .muted { color:#6b7280; text-decoration:none }
.primary { padding: 8px 16px; font-size: 14px; border-radius: 9999px; border: 2px solid #3b82f6; color: #3b82f6; background: #ffffff; font-weight: 600 }
.primary:hover { background: #eff6ff }
.muted { padding:8px 14px; border-radius:9999px; border:1px solid #e5e7eb; background:#fff }

@media (max-width: 768px) {
  .grid-form { grid-template-columns: 1fr }
}

.icon-cancel { width:16px; height:16px; margin-right:8px; vertical-align:middle; color:#ef4444 }
.icon-cancel circle { stroke: currentColor; stroke-width:1.5 }
.icon-cancel path { stroke: currentColor; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round }

/* Alinear icono y texto en botones */
.actions button { display:inline-flex; align-items:center; gap:8px }
</style>
