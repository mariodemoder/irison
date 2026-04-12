<template>
  <MainLayout>
    <div>
      <div class="page-header">
        <div>
          <h1>Notificaciones</h1>
          <div class="form-sub">Historial de emails enviados y fallidos</div>
        </div>
      </div>

      <div class="filters">
        <div class="search-wrapper">
          <input v-model="filters.q" placeholder="Buscar por paciente o email" class="search-input" @input="debouncedReload" />
        </div>
        <select v-model="filters.status" @change="load(1)">
          <option value="">Estado: todos</option>
          <option value="sent">Enviado</option>
          <option value="failed">Fallido</option>
        </select>
        <select v-model="filters.reminder_type" @change="load(1)">
          <option value="">Tipo: todos</option>
          <option value="24h">24h antes</option>
          <option value="2h">2h antes</option>
        </select>
        <input v-model="filters.from_date" type="date" class="search-input" @change="load(1)" />
        <input v-model="filters.to_date" type="date" class="search-input" @change="load(1)" />
      </div>

      <div class="summary">
        <div><strong>{{ summary.count }}</strong> notificación(es)</div>
        <div>Enviadas: <strong>{{ summary.sent_count }}</strong> · Fallidas: <strong>{{ summary.failed_count }}</strong></div>
      </div>

      <AppLoading v-if="loading" message="Cargando notificaciones..." />

      <template v-else>
        <div class="list-header">
          <div>Fecha</div>
          <div>Paciente</div>
          <div>Tipo</div>
          <div>Email</div>
          <div>Estado</div>
          <div>Error</div>
          <div></div>
        </div>

        <div class="list">
          <div
            v-for="item in reminders"
            :key="item.id"
            class="notification-row"
            role="button"
            tabindex="0"
            @click="goToShow(item.id)"
            @keydown.enter.prevent="goToShow(item.id)"
          >
            <div>{{ formatDate(item.sent_at || item.created_at) }}</div>
            <div>
              <router-link v-if="item.patient?.id" :to="`/patients/${item.patient.id}`" class="patient-link" @click.stop>
                {{ patientLabel(item) }}
              </router-link>
              <span v-else>{{ patientLabel(item) }}</span>
            </div>
            <div><span class="type-chip">{{ typeLabel(item.reminder_type) }}</span></div>
            <div>{{ item.recipient_email || '—' }}</div>
            <div><span class="status" :class="item.status">{{ statusLabel(item.status) }}</span></div>
            <div class="error-col">{{ item.error_message || '—' }}</div>
            <div class="row-action">
              <button
                type="button"
                class="action-btn details"
                @click.stop="goToShow(item.id)"
              >
                Ver detalle
              </button>
              <button
                v-if="item.status === 'failed'"
                type="button"
                class="action-btn resend"
                :disabled="resendingId === item.id"
                @click="resend(item)"
              >
                {{ resendingId === item.id ? 'Reenviando...' : 'Reenviar' }}
              </button>
            </div>
          </div>
          <EmptyIndexState v-if="reminders.length === 0 && !hasActiveFilters" />
          <div v-else-if="reminders.length === 0" class="empty">No hay resultados para los filtros aplicados.</div>
        </div>

        <div v-if="meta" class="pagination">
          <div class="pagination-info">Página {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} notificaciones</div>
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

const toast = useToast()
const router = useRouter()
const loading = ref(false)
const reminders = ref([])
const meta = ref(null)
const summary = ref({ count: 0, sent_count: 0, failed_count: 0 })
const resendingId = ref(null)
let searchTimer = null

const filters = ref({
  q: '',
  status: '',
  reminder_type: '',
  from_date: '',
  to_date: '',
})

const hasActiveFilters = computed(() => {
  return Boolean(String(filters.value.q || '').trim())
    || Boolean(filters.value.status)
    || Boolean(filters.value.reminder_type)
    || Boolean(filters.value.from_date)
    || Boolean(filters.value.to_date)
})

