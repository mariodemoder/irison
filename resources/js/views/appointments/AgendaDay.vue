<template>
  <MainLayout>
    <div>
      <CalendarHeader
        view="day"
        :label="dayLabel"
        @prev="prevDay"
        @next="nextDay"
        @today="goToToday"
      />

      <div class="header-secondary">
        <div class="header-secondary-left">
          <div class="mini-cal-wrapper">
            <div class="mini-cal">
              <div class="cal-day" :class="{ 'cal-dimmed': isAllMode }">{{ displayDay }}</div>
              <div class="cal-month" :class="{ 'cal-dimmed': isAllMode }">{{ displayMonthYear }}</div>
              <input id="agenda-date" name="date" type="date" v-model="date" class="mini-date" :disabled="isAllMode" aria-label="Seleccionar fecha" />
            </div>
          </div>

          <div class="scope-bar" role="group" aria-label="Ámbito de fechas">
            <button :class="['scope-btn', isAllMode && 'scope-active']" @click="setMode(true)">Ver todo</button>
          </div>
        </div>

        <router-link to="/appointments/create" class="btn btn-sm small compact header-create-btn">
          Nueva cita
        </router-link>
      </div>

        <div class="filters-row">
          <div class="search-wrapper">
            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input v-model="query" placeholder="Buscar por nombre o NIF" class="search-input" />
          </div>

          <select v-model="statusFilter" @change="applyRouteFilters">
            <option value="">Todos</option>
            <option value="scheduled">Programadas</option>
            <option value="rescheduled">Reprogramadas</option>
            <option value="completed">Completadas</option>
            <option value="canceled">Canceladas</option>
          </select>

          <select v-model="paymentFilter" @change="applyRouteFilters">
            <option value="">Todos</option>
            <option value="pending">Pendiente</option>
            <option value="partially_paid">Parcial</option>
            <option value="paid">Pagado</option>
          </select>
        </div>

        <div class="list-header">
          <div>Horario</div>
          <div class="row-left">Paciente</div>
          <div class="row-left">Notas</div>
          <div>Estado</div>
          <div>Pago</div>
          <div></div>
        </div>

        <div class="list">
          <AppLoading v-if="loading" message="Cargando citas..." />
          <template v-else v-for="item in listWithGaps" :key="item._type === 'gap' ? `gap-${item.from}` : item.id">

            <!-- Hueco libre -->
            <div v-if="item._type === 'gap'" class="gap-row" role="button" tabindex="0" @click="goToNewWithGap(item)" @keydown.enter="goToNewWithGap(item)">
              <svg class="gap-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <span class="gap-time">{{ hhmm(item.from) }} – {{ hhmm(item.to) }}</span>
              <span class="gap-dur">{{ item.duration }} min libres</span>
              <span class="gap-cta">+ Nueva cita</span>
            </div>

            <!-- Cita -->
            <div v-else class="appointment-row" role="button" tabindex="0" @click="goToAppointment(item.id)" @keydown.enter="goToAppointment(item.id)">
              <div :class="['row-col','time', timeClass(item.status)]">
                <span v-if="isAllMode" class="row-date">{{ formatDateShort(item.start_time) }} · </span>{{ formatTimeCalendar(item.start_time) }} - {{ formatTimeCalendar(item.end_time) }}
              </div>
              <div class="row-left">
                <div class="row-name">{{ item.patient?.nif ?? '—' }} - {{ item.patient?.name ?? ('Paciente #' + item.patient_id) }}</div>
              </div>
              <div class="row-col note time">{{ item.notes ?? '' }}</div>
              <div class="row-col"><span class="status" :class="item.status">{{ statusLabel(item.status) }}</span></div>
              <div class="row-col">
                <span class="payment-status" :class="paymentStatusClass(item.payment_status)">{{ paymentStatusLabel(item.payment_status) }}</span>
              </div>
              <div class="row-action">
                <router-link :to="`/appointments/${item.id}/edit`" class="action-btn datos" @click.stop>✎ Editar</router-link>
              </div>
            </div>

          </template>
          <template v-if="!loading">
            <EmptyIndexState v-if="filteredAppointments.length === 0 && !hasActiveFilters" />
            <div v-else-if="filteredAppointments.length === 0" class="empty">No hay resultados para los filtros aplicados.</div>
          </template>
        </div>

        <div v-if="isAllMode && filteredAppointments.length > 0" class="pagination">
          <div class="pagination-info">Página {{ currentPage }} / {{ totalPages }} — {{ filteredAppointments.length }} citas</div>
          <div class="pagination-actions">
            <button class="icon-btn" :disabled="currentPage <= 1" @click="prevPage">‹</button>
            <button class="icon-btn" :disabled="currentPage >= totalPages" @click="nextPage">›</button>
          </div>
        </div>
      </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import CalendarHeader from '../../components/calendar/CalendarHeader.vue'
