<template>
  <div class="payments-page">
    <h1>Mis pagos</h1>

    <div class="tabs">
      <button :class="{ active: tab === 'history' }" @click="tab = 'history'">Historial</button>
      <button :class="{ active: tab === 'pending' }" @click="tab = 'pending'">
        Pendientes
        <span v-if="pendingCount" class="tab-badge">{{ pendingCount }}</span>
      </button>
    </div>

    <div v-if="loading" class="loading">Cargando...</div>

    <div v-else-if="tab === 'history'">
      <div v-if="payments.length === 0" class="empty-state">
        <p>No hay pagos registrados</p>
      </div>
      <div v-else class="payment-list">
        <div v-for="payment in payments" :key="payment.id" class="payment-card">
          <div class="payment-info">
            <p class="payment-concept">{{ payment.concept || 'Pago' }}</p>
            <p class="payment-date">{{ new Date(payment.paid_at || payment.created_at).toLocaleDateString('es') }}</p>
          </div>
          <div class="payment-amount">
            <span class="amount">€{{ payment.amount }}</span>
            <span class="payment-status" :class="payment.status">{{ payment.status }}</span>
          </div>
        </div>
      </div>
    </div>

    <div v-else>
      <div v-if="pendingPayments.length === 0" class="empty-state">
        <p>No tienes pagos pendientes</p>
      </div>
      <div v-else class="payment-list">
        <div v-for="payment in pendingPayments" :key="payment.id" class="payment-card pending">
          <div class="payment-info">
            <p class="payment-concept">{{ payment.concept || 'Pago pendiente' }}</p>
            <p class="payment-date">Cita #{{ payment.appointment_id }}</p>
          </div>
          <div class="payment-amount">
            <span class="amount pending-amount">€{{ payment.amount }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import patientApi from '../../patient/services/patientApi'

const tab = ref('history')
const payments = ref([])
const pendingPayments = ref([])
const pendingCount = ref(0)
const loading = ref(true)

async function loadHistory() {
  try {
    const { data } = await patientApi.get('/payments')
    payments.value = data.data
  } catch (e) {
    console.error('Error loading payments:', e)
  }
}

async function loadPending() {
  try {
    const { data } = await patientApi.get('/payments/pending')
    pendingPayments.value = data.payments
    pendingCount.value = data.payments.length
  } catch (e) {
    console.error('Error loading pending payments:', e)
  }
}

onMounted(async () => {
  await loadHistory()
  await loadPending()
  loading.value = false
})
</script>

<style scoped>
.payments-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

h1 {
  font-size: 20px;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.tabs {
  display: flex;
  gap: 8px;
}

.tabs button {
  flex: 1;
  padding: 10px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #ffffff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  color: #64748b;
  position: relative;
}

.tabs button.active {
  background: #6366f1;
  color: #ffffff;
  border-color: #6366f1;
}

.tab-badge {
  background: #dc2626;
  color: #ffffff;
  font-size: 11px;
  padding: 1px 6px;
  border-radius: 10px;
  margin-left: 4px;
}

.loading {
  text-align: center;
  padding: 40px;
  color: #64748b;
}

.empty-state {
  text-align: center;
  padding: 40px;
  color: #64748b;
}

.payment-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.payment-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.payment-card.pending {
  border-left: 4px solid #f59e0b;
}

.payment-info {
  display: flex;
  flex-direction: column;
}

.payment-concept {
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.payment-date {
  font-size: 13px;
  color: #64748b;
  margin: 2px 0 0;
}

.payment-amount {
  text-align: right;
}

.amount {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
}

.pending-amount {
  color: #f59e0b;
}

.payment-status {
  display: block;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.payment-status.completed { color: #16a34a; }
.payment-status.pending { color: #f59e0b; }
.payment-status.refunded { color: #64748b; }
</style>
