<template>
  <div class="billing-wrapper">
    <div class="billing-card">
        <h1 class="title">Tu periodo de prueba ha finalizado</h1>

        <p class="subtitle">
          Para seguir usando la plataforma y no perder tus datos,
          necesitas activar tu suscripción.
        </p>

        <form @submit.prevent="startCheckout" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Plan</label>
            <div class="mt-1 p-4 border rounded flex items-center justify-between">
              <div>
                <div class="text-lg font-semibold">Profesional</div>
                <div class="text-sm text-gray-500">Gestión completa · 29€/mes</div>
              </div>
              <div class="text-right">
                <div class="text-xl font-bold">29€</div>
                <div class="text-xs text-gray-500">/ mes</div>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Método</label>
            <select v-model="method" class="mt-1 block w-full border rounded px-3 py-2">
              <option value="card">Tarjeta</option>
              <option value="transfer">Transferencia</option>
            </select>
          </div>

          <div class="flex items-center gap-2">
            <button type="submit" class="btn btn--solid" :disabled="loading">
              {{ loading ? 'Redirigiendo...' : 'Activar suscripción' }}
            </button>
            <button type="button" class="btn btn--ghost" :disabled="confirming" @click="confirmCheckoutReturn">
              {{ confirming ? 'Verificando...' : 'Ya pagué, verificar estado' }}
            </button>
          </div>

          <p v-if="info" class="text-green-700">{{ info }}</p>
          <p v-if="error" class="text-red-600">{{ error }}</p>
        </form>

        <p class="note">No se perderá ningún dato.</p>
      </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'
import { ensureMeLoaded } from '../shared/meCache'

const router = useRouter()
const route = useRoute()
const method = ref('card')
const error = ref(null)
const loading = ref(false)
const confirming = ref(false)
const info = ref('')

async function startCheckout() {
  error.value = null
  info.value = ''
  loading.value = true
  try {
    const res = await api.post('/billing/checkout', { method: method.value })
    const checkoutUrl = res.data?.checkout?.checkout_url
    if (!checkoutUrl) throw new Error('No se recibió URL de pago')
    window.location.href = checkoutUrl
  } catch (e) {
    error.value = e.response?.data?.message || e.message
  } finally {
    loading.value = false
  }
}

async function confirmCheckoutReturn() {
  error.value = null
  info.value = ''
  confirming.value = true

  try {
    const sessionId = String(route.query.session_id || '').trim()
    const res = await api.post('/billing/confirm', { session_id: sessionId || undefined })

    if (res.data?.status === 'active') {
      info.value = 'Pago confirmado. Activando suscripción...'
      await ensureMeLoaded({ force: true })
      await router.replace('/dashboard')
      return
    }

    error.value = res.data?.message || 'Stripe todavía no confirma el pago. Intenta nuevamente en unos segundos.'
  } catch (e) {
    error.value = e.response?.data?.message || e.message
  } finally {
    confirming.value = false
  }
}

onMounted(async () => {
  if (route.query.checkout === 'success' || route.query.session_id) {
    await confirmCheckoutReturn()
  }
})

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



.note {
  margin-top: 16px;
  font-size: 13px;
  color: #64748b;
}
</style>