import EmptyIndexState from '../../components/EmptyIndexState.vue'
import AppLoading from '../../components/AppLoading.vue'
import { statusLabel, timeClass, formatTimeCalendar } from '../../shared/appointmentHelpers'

const router = useRouter()
const route = useRoute()
const appointments = ref([])
const loading = ref(false)
const date = ref(new Date().toISOString().slice(0,10))
const isAllMode = computed(() => String(route.query.all || '') === '1')
const query = ref('')
const paymentFilter = ref('')
const statusFilter = ref('')
const pageSize = 10
const currentPage = ref(1)
const displayDay = computed(() => {
  const d = new Date(date.value)
  return d.getDate()
})
const displayMonthYear = computed(() => {
  const d = new Date(date.value)
  return d.toLocaleString(undefined, { month: 'short', year: 'numeric' })
})
const dayLabel = computed(() => {
  const d = new Date(`${date.value}T00:00:00`)
  return d.toLocaleDateString('es-ES', { day: 'numeric', month: 'short', year: 'numeric' })
})


function setMode(all) {
  const today = new Date().toISOString().slice(0, 10)
  if (!all) date.value = today
  router.replace({ query: { ...route.query, all: all ? '1' : undefined, date: all ? route.query.date : today } })
}

function formatDateShort(dt) {
  if (!dt) return ''
  const d = new Date(String(dt).replace(' ', 'T'))
  if (isNaN(d.getTime())) return ''
  const dd = String(d.getDate()).padStart(2, '0')
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const yyyy = d.getFullYear()
  return `${dd}/${mm}/${yyyy}`
}

async function load() {
  loading.value = true
  try {
    const params = isAllMode.value ? {} : { date: date.value }
    const res = await api.get('/appointments', { params })
    // si la API devuelve paginación cambia según sea necesario
    appointments.value = Array.isArray(res.data) ? res.data : (res.data.data || [])
  } catch (e) {
    console.error('Error cargando citas', e)
    appointments.value = []
  } finally {
    loading.value = false
  }
}

function normalizeDateInput(rawDate) {
  const value = String(rawDate || '').trim()
  return /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : null
}

function syncDateFromRoute() {
  const routeDate = normalizeDateInput(route.query.date)
  if (!routeDate) return false
  if (routeDate === date.value) return false

  date.value = routeDate
  return true
}

function normalizeStatusInput(value) {
  const allowed = ['pending', 'scheduled', 'rescheduled', 'completed', 'canceled']
  const parsed = String(value || '').trim()
  return allowed.includes(parsed) ? parsed : ''
}

function normalizePaymentInput(value) {
  const allowed = ['unpaid', 'pending', 'partially_paid', 'paid']
  const parsed = String(value || '').trim()
  return allowed.includes(parsed) ? parsed : ''
}

function syncFiltersFromRoute() {
  const currentStatus = normalizeStatusInput(route.query.status)
  const currentPayment = String(route.query.unpaid || '') === '1'
    ? 'unpaid'
    : normalizePaymentInput(route.query.payment)

  statusFilter.value = currentStatus
  paymentFilter.value = currentPayment
}

function applyRouteFilters() {
  const nextQuery = { ...route.query }

  nextQuery.status = statusFilter.value || undefined

  if (paymentFilter.value === 'unpaid') {
    nextQuery.unpaid = '1'
    nextQuery.payment = undefined
  } else {
    nextQuery.unpaid = undefined
    nextQuery.payment = paymentFilter.value || undefined
  }

  router.replace({ query: nextQuery })
}

function prevPage() {
  if (currentPage.value > 1) {
    currentPage.value -= 1
  }
}

function nextPage() {
  if (currentPage.value < totalPages.value) {
    currentPage.value += 1
  }
}

function prevDay() {
  const d = new Date(date.value)
  d.setDate(d.getDate() - 1)
  const nextDate = d.toISOString().slice(0,10)
  date.value = nextDate
  router.replace({ query: { ...route.query, all: undefined, date: nextDate } })
}

