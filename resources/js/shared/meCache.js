import { computed, ref } from 'vue'
import api from '../services/api'

export const meUser = ref(null)
export const meClinic = ref(null)
export const meStatus = ref('blocked')
export const meTrialEndsAt = ref(null)
export const meCancellationGraceEndsAt = ref(null)
export const meCancellationDaysLeft = ref(null)
export const meCancellationReadOnlyDaysLeft = ref(null)
export const meReadOnlyNoTransactions = ref(false)
export const meCanTransact = ref(false)

export const isProfessional = computed(() => meUser.value?.profile?.slug === 'professional')
export const isReceptionist = computed(() => meUser.value?.profile?.slug === 'reception')
export const isFullAccess = computed(() => {
  const slug = meUser.value?.profile?.slug
  return meUser.value?.role === 'owner' || slug === 'admin' || slug === 'manager'
})
export const hasOperationalAccess = computed(() => isFullAccess.value || isReceptionist.value)

export const mePlan = computed(() => meClinic.value?.plan || 'basic')
export const isBasic = computed(() => mePlan.value === 'basic')
export const isPro = computed(() => mePlan.value === 'pro')
export const isEnterprise = computed(() => mePlan.value === 'enterprise')
export const planLabel = computed(() => {
  const labels = { basic: 'BASIC', pro: 'PRO', enterprise: 'ENTERPRISE' }
  return labels[mePlan.value] || 'BASIC'
})

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
  meCancellationGraceEndsAt.value = null
  meCancellationDaysLeft.value = null
  meCancellationReadOnlyDaysLeft.value = null
  meReadOnlyNoTransactions.value = false
  meCanTransact.value = false
  meLoaded = false
  meRequestPromise = null
}

export { resetMeCache }

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
    meCancellationGraceEndsAt.value = res.data?.cancellation_grace_ends_at || null
    meCancellationDaysLeft.value = res.data?.cancellation_days_left ?? null
    meCancellationReadOnlyDaysLeft.value = res.data?.cancellation_read_only_days_left ?? null
    meReadOnlyNoTransactions.value = Boolean(res.data?.read_only_no_transactions)
    meCanTransact.value = Boolean(res.data?.can_transact)
    meLoaded = true
  })()

  try {
    await meRequestPromise
  } finally {
    meRequestPromise = null
  }
}
