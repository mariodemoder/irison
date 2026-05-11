import { findOverlaps, confirmAndCancel } from './appointmentHelpers'
import { goBackWithPriority } from './navigationHelpers'

export async function openCreatePatientPopup({ api, Swal, toast } = {}) {
  const { value: formValues } = await Swal.fire({
    title: 'Crear paciente',
    html: `
      <div class="swal-card" style="display:grid;gap:8px;text-align:left;max-height:55vh;overflow:auto;padding-right:4px;">
        <input id="swal-name" class="input" placeholder="Nombre">
        <input id="swal-nif" class="input" placeholder="NIF (opcional)">
        <input id="swal-phone" class="input" placeholder="Telefono (opcional)">
        <input id="swal-email" class="input" placeholder="Email (opcional)">
        <input id="swal-birth-date" class="input" type="date" placeholder="Fecha de nacimiento">
        <input id="swal-address" class="input" placeholder="Direccion (opcional)">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <input id="swal-zip" class="input" placeholder="ZIP (opcional)">
          <input id="swal-city" class="input" placeholder="Ciudad (opcional)">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <input id="swal-province" class="input" placeholder="Provincia (opcional)">
          <input id="swal-country" class="input" placeholder="Pais (opcional)">
        </div>
        <textarea id="swal-notes" class="input" rows="3" placeholder="Notas (opcional)" style="resize:vertical;"></textarea>
      </div>
    `,
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
      const birth_date = document.getElementById('swal-birth-date')?.value?.trim() || null
      const address = document.getElementById('swal-address')?.value?.trim() || null
      const zip = document.getElementById('swal-zip')?.value?.trim() || null
      const city = document.getElementById('swal-city')?.value?.trim() || null
      const province = document.getElementById('swal-province')?.value?.trim() || null
      const country = document.getElementById('swal-country')?.value?.trim() || null
      const notes = document.getElementById('swal-notes')?.value?.trim() || null

      if (!name) {
        Swal.showValidationMessage('El nombre es requerido')
        return false
      }

      if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        Swal.showValidationMessage('Formato de email invalido')
        return false
      }

      try {
        const res = await api.post('/patients', {
          name,
          nif,
          phone,
          email,
          birth_date,
          address,
          zip,
          city,
          province,
          country,
          notes,
        })
        return res.data || res.data?.data || res
      } catch (e) {
        const validationErrors = e.response?.data?.errors
        const firstFieldError = validationErrors && Object.values(validationErrors)[0]?.[0]
        const msg = firstFieldError || e.response?.data?.message || 'Error creando paciente'
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
