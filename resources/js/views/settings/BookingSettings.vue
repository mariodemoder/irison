<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import api from '../../services/api'
import { useToast } from 'vue-toastification'
import BtnTrash from '../../components/BtnTrash.vue'

const props = defineProps({
  cesionTypes: { type: Array, default: () => [] },
})

const toast = useToast()
const localLoading = ref(false)
const activeSubTab = ref('settings')

const settings = ref({
  slug: '',
  title: '',
  is_active: true,
  max_horizon_days: 60,
  cancellation_hours: 24,
})

const services = ref([])
const professionals = ref([])
const scheduleGrid = ref({})
const scheduleFromUser = ref({})
const exceptions = ref({})

const editingProfessional = ref(null)
const slugError = ref('')
const slugTimeout = ref(null)

let _serviceKey = 0

function makeService() {
  return {
    _key: ++_serviceKey,
    name: '',
    description: '',
    duration_minutes: 60,
    price: null,
    is_active: true,
    appointment_type_id: null,
  }
}

const removedServiceIds = ref([])

function addServiceView() {
  services.value.push(makeService())
}

function removeService(index) {
  const svc = services.value[index]
  if (svc.id) {
    removedServiceIds.value.push(svc.id)
  }
  services.value.splice(index, 1)
}

function fillFromAppointmentType(svc) {
  if (!svc.appointment_type_id) return
  const ct = props.cesionTypes.find(c => c.id === svc.appointment_type_id)
  if (ct) {
    svc.duration_minutes = (ct.estimated_hours ?? 0) * 60 + (ct.estimated_minutes ?? 60)
    svc.price = ct.price ?? 0
  }
}

async function loadSettings() {
  localLoading.value = true
  try {
    const res = await api.get('/booking/settings')
    if (res.data.data) {
      settings.value = { ...settings.value, ...res.data.data }
    }
    const [svcRes, profRes] = await Promise.all([
      api.get('/booking/services'),
      api.get('/booking/professionals'),
    ])
    services.value = svcRes.data.data || []
    professionals.value = profRes.data.data || []

    const profIds = professionals.value.map(p => p.id)
    await Promise.all(profIds.flatMap(id => [loadSchedules(id), loadExceptions(id)]))
  } catch (e) {
    toast.error('Error al cargar configuración de reserva online.')
  } finally {
    localLoading.value = false
  }
}

async function checkSlugAvailability() {
  const slug = settings.value.slug?.trim()
  if (!slug) {
    slugError.value = 'La URL es obligatoria.'
    return false
  }
  try {
    const res = await api.get('/booking/slug-check', { params: { slug } })
    if (!res.data.available) {
      slugError.value = 'Esa URL ya está en uso. Elige otra.'
      return false
    }
    slugError.value = ''
    return true
  } catch {
    slugError.value = ''
    return true
  }
}

watch(() => settings.value.slug, () => {
  if (slugTimeout.value) clearTimeout(slugTimeout.value)
  slugTimeout.value = setTimeout(checkSlugAvailability, 400)
})

async function saveBookingSettings() {
  const slugAvailable = await checkSlugAvailability()
  if (!slugAvailable) {
    toast.error('La URL ya está en uso. Elige otra antes de guardar.')
    return
  }

  try {
    await api.put('/booking/settings', settings.value)

    for (const svc of services.value) {
      if (svc._key) {
        const res = await api.post('/booking/services', svc)
        Object.assign(svc, res.data.data)
      } else {
        const res = await api.put(`/booking/services/${svc.id}`, svc)
        Object.assign(svc, res.data.data)
      }
    }

    for (const id of removedServiceIds.value) {
      await api.delete(`/booking/services/${id}`)
    }
    removedServiceIds.value = []
  } catch (e) {
    throw e
  }
}

defineExpose({ saveBookingSettings })

async function toggleProfessional(bp) {
  try {
    const res = await api.put(`/booking/professionals/${bp.id}`, {
      allow_online_booking: bp.allow_online_booking,
    })
    Object.assign(bp, res.data.data)
  } catch {
    toast.error('Error al actualizar profesional.')
  }
}

const dayLabels = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']

