<template>
  <MainLayout>
    <div>
      <div class="entity-card">
        <div class="page-header">
          <div>
            <h1>Equipo</h1>
            <div class="form-sub">Administra los usuarios y profesiones de tu clínica</div>
          </div>
          <div v-if="userMeta" class="user-limit-badge">
            {{ userMeta.total }} / {{ maxUsers > 0 ? maxUsers : '∞' }} usuarios
          </div>
          <MoreActionsMenu class="more-actions--push-right" aria-label="Más opciones">
            <button class="ma-item" @click="openProfessionsModal">
              Profesiones
            </button>
          </MoreActionsMenu>
        </div>

        <div v-if="showLimitWarning" class="limit-warning-banner">
          <div class="limit-warning-content">
            <span class="limit-warning-icon">⚠️</span>
            <span>
              <strong>Límite de usuarios:</strong> Has usado {{ userMeta?.total ?? 0 }} de {{ maxUsers > 0 ? maxUsers : '∞' }} usuarios.
              <template v-if="maxUsers > 0 && (userMeta?.total ?? 0) >= maxUsers">No puedes añadir más usuarios.</template>
              <template v-else-if="maxUsers > 0">Quedan {{ maxUsers - (userMeta?.total ?? 0) }} disponibles.</template>
            </span>
          </div>
          <router-link v-if="maxUsers > 0" to="/settings/subscription" class="limit-warning-btn">Solicitar upgrade</router-link>
        </div>

        <AppLoading v-if="loading" message="Cargando..." />

        <div v-else>

              <div class="team-section-head">
                <div class="search-center">
                  <div class="search-wrapper">
                    <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input v-model="userQuery" class="search-input" placeholder="Buscar usuario..." @input="debouncedLoadUsers" />
                  </div>
                </div>
                <NewButton v-if="!isBasic" label="Nuevo usuario" to="/team/users/create" />
                <span v-else class="basic-limit-msg">Plan Basic: 1 usuario incluido</span>
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
      </div>
    </div>

    <!-- Modal Profesiones -->
    <div v-if="showProfessionsModal" class="modal-backdrop" @click.self="closeProfessionsModal">
      <div class="modal-card">
        <div class="modal-card-header">
          <h2>Profesiones</h2>
          <button class="modal-close-btn" @click="closeProfessionsModal">&times;</button>
        </div>
        <div class="modal-card-body">
          <div class="team-section-head" style="margin-bottom:12px;">
            <div class="section-copy">Cada profesión puede asignarse a uno o varios usuarios.</div>
            <NewButton label="Nueva profesión" @click="addProfession" />
          </div>

          <div v-if="professionsLoading" style="text-align:center;padding:24px;color:#6b7280;">Cargando profesiones...</div>

          <template v-else>
            <EntityTable v-if="professions.length > 0" :columns="professionColumns" table-class="professions-table">
              <tr v-for="p in professions" :key="p.id" class="entity-table-row" style="cursor:pointer" @click="editProfession(p)">
                <td class="col-min">{{ p.id }}</td>
                <td class="col-mid name-col">{{ p.name }}</td>
                <td class="row-action professions-action-col" @click.stop>
                  <BtnTrash variant="danger" @click="deleteProfession(p)" />
                </td>
              </tr>
            </EntityTable>
            <EmptyIndexState v-else />
          </template>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import Swal from 'sweetalert2'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import EmptyIndexState from '../../components/EmptyIndexState.vue'
import EntityTable from '../../components/EntityTable.vue'
import BtnTrash from '../../components/BtnTrash.vue'
import api from '../../services/api'
import { getLoadErrorMessage } from '../../shared/httpErrors'
import { isBasic } from '../../shared/meCache'
import { confirmDelete } from '../../shared/confirmDelete'

const router = useRouter()
const toast = useToast()

const loading = ref(true)

// Users
const users = ref([])
const userMeta = ref(null)
const userQuery = ref('')
const usersLoading = ref(false)
let searchTimer = null

const maxUsers = ref(3)

const showLimitWarning = computed(() => {
  if (!userMeta.value || maxUsers.value <= 0) return false
  const usage = userMeta.value.total ?? 0
  return usage >= maxUsers.value * 0.8
})

