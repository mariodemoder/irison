<script setup>
import { ref } from 'vue'
import api from '../services/api'
import { useRouter } from 'vue-router'

const router = useRouter()

// Valores por defecto para testing rápido — eliminar en producción
const email = ref('mario@test.com')
const password = ref('password123')
const loading = ref(false)
const error = ref(null)

const login = async () => {
  error.value = null
  loading.value = true

  try {
    const res = await api.post('/login', {
      email: email.value,
      password: password.value,
    })

    localStorage.setItem('token', res.data.access_token)
    router.push('/dashboard')
  } catch (e) {
    error.value = 'Email o contraseña incorrectos'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="page">
    <div class="card">
      <h1>Acceso a la clínica</h1>
      <p class="subtitle">Gestiona pacientes, citas y facturación</p>

      <form @submit.prevent="login">
        <input
          v-model="email"
          type="email"
          placeholder="Email"
          required
        />

        <input
          v-model="password"
          type="password"
          placeholder="Contraseña"
          required
        />

        <button :disabled="loading">
          {{ loading ? 'Entrando...' : 'Entrar' }}
        </button>
      </form>

      <p v-if="error" class="error">{{ error }}</p>
    </div>
  </div>
</template>

<style scoped>
.page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #3b82f6, #6366f1);
  font-family: system-ui, sans-serif;
}

.card {
  background: #fff;
  padding: 2.5rem;
  border-radius: 12px;
  width: 100%;
  max-width: 380px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

h1 {
  margin-bottom: 0.25rem;
  font-size: 1.5rem;
}

.subtitle {
  margin-bottom: 1.5rem;
  color: #6b7280;
  font-size: 0.95rem;
}

input {
  width: 100%;
  padding: 0.75rem;
  margin-bottom: 1rem;
  border-radius: 8px;
  border: 1px solid #d1d5db;
  font-size: 0.95rem;
}

input:focus {
  outline: none;
  border-color: #6366f1;
}

button {
  width: 100%;
  padding: 0.75rem;
  border: none;
  border-radius: 8px;
  background: #6366f1;
  color: white;
  font-size: 1rem;
  cursor: pointer;
}

button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.error {
  margin-top: 1rem;
  color: #dc2626;
  font-size: 0.9rem;
  text-align: center;
}
</style>
