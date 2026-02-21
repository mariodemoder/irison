export function formatTime(dt) {
  if (!dt) return '—'
  try {
    const d = new Date(dt)
      // Forzar formato 24h (sin AM/PM) para consistencia en la UI
      return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false })
  } catch (e) {
    return dt
  }
}

  export function formatTimeCalendar(dt) {
    if (!dt) return '—'
    try {
      const d = new Date(dt)
      let hh = d.getHours()
      let mm = d.getMinutes()
      // truncar al múltiplo de 5 (no avanzar al siguiente intervalo)
      mm = Math.floor(mm / 5) * 5
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
    const dt = new Date(d)
    // Fecha + hora en formato legible sin AM/PM
    return dt.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })
  } catch (e) {
    return d
  }
}

export function formatDateShort(dt) {
  if (!dt) return ''
  try {
    const d = new Date(dt)
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

export async function confirmAndCancel(id, { api, toast, onSuccess } = {}) {
  const { isConfirmed } = await Swal.fire({
    title: '¿Cancelar esta cita?',
    text: 'Esta acción no se puede deshacer.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, cancelar',
    cancelButtonText: 'No, mantener',
  })
  if (!isConfirmed) return false
  try {
    await api.post(`/appointments/${id}/cancel`)
    if (toast && typeof toast.success === 'function') toast.success('Cita cancelada')
    if (typeof onSuccess === 'function') await onSuccess()
    return true
  } catch (e) {
    if (toast && typeof toast.error === 'function') toast.error('Error cancelando la cita')
    throw e
  }
}

export async function findOverlaps({ start, end, currentId = null, api, per_page = 200 }) {
  if (!start || !end) return []
  const params = { start: new Date(start).toISOString(), end: new Date(end).toISOString(), per_page }
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
