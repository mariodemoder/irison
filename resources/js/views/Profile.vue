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

      <div v-if="loading">Cargando...</div>

      <div v-else>
        <div style="max-width:760px">
          <div class="tabs">
            <button :class="['tab', { active: activeTab==='datos' }]" @click="activeTab='datos'">Datos</button>
            <button :class="['tab', { active: activeTab==='seguridad' }]" @click="activeTab='seguridad'">Seguridad</button>
            <button :class="['tab', { active: activeTab==='subscripcion' }]" @click="activeTab='subscripcion'">Subscripción</button>
          </div>

          <div class="tab-panel" v-show="activeTab==='datos'">
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

              <div style="display:flex;gap:8px">
                <button class="btn btn-sm" type="submit" :disabled="saving">Guardar</button>
                <button class="btn btn-sm" type="button" @click.prevent="reload">Cancelar</button>
                <div style="margin-left:auto">
                  <button v-if="status==='blocked'" class="btn" @click.prevent="subscribe">Activar plan (Stripe)</button>
                </div>
              </div>
            </form>
          </div>

          <div class="tab-panel" v-show="activeTab==='seguridad'" style="margin-top:14px">
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
              <div style="display:flex;gap:8px">
                <button class="btn btn-sm" type="submit" :disabled="pwSaving">Cambiar contraseña</button>
                <button class="btn btn-sm" type="button" @click.prevent="pwReset">Limpiar</button>
              </div>
            </form>
          </div>

          <div class="tab-panel" v-show="activeTab==='subscripcion'" style="margin-top:14px">
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
              </div>
              <div v-else>
                <div>No tienes suscripción activa.</div>
              </div>

              <div style="margin-top:12px">
                <button class="btn btn-primary" @click.prevent="beginPaidPlanFake">Comenzar plan pago</button>
              </div>
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

// pestañas: 'datos' | 'seguridad' | 'subscripcion'
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

    form.value.name = user.value?.name ?? ''
    form.value.email = user.value?.email ?? ''
    form.value.clinic_name = clinic.value?.name ?? ''
    form.value.clinic_nif = clinic.value?.nif ?? ''
    form.value.clinic_address = clinic.value?.address ?? ''
    form.value.clinic_locality = clinic.value?.locality ?? ''
    form.value.clinic_province = clinic.value?.province ?? ''
    form.value.clinic_country = clinic.value?.country ?? ''
    form.value.clinic_zip = clinic.value?.zip ?? ''
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
      }
    }
    // Intentamos PUT a /me (backend debe aceptar actualización parcial)
    const res = await api.put('/me', payload)
    toast.success('Datos guardados')
    // actualizar estado local
    user.value = res.data.user ?? user.value
    clinic.value = res.data.clinic ?? clinic.value
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
</script>

<style scoped>
.label { display:block; font-weight:600; margin-bottom:6px }
.input { width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7eb }
.btn { /* use global .btn styles from resources/css/app.css */ }
.sub-banner { display:flex; align-items:center; gap:12px; background: rgba(255,255,255,0.9); padding:8px 10px; border-radius:10px; box-shadow: 0 6px 18px rgba(2,6,23,0.06) }
.sub-banner .meta { display:flex; flex-direction:column }
.sub-banner .small { font-size:12px; color:var(--text-muted,#6b7280) }
.status-dot { width:10px; height:10px; border-radius:50%; display:inline-block }
.status-dot.green { background: #10b981 }
.status-dot.yellow { background: #f59e0b }
.status-dot.red { background: #ef4444 }

.tabs { display:flex; gap:8px; margin-bottom:12px }
.tab { padding:8px 12px; border-radius:8px; background:transparent; border:1px solid transparent; cursor:pointer }
.tab.active { background:#eef2ff; border-color:#c7d2fe; font-weight:600 }
.tab-panel { background:transparent }
</style>
