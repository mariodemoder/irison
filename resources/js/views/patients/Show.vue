<template>
  <MainLayout>
    <div class="form-wrapper">
      <div class="form-card">
        <div class="form-header">
          <h1>Paciente</h1>
          <p class="form-sub">Datos y historial</p>
        </div>

        <div class="grid-display">
          <div class="card">
            <div class="card-row"><strong>Nombre</strong></div>
            <div class="card-row">{{ patient?.name ?? '—' }}</div>
          </div>

          <div class="card">
            <div class="card-row"><strong>NIF</strong></div>
            <div class="card-row">{{ patient?.nif ?? '—' }}</div>
          </div>

          <div class="card">
            <div class="card-row"><strong>Teléfono</strong></div>
            <div class="card-row">{{ patient?.phone ?? '—' }}</div>
          </div>

          <div class="card">
            <div class="card-row"><strong>Email</strong></div>
            <div class="card-row">{{ patient?.email ?? '—' }}</div>
          </div>

          <div class="card full">
            <div class="card-row"><strong>Notas</strong></div>
            <div class="card-row">{{ patient?.notes ?? '—' }}</div>
          </div>
        </div>

        <div style="margin-top:18px;display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
          <div class="history-card">
            <div class="history-title">Citas</div>
            <div v-if="appointments && appointments.length"> 
              <ul>
                <li v-for="a in appointments" :key="a.id">{{ a.start_time }} — {{ a.status }}</li>
              </ul>
            </div>
            <div v-else class="empty">Sin citas</div>
          </div>

          <div class="history-card">
            <div class="history-title">Bonos</div>
            <div v-if="packs && packs.length">
              <ul>
                <li v-for="p in packs" :key="p.id">{{ p.remaining_sessions }} / {{ p.total_sessions }} — {{ p.status }}</li>
              </ul>
            </div>
            <div v-else class="empty">Sin bonos</div>
          </div>

          <div class="history-card">
            <div class="history-title">Pagos</div>
            <div v-if="payments && payments.length">
              <ul>
                <li v-for="pay in payments" :key="pay.id">{{ pay.amount }} — {{ pay.status }}</li>
              </ul>
            </div>
            <div v-else class="empty">Sin pagos</div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from '../../layouts/MainLayout.vue'
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../../services/api'

const route = useRoute()
const router = useRouter()
const patient = ref(null)
const appointments = ref([])
const packs = ref([])
const payments = ref([])
const loading = ref(false)

async function loadPatient() {
  loading.value = true
  try {
    const id = route.params.id
    const res = await api.get(`/patients/${id}`)
    patient.value = res.data
    appointments.value = res.data.appointments || []
    packs.value = res.data.packs || []
    payments.value = res.data.payments || []
  } catch (e) {
    console.error('Error cargando paciente', e)
    // si 404, volver al listado
    if (e.response && e.response.status === 404) router.push('/patients')
  } finally {
    loading.value = false
  }
}

onMounted(() => loadPatient())
</script>

<style scoped>
/* Estilos mínimos si hacen falta */
</style>
