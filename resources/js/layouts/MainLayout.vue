<template>
  <div class="min-h-screen flex bg-gray-50">
    <aside :class="['w-64 bg-white border-r p-4', { 'hidden': !open, 'block': open }, 'md:block']">
      <div class="mb-6">
        <img :src="logo" alt="Logo" class="w-40 h-40 object-contain" />
      </div>

      <nav class="space-y-1">
        <router-link to="/dashboard" class="block px-3 py-2 rounded text-gray-800 hover:bg-gray-100">Dashboard</router-link>
        <router-link to="/patients" class="block px-3 py-2 rounded text-gray-800 hover:bg-gray-100">Pacientes</router-link>
        <router-link to="/appointments" class="block px-3 py-2 rounded text-gray-800 hover:bg-gray-100">Agenda</router-link>
        <router-link to="/payments" class="block px-3 py-2 rounded text-gray-800 hover:bg-gray-100">Pagos</router-link>
        <router-link to="/links" class="block px-3 py-2 rounded text-blue-600 hover:bg-gray-100">Links activos</router-link>
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
import logo from '../assets/fisiomeca.svg'

const open = ref(false)
</script>

<style scoped>
/* Estilos mínimos: el diseño depende de utilidades de Tailwind si está disponible. */
</style>