function defaultScheduleGrid() {
  return [
    { day_of_week: 1, enabled: true,  start_time: '09:00', end_time: '18:00' },
    { day_of_week: 2, enabled: true,  start_time: '09:00', end_time: '18:00' },
    { day_of_week: 3, enabled: true,  start_time: '09:00', end_time: '18:00' },
    { day_of_week: 4, enabled: true,  start_time: '09:00', end_time: '18:00' },
    { day_of_week: 5, enabled: true,  start_time: '09:00', end_time: '18:00' },
    { day_of_week: 6, enabled: false, start_time: '09:00', end_time: '14:00' },
    { day_of_week: 7, enabled: false, start_time: '09:00', end_time: '14:00' },
  ]
}

async function loadSchedules(professionalId) {
  try {
    const res = await api.get(`/booking/professionals/${professionalId}/schedules`)
    const data = res.data.data || []
    scheduleFromUser.value[professionalId] = !!res.data.from_user

    const grid = defaultScheduleGrid()
    if (data.length > 0) {
      scheduleGrid.value[professionalId] = grid.map(def => {
        const found = data.find(s => s.day_of_week === def.day_of_week)
        return found
          ? { day_of_week: found.day_of_week, enabled: true, start_time: found.start_time, end_time: found.end_time }
          : { ...def, enabled: false }
      })
    } else {
      scheduleGrid.value[professionalId] = grid
    }
  } catch {
    scheduleGrid.value[professionalId] = defaultScheduleGrid()
    scheduleFromUser.value[professionalId] = false
  }
}

async function saveBulkSchedules(professionalId) {
  try {
    const schedules = scheduleGrid.value[professionalId].map(s => ({
      day_of_week: s.day_of_week,
      start_time: s.enabled ? (s.start_time || '').substring(0, 5) : null,
      end_time: s.enabled ? (s.end_time || '').substring(0, 5) : null,
    }))
    await api.post(`/booking/professionals/${professionalId}/schedules/bulk`, { schedules })
    scheduleFromUser.value[professionalId] = false
    toast.success('Horarios guardados.')
  } catch (e) {
    toast.error('Error al guardar horarios.')
  }
}

async function loadExceptions(professionalId) {
  try {
    const res = await api.get(`/booking/professionals/${professionalId}/exceptions`)
    exceptions.value[professionalId] = res.data.data || []
  } catch {
    exceptions.value[professionalId] = []
  }
}

function toggleProfessionalDetail(bp) {
  editingProfessional.value = editingProfessional.value === bp.id ? null : bp.id
}

const bookingPublicUrl = computed(() =>
  settings.value.slug
    ? `${window.location.origin}/booking/${encodeURIComponent(settings.value.slug)}`
    : ''
)

async function copyBookingUrl() {
  try {
    await navigator.clipboard.writeText(bookingPublicUrl.value)
    toast.success('Enlace copiado')
  } catch (e) {
    toast.error('No se pudo copiar el enlace')
  }
}



onMounted(loadSettings)
</script>

