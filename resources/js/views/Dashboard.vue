<script setup>
import { ref, onMounted } from 'vue'
import api from '../services/api'

const user = ref(null)
const clinic = ref(null)
const trial_active = ref(true)
const loading = ref(true)

async function subscribe() {
  try {
    const res = await api.post('/stripe/checkout')
    window.location.href = res.data.url
  } catch (e) {
    console.error('Error creando checkout', e)
  }
}

async function subscribeFake() {
  try {
    const res = await api.post('/subscribe/fake')
    // actualizar estado en UI
    clinic.value = res.data.clinic
    trial_active.value = !!res.data.trial_active
  } catch (e) {
    console.error('Error activando suscripción fake', e)
  }
}

onMounted(async () => {
  try {
    const res = await api.get('/me')
    user.value = res.data.user
    clinic.value = res.data.clinic
    trial_active.value = !!res.data.trial_active
  } catch (e) {
    console.error('Error cargando /me', e)
    // Si el backend responde 403 por trial expirado, marcar trial como inactivo
    if (e?.response?.status === 403) {
      trial_active.value = false
      user.value = null
      clinic.value = null
    }
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="loading">Cargando...</div>

  <div v-else>
    <h1>Dashboard</h1>

    <div v-if="!trial_active">
      <p class="alert">Tu trial ha expirado</p>
      <div style="display:flex;gap:8px;">
        <button @click="subscribe" class="btn">Suscribirse (Stripe)</button>
        <button @click="subscribeFake" class="btn">Activar plan (fake)</button>
      </div>
    </div>

    <div v-else>
      <p>Bienvenido {{ user?.name ?? '—' }}</p>
      <p>Clínica: {{ clinic?.name ?? '—' }}</p>
    </div>
  </div>
</template>
