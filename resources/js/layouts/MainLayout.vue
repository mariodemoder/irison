<template>
  <div class="min-h-screen flex bg-gray-50">
    <aside :class="['w-64 bg-white border-r p-4', { 'hidden': !open, 'block': open }, 'md:block']" style="position:sticky;top:0;height:100vh;overflow:auto;">
      <div class="mb-6">
        <img :src="logo" alt="Logo" class="w-40 h-40 object-contain" />
      </div>

      <nav class="space-y-1">
        <router-link :class="[{ 'menu-active': isActive('/dashboard') }, 'block px-3 py-2 rounded text-gray-800 hover:bg-gray-100']" to="/dashboard" @click="keepMenuOpen">Dashboard</router-link>
        <router-link :class="[{ 'menu-active': isActive('/patients') }, 'block px-3 py-2 rounded text-gray-800 hover:bg-gray-100']" to="/patients" @click="keepMenuOpen">Pacientes</router-link>
        <router-link :class="[{ 'menu-active': isActive('/appointments') }, 'block px-3 py-2 rounded text-gray-800 hover:bg-gray-100']" to="/appointments" @click="keepMenuOpen">Agenda</router-link>
        <router-link :class="[{ 'menu-active': isActive('/payments') }, 'block px-3 py-2 rounded text-gray-800 hover:bg-gray-100']" to="/payments" @click="keepMenuOpen">Pagos</router-link>
      </nav>
    </aside>

    <div class="flex-1 flex flex-col">
      <header class="h-14 bg-white border-b flex items-center px-4 justify-between">
        <div class="flex items-center gap-4">
          <button class="md:hidden p-2 rounded hover:bg-gray-100" @click="open = !open">☰</button>
        </div>

        <div class="flex items-center gap-4">
          <div class="header-card">
            <div class="header-card-label">{{ clinic?.name ?? '—' }} — <router-link to="/profile" class="user-link">{{ user?.name ?? '—' }}</router-link></div>
            <div class="header-card-sub">
              <span :class="['status-dot', subscriptionState.color]" aria-hidden="true"></span>
              <span class="sub-label">{{ subscriptionState.label }}</span>
            </div>
          </div>
        </div>
      </header>

      <main class="p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import logo from '../assets/fisiomeca.svg'
import api from '../services/api'

const MENU_OPEN_KEY = 'layout_menu_open'
const open = ref(false)
const route = useRoute()

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
    if (daysLeft.value > 7) return { color: 'yellow', label: `Prueba — quedan ${daysLeft.value} días` }
    if (daysLeft.value > 0) return { color: 'red', label: `Prueba — quedan ${daysLeft.value} días` }
    return { color: 'red', label: 'Tu prueba ha finalizado' }
  }
  return { color: 'red', label: 'Suscripción vencida' }
})

onMounted(async () => {
  const persistedOpen = localStorage.getItem(MENU_OPEN_KEY)
  if (persistedOpen === null) {
    open.value = window.innerWidth >= 768
  } else {
    open.value = persistedOpen === '1'
  }

  try {
    const res = await api.get('/me')
    user.value = res.data.user
    clinic.value = res.data.clinic
    status.value = res.data.status || status.value
    trial_ends_at.value = res.data.trial_ends_at || null
  } catch (e) {
    // silencioso: no bloquear layout si falla
    console.error('Error cargando /me en MainLayout', e)
  } finally {
    loading.value = false
  }
})

watch(open, (value) => {
  localStorage.setItem(MENU_OPEN_KEY, value ? '1' : '0')
})

function keepMenuOpen() {
  open.value = true
}

function isActive(base) {
  const p = route.path || ''
  return p === base || p.startsWith(base + '/')
}
</script>

<style scoped>
/* Estilos mínimos: el diseño depende de utilidades de Tailwind si está disponible. */
.menu-active {
  background: #eef2ff;
  color: #1f2937 !important;
  font-weight: 600;
}

.status-dot { width:10px; height:10px; border-radius:50%; display:inline-block; margin-right:6px }
.status-dot.green { background: #10b981 }
.status-dot.yellow { background: #f59e0b }
.status-dot.red { background: #ef4444 }

.header-card { display:flex; align-items:center; gap:12px; background: rgba(255,255,255,0.95); padding:8px 12px; border-radius:10px; box-shadow: 0 6px 18px rgba(2,6,23,0.06) }
.header-card-label { font-weight:600; color:#111827 }
.user-link { color:#374151; text-decoration:none }
.user-link:hover { text-decoration:underline }
.header-card-sub { display:flex; align-items:center; gap:8px; color:#6b7280; font-size:13px }
.sub-label { color:#6b7280 }
</style>

