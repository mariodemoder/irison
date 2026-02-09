<template>
  <MainLayout>
    <div class="show-wrapper">
      <div class="show-card">
        <div class="show-header">
          <h1>Cita</h1>
          <div class="form-sub">Detalle de la cita</div>
        </div>

        <div v-if="loading">Cargando...</div>

        <div v-else>
          <div class="field full">
            <label class="label">Paciente</label>
            <div class="value"><router-link :to="`/patients/${appointment.patient_id}`">{{ appointment.patient?.name ?? ('Paciente #' + appointment.patient_id) }}</router-link></div>
          </div>

          <div class="field">
            <label class="label">Inicio</label>
            <div class="value">{{ appointment.start_time }}</div>
          </div>

          <div class="field">
            <label class="label">Fin</label>
            <div class="value">{{ appointment.end_time }}</div>
          </div>

          <div class="field full">
            <label class="label">Notas</label>
            <div class="value">{{ appointment.notes ?? '—' }}</div>
          </div>

          <div class="actions">
            <router-link :to="`/appointments/${appointment.id}/edit`" class="primary">Editar</router-link>
            <button class="muted" @click="cancel" :disabled="cancelling">Cancelar cita</button>
            <button class="muted" @click="back">Volver</button>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import { useToast } from 'vue-toastification'

const route = useRoute()
const router = useRouter()
const appointment = ref({})
const loading = ref(false)
const cancelling = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await api.get(`/appointments/${route.params.id}`)
    appointment.value = res.data
  } catch (e) {
    console.error('Error cargando cita', e)
    if (e.response && e.response.status === 404) router.push('/appointments/day')
  } finally {
    loading.value = false
  }
}

onMounted(() => load())

function back() {
  router.push('/appointments/day')
}

async function cancel() {
  cancelling.value = true
  try {
    const toast = useToast()
    await api.post(`/appointments/${route.params.id}/cancel`)
    toast.success('Cita cancelada')
    await load()
  } catch (e) {
    console.error('Error cancelando cita', e)
  } finally {
    cancelling.value = false
  }
}
</script>

<style scoped>
.show-wrapper { display:flex; justify-content:center; padding:24px }
.show-card { width:100%; max-width:760px; background: #fff; border-radius:12px; box-shadow: 0 10px 30px rgba(2,6,23,0.06); padding:24px }
.show-header h1 { margin:0; font-size:22px }
.form-sub { color:#6b7280; font-size:13px; margin-top:6px }
.field { margin-top:12px }
.label { font-weight:600; margin-bottom:6px }
.value { padding:10px; background:#f8fafc; border-radius:8px }
.actions { display:flex; gap:12px; margin-top:16px }
.primary { padding: 8px 16px; font-size: 14px; border-radius: 9999px; border: 2px solid #3b82f6; color: #3b82f6; background: #ffffff; font-weight: 600 }
.muted { padding:8px 14px; border-radius:9999px; border:1px solid #e5e7eb; background:#fff }
</style>
