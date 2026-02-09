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
            <PatientSelect v-model="form.patient_id" />
            <div v-if="errors.patient_id" class="field-error">{{ errors.patient_id[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Inicio</label>
            <input v-model="form.start_time" type="datetime-local" class="input" />
            <div v-if="errors.start_time" class="field-error">{{ errors.start_time[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Fin</label>
            <input v-model="form.end_time" type="datetime-local" class="input" />
            <div v-if="errors.end_time" class="field-error">{{ errors.end_time[0] }}</div>
          </div>

          <div class="field full">
            <label class="label">Notas</label>
            <textarea v-model="form.notes" class="textarea" rows="4"></textarea>
            <div v-if="errors.notes" class="field-error">{{ errors.notes[0] }}</div>
          </div>

          <div class="actions full">
            <button class="primary" type="submit" :disabled="submitting">Guardar</button>
            <button type="button" class="muted" @click.prevent="cancel">Cancelar</button>
          </div>
        </form>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { reactive, ref, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import { useToast } from 'vue-toastification'
import PatientSelect from '../../components/PatientSelect2.vue'

const router = useRouter()
const route = useRoute()
const isEdit = ref(false)
const form = reactive({ patient_id: '', start_time: '', end_time: '', notes: '' })
const errors = reactive({})
const submitting = ref(false)
const loading = ref(false)

// Register component locally
const components = { PatientSelect }

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

async function loadForEdit(id) {
  loading.value = true
  try {
    const res = await api.get(`/appointments/${id}`)
    const data = res.data
    form.patient_id = data.patient_id || ''
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
})

watch(() => route.params.id, (id) => {
  if (id) {
    isEdit.value = true
    Object.keys(errors).forEach(k => delete errors[k])
    loadForEdit(id)
  } else {
    isEdit.value = false
    form.patient_id = ''
    form.start_time = ''
    form.end_time = ''
    form.notes = ''
    Object.keys(errors).forEach(k => delete errors[k])
  }
})

async function submit() {
  submitting.value = true
  Object.keys(errors).forEach(k => delete errors[k])
  try {
    const toast = useToast()
    const payload = {
      patient_id: form.patient_id,
      start_time: form.start_time,
      end_time: form.end_time,
      notes: form.notes,
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

@media (max-width: 768px) {
  .grid-form { grid-template-columns: 1fr }
}
</style>
