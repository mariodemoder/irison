<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  slug: String,
  serviceId: [Number, String],
  professionalId: [Number, null],
  selectedDate: [String, null],
  maxHorizonDays: { type: Number, default: 60 },
})

const emit = defineEmits(['select'])

const baseUrl = '/api/booking'
const api = axios.create({
  baseURL: window.location.origin,
  headers: { Accept: 'application/json' },
})

const currentMonth = ref(new Date().getMonth())
const currentYear = ref(new Date().getFullYear())
const availabilityMap = ref({})
const loading = ref(false)
const availabilityError = ref('')

const monthLabel = computed(() => {
  const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
  return `${months[currentMonth.value]} ${currentYear.value}`
})

const daysInMonth = computed(() => {
  return new Date(currentYear.value, currentMonth.value + 1, 0).getDate()
})

const firstDayOfWeek = computed(() => {
  return new Date(currentYear.value, currentMonth.value, 1).getDay()
})

const today = new Date()
today.setHours(0, 0, 0, 0)

async function loadAvailability() {
  loading.value = true
  availabilityError.value = ''
  try {
    const monthStr = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}`
    const params = {
      slug: props.slug,
      service_id: props.serviceId,
      month: monthStr,
    }
    if (props.professionalId) {
      params.professional_id = props.professionalId
    }
    const res = await api.get(`${baseUrl}/availability`, { params })

    availabilityMap.value = {}
    for (const d of res.data.dates) {
      availabilityMap.value[d.date] = d.has_availability
    }
  } catch (e) {
    availabilityMap.value = {}
    availabilityError.value = e.response?.data?.message || 'Error al cargar la disponibilidad.'
  } finally {
    loading.value = false
  }
}

function prevMonth() {
  if (currentMonth.value === 0) {
    currentMonth.value = 11
    currentYear.value--
  } else {
    currentMonth.value--
  }
}

function nextMonth() {
  if (currentMonth.value === 11) {
    currentMonth.value = 0
    currentYear.value++
  } else {
    currentMonth.value++
  }
}

function isAvailable(day) {
  const date = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  return availabilityMap.value[date]
}

function isPast(day) {
  const date = new Date(currentYear.value, currentMonth.value, day)
  date.setHours(0, 0, 0, 0)
  return date < today
}

function isSelected(day) {
  const date = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  return props.selectedDate === date
}

function selectDate(day) {
  if (isPast(day) || !isAvailable(day)) return
  const date = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  emit('select', date)
}

watch(() => [props.serviceId, props.professionalId, currentMonth.value, currentYear.value], loadAvailability)
onMounted(loadAvailability)
</script>

<template>
  <div class="step-card">
    <h2 class="step-title">Elige una fecha</h2>
    <p class="step-subtitle">Selecciona el día para tu consulta.</p>

    <div v-if="availabilityError" class="availability-error">{{ availabilityError }}</div>

    <div class="calendar">
      <div class="calendar-header">
        <button class="calendar-nav" :disabled="currentMonth === today.getMonth() && currentYear === today.getFullYear()" @click="prevMonth">&larr;</button>
        <span class="calendar-month">{{ monthLabel }}</span>
        <button class="calendar-nav" @click="nextMonth">&rarr;</button>
      </div>

      <div class="calendar-weekdays">
        <span v-for="d in ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa']" :key="d">{{ d }}</span>
      </div>

      <div v-if="loading" class="calendar-loading">
        <div class="loading-spinner" />
      </div>

      <div v-else class="calendar-days">
        <span v-for="i in firstDayOfWeek" :key="'empty-' + i" class="calendar-day empty" />

        <button
          v-for="day in daysInMonth"
          :key="day"
          class="calendar-day"
          :class="{
            past: isPast(day),
            available: isAvailable(day) && !isPast(day),
            unavailable: !isAvailable(day) && !isPast(day),
            selected: isSelected(day),
          }"
          :disabled="isPast(day) || !isAvailable(day)"
          @click="selectDate(day)"
        >
          {{ day }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.step-card {
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(17, 32, 59, 0.08);
  border-radius: 26px;
  padding: 28px;
  box-shadow: 0 14px 36px rgba(17, 32, 59, 0.06);
}

.step-title {
  margin: 0 0 4px;
  font-size: 1.3rem;
  font-weight: 800;
  letter-spacing: -0.03em;
}

.step-subtitle {
  margin: 0 0 20px;
  color: #556176;
}

.calendar {
  max-width: 400px;
  margin: 0 auto;
}

.calendar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.calendar-nav {
  background: none;
  border: 1px solid rgba(17, 32, 59, 0.08);
  border-radius: 999px;
  padding: 8px 14px;
  cursor: pointer;
  font-size: 1rem;
  font-weight: 700;
  color: #11203b;
  transition: all 0.15s;
  font-family: inherit;
}

.calendar-nav:hover:not(:disabled) {
  background: rgb(247, 243, 255);
  border-color: rgb(106, 48, 252);
}

.calendar-nav:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.calendar-month {
  font-weight: 800;
  font-size: 1.1rem;
}

.calendar-weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
  text-align: center;
  margin-bottom: 8px;
}

.calendar-weekdays span {
  font-size: 11px;
  font-weight: 700;
  color: #556176;
  text-transform: uppercase;
  padding: 6px 0;
}

.calendar-days {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}

.calendar-day {
  aspect-ratio: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.15s;
  border: none;
  font-family: inherit;
  cursor: default;
  background: transparent;
  color: #556176;
}

.calendar-day.empty {
  background: transparent;
}

.calendar-day.past {
  opacity: 0.3;
}

.calendar-day.available {
  cursor: pointer;
  color: #11203b;
}

.calendar-day.available:hover {
  background: rgb(247, 243, 255);
  border: 1px solid rgb(106, 48, 252);
}

.calendar-day.unavailable {
  color: #d1d5db;
}

.calendar-day.selected {
  background: rgb(86, 39, 221);
  color: #fff;
  font-weight: 700;
}

.loading-spinner {
  width: 24px;
  height: 24px;
  border: 2px solid rgba(106, 48, 252, 0.2);
  border-top-color: rgb(86, 39, 221);
  border-radius: 999px;
  margin: 20px auto;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.calendar-loading {
  display: flex;
  justify-content: center;
}

.availability-error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fca5a5;
  border-radius: 8px;
  padding: 10px 14px;
  margin-bottom: 16px;
  font-size: 13px;
  font-weight: 500;
}
</style>
