<template>
  <MainLayout>
    <div>
      <div class="page-header">
          <div class="mini-cal">
            <div class="cal-day">{{ displayDay }}</div>
            <div class="cal-month">{{ displayMonthYear }}</div>
            <input type="date" v-model="date" class="mini-date" aria-label="Seleccionar fecha" />
          </div>

          <div class="search-center">
            <div class="search-wrapper">
              <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
              <input v-model="query" placeholder="Buscar por nombre o NIF" class="search-input" />
            </div>
          </div>

          <div class="header-actions">
            <router-link to="/appointments/create" class="btn btn-sm small compact">Nueva cita</router-link>
          </div>
        </div>

        <div class="list-header">
          <div>Hora</div>
          <div>Paciente</div>
          <div>Estado</div>
          <div></div>
        </div>

        <div class="list">
          <div v-for="a in filteredAppointments" :key="a.id" class="appointment-row" role="button" tabindex="0" @click="goToAppointment(a.id)" @keydown.enter="goToAppointment(a.id)">
            <div class="row-col">{{ formatTime(a.start_time) }}</div>
            <div class="row-left">
              <div class="row-name">{{ a.patient?.name ?? ('Paciente #' + a.patient_id) }}</div>
              <div class="row-sub">{{ a.patient?.nif ?? '—' }}</div>
            </div>
            <div class="row-col"><span class="status" :class="a.status">{{ a.status ?? '—' }}</span></div>
            <div class="row-action">
              <router-link :to="`/appointments/${a.id}/edit`" class="action-btn datos" @click.stop>✎ Editar</router-link>
            </div>
          </div>

          <div v-if="filteredAppointments.length === 0" class="empty">No hay citas para esta fecha.</div>
        </div>
      </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'

const router = useRouter()
const appointments = ref([])
const loading = ref(false)
const date = ref(new Date().toISOString().slice(0,10))
const query = ref('')
const nextAppointment = ref(null)
const tomorrowCount = ref(0)
const displayDay = computed(() => {
  const d = new Date(date.value)
  return d.getDate()
})
const displayMonthYear = computed(() => {
  const d = new Date(date.value)
  return d.toLocaleString(undefined, { month: 'short', year: 'numeric' })
})

