<template>
  <MainLayout>
    <div class="form-wrapper">
      <div class="form-card">
        <div class="form-header" style="display:flex;justify-content:space-between;align-items:start">
          <div>
            <h1>Historia Clínica</h1>
            <div class="form-sub">Paciente: {{ patient?.counter ? `${patient.counter} · ` : '' }}{{ patient?.name || '—' }}</div>
          </div>
          <div class="header-actions" style="display:flex;gap:8px">
            <button
              type="button"
              class="pdf-btn"
              title="Vista previa PDF"
              @click.prevent="previewHistoryPdf"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="pdf-icon">
                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"></path>
                <circle cx="12" cy="12" r="2.5"></circle>
              </svg>
            </button>
            <button
              type="button"
              class="pdf-btn"
              title="Descargar PDF"
              @click.prevent="downloadHistoryPdf"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="pdf-icon">
                <path d="M12 4v11"></path>
                <path d="M8.5 11.5L12 15l3.5-3.5"></path>
                <path d="M5 19h14"></path>
              </svg>
            </button>
            <button class="muted back-btn" @click.prevent="goBack">Volver</button>
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
import { useToast } from 'vue-toastification'
import { formatTime, formatDateShort, statusLabel } from '../../shared/appointmentHelpers'
import { goBackWithStack } from '../../shared/navigationHelpers'

const route = useRoute()
const router = useRouter()
const toast = useToast()
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
  const patientId = Number(route.params.id || 0)
  const fallbackPath = patientId > 0 ? `/patients/${patientId}` : '/patients'
  goBackWithStack(router, fallbackPath)
}

function historyPdfFilename() {
  const id = Number(route.params.id || 0)
  return `historia-clinica-${id > 0 ? id : 'paciente'}.pdf`
}

async function previewHistoryPdf() {
  const id = Number(route.params.id || 0)
  if (!id) return

  try {
    const res = await api.get(`/patients/${id}/history/pdf`, { responseType: 'blob' })
    const file = new Blob([res.data], { type: 'application/pdf' })
    const fileUrl = URL.createObjectURL(file)
    window.open(fileUrl, '_blank', 'noopener,noreferrer')
    setTimeout(() => URL.revokeObjectURL(fileUrl), 60000)
  } catch (e) {
    toast.error('No se pudo abrir el PDF de historia clínica')
  }
}

async function downloadHistoryPdf() {
  const id = Number(route.params.id || 0)
  if (!id) return

  try {
    const res = await api.get(`/patients/${id}/history/pdf`, {
      responseType: 'blob',
      params: { download: 1 },
    })

    const file = new Blob([res.data], { type: 'application/pdf' })
    const fileUrl = URL.createObjectURL(file)
    const link = document.createElement('a')
    link.href = fileUrl
    link.download = historyPdfFilename()
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(fileUrl), 60000)
  } catch (e) {
    toast.error('No se pudo descargar el PDF de historia clínica')
  }
}

onMounted(load)
</script>

<style scoped>
/* reuse existing app styles */
.form-wrapper { display:flex; justify-content:center; padding:24px }
.form-card { width:100%; max-width:960px; background: #fff; border-radius:12px; box-shadow: 0 10px 30px rgba(2,6,23,0.06); padding:24px }
.form-header h1 { margin:0; font-size:22px }
.history-card { background:#fff; padding:14px; border-radius:10px; border:1px solid #eef2ff; box-shadow: 0 6px 18px rgba(2,6,23,0.04) }
.empty-card { padding:18px; border-radius:8px; border:2px dashed #e6e6e6; color:#6b7280; text-align:center; min-height:72px; display:flex; align-items:center; justify-content:center }
.action-btn { padding:6px 10px; border-radius:8px; border:1px solid #e5e7eb; background:#fff }
.header-actions { align-items:center }
.pdf-btn { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:8px; border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8 }
.pdf-btn:hover { background:#dbeafe; border-color:#93c5fd }
.pdf-icon { width:14px; height:14px; display:block }
.status { font-weight:700; text-transform:capitalize }
.status.canceled { color:#da7a7a }
.status.scheduled { color:#1e3a8a }
.status.completed { color:#166534 }
</style>
