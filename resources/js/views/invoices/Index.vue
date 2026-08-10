<template>
  <MainLayout>
    <div>
      <div class="entity-card">
        <div class="page-header">
          <div>
            <h1>Facturación</h1>
            <div class="form-sub">Listado de facturas</div>
          </div>
          <div>
            <NewButton label="Nueva Factura" to="/invoices/create" />
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
          <label class="filter-date-field"><span>Desde</span><input v-model="filters.from_date" type="date" class="filter-date" @change="load(1)" /></label>
          <label class="filter-date-field"><span>Hasta</span><input v-model="filters.to_date" type="date" class="filter-date" @change="load(1)" /></label>
        </div>

        <div class="summary">
          <div><strong>{{ summary.count }}</strong> factura(s)</div>
          <div>Total: <strong>{{ formatCurrency(summary.total_amount) }}</strong></div>
        </div>

        <AppLoading v-if="loading" message="Cargando facturas..." />

        <template v-else>
          <EntityTable v-if="documents.length > 0" :columns="tableColumns" table-class="invoices-table">
            <template #default>
              <tr
                v-for="doc in documents"
                :key="doc.id"
                class="entity-table-row"
                role="button"
                tabindex="0"
                @click="goToShow(doc.id)"
                @keydown.enter.prevent="goToShow(doc.id)"
              >
                <td class="col-min">{{ doc.counter }}</td>
                <td class="col-min">{{ formatDateOnlyDay(doc.date || doc.created_at) }}</td>
                <td class="col-mid">
                  <router-link
                    v-if="doc.patient?.id"
                    :to="`/patients/${doc.patient.id}`"
                    class="patient-link"
                    @click.stop
                  >
                    {{ patientLabel(doc) }}
                  </router-link>
                  <span v-else>{{ patientLabel(doc) }}</span>
                </td>
                <td class="col-min">
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
                </td>
                <td class="col-min">{{ formatCurrency(doc.amount) }}</td>
                <td class="col-min"><span class="status" :class="paymentStatusClass(doc)">{{ statusLabel(paymentStatusValue(doc)) }}</span></td>
              </tr>
            </template>
          </EntityTable>

          <EmptyIndexState v-else-if="!hasActiveFilters" />
          <div v-else class="empty">No hay resultados para los filtros aplicados.</div>

          <div v-if="meta" class="pagination">
            <div class="pagination-info">Página {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} facturas</div>
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
import { useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import EmptyIndexState from '../../components/EmptyIndexState.vue'
import EntityTable from '../../components/EntityTable.vue'
import { useToast } from 'vue-toastification'
import { formatDateOnlyDay } from '../../shared/dateHelpers'
import { getLoadErrorMessage } from '../../shared/httpErrors'

const toast = useToast()
const router = useRouter()

const loading = ref(false)
const documents = ref([])
const meta = ref(null)
const summary = ref({ count: 0, total_amount: 0 })
let searchTimer = null

const tableColumns = [
  { key: 'counter', label: 'Número', thClass: 'col-min' },
  { key: 'date', label: 'Fecha', thClass: 'col-min' },
  { key: 'patient', label: 'Paciente', thClass: 'col-mid' },
  { key: 'type', label: 'Tipo', thClass: 'col-min' },
  { key: 'amount', label: 'Importe', thClass: 'col-min' },
  { key: 'payment_status', label: 'Estado de pago', thClass: 'col-min' },
]

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

  if (type === 'abono' && typeinvoice === 'appointment') return 'Cita'
  if (type === 'abono' && typeinvoice === 'package') return 'Bono'
  if (type === 'abono' && typeinvoice === 'credit') return 'Adelanto'
  if (type === 'abono' && typeinvoice === 'manual') return 'Manual'
  if (type === 'abono' && typeinvoice === 'varios') return 'Varios'
  if (typeinvoice === 'appointment') return 'Cita'
  if (typeinvoice === 'package') return 'Bono'
  if (typeinvoice === 'credit') return 'Adelanto'
  if (typeinvoice === 'manual') return 'Manual'
  if (typeinvoice === 'varios') return 'Varios'
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
    toast.error(getLoadErrorMessage(e, 'facturas'))
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

.filters { display:grid; grid-template-columns:1.6fr 1fr 1fr 1fr; gap:8px; margin-bottom:10px }
.filters select, .search-input { padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; width:100% }

.summary { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; color:#374151; font-size:14px }

.type-chip { display:inline-flex; align-items:center; gap:6px; padding:4px 8px; border-radius:9999px; background:#eff6ff; color:#1d4ed8; font-weight:600; font-size:12px }
.type-icon { width:13px; height:13px; display:block }

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
  .filters,
  .page-header { grid-template-columns:1fr }
}
</style>
