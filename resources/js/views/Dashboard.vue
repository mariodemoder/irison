<script setup>
import { ref, onMounted, computed } from 'vue'
import MainLayout from '../layouts/MainLayout.vue'
import api from '../services/api'
import LineChartCard from '../components/dashboard/LineChartCard.vue'
import BarChartCard from '../components/dashboard/BarChartCard.vue'
import AppLoading from '../components/AppLoading.vue'

const loading = ref(true)

const alertsLoading = ref(true)
const todaySummaryLoading = ref(true)
const todaySummary = ref({
  total: 0,
  completed: 0,
  canceled: 0,
  pending: 0,
})
const todayFinancial = ref({
  collectedAmount: 0,
  bonusSessionsUsed: 0,
  bonusSessionsValue: 0,
  creditAppliedAmount: 0,
  totalProductionAmount: 0,
})
const unpaidBonusesCount = ref(0)
const creditInFavorAmount = ref(0)
const unpaidSessionsCount = ref(0)
const unpaidSessionsTodayCount = ref(0)
const completedUnpaidAppointmentsCount = ref(0)
const partialAppointmentsCount = ref(0)
const exhaustedBonusPatientsCount = ref(0)
const patientsWithCreditCount = ref(0)
const dashboardDate = ref(todayIsoDate())

const currencyFormatter = new Intl.NumberFormat('es-ES', {
  style: 'currency',
  currency: 'EUR',
})

const todayLabel = computed(() => {
  return new Date().toLocaleDateString('es-ES', {
    weekday: 'long',
    day: '2-digit',
    month: '2-digit',
  })
})

const todayDateQuery = computed(() => dashboardDate.value)

const importantAlerts = computed(() => {
  const items = []

  if (unpaidBonusesCount.value > 0) {
    items.push({
      key: 'unpaid-bonuses',
      text: `${unpaidBonusesCount.value} bono${unpaidBonusesCount.value === 1 ? '' : 's'} impago${unpaidBonusesCount.value === 1 ? '' : 's'}.`,
      to: {
        path: '/bonuses',
        query: {
          payment_state: 'unpaid',
        },
      },
    })
  }

  if (creditInFavorAmount.value > 0) {
    items.push({
      key: 'credit-in-favor',
      text: `Créditos a favor: ${currencyFormatter.format(creditInFavorAmount.value)}.`,
      to: {
        path: '/patients',
        query: {
          has_credit: '1',
        },
      },
    })
  }

  if (unpaidSessionsCount.value > 0) {
    items.push({
      key: 'unpaid-sessions-total',
      text: `${unpaidSessionsCount.value} sesi${unpaidSessionsCount.value === 1 ? 'ón' : 'ones'} impaga${unpaidSessionsCount.value === 1 ? '' : 's'} totales.`,
      to: {
        path: '/appointments/day',
        query: {
          unpaid: '1',
          all: '1',
        },
      },
    })
  }

  if (unpaidSessionsTodayCount.value > 0) {
    items.push({
      key: 'unpaid-sessions-today',
      text: `${unpaidSessionsTodayCount.value} sesi${unpaidSessionsTodayCount.value === 1 ? 'ón' : 'ones'} impaga${unpaidSessionsTodayCount.value === 1 ? '' : 's'} de hoy.`,
      to: {
        path: '/appointments/day',
        query: {
          date: dashboardDate.value,
          unpaid: '1',
        },
      },
    })
  }

  return items
})

const riskAlerts = computed(() => {
  return [
    {
      key: 'risk-completed-unpaid',
      value: completedUnpaidAppointmentsCount.value,
      text: 'Citas completadas sin pagar',
      to: {
        path: '/appointments/day',
        query: {
          status: 'completed',
          unpaid: '1',
          all: '1',
        },
      },
    },
    {
      key: 'risk-partials',
      value: partialAppointmentsCount.value,
      text: 'Citas parciales',
      to: {
        path: '/appointments/day',
        query: {
          payment: 'partially_paid',
          all: '1',
        },
      },
    },
    {
      key: 'risk-exhausted-bonus',
      value: exhaustedBonusPatientsCount.value,
      text: 'Pacientes con bono agotado',
      to: {
        path: '/bonuses',
        query: {
          status: 'exhausted',
        },
      },
    },
    {
      key: 'risk-credit-available',
      value: patientsWithCreditCount.value,
      text: 'Pacientes con crédito disponible',
      to: {
        path: '/patients',
        query: {
          has_credit: '1',
        },
      },
    },
  ]
})

