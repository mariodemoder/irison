<template>
  <MainLayout>
    <div class="show-wrapper">
      <div class="show-card">
        <div class="show-header">
          <h1 class="title-row">
            <span>Cita</span>
            <span class="status" :class="effectiveStatus">{{ statusLabel(effectiveStatus) }}</span>
          </h1>
          <div class="header-right">
            <div class="header-actions">
              <template v-if="appointment.status !== 'canceled' && !isProfessional">
                <EditButton :to="`/appointments/${appointment.id}/edit`" />
              </template>
              <div class="back-menu-group">
                <button class="muted back-btn" @click="back">Volver</button>
                <div v-if="hasQuickActions && !isProfessional" class="quick-actions" ref="quickActionsRef">
                  <button
                    type="button"
                    class="muted quick-trigger menu-right-btn"
                    @click="toggleQuickActions"
                    :disabled="cancelling || submitting"
                    aria-label="Acciones"
                    title="Acciones"
                  >
                    <svg class="quick-trigger-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <circle cx="12" cy="5" r="1.8" fill="currentColor" />
                      <circle cx="12" cy="12" r="1.8" fill="currentColor" />
                      <circle cx="12" cy="19" r="1.8" fill="currentColor" />
                    </svg>
                  </button>
                  <div v-if="quickActionsOpen" class="quick-menu">
                    <span
                      v-if="canShowReprogramAction"
                      class="quick-item-wrap"
                      :title="!canReprogram ? reprogramTooltipMessage : ''"
                    >
                      <button
                        type="button"
                        class="quick-item"
                        @click.prevent="runReprogram"
                        :disabled="!canReprogram || submitting"
                        :aria-label="!canReprogram ? `${reprogramTooltipMessage}. Reprogramar` : 'Reprogramar'"
                      >
                        Reprogramar
                      </button>
                    </span>
                    <button
                      v-if="canShowCancelAction"
                      type="button"
                      class="quick-item danger"
                      @click.prevent="runCancel"
                      :disabled="cancelling"
                    >
                      Cancelar Cita
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <AppLoading v-if="loading" message="Cargando cita..." />

        <div v-else>
          <div class="field full">
            <label class="label">Paciente</label>
            <div class="value"><router-link :to="`/patients/${appointment.patient_id}`">{{ appointment.patient?.counter ? (`${appointment.patient.counter} · `) : '' }}{{ appointment.patient?.name || appointment.patient_name || appointment.patient?.full_name || ('Paciente #' + appointment.patient_id) }}</router-link></div>
          </div>

          <div class="field">
            <label class="label">Inicio</label>
            <div class="value">{{ formatDateShort(appointment.start_time) }} - {{ formatTime(appointment.start_time) }} hs.</div>
          </div>

          <div class="field">
            <label class="label">Fin</label>
            <div class="value">{{ formatDateShort(appointment.end_time) }} - {{ formatTime(appointment.end_time) }} hs.</div>
          </div>

          <div class="field full">
            <label class="label">Tipo de cita</label>
            <div class="value"><span class="type-badge" :style="appointmentTypeStyle(appointment)">{{ appointment.appointment_type?.description || appointment.appointmentType?.description || appointment.custom_type || '—' }}</span></div>
          </div>

          <div class="field full">
            <label class="label">Profesional</label>
            <div class="value">{{ appointment.professional?.name || clinicOwnerName }}</div>
          </div>

          <div class="field full">
            <label class="label">Notas</label>
            <div v-if="isProfessional && !isEditingNotes" class="value value-notes" @click="startEditNotes">
              {{ appointment.notes || '—' }}
              <span class="edit-hint">✎</span>
            </div>
            <div v-else-if="isProfessional && isEditingNotes" class="value">
              <textarea v-model="notesDraft" class="notes-textarea" rows="3"></textarea>
              <div class="notes-actions">
                <button class="btn btn-sm" @click="saveNotes">Guardar</button>
                <button class="btn btn-sm muted" @click="cancelEditNotes">Cancelar</button>
              </div>
            </div>
            <div v-else class="value">{{ appointment.notes ?? '—' }}</div>
          </div>

          <div class="field">
            <label class="label">Precio</label>
            <div class="value">{{ appointmentPriceLabel }}</div>
          </div>

          <div v-if="!hasBonusCoverage" class="field">
            <label class="label">Importe pendiente de pago</label>
            <div class="value">{{ appointmentPendingPaymentLabel }}</div>
          </div>

          <div v-if="!isUnpaid && !hasBonusCoverage" class="field">
            <label class="label">Forma de pago</label>
            <div class="value">{{ hasBonusCoverage ? 'Cubierto por Bono' : 'Pago simple' }}</div>
          </div>

          <div v-if="!isCanceled" class="field">
            <div class="value">
              <span class="payment-badge" :class="paymentStatusClass">{{ paymentStatusLabel }}</span>
            </div>
          </div>

          <div v-if="appointment.payment_type === 'bonus'" class="field full">
            <label class="label">Bono asociado</label>
            <div v-if="appointment.bonus" class="value">
              <div><strong>{{ appointment.bonus.name }}</strong> -- Sesiones totales: {{ appointment.bonus.total_sessions }}
              <div v-if="appointment.bonus.expires_at">Expira: {{ formatDateShort(appointment.bonus.expires_at) }}</div>
            </div></div>
            <div v-else class="value">—</div>
          </div>

        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import { useToast } from 'vue-toastification'
