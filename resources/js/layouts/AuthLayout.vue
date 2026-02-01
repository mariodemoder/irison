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
.accent { color: var(--primary); }
.brand img { border-radius: 12px; box-shadow: none; width: 96px; height: 96px }
.brand h1 { margin: 0 }
.brand p { margin: 0; color: var(--text-muted) }

.auth-container { display: grid; grid-template-columns: 1fr 1fr; min-height: 100vh; }
.auth-left { padding: 4rem; display: flex; align-items: center; justify-content: center }
.brand { max-width: 520px }
.brand h1 { line-height: 1.05 }

.auth-right { padding: 4rem; display: flex; align-items: center; justify-content: center }
.auth-card { width: 100%; max-width: 520px; background: var(--bg-card); padding: 28px; border-radius: 16px; box-shadow: 0 10px 30px rgba(2,6,23,0.06); }

@media (max-width: 768px) {
  .auth-container { grid-template-columns: 1fr; }
  .auth-left, .auth-right { padding: 2rem }
  .auth-card { border-radius: 12px }
}
</style>
