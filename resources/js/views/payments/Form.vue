<template>
  <MainLayout>
    <div class="form-wrapper">
      <div class="form-card">
        <div class="form-header">
          <h1>{{ isEdit ? 'Editar pago' : 'Nuevo pago' }}</h1>
          <p class="form-sub">{{ isEdit ? 'Actualiza los datos del pago.' : 'Registra un nuevo pago de cliente.' }}</p>
        </div>

        <form class="grid-form" @submit.prevent="submit">
          <div class="field full" v-if="errors.general">
            <div class="field-error">{{ errors.general[0] }}</div>
          </div>

          <div class="field full">
            <label class="label">Paciente</label>
            <select v-model="form.patient_id" class="input" :disabled="comingFromAppointment" required>
              <option value="">Selecciona paciente</option>
              <option v-for="p in patients" :key="p.id" :value="String(p.id)">
                {{ p.name }} {{ p.nif ? `— ${p.nif}` : '' }}
              </option>
            </select>
          </div>

          <div class="field full">
            <label class="label">Motivo del ingreso</label>
            <select v-model="form.concept" class="input" :disabled="comingFromAppointment" required>
              <option value="appointment">🧾 Pago de cita individual</option>
              <option value="package">🎁 Compra de bono</option>
              <option value="credit">💰 Adelanto (crédito a favor)</option>
            </select>
          </div>

          <div class="field">
            <label class="label">Importe (€)</label>
            <input v-model.number="form.amount" type="number" min="0" step="0.01" class="input" required />
          </div>

          <div class="field">
            <label class="label">Método</label>
            <select v-model="form.method" class="input" required>
              <option value="cash">Efectivo</option>
              <option value="card">Tarjeta</option>
              <option value="transfer">Transferencia</option>
            </select>
          </div>

          <div class="field">
            <label class="label">Estado</label>
            <select v-model="form.status" class="input" required>
              <option value="completed">Completado</option>
              <option value="pending">Pendiente</option>
              <option value="refunded">Reembolsado</option>
            </select>
          </div>

          <div class="field">
            <label class="label">Fecha de pago</label>
            <input v-model="form.paid_at" type="datetime-local" class="input" />
          </div>

          <div class="field full" v-if="form.concept === 'appointment'">
            <label class="label">Cita (obligatoria)</label>
            <div v-if="comingFromAppointment && form.appointment_id" style="padding:12px; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; color:#065f46; font-size:13px; margin-bottom:8px">
              <strong>Cita seleccionada:</strong> #{{ form.appointment_id }}
            </div>
            <div v-if="!comingFromAppointment" class="combo-inline">
              <input
                ref="appointmentInputEl"
                v-model="appointmentDisplay"
                type="text"
                class="input combo-text"
                list="appointment-options-list"
                placeholder="Selecciona cita impaga/parcial"
                :disabled="!form.patient_id || loadingAppointmentOptions"
                @input="onAppointmentDisplayInput"
              />
              <button
                type="button"
                class="combo-side-btn"
                :disabled="!form.patient_id || loadingAppointmentOptions"
                @click="handleAppointmentSideButton"
                :title="hasAppointmentSelection ? 'Limpiar selección' : 'Mostrar opciones'"
              >
                {{ hasAppointmentSelection ? '✕' : '▾' }}
              </button>
            </div>
            <datalist id="appointment-options-list">
              <option v-for="a in filteredAppointmentOptions" :key="a.id" :value="appointmentOptionValue(a)"></option>
            </datalist>
            <div class="help-text" v-if="loadingAppointmentOptions">Cargando citas...</div>
            <div class="help-text" v-else-if="form.patient_id && filteredAppointmentOptions.length === 0">
              No hay citas impagas/parciales sin bono asignado para este paciente.
            </div>
          </div>

          <div class="field full" v-if="form.concept === 'package'">
            <label class="label">Bono (obligatorio)</label>
            <div class="combo-inline">
              <input
                ref="packageInputEl"
                v-model="packageDisplay"
                type="text"
                class="input combo-text"
                list="package-options-list"
                placeholder="Selecciona bono impago/parcial"
                :disabled="!form.patient_id || loadingPackageOptions"
                @input="onPackageDisplayInput"
              />
              <button
                type="button"
                class="combo-side-btn"
                :disabled="!form.patient_id || loadingPackageOptions"
                @click="handlePackageSideButton"
                :title="hasPackageSelection ? 'Limpiar selección' : 'Mostrar opciones'"
              >
                {{ hasPackageSelection ? '✕' : '▾' }}
              </button>
            </div>
            <datalist id="package-options-list">
              <option v-for="pkg in filteredPackageOptions" :key="pkg.id" :value="packageOptionValue(pkg)"></option>
            </datalist>
            <div class="help-text" v-if="loadingPackageOptions">Cargando bonos...</div>
            <div class="help-text" v-else-if="form.patient_id && filteredPackageOptions.length === 0">
              No hay bonos impagos/parciales para este paciente.
            </div>
          </div>

          <div class="field full" v-if="form.concept === 'credit'">
            <div class="help-text">
              Este pago se registrará como adelanto y quedará como saldo a favor del paciente.
            </div>
          </div>

          <div class="field full">
            <label class="label">Notas (opcional)</label>
            <textarea v-model="form.notes" rows="3" class="input"></textarea>
          </div>

          <div class="actions full">
            <button class="primary" type="submit" :disabled="submitting">{{ submitting ? 'Guardando...' : 'Guardar' }}</button>
            <button type="button" class="muted" @click.prevent="cancel">Cancelar</button>
          </div>
        </form>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import MainLayout from '../../layouts/MainLayout.vue'
