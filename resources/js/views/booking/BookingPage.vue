<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'

import StepService from './steps/StepService.vue'
import StepProfessional from './steps/StepProfessional.vue'
import StepCalendar from './steps/StepCalendar.vue'
import StepTimeSlot from './steps/StepTimeSlot.vue'
import StepPatientInfo from './steps/StepPatientInfo.vue'
import StepConfirmation from './steps/StepConfirmation.vue'

const route = useRoute()
const router = useRouter()
const slug = route.params.slug

const baseUrl = '/api/booking'

const steps = [
  { key: 'service', label: 'Servicio' },
  { key: 'professional', label: 'Profesional' },
  { key: 'calendar', label: 'Fecha' },
  { key: 'timeslot', label: 'Hora' },
  { key: 'info', label: 'Tus datos' },
  { key: 'confirm', label: 'Confirmar' },
]

const currentStep = ref(0)
const loading = ref(true)
const submitting = ref(false)
const error = ref(null)

const pageData = ref(null)
const selectedService = ref(null)
const selectedProfessional = ref(null)
const selectedDate = ref(null)
const selectedSlot = ref(null)
const patientData = ref(null)
const createdAppointment = ref(null)

const totalSteps = computed(() => steps.length)

const api = axios.create({
  baseURL: window.location.origin,
  headers: { Accept: 'application/json' },
})

async function loadPage() {
  loading.value = true
  error.value = null
  try {
    const res = await api.get(`${baseUrl}/${slug}`)
    pageData.value = res.data
  } catch (e) {
    if (e.response?.status === 404) {
      error.value = 'Página de reserva no encontrada.'
    } else {
      error.value = 'Error al cargar la página. Intenta de nuevo.'
    }
  } finally {
    loading.value = false
  }
}

function selectService(service) {
  selectedService.value = service
  selectedProfessional.value = null
  selectedDate.value = null
  selectedSlot.value = null
  nextStep()
}

function selectProfessional(professional) {
  selectedProfessional.value = professional
  selectedDate.value = null
  selectedSlot.value = null
  nextStep()
}

function selectDate(date) {
  selectedDate.value = date
  selectedSlot.value = null
  nextStep()
}

function selectSlot(slot) {
  selectedSlot.value = slot
  nextStep()
}

function submitPatientInfo(data) {
  patientData.value = data
  submitBooking()
}

async function submitBooking() {
  submitting.value = true
  error.value = null
  try {
    const res = await api.post(`${baseUrl}`, {
      slug,
      service_id: selectedService.value.id,
      professional_id: selectedSlot.value.professional_id,
      date: selectedDate.value,
      start_time: selectedSlot.value.start,
      patient: patientData.value,
    })
    createdAppointment.value = res.data
    nextStep()
  } catch (e) {
    if (e.response?.status === 422) {
      error.value = e.response.data.message || 'Error al crear la cita.'
    } else {
      error.value = 'Error del servidor. Intenta de nuevo.'
    }
  } finally {
    submitting.value = false
  }
}

function nextStep() {
  if (currentStep.value < totalSteps.value - 1) {
    currentStep.value++
  }
}

function prevStep() {
  if (currentStep.value > 0) {
    currentStep.value--
  }
}

function resetBooking() {
  currentStep.value = 0
  selectedService.value = null
  selectedProfessional.value = null
  selectedDate.value = null
  selectedSlot.value = null
  patientData.value = null
  createdAppointment.value = null
  error.value = null
}

onMounted(loadPage)
</script>

