<template>
  <MainLayout>
    <div>
     <div class="page-header">
        <div>
          <h1>Agenda</h1>
          <div class="form-sub">Visualiza y gestiona tus citas</div>
        </div>
      </div>
      <CalendarHeader
        view="week"
        :label="weekRangeLabel"
        @prev="prevWeek"
        @next="nextWeek"
        @today="goToToday"
      />

      <!-- ── Cuadrícula semanal ──────────────────────────── -->
      <div class="week-cal">

        <!-- Cabecera de días (sticky) -->
        <div class="cal-head">
          <div class="g-cell"></div>
          <div
            v-for="day in weekDays"
            :key="day.iso"
            :class="['dh-cell', { 'dh-today': day.isToday, 'dh-closed': day.isClosed }]"
          >
            <span class="dh-name">{{ day.name }}</span>
            <span :class="['dh-num', { 'dh-bubble': day.isToday }]">{{ day.num }}</span>
            <span v-if="day.isClosed" class="dh-closed-badge">Cerrado</span>
            <span v-if="apptCountForDay(day.iso) > 0" class="dh-badge">{{ apptCountForDay(day.iso) }}</span>
          </div>
        </div>

        <!-- Cuerpo desplazable -->
        <div class="cal-body" ref="calBodyRef">
          <div class="cal-inner">

            <!-- Franjas horarias -->
            <div class="t-gutter">
              <div v-for="h in hours" :key="h" class="t-row">
                {{ String(h).padStart(2, '0') }}:00
              </div>
            </div>

            <!-- 7 columnas de días -->
            <div class="days-wrap">
              <div
                v-for="day in weekDays"
                :key="day.iso"
                :class="['dc', { 'dc-today': day.isToday, 'dc-weekend': day.isWeekend, 'dc-closed': day.isClosed }]"
              >
                <!-- Filas de hora (guías) -->
                <div
                  v-for="h in hours"
                  :key="h"
                  :class="['hr-row', { 'hr-row-closed': day.isClosed }]"
                  :title="`Nueva cita ${String(h).padStart(2,'0')}:00`"
                  @click="goToNewSlot(day.iso, h)"
                ></div>

                <div v-if="day.isClosed" class="day-closed-overlay">Día cerrado</div>

                <!-- Indicador de hora actual -->
                <div
                  v-if="day.isToday && nowTop !== null"
                  class="now-bar"
                  :style="{ top: nowTop + 'px' }"
                >
                  <span class="now-dot"></span>
                </div>

                <!-- Bloques de citas -->
                <div
                  v-for="a in appointmentsForDay(day.iso)"
                  :key="a.id"
                  :class="['appt', 'appt-' + a.status]"
                  :style="apptStyle(a)"
                  role="button"
                  tabindex="0"
                  @click="goToAppointment(a.id)"
                  @keydown.enter="goToAppointment(a.id)"
                  :title="`${a._tLabel} · ${a.patient?.name ?? '—'}`"
                >
                  <span class="appt-t">{{ a._tLabel }}</span>
                  <span class="appt-n">{{ a.patient?.name ?? '—' }}</span>
                  <span v-if="a._h >= 48" class="appt-notes">{{ a.notes }}</span>
                </div>

              </div>
            </div>

          </div>
        </div>

      </div>

    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import CalendarHeader from '../../components/calendar/CalendarHeader.vue'
import { isDateClosed, normalizeClosedDays } from '../../shared/clinicCalendar'
import { useToast } from 'vue-toastification'

const router = useRouter()
const toast = useToast()

// ── Constantes ────────────────────────────────────────
const HOUR_START = 0
const HOUR_END   = 24
const SLOT_H     = 64   // px por hora
const hours      = Array.from({ length: HOUR_END - HOUR_START }, (_, i) => HOUR_START + i)

// ── Estado ────────────────────────────────────────────
const weekStart  = ref(getMonday(new Date()))
const nowTop     = ref(null)
const calBodyRef = ref(null)
const appointments = ref([])
const loading = ref(false)
const closedDays = ref([])
let   timerId    = null

