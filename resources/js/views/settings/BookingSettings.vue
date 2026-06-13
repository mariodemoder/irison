<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api'
import { useToast } from 'vue-toastification'
import SaveButton from '../../components/SaveButton.vue'

const toast = useToast()
const localLoading = ref(false)
const saving = ref(false)
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
const schedules = ref({})
const exceptions = ref({})

const editingService = ref(null)
const editingProfessional = ref(null)

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
  } catch (e) {
    toast.error('Error al cargar configuración de reserva online.')
  } finally {
    localLoading.value = false
  }
}

async function saveSettings() {
  saving.value = true
  try {
    await api.put('/booking/settings', settings.value)
    toast.success('Configuración guardada.')
  } catch (e) {
    toast.error('Error al guardar.')
  } finally {
    saving.value = false
  }
}

async function toggleService(service) {
  try {
    const res = await api.put(`/booking/services/${service.id}`, {
      is_active: !service.is_active,
    })
    Object.assign(service, res.data.data)
  } catch {
    toast.error('Error al actualizar servicio.')
  }
}

async function deleteService(service) {
  if (!confirm('¿Eliminar este servicio?')) return
  try {
    await api.delete(`/booking/services/${service.id}`)
    services.value = services.value.filter(s => s.id !== service.id)
    toast.success('Servicio eliminado.')
  } catch {
    toast.error('Error al eliminar.')
  }
}

async function saveService() {
  try {
    if (editingService.value.id) {
      const res = await api.put(`/booking/services/${editingService.value.id}`, editingService.value)
      const idx = services.value.findIndex(s => s.id === editingService.value.id)
      if (idx >= 0) services.value[idx] = res.data.data
    } else {
      const res = await api.post('/booking/services', editingService.value)
      services.value.push(res.data.data)
    }
    editingService.value = null
    toast.success('Servicio guardado.')
  } catch (e) {
    toast.error('Error al guardar servicio.')
  }
}

function openNewService() {
  editingService.value = { name: '', description: '', duration_minutes: 60, price: null, is_active: true }
}

function openEditService(service) {
  editingService.value = { ...service }
}

function cancelEditService() {
  editingService.value = null
}

async function toggleProfessional(bp) {
  try {
    const res = await api.put(`/booking/professionals/${bp.id}`, {
      allow_online_booking: !bp.allow_online_booking,
    })
    Object.assign(bp, res.data.data)
  } catch {
    toast.error('Error al actualizar profesional.')
  }
}

