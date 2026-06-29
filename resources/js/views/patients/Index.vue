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

          <router-link v-if="!isProfessional" to="/patients/create" class="btn btn-sm small">Nuevo paciente</router-link>
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
                  <th v-if="!isProfessional" class="patients-action-col"></th>
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
                  <td v-if="!isProfessional" class="row-action patients-action-col">
                    <router-link :to="{ path: `/patients/${p.id}/edit`, query: { from: 'list' } }" class="action-btn datos" aria-label="Datos" @click.stop>✎ Editar</router-link>
                  </td>
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
import Swal from 'sweetalert2'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import EmptyIndexState from '../../components/EmptyIndexState.vue'
import { formatDateOnlyDay } from '../../shared/dateHelpers'
import { isProfessional } from '../../shared/meCache'

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

  const res = await Swal.fire({
    title: `Eliminar paciente`,
    text: `¿Eliminar al paciente "${p.counter ? `${p.counter} · ` : ''}${p.name}"? Esta acción es reversible (soft delete).`,
    icon: 'warning',
    iconColor: '#f97316',
    width: '420px',
    buttonsStyling: false,
    customClass: {
      popup: 'swal-popup-warning-card',
      confirmButton: 'app-btn app-btn-warning',
      cancelButton: 'app-btn app-btn-muted',
      actions: 'swal-actions'
    },
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  })

  if (!res.isConfirmed) return

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

.row-left { display:flex; flex-direction:column }
.row-name { font-weight:600; font-size:15px }
.row-sub { color:#6b7280; font-size:13px }
.row-number { font-weight:600 }
.row-action { text-align:left }
.patients-action-col { width:130px }
.empty { color:#6b7280; padding:12px; text-align:center }

.action-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:8px; text-decoration:none; color:#374151; font-size:13px; border:1px solid transparent }
.action-btn.history { background:#eef2ff; border-color: #dbeafe; color:#1e3a8a }
.action-btn.datos { background:#fff; border-color:#e5e7eb; color:#374151 }
.action-btn.danger { background:#fff1f2; border-color:#fecdd3; color:#be123c }
.action-btn:hover { transform:translateY(-1px) }

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

<!-- Global styles for SweetAlert buttons (not scoped so they apply to the modal) -->
<style>
/* SweetAlert modal styled to match app buttons and spacing */
.app-btn { display:inline-flex; align-items:center; justify-content:center; padding:8px 14px; border-radius:9999px; border:2px solid transparent; font-weight:600; cursor:pointer; font-size:13px; box-shadow:none }
.app-btn-warning { border-color:#f97316; background:#f97316; color:#ffffff }
.app-btn-warning:hover { background:#ef7a1e }
.app-btn-muted { border-color:#e5e7eb; color:#374151; background:#ffffff }
.swal-actions { display:flex; gap:8px; justify-content:center; margin-top:12px }

/* Popup container and typography to match app */
.swal2-popup { font-family: inherit; border-radius:12px; padding:18px; box-shadow: 0 10px 30px rgba(2,6,23,0.08); }
.swal2-title { font-size:16px; font-weight:700; color:#111827; margin-bottom:6px }
.swal2-content { color:#ffffff; font-size:14px; margin-bottom:6px }
.swal2-icon { margin: 0 auto 8px }
.swal2-actions .app-btn { min-width:110px }
</style>