<template>
  <div v-if="localLoading" style="text-align:center;padding:24px;color:#6b7280;">Cargando configuración...</div>
  <div v-else>
    <div class="sub-tabs" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
      <button :class="['tab', 'tab-small', { active: activeSubTab==='settings' }]" @click="activeSubTab='settings'">Configuración</button>
      <button :class="['tab', 'tab-small', { active: activeSubTab==='services' }]" @click="activeSubTab='services'">Servicios</button>
      <button :class="['tab', 'tab-small', { active: activeSubTab==='professionals' }]" @click="activeSubTab='professionals'">Profesionales</button>
    </div>

    <!-- Settings -->
    <div v-show="activeSubTab==='settings'" class="booking-settings-form">
      <div class="form-row-2">
        <div class="form-group">
          <label>URL pública</label>
          <div class="url-preview">/booking/<strong>{{ settings.slug }}</strong></div>
          <input v-model="settings.slug" class="input" :class="{ 'input-error': slugError }" placeholder="mi-clinica" />
          <span v-if="slugError" class="slug-error">{{ slugError }}</span>
        </div>
        <div class="form-group">
          <label>Título</label>
          <input v-model="settings.title" class="input" placeholder="Reserva tu cita" />
        </div>
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label>Estado</label>
          <select v-model="settings.is_active" class="input">
            <option :value="true">Activado</option>
            <option :value="false">Desactivado</option>
          </select>
        </div>
        <div class="form-group">
          <label>Horizonte máximo de reserva (días)</label>
          <select v-model.number="settings.max_horizon_days" class="input">
            <option :value="30">30 días</option>
            <option :value="60">60 días</option>
            <option :value="90">90 días</option>
            <option :value="180">180 días</option>
          </select>
        </div>
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label>Política de cancelación (horas antes)</label>
          <select v-model.number="settings.cancellation_hours" class="input">
            <option :value="1">1 hora</option>
            <option :value="12">12 horas</option>
            <option :value="24">24 horas</option>
            <option :value="48">48 horas</option>
            <option :value="72">72 horas</option>
          </select>
        </div>
      </div>
      <div v-if="settings.slug" class="public-link-actions">
        <a :href="bookingPublicUrl" target="_blank" rel="noopener" class="btn btn-sm btn-outline">
          Ver página pública&nbsp;↗
        </a>
        <button type="button" class="btn btn-sm btn-outline" @click="copyBookingUrl">Copiar enlace público</button>
      </div>
    </div>

    <!-- Services -->
    <div v-show="activeSubTab==='services'">
      <div class="section-head">
        <span class="section-head-title">Servicios</span>
        <NewButton label="Nuevo servicio" @click="addServiceView" />
      </div>

      <div class="counter-table-wrap" style="margin-top:14px">
        <table class="counter-table sesiones-table">
          <colgroup>
            <col class="svc-col-name">
            <col class="svc-col-type">
            <col class="svc-col-duration">
            <col class="svc-col-price">
            <col class="svc-col-active">
            <col class="svc-col-actions">
          </colgroup>
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Tipo de cita</th>
              <th>Duración</th>
              <th>Precio</th>
              <th>Activo</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(svc, index) in services" :key="svc.id ?? svc._key">
              <td data-label="Nombre">
                <input class="input counter-input" v-model="svc.name" placeholder="Ej: Sesión de fisioterapia" />
              </td>
              <td data-label="Tipo de cita">
                <select class="input counter-input" v-model="svc.appointment_type_id" @change="fillFromAppointmentType(svc)">
                  <option :value="null">Sin tipo</option>
                  <option v-for="ct in cesionTypes" :key="ct.id" :value="ct.id">{{ ct.description }}</option>
                </select>
              </td>
              <td data-label="Duración">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px">
                  <div>
                    <label style="display:block;font-size:11px;color:#6b7280;margin-bottom:2px">Horas</label>
                    <input class="input counter-input" type="number" min="0" step="1"
                      :value="Math.floor((svc.duration_minutes ?? 60) / 60)"
                      @input="svc.duration_minutes = (parseInt($event.target.value) || 0) * 60 + ((svc.duration_minutes ?? 60) % 60)"
                      :disabled="!!svc.appointment_type_id"
                      style="font-size:13px;padding:6px" />
                  </div>
                  <div>
                    <label style="display:block;font-size:11px;color:#6b7280;margin-bottom:2px">Min</label>
                    <input class="input counter-input" type="number" min="0" max="59" step="1"
                      :value="(svc.duration_minutes ?? 60) % 60"
                      @input="svc.duration_minutes = Math.floor((svc.duration_minutes ?? 60) / 60) * 60 + (parseInt($event.target.value) || 0)"
                      :disabled="!!svc.appointment_type_id"
                      style="font-size:13px;padding:6px" />
                  </div>
                </div>
              </td>
              <td data-label="Precio">
                <input class="input counter-input" type="number" step="0.01" min="0" v-model.number="svc.price" placeholder="0" />
              </td>
              <td data-label="Activo">
                <select class="input counter-input" v-model="svc.is_active">
                  <option :value="true">Sí</option>
                  <option :value="false">No</option>
                </select>
              </td>
              <td data-label="Acciones">
                <BtnTrash @click.prevent="removeService(index)" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="services.length === 0" class="empty-services">
        No hay servicios configurados.
      </div>
    </div>

    <!-- Professionals -->
    <div v-show="activeSubTab==='professionals'">
      <div v-if="professionals.length === 0" style="text-align:center;color:#6b7280;padding:24px;">
        No hay profesionales configurados. Los profesionales se añaden automáticamente desde los usuarios de la clínica.
      </div>

      <div v-for="bp in professionals" :key="bp.id" class="professional-card-admin">
        <div class="professional-card-admin__header" @click="toggleProfessionalDetail(bp)">
          <div class="professional-card-admin__info">
            <strong>{{ bp.user?.name }}</strong>
            <span v-if="bp.user?.profession" class="profession-badge">{{ bp.user.profession.name }}</span>
            <span class="text-muted" style="font-size:12px;">{{ bp.user?.email }}</span>
          </div>
          <div style="display:flex;align-items:center;gap:8px;">
            <select class="input counter-input" style="width:auto;min-width:100px;" v-model="bp.allow_online_booking" @change="toggleProfessional(bp)" @click.stop>
              <option :value="true">Online</option>
              <option :value="false">Offline</option>
            </select>
            <svg class="accordion-chevron" :class="{ open: editingProfessional === bp.id }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
          </div>
        </div>

        <div v-if="editingProfessional === bp.id" class="professional-card-admin__detail">
          <div class="detail-section">
            <div class="sched-header">
              <h4>
                Horario semanal
                <span v-if="!scheduleFromUser[bp.id]" class="badge badge-booking">Personalizado para booking</span>
              </h4>
              <button class="btn btn-sm" @click="saveBulkSchedules(bp.id)">
                Guardar horarios
              </button>
            </div>

            <div class="hours-table-wrap" style="margin-top:6px">
              <table class="counter-table hours-table">
                <thead>
                  <tr>
                    <th></th>
                    <th v-for="s in (scheduleGrid[bp.id] || [])" :key="'head-'+s.day_of_week">{{ dayLabels[s.day_of_week] || 'Día ' + s.day_of_week }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="hours-row-label">Trabaja</td>
                    <td v-for="s in (scheduleGrid[bp.id] || [])" :key="'enabled-'+s.day_of_week" class="hours-cell-center">
                      <label class="day-toggle">
                        <input v-model="s.enabled" type="checkbox" />
                        <span>{{ s.enabled ? 'Sí' : 'No' }}</span>
                      </label>
                    </td>
                  </tr>
                  <tr>
                    <td class="hours-row-label">Desde</td>
                    <td v-for="s in (scheduleGrid[bp.id] || [])" :key="'start-'+s.day_of_week" class="hours-cell-center">
                       <input class="time-grid-input" type="time" step="300" v-model="s.start_time" :disabled="!s.enabled" :aria-label="'Hora de entrada ' + (dayLabels[s.day_of_week] || '')" />
                    </td>
                  </tr>
                  <tr>
                    <td class="hours-row-label">Hasta</td>
                    <td v-for="s in (scheduleGrid[bp.id] || [])" :key="'end-'+s.day_of_week" class="hours-cell-center">
                       <input class="time-grid-input" type="time" step="300" v-model="s.end_time" :disabled="!s.enabled" :aria-label="'Hora de salida ' + (dayLabels[s.day_of_week] || '')" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="scheduleFromUser[bp.id]" class="text-muted" style="font-size:13px;margin:8px 0;">
              <span class="badge badge-team">Usando horario de Equipo</span> — Los horarios del equipo se cargaron por defecto. Personaliza arriba y presiona "Guardar horarios" para crear horarios específicos para booking.
            </div>
          </div>

          <div class="detail-section">
            <h4>Excepciones / Bloqueos</h4>
            <div v-for="exc in (exceptions[bp.id] || [])" :key="exc.id" class="exception-row">
              <span>{{ exc.date }}</span>
              <span v-if="exc.start_time">{{ exc.start_time }} - {{ exc.end_time }}</span>
              <span v-else>Todo el día</span>
              <span v-if="exc.reason" class="text-muted">({{ exc.reason }})</span>
            </div>
            <div v-if="(exceptions[bp.id] || []).length === 0" class="text-muted" style="font-size:13px;margin:8px 0;">
              Sin excepciones.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 12px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-group label {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.url-preview {
  font-size: 13px;
  color: #6b7280;
  background: #f9fafb;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  margin-bottom: 4px;
}

.url-preview strong {
  color: #1d4ed8;
}

.public-link-actions {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 4px;
}

.input {
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  font-size: 14px;
  width: 100%;
  box-sizing: border-box;
  font-family: inherit;
}

.input:focus {
  outline: none;
  border-color: #3b82f6;
}

.input-error {
  border-color: #ef4444;
}

.slug-error {
  font-size: 12px;
  color: #ef4444;
  margin-top: 4px;
}

.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.section-head-title {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
}

.counter-table-wrap {
  overflow-x: auto;
}

.counter-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
}

.counter-table th {
  text-align: left;
  padding: 10px 12px;
  font-weight: 700;
  color: #374151;
  border-bottom: 2px solid #e5e7eb;
  white-space: nowrap;
}

.counter-table td {
  padding: 8px 12px;
  border-bottom: 1px solid #f3f4f6;
  vertical-align: middle;
}

.counter-input {
  padding: 6px 10px;
  font-size: 13px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  width: 100%;
  box-sizing: border-box;
  font-family: inherit;
}

.sesiones-table {
  min-width: 700px;
}

.svc-col-name { width: 28% }
.svc-col-duration { width: 12% }
.svc-col-price { width: 15% }
.svc-col-type { width: 22% }
.svc-col-active { width: 13% }
.svc-col-actions { width: 10% }

.empty-services {
  text-align: center;
  color: #6b7280;
  padding: 24px;
}

@media (max-width: 768px) {
  .counter-table thead { display: none }
  .counter-table tr {
    display: grid;
    grid-template-columns: 1fr;
    gap: 4px;
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
  }
  .counter-table td {
    display: grid;
    grid-template-columns: 100px 1fr;
    gap: 8px;
    padding: 4px 0;
    border: none;
    align-items: center;
  }
  .counter-table td::before {
    content: attr(data-label);
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
  }
}

.professional-card-admin {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  margin-bottom: 12px;
  background: #fff;
  overflow: hidden;
}

.professional-card-admin__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 16px;
  cursor: pointer;
}

