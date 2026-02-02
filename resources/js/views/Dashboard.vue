<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '../services/api'

const user = ref(null)
const clinic = ref(null)
const status = ref('blocked')
const trial_ends_at = ref(null)
const loading = ref(true)

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
    if (daysLeft.value > 7) return { color: 'green', label: `Trial — ${daysLeft.value} días` }
    if (daysLeft.value > 0) return { color: 'yellow', label: `Trial — ${daysLeft.value} días` }
    return { color: 'red', label: 'Trial vencido' }
  }
  return { color: 'red', label: 'Suscripción vencida' }
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
    // actualizar estado en UI
    clinic.value = res.data.clinic
    status.value = res.data.status_clinic || status.value
    trial_ends_at.value = res.data.trial_ends_at || trial_ends_at.value
  } catch (e) {
    console.error('Error activando suscripción fake', e)
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
    // si ocurre 403 por otra razón, mantenemos estado por defecto
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="loading">Cargando...</div>

  <div v-else>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
      <h1>Dashboard</h1>

      <div class="sub-banner">
        <div class="meta">
          <div style="font-weight:600">{{ user?.name ?? '—' }}</div>
          <div class="small">{{ clinic?.name ?? '—' }}</div>
        </div>

        <div style="display:flex;align-items:center;gap:8px">
          <span :class="['status-dot', subscriptionState.color]"></span>
          <div style="font-size:13px">{{ subscriptionState.label }}</div>
        </div>
      </div>
    </div>

    <div v-if="status === 'trial'">
      <p>
        Estás en periodo de prueba hasta
        <strong>{{ trial_ends_at }}</strong>
      </p>
    </div>

    <div v-else-if="status === 'blocked'">
      <p class="alert">Tu periodo de prueba ha terminado.</p>
      <div style="display:flex;gap:8px;">
        <button @click="subscribe" class="btn">Activar plan (Stripe)</button>
        <button @click="subscribeFake" class="btn">Activar plan (fake)</button>
      </div>
    </div>

    <div v-else-if="status === 'active'">
      <p class="ok">Plan activo ✅</p>
      <p>Bienvenido {{ user?.name ?? '—' }}</p>
      <p>Clínica: {{ clinic?.name ?? '—' }}</p>
    </div>
  </div>
</template>
