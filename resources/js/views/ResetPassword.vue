<script setup>
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthLayout from '../layouts/AuthLayout.vue'
import BaseInput from '../components/BaseInput.vue'
import BaseButton from '../components/BaseButton.vue'
import ErrorAlert from '../components/ErrorAlert.vue'
import axios from 'axios'

const route = useRoute()
const router = useRouter()

const token = ref(String(route.query.token || '').trim())
const email = ref(String(route.query.email || '').trim())
const password = ref('')
const passwordConfirmation = ref('')
const loading = ref(false)
const error = ref('')
const success = ref('')

const hasRequiredParams = computed(() => token.value !== '' && email.value !== '')

async function submit() {
  if (!hasRequiredParams.value) {
    error.value = 'El enlace de recuperacion es invalido o incompleto.'
    return
  }

  loading.value = true
  error.value = ''
  success.value = ''

  try {
    const res = await axios.post('/api/password/reset', {
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })

    success.value = res.data?.message || 'Contrasena actualizada correctamente.'

    setTimeout(() => {
      router.push(`/login?email=${encodeURIComponent(email.value)}`)
    }, 900)
  } catch (err) {
    if (err?.response?.status === 422) {
      const errs = err.response.data?.errors || {}
      const first = Object.values(errs)[0]
      error.value = Array.isArray(first)
        ? first[0]
        : (err.response.data?.message || 'No se pudo restablecer la contrasena.')
    } else {
      error.value = err?.response?.data?.message || 'No se pudo restablecer la contrasena.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout>
    <h2 class="text-2xl font-semibold">Restablecer contraseña</h2>
    <p class="text-sm muted mt-2">Define una nueva contraseña para tu cuenta.</p>

    <ErrorAlert
      v-if="!hasRequiredParams"
      class="mt-3"
      variant="warning"
      title="Enlace inválido"
      message="El enlace no incluye los datos necesarios. Solicita un nuevo correo de recuperación."
    />

    <form class="mt-4" @submit.prevent="submit">
      <BaseInput v-model="email" label="Email" type="email" autocomplete="email" />
      <BaseInput v-model="password" label="Nueva contraseña" type="password" autocomplete="new-password" />
      <BaseInput v-model="passwordConfirmation" label="Confirmar contraseña" type="password" autocomplete="new-password" />
      <BaseButton :type="'submit'">{{ loading ? 'Actualizando...' : 'Actualizar contraseña' }}</BaseButton>
    </form>

    <p v-if="success" class="ok-message mt-3">{{ success }}</p>

    <p class="footer text-sm mt-4">
      Volver al acceso
      <router-link to="/login" class="text-accent">Iniciar sesión</router-link>
    </p>

    <ErrorAlert v-if="error" class="mt-3" title="No se pudo restablecer" :message="error" />
  </AuthLayout>
</template>

<style scoped>
.footer { text-align: center }
.muted { color: #6b7280 }
.ok-message {
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  border: 1px solid #86efac;
  background: #ecfdf5;
  color: #14532d;
  font-size: 14px;
}
</style>