.professional-card-admin__info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.profession-badge {
  display: inline-block;
  font-size: 11px;
  font-weight: 600;
  color: #6b7280;
  background: #f3f4f6;
  padding: 1px 8px;
  border-radius: 999px;
  margin-left: 6px;
}

.professional-card-admin__detail {
  padding: 0 16px 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.accordion-chevron {
  width: 18px;
  height: 18px;
  color: #9ca3af;
  transition: transform 0.2s ease;
  flex-shrink: 0;
}

.accordion-chevron.open {
  transform: rotate(180deg);
}

.detail-section h4 {
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 700;
  color: #374151;
}

.exception-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 0;
  font-size: 13px;
}

.sched-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.sched-header h4 {
  margin: 0;
}
.sched-header .btn {
  width: 25%;
  justify-content: center;
  text-align: center;
}

.hours-table-wrap { overflow-x: auto; }
.hours-table { min-width: 100%; }
.hours-table th { padding: 8px 6px; text-align: center; font-size: 13px; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; }
.hours-row-label { font-weight: 600; color: #374151; padding-right: 12px; white-space: nowrap; font-size: 13px; }
.hours-cell-center { text-align: center; padding: 4px; }
.day-toggle { display: inline-flex; align-items: center; gap: 4px; cursor: pointer; font-size: 13px; }
.day-toggle input { width: 16px; height: 16px; cursor: pointer; }

.text-muted {
  color: #6b7280;
}

.badge {
  display: inline-block;
  font-size: 10px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  vertical-align: middle;
  margin-left: 6px;
}
.badge-booking {
  background: #dbeafe;
  color: #1d4ed8;
}
.badge-team {
  background: #fef3c7;
  color: #92400e;
}

.sub-tabs .tab-small {
  padding: 6px 14px;
  border-radius: 999px;
  border: 1px solid #e5e7eb;
  background: #fff;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
}

.sub-tabs .tab-small.active {
  background: #eff6ff;
  border-color: #3b82f6;
  color: #1d4ed8;
}

.modal-action-btn {
  min-width: 110px;
  border-radius: 9999px;
  padding: 8px 14px;
  font-size: 14px;
  font-weight: 600;
}

@media (max-width: 640px) {
  .form-row-2 {
    grid-template-columns: 1fr;
  }
}
</style>
