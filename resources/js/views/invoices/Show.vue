<template>
  <MainLayout>
    <div class="show-wrap">
      <div class="show-card">
        <div class="show-header">
          <div>
            <h1>{{ documentHeading }}</h1>
            <p class="form-sub">{{ documentSubheading }}</p>
          </div>
          <div class="header-actions">
            <div class="back-menu-group">
              <button type="button" class="muted back-btn" @click="goBack">Volver</button>
              <div class="quick-actions" ref="quickActionsRef">
                <button
                  type="button"
                  class="muted quick-trigger menu-right-btn"
                  @click="toggleQuickActions"
                  :disabled="!documentData?.id || loading"
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
                  
                  <button
                    type="button"
                    class="quick-item"
                    @click.prevent="runPreviewPdf"
                    :disabled="!documentData?.id || loading"
                  >
                    Ver PDF
                  </button>
                  <button
                    type="button"
                    class="quick-item"
                    @click.prevent="runDownloadPdf"
                    :disabled="!documentData?.id || loading"
                  >
                    Descargar PDF
                  </button>

                  <button
                    v-if="showDocumentAction"
                    type="button"
                    class="quick-item"
                    @click.prevent="runHandleAbono"
                    :disabled="documentActionDisabled"
                  >
                    {{ documentActionLabel }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <AppLoading v-if="loading" message="Cargando factura..." />

        <div v-else-if="documentData" class="details-grid">
          <div class="field"><label class="label">Fecha</label><div class="value">{{ formatDateOnlyDay(documentData.date || documentData.created_at) }}</div></div>
          <div class="field"><label class="label">Número</label><div class="value">{{ documentData.counter || '—' }}</div></div>

          <div class="field full"><label class="label">Usuario emisor</label><div class="value">{{ documentData.user_full_name || '—' }}</div></div>

          <div class="field"><label class="label">NIF paciente</label><div class="value">{{ documentData.patient_nif || documentData.patient?.nif || '—' }}</div></div>
          <div class="field"><label class="label">Nombre paciente</label><div class="value">{{ documentData.patient_full_name || documentData.patient?.name || '—' }}</div></div>

          <div class="field"><label class="label">Domicilio</label><div class="value">{{ documentData.patient_address || documentData.patient?.address || '—' }}</div></div>
          <div class="field"><label class="label">Teléfono</label><div class="value">{{ documentData.patient_phone || documentData.patient?.phone || '—' }}</div></div>

          <div class="field full"><label class="label">Detalle</label><div class="value">{{ documentData.notes || '—' }}</div></div>

          <!-- Items de la factura (solo para typeinvoice === 'varios') -->
          <template v-if="documentData.typeinvoice === 'varios' && documentData.items?.length">
            <div class="field full">
              <label class="label">Conceptos</label>
              <div class="items-table">
                <div class="items-head">
                  <div>Descripción</div>
                  <div>Cant.</div>
                  <div>P. Unit.</div>
                  <div>IVA %</div>
                  <div>Total</div>
                </div>
                <div v-for="item in documentData.items" :key="item.id" class="items-row">
                  <div>
                    {{ item.description }}
                    <span class="item-tag" v-if="item.type !== 'manual'">{{ itemTypeLabel(item.type) }}</span>
                  </div>
                  <div>{{ item.quantity }}</div>
                  <div>{{ formatCurrency(item.unit_price) }}</div>
                  <div>{{ item.tax_rate }}%</div>
                  <div class="item-total">{{ formatCurrency(item.total) }}</div>
                </div>
              </div>
            </div>
          </template>

          <div class="field"><label class="label">Importe</label><div class="value">{{ formatCurrency(documentData.amount) }}</div></div>
          <div v-if="showPaymentStatus" class="field"><label class="label">Estado de pago</label><div class="value"><span class="status" :class="paymentStatusClass">{{ statusLabel(paymentStatusValue) }}</span></div></div>
        </div>

        <div v-else class="alert-subtle">No se encontró la factura.</div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import { useToast } from 'vue-toastification'
import { formatDateOnlyDay } from '../../shared/dateHelpers'
import { goBackWithPriority } from '../../shared/navigationHelpers'

const toast = useToast()
const route = useRoute()
const router = useRouter()

const loading = ref(false)
const documentData = ref(null)
const issuingAbono = ref(false)
const quickActionsOpen = ref(false)
const quickActionsRef = ref(null)

const hasRectification = computed(() => Number(documentData.value?.rectification_document?.id || 0) > 0)

const canIssueAbono = computed(() => {
  if (!documentData.value) return false

  return documentData.value.type === 'invoice'
    && String(documentData.value.status || '') === 'issued'
    && !hasRectification.value
})

const canHandleAbono = computed(() => {
  return Boolean(documentData.value) && !issuingAbono.value && (canIssueAbono.value || hasRectification.value)
})

const canViewOriginInvoice = computed(() => {
  return documentData.value?.type === 'abono' && Number(documentData.value?.origin_document?.id || 0) > 0
})

const showDocumentAction = computed(() => {
  return canViewOriginInvoice.value || canIssueAbono.value || hasRectification.value
})

const documentActionDisabled = computed(() => {
  if (canViewOriginInvoice.value) return false
  return !canHandleAbono.value
})

const documentActionLabel = computed(() => {
  if (canViewOriginInvoice.value) return 'Ver Factura'
  if (hasRectification.value) return 'Ver abono'
  if (canIssueAbono.value) return issuingAbono.value ? 'Generando abono...' : 'Crear abono'
  return ''
})

const documentHeading = computed(() => {
  return documentData.value?.type === 'abono' ? 'Factura rectificativa' : 'Factura'
})

const documentSubheading = computed(() => {
  return documentData.value?.type === 'abono'
    ? 'Detalle de factura rectificativa (solo lectura)'
    : 'Detalle de factura (solo lectura)'
})

const showPaymentStatus = computed(() => {
  return documentData.value?.type !== 'abono'
})

const paymentActionLabel = computed(() => {
  if (documentData.value?.type === 'abono') return ''
  return isPaidDocument.value ? 'Ver Pago' : 'Pagar'
})

const paymentStatusValue = computed(() => {
  return String(documentData.value?.payment_status || documentData.value?.status || '')
})

const paymentStatusClass = computed(() => {
  return paymentStatusValue.value === 'paid' ? 'issued' : paymentStatusValue.value
})

const isPaidDocument = computed(() => {
  return ['paid', 'issued', 'covered_by_pack'].includes(paymentStatusValue.value)
})

const clinicLocation = computed(() => {
  const zip = String(documentData.value?.clinic_zip || '').trim()
  const province = String(documentData.value?.clinic_province || '').trim()
  const country = String(documentData.value?.clinic_country || '').trim()
  const parts = [zip, province, country].filter(Boolean)
  return parts.length ? parts.join(' · ') : '—'
})

function formatCurrency(value) {
  const number = Number(value || 0)
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(number)
}

function statusLabel(status) {
  if (status === 'covered_by_pack') return 'Cubierta por bono'
  if (status === 'partially_paid') return 'Parcialmente pagada'
  if (status === 'paid') return 'Pagada'
  if (status === 'pending') return 'Pendiente'
  if (status === 'issued') return 'Pagado'
  if (status === 'draft') return 'Pendiente'
  if (status === 'cancelled') return 'Cancelado'
  return status || '—'
}

function goBack() {
  const from = String(route.query.from || '').toLowerCase()
  const appointmentId = Number(route.query.appointment_id || 0)

  goBackWithPriority(router, {
    priorityPath: from === 'appointment' && appointmentId > 0 ? `/appointments/${appointmentId}` : '',
    fallbackPath: '/invoices',
  })
}

async function handleDocumentAction() {
  const currentDocument = documentData.value
  if (!currentDocument) return

  if (currentDocument.type === 'abono' && currentDocument.origin_document?.id) {
    router.push(`/invoices/${currentDocument.origin_document.id}`)
    return
  }

  if (!canHandleAbono.value) return

  if (currentDocument.rectification_document?.id) {
    router.push(`/invoices/${currentDocument.rectification_document.id}`)
    return
  }

  issuingAbono.value = true

  try {
    const res = await api.post(`/documents/${currentDocument.id}/abono`)
    const abonoId = Number(res.data?.data?.id || 0)

    if (!abonoId) {
      throw new Error('No se recibió el id del abono')
    }

    toast.success(res.data?.message || 'Factura rectificativa creada correctamente')
    router.push(`/invoices/${abonoId}`)
  } catch (e) {
    toast.error(e?.response?.data?.error || 'No se pudo generar el abono de la factura')
  } finally {
    issuingAbono.value = false
  }
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

function runHandleAbono() {
  closeQuickActions()
  handleDocumentAction()
}

function runPreviewPdf() {
  closeQuickActions()
  previewPdf()
}

function runDownloadPdf() {
  closeQuickActions()
  downloadPdf()
}

function runPay() {
  closeQuickActions()
  if (!documentData.value?.id) return

  const isPaid = isPaidDocument.value
  const path = isPaid ? '/payments' : '/payments/create'
  const query = { invoice_id: documentData.value.id }
  router.push({ path, query })
}

async function load() {
  loading.value = true
  try {
    const id = route.params.id
    const res = await api.get(`/documents/${id}`)
    documentData.value = res.data || null
  } catch (e) {
    documentData.value = null
    const status = e?.response?.status
    const message = e?.response?.data?.message
    toast.error((status === 402 || status === 403) && message ? `Error cargando factura - ${message}` : 'Error cargando factura')
  } finally {
    loading.value = false
  }
}

function invoiceDownloadName() {
  const suffix = String(documentData.value?.counter || documentData.value?.id || 'factura').replace(/[^a-zA-Z0-9_-]/g, '_')
  return documentData.value?.type === 'abono'
    ? `factura-rectificativa-${suffix}.pdf`
    : `factura-${suffix}.pdf`
}

async function previewPdf() {
  const id = documentData.value?.id
  if (!id) return

  try {
    const res = await api.get(`/documents/${id}/pdf`, { responseType: 'blob' })
    const file = new Blob([res.data], { type: 'application/pdf' })
    const fileUrl = URL.createObjectURL(file)
    window.open(fileUrl, '_blank', 'noopener,noreferrer')
    setTimeout(() => URL.revokeObjectURL(fileUrl), 60000)
  } catch (e) {
    toast.error('No se pudo abrir el PDF de la factura')
  }
}

async function downloadPdf() {
  const id = documentData.value?.id
  if (!id) return

  try {
    const res = await api.get(`/documents/${id}/pdf`, { responseType: 'blob' })
    const file = new Blob([res.data], { type: 'application/pdf' })
    const fileUrl = URL.createObjectURL(file)
    const link = document.createElement('a')
    link.href = fileUrl
    link.download = invoiceDownloadName()
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(fileUrl), 60000)
  } catch (e) {
    toast.error('No se pudo descargar el PDF de la factura')
  }
}

onMounted(async () => {
  await load()
  document.addEventListener('click', handleClickOutsideQuickActions)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutsideQuickActions)
})

