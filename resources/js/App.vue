<template>
  <div
    class="w-full"
    :class="[{ 'theme--dark-menus': isDarkTheme }, { 'app-shell--edge-to-edge': isEdgeToEdge }]"
    :style="{ '--theme-color': themeColor || '#e5e7eb', '--theme-color-light': themeLightColor }"
  >
    <div v-if="globalHttpError.visible" class="global-error-wrap">
      <ErrorAlert
        :variant="globalHttpError.variant"
        :title="globalHttpError.title"
        :message="globalHttpError.message"
        :details="globalHttpError.details"
        dismissible
        @dismiss="clearGlobalHttpError"
      />
    </div>

    <router-view />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import ErrorAlert from './components/ErrorAlert.vue'
import { clearGlobalHttpError, globalHttpError } from './shared/globalHttpError'
import { meClinic } from './shared/meCache'

const route = useRoute()

const themeColor = computed(() => meClinic.value?.theme_color || '#e5e7eb')

const themeLightColor = computed(() => {
  const hex = (themeColor.value || '#e5e7eb').replace('#', '')
  const r = parseInt(hex.slice(0, 2), 16)
  const g = parseInt(hex.slice(2, 4), 16)
  const b = parseInt(hex.slice(4, 6), 16)

  // Si el color base es muy oscuro (ej: negro), mantener un fondo oscuro.
  const perceivedLuma = (0.299 * r) + (0.587 * g) + (0.114 * b)
  const isDarkBase = perceivedLuma < 60

  const whiteMix = isDarkBase ? 0.15 : 0.85
  const colorMix = 1 - whiteMix

  const lr = Math.round(r * colorMix + 255 * whiteMix)
  const lg = Math.round(g * colorMix + 255 * whiteMix)
  const lb = Math.round(b * colorMix + 255 * whiteMix)
  return `rgb(${lr}, ${lg}, ${lb})`
})

const isDarkTheme = computed(() => {
  const hex = (themeColor.value || '#e5e7eb').replace('#', '')
  const r = parseInt(hex.slice(0, 2), 16)
  const g = parseInt(hex.slice(2, 4), 16)
  const b = parseInt(hex.slice(4, 6), 16)
  const perceivedLuma = (0.299 * r) + (0.587 * g) + (0.114 * b)
  return perceivedLuma < 60
})

const isEdgeToEdge = computed(() => route.meta?.publicLanding === true)
</script>

<style scoped>
.w-full {
  padding: 0 2%;
}

.app-shell--edge-to-edge {
  padding: 0;
}

.global-error-wrap {
  position: sticky;
  top: 12px;
  z-index: 60;
  margin: 14px 0;
}

@media (max-width: 640px) {
  .global-error-wrap {
    top: 6px;
    margin: 10px 0;
  }
}
</style>
