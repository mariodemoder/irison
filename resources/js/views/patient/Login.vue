<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-header">
        <div v-if="clinicBranding" class="auth-brand">
          <img v-if="clinicBranding.logo_url" :src="clinicBranding.logo_url" :alt="clinicBranding.name" class="auth-logo" />
          <div class="auth-clinic-name">{{ clinicBranding.name }}</div>
        </div>
        <h1>Portal del Paciente</h1>
        <p>Inicia sesión para acceder a tu información</p>
      </div>

      <form @submit.prevent="handleLogin" class="auth-form">
        <div v-if="error" class="auth-error">
          {{ error }}
        </div>

        <div class="form-group">
          <label for="email">Email</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            required
            placeholder="tu@email.com"
            :disabled="loading"
          />
        </div>

        <div class="form-group">
          <label for="password">Contraseña</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            required
            placeholder="Tu contraseña"
            :disabled="loading"
          />
        </div>

        <button type="submit" class="auth-btn" :disabled="loading">
          {{ loading ? 'Iniciando sesión...' : 'Iniciar sesión' }}
        </button>

        <div class="auth-links">
          <router-link :to="forgotLink">¿Olvidaste tu contraseña?</router-link>
        </div>
      </form>
    </div>
    <footer class="auth-footer">
      © {{ new Date().getFullYear() }} Irison. All rights reserved.
    </footer>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePatientAuth } from '../../patient/composables/usePatientAuth'

const route = useRoute()
const router = useRouter()
const { login, clinicBranding, fetchClinicBranding } = usePatientAuth()

const clinic = computed(() => route.query.clinic || '')
const forgotLink = computed(() => ({
  path: '/patient/forgot-password',
  query: clinic.value ? { clinic: clinic.value } : {},
}))

const form = ref({ email: '', password: '' })
const loading = ref(false)
const error = ref('')

onMounted(() => {
  fetchClinicBranding(clinic.value)
})

async function handleLogin() {
  loading.value = true
  error.value = ''

  try {
    await login(form.value.email, form.value.password, clinic.value)
    router.push('/patient/dashboard')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Credenciales incorrectas.'
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: linear-gradient(135deg, #f0f4ff 0%, #e8f0fe 100%);
}

.auth-card {
  width: 100%;
  max-width: 400px;
  background: #ffffff;
  border-radius: 16px;
  padding: 32px;
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
}

.auth-header {
  text-align: center;
  margin-bottom: 24px;
}

.auth-logo {
  height: 40px;
  margin-bottom: 16px;
}

.auth-brand {
  margin-bottom: 12px;
}

.auth-clinic-name {
  font-size: 22px;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 4px;
}

.auth-header h1 {
  font-size: 20px;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 4px;
}

.auth-header p {
  font-size: 14px;
  color: #64748b;
  margin: 0;
}

.auth-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.auth-error {
  padding: 12px;
  border-radius: 8px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  font-size: 14px;
  text-align: center;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}

.form-group input {
  padding: 10px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 15px;
  transition: border-color 0.15s;
}

.form-group input:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.auth-btn {
  padding: 12px;
  border: none;
  border-radius: 8px;
  background: #6366f1;
  color: #ffffff;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s;
}

.auth-btn:hover:not(:disabled) {
  background: #4f46e5;
}

.auth-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.auth-links {
  text-align: center;
}

.auth-links a {
  color: #6366f1;
  font-size: 14px;
  text-decoration: none;
}

.auth-links a:hover {
  text-decoration: underline;
}

.auth-footer {
  margin-top: 24px;
  text-align: center;
  font-size: 12px;
  color: #94a3b8;
}
</style>
