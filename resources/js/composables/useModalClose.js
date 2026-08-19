import { ref, onMounted, onBeforeUnmount } from 'vue'

/**
 * Cierre seguro de modales:
 * - El backdrop solo cierra cuando mousedown Y mouseup ocurren sobre el propio
 *   backdrop (evita que el modal se cierre al seleccionar texto y soltar el
 *   ratón fuera del panel).
 * - La tecla ESC cierra el modal.
 *
 * @param {Function} closeFn - se llama cuando el modal debe cerrarse
 * @param {import('vue').Ref<boolean>|null} isOpen - ref opcional que condiciona
 *   el ESC (para modales inline cuyo script vive siempre montado). Pasa null
 *   cuando el componente solo existe mientras está abierto (v-if).
 */
export function useModalClose(closeFn, isOpen = null) {
  const mousedownTarget = ref(null)

  function onBackdropMouseDown(e) {
    mousedownTarget.value = e.target
  }

  function onBackdropMouseUp(e) {
    if (e.target === e.currentTarget && mousedownTarget.value === e.currentTarget) {
      closeFn()
    }
    mousedownTarget.value = null
  }

  function onKeydown(e) {
    if (e.key !== 'Escape') return
    if (isOpen && !isOpen.value) return
    closeFn()
  }

  onMounted(() => document.addEventListener('keydown', onKeydown))
  onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))

  return { onBackdropMouseDown, onBackdropMouseUp }
}