import api from '../../services/api'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const isEdit = ref(false)
const submitting = ref(false)
const patients = ref([])
const errors = reactive({})
const appointmentOptions = ref([])
const loadingAppointmentOptions = ref(false)
const appointmentQuery = ref('')
const packageOptions = ref([])
const loadingPackageOptions = ref(false)
const packageQuery = ref('')
const comingFromAppointment = ref(false)
const appointmentDisplay = ref('')
const packageDisplay = ref('')
const appointmentInputEl = ref(null)
const packageInputEl = ref(null)

const hasAppointmentSelection = computed(() => !!form.appointment_id)
const hasPackageSelection = computed(() => !!form.package_id)

const form = reactive({
  patient_id: '',
  concept: 'appointment',
  amount: 0,
  method: 'cash',
  status: 'completed',
  paid_at: '',
  appointment_id: '',
  package_id: '',
  notes: '',
})

const filteredAppointmentOptions = computed(() => {
  const q = (appointmentQuery.value || '').toLowerCase().trim()
  if (!q) return appointmentOptions.value

  return appointmentOptions.value.filter(option => {
    const values = [
      String(option.id),
      option.status,
      option.payment_status,
      option.start_time,
      option.end_time,
      String(option.pending_amount),
      String(option.refunded_amount),
      String(option.debt_amount),
      appointmentLabel(option),
    ]

    return values.some(v => String(v || '').toLowerCase().includes(q))
  })
})

const pendingOrSelectedPackageOptions = computed(() => {
  return packageOptions.value.filter(option => {
    const pendingAmount = Number(option.pending_amount || 0)
    const outstandingAmount = Number(option.outstanding_amount || 0)
    const isCurrentSelected = form.package_id && String(option.id) === String(form.package_id)
    return pendingAmount > 0 || outstandingAmount > 0 || isCurrentSelected
  })
})

const filteredPackageOptions = computed(() => {
  const q = (packageQuery.value || '').toLowerCase().trim()
  if (!q) return pendingOrSelectedPackageOptions.value

  return pendingOrSelectedPackageOptions.value.filter(option => {
    const values = [
      String(option.id),
      option.status,
      String(option.price),
      String(option.completed_amount),
      String(option.pending_amount),
      String(option.outstanding_amount),
      packageLabel(option),
    ]

    return values.some(v => String(v || '').toLowerCase().includes(q))
  })
})

function clearErrors() {
  Object.keys(errors).forEach(k => delete errors[k])
}

async function loadPatients() {
  try {
    const res = await api.get('/patients', { params: { per_page: 100 } })
    patients.value = Array.isArray(res.data?.data) ? res.data.data : []
  } catch (e) {
    patients.value = []
    errors.general = ['No se pudieron cargar pacientes']
  }
}

async function loadForEdit(id) {
  try {
    const res = await api.get(`/payments/${id}`)
    const data = res.data || {}
    form.patient_id = data.patient_id ? String(data.patient_id) : ''
    form.concept = data.concept || (data.appointment_id ? 'appointment' : (data.package_id ? 'package' : 'credit'))
    form.amount = Number(data.amount || 0)
    form.method = data.method || 'cash'
    form.status = data.status || 'completed'
    form.paid_at = toDateTimeLocal(data.paid_at)
    form.appointment_id = data.appointment_id ? String(data.appointment_id) : ''
    form.package_id = data.package_id ? String(data.package_id) : ''
    form.notes = data.notes || ''

    if (form.patient_id) {
      await Promise.all([
        loadAppointmentOptions(Number(form.patient_id), data.appointment_id ? Number(data.appointment_id) : null),
        loadPackageOptions(Number(form.patient_id), data.package_id ? Number(data.package_id) : null),
      ])
      syncAppointmentDisplayFromId()
      syncPackageDisplayFromId()
    }
  } catch (e) {
    toast.error('Error cargando pago')
    router.push('/payments')
  }
}

