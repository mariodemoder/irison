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
            <div style="display:flex; gap:12px; align-items:flex-start">
              <select v-model="form.patient_id" @change="onPatientChange" class="input" :disabled="isCanceled && mode !== 'reprogram'">
                <option value="" disabled>Selecciona un paciente</option>
                <option v-for="p in patients" :key="p.id" :value="p.id">{{ p.name }}{{ p.nif ? (' — ' + p.nif) : '' }}</option>
                <option value="__create">+ Crear paciente...</option>
              </select>
              <div v-if="form.patient_id && (!bonuses || bonuses.length === 0)" class="inline-alert">
                <div>Sin bonos disponibles</div>
                <div>
                  <button type="button" class="muted" @click.prevent="suggestCreateBonus">Crear bono</button>
                </div>
              </div>
            </div>
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

          <div class="field" v-if="bonuses.length > 0">
            <label class="label">Seleccionar bono</label>
            <label style="display:flex; align-items:center; gap:8px"><input type="checkbox" v-model="selectBonus" /> Marcar para usar bono</label>
          </div>

          <div v-if="selectBonus && bonuses.length > 0" class="field full">
            <label class="label">Bono</label>
            <div v-if="bonusesLoading">Cargando bonos...</div>
            <div v-else>
              <div v-if="bonuses.length === 0" class="alert-subtle">
                <div>No hay bonos activos para este paciente.</div>
                <div style="margin-top:8px">
                  <button type="button" class="muted" @click.prevent="suggestCreateBonus">Sugerir crear bono</button>
                </div>
              </div>
              <select v-else v-model="form.use_bonus_id" class="input">
                <option value="" disabled>Selecciona un bono</option>
                <option v-for="b in bonuses" :key="b.id" :value="b.id">{{ b.total_sessions }} sesiones — {{ b.remaining_sessions }} restantes{{ b.expires_at ? (' — expira ' + formatExpiry(b.expires_at)) : '' }}</option>
              </select>
              <div v-if="errors.use_bonus_id" class="field-error">{{ errors.use_bonus_id[0] }}</div>
            </div>

            <label class="label" style="margin-top:8px">Notas (bono)</label>
            <input v-model="form.bonus_notes" class="input" />
          </div>

          <div class="actions full">
                  <button class="primary" type="submit" :disabled="submitting">Guardar</button>
                  <button v-if="isEdit && isFutureAppointment" type="button" class="muted" @click.prevent="startReprogram" :disabled="submitting">
                    Reprogramar
                  </button>
                  <button v-if="isEdit && !isCanceled" type="button" class="muted" @click.prevent="appointmentCancel" :disabled="submitting">
                    <IconCancel />
                    Cancelar Cita
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
import { formatDate } from '../../shared/appointmentHelpers'
import {
  openCreatePatientPopup as sharedOpenCreatePatientPopup,
  loadPatients as loadPatientsShared,
  checkOverlapShared,
  goBack as sharedGoBack,
  startReprogramShared,
  appointmentCancelShared
} from '../../shared/formHelpers'

function formatExpiry(v) {
  if (!v) return ''
  try {
    const d = new Date(v)
    const dd = String(d.getDate()).padStart(2, '0')
    const mm = String(d.getMonth() + 1).padStart(2, '0')
    const yyyy = d.getFullYear()
    return `${dd}/${mm}/${yyyy}`
  } catch (e) {
    return ''
  }
}

const router = useRouter()
const route = useRoute()
const isEdit = ref(false)
const mode = ref(route.query.mode || null)
const form = reactive({ patient_id: '', status: 'scheduled', start_time: '', end_time: '', notes: '', use_bonus_id: '', bonus_notes: '' })

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
const selectBonus = ref(false)

const bonuses = ref([])
const bonusesLoading = ref(false)

