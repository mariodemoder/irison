<script setup>
import { ref, computed, onMounted } from 'vue'
import { useModalClose } from '../../composables/useModalClose'
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
      professional_id: selectedProfessional.value?.id ?? null,
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
const showPrivacyModal = ref(false)

const showTermsModal = ref(false)

const {
  onBackdropMouseDown: onPrivacyBackdropMouseDown,
  onBackdropMouseUp: onPrivacyBackdropMouseUp,
} = useModalClose(() => { showPrivacyModal.value = false }, showPrivacyModal)
const {
  onBackdropMouseDown: onTermsBackdropMouseDown,
  onBackdropMouseUp: onTermsBackdropMouseUp,
} = useModalClose(() => { showTermsModal.value = false }, showTermsModal)

const currentYear = new Date().getFullYear()
const faviconUrl = `${import.meta.env.BASE_URL}favicon.svg`
</script>

<template>
  <div class="booking-shell">
    <div class="booking-inner">
      <header class="booking-header">
        <div v-if="pageData" class="booking-header__clinic">
          <img v-if="pageData.clinic.logo_url" :src="pageData.clinic.logo_url" :alt="pageData.clinic.name" class="booking-header__logo" />
          <h1>{{ pageData.clinic.name }}</h1>
          <p v-if="pageData.settings.title" class="booking-header__tagline">{{ pageData.settings.title }}</p>
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
          :professional-id="selectedProfessional?.id ?? null"
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

      <div
        v-if="showPrivacyModal"
        class="booking-modal-backdrop"
        @mousedown.left="onPrivacyBackdropMouseDown"
        @mouseup.left="onPrivacyBackdropMouseUp"
      >
        <div class="booking-modal">
          <div class="booking-modal-header">
            <h3>Política de Privacidad</h3>
            <button class="booking-modal-close" @click="showPrivacyModal = false">✕</button>
          </div>
          <div class="booking-modal-body">
            <p>En cumplimiento del Reglamento (UE) 2016/679 de Protección de Datos (RGPD) y la Ley Orgánica 3/2018 de Protección de Datos Personales y garantía de los derechos digitales (LOPDGDD), se informa al usuario de la presente política de privacidad.</p>
            <h4>Responsable del tratamiento</h4>
            <p><strong>{{ pageData?.clinic?.name }}</strong>, con domicilio en {{ pageData?.clinic?.address || 'el indicado en la ficha de la clínica' }} y correo electrónico de contacto {{ pageData?.clinic?.email || 'el indicado en la ficha de la clínica' }}.</p>
            <h4>Finalidad del tratamiento</h4>
            <p>Gestión de la reserva de citas online, comunicación con el paciente, y cumplimiento de obligaciones legales y sanitarias derivadas de la prestación de servicios.</p>
            <h4>Legitimación</h4>
            <p>Ejecución de un contrato de servicios y consentimiento del interesado al facilitar sus datos a través del formulario de reserva.</p>
            <h4>Destinatarios</h4>
            <p>Los datos no serán cedidos a terceros salvo obligación legal. Se utilizan servicios de alojamiento y comunicación con los estándares de seguridad exigidos por la normativa.</p>
            <h4>Derechos</h4>
            <p>Puede ejercer sus derechos de acceso, rectificación, supresión, limitación, portabilidad y oposición dirigiéndose al correo electrónico del responsable.</p>
          </div>
        </div>
      </div>

      <div
        v-if="showTermsModal"
        class="booking-modal-backdrop"
        @mousedown.left="onTermsBackdropMouseDown"
        @mouseup.left="onTermsBackdropMouseUp"
      >
        <div class="booking-modal">
          <div class="booking-modal-header">
            <h3>Términos y Condiciones</h3>
            <button class="booking-modal-close" @click="showTermsModal = false">✕</button>
          </div>
          <div class="booking-modal-body">
            <p>Los siguientes términos y condiciones regulan el uso del servicio de reserva de citas online ofrecido por <strong>{{ pageData?.clinic?.name }}</strong>.</p>
            <h4>Reserva de citas</h4>
            <p>El usuario podrá reservar una cita a través del sistema online. La confirmación de la reserva queda sujeta a disponibilidad. Una vez confirmada, se enviará un resumen al correo electrónico facilitado.</p>
            <h4>Cancelación</h4>
            <p v-if="pageData?.settings?.cancellation_hours">El usuario puede cancelar su cita hasta {{ pageData.settings.cancellation_hours }} horas antes de la hora prevista sin coste alguno.</p>
            <p v-else>El usuario puede cancelar su cita en cualquier momento antes de la hora prevista.</p>
            <h4>Responsabilidad</h4>
            <p>La clínica no se hace responsable por daños o perjuicios derivados del uso incorrecto del sistema de reservas o por causas de fuerza mayor que impidan la prestación del servicio.</p>
            <h4>Modificaciones</h4>
            <p>La clínica se reserva el derecho de modificar estos términos en cualquier momento, notificando los cambios a través de este mismo medio.</p>
          </div>
        </div>
      </div>

      <footer class="booking-footer">
        <div class="booking-footer-inner">
          <div class="booking-footer-brand">
            <img :src="faviconUrl" alt="Irison" class="booking-footer-logo" />
            <span class="booking-footer-name">Irison</span>
          </div>

          <div class="booking-footer-meta">
            <p v-if="pageData?.settings?.cancellation_hours" class="booking-cancel-policy">
              Política de cancelación: puedes cancelar hasta {{ pageData.settings.cancellation_hours }} horas antes de la cita.
            </p>

            <nav class="booking-footer-nav">
              <button class="booking-footer-link" @click="showPrivacyModal = true">Privacidad</button>
              <button class="booking-footer-link" @click="showTermsModal = true">Términos</button>
              <a href="mailto:hola@irison.es" class="booking-footer-link">Contacto</a>
            </nav>
          </div>

          <p class="booking-footer-copy">&copy; {{ currentYear }} Irison. All rights reserved.</p>
          <p class="booking-footer-tagline">Simplify your time. Focus on what matters.</p>
        </div>
      </footer>
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