function packageLabel(pkg) {
  const name = pkg.name || `Bono #${pkg.id}`
  const totalSessions = Number(pkg.total_sessions || 0)
  const expiresAt = formatShortDate(pkg.expires_at)

  return `${name} · ${totalSessions} sesiones · expira ${expiresAt}`
}

function packageOptionValue(pkg) {
  return `#${pkg.id} · ${packageLabel(pkg)}`
}

function formatShortDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  const dd = String(date.getDate()).padStart(2, '0')
  const mm = String(date.getMonth() + 1).padStart(2, '0')
  const yyyy = date.getFullYear()
  return `${dd}/${mm}/${yyyy}`
}

function appointmentLabel(appointment) {
  const start = appointment.start_time ? new Date(appointment.start_time).toLocaleString('es-ES') : 'Sin fecha inicio'
  const end = appointment.end_time ? new Date(appointment.end_time).toLocaleString('es-ES') : 'Sin fecha fin'
  const rawNotes = (appointment.notes || '').trim()
  const notes = rawNotes
    ? (rawNotes.length > 30 ? `${rawNotes.slice(0, 30)}...` : rawNotes)
    : 'Sin notas'

  return `(${start} - ${end}) - ${notes}`
}

function appointmentOptionValue(appointment) {
  return `#${appointment.id} · ${appointmentLabel(appointment)}`
}

function syncAppointmentDisplayFromId() {
  if (!form.appointment_id) {
    appointmentDisplay.value = ''
    return
  }
  const selected = appointmentOptions.value.find(a => String(a.id) === String(form.appointment_id))
  appointmentDisplay.value = selected ? appointmentOptionValue(selected) : ''
}

function syncPackageDisplayFromId() {
  if (!form.package_id) {
    packageDisplay.value = ''
    return
  }
  const selected = filteredPackageOptions.value.find(p => String(p.id) === String(form.package_id))
    || packageOptions.value.find(p => String(p.id) === String(form.package_id))
  packageDisplay.value = selected ? packageOptionValue(selected) : ''
}

function onAppointmentDisplayInput() {
  appointmentQuery.value = appointmentDisplay.value
  const selected = appointmentOptions.value.find(a => appointmentOptionValue(a) === appointmentDisplay.value)
  form.appointment_id = selected ? String(selected.id) : ''
}

function onPackageDisplayInput() {
  packageQuery.value = packageDisplay.value
  const selected = filteredPackageOptions.value.find(p => packageOptionValue(p) === packageDisplay.value)
  form.package_id = selected ? String(selected.id) : ''
}

function handleAppointmentSideButton() {
  if (hasAppointmentSelection.value) {
    form.appointment_id = ''
    appointmentDisplay.value = ''
    appointmentQuery.value = ''
  }

  if (appointmentInputEl.value) {
    appointmentInputEl.value.focus()
  }
}

function handlePackageSideButton() {
  if (hasPackageSelection.value) {
    form.package_id = ''
    packageDisplay.value = ''
    packageQuery.value = ''
  }

  if (packageInputEl.value) {
    packageInputEl.value.focus()
  }
}

async function loadPackageOptions(patientId, currentPackageId = null) {
  if (!patientId) {
    packageOptions.value = []
    return
  }

  loadingPackageOptions.value = true
  try {
    const res = await api.get('/payments/package-options', {
      params: {
        patient_id: patientId,
        current_package_id: currentPackageId || undefined,
      },
    })

    packageOptions.value = Array.isArray(res.data?.data) ? res.data.data : []
    syncPackageDisplayFromId()
  } catch (e) {
    packageOptions.value = []
    errors.general = ['No se pudieron cargar los bonos del paciente']
  } finally {
    loadingPackageOptions.value = false
  }
}

async function loadAppointmentOptions(patientId, currentAppointmentId = null) {
  if (!patientId) {
    appointmentOptions.value = []
    return
  }

  loadingAppointmentOptions.value = true
  try {
    const res = await api.get('/payments/appointment-options', {
      params: {
        patient_id: patientId,
        current_appointment_id: currentAppointmentId || undefined,
      },
    })

    appointmentOptions.value = Array.isArray(res.data?.data) ? res.data.data : []
    syncAppointmentDisplayFromId()
  } catch (e) {
    appointmentOptions.value = []
    errors.general = ['No se pudieron cargar las citas del paciente']
  } finally {
    loadingAppointmentOptions.value = false
  }
}

function toDateTimeLocal(value) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''

  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')

  return `${year}-${month}-${day}T${hours}:${minutes}`
}