function nextDay() {
  const d = new Date(date.value)
  d.setDate(d.getDate() + 1)
  const nextDate = d.toISOString().slice(0,10)
  date.value = nextDate
  router.replace({ query: { ...route.query, all: undefined, date: nextDate } })
}

function goToToday() {
  const today = new Date().toISOString().slice(0,10)
  date.value = today
  router.replace({ query: { ...route.query, all: undefined, date: today } })
}

onMounted(() => load())

onMounted(() => {
  syncDateFromRoute()
  syncFiltersFromRoute()
})

watch(date, () => {
  load()
})

watch(() => route.query, () => {
  syncFiltersFromRoute()
  const changedDate = syncDateFromRoute()
  if (!changedDate) {
    load()
  }
})

function goToAppointment(id) {
  router.push(`/appointments/${id}`)
}

function paymentStatusLabel(status) {
  const map = {
    pending: 'Pendiente',
    partially_paid: 'Parcial',
    paid: 'Pagado',
    covered_by_pack: 'Cubierto por bono',
  }
  return map[status] || 'Pendiente'
}

function paymentStatusClass(status) {
  const map = {
    pending: 'payment-pending',
    partially_paid: 'payment-partial',
    paid: 'payment-complete',
    covered_by_pack: 'payment-complete',
  }
  return map[status] || 'payment-pending'
}

const filteredAppointments = computed(() => {
  const q = (query.value || '').toLowerCase().trim()
  const showOnlyUnpaid = paymentFilter.value === 'unpaid'
  const routeStatusFilter = statusFilter.value
  const routePaymentFilter = paymentFilter.value

  return appointments.value.filter((a) => {
    if (routeStatusFilter === 'completed' && a?.status !== 'completed') {
      return false
    }

    if (routeStatusFilter === 'canceled' && a?.status !== 'canceled') {
      return false
    }

    if (routeStatusFilter === 'pending' && ['completed', 'canceled'].includes(a?.status)) {
      return false
    }

    if (routeStatusFilter === 'scheduled' && a?.status !== 'scheduled') {
      return false
    }

    if (routeStatusFilter === 'rescheduled' && a?.status !== 'rescheduled') {
      return false
    }

    if (showOnlyUnpaid && !['pending', 'partially_paid'].includes(a?.payment_status)) {
      return false
    }

    if (routePaymentFilter === 'pending' && a?.payment_status !== 'pending') {
      return false
    }

    if (routePaymentFilter === 'partially_paid' && a?.payment_status !== 'partially_paid') {
      return false
    }

    if (routePaymentFilter === 'paid' && !['paid', 'covered_by_pack'].includes(a?.payment_status)) {
      return false
    }

    if (!q) return true

    const name = a.patient?.name ?? ''
    const nif = a.patient?.nif ?? ''
    return [name, nif].some((f) => f && String(f).toLowerCase().includes(q))
  })
})

const hasActiveFilters = computed(() => {
  return Boolean(String(query.value || '').trim())
    || Boolean(statusFilter.value)
    || Boolean(paymentFilter.value)
})

const totalPages = computed(() => {
  if (!isAllMode.value) return 1
  const total = filteredAppointments.value.length
  return Math.max(1, Math.ceil(total / pageSize))
})

const paginatedAppointments = computed(() => {
  if (!isAllMode.value) return filteredAppointments.value
  const start = (currentPage.value - 1) * pageSize
  return filteredAppointments.value.slice(start, start + pageSize)
})

// statusLabel and timeClass moved to shared/appointmentHelpers

function parseMin(dtStr) {
  const m = String(dtStr || '').match(/[ T](\d{2}):(\d{2})/)
  return m ? Number(m[1]) * 60 + Number(m[2]) : 0
}

function hhmm(totalMin) {
  return `${String(Math.floor(totalMin / 60)).padStart(2, '0')}:${String(totalMin % 60).padStart(2, '0')}`
}

function goToNewWithGap(item) {
  const pad = n => String(n).padStart(2, '0')
  const toISO = min => `${date.value}T${pad(Math.floor(min / 60))}:${pad(min % 60)}`
  router.push({ path: '/appointments/create', query: { start: toISO(item.from), end: toISO(item.to) } })
}

