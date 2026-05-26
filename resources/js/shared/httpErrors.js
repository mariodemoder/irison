export function getHttpStatus(error) {
  return Number(error?.response?.status || 0)
}

export function getApiErrorCode(error) {
  return String(error?.response?.data?.code || '')
}

export function getApiErrorMessage(error, fallback = 'Ha ocurrido un error inesperado.') {
  const message = error?.response?.data?.message
  if (typeof message === 'string' && message.trim()) {
    return message.trim()
  }

  if (typeof error?.message === 'string' && error.message.trim()) {
    return error.message.trim()
  }

  return fallback
}

export function getValidationMessages(error) {
  const errors = error?.response?.data?.errors
  if (!errors || typeof errors !== 'object') return []

  return Object.values(errors).flat().map((message) => String(message || '').trim()).filter(Boolean)
}

export function isSubscriptionRequiredError(error) {
  const status = getHttpStatus(error)
  const code = getApiErrorCode(error)

  return code === 'SUBSCRIPTION_REQUIRED' && (status === 402 || status === 403)
}

export function getLoadErrorMessage(error, entityLabel = 'datos') {
  const status = getHttpStatus(error)
  const message = getApiErrorMessage(error, '')
  const base = `Error cargando ${String(entityLabel || 'datos').trim() || 'datos'}`

  if ((status === 402 || status === 403) && message) {
    return `${base} - ${message}`
  }

  return base
}