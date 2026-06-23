<template>
  <MainLayout>
    <div class="form-wrapper">
      <div class="form-card">
        <div class="form-header">
          <div>
            <h1>Nueva Factura</h1>
            <p class="form-sub">Añade los conceptos que quieras facturar (sesiones, bonos, productos o manual)</p>
          </div>
          <button type="button" class="muted back-btn" @click.prevent="goBack">Volver</button>
        </div>

        <!-- PACIENTE -->
        <section class="section">
          <h2 class="section-title">Paciente</h2>
          <PatientSelect
            :model-value="selectedPatient?.id ? String(selectedPatient.id) : ''"
            :patients="patients"
            :current-patient="selectedPatient"
            class="input"
            placeholder="Selecciona paciente"
            @update:modelValue="handlePatientSelect"
          />
        </section>

        <!-- FECHA Y NOTAS -->
        <section class="section grid-form">
          <div class="field">
            <label class="label">Fecha</label>
            <input v-model="form.date" type="date" class="input" />
          </div>
          <div class="field">
            <label class="label">Notas (opcional)</label>
            <input v-model="form.notes" type="text" class="input" placeholder="Observaciones…" />
          </div>
        </section>

        <!-- ITEMS -->
        <section class="section">
          <div class="items-header">
            <h2 class="section-title" style="margin:0">Conceptos</h2>
            <div class="add-actions">
              <button type="button" class="btn-add" @click="openAddPanel('appointment')">+ Cita</button>
              <button v-if="!isAppointmentPreloaded" type="button" class="btn-add" @click="openAddPanel('bonus')">+ Bono</button>
              <button type="button" class="btn-add" @click="openAddPanel('product')">+ Producto</button>
              <button type="button" class="btn-add" @click="addManualItem()">+ Manual</button>
            </div>
          </div>

          <!-- Panel buscador de citas -->
          <div v-if="addPanel === 'appointment'" class="add-panel">
            <div class="panel-title">Seleccionar cita sin facturar</div>
            <div v-if="!selectedPatient" class="panel-notice">Primero selecciona un paciente.</div>
            <div v-else>
              <div v-if="appointmentsLoading" class="panel-loading">Cargando citas…</div>
              <div v-else-if="availableAppointments.length === 0" class="panel-notice">No hay citas pendientes de facturar.</div>
              <div v-else class="panel-list">
                <button
                  v-for="a in availableAppointments"
                  :key="a.id"
                  type="button"
                  class="panel-row"
                  @click="addAppointmentItem(a)"
                >
                  <span>{{ a.description }}</span>
                  <span class="panel-row-date">{{ formatDateShort(a.start_time) }}</span>
                  <span class="panel-row-price">{{ formatMoney(a.price) }}</span>
                </button>
              </div>
            </div>
            <button type="button" class="panel-close" @click="addPanel = null">Cerrar</button>
          </div>

          <div v-if="addPanel === 'bonus' && !isAppointmentPreloaded" class="add-panel">
            <div class="panel-title">Seleccionar bono sin facturar</div>
            <div v-if="!selectedPatient" class="panel-notice">Primero selecciona un paciente.</div>
            <div v-else>
              <div v-if="bonusesLoading" class="panel-loading">Cargando bonos…</div>
              <div v-else-if="availableBonuses.length === 0" class="panel-notice">No hay bonos pendientes de facturar.</div>
              <div v-else class="panel-list">
                <button
                  v-for="bonus in availableBonuses"
                  :key="bonus.id"
                  type="button"
                  class="panel-row"
                  @click="addBonusItem(bonus)"
                >
                  <span>{{ bonus.description }}</span>
                  <span class="panel-row-date">{{ bonus.remaining_sessions }} sesiones</span>
                  <span class="panel-row-price">{{ formatMoney(bonus.price) }}</span>
                </button>
              </div>
            </div>
            <button type="button" class="panel-close" @click="addPanel = null">Cerrar</button>
          </div>

          <!-- Panel buscador de productos -->
          <div v-if="addPanel === 'product'" class="add-panel">
            <div class="panel-title">Buscar producto</div>
            <input
              v-model="productQuery"
              class="input"
              placeholder="Nombre o referencia…"
              @input="onProductInput"
              autocomplete="off"
            />
            <div v-if="productResults.length" class="panel-list" style="margin-top:8px">
              <button
                v-for="p in productResults"
                :key="p.id"
                type="button"
                class="panel-row"
                @click="addProductItem(p)"
              >
                <span>{{ p.name }}</span>
                <span v-if="p.reference" class="panel-row-date">{{ p.reference }}</span>
                <span class="panel-row-price">{{ formatMoney(p.sale_price) }} <span v-if="p.sale_tax > 0">(+{{ p.sale_tax }}% IVA)</span></span>
              </button>
            </div>
            <button type="button" class="panel-close" @click="addPanel = null">Cerrar</button>
          </div>

          <!-- Tabla de items -->
          <div v-if="items.length" class="items-table">
            <div class="items-table-head">
              <div>Descripción</div>
              <div>Cant.</div>
              <div>Precio</div>
              <div>IVA %</div>
              <div>Total</div>
              <div></div>
            </div>
            <div v-for="(item, idx) in items" :key="idx" class="items-row">
              <div>
                <input v-model="item.description" class="input input-sm" placeholder="Descripción" @input="recalcItem(idx)" />
                <span v-if="item.type !== 'manual'" class="item-type-tag">{{ itemTypeLabel(item.type) }}</span>
              </div>
              <div>
                <input v-model.number="item.quantity" type="number" min="0.0001" step="0.01" class="input input-sm" @input="recalcItem(idx)" />
              </div>
              <div>
                <input v-model.number="item.unit_price" type="number" min="0" step="0.01" class="input input-sm" @input="recalcItem(idx)" />
              </div>
              <div>
                <input v-model.number="item.tax_rate" type="number" min="0" max="100" step="0.01" class="input input-sm" @input="recalcItem(idx)" />
              </div>
              <div class="item-total">{{ formatMoney(item.total) }}</div>
              <div>
                <BtnTrash @click="removeItem(idx)" title="Eliminar" />
              </div>
            </div>
          </div>

          <div v-else class="items-empty">Todavía no has añadido ningún concepto.</div>
        </section>

        <!-- TOTALES -->
        <section v-if="items.length" class="section totals-section">
          <div class="totals-grid">
            <div class="totals-row">
              <span>Base imponible</span>
              <span>{{ formatMoney(totals.base) }}</span>
            </div>
            <div class="totals-row">
              <span>IVA total</span>
              <span>{{ formatMoney(totals.taxAmount) }}</span>
            </div>
            <div class="totals-row totals-total">
              <span>Total</span>
              <span>{{ formatMoney(totals.total) }}</span>
            </div>
          </div>
        </section>

        <!-- ACCIONES -->
        <section class="section actions">
          <button type="button" class="primary" :disabled="saving" @click="submit">
            {{ saving ? 'Guardando…' : 'Emitir Factura' }}
          </button>
          <button type="button" class="muted" @click="goBack">Volver</button>
        </section>

        <div v-if="serverErrors.length" class="error-box">
          <div v-for="(e, i) in serverErrors" :key="i">{{ e }}</div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import PatientSelect from '../../components/PatientSelect.vue'
