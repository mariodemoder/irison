<template>
  <MainLayout>
    <div class="consent-templates">
      <div class="page-header">
        <h1>Plantillas de Consentimientos</h1>
        <div class="header-actions">
          <button class="help-btn" @click="showHelp = true" title="Ayuda">?</button>
          <button v-if="!isProfessional" class="primary" @click="goCreate">Nueva plantilla</button>
        </div>
      </div>

      <div v-if="!loading && templates.length === 0" class="empty-card">
        No hay plantillas. Crea la primera.
      </div>

      <div v-if="loading" class="loading-card">Cargando...</div>

      <HelpModal v-if="showHelp" @close="showHelp = false" />

      <div v-if="templates.length > 0" class="table-wrap">
        <table class="entity-table">
          <thead>
            <tr>
              <th>Título</th>
              <th>Categoría</th>
              <th>Versión</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in templates" :key="t.id">
              <td>{{ t.title }}</td>
              <td>{{ t.category?.name ?? '—' }}</td>
              <td>{{ t.version }}</td>
              <td>
                <span class="status-badge" :class="t.status">{{ t.status }}</span>
              </td>
              <td class="actions-cell">
                <button class="action-btn" @click="goEdit(t.id)">Editar</button>
                <button class="action-btn danger" @click="remove(t)">Eliminar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import { isProfessional } from '../../shared/meCache'
import HelpModal from '../../components/consents/HelpModal.vue'

const router = useRouter()
const showHelp = ref(false)
const toast = useToast()
const templates = ref([])
const loading = ref(true)

onMounted(fetch)

async function fetch() {
  loading.value = true
  try {
    const res = await api.get('/consent-templates')
    templates.value = res.data.data
  } catch (e) {
    toast.error('Error al cargar plantillas')
  } finally {
    loading.value = false
  }
}

function goCreate() {
  router.push('/consent-templates/create')
}

function goEdit(id) {
  router.push(`/consent-templates/${id}/edit`)
}

async function remove(t) {
  if (!confirm(`¿Eliminar "${t.title}"?`)) return
  try {
    await api.delete(`/consent-templates/${t.id}`)
    templates.value = templates.value.filter(x => x.id !== t.id)
    toast.success('Plantilla eliminada')
  } catch (e) {
    toast.error('Error al eliminar')
  }
}
</script>

<style scoped>
.consent-templates { padding: 24px; max-width: 960px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.page-header h1 { font-size: 20px; font-weight: 700; color: #1f2937; }
.table-wrap { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); overflow: hidden; }
.entity-table { width: 100%; border-collapse: collapse; }
.entity-table th { text-align: left; padding: 12px 16px; font-size: 12px; font-weight: 600; color: #6b7280; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
.entity-table td { padding: 12px 16px; font-size: 14px; border-bottom: 1px solid #f3f4f6; }
.status-badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 500; }
.status-badge.active { background: #d1fae5; color: #065f46; }
.status-badge.inactive { background: #f3f4f6; color: #6b7280; }
.actions-cell { display: flex; gap: 8px; }
.action-btn { padding: 4px 12px; border-radius: 6px; border: 1px solid #d1d5db; background: #fff; cursor: pointer; font-size: 13px; }
.action-btn.danger { color: #dc2626; }
.header-actions { display: flex; gap: 8px; align-items: center; }
.help-btn { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #d1d5db; background: #fff; cursor: pointer; font-size: 16px; font-weight: 700; color: #6b7280; display: flex; align-items: center; justify-content: center; line-height: 1; }
.help-btn:hover { background: #f3f4f6; color: #374151; }
.loading-card, .empty-card { padding: 48px; text-align: center; color: #6b7280; }
</style>