const monthlyRevenue = [1200, 1500, 1800, 2100]
const monthlyRevenueLabels = ['Ene', 'Feb', 'Mar', 'Abr']
const weeklyAppointments = [20, 35, 28, 40]
const weeklyAppointmentsLabels = ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4']

function todayIsoDate() {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

function resetTodaySummary() {
  todaySummary.value = {
    total: 0,
    completed: 0,
    canceled: 0,
    pending: 0,
  }
}

function resetTodayFinancial() {
  todayFinancial.value = {
    collectedAmount: 0,
    bonusSessionsUsed: 0,
    bonusSessionsValue: 0,
    creditAppliedAmount: 0,
    totalProductionAmount: 0,
  }
}

function resetAlertsAndRisks() {
  unpaidBonusesCount.value = 0
  creditInFavorAmount.value = 0
  unpaidSessionsCount.value = 0
  unpaidSessionsTodayCount.value = 0
  completedUnpaidAppointmentsCount.value = 0
  partialAppointmentsCount.value = 0
  exhaustedBonusPatientsCount.value = 0
  patientsWithCreditCount.value = 0
}

async function fetchDashboardCards() {
  todaySummaryLoading.value = true

  try {
    const res = await api.get('/dashboard/summary', {
      params: {
        block: 'cards',
      },
    })
    const data = res.data?.data ?? {}

    if (typeof data?.date === 'string' && data.date.length >= 10) {
      dashboardDate.value = data.date.slice(0, 10)
    } else {
      dashboardDate.value = todayIsoDate()
    }

    const summary = data.today_summary ?? {}
    todaySummary.value = {
      total: Number(summary.total || 0),
      completed: Number(summary.completed || 0),
      canceled: Number(summary.canceled || 0),
      pending: Number(summary.pending || 0),
    }

    const financial = data.today_financial ?? {}
    todayFinancial.value = {
      collectedAmount: Number(financial.collectedAmount || 0),
      bonusSessionsUsed: Number(financial.bonusSessionsUsed || 0),
      bonusSessionsValue: Number(financial.bonusSessionsValue || 0),
      creditAppliedAmount: Number(financial.creditAppliedAmount || 0),
      totalProductionAmount: Number(financial.totalProductionAmount || 0),
    }
  } catch (e) {
    console.error('Error cargando tarjetas del dashboard', e)
    dashboardDate.value = todayIsoDate()
    resetTodaySummary()
    resetTodayFinancial()
  } finally {
    todaySummaryLoading.value = false
    loading.value = false
  }
}

async function fetchDashboardAlerts() {
  alertsLoading.value = true

  try {
    const res = await api.get('/dashboard/summary', {
      params: {
        block: 'alerts',
      },
    })

    const data = res.data?.data ?? {}

    const important = data.important_alerts ?? {}
    unpaidBonusesCount.value = Number(important.unpaidBonusesCount || 0)
    creditInFavorAmount.value = Number(important.creditInFavorAmount || 0)
    unpaidSessionsCount.value = Number(important.unpaidSessionsCount || 0)
    unpaidSessionsTodayCount.value = Number(important.unpaidSessionsTodayCount || 0)

    const risks = data.risk_alerts ?? {}
    completedUnpaidAppointmentsCount.value = Number(risks.completedUnpaidAppointmentsCount || 0)
    partialAppointmentsCount.value = Number(risks.partialAppointmentsCount || 0)
    exhaustedBonusPatientsCount.value = Number(risks.exhaustedBonusPatientsCount || 0)
    patientsWithCreditCount.value = Number(risks.patientsWithCreditCount || 0)
  } catch (e) {
    console.error('Error cargando alertas y riesgos del dashboard', e)
    resetAlertsAndRisks()
  } finally {
    alertsLoading.value = false
  }
}

onMounted(async () => {
  await fetchDashboardCards()
  fetchDashboardAlerts()
})
</script>

<template>
  <MainLayout>
    <AppLoading v-if="loading" message="Cargando dashboard..." />

    <div v-else class="dashboard-container">
      <section class="today-summary">
        <div class="summary-title">Resumen del día - Hoy · {{ todayLabel }}</div>
        
        <AppLoading v-if="todaySummaryLoading" compact message="Cargando resumen de hoy..." />

        <div v-else class="today-grid">
          <router-link class="today-card" :to="{ path: '/appointments/day', query: { date: todayDateQuery } }">
            <div class="today-label"><span class="today-icon today" aria-hidden="true"></span> Citas hoy</div>
            <div class="today-value">{{ todaySummary.total }}</div>
          </router-link>

          <router-link class="today-card" :to="{ path: '/appointments/day', query: { date: todayDateQuery, status: 'completed' } }">
            <div class="today-label"><span class="today-icon completed" aria-hidden="true"></span> Citas completadas</div>
            <div class="today-value">{{ todaySummary.completed }}</div>
          </router-link>

          <router-link class="today-card" :to="{ path: '/appointments/day', query: { date: todayDateQuery, status: 'canceled' } }">
            <div class="today-label"><span class="today-icon canceled" aria-hidden="true"></span> Canceladas</div>
            <div class="today-value">{{ todaySummary.canceled }}</div>
          </router-link>

          <router-link class="today-card" :to="{ path: '/appointments/day', query: { date: todayDateQuery, status: 'pending' } }">
            <div class="today-label"><span class="today-icon pending" aria-hidden="true"></span> Pendientes</div>
            <div class="today-value">{{ todaySummary.pending }}</div>
          </router-link>
        </div>

        <div v-if="!todaySummaryLoading" class="today-finance">
                    <div class="today-finance-grid">
            <div class="today-card today-finance-card">
              <div class="today-label">
                <span class="finance-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="6" width="18" height="12" rx="2"></rect>
                    <circle cx="12" cy="12" r="2.5"></circle>
                    <path d="M7 12h.01M17 12h.01"></path>
                  </svg>
                </span>
                Total cobrado hoy
              </div>
              <div class="today-value">{{ currencyFormatter.format(todayFinancial.collectedAmount) }}</div>
              <div class="today-finance-note">efectivo/tarjeta</div>
            </div>

            <div class="today-card today-finance-card">
              <div class="today-label">
                <span class="finance-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"></path>
                    <path d="M12 6v12"></path>
                  </svg>
                </span>
                Sesiones por bono
              </div>
              <div class="today-value">{{ todayFinancial.bonusSessionsUsed }}</div>
              <div class="today-finance-note">valor {{ currencyFormatter.format(todayFinancial.bonusSessionsValue) }}</div>
            </div>

            <div class="today-card today-finance-card">
              <div class="today-label">
                <span class="finance-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2.5" y="5" width="19" height="14" rx="2"></rect>
                    <path d="M2.5 10h19M7 15h4"></path>
                  </svg>
                </span>
                Crédito aplicado
              </div>
              <div class="today-value">{{ currencyFormatter.format(todayFinancial.creditAppliedAmount) }}</div>
              <div class="today-finance-note">descontado hoy</div>
            </div>

            <div class="today-card today-finance-card">
              <div class="today-label">
                <span class="finance-icon" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 16l5-5 4 3 7-7"></path>
                    <path d="M14 7h6v6"></path>
                  </svg>
                </span>
                Producción total del día
              </div>
              <div class="today-value">{{ currencyFormatter.format(todayFinancial.totalProductionAmount) }}</div>
              <div class="today-finance-note">real + bono + crédito</div>
            </div>
          </div>
        </div>
      </section>

      <div class="alerts-inline card-list">
        <div class="inline-title">Alertas importantes</div>
        <AppLoading v-if="alertsLoading" compact message="Cargando alertas..." />
        <ul v-else-if="importantAlerts.length" class="alerts-list">
          <li v-for="alert in importantAlerts" :key="alert.key" class="alerts-item">
            <span class="alert-dot" aria-hidden="true"></span>
            <router-link :to="alert.to" class="alert-link">{{ alert.text }}</router-link>
          </li>
        </ul>
        <div v-else class="alerts-empty">Sin alertas pendientes.</div>
      </div>

      <div class="alerts-inline card-list risks-inline">
        <div class="inline-title">Pendientes importantes · Riesgos</div>
        <div class="alerts-subtitle">Estos son puntos donde se pierde dinero.</div>
        <AppLoading v-if="alertsLoading" compact message="Cargando riesgos..." />
        <ul v-else class="alerts-list">
          <li v-for="risk in riskAlerts" :key="risk.key" class="alerts-item">
            <span class="alert-dot" aria-hidden="true"></span>
            <router-link :to="risk.to" class="alert-link">{{ risk.text }}: {{ risk.value }}</router-link>
          </li>
        </ul>
      </div>

      <div class="dashboard-grid">
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
  margin-top: 24px;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 24px;
}

