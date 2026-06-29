<template>
  <MainLayout>
    <div class="consent-form">
      <div class="form-card">
        <div class="form-header">
          <h1>{{ isEditing ? 'Editar plantilla' : 'Nueva plantilla' }}</h1>
        </div>

        <div class="form-body">
          <label class="field">
            <span>Título</span>
            <input v-model="form.title" class="input" placeholder="Ej: Consentimiento informado general" />
          </label>

          <label class="field">
            <span>Categoría</span>
            <select v-model="form.category_id" class="input">
              <option :value="null">Sin categoría</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </label>

          <label class="field">
            <span>Descripción</span>
            <textarea v-model="form.description" class="input" rows="2" placeholder="Breve descripción opcional"></textarea>
          </label>

          <label class="field">
            <span>Estado</span>
            <select v-model="form.status" class="input">
              <option value="active">Activo</option>
              <option value="inactive">Inactivo</option>
            </select>
          </label>

          <label class="field">
            <span>Contenido</span>
            <div class="content-help">
              Variables disponibles: <code>{paciente_nombre}</code>, <code>{paciente_apellidos}</code>, <code>{dni}</code>,
              <code>{telefono}</code>, <code>{email}</code>, <code>{fecha}</code>, <code>{profesional}</code>,
              <code>{clinica}</code>, <code>{tratamiento}</code>, <code>{especialidad}</code>
            </div>
            <textarea v-model="form.content" class="input content-editor" rows="18" placeholder="Escribe el contenido aquí..."></textarea>
          </label>

          <div class="form-actions">
            <button class="muted" @click="goBack">Cancelar</button>
            <SaveButton :saving="saving" @click="save" />
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import SaveButton from '../../components/SaveButton.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const isEditing = computed(() => !!route.params.id)
const categories = ref([])
const saving = ref(false)

const form = ref({
  title: '',
  category_id: null,
  description: '',
  content: '',
  status: 'active',
})

onMounted(async () => {
  try {
    const catRes = await api.get('/consent-categories')
    categories.value = catRes.data.data
  } catch (_) {}

  if (isEditing.value) {
    try {
      const res = await api.get(`/consent-templates/${route.params.id}`)
      const t = res.data.data
      form.value = {
        title: t.title,
        category_id: t.category_id,
        description: t.description || '',
        content: t.content,
        status: t.status,
      }
    } catch (_) {
      toast.error('Error al cargar plantilla')
      router.push('/consent-templates')
    }
  }
})

async function save() {
  if (!form.value.title || !form.value.content) {
    toast.warning('Completa título y contenido')
    return
  }
  saving.value = true
  try {
    if (isEditing.value) {
      await api.put(`/consent-templates/${route.params.id}`, form.value)
      toast.success('Plantilla actualizada')
    } else {
      await api.post('/consent-templates', form.value)
      toast.success('Plantilla creada')
    }
    router.push('/consent-templates')
  } catch (e) {
    toast.error('Error al guardar')
  } finally {
    saving.value = false
  }
}

function goBack() {
  router.push('/consent-templates')
}
</script>

<style scoped>
.consent-form { padding: 24px; max-width: 800px; margin: 0 auto; }
.form-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 24px; }
.form-header h1 { font-size: 20px; font-weight: 700; margin-bottom: 24px; }
.form-body { display: flex; flex-direction: column; gap: 16px; }
.field { display: flex; flex-direction: column; gap: 4px; }
.field span { font-size: 13px; font-weight: 600; color: #374151; }
.input { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; }
.input:focus { border-color: #4338ca; box-shadow: 0 0 0 2px rgba(67,56,202,.15); }
textarea.input { resize: vertical; font-family: inherit; }
.content-help { font-size: 12px; color: #6b7280; line-height: 1.6; }
.content-help code { background: #f3f4f6; padding: 1px 4px; border-radius: 3px; font-size: 11px; }
.form-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 8px; }
</style>
