<script setup>
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  slug: String,
  serviceId: [Number, String],
  professionalId: [Number, null],
  date: [String, null],
  selectedSlot: { type: Object, default: null },
})

const emit = defineEmits(['select'])

const baseUrl = '/api/booking'
const api = axios.create({
  baseURL: window.location.origin,
  headers: { Accept: 'application/json' },
})

const slots = ref([])
const loading = ref(false)
const error = ref(null)

async function loadSlots() {
  if (!props.date) return

  loading.value = true
  error.value = null
  try {
    const params = {
      slug: props.slug,
      service_id: props.serviceId,
      date: props.date,
    }
    if (props.professionalId) {
      params.professional_id = props.professionalId
    }
    const res = await api.get(`${baseUrl}/slots`, { params })
    slots.value = res.data.slots
  } catch {
    error.value = 'Error al cargar los horarios.'
  } finally {
    loading.value = false
  }
}

function formatDateLabel(dateStr) {
  const d = new Date(dateStr + 'T12:00:00')
  const days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']
  const months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']
  return `${days[d.getDay()]}, ${d.getDate()} de ${months[d.getMonth()]}`
}

function groupByProfessional(slots) {
  if (!props.professionalId) {
    // Any-professional mode: flat deduplicated list by start time.
    const seen = new Map()
    for (const slot of slots) {
      if (!seen.has(slot.start)) {
        seen.set(slot.start, slot)
      }
    }
    return [{ professional_id: null, professional_name: null, slots: Array.from(seen.values()) }]
  }

  const groups = {}
  for (const slot of slots) {
    if (!groups[slot.professional_id]) {
      groups[slot.professional_id] = {
        professional_id: slot.professional_id,
        professional_name: slot.professional_name,
        slots: [],
      }
    }
    groups[slot.professional_id].slots.push(slot)
  }
  return Object.values(groups)
}

watch(() => props.date, loadSlots)
onMounted(loadSlots)
</script>

<template>
  <div class="step-card">
    <h2 class="step-title">Elige un horario</h2>
    <p v-if="date" class="step-subtitle">{{ formatDateLabel(date) }}</p>

    <div v-if="loading" class="slot-loading">
      <div class="loading-spinner" />
      <p>Cargando horarios...</p>
    </div>

    <div v-else-if="error" class="slot-error">
      <p>{{ error }}</p>
      <button class="retry-btn" @click="loadSlots">Reintentar</button>
    </div>

    <div v-else-if="slots.length === 0" class="slot-empty">
      No hay horarios disponibles para esta fecha.
    </div>

    <div v-else class="slot-groups">
      <div v-for="group in groupByProfessional(slots)" :key="group.professional_id" class="slot-group">
        <h3 v-if="group.professional_name" class="slot-group__title">{{ group.professional_name }}</h3>
        <div class="slot-grid">
          <button
            v-for="slot in group.slots"
            :key="slot.start + '-' + slot.professional_id"
            class="slot-btn"
            :class="{ selected: selectedSlot?.start === slot.start && selectedSlot?.professional_id === slot.professional_id }"
            @click="emit('select', slot)"
          >
            {{ slot.start }}
          </button>
        </div>
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

.slot-loading,
.slot-empty,
.slot-error {
  text-align: center;
  padding: 32px 0;
  color: #556176;
}

.loading-spinner {
  width: 24px;
  height: 24px;
  border: 2px solid rgba(106, 48, 252, 0.2);
  border-top-color: rgb(86, 39, 221);
  border-radius: 999px;
  margin: 0 auto 8px;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.retry-btn {
  margin-top: 8px;
  padding: 8px 16px;
  border-radius: 999px;
  border: 1px solid rgb(106, 48, 252);
  background: #fff;
  color: rgb(86, 39, 221);
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
}

.slot-groups {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.slot-group__title {
  margin: 0 0 10px;
  font-size: 14px;
  font-weight: 700;
  color: #556176;
}

.slot-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
  gap: 8px;
}

.slot-btn {
  padding: 12px 8px;
  border-radius: 12px;
  border: 2px solid rgba(17, 32, 59, 0.08);
  background: #fff;
  font-size: 14px;
  font-weight: 700;
  color: #11203b;
  cursor: pointer;
  transition: all 0.15s;
  font-family: inherit;
  text-align: center;
}

.slot-btn:hover {
  border-color: rgb(106, 48, 252);
  background: rgb(247, 243, 255);
}

.slot-btn.selected {
  border-color: rgb(86, 39, 221);
  background: rgb(86, 39, 221);
  color: #fff;
}
</style>
