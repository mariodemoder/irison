<template>
  <MainLayout>
    <div>
      <div class="page-header">
        <div>
          <h1>Pacientes</h1>
          <div class="form-sub">Listado de pacientes</div>
        </div>

        <div class="search-center">
          <div class="search-wrapper">
            <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input v-model="query" placeholder="Buscar pacientes por nombre, NIF, teléfono o email" class="search-input" />
          </div>
        </div>

        <router-link to="/patients/create" class="btn btn-sm small">Nuevo paciente</router-link>
      </div>

      <div v-if="loading">Cargando...</div>

      <div v-else>
        <div class="list-header">
          <div>Paciente</div>
          <div>Teléfono</div>
          <div>Email</div>
          <div></div>
        </div>

        <div class="list">
          <div v-for="p in filteredPatients" :key="p.id" class="patient-row">
            <div class="row-left">
              <div class="row-name">{{ p.name }}</div>
              <div class="row-sub">{{ p.nif ?? '—' }}</div>
            </div>
            <div class="row-col">{{ p.phone ?? '—' }}</div>
            <div class="row-col">{{ p.email ?? '—' }}</div>
            <div class="row-action">
              <router-link :to="`/patients/${p.id}`" class="action-btn history" aria-label="Historial">🔍 Historial</router-link>
              <router-link :to="`/patients/${p.id}/edit`" class="action-btn datos" aria-label="Datos">✎ Datos</router-link>
            </div>
          </div>
        </div>

        <div v-if="meta" class="pagination">
          <button :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">Anterior</button>
          <div>Página {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} pacientes</div>
          <button :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Siguiente</button>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'

const patients = ref([])
const meta = ref(null)
const loading = ref(false)
const query = ref('')

const filteredPatients = computed(() => {
  const q = (query.value || '').toLowerCase().trim()
  if (!q) return patients.value
  return patients.value.filter(p => {
    return [p.name, p.nif, p.phone, p.email].some(f => f && String(f).toLowerCase().includes(q))
  })
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
</script>

<style scoped>
/* Botón estilo outline azul, pill */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
}
.btn.btn-sm {
  padding: 6px 12px;
  font-size: 13px;
  border-radius: 9999px;
  border: 2px solid #3b82f6; /* azul */
  color: #3b82f6;
  background: #ffffff;
  font-weight: 600;
}
.btn.btn-sm:hover { background: #eff6ff }

.btn.btn-sm.small { padding:6px 10px; font-size:13px }

.page-header { display:grid; grid-template-columns: 1fr 480px auto; align-items:center; gap:12px; margin-bottom:16px }
.page-header h1 { margin:0 }
.form-sub { color:#6b7280; font-size:13px; margin-top:4px }

.search-center { display:flex; justify-content:center }
.search-wrapper { position:relative; width:100%; max-width:480px }
.search-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#9ca3af }
.search-input { width:100%; padding:8px 12px 8px 36px; border-radius:9999px; border:1px solid #e5e7eb; font-size:14px }


.list { display:flex; flex-direction:column; gap:8px }
.list-header { display:grid; grid-template-columns: 2fr 1fr 1fr auto; gap:12px; align-items:center; padding:8px 14px; color:#6b7280; font-weight:600; font-size:13px }
.patient-row { display:grid; grid-template-columns: 2fr 1fr 1fr auto; gap:12px; align-items:center; background:#fff; padding:12px 14px; border-radius:10px; text-decoration:none; color:inherit; border:1px solid #eef2ff22 }
.patient-row:hover { box-shadow: 0 10px 24px rgba(2,6,23,0.06); transform: translateY(-2px) }
.row-left { display:flex; flex-direction:column }
.row-name { font-weight:700 }
.row-sub { color:#6b7280; font-size:13px }
.row-col { color:#374151; font-size:13px }
.row-action { display:flex; align-items:center; justify-content:center; color:#6b7280 }

.action-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:8px; text-decoration:none; color:#374151; font-size:13px; border:1px solid transparent }
.action-btn.history { background:#eef2ff; border-color: #dbeafe; color:#1e3a8a }
.action-btn.datos { background:#fff; border-color:#e5e7eb; color:#374151 }
.action-btn:hover { transform:translateY(-1px) }

.pagination { margin-top:12px; display:flex; gap:8px; align-items:center }

@media (max-width: 900px) {
  .page-header { grid-template-columns: 1fr auto }
  .search-center { order:3; grid-column: 1 / -1 }
}

@media (max-width: 480px) {
  .patient-row { grid-template-columns: 1fr; gap:6px }
  .row-action { justify-content:flex-start }
}
</style>
