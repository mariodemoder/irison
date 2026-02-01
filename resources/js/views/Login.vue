<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AuthLayout from '../layouts/AuthLayout.vue'
import BaseInput from '../components/BaseInput.vue'
import BaseButton from '../components/BaseButton.vue'
import axios from 'axios'

const router = useRouter()
const email = ref('mario@test.com')
const password = ref('password123')
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    const res = await axios.post('/api/login', { email: email.value, password: password.value })
    localStorage.setItem('token', res.data.token || res.data.access_token)
    router.push('/dashboard')
  } catch (err) {
    error.value = err.response?.data?.message || 'Error en login'
  } finally { loading.value = false }
}
</script>

<template>
  <AuthLayout>
    <div class="login-column">
      <h2 class="text-2xl font-semibold mb-2">¡Buenos días!</h2>

      <form @submit.prevent="submit">
        <BaseInput v-model="email" label="Email" autocomplete="email" />
        <BaseInput v-model="password" label="Contraseña" type="password" autocomplete="current-password" />

        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;margin-bottom:6px;">
          <a class="link text-sm text-gray-600">¿Has olvidado la contraseña?</a>
        </div>

        <BaseButton :type="'submit'">{{ loading ? 'Entrando...' : 'Iniciar sesión' }}</BaseButton>
      </form>

      <p class="footer text-sm mt-4">
        ¿Aún no tienes cuenta?
        <router-link to="/register" class="text-accent">Registrarse</router-link>
      </p>

      <p v-if="error" class="mt-3 text-sm error-text">{{ error }}</p>
    </div>
  </AuthLayout>
</template>

<style scoped>
.footer { text-align: center }
</style>