import { useToast } from 'vue-toastification'
import Swal from 'sweetalert2'
import { loadPatients as loadPatientsShared, openCreatePatientPopup as sharedOpenCreatePatientPopup } from '../../shared/formHelpers'
import BtnTrash from '../../components/BtnTrash.vue'

const toast = useToast()
const route = useRoute()
const router = useRouter()

// ─── Estado ──────────────────────────────────────────────────────────────────
const saving = ref(false)
const serverErrors = ref([])

const form = ref({
  date: new Date().toISOString().slice(0, 10),
  notes: '',
})

// Paciente
const patients = ref([])
const selectedPatient = ref(null)

// Productos (buscador)
const productQuery = ref('')
const productResults = ref([])
let productTimer = null

// Citas disponibles
const availableAppointments = ref([])
const appointmentsLoading = ref(false)

// Bonos disponibles
const availableBonuses = ref([])
const bonusesLoading = ref(false)

// Panel activo: 'appointment' | 'product' | null
const addPanel = ref(null)

// Líneas de factura
const items = ref([])

const preloadSource = computed(() => String(route.query.from || '').trim())
const isAppointmentPreloaded = computed(() => preloadSource.value === 'appointment')

// ─── Totales ─────────────────────────────────────────────────────────────────
const totals = computed(() => {
  let base = 0
  let taxAmount = 0
  for (const item of items.value) {
    const lineBase = (item.quantity || 0) * (item.unit_price || 0)
    base += lineBase
    taxAmount += lineBase * ((item.tax_rate || 0) / 100)
  }
  return {
    base: round2(base),
    taxAmount: round2(taxAmount),
    total: round2(base + taxAmount),
  }
})

