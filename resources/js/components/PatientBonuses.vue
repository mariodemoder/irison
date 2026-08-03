<template>
  <div class="patient-bonuses">
    <div style="display:flex; justify-content:space-between; align-items:center">
      <h3 style="margin:0">Bonos</h3>
      <div style="display:flex;gap:8px;align-items:center">
        <button
          class="toggle-canceled-btn"
          @click="toggleInactiveVisibility"
          :title="showInactiveBonuses ? 'Ocultar pagados y agotados' : 'Ver todos los bonos'"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:14px;height:14px">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path d="M21 21l-4.3-4.3"></path>
          </svg>
        </button>
        <button v-if="!isProfessional" @click="openAssociate" class="primary" style="padding:6px 10px;font-size:13px">Asociar Bono</button>
      </div>
    </div>

    <!-- Asociar Bono Modal -->
    <div v-if="showForm" class="modal-backdrop" @click.self="cancelForm">
      <div class="modal-card" @click.stop>
        <div class="modal-header">
          <h3 style="margin:0">Asociar Bono</h3>
          <button type="button" class="modal-close" @click="cancelForm">&times;</button>
        </div>
        <form @submit.prevent="create">
          <!-- Template search / selection -->
          <div class="create-row">
            <label>Tipo de bono</label>
            <EntitySearchSelect
              v-if="!selectedTemplate"
              :items="bonusTypeItems"
              :loading="false"
              placeholder="Buscar tipo de bono..."
              entityLabel="tipo de bono"
              :allowCustom="false"
              :allowCreate="true"
              @select="onSelectBonusType"
              @create="openCreateTypeForm"
            />
            <div v-else class="template-selected">
              <div class="template-selected-info">
                <strong>{{ selectedTemplate.description }}</strong>
                <span class="template-option-price">{{ formatPrice(selectedTemplate.price) }}</span>
              </div>
              <div v-if="selectedTemplate.lines && selectedTemplate.lines.length" class="template-option-lines">
                <span v-for="(line, idx) in selectedTemplate.lines" :key="idx" class="template-option-line">
                  {{ line.quantity }}x {{ line.appointment_type_name || 'Sesión' }} <template v-if="line.unit_price">({{ formatPrice(line.unit_price) }})</template>
                </span>
              </div>
              <button type="button" class="change-type-btn" @click="clearSelection">Cambiar tipo</button>
            </div>
          </div>

          <!-- Editable fields -->
          <div v-if="selectedTemplate" class="template-fields">
            <div class="create-row">
              <label>Nombre</label>
              <input v-model="form.name" type="text" required />
            </div>
            <div class="create-row">
              <label>Precio</label>
              <input v-model.number="form.price" type="number" step="0.01" min="0" />
            </div>
            <div class="create-row">
              <label>Expira (opcional)</label>
              <input v-model="form.expires_at" type="date" />
            </div>
          </div>

          <div class="create-actions">
            <button type="submit" class="primary" :disabled="!selectedTemplate">Asociar</button>
            <button type="button" class="muted" @click="cancelForm">Cancelar</button>
          </div>
        </form>
      </div>
    </div>

    <div class="bonus-list">
      <div
        v-for="b in visibleBonuses"
        :key="b.id"
        class="bonus-card"
        :class="[b.status, { new: b.justCreated, expanded: expandedBonuses.has(b.id) }]"
        @click="toggleExpanded(b.id)"
      >
        <div class="bonus-header">
          <div class="bonus-header-row">
            <div class="bonus-header-left">
              <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
              <div>
                <strong>{{ bonusTitle(b) }}</strong>
                <div v-if="b.bonus_type_name" class="bonus-type-name">{{ b.bonus_type_name }}</div>
              </div>
            </div>
            <div class="bonus-header-right">
              <span v-if="b.expires_at" class="bonus-expiry">Expira: {{ formatDMY(b.expires_at) }}</span>
              <div class="bonus-badges">
                <div class="bonus-badge" :class="b.status">{{ statusLabel(b.status) }}</div>
                <div class="payment-badge" :class="bonusPaymentClass(b)">{{ bonusPaymentLabel(b) }}</div>
              </div>
            </div>
          </div>
          <div class="bonus-price-row">
            <span></span>
            <span class="bonus-price-total">Total: {{ formatBonusPrice(b.price) }}</span>
          </div>
        </div>

        <div v-if="expandedBonuses.has(b.id)" class="bonus-body" @click.stop>
          <div>
            <span class="big-number">{{ b.remaining_sessions }}</span>
            <span class="muted-text">/ {{ b.total_sessions }} sesiones</span>
          </div>

          <div v-if="b.session_lines && b.session_lines.length > 0" class="session-lines">
            <div v-for="line in b.session_lines" :key="line.id" class="session-line">
              <span class="session-line-type">{{ line.appointment_type_name }}</span>
              <span class="session-line-count">{{ line.remaining_quantity }}/{{ line.quantity }}</span>
            </div>
          </div>
        </div>

        <div v-if="!isProfessional && expandedBonuses.has(b.id)" class="bonus-actions" @click.stop>
          <div class="bonus-actions-left">
            <button
              v-if="b.status === 'last'"
              class="renew-btn"
              @click="prefillRenew(b)"
            >
              Renovar
            </button>
          </div>

          <div class="bonus-actions-right">
            <button
              v-if="!isBonusPaidLocal(b)"
              type="button"
              class="action-btn"
              title="Registrar pago del bono"
              @click="goToBonusPayment(b)"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="btn-icon">
                <path d="M12 1v22"></path>
                <path d="M17 5.5a4 4 0 0 0-4-2.5H10a4 4 0 0 0 0 8h4a4 4 0 0 1 0 8h-3a4 4 0 0 1-4-2.5"></path>
              </svg>
              <span>Pagar</span>
            </button>

            <button
              type="button"
              class="action-btn"
              :title="b.invoice_id ? 'Ver factura' : 'Facturar bono'"
              :disabled="invoicingBonusId === b.id"
              @click="goInvoiceFromBonus(b)"
            >
              <span v-if="invoicingBonusId === b.id">…</span>
              <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="btn-icon">
                <path d="M7 3h8l4 4v14H7z"></path>
                <path d="M15 3v4h4"></path>
                <path d="M10 12h6M10 16h6"></path>
              </svg>
              <span>Factura</span>
            </button>

