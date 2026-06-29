<template>
  <div class="layout-shell min-h-screen" :style="{ backgroundColor: 'var(--theme-color-light)' }">
    <aside v-show="isMobile || open" class="sidebar" :class="{ compact: compactMode || isMobile }">
      <button class="logo-wrap" type="button" @click="toggleMenuMode" :title="isMobile ? 'Menú compacto' : 'Expandir/contraer menú'">
        <img :src="currentLogo" alt="Logo" class="sidebar-logo" />
      </button>

      <nav class="space-y-1">
        <router-link
          v-for="item in filteredNavItems"
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
            <svg v-else-if="item.path === '/products'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 7l9-4 9 4-9 4-9-4z"></path>
              <path d="M3 7v10l9 4 9-4V7"></path>
              <path d="M12 11v10"></path>
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
            <svg v-else-if="item.path === '/team'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <svg v-else-if="item.path === '/company-services'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 9l8-5 8 5v10l-8 4-8-4z"></path>
              <path d="M9 12h6"></path>
            </svg>
            <svg v-else-if="item.path === '/consent-templates'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M10 2h4v6h6v12H4V8l6-6z"></path>
              <path d="M10 2v6H4"></path>
              <path d="M9 13h6M9 17h4"></path>
            </svg>
            <svg v-else-if="item.path === '/settings'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11.5" cy="10.3" r="3"></circle>
              <path d="M12 2a1.5 1.5 0 0 1 1.5 1.5v1.1a7 7 0 0 1 1.77.73l.78-.78a1.5 1.5 0 1 1 2.12 2.12l-.78.78A7 7 0 0 1 18.4 9.5h1.1a1.5 1.5 0 0 1 0 3h-1.1a7 7 0 0 1-.73 1.77l.78.78a1.5 1.5 0 1 1-2.12 2.12l-.78-.78a7 7 0 0 1-1.77.73v1.1a1.5 1.5 0 0 1-3 0v-1.1a7 7 0 0 1-1.77-.73l-.78.78a1.5 1.5 0 1 1-2.12-2.12l.78-.78A7 7 0 0 1 4.6 12.5H3.5a1.5 1.5 0 0 1 0-3h1.1a7 7 0 0 1 .73-1.77l-.78-.78a1.5 1.5 0 1 1 2.12-2.12l.78.78A7 7 0 0 1 9.5 4.6V3.5A1.5 1.5 0 0 1 12 2z"></path>
            </svg>
            <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 9l8-5 8 5v10l-8 4-8-4z"></path>
              <path d="M9 12h6"></path>
            </svg>
          </span>
          <span v-if="!(compactMode || isMobile)" class="menu-label">{{ item.label }}</span>
        </router-link>
      </nav>
    </aside>

    <div class="app-column" :class="columnClasses">
      <header class="h-14 border-b flex items-center px-4 justify-between" :class="headerClasses">
        <div class="flex items-center gap-4">
        </div>

        <div class="flex items-center gap-4">
          <div class="header-card">
            <div class="header-card-meta">
              <div class="header-card-label">{{ clinic?.name ?? '—' }} — <router-link to="/profile" class="user-link">Perfil de Usuario</router-link></div>
              <div class="header-card-sub">
                <span class="sub-label">{{ subscriptionStatusDot }} {{ subscriptionState.label }}</span>
              </div>
            </div>
            <button class="logout-btn" @click.prevent="logoutAction">Cerrar sesión</button>
          </div>
        </div>
      </header>

      <div v-if="showCanceledPaidBanner" class="subscription-warning-banner">
        <strong>Suscripción cancelada.</strong>
        Tu cuenta está activa hasta completar el periodo ya pagado.
        <span v-if="cancellationDaysLeftLabel" class="banner-days">Quedan {{ cancellationDaysLeftLabel }} de acceso total.</span>
        Luego pasará a modo solo lectura durante 7 días extra. Al finalizar este periodo tus datos seran eliminados de forma permanente.
      </div>

      <div v-if="showCanceledReadOnlyBanner" class="subscription-canceled-banner">
        <strong>Suscripción finalizada.</strong>
        Tu cuenta está en modo solo lectura. Si no reactivas, tus datos se eliminarán próximamente de forma permanente.
        <span v-if="cancellationReadOnlyDaysLeftLabel" class="banner-days">Quedan {{ cancellationReadOnlyDaysLeftLabel }} de gracia.</span>
      </div>

      <div v-if="showTrialReadOnlyBanner" class="subscription-canceled-banner subscription-canceled-banner--action">
        <div class="subscription-banner-copy">
          <strong>Trial finalizado.</strong>
          Dispones de una semana adicional en modo solo lectura. Puedes consultar datos, pero no crear ni editar transacciones.
        </div>
        <button type="button" class="btn btn-primary allow-readonly-action subscription-banner-action" @click.prevent="beginPaidPlanFromBanner">
          Activar cuenta de pago
        </button>
      </div>

      <main class="p-6" :class="{ 'readonly-mode': isReadOnlyNoTransactions }">
        <slot />
      </main>

      <footer class="app-footer">
        <div class="app-footer-inner">
          <div class="app-footer-brand">
            <img :src="faviconUrl" alt="Irison" class="app-footer-logo" />
            <span class="app-footer-name">Irison</span>
          </div>

          <nav class="app-footer-nav">
            <router-link to="/privacy" class="app-footer-link">Privacidad</router-link>
            <router-link to="/terms" class="app-footer-link">Términos</router-link>
            <button type="button" class="app-footer-link app-footer-btn" @click="openContactForm">Contacto</button>
          </nav>

          <p class="app-footer-copy">© {{ currentYear }} Irison. All rights reserved.</p>
          <p class="app-footer-tagline">Simplify your time. Focus on what matters.</p>
        </div>
      </footer>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import api from '../services/api'
