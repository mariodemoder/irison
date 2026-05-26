import axios from 'axios'
import router from '../router'
import logout from '../utils/logout'
import { resetMeCache } from '../shared/meCache'
import { getValidationMessages } from '../shared/httpErrors'
import { clearGlobalHttpError, showGlobalHttpError } from '../shared/globalHttpError'

function ensureErrorPayload(error, fallbackMessage) {
  if (!error.response) return error

  if (!error.response.data || typeof error.response.data !== 'object') {
    error.response.data = {}
  }

  if (!error.response.data.message && fallbackMessage) {
    error.response.data.message = fallbackMessage
  }

  return error
}

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    Accept: 'application/json',
  },
})

// Añadir token si existe
api.interceptors.request.use(config => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  response => {
    clearGlobalHttpError()

    const code = response?.data?.code
    if (code === 'SUBSCRIPTION_REQUIRED' && router.currentRoute.value.path !== '/billing/required') {
      router.push('/billing/required')
    }

    return response
  },
  async error => {
    const status = Number(error?.response?.status || 0)
    const code = String(error?.response?.data?.code || '')

    if (status === 401) {
      clearGlobalHttpError()
      resetMeCache()
      await logout(router)
      return Promise.reject(ensureErrorPayload(error, 'Tu sesión ha expirado. Inicia sesión de nuevo.'))
    }

    if ((status === 402 || status === 403) && code === 'SUBSCRIPTION_REQUIRED') {
      if (router.currentRoute.value.path !== '/billing/required') {
        router.push('/billing/required')
      }

      showGlobalHttpError({
        variant: 'warning',
        status,
        title: 'Suscripción requerida',
        message: 'Tu suscripción requiere activación para continuar.',
      })

      return Promise.reject(ensureErrorPayload(error, 'Tu suscripción requiere activación para continuar.'))
    }

    if (status === 403 && code === 'CLINIC_READ_ONLY_NO_TRANSACTIONS') {
      showGlobalHttpError({
        variant: 'warning',
        status,
        title: 'Modo solo lectura',
        message: 'Durante esta semana no se permiten transacciones.',
      })

      return Promise.reject(ensureErrorPayload(error, 'Modo solo lectura: durante esta semana no se permiten transacciones.'))
    }

    if (status === 403) {
      showGlobalHttpError({
        variant: 'warning',
        status,
        title: 'Acceso denegado',
        message: 'No tienes permisos para realizar esta accion.',
      })

      return Promise.reject(ensureErrorPayload(error, 'No tienes permisos para realizar esta accion.'))
    }

    if (status === 422) {
      const validationMessages = getValidationMessages(error)
      showGlobalHttpError({
        variant: 'warning',
        status,
        title: 'Revisa los datos del formulario',
        message: validationMessages.length
          ? 'Corrige los campos marcados para continuar.'
          : 'Hay campos con errores de validacion.',
        details: validationMessages,
      })

      return Promise.reject(ensureErrorPayload(error, 'Hay campos con errores de validacion.'))
    }

    if (status >= 500) {
      showGlobalHttpError({
        variant: 'error',
        status,
        title: 'Error del servidor',
        message: 'Ha ocurrido un error inesperado. Intentalo de nuevo.',
      })

      return Promise.reject(ensureErrorPayload(error, 'Ha ocurrido un error inesperado. Inténtalo de nuevo.'))
    }

    clearGlobalHttpError()

    return Promise.reject(error)
  }
)

export default api
