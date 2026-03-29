<template>
  <MainLayout>
    <div>
      <div class="page-header">
        <div>
          <h1>Bonos</h1>
          <div class="form-sub">Listado y búsqueda de bonos</div>
        </div>
        <div>
          <router-link to="/patients" class="primary">Gestionar en paciente</router-link>
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
        <div class="list-header">
          <div>Fecha</div>
          <div>Número</div>
          <div>Paciente</div>
          <div>Bono</div>
          <div>Sesiones</div>
          <div>Precio</div>
          <div>Estado</div>
          <div>Pago</div>
          <div>Factura</div>
        </div>

        <div class="list">
          <div v-for="bonus in bonuses" :key="bonus.id" class="payment-row">
            <div>{{ formatDateOnlyDay(bonus.created_at) }}</div>
            <div>{{ bonus.counter || '—' }}</div>
            <div>
              <router-link v-if="bonus.patient?.id" :to="`/patients/${bonus.patient.id}`" class="patient-link">
                {{ bonus.patient?.name ?? `Paciente #${bonus.patient_id}` }}
              </router-link>
              <span v-else>{{ bonus.patient?.name ?? `Paciente #${bonus.patient_id}` }}</span>
            </div>
            <div>{{ bonus.name || `Bono #${bonus.id}` }}</div>
            <div>{{ bonus.remaining_sessions }}/{{ bonus.total_sessions }}</div>
            <div>{{ formatCurrency(bonus.price) }}</div>
            <div><span class="status" :class="bonus.status">{{ statusLabel(bonus.status) }}</span></div>
            <div><span class="status" :class="bonus.is_paid ? 'completed' : 'pending'">{{ bonus.is_paid ? 'Pagado' : 'Impago' }}</span></div>
            <div>
              <router-link
                v-if="bonus.invoice_id"
                :to="`/invoices/${bonus.invoice_id}`"
                class="invoice-link"
              >
                Ver factura
              </router-link>
              <button
                v-else
                type="button"
                class="secondary"
                :disabled="loading || invoicingId === bonus.id"
                @click="issueBonusInvoice(bonus)"
              >
                {{ invoicingId === bonus.id ? 'Facturando...' : 'Facturar' }}
              </button>
            </div>
          </div>
          <EmptyIndexState v-if="bonuses.length === 0 && !hasActiveFilters" />
          <div v-else-if="bonuses.length === 0" class="empty">No hay resultados para los filtros aplicados.</div>
        </div>

        <div v-if="meta" class="pagination">
          <div class="pagination-info">Página {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} bonos</div>
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
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import EmptyIndexState from '../../components/EmptyIndexState.vue'
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
.page-header { margin-bottom:14px }
.page-header { display:flex; justify-content:space-between; align-items:center; gap:12px }
.page-header h1 { margin:0; font-size:20px; font-weight:800 }
.form-sub { color:#6b7280; font-size:13px; margin-top:4px }

.primary { padding:8px 14px; border-radius:9999px; border:2px solid #3b82f6; color:#3b82f6; background:#fff; font-weight:600 }
.primary:hover { background:#eff6ff }

.filters { display:grid; grid-template-columns:1.6fr 1fr 1fr; gap:8px; margin-bottom:10px }
.filters select, .search-input { padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; width:100% }

.summary { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; color:#374151; font-size:14px }

.list { display:flex; flex-direction:column; gap:8px }
.list-header { display:grid; grid-template-columns: 1.2fr 1.1fr 1.8fr 1.4fr 1fr 1fr 1.1fr 1fr 1.2fr; gap:10px; color:#6b7280; font-size:13px; font-weight:600; padding:6px 10px }
.payment-row { display:grid; grid-template-columns: 1.2fr 1.1fr 1.8fr 1.4fr 1fr 1fr 1.1fr 1fr 1.2fr; gap:10px; background:#fff; border:1px solid #eef2ff22; border-radius:10px; padding:10px; align-items:center; font-size:13px }

.status { padding:5px 8px; border-radius:9999px; font-weight:700; text-transform:capitalize; font-size:11px; white-space:nowrap; display:inline-block }
.status.completed { background:#dcfce7; color:#166534 }
.status.pending { background:#fee2e2; color:#b91c1c }
.status.active { background:#dcfce7; color:#166534 }
.status.last { background:#fef3c7; color:#92400e }
.status.exhausted { background:#f3f4f6; color:#374151 }
.status.expired { background:#f3f4f6; color:#374151 }

.patient-link { color: var(--secondary); text-decoration: none; font-weight: 600 }
.patient-link:hover { text-decoration: underline }

.invoice-link { color: var(--secondary); text-decoration: none; font-weight: 600 }
.invoice-link:hover { text-decoration: underline }

.secondary { padding:6px 10px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; font-size:12px; font-weight:600; cursor:pointer }
.secondary:disabled { opacity:0.55; cursor:not-allowed }

.empty { color:#6b7280; padding:12px }

.pagination { margin-top:12px; display:flex; justify-content:flex-end; gap:12px; align-items:center }
.pagination-info { color:#6b7280; font-size:13px }
.pagination-actions { display:flex; gap:8px }
.icon-btn { width:32px; height:32px; border-radius:8px; border:1px solid #e5e7eb; background:#fff }
.icon-btn:disabled { opacity:0.45 }

@media (max-width: 900px) {
  .filters, .list-header, .payment-row { grid-template-columns:1fr }
}
</style>
