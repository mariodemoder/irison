<template>
  <div class="min-h-screen flex bg-gray-50">
    <aside :class="['w-64 bg-white border-r p-4', { 'hidden': !open, 'block': open }, 'md:block']">
      <div class="mb-6">
        <img :src="logo" alt="Logo" class="w-40 h-40 object-contain" />
      </div>

      <nav class="space-y-1">
        <router-link :class="[{ 'menu-active': isActive('/dashboard') }, 'block px-3 py-2 rounded text-gray-800 hover:bg-gray-100']" to="/dashboard">Dashboard</router-link>
        <router-link :class="[{ 'menu-active': isActive('/patients') }, 'block px-3 py-2 rounded text-gray-800 hover:bg-gray-100']" to="/patients">Pacientes</router-link>
        <router-link :class="[{ 'menu-active': isActive('/appointments') }, 'block px-3 py-2 rounded text-gray-800 hover:bg-gray-100']" to="/appointments">Agenda</router-link>
        <router-link :class="[{ 'menu-active': isActive('/payments') }, 'block px-3 py-2 rounded text-gray-800 hover:bg-gray-100']" to="/payments">Pagos</router-link>
        <router-link :class="[{ 'menu-active': isActive('/links') }, 'block px-3 py-2 rounded text-blue-600 hover:bg-gray-100']" to="/links">Links activos</router-link>
      </nav>
    </aside>

    <div class="flex-1 flex flex-col">
      <header class="h-14 bg-white border-b flex items-center px-4 justify-between">
        <div class="flex items-center gap-4">
          <button class="md:hidden p-2 rounded hover:bg-gray-100" @click="open = !open">☰</button>
          <h2 class="text-lg font-semibold">Dashboard</h2>
        </div>

        <div class="flex items-center gap-4">
          <router-link to="/profile" class="text-sm text-gray-700 hover:underline">Mi cuenta</router-link>
        </div>
      </header>

      <main class="p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import logo from '../assets/fisiomeca.svg'

const open = ref(false)
const route = useRoute()

function isActive(base) {
  const p = route.path || ''
  return p === base || p.startsWith(base + '/')
}
</script>

<style scoped>
/* Estilos mínimos: el diseño depende de utilidades de Tailwind si está disponible. */
.menu-active {
  background: #eef2ff;
  color: #1f2937 !important;
  font-weight: 600;
}
</style>

