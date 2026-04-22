<template>
  <MainLayout>
    <div>
      <div class="entity-card">
        <div class="page-header">
          <div>
            <h1>Bonos</h1>
            <div class="form-sub">Listado y búsqueda de bonos</div>
          </div>
          <div>
            <router-link to="/patients" class="btn btn-sm small">Gestionar en paciente</router-link>
          </div>
        </div>

        <div class="filters">
          <div class="search-wrapper">
            <input v-model="filters.q" placeholder="Buscar por bono, paciente o NIF" class="search-input" @input="debouncedReload" />
          </div>
          <select v-model="filters.status" @change="load(1)">
            <option value="">Estado: todos</option>
            <option value="active">Activo</option>
            <option value="last">Última sesión</option>
            <option value="exhausted">Agotado</option>
            <option value="expired">Expirado</option>
          </select>
          <select v-model="filters.payment_state" @change="load(1)">
            <option value="">Pago: todos</option>
            <option value="unpaid">Impagos</option>
            <option value="paid">Pagados</option>
          </select>
        </div>

        <div class="summary">
          <div><strong>{{ summary.count }}</strong> bono(s)</div>
          <div>Total: <strong>{{ formatCurrency(summary.total_amount) }}</strong></div>
        </div>

        <AppLoading v-if="loading" message="Cargando bonos..." />

        <template v-else>
          <EntityTable v-if="bonuses.length > 0" :columns="tableColumns" table-class="bonuses-table">
            <template #default>
              <tr v-for="bonus in bonuses" :key="bonus.id" class="entity-table-row">
                <td class="col-min">{{ bonus.counter || '—' }}</td>
                <td class="col-min">{{ formatDateOnlyDay(bonus.created_at) }}</td>
                <td class="col-mid">
                  <div class="truncate-cell">
                    <router-link v-if="bonus.patient?.id" :to="`/patients/${bonus.patient.id}`" class="patient-link truncate-text">
                      {{ bonus.patient?.counter ? `${bonus.patient.counter} · ` : '' }}{{ bonus.patient?.name ?? `Paciente #${bonus.patient_id}` }}
                    </router-link>
                    <span v-else class="truncate-text">{{ bonus.patient?.counter ? `${bonus.patient.counter} · ` : '' }}{{ bonus.patient?.name ?? `Paciente #${bonus.patient_id}` }}</span>
                  </div>
                </td>
                <td class="col-min">
                  <div class="truncate-cell">
                    <span class="truncate-text">{{ bonus.name || `Bono #${bonus.id}` }}</span>
                  </div>
                </td>
                <td class="col-min">{{ bonus.remaining_sessions }}/{{ bonus.total_sessions }}</td>
                <td class="col-min">{{ formatCurrency(bonus.price) }}</td>
                <td class="col-min">{{ bonus.expires_at ? formatDateOnlyDay(bonus.expires_at) : '—' }}</td>
                <td class="col-min"><span class="status" :class="bonus.status">{{ statusLabel(bonus.status) }}</span></td>
                <td class="col-min"><span class="status" :class="bonus.is_paid ? 'completed' : 'pending'">{{ bonus.is_paid ? 'Pagado' : 'Impago' }}</span></td>
                <td class="col-min">
                  <div v-if="bonus.invoice_id" class="pdf-actions">
                    <button
                      type="button"
                      class="pdf-btn"
                      title="Vista previa PDF"
                      @click.stop="previewPdf(bonus)"
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
                      @click.stop="downloadPdf(bonus)"
                    >
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="pdf-icon">
                        <path d="M12 4v11"></path>
                        <path d="M8.5 11.5L12 15l3.5-3.5"></path>
                        <path d="M5 19h14"></path>
                      </svg>
                    </button>
                  </div>
                  <button
                    v-else
                    type="button"
                    class="secondary"
                    :disabled="loading || invoicingId === bonus.id"
                    @click="issueBonusInvoice(bonus)"
                  >
                    {{ invoicingId === bonus.id ? 'Facturando...' : 'Facturar' }}
                  </button>
                </td>
              </tr>
            </template>
          </EntityTable>

          <EmptyIndexState v-else-if="!hasActiveFilters" />
          <div v-else class="empty">No hay resultados para los filtros aplicados.</div>

          <div v-if="meta" class="pagination">
            <div class="pagination-info">Página {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} bonos</div>
            <div class="pagination-actions">
              <button :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)" class="icon-btn">‹</button>
              <button :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)" class="icon-btn">›</button>
            </div>
          </div>
        </template>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import EmptyIndexState from '../../components/EmptyIndexState.vue'
import EntityTable from '../../components/EntityTable.vue'
import { useToast } from 'vue-toastification'
import { formatDateOnlyDay } from '../../shared/dateHelpers'

const toast = useToast()
const route = useRoute()
const router = useRouter()

const loading = ref(false)
const bonuses = ref([])
const meta = ref(null)
const summary = ref({ count: 0, total_amount: 0 })
const invoicingId = ref(null)
let searchTimer = null

const tableColumns = [
  { key: 'counter', label: 'Número', thClass: 'col-min' },
  { key: 'created_at', label: 'Fecha', thClass: 'col-min' },
  { key: 'patient', label: 'Paciente', thClass: 'col-mid' },
  { key: 'name', label: 'Bono', thClass: 'col-min' },
  { key: 'sessions', label: 'Sesiones', thClass: 'col-min' },
  { key: 'price', label: 'Precio', thClass: 'col-min' },
  { key: 'expires_at', label: 'Vencimiento', thClass: 'col-min' },
  { key: 'status', label: 'Estado', thClass: 'col-min' },
  { key: 'payment_state', label: 'Pago', thClass: 'col-min' },
  { key: 'invoice', label: 'Factura', thClass: 'col-min' },
]

