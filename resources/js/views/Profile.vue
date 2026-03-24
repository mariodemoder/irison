<template>
  <MainLayout>
    <div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
        <h1>Mi cuenta</h1>
        <div class="sub-banner">
          <div class="meta">
            <div style="font-weight:600">{{ user?.name ?? '—' }}</div>
            <div class="small">{{ clinic?.name ?? '—' }}</div>
          </div>

          <div style="display:flex;align-items:center;gap:8px">
            <span :class="['status-dot', subscriptionState.color]"></span>
            <div style="font-size:13px">{{ subscriptionState.label }}</div>
          </div>

          <div style="margin-left:12px">
            <button class="btn btn-sm" @click.prevent="logoutAction">Cerrar sesión</button>
          </div>
        </div>
      </div>

      <AppLoading v-if="loading" message="Cargando perfil..." />

      <div v-else>
        <div style="max-width:760px">
          <div class="tabs">
            <button :class="['tab', { active: activeTab==='datos' }]" @click="activeTab='datos'">Datos</button>
            <button :class="['tab', { active: activeTab==='contadores' }]" @click="activeTab='contadores'">Contadores</button>
            <button :class="['tab', { active: activeTab==='seguridad' }]" @click="activeTab='seguridad'">Seguridad</button>
            <button :class="['tab', { active: activeTab==='subscripcion' }]" @click="activeTab='subscripcion'">Subscripción</button>
          </div>

          <div class="profile-shell">
            <div class="card-stage">
              <div class="tab-panel tab-card" v-show="activeTab==='datos'">
                <form @submit.prevent="save" style="display:grid;gap:12px;">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                  <label class="label">Nombre</label>
                  <input class="input" v-model="form.name" />
                </div>
                <div>
                  <label class="label">Email</label>
                  <input class="input" v-model="form.email" />
                </div>
              </div>

              <div>
                <label class="label">Nombre clínica</label>
                <input class="input" v-model="form.clinic_name" />
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                  <label class="label">NIF</label>
                  <input class="input" v-model="form.clinic_nif" />
                </div>
                <div>
                  <label class="label">Código postal</label>
                  <input class="input" v-model="form.clinic_zip" />
                </div>
              </div>

              <div>
                <label class="label">Dirección</label>
                <input class="input" v-model="form.clinic_address" />
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                <div>
                  <label class="label">Localidad</label>
                  <input class="input" v-model="form.clinic_locality" />
                </div>
                <div>
                  <label class="label">Provincia</label>
                  <input class="input" v-model="form.clinic_province" />
                </div>
                <div>
                  <label class="label">País</label>
                  <input class="input" v-model="form.clinic_country" />
                </div>
              </div>

                  <div v-if="status==='blocked'" class="panel-note">
                    Tu clínica no tiene suscripción activa. Puedes activarla desde la pestaña de Subscripción.
                  </div>
                </form>
              </div>

              <div class="tab-panel tab-card" v-show="activeTab==='seguridad'">
                <h2>Cambiar contraseña</h2>
                <div v-if="pwMessage" class="field-error">{{ pwMessage }}</div>
                <form @submit.prevent="changePassword" style="display:grid;gap:12px">
                  <div>
                    <label class="label">Contraseña actual</label>
                    <input class="input" type="password" v-model="pw.current_password" />
                  </div>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div>
                      <label class="label">Nueva contraseña</label>
                      <input class="input" type="password" v-model="pw.password" />
                    </div>
                    <div>
                      <label class="label">Confirmar contraseña</label>
                      <input class="input" type="password" v-model="pw.password_confirmation" />
                    </div>
                  </div>
                </form>
              </div>

              <div class="tab-panel tab-card" v-show="activeTab==='subscripcion'">
                <h2>Subscripción</h2>
                <div style="display:flex;align-items:center;gap:12px;margin-top:8px">
                  <span :class="['status-dot', subscriptionState.color]"></span>
                  <div>{{ subscriptionState.label }}</div>
                </div>
                <div style="margin-top:12px">
                  <div v-if="status==='trial'">
                    <div>Quedan <strong>{{ daysLeft ?? '—' }}</strong> días de demo.</div>
                  </div>
                  <div v-else-if="status==='active'">
                    <div>Tu suscripción está activa.</div>

                    <div class="subscription-history" style="margin-top:14px">
                      <div class="subscription-history-title">Pagos realizados</div>
                      <div v-if="subscriptionPayments.length === 0" class="subscription-history-empty">
                        No hay pagos registrados.
                      </div>
                      <div v-else class="subscription-history-list">
                        <div class="subscription-history-head">
                          <div>Fecha</div>
                          <div>Número</div>
                          <div>Importe</div>
                        </div>
                        <div
                          v-for="payment in subscriptionPayments"
                          :key="payment.id"
                          class="subscription-history-row"
                        >
                          <div>{{ formatDateTime(payment.created_at) }}</div>
                          <div>{{ payment.counter || '—' }}</div>
                          <div>{{ formatBillingAmount(payment.amount, payment.currency) }}</div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div v-else>
                    <div>No tienes suscripción activa.</div>
                  </div>

                  <div class="subscription-actions">
                    <button v-if="status !== 'active'" class="btn btn-primary" @click.prevent="beginPaidPlanFake">Comenzar plan pago</button>
                    <button v-if="status==='blocked'" class="btn" @click.prevent="subscribe">Activar plan (Stripe)</button>
                  </div>
                </div>
              </div>

              <div class="tab-panel tab-card" v-show="activeTab==='contadores'">
                <h2>Contadores</h2>
                <div style="margin-top:8px;color:#6b7280;font-size:13px">
                  Formato final: <strong>PREFIJO-000001</strong> (prefijo de 1 a 4 caracteres)
                </div>

                <div style="margin-top:12px;display:grid;gap:10px">
                  <div
                    v-for="row in counters"
                    :key="row.table_type"
                    class="counter-grid"
                  >
                    <div>
                      <label class="label">Tipo</label>
                      <input class="input" :value="counterTypeLabels[row.table_type] || row.table_type" disabled />
                    </div>
                    <div>
                      <label class="label">Prefijo</label>
                      <input class="input" maxlength="4" v-model="row.prefix" />
                    </div>
                    <div>
                      <label class="label">Último número</label>
                      <input class="input" type="number" min="0" v-model.number="row.last_number" />
                    </div>
                    <div>
                      <label class="label">Siguiente</label>
                      <input class="input" :value="previewCounter(row)" disabled />
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="action-plane">
              <div v-if="activeTab==='datos' || activeTab==='contadores'" class="action-row">
                <button class="btn btn-sm" type="button" :disabled="saving" @click.prevent="save">Guardar</button>
              </div>

              <div v-else-if="activeTab==='seguridad'" class="action-row">
                <button class="btn btn-sm" type="button" :disabled="pwSaving" @click.prevent="changePassword">Cambiar contraseña</button>
                <button class="btn btn-sm" type="button" @click.prevent="pwReset">Limpiar</button>
              </div>

              <div v-else class="action-row action-row-empty"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import MainLayout from '../layouts/MainLayout.vue'
