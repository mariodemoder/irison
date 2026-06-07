<script setup>
import { ref } from 'vue'
import AuthLayout from '../layouts/AuthLayout.vue'
import BaseInput from '../components/BaseInput.vue'
import BaseButton from '../components/BaseButton.vue'
import ErrorAlert from '../components/ErrorAlert.vue'
import axios from 'axios'

const email = ref('')
const loading = ref(false)
const error = ref('')
const success = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  success.value = ''

  try {
    const res = await axios.post('/api/password/forgot', {
      email: String(email.value || '').trim(),
    })

    success.value = res.data?.message || 'Si el email existe, te hemos enviado instrucciones para recuperar tu contrasena.'
  } catch (err) {
    if (err?.response?.status === 429 && err?.response?.data?.code === 'PASSWORD_RESET_LIMIT_REACHED') {
      error.value = err.response.data?.message || 'Pongase en contacto con el equipo tecnico de Irison.'
      return
    }

    if (err?.response?.status === 422) {
      const errs = err.response.data?.errors || {}
      const first = Object.values(errs)[0]
      error.value = Array.isArray(first) ? first[0] : 'Revisa el email e intentalo de nuevo.'
    } else {
      error.value = err?.response?.data?.message || 'No se pudo enviar el enlace de recuperacion.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthLayout>
    <h2 class="text-2xl font-semibold">Recuperar contraseña</h2>
    <p class="text-sm muted mt-2">
      Introduce tu email y te enviaremos un enlace para restablecer tu contraseña.
    </p>

    <form class="mt-4" @submit.prevent="submit">
      <BaseInput v-model="email" label="Email" type="email" autocomplete="email" />
      <BaseButton :type="'submit'">{{ loading ? 'Enviando...' : 'Enviar enlace' }}</BaseButton>
    </form>

    <p v-if="success" class="ok-message mt-3">{{ success }}</p>

    <p class="footer text-sm mt-4">
      ¿Recordaste tu contraseña?
      <router-link to="/login" class="text-accent">Iniciar sesión</router-link>
    </p>

    <ErrorAlert v-if="error" class="mt-3" title="No se pudo completar la solicitud" :message="error" />
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
