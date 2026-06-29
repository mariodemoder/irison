<template>
  <MainLayout>
    <div>
      <div class="entity-card">
        <div class="page-header agenda-page-header">
        <div>
          <h1>Agenda</h1>
          <div class="form-sub">Visualiza y gestiona tus citas</div>
        </div>

      </div>

      <CalendarHeader
        view="day"
        :label="dayLabel"
        :active-today="isTodayMode"
        @prev="prevDay"
        @next="nextDay"
        @today="goToToday"
      />
      <div class="agenda-card">
        <div class="header-secondary">
        <div class="header-secondary-left">
          <div class="mini-cal-wrapper">
            <div class="mini-cal">
              <div class="cal-day" :class="{ 'cal-dimmed': isAllMode, 'cal-closed': isSelectedDateClosed }">{{ displayDay }}</div>
              <div class="cal-month" :class="{ 'cal-dimmed': isAllMode, 'cal-closed': isSelectedDateClosed }">{{ displayMonthYear }}</div>
              <input id="agenda-date" name="date" type="date" v-model="date" class="mini-date" :disabled="isAllMode" aria-label="Seleccionar fecha" />
            </div>
          </div>

          <div class="scope-bar" role="group" aria-label="Ámbito de fechas">
            <button :class="['scope-btn', isTodayMode && 'scope-active']" @click="setMode(false)">Hoy</button>
            <button :class="['scope-btn', isAllMode && 'scope-active']" @click="setMode(true)">Todos los días</button>
          </div>

          <div class="scope-bar" role="group" aria-label="Ámbito de citas">
            <button :class="['scope-btn', showScheduledOnly && 'scope-active']" @click="setAppointmentScope('scheduled')">Citas programadas</button>
            <button :class="['scope-btn', !showScheduledOnly && 'scope-active']" @click="setAppointmentScope('all')">Todas las citas</button>
          </div>

          <div v-if="!isProfessional && agendaProfessionals.length > 0" class="scope-bar">
            <select v-model="professionalFilter" class="professional-select" @change="onProfessionalChange">
              <option value="">Todos los profesionales</option>
              <option v-for="prof in agendaProfessionals" :key="prof.id" :value="String(prof.id)">
                {{ prof.name }}
              </option>
            </select>
          </div>

          <button
            type="button"
            :class="['filter-trigger', detailedFiltersCount > 0 && 'filter-trigger-active']"
            @click="openDetailedFilters"
            aria-label="Abrir filtros detallados"
            title="Filtros Detallados"
            >
            <svg class="filter-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M3 5h18"></path>
              <path d="M6 12h12"></path>
              <path d="M10 19h4"></path>
            </svg>
            <span v-if="detailedFiltersCount > 0" class="filter-badge">{{ detailedFiltersCount }}</span>
          </button>

          <div v-if="appliedDetailedFilterTags.length" class="applied-filter-tags" aria-label="Filtros aplicados">
            <span v-for="tag in appliedDetailedFilterTags" :key="tag.key" class="applied-filter-tag">
              <span>{{ tag.label }}</span>
              <button
                type="button"
                class="applied-filter-tag-remove"
                @click.stop="removeAppliedFilterTag(tag.key)"
                :aria-label="`Quitar filtro ${tag.label}`"
                title="Quitar filtro"
              >
                ×
              </button>
            </span>
          </div>
        </div>

        <div class="header-actions-right">
          <button
            v-if="!isProfessional"
            type="button"
            class="btn btn-sm small compact header-create-btn"
            :disabled="(isSelectedDateClosed && !isAllMode) || !canCreateAppointment"
            :title="canCreateAppointment ? 'Nueva cita' : 'Activa tu suscripción para crear citas'"
            @click.prevent="createAppointmentFromHeader"
          >
            Nueva cita
          </button>
        </div>
      </div>

        <div v-if="isSelectedDateClosed" class="closed-day-alert">
          Día marcado como cerrado. No se mostrarán huecos disponibles ni se permitirán nuevas citas desde esta vista.
        </div>

        <div class="filters-row">
          <div class="search-wrapper">
            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input v-model="query" placeholder="Buscar por Paciente o Notas" class="search-input" />
          </div>
        </div>

        <div v-if="detailedFiltersOpen" class="filters-modal-backdrop" @click.self="closeDetailedFilters">
          <div class="filters-modal" role="dialog" aria-modal="true" aria-label="Filtros detallados">
            <div class="filters-modal-head">
              <h3>Filtros detallados</h3>
              <button type="button" class="filters-modal-close" @click="closeDetailedFilters" aria-label="Cerrar filtros">✕</button>
            </div>

            <div class="filters-modal-body">
              <label class="filters-field">
                <span>Estado</span>
                <select v-model="draftStatusFilter">
                  <option value="">Todos los estados</option>
                  <option value="scheduled">Programadas</option>
                  <option value="rescheduled">Reprogramadas</option>
                  <option value="completed">Completadas</option>
                  <option value="canceled">Canceladas</option>
                </select>
              </label>

              <label class="filters-field">
                <span>Pago</span>
                <select v-model="draftPaymentFilter">
                  <option value="">Todos los pagos</option>
                  <option value="pending">Pendiente</option>
                  <option value="partially_paid">Parcial</option>
                  <option value="paid">Pagado</option>
                </select>
              </label>
            </div>

            <div class="filters-modal-actions">
              <button type="button" class="filters-secondary" @click="clearDetailedFilters">Limpiar</button>
              <button type="button" class="filters-primary" @click="applyDetailedFilters">Aplicar</button>
            </div>
          </div>
        </div>

        <div class="list-wrapper">
          <table class="agenda-table">
            <thead>
              <tr>
                <th>Horario</th>
                <th>Paciente</th>
                <th>Profesional</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Pago</th>
                <th v-if="!isProfessional" class="action-col"></th>
              </tr>
            </thead>
            <tbody>
              <AppLoading v-if="loading" message="Cargando citas..." />
              <template v-else v-for="item in listWithGaps" :key="item._type === 'gap' ? `gap-${item.from}` : item.id">

                <!-- Hueco libre -->
                <tr v-if="item._type === 'gap' && !isProfessional" class="gap-tr">
                  <td colspan="7">
                    <div class="gap-row" role="button" tabindex="0" @click="goToNewWithGap(item)" @keydown.enter="goToNewWithGap(item)">
                      <svg class="gap-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                      <span class="gap-time">{{ hhmm(item.from) }} – {{ hhmm(item.to) }}</span>
                      <span class="gap-dur">{{ item.duration }} min libres</span>
                      <span class="gap-cta">+ Nueva cita</span>
                    </div>
                  </td>
                </tr>

                <!-- Cita -->
                <tr v-else class="appointment-row" role="button" tabindex="0" @click="goToAppointment(item.id)" @keydown.enter="goToAppointment(item.id)">
                  <td class="time">
                    <span :class="timeClass(item.status)">
                      <span v-if="isAllMode" class="row-date">{{ formatDateShort(item.start_time) }} · </span>{{ formatTimeCalendar(item.start_time) }} - {{ formatTimeCalendar(item.end_time) }}
                    </span>
                  </td>
                  <td>
                    <div class="row-name">{{ item.patient?.counter ? (`${item.patient.counter} · `) : '' }}{{ item.patient?.nif ?? '—' }} - {{ item.patient?.name ?? ('Paciente #' + item.patient_id) }}</div>
                  </td>
                  <td class="time">{{ item.professional?.name || clinicOwnerName }}</td>
                  <td><span class="type-badge" :style="appointmentTypeStyle(item)">{{ item.appointment_type?.description || item.custom_type || '—' }}</span></td>
                  <td><span class="status" :class="item.status">{{ statusLabel(item.status) }}</span></td>
                  <td>
                    <span class="payment-status" :class="paymentStatusClass(item.payment_status)">{{ paymentStatusLabel(item.payment_status) }}</span>
                  </td>
                  <td v-if="!isProfessional" class="row-action">
                    <router-link :to="`/appointments/${item.id}/edit`" class="action-btn datos" @click.stop>✎ Editar</router-link>
                  </td>
                </tr>

              </template>
              <tr v-if="!loading">
                <td colspan="7">
                  <EmptyIndexState
                    v-if="filteredAppointments.length === 0 && !hasActiveFilters"
                    :title="emptyStateTitle"
                    :subtitle="emptyStateSubtitle"
                  />
                  <div v-else-if="filteredAppointments.length === 0" class="empty">No hay resultados para los filtros aplicados.</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="isAllMode && filteredAppointments.length > 0" class="pagination">
          <div class="pagination-info">Página {{ currentPage }} / {{ totalPages }} — {{ filteredAppointments.length }} citas</div>
          <div class="pagination-actions">
            <button class="icon-btn" :disabled="currentPage <= 1" @click="prevPage">‹</button>
            <button class="icon-btn" :disabled="currentPage >= totalPages" @click="nextPage">›</button>
          </div>
        </div>
      </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import CalendarHeader from '../../components/calendar/CalendarHeader.vue'