import AppLoading from '../components/AppLoading.vue'
import api from '../services/api'
import { useToast } from 'vue-toastification'
import logout from '../utils/logout'

const router = useRouter()
const toast = useToast()

const user = ref(null)
const clinic = ref(null)
const status = ref('blocked')
const trial_ends_at = ref(null)
const loading = ref(true)
const saving = ref(false)
const subscriptionPayments = ref([])

const form = ref({
  name: '',
  email: '',
  clinic_name: '',
  clinic_nif: '',
  clinic_address: '',
  clinic_locality: '',
  clinic_province: '',
  clinic_country: '',
  clinic_zip: '',
})

const counterTypeLabels = {
  documents: 'Facturación',
  payout: 'Abonos',
  bonuses: 'Bonos',
  payments: 'Pagos',
}

function defaultCounters() {
  return [
    { table_type: 'documents', prefix: 'FR', last_number: 0 },
    { table_type: 'payout', prefix: 'AB', last_number: 0 },
    { table_type: 'bonuses', prefix: 'B0', last_number: 0 },
    { table_type: 'payments', prefix: 'PA', last_number: 0 },
  ]
}

const counters = ref(defaultCounters())

// pestañas: 'datos' | 'seguridad' | 'subscripcion' | 'contadores'
const activeTab = ref('datos')

// password change
const pw = ref({ current_password: '', password: '', password_confirmation: '' })
const pwSaving = ref(false)
const pwMessage = ref('')

const daysLeft = computed(() => {
  if (!trial_ends_at.value) return null
  const end = new Date(trial_ends_at.value)
  const now = new Date()
  const diff = end.getTime() - now.getTime()
  return Math.ceil(diff / (1000 * 60 * 60 * 24))
})

