export function parseAppointmentDateTime(value) {
  if (!value) return null

  if (value instanceof Date) {
    return Number.isNaN(value.getTime()) ? null : value
  }

  const raw = String(value).trim()
  if (!raw) return null

  const sqlMatch = raw.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/)
  if (sqlMatch) {
    const [, y, m, d, hh, mm, ss] = sqlMatch
    const parsed = new Date(Number(y), Number(m) - 1, Number(d), Number(hh), Number(mm), Number(ss || '0'))
    return Number.isNaN(parsed.getTime()) ? null : parsed
  }

  const parsed = new Date(raw)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

const APPOINTMENT_SLOT_MINUTES = 15

export function toDatetimeLocalValue(value) {
  const raw = value == null ? '' : String(value).trim()
  if (!raw) return ''

  const sqlLike = raw.match(/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/)
  if (sqlLike) {
    return raw.replace(' ', 'T').slice(0, 16)
  }

  const parsed = parseAppointmentDateTime(raw)
  if (!parsed) return ''

  const yyyy = parsed.getFullYear()
  const mm = String(parsed.getMonth() + 1).padStart(2, '0')
  const dd = String(parsed.getDate()).padStart(2, '0')
  const hh = String(parsed.getHours()).padStart(2, '0')
  const min = String(parsed.getMinutes()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}T${hh}:${min}`
}

export function formatTime(dt) {
  if (!dt) return '—'
  try {
    const d = parseAppointmentDateTime(dt)
    if (!d) return dt
      // Forzar formato 24h (sin AM/PM) para consistencia en la UI
      return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false })
  } catch (e) {
    return dt
  }
}

  export function formatTimeCalendar(dt) {
    if (!dt) return '—'
    try {
      const d = parseAppointmentDateTime(dt)
      if (!d) return dt
      let hh = d.getHours()
      let mm = d.getMinutes()
      // Truncar al multiplo de 15 (no avanzar al siguiente intervalo)
      mm = Math.floor(mm / APPOINTMENT_SLOT_MINUTES) * APPOINTMENT_SLOT_MINUTES
      const hhStr = String(hh).padStart(2, '0')
      const mmStr = String(mm).padStart(2, '0')
      return `${hhStr}:${mmStr}`
    } catch (e) {
      return dt
    }
  }
export function formatDate(d) {
  if (!d) return ''
  try {
    const dt = parseAppointmentDateTime(d)
    if (!dt) return d
    // Fecha + hora en formato legible sin AM/PM
    return dt.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })
  } catch (e) {
    return d
  }
}

export function formatDateShort(dt) {
  if (!dt) return ''
  try {
    const d = parseAppointmentDateTime(dt)
    if (!d) return dt
    const day = String(d.getDate()).padStart(2, '0')
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const year = d.getFullYear()
    return `${day}/${month}/${year}`
  } catch (e) {
    return dt
  }
}

export function statusLabel(s) {
  if (!s) return '—'
  const map = {
    scheduled: 'Programada',
    rescheduled: 'Reprogramada',
    completed: 'Completada',
    canceled: 'Cancelada',
    cancelled: 'Cancelada'
  }
  return map[s] || String(s)
}

export function timeClass(s) {
  if (!s) return ''
  const map = {
    scheduled: 'time-scheduled',
    rescheduled: 'time-rescheduled',
    completed: 'time-completed',
    canceled: 'time-canceled',
    cancelled: 'time-canceled'
  }
  return map[s] || ''
}

import Swal from 'sweetalert2'

export function getContrastColor(hex) {
  if (!hex) return '#1f2937'
  const c = hex.replace('#', '')
  const r = parseInt(c.substring(0,2), 16)
  const g = parseInt(c.substring(2,4), 16)
  const b = parseInt(c.substring(4,6), 16)
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255
  return luminance > 0.5 ? '#1f2937' : '#ffffff'
}

export async function confirmAndCancel(id, { api, toast, onSuccess } = {}) {
  const { isConfirmed } = await Swal.fire({
    title: '¿Cancelar esta cita?',
    text: 'Esta acción no se puede deshacer.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, cancelar',
    cancelButtonText: 'No, mantener',
    buttonsStyling: false,
    customClass: {
      popup: 'swal-popup-card',
      confirmButton: 'primary',
      cancelButton: 'muted',
    },
  })
  if (!isConfirmed) return false
  try {
    await api.post(`/appointments/${id}/cancel`)
    if (toast && typeof toast.success === 'function') {
      toast.success('Cita cancelada', {
        toastClassName: 'toast-delete',
        progressClassName: 'toast-delete-progress',
      })
    }
    if (typeof onSuccess === 'function') await onSuccess()
    return true
  } catch (e) {
    if (toast && typeof toast.error === 'function') toast.error('Error cancelando la cita')
    throw e
  }
}

export async function findOverlaps({ start, end, currentId = null, api, per_page = 200, professionalId = null }) {
  if (!start || !end) return []
  const params = { start: new Date(start).toISOString(), end: new Date(end).toISOString(), per_page }
  if (professionalId) {
    params.professional_id = professionalId
  } else {
    params.no_professional = 1
  }
  const res = await api.get('/appointments', { params })
  const list = Array.isArray(res.data.data) ? res.data.data : (res.data || [])

  const chosenStart = new Date(start)
  const chosenEnd = new Date(end)
  const isSameDay = (d1, d2) => (
    d1.getFullYear() === d2.getFullYear() &&
    d1.getMonth() === d2.getMonth() &&
    d1.getDate() === d2.getDate()
  )

  return list.filter(a => {
    try {
      // ignore canceled appointments for overlap checks
      if (a.status === 'canceled' || a.status === 'cancelled') return false
      const aStart = new Date(a.start_time)
      const aEnd = new Date(a.end_time)
      const intersects = (aStart < chosenEnd && aEnd > chosenStart)
      return intersects && isSameDay(aStart, chosenStart) && String(a.id) !== String(currentId)
    } catch (e) {
      return false
    }
  })
}
