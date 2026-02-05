<template>
  <MainLayout>
    <div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h1>Pacientes</h1>
        <router-link to="/patients/create" class="btn btn-sm" style="text-decoration:none">Nuevo paciente</router-link>
      </div>

      <div v-if="loading">Cargando...</div>

      <table v-else style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="text-align:left;border-bottom:1px solid #e5e7eb">
          <th style="padding:8px">Nombre</th>
          <th style="padding:8px">Teléfono</th>
          <th style="padding:8px">Email</th>
          <th style="padding:8px">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="p in patients" :key="p.id" style="border-bottom:1px solid #f3f4f6">
          <td style="padding:8px">{{ p.name }}</td>
          <td style="padding:8px">{{ p.phone ?? '—' }}</td>
          <td style="padding:8px">{{ p.email ?? '—' }}</td>
          <td style="padding:8px">
            <router-link :to="`/patients/${p.id}`">Ver</router-link>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="meta" style="margin-top:12px;display:flex;gap:8px;align-items:center">
      <button :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)">Anterior</button>
      <div>Página {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} pacientes</div>
      <button :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)">Siguiente</button>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'

const patients = ref([])
const meta = ref(null)
const loading = ref(false)

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
.btn { background:#111827;color:#fff;padding:8px 12px;border-radius:6px; display:inline-flex; align-items:center; justify-content:center }
.btn.btn-sm { padding:4px 8px; font-size:13px; border-radius:6px; width:33%; max-width:180px }
table th, table td { font-size:14px }
</style>
