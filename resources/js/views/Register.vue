<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AuthLayout from '../layouts/AuthLayout.vue'
import BaseInput from '../components/BaseInput.vue'
import BaseButton from '../components/BaseButton.vue'
import axios from 'axios'

const router = useRouter()
const name = ref('')
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  try {
    await axios.post('/api/register', { name: name.value, email: email.value, password: password.value })
    router.push('/login')
  } catch (err) {
    error.value = err.response?.data?.message || 'Error al crear cuenta'
  } finally { loading.value = false }
}
</script>

<template>
  <AuthLayout>
    <h2 class="text-2xl font-semibold">Crear cuenta</h2>

    <form @submit.prevent="submit">
      <BaseInput v-model="name" label="Nombre" />
      <BaseInput v-model="email" label="Email" autocomplete="email" />
      <BaseInput v-model="password" label="Contraseña" type="password" autocomplete="new-password" />

      <BaseButton :type="'submit'">{{ loading ? 'Creando...' : 'Crear cuenta' }}</BaseButton>
    </form>

    <p class="footer text-sm mt-4">
      ¿Ya tienes cuenta?
      <router-link to="/login" class="text-accent">Iniciar sesión</router-link>
    </p>

    <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>
  </AuthLayout>
</template>
