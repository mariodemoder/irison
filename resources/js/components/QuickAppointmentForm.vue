<template>
  <form class="qaf-form" @submit.prevent="submit">
    <div v-if="errors.general" class="field-error qaf-error">{{ errors.general }}</div>

    <div class="qaf-grid">
      <div class="field" v-if="!patientId">
        <label class="label">Paciente</label>
        <select v-model="form.patient_id" class="input" required>
          <option value="">Selecciona paciente</option>
          <option v-for="p in patients" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <div v-if="errors.patient_id" class="field-error">{{ errors.patient_id }}</div>
      </div>

      <div class="field">
        <label class="label">Tipo</label>
        <select v-model="form.app_type_id" class="input" @change="onTypeChange">
          <option value="">Selecciona tipo</option>
          <option value="__custom">Otro (escribir)</option>
          <option v-for="t in appointmentTypes" :key="t.id" :value="t.id">{{ t.description }}</option>
        </select>
        <div v-if="errors.app_type_id" class="field-error">{{ errors.app_type_id }}</div>
      </div>
    </div>

    <div class="field" v-if="form.app_type_id === '__custom'">
      <label class="label">Tipo personalizado</label>
      <input v-model="form.custom_type" class="input" placeholder="Ej: Consulta" />
    </div>

    <div class="qaf-grid qaf-grid-3">
      <div class="field">
        <label class="label">Fecha</label>
        <input v-model="form.date" type="date" class="input" required />
        <div v-if="errors.date" class="field-error">{{ errors.date }}</div>
      </div>
      <div class="field">
        <label class="label">Inicio</label>
        <select v-model="form.start_time" class="input" required>
          <option value="">Hora</option>
          <option v-for="opt in timeOptions" :key="'s'+opt" :value="opt">{{ opt }}</option>
        </select>
        <div v-if="errors.start_time" class="field-error">{{ errors.start_time }}</div>
      </div>
      <div class="field">
        <label class="label">Fin</label>
        <select v-model="form.end_time" class="input" required>
          <option value="">Hora</option>
          <option v-for="opt in timeOptions" :key="'e'+opt" :value="opt">{{ opt }}</option>
        </select>
        <div v-if="errors.end_time" class="field-error">{{ errors.end_time }}</div>
      </div>
    </div>

    <div class="qaf-grid">
      <div class="field">
        <label class="label">Estado</label>
        <select v-model="form.status" class="input">
          <option value="scheduled">Programada</option>
          <option value="completed">Completada</option>
          <option value="confirmed">Confirmada</option>
          <option value="pending">Pendiente</option>
        </select>
      </div>
      <div class="field">
        <label class="label">Precio (€)</label>
        <input v-model.number="form.price" type="number" step="0.01" min="0" class="input" />
        <div v-if="errors.price" class="field-error">{{ errors.price }}</div>
      </div>
    </div>

    <div class="field">
      <label class="label">Notas (opcional)</label>
      <input v-model="form.notes" class="input" placeholder="Observaciones…" />
    </div>

    <div class="qaf-actions">
      <button type="submit" class="primary" :disabled="submitting">
        {{ submitting ? 'Guardando…' : 'Crear cita' }}
      </button>
    </div>
  </form>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import api from '../services/api'

const props = defineProps({
  patientId: { type: Number, default: null },
  onSuccess: { type: Function, default: null },
})

const emit = defineEmits(['saved'])

const patients = ref([])
const appointmentTypes = ref([])
const submitting = ref(false)
const errors = reactive({})

const form = reactive({
  patient_id: props.patientId || '',
  app_type_id: '',
  custom_type: '',
  date: new Date().toISOString().slice(0, 10),
  start_time: '',
  end_time: '',
  status: 'scheduled',
  price: 0,
  notes: '',
})

function generateTimeOptions() {
  const opts = []
  for (let h = 0; h < 24; h++) {
    for (let m = 0; m < 60; m += 15) {
      opts.push(`${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`)
    }
  }
  return opts
}
const timeOptions = generateTimeOptions()

function onTypeChange() {
  const selected = appointmentTypes.value.find(t => String(t.id) === String(form.app_type_id))
  if (selected && selected.price > 0) {
    form.price = selected.price
  }
}

async function loadPatients() {
  try {
    const res = await api.get('/patients', { params: { per_page: 200 } })
    patients.value = Array.isArray(res.data.data) ? res.data.data : (res.data || [])
  } catch { patients.value = [] }
}

async function loadTypes() {
  try {
    const res = await api.get('/appointment-types')
    appointmentTypes.value = Array.isArray(res.data.data) ? res.data.data : (Array.isArray(res.data) ? res.data : [])
  } catch { appointmentTypes.value = [] }
}

async function submit() {
  Object.keys(errors).forEach(k => delete errors[k])
  submitting.value = true

  try {
    const payload = {
      patient_id: Number(form.patient_id),
      date: form.date,
      start_time: form.start_time,
      end_time: form.end_time,
      price: Number(form.price) || 0,
      status: form.status,
      notes: form.notes || null,
    }
    if (form.app_type_id && form.app_type_id !== '__custom') {
      payload.app_type_id = Number(form.app_type_id)
    }
    if (form.app_type_id === '__custom' && form.custom_type) {
      payload.custom_type = form.custom_type
    }

    const res = await api.post('/appointments', payload)
    const data = res.data?.data || res.data
    emit('saved', data)
    props.onSuccess?.(data)
  } catch (e) {
    if (e.response?.status === 422) {
      const serverErrors = e.response.data?.errors || {}
      Object.assign(errors, Object.fromEntries(
        Object.entries(serverErrors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      ))
    } else {
      errors.general = e.response?.data?.message || 'Error creando cita'
    }
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  loadPatients()
  loadTypes()
})
</script>

<style scoped>
.qaf-form { display:flex; flex-direction:column; gap:14px }
.qaf-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px }
.qaf-grid-3 { grid-template-columns:1fr 1fr 1fr }
.qaf-actions { display:flex; justify-content:flex-end; margin-top:4px }
.qaf-error { margin-bottom:4px }
.field { display:flex; flex-direction:column }
.label { font-weight:600; margin-bottom:6px; font-size:13px }
.input { padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; width:100%; box-sizing:border-box }
.input:focus { outline:none; border-color:#3b82f6 }
.field-error { color:#b91c1c; font-size:13px; margin-top:4px }
</style>