const userColumns = [
  { key: 'id', label: 'ID', thClass: 'col-min' },
  { key: 'name', label: 'Nombre', thClass: 'col-mid' },
  { key: 'email', label: 'Email', thClass: 'col-mid' },
  { key: 'profile', label: 'Perfil', thClass: 'col-min' },
  { key: 'profession', label: 'Profesión', thClass: 'col-min' },
  { key: 'booking', label: 'Online', thClass: 'col-min' },
  { key: 'manage_agenda', label: 'Agenda', thClass: 'col-min' },
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
    maxUsers.value = res.data?.meta?.max_users ?? 3
  } catch (e) {
    users.value = []
    userMeta.value = null
    maxUsers.value = 3
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
  const confirmed = await confirmDelete({
    title: 'Eliminar usuario',
    text: `¿Eliminar a ${u.name}? Esta acción no se puede deshacer.`,
    confirmButtonText: 'Eliminar',
  })
  if (!confirmed) return
  try {
    await api.delete(`/team/users/${u.id}`)
    toast.success('Usuario eliminado')
    loadUsers(userMeta.value?.current_page || 1)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error al eliminar usuario')
  }
}

// Professions modal
const showProfessionsModal = ref(false)
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

function openProfessionsModal() {
  showProfessionsModal.value = true
  loadProfessions()
}

function closeProfessionsModal() {
  showProfessionsModal.value = false
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
  const confirmed = await confirmDelete({
    title: 'Eliminar profesión',
    text: `¿Eliminar "${p.name}"?`,
    confirmButtonText: 'Eliminar',
  })
  if (!confirmed) return
  try {
    await api.delete(`/team/professions/${p.id}`)
    toast.success('Profesión eliminada')
    await loadProfessions()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error al eliminar profesión')
  }
}

// El cierre del menú ⋮ lo gestiona el componente MoreActionsMenu
onMounted(async () => {
  loading.value = true
  try {
    await loadUsers(1)
  } finally {
    loading.value = false
  }
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
.search-center { width: 100%; max-width: 400px; }
.name-col { font-weight: 600; }
.row-action { display: flex; align-items: center; gap: 8px; }
.users-action-col { width: 150px; }
.professions-action-col { width: 80px; }
.text-muted { color: #9ca3af; font-size: 13px; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.badge-on { background: #dcfce7; color: #166534; }
.badge-off { background: #f3f4f6; color: #6b7280; }
.pagination { margin-top: 12px; display: flex; justify-content: flex-end; gap: 12px; align-items: center; }
.pagination-info { color: #6b7280; font-size: 13px; }
.pagination-actions { display: flex; gap: 8px; }
.icon-btn { width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e5e7eb; background: #fff; cursor: pointer; }
.icon-btn:disabled { opacity: 0.45; }
.user-limit-badge {
  display: inline-flex;
  align-items: center;
  padding: 4px 12px;
  border-radius: 999px;
  background: #f3f4f6;
  color: #374151;
  font-size: 13px;
  font-weight: 600;
  white-space: nowrap;
}

.limit-warning-banner {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 12px 16px; border-radius: 10px; margin-bottom: 16px;
  background: #fef3c7; border: 1px solid #f59e0b; color: #92400e;
  flex-wrap: wrap;
}
.limit-warning-content { display: flex; align-items: center; gap: 8px; font-size: 14px; }
.limit-warning-icon { font-size: 18px; }
.limit-warning-btn {
  display: inline-block; padding: 6px 14px; font-size: 13px; font-weight: 600;
  border-radius: 8px; background: #f59e0b; color: #fff; text-decoration: none; white-space: nowrap;
}
.limit-warning-btn:hover { background: #d97706; }

.basic-limit-msg { font-size: 13px; color: #6b7280; font-weight: 500; padding: 6px 0; }

.section-copy {
  color: #6b7280;
  font-size: 14px;
}

/* Modal overlay */
.modal-backdrop {
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.4);
  z-index:1000;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:24px;
}
.modal-card {
  background:#fff;
  border-radius:16px;
  width:100%;
  max-width:560px;
  max-height:80vh;
  display:flex;
  flex-direction:column;
  box-shadow:0 20px 60px rgba(0,0,0,.15);
}
.modal-card-header {
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:20px 24px 0;
}
.modal-card-header h2 {
  margin:0;
  font-size:18px;
  font-weight:700;
}
.modal-close-btn {
  background:none;
  border:none;
  font-size:24px;
  color:#6b7280;
  cursor:pointer;
  padding:0;
  line-height:1;
  font-family:inherit;
}
.modal-close-btn:hover { color:#111827; }
.modal-card-body {
  padding:16px 24px 24px;
  overflow-y:auto;
  flex:1;
}
</style>