const subscriptionState = computed(() => {
  if (status.value === 'active') return { color: 'green', label: 'Suscripción activa' }
  if (status.value === 'trial') return { color: 'yellow', label: `Prueba — quedan ${daysLeft.value ?? '—'} días` }
  return { color: 'red', label: 'Sin suscripción' }
})

// indica si el trial está vencido
const trialExpired = computed(() => {
  return status.value === 'trial' && daysLeft.value !== null && daysLeft.value <= 0
})

onMounted(async () => {
  await load()
})

async function load() {
  loading.value = true
  try {
    const res = await api.get('/me')
    user.value = res.data.user
    clinic.value = res.data.clinic
    status.value = res.data.status || status.value
    trial_ends_at.value = res.data.trial_ends_at || null
    subscriptionPayments.value = Array.isArray(res.data.subscription_payments) ? res.data.subscription_payments : []

    form.value.name = user.value?.name ?? ''
    form.value.email = user.value?.email ?? ''
    form.value.clinic_name = clinic.value?.name ?? ''
    form.value.clinic_nif = clinic.value?.nif ?? ''
    form.value.clinic_address = clinic.value?.address ?? ''
    form.value.clinic_locality = clinic.value?.locality ?? ''
    form.value.clinic_province = clinic.value?.province ?? ''
    form.value.clinic_country = clinic.value?.country ?? ''
    form.value.clinic_zip = clinic.value?.zip ?? ''

    const incomingCounters = Array.isArray(res.data.counters) ? res.data.counters : []
    if (incomingCounters.length > 0) {
      counters.value = defaultCounters().map((base) => {
        const found = incomingCounters.find((item) => item.table_type === base.table_type)
        return {
          table_type: base.table_type,
          prefix: (found?.prefix ?? base.prefix ?? '').toString().toUpperCase(),
          last_number: Number.isFinite(Number(found?.last_number)) ? Math.max(Number(found?.last_number), 0) : 0,
        }
      })
    } else {
      counters.value = defaultCounters()
    }
  } catch (e) {
    console.error('Error cargando /me', e)
    toast.error('Error cargando datos de usuario')
  } finally {
    loading.value = false
  }
}

function logoutAction() {
  logout(router)
}

async function save() {
  saving.value = true
  try {
    const payload = {
      name: form.value.name,
      email: form.value.email,
      clinic: {
        name: form.value.clinic_name,
        nif: form.value.clinic_nif,
        address: form.value.clinic_address,
        locality: form.value.clinic_locality,
        province: form.value.clinic_province,
        country: form.value.clinic_country,
        zip: form.value.clinic_zip,
      },
      counters: counters.value.map((item) => ({
        table_type: item.table_type,
        prefix: (item.prefix ?? '').toString().trim().toUpperCase(),
        last_number: Number.isFinite(Number(item.last_number)) ? Math.max(Number(item.last_number), 0) : 0,
      }))
    }
    // Intentamos PUT a /me (backend debe aceptar actualización parcial)
    const res = await api.put('/me', payload)
    toast.success('Datos guardados')
    // actualizar estado local
    user.value = res.data.user ?? user.value
    clinic.value = res.data.clinic ?? clinic.value
    if (Array.isArray(res.data.counters) && res.data.counters.length > 0) {
      counters.value = defaultCounters().map((base) => {
        const found = res.data.counters.find((item) => item.table_type === base.table_type)
        return {
          table_type: base.table_type,
          prefix: (found?.prefix ?? base.prefix ?? '').toString().toUpperCase(),
          last_number: Number.isFinite(Number(found?.last_number)) ? Math.max(Number(found?.last_number), 0) : 0,
        }
      })
    }
  } catch (e) {
    console.error('Error guardando perfil', e)
    const msg = e.response?.data?.message || 'Error guardando datos'
    toast.error(msg)
  } finally {
    saving.value = false
  }
}

function reload() { load() }

function pwReset() {
  pw.value.current_password = ''
  pw.value.password = ''
  pw.value.password_confirmation = ''
  pwMessage.value = ''
}

async function changePassword() {
  pwSaving.value = true
  pwMessage.value = ''
  try {
    await api.post('/me/password', { ...pw.value })
    pwReset()
    const toast = useToast()
    toast.success('Contraseña actualizada')
  } catch (e) {
    console.error('Error cambiando contraseña', e)
    if (e.response && e.response.status === 422) {
      const errs = e.response.data.errors || {}
      // Mostrar primer error encontrado
      const first = Object.values(errs)[0]
      pwMessage.value = Array.isArray(first) ? first[0] : String(first)
    } else {
      pwMessage.value = e.response?.data?.message || 'Error cambiando contraseña'
    }
  } finally {
    pwSaving.value = false
  }
}

