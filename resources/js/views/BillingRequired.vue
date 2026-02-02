<template>
  <div class="billing-wrapper">
    <div class="billing-card">
      <h1 class="title">Tu periodo de prueba ha finalizado</h1>

      <p class="subtitle">
        Para seguir usando la plataforma y no perder tus datos,
        necesitas activar tu suscripción.
      </p>

      <ul class="benefits">
        <li>Gestión de pacientes ilimitada</li>
        <li>Agenda y control de citas</li>
        <li>Acceso a facturación y pagos</li>
      </ul>

      <button class="btn-primary" @click="goToCheckout">Activar suscripción</button>

      <p class="note">No se perderá ningún dato.</p>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import api from '../services/api'

const router = useRouter()

async function goToCheckout() {
  try {
    // Temporal: usar endpoint fake para activar suscripción en desarrollo
    const res = await api.post('/subscribe/fake')
    // Si el backend devuelve éxito y actualiza la clínica, volvemos al dashboard
    if (res?.data) {
      // permitir que Dashboard recargue /me en su montaje
      router.push('/dashboard')
      return
    }
    console.log('Respuesta inesperada al activar suscripción', res)
  } catch (e) {
    console.error('Error activando suscripción (fake)', e)
  }
}
</script>

<style scoped>
.billing-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f8fafc;
  padding: 24px;
}

.billing-card {
  width: 100%;
  max-width: 460px;
  background: #ffffff;
  border-radius: 16px;
  padding: 32px;
  box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
  text-align: center;
}

.title {
  font-size: 24px;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 12px;
}

.subtitle {
  font-size: 15px;
  color: #475569;
  margin-bottom: 24px;
  line-height: 1.6;
}

.benefits {
  list-style: none;
  padding: 0;
  margin: 0 0 28px;
  text-align: left;
}

.benefits li {
  position: relative;
  padding-left: 28px;
  margin-bottom: 12px;
  font-size: 14px;
  color: #334155;
}

.benefits li::before {
  content: "✔";
  position: absolute;
  left: 0;
  color: var(--accent, #10b981);
  font-weight: 700;
}

.btn-primary {
  width: 100%;
  padding: 14px;
  border-radius: 12px;
  background: var(--black, #0f172a);
  color: #ffffff;
  font-size: 15px;
  font-weight: 700;
  border: none;
  cursor: pointer;
  box-shadow: 0 10px 28px rgba(2, 6, 23, 0.18);
  transition: transform 0.1s ease, box-shadow 0.15s ease;
}

.btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 14px 32px rgba(2, 6, 23, 0.22);
}

.note {
  margin-top: 16px;
  font-size: 13px;
  color: #64748b;
}
</style>
