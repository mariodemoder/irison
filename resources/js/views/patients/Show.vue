<template>
  <MainLayout>
    <div class="form-wrapper">
      <div class="form-card">
        <div class="form-header" style="display:flex;justify-content:space-between;align-items:start">
          <div>
            <h1>Paciente e Historial</h1>
          </div>
          <p></p>
          <div style="display:flex;gap:8px">
            <button class="primary" @click.prevent="goEdit" style="padding:6px 12px;font-size:13px">Editar</button>
            <button class="primary" @click.prevent="viewHistory" style="padding:6px 12px;font-size:13px">Ver Historia Clínica</button>
            <button class="action-btn" @click.prevent="confirmDelete" style="padding:6px 12px;font-size:13px;display:flex;gap:6px;align-items:center">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:14px;height:14px">
                <path d="M3 6h18"></path>
                <path d="M8 6V4h8v2"></path>
                <path d="M19 6l-1 14H6L5 6"></path>
                <path d="M10 11v6M14 11v6"></path>
              </svg>
              <span>Eliminar</span>
            </button>
            <button class="muted" @click.prevent="goBack" style="padding:6px 12px;font-size:13px">Volver</button>
          </div>
        </div>
        <br>
        <div class="grid-display">
          <div class="card">
            <div class="card-row"><strong>Nombre: </strong>{{ patient?.name ?? '—' }}</div>
            <div v-if="activeBonusCount > 0" class="mini-badge">{{ activeBonusCount }} bono(s) activo(s)</div>
          
          </div>

          <div class="card">
            <div class="card-row"><strong>NIF: </strong>{{ patient?.nif ?? '—' }}</div>
          </div>

          <div class="card">
            <div class="card-row"><strong>Teléfono: </strong>{{ patient?.phone ?? '—' }}</div>
          </div>

          <div class="card">
            <div class="card-row"><strong>Email: </strong>{{ patient?.email ?? '—' }}</div>
          </div>

          <div class="card full">
            <div class="card-row"><strong>Notas</strong></div>
            <div class="card-row">{{ patient?.notes ?? '—' }}</div>
          </div>
        </div>

        <div class="history-grid">
          <div class="history-card">
            <div class="history-title" style="display:flex;justify-content:space-between;align-items:center">
              <div>Citas</div>
              <div style="display:flex;gap:8px;align-items:center">
                <button
                  class="toggle-canceled-btn"
                  @click.prevent="toggleCanceledVisibility"
                  :title="showCanceledAppointments ? 'Ver solo citas pendientes' : 'Ver todas las citas'"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:14px;height:14px">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path d="M21 21l-4.3-4.3"></path>
                  </svg>
                </button>
                <button class="primary" @click.prevent="createAppointment" style="padding:6px 10px;font-size:13px">Crear</button>
              </div>
            </div>
            <div v-if="filteredAppointments && filteredAppointments.length"> 
              <ul>
                <li v-for="a in filteredAppointments" :key="a.id" role="button" tabindex="0" @click.prevent="goToAppointment(a.id)" @keydown.enter.prevent="goToAppointment(a.id)" style="cursor:pointer">
                  <div style="display:flex; gap:10px; align-items:center">
                    <div>
                      <strong>{{ formatDateShort(a.start_time) }} {{ formatTime(a.start_time) }}</strong>
                    </div>
                    <div>
                      <span class="status" :class="a.status">{{ statusLabel(a.status) }}</span>
                    </div>
                  </div>
                </li>
              </ul>
            </div>
            <div v-else class="empty-card">Sin citas</div>
          </div>

          <div class="history-card">
            <PatientBonuses v-if="patient && patient.id" :patientId="patient.id" @active-bonus-count="v => activeBonusCount = v" />
          </div>

          <div class="history-card">
            <div class="history-title" style="display:flex;justify-content:space-between;align-items:center">
              <div>Pagos</div>
              <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end">
                <button
                  class="toggle-canceled-btn"
                  @click.prevent="toggleCompletedPaymentsVisibility"
                  :title="showCompletedPayments ? 'Ver solo pagos a favor (anticipos)' : 'Ver todos los pagos'"
                >
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:14px;height:14px">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path d="M21 21l-4.3-4.3"></path>
                  </svg>
                </button>
                <button class="primary" @click.prevent="createPayment" style="padding:6px 10px;font-size:13px">Crear</button>
              </div>
            </div>
            <div v-if="sortedPayments && sortedPayments.length">
              <div class="payments-total">Total: {{ formatPaymentAmount(totalPaymentsAmount) }}</div>
              <ul>
                <li v-for="pay in sortedPayments" :key="pay.id">
                  ({{ formatPaymentDate(pay.paid_at || pay.created_at) }}) - {{ formatPaymentAmount(pay.amount) }} {{ paymentMethodLabel(pay.method) }} {{ paymentConceptLabel(pay.concept) }} 
                </li>
              </ul>
            </div>
            <div v-else class="empty-card">Sin pagos</div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from '../../layouts/MainLayout.vue'
