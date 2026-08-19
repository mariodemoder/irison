<template>
  <MainLayout>
    <div>
      <div class="entity-card">
        <div class="page-header">
          <div>
            <h1>Notificaciones</h1>
            <div class="form-sub">Historial de emails enviados y fallidos</div>
          </div>
          <div class="header-actions">
            <button class="help-btn" @click="showHelp = true" title="Ayuda">?</button>
          </div>
        </div>

        <div class="filters">
          <div class="search-wrapper">
            <input v-model="filters.q" placeholder="Buscar por email, asunto o paciente" class="search-input" @input="debouncedReload" />
          </div>
          <select v-model="filters.status" @change="load(1)">
            <option value="">Estado: todos</option>
            <option value="sent">Enviado</option>
            <option value="failed">Fallido</option>
          </select>
          <select v-model="filters.category" @change="load(1)">
            <option value="">Categoría: todas</option>
            <option v-for="option in categoryOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
          <label class="filter-date-field"><span>Desde</span><input v-model="filters.from_date" type="date" class="filter-date" @change="load(1)" /></label>
          <label class="filter-date-field"><span>Hasta</span><input v-model="filters.to_date" type="date" class="filter-date" @change="load(1)" /></label>
        </div>

        <div class="summary">
          <div><strong>{{ summary.count }}</strong> notificación(es)</div>
          <div>Enviadas: <strong>{{ summary.sent_count }}</strong> · Fallidas: <strong>{{ summary.failed_count }}</strong></div>
        </div>

        <AppLoading v-if="loading" message="Cargando notificaciones..." />

        <template v-else>
          <div v-if="logs.length > 0" class="entity-table-wrap">
            <table class="entity-table">
              <thead>
                <tr>
                  <th class="col-min">Fecha</th>
                  <th class="col-mid">Destinatario</th>
                  <th class="col-max">Asunto</th>
                  <th class="col-mid">Paciente</th>
                  <th class="col-min">Estado</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="item in logs"
                  :key="item.id"
                  class="entity-table-row"
                  role="button"
                  tabindex="0"
                  @click="goToShow(item.id)"
                  @keydown.enter.prevent="goToShow(item.id)"
                >
                  <td class="col-min">{{ formatDate(item.sent_at || item.created_at) }}</td>
                  <td class="col-mid">
                    <div class="to-email">{{ item.to_email || '—' }}</div>
                  </td>
                  <td class="col-max">
                    <div class="subject">{{ item.subject || '—' }}</div>
                    <span class="type-chip">{{ item.category_label || item.category }}</span>
                  </td>
                  <td class="col-mid">
                    <div class="row-name">
                      <router-link v-if="item.patient?.id" :to="`/patients/${item.patient.id}`" class="patient-link" @click.stop>
                        {{ patientLabel(item) }}
                      </router-link>
                      <span v-else>{{ patientLabel(item) }}</span>
                    </div>
                  </td>
                  <td class="col-min"><span class="status" :class="item.status">{{ statusLabel(item.status) }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
          <EmptyIndexState v-else-if="!hasActiveFilters" />
          <div v-else class="empty">No hay resultados para los filtros aplicados.</div>

          <div v-if="meta" class="pagination">
            <div class="pagination-info">Página {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} notificaciones</div>
            <div class="pagination-actions">
              <button :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)" class="icon-btn">‹</button>
              <button :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)" class="icon-btn">›</button>
            </div>
          </div>
        </template>
      </div>

      <HelpModal v-if="showHelp" @close="showHelp = false" />
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
import HelpModal from '../../components/notifications/HelpModal.vue'
import { useToast } from 'vue-toastification'
import { getLoadErrorMessage } from '../../shared/httpErrors'

const toast = useToast()
const router = useRouter()
const loading = ref(false)
const logs = ref([])
const meta = ref(null)
const summary = ref({ count: 0, sent_count: 0, failed_count: 0 })
const showHelp = ref(false)
let searchTimer = null

