import { findOverlaps, confirmAndCancel } from './appointmentHelpers'
import { goBackWithPriority } from './navigationHelpers'

export async function openCreatePatientPopup({ api, Swal, toast } = {}) {
  const { value: formValues } = await Swal.fire({
    title: 'Crear paciente',
    html:
      '<div class="swal-card">' +
      '<input id="swal-name" class="input" placeholder="Nombre">' +
      '<input id="swal-nif" class="input" placeholder="NIF (opcional)">' +
      '<input id="swal-phone" class="input" placeholder="Teléfono (opcional)">' +
      '<input id="swal-email" class="input" placeholder="Email (opcional)">' +
      '</div>',
    focusConfirm: false,
    showCancelButton: true,
    confirmButtonText: 'Crear',
    cancelButtonText: 'Cancelar',
    buttonsStyling: false,
    customClass: {
      popup: 'swal-popup-card',
      confirmButton: 'primary',
      cancelButton: 'muted'
    },
    preConfirm: async () => {
      const name = document.getElementById('swal-name')?.value?.trim()
      const nif = document.getElementById('swal-nif')?.value?.trim() || null
      const phone = document.getElementById('swal-phone')?.value?.trim() || null
      const email = document.getElementById('swal-email')?.value?.trim() || null
      if (!name) {
        Swal.showValidationMessage('El nombre es requerido')
        return false
      }
      try {
        const res = await api.post('/patients', { name, nif, phone, email })
        return res.data || res.data?.data || res
      } catch (e) {
        const msg = e.response?.data?.message || 'Error creando paciente'
        Swal.showValidationMessage(msg)
        return false
      }
    }
  })

  if (formValues) {
    const newPatient = formValues.data ? formValues.data : formValues
    if (toast && typeof toast.success === 'function') toast.success('Paciente creado')
    return newPatient
  }
  return null
}

export async function loadPatients(api, per_page = 200) {
  try {
    const res = await api.get('/patients', { params: { per_page } })
    return Array.isArray(res.data.data) ? res.data.data : (res.data || [])
  } catch (e) {
    return []
  }
}

export async function checkOverlapShared({ start, end, currentId = null, api, Swal, per_page = 200 } = {}) {
  if (!start || !end) return []
  const filtered = await findOverlaps({ start, end, currentId, api, per_page })
  // defensa adicional: excluir la misma cita si viene en la lista
  const cleaned = filtered.filter(a => String(a.id) !== String(currentId))
  const hasScheduled = cleaned.some(a => a.status === 'scheduled')
  if (hasScheduled && Swal) {
    Swal.fire({
      icon: 'warning',
      title: 'La franja horaria se solapa con otra cita.',
      text: 'Hay una cita ya programada en ese intervalo de tiempo (mismo día).',
      confirmButtonText: 'Entendido',
      buttonsStyling: false,
      customClass: { confirmButton: 'primary' }
    })
  }
  return cleaned
}

export function goBack(router, route) {
  const from = route.query.from
  const id = route.params.id

  let priorityPath = ''

  if (from === 'day') {
    priorityPath = '/appointments/day'
  } else if (from === 'show' && id) {
    priorityPath = `/appointments/${id}`
  }

  goBackWithPriority(router, {
    priorityPath,
    fallbackPath: '/appointments/day',
  })
}

export function startReprogramShared(router, route) {
  router.push({ query: { ...route.query, mode: 'reprogram' } })
}

export function appointmentCancelShared(id, { api, toast, router, onSuccess } = {}) {
  const handler = typeof onSuccess === 'function' ? onSuccess : () => router && router.push('/appointments/day')
  return confirmAndCancel(id, { api, toast, onSuccess: handler })
}

export default {
  openCreatePatientPopup,
  loadPatients,
  checkOverlapShared,
  goBack,
  startReprogramShared,
  appointmentCancelShared
}
