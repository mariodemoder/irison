import { findOverlaps, confirmAndCancel } from './appointmentHelpers'
import { goBackWithPriority } from './navigationHelpers'

export async function openCreatePatientPopup({ api, Swal, toast } = {}) {
  const { value: formValues } = await Swal.fire({
    title: 'Crear paciente',
    html: `
      <div class="swal-card swal-card-scrollable">
        <div class="create-row">
          <label for="swal-name">Nombre</label>
          <input id="swal-name" class="input" placeholder="Ej: Juan Perez">
        </div>
        <div class="create-row">
          <label for="swal-nif">NIF (opcional)</label>
          <input id="swal-nif" class="input" placeholder="Ej: 12345678A">
        </div>
        <div class="create-row">
          <label for="swal-phone">Telefono (opcional)</label>
          <input id="swal-phone" class="input" placeholder="Ej: +34 600 000 000">
        </div>
        <div class="create-row">
          <label for="swal-email">Email (opcional)</label>
          <input id="swal-email" class="input" placeholder="Ej: paciente@email.com">
        </div>
        <div class="create-row">
          <label for="swal-birth-date">Fecha de nacimiento (opcional)</label>
          <input id="swal-birth-date" class="input" type="date">
        </div>
        <div class="create-row">
          <label for="swal-address">Direccion (opcional)</label>
          <input id="swal-address" class="input" placeholder="Calle, numero, piso">
        </div>
        <div class="create-grid-2">
          <div class="create-row">
            <label for="swal-zip">ZIP (opcional)</label>
            <input id="swal-zip" class="input" placeholder="Ej: 28001">
          </div>
          <div class="create-row">
            <label for="swal-city">Ciudad (opcional)</label>
            <input id="swal-city" class="input" placeholder="Ej: Madrid">
          </div>
        </div>
        <div class="create-grid-2">
          <div class="create-row">
            <label for="swal-province">Provincia (opcional)</label>
            <input id="swal-province" class="input" placeholder="Ej: Madrid">
          </div>
          <div class="create-row">
            <label for="swal-country">Pais (opcional)</label>
            <input id="swal-country" class="input" placeholder="Ej: Espana">
          </div>
        </div>
        <div class="create-row">
          <label for="swal-notes">Notas (opcional)</label>
          <textarea id="swal-notes" class="input" rows="3" placeholder="Observaciones" style="resize:vertical;"></textarea>
        </div>
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
      customClass: { popup: 'swal-popup-card', confirmButton: 'primary' }
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

export async function openCreateAppointmentTypePopup({ api, Swal, toast } = {}) {
  const { value: formValues } = await Swal.fire({
    title: 'Crear tipo de sesión',
    html: `
      <div class="swal-card swal-card-scrollable">
        <div class="create-row">
          <label for="swal-type-description">Descripción</label>
          <input id="swal-type-description" class="input" placeholder="Ej: Sesión estándar">
        </div>
        <div class="create-grid-2">
          <div class="create-row">
            <label for="swal-type-hours">Horas estimadas</label>
            <input id="swal-type-hours" class="input" type="number" min="0" max="23" value="0" step="1">
          </div>
          <div class="create-row">
            <label for="swal-type-minutes">Minutos estimados</label>
            <input id="swal-type-minutes" class="input" type="number" min="0" max="59" value="60" step="5">
          </div>
        </div>
        <div class="create-row">
          <label for="swal-type-price">Precio (€)</label>
          <input id="swal-type-price" class="input" type="number" min="0" step="0.01" value="0">
        </div>
        <div class="create-row">
          <label>Color</label>
          <div class="create-color-palette" id="swal-type-color-palette">
            <button type="button" class="color-option" data-color="" style="background:#fff;border:2px solid #d1d5db" title="Ninguno"></button>
            <button type="button" class="color-option" data-color="#F8FAFC" style="background:#F8FAFC" title="Irison"></button>
            <button type="button" class="color-option" data-color="#CDD6E9" style="background:#CDD6E9" title="Negro"></button>
            <button type="button" class="color-option" data-color="#FFE0E7" style="background:#FFE0E7" title="Rosa pastel"></button>
            <button type="button" class="color-option" data-color="#FCE0CC" style="background:#FCE0CC" title="Durazno pastel"></button>
            <button type="button" class="color-option" data-color="#FAF6CD" style="background:#FAF6CD" title="Amarillo pastel"></button>
            <button type="button" class="color-option" data-color="#E0FFEC" style="background:#E0FFEC" title="Verde pastel"></button>
            <button type="button" class="color-option" data-color="#CAE3FA" style="background:#CAE3FA" title="Azul pastel"></button>
            <button type="button" class="color-option" data-color="#D8C0FA" style="background:#D8C0FA" title="Lila pastel"></button>
          </div>
        </div>
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
    didOpen: () => {
      const palette = document.getElementById('swal-type-color-palette')
      if (!palette) return
      palette.querySelectorAll('.color-option').forEach(btn => {
        btn.addEventListener('click', () => {
          palette.querySelectorAll('.color-option').forEach(b => b.classList.remove('selected'))
          btn.classList.add('selected')
        })
      })
    },
    preConfirm: async () => {
      const description = document.getElementById('swal-type-description')?.value?.trim()
      const hours = document.getElementById('swal-type-hours')?.value?.trim()
      const minutes = document.getElementById('swal-type-minutes')?.value?.trim()
      const price = document.getElementById('swal-type-price')?.value?.trim()
      const selectedColor = document.querySelector('#swal-type-color-palette .selected')
      const color = selectedColor ? selectedColor.getAttribute('data-color') : ''

      if (!description) {
        Swal.showValidationMessage('La descripción es requerida')
        return false
      }

      try {
        const res = await api.post('/appointment-types', {
          description,
          estimated_hours: hours ? Number(hours) : 0,
          estimated_minutes: minutes ? Number(minutes) : 60,
          price: price ? Number(price) : 0,
          color: color || null,
        })
        return res.data || res.data?.data || res
      } catch (e) {
        const validationErrors = e.response?.data?.errors
        const firstFieldError = validationErrors && Object.values(validationErrors)[0]?.[0]
        const msg = firstFieldError || e.response?.data?.message || 'Error creando tipo de sesión'
        Swal.showValidationMessage(msg)
        return false
      }
    }
  })

  if (formValues) {
    const newType = formValues.data ? formValues.data : formValues
    if (toast && typeof toast.success === 'function') toast.success('Tipo de sesión creado')
    return newType
  }
  return null
}

export default {
  openCreatePatientPopup,
  openCreateAppointmentTypePopup,
  loadPatients,
  checkOverlapShared,
  goBack,
  startReprogramShared,
  appointmentCancelShared
}