.booking-header__clinic h1 {
  margin: 0;
  font-size: 2rem;
  font-weight: 800;
  letter-spacing: -0.03em;
}

.booking-header__logo {
  max-width: 220px;
  max-height: 64px;
  width: auto;
  height: auto;
  object-fit: contain;
  display: block;
  margin: 0 auto 12px;
}

.booking-header__clinic p {
  margin: 4px 0 0;
  color: #556176;
}

.booking-header__tagline {
  font-size: 1.05rem;
  font-weight: 500;
  color: #6b7280;
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

.booking-footer {
  border-top: 1px solid rgba(0, 0, 0, 0.08);
  padding: 14px 16px;
  font-size: 13px;
  opacity: 0.75;
  margin-top: 40px;
}

.booking-footer-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
}

.booking-footer-meta {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.booking-cancel-policy {
  margin: 0;
  font-size: 12px;
  color: #6b7280;
  text-align: center;
}

.booking-footer-brand {
  display: flex;
  align-items: center;
  gap: 8px;
}

.booking-footer-logo {
  width: 18px;
  height: 18px;
  opacity: 0.8;
  filter: grayscale(1);
}

.booking-footer-name {
  font-weight: 600;
  color: #111827;
  font-size: 14px;
}

.booking-footer-nav {
  display: flex;
  align-items: center;
  gap: 20px;
}

.booking-footer-link {
  color: #6b7280;
  text-decoration: none;
  background: none;
  border: none;
  font: inherit;
  font-size: inherit;
  cursor: pointer;
  padding: 0;
  transition: color 0.15s;
}

.booking-footer-link:hover {
  color: #111827;
}

.booking-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 300;
  padding: 24px;
}

.booking-modal {
  background: #fff;
  border-radius: 16px;
  max-width: 600px;
  width: 100%;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.booking-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px 0;
}

.booking-modal-header h3 {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
}

.booking-modal-close {
  background: none;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
  color: #6b7280;
  padding: 4px 8px;
  border-radius: 6px;
}

.booking-modal-close:hover {
  background: #f3f4f6;
  color: #111827;
}

.booking-modal-body {
  padding: 16px 24px 24px;
  overflow-y: auto;
  font-size: 14px;
  line-height: 1.6;
  color: #374151;
}

.booking-modal-body h4 {
  margin: 16px 0 6px;
  font-size: 14px;
  font-weight: 700;
  color: #111827;
}

.booking-modal-body p {
  margin: 0 0 8px;
}

.booking-footer-copy {
  margin: 0;
  color: #6b7280;
}

.booking-footer-tagline {
  margin: 0;
  font-size: 11px;
  color: #9ca3af;
  letter-spacing: 0.02em;
}
</style>
