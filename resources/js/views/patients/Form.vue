
<template>
  <MainLayout>
    <div class="form-wrapper">
      <div class="form-card">
        <div class="form-header">
          <h1>{{ isEdit ? 'Editar paciente' : 'Nuevo paciente' }}</h1>
            <p class="form-sub">{{ isEdit ? 'Edita los datos del paciente.' : 'Crea un nuevo paciente para gestionar sus citas y pagos.' }}</p>
        </div>

        <form class="grid-form" @submit.prevent="submit">
          <div v-if="duplicateMessage" class="field full" style="background:#fff7ed;border:1px solid #ffd8a8;padding:12px;border-radius:8px">
            <div style="font-weight:300;color:#92400e">{{ duplicateMessage }}</div>
            <div style="margin-top:8px;display:flex;gap:8px;align-items:center">
              <button type="button" class="primary" @click.prevent="goToDuplicate">Ir al paciente existente</button>
              <button type="button" class="muted" @click.prevent="(duplicateMessage='',duplicateId=null)">Ignorar</button>
            </div>
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

          <div class="field full">
            <label class="label">Notas</label>
            <textarea v-model="form.notes" class="textarea" rows="4"></textarea>
            <div v-if="errors.notes" class="field-error">{{ errors.notes[0] }}</div>
          </div>

          <div class="actions full">
            <button class="primary" type="submit" :disabled="submitting">Guardar</button>
            <router-link to="/patients" class="muted">Cancelar</router-link>
          </div>
        </form>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'

const router = useRouter()
const route = useRoute()
const isEdit = ref(false)
const form = reactive({ name: '', nif: '', phone: '', email: '', notes: '' })
const errors = reactive({})
const submitting = ref(false)
const duplicateId = ref(null)
const duplicateMessage = ref('')
const loading = ref(false)

function goToDuplicate() {
  if (duplicateId.value) router.push(`/patients/${duplicateId.value}/edit`)
}

async function loadForEdit(id) {
  loading.value = true
  try {
    const res = await api.get(`/patients/${id}`)
    const data = res.data
    // Map backend shape to form fields
    form.name = data.name ?? ''
    form.nif = data.nif ?? ''
    form.phone = data.phone ?? ''
    form.email = data.email ?? ''
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

async function submit() {
  submitting.value = true
  Object.keys(errors).forEach(k => delete errors[k])
  try {
    if (isEdit.value && route.params.id) {
      await api.put(`/patients/${route.params.id}`, { ...form })
    } else {
      await api.post('/patients', { ...form })
    }
    router.push('/patients')
  } catch (e) {
    if (e.response && e.response.status === 422) {
      const eobj = e.response.data.errors || {}
      Object.assign(errors, eobj)
    } else if (e.response && e.response.status === 409) {
      // NIF duplicado: mostrar aviso y enlace al paciente existente
      duplicateMessage.value = e.response.data.message || 'El NIF ya existe para otro paciente.'
      duplicateId.value = e.response?.data?.existing?.id ?? null
    } else {
      console.error('Error guardando paciente', e)
    }
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.form-wrapper { display:flex; justify-content:center; padding:24px }
.form-card { width:100%; max-width:760px; background: #fff; border-radius:12px; box-shadow: 0 10px 30px rgba(2,6,23,0.06); padding:24px }
.form-header h1 { margin:0; font-size:22px }
.form-sub { color:#6b7280; font-size:13px; margin-top:6px }

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