watch(() => route.params.id, async (newId, oldId) => {
  if (newId && newId !== oldId) {
    documentData.value = null
    await load()
  }
})

function itemTypeLabel(type) {
  if (type === 'appointment') return 'Cita'
  if (type === 'bonus') return 'Bono'
  if (type === 'product') return 'Producto'
  return ''
}
</script>

<style scoped>
.show-wrap { display:flex; justify-content:center; padding:6px 0 }
.show-card { width:100%; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; box-shadow:0 10px 30px rgba(2,6,23,0.06) }
.show-header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px }
.show-header h1 { margin:0; font-size:22px }
.header-actions { display:flex; align-items:center; gap:0 }
.back-menu-group { display:inline-flex; align-items:center; gap:0 }
.pdf-btn { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:8px; border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8 }
.pdf-btn:hover { background:#dbeafe; border-color:#93c5fd }
.pdf-btn:disabled { opacity:0.45; cursor:not-allowed }
.pdf-icon { width:14px; height:14px; display:block }

.quick-trigger { padding:11px 12px; display:inline-flex; align-items:center; justify-content:center }
.quick-trigger-icon { width:18px; height:18px; color:#4b5563 }
.quick-actions { position:relative }
.quick-menu { position:absolute; right:0; top:calc(100% + 6px); min-width:200px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 10px 24px rgba(2,6,23,0.10); padding:6px; display:flex; flex-direction:column; gap:4px; z-index:30 }
.quick-item { text-align:left; padding:8px 10px; border:1px solid transparent; background:#fff; border-radius:8px; font-size:14px; color:#111827 }
.quick-item:hover { background:#f9fafb }
.quick-item:disabled { opacity:0.45; cursor:not-allowed }

.details-grid { display:grid; grid-template-columns:repeat(2, 1fr); gap:12px }
.field { display:flex; flex-direction:column }
.field.full { grid-column:1 / -1 }
.label { font-weight:600; margin-bottom:6px }
.value { padding:10px; border:1px solid #e5e7eb; border-radius:8px; background:#fff }

/* Items table en show */
.items-table { border:1px solid #e5e7eb; border-radius:8px; overflow:hidden }
.items-head { display:grid; grid-template-columns:2fr 0.6fr 0.8fr 0.6fr 0.8fr; gap:8px; padding:7px 12px; background:#f3f4f6; font-size:12px; font-weight:700; color:#6b7280 }
.items-row { display:grid; grid-template-columns:2fr 0.6fr 0.8fr 0.6fr 0.8fr; gap:8px; padding:7px 12px; border-top:1px solid #f3f4f6; font-size:13px; align-items:center }
.item-tag { display:inline-block; font-size:11px; color:#2563eb; background:#dbeafe; border-radius:4px; padding:1px 5px; margin-left:4px }
.item-total { font-weight:700 }

.status { padding:5px 8px; border-radius:9999px; font-weight:700; text-transform:capitalize; font-size:11px }
.status.issued { background:#dcfce7; color:#166534 }
.status.paid { background:#dcfce7; color:#166534 }
.status.draft { background:#fef3c7; color:#92400e }
.status.pending { background:#fef3c7; color:#92400e }
.status.partially_paid { background:#dbeafe; color:#1e40af }
.status.covered_by_pack { background:#dbeafe; color:#1e40af }
.status.cancelled { background:#f3f4f6; color:#374151 }

.alert-subtle { background:#f8fafc; border:1px solid #e6edf3; padding:10px; border-radius:8px; color:#334155; font-size:14px }

@media (max-width: 768px) {
  .details-grid { grid-template-columns:1fr }
  .items-head, .items-row { grid-template-columns:1fr 0.5fr 0.7fr 0.5fr 0.7fr }
}
</style>
