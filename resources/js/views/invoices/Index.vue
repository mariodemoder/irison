<template>
  <MainLayout>
    <div>
      <div class="page-header">
        <div>
          <h1>Facturación</h1>
          <div class="form-sub">Listado de facturas</div>
        </div>
        <div>
          <router-link to="/appointments/day" class="primary">Gestionar en Cita</router-link>
        </div>
      </div>

      <div class="filters">
        <div class="search-wrapper">
          <input v-model="filters.q" placeholder="Buscar por paciente o NIF" class="search-input" @input="debouncedReload" />
        </div>
        <select v-model="filters.status" @change="load(1)">
          <option value="">Estado: todos</option>
          <option value="issued">Pagado</option>
          <option value="draft">Pendiente</option>
          <option value="cancelled">Cancelado</option>
        </select>
        <input v-model="filters.from_date" type="date" class="search-input" @change="load(1)" />
        <input v-model="filters.to_date" type="date" class="search-input" @change="load(1)" />
      </div>

      <div class="summary">
        <div><strong>{{ summary.count }}</strong> factura(s)</div>
        <div>Total: <strong>{{ formatCurrency(summary.total_amount) }}</strong></div>
      </div>

      <AppLoading v-if="loading" message="Cargando facturas..." />

      <template v-else>
        <div class="list-header">
          <div>Número</div>
          <div>Fecha</div>
          <div>Paciente</div>
          <div>Tipo</div>
          <div>Importe</div>
          <div>Estado de pago</div>
          <div>PDF</div>
        </div>

        <div class="list">
          <div
            v-for="doc in documents"
            :key="doc.id"
            class="invoice-row"
            role="button"
            tabindex="0"
            @click="goToShow(doc.id)"
            @keydown.enter.prevent="goToShow(doc.id)"
          >
            <div>{{ doc.counter }}</div>
            <div>{{ formatDateOnlyDay(doc.date || doc.created_at) }}</div>
            <router-link
              v-if="doc.patient?.id"
              :to="`/patients/${doc.patient.id}`"
              class="patient-link"
              @click.stop
            >
              {{ patientLabel(doc) }}
            </router-link>
            <div v-else>{{ patientLabel(doc) }}</div>
            <div>
              <span class="type-chip">
                <svg v-if="doc.typeinvoice === 'package'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="type-icon">
                  <rect x="3" y="8" width="18" height="4" rx="1"></rect>
                  <path d="M4 12h16v8H4z"></path>
                  <path d="M12 8v12"></path>
                  <path d="M12 8c-1.8 0-3-1.2-3-2.5S10 3 12 5.5"></path>
                  <path d="M12 8c1.8 0 3-1.2 3-2.5S14 3 12 5.5"></path>
                </svg>
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="type-icon">
                  <path d="M7 3h8l4 4v14H7z"></path>
                  <path d="M15 3v4h4"></path>
                  <path d="M10 12h6M10 16h6"></path>
                </svg>
                <span>{{ typeInvoiceLabel(doc) }}</span>
              </span>
            </div>
            <div>{{ formatCurrency(doc.amount) }}</div>
            <div><span class="status" :class="paymentStatusClass(doc)">{{ statusLabel(paymentStatusValue(doc)) }}</span></div>
            <div class="pdf-actions">
              <button
                type="button"
                class="pdf-btn"
                title="Vista previa PDF"
                @click.stop="previewPdf(doc)"
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="pdf-icon">
                  <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                  <circle cx="12" cy="12" r="2.5"></circle>
                </svg>
              </button>
              <button
                type="button"
                class="pdf-btn"
                title="Descargar PDF"
                @click.stop="downloadPdf(doc)"
              >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="pdf-icon">
                  <path d="M12 4v11"></path>
                  <path d="M8.5 11.5L12 15l3.5-3.5"></path>
                  <path d="M5 19h14"></path>
                </svg>
              </button>
            </div>
          </div>
          <EmptyIndexState v-if="documents.length === 0 && !hasActiveFilters" />
          <div v-else-if="documents.length === 0" class="empty">No hay resultados para los filtros aplicados.</div>
        </div>

        <div v-if="meta" class="pagination">
          <div class="pagination-info">Página {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} facturas</div>
          <div class="pagination-actions">
            <button :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)" class="icon-btn">‹</button>
            <button :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)" class="icon-btn">›</button>
          </div>
        </div>
      </template>
    </div>
  </MainLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import EmptyIndexState from '../../components/EmptyIndexState.vue'
