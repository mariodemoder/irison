<template>
  <div class="patient-layout">
    <!-- Top bar -->
    <header class="patient-header">
      <div class="header-spacer"></div>
      <div class="header-center">
        <img v-if="clinicLogo" :src="clinicLogo" :alt="clinicName" class="header-logo" />
        <span class="header-title">{{ clinicName || 'Mi Portal' }}</span>
      </div>
      <div class="header-right">
        <button class="logout-btn" @click="handleLogout">Salir</button>
      </div>
    </header>

    <!-- Main content -->
    <main class="patient-main">
      <router-view />
      <footer class="patient-footer">
        © {{ new Date().getFullYear() }} Irison. All rights reserved.
      </footer>
    </main>

    <!-- Bottom navigation -->
    <nav class="patient-nav">
      <router-link to="/patient/dashboard" class="nav-item" :class="{ active: isActive('/patient/dashboard') }">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="4" rx="1.5"/><rect x="14" y="10" width="7" height="11" rx="1.5"/><rect x="3" y="13" width="7" height="8" rx="1.5"/></svg>
        <span>Inicio</span>
      </router-link>
      <router-link to="/patient/appointments" class="nav-item" :class="{ active: isActive('/patient/appointments') }">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
        <span>Citas</span>
      </router-link>
      <router-link to="/patient/bonuses" class="nav-item" :class="{ active: isActive('/patient/bonuses') }">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        <span>Bonos</span>
      </router-link>
      <router-link to="/patient/payments" class="nav-item" :class="{ active: isActive('/patient/payments') }">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M7 15h4"/></svg>
        <span>Pagos</span>
      </router-link>
      <router-link to="/patient/profile" class="nav-item" :class="{ active: isActive('/patient/profile') }">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="3.5"/><path d="M5 19c1.4-3 3.8-4.5 7-4.5s5.6 1.5 7 4.5"/></svg>
        <span>Perfil</span>
      </router-link>
    </nav>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePatientAuth } from '../../patient/composables/usePatientAuth'

const route = useRoute()
const router = useRouter()
const { patient, logout } = usePatientAuth()

const clinicName = computed(() => patient.value?.clinic?.name || '')
const clinicLogo = computed(() => patient.value?.clinic?.logo_url || null)

function isActive(path) {
  return route.path.startsWith(path)
}

async function handleLogout() {
  await logout()
  router.push('/patient/login')
}
</script>

<style scoped>
.patient-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background: #f8fafc;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.patient-header {
  position: sticky;
  top: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: #ffffff;
  border-bottom: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.header-spacer {
  flex: 1;
}

.header-center {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  flex: 1;
}

.header-right {
  flex: 1;
  display: flex;
  justify-content: flex-end;
}

.header-logo {
  width: 32px;
  height: 32px;
}

.header-title {
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
}

.logout-btn {
  padding: 6px 14px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  color: #64748b;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.logout-btn:hover {
  background: #f1f5f9;
  color: #1e293b;
}

.patient-main {
  flex: 1;
  padding: 16px;
  padding-bottom: 80px;
}

.patient-footer {
  text-align: center;
  padding: 20px 16px;
  font-size: 12px;
  color: #94a3b8;
}

.patient-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  justify-content: space-around;
  padding: 8px 0;
  background: #ffffff;
  border-top: 1px solid #e2e8f0;
  box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.05);
  z-index: 50;
}

.nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: 4px 12px;
  text-decoration: none;
  color: #94a3b8;
  font-size: 11px;
  font-weight: 600;
  transition: color 0.15s;
}

.nav-item svg {
  width: 22px;
  height: 22px;
}

.nav-item.active {
  color: #6366f1;
}

.nav-item:hover {
  color: #6366f1;
}

@media (min-width: 768px) {
  .patient-layout {
    max-width: 480px;
    margin: 0 auto;
    box-shadow: 0 0 40px rgba(0, 0, 0, 0.08);
  }

  .patient-nav {
    max-width: 480px;
    left: 50%;
    transform: translateX(-50%);
  }
}
</style>
