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
            <select v-model="form.patient_id" @change="onPatientChange" class="input" :disabled="comingFromAppointment" required>
              <option value="">Selecciona paciente</option>
              <option v-for="p in patients" :key="p.id" :value="String(p.id)">
                {{ p.name }} {{ p.nif ? `— ${p.nif}` : '' }}
              </option>
              <option value="__create">+ Crear paciente...</option>
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
            <AppLoading v-if="loadingAppointmentOptions" compact message="Cargando citas..." />
            <div class="help-text" v-else-if="form.patient_id && filteredAppointmentOptions.length === 0">
              No hay citas impagas/parciales sin bono asignado para este paciente.
            </div>
          </div>

          <div class="field full" v-if="form.concept === 'package'">
            <label class="label">Bono (obligatorio)</label>
            <select
              v-model="form.package_id"
              @change="onPackageSelectChange"
              class="input"
              :disabled="!form.patient_id || loadingPackageOptions"
              style="width:100%"
            >
              <option value="" disabled>Selecciona bono impago/parcial</option>
              <option value="__create_bonus">+ Crear bono...</option>
              <option v-if="filteredPackageOptions.length === 0" value="" disabled>No hay bonos impagos/parciales</option>
              <option v-for="pkg in filteredPackageOptions" :key="pkg.id" :value="String(pkg.id)">
                {{ packageOptionValue(pkg) }}
              </option>
            </select>
            <AppLoading v-if="loadingPackageOptions" compact message="Cargando bonos..." />
            <div class="help-text" v-else-if="form.patient_id && filteredPackageOptions.length === 0">
              No hay bonos impagos/parciales para este paciente.
            </div>
          </div>

          <div class="field full" v-if="form.concept === 'credit'">
            <div class="help-text">
              Este pago se registrará como adelanto y quedará como saldo a favor del paciente.
            </div>
          </div>
          
          <div class="field" v-if="form.concept === 'package'">
            <label class="label">Total a pagar</label>
            <div class="money-total">{{ formatMoney(packagePendingAmount) }}</div>
          </div>

          <div class="field" v-if="form.concept === 'package'">
            <label class="label">Importe (€) A pagar</label>
            <input
              :value="amountInputValue"
              type="text"
              inputmode="decimal"
              class="input"
              :disabled="form.concept === 'package'"
              required
              @focus="onAmountFocus"
              @input="onAmountInput"
              @blur="onAmountBlur"
            />
          </div>

          <div class="field" v-if="form.concept !== 'package'">
            <label class="label">Importe (€)</label>
            <input
              :value="amountInputValue"
              type="text"
              inputmode="decimal"
              class="input"
              required
              @focus="onAmountFocus"
              @input="onAmountInput"
              @blur="onAmountBlur"
            />
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
            <select v-model="form.status" class="input" :disabled="form.concept === 'package'" required>
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
import Swal from 'sweetalert2'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import api from '../../services/api'
import {
  openCreatePatientPopup as sharedOpenCreatePatientPopup,
  loadPatients as loadPatientsShared,
} from '../../shared/formHelpers'

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
const comingFromAppointment = ref(false)
const appointmentDisplay = ref('')
const appointmentInputEl = ref(null)

const hasAppointmentSelection = computed(() => !!form.appointment_id)
const hasPackageSelection = computed(() => !!form.package_id)
const selectedPackageOption = computed(() => {
  if (!form.package_id) return null
  if (String(form.package_id) === '__create_bonus') return null
  return packageOptions.value.find(option => String(option.id) === String(form.package_id)) || null
})

function resolvePackageTotalPrice(option) {
  const raw = option?.price ?? option?.bonus_price ?? option?.bonus?.price ?? 0
  const value = Number(raw)
  return Number.isFinite(value) ? value : 0
}

