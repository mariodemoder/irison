<template>
  <div class="sign-page">
    <div v-if="loading" class="loading-state">
      <p>Cargando consentimiento...</p>
    </div>

    <div v-else-if="error" class="error-state">
      <h2>{{ errorTitle }}</h2>
      <p>{{ errorMessage }}</p>
    </div>

    <div v-else-if="consent" class="sign-card">
      <div class="sign-header">
        <h1>{{ consent.template_title }}</h1>
        <p class="clinic-name">{{ consent.clinic_name }}</p>
        <p class="patient-name">Paciente: {{ consent.patient_name }}</p>
      </div>

      <div class="sign-content" v-html="consent.content_html"></div>

      <div class="sign-area">
        <h3>Firma</h3>
        <SignPad ref="signPad" @confirm="submitSign" />
        <div v-if="submitting" class="saving-indicator">Registrando firma...</div>
        <div v-if="success" class="success-message">
          Gracias. Su consentimiento ha sido registrado correctamente.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import SignPad from '../../components/consents/SignPad.vue'

const route = useRoute()
const loading = ref(true)
const error = ref(false)
const errorTitle = ref('')
const errorMessage = ref('')
const consent = ref(null)
const submitting = ref(false)
const success = ref(false)
const signPad = ref(null)

onMounted(async () => {
  try {
    const res = await axios.get(`/api/consent/sign/${route.params.token}`)
    consent.value = res.data.data
    loading.value = false
  } catch (e) {
    loading.value = false
    error.value = true
    if (e.response?.status === 410) {
      if (e.response?.data?.status === 'already_signed') {
        errorTitle.value = 'Ya firmado'
        errorMessage.value = 'Este consentimiento ya ha sido firmado anteriormente.'
      } else {
        errorTitle.value = 'Enlace caducado'
        errorMessage.value = 'Este enlace ha caducado. Solicita un nuevo enlace.'
      }
    } else {
      errorTitle.value = 'Enlace no válido'
      errorMessage.value = 'Este enlace no es válido.'
    }
  }
})

async function submitSign(svg) {
  submitting.value = true
  try {
    await axios.post(`/api/consent/sign/${route.params.token}`, { signature_svg: svg })
    success.value = true
  } catch (_) {
    alert('Error al registrar la firma. Intenta de nuevo.')
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.sign-page { min-height: 100vh; background: #f3f4f6; display: flex; align-items: center; justify-content: center; padding: 24px; font-family: system-ui, sans-serif; }
.sign-card { max-width: 800px; width: 100%; background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,.1); padding: 40px; }
.sign-header { margin-bottom: 24px; text-align: center; }
.sign-header h1 { font-size: 22px; color: #1f2937; margin: 0 0 8px; }
.clinic-name { color: #4338ca; font-weight: 600; margin: 0 0 4px; }
.patient-name { color: #6b7280; margin: 0; }
.sign-content { padding: 24px 0; border-top: 1px solid #e5e7eb; line-height: 1.7; font-size: 14px; }
.sign-area { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 24px; }
.sign-area h3 { font-size: 16px; margin-bottom: 12px; }
.loading-state, .error-state { text-align: center; padding: 48px; color: #6b7280; }
.error-state h2 { font-size: 20px; color: #dc2626; margin-bottom: 8px; }
.saving-indicator { color: #4338ca; font-weight: 600; margin-top: 12px; }
.success-message { background: #d1fae5; color: #065f46; padding: 16px; border-radius: 8px; font-weight: 600; text-align: center; margin-top: 12px; }
</style>
