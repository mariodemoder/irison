<template>
  <MainLayout>
    <div>
      <div class="entity-card">
        <div class="page-header">
          <div>
            <h1>Pacientes</h1>
            <div class="form-sub">Listado de pacientes</div>
          </div>

          <div class="search-center">
            <div class="search-wrapper">
              <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
              <input v-model="query" placeholder="Buscar pacientes por numero, nombre, NIF, teléfono o email" class="search-input" />
            </div>
          </div>

          <NewButton v-if="!isProfessional" label="Nuevo paciente" to="/patients/create" />
        </div>

        <AppLoading v-if="loading" message="Cargando pacientes..." />

        <div v-else>
          <div v-if="filteredPatients.length > 0" class="entity-table-wrap">
            <table class="entity-table">
              <thead>
                <tr>
                  <th class="wide-min">Número</th>
                  <th class="wide-max">Nombre</th>
                  <th class="wide-min">Alta</th>
                  <th class="wide-min">Teléfono</th>
                  <th class="wide-mid">Email</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="p in filteredPatients"
                  :key="p.id"
                  class="entity-table-row"
                  role="button"
                  tabindex="0"
                  @click="goToPatient(p.id)"
                  @keydown.enter="goToPatient(p.id)"
                >
                  <td class="row-number wide-min">{{ p.counter ?? '—' }}</td>
                  <td class="wide-max">
                    <div class="row-name">{{ p.nif ?? '—' }} - {{ p.name }}</div>
                  </td>
                  <td class="wide-min">{{ formatDateOnlyDay(p.created_at) }}</td>
                  <td class="wide-min">{{ p.phone ?? '—' }}</td>
                  <td class="wide-mid">{{ p.email ?? '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <EmptyIndexState
            v-else-if="!hasActiveFilters"
            title="No hay pacientes todavía"
            subtitle="Empieza creando tu primer paciente para gestionar citas y pagos."
          />
          <div v-else class="empty">No hay resultados para los filtros aplicados.</div>

          <div v-if="meta" class="pagination">
            <div class="pagination-info">Página {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} pacientes</div>
            <div class="pagination-actions">
              <button :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)" class="icon-btn" aria-label="Anterior">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
              </button>
              <button :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)" class="icon-btn" aria-label="Siguiente">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
              </button>
            </div>
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
import AppLoading from '../../components/AppLoading.vue'
import EmptyIndexState from '../../components/EmptyIndexState.vue'
import { formatDateOnlyDay } from '../../shared/dateHelpers'
import { isProfessional } from '../../shared/meCache'
import { confirmDelete } from '../../shared/confirmDelete'

const patients = ref([])
const meta = ref(null)
const loading = ref(false)
const query = ref('')
const deletingId = ref(null)

const route = useRoute()
const router = useRouter()
const toast = useToast()

const filteredPatients = computed(() => {
  const q = (query.value || '').toLowerCase().trim()
  const hasCreditFilter = String(route.query.has_credit || '') === '1'

  return patients.value.filter((p) => {
    const matchesCredit = !hasCreditFilter || Number(p?.available_credit || 0) > 0

    if (!matchesCredit) {
      return false
    }

    if (!q) {
      return true
    }

    return [p.counter, p.name, p.nif, p.phone, p.email].some((f) => f && String(f).toLowerCase().includes(q))
  })
})

const hasActiveFilters = computed(() => {
  const q = String(query.value || '').trim()
  const hasCreditFilter = String(route.query.has_credit || '') === '1'
  return Boolean(q) || hasCreditFilter
})

async function load(page = 1) {
  loading.value = true
  try {
    const res = await api.get('/patients', { params: { per_page: 15, page } })
    patients.value = res.data.data
    meta.value = res.data.meta
  } catch (e) {
    console.error('Error cargando pacientes', e)
  } finally {
    loading.value = false
  }
}

onMounted(() => load())

// Mostrar mensajes pasados por query (p.e. after create/update/delete)
onMounted(() => {
  const m = route.query.msg
  if (m) {
    toast.success(String(m))
    // limpiar query sin recargar
    router.replace({ query: Object.assign({}, route.query, { msg: undefined }) })
  }
})

async function deletePatient(p) {
  if (deletingId.value) return

  const confirmed = await confirmDelete({
    title: 'Eliminar paciente',
    text: `¿Eliminar al paciente "${p.counter ? `${p.counter} · ` : ''}${p.name}"? Esta acción es reversible (soft delete).`,
  })

  if (!confirmed) return

  deletingId.value = p.id
  try {
    await api.delete(`/patients/${p.id}`)
    toast.warning('Paciente eliminado')
    // recargar página actual de listado
    load(meta.value?.current_page || 1)
  } catch (e) {
    const msg = e.response?.data?.message || 'Error eliminando paciente'
    toast.error(msg)
  } finally {
    deletingId.value = null
  }
}

function goToPatient(id) {
  router.push(`/patients/${id}`)
}
</script>

<style scoped>
/* Button/search/entity-table styles are now global in resources/css/app.css */

.page-header { display:grid; grid-template-columns: 1fr 480px auto; align-items:center; gap:12px; margin-bottom:16px }

.row-name { font-weight:600; font-size:15px }
.row-number { font-weight:600 }
.empty { color:#6b7280; padding:12px; text-align:center }

.pagination { margin-top:12px; display:flex; justify-content:flex-end; gap:12px; align-items:center }
.pagination-info { color:#6b7280; font-size:13px }
.pagination-actions { display:flex; gap:8px }
.icon-btn { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; cursor:pointer }
.icon-btn svg { width:18px; height:18px; color:#374151 }
.icon-btn:disabled { opacity:0.45; cursor:default }

@media (max-width: 900px) {
  .page-header { grid-template-columns: 1fr auto }
}
</style>

