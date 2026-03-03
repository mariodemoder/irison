<script setup>
import { ref, onMounted, computed } from 'vue'
import MainLayout from '../layouts/MainLayout.vue'
import api from '../services/api'
import LineChartCard from '../components/dashboard/LineChartCard.vue'
import BarChartCard from '../components/dashboard/BarChartCard.vue'

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

const currencyFormatter = new Intl.NumberFormat('es-ES', {
  style: 'currency',
  currency: 'EUR',
})

const REAL_PAYMENT_METHODS = new Set(['cash', 'card', 'transfer', 'bizum', 'stripe'])

const todayLabel = computed(() => {
  return new Date().toLocaleDateString('es-ES', {
    weekday: 'long',
    day: '2-digit',
    month: '2-digit',
  })
})

const todayDateQuery = computed(() => todayIsoDate())

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
      key: 'unpaid-sessions',
      text: `${unpaidSessionsCount.value} sesi${unpaidSessionsCount.value === 1 ? 'ón' : 'ones'} impaga${unpaidSessionsCount.value === 1 ? '' : 's'}.`,
      to: {
        path: '/appointments/day',
        query: {
          unpaid: '1',
          all: '1',
        },
      },
    })
  }

  return items
})

const monthlyRevenue = [1200, 1500, 1800, 2100]
const monthlyRevenueLabels = ['Ene', 'Feb', 'Mar', 'Abr']
const weeklyAppointments = [20, 35, 28, 40]
const weeklyAppointmentsLabels = ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4']


async function fetchImportantAlerts() {
  alertsLoading.value = true
  try {
    const [appointmentsRes, unpaidBonusesRes, totalCreditInFavor] = await Promise.all([
      api.get('/appointments'),
      api.get('/bonuses/unpaid-summary'),
      fetchTotalCreditInFavor(),
    ])

    const appointments = Array.isArray(appointmentsRes.data?.data)
      ? appointmentsRes.data.data
      : (Array.isArray(appointmentsRes.data) ? appointmentsRes.data : [])

    unpaidSessionsCount.value = appointments.filter((appointment) => {
      const paymentStatus = appointment?.payment_status
      const status = appointment?.status

      return status !== 'canceled' && ['pending', 'partially_paid'].includes(paymentStatus)
    }).length

    unpaidBonusesCount.value = Number(unpaidBonusesRes.data?.data?.total || 0)
    creditInFavorAmount.value = totalCreditInFavor
  } catch (e) {
    console.error('Error cargando alertas importantes', e)
    unpaidBonusesCount.value = 0
    creditInFavorAmount.value = 0
    unpaidSessionsCount.value = 0
  } finally {
    alertsLoading.value = false
  }
}