import { statusLabel, formatDateShort, formatTime, parseAppointmentDateTime, getContrastColor } from '../../shared/appointmentHelpers'
import { appointmentCancelShared } from '../../shared/formHelpers'
import { goBackWithStack } from '../../shared/navigationHelpers'
import { isProfessional } from '../../shared/meCache'

const route = useRoute()
const router = useRouter()
const appointment = ref({})
const loading = ref(false)
const clinicOwnerName = ref('')
const cancelling = ref(false)
const submitting = ref(false)
const canReprogram = ref(false)
const isEdit = ref(!!route.params.id)
const quickActionsOpen = ref(false)
const quickActionsRef = ref(null)
const isEditingNotes = ref(false)
const notesDraft = ref('')

async function saveNotes() {
  try {
    await api.put(`/appointments/${appointment.value.id}`, { notes: notesDraft.value })
    appointment.value.notes = notesDraft.value
    isEditingNotes.value = false
    useToast().success('Notas actualizadas')
  } catch (e) {
    useToast().error('Error al guardar las notas')
  }
}

function startEditNotes() {
  notesDraft.value = appointment.value.notes || ''
  isEditingNotes.value = true
}

function cancelEditNotes() {
  isEditingNotes.value = false
  notesDraft.value = ''
}

const isCanceled = computed(() => appointment.value && (appointment.value.status === 'canceled' || appointment.value.status === 'cancelled'))
const isFutureAppointment = computed(() => {
  try {
    const t = appointment.value.start_time
    if (!t) return false
    const dt = parseAppointmentDateTime(t)
    if (!dt) return false
    return dt.getTime() > Date.now()
  } catch (e) {
    return false
  }
})

const effectiveStatus = computed(() => {
  if (!appointment.value || !appointment.value.status) return ''
  const s = appointment.value.status
  if (s === 'scheduled' && appointment.value.end_time) {
    try {
      const end = parseAppointmentDateTime(appointment.value.end_time)
      if (end && end.getTime() < Date.now()) return 'completed'
    } catch (e) {
      // ignore parse errors
    }
  }
  return s
})

const paymentStatusLabel = computed(() => {
  const ps = appointment.value?.payment_status
  if (!ps) return 'Pago Pendiente'
  const map = {
    'pending': 'Pago Pendiente',
    'partially_paid': 'Parcialmente Pagada',
    'paid': 'Pagada',
    'covered_by_pack': 'Cubierta por bono',
  }
  return map[ps] || 'Pago Pendiente'
})

const paymentStatusClass = computed(() => {
  const ps = appointment.value?.payment_status
  if (!ps) return 'pending'
  const map = {
    'pending': 'pending',
    'partially_paid': 'partially-paid',
    'paid': 'paid',
    'covered_by_pack': 'covered',
  }
  return map[ps] || 'pending'
})

const appointmentPriceAmount = computed(() => {
  const amount = Number(appointment.value?.price || 0)
  return Number.isFinite(amount) && amount > 0 ? amount : 0
})

const appointmentPendingPaymentAmount = computed(() => {
  const sessionPrice = appointmentPriceAmount.value
  const pendingFromApi = Number(appointment.value?.pending_payment_amount)

  if (Number.isFinite(pendingFromApi) && pendingFromApi >= 0) {
    return Number(pendingFromApi.toFixed(2))
  }

  return Number(sessionPrice.toFixed(2))
})

const appointmentPriceLabel = computed(() => `${appointmentPriceAmount.value.toFixed(2)}€`)
const appointmentPendingPaymentLabel = computed(() => `${appointmentPendingPaymentAmount.value.toFixed(2)}€`)
const hasBonusCoverage = computed(() => Boolean(appointment.value?.bonus_id))

const isUnpaid = computed(() => {
  const ps = appointment.value?.payment_status
  return !ps || ps === 'pending'
})

const reprogramTooltipMessage = 'Reprogramación sólo con al menos 1 hora de antelación'

const canShowReprogramAction = computed(() => isEdit.value && isFutureAppointment.value)
const canShowCancelAction = computed(() => isEdit.value && !isCanceled.value && effectiveStatus.value !== 'completed')
const hasQuickActions = computed(() => canShowReprogramAction.value || canShowCancelAction.value)

async function loadOwnerName() {
  try {
    const res = await api.get('/me')
    clinicOwnerName.value = res?.data?.clinic_owner_name || ''
  } catch (e) {
    clinicOwnerName.value = ''
  }
}

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