import logoCompact from '../assets/logoini.svg'
import logoFull from '../assets/logonameviolet.svg'
import logout from '../utils/logout'
import {
  meUser,
  meClinic,
  meStatus,
  meTrialEndsAt,
  meCancellationDaysLeft,
  meCancellationReadOnlyDaysLeft,
  meReadOnlyNoTransactions,
  meCanTransact,
  isProfessional,
  ensureMeLoaded,
} from '../shared/meCache'

const MENU_OPEN_KEY = 'layout_menu_open'
const MENU_COMPACT_KEY = 'layout_menu_compact'
const MOBILE_BREAKPOINT = 768

const open = ref(true)
const compactMode = ref(false)
const isMobile = ref(false)
const currentYear = new Date().getFullYear()
const faviconUrl = `${import.meta.env.BASE_URL}favicon.svg`

const route = useRoute()
const router = useRouter()

const navItems = [
  { path: '/dashboard', label: 'Dashboard' },
  { path: '/appointments', label: 'Agenda' },
  { path: '/patients', label: 'Pacientes' },
  { path: '/products', label: 'Productos' },
  { path: '/bonuses', label: 'Bonos' },
  { path: '/invoices', label: 'Facturación' },
  { path: '/payments', label: 'Pagos' },
  { path: '/notifications', label: 'Notificaciones' },
  { path: '/team', label: 'Equipo' },
  { path: '/company-services', label: 'Servicios' },
  { path: '/consent-templates', label: 'Consentimientos' },
  { path: '/settings', label: 'Configuración' },
]

const professionalPaths = ['/appointments', '/patients']

const filteredNavItems = computed(() => {
  if (isProfessional.value) {
    return navItems.filter(item => professionalPaths.includes(item.path))
  }
  return navItems
})

const user = meUser
const clinic = meClinic
const status = meStatus
const trial_ends_at = meTrialEndsAt
const cancellationDaysLeft = meCancellationDaysLeft
const cancellationReadOnlyDaysLeft = meCancellationReadOnlyDaysLeft
const readOnlyNoTransactions = meReadOnlyNoTransactions
const canTransact = meCanTransact

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
  if (status.value === 'canceled' || status.value === 'cancelled') {
    return { color: 'red', label: 'Suscripción cancelada' }
  }
  if (status.value === 'trial_read_only') {
    return { color: 'red', label: 'Trial finalizado — solo lectura (7 días)' }
  }
  return { color: 'red', label: 'Suscripción vencida' }
})

const subscriptionStatusDot = computed(() => {
  if (status.value === 'trial') return '🟠'
  if (status.value === 'active' || status.value === 'activa') return '🟢'
  if (status.value === 'trial_read_only') return '🔴'
  if (status.value === 'canceled' || status.value === 'cancelled' || status.value === 'blocked') return '🔴'
  return '🔴'
})

const isReadOnlyNoTransactions = computed(() => {
  if (readOnlyNoTransactions.value) {
    return true
  }

  if (status.value === 'trial_read_only') {
    return true
  }

  if (!canTransact.value && (status.value === 'canceled' || status.value === 'cancelled')) {
    return true
  }

  return false
})