import { useToast } from 'vue-toastification'
import { formatDateOnlyDay } from '../../shared/dateHelpers'

const toast = useToast()
const router = useRouter()

const loading = ref(false)
const documents = ref([])
const meta = ref(null)
const summary = ref({ count: 0, total_amount: 0 })
let searchTimer = null

const filters = ref({
  q: '',
  status: '',
  from_date: '',
  to_date: '',
})

const hasActiveFilters = computed(() => {
  return Boolean(String(filters.value.q || '').trim())
    || Boolean(filters.value.status)
    || Boolean(filters.value.from_date)
    || Boolean(filters.value.to_date)
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

function paymentStatusValue(doc) {
  return String(doc?.payment_status || doc?.status || '')
}

function paymentStatusClass(doc) {
  const status = paymentStatusValue(doc)
  return status === 'paid' ? 'issued' : status
}

function typeInvoiceLabel(doc) {
  const type = String(doc?.type || '')
  const typeinvoice = String(doc?.typeinvoice || '')

  if (type === 'abono' && typeinvoice === 'appointment') return 'Abono de Cita'
  if (type === 'abono' && typeinvoice === 'package') return 'Abono de Bono'
  if (type === 'abono' && typeinvoice === 'credit') return 'Abono de Adelanto'
  if (type === 'abono' && typeinvoice === 'manual') return 'Abono Manual'
  if (typeinvoice === 'appointment') return 'Factura de Cita'
  if (typeinvoice === 'package') return 'Factura de Bono'
  if (typeinvoice === 'credit') return 'Factura de Adelanto'
  if (typeinvoice === 'manual') return 'Factura Manual'
  return 'Otro'
}

function patientLabel(doc) {
  const counter = String(doc?.patient?.counter || '').trim()
  const name = String(doc?.patient_full_name || doc?.patient?.name || '').trim()
  const prefix = counter ? `${counter} · ` : ''

  if (name) return `${prefix}${name}`
  if (doc?.patient_id) return `${prefix}Paciente #${doc.patient_id}`
  if (prefix) return prefix.slice(0, -3)
  return 'Paciente sin datos'
}

function goToShow(id) {
  if (!id) return
  router.push(`/invoices/${id}`)
}

function invoiceDownloadName(doc) {
  const suffix = String(doc?.counter || doc?.id || 'factura').replace(/[^a-zA-Z0-9_-]/g, '_')
  return doc?.type === 'abono'
    ? `factura-rectificativa-${suffix}.pdf`
    : `factura-${suffix}.pdf`
}

async function previewPdf(doc) {
  if (!doc?.id) return

  try {
    const res = await api.get(`/documents/${doc.id}/pdf`, { responseType: 'blob' })
    const file = new Blob([res.data], { type: 'application/pdf' })
    const fileUrl = URL.createObjectURL(file)
    window.open(fileUrl, '_blank', 'noopener,noreferrer')
    setTimeout(() => URL.revokeObjectURL(fileUrl), 60000)
  } catch (e) {
    toast.error('No se pudo abrir el PDF de la factura')
  }
}

async function downloadPdf(doc) {
  if (!doc?.id) return

  try {
    const res = await api.get(`/documents/${doc.id}/pdf`, { responseType: 'blob' })
    const file = new Blob([res.data], { type: 'application/pdf' })
    const fileUrl = URL.createObjectURL(file)
    const link = document.createElement('a')
    link.href = fileUrl
    link.download = invoiceDownloadName(doc)
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(fileUrl), 60000)
  } catch (e) {
    toast.error('No se pudo descargar el PDF de la factura')
  }
}

async function load(page = 1) {
  loading.value = true
  try {
    const res = await api.get('/documents', {
      params: {
        page,
        per_page: 15,
        q: filters.value.q || undefined,
        status: filters.value.status || undefined,
        from_date: filters.value.from_date || undefined,
        to_date: filters.value.to_date || undefined,
      },
    })

    documents.value = Array.isArray(res.data?.data) ? res.data.data : []
    meta.value = res.data?.meta ?? null
    summary.value = res.data?.summary ?? { count: 0, total_amount: 0 }
  } catch (e) {
    documents.value = []
    meta.value = null
    summary.value = { count: 0, total_amount: 0 }
    toast.error('Error cargando facturas')
  } finally {
    loading.value = false
  }
}

function debouncedReload() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 250)
}

