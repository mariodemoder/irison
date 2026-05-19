<template>
  <div class="patient-bonuses">
    <div style="display:flex; justify-content:space-between; align-items:center">
      <h3 style="margin:0">Bonos</h3>
      <div style="display:flex;gap:8px;align-items:center">
        <button
          class="toggle-canceled-btn"
          @click="toggleInactiveVisibility"
          :title="showInactiveBonuses ? 'Ver solo bonos disponibles' : 'Ver todos los bonos'"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width:14px;height:14px">
                    <circle cx="11" cy="11" r="7"></circle>
                    <path d="M21 21l-4.3-4.3"></path>
          </svg>
        </button>
        <button @click="showForm = !showForm" class="primary" style="padding:6px 10px;font-size:13px">Crear</button>
      </div>
    </div>

    <div v-if="showForm" style="margin-top:8px">
      <div class="create-card">
        <form @submit.prevent="create" class="create-form">
          <div class="create-row">
            <label>Tipo de bono</label>
            <select v-model="selectedTemplateId" @change="applySelectedTemplate">
              <option value="manual">Manual</option>
              <option
                v-for="tpl in bonusTemplates"
                :key="tpl.id"
                :value="String(tpl.id)"
              >
                {{ tpl.description || 'Bono sin descripción' }}
              </option>
            </select>
          </div>
          <div class="create-row">
            <label>Nombre</label>
            <input v-model="form.name" type="text" required />
          </div>
          <div class="create-row">
            <label>Nº sesiones</label>
            <input v-model.number="form.total_sessions" type="number" min="1" required />
          </div>
          <div class="create-row">
            <label>Precio</label>
            <input v-model.number="form.price" type="number" step="0.01" min="0" />
          </div>
          <div class="create-row">
            <label>Expira (opcional)</label>
            <input v-model="form.expires_at" type="date" />
          </div>
          <div class="create-actions">
            <button type="submit" class="primary">Crear</button>
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
        :class="[b.status, { new: b.justCreated }]"
      >
        <div class="bonus-header">
          <div>
            <strong>{{ bonusTitle(b) }}</strong>
          </div>
          <div class="bonus-badges">
            <div class="bonus-badge">
              {{ statusLabel(b.status) }}
            </div>
            <div class="payment-badge" :class="bonusPaymentClass(b)">
              {{ bonusPaymentLabel(b) }}
            </div>
          </div>
        </div>

        <div class="bonus-body">
          <div>
            <span class="big-number">{{ b.remaining_sessions }}</span>
            <span class="muted-text">/ {{ b.total_sessions }} sesiones</span>
          </div>
          <div class="bonus-price">
            Precio: {{ formatBonusPrice(b.price) }}
          </div>
          <div class="expiry">
            Expira: {{ b.expires_at ? formatDMY(b.expires_at) : '—' }}
          </div>
        </div>

        <div class="bonus-actions">
          <button
            v-if="b.status === 'last'"
            class="renew-btn"
            @click="prefillRenew(b)"
          >
            Renovar
          </button>

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
          </button>

          <button
            @click="confirmDeleteBonus(b)"
            class="action-btn"
            :title="b.invoice_id ? 'No se puede eliminar: bono facturado' : 'Eliminar bono'"
            :disabled="Boolean(b.invoice_id)"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="btn-icon">
              <path d="M3 6h18"></path>
              <path d="M8 6V4h8v2"></path>
              <path d="M19 6l-1 14H6L5 6"></path>
              <path d="M10 11v6M14 11v6"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import api from '../services/api'
import { useToast } from 'vue-toastification'
import { formatDMY } from '../shared/dateHelpers'

const props = defineProps({ patientId: { type: [String, Number], required: true } })
const emit = defineEmits(['active-bonus-count'])
const bonuses = ref([])
const showForm = ref(false)
const showInactiveBonuses = ref(false)
const form = ref({ name: 'Bono', total_sessions: 1, price: 0, expires_at: '' })
const bonusTemplates = ref([])
const selectedTemplateId = ref('manual')
const toast = useToast()
const router = useRouter()
const invoicingBonusId = ref(null)

function normalizeTemplate(item) {
  const rawDate = String(item?.expires_at || '').trim()
  const expiresAt = /^\d{4}-\d{2}-\d{2}$/.test(rawDate)
    ? rawDate
    : (/^\d{4}-\d{2}-\d{2}T/.test(rawDate) ? rawDate.slice(0, 10) : '')

  return {
    id: Number(item?.id),
    description: String(item?.description || '').trim(),
    sessions: Number.isFinite(Number(item?.sessions)) ? Math.max(Number(item.sessions), 1) : 1,
    price: Number.isFinite(Number(item?.price)) ? Math.max(Number(item.price), 0) : 0,
    expires_at: expiresAt,
  }
}