function typeLabel(type) {
  if (type === '24h') return '24h antes'
  if (type === '2h') return '2h antes'
  return type || '—'
}

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
    const res = await api.get('/reminders', {
      params: {
        page,
        per_page: 15,
        q: filters.value.q || undefined,
        status: filters.value.status || undefined,
        reminder_type: filters.value.reminder_type || undefined,
        from_date: filters.value.from_date || undefined,
        to_date: filters.value.to_date || undefined,
      },
    })

    reminders.value = Array.isArray(res.data?.data) ? res.data.data : []
    meta.value = res.data?.meta ?? null
    summary.value = res.data?.summary ?? { count: 0, sent_count: 0, failed_count: 0 }
  } catch (e) {
    reminders.value = []
    meta.value = null
    summary.value = { count: 0, sent_count: 0, failed_count: 0 }
    toast.error('Error cargando notificaciones')
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

async function resend(item) {
  if (!item?.id) return

  resendingId.value = item.id
  try {
    const res = await api.post(`/reminders/${item.id}/resend`)
    toast.success(res.data?.message || 'Recordatorio reenviado')
    await load(meta.value?.current_page || 1)
  } catch (e) {
    const message = e.response?.data?.message || 'No se pudo reenviar el recordatorio'
    toast.error(message)
    await load(meta.value?.current_page || 1)
  } finally {
    resendingId.value = null
  }
}

onMounted(async () => {
  await load(1)
})
</script>

<style scoped>

.filters { display:grid; grid-template-columns:1.8fr 1fr 1fr 1fr 1fr; gap:8px; margin-bottom:10px }
.filters select, .search-input { padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; width:100% }

.summary { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; color:#374151; font-size:14px }

.list { display:flex; flex-direction:column; gap:8px }
.list-header { display:grid; grid-template-columns:1.1fr 1.6fr .9fr 1.5fr .9fr 2fr 120px; gap:10px; color:#6b7280; font-size:13px; font-weight:600; padding:6px 10px }
.notification-row { display:grid; grid-template-columns:1.1fr 1.6fr .9fr 1.5fr .9fr 2fr 210px; gap:10px; background:#fff; border:1px solid #eef2ff22; border-radius:10px; padding:10px; align-items:center; font-size:13px; cursor:pointer }
.notification-row:hover { border-color:#dbeafe; background:#f8fbff }

.patient-link { color: var(--secondary); text-decoration:none; font-weight:600 }
.patient-link:hover { text-decoration:underline }

.type-chip { display:inline-flex; align-items:center; padding:4px 8px; border-radius:9999px; background:#eff6ff; color:#1d4ed8; font-size:12px; font-weight:600 }
.status { display:inline-flex; align-items:center; padding:5px 8px; border-radius:9999px; font-weight:700; font-size:11px }
.status.sent { background:#dcfce7; color:#166534 }
.status.failed { background:#fee2e2; color:#991b1b }
.error-col { color:#6b7280; word-break:break-word }
.row-action { display:flex; align-items:center; justify-content:flex-start }
.action-btn { display:inline-flex; align-items:center; justify-content:center; padding:6px 10px; border-radius:8px; border:1px solid transparent; font-size:13px }
.action-btn.details { background:#fff; color:#374151; border-color:#e5e7eb }
.action-btn.resend { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe }
.action-btn:disabled { opacity:.55 }
.empty { color:#6b7280; padding:12px }

.pagination { margin-top:12px; display:flex; justify-content:flex-end; gap:12px; align-items:center }
.pagination-info { color:#6b7280; font-size:13px }
.pagination-actions { display:flex; gap:8px }
.icon-btn { width:32px; height:32px; border-radius:8px; border:1px solid #e5e7eb; background:#fff }

@media (max-width: 900px) {
  .filters, .list-header, .notification-row { grid-template-columns:1fr }
}
</style>
