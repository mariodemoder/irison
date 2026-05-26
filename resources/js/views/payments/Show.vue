<template>
  <MainLayout>
    <div class="show-wrap">
      <div class="show-card">
        <div class="show-header">
          <div>
            <h1>Pago</h1>
            <p class="form-sub">Detalle del pago</p>
          </div>
          <div class="header-actions">
            <router-link v-if="paymentData?.id" :to="`/payments/${paymentData.id}/edit`" class="primary">Editar</router-link>
            <button type="button" class="muted back-btn" @click="goBack">Volver</button>
          </div>
        </div>

        <AppLoading v-if="loading" message="Cargando pago..." />

        <div v-else-if="paymentData" class="details-grid">
          <div class="field"><label class="label">Numero</label><div class="value">{{ paymentData.counter || '—' }}</div></div>
          <div class="field"><label class="label">Fecha</label><div class="value">{{ formatDateOnlyDay(paymentData.paid_at || paymentData.created_at) }}</div></div>

          <div class="field full"><label class="label">Paciente</label><div class="value">{{ patientLabel }}</div></div>

          <div class="field"><label class="label">Concepto</label><div class="value">{{ conceptLabel(paymentData.concept) }}</div></div>
          <div class="field"><label class="label">Metodo</label><div class="value">{{ methodLabel(paymentData.method) }}</div></div>

          <div class="field"><label class="label">Importe</label><div class="value">{{ formatCurrency(paymentData.amount) }}</div></div>
          <div class="field"><label class="label">Estado</label><div class="value"><span class="status" :class="paymentData.status">{{ statusLabel(paymentData.status) }}</span></div></div>

          <div class="field full"><label class="label">Notas</label><div class="value">{{ paymentData.notes || '—' }}</div></div>

          <div v-if="paymentData.concept === 'credit'" class="field"><label class="label">A favor pendiente</label><div class="value">{{ formatCurrency(paymentData.credit_pending_amount) }}</div></div>
        </div>

        <div v-else class="alert-subtle">No se encontro el pago.</div>
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
import { useToast } from 'vue-toastification'
import { formatDateOnlyDay } from '../../shared/dateHelpers'
import { getLoadErrorMessage } from '../../shared/httpErrors'
import { goBackWithPriority } from '../../shared/navigationHelpers'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const loading = ref(false)
const paymentData = ref(null)

const patientLabel = computed(() => {
  const patient = paymentData.value?.patient
  if (!patient) return '—'
  const prefix = patient.counter ? `${patient.counter} · ` : ''
  return `${prefix}${patient.name || `Paciente #${patient.id}`}`
})

function formatCurrency(value) {
  const number = Number(value || 0)
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(number)
}

function conceptLabel(concept) {
  if (concept === 'appointment') return 'Cita individual'
  if (concept === 'package') return 'Compra de bono'
  if (concept === 'credit') return 'Adelanto'
  return concept || '—'
}

function methodLabel(method) {
  if (method === 'cash') return 'Efectivo'
  if (method === 'card') return 'Tarjeta'
  if (method === 'transfer') return 'Transferencia'
  return method || '—'
}

function statusLabel(status) {
  if (status === 'completed') return 'Aplicado'
  if (status === 'pending') return 'Pendiente de aplicar'
  if (status === 'refunded') return 'Reembolsado'
  return status || '—'
}

function goBack() {
  goBackWithPriority(router, {
    priorityPath: '/payments',
    fallbackPath: '/payments',
  })
}

async function load() {
  loading.value = true
  try {
    const id = route.params.id
    const res = await api.get(`/payments/${id}`)
    paymentData.value = res.data || null
  } catch (e) {
    paymentData.value = null
    toast.error(getLoadErrorMessage(e, 'pago'))
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await load()
})
</script>

<style scoped>
.show-wrap { display:flex; justify-content:center; padding:6px 0 }
.show-card { width:100%; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; box-shadow:0 10px 30px rgba(2,6,23,0.06) }
.show-header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px }
.show-header h1 { margin:0; font-size:22px }
.header-actions { display:flex; align-items:center; gap:8px }

.details-grid { display:grid; grid-template-columns:repeat(2, 1fr); gap:12px }
.field { display:flex; flex-direction:column }
.field.full { grid-column:1 / -1 }
.label { font-weight:600; margin-bottom:6px }
.value { padding:10px; border:1px solid #e5e7eb; border-radius:8px; background:#fff }

.status { padding:5px 8px; border-radius:9999px; font-weight:700; text-transform:capitalize; font-size:11px }
.status.completed { background:#dcfce7; color:#166534 }
.status.pending { background:#fef3c7; color:#92400e }
.status.refunded { background:#fee2e2; color:#b91c1c }

.alert-subtle { background:#f8fafc; border:1px solid #e6edf3; padding:10px; border-radius:8px; color:#334155; font-size:14px }

@media (max-width: 768px) {
  .details-grid { grid-template-columns:1fr }
}
</style>