async function loadSchedules(professionalId) {
  try {
    const res = await api.get(`/booking/professionals/${professionalId}/schedules`)
    schedules.value[professionalId] = res.data.data || []
  } catch {
    schedules.value[professionalId] = []
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
  if (editingProfessional.value === bp.id) {
    editingProfessional.value = null
  } else {
    editingProfessional.value = bp.id
    loadSchedules(bp.id)
    loadExceptions(bp.id)
  }
}

async function saveSchedule(professionalId, schedule) {
  try {
    if (schedule.id) {
      const res = await api.put(`/booking/professionals/${professionalId}/schedules/${schedule.id}`, schedule)
      const idx = schedules.value[professionalId].findIndex(s => s.id === schedule.id)
      if (idx >= 0) schedules.value[professionalId][idx] = res.data.data
    } else {
      const res = await api.post(`/booking/professionals/${professionalId}/schedules`, schedule)
      schedules.value[professionalId].push(res.data.data)
    }
    toast.success('Horario guardado.')
  } catch (e) {
    toast.error('Error al guardar horario.')
  }
}

async function deleteSchedule(professionalId, scheduleId) {
  try {
    await api.delete(`/booking/professionals/${professionalId}/schedules/${scheduleId}`)
    schedules.value[professionalId] = schedules.value[professionalId].filter(s => s.id !== scheduleId)
  } catch {
    toast.error('Error al eliminar horario.')
  }
}

const newSchedule = ref(null)

function addSchedule(professionalId) {
  newSchedule.value = { professionalId, day_of_week: 1, start_time: '09:00', end_time: '17:00' }
}

function cancelNewSchedule() {
  newSchedule.value = null
}

async function saveNewSchedule() {
  if (!newSchedule.value) return
  await saveSchedule(newSchedule.value.professionalId, {
    day_of_week: newSchedule.value.day_of_week,
    start_time: newSchedule.value.start_time,
    end_time: newSchedule.value.end_time,
  })
  newSchedule.value = null
}

const dayNames = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo']

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
          <input v-model="settings.slug" class="input" placeholder="mi-clinica" />
        </div>
        <div class="form-group">
          <label>Título</label>
          <input v-model="settings.title" class="input" placeholder="Reserva tu cita" />
        </div>
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label>Activo</label>
          <select v-model.number="settings.is_active" class="input">
            <option :value="1">Activado</option>
            <option :value="0">Desactivado</option>
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
      <SaveButton style="margin-top:12px" label="Guardar configuración" :saving="saving" @click="saveSettings" />
    </div>

    <!-- Services -->
    <div v-show="activeSubTab==='services'">
      <div class="service-actions" style="display:flex;justify-content:flex-end;margin-bottom:12px;">
        <button class="btn btn-sm small" @click="openNewService">+ Nuevo servicio</button>
      </div>

      <table class="entity-table" style="min-width:auto;">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Duración</th>
            <th>Precio</th>
            <th>Activo</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="svc in services" :key="svc.id" class="entity-table-row">
            <td>{{ svc.name }}</td>
            <td>{{ svc.duration_minutes }} min</td>
            <td>{{ svc.price ? Number(svc.price).toFixed(2) + '€' : '—' }}</td>
            <td>
              <button class="toggle-btn" :class="{ active: svc.is_active }" @click="toggleService(svc)">
                {{ svc.is_active ? 'Sí' : 'No' }}
              </button>
            </td>
            <td>
              <button class="btn btn-sm small" @click="openEditService(svc)">Editar</button>
              <button class="btn btn-sm small warning" @click="deleteService(svc)">Eliminar</button>
            </td>
          </tr>
          <tr v-if="services.length === 0">
            <td colspan="5" style="text-align:center;color:#6b7280;padding:24px;">No hay servicios configurados.</td>
          </tr>
        </tbody>
      </table>

      <!-- Service form modal -->
      <div v-if="editingService" class="modal-overlay" @click.self="cancelEditService">
        <div class="modal-card">
          <h3>{{ editingService.id ? 'Editar servicio' : 'Nuevo servicio' }}</h3>
          <div class="modal-body">
            <div class="form-group">
              <label>Nombre</label>
              <input v-model="editingService.name" class="input" placeholder="Ej: Sesión de fisioterapia" />
            </div>
            <div class="form-group">
              <label>Descripción</label>
              <textarea v-model="editingService.description" class="input" rows="2" placeholder="Descripción opcional" />
            </div>
            <div class="form-row-2">
              <div class="form-group">
                <label>Duración (minutos)</label>
                <input v-model.number="editingService.duration_minutes" type="number" class="input" min="5" max="480" />
              </div>
              <div class="form-group">
                <label>Precio (€)</label>
                <input v-model.number="editingService.price" type="number" step="0.01" min="0" class="input" placeholder="0" />
              </div>
            </div>
          </div>
          <div class="modal-actions">
            <button class="btn--ghost modal-action-btn" @click="cancelEditService">Cancelar</button>
            <SaveButton class="modal-action-btn" @click="saveService" />
          </div>
        </div>
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
            <span class="text-muted" style="font-size:12px;">{{ bp.user?.email }}</span>
          </div>
          <button class="toggle-btn" :class="{ active: bp.allow_online_booking }" @click.stop="toggleProfessional(bp)">
            {{ bp.allow_online_booking ? 'Online' : 'Offline' }}
          </button>
        </div>

        <div v-if="editingProfessional === bp.id" class="professional-card-admin__detail">
          <div class="detail-section">
            <h4>Horario semanal</h4>
            <div v-for="sched in (schedules[bp.id] || [])" :key="sched.id" class="schedule-row">
              <span>{{ dayNames[sched.day_of_week] || 'Día ' + sched.day_of_week }}</span>
              <span>{{ sched.start_time }} - {{ sched.end_time }}</span>
              <button class="btn btn-sm small warning" @click="deleteSchedule(bp.id, sched.id)">×</button>
            </div>
            <div v-if="(schedules[bp.id] || []).length === 0" class="text-muted" style="font-size:13px;margin:8px 0;">
              Sin horarios configurados.
            </div>

            <div v-if="newSchedule?.professionalId === bp.id" class="schedule-form">
              <select v-model.number="newSchedule.day_of_week" class="input" style="width:auto;">
                <option v-for="(name, i) in dayNames" :key="i" :value="i" :disabled="i===0">{{ name }}</option>
              </select>
              <input v-model="newSchedule.start_time" type="time" class="input" style="width:auto;" />
              <input v-model="newSchedule.end_time" type="time" class="input" style="width:auto;" />
              <button class="btn btn-sm" @click="saveNewSchedule">✓</button>
              <button class="btn btn-sm small warning" @click="cancelNewSchedule">×</button>
            </div>

            <button v-if="!newSchedule || newSchedule.professionalId !== bp.id" class="btn btn-sm" @click="addSchedule(bp.id)" style="margin-top:8px;">
              + Añadir horario
            </button>
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

.toggle-btn {
  padding: 4px 12px;
  border-radius: 999px;
  border: 1px solid #d1d5db;
  background: #fff;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  font-family: inherit;
}

.toggle-btn.active {
  background: #22c55e;
  color: #fff;
  border-color: #22c55e;
}

.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}

.modal-card {
  background: #fff;
  border-radius: 16px;
  padding: 24px;
  width: min(480px, calc(100% - 32px));
  max-height: 80vh;
  overflow-y: auto;
}

.modal-card h3 {
  margin: 0 0 16px;
  font-size: 16px;
  font-weight: 700;
}

.modal-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-bottom: 16px;
}

.modal-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
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

.professional-card-admin__detail {
  padding: 0 16px 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-section h4 {
  margin: 0 0 8px;
  font-size: 13px;
  font-weight: 700;
  color: #374151;
}

.schedule-row,
.exception-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 0;
  font-size: 13px;
}

.schedule-form {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
  margin-top: 8px;
}

.text-muted {
  color: #6b7280;
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