// recompute permission when appointment changes
function computeReprogramAllowance() {
  if (!appointment.value || !appointment.value.start_time) {
    canReprogram.value = false
    return
  }
  const startDate = parseAppointmentDateTime(appointment.value.start_time)
  if (!startDate) {
    canReprogram.value = false
    return
  }
  const start = startDate.getTime()
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
  goBackWithStack(router, '/appointments/day')
}

async function cancel() {
  cancelling.value = true
  try {
    const toast = useToast()
    await api.post(`/appointments/${route.params.id}/cancel`)
    toast.success('Cita cancelada', {
      toastClassName: 'toast-delete',
      progressClassName: 'toast-delete-progress',
    })
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

function toggleQuickActions() {
  quickActionsOpen.value = !quickActionsOpen.value
}

function closeQuickActions() {
  quickActionsOpen.value = false
}

function handleClickOutsideQuickActions(event) {
  if (!quickActionsOpen.value) return
  if (!quickActionsRef.value) return
  if (!quickActionsRef.value.contains(event.target)) {
    closeQuickActions()
  }
}

function runReprogram() {
  closeQuickActions()
  goReprogram()
}

function runCancel() {
  closeQuickActions()
  appointmentCancel()
}

function goReprogram() {
  router.push({ path: `/appointments/${appointment.value.id}/edit`, query: { mode: 'reprogram' } })
}

function appointmentTypeStyle(item) {
  const color = item.appointment_type?.color || item.appointmentType?.color
  return color ? { backgroundColor: color, color: getContrastColor(color) } : {}
}

onMounted(() => {
  load()
  loadOwnerName()
  document.addEventListener('click', handleClickOutsideQuickActions)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutsideQuickActions)
})
</script>

<style scoped>
.show-wrapper { display:flex; justify-content:center; padding:24px }
.show-card { width:100%; max-width:760px; background: #fff; border-radius:12px; box-shadow: 0 10px 30px rgba(2,6,23,0.06); padding:24px }
.show-header h1 { margin:0; font-size:22px }
.title-row { display:inline-flex; align-items:center; gap:10px }

/* Header layout: title left, status+actions right on one line */
.show-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px }
.header-right { display:flex; align-items:flex-start; }
.header-actions { display:flex; gap:8px; align-items:center }
.header-top-right { display:flex; align-items:center; gap:0 }
.field { margin-top:12px }
.label { font-weight:600; margin-bottom:6px }
.value { padding:10px; background:#f8fafc; border-radius:8px }
.actions { display:flex; gap:12px; margin-top:16px }
.action-row { display:flex; justify-content:space-between; align-items:center }
.left-actions { display:flex; gap:12px; align-items:center }
.quick-trigger { padding:11px 12px; display:inline-flex; align-items:center; justify-content:center }
.quick-trigger-icon { width:18px; height:18px; color:#4b5563 }
.quick-actions { position:relative }
.quick-menu { position:absolute; right:0; top:calc(100% + 6px); min-width:180px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 10px 24px rgba(2,6,23,0.10); padding:6px; display:flex; flex-direction:column; gap:4px; z-index:20 }
.quick-item-wrap { display:block }
.quick-item { text-align:left; padding:8px 10px; border:1px solid transparent; background:#fff; border-radius:8px; font-size:14px; color:#111827 }
.quick-item:hover { background:#f9fafb }
.quick-item.danger { color:#b91c1c }

.type-badge { padding:6px 10px; border-radius:9999px; font-weight:700 }
.status { padding:8px 14px; border-radius:9999px; font-weight:700; text-transform:capitalize; display:inline-flex; align-items:center }
.status.canceled { background:#fff4f4; color:#da7a7a }
.status.scheduled { background:#eef2ff; color:#1e3a8a }
.status.completed { background:#dcfce7; color:#166534 }
 .status.rescheduled { background:#fff7ed; color:#b45309 }

/* Payment status badges */
.payment-badge { padding:6px 12px; border-radius:9999px; font-weight:600; font-size:12px; display:inline-block }
.payment-badge.pending { background:#fee2e2; color:#b91c1c }
.payment-badge.partially-paid { background:#fef3c7; color:#b45309 }
.payment-badge.paid { background:#dcfce7; color:#166534 }
.payment-badge.covered { background:#dbeafe; color:#1e40af }

/* Alinear texto en botones */
.actions button { display:inline-flex; align-items:center; gap:8px }
.value-notes { cursor:pointer; display:flex; justify-content:space-between; align-items:center }
.value-notes:hover { background:#f1f5f9 }
.edit-hint { color:#94a3b8; font-size:13px }
.notes-textarea { width:100%; padding:8px; border:1px solid #e2e8f0; border-radius:8px; font-family:inherit; font-size:14px; resize:vertical }
.notes-textarea:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,0.1) }
.notes-actions { display:flex; gap:8px; margin-top:8px }

</style>