function round2(n) {
  return Math.round(n * 100) / 100
}

// ─── Paciente ─────────────────────────────────────────────────────────────────
async function loadPatients() {
  patients.value = await loadPatientsShared(api, 200)
}

async function handlePatientSelect(value) {
  if (value === '__create') {
    const newPatient = await sharedOpenCreatePatientPopup({ api, Swal, toast })
    if (newPatient?.id) {
      patients.value.unshift(newPatient)
      selectedPatient.value = newPatient
    }
    return
  }

  const patient = patients.value.find(item => String(item.id) === String(value)) || null
  selectedPatient.value = patient
  availableAppointments.value = []
  availableBonuses.value = []
}

function clearPatient() {
  selectedPatient.value = null
  availableAppointments.value = []
  availableBonuses.value = []
}

// ─── Citas ───────────────────────────────────────────────────────────────────
async function openAddPanel(type) {
  if (type === 'bonus' && isAppointmentPreloaded.value) return
  addPanel.value = type
  if (type === 'appointment' && selectedPatient.value && availableAppointments.value.length === 0) {
    await loadAppointments()
  }
  if (type === 'bonus' && selectedPatient.value && availableBonuses.value.length === 0) {
    await loadBonuses()
  }
}

async function loadAppointments() {
  if (!selectedPatient.value) return
  appointmentsLoading.value = true
  try {
    const res = await api.get('/documents/search/appointments', { params: { patient_id: selectedPatient.value.id } })
    availableAppointments.value = res.data?.data ?? []
  } catch {
    availableAppointments.value = []
  } finally {
    appointmentsLoading.value = false
  }
}

function addAppointmentItem(a) {
  items.value.push({
    type: 'appointment',
    reference_id: a.id,
    description: a.description || `Cita #${a.id}`,
    quantity: 1,
    unit_price: a.price ?? 0,
    tax_rate: 0,
    buy_price: 0,
    buy_tax: 0,
    total: round2(a.price ?? 0),
  })
  // Quitar de la lista disponible
  availableAppointments.value = availableAppointments.value.filter(x => x.id !== a.id)
  addPanel.value = null
}

async function loadBonuses() {
  if (!selectedPatient.value) return
  bonusesLoading.value = true
  try {
    const res = await api.get('/documents/search/bonuses', { params: { patient_id: selectedPatient.value.id } })
    availableBonuses.value = res.data?.data ?? []
  } catch {
    availableBonuses.value = []
  } finally {
    bonusesLoading.value = false
  }
}

function addBonusItem(bonus) {
  items.value.push({
    type: 'bonus',
    reference_id: bonus.id,
    description: bonus.description || bonus.name || `Bono #${bonus.id}`,
    quantity: 1,
    unit_price: bonus.price ?? 0,
    tax_rate: 0,
    buy_price: 0,
    buy_tax: 0,
    total: round2(bonus.price ?? 0),
  })
  availableBonuses.value = availableBonuses.value.filter(x => x.id !== bonus.id)
  addPanel.value = null
}

// ─── Productos ───────────────────────────────────────────────────────────────
function onProductInput() {
  clearTimeout(productTimer)
  productTimer = setTimeout(async () => {
    try {
      const q = productQuery.value.trim()
      const res = await api.get('/documents/search/products', { params: { q } })
      productResults.value = res.data?.data ?? []
    } catch { productResults.value = [] }
  }, 200)
}

function addProductItem(p) {
  items.value.push({
    type: 'product',
    reference_id: p.id,
    description: p.name,
    quantity: 1,
    unit_price: p.sale_price ?? 0,
    tax_rate: p.sale_tax ?? 0,
    buy_price: p.purchase_price ?? 0,
    buy_tax: p.purchase_tax ?? 0,
    total: round2((p.sale_price ?? 0) * (1 + (p.sale_tax ?? 0) / 100)),
  })
  productQuery.value = ''
  productResults.value = []
  addPanel.value = null
}