const listWithGaps = computed(() => {
  if (isAllMode.value) {
    return paginatedAppointments.value.map(a => ({ _type: 'appt', ...a }))
  }

  const sorted = [...filteredAppointments.value].sort((a, b) => parseMin(a.start_time) - parseMin(b.start_time))
  const result = []
  let lastEnd = null

  for (const a of sorted) {
    const sm = parseMin(a.start_time)
    const em = parseMin(a.end_time)

    if (lastEnd !== null && a.status !== 'canceled') {
      const gap = sm - lastEnd
      if (gap >= 15) {
        result.push({ _type: 'gap', from: lastEnd, to: sm, duration: gap })
      }
    }

    result.push({ _type: 'appt', ...a })

    if (a.status !== 'canceled') {
      lastEnd = lastEnd === null ? em : Math.max(lastEnd, em)
    }
  }

  return result
})

watch([query, paymentFilter, statusFilter, isAllMode], () => {
  currentPage.value = 1
})

watch(totalPages, (pages) => {
  if (currentPage.value > pages) {
    currentPage.value = pages
  }
})
</script>

.style-reset { }
<style scoped>
*, ::before, ::after { box-sizing: border-box; border-width: 0; border-style: solid; border-color: #e5e7eb }

.header-secondary { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; flex-wrap:wrap }
.header-secondary-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap }

