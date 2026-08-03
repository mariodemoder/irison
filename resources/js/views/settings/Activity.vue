<template>
  <MainLayout>
    <div class="entity-card">
      <div class="page-header">
        <div>
          <h1>Registro de actividad</h1>
          <div class="form-sub">Auditoría de recepción: pacientes, citas, pagos y consentimientos</div>
        </div>
      </div>

      <div class="filters">
        <div class="search-wrapper">
          <input
            v-model="query"
            placeholder="Buscar por evento o descripción"
            class="search-input"
            @input="debouncedLoad"
          />
        </div>
        <select v-model="entityFilter" @change="load(1)">
          <option value="">Entidad: todas</option>
          <option value="patient">Pacientes</option>
          <option value="appointment">Citas</option>
          <option value="payment">Pagos</option>
          <option value="consent">Consentimientos</option>
        </select>
        <input v-model="fromDate" type="date" class="date-input" @change="load(1)" />
        <input v-model="toDate" type="date" class="date-input" @change="load(1)" />
        <button class="btn btn-sm small" @click="resetFilters">Limpiar</button>
      </div>

      <AppLoading v-if="loading" message="Cargando actividad..." />

      <div v-else>
        <div v-if="items.length === 0" class="empty-card">No hay actividad registrada.</div>

        <div v-else class="table-wrap">
          <table class="entity-table">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Usuario</th>
                <th>Evento</th>
                <th>Descripción</th>
                <th>Entidad</th>
                <th>ID</th>
                <th>IP</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in items" :key="item.id">
                <td>{{ formatDate(item.created_at) }}</td>
                <td>{{ item.user_name || '—' }}</td>
                <td><span class="activity-badge">{{ item.event }}</span></td>
                <td>{{ item.description }}</td>
                <td>{{ item.entity || '—' }}</td>
                <td>{{ item.entity_id ?? '—' }}</td>
                <td class="mono">{{ item.ip || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="meta && meta.last_page > 1" class="pagination">
          <button class="btn btn-sm small" :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">
            ← Anterior
          </button>
          <span class="pagination-info">Página {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} registros</span>
          <button
            class="btn btn-sm small"
            :disabled="meta.current_page >= meta.last_page"
            @click="load(meta.current_page + 1)"
          >
            Siguiente →
          </button>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import api from '../../services/api'
import { getLoadErrorMessage } from '../../shared/httpErrors'

const toast = useToast()

const items = ref([])
const meta = ref(null)
const loading = ref(false)

const query = ref('')
const entityFilter = ref('')
const fromDate = ref('')
const toDate = ref('')

let searchTimer = null

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleString('es-ES', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function load(page = 1) {
  loading.value = true
  try {
    const res = await api.get('/activity', {
      params: {
        page,
        per_page: 20,
        q: query.value || undefined,
        entity: entityFilter.value || undefined,
        from_date: fromDate.value || undefined,
        to_date: toDate.value || undefined,
      },
    })
    items.value = Array.isArray(res.data?.data) ? res.data.data : []
    meta.value = res.data?.meta ?? null
  } catch (e) {
    items.value = []
    meta.value = null
    toast.error(getLoadErrorMessage(e, 'actividad'))
  } finally {
    loading.value = false
  }
}

function debouncedLoad() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 250)
}

function resetFilters() {
  query.value = ''
  entityFilter.value = ''
  fromDate.value = ''
  toDate.value = ''
  load(1)
}

onMounted(() => load(1))
</script>

<style scoped>
.entity-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}
.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 16px;
}
.search-wrapper { flex: 1; min-width: 200px; }
.search-input {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
}
.input {
  padding: 8px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  font-size: 14px;
  background: #fff;
}
.input, select.input { min-width: 150px; }
.activity-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 6px;
  background: #eef2ff;
  color: #4338ca;
  font-size: 12px;
  font-weight: 600;
}
.mono {
  font-family: monospace;
  font-size: 12px;
  color: #6b7280;
}
.pagination {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 16px;
  justify-content: flex-end;
}
.pagination-info { color: #6b7280; font-size: 13px; }
</style>