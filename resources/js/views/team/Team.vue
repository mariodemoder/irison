<template>
  <MainLayout>
    <div>
      <div class="page-header">
        <div>
          <h1>Equipo</h1>
          <div class="form-sub">Administra los usuarios, perfiles y profesiones de tu clínica</div>
        </div>
      </div>

      <AppLoading v-if="loading" message="Cargando..." />

      <div v-else>
        <div class="profile-container">
          <div class="tabs">
            <router-link :class="['tab', { active: activeTab === 'users' }]" to="/team/users">Usuarios</router-link>
            <router-link :class="['tab', { active: activeTab === 'professions' }]" to="/team/professions">Profesiones</router-link>
          </div>

          <div class="profile-shell">
            <div class="card-stage">

            <div v-show="activeTab === 'users'">
            <div class="team-section-head">
              <div class="search-center">
                <div class="search-wrapper">
                  <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                  <input v-model="userQuery" class="search-input" placeholder="Buscar usuario..." @input="debouncedLoadUsers" />
                </div>
              </div>
              <router-link to="/team/users/create" class="btn btn-sm small btn-nuevo-usuario">+ Nuevo usuario</router-link>
            </div>

            <div v-if="usersLoading" style="text-align:center;padding:24px;color:#6b7280;">Cargando usuarios...</div>

            <template v-else>
              <EntityTable v-if="users.length > 0" :columns="userColumns" table-class="users-table">
                <tr 
                  v-for="u in users" 
                  :key="u.id" 
                  class="entity-table-row"
                  :style="u.role !== 'owner' ? 'cursor: pointer;' : ''"
                  @click="u.role !== 'owner' ? $router.push(`/team/users/${u.id}/edit`) : null"
                >
                  <td class="col-min">{{ u.id }}</td>
                  <td class="col-mid name-col">{{ u.name }}</td>
                  <td class="col-mid">{{ u.email }}</td>
                  <td class="col-min">{{ u.profile?.name || '—' }}</td>
                  <td class="col-min">{{ u.profession?.name || '—' }}</td>
                  <td class="col-min">
                    <span :class="u.allow_online_booking ? 'badge badge-on' : 'badge badge-off'">
                      {{ u.allow_online_booking ? 'Sí' : 'No' }}
                    </span>
                  </td>
                  <td class="col-min">
                    <span :class="u.allow_manage_agenda ? 'badge badge-on' : 'badge badge-off'">
                      {{ u.allow_manage_agenda ? 'Sí' : 'No' }}
                    </span>
                  </td>
                  <td class="row-action users-action-col">
                    <BtnTrash v-if="u.role !== 'owner'" @click.stop="deleteUser(u)" />
                  </td>
                </tr>
              </EntityTable>

              <EmptyIndexState v-else />

              <div v-if="userMeta" class="pagination">
                <div class="pagination-info">Página {{ userMeta.current_page }} / {{ userMeta.last_page }} — {{ userMeta.total }} usuarios</div>
                <div class="pagination-actions">
                  <button :disabled="userMeta.current_page <= 1" @click="loadUsers(userMeta.current_page - 1)" class="icon-btn">‹</button>
                  <button :disabled="userMeta.current_page >= userMeta.last_page" @click="loadUsers(userMeta.current_page + 1)" class="icon-btn">›</button>
                </div>
              </div>
            </template>
          </div>

              <!-- Profesiones -->
              <div v-show="activeTab === 'professions'">
                <div class="team-section-head">
                  <h2 class="section-head-title">Profesiones</h2>
                  <button class="btn btn-sm btn-nueva-profesion" type="button" @click="addProfession">+ Nueva profesión</button>
                </div>
                <div class="section-copy">Cada profesión puede asignarse a uno o varios usuarios.</div>

                <div v-if="professionsLoading" style="text-align:center;padding:24px;color:#6b7280;">Cargando profesiones...</div>

                <template v-else>
                  <EntityTable v-if="professions.length > 0" :columns="professionColumns" table-class="professions-table">
                    <tr v-for="p in professions" :key="p.id" class="entity-table-row" style="cursor:pointer" @click="editProfession(p)">
                      <td class="col-min">{{ p.id }}</td>
                      <td class="col-mid name-col">{{ p.name }}</td>
                      <td class="row-action professions-action-col" @click.stop>
                        <BtnTrash @click="deleteProfession(p)" />
                      </td>
                    </tr>
                  </EntityTable>
                  <EmptyIndexState v-else />
                </template>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import Swal from 'sweetalert2'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import EmptyIndexState from '../../components/EmptyIndexState.vue'
import EntityTable from '../../components/EntityTable.vue'
import BtnTrash from '../../components/BtnTrash.vue'
import api from '../../services/api'
import { getLoadErrorMessage } from '../../shared/httpErrors'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const loading = ref(true)
const activeTab = computed(() => route.path.includes('/professions') ? 'professions' : 'users')

// Users
const users = ref([])
const userMeta = ref(null)
const userQuery = ref('')
const usersLoading = ref(false)
let searchTimer = null

const userColumns = [
  { key: 'id', label: 'ID', thClass: 'col-min' },
  { key: 'name', label: 'Nombre', thClass: 'col-mid' },
  { key: 'email', label: 'Email', thClass: 'col-mid' },
  { key: 'profile', label: 'Perfil', thClass: 'col-min' },
  { key: 'profession', label: 'Profesión', thClass: 'col-min' },
  { key: 'booking', label: 'Reserva Online', thClass: 'col-min' },
  { key: 'manage_agenda', label: 'Gest. Agenda', thClass: 'col-min' },
  { key: 'actions', label: '', thClass: 'users-action-col' },
]

