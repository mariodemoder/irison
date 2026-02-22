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

// listado compacto: pacientes con bonos cerca de agotarse
const lowBonusPatients = ref([])
const lowBonusLoading = ref(true)

const shortLowBonusList = computed(() => lowBonusPatients.value.slice(0, 5))

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
  // cargar listado compacto en paralelo
  fetchLowBonuses()
})

async function fetchLowBonuses() {
  try {
    const res = await api.get('/bonuses/expiring')
    console.log('bonuses/expiring response:', res.data) // revisar en consola
    // Normalizar la respuesta a los campos que usa la plantilla
    lowBonusPatients.value = (res.data || []).map(item => {
      const bonusObj = item.bonus ?? item.bono ?? {}
      return {
        id: item.patient_id ?? item.id ?? item.patient?.id,
        patient_name: item.patient_name ?? item.patient?.name ?? item.name ?? '—',
        bonus_name: item.bonus_name ?? bonusObj.name ?? bonusObj.title ?? bonusObj.descripcion ?? '—',
        expires_at: item.expires_at ?? bonusObj.expires_at ?? bonusObj.expiration ?? bonusObj.expiresAt ?? null,
        sessions_left: item.sessions_left ?? item.remaining_sessions ?? item.sessions ?? 0,
        // conservar objeto original por si hace falta
        _raw: item
      }
    })
  } catch (e) {
    console.error('Error cargando bonos por agotarse', e)
  } finally {
    lowBonusLoading.value = false
  }
}

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

      <!-- Listado compacto de bonos por agotarse -->
      <div v-if="!lowBonusLoading && lowBonusPatients.length" class="compact-card" style="margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <div style="font-weight:600">Bonos por agotarse</div>
          <div class="small" style="font-size:12px;color:var(--text-muted,#6b7280)">Mostrando {{ shortLowBonusList.length }} de {{ lowBonusPatients.length }}</div>
        </div>
        <ul class="compact-list" style="margin-top:6px;">
          <li v-for="p in shortLowBonusList" :key="p.id" class="compact-item">
            Paciente <router-link :to="`/patients/${p.id}`" class="compact-link">{{ p.patient_name }}</router-link>
            <span class="compact-bonus">· Bono: <strong>{{ p.bonus_name ?? '—' }}</strong><span v-if="p.expires_at"> · expira {{ p.expires_at }}</span></span>
            <div style="float:right;color:var(--text-muted,#6b7280)">Queda {{ p.sessions_left }} sesión<span v-if="p.sessions_left > 1">es</span></div>
          </li>
        </ul>
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
/* Buttons use global .btn styles from resources/css/app.css */

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

/* Compact list styles */
.compact-card {
  background: rgba(255,255,255,0.95);
  padding: 8px 10px;
  border-radius: 10px;
  box-shadow: 0 6px 18px rgba(2,6,23,0.04);
  font-size: 13px;
}
.compact-list { list-style:none;margin:6px 0 0;padding:0;max-height:88px;overflow:auto }
.compact-item { padding:6px 0;border-bottom:1px solid rgba(0,0,0,0.04);color:var(--text,#111827) }
.compact-item:last-child { border-bottom: none }
.compact-link { color: var(--primary,#0369a1); font-weight:600; text-decoration:none }
.compact-link:hover { text-decoration:underline }
.compact-bonus { margin-left:8px; font-size:12px; color:var(--text-muted,#6b7280) }
.compact-bonus strong { color:var(--text,#111827); font-weight:600 }
</style>
