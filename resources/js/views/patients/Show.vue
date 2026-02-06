<template>
  <MainLayout>
    <div class="form-wrapper">
      <div class="form-card">
        <div class="form-header" style="display:flex;justify-content:space-between;align-items:start">
          <div>
            <h1>Paciente</h1>
            <p class="form-sub">Datos y historial</p>
          </div>
          <div style="display:flex;gap:8px">
            <button class="primary" @click.prevent="goEdit" style="padding:6px 12px;font-size:13px">Editar</button>
          </div>
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

        <div class="history-grid">
          <div class="history-card">
            <div class="history-title">Citas</div>
            <div v-if="appointments && appointments.length"> 
              <ul>
                <li v-for="a in appointments" :key="a.id">{{ a.start_time }} — {{ a.status }}</li>
              </ul>
            </div>
            <div v-else class="empty-card">Sin citas</div>
          </div>

          <div class="history-card">
            <div class="history-title">Bonos</div>
            <div v-if="packs && packs.length">
              <ul>
                <li v-for="p in packs" :key="p.id">{{ p.remaining_sessions }} / {{ p.total_sessions }} — {{ p.status }}</li>
              </ul>
            </div>
            <div v-else class="empty-card">Sin bonos</div>
          </div>

          <div class="history-card">
            <div class="history-title">Pagos</div>
            <div v-if="payments && payments.length">
              <ul>
                <li v-for="pay in payments" :key="pay.id">{{ pay.amount }} — {{ pay.status }}</li>
              </ul>
            </div>
            <div v-else class="empty-card">Sin pagos</div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from '../../layouts/MainLayout.vue'
import { ref, onMounted, watch } from 'vue'
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

watch(() => route.params.id, (id) => {
  if (id) loadPatient()
})

function goEdit() {
  if (patient.value && patient.value.id) {
    router.push({ path: `/patients/${patient.value.id}/edit`, query: { from: 'show' } })
  }
}
</script>

<style scoped>
/* Reusar estilos del formulario y mejorar visual */
.form-wrapper { display:flex; justify-content:center; padding:24px }
.form-card { width:100%; max-width:960px; background: #fff; border-radius:12px; box-shadow: 0 10px 30px rgba(2,6,23,0.06); padding:24px }
.form-header h1 { margin:0; font-size:22px }
.form-sub { color:#6b7280; font-size:13px; margin-top:6px }

.grid-display { display:grid; grid-template-columns: repeat(2,1fr); gap:12px }
.card { background:#fafafa; padding:12px; border-radius:10px; border:1px solid #eef2ff22 }
.card.full { grid-column:1 / -1 }
.card-row { margin-bottom:6px }

.history-grid { margin-top:18px; display:grid; grid-template-columns:repeat(3,1fr); gap:12px }
.history-card { background:#fff; padding:14px; border-radius:10px; border:1px solid #eef2ff; box-shadow: 0 6px 18px rgba(2,6,23,0.04) }
.history-title { font-weight:700; margin-bottom:8px }
.empty-card { padding:18px; border-radius:8px; border:2px dashed #e6e6e6; color:#6b7280; text-align:center; min-height:72px; display:flex; align-items:center; justify-content:center }

.primary { padding:8px 14px; border-radius:9999px; border:2px solid #3b82f6; color:#3b82f6; background:#fff; font-weight:600 }
.primary:hover { background:#eff6ff }

@media (max-width: 900px) {
  .history-grid { grid-template-columns: 1fr }
  .grid-display { grid-template-columns: 1fr }
}
</style>