async function loadUsers(page = 1) {
  usersLoading.value = true
  try {
    const res = await api.get('/team/users', {
      params: { page, per_page: 15, q: userQuery.value || undefined },
    })
    users.value = Array.isArray(res.data?.data) ? res.data.data : []
    userMeta.value = res.data?.meta ?? null
  } catch (e) {
    users.value = []
    userMeta.value = null
    toast.error(getLoadErrorMessage(e, 'usuarios'))
  } finally {
    usersLoading.value = false
  }
}

function debouncedLoadUsers() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadUsers(1), 250)
}

async function deleteUser(u) {
  const { isConfirmed } = await Swal.fire({
    title: 'Eliminar usuario',
    text: `¿Eliminar a ${u.name}? Esta acción no se puede deshacer.`,
    icon: 'warning',
    confirmButtonText: 'Eliminar',
    cancelButtonText: 'Cancelar',
    showCancelButton: true,
    customClass: { popup: 'swal-popup-card' },
  })
  if (!isConfirmed) return
  try {
    await api.delete(`/team/users/${u.id}`)
    toast.success('Usuario eliminado')
    loadUsers(userMeta.value?.current_page || 1)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error al eliminar usuario')
  }
}

// Professions
const professions = ref([])
const professionsLoading = ref(false)

const professionColumns = [
  { key: 'id', label: 'ID', thClass: 'col-min' },
  { key: 'name', label: 'Nombre', thClass: 'col-mid' },
  { key: 'actions', label: '', thClass: 'professions-action-col' },
]

async function loadProfessions() {
  professionsLoading.value = true
  try {
    const res = await api.get('/team/professions')
    professions.value = Array.isArray(res.data?.data) ? res.data.data : []
  } catch (e) {
    professions.value = []
    toast.error(getLoadErrorMessage(e, 'profesiones'))
  } finally {
    professionsLoading.value = false
  }
}

async function addProfession() {
  const { value: name, isConfirmed } = await Swal.fire({
    title: 'Nueva profesión',
    input: 'text',
    inputLabel: 'Nombre de la profesión',
    inputPlaceholder: 'Ej: Fisioterapeuta',
    showCancelButton: true,
    confirmButtonText: 'Crear',
    cancelButtonText: 'Cancelar',
    inputValidator: (v) => { if (!v?.trim()) return 'El nombre es obligatorio' },
    customClass: { popup: 'swal-popup-card' },
  })
  if (!isConfirmed || !name?.trim()) return
  try {
    await api.post('/team/professions', { name: name.trim() })
    toast.success('Profesión añadida')
    await loadProfessions()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error al añadir profesión')
  }
}

async function editProfession(p) {
  const { value: name, isConfirmed } = await Swal.fire({
    title: 'Editar profesión',
    input: 'text',
    inputLabel: 'Nombre de la profesión',
    inputValue: p.name,
    showCancelButton: true,
    confirmButtonText: 'Guardar',
    cancelButtonText: 'Cancelar',
    inputValidator: (v) => { if (!v?.trim()) return 'El nombre es obligatorio' },
    customClass: { popup: 'swal-popup-card' },
  })
  if (!isConfirmed || !name?.trim()) return
  try {
    await api.put(`/team/professions/${p.id}`, { name: name.trim() })
    toast.success('Profesión actualizada')
    await loadProfessions()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error al actualizar profesión')
  }
}

async function deleteProfession(p) {
  const { isConfirmed } = await Swal.fire({
    title: 'Eliminar profesión',
    text: `¿Eliminar "${p.name}"?`,
    icon: 'warning',
    confirmButtonText: 'Eliminar',
    cancelButtonText: 'Cancelar',
    showCancelButton: true,
    customClass: { popup: 'swal-popup-card' },
  })
  if (!isConfirmed) return
  try {
    await api.delete(`/team/professions/${p.id}`)
    toast.success('Profesión eliminada')
    await loadProfessions()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error al eliminar profesión')
  }
}

onMounted(async () => {
  loading.value = true
  try {
    await Promise.all([loadUsers(1), loadProfessions()])
  } finally {
    loading.value = false
  }
})

watch(activeTab, () => {
  if (activeTab.value === 'professions') loadProfessions()
})
</script>

<style scoped>
.team-section-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.section-head-title {
  font-size: 16px;
  font-weight: 700;
}
.search-center { width: 100%; max-width: 400px; }
.name-col { font-weight: 600; }
.row-action { display: flex; align-items: center; gap: 8px; }
.users-action-col { width: 150px; }
.action-btn {
  display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 8px;
  text-decoration: none; color: #374151; font-size: 13px; border: 1px solid #e5e7eb; background: #fff;
}
.action-btn-danger { color: #b91c1c; border-color: #fca5a5; }
.action-btn-danger:hover { background: #fef2f2; }
.text-muted { color: #9ca3af; font-size: 13px; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.badge-on { background: #dcfce7; color: #166534; }
.badge-off { background: #f3f4f6; color: #6b7280; }

.professions-action-col { width: 80px; }
.pagination { margin-top: 12px; display: flex; justify-content: flex-end; gap: 12px; align-items: center; }
.pagination-info { color: #6b7280; font-size: 13px; }
.pagination-actions { display: flex; gap: 8px; }
.icon-btn { width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e5e7eb; background: #fff; cursor: pointer; }
.icon-btn:disabled { opacity: 0.45; }
.btn-nuevo-usuario,
.btn-nueva-profesion {
  min-width: 0 !important;
  max-width: 140px !important;
  padding: 6px 12px !important;
  font-size: 13px !important;
  display: inline-flex !important;
  align-items: center !important;
  white-space: nowrap !important;
}
.section-copy {
  color: #6b7280;
  font-size: 14px;
  margin-bottom: 16px;
}
</style>