const packagePendingAmount = computed(() => {
  const selected = selectedPackageOption.value
  if (!selected) return 0

  const totalPrice = resolvePackageTotalPrice(selected)
  const completedAmount = Number(selected.completed_amount || 0)
  const pendingAmount = Number(selected.pending_amount || 0)
  const foundPaymentsAmount = completedAmount + pendingAmount

  return Math.max(totalPrice - foundPaymentsAmount, 0)
})

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
const amountInputFocused = ref(false)
const amountInputDraft = ref('')

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
  const unpaidStatuses = new Set([
    'pending',
    'partially_paid',
    'partial',
    'unpaid',
    'incomplete',
  ])

  const filtered = packageOptions.value.filter(option => {
    const pendingAmount = Number(option.pending_amount || 0)
    const outstandingAmount = Number(option.outstanding_amount || 0)
    const status = String(option.status || '').toLowerCase().trim()
    const isUnpaidByStatus = unpaidStatuses.has(status)
    const isCurrentSelected = form.package_id && String(option.id) === String(form.package_id)
    return pendingAmount > 0 || outstandingAmount > 0 || isUnpaidByStatus || isCurrentSelected
  })

  return [...filtered].sort((a, b) => {
    const aPending = Math.max(Number(a.pending_amount || 0), Number(a.outstanding_amount || 0), 0)
    const bPending = Math.max(Number(b.pending_amount || 0), Number(b.outstanding_amount || 0), 0)

    if (bPending !== aPending) return bPending - aPending
    return Number(b.id || 0) - Number(a.id || 0)
  })
})

const filteredPackageOptions = computed(() => {
  return pendingOrSelectedPackageOptions.value
})

const amountInputValue = computed(() => {
  if (amountInputFocused.value) {
    return amountInputDraft.value
  }

  return formatMoneyForInput(form.amount)
})

function clearErrors() {
  Object.keys(errors).forEach(k => delete errors[k])
}

async function loadPatients() {
  patients.value = await loadPatientsShared(api, 100)
}