// ── Utilidades ────────────────────────────────────────
function getMonday(date) {
  const d   = new Date(date)
  const day = d.getDay()
  const off = day === 0 ? -6 : 1 - day
  d.setDate(d.getDate() + off)
  d.setHours(0, 0, 0, 0)
  return d
}

function toISO(date) {
  const Y = date.getFullYear()
  const M = String(date.getMonth() + 1).padStart(2, '0')
  const D = String(date.getDate()).padStart(2, '0')
  return `${Y}-${M}-${D}`
}

function parseMin(dtStr) {
  const m = String(dtStr).match(/[ T](\d{2}):(\d{2})/)
  return m ? Number(m[1]) * 60 + Number(m[2]) : 0
}

function hhmm(totalMin) {
  return `${String(Math.floor(totalMin / 60)).padStart(2, '0')}:${String(totalMin % 60).padStart(2, '0')}`
}

function toSqlDateTime(date, endOfDay = false) {
  const d = new Date(date)
  if (endOfDay) {
    d.setHours(23, 59, 59, 0)
  } else {
    d.setHours(0, 0, 0, 0)
  }

  const yyyy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  const hh = String(d.getHours()).padStart(2, '0')
  const min = String(d.getMinutes()).padStart(2, '0')
  const sec = String(d.getSeconds()).padStart(2, '0')

  return `${yyyy}-${mm}-${dd} ${hh}:${min}:${sec}`
}

async function load() {
  loading.value = true
  try {
    const weekEnd = new Date(weekStart.value)
    weekEnd.setDate(weekEnd.getDate() + 6)

    const res = await api.get('/appointments', {
      params: {
        from: toSqlDateTime(weekStart.value),
        to: toSqlDateTime(weekEnd, true),
      },
    })

    appointments.value = Array.isArray(res.data) ? res.data : (res.data.data || [])
  } catch (error) {
    console.error('Error cargando citas de la semana', error)
    appointments.value = []
  } finally {
    loading.value = false
  }
}

async function loadClinicCalendarConfig() {
  try {
    const res = await api.get('/me')
    closedDays.value = normalizeClosedDays(res?.data?.clinic?.closed_days)
  } catch (e) {
    closedDays.value = []
  }
}

// ── Días de la semana ─────────────────────────────────
const DAY_NAMES = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']
const todayISO  = toISO(new Date())

const weekDays = computed(() =>
  Array.from({ length: 7 }, (_, i) => {
    const d = new Date(weekStart.value)
    d.setDate(d.getDate() + i)
    const iso = toISO(d)
    return {
      iso,
      name:      DAY_NAMES[i],
      num:       d.getDate(),
      isToday:   iso === todayISO,
      isClosed:  isDateClosed(iso, closedDays.value),
      isWeekend: i >= 5,
    }
  })
)

const weekRangeLabel = computed(() => {
  const s = weekDays.value[0]
  const e = weekDays.value[6]
  const fmt = (iso, opts) =>
    new Date(iso + 'T00:00:00').toLocaleDateString('es-ES', opts)
  return `${fmt(s.iso, { day: 'numeric', month: 'short' })} – ${fmt(e.iso, { day: 'numeric', month: 'short', year: 'numeric' })}`
})

// ── Navegación ────────────────────────────────────────
function prevWeek() {
  const d = new Date(weekStart.value)
  d.setDate(d.getDate() - 7)
  weekStart.value = d
}
function nextWeek() {
  const d = new Date(weekStart.value)
  d.setDate(d.getDate() + 7)
  weekStart.value = d
}
function goToToday() {
  weekStart.value = getMonday(new Date())
}
function goToAppointment(id) {
  router.push(`/appointments/${id}`)
}

function goToNewSlot(iso, hour) {
  if (isDateClosed(iso, closedDays.value)) {
    toast.info('La clínica está cerrada en esa fecha')
    return
  }
  const pad = n => String(n).padStart(2, '0')
  const start = `${iso}T${pad(hour)}:00`
  const end   = `${iso}T${pad(Math.min(hour + 1, 23))}:00`
  router.push({ path: '/appointments/create', query: { start, end } })
}

