import { ref } from 'vue'
import api from '../services/api'

export const meUser = ref(null)
export const meClinic = ref(null)
export const meStatus = ref('blocked')
export const meTrialEndsAt = ref(null)

let lastToken = null
let meLoaded = false
let meRequestPromise = null

function getCurrentToken() {
  try {
    return localStorage.getItem('token') || null
  } catch {
    return null
  }
}

function resetMeCache() {
  meUser.value = null
  meClinic.value = null
  meStatus.value = 'blocked'
  meTrialEndsAt.value = null
  meLoaded = false
  meRequestPromise = null
}

export async function ensureMeLoaded(options = {}) {
  const force = Boolean(options?.force)
  const token = getCurrentToken()

  if (token !== lastToken) {
    lastToken = token
    resetMeCache()
  }

  if (!force && meLoaded) {
    return
  }

  if (!force && meRequestPromise) {
    await meRequestPromise
    return
  }

  meRequestPromise = (async () => {
    const res = await api.get('/me')
    meUser.value = res.data?.user || null
    meClinic.value = res.data?.clinic || null
    meStatus.value = res.data?.status || 'blocked'
    meTrialEndsAt.value = res.data?.trial_ends_at || null
    meLoaded = true
  })()

  try {
    await meRequestPromise
  } finally {
    meRequestPromise = null
  }
}