const showCanceledPaidBanner = computed(() => {
  return (status.value === 'canceled' || status.value === 'cancelled') && canTransact.value
})

const showCanceledReadOnlyBanner = computed(() => {
  return (status.value === 'canceled' || status.value === 'cancelled') && !canTransact.value && readOnlyNoTransactions.value
})

const showTrialReadOnlyBanner = computed(() => {
  return status.value === 'trial_read_only' && readOnlyNoTransactions.value
})

const cancellationDaysLeftLabel = computed(() => {
  const days = Number(cancellationDaysLeft.value ?? 0)
  if (!Number.isFinite(days) || days <= 0) return ''
  return days === 1 ? '1 día' : `${days} días`
})

const cancellationReadOnlyDaysLeftLabel = computed(() => {
  const days = Number(cancellationReadOnlyDaysLeft.value ?? 0)
  if (!Number.isFinite(days) || days <= 0) return ''
  return days === 1 ? '1 día' : `${days} días`
})

const isTrialEndingSoon = computed(() => {
  const days = Number(daysLeft.value ?? 0)
  return status.value === 'trial' && Number.isFinite(days) && days > 0 && days <= 7
})

const headerClasses = computed(() => {
  return isTrialEndingSoon.value ? ['header-trial-warning'] : ['header-default']
})

const currentLogo = computed(() => {
  return compactMode.value ? logoCompact : logoFull
})

const columnClasses = computed(() => {
  if (isMobile.value) {
    return ['with-sidebar-mobile']
  }

  if (!open.value) {
    return []
  }

  return compactMode.value ? ['with-sidebar-compact'] : ['with-sidebar-full']
})

function syncViewportMode() {
  isMobile.value = window.innerWidth < MOBILE_BREAKPOINT

  if (isMobile.value) {
    open.value = false
    return
  }

  open.value = true
}

