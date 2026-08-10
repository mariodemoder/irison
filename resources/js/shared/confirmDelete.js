import Swal from 'sweetalert2'

/**
 * Popup genérico de confirmación de borrado.
 *
 * ÚNICO estilo de confirmación de borrado de la app (naranja warning).
 * OBLIGATORIO usar este helper para cualquier operación de eliminar:
 * si creas un borrado, usa confirmDelete() — no crees otro popup.
 * Ver: docs/frontend/confirmacion-eliminar.md
 *
 * @param {Object} options
 * @param {string} options.title             Título del popup (p.ej. "Eliminar gasto")
 * @param {string} options.text              Mensaje (p.ej. '¿Eliminar "X"? Esta acción no se puede deshacer.')
 * @param {string} [options.confirmButtonText] Texto del botón de confirmar (default 'Sí, eliminar')
 * @param {string} [options.cancelButtonText]  Texto del botón de cancelar (default 'Cancelar')
 * @param {string} [options.iconColor]         Color del icono de advertencia (default '#f97316')
 * @returns {Promise<boolean>} true si el usuario confirmó, false si canceló
 */
export async function confirmDelete({
  title = 'Eliminar',
  text = 'Esta acción no se puede deshacer.',
  confirmButtonText = 'Sí, eliminar',
  cancelButtonText = 'Cancelar',
  iconColor = '#f97316',
} = {}) {
  const { isConfirmed } = await Swal.fire({
    title,
    text,
    icon: 'warning',
    iconColor,
    width: '420px',
    buttonsStyling: false,
    customClass: {
      popup: 'swal-popup-warning-card',
      confirmButton: 'app-btn app-btn-warning',
      cancelButton: 'app-btn app-btn-muted',
      actions: 'swal-actions',
    },
    showCancelButton: true,
    confirmButtonText,
    cancelButtonText,
  })
  return isConfirmed
}