<template>
  <div class="booking-shell">
    <div class="booking-inner">
      <header class="booking-header">
        <div class="booking-header__top">
          <img src="../../assets/logonameviolet.svg" alt="Irison" class="booking-logo" />
        </div>
        <div v-if="pageData" class="booking-header__clinic">
          <h1>{{ pageData.clinic.name }}</h1>
          <p v-if="pageData.clinic.address">{{ pageData.clinic.address }}</p>
        </div>
        <div v-if="!error && !loading" class="booking-steps">
          <div
            v-for="(step, i) in steps"
            :key="step.key"
            class="step-dot"
            :class="{ active: i === currentStep, done: i < currentStep }"
          >
            <span class="step-dot__circle">{{ i < currentStep ? '✓' : i + 1 }}</span>
            <span class="step-dot__label">{{ step.label }}</span>
          </div>
        </div>
      </header>

      <div v-if="loading" class="booking-loading">
        <div class="loading-spinner" />
        <p>Cargando...</p>
      </div>

      <div v-else-if="error && !pageData" class="booking-error">
        <h2>Error</h2>
        <p>{{ error }}</p>
        <button class="btn btn--solid booking-btn-main" @click="loadPage">Reintentar</button>
      </div>

      <template v-else-if="pageData">
        <StepService
          v-if="currentStep === 0"
          :services="pageData.services"
          :selected="selectedService"
          @select="selectService"
        />

        <StepProfessional
          v-if="currentStep === 1"
          :professionals="pageData.professionals"
          :selected="selectedProfessional"
          @select="selectProfessional"
        />

        <StepCalendar
          v-if="currentStep === 2"
          :slug="slug"
          :service-id="selectedService.id"
          :professional-id="selectedProfessional?.id || null"
          :selected-date="selectedDate"
          :max-horizon-days="pageData.settings.max_horizon_days"
          @select="selectDate"
        />

        <StepTimeSlot
          v-if="currentStep === 3"
          :slug="slug"
          :service-id="selectedService.id"
          :professional-id="selectedProfessional?.id || null"
          :date="selectedDate"
          :selected-slot="selectedSlot"
          @select="selectSlot"
        />

        <StepPatientInfo
          v-if="currentStep === 4"
          :submitting="submitting"
          :error="error"
          @submit="submitPatientInfo"
          @back="prevStep"
        />

        <StepConfirmation
          v-if="currentStep === 5"
          :appointment="createdAppointment"
          @reset="resetBooking"
        />

        <div v-if="currentStep > 0 && currentStep < 5" class="booking-nav">
          <button class="btn btn--ghost booking-btn-ghost" @click="prevStep">Volver</button>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.booking-shell {
  --violet-600: rgb(106, 48, 252);
  --violet-700: rgb(86, 39, 221);
  --violet-900: rgb(58, 24, 154);
  --violet-rgb: 106, 48, 252;

  min-height: 100vh;
  color: #11203b;
  background:
    radial-gradient(circle at top left, rgba(var(--violet-rgb), 0.34), transparent 30%),
    radial-gradient(circle at top right, rgba(var(--violet-rgb), 0.2), transparent 28%),
    linear-gradient(180deg, rgb(247, 243, 255) 0%, #ffffff 42%, rgb(235, 226, 255) 100%);
}

.booking-inner {
  width: min(680px, calc(100% - 32px));
  margin: 0 auto;
  padding: 28px 0 48px;
}

.booking-header {
  text-align: center;
  padding: 20px 0 30px;
}

.booking-header__top {
  margin-bottom: 16px;
}

.booking-logo {
  height: 40px;
}

.booking-header__clinic h1 {
  margin: 0;
  font-size: 1.6rem;
  font-weight: 800;
  letter-spacing: -0.03em;
}

.booking-header__clinic p {
  margin: 4px 0 0;
  color: #556176;
}

.booking-steps {
  display: flex;
  justify-content: center;
  gap: 8px;
  margin-top: 24px;
  flex-wrap: wrap;
}

.step-dot {
  display: flex;
  align-items: center;
  gap: 6px;
}

.step-dot__circle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  background: rgba(17, 32, 59, 0.06);
  color: #556176;
  transition: all 0.2s;
}

.step-dot.active .step-dot__circle {
  background: var(--violet-700);
  color: #fff;
}

.step-dot.done .step-dot__circle {
  background: #22c55e;
  color: #fff;
}

.step-dot__label {
  font-size: 12px;
  font-weight: 600;
  color: #556176;
  display: none;
}

.step-dot.active .step-dot__label,
.step-dot.done .step-dot__label {
  display: inline;
}

.booking-loading {
  text-align: center;
  padding: 60px 0;
  color: #556176;
}

.loading-spinner {
  width: 36px;
  height: 36px;
  border: 3px solid rgba(106, 48, 252, 0.2);
  border-top-color: var(--violet-700);
  border-radius: 999px;
  margin: 0 auto 12px;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.booking-error {
  text-align: center;
  padding: 60px 20px;
}

.booking-error h2 {
  margin: 0 0 8px;
}

.booking-error p {
  color: #556176;
  margin-bottom: 20px;
}

.booking-btn-main {
  background: var(--violet-700);
  box-shadow: 0 12px 32px rgba(var(--violet-rgb), 0.3);
}

.booking-btn-main:hover {
  background: var(--violet-600);
}

.booking-btn-ghost {
  border-color: var(--violet-700);
  color: var(--violet-700);
}

.booking-nav {
  display: flex;
  justify-content: center;
  margin-top: 24px;
}
</style>