const filters = ref({
  q: '',
  status: '',
  payment_state: '',
})

const hasActiveFilters = computed(() => {
  return Boolean(String(filters.value.q || '').trim())
    || Boolean(filters.value.status)
    || Boolean(filters.value.payment_state)
})

function applyQueryFilters() {
  const allowedPaymentStates = ['paid', 'unpaid']
  const allowedStatuses = ['active', 'last', 'exhausted', 'expired']

  const queryQ = String(route.query.q || '').trim()
  const queryStatus = String(route.query.status || '').trim()
  const queryPaymentState = String(route.query.payment_state || '').trim()

  filters.value.q = queryQ
  filters.value.status = allowedStatuses.includes(queryStatus) ? queryStatus : ''
  filters.value.payment_state = allowedPaymentStates.includes(queryPaymentState) ? queryPaymentState : ''
}

function formatCurrency(value) {
  const number = Number(value || 0)
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(number)
}

function statusLabel(status) {
  if (status === 'active') return 'Activo'
  if (status === 'last') return 'Última sesión'
  if (status === 'exhausted') return 'Agotado'
  if (status === 'expired') return 'Expirado'
  return status || '—'
}

function invoiceDownloadName(bonus) {
  const suffix = String(bonus?.invoice_id || bonus?.id || 'factura').replace(/[^a-zA-Z0-9_-]/g, '_')
  return `factura-${suffix}.pdf`
}

async function previewPdf(bonus) {
  const invoiceId = Number(bonus?.invoice_id || 0)
  if (!invoiceId) return

  try {
    const res = await api.get(`/documents/${invoiceId}/pdf`, { responseType: 'blob' })
    const file = new Blob([res.data], { type: 'application/pdf' })
    const fileUrl = URL.createObjectURL(file)
    window.open(fileUrl, '_blank', 'noopener,noreferrer')
    setTimeout(() => URL.revokeObjectURL(fileUrl), 60000)
  } catch (e) {
    toast.error('No se pudo abrir el PDF de la factura')
  }
}

async function downloadPdf(bonus) {
  const invoiceId = Number(bonus?.invoice_id || 0)
  if (!invoiceId) return

  try {
    const res = await api.get(`/documents/${invoiceId}/pdf`, { responseType: 'blob' })
    const file = new Blob([res.data], { type: 'application/pdf' })
    const fileUrl = URL.createObjectURL(file)
    const link = document.createElement('a')
    link.href = fileUrl
    link.download = invoiceDownloadName(bonus)
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
    const res = await api.get('/bonuses', {
      params: {
        page,
        per_page: 15,
        q: filters.value.q || undefined,
        status: filters.value.status || undefined,
        payment_state: filters.value.payment_state || undefined,
      },
    })

    bonuses.value = Array.isArray(res.data?.data) ? res.data.data : []
    meta.value = res.data?.meta ?? null
    summary.value = res.data?.summary ?? { count: 0, total_amount: 0 }
  } catch (e) {
    bonuses.value = []
    meta.value = null
    summary.value = { count: 0, total_amount: 0 }
    toast.error('Error cargando bonos')
  } finally {
    loading.value = false
  }
}

function debouncedReload() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 250)
}

async function issueBonusInvoice(bonus) {
  if (!bonus?.id || bonus?.invoice_id) return

  invoicingId.value = bonus.id

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
    toast.error(e?.response?.data?.message || 'Error emitiendo factura del bono')
  } finally {
    invoicingId.value = null
  }
}

onMounted(async () => {
  applyQueryFilters()
  await load(1)
})
</script>

<style scoped>
.filters { display:grid; grid-template-columns:1.6fr 1fr 1fr; gap:8px; margin-bottom:10px }
.filters select, .search-input { padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; width:100% }

.summary { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; color:#374151; font-size:14px }

.status { padding:5px 8px; border-radius:9999px; font-weight:700; text-transform:capitalize; font-size:11px; white-space:nowrap; display:inline-block }
.status.completed { background:#dcfce7; color:#166534 }
.status.pending { background:#fee2e2; color:#b91c1c }
.status.active { background:#dcfce7; color:#166534 }
.status.last { background:#fef3c7; color:#92400e }
.status.exhausted { background:#f3f4f6; color:#374151 }
.status.expired { background:#f3f4f6; color:#374151 }

.patient-link { color: var(--secondary); text-decoration: none; font-weight: 600 }
.patient-link:hover { text-decoration: underline }

.truncate-cell { min-width: 0; width: 100% }
.truncate-text {
  display: block;
  min-width: 0;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.invoice-link { color: var(--secondary); text-decoration: none; font-weight: 600 }
.invoice-link:hover { text-decoration: underline }

.pdf-btn { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:8px; border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8 }
.pdf-btn:hover { background:#dbeafe; border-color:#93c5fd }
.pdf-icon { width:14px; height:14px; display:block }
.pdf-actions { display:flex; gap:6px; align-items:center }

.secondary { padding:6px 10px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; font-size:12px; font-weight:600; cursor:pointer }
.secondary:disabled { opacity:0.55; cursor:not-allowed }

.empty { color:#6b7280; padding:12px }

.pagination { margin-top:12px; display:flex; justify-content:flex-end; gap:12px; align-items:center }
.pagination-info { color:#6b7280; font-size:13px }
.pagination-actions { display:flex; gap:8px }
.icon-btn { width:32px; height:32px; border-radius:8px; border:1px solid #e5e7eb; background:#fff }
.icon-btn:disabled { opacity:0.45 }

@media (max-width: 900px) {
  .filters,
  .page-header { grid-template-columns:1fr }
}
</style>