onMounted(async () => {
  await load(1)
})
</script>

<style scoped>
.page-header { margin-bottom:14px }
.page-header { display:flex; justify-content:space-between; align-items:center; gap:12px }
.page-header h1 { margin:0; font-size:20px; font-weight:800 }
.form-sub { color:#6b7280; font-size:13px; margin-top:4px }

.filters { display:grid; grid-template-columns:1.6fr 1fr 1fr 1fr; gap:8px; margin-bottom:10px }
.filters select, .search-input { padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; width:100% }

.summary { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; color:#374151; font-size:14px }

.list { display:flex; flex-direction:column; gap:8px }
.list-header { display:grid; grid-template-columns: 1fr 1.1fr 2fr 2fr 1fr 1fr .7fr; gap:10px; color:#6b7280; font-size:13px; font-weight:600; padding:6px 10px }
.invoice-row { display:grid; grid-template-columns: 1fr 1.1fr 2fr 2fr 1fr 1fr .7fr; gap:10px; background:#fff; border:1px solid #eef2ff22; border-radius:10px; padding:10px; align-items:center; font-size:13px; cursor:pointer }
.invoice-row:hover { border-color:#dbeafe; background:#f8fbff }

.type-chip { display:inline-flex; align-items:center; gap:6px; padding:4px 8px; border-radius:9999px; background:#eff6ff; color:#1d4ed8; font-weight:600; font-size:12px }
.type-icon { width:13px; height:13px; display:block }

.pdf-btn { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:8px; border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8 }
.pdf-btn:hover { background:#dbeafe; border-color:#93c5fd }
.pdf-icon { width:14px; height:14px; display:block }
.pdf-actions { display:flex; gap:6px; align-items:center }

.patient-link { color: var(--secondary); text-decoration: none; font-weight: 600 }
.patient-link:hover { text-decoration: underline }

.status { padding:5px 8px; border-radius:9999px; font-weight:700; text-transform:capitalize; font-size:11px }
.status.issued { background:#dcfce7; color:#166534 }
.status.paid { background:#dcfce7; color:#166534 }
.status.draft { background:#fef3c7; color:#92400e }
.status.pending { background:#fef3c7; color:#92400e }
.status.partially_paid { background:#dbeafe; color:#1e40af }
.status.covered_by_pack { background:#dbeafe; color:#1e40af }
.status.cancelled { background:#f3f4f6; color:#374151 }

.empty { color:#6b7280; padding:12px }

.pagination { margin-top:12px; display:flex; justify-content:flex-end; gap:12px; align-items:center }
.pagination-info { color:#6b7280; font-size:13px }
.pagination-actions { display:flex; gap:8px }
.icon-btn { width:32px; height:32px; border-radius:8px; border:1px solid #e5e7eb; background:#fff }
.icon-btn:disabled { opacity:0.45 }

@media (max-width: 900px) {
  .filters, .list-header, .invoice-row { grid-template-columns:1fr }
}
</style>