async function onPatientChange() {
  if (comingFromAppointment.value) return

  if (form.patient_id === '__create') {
    const newPatient = await sharedOpenCreatePatientPopup({ api, Swal, toast })

    if (newPatient && newPatient.id) {
      patients.value.unshift(newPatient)
      form.patient_id = String(newPatient.id)
    } else {
      form.patient_id = ''
      appointmentOptions.value = []
      packageOptions.value = []
      form.appointment_id = ''
      form.package_id = ''
      return
    }
  }

  if (form.patient_id && form.patient_id !== '__create') {
    form.appointment_id = ''
    form.package_id = ''
    appointmentOptions.value = []
    packageOptions.value = []
    appointmentQuery.value = ''
    appointmentDisplay.value = ''

    if (form.concept === 'appointment') {
      await loadAppointmentOptions(Number(form.patient_id))
    }
    return
  }

  appointmentOptions.value = []
  packageOptions.value = []
  form.appointment_id = ''
  form.package_id = ''
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
      if (form.concept === 'appointment') {
        await loadAppointmentOptions(Number(form.patient_id), data.appointment_id ? Number(data.appointment_id) : null)
      }
      if (form.concept === 'package') {
        await loadPackageOptions(Number(form.patient_id), data.package_id ? Number(data.package_id) : null)
      }
      syncAppointmentDisplayFromId()
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

function formatMoneyForInput(value) {
  const amount = Number(value || 0)
  if (!Number.isFinite(amount)) return '0.00'
  return amount.toFixed(2)
}

function formatMoneyForEditing(value) {
  const amount = Number(value || 0)
  if (!Number.isFinite(amount) || amount === 0) return ''

  return String(amount)
}

function normalizeMoneyDraft(rawValue) {
  const raw = String(rawValue || '').replace(',', '.')
  let normalized = ''
  let hasDot = false

  for (const character of raw) {
    if (/\d/.test(character)) {
      normalized += character
      continue
    }

    if (character === '.' && !hasDot) {
      normalized += character
      hasDot = true
    }
  }

  if (normalized.startsWith('.')) {
    normalized = `0${normalized}`
  }

  if (hasDot) {
    const [wholePart = '', decimalPart = ''] = normalized.split('.')
    return `${wholePart}.${decimalPart.slice(0, 2)}`
  }

  return normalized
}

function parseMoneyInput(rawValue) {
  const normalized = normalizeMoneyDraft(rawValue)

  if (!normalized || normalized === '.') return 0

  const amount = Number(normalized)

  if (!Number.isFinite(amount) || amount < 0) return 0
  return amount
}

function onAmountFocus() {
  amountInputFocused.value = true
  amountInputDraft.value = formatMoneyForEditing(form.amount)
}

function onAmountInput(event) {
  const normalizedDraft = normalizeMoneyDraft(event?.target?.value)
  amountInputDraft.value = normalizedDraft

  if (event?.target) {
    event.target.value = normalizedDraft
  }

  form.amount = parseMoneyInput(normalizedDraft)
}

function onAmountBlur() {
  amountInputFocused.value = false
  amountInputDraft.value = ''
  form.amount = Number(Number(form.amount || 0).toFixed(2))
}

function formatMoney(value) {
  const amount = Number(value || 0)
  if (!Number.isFinite(amount)) return '0,00 €'
  return new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount)
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

function onAppointmentDisplayInput() {
  appointmentQuery.value = appointmentDisplay.value
  const selected = appointmentOptions.value.find(a => appointmentOptionValue(a) === appointmentDisplay.value)
  form.appointment_id = selected ? String(selected.id) : ''
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

async function suggestCreateBonus() {
  if (!form.patient_id || form.patient_id === '__create') {
    toast.info('Selecciona primero un paciente')
    return
  }

  const result = await Swal.fire({
    title: 'Crear bono',
    html: `
      <div class="swal-card">
        <div class="create-row">
          <label for="swal-bonus-name">Nombre</label>
          <input id="swal-bonus-name" class="input" type="text" required value="Bono" />
        </div>
        <div class="create-row">
          <label for="swal-bonus-sessions">Nº sesiones</label>
          <input id="swal-bonus-sessions" class="input" type="number" min="1" required value="1" />
        </div>
        <div class="create-row">
          <label for="swal-bonus-price">Precio</label>
          <input id="swal-bonus-price" class="input" type="number" step="0.01" min="0" value="0" />
        </div>
        <div class="create-row">
          <label for="swal-bonus-expires">Expira (opcional)</label>
          <input id="swal-bonus-expires" class="input" type="date" />
        </div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: 'Crear',
    cancelButtonText: 'Cancelar',
    customClass: { popup: 'swal-popup-card' },
    focusConfirm: false,
    preConfirm: async () => {
      const name = document.getElementById('swal-bonus-name')?.value?.trim() || 'Bono'
      const totalSessions = Number(document.getElementById('swal-bonus-sessions')?.value || 0)
      const price = Number(document.getElementById('swal-bonus-price')?.value || 0)
      const expiresAt = document.getElementById('swal-bonus-expires')?.value || ''

      if (!Number.isFinite(totalSessions) || totalSessions <= 0) {
        Swal.showValidationMessage('El número de sesiones debe ser mayor a 0')
        return false
      }

      if (!Number.isFinite(price) || price < 0) {
        Swal.showValidationMessage('El precio debe ser 0 o mayor')
        return false
      }

      try {
        const payload = {
          name,
          total_sessions: totalSessions,
          price,
        }
        if (expiresAt) payload.expires_at = expiresAt

        const res = await api.post(`/patients/${form.patient_id}/bonuses`, payload)
        return (res.data && res.data.data) ? res.data.data : res.data
      } catch (e) {
        const message = e?.response?.data?.message || 'Error creando bono'
        Swal.showValidationMessage(message)
        return false
      }
    },
  })

  if (!result.isConfirmed || !result.value) return

  const createdBonus = result.value
  const createdPackageId = createdBonus?.id ? Number(createdBonus.id) : null
  await loadPackageOptions(Number(form.patient_id), createdPackageId || null)
  if (createdPackageId) {
    form.package_id = String(createdPackageId)
  }
  if (!isEdit.value) {
    form.amount = Number(packagePendingAmount.value || 0)
  }
  toast.success('Bono creado')
}

async function onPackageSelectChange() {
  if (form.package_id === '__create_bonus') {
    form.package_id = ''
    await suggestCreateBonus()
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

    packageOptions.value = Array.isArray(res.data?.data)
      ? res.data.data.map((option) => ({
          ...option,
          price: resolvePackageTotalPrice(option),
          bonus_price: resolvePackageTotalPrice(option),
          completed_amount: Number(option?.completed_amount || 0),
          pending_amount: Number(option?.pending_amount || 0),
          outstanding_amount: Number(option?.outstanding_amount || 0),
        }))
      : []
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

function parseRouteAmount(value) {
  const parsed = Number(String(value ?? '').replace(',', '.'))
  if (!Number.isFinite(parsed) || parsed < 0) return null
  return Number(parsed.toFixed(2))
}

async function submit() {
  submitting.value = true
  clearErrors()

  if (!form.patient_id || form.patient_id === '__create') {
    errors.general = ['Debes seleccionar un paciente válido.']
    submitting.value = false
    return
  }

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

  const hasPatientQuery = !!route.query.patient_id

  // Check if coming from appointment form with preloaded params
  if (hasPatientQuery && route.query.concept === 'appointment' && route.query.appointment_id) {
    comingFromAppointment.value = true
    form.patient_id = String(route.query.patient_id)
    form.concept = 'appointment'
    form.appointment_id = String(route.query.appointment_id)
    form.paid_at = toDateTimeLocal(new Date().toISOString())

    const pendingAmountFromQuery = parseRouteAmount(route.query.amount)
    if (pendingAmountFromQuery !== null) {
      form.amount = pendingAmountFromQuery
    }

    // Load appointment options for this patient
    await loadAppointmentOptions(Number(form.patient_id), Number(form.appointment_id))
    syncAppointmentDisplayFromId()
  } else if (!route.params.id && hasPatientQuery) {
    form.patient_id = String(route.query.patient_id)
    form.paid_at = toDateTimeLocal(new Date().toISOString())

    if (typeof route.query.concept === 'string' && ['appointment', 'package', 'credit'].includes(route.query.concept)) {
      form.concept = route.query.concept
    }

    if (form.concept === 'appointment') {
      await loadAppointmentOptions(Number(form.patient_id))
    }
    if (form.concept === 'package') {
      await loadPackageOptions(Number(form.patient_id))
    }
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
    if (!value || value === '__create') {
      form.appointment_id = ''
      form.package_id = ''
      appointmentOptions.value = []
      packageOptions.value = []
      appointmentQuery.value = ''
      appointmentDisplay.value = ''
      return
    }

    const changedPatient = String(value) !== String(oldValue || '')
    if (changedPatient && !comingFromAppointment.value) {
      form.appointment_id = ''
      form.package_id = ''
      appointmentOptions.value = []
      packageOptions.value = []
      appointmentQuery.value = ''
      appointmentDisplay.value = ''

      if (form.concept === 'appointment') {
        await loadAppointmentOptions(Number(value))
      }
    }

    if (comingFromAppointment.value) {
      await loadAppointmentOptions(Number(value), form.appointment_id ? Number(form.appointment_id) : null)
    }
  }
)

watch(
  () => form.concept,
  async (value) => {
    if (value !== 'appointment') {
      form.appointment_id = ''
      appointmentOptions.value = []
      appointmentQuery.value = ''
      appointmentDisplay.value = ''
    } else if (form.patient_id) {
      await loadAppointmentOptions(Number(form.patient_id), form.appointment_id ? Number(form.appointment_id) : null)
    }

    if (value !== 'package') {
      form.package_id = ''
      packageOptions.value = []
      return
    }

    if (form.patient_id) {
      await loadPackageOptions(Number(form.patient_id), form.package_id ? Number(form.package_id) : null)
      if (!isEdit.value) {
        form.amount = Number(packagePendingAmount.value || 0)
      }
    }
  }
)

watch(
  () => form.package_id,
  (value) => {
    if (form.concept !== 'package') return
    if (!value) {
      if (!isEdit.value) form.amount = 0
      return
    }

    if (!isEdit.value) {
      form.amount = Number(packagePendingAmount.value || 0)
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
.money-total { padding:12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; font-weight:700; background:#f8fafc; color:#0f172a }

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
.swal-card .create-row { display:flex; flex-direction:column; gap:6px }
.swal-card .create-row label { font-weight:600; text-align:left; color:#111827 }
.swal-card .input { width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7eb; box-sizing:border-box }
.swal2-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:12px }
.swal2-actions .primary, .primary { padding: 8px 16px; font-size: 14px; border-radius: 9999px; border: 2px solid #3b82f6; color: #3b82f6; background: #ffffff; font-weight: 600 }
.swal2-actions .primary:hover, .primary:hover { background:#eff6ff }
</style>
