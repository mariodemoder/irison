<template>
  <MainLayout>
    <div>
      <div class="page-header">
        <div>
          <h1>Pagos de clientes</h1>
          <div class="form-sub">Listado y búsqueda de pagos</div>
        </div>
        <div>
          <router-link to="/payments/create" class="primary">Nuevo pago</router-link>
        </div>
      </div>

      <div class="filters">
        <div class="search-wrapper">
          <input v-model="filters.q" placeholder="Buscar por paciente o NIF" class="search-input" @input="debouncedReload" />
        </div>
        <select v-model="filters.status" @change="load(1)">
          <option value="">Estado: todos</option>
          <option value="completed">Completado</option>
          <option value="pending">Pendiente</option>
          <option value="refunded">Reembolsado</option>
        </select>
        <select v-model="filters.method" @change="load(1)">
          <option value="">Método: todos</option>
          <option value="cash">Efectivo</option>
          <option value="card">Tarjeta</option>
          <option value="transfer">Transferencia</option>
        </select>
        <select v-model="filters.concept" @change="load(1)">
          <option value="">Concepto: todos</option>
          <option value="appointment">Cita individual</option>
          <option value="package">Compra de bono</option>
          <option value="credit">Adelanto</option>
        </select>
      </div>

      <div class="summary">
        <div><strong>{{ summary.count }}</strong> pago(s)</div>
        <div>Total: <strong>{{ formatCurrency(summary.total_amount) }}</strong></div>
      </div>

      <div class="list-header">
        <div>Fecha</div>
        <div>Paciente</div>
        <div>Importe</div>
        <div>Concepto</div>
        <div>Método</div>
        <div>Estado</div>
        <div></div>
      </div>

      <div class="list">
        <div v-for="pay in payments" :key="pay.id" class="payment-row">
          <div>{{ formatDate(pay.created_at) }}</div>
          <div>{{ pay.patient?.name ?? `Paciente #${pay.patient_id}` }}</div>
          <div>{{ formatCurrency(pay.amount) }}</div>
          <div>{{ conceptLabel(pay.concept) }}</div>
          <div>{{ methodLabel(pay.method) }}</div>
          <div><span class="status" :class="pay.status">{{ statusLabel(pay.status) }}</span></div>
          <div class="row-action">
            <router-link :to="`/payments/${pay.id}/edit`" class="action-btn datos">✎ Editar</router-link>
          </div>
        </div>
        <div v-if="!loading && payments.length === 0" class="empty">Sin pagos registrados.</div>
      </div>

      <div v-if="meta" class="pagination">
        <div class="pagination-info">Página {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} pagos</div>
        <div class="pagination-actions">
          <button :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)" class="icon-btn">‹</button>
          <button :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)" class="icon-btn">›</button>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import { useToast } from 'vue-toastification'

const toast = useToast()

const loading = ref(false)
const payments = ref([])
const meta = ref(null)
const summary = ref({ count: 0, total_amount: 0 })
let searchTimer = null

const filters = ref({
  q: '',
  status: '',
  method: '',
  concept: '',
})

function formatCurrency(value) {
  const number = Number(value || 0)
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(number)
}

function formatDate(dateValue) {
  if (!dateValue) return '—'
  return new Date(dateValue).toLocaleString('es-ES')
}

function statusLabel(status) {
  if (status === 'completed') return 'Completado'
  if (status === 'pending') return 'Pendiente'
  if (status === 'refunded') return 'Reembolsado'
  return status || '—'
}

function methodLabel(method) {
  if (method === 'cash') return 'Efectivo'
  if (method === 'card') return 'Tarjeta'
  if (method === 'transfer') return 'Transferencia'
  return method || '—'
}

function conceptLabel(concept) {
  if (concept === 'appointment') return 'Cita individual'
  if (concept === 'package') return 'Compra de bono'
  if (concept === 'credit') return 'Adelanto'
  return concept || '—'
}

async function load(page = 1) {
  loading.value = true
  try {
    const res = await api.get('/payments', {
      params: {
        page,
        per_page: 15,
        q: filters.value.q || undefined,
        status: filters.value.status || undefined,
        method: filters.value.method || undefined,
        concept: filters.value.concept || undefined,
      },
    })

    payments.value = Array.isArray(res.data?.data) ? res.data.data : []
    meta.value = res.data?.meta ?? null
    summary.value = res.data?.summary ?? { count: 0, total_amount: 0 }
  } catch (e) {
    payments.value = []
    meta.value = null
    summary.value = { count: 0, total_amount: 0 }
    toast.error('Error cargando pagos')
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
.page-header h1 { margin:0; font-size:22px }
.form-sub { color:#6b7280; font-size:13px; margin-top:4px }

.primary { padding:8px 14px; border-radius:9999px; border:2px solid #3b82f6; color:#3b82f6; background:#fff; font-weight:600 }
.primary:hover { background:#eff6ff }

.filters { display:grid; grid-template-columns:1.6fr repeat(3, 1fr); gap:8px; margin-bottom:10px }
.filters select, .search-input { padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; width:100% }

.summary { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; color:#374151; font-size:14px }

.list { display:flex; flex-direction:column; gap:8px }
.list-header { display:grid; grid-template-columns: 1.3fr 2fr 1fr 1.2fr 1fr 1fr 120px; gap:10px; color:#6b7280; font-size:13px; font-weight:600; padding:6px 10px }
.payment-row { display:grid; grid-template-columns: 1.3fr 2fr 1fr 1.2fr 1fr 1fr 120px; gap:10px; background:#fff; border:1px solid #eef2ff22; border-radius:10px; padding:10px; align-items:center; font-size:13px }

.status { padding:5px 8px; border-radius:9999px; font-weight:700; text-transform:capitalize; font-size:11px }
.status.completed { background:#dcfce7; color:#166534 }
.status.pending { background:#fef3c7; color:#92400e }
.status.refunded { background:#f3f4f6; color:#374151 }

.row-action { display:flex; align-items:center; justify-content:flex-start }
.action-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:8px; text-decoration:none; color:#374151; font-size:13px; border:1px solid transparent }
.action-btn.datos { background:#fff; border-color:#e5e7eb; color:#374151 }

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
