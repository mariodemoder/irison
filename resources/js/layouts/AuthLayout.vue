<template>
  <div class="auth-container">
    <div class="auth-left">
        <div class="brand">
          <img :src="logo" alt="Logo" class="logo-image" />
        <h1 class="mt-6 text-3xl font-semibold leading-tight">
          Gestiona tu clínica 
          <span class="accent">sin dolor</span>
        </h1>
        <p class="mt-4 text-gray-600">Todo lo que necesitas para gestionar clientes, citas y pagos.</p>
      </div>
    </div>

    <div class="auth-right">
      <div class="auth-card">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup>
import logo from '../assets/fisiomeca.svg'
</script>

<style scoped>
.accent { color: var(--accent); }
.brand img { border-radius: 12px; box-shadow: 0 12px 30px rgba(15,23,42,0.06); width: 96px; height: 96px }
.brand h1 { margin: 0 }
.brand p { margin: 0 }
</style>
