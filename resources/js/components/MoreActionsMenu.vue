<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

/**
 * Botón de acciones "tres puntitos" (⋮) con menú desplegable.
 *
 * Unifica los antiguos patrones `sub-menu` (carácter &#8942;) y `quick-actions`
 * (SVG 3 círculos). Ver docs/frontend/more-actions-menu.md
 *
 * Uso:
 *   <MoreActionsMenu aria-label="Más opciones">
 *     <button class="ma-item" @click="doThing">Acción</button>
 *     <button class="ma-item ma-item--danger" @click="doDanger">Eliminar</button>
 *     <a class="ma-item" href="/x" target="_blank">Enlace</a>
 *   </MoreActionsMenu>
 *
 * Props:
 *   align            'right' (default) | 'left' — alineación del menú respecto al trigger
 *   disabled         Deshabilita el trigger
 *   ariaLabel        aria-label / title del trigger (default 'Acciones')
 *   closeOnItemClick Cerrar el menú al hacer click en un item (default true).
 *                    Se escucha en fase de captura, así que funciona también
 *                    para items con `@click.stop` (evita que el click navegue
 *                    en filas clickables).
 *   triggerClass     Clases extra para el trigger por defecto (p.ej. "muted quick-trigger menu-right-btn").
 *                    Si se pasa, se omite el estilo por defecto `ma-trigger--default`.
 *
 * Slot #trigger (opcional): trigger personalizado. Recibe { toggle, open }.
 */
const props = defineProps({
  align: { type: String, default: 'right' }, // 'right' | 'left'
  disabled: { type: Boolean, default: false },
  ariaLabel: { type: String, default: 'Acciones' },
  closeOnItemClick: { type: Boolean, default: true },
  triggerClass: { type: String, default: '' },
})

const open = ref(false)
const rootRef = ref(null)
const triggerRef = ref(null)

function toggle() {
  if (props.disabled) return
  open.value = !open.value
}

function close() {
  open.value = false
}

function handleOutsideClick(event) {
  if (rootRef.value && !rootRef.value.contains(event.target)) {
    close()
  }
}

function handleKeydown(event) {
  if (event.key === 'Escape' && open.value) {
    close()
    triggerRef.value?.focus()
  }
}

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)
  document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleOutsideClick)
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div ref="rootRef" class="more-actions" :class="`align-${align}`">
    <slot name="trigger" :toggle="toggle" :open="open">
      <button
        ref="triggerRef"
        type="button"
        class="ma-trigger"
        :class="triggerClass || 'ma-trigger--default'"
        :disabled="disabled"
        :aria-label="ariaLabel"
        :title="ariaLabel"
        aria-haspopup="menu"
        :aria-expanded="open"
        @click.stop="toggle"
      >
        <svg class="ma-trigger-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <circle cx="12" cy="5" r="1.8" fill="currentColor" />
          <circle cx="12" cy="12" r="1.8" fill="currentColor" />
          <circle cx="12" cy="19" r="1.8" fill="currentColor" />
        </svg>
      </button>
    </slot>

    <div
      v-if="open"
      class="ma-menu"
      role="menu"
      :aria-label="ariaLabel"
      @click.capture="closeOnItemClick ? close() : null"
    >
      <slot />
    </div>
  </div>
</template>

<style scoped>
.more-actions {
  position: relative;
  display: inline-block;
}

/* Empuja el componente a la derecha dentro de un contenedor flex */
.more-actions--push-right {
  margin-left: auto;
}

/* Trigger por defecto: botón icono compacto (filas de listados) */
.ma-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-family: inherit;
  line-height: 1;
}
.ma-trigger:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.ma-trigger--default {
  width: 32px;
  height: 32px;
  padding: 0;
  background: #f9fafb;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  color: #6b7280;
}
.ma-trigger--default:hover:not(:disabled) {
  background: #f3f4f6;
  color: #374151;
}

.ma-trigger-icon {
  width: 18px;
  height: 18px;
  display: block;
}

/* Menú desplegable */
.ma-menu {
  position: absolute;
  top: calc(100% + 4px);
  min-width: 180px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  box-shadow: 0 10px 24px rgba(2, 6, 23, 0.1);
  padding: 6px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  z-index: 100;
}
.align-right .ma-menu {
  right: 0;
}
.align-left .ma-menu {
  left: 0;
}

/* Items del menú: aplicar la clase `ma-item` a cada botón/enlace del slot */
.ma-menu :deep(.ma-item) {
  display: block;
  width: 100%;
  padding: 8px 10px;
  border: 1px solid transparent;
  background: #fff;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  text-align: left;
  color: #111827;
  font-family: inherit;
  text-decoration: none;
  white-space: nowrap;
  cursor: pointer;
}
.ma-menu :deep(.ma-item:hover:not(:disabled)) {
  background: #f9fafb;
}
.ma-menu :deep(.ma-item:disabled) {
  opacity: 0.45;
  cursor: not-allowed;
}
.ma-menu :deep(.ma-item--danger) {
  color: #b91c1c;
}
.ma-menu :deep(.ma-item--danger:hover:not(:disabled)) {
  background: #fef2f2;
}
</style>