onMounted(async () => {
  const persistedCompact = localStorage.getItem(MENU_COMPACT_KEY)

  compactMode.value = persistedCompact === '1'
  syncViewportMode()

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
  open.value = true
}

function toggleMenuMode() {
  if (isMobile.value) {
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

function beginPaidPlanFromBanner() {
  router.push('/billing/required')
}

async function openContactForm() {
  const { value: formValues, isConfirmed } = await Swal.fire({
    title: 'Contacto',
    html: `
      <div style="text-align:left;margin-bottom:8px">
        <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:4px">Asunto</label>
        <input id="swal-contact-subject" class="swal2-input" style="margin:0;width:100%;box-sizing:border-box" placeholder="Asunto del mensaje" maxlength="200" />
      </div>
      <div style="text-align:left">
        <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:4px">Mensaje</label>
        <textarea id="swal-contact-body" class="swal2-textarea" style="margin:0;width:100%;box-sizing:border-box;height:140px;resize:vertical" placeholder="Describe tu consulta..." maxlength="4000"></textarea>
      </div>
    `,
    customClass: { popup: 'swal-popup-card' },
    confirmButtonText: 'Enviar',
    cancelButtonText: 'Cancelar',
    showCancelButton: true,
    focusConfirm: false,
    preConfirm: () => {
      const subject = document.getElementById('swal-contact-subject').value.trim()
      const body    = document.getElementById('swal-contact-body').value.trim()
      if (!subject) { Swal.showValidationMessage('El asunto es obligatorio'); return false }
      if (!body)    { Swal.showValidationMessage('El mensaje no puede estar vacío'); return false }
      return { subject, body }
    },
  })

  if (!isConfirmed || !formValues) return

  try {
    await api.post('/contact', formValues)
    Swal.fire({ icon: 'success', title: 'Mensaje enviado', text: 'Nos pondremos en contacto contigo pronto.', customClass: { popup: 'swal-popup-card' } })
  } catch (err) {
    const msg = err?.response?.data?.message || 'No se pudo enviar el mensaje.'
    Swal.fire({ icon: 'error', title: 'Error', text: msg, customClass: { popup: 'swal-popup-card' } })
  }
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
  border-right: 1px solid var(--theme-color);
  padding: 16px;
  box-shadow: 0 14px 28px rgba(15, 23, 42, 0.12);
  transition: width 0.2s ease;
}

:global(.theme--dark-menus) .sidebar {
  background: #cbd5e1;
}

.sidebar.compact {
  width: 72px;
}

.sidebar.compact .menu-link {
  justify-content: center;
  padding: 8px 6px;
}

.logo-wrap {
  margin-bottom: 12px;
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
  height: 80px;
  object-fit: contain;
}

.sidebar.compact .sidebar-logo {
  width: 42px;
  height: 42px;
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

.subscription-canceled-banner {
  margin: 12px 24px 0;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid #fca5a5;
  background: #fff1f2;
  color: #9f1239;
  font-size: 14px;
  line-height: 1.4;
}
.subscription-warning-banner {
  margin: 12px 24px 0;
  padding: 12px 14px;
  border-radius: 10px;
  border: 1px solid #faf060;
  background: #fffdf1;
  color: #cfad13;
  font-size: 14px;
  line-height: 1.4;
}
.subscription-canceled-banner--action {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.subscription-banner-copy {
  min-width: 0;
}

.subscription-banner-action {
  white-space: nowrap;
}

.banner-days {
  margin-left: 6px;
  font-weight: 700;
}

.readonly-mode :deep(button[type='submit']),
.readonly-mode :deep(a.action-btn),
.readonly-mode :deep(.action-btn),
.readonly-mode :deep(.btn-primary),
.readonly-mode :deep(.save-button),
.readonly-mode :deep(.invoice-mini-btn),
.readonly-mode :deep(.btn-trash),
.readonly-mode :deep(.plus-btn),
.readonly-mode :deep(a[href*='/edit']),
.readonly-mode :deep(a[href*='/create']),
.readonly-mode :deep(.quick-action-card),
.readonly-mode :deep(button.primary),
.readonly-mode :deep(button.muted),
.readonly-mode :deep(button[data-action='emit-invoice']),
.readonly-mode :deep(a[href*='/payments/create']),
.readonly-mode :deep(a[href*='/invoices/create']) {
  display: none !important;
}

.readonly-mode :deep(.allow-readonly-action) {
  display: inline-flex !important;
}

.app-column.with-sidebar-full {
  padding-left: 260px;
}

.app-column.with-sidebar-compact {
  padding-left: 72px;
}

.app-column.with-sidebar-mobile {
  padding-left: 68px;
}

/* ── Footer ── */
.app-footer {
  margin-top: auto;
  border-top: 1px solid var(--theme-color);
  background: #ffffff;
  padding: 10px 16px;
  font-size: 13px;
}

:global(.theme--dark-menus) .app-footer {
  background: #cbd5e1;
}

.app-footer-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
}

.app-footer-brand {
  display: flex;
  align-items: center;
  gap: 8px;
}

.app-footer-logo {
  width: 18px;
  height: 18px;
  opacity: 0.8;
  filter: grayscale(1);
}

.app-footer-name {
  font-weight: 600;
  color: #111827;
  font-size: 14px;
}

.app-footer-nav {
  display: flex;
  align-items: center;
  gap: 20px;
}

.app-footer-link {
  color: #6b7280;
  text-decoration: none;
  transition: color 0.15s;
}

.app-footer-link:hover {
  color: #111827;
}

.app-footer-copy {
  margin: 0;
  color: #6b7280;
}

.app-footer-btn {
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  font-size: inherit;
  font-family: inherit;
}

.app-footer-tagline {
  margin: 0;
  font-size: 11px;
  color: #9ca3af;
  letter-spacing: 0.02em;
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
.logout-btn { padding:6px 12px; border-radius:999px; border:1px solid var(--theme-color); background:#fff; color:#374151; font-size:13px; font-weight:600; white-space:nowrap }
.logout-btn:hover { background:#f8fafc }

.header-default {
  background: #ffffff;
  border-bottom-color: var(--theme-color);
}

:global(.theme--dark-menus) .header-default {
  background: #cbd5e1;
}

.header-trial-warning {
  background: #fee2e2;
  border-bottom-color: #fca5a5;
}

@media (max-width: 767px) {
  .sidebar {
    width: 68px;
    padding: 10px 6px;
    box-shadow: 0 8px 16px rgba(15, 23, 42, 0.1);
  }

  .sidebar-logo,
  .sidebar.compact .sidebar-logo {
    width: 34px;
    height: 34px;
  }

  .logo-wrap {
    margin-bottom: 12px;
  }

  .menu-link,
  .sidebar.compact .menu-link {
    padding: 10px 6px;
    justify-content: center;
    border-radius: 8px;
  }

  .app-column.with-sidebar-full,
  .app-column.with-sidebar-compact,
  .app-column.with-sidebar-mobile {
    padding-left: 68px;
  }

  .subscription-canceled-banner--action {
    flex-direction: column;
    align-items: flex-start;
  }

  .subscription-banner-action {
    width: 100%;
    text-align: center;
  }
}
</style>

