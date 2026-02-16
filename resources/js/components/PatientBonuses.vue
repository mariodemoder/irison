<template>
  <div class="patient-bonuses">
    <div style="display:flex; justify-content:space-between; align-items:center">
      <h3 style="margin:0"></h3>
      <div>
        <button @click="showForm = !showForm" class="primary" style="padding:6px 10px;font-size:13px">Crear</button>
      </div>
    </div>

    <div v-if="showForm" style="margin-top:8px">
      <form @submit.prevent="create">
        <div>
          <label>Nombre</label>
          <input v-model.number="form.name" type="text" value='Bono' required />
        </div>
        <div>
          <label>Nº sesiones</label>
          <input v-model.number="form.total_sessions" type="number" min="1" required />
        </div>
        <div>
          <label>Precio</label>
          <input v-model.number="form.price" type="number" step="0.01" min="0" />
        </div>
        <div>
          <label>Expira (opcional)</label>
          <input v-model="form.expires_at" type="date" />
        </div>
        <div style="margin-top:8px">
          <button type="submit" class="primary">Crear</button>
          <button type="button" class="muted" @click="cancelForm">Cancelar</button>
        </div>
      </form>
    </div>

    <ul style="margin-top:12px">
      <li v-for="b in bonuses" :key="b.id" style="margin-bottom:8px">
        <div v-if="b.name"><strong>{{ b.name }}</strong></div>
        <div>Sesiones: {{ b.total_sessions }} sesiones</div>
        <div>Restan: {{ b.remaining_sessions }}</div>
        <div>Expira: {{ b.expires_at ? formatDMY(b.expires_at) : '—' }}</div>
        <div style="margin-top:6px">
          <button @click="confirmDeleteBonus(b)" class="action-btn">🗑️ Eliminar</button>
        </div>
      </li>
    </ul>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import Swal from 'sweetalert2'
import api from '../services/api'
import { useToast } from 'vue-toastification'
import { formatDMY } from '../shared/dateHelpers'

const props = defineProps({ patientId: { type: [String, Number], required: true } })
const bonuses = ref([])
const showForm = ref(false)
const form = ref({ name:'bono',total_sessions: 1, price: 0, expires_at: '' })
const toast = useToast()



async function load() {
  try {
    const res = await api.get(`/patients/${props.patientId}/bonuses`)
    bonuses.value = Array.isArray(res.data.data) ? res.data.data : []
  } catch (e) {
    bonuses.value = []
  }
}

function cancelForm() {
  showForm.value = false
  form.value = { total_sessions: 1, price: 0, expires_at: '' }
}

async function create() {
  try {
    const bonus = { ...form.value }
    if (!bonus.expires_at) delete bonus.expires_at
    const res = await api.post(`/patients/${props.patientId}/bonuses`, payload)
    const b = res.data.data
    bonuses.value.unshift(b)
    toast.success('Bono creado')
    cancelForm()
  } catch (e) {
    toast.error('Error creando bono')
  }
}

async function deleteBonus(id) {
  try {
    await api.delete(`/bonuses/${id}`)
    bonuses.value = bonuses.value.filter(b => b.id !== id)
    toast.success('Bono eliminado')
  } catch (e) {
    toast.error('Error eliminando bono')
  }
}

async function confirmDeleteBonus(bonus) {
  const res = await Swal.fire({
    title: `Eliminar bono`,
    text: `¿Eliminar el bono de ${bonus.total_sessions} sesiones?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  })

  if (!res.isConfirmed) return
  await deleteBonus(bonus.id)
}

onMounted(load)
</script>

<style scoped>
.patient-bonuses label { display:block; font-weight:600; margin-bottom:4px }
.patient-bonuses input { padding:8px; border:1px solid #e5e7eb; border-radius:6px }
.primary { padding:6px 12px; border-radius:9999px; border:2px solid #3b82f6; color:#3b82f6; background:#fff }
.muted { padding:6px 12px; border-radius:8px; border:1px solid #e5e7eb; background:#fff }
.action-btn { padding:4px 8px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; font-size:12px }
</style>
