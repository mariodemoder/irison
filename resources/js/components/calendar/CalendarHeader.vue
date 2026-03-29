<template>
  <div class="page-header">
    <div class="week-nav">
      <button type="button" @click="emit('prev')" class="nav-btn" aria-label="Anterior">
        ◀
      </button>

      <button type="button" @click="emit('next')" class="nav-btn" aria-label="Siguiente">
        ▶
      </button>

      <span class="week-range">
        {{ label }}
      </span>

      <button type="button" @click="emit('today')" class="today-btn">
        Hoy
      </button>
    </div>

    <div class="header-actions">
      <div class="view-toggle" role="group" aria-label="Modo de vista">
        <router-link
          to="/appointments/day"
          class="vt-btn"
          :class="{ 'vt-active': view === 'day' }"
        >
          Día
        </router-link>

        <router-link
          to="/appointments/week"
          class="vt-btn"
          :class="{ 'vt-active': view === 'week' }"
        >
          Semana
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  label: {
    type: String,
    default: '',
  },
  view: {
    type: String,
    default: 'day',
  },
})

const emit = defineEmits(['prev', 'next', 'today'])
</script>

<style scoped>
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  gap: 12px;
  flex-wrap: wrap;
}

.week-nav {
  display: flex;
  align-items: center;
  gap: 6px;
  justify-self: start;
}

.nav-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  cursor: pointer;
  color: #374151;
  flex-shrink: 0;
  font-size: 12px;
  line-height: 1;
}

.nav-btn:hover { background: #f1f5f9 }

.week-range {
  font-size: 15px;
  font-weight: 700;
  color: #111827;
  margin: 0 4px;
  white-space: nowrap;
}

.today-btn {
  padding: 5px 14px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.today-btn:hover { background: #f1f5f9 }

.header-actions {
  display: flex;
  gap: 8px;
  align-items: center;
  justify-self: end;
}

.view-toggle {
  display: inline-flex;
  align-items: stretch;
  border: 1px solid #3b82f6;
  border-radius: 8px;
  overflow: hidden;
}

.vt-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 72px;
  line-height: 1;
  padding: 5px 14px;
  font-size: 13px;
  font-weight: 600;
  color: #3b82f6;
  text-decoration: none;
  background: #fff;
  border: 0;
  transition: background .12s, color .12s;
}

.vt-btn:not(:last-child) { border-right: 1px solid #3b82f6 }
.vt-btn:hover { background: #eff6ff }
.vt-active {
  background: #dbeafe !important;
  color: #1d4ed8 !important;
  font-weight: 700;
}

@media (max-width: 900px) {
  .week-nav,
  .header-actions {
    justify-self: stretch;
  }

  .header-actions {
    justify-content: flex-end;
  }
}
</style>