function applySelectedTemplate() {
  if (selectedTemplateId.value === 'manual') return

  const selected = bonusTemplates.value.find((tpl) => String(tpl.id) === String(selectedTemplateId.value))
  if (!selected) return

  form.value.name = selected.description || 'Bono'
  form.value.total_sessions = selected.sessions
  form.value.price = selected.price
  form.value.expires_at = selected.expires_at || ''
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
    invoice_id: b.invoice_id != null ? Number(b.invoice_id) : null,
    total_sessions: b.total_sessions != null ? Number(b.total_sessions) : 0,
    remaining_sessions: b.remaining_sessions != null ? Number(b.remaining_sessions) : 0,
    price: b.price != null ? Number(b.price) : 0,
    expires_at: b.expires_at ?? null,
    status: b.status ?? (b.remaining_sessions <= 0 ? 'exhausted' : 'active'),
    is_paid: Boolean(b.is_paid),
    justCreated: b.justCreated ?? false,
  }
}

function formatBonusPrice(value) {
  const amount = Number(value)
  if (!Number.isFinite(amount) || amount < 0) return '0.00€'
  return `${amount.toFixed(2)}€`
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
    : bonuses.value.filter(b => b.status !== 'expired' && b.status !== 'exhausted')

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
  try {
    const [bonusesRes, meRes] = await Promise.all([
      api.get(`/patients/${props.patientId}/bonuses`),
      api.get('/me'),
    ])

    const incomingTemplates = Array.isArray(meRes?.data?.bonus_types) ? meRes.data.bonus_types : []
    bonusTemplates.value = incomingTemplates
      .map(normalizeTemplate)
      .filter((tpl) => Number.isFinite(tpl.id) && tpl.id > 0)

    const res = bonusesRes
    bonuses.value = Array.isArray(res.data.data) ? res.data.data.map(normalizeBonus) : []
  } catch (e) {
    bonuses.value = []
    bonusTemplates.value = []
  }
}

function cancelForm() {
  showForm.value = false
  form.value = { name: 'Bono', total_sessions: 1, price: 0, expires_at: '' }
  selectedTemplateId.value = 'manual'
}

async function create() {
  try {
    const bonus = { ...form.value }
    if (selectedTemplateId.value !== 'manual') {
      const templateId = Number(selectedTemplateId.value)
      if (Number.isFinite(templateId) && templateId > 0) {
        bonus.bonus_type_id = templateId
      }
    }
    if (!bonus.expires_at) delete bonus.expires_at
    const res = await api.post(`/patients/${props.patientId}/bonuses`, bonus)
    const b = (res.data && res.data.data) ? res.data.data : res.data
    const nb = normalizeBonus(b)
    nb.justCreated = true
    bonuses.value.unshift(nb)
    // remove highlight after a short delay
    setTimeout(() => { nb.justCreated = false }, 4000)
    toast.success('Bono creado')
    cancelForm()
  } catch (e) {
    toast.error('Error creando bono')
  }
}

async function deleteBonus(id) {
  try {
    await api.delete(`/bonuses/${id}`)
    bonuses.value = bonuses.value.filter(b => b.id !== id)
    toast.success('Bono eliminado')
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
    cancelButtonText: 'Cancelar'
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
  selectedTemplateId.value = 'manual'
  form.value.name = b.name || 'Bono'
  form.value.total_sessions = b.total_sessions != null ? Number(b.total_sessions) : 1
  form.value.price = b.price != null ? b.price : 0
  form.value.expires_at = ''
  showForm.value = true
  // focus could be added if needed
}
</script>

<style scoped>
.patient-bonuses label { display:block; font-weight:600; margin-bottom:4px }
.patient-bonuses input { padding:8px; border:1px solid #e5e7eb; border-radius:6px }
.patient-bonuses select { padding:8px; border:1px solid #e5e7eb; border-radius:6px; background:#fff }
.primary { padding:6px 12px; border-radius:9999px; border:2px solid #3b82f6; color:#3b82f6; background:#fff }
.muted { padding:6px 12px; border-radius:8px; border:1px solid #e5e7eb; background:#fff }
.action-btn { padding:4px 8px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; font-size:12px }
.action-btn:disabled { opacity:0.45; cursor:not-allowed }
.btn-icon { width:14px; height:14px; display:block }

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

/* 6️⃣ Estilos UX profesional */
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

/* Newly created highlight */
.bonus-card.new {
  background: #f3f4f6; /* light gray */
  border-color: #e5e7eb;
  box-shadow: 0 6px 18px rgba(2,6,23,0.04);
}

.bonus-header {
  display:flex;
  justify-content:space-between;
  align-items:center;
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

.expiry {
  font-size:12px;
  color:#6b7280;
  margin-top:4px;
}

.bonus-price {
  font-size:13px;
  color:#111827;
  font-weight:600;
  margin-top:4px;
}

.bonus-actions {
  margin-top:8px;
  display:flex;
  justify-content:space-between;
  align-items:center;
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

/* Create form card */
.create-card {
  padding:12px;
  border-radius:10px;
  border:1px solid #e5e7eb;
  background:#f8fafc;
}
.create-row { margin-bottom:8px }
.create-row label { display:block; font-weight:600; margin-bottom:6px }
.create-row input { width:100%; padding:8px; border:1px solid #e5e7eb; border-radius:6px }
.create-actions { margin-top:10px; display:flex; gap:8px }

</style>
