<template>
  <MainLayout>
    <div class="form-wrapper">
      <div class="form-card">
        <div class="form-header">
          <h1>{{ isEdit ? 'Editar usuario' : 'Nuevo usuario' }}</h1>
          <p class="form-sub">{{ isEdit ? 'Actualiza los datos del usuario.' : 'Añade un nuevo usuario al equipo.' }}</p>
        </div>

        <form class="user-form" @submit.prevent="submit">
          <div v-if="errors.general" class="field full">
            <div class="field-error">{{ errors.general[0] }}</div>
          </div>

          <!-- Datos básicos -->
          <div class="form-section">
            <h2 class="form-section-title">Datos básicos</h2>

            <div class="grid-2">
              <div class="field">
                <label class="label">Nombre</label>
                <input v-model="form.name" type="text" class="input" />
                <div v-if="errors.name" class="field-error">{{ errors.name[0] }}</div>
              </div>
              <div class="field">
                <label class="label">Email</label>
                <input v-model="form.email" type="email" class="input" />
                <div v-if="errors.email" class="field-error">{{ errors.email[0] }}</div>
              </div>
            </div>

            <div class="field">
              <label class="label">{{ isEdit ? 'Nueva contraseña (dejar vacío para mantener)' : 'Contraseña' }}</label>
              <input v-model="form.password" type="password" class="input" autocomplete="new-password" />
              <div v-if="errors.password" class="field-error">{{ errors.password[0] }}</div>
            </div>

            <div class="grid-2">
              <div class="field">
                <label class="label">Perfil</label>
                <select v-model.number="form.profile_id" class="input" :disabled="isOwner">
                  <option :value="null">Seleccionar perfil...</option>
                  <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <div v-if="errors.profile_id" class="field-error">{{ errors.profile_id[0] }}</div>
                <div v-if="isOwner" class="field-help">El perfil del propietario no se puede cambiar.</div>
              </div>
              <div class="field">
                <label class="label">Profesión</label>
                <select v-model.number="form.profession_id" class="input">
                  <option :value="null">Sin profesión</option>
                  <option v-for="p in professions" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <div v-if="errors.profession_id" class="field-error">{{ errors.profession_id[0] }}</div>
              </div>
            </div>
          </div>

          <!-- Reserva Online -->
          <div class="form-section">
            <h2 class="form-section-title">Reserva Online</h2>
            <label class="toggle-row">
              <input v-model="form.allow_online_booking" type="checkbox" />
              <span>Habilitar reserva online para este usuario</span>
            </label>
          </div>

          <!-- Horarios laborales -->
          <div class="form-section">
            <h2 class="form-section-title">Horarios laborales</h2>
            <div class="section-copy">Define los días y horarios de trabajo de este usuario. Por defecto se usan los horarios de la clínica.</div>

            <div class="hours-table-wrap">
              <table class="counter-table hours-table">
                <thead>
                  <tr>
                    <th></th>
                    <th v-for="s in schedules" :key="'head-'+s.day_of_week">{{ dayLabels[s.day_of_week] }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="hours-row-label">Trabaja</td>
                    <td v-for="s in schedules" :key="'enabled-'+s.day_of_week" class="hours-cell-center">
                      <label class="day-toggle">
                        <input v-model="s.enabled" type="checkbox" />
                        <span>{{ s.enabled ? 'Sí' : 'No' }}</span>
                      </label>
                    </td>
                  </tr>
                  <tr>
                    <td class="hours-row-label">Desde</td>
                    <td v-for="s in schedules" :key="'start-'+s.day_of_week" class="hours-cell-center">
                      <input class="input counter-input" type="time" step="300" v-model="s.start_time" :disabled="!s.enabled" />
                    </td>
                  </tr>
                  <tr>
                    <td class="hours-row-label">Hasta</td>
                    <td v-for="s in schedules" :key="'end-'+s.day_of_week" class="hours-cell-center">
                      <input class="input counter-input" type="time" step="300" v-model="s.end_time" :disabled="!s.enabled" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Días no laborables -->
          <div class="form-section">
            <h2 class="form-section-title">Días no laborables</h2>
            <div class="section-copy">Selecciona fechas puntuales en las que el usuario no estará disponible.</div>

            <div class="closed-days-grid">
              <div class="closed-card">
                <label class="label">Individual</label>
                <div class="closed-row">
                  <input v-model="newExceptionDate" class="input" type="date" />
                  <button class="btn btn-sm" type="button" @click="addException">+</button>
                </div>
                <div v-if="exceptions.length" class="chip-list">
                  <button v-for="(ex, i) in exceptions.filter(e => !e.isRange)" :key="'exc-'+i" type="button" class="chip" @click="removeException(i, false)">
                    <span>{{ formatDate(ex.date) }}</span>
                    <span aria-hidden="true">✕</span>
                  </button>
                </div>
                <div v-else class="chip-empty">No hay días individuales.</div>
              </div>

              <div class="closed-card">
                <label class="label">Rango</label>
                <div class="closed-row">
                  <input v-model="rangeStart" class="input" type="date" />
                  <input v-model="rangeEnd" class="input" type="date" />
                  <button class="btn btn-sm" type="button" @click="addExceptionRange">+</button>
                </div>
                <div v-if="exceptions.filter(e => e.isRange).length" class="chip-list">
                  <button v-for="(ex, i) in exceptions.filter(e => e.isRange)" :key="'range-'+i" type="button" class="chip" @click="removeException(i, true)">
                    <span>{{ formatDate(ex.date) }} — {{ formatDate(ex.end_date) }}</span>
                    <span aria-hidden="true">✕</span>
                  </button>
                </div>
                <div v-else class="chip-empty">No hay rangos.</div>
              </div>
            </div>
          </div>

          <div class="actions full">
            <button class="primary" type="submit" :disabled="submitting">{{ submitting ? 'Guardando...' : 'Guardar' }}</button>
            <button type="button" class="muted" @click.prevent="cancel">Cancelar</button>
          </div>
        </form>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import MainLayout from '../../layouts/MainLayout.vue'
import api from '../../services/api'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const isEdit = ref(false)
const isOwner = ref(false)
const submitting = ref(false)
const errors = reactive({})

const profiles = ref([])
const professions = ref([])

const dayLabels = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']

function defaultSchedules() {
  return [
    { day_of_week: 1, enabled: true,  start_time: '09:00', end_time: '18:00' },
    { day_of_week: 2, enabled: true,  start_time: '09:00', end_time: '18:00' },
    { day_of_week: 3, enabled: true,  start_time: '09:00', end_time: '18:00' },
    { day_of_week: 4, enabled: true,  start_time: '09:00', end_time: '18:00' },
    { day_of_week: 5, enabled: true,  start_time: '09:00', end_time: '18:00' },
    { day_of_week: 6, enabled: false, start_time: '09:00', end_time: '14:00' },
    { day_of_week: 0, enabled: false, start_time: '09:00', end_time: '14:00' },
  ]
}

const form = reactive({
  name: '',
  email: '',
  password: '',
  profile_id: null,
  profession_id: null,
  allow_online_booking: false,
})

const schedules = ref(defaultSchedules())
const exceptions = ref([])

const newExceptionDate = ref('')
const rangeStart = ref('')
const rangeEnd = ref('')

function clearErrors() {
  Object.keys(errors).forEach(k => delete errors[k])
}

async function loadFormData() {
  try {
    const [profilesRes, professionsRes] = await Promise.all([
      api.get('/team/profiles'),
      api.get('/team/professions'),
    ])
    profiles.value = profilesRes.data?.data || []
    professions.value = professionsRes.data?.data || []
  } catch (e) {
    toast.error('Error cargando datos del formulario')
  }
}

async function loadForEdit(id) {
  try {
    const res = await api.get(`/team/users/${id}`)
    const data = res.data || {}
    form.name = data.name ?? ''
    form.email = data.email ?? ''
    form.password = ''
    form.profile_id = data.profile?.id ?? null
    form.profession_id = data.profession?.id ?? null
    form.allow_online_booking = !!data.allow_online_booking
    isOwner.value = data.role === 'owner'

    if (Array.isArray(data.schedules) && data.schedules.length > 0) {
      schedules.value = data.schedules.map(s => ({
        day_of_week: s.day_of_week,
        enabled: !!s.enabled,
        start_time: s.start_time || '09:00',
        end_time: s.end_time || '18:00',
      }))
    }

    exceptions.value = (data.schedule_exceptions || []).map(e => {
      const parts = String(e.date || '').split('..')
      if (parts.length === 2) {
        return { date: toDateOnly(parts[0]), end_date: toDateOnly(parts[1]), isRange: true, reason: e.reason }
      }
      return { date: toDateOnly(parts[0]), isRange: false, reason: e.reason }
    })
  } catch (e) {
    toast.error('No se pudo cargar el usuario')
    router.push('/team/users')
  }
}

function addException() {
  if (!newExceptionDate.value) return
  exceptions.value.push({ date: newExceptionDate.value, isRange: false })
  newExceptionDate.value = ''
}

function addExceptionRange() {
  if (!rangeStart.value || !rangeEnd.value) return
  exceptions.value.push({ date: rangeStart.value, end_date: rangeEnd.value, isRange: true })
  rangeStart.value = ''
  rangeEnd.value = ''
}

function removeException(index, isRange) {
  const filtered = exceptions.value.filter(e => e.isRange === isRange)
  const realIndex = exceptions.value.indexOf(filtered[index])
  if (realIndex >= 0) exceptions.value.splice(realIndex, 1)
}

function toDateOnly(d) {
  return String(d || '').split('T')[0].split(' ')[0]
}

function formatDate(d) {
  if (!d) return ''
  const datePart = toDateOnly(d)
  if (!datePart) return ''
  const [y, m, day] = datePart.split('-')
  if (!y || !m || !day) return datePart
  return `${day}/${m}/${y}`
}

function normalizePayload() {
  const scheduleExceptions = exceptions.value.map(e => {
    if (e.isRange) {
      return { date: toDateOnly(e.date) + '..' + toDateOnly(e.end_date), reason: e.reason || null }
    }
    return { date: toDateOnly(e.date), reason: e.reason || null }
  })

  return {
    name: String(form.name || '').trim(),
    email: String(form.email || '').trim(),
    password: form.password || undefined,
    profile_id: form.profile_id || undefined,
    profession_id: form.profession_id || null,
    allow_online_booking: !!form.allow_online_booking,
    schedules: schedules.value.map(s => ({
      day_of_week: s.day_of_week,
      enabled: !!s.enabled,
      start_time: s.enabled ? s.start_time : null,
      end_time: s.enabled ? s.end_time : null,
    })),
    schedule_exceptions: scheduleExceptions,
  }
}

async function submit() {
  clearErrors()
  submitting.value = true

  try {
    const payload = normalizePayload()

    if (isEdit.value) {
      await api.put(`/team/users/${route.params.id}`, payload)
      toast.success('Usuario actualizado')
      router.push('/team/users')
    } else {
      await api.post('/team/users', payload)
      toast.success('Usuario creado')
      router.push('/team/users')
    }
  } catch (e) {
    if (e.response?.status === 422) {
      Object.assign(errors, e.response?.data?.errors || {})
      if (!Object.keys(errors).length) {
        errors.general = [e.response?.data?.message || 'Error de validación']
      }
    } else {
      errors.general = [e.response?.data?.message || 'Error guardando usuario']
    }
  } finally {
    submitting.value = false
  }
}

function cancel() {
  router.push('/team/users')
}

onMounted(async () => {
  await loadFormData()

  if (route.params.id) {
    isEdit.value = true
    await loadForEdit(route.params.id)
  }
})
</script>

<style scoped>
.form-wrapper { display: flex; justify-content: center; padding: 24px; }
.form-card { width: 100%; max-width: 860px; background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(2,6,23,0.06); padding: 24px; }
.form-header h1 { margin: 0; font-size: 22px; }
.form-section { margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
.form-section-title { font-size: 16px; font-weight: 700; margin: 0 0 12px; color: #111827; }
.section-copy { color: #6b7280; font-size: 14px; margin-bottom: 14px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field { display: flex; flex-direction: column; margin-bottom: 10px; }
.field.full { grid-column: 1 / -1; }
.label { font-weight: 600; margin-bottom: 6px; color: #374151; }
.input { padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; background: #fff; }
.input:disabled { background: #f3f4f6; color: #6b7280; }
select.input { cursor: pointer; }
.field-error { color: #b91c1c; font-size: 13px; margin-top: 4px; }
.field-help { color: #6b7280; font-size: 12px; margin-top: 4px; }

.toggle-row { display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 14px; color: #374151; }
.toggle-row input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }

/* Horarios */
.hours-table-wrap { overflow-x: auto; }
.hours-table { min-width: 100%; }
.hours-table th { padding: 8px 6px; text-align: center; font-size: 13px; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; }
.hours-row-label { font-weight: 600; color: #374151; padding-right: 12px; white-space: nowrap; font-size: 13px; }
.hours-cell-center { text-align: center; padding: 4px; }
.day-toggle { display: inline-flex; align-items: center; gap: 4px; cursor: pointer; font-size: 13px; }
.day-toggle input { width: 16px; height: 16px; cursor: pointer; }
.counter-input { width: 100px; padding: 6px 8px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 13px; text-align: center; }

/* Días no laborables */
.closed-days-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.closed-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; }
.closed-row { display: flex; gap: 8px; align-items: center; margin-top: 8px; flex-wrap: wrap; }
.closed-row .input { flex: 1; min-width: 0; }
.chip-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.chip {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 4px 10px; border-radius: 999px; border: 1px solid #e5e7eb;
  background: #fff; font-size: 12px; cursor: pointer; color: #374151;
}
.chip:hover { border-color: #fca5a5; color: #b91c1c; }
.chip-empty { color: #9ca3af; font-size: 13px; margin-top: 8px; }

.actions { display: flex; gap: 12px; align-items: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb; }
.primary {
  padding: 8px 16px; font-size: 14px; border-radius: 9999px;
  border: 2px solid #3b82f6; color: #3b82f6; background: #fff; font-weight: 600; cursor: pointer;
}
.primary:hover { background: #eff6ff; }
.primary:disabled { opacity: 0.5; cursor: not-allowed; }
.muted {
  padding: 8px 16px; font-size: 14px; border-radius: 9999px;
  border: 1px solid #d1d5db; color: #374151; background: #fff; font-weight: 600; cursor: pointer;
}

@media (max-width: 768px) {
  .grid-2 { grid-template-columns: 1fr; }
  .closed-days-grid { grid-template-columns: 1fr; }
}
</style>
