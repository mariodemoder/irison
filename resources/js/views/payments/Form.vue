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
            <select v-model="form.patient_id" class="input" required>
              <option value="">Selecciona paciente</option>
              <option v-for="p in patients" :key="p.id" :value="String(p.id)">
                {{ p.name }} {{ p.nif ? `— ${p.nif}` : '' }}
              </option>
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

          <div class="field full">
            <label class="label">Cita (opcional)</label>
            <input
              v-model="appointmentQuery"
              type="text"
              class="input"
              placeholder="Buscar cita por fecha, estado o saldo pendiente"
              :disabled="!form.patient_id"
            />
            <select v-model="form.appointment_id" class="input" :disabled="!form.patient_id || loadingAppointmentOptions">
              <option value="">Sin cita asociada</option>
              <option v-for="a in filteredAppointmentOptions" :key="a.id" :value="String(a.id)">
                {{ appointmentLabel(a) }}
              </option>
            </select>
            <div class="help-text" v-if="loadingAppointmentOptions">Cargando citas...</div>
            <div class="help-text" v-else-if="form.patient_id && filteredAppointmentOptions.length === 0">
              No hay citas no canceladas con saldo pendiente real para este paciente.
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

const form = reactive({
  patient_id: '',
  amount: 0,
  method: 'cash',
  status: 'completed',
  paid_at: '',
  appointment_id: '',
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
    form.amount = Number(data.amount || 0)
    form.method = data.method || 'cash'
    form.status = data.status || 'completed'
    form.paid_at = toDateTimeLocal(data.paid_at)
    form.appointment_id = data.appointment_id ? String(data.appointment_id) : ''
    form.notes = data.notes || ''

    if (form.patient_id) {
      await loadAppointmentOptions(Number(form.patient_id), data.appointment_id ? Number(data.appointment_id) : null)
    }
  } catch (e) {
    toast.error('Error cargando pago')
    router.push('/payments')
  }
}

function appointmentLabel(appointment) {
  const start = appointment.start_time ? new Date(appointment.start_time).toLocaleString('es-ES') : 'Sin fecha'
  const pending = Number(appointment.pending_amount || 0)
  const refunded = Number(appointment.refunded_amount || 0)
  const debt = Number(appointment.debt_amount || 0)

  return `#${appointment.id} · ${start} · ${appointment.payment_status} · deuda ${debt.toFixed(2)}€ · pendiente ${pending.toFixed(2)}€ · reembolsado ${refunded.toFixed(2)}€`
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

  const payload = {
    patient_id: Number(form.patient_id),
    amount: Number(form.amount),
    method: form.method,
    status: form.status,
    notes: form.notes || null,
    paid_at: form.paid_at || null,
    appointment_id: form.appointment_id ? Number(form.appointment_id) : null,
  }

  try {
    if (isEdit.value) {
      await api.put(`/payments/${route.params.id}`, payload)
      toast.success('Pago actualizado')
    } else {
      await api.post('/payments', payload)
      toast.success('Pago creado')
    }

    router.push('/payments')
  } catch (e) {
    const msg = e.response?.data?.message || 'Error guardando pago'
    errors.general = [msg]
  } finally {
    submitting.value = false
  }
}

function cancel() {
  router.push('/payments')
}

onMounted(async () => {
  await loadPatients()

  if (!route.params.id) {
    form.paid_at = toDateTimeLocal(new Date().toISOString())
  }

  if (route.params.id) {
    isEdit.value = true
    await loadForEdit(route.params.id)
  }
})

watch(
  () => form.patient_id,
  async (value, oldValue) => {
    if (!value) {
      form.appointment_id = ''
      appointmentOptions.value = []
      appointmentQuery.value = ''
      return
    }

    const changedPatient = String(value) !== String(oldValue || '')
    if (changedPatient) {
      form.appointment_id = ''
      appointmentQuery.value = ''
    }

    await loadAppointmentOptions(Number(value), form.appointment_id ? Number(form.appointment_id) : null)
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