import { ref, onMounted, watch, computed } from 'vue'
import PatientBonuses from '../../components/PatientBonuses.vue'
import { formatTime, formatDateShort, statusLabel } from '../../shared/appointmentHelpers'
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'
import { useToast } from 'vue-toastification'
import Swal from 'sweetalert2'

const route = useRoute()
const router = useRouter()
const patient = ref(null)
const activeBonusCount = ref(0)
const appointments = ref([])
const packs = ref([])
const payments = ref([])
const loading = ref(false)
const showCanceledAppointments = ref(false)
const showCompletedPayments = ref(false)

const filteredAppointments = computed(() => {
  if (showCanceledAppointments.value) return appointments.value
  return appointments.value.filter(a => a.status === 'scheduled' || a.status === 'rescheduled')
})

const filteredPayments = computed(() => {
  if (showCompletedPayments.value) return payments.value
  return payments.value.filter(p => p.concept === 'credit')
})

const sortedPayments = computed(() => {
  if (!filteredPayments.value || filteredPayments.value.length === 0) return []

  const toMs = (pay) => {
    const raw = pay?.paid_at || pay?.created_at
    if (!raw) return 0
    const ts = new Date(raw).getTime()
    return Number.isNaN(ts) ? 0 : ts
  }

  return [...filteredPayments.value].sort((a, b) => toMs(b) - toMs(a))
})

const totalPaymentsAmount = computed(() => {
  return sortedPayments.value.reduce((sum, pay) => sum + Number(pay?.amount || 0), 0)
})

async function loadPatient() {
  loading.value = true
  try {
    const id = route.params.id
    const res = await api.get(`/patients/${id}`)
    patient.value = res.data
    appointments.value = res.data.appointments || []
    packs.value = res.data.packs || []
    payments.value = res.data.payments || []
  } catch (e) {
    console.error('Error cargando paciente', e)
    // si 404, volver al listado
    if (e.response && e.response.status === 404) router.push('/patients')
  } finally {
    loading.value = false
  }
}

onMounted(() => loadPatient())

watch(() => route.params.id, (id) => {
  if (id) loadPatient()
})

function goEdit() {
  if (patient.value && patient.value.id) {
    router.push({ path: `/patients/${patient.value.id}/edit`, query: { from: 'show' } })
  }
}

function viewHistory() {
  if (patient.value && patient.value.id) {
    router.push({ path: `/patients/${patient.value.id}/history` })
  }
}

function goBack() {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push('/patients')
  }
}

function goToAppointment(id) {
  if (!id) return
  router.push(`/appointments/${id}`)
}

function createAppointment() {
  if (!patient.value || !patient.value.id) return
  router.push({ path: '/appointments/create', query: { patient_id: patient.value.id } })
}

function toggleCanceledVisibility() {
  showCanceledAppointments.value = !showCanceledAppointments.value
}
function toggleCompletedPaymentsVisibility() {
  showCompletedPayments.value = !showCompletedPayments.value
}

function createPayment() {
  if (!patient.value || !patient.value.id) return
  router.push({ path: '/payments/create', query: { patient_id: patient.value.id } })
}

function paymentMethodLabel(method) {
  const map = {
    cash: 'efectivo',
    card: 'tarjeta',
    transfer: 'transferencia',
  }

  return map[method] || 'método no definido'
}