// ── Layout: columnas para solapamientos ───────────────
function layoutDay(raw) {
  const appts = raw
    .map(a => {
      const sm = parseMin(a.start_time)
      const em = parseMin(a.end_time)
      return { ...a, _sm: sm, _em: em, _tLabel: hhmm(sm) }
    })
    .sort((a, b) => a._sm - b._sm)

  const cols = []
  appts.forEach(a => {
    let placed = false
    for (let c = 0; c < cols.length; c++) {
      if (cols[c][cols[c].length - 1]._em <= a._sm) {
        cols[c].push(a)
        a._col = c
        placed = true
        break
      }
    }
    if (!placed) {
      a._col = cols.length
      cols.push([a])
    }
  })

  const total = cols.length || 1
  appts.forEach(a => {
    a._total = total
    a._h = Math.max((a._em - a._sm) / 60 * SLOT_H - 2, 22)
  })
  return appts
}

// ── Citas por día ─────────────────────────────────────
const byDay = computed(() => {
  const map = {}
  weekDays.value.forEach(d => {
    map[d.iso] = layoutDay(
      appointments.value.filter(a => String(a.start_time || '').startsWith(d.iso))
    )
  })
  return map
})

function appointmentsForDay(iso) {
  return byDay.value[iso] ?? []
}

function apptCountForDay(iso) {
  return appointmentsForDay(iso).length
}

function apptStyle(a) {
  const top  = Math.max((a._sm - HOUR_START * 60) / 60 * SLOT_H, 0)
  const pct  = 100 / a._total
  return {
    top:    top + 'px',
    height: a._h + 'px',
    left:   `calc(${a._col * pct}% + 2px)`,
    width:  `calc(${pct}% - 4px)`,
  }
}

// ── Indicador de hora actual ──────────────────────────
function updateNow() {
  const now = new Date()
  const min = now.getHours() * 60 + now.getMinutes()
  nowTop.value = (min >= HOUR_START * 60 && min < HOUR_END * 60)
    ? (min - HOUR_START * 60) / 60 * SLOT_H
    : null
}

onMounted(async () => {
  updateNow()
  timerId = setInterval(updateNow, 60_000)
  await loadClinicCalendarConfig()
  await load()
  await nextTick()
  if (calBodyRef.value) {
    calBodyRef.value.scrollTop = nowTop.value !== null
      ? Math.max(nowTop.value - 140, 0)
      : 0
  }
})

watch(weekStart, () => {
  load()
})

onUnmounted(() => {
  if (timerId) clearInterval(timerId)
})
</script>

<style scoped>
*, ::before, ::after { box-sizing: border-box }

/* ── Contenedor del calendario ───────────────────────── */
.week-cal {
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  overflow: hidden;
  background: #fff;
  box-shadow: 0 4px 20px rgba(2, 6, 23, 0.05);
}

/* ── Franja de cabecera de días (sticky) ─────────────── */
.cal-head {
  display: grid;
  grid-template-columns: 56px repeat(7, 1fr);
  border-bottom: 2px solid #e5e7eb;
  background: #fff;
  position: sticky;
  top: 0;
  z-index: 10;
}

.g-cell { border-right: 1px solid #e5e7eb }

.dh-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 10px 4px 8px;
  border-right: 1px solid #e5e7eb;
  gap: 3px;
  min-width: 0;
  position: relative;
}
.dh-closed .dh-name,
.dh-closed .dh-num {
  text-decoration: line-through;
  color: #b91c1c;
}
.dh-closed-badge {
  margin-top: 4px;
  font-size: 10px;
  font-weight: 700;
  color: #9f1239;
  background: #ffe4e6;
  border: 1px solid #fecdd3;
  border-radius: 999px;
  padding: 1px 6px;
}
.dh-cell:last-child { border-right: none }