.form-sub { color:#6b7280; font-size:13px; margin-top:4px }
.calendar-card { display:flex; align-items:center; gap:12px; background:#fff; padding:10px; border-radius:10px; border:1px solid #eef2ff22 }
.cal-left, .cal-right { width:36px }
.cal-center { display:flex; flex-direction:column; align-items:center }
.cal-day { font-weight:800; font-size:22px }
.cal-month { color:#6b7280; font-size:13px }
.date-input { display:none }

.mini-cal { display:flex; flex-direction:row; align-items:center; gap:12px; padding:8px 12px; background:#fff; border-radius:10px; border:1px solid #eef2ff22; width:230px; box-shadow: 0 4px 10px rgba(2,6,23,0.03) }
.mini-cal-wrapper { display:flex; align-items:center; gap:10px }
.mini-cal .cal-day { font-size:20px; font-weight:800 }
.mini-cal .cal-month { font-size:13px; color:#6b7280 }
.mini-cal .cal-meta { display:flex; flex-direction:column; line-height:1 }
.mini-date { border:1px solid #e5e7eb; border-radius:8px; padding:6px; font-size:13px; margin-left:auto; appearance: auto; -webkit-appearance: textfield; -moz-appearance: textfield; cursor: pointer }
.mini-date::-webkit-calendar-picker-indicator { display: block; cursor: pointer }

@media (max-width: 900px) {
  .mini-cal { width:100%; max-width:240px }
}

.filters-row {
  display: grid;
  grid-template-columns: 1.6fr 1fr 1fr;
  gap: 8px;
  margin-bottom: 12px;
  align-items: center;
}

.filters-row select {
  padding: 8px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 13px;
  background: #fff;
}

/* Ensure mini calendar sits above the search area to avoid being covered */
.mini-cal-wrapper { position: relative; z-index: 20 }
.search-center, .search-wrapper { position: relative; z-index: 0 }

.list { display:flex; flex-direction:column; gap:8px; overflow-x:auto }
.list-header { display:grid; grid-template-columns: 140px 1.3fr 2fr 130px 160px 120px; gap:12px; align-items:center; padding:8px 14px; color:#6b7280; font-weight:600; font-size:13px }
.appointment-row { display:grid; grid-template-columns: 140px 1.3fr 2fr 130px 160px 120px; gap:12px; align-items:center; background:#fff; padding:12px 14px; border-radius:10px; text-decoration:none; color:inherit; border:1px solid #eef2ff22; min-width:820px }
.appointment-row:hover { box-shadow: 0 10px 24px rgba(2,6,23,0.06); transform: translateY(-2px) }
.row-left { display:flex; flex-direction:column }
.row-name { font-weight:600; font-size:15px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis }
.row-sub { color:#6b7280; font-size:13px }
.row-col { color:#374151; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis }
.time { white-space: nowrap; overflow: hidden; text-overflow: ellipsis }
.time-scheduled { background:#eef2ff; color:#1e3a8a; padding:4px 8px; border-radius:8px; display:inline-block }
.time-completed { background:#dcfce7; color:#166534; padding:4px 8px; border-radius:8px; display:inline-block }
.time-canceled { background:#fff4f4; color:#da7a7a; padding:4px 8px; border-radius:8px; display:inline-block }
 .time-rescheduled { background:#fff7ed; color:#b45309; padding:4px 8px; border-radius:8px; display:inline-block }
.note { font-style: italic; display:block; text-align:left;
  white-space: normal;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical; }
.row-action { display:flex; align-items:center; justify-content:flex-start; color:#6b7280 }

.status { padding:6px 10px; border-radius:9999px; font-weight:700; text-transform:capitalize }
.status.canceled { background:#fff4f4; color:#da7a7a }
.status.scheduled { background:#eef2ff; color:#1e3a8a }
.status.completed { background:#dcfce7; color:#166534 }
 .status.rescheduled { background:#fff7ed; color:#b45309 }

.payment-status { padding:6px 10px; border-radius:9999px; font-weight:700; font-size:12px }
.payment-pending { background:#fee2e2; color:#b91c1c }
.payment-partial { background:#fef3c7; color:#92400e }
.payment-complete { background:#dcfce7; color:#166534 }

.empty { color:#6b7280; padding:12px }

/* Fila de hueco libre */
.gap-row { display:flex; align-items:center; gap:8px; padding:7px 14px; border-radius:8px; background:#f0fdf4; border:1px dashed #86efac; color:#166534; font-size:13px; cursor:pointer; transition:background .12s }
.gap-row:hover { background:#dcfce7; border-color:#4ade80 }
.gap-icon { width:15px; height:15px; flex-shrink:0; opacity:.7 }
.gap-time { font-weight:700; white-space:nowrap }
.gap-dur { color:#16a34a; font-size:12px; margin-left:2px }
.gap-cta { margin-left:auto; font-size:12px; font-weight:700; color:#15803d; padding:2px 10px; border:1px solid #86efac; border-radius:6px; white-space:nowrap }

.action-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:8px; text-decoration:none; color:#374151; font-size:13px; border:1px solid transparent }
.action-btn.datos { background:#fff; border-color:#e5e7eb; color:#374151 }

/* Botón "Nueva cita" solo para vista día */
.btn { display:inline-flex; align-items:center; justify-content:center; text-decoration:none; cursor:pointer }
.btn.btn-sm { padding:6px 12px; font-size:13px; border-radius:9999px; border:2px solid #3b82f6; color:#3b82f6; background:#ffffff; font-weight:600; width:auto; box-shadow:none; margin-top:0; transition:background .15s, color .15s, border-color .15s }
.btn.btn-sm.small,
.btn.btn-sm.small.compact { padding:6px 10px; font-size:13px }
.btn.btn-sm:hover { background:#eff6ff; border-color:#2563eb; color:#2563eb }
.header-create-btn { margin-left:auto }

/* Toggle Hoy / Semana — en cabecera, no debajo */
.scope-bar { display:flex; gap:0; border:1px solid #e5e7eb; border-radius:9px; overflow:hidden; width:fit-content }
.scope-btn { padding:6px 16px; font-size:13px; font-weight:600; color:#6b7280; background:#fff; border:none; cursor:pointer; transition:background .12s, color .12s }
.scope-btn:not(:last-child) { border-right:1px solid #e5e7eb }
.scope-btn:hover:not(.scope-active) { background:#f1f5f9 }
.scope-active { background:#4f46e5; color:#fff }

/* Mini-cal deshabilitado en modo Ver Todo */
.cal-dimmed { opacity:.4 }
.mini-date:disabled { opacity:.4; cursor:default }

/* Fecha corta en modo Ver Todo */
.row-date { display:inline; font-size:13px; font-weight:600; color:#6b7280 }

.pagination { margin-top:12px; display:flex; justify-content:flex-end; gap:12px; align-items:center }
.pagination-info { color:#6b7280; font-size:13px }
.pagination-actions { display:flex; gap:8px }
.icon-btn { width:32px; height:32px; border-radius:8px; border:1px solid #e5e7eb; background:#fff }
.icon-btn:disabled { opacity:0.45; cursor:not-allowed }

@media (max-width: 900px) {
  .header-secondary { justify-content:flex-start }

  .filters-row {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .appointment-row { grid-template-columns: 140px 2fr 220px 130px 160px auto; gap:8px }
  .row-action { justify-content:flex-start }
}
.list-header > div,
.appointment-row > div {
  text-align: left;
}
</style>
