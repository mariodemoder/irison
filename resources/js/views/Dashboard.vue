<script setup>
import { ref, onMounted, computed } from 'vue'
import MainLayout from '../layouts/MainLayout.vue'
import api from '../services/api'
import StatsCard from '../components/dashboard/StatsCard.vue'
import LineChartCard from '../components/dashboard/LineChartCard.vue'
import BarChartCard from '../components/dashboard/BarChartCard.vue'

const user = ref(null)
const clinic = ref(null)
const status = ref('blocked')
const trial_ends_at = ref(null)
const loading = ref(true)

const lowBonusPatients = ref([])
const lowBonusLoading = ref(true)
const alertsLoading = ref(true)
const unpaidCompletedAppointments = ref(0)
const pendingPayments = ref(0)
const partialPayments = ref(0)

const shortLowBonusList = computed(() => lowBonusPatients.value.slice(0, 5))

const importantAlerts = computed(() => {
  const items = []

  if (unpaidCompletedAppointments.value > 0) {
    items.push(`${unpaidCompletedAppointments.value} cita${unpaidCompletedAppointments.value === 1 ? '' : 's'} completada${unpaidCompletedAppointments.value === 1 ? '' : 's'} sin pago.`)
  }

  if (pendingPayments.value > 0) {
    items.push(`${pendingPayments.value} pago${pendingPayments.value === 1 ? '' : 's'} pendiente${pendingPayments.value === 1 ? '' : 's'}.`)
  }

  if (partialPayments.value > 0) {
    items.push(`${partialPayments.value} pago${partialPayments.value === 1 ? '' : 's'} parcial${partialPayments.value === 1 ? '' : 'es'}.`)
  }

  return items
})

const monthlyRevenue = [1200, 1500, 1800, 2100]
const monthlyRevenueLabels = ['Ene', 'Feb', 'Mar', 'Abr']
const weeklyAppointments = [20, 35, 28, 40]
const weeklyAppointmentsLabels = ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4']

const daysLeft = computed(() => {
  if (!trial_ends_at.value) return null
  const end = new Date(trial_ends_at.value)
  const now = new Date()
  const diff = end.getTime() - now.getTime()
  return Math.ceil(diff / (1000 * 60 * 60 * 24))
})

const subscriptionState = computed(() => {
  if (status.value === 'active') return { color: 'green', label: 'Suscripción activa' }
  if (status.value === 'trial') {
    if (daysLeft.value === null) return { color: 'red', label: 'Trial (sin fecha)' }
    if (daysLeft.value > 7 && daysLeft.value < 15) return { color: 'yellow', label: `Te quedan ${daysLeft.value} días de prueba` }
    if (daysLeft.value > 0 && daysLeft.value < 7) return { color: 'red', label: `Te quedan ${daysLeft.value} días de prueba` }
    if (daysLeft.value >= 15) return { color: 'yellow', label: `Te quedan ${daysLeft.value} días de prueba` }
    return { color: 'red', label: 'Tu prueba ha finalizado' }
  }
  return { color: 'red', label: 'Suscripción vencida' }
})

const subscriptionValue = computed(() => {
  if (status.value === 'active') return 'Activa'
  if (status.value === 'trial') return daysLeft.value && daysLeft.value > 0 ? `${daysLeft.value} días` : 'Finalizada'
  return 'Vencida'
})

const bonusSummary = computed(() => {
  if (lowBonusLoading.value) return 'Cargando...'
  return `${lowBonusPatients.value.length}`
})

const bonusSubtitle = computed(() => {
  if (lowBonusLoading.value) return 'Bonos por agotarse'
  if (!lowBonusPatients.value.length) return 'Sin bonos por agotarse'
  return `Mostrando ${shortLowBonusList.value.length} de ${lowBonusPatients.value.length}`
})

async function subscribe() {
  try {
    const res = await api.post('/stripe/checkout')
    window.location.href = res.data.url
  } catch (e) {
    console.error('Error creando checkout', e)
  }
}

async function subscribeFake() {
  try {
    const res = await api.post('/subscribe/fake')
    clinic.value = res.data.clinic
    status.value = res.data.status_clinic || status.value
    trial_ends_at.value = res.data.trial_ends_at || trial_ends_at.value
  } catch (e) {
    console.error('Error activando suscripción fake', e)
  }
}

async function fetchLowBonuses() {
  try {
    const res = await api.get('/bonuses/expiring')
    lowBonusPatients.value = (res.data || []).map((item) => {
      const bonusObj = item.bonus ?? item.bono ?? {}
      return {
        id: item.patient_id ?? item.id ?? item.patient?.id,
        patient_name: item.patient_name ?? item.patient?.name ?? item.name ?? '—',
        bonus_name: item.bonus_name ?? bonusObj.name ?? bonusObj.title ?? bonusObj.descripcion ?? '—',
        expires_at: item.expires_at ?? bonusObj.expires_at ?? bonusObj.expiration ?? bonusObj.expiresAt ?? null,
        sessions_left: item.sessions_left ?? item.remaining_sessions ?? item.sessions ?? 0,
      }
    })
  } catch (e) {
    console.error('Error cargando bonos por agotarse', e)
  } finally {
    lowBonusLoading.value = false
  }
}