<BtnTrash
              @click="confirmDeleteBonus(b)"
              :title="b.invoice_id ? 'No se puede eliminar: bono facturado' : 'Eliminar bono'"
              :disabled="Boolean(b.invoice_id)"
            >Eliminar</BtnTrash>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch, reactive } from 'vue'
import { useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import api from '../services/api'
import { useToast } from 'vue-toastification'
import { formatDMY } from '../shared/dateHelpers'
import { isProfessional } from '../shared/meCache'
import EntitySearchSelect from './EntitySearchSelect.vue'
import BtnTrash from './BtnTrash.vue'

const props = defineProps({ patientId: { type: [String, Number], required: true } })
const emit = defineEmits(['active-bonus-count'])
const bonuses = ref([])
const showForm = ref(false)
const showInactiveBonuses = ref(false)
const form = ref({ name: 'Bono', price: 0, expires_at: '' })
const bonusTemplates = ref([])
const selectedTemplate = ref(null)
const toast = useToast()
const router = useRouter()
const invoicingBonusId = ref(null)
const expandedBonuses = reactive(new Set())

function toggleExpanded(id) {
  if (expandedBonuses.has(id)) {
    expandedBonuses.delete(id)
  } else {
    expandedBonuses.add(id)
  }
}

const bonusTypeItems = computed(() =>
  bonusTemplates.value.map(tpl => ({
    value: tpl.id,
    label: tpl.description || 'Sin nombre',
    sublabel: formatPrice(tpl.price),
    payload: tpl,
  }))
)

function onSelectBonusType(payload) {
  selectedTemplate.value = payload
  form.value.name = payload.description || 'Bono'
  form.value.price = payload.price
  form.value.expires_at = payload.expires_at || ''
}

function clearSelection() {
  selectedTemplate.value = null
  form.value = { name: 'Bono', price: 0, expires_at: '' }
}

function openAssociate() {
  clearSelection()
  showForm.value = true
}

function openCreateTypeForm() {
  cancelForm()
  router.push({ path: '/company-services', query: { tab: 'bonos', returnTo: `/patients/${props.patientId}` } })
}

function normalizeTemplate(item) {
  const rawDate = String(item?.expires_at || '').trim()
  const expiresAt = /^\d{4}-\d{2}-\d{2}$/.test(rawDate)
    ? rawDate
    : (/^\d{4}-\d{2}-\d{2}T/.test(rawDate) ? rawDate.slice(0, 10) : '')

  const rawLines = Array.isArray(item?.appointment_types) ? item.appointment_types : (Array.isArray(item?.lines) ? item.lines : [])
  const lines = rawLines.map((at) => ({
    appointment_type_id: at?.id ?? at?.appointment_type_id ?? null,
    appointment_type_name: at?.description ?? at?.appointment_type_name ?? 'Sesión',
    quantity: Number.isFinite(Number(at?.pivot?.quantity ?? at?.quantity)) ? Math.max(Number(at?.pivot?.quantity ?? at?.quantity), 1) : 1,
    unit_price: Number.isFinite(Number(at?.pivot?.unit_price ?? at?.unit_price)) ? Math.max(Number(at?.pivot?.unit_price ?? at?.unit_price), 0) : 0,
  }))

  const totalSessions = lines.reduce((sum, l) => sum + l.quantity, 0)

  return {
    id: Number(item?.id),
    description: String(item?.description || '').trim(),
    sessions: totalSessions || (Number.isFinite(Number(item?.sessions)) ? Math.max(Number(item.sessions), 1) : 1),
    price: Number.isFinite(Number(item?.price)) ? Math.max(Number(item.price), 0) : 0,
    expires_at: expiresAt,
    lines,
  }
}

function statusLabel(status) {
  switch (status) {
    case 'active': return 'Activo'
    case 'last': return 'Última sesión'
    case 'exhausted': return 'Agotado'
    case 'expired': return 'Expirado'
    default: return ''
  }
}

function normalizeBonus(b) {
  return {
    id: b.id,
    counter: b.counter ?? null,
    name: b.name ?? null,
    bonus_type_id: b.bonus_type_id != null ? Number(b.bonus_type_id) : null,
    bonus_type_name: b.bonus_type_name ?? null,
    invoice_id: b.invoice_id != null ? Number(b.invoice_id) : null,
    total_sessions: b.total_sessions != null ? Number(b.total_sessions) : 0,
    remaining_sessions: b.remaining_sessions != null ? Number(b.remaining_sessions) : 0,
    price: b.price != null ? Number(b.price) : 0,
    expires_at: b.expires_at ?? null,
    status: b.status ?? (b.remaining_sessions <= 0 ? 'exhausted' : 'active'),
    is_paid: Boolean(b.is_paid),
    justCreated: b.justCreated ?? false,
    session_lines: Array.isArray(b.session_lines) ? b.session_lines.map(line => ({
      ...line,
      appointment_type_name: line.appointment_type_name ?? line.appointment_type?.description ?? '—',
    })) : [],
  }
}

function formatBonusPrice(value) {
  const amount = Number(value)
  if (!Number.isFinite(amount) || amount < 0) return '0.00€'
  return `${amount.toFixed(2)}€`
}

function formatPrice(value) {
  return formatBonusPrice(value)
}

function bonusTitle(bonus) {
  const counter = (bonus?.counter ?? '').toString().trim()
  const name = (bonus?.name ?? '').toString().trim()

  if (counter && name) return `${counter} ${name}`
  if (counter) return counter
  if (name) return name

  return 'Bono'
}

function isExpiredLocal(bonus) {
  if (!bonus?.expires_at) return false
  const expiresDate = new Date(bonus.expires_at)
  if (Number.isNaN(expiresDate.getTime())) return false
  expiresDate.setHours(23, 59, 59, 999)
  return expiresDate.getTime() < Date.now()
}

function isBonusPaidLocal(bonus) {
  return Boolean(bonus?.is_paid)
}

function bonusPaymentLabel(bonus) {
  return isBonusPaidLocal(bonus) ? 'Pago' : 'Impago'
}

function bonusPaymentClass(bonus) {
  return isBonusPaidLocal(bonus) ? 'paid' : 'unpaid'
}

const visibleBonuses = computed(() => {
  const filtered = showInactiveBonuses.value
    ? bonuses.value
    : bonuses.value.filter(b => !(b.is_paid && b.status === 'exhausted'))

  const order = { active: 0, last: 1, exhausted: 2, expired: 2 }
  return [...filtered].sort((a, b) => {
    const oa = order[a.status] ?? 99
    const ob = order[b.status] ?? 99
    if (oa !== ob) return oa - ob
    return (b.id || 0) - (a.id || 0)
  })
})

function toggleInactiveVisibility() {
  showInactiveBonuses.value = !showInactiveBonuses.value
}

function emitActiveCount() {
  const count = bonuses.value.filter(b => b.status === 'active' || b.status === 'last').length
  emit('active-bonus-count', count)
}

watch(bonuses, () => emitActiveCount(), { immediate: true })

async function load() {
  const [bonusesResult, typesResult] = await Promise.allSettled([
    api.get(`/patients/${props.patientId}/bonuses`),
    api.get('/bonus-types'),
  ])

  if (bonusesResult.status === 'fulfilled') {
    const res = bonusesResult.value
    bonuses.value = Array.isArray(res?.data?.data) ? res.data.data.map(normalizeBonus) : []
  }

  if (typesResult.status === 'fulfilled') {
    const res = typesResult.value
    const incomingTemplates = Array.isArray(res?.data?.data) ? res.data.data : []
    bonusTemplates.value = incomingTemplates
      .map(normalizeTemplate)
      .filter((tpl) => Number.isFinite(tpl.id) && tpl.id > 0)
  }
}

function cancelForm() {
  showForm.value = false
  form.value = { name: 'Bono', price: 0, expires_at: '' }
  selectedTemplate.value = null
}



async function create() {
  if (!selectedTemplate.value) return
  try {
    const bonus = {
      name: form.value.name,
      price: form.value.price,
      bonus_type_id: selectedTemplate.value.id,
      total_sessions: selectedTemplate.value.sessions,
    }
    if (form.value.expires_at) bonus.expires_at = form.value.expires_at
    const res = await api.post(`/patients/${props.patientId}/bonuses`, bonus)
    const b = (res.data && res.data.data) ? res.data.data : res.data
    const nb = normalizeBonus(b)
    nb.justCreated = true
    bonuses.value.unshift(nb)
    expandedBonuses.add(nb.id)
    setTimeout(() => { nb.justCreated = false }, 4000)
    toast.success('Bono asociado')
    cancelForm()
  } catch (e) {
    toast.error('Error asociando bono')
  }
}

async function deleteBonus(id) {
  try {
    await api.delete(`/bonuses/${id}`)
    bonuses.value = bonuses.value.filter(b => b.id !== id)
    toast.success('Bono eliminado', {
      toastClassName: 'toast-delete',
      progressClassName: 'toast-delete-progress',
    })
  } catch (e) {
    toast.error('Error eliminando bono')
  }
}

async function confirmDeleteBonus(bonus) {
  if (bonus?.invoice_id) {
    toast.error('No se puede eliminar un bono que ya está facturado')
    return
  }

  const res = await Swal.fire({
    title: `Eliminar bono`,
    text: `¿Eliminar el bono de ${bonus.total_sessions} sesiones?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
    customClass: { popup: 'swal-popup-warning-card' },
  })

  if (!res.isConfirmed) return
  await deleteBonus(bonus.id)
}

async function goInvoiceFromBonus(bonus) {
  if (!bonus?.id) return

  if (bonus.invoice_id) {
    router.push(`/invoices/${bonus.invoice_id}`)
    return
  }

  invoicingBonusId.value = bonus.id
  try {
    const res = await api.post(`/bonuses/${bonus.id}/invoice`)
    const invoiceId = Number(res.data?.data?.id || 0)

    if (invoiceId > 0) {
      bonus.invoice_id = invoiceId
      toast.success(res.data?.message || 'Factura emitida correctamente')
      router.push(`/invoices/${invoiceId}`)
      return
    }

    toast.error('No se pudo obtener la factura creada')
  } catch (e) {
    toast.error(e?.response?.data?.message || 'No se pudo emitir la factura del bono')
  } finally {
    invoicingBonusId.value = null
  }
}

function goToBonusPayment(bonus) {
  if (!bonus?.id || !props.patientId || isBonusPaidLocal(bonus)) return

  const amount = Number(bonus.price || 0)
  router.push({
    path: '/payments/create',
    query: {
      patient_id: String(props.patientId),
      concept: 'package',
      package_id: String(bonus.id),
      amount: Number.isFinite(amount) ? amount.toFixed(2) : '0.00',
    },
  })
}

onMounted(load)

function prefillRenew(b) {
  openAssociate()
  if (b.bonus_type_id) {
    const tpl = bonusTemplates.value.find((t) => t.id === b.bonus_type_id)
    if (tpl) {
      onSelectBonusType(tpl)
      return
    }
  }
  form.value.name = b.name || 'Bono'
  form.value.price = b.price != null ? Number(b.price) : 0
  form.value.expires_at = ''
}
</script>

<style scoped>
.patient-bonuses label { display:block; font-weight:600; margin-bottom:4px }
.patient-bonuses input { padding:8px; border:1px solid #e5e7eb; border-radius:6px }
.primary { padding:6px 12px; border-radius:9999px; border:2px solid #3b82f6; color:#3b82f6; background:#fff }
.primary:disabled { opacity:0.45; cursor:not-allowed }
.muted { padding:6px 12px; border-radius:8px; border:1px solid #e5e7eb; background:#fff }
.action-btn {
  display:inline-flex;
  align-items:center;
  gap:4px;
  padding:4px 8px;
  border-radius:8px;
  border:1px solid #e5e7eb;
  background:#fff;
  font-size:12px;
}
.action-btn:disabled { opacity:0.45; cursor:not-allowed }
.btn-icon { width:14px; height:14px }

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

.bonus-list {
  margin-top:12px;
  display:flex;
  flex-direction:column;
  gap:10px;
}

.bonus-card {
  padding:14px;
  border-radius:12px;
  border:1px solid #e5e7eb;
  background:#fff;
  transition:all 0.2s ease;
  cursor:pointer;
}

.bonus-card.active {
  background:#f0fdf4;
  border-color:#bbf7d0;
}

.bonus-card.last {
  background:#fffbeb;
  border-color:#fde68a;
}

.bonus-card.exhausted {
  background:#fef2f2;
  border-color:#fecaca;
  opacity:0.85;
}

.bonus-card.new {
  background: #f3f4f6;
  border-color: #e5e7eb;
  box-shadow: 0 6px 18px rgba(2,6,23,0.04);
}

.bonus-header {
  display:flex;
  flex-direction:column;
  gap:4px;
}

.bonus-header-row {
  display:flex;
  justify-content:space-between;
  align-items:center;
}

.bonus-header-left {
  display:flex;
  align-items:center;
  gap:8px;
  min-width:0;
}

.chevron {
  width:16px;
  height:16px;
  flex-shrink:0;
  transition:transform 0.2s ease;
  color:#9ca3af;
}

.expanded .chevron {
  transform:rotate(180deg);
}

.bonus-header-right {
  display:flex;
  align-items:center;
  gap:8px;
  flex-shrink:0;
}

.bonus-expiry {
  font-size:12px;
  font-weight:700;
  color:#d97706;
  white-space:nowrap;
}

.bonus-price-row {
  display:flex;
  justify-content:space-between;
  align-items:center;
  padding-left:24px;
}

.bonus-price-total {
  font-size:14px;
  font-weight:700;
  color:#111827;
  text-align:right;
}

.bonus-badges {
  display:flex;
  align-items:center;
  gap:6px;
}

.bonus-badge {
  padding:4px 8px;
  border-radius:9999px;
  font-size:11px;
  font-weight:700;
}

.bonus-badge.active { background:#dcfce7; color:#166534 }
.bonus-badge.last { background:#fef3c7; color:#92400e }
.bonus-badge.exhausted { background:#fee2e2; color:#991b1b }
.bonus-badge.expired { background:#f3f4f6; color:#6b7280 }

.payment-badge {
  padding:4px 8px;
  border-radius:9999px;
  font-size:11px;
  font-weight:700;
}

.payment-badge.paid {
  background:#dcfce7;
  color:#166534;
}

.payment-badge.unpaid {
  background:#fee2e2;
  color:#b91c1c;
}

.big-number {
  font-size:20px;
  font-weight:700;
  margin-right:6px;
}

.muted-text {
  color:#64748b;
  font-size:13px;
}

.session-lines {
  margin-top:6px;
  display:flex;
  flex-direction:column;
  gap:3px;
}

.session-line {
  display:flex;
  justify-content:space-between;
  align-items:center;
  font-size:12px;
  color:#6b7280;
  padding:2px 6px;
  background:#f9fafb;
  border-radius:4px;
}

.session-line-type {
  font-weight:500;
}

.session-line-count {
  font-weight:700;
  color:#374151;
}

.bonus-type-name {
  font-size:12px;
  color:#6b7280;
  margin-top:2px;
}

.bonus-actions {
  margin-top:8px;
  display:flex;
  justify-content:space-between;
  align-items:center;
}

.bonus-actions-left {
  display:flex;
  gap:6px;
}

.bonus-actions-right {
  display:flex;
  gap:6px;
}

.renew-btn {
  background:#f59e0b;
  color:white;
  border:none;
  padding:6px 10px;
  border-radius:9999px;
  font-size:12px;
  font-weight:600;
}

/* Modal */
.modal-backdrop {
  position:fixed;
  inset:0;
  z-index:1050;
  background:rgba(0,0,0,0.45);
  display:flex;
  align-items:center;
  justify-content:center;
}
.modal-card {
  background:#fff;
  border-radius:12px;
  width:90%;
  max-width:560px;
  min-height:420px;
  max-height:90vh;
  overflow-y:auto;
  padding:24px;
  box-shadow:0 20px 60px rgba(0,0,0,0.2);
}
.modal-header {
  display:flex;
  justify-content:space-between;
  align-items:center;
  margin-bottom:16px;
}
.modal-close {
  background:none;
  border:none;
  font-size:24px;
  line-height:1;
  cursor:pointer;
  color:#6b7280;
  padding:0 4px;
}
.modal-close:hover { color:#111827 }

.create-row { margin-bottom:12px }
.create-row label { display:block; font-weight:600; margin-bottom:6px }
.create-row input { width:100%; padding:8px; border:1px solid #e5e7eb; border-radius:6px }
.create-actions { margin-top:16px; display:flex; gap:8px; justify-content:flex-end }

.template-selected {
  border:1px solid #e5e7eb;
  border-radius:8px;
  padding:10px 12px;
  background:#f9fafb;
}

.template-selected-info {
  display:flex;
  justify-content:space-between;
  align-items:center;
}

.template-option-price {
  font-size:13px;
  color:#6b7280;
  font-weight:600;
}

.template-option-lines {
  margin-top:6px;
  display:flex;
  flex-wrap:wrap;
  gap:4px;
}

.template-option-line {
  font-size:12px;
  color:#6b7280;
  background:#f3f4f6;
  padding:3px 8px;
  border-radius:4px;
}

.change-type-btn {
  margin-top:6px;
  font-size:12px;
  color:#6b7280;
  background:none;
  border:none;
  cursor:pointer;
  padding:0;
  text-decoration:underline;
}
.change-type-btn:hover { color:#374151 }

.template-fields { margin-top:4px }
</style>
