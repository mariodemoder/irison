import { ref, computed } from 'vue'
import patientApi from '../services/patientApi'

const patient = ref(null)
const token = ref(localStorage.getItem('patient_token'))
const clinicBranding = ref(null)
const portalSettings = ref(null)

export function usePatientAuth() {
  const isAuthenticated = computed(() => !!token.value)

  async function login(email, password, clinic) {
    const { data } = await patientApi.post('/auth/login', { email, password, clinic: clinic || undefined })
    token.value = data.token
    patient.value = data.patient
    clinicBranding.value = data.patient?.clinic ?? null
    portalSettings.value = data.portal ?? null
    localStorage.setItem('patient_token', data.token)
    localStorage.setItem('patient_data', JSON.stringify(data.patient))
    localStorage.setItem('patient_portal_settings', JSON.stringify(data.portal ?? null))
    return data
  }

  async function logout() {
    try {
      await patientApi.post('/auth/logout')
    } catch (e) {
      // Ignore errors on logout
    } finally {
      token.value = null
      patient.value = null
      clinicBranding.value = null
      portalSettings.value = null
      localStorage.removeItem('patient_token')
      localStorage.removeItem('patient_data')
      localStorage.removeItem('patient_portal_settings')
    }
  }

  async function fetchMe() {
    try {
      const { data } = await patientApi.get('/auth/me')
      patient.value = data
      clinicBranding.value = data.clinic ?? null
      portalSettings.value = data.portal ?? null
      localStorage.setItem('patient_data', JSON.stringify(data))
      localStorage.setItem('patient_portal_settings', JSON.stringify(data.portal ?? null))
      return data
    } catch (e) {
      if (e?.response?.status === 401) {
        await logout()
      }
      throw e
    }
  }

  /**
   * Obtiene el branding público de la clínica (páginas guest: login,
   * forgot/reset). Resuelve por Clinic.slug vía ?clinic= en la URL.
   */
  async function fetchClinicBranding(slug) {
    if (!slug) {
      clinicBranding.value = null
      return null
    }
    try {
      const { data } = await patientApi.get(`/public/branding/${encodeURIComponent(slug)}`)
      clinicBranding.value = data
      return data
    } catch (e) {
      clinicBranding.value = null
      return null
    }
  }

  function loadFromStorage() {
    const stored = localStorage.getItem('patient_data')
    if (stored) {
      try {
        patient.value = JSON.parse(stored)
      } catch (e) {
        patient.value = null
      }
    }
  }

  function clearAuth() {
    token.value = null
    patient.value = null
    clinicBranding.value = null
    portalSettings.value = null
    localStorage.removeItem('patient_token')
    localStorage.removeItem('patient_data')
    localStorage.removeItem('patient_portal_settings')
  }

  // Initialize from storage
  if (token.value && !patient.value) {
    loadFromStorage()
    const storedPortal = localStorage.getItem('patient_portal_settings')
    if (storedPortal) {
      try {
        portalSettings.value = JSON.parse(storedPortal)
      } catch (e) {
        portalSettings.value = null
      }
    }
  }

  return {
    patient,
    token,
    clinicBranding,
    portalSettings,
    isAuthenticated,
    login,
    logout,
    fetchMe,
    fetchClinicBranding,
    loadFromStorage,
    clearAuth,
  }
}
