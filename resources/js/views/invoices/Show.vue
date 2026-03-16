<template>
  <MainLayout>
    <div class="show-wrap">
      <div class="show-card">
        <div class="show-header">
          <div>
            <h1>Factura</h1>
            <p class="form-sub">Detalle de factura (solo lectura)</p>
          </div>
          <button type="button" class="muted" @click="goBack">Volver</button>
        </div>

        <div v-if="loading" class="alert-subtle">Cargando factura...</div>

        <div v-else-if="documentData" class="details-grid">
          <div class="field"><label class="label">Fecha</label><div class="value">{{ formatDateOnlyDay(documentData.date || documentData.created_at) }}</div></div>
          <div class="field"><label class="label">Número</label><div class="value">{{ documentData.counter || '—' }}</div></div>

          <div class="field full"><label class="label">Usuario emisor</label><div class="value">{{ documentData.user_full_name || '—' }}</div></div>

          <div class="field"><label class="label">NIF paciente</label><div class="value">{{ documentData.patient_nif || documentData.patient?.nif || '—' }}</div></div>
          <div class="field"><label class="label">Nombre paciente</label><div class="value">{{ documentData.patient_full_name || documentData.patient?.name || '—' }}</div></div>

          <div class="field"><label class="label">Domicilio</label><div class="value">{{ documentData.patient_address || documentData.patient?.address || '—' }}</div></div>
          <div class="field"><label class="label">Teléfono</label><div class="value">{{ documentData.patient_phone || documentData.patient?.phone || '—' }}</div></div>

          <div class="field full"><label class="label">Detalle</label><div class="value">{{ documentData.notes || '—' }}</div></div>

          <div class="field"><label class="label">Importe</label><div class="value">{{ formatCurrency(documentData.amount) }}</div></div>
          <div class="field"><label class="label">Estado de pago</label><div class="value"><span class="status" :class="documentData.status">{{ statusLabel(documentData.status) }}</span></div></div>
        </div>

        <div v-else class="alert-subtle">No se encontró la factura.</div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import { useToast } from 'vue-toastification'
import { formatDateOnlyDay } from '../../shared/dateHelpers'

const toast = useToast()
const route = useRoute()
const router = useRouter()

const loading = ref(false)
const documentData = ref(null)

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
  if (status === 'issued') return 'Pagado'
  if (status === 'draft') return 'Pendiente'
  if (status === 'cancelled') return 'Cancelado'
  return status || '—'
}

function goBack() {
  router.push('/invoices')
}

async function load() {
  loading.value = true
  try {
    const id = route.params.id
    const res = await api.get(`/documents/${id}`)
    documentData.value = res.data || null
  } catch (e) {
    documentData.value = null
    toast.error('Error cargando factura')
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
.show-card { width:100%; max-width:860px; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; box-shadow:0 10px 30px rgba(2,6,23,0.06) }
.show-header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px }
.show-header h1 { margin:0; font-size:22px }
.form-sub { color:#6b7280; font-size:13px; margin-top:4px }

.details-grid { display:grid; grid-template-columns:repeat(2, 1fr); gap:12px }
.field { display:flex; flex-direction:column }
.field.full { grid-column:1 / -1 }
.label { font-weight:600; margin-bottom:6px }
.value { padding:10px; border:1px solid #e5e7eb; border-radius:8px; background:#fff }

.status { padding:5px 8px; border-radius:9999px; font-weight:700; text-transform:capitalize; font-size:11px }
.status.issued { background:#dcfce7; color:#166534 }
.status.draft { background:#fef3c7; color:#92400e }
.status.cancelled { background:#f3f4f6; color:#374151 }

.alert-subtle { background:#f8fafc; border:1px solid #e6edf3; padding:10px; border-radius:8px; color:#334155; font-size:14px }
.muted { padding:8px 14px; border-radius:9999px; border:1px solid #e5e7eb; background:#fff }

@media (max-width: 768px) {
  .details-grid { grid-template-columns:1fr }
}
</style>