// ─── Manual ──────────────────────────────────────────────────────────────────
function addManualItem() {
  items.value.push({
    type: 'manual',
    reference_id: null,
    description: '',
    quantity: 1,
    unit_price: 0,
    tax_rate: 0,
    buy_price: 0,
    buy_tax: 0,
    total: 0,
  })
  addPanel.value = null
}

// ─── Recalcular ──────────────────────────────────────────────────────────────
function recalcItem(idx) {
  const item = items.value[idx]
  if (!item) return
  item.total = round2((item.quantity || 0) * (item.unit_price || 0) * (1 + (item.tax_rate || 0) / 100))
}

function removeItem(idx) {
  items.value.splice(idx, 1)
}

// ─── Submit ──────────────────────────────────────────────────────────────────
async function submit() {
  serverErrors.value = []

  if (!selectedPatient.value) {
    toast.error('Selecciona un paciente antes de emitir la factura.')
    return
  }
  if (items.value.length === 0) {
    toast.error('Añade al menos un concepto a la factura.')
    return
  }

  saving.value = true
  try {
    const payload = {
      patient_id: selectedPatient.value.id,
      date: form.value.date,
      notes: form.value.notes || null,
      items: items.value.map(item => ({
        type: item.type,
        reference_id: item.reference_id ?? null,
        description: item.description,
        quantity: item.quantity,
        unit_price: item.unit_price,
        tax_rate: item.tax_rate,
        buy_price: item.buy_price ?? 0,
        buy_tax: item.buy_tax ?? 0,
      })),
    }
    const res = await api.post('/documents/varios', payload)
    const docId = res.data?.data?.id
    toast.success('Factura emitida correctamente.')
    router.push(docId ? `/invoices/${docId}` : '/invoices')
  } catch (e) {
    if (e?.response?.status === 422) {
      const errs = e.response.data?.errors ?? {}
      serverErrors.value = Object.values(errs).flat()
    } else {
      toast.error(e?.response?.data?.message || 'Error al emitir la factura.')
    }
  } finally {
    saving.value = false
  }
}