async function onPatientChange() {
  if (form.patient_id === '__create') {
    const toast = useToast()
    const newPatient = await sharedOpenCreatePatientPopup({ api, Swal, toast })
    if (newPatient) {
      patients.value.unshift(newPatient)
      form.patient_id = newPatient.id
    } else {
      form.patient_id = ''
    }
  }
  // Load all bonuses for the selected patient so user can choose
  if (form.patient_id && form.patient_id !== '__create') {
    await loadBonusesForPatient(form.patient_id)
  } else {
    bonuses.value = []
    selectBonus.value = false
    form.use_bonus_id = ''
  }
}

async function loadBonusesForPatient(patientId) {
  bonuses.value = []
  if (!patientId) return
  bonusesLoading.value = true
  try {
    const res = await api.get(`/patients/${patientId}/bonuses`)
    bonuses.value = (res.data && res.data.data) ? res.data.data : []
  } catch (e) {
    bonuses.value = []
  } finally {
    bonusesLoading.value = false
    // If there are no bonuses, ensure checkbox is off and selection cleared
    if (!bonuses.value || bonuses.value.length === 0) {
      selectBonus.value = false
      form.use_bonus_id = ''
    }
  }
}

// openCreatePatientPopup moved to shared/formHelpers


async function loadPatients() {
  patients.value = await loadPatientsShared(api)
}

function suggestCreateBonus() {
  const toast = useToast()
  if (!form.patient_id) {
    toast.info('Selecciona primero un paciente')
    return
  }

  // Navegar a la ficha del paciente donde existe la card para crear bonos
  router.push({ path: `/patients/${form.patient_id}`, query: { open: 'bonuses' } })
}

function cancel() {
  sharedGoBack(router, route)
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
        const currentId = route.params.id ? String(route.params.id) : null
        const cleaned = await checkOverlapShared({ start: form.start_time, end: form.end_time, currentId, api, Swal })
        overlapping.value = cleaned
      } catch (e) {
        overlapping.value = []
      }
      resolve()
    }, 300)
  })
}

function appointmentCancel() {
  const toast = useToast()
  appointmentCancelShared(route.params.id, { api, toast, router }).catch(() => {})
 
}

// formatDate moved to shared/appointmentHelpers

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
  startReprogramShared(router, route)
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

onMounted(async () => {
  const id = route.params.id
  if (id) {
    isEdit.value = true
    await loadForEdit(id)
  }
  // Load patients list first so we can preselect patient from query
  await loadPatients()

  // If opened with ?patient_id=..., preselect that patient for creation
  const preselect = route.query.patient_id
  if (!isEdit.value && preselect) {
    form.patient_id = String(preselect)
    // Load bonuses for the preselected patient
    await loadBonusesForPatient(form.patient_id)
  }
})

// When selecting patient, load bonuses for that patient
watch(() => form.patient_id, (id) => {
  if (id && id !== '__create') loadBonusesForPatient(id)
})

// When toggling 'selectBonus', ensure bonuses are loaded
watch(() => selectBonus.value, (v) => {
  if (v && form.patient_id) loadBonusesForPatient(form.patient_id)
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
      use_bonus_id: form.use_bonus_id || undefined,
      bonus_notes: form.bonus_notes || undefined,
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
      // If user marked to select a bonus but there are no bonuses, block and show error
      if (selectBonus.value && (!bonuses.value || bonuses.value.length === 0) && !payload.use_bonus_id) {
        errors.general = ['No hay bonos activos disponibles para este paciente']
        submitting.value = false
        return
      }

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

.alert-subtle { background: #f8fafc; border: 1px solid #e6edf3; padding:10px; border-radius:8px; color:#334155; font-size:14px }

.inline-alert { display:flex; flex-direction:column; gap:6px; background: #f8fafc; border: 1px solid #e6edf3; padding:8px; border-radius:8px; color:#334155; font-size:13px; max-width:360px }
.inline-alert button { padding:6px 10px; font-size:13px }
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
