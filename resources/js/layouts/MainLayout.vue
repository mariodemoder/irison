<template>
  <div class="layout-shell min-h-screen bg-gray-50">
    <aside v-show="open" class="sidebar" :class="{ compact: compactMode }">
      <button class="logo-wrap" type="button" @click="toggleMenuMode" :title="isMobile ? 'Mostrar/ocultar menú' : 'Expandir/contraer menú'">
        <img :src="logo" alt="Logo" class="sidebar-logo" />
      </button>

      <nav class="space-y-1">
        <router-link
          v-for="item in navItems"
          :key="item.path"
          :class="[{ 'menu-active': isActive(item.path) }, 'menu-link']"
          :to="item.path"
          @click="keepMenuOpen"
        >
          <span class="menu-icon" aria-hidden="true">
            <svg v-if="item.path === '/dashboard'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
              <rect x="14" y="3" width="7" height="4" rx="1.5"></rect>
              <rect x="14" y="10" width="7" height="11" rx="1.5"></rect>
              <rect x="3" y="13" width="7" height="8" rx="1.5"></rect>
            </svg>
            <svg v-else-if="item.path === '/patients'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="8" r="3.5"></circle>
              <path d="M5 19c1.4-3 3.8-4.5 7-4.5s5.6 1.5 7 4.5"></path>
            </svg>
            <svg v-else-if="item.path === '/appointments'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="5" width="18" height="16" rx="2"></rect>
              <path d="M8 3v4M16 3v4M3 10h18"></path>
            </svg>
            <svg v-else-if="item.path === '/payments'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2.5" y="5" width="19" height="14" rx="2"></rect>
              <path d="M2.5 10h19M7 15h4"></path>
            </svg>
            <svg v-else-if="item.path === '/invoices'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M7 3h8l4 4v14H7z"></path>
              <path d="M15 3v4h4"></path>
              <path d="M10 12h6M10 16h6"></path>
            </svg>
            <svg v-else-if="item.path === '/notifications'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"></path>
              <path d="M10 17a2 2 0 0 0 4 0"></path>
            </svg>
            <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 9l8-5 8 5v10l-8 4-8-4z"></path>
              <path d="M9 12h6"></path>
            </svg>
          </span>
          <span v-if="!compactMode" class="menu-label">{{ item.label }}</span>
        </router-link>
      </nav>
    </aside>

    <div v-if="isMobile && open" class="sidebar-backdrop" @click="open = false"></div>

    <div class="app-column" :class="columnClasses">
      <header class="h-14 bg-white border-b flex items-center px-4 justify-between">
        <div class="flex items-center gap-4">
        </div>

        <div class="flex items-center gap-4">
          <div class="header-card">
            <div class="header-card-meta">
              <div class="header-card-label">{{ clinic?.name ?? '—' }} — <router-link to="/profile" class="user-link">CONFIGURACIÓN</router-link></div>
              <div class="header-card-sub">
                <span :class="['status-dot', subscriptionState.color]" aria-hidden="true"></span>
                <span class="sub-label">{{ subscriptionState.label }}</span>
              </div>
            </div>
            <button class="logout-btn" @click.prevent="logoutAction">Cerrar sesión</button>
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
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import logo from '../assets/fisiomeca.svg'
import logout from '../utils/logout'
import { meUser, meClinic, meStatus, meTrialEndsAt, ensureMeLoaded } from '../shared/meCache'

const MENU_OPEN_KEY = 'layout_menu_open'
const MENU_COMPACT_KEY = 'layout_menu_compact'
const MOBILE_BREAKPOINT = 768

const open = ref(true)
const compactMode = ref(false)
const isMobile = ref(false)

const route = useRoute()
const router = useRouter()

const navItems = [
  { path: '/dashboard', label: 'Dashboard' },
  { path: '/patients', label: 'Pacientes' },
  { path: '/appointments', label: 'Agenda' },
  { path: '/payments', label: 'Pagos' },
  { path: '/notifications', label: 'Notificaciones' },
  { path: '/invoices', label: 'Facturación' },
  { path: '/bonuses', label: 'Bonos' },
]

const user = meUser
const clinic = meClinic
const status = meStatus
const trial_ends_at = meTrialEndsAt

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

const columnClasses = computed(() => {
  if (isMobile.value || !open.value) {
    return []
  }

  return compactMode.value ? ['with-sidebar-compact'] : ['with-sidebar-full']
})

function syncViewportMode() {
  isMobile.value = window.innerWidth < MOBILE_BREAKPOINT

  if (!isMobile.value) {
    open.value = true
  }
}

