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
            <select v-model="form.patient_id" @change="onPatientChange" class="input" :disabled="isCanceled && mode !== 'reprogram'">
              <option value="" disabled>Selecciona un paciente</option>
              <option v-for="p in patients" :key="p.id" :value="p.id">{{ p.name }}{{ p.nif ? (' — ' + p.nif) : '' }}</option>
              <option value="__create">+ Crear paciente...</option>
            </select>
            <div v-if="errors.patient_id" class="field-error">{{ errors.patient_id[0] }}</div>
          </div>
          <div class="field">
            <label class="label">Estado</label>
            <OptionSelect v-model="form.status" :options="statusOptions" :disabled="isCanceled" />
            <div v-if="errors.status" class="field-error">{{ errors.status[0] }}</div>
          </div>
          <div class="field">
            <label class="label">Inicio</label>
            <input v-model="form.start_time" type="datetime-local" class="input" :disabled="isCanceled && mode !== 'reprogram'" />
            <div v-if="errors.start_time" class="field-error">{{ errors.start_time[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Fin</label>
            <input v-model="form.end_time" type="datetime-local" class="input" :disabled="isCanceled && mode !== 'reprogram'" />
            <div v-if="errors.end_time" class="field-error">{{ errors.end_time[0] }}</div>
            <div v-if="overlapping.length">
              <div v-if="hasScheduledOverlap" class="field-error">La franja horaria se solapa con otra cita programada.</div>
              <ul class="overlap-list">
                <li v-for="a in overlapping" :key="a.id" class="overlap-item">
                  <div style="display:flex; gap:8px; align-items:center;">
                    <div style="flex:1">
                      <strong>{{ formatDate(a.start_time) }} - {{ formatDate(a.end_time) }}</strong>
                      <div style="color:#374151">{{ a.patient?.name || a.patient_name || 'Paciente desconocido' }}</div>
                    </div>
                    <div>
                      <button type="button" class="muted" @click.prevent="goToAppointment(a.id)">Ir a cita</button>
                    </div>
                  </div>
                </li>
              </ul>
            </div>
          </div>

          <div class="field full">
            <label class="label">Notas</label>
            <textarea v-model="form.notes" class="textarea" rows="4" :disabled="isCanceled && mode !== 'reprogram'"></textarea>
            <div v-if="errors.notes" class="field-error">{{ errors.notes[0] }}</div>
          </div>

          <div class="actions full">
                  <button class="primary" type="submit" :disabled="submitting">Guardar</button>
                  <button v-if="isEdit && isFutureAppointment" type="button" class="muted" @click.prevent="startReprogram" :disabled="submitting">
                    Reprogramar
                  </button>
                  <button v-if="isEdit && !isCanceled" type="button" class="muted" @click.prevent="appointmentCancel" :disabled="submitting">
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
import OptionSelect from '../../components/OptionSelect.vue'
import { useToast } from 'vue-toastification'
import Swal from 'sweetalert2'

const router = useRouter()
const route = useRoute()
const isEdit = ref(false)
const mode = ref(route.query.mode || null)
const form = reactive({ patient_id: '', status: 'scheduled', start_time: '', end_time: '', notes: '' })

const statusOptions = [
  { value: 'scheduled', label: 'Programada', color: '#99b1ff' },
  { value: 'completed', label: 'Completada', color: '#a1f7bf' },
  { value: 'canceled', label: 'Cancelada', color: '#ffcccc' }
]
const isCanceled = ref(false)
const originalStart = ref(null)
const canReprogramInForm = ref(false)
const errors = reactive({})
const submitting = ref(false)
const loading = ref(false)
const patients = ref([])
const overlapping = ref([])
const hasScheduledOverlap = computed(() => overlapping.value.some(a => a.status === 'scheduled'))
let overlapTimer = null

function onPatientChange() {
  if (form.patient_id === '__create') {
    // abrir popup para crear paciente
    openCreatePatientPopup()
  }
}

async function openCreatePatientPopup() {
  const { value: formValues } = await Swal.fire({
    title: 'Crear paciente',
    html:
    
      '<div class="swal-card">' +
      '<input id="swal-name" class="input" placeholder="Nombre">' +
      '<input id="swal-nif" class="input" placeholder="NIF (opcional)">' +
      '<input id="swal-phone" class="input" placeholder="Teléfono (opcional)">' +
      '<input id="swal-email" class="input" placeholder="Email (opcional)">' +
      '</div>',
    focusConfirm: false,
    showCancelButton: true,
    confirmButtonText: 'Crear',
    cancelButtonText: 'Cancelar',
    buttonsStyling: false,
    customClass: {
      popup: 'swal-popup-card',
      confirmButton: 'primary',
      cancelButton: 'muted'
    },
    preConfirm: async () => {
      const name = document.getElementById('swal-name')?.value?.trim()
      const nif = document.getElementById('swal-nif')?.value?.trim() || null
      const phone = document.getElementById('swal-phone')?.value?.trim() || null
      const email = document.getElementById('swal-email')?.value?.trim() || null
      if (!name) {
        Swal.showValidationMessage('El nombre es requerido')
        return false
      }
      try {
        const res = await api.post('/patients', { name, nif, phone, email })
        return res.data || res.data?.data || res
      } catch (e) {
        const msg = e.response?.data?.message || 'Error creando paciente'
        Swal.showValidationMessage(msg)
        return false
      }
    }
  })

  if (formValues) {
    const newPatient = formValues.data ? formValues.data : formValues
    patients.value.unshift(newPatient)
    form.patient_id = newPatient.id
    const toast = useToast()
    toast.success('Paciente creado')
  } else {
    form.patient_id = ''
  }
}


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

function checkOverlap() {
  // no hay rango completo
  if (!form.start_time || !form.end_time) {
    overlapping.value = []
    return Promise.resolve()
  }

  if (overlapTimer) clearTimeout(overlapTimer)

  return new Promise((resolve) => {
    overlapTimer = setTimeout(async () => {
      try {
        const params = {
          start: new Date(form.start_time).toISOString(),
          end: new Date(form.end_time).toISOString(),
          per_page: 200
        }
        const res = await api.get('/appointments', { params })
        const list = Array.isArray(res.data.data) ? res.data.data : (res.data || [])
        const currentId = route.params.id ? String(route.params.id) : null

        // calcular inicio/fin del intervalo elegido
        const chosenStart = new Date(form.start_time)
        const chosenEnd = new Date(form.end_time)

        // helper: mismo día (comparación por Y/M/D local)
        const isSameDay = (d1, d2) => (
          d1.getFullYear() === d2.getFullYear() &&
          d1.getMonth() === d2.getMonth() &&
          d1.getDate() === d2.getDate()
        )

        // conservar solo citas que (a) se solapen en tiempo con el intervalo elegido
        // y (b) ocurran el mismo día que el inicio elegido
        const filtered = list.filter(a => {
          try {
            const aStart = new Date(a.start_time)
            const aEnd = new Date(a.end_time)
            const intersects = (aStart < chosenEnd && aEnd > chosenStart)
            return intersects && isSameDay(aStart, chosenStart)
          } catch (e) {
            return false
          }
        }).filter(a => String(a.id) !== currentId)
        overlapping.value = filtered
        const hasScheduled = filtered.some(a => a.status === 'scheduled')
        if (hasScheduled) {
          Swal.fire({
            icon: 'warning',
            title: 'La franja horaria se solapa con otra cita.',
            text: 'Hay una cita ya programada en ese intervalo de tiempo (mismo día).',
            confirmButtonText: 'Entendido',
            buttonsStyling: false,
            customClass: { confirmButton: 'primary' }
          })
        }
      } catch (e) {
        overlapping.value = []
      }
      resolve()
    }, 300)
  })
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

function formatDate(d) {
  try {
    if (!d) return ''
    const dt = new Date(d)
    return dt.toLocaleString()
  } catch (e) {
    return d
  }
}

function goToAppointment(id) {
  if (!id) return
  router.push(`/appointments/${id}`)
}

const isFutureAppointment = computed(() => {
  try {
    const t = originalStart.value || form.start_time
    if (!t) return false
    const dt = new Date(t)
    return dt.getTime() > Date.now()
  } catch (e) {
    return false
  }
})

function startReprogram() {
  // enable reprogram mode in the route so form respects reprogram behavior
  router.push({ query: { ...route.query, mode: 'reprogram' } })
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

watch(() => [form.start_time, form.end_time], () => {
  checkOverlap()
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
      // comprobar solapamiento antes de enviar (muestra aviso, pero no bloquea)
      await checkOverlap()
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

/* Estilos globales para el popup de creación de paciente */
<style>
.swal-popup-card {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(2,6,23,0.06);
  padding: 18px 18px 16px;
  max-width: 480px;
}
.swal-popup-card .swal2-title { margin-bottom:8px }
.swal-card { display:flex; flex-direction:column; gap:10px }
.swal-card .input { width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7eb; box-sizing:border-box }
.swal2-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:12px }
.swal2-actions .primary, .primary { padding: 8px 16px; font-size: 14px; border-radius: 9999px; border: 2px solid #3b82f6; color: #3b82f6; background: #ffffff; font-weight: 600 }
.swal2-actions .primary:hover, .primary:hover { background:#eff6ff }
.swal2-actions .muted { padding:8px 14px; border-radius:9999px; border:1px solid #e5e7eb; background:#fff }


</style>