function goBack() {
  router.push('/invoices')
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function formatMoney(val) {
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(Number(val || 0))
}

function formatDateShort(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function itemTypeLabel(type) {
  if (type === 'appointment') return 'Cita'
  if (type === 'bonus') return 'Bono'
  if (type === 'product') return 'Producto'
  return ''
}

function preloadFromAppointmentQuery() {
  if (!isAppointmentPreloaded.value) return

  const patientId = Number(route.query.patient_id || 0)
  const appointmentId = Number(route.query.appointment_id || 0)
  const patientName = String(route.query.patient_name || '').trim()
  const patientNif = String(route.query.patient_nif || '').trim()
  const itemDescription = String(route.query.item_description || '').trim()
  const itemAmount = Number(route.query.item_amount || 0)

  if (patientId > 0) {
    selectedPatient.value = {
      id: patientId,
      name: patientName || `Paciente #${patientId}`,
      nif: patientNif || null,
    }
    availableAppointments.value = []
  }

  if (appointmentId > 0 && !items.value.some(item => item.type === 'appointment' && Number(item.reference_id || 0) === appointmentId)) {
    items.value.push({
      type: 'appointment',
      reference_id: appointmentId,
      description: itemDescription || `Cita #${appointmentId}`,
      quantity: 1,
      unit_price: Number.isFinite(itemAmount) ? itemAmount : 0,
      tax_rate: 0,
      buy_price: 0,
      buy_tax: 0,
      total: round2(Number.isFinite(itemAmount) ? itemAmount : 0),
    })
  }
}

onMounted(async () => {
  await loadPatients()
  preloadFromAppointmentQuery()
})
</script>

<style scoped>
.form-wrapper { display:flex; justify-content:center; padding:24px }
.form-card { width:100%; max-width:860px; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(2,6,23,0.06); padding:28px }
.form-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:20px }
.form-header h1 { margin:0; font-size:22px }
.form-sub { margin:4px 0 0; color:#6b7280; font-size:14px }

.section { margin-bottom:24px }
.section-title { font-size:15px; font-weight:700; margin:0 0 12px; color:#111827 }

/* Grid */
.grid-form { display:grid; grid-template-columns:repeat(2,1fr); gap:12px }
.field { display:flex; flex-direction:column }
.label { font-weight:600; margin-bottom:6px; font-size:13px }
.input { padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; width:100%; box-sizing:border-box }
.input:focus { outline:none; border-color:#3b82f6 }
.input-sm { padding:6px 8px; font-size:13px }

/* Items */
.items-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px }
.add-actions { display:flex; gap:8px }
.btn-add { padding:6px 14px; font-size:13px; font-weight:600; border:1.5px solid #3b82f6; border-radius:9999px; background:#fff; color:#3b82f6; cursor:pointer }
.btn-add:hover { background:#eff6ff }

/* Panel buscador */
.add-panel { background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px; padding:16px; margin-bottom:16px }
.panel-title { font-weight:700; font-size:14px; margin-bottom:10px }
.panel-notice { font-size:13px; color:#6b7280; margin-bottom:10px }
.panel-loading { font-size:13px; color:#6b7280 }
.panel-list { display:flex; flex-direction:column; gap:4px; max-height:200px; overflow-y:auto }
.panel-row { display:flex; align-items:center; gap:12px; padding:8px 12px; border:none; background:#fff; border-radius:8px; cursor:pointer; text-align:left; border:1px solid transparent }
.panel-row:hover { border-color:#3b82f6; background:#eff6ff }
.panel-row-date { font-size:12px; color:#6b7280; margin-left:auto }
.panel-row-price { font-size:13px; font-weight:700; color:#111827; min-width:70px; text-align:right }
.panel-close { margin-top:10px; font-size:12px; color:#6b7280; background:none; border:none; cursor:pointer; padding:0 }
.panel-close:hover { color:#111 }

/* Tabla de items */
.items-table { border:1px solid #e5e7eb; border-radius:10px; overflow:hidden }
.items-table-head { display:grid; grid-template-columns:2fr 1fr 1fr 1fr 1fr 32px; gap:8px; padding:8px 12px; background:#f3f4f6; font-size:12px; font-weight:700; color:#6b7280 }
.items-row { display:grid; grid-template-columns:2fr 1fr 1fr 1fr 1fr 32px; gap:8px; padding:8px 12px; border-top:1px solid #f3f4f6; align-items:center }
.item-type-tag { display:inline-block; font-size:11px; color:#2563eb; background:#dbeafe; border-radius:4px; padding:1px 6px; margin-top:3px }
.item-total { font-weight:700; font-size:14px }
.btn-remove { background:none; border:none; cursor:pointer; color:#9ca3af; font-size:14px; padding:2px 6px; border-radius:4px }
.btn-remove:hover { background:#fee2e2; color:#b91c1c }
.items-empty { padding:20px; text-align:center; color:#9ca3af; font-size:14px; background:#f9fafb; border-radius:10px }

/* Totales */
.totals-section { display:flex; justify-content:flex-end }
.totals-grid { min-width:260px; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden }
.totals-row { display:flex; justify-content:space-between; padding:8px 16px; font-size:14px; border-top:1px solid #f3f4f6 }
.totals-row:first-child { border-top:none }
.totals-total { font-weight:700; font-size:16px; background:#f9fafb }

/* Acciones */
.actions { display:flex; gap:12px; align-items:center; padding-top:8px }
.primary { padding:10px 24px; font-size:14px; border-radius:9999px; border:2px solid #3b82f6; color:#3b82f6; background:#fff; font-weight:700; cursor:pointer }
.primary:hover:not(:disabled) { background:#eff6ff }
.primary:disabled { opacity:.5; cursor:not-allowed }
.muted { padding:10px 16px; font-size:14px; border-radius:9999px; border:1px solid #e5e7eb; background:#fff; color:#6b7280; cursor:pointer }
.muted:hover { background:#f9fafb }

/* Errores */
.error-box { margin-top:16px; background:#fee2e2; border-radius:8px; padding:12px 16px; font-size:13px; color:#b91c1c }

@media (max-width:768px) {
  .grid-form { grid-template-columns:1fr }
  .items-table-head,
  .items-row { grid-template-columns:2fr 0.6fr 0.8fr 0.6fr 0.8fr 28px }
  .totals-section { justify-content:stretch }
  .totals-grid { width:100%; min-width:0 }
}
</style>