function todayIsoDate() {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

function isSameDateString(dateValue, targetIsoDate) {
  if (!dateValue) return false
  if (typeof dateValue === 'string' && dateValue.length >= 10) {
    return dateValue.slice(0, 10) === targetIsoDate
  }

  const parsed = new Date(dateValue)
  if (Number.isNaN(parsed.getTime())) return false

  const year = parsed.getFullYear()
  const month = String(parsed.getMonth() + 1).padStart(2, '0')
  const day = String(parsed.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}` === targetIsoDate
}

async function fetchTodayCashCollected() {
  const targetDate = todayIsoDate()
  let total = 0
  let currentPage = 1
  let lastPage = 1

  do {
    const response = await api.get('/payments', {
      params: {
        status: 'completed',
        per_page: 100,
        page: currentPage,
      },
    })

    const rows = Array.isArray(response.data?.data) ? response.data.data : []

    total += rows.reduce((sum, payment) => {
      const method = String(payment?.method || '').toLowerCase()
      if (!REAL_PAYMENT_METHODS.has(method)) return sum
      if (!isSameDateString(payment?.paid_at, targetDate)) return sum

      return sum + Number(payment?.amount || 0)
    }, 0)

    lastPage = Number(response.data?.meta?.last_page || currentPage)
    currentPage += 1
  } while (currentPage <= lastPage)

  return Number(total.toFixed(2))
}

async function fetchTodaySummary() {
  todaySummaryLoading.value = true

  try {
    const res = await api.get('/appointments', {
      params: {
        date: todayIsoDate(),
      },
    })

    const appointments = Array.isArray(res.data?.data)
      ? res.data.data
      : (Array.isArray(res.data) ? res.data : [])

    const completed = appointments.filter((appointment) => appointment?.status === 'completed').length
    const canceled = appointments.filter((appointment) => appointment?.status === 'canceled').length
    const pending = appointments.filter((appointment) => !['completed', 'canceled'].includes(appointment?.status)).length

    const activeAppointments = appointments.filter((appointment) => appointment?.status !== 'canceled')

    const collectedAmount = await fetchTodayCashCollected()

    const bonusAppointments = activeAppointments.filter((appointment) => {
      const paymentType = String(appointment?.payment_type || '')
      const paymentStatus = String(appointment?.payment_status || '')
      const hasBonusId = Boolean(appointment?.bonus_id)

      return paymentType === 'bonus' || paymentStatus === 'covered_by_pack' || hasBonusId
    })

    const bonusSessionsUsed = bonusAppointments.length
    const bonusSessionsValue = bonusAppointments.reduce((sum, appointment) => {
      return sum + Number(appointment?.price || 0)
    }, 0)

    const creditAppliedAmount = activeAppointments.reduce((sum, appointment) => {
      const creditUsages = Array.isArray(appointment?.credit_usages) ? appointment.credit_usages : []
      const usageAmount = creditUsages
        .filter((usage) => !usage?.reversed_at)
        .reduce((usageSum, usage) => usageSum + Number(usage?.amount || 0), 0)

      return sum + usageAmount
    }, 0)

    todaySummary.value = {
      total: appointments.length,
      completed,
      canceled,
      pending,
    }

    todayFinancial.value = {
      collectedAmount: Number(collectedAmount.toFixed(2)),
      bonusSessionsUsed,
      bonusSessionsValue: Number(bonusSessionsValue.toFixed(2)),
      creditAppliedAmount: Number(creditAppliedAmount.toFixed(2)),
      totalProductionAmount: Number((collectedAmount + bonusSessionsValue + creditAppliedAmount).toFixed(2)),
    }
  } catch (e) {
    console.error('Error cargando resumen del día', e)
    todaySummary.value = {
      total: 0,
      completed: 0,
      canceled: 0,
      pending: 0,
    }
    todayFinancial.value = {
      collectedAmount: 0,
      bonusSessionsUsed: 0,
      bonusSessionsValue: 0,
      creditAppliedAmount: 0,
      totalProductionAmount: 0,
    }
  } finally {
    todaySummaryLoading.value = false
  }
}

async function fetchTotalCreditInFavor() {
  let total = 0
  let currentPage = 1
  let lastPage = 1

  do {
    const response = await api.get('/patients', {
      params: {
        per_page: 100,
        page: currentPage,
      },
    })

    const patients = Array.isArray(response.data?.data) ? response.data.data : []
    total += patients.reduce((sum, patient) => sum + Number(patient?.available_credit || 0), 0)

    lastPage = Number(response.data?.meta?.last_page || currentPage)
    currentPage += 1
  } while (currentPage <= lastPage)

  return Number(total.toFixed(2))
}

onMounted(async () => {
  loading.value = false
  fetchTodaySummary()
  fetchImportantAlerts()
})
</script>

<template>
  <MainLayout>
    <div v-if="loading">Cargando...</div>

    <div v-else class="dashboard-container">
      <section class="today-summary">
        <div class="summary-title">Resumen del día - Hoy · {{ todayLabel }}</div>
        
        <div v-if="todaySummaryLoading" class="alerts-empty">Cargando resumen de hoy...</div>

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
        <div v-if="alertsLoading" class="alerts-empty">Cargando alertas...</div>
        <ul v-else-if="importantAlerts.length" class="alerts-list">
          <li v-for="alert in importantAlerts" :key="alert.key" class="alerts-item">
            <span class="alert-dot" aria-hidden="true"></span>
            <router-link :to="alert.to" class="alert-link">{{ alert.text }}</router-link>
          </li>
        </ul>
        <div v-else class="alerts-empty">Sin alertas pendientes.</div>
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
