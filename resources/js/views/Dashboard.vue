<script setup>
import { ref, onMounted } from 'vue'
import api from '../services/api'

const user = ref(null)
const clinic = ref(null)
const status = ref('blocked')
const trial_ends_at = ref(null)
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
    status.value = res.data.status_clinic || status.value
    trial_ends_at.value = res.data.trial_ends_at || trial_ends_at.value
  } catch (e) {
    console.error('Error activando suscripción fake', e)
  }
}

onMounted(async () => {
    try {
    const res = await api.get('/me')
    user.value = res.data.user
    clinic.value = res.data.clinic
    status.value = res.data.status || status.value
    trial_ends_at.value = res.data.trial_ends_at || null
  } catch (e) {
    console.error('Error cargando /me', e)
    // si ocurre 403 por otra razón, mantenemos estado por defecto
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="loading">Cargando...</div>

  <div v-else>
    <h1>Dashboard</h1>

    <div v-if="status === 'trial'">
      <p>
        Estás en periodo de prueba hasta
        <strong>{{ trial_ends_at }}</strong>
      </p>
    </div>

    <div v-else-if="status === 'blocked'">
      <p class="alert">Tu periodo de prueba ha terminado.</p>
      <div style="display:flex;gap:8px;">
        <button @click="subscribe" class="btn">Activar plan (Stripe)</button>
        <button @click="subscribeFake" class="btn">Activar plan (fake)</button>
      </div>
    </div>

    <div v-else-if="status === 'active'">
      <p class="ok">Plan activo ✅</p>
      <p>Bienvenido {{ user?.name ?? '—' }}</p>
      <p>Clínica: {{ clinic?.name ?? '—' }}</p>
    </div>
  </div>
</template>
