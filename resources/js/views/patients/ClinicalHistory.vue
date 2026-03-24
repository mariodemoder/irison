<template>
  <MainLayout>
    <div class="form-wrapper">
      <div class="form-card">
        <div class="form-header" style="display:flex;justify-content:space-between;align-items:start">
          <div>
            <h1>Historia Clínica</h1>
            <div class="form-sub">Paciente: {{ patient?.name || '—' }}</div>
          </div>
          <div style="display:flex;gap:8px">
            <button class="muted" @click.prevent="goBack" style="padding:6px 12px;font-size:13px">Volver</button>
          </div>
        </div>

        <div style="margin-top:18px">
          <AppLoading v-if="loading" message="Cargando historia..." />

          <div v-else>
            <div v-if="appointments.length">
              <ul style="list-style:none;padding:0;margin:0">
                <li v-for="a in appointments" :key="a.id" style="margin-bottom:12px">
                  <div class="history-card">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                      <div>
                        <strong>{{ formatDateShort(a.start_time) }} {{ formatTime(a.start_time) }}</strong>
                        <div style="color:#6b7280">Estado: <span class="status" :class="a.status">{{ statusLabel(a.status) }}</span></div>
                      </div>
                      <div>
                        <router-link :to="`/appointments/${a.id}`" class="action-btn">Ver cita</router-link>
                      </div>
                    </div>
                    <div style="margin-top:10px">Notas: {{ a.notes || '—' }}</div>
                  </div>
                </li>
              </ul>
            </div>
            <div v-else class="empty-card">Sin historial de citas</div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'
import { formatTime, formatDateShort, statusLabel } from '../../shared/appointmentHelpers'

const route = useRoute()
const router = useRouter()
const patient = ref(null)
const appointments = ref([])
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const id = route.params.id
    const res = await api.get(`/patients/${id}`)
    patient.value = res.data
    appointments.value = res.data.appointments || []
  } catch (e) {
    console.error('Error cargando historia', e)
    appointments.value = []
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.back()
}

onMounted(load)
</script>

<style scoped>
/* reuse existing app styles */
.form-wrapper { display:flex; justify-content:center; padding:24px }
.form-card { width:100%; max-width:960px; background: #fff; border-radius:12px; box-shadow: 0 10px 30px rgba(2,6,23,0.06); padding:24px }
.form-header h1 { margin:0; font-size:22px }
.form-sub { color:#6b7280; font-size:13px; margin-top:6px }
.history-card { background:#fff; padding:14px; border-radius:10px; border:1px solid #eef2ff; box-shadow: 0 6px 18px rgba(2,6,23,0.04) }
.empty-card { padding:18px; border-radius:8px; border:2px dashed #e6e6e6; color:#6b7280; text-align:center; min-height:72px; display:flex; align-items:center; justify-content:center }
.action-btn { padding:6px 10px; border-radius:8px; border:1px solid #e5e7eb; background:#fff }
.status { font-weight:700; text-transform:capitalize }
.status.canceled { color:#da7a7a }
.status.scheduled { color:#1e3a8a }
.status.completed { color:#166534 }
</style>
