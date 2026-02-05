<script setup>
import { ref, onMounted, computed } from 'vue'
import MainLayout from '../layouts/MainLayout.vue'
import { useRouter } from 'vue-router'
import logout from '../utils/logout'
import api from '../services/api'

const router = useRouter()
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
    if (daysLeft.value > 7 | daysLeft.value < 15) return { color: 'yellow', label: `Te quedan ${daysLeft.value} días de prueba` }
    if (daysLeft.value > 0 | daysLeft.value < 7) return { color: 'red', label: `Te quedan ${daysLeft.value} días de prueba` }
    return { color: 'red', label: 'Tu prueba ha finalizado' }
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

function logoutAction() {
  logout(router)
}
</script>

<template>
  <MainLayout>
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
          <div style="margin-left:12px">
            <button class="btn btn-sm" @click.prevent="logoutAction">Cerrar sesión</button>
          </div>
        </div>
      </div>

      <div v-if="status === 'trial'">
        <p v-if="daysLeft !== null && daysLeft > 0">
          Te quedan <strong>{{ daysLeft }}</strong> días de prueba
        </p>
        <p v-else>Tu prueba ha finalizado</p>
      </div>

      <div v-else-if="status === 'blocked'">
        <p class="alert">Tu prueba ha finalizado</p>
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
  </MainLayout>
</template>

<style scoped>
/* Botón más pequeño */
.btn {
  background: var(--primary, #1f2937);
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 8px 12px;
  font-size: 14px;
  cursor: pointer;
}
.btn.btn-sm {
  padding: 4px 8px;
  font-size: 12px;
  border-radius: 6px;
}

/* Card float / sub-banner más compacto */
.sub-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  background: rgba(255,255,255,0.9);
  padding: 8px 10px;
  border-radius: 10px;
  box-shadow: 0 6px 18px rgba(2,6,23,0.06);
}
.sub-banner .meta { display:flex;flex-direction:column }
.sub-banner .small { font-size:12px; color:var(--text-muted,#6b7280) }
.status-dot { width:10px;height:10px;border-radius:50%;display:inline-block }
.status-dot.green { background: #10b981 }
.status-dot.yellow { background: #f59e0b }
.status-dot.red { background: #ef4444 }

.alert { color: #b91c1c }
.ok { color: #059669 }
</style>