.today-summary {
  margin-bottom: 20px;
  background: var(--bg-card);
  border: 1px solid #94a3b8;
  border-radius: 20px;
  padding: 16px 20px;
  padding-top: 30px;
  position: relative;
}

.summary-title {
  position: absolute;
  top: -14px;
  left: 24px;
  background: var(--secondary);
  color: white;
  padding: 6px 16px;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 600;
}

.today-subtitle {
  margin-top: -2px;
  margin-bottom: 10px;
  font-size: 13px;
  color: var(--text-muted, #6b7280);
  text-transform: capitalize;
}

.today-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.today-card {
  border: 1px solid rgba(15, 23, 42, 0.12);
  border-radius: 12px;
  padding: 10px 12px;
  background: var(--bg-app);
  text-decoration: none;
  color: inherit;
  display: block;
}

.today-card:hover {
  border-color: rgba(37, 99, 235, 0.4);
}

.today-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--text-muted, #6b7280);
}

.today-icon {
  width: 9px;
  height: 9px;
  border-radius: 999px;
  background: #3b82f6;
  flex: 0 0 auto;
}

.today-icon.today,
.today-icon.pending {
  background: #3b82f6;
}

.today-icon.completed {
  background: #22c55e;
}

.today-icon.canceled {
  background: #fca5a5;
}

