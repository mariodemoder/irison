<template>
  <div class="impersonate-entry">
    <div class="impersonate-card">
      <h1>Accediendo a la clínica...</h1>
      <p>{{ message }}</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const message = ref('Validando sesión temporal.')

onMounted(async () => {
  const rawToken = route.query.token
  const token = typeof rawToken === 'string' ? rawToken.trim() : ''

  if (!token) {
    message.value = 'No se recibió token de impersonación. Redirigiendo a login.'
    await router.replace('/login')
    return
  }

  localStorage.setItem('token', token)
  message.value = 'Sesión de impersonación iniciada. Redirigiendo al panel.'

  await router.replace('/dashboard')
})
</script>

<style scoped>
.impersonate-entry {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  padding: 24px;
}

.impersonate-card {
  width: min(560px, 100%);
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 28px;
  box-shadow: 0 10px 35px rgba(2, 6, 23, 0.08);
}

h1 {
  margin: 0 0 8px;
  font-size: 22px;
  color: #0f172a;
}

p {
  margin: 0;
  color: #334155;
}
</style>
