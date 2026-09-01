<template>
  <div class="notifications-page">
    <h1>Notificaciones</h1>

    <div v-if="loading" class="loading">Cargando...</div>

    <div v-else-if="notifications.length === 0" class="empty-state">
      <p>No tienes notificaciones</p>
    </div>

    <div v-else class="notification-list">
      <div
        v-for="notification in notifications"
        :key="notification.id"
        class="notification-card"
        :class="{ unread: !notification.read_at }"
        @click="handleMarkRead(notification)"
      >
        <div class="notif-icon">
          <svg v-if="notification.type === 'appointment_confirmed'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
          <svg v-else-if="notification.type === 'appointment_reminder'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5"/><path d="M10 17a2 2 0 0 0 4 0"/></svg>
          <svg v-else-if="notification.type === 'consent_pending'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 2h4v6h6v12H4V8l6-6z"/><path d="M10 2v6H4"/></svg>
          <svg v-else-if="notification.type === 'payment_pending'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M7 15h4"/></svg>
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
        </div>
        <div class="notif-content">
          <h3>{{ notification.title }}</h3>
          <p v-if="notification.body">{{ notification.body }}</p>
          <span class="notif-time">{{ timeAgo(notification.created_at) }}</span>
        </div>
        <div v-if="!notification.read_at" class="unread-dot"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import patientApi from '../../patient/services/patientApi'

const notifications = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await patientApi.get('/notifications')
    notifications.value = data.data
  } catch (e) {
    console.error('Error loading notifications:', e)
  } finally {
    loading.value = false
  }
})

async function handleMarkRead(notification) {
  if (notification.read_at) return
  try {
    await patientApi.post(`/notifications/${notification.id}/read`)
    notification.read_at = new Date().toISOString()
  } catch (e) {
    console.error('Error marking notification as read:', e)
  }
}

function timeAgo(datetime) {
  const now = new Date()
  const date = new Date(datetime)
  const diff = now - date
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(diff / 3600000)
  const days = Math.floor(diff / 86400000)

  if (minutes < 1) return 'Ahora'
  if (minutes < 60) return `Hace ${minutes} min`
  if (hours < 24) return `Hace ${hours}h`
  if (days < 7) return `Hace ${days}d`
  return date.toLocaleDateString('es')
}
</script>

<style scoped>
.notifications-page {
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

.notification-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.notification-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px;
  background: #ffffff;
  border-radius: 10px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  cursor: pointer;
  position: relative;
}

.notification-card.unread {
  background: #f0f4ff;
}

.notif-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #f0f4ff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.notif-icon svg {
  width: 18px;
  height: 18px;
  color: #6366f1;
}

.notif-content {
  flex: 1;
}

.notif-content h3 {
  font-size: 14px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.notif-content p {
  font-size: 13px;
  color: #64748b;
  margin: 2px 0 0;
}

.notif-time {
  font-size: 12px;
  color: #94a3b8;
}

.unread-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #6366f1;
  flex-shrink: 0;
}
</style>
