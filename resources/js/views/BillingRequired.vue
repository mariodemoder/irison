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
          <ErrorAlert v-if="error" class="mt-1 text-left" title="No se pudo activar la suscripción" :message="error" />

          <div v-if="showLocalFallbackAction" class="fallback-actions">
            <button type="button" class="btn btn-primary" :disabled="activatingLocal" @click="activateLocalFallback">
              {{ activatingLocal ? 'Activando...' : 'Activar en modo local' }}
            </button>
          </div>
        </form>

        <p class="note">No se perderá ningún dato.</p>
      </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Swal from 'sweetalert2'
import ErrorAlert from '../components/ErrorAlert.vue'
import api from '../services/api'
import { ensureMeLoaded } from '../shared/meCache'

const router = useRouter()
const route = useRoute()
const method = ref('card')
const error = ref(null)
const loading = ref(false)
const confirming = ref(false)
const info = ref('')
const showLocalFallbackAction = ref(false)
const activatingLocal = ref(false)

async function showSubscriberWelcomePopup() {
  await Swal.fire({
    html: `
      <div style="display:flex;flex-direction:column;align-items:center;gap:10px;">
        <svg width="42" height="42" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <circle cx="12" cy="12" r="9" stroke="#111827" stroke-width="1.6" />
          <circle cx="9" cy="10" r="1" fill="#111827" />
          <circle cx="15" cy="10" r="1" fill="#111827" />
          <path d="M8 14c1 1.3 2.4 2 4 2s3-.7 4-2" stroke="#111827" stroke-width="1.6" stroke-linecap="round" />
        </svg>
        <div style="font-weight:700;color:#0f172a;">Enhorabuena</div>
        <div style="color:#334155;">Ya eres un suscriptor activo en Irisis</div>
      </div>
    `,
    showConfirmButton: false,
    timer: 1900,
    timerProgressBar: true,
    customClass: { popup: 'swal-popup-card' },
  })
}

async function startCheckout() {
  error.value = null
  info.value = ''
  showLocalFallbackAction.value = false
  loading.value = true
  try {
    const res = await api.post('/billing/checkout', { method: method.value })
    const checkoutUrl = res.data?.checkout?.checkout_url
    if (!checkoutUrl) throw new Error('No se recibió URL de pago')
    window.location.href = checkoutUrl
  } catch (e) {
    const code = String(e?.response?.data?.code || '')
    const message = e.response?.data?.message || e.message
    error.value = message

    if (import.meta.env.DEV && (code === 'STRIPE_UNREACHABLE' || String(message).toLowerCase().includes('stripe'))) {
      showLocalFallbackAction.value = true
      info.value = 'Stripe no está disponible en este entorno. Puedes activar en modo local para continuar.'
    }
  } finally {
    loading.value = false
  }
}

async function activateLocalFallback() {
  error.value = null
  activatingLocal.value = true

  try {
    await api.post('/subscribe/fake', { amount: 2900 })
    info.value = 'Suscripción activada en modo local.'
    await ensureMeLoaded({ force: true })
    await showSubscriberWelcomePopup()
    await router.replace('/dashboard')
  } catch (e) {
    error.value = e.response?.data?.message || e.message || 'No se pudo activar en modo local.'
  } finally {
    activatingLocal.value = false
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
      await showSubscriberWelcomePopup()
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

.fallback-actions {
  margin-top: 10px;
}
</style>