onMounted(async () => {
  const persistedOpen = localStorage.getItem(MENU_OPEN_KEY)
  const persistedCompact = localStorage.getItem(MENU_COMPACT_KEY)

  compactMode.value = persistedCompact === '1'
  syncViewportMode()

  if (isMobile.value) {
    open.value = persistedOpen === '1'
  }

  window.addEventListener('resize', syncViewportMode)

  try {
    await ensureMeLoaded()
  } catch (e) {
    // silencioso: no bloquear layout si falla
    console.error('Error cargando /me en MainLayout', e)
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', syncViewportMode)
})

watch(open, (value) => {
  localStorage.setItem(MENU_OPEN_KEY, value ? '1' : '0')
})

watch(compactMode, (value) => {
  localStorage.setItem(MENU_COMPACT_KEY, value ? '1' : '0')
})

function keepMenuOpen() {
  if (!isMobile.value) {
    open.value = true
    return
  }

  open.value = false
}

function toggleMenuMode() {
  if (isMobile.value) {
    open.value = !open.value
    return
  }

  compactMode.value = !compactMode.value
}

function logoutAction() {
  logout(router)
}

function isActive(base) {
  const p = route.path || ''
  return p === base || p.startsWith(base + '/')
}
</script>

<style scoped>
.layout-shell {
  position: relative;
}

.sidebar {
  position: fixed;
  top: 0;
  left: 0;
  z-index: 80;
  width: 260px;
  height: 100vh;
  overflow: auto;
  background: #ffffff;
  border-right: 1px solid #e5e7eb;
  padding: 16px;
  box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
  transition: width 0.2s ease;
}

.sidebar.compact {
  width: 86px;
}

.logo-wrap {
  margin-bottom: 24px;
  display: flex;
  justify-content: center;
  width: 100%;
  border: 0;
  background: transparent;
  padding: 0;
  cursor: pointer;
}

.sidebar-logo {
  width: 160px;
  height: 160px;
  object-fit: contain;
}

.sidebar.compact .sidebar-logo {
  width: 56px;
  height: 56px;
}

.menu-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  color: #1e3a8a;
  text-decoration: none;
  transition: background-color 0.15s ease, color 0.15s ease;
}

.menu-link:hover {
  background: #eff6ff;
  color: #1d4ed8;
}

.menu-icon {
  width: 20px;
  height: 20px;
  flex: 0 0 auto;
  color: #2563eb;
}

.menu-icon svg {
  width: 100%;
  height: 100%;
}

.menu-label {
  font-size: 14px;
  font-weight: 600;
}

.sidebar-backdrop {
  position: fixed;
  inset: 0;
  z-index: 70;
  background: rgba(15, 23, 42, 0.32);
}

.app-column {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  transition: padding-left 0.2s ease;
}

.app-column.with-sidebar-full {
  padding-left: 260px;
}

.app-column.with-sidebar-compact {
  padding-left: 86px;
}

/* Estilos mínimos: el diseño depende de utilidades de Tailwind si está disponible. */
.menu-active {
  background: #dbeafe;
  color: #1e3a8a !important;
  font-weight: 600;
}

.menu-active .menu-icon {
  color: #1d4ed8;
}

.status-dot { width:10px; height:10px; border-radius:50%; display:inline-block; margin-right:6px }
.status-dot.green { background: #10b981 }
.status-dot.yellow { background: #f59e0b }
.status-dot.red { background: #ef4444 }

.header-card { display:flex; align-items:center; gap:12px; background: rgba(255,255,255,0.95); padding:8px 12px; border-radius:10px; box-shadow: 0 6px 18px rgba(2,6,23,0.06) }
.header-card-meta { display:flex; flex-direction:column; min-width: 220px; }
.header-card-label { font-weight:600; color:#111827 }
.user-link { color:#374151; text-decoration:none }
.user-link:hover { text-decoration:underline }
.header-card-sub { display:flex; align-items:center; gap:8px; color:#6b7280; font-size:13px }
.sub-label { color:#6b7280 }
.logout-btn { padding:6px 12px; border-radius:999px; border:1px solid #e5e7eb; background:#fff; color:#374151; font-size:13px; font-weight:600; white-space:nowrap }
.logout-btn:hover { background:#f8fafc }

@media (max-width: 767px) {
  .app-column.with-sidebar-full,
  .app-column.with-sidebar-compact {
    padding-left: 0;
  }
}
</style>