import EmptyIndexState from '../../components/EmptyIndexState.vue'
import AppLoading from '../../components/AppLoading.vue'
import { statusLabel, timeClass, formatTimeCalendar, getContrastColor } from '../../shared/appointmentHelpers'
import { isDateClosed, normalizeClosedDays } from '../../shared/clinicCalendar'
import { useToast } from 'vue-toastification'
import { isProfessional } from '../../shared/meCache'

const router = useRouter()
const route = useRoute()
const toast = useToast()
const appointments = ref([])
const loading = ref(false)
const date = ref(localIsoDate())
const closedDays = ref([])
const subscriptionStatus = ref('blocked')
const clinicOwnerName = ref('')
const isAllMode = computed(() => String(route.query.all || '') === '1')
const isTodayMode = computed(() => !isAllMode.value && date.value === localIsoDate())
const canCreateAppointment = computed(() => {
  return subscriptionStatus.value === 'active' || subscriptionStatus.value === 'trial'
})
const appointmentScope = ref('scheduled')
const query = ref('')
const paymentFilter = ref('')
const statusFilter = ref('')
const detailedFiltersOpen = ref(false)
const agendaProfessionals = ref([])
const professionalFilter = ref('')
const draftStatusFilter = ref('')
const draftPaymentFilter = ref('')
const pageSize = 10
const currentPage = ref(1)
const nowTimestamp = ref(Date.now())
let nowTimerId = null
const showScheduledOnly = computed(() => appointmentScope.value === 'scheduled')
const detailedFiltersCount = computed(() => {
  let count = 0
  if (statusFilter.value) count += 1
  if (paymentFilter.value) count += 1
  return count
})
const statusFilterLabelMap = {
  pending: 'Pendientes',
  scheduled: 'Programadas',
  rescheduled: 'Reprogramadas',
  completed: 'Completadas',
  canceled: 'Canceladas',
}
const paymentFilterLabelMap = {
  unpaid: 'Impago',
  pending: 'Pendiente',
  partially_paid: 'Parcial',
  paid: 'Pagado',
}
const appliedDetailedFilterTags = computed(() => {
  const tags = []

  if (statusFilter.value) {
    tags.push({
      key: 'status',
      label: `Estado: ${statusFilterLabelMap[statusFilter.value] || statusFilter.value}`,
    })
  }

  if (paymentFilter.value) {
    tags.push({
      key: 'payment',
      label: `Pago: ${paymentFilterLabelMap[paymentFilter.value] || paymentFilter.value}`,
    })
  }

  return tags
})
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
const isSelectedDateClosed = computed(() => !isAllMode.value && isDateClosed(date.value, closedDays.value))

