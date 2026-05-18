<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AuthLayout from '../layouts/AuthLayout.vue'
import BaseInput from '../components/BaseInput.vue'
import BaseButton from '../components/BaseButton.vue'
import ErrorAlert from '../components/ErrorAlert.vue'
import axios from 'axios'

const router = useRouter()
const name = ref('')
const clinic_name = ref('')
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const success = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  success.value = ''
  try {
    // Sanear contraseña similar al login: evitar comillas envolventes accidentales
    const sendPassword = (password.value || '').toString().trim().replace(/^"|"$/g, '')
    await axios.post('/api/register', { name: name.value, clinic_name: clinic_name.value, email: email.value, password: sendPassword })
    success.value = 'Cuenta creada. Revisa tu correo y haz clic en el enlace de activación para iniciar tu trial.'
    router.push('/login?activation=pending')
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
      <BaseInput v-model="clinic_name" label="Nombre de la clínica" />
      <BaseInput v-model="email" label="Email" autocomplete="email" />
      <BaseInput v-model="password" label="Contraseña" type="password" autocomplete="new-password" />

      <BaseButton :type="'submit'">{{ loading ? 'Creando...' : 'Crear cuenta' }}</BaseButton>
    </form>

    <p class="footer text-sm mt-4">
      ¿Ya tienes cuenta?
      <router-link to="/login" class="text-accent">Iniciar sesión</router-link>
    </p>

    <ErrorAlert v-if="error" class="mt-3" title="No se pudo crear la cuenta" :message="error" />
    <p v-if="success" class="mt-3 text-sm success-text">{{ success }}</p>
  </AuthLayout>
</template>
