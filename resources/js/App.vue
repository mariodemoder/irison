<template>
  <div
    class="w-full"
    :class="{ 'app-shell--edge-to-edge': isEdgeToEdge }"
    :style="{ '--theme-color': themeColor || '#e5e7eb', '--theme-color-light': themeLightColor }"
  >
    <router-view />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { meClinic } from './shared/meCache'

const route = useRoute()

const themeColor = computed(() => meClinic.value?.theme_color || '#e5e7eb')

const themeLightColor = computed(() => {
  const hex = (themeColor.value || '#e5e7eb').replace('#', '')
  const r = parseInt(hex.slice(0, 2), 16)
  const g = parseInt(hex.slice(2, 4), 16)
  const b = parseInt(hex.slice(4, 6), 16)
  // Mezclar con blanco (255, 255, 255) al 85% para un fondo muy claro
  const lr = Math.round(r * 0.15 + 255 * 0.85)
  const lg = Math.round(g * 0.15 + 255 * 0.85)
  const lb = Math.round(b * 0.15 + 255 * 0.85)
  return `rgb(${lr}, ${lg}, ${lb})`
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
</style>