function paymentConceptLabel(concept) {
  const map = {
    credit: 'Anticipo',
    package: 'Bono',
    appointment: 'Simple',
  }

  return map[concept] || 'Motivo no definido'
}

function formatPaymentAmount(amount) {
  const n = Number(amount || 0)
  if (!Number.isFinite(n)) return '0€'
  if (Number.isInteger(n)) return `${n}€`
  return `${n.toFixed(2)}€`
}

function formatPaymentDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yy = String(d.getFullYear()).slice(-2)
  return `${dd}/${mm}/${yy}`
}

async function confirmDelete() {
  if (!patient.value) return
  const res = await Swal.fire({
    title: `Eliminar paciente`,
    text: `¿Eliminar al paciente "${patient.value.name}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  })

  if (!res.isConfirmed) return

  try {
    await api.delete(`/patients/${patient.value.id}`)
    const toast = useToast()
    toast.success('Paciente eliminado')
    // ir al listado
    router.push('/patients')
  } catch (e) {
    const msg = e.response?.data?.message || 'Error eliminando paciente'
    const toast = useToast()
    toast.error(msg)
  }
}
</script>

<style scoped>
/* Reusar estilos del formulario y mejorar visual */
.form-wrapper { display:flex; justify-content:center; padding:24px }
.form-card { width:100%; max-width:960px; background: #fff; border-radius:12px; box-shadow: 0 10px 30px rgba(2,6,23,0.06); padding:24px }
.form-header h1 { margin:0; font-size:22px }
.form-sub { color:#6b7280; font-size:13px; margin-top:6px }

.grid-display { display:grid; grid-template-columns: repeat(2,1fr); gap:12px }
.card { background:#fafafa; padding:5px; border-radius:10px; border:1px solid #eef2ff22 }
.card.full { grid-column:1 / -1 }
.card-row { margin-bottom:6px }

.history-grid { margin-top:18px; display:grid; grid-template-columns:repeat(3,1fr); gap:12px }
.history-card { background:#fff; padding:14px; border-radius:10px; border:1px solid #eef2ff; box-shadow: 0 6px 18px rgba(2,6,23,0.04) }
.history-title { font-weight:700; margin-bottom:8px }
.empty-card { padding:18px; border-radius:8px; border:2px dashed #e6e6e6; color:#6b7280; text-align:center; min-height:72px; display:flex; align-items:center; justify-content:center }

.history-card ul { list-style:none; padding:0; margin:0 }
.history-card li { padding:6px 0; border-bottom:1px dashed #f1f5f9; font-size:12px; color:#334155 }
.history-card li:last-child { border-bottom: none }
.payments-total { font-size:12px; font-weight:700; color:#0f172a; margin-bottom:6px }

.history-card .status { padding:4px 8px; border-radius:9999px; font-weight:700; text-transform:capitalize; font-size:11px }
.status.canceled { background:#fff4f4; color:#da7a7a }
.status.scheduled { background:#eef2ff; color:#1e3a8a }
.status.rescheduled { background:#fffbeb; color:#b45309 }
.status.reprogrammed { background:#fffbeb; color:#b45309 }
.status.completed { background:#dcfce7; color:#166534 }

.primary { padding:8px 14px; border-radius:9999px; border:2px solid #3b82f6; color:#3b82f6; background:#fff; font-weight:600 }
.primary:hover { background:#eff6ff }

.toggle-canceled-btn {
  width:32px;
  height:32px;
  border-radius:9999px;
  border:1px solid #fca5a5;
  color:#f87171;
  background:transparent;
  font-size:13px;
  display:flex;
  align-items:center;
  justify-content:center;
  opacity:0.9;
}

.toggle-canceled-btn:hover {
  background:transparent;
  border-color:#f87171;
  color:#ef4444;
}

@media (max-width: 900px) {
  .history-grid { grid-template-columns: 1fr }
  .grid-display { grid-template-columns: 1fr }
}

.mini-badge { display:inline-block; margin-top:6px; padding:6px 10px; background:#ecfdf5; color:#065f46; border-radius:9999px; font-size:13px; font-weight:700 }
</style>