function formatTime(dt) {
  if (!dt) return '—'
  // espera ISO string o timestamp compatible
  const d = new Date(dt)
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

async function load() {
  loading.value = true
  try {
    const res = await api.get('/appointments', { params: { date: date.value } })
    // si la API devuelve paginación cambia según sea necesario
    appointments.value = Array.isArray(res.data) ? res.data : (res.data.data || [])
  } catch (e) {
    console.error('Error cargando citas', e)
    appointments.value = []
  } finally {
    loading.value = false
  }
}

async function loadNext() {
  try {
    const now = new Date().toISOString()
    const res = await api.get('/appointments', { params: { from: now, per_page: 1 } })
    const data = Array.isArray(res.data) ? res.data : (res.data.data || [])
    nextAppointment.value = data.length ? data[0] : null
  } catch (e) {
    console.error('Error cargando próxima cita', e)
    nextAppointment.value = null
  }
}

async function checkTomorrow() {
  try {
    const d = new Date(date.value)
    d.setDate(d.getDate() + 1)
    const t = d.toISOString().slice(0,10)
    const res = await api.get('/appointments', { params: { date: t } })
    const data = Array.isArray(res.data) ? res.data : (res.data.data || [])
    tomorrowCount.value = data.length
  } catch (e) {
    tomorrowCount.value = 0
  }
}

function prevDay() {
  const d = new Date(date.value)
  d.setDate(d.getDate() - 1)
  date.value = d.toISOString().slice(0,10)
  load(); loadNext(); checkTomorrow()
}

function nextDay() {
  const d = new Date(date.value)
  d.setDate(d.getDate() + 1)
  date.value = d.toISOString().slice(0,10)
  load(); loadNext(); checkTomorrow()
}

onMounted(() => load())
onMounted(() => {
  load()
  loadNext()
  checkTomorrow()
})

watch(date, () => {
  load(); loadNext(); checkTomorrow()
})

function goToAppointment(id) {
  router.push(`/appointments/${id}`)
}

const filteredAppointments = computed(() => {
  const q = (query.value || '').toLowerCase().trim()
  if (!q) return appointments.value
  return appointments.value.filter(a => {
    const name = a.patient?.name ?? ''
    const nif = a.patient?.nif ?? ''
    return [name, nif].some(f => f && String(f).toLowerCase().includes(q))
  })
})
</script>

.style-reset { }
<style scoped>
*, ::before, ::after { box-sizing: border-box; border-width: 0; border-style: solid; border-color: #e5e7eb }
.page-header { display:grid; grid-template-columns: 230px 1fr 160px; align-items:center; gap:0px; margin-bottom:16px }
.page-header h1 { margin:0; font-size:20px; font-weight:800 }
.form-sub { color:#6b7280; font-size:13px; margin-top:4px }
.calendar-card { display:flex; align-items:center; gap:12px; background:#fff; padding:10px; border-radius:10px; border:1px solid #eef2ff22 }
.cal-left, .cal-right { width:36px }
.cal-center { display:flex; flex-direction:column; align-items:center }
.cal-day { font-weight:800; font-size:22px }
.cal-month { color:#6b7280; font-size:13px }
.date-input { display:none }

.mini-cal { display:flex; flex-direction:row; align-items:center; gap:12px; padding:8px 12px; background:#fff; border-radius:10px; border:1px solid #eef2ff22; width:230px; box-shadow: 0 4px 10px rgba(2,6,23,0.03) }
.mini-cal .cal-day { font-size:20px; font-weight:800 }
.mini-cal .cal-month { font-size:13px; color:#6b7280 }
.mini-cal .cal-meta { display:flex; flex-direction:column; line-height:1 }
.mini-date { border:1px solid #e5e7eb; border-radius:8px; padding:6px; font-size:13px; margin-left:auto }

@media (max-width: 900px) {
  .mini-cal { width:100%; max-width:240px }
  .page-header { grid-template-columns: 1fr auto }
}

.search-center { display:flex; justify-content:flex-end; align-items:center }

.list { display:flex; flex-direction:column; gap:8px }
.list-header { display:grid; grid-template-columns: 100px 2fr 220px auto; gap:12px; align-items:center; padding:8px 14px; color:#6b7280; font-weight:600; font-size:13px }
.appointment-row { display:grid; grid-template-columns: 100px 2fr 160px auto; gap:12px; align-items:center; background:#fff; padding:12px 14px; border-radius:10px; text-decoration:none; color:inherit; border:1px solid #eef2ff22 }
.appointment-row:hover { box-shadow: 0 10px 24px rgba(2,6,23,0.06); transform: translateY(-2px) }
.row-left { display:flex; flex-direction:column }
.row-name { font-weight:600; font-size:15px }
.row-sub { color:#6b7280; font-size:13px }
.row-col { color:#374151; font-size:13px }
.row-action { display:flex; align-items:center; justify-content:center; color:#6b7280 }

.status { padding:6px 10px; border-radius:9999px; font-weight:700; text-transform:capitalize }
.status.canceled { background:#fff4f4; color:#da7a7a }
.status.scheduled { background:#eef2ff; color:#1e3a8a }
.status.completed { background:#dcfce7; color:#166534 }

.empty { color:#6b7280; padding:12px }

.action-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:8px; text-decoration:none; color:#374151; font-size:13px; border:1px solid transparent }
.action-btn.datos { background:#fff; border-color:#e5e7eb; color:#374151 }

/* Botón "Nueva cita" más compacto */
.btn.small.compact { padding:6px 30px; min-width:0; width:auto; }

/* Alineación de búsqueda y acciones a la derecha */
.header-actions { display:flex; gap:8px; align-items:center; justify-self:end }

@media (max-width: 900px) {
  .page-header { grid-template-columns: 1fr auto }
}

@media (max-width: 480px) {
  .appointment-row { grid-template-columns: 1fr; gap:6px }
  .row-action { justify-content:flex-start }
}
</style>