async function submit() {
  submitting.value = true
  clearErrors()

  if (form.concept === 'appointment' && !form.appointment_id) {
    errors.general = ['Debes seleccionar una cita impaga o parcial.']
    submitting.value = false
    return
  }

  if (form.concept === 'package' && !form.package_id) {
    errors.general = ['Debes seleccionar un bono impago o parcial.']
    submitting.value = false
    return
  }

  const payload = {
    patient_id: Number(form.patient_id),
    concept: form.concept,
    amount: Number(form.amount),
    method: form.method,
    status: form.status,
    notes: form.notes || null,
    paid_at: form.paid_at || null,
    appointment_id: form.concept === 'appointment' && form.appointment_id ? Number(form.appointment_id) : null,
    package_id: form.concept === 'package' && form.package_id ? Number(form.package_id) : null,
  }

  try {
    if (isEdit.value) {
      await api.put(`/payments/${route.params.id}`, payload)
      toast.success('Pago actualizado')
    } else {
      await api.post('/payments', payload)
      toast.success('Pago creado')
    }

    // If coming from appointment form, navigate back to appointment
    if (comingFromAppointment.value && form.appointment_id) {
      router.push(`/appointments/${form.appointment_id}`)
    } else {
      router.push('/payments')
    }
  } catch (e) {
    const msg = e.response?.data?.message || 'Error guardando pago'
    errors.general = [msg]
  } finally {
    submitting.value = false
  }
}

function cancel() {
  if (comingFromAppointment.value && form.appointment_id) {
    router.push(`/appointments/${form.appointment_id}`)
  } else {
    router.push('/payments')
  }
}

onMounted(async () => {
  await loadPatients()

  // Check if coming from appointment form with preloaded params
  if (route.query.patient_id && route.query.concept === 'appointment' && route.query.appointment_id) {
    comingFromAppointment.value = true
    form.patient_id = String(route.query.patient_id)
    form.concept = 'appointment'
    form.appointment_id = String(route.query.appointment_id)
    form.paid_at = toDateTimeLocal(new Date().toISOString())
    // Load appointment options for this patient
    await loadAppointmentOptions(Number(form.patient_id), Number(form.appointment_id))
    syncAppointmentDisplayFromId()
  } else {
    if (!route.params.id) {
      form.paid_at = toDateTimeLocal(new Date().toISOString())
    }

    if (route.params.id) {
      isEdit.value = true
      await loadForEdit(route.params.id)
    }
  }
})

watch(
  () => form.patient_id,
  async (value, oldValue) => {
    if (!value) {
      form.appointment_id = ''
      form.package_id = ''
      appointmentOptions.value = []
      packageOptions.value = []
      appointmentQuery.value = ''
      packageQuery.value = ''
      appointmentDisplay.value = ''
      packageDisplay.value = ''
      return
    }

    const changedPatient = String(value) !== String(oldValue || '')
    if (changedPatient && !comingFromAppointment.value) {
      form.appointment_id = ''
      form.package_id = ''
      appointmentQuery.value = ''
      packageQuery.value = ''
      appointmentDisplay.value = ''
      packageDisplay.value = ''
    }

    await Promise.all([
      loadAppointmentOptions(Number(value), form.appointment_id ? Number(form.appointment_id) : null),
      loadPackageOptions(Number(value), form.package_id ? Number(form.package_id) : null),
    ])
  }
)

watch(
  () => form.concept,
  async (value) => {
    if (value !== 'appointment') {
      form.appointment_id = ''
      appointmentQuery.value = ''
      appointmentDisplay.value = ''
    }

    if (value !== 'package') {
      form.package_id = ''
      packageQuery.value = ''
      packageDisplay.value = ''
      return
    }

    if (form.patient_id) {
      await loadPackageOptions(Number(form.patient_id), form.package_id ? Number(form.package_id) : null)
    }
  }
)
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
.input { padding:12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px }
.combo-inline { display:flex; align-items:center; gap:8px }
.combo-text { flex:1 }
.combo-side-btn {
  height:42px;
  min-width:42px;
  border-radius:8px;
  border:1px solid #e5e7eb;
  background:#fff;
  color:#6b7280;
  font-size:14px;
}
.combo-side-btn:disabled { opacity:0.5 }
.help-text { color:#6b7280; font-size:12px; margin-top:6px }
.field-error { color:#b91c1c; font-size:13px; margin-top:6px }

.actions { display:flex; gap:12px; align-items:center }
.primary {
  padding: 8px 16px;
  font-size: 14px;
  border-radius: 9999px;
  border: 2px solid #3b82f6;
  color: #3b82f6;
  background: #ffffff;
  font-weight: 600;
}
.primary:hover { background: #eff6ff }

@media (max-width: 768px) {
  .grid-form { grid-template-columns: 1fr }
}
</style>