function localIsoDate() {
  const now = new Date()
  const yyyy = now.getFullYear()
  const mm = String(now.getMonth() + 1).padStart(2, '0')
  const dd = String(now.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}


function setMode(all) {
  const today = new Date().toISOString().slice(0, 10)
  if (!all) date.value = today
  router.replace({ query: { ...route.query, all: all ? '1' : undefined, date: all ? route.query.date : today } })
}

function setAppointmentScope(scope) {
  appointmentScope.value = scope === 'all' ? 'all' : 'scheduled'
  router.replace({
    query: {
      ...route.query,
      appointment_scope: appointmentScope.value === 'all' ? 'all' : undefined,
    },
  })
}

function normalizeAppointmentScopeInput(value) {
  return String(value || '').trim() === 'all' ? 'all' : 'scheduled'
}

function syncAppointmentScopeFromRoute() {
  appointmentScope.value = normalizeAppointmentScopeInput(route.query.appointment_scope)
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

function parseDateTime(value) {
  const normalized = String(value || '').trim().replace(' ', 'T')
  if (!normalized) return null
  const parsed = new Date(normalized)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

function isScheduledAppointment(appointment) {
  if (appointment?.status === 'canceled') {
    return false
  }

  const endAt = parseDateTime(appointment?.end_time) || parseDateTime(appointment?.start_time)
  if (!endAt) {
    return true
  }

  return endAt.getTime() > nowTimestamp.value
}

async function load() {
  loading.value = true
  try {
    const params = isAllMode.value ? {} : { date: date.value }
    if (professionalFilter.value) {
      params.professional_id = professionalFilter.value
    }
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

async function loadAgendaProfessionals() {
  try {
    const res = await api.get('/agenda/professionals')
    agendaProfessionals.value = Array.isArray(res.data?.data) ? res.data.data : []
  } catch (e) {
    agendaProfessionals.value = []
  }
}

function onProfessionalChange() {
  load()
}

async function loadClinicCalendarConfig() {
  try {
    const res = await api.get('/me')
    closedDays.value = normalizeClosedDays(res?.data?.clinic?.closed_days)
    subscriptionStatus.value = String(res?.data?.status || 'blocked').trim().toLowerCase()
    clinicOwnerName.value = res?.data?.clinic_owner_name || ''
  } catch (e) {
    closedDays.value = []
    subscriptionStatus.value = 'blocked'
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

function openDetailedFilters() {
  draftStatusFilter.value = statusFilter.value
  draftPaymentFilter.value = paymentFilter.value
  detailedFiltersOpen.value = true
}

function closeDetailedFilters() {
  detailedFiltersOpen.value = false
}

function applyDetailedFilters() {
  statusFilter.value = draftStatusFilter.value
  paymentFilter.value = draftPaymentFilter.value
  applyRouteFilters()
  closeDetailedFilters()
}

function clearDetailedFilters() {
  draftStatusFilter.value = ''
  draftPaymentFilter.value = ''
}

function removeAppliedFilterTag(key) {
  if (key === 'status') {
    statusFilter.value = ''
  }

  if (key === 'payment') {
    paymentFilter.value = ''
  }

  draftStatusFilter.value = statusFilter.value
  draftPaymentFilter.value = paymentFilter.value
  applyRouteFilters()
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
onMounted(() => loadAgendaProfessionals())

onMounted(() => {
  loadClinicCalendarConfig()
})

onMounted(() => {
  syncDateFromRoute()
  syncFiltersFromRoute()
  syncAppointmentScopeFromRoute()
  nowTimerId = setInterval(() => {
    nowTimestamp.value = Date.now()
  }, 60_000)
})

onUnmounted(() => {
  if (nowTimerId) clearInterval(nowTimerId)
})

watch(date, () => {
  load()
})

watch(() => route.query, () => {
  syncFiltersFromRoute()
  syncAppointmentScopeFromRoute()
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
    if (showScheduledOnly.value && !isScheduledAppointment(a)) {
      return false
    }

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
    const notes = a.notes ?? ''
    return [name, nif, notes].some((f) => f && String(f).toLowerCase().includes(q))
  })
})

const hasActiveFilters = computed(() => {
  return Boolean(String(query.value || '').trim())
    || appointmentScope.value === 'all'
    || Boolean(statusFilter.value)
    || Boolean(paymentFilter.value)
})

const emptyStateTitle = computed(() => {
  if (isTodayMode.value) return 'No hay citas hoy'
  if (!isAllMode.value) return 'No hay citas para este día'
  return 'No hay citas todavía'
})

const emptyStateSubtitle = computed(() => {
  if (isTodayMode.value) return 'Puedes crear una cita nueva desde esta misma pantalla.'
  if (!isAllMode.value) return 'Prueba con otra fecha o crea una nueva cita.'
  return 'Empieza creando tu primera cita para ver actividad en agenda.'
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
  if (!canCreateAppointment.value) {
    toast.info('Activa tu suscripción para crear nuevas citas')
    return
  }

  if (isSelectedDateClosed.value) {
    toast.info('La clínica está cerrada en esta fecha')
    return
  }
  const pad = n => String(n).padStart(2, '0')
  const toISO = min => `${date.value}T${pad(Math.floor(min / 60))}:${pad(min % 60)}`
  router.push({ path: '/appointments/create', query: { start: toISO(item.from), end: toISO(item.to) } })
}

function appointmentTypeStyle(item) {
  const color = item.appointment_type?.color
  return color ? { backgroundColor: color, color: getContrastColor(color) } : {}
}

function createAppointmentFromHeader() {
  if (!canCreateAppointment.value) {
    toast.info('Activa tu suscripción para crear nuevas citas')
    return
  }

  if (isSelectedDateClosed.value && !isAllMode.value) {
    toast.info('La clínica está cerrada en esta fecha')
    return
  }

  if (isAllMode.value) {
    router.push('/appointments/create')
    return
  }

  router.push({ path: '/appointments/create', query: { start: `${date.value}T09:00`, end: `${date.value}T10:00` } })
}

const listWithGaps = computed(() => {
  if (isAllMode.value) {
    return paginatedAppointments.value.map(a => ({ _type: 'appt', ...a }))
  }

  if (isSelectedDateClosed.value) {
    return [...filteredAppointments.value]
      .sort((a, b) => parseMin(a.start_time) - parseMin(b.start_time))
      .map(a => ({ _type: 'appt', ...a }))
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

watch([query, paymentFilter, statusFilter, isAllMode, appointmentScope], () => {
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

.agenda-page-header {
  padding: 0 16px;
}

.header-secondary { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; flex-wrap:wrap }
.header-secondary-left { display:flex; align-items:center; gap:12px; flex-wrap:wrap }
.header-actions-right { display:flex; align-items:center; gap:18px; margin-left:auto }

.agenda-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 4px 12px rgba(2, 6, 23, 0.05);
}

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
  grid-template-columns: minmax(260px, 1fr);
  gap: 8px;
  margin-bottom: 12px;
  align-items: center;
}

.filter-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  padding: 0;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  color: #374151;
  cursor: pointer;
  position: relative;
}

.filter-icon {
  width: 18px;
  height: 18px;
}

.filter-trigger:hover { background: #f8fafc }
.filter-trigger-active {
  border-color: #3b82f6;
  background: #eff6ff;
  color: #1d4ed8;
}
.filter-trigger-active:hover { background: #dbeafe }

.filter-badge {
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  border-radius: 9999px;
  background: #dbeafe;
  color: #1d4ed8;
  font-weight: 700;
  font-size: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  position: absolute;
  top: -5px;
  right: -5px;
  border: 1px solid #bfdbfe;
}

.applied-filter-tags {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.applied-filter-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 8px;
  border-radius: 9999px;
  border: 1px solid #bfdbfe;
  background: #eff6ff;
  color: #1e3a8a;
  font-size: 11px;
  font-weight: 600;
}

.applied-filter-tag-remove {
  width: 16px;
  height: 16px;
  border-radius: 9999px;
  border: 1px solid #93c5fd;
  background: #dbeafe;
  color: #1e3a8a;
  font-size: 12px;
  line-height: 1;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.filters-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
  padding: 16px;
}

.filters-modal {
  width: min(460px, 100%);
  background: #fff;
  border-radius: 12px;
  border: 1px solid #e5e7eb;
  box-shadow: 0 20px 45px rgba(2, 6, 23, 0.18);
  overflow: hidden;
}

.filters-modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid #e5e7eb;
}

.filters-modal-head h3 {
  margin: 0;
  font-size: 15px;
  font-weight: 700;
  color: #111827;
}

.filters-modal-close {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #6b7280;
  cursor: pointer;
}

.filters-modal-body {
  padding: 14px 16px;
  display: grid;
  gap: 12px;
}

.filters-field {
  display: grid;
  gap: 6px;
}

.filters-field span {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.filters-field select {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 13px;
  background: #fff;
}

.filters-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 12px 16px 16px;
}

.filters-secondary,
.filters-primary {
  border-radius: 8px;
  padding: 8px 12px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}

.filters-secondary {
  border: 1px solid #e5e7eb;
  background: #fff;
  color: #374151;
}

.filters-primary {
  border: 1px solid #2563eb;
  background: #2563eb;
  color: #fff;
}

/* Ensure mini calendar sits above the search area to avoid being covered */
.mini-cal-wrapper { position: relative; z-index: 20 }
.search-center, .search-wrapper { position: relative; z-index: 0 }

/* ── Tabla responsiva ─────────────────────────────── */
.list-wrapper { overflow-x: auto }
.agenda-table { width:100%; min-width:1020px; border-collapse:collapse; table-layout:auto }
.agenda-table thead { position:sticky; top:0; z-index:2 }
.agenda-table th {
  padding:8px 8px; font-weight:600; font-size:13px; color:#6b7280;
  text-align:left; border-bottom:2px solid #e5e7eb; background:#fff;
}
.agenda-table th.action-col { width:80px; text-align:center }
.agenda-table td { padding:6px 8px; font-size:13px; color:#374151; text-align:left; vertical-align:middle; border-bottom:1px solid #f3f4f6 }
.agenda-table td:first-child { padding-left:12px }
.appointment-row { cursor:pointer; transition:background .12s }
.appointment-row:hover td { background:rgba(0,0,0,0.02) }
.row-name { font-weight:600; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis }
.time { white-space: nowrap; overflow: hidden; text-overflow: ellipsis }
.time-scheduled { background:#eef2ff; color:#1e3a8a; padding:4px 8px; border-radius:8px; display:inline-block }
.time-completed { background:#dcfce7; color:#166534; padding:4px 8px; border-radius:8px; display:inline-block }
.time-canceled { background:#fff4f4; color:#da7a7a; padding:4px 8px; border-radius:8px; display:inline-block }
 .time-rescheduled { background:#fff7ed; color:#b45309; padding:4px 8px; border-radius:8px; display:inline-block }
td.row-action { text-align:center; width:80px }

.type-badge { padding:6px 10px; border-radius:9999px; font-weight:700 }
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
.header-create-btn { margin-left:0 }

/* Toggle Hoy / Semana — en cabecera, no debajo */
.scope-bar { display:flex; gap:0; border:1px solid #e5e7eb; border-radius:9px; overflow:hidden; width:fit-content }
.scope-btn { padding:6px 16px; font-size:13px; font-weight:600; color:#6b7280; background:#fff; border:none; cursor:pointer; transition:background .12s, color .12s }
.scope-btn:not(:last-child) { border-right:1px solid #e5e7eb }
.scope-btn:hover:not(.scope-active) { background:#f1f5f9 }
.scope-active { background:#eff6ff; color:#2563eb; border-color:#3b82f6 }
.scope-btn.scope-active:hover { background:#dbeafe }

.professional-select {
  padding: 6px 10px;
  font-size: 13px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  color: #374151;
  cursor: pointer;
  max-width: 200px;
}

/* Mini-cal deshabilitado en modo Ver Todo */
.cal-dimmed { opacity:.4 }
.cal-closed { text-decoration: line-through; color:#b91c1c }
.mini-date:disabled { opacity:.4; cursor:default }
.closed-day-alert {
  margin-bottom: 12px;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid #fecaca;
  background: #fff1f2;
  color: #9f1239;
  font-size: 13px;
}

/* Fecha corta en modo Ver Todo */
.row-date { display:inline; font-size:13px; font-weight:600; color:#6b7280 }

.pagination { margin-top:12px; display:flex; justify-content:flex-end; gap:12px; align-items:center }
.pagination-info { color:#6b7280; font-size:13px }
.pagination-actions { display:flex; gap:8px }
.icon-btn { width:32px; height:32px; border-radius:8px; border:1px solid #e5e7eb; background:#fff }
.icon-btn:disabled { opacity:0.45; cursor:not-allowed }

@media (max-width: 900px) {
  .header-secondary { justify-content:flex-start }
  .header-actions-right { width:100%; justify-content:flex-end }

  .filters-row {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .agenda-table { min-width:800px }
}
</style>