.dh-name {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: #6b7280;
}
.dh-today .dh-name { color: #2563eb }

.dh-num {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
}
.dh-bubble { background: #2563eb; color: #fff }

.dh-badge {
  position: absolute;
  top: 6px;
  right: 6px;
  background: #dbeafe;
  color: #1d4ed8;
  font-size: 10px;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 9999px;
  line-height: 1.4;
}
.dh-today .dh-badge { background: #bfdbfe }

/* ── Cuerpo desplazable ───────────────────────────────── */
.cal-body {
  overflow-y: auto;
  max-height: 620px;
}

.cal-inner {
  display: flex;
  min-width: 560px;
}

/* ── Franja de etiquetas horarias ─────────────────────── */
.t-gutter {
  width: 56px;
  flex-shrink: 0;
  border-right: 1px solid #e5e7eb;
  background: #fff;
}

.t-row {
  height: 64px;
  display: flex;
  align-items: flex-start;
  justify-content: flex-end;
  padding: 4px 8px 0 0;
  font-size: 10px;
  color: #9ca3af;
  user-select: none;
}

/* ── Wrapper de los 7 días ────────────────────────────── */
.days-wrap {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  min-width: 0;
}

/* ── Columna de cada día ──────────────────────────────── */
.dc {
  position: relative;
  border-right: 1px solid #e5e7eb;
}
.dc-closed {
  background: repeating-linear-gradient(
    -45deg,
    rgba(244, 63, 94, 0.06),
    rgba(244, 63, 94, 0.06) 8px,
    rgba(255, 255, 255, 0.8) 8px,
    rgba(255, 255, 255, 0.8) 16px
  );
}
.dc:last-child { border-right: none }

/* Fin de semana con fondo levemente distinto */
.dc-weekend { background: #fafafa }

/* Día actual con fondo azul muy tenue */
.dc-today { background: #eff6ff }

/* Filas de hora (guías horizontales) */
.hr-row {
  height: 64px;
  border-bottom: 1px solid #f3f4f6;
  pointer-events: auto;
  cursor: pointer;
}
.hr-row-closed {
  cursor: not-allowed;
}
.day-closed-overlay {
  position: absolute;
  top: 8px;
  right: 8px;
  font-size: 11px;
  font-weight: 700;
  color: #9f1239;
  background: rgba(255, 241, 242, 0.96);
  border: 1px solid #fecdd3;
  border-radius: 999px;
  padding: 2px 8px;
  pointer-events: none;
}
.hr-row:hover {
  background: rgba(59, 130, 246, 0.08);
}

/* Línea de media hora */
.hr-row::after {
  content: '';
  display: block;
  height: 1px;
  background: #f9fafb;
  margin-top: 31px;
}

/* ── Indicador de hora actual ─────────────────────────── */
.now-bar {
  position: absolute;
  left: 0;
  right: 0;
  height: 2px;
  background: #ef4444;
  z-index: 5;
  pointer-events: none;
}

.now-dot {
  position: absolute;
  left: -4px;
  top: -4px;
  width: 10px;
  height: 10px;
  background: #ef4444;
  border-radius: 50%;
}

/* ── Bloques de citas ─────────────────────────────────── */
.appt {
  position: absolute;
  border-radius: 6px;
  padding: 4px 6px;
  cursor: pointer;
  overflow: hidden;
  font-size: 11px;
  line-height: 1.35;
  z-index: 2;
  display: flex;
  flex-direction: column;
  gap: 1px;
  transition: box-shadow .15s, filter .15s;
}
.appt:hover {
  box-shadow: 0 4px 14px rgba(0, 0, 0, .15);
  filter: brightness(.96);
  z-index: 6;
}
.appt:focus-visible {
  outline: 2px solid #2563eb;
  outline-offset: 1px;
  z-index: 6;
}

.appt-t {
  font-size: 10px;
  font-weight: 700;
  opacity: .75;
  white-space: nowrap;
}

.appt-n {
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.appt-notes {
  font-size: 10px;
  opacity: .7;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Colores por estado */
.appt-scheduled   { background: #dbeafe; color: #1e40af; border-left: 3px solid #3b82f6 }
.appt-completed   { background: #dcfce7; color: #166534; border-left: 3px solid #22c55e }
.appt-rescheduled { background: #fef3c7; color: #92400e; border-left: 3px solid #f59e0b }
.appt-canceled    { background: #fee2e2; color: #991b1b; border-left: 3px solid #ef4444; opacity: .65 }

/* ── Responsive ───────────────────────────────────────── */
@media (max-width: 900px) {
  .week-cal { overflow-x: auto }
  .cal-inner { min-width: 700px }
}
</style>
