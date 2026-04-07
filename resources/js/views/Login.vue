<script setup>
import { computed, ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import AuthLayout from '../layouts/AuthLayout.vue'
import BaseInput from '../components/BaseInput.vue'
import BaseButton from '../components/BaseButton.vue'
import axios from 'axios'

const router = useRouter()
const route = useRoute()
const email = ref('mario@test.com')
const password = ref('Pasword123')
const loading = ref(false)
const error = ref('')

const activation = String(route.query.activation || '')

const activationCard = computed(() => {
  if (activation === 'pending') {
    return {
      show: true,
      kind: 'pending',
      badge: 'Revisa tu correo',
      title: 'Tu cuenta casi está lista',
      message: 'Te enviamos un enlace de activación. Ábrelo para comenzar tu prueba con DueleAhi.',
    }
  }

  if (activation === 'success') {
    return {
      show: true,
      kind: 'success',
      badge: 'Activación completada',
      title: 'Bienvenida, tu cuenta está activa',
      message: 'Excelente, ya puedes iniciar sesión y empezar tu periodo de prueba.',
    }
  }

  if (activation === 'already') {
    return {
      show: true,
      kind: 'already',
      badge: 'Todo en orden',
      title: 'Qué alegría verte de nuevo',
      message: 'Tu cuenta ya estaba activada. Inicia sesión y continúa donde lo dejaste.',
    }
  }

  if (activation === 'invalid') {
    return {
      show: true,
      kind: 'invalid',
      badge: 'Enlace no válido',
      title: 'Ese enlace ya no funciona',
      message: 'No te preocupes, solicita un nuevo correo de activación y lo resolvemos en segundos.',
    }
  }

  return { show: false }
})

async function submit() {
  loading.value = true
  error.value = ''
  try {
    // Sanear contraseña: eliminar comillas envolventes y espacios accidentales
    const sendPassword = (password.value || '').toString().trim().replace(/^"|"$/g, '')
    const res = await axios.post('/api/login', { email: email.value, password: sendPassword })
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

      <div
        v-if="activationCard.show"
        class="activation-card"
        :class="`activation-${activationCard.kind}`"
      >
        <span class="activation-badge">{{ activationCard.badge }}</span>
        <h3 class="activation-title">{{ activationCard.title }}</h3>
        <p class="activation-text">{{ activationCard.message }}</p>
      </div>

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

.activation-card {
  margin: 10px 0 16px 0;
  padding: 14px 14px 12px 14px;
  border-radius: 14px;
  border: 1px solid transparent;
}

.activation-badge {
  display: inline-block;
  font-size: 12px;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 999px;
  margin-bottom: 8px;
}

.activation-title {
  margin: 0 0 4px 0;
  font-size: 18px;
  line-height: 1.2;
}

.activation-text {
  margin: 0;
  font-size: 14px;
  line-height: 1.45;
}

.activation-pending {
  background: #eff6ff;
  border-color: #bfdbfe;
  color: #1e3a8a;
}

.activation-pending .activation-badge {
  background: #dbeafe;
  color: #1d4ed8;
}

.activation-success {
  background: #ecfdf5;
  border-color: #86efac;
  color: #14532d;
}

.activation-success .activation-badge {
  background: #dcfce7;
  color: #15803d;
}

.activation-already {
  background: #fff7ed;
  border-color: #fdba74;
  color: #7c2d12;
}

.activation-already .activation-badge {
  background: #ffedd5;
  color: #c2410c;
}

.activation-invalid {
  background: #fef2f2;
  border-color: #fca5a5;
  color: #991b1b;
}

.activation-invalid .activation-badge {
  background: #fee2e2;
  color: #dc2626;
}
</style>