const categoryOptions = [
  { value: 'reminder_24h', label: 'Recordatorio 24h' },
  { value: 'reminder_2h', label: 'Recordatorio 2h' },
  { value: 'appointment_created', label: 'Nueva cita' },
  { value: 'appointment_updated', label: 'Cita modificada' },
  { value: 'appointment_cancelled', label: 'Cita cancelada' },
  { value: 'booking_confirmation', label: 'Reserva online' },
  { value: 'new_online_booking', label: 'Nueva reserva online' },
  { value: 'consent_sign_request', label: 'Firma de consentimiento' },
  { value: 'subscription_activated', label: 'Suscripción activada' },
  { value: 'checkout_link', label: 'Enlace de pago' },
  { value: 'payment_completed', label: 'Pago completado' },
  { value: 'subscription_upgraded', label: 'Suscripción actualizada' },
  { value: 'invoice_payment_failed', label: 'Pago de factura fallido' },
  { value: 'invoice_resend', label: 'Reenvío de factura' },
  { value: 'subscription_canceled_internal', label: 'Suscripción cancelada (interno)' },
  { value: 'contact', label: 'Contacto' },
  { value: 'account_activation', label: 'Activación de cuenta' },
  { value: 'trial_lifecycle', label: 'Hito de trial' },
  { value: 'password_reset', label: 'Restablecer contraseña' },
  { value: 'generic', label: 'Genérico' },
]

const filters = ref({
  q: '',
  status: '',
  category: '',
  from_date: '',
  to_date: '',
})

const hasActiveFilters = computed(() => {
  return Boolean(String(filters.value.q || '').trim())
    || Boolean(filters.value.status)
    || Boolean(filters.value.category)
    || Boolean(filters.value.from_date)
    || Boolean(filters.value.to_date)
})

function statusLabel(status) {
  if (status === 'sent') return 'Enviado'
  if (status === 'failed') return 'Fallido'
  return status || '—'
}

function formatDate(value) {
  if (!value) return '—'

  return new Intl.DateTimeFormat('es-ES', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(value))
}

function patientLabel(item) {
  const counter = String(item?.patient?.counter || '').trim()
  const name = String(item?.patient?.name || '').trim()
  const prefix = counter ? `${counter} · ` : ''

  if (name) return `${prefix}${name}`
  if (item?.appointment_id) return `${prefix}Cita #${item.appointment_id}`
  return 'Paciente sin datos'
}

async function load(page = 1) {
  loading.value = true
  try {
    const res = await api.get('/notifications', {
      params: {
        page,
        per_page: 15,
        q: filters.value.q || undefined,
        status: filters.value.status || undefined,
        category: filters.value.category || undefined,
        from_date: filters.value.from_date || undefined,
        to_date: filters.value.to_date || undefined,
      },
    })

    logs.value = Array.isArray(res.data?.data) ? res.data.data : []
    meta.value = res.data?.meta ?? null
    summary.value = res.data?.summary ?? { count: 0, sent_count: 0, failed_count: 0 }
  } catch (e) {
    logs.value = []
    meta.value = null
    summary.value = { count: 0, sent_count: 0, failed_count: 0 }
    toast.error(getLoadErrorMessage(e, 'notificaciones'))
  } finally {
    loading.value = false
  }
}

function goToShow(id) {
  if (!id) return
  router.push(`/notifications/${id}`)
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
.header-actions { display: flex; gap: 8px; align-items: center }
.help-btn { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #d1d5db; background: #fff; cursor: pointer; font-size: 16px; font-weight: 700; color: #6b7280; display: flex; align-items: center; justify-content: center; line-height: 1 }
.help-btn:hover { background: #f3f4f6; color: #374151 }

.filters { display: grid; grid-template-columns: 1.8fr 1fr 1.4fr 1fr 1fr; gap: 8px; margin-bottom: 16px }
.filters select, .search-input { padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 13px; width: 100% }

.summary { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; color: #374151; font-size: 14px }

.patient-link { color: var(--secondary); text-decoration: none; font-weight: 600 }
.patient-link:hover { text-decoration: underline }

.to-email { font-weight: 600; color: #111827 }

.subject { font-size: 13px; color: #374151; word-break: break-word; margin-bottom: 4px }
.type-chip { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 9999px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 600 }

.status { display: inline-flex; align-items: center; padding: 5px 8px; border-radius: 9999px; font-weight: 700; font-size: 11px }
.status.sent { background: #dcfce7; color: #166534 }
.status.failed { background: #fee2e2; color: #991b1b }

.empty { color: #6b7280; padding: 16px; text-align: center }

.pagination { margin-top: 16px; display: flex; justify-content: flex-end; gap: 12px; align-items: center }
.pagination-info { color: #6b7280; font-size: 13px }
.pagination-actions { display: flex; gap: 8px }
.icon-btn { width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e5e7eb; background: #fff; cursor: pointer }
.icon-btn:disabled { opacity: 0.5; cursor: not-allowed }

@media (max-width: 900px) {
  .filters { grid-template-columns: 1fr }
}
</style>