async function subscribe() {
  try {
    const res = await api.post('/stripe/checkout')
    window.location.href = res.data.url
  } catch (e) {
    console.error('Error creando checkout', e)
    toast.error('Error iniciando subscripción')
  }
}

function beginPaidPlanFake() {
  router.push('/billing/required')
}

function formatDateTime(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleString('es-ES')
}

function formatBillingAmount(amountInCents, currency = 'EUR') {
  const cents = Number(amountInCents || 0)
  const amount = Number.isFinite(cents) ? cents / 100 : 0
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: currency || 'EUR' }).format(amount)
}

function previewCounter(row) {
  const prefix = (row?.prefix ?? '').toString().trim().toUpperCase().slice(0, 4)
  const value = Number.isFinite(Number(row?.last_number)) ? Math.max(Number(row.last_number) + 1, 1) : 1
  return `${prefix || '---'}-${String(value).padStart(6, '0')}`
}
</script>

<style scoped>
.label { display:block; font-weight:600; margin-bottom:6px }
.input { width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7eb }
.sub-banner { display:flex; align-items:center; gap:12px; background: rgba(255,255,255,0.9); padding:8px 10px; border-radius:10px; box-shadow: 0 6px 18px rgba(2,6,23,0.06) }
.sub-banner .meta { display:flex; flex-direction:column }
.sub-banner .small { font-size:12px; color:var(--text-muted,#6b7280) }
.status-dot { width:10px; height:10px; border-radius:50%; display:inline-block }
.status-dot.green { background: #10b981 }
.status-dot.yellow { background: #f59e0b }
.status-dot.red { background: #ef4444 }

.profile-shell { display:grid; gap:14px }
.card-stage { min-height:560px }
.tabs { display:flex; gap:8px; margin-bottom:12px }
.tab { padding:8px 12px; border-radius:8px; background:transparent; border:1px solid transparent; cursor:pointer }
.tab.active { background:#eef2ff; border-color:#c7d2fe; font-weight:600 }
.tab-panel { background:transparent }
.tab-card {
  min-height:560px;
  padding:20px;
  border:1px solid #e5e7eb;
  border-radius:16px;
  background:#fff;
  box-shadow: 0 10px 30px rgba(2,6,23,0.06);
}
.action-plane {
  position:sticky;
  bottom:16px;
  padding:12px 16px;
  border:1px solid #e5e7eb;
  border-radius:16px;
  background:rgba(255,255,255,0.96);
  box-shadow: 0 12px 28px rgba(2,6,23,0.08);
  backdrop-filter: blur(8px);
}
.action-row { display:flex; gap:8px; min-height:38px; align-items:center }
.action-row-empty { justify-content:flex-end }
.panel-note {
  margin-top:auto;
  padding:12px 14px;
  border-radius:12px;
  background:#fff7ed;
  color:#9a3412;
  font-size:13px;
}
.subscription-actions { display:flex; gap:8px; margin-top:16px; flex-wrap:wrap }
.subscription-history-title { font-size:13px; font-weight:700; color:#111827; margin-bottom:8px }
.subscription-history-empty { color:#6b7280; font-size:13px; padding:10px; border:1px dashed #d1d5db; border-radius:8px }
.subscription-history-list { border:1px solid #e5e7eb; border-radius:10px; overflow:hidden }
.subscription-history-head,
.subscription-history-row {
  display:grid;
  grid-template-columns:1.4fr 1fr 1fr;
  gap:10px;
  padding:8px 10px;
  font-size:13px;
  align-items:center;
}
.subscription-history-head { background:#f9fafb; color:#6b7280; font-weight:600 }
.subscription-history-row { border-top:1px solid #f3f4f6 }
.counter-grid {
  display:grid;
  grid-template-columns:180px 140px 160px 1fr;
  gap:10px;
  align-items:end;
}

@media (max-width: 768px) {
  .tab-card { min-height:auto; }
  .card-stage { min-height:auto; }
  .action-plane {
    position:static;
    bottom:auto;
  }
  .counter-grid {
    grid-template-columns:1fr;
  }
  .subscription-history-head,
  .subscription-history-row {
    grid-template-columns:1fr;
  }
}
</style>