async function fetchImportantAlerts() {
  alertsLoading.value = true
  try {
    const [appointmentsRes, pendingPaymentsRes] = await Promise.all([
      api.get('/appointments'),
      api.get('/payments', { params: { status: 'pending', per_page: 1 } }),
    ])

    const appointments = Array.isArray(appointmentsRes.data?.data)
      ? appointmentsRes.data.data
      : (Array.isArray(appointmentsRes.data) ? appointmentsRes.data : [])

    unpaidCompletedAppointments.value = appointments.filter(a => a.status === 'completed' && a.payment_status === 'pending').length
    partialPayments.value = appointments.filter(a => a.payment_status === 'partially_paid').length
    pendingPayments.value = Number(pendingPaymentsRes.data?.meta?.total || 0)
  } catch (e) {
    console.error('Error cargando alertas importantes', e)
    unpaidCompletedAppointments.value = 0
    partialPayments.value = 0
    pendingPayments.value = 0
  } finally {
    alertsLoading.value = false
  }
}

onMounted(async () => {
  try {
    const res = await api.get('/me')
    user.value = res.data.user
    clinic.value = res.data.clinic
    status.value = res.data.status || status.value
    trial_ends_at.value = res.data.trial_ends_at || null
  } catch (e) {
    console.error('Error cargando /me', e)
  } finally {
    loading.value = false
  }

  fetchLowBonuses()
  fetchImportantAlerts()
})
</script>

<template>
  <MainLayout>
    <div v-if="loading">Cargando...</div>

    <div v-else class="dashboard-container">
      <header class="dashboard-header">
        <h1>Dashboard</h1>
        <div class="clinic-badge">{{ clinic?.name ?? 'FisioMeca' }}</div>
      </header>

      <div class="dashboard-grid">
        <StatsCard
          title="Suscripción"
          :value="subscriptionValue"
          :subtitle="subscriptionState.label"
          :details="`Usuario: ${user?.name ?? '—'}`"
        >
          <template #actions>
            <button v-if="status === 'blocked'" class="btn btn-sm" @click.prevent="subscribe">Activar plan (Stripe)</button>
            <button v-if="status === 'blocked'" class="btn btn-sm" @click.prevent="subscribeFake">Activar plan (fake)</button>
          </template>
        </StatsCard>

        <StatsCard
          title="Bonos"
          :value="bonusSummary"
          :subtitle="bonusSubtitle"
          :details="shortLowBonusList.length ? `${shortLowBonusList[0].patient_name} · ${shortLowBonusList[0].bonus_name}` : '—'"
        />

        <LineChartCard
          title="Ingresos mensuales"
          :labels="monthlyRevenueLabels"
          :values="monthlyRevenue"
        />

        <BarChartCard
          title="Citas por semana"
          :labels="weeklyAppointmentsLabels"
          :values="weeklyAppointments"
        />
      </div>

      <div v-if="shortLowBonusList.length" class="bonus-inline card-list">
        <div class="inline-title">Bonos por agotarse</div>
        <ul>
          <li v-for="p in shortLowBonusList" :key="`${p.id}-${p.bonus_name}`">
            Paciente <router-link :to="`/patients/${p.id}`">{{ p.patient_name }}</router-link>
            · Bono: <strong>{{ p.bonus_name }}</strong>
            <span v-if="p.expires_at"> · expira {{ p.expires_at }}</span>
            <span class="sessions"> · Queda {{ p.sessions_left }} sesión<span v-if="p.sessions_left > 1">es</span></span>
          </li>
        </ul>
      </div>

      <div class="alerts-inline card-list">
        <div class="inline-title">Alertas importantes</div>
        <div v-if="alertsLoading" class="alerts-empty">Cargando alertas...</div>
        <ul v-else-if="importantAlerts.length" class="alerts-list">
          <li v-for="alert in importantAlerts" :key="alert" class="alerts-item">
            <span class="alert-dot" aria-hidden="true"></span>
            <span>{{ alert }}</span>
          </li>
        </ul>
        <div v-else class="alerts-empty">Sin alertas pendientes.</div>
      </div>
    </div>
  </MainLayout>
</template>

<style scoped>
.dashboard-container {
  padding: 40px;
  background: var(--bg-app);
  min-height: 100vh;
}

.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
}

.dashboard-header h1 {
  margin: 0;
}

.clinic-badge {
  background: var(--secondary);
  color: #fff;
  border-radius: 999px;
  padding: 8px 16px;
  font-weight: 600;
  font-size: 14px;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
}

.bonus-inline {
  margin-top: 24px;
  background: var(--bg-card);
  border: 1px solid #94a3b8;
  border-radius: 20px;
  padding: 16px 20px;
}

.inline-title {
  font-weight: 700;
  margin-bottom: 8px;
}

.bonus-inline ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

.bonus-inline li {
  padding: 6px 0;
  border-bottom: 1px solid rgba(15, 23, 42, 0.08);
}

.bonus-inline li:last-child {
  border-bottom: none;
}

.bonus-inline a {
  color: var(--secondary);
  text-decoration: none;
  font-weight: 600;
}

.bonus-inline a:hover {
  text-decoration: underline;
}

.sessions {
  color: var(--text-muted, #6b7280);
}

.alerts-inline {
  margin-top: 16px;
  background: var(--bg-card);
  border: 1px solid #fdba74;
  border-radius: 20px;
  padding: 12px 16px;
}

.alerts-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.alerts-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  padding: 4px 0;
}

.alert-dot {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: #f59e0b;
  flex: 0 0 auto;
}

.alerts-empty {
  font-size: 13px;
  color: var(--text-muted, #6b7280);
}

@media (max-width: 900px) {
  .dashboard-container {
    padding: 18px;
  }

  .dashboard-grid {
    grid-template-columns: 1fr;
  }
}
</style>
