
<template>
  <MainLayout>
    <div class="form-wrapper">
      <div class="form-card">
        <div class="form-header">
          <div>
            <h1>{{ isEdit ? 'Editar paciente' : 'Nuevo paciente' }}</h1>
            <p class="form-sub">{{ isEdit ? 'Edita los datos del paciente.' : 'Crea un nuevo paciente para gestionar sus citas y pagos.' }}</p>
          </div>
          <button type="button" class="muted back-btn" @click.prevent="cancel">Volver</button>
        </div>

        <form class="grid-form" @submit.prevent="submit">
          <div v-if="errors.general" class="field full">
            <div class="field-error">{{ errors.general[0] }}</div>
          </div>

          <div v-if="duplicateMessage" class="field full" style="background:#fff7ed;border:1px solid #ffd8a8;padding:12px;border-radius:8px">
            <div style="font-weight:300;color:#92400e">{{ duplicateMessage }}</div>
            <div style="margin-top:8px;display:flex;gap:8px;align-items:center">
              <button type="button" class="primary" @click.prevent="goToDuplicate">Ir al paciente existente</button>
              <button type="button" class="muted" @click.prevent="(duplicateMessage='',duplicateId=null)">Ignorar</button>
            </div>
          </div>

          <div v-if="isEdit" class="field">
            <label class="label">Numero</label>
            <input :value="form.counter || '—'" type="text" class="input" readonly />
          </div>

          <div class="field">
            <label class="label">Nombre</label>
            <input v-model="form.name" type="text" class="input" />
            <div v-if="errors.name" class="field-error">{{ errors.name[0] }}</div>
          </div>

          <div class="field">
            <label class="label">NIF</label>
            <input v-model="form.nif" type="text" class="input" />
            <div v-if="errors.nif" class="field-error">{{ errors.nif[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Teléfono</label>
            <input v-model="form.phone" type="text" class="input" />
            <div v-if="errors.phone" class="field-error">{{ errors.phone[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Email</label>
            <input v-model="form.email" type="email" class="input" />
            <div v-if="errors.email" class="field-error">{{ errors.email[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Fecha de nacimiento</label>
            <input v-model="form.birth_date" type="date" class="input" />
            <div v-if="errors.birth_date" class="field-error">{{ errors.birth_date[0] }}</div>
          </div>

          <div class="field full">
            <label class="label">Dirección</label>
            <input v-model="form.address" type="text" class="input" />
            <div v-if="errors.address" class="field-error">{{ errors.address[0] }}</div>
          </div>

          <div class="field">
            <label class="label">ZIP</label>
            <input v-model="form.zip" type="text" class="input" />
            <div v-if="errors.zip" class="field-error">{{ errors.zip[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Ciudad</label>
            <input v-model="form.city" type="text" class="input" />
            <div v-if="errors.city" class="field-error">{{ errors.city[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Provincia</label>
            <input v-model="form.province" type="text" class="input" />
            <div v-if="errors.province" class="field-error">{{ errors.province[0] }}</div>
          </div>

          <div class="field">
            <label class="label">País</label>
            <input v-model="form.country" type="text" class="input" />
            <div v-if="errors.country" class="field-error">{{ errors.country[0] }}</div>
          </div>

          <div class="field full">
            <label class="label">Notas</label>
            <textarea v-model="form.notes" class="textarea" rows="4"></textarea>
            <div v-if="errors.notes" class="field-error">{{ errors.notes[0] }}</div>
          </div>

          <div class="actions full">
            <button class="primary" type="submit" :disabled="submitting">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { reactive, ref, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import { useToast } from 'vue-toastification'

const router = useRouter()
const route = useRoute()
const isEdit = ref(false)
const form = reactive({ counter: '', name: '', nif: '', phone: '', email: '', birth_date: '', address: '', zip: '', city: '', province: '', country: '', notes: '' })
const errors = reactive({})
const submitting = ref(false)
const duplicateId = ref(null)
const duplicateMessage = ref('')
const loading = ref(false)

function isValidEmailFormat(value) {
  const email = String(value || '').trim()
  if (!email) return true
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
}

function isValidBirthDate(value) {
  const birthDate = String(value || '').trim()
  if (!birthDate) return true

  const match = birthDate.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (!match) return false

  const year = Number(match[1])
  const month = Number(match[2])
  const day = Number(match[3])
  const now = new Date()

  if (year < 1900 || year > now.getFullYear()) return false
  if (month < 1 || month > 12) return false
  if (day < 1 || day > 31) return false

  const parsed = new Date(`${birthDate}T00:00:00`)
  if (Number.isNaN(parsed.getTime())) return false

  return parsed <= now
}

function extractBirthDateErrorMessage(error) {
  const data = error?.response?.data || {}
  const backendMessage = String(data.message || data.error || '')

  const validationBirthDate = Array.isArray(data.errors?.birth_date)
    ? data.errors.birth_date[0]
    : ''

  if (validationBirthDate) {
    return validationBirthDate
  }

  const looksLikeBirthDateSqlError = backendMessage.includes('Incorrect date value')
    && backendMessage.includes('birth_date')

  if (looksLikeBirthDateSqlError) {
    return 'Fecha de nacimiento inválida. Usa un formato válido (YYYY-MM-DD).'
  }

  return ''
}

function goToDuplicate() {
  if (duplicateId.value) router.push(`/patients/${duplicateId.value}`)
}

function cancel() {
  // Respetar el origen si se pasó via query
  const from = route.query.from
  const id = route.params.id
  if (from === 'show' && id) {
    router.push(`/patients/${id}`)
    return
  }
  if (from === 'list') {
    router.push('/patients')
    return
  }
  // fallback: history back o listado
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push('/patients')
  }
}

async function loadForEdit(id) {
  loading.value = true
  try {
    const res = await api.get(`/patients/${id}`)
    const data = res.data
    // Map backend shape to form fields
    form.counter = data.counter ?? ''
    form.name = data.name ?? ''
    form.nif = data.nif ?? ''
    form.phone = data.phone ?? ''
    form.email = data.email ?? ''
    form.birth_date = data.birth_date ?? ''
    form.address = data.address ?? ''
    form.zip = data.zip ?? ''
    form.city = data.city ?? ''
    form.province = data.province ?? ''
    form.country = data.country ?? ''
    form.notes = data.notes ?? ''
  } catch (e) {
    console.error('Error cargando paciente para edición', e)
    if (e.response && e.response.status === 404) router.push('/patients')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  const id = route.params.id
  if (id) {
    isEdit.value = true
    loadForEdit(id)
  }
})

// Si navegamos desde /patients/create -> /patients/:id el componente se reutiliza,
// por eso hay que observar los cambios en la ruta y recargar el paciente.
watch(() => route.params.id, (id) => {
  if (id) {
    isEdit.value = true
    duplicateMessage.value = ''
    duplicateId.value = null
    loadForEdit(id)
  } else {
    isEdit.value = false
    // limpiar formulario cuando volvemos a modo creación
    form.counter = ''
    form.name = ''
    form.nif = ''
    form.phone = ''
    form.email = ''
    form.birth_date = ''
    form.address = ''
    form.zip = ''
    form.city = ''
    form.province = ''
    form.country = ''
    form.notes = ''
    Object.keys(errors).forEach(k => delete errors[k])
  }
})

async function submit() {
  submitting.value = true
  Object.keys(errors).forEach(k => delete errors[k])
  form.email = String(form.email || '').trim()
  form.birth_date = String(form.birth_date || '').trim()

  if (!isValidEmailFormat(form.email)) {
    errors.email = ['Formato de email inválido']
    submitting.value = false
    return
  }

  if (!isValidBirthDate(form.birth_date)) {
    errors.birth_date = ['Fecha de nacimiento inválida. Usa un formato válido (YYYY-MM-DD).']
    submitting.value = false
    return
  }

  try {
      const toast = useToast()
      if (isEdit.value && route.params.id) {
        await api.put(`/patients/${route.params.id}`, { ...form })
        toast.success('Paciente actualizado')
        router.push(`/patients/${route.params.id}`)
      } else {
        const res = await api.post('/patients', { ...form })
        const createdId = Number(res?.data?.id || res?.data?.data?.id || 0)
        toast.success('Paciente creado')
        if (createdId > 0) {
          router.push(`/patients/${createdId}`)
        } else {
          router.push('/patients')
        }
      }
  } catch (e) {
    // Normalizar y manejar errores de validación y conflicto
    if (e.response) {
      console.error('API error response:', e.response)
      const status = e.response.status
      const data = e.response.data || {}

      if (status === 422) {
        // data.errors expected
        const eobj = data.errors || {}
        Object.assign(errors, eobj)

        const birthDateMessage = extractBirthDateErrorMessage(e)
        if (birthDateMessage) {
          errors.birth_date = [birthDateMessage]
        }

        // si existe un error de tipo unique en nif, también mostrar mensaje general
        if (!eobj.nif && data.message && data.message.toLowerCase().includes('nif')) {
          errors.nif = [data.message]
        }
      } else if (status === 500) {
        const birthDateMessage = extractBirthDateErrorMessage(e)
        if (birthDateMessage) {
          errors.birth_date = [birthDateMessage]
        } else {
          errors.general = [data.message || 'Error interno del servidor']
        }
      } else if (status === 409) {
        // NIF duplicado: mostrar aviso y enlace al paciente existente
        duplicateMessage.value = data.message || 'El NIF ya existe para otro paciente.'
        duplicateId.value = data?.existing?.id ?? null
      } else {
        console.error('Error guardando paciente', e)
        // mostrar mensaje general bajo errors.general
        errors.general = [data.message || 'Error desconocido']
      }
    } else {
      console.error('Error guardando paciente (sin respuesta)', e)
      errors.general = ['Error de red o servidor']
    }
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.form-wrapper { display:flex; justify-content:center; padding:24px }
.form-card { width:100%; max-width:760px; background: #fff; border-radius:12px; box-shadow: 0 10px 30px rgba(2,6,23,0.06); padding:24px }
.form-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px }
.form-header h1 { margin:0; font-size:22px }

.grid-form { display:grid; grid-template-columns: repeat(2, 1fr); gap:12px }
.grid-form .full { grid-column: 1 / -1 }
.field { display:flex; flex-direction:column }
.label { font-weight:600; margin-bottom:6px }
.input, .textarea { padding:12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px }
.textarea { resize:vertical }
.field-error { color:#b91c1c; font-size:13px; margin-top:6px }

.actions { display:flex; gap:12px; align-items:center }
.actions .muted { color:#6b7280; text-decoration:none }
.primary {
  /* Match Nuevo paciente button (outline blue, pill) */
  padding: 8px 16px;
  font-size: 14px;
  border-radius: 9999px;
  border: 2px solid #3b82f6;
  color: #3b82f6;
  background: #ffffff;
  font-weight: 600;
}
.primary:hover { background: #eff6ff }

@media (max-width: 768px) {
  .grid-form { grid-template-columns: 1fr }
}

</style>
