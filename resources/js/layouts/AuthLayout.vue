<template>
  <div class="auth-container">
    <div class="auth-left">
        <div class="brand">
          <img :src="logo" alt="Logo" class="logo-image" />
        <h1 class="mt-6 text-3xl font-semibold leading-tight">
          Gestiona tu tiempo.<br />
          Diseña tu futuro.<br />
          Vive simple
        </h1>
        <p class="mt-4 text-gray-600">Todo lo que necesitas para gestionar pacientes, citas, ventas y pagos.</p>
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
import logo from '../assets/logoIni.svg'
</script>

<style scoped>
.accent { color: var(--primary); }
.brand { max-width: 480px; display: flex; flex-direction: column; align-items: center; text-align: center }
.brand img { border-radius: 16px; box-shadow:2px; width: 200px; height: 200px }
.brand h1 { margin: 0; text-align: center }
.brand p { margin: 0; color: var(--text-muted) }

.auth-container { display: grid; grid-template-columns: 1fr 1fr; gap: clamp(24px, 6vw, 72px); min-height: 100vh; background: linear-gradient(135deg, #eff6ff 0%, #bfdbfe 55%, #2563EB 100%); }
.auth-left { padding: 2rem 0; display: flex; align-items: center; justify-content: flex-end }
.brand h1 { line-height: 1.05 }

.auth-right { padding: 2rem 0; display: flex; align-items: center; justify-content: flex-start }
.auth-card { width: 100%; max-width: 480px; background: rgba(255,255,255,0.92); padding: 28px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.8); box-shadow: 0 24px 60px rgba(30,64,175,0.16); backdrop-filter: blur(12px); }

@media (max-width: 768px) {
  .auth-container { grid-template-columns: 1fr; gap: 0; }
  .auth-left, .auth-right { padding: 2rem; justify-content: center }
  .auth-card { border-radius: 12px }
}
</style>