.today-value {
  margin-top: 4px;
  font-size: 22px;
  font-weight: 800;
  line-height: 1.1;
}

.today-finance {
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px solid rgba(15, 23, 42, 0.12);
}

.today-finance-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.today-finance-card {
  display: block;
}

.today-finance-note {
  margin-top: 4px;
  font-size: 12px;
  color: var(--text-muted, #6b7280);
}

.finance-icon {
  width: 16px;
  height: 16px;
  flex: 0 0 auto;
  color: #2563eb;
}

.finance-icon svg {
  width: 100%;
  height: 100%;
}

.inline-title {
  font-weight: 700;
  margin-bottom: 8px;
}

.alerts-inline {
  margin-top: 16px;
  background: var(--bg-card);
  border: 1px solid #93c5fd;
  border-radius: 20px;
  padding: 12px 16px;
}

.risks-inline {
  margin-top: 12px;
}

.alerts-subtitle {
  margin-top: -2px;
  margin-bottom: 8px;
  font-size: 12px;
  color: var(--text-muted, #6b7280);
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

.alert-link {
  color: inherit;
  text-decoration: none;
}

.alert-link:hover {
  text-decoration: underline;
}

.alert-dot {
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: #fd8331;
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

  .today-grid {
    grid-template-columns: 1fr;
  }

  .today-finance-grid {
    grid-template-columns: 1fr;
  }
}
</style>
