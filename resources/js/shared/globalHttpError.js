import { reactive } from 'vue'

export const globalHttpError = reactive({
  visible: false,
  variant: 'error',
  title: '',
  message: '',
  details: [],
  status: 0,
})

export function showGlobalHttpError({
  variant = 'error',
  title = 'Ha ocurrido un error',
  message = 'Intentalo de nuevo en unos segundos.',
  details = [],
  status = 0,
} = {}) {
  globalHttpError.visible = true
  globalHttpError.variant = ['error', 'warning', 'info'].includes(variant) ? variant : 'error'
  globalHttpError.title = String(title || '').trim() || 'Ha ocurrido un error'
  globalHttpError.message = String(message || '').trim() || 'Intentalo de nuevo en unos segundos.'
  globalHttpError.details = Array.isArray(details)
    ? details.map((detail) => String(detail || '').trim()).filter(Boolean)
    : []
  globalHttpError.status = Number(status || 0)
}

export function clearGlobalHttpError() {
  globalHttpError.visible = false
  globalHttpError.variant = 'error'
  globalHttpError.title = ''
  globalHttpError.message = ''
  globalHttpError.details = []
  globalHttpError.status = 0
}
