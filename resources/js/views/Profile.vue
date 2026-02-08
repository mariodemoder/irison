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
        <form @submit.prevent="save" style="display:grid;gap:12px;max-width:760px">
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

          <div style="display:flex;gap:8px">
            <button class="btn btn-sm" type="submit" :disabled="saving">Guardar</button>
            <button class="btn btn-sm" type="button" @click.prevent="reload">Cancelar</button>
            <div style="margin-left:auto">
              <button v-if="status==='blocked'" class="btn" @click.prevent="subscribe">Activar plan (Stripe)</button>
            </div>
          </div>
        </form>
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

const form = ref({ name: '', email: '', clinic_name: '' })

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
      clinic: { name: form.value.clinic_name }
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

async function subscribe() {
  try {
    const res = await api.post('/stripe/checkout')
    window.location.href = res.data.url
  } catch (e) {
    console.error('Error creando checkout', e)
    toast.error('Error iniciando subscripción')
  }
}
</script>

<style scoped>
.label { display:block; font-weight:600; margin-bottom:6px }
.input { width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7eb }
.btn { background: var(--primary, #1f2937); color: #fff; border: none; border-radius: 6px; padding: 8px 12px; font-size: 14px; cursor: pointer }
.btn.btn-sm { padding: 6px 10px; font-size: 13px; border-radius: 6px }
.sub-banner { display:flex; align-items:center; gap:12px; background: rgba(255,255,255,0.9); padding:8px 10px; border-radius:10px; box-shadow: 0 6px 18px rgba(2,6,23,0.06) }
.sub-banner .meta { display:flex; flex-direction:column }
.sub-banner .small { font-size:12px; color:var(--text-muted,#6b7280) }
.status-dot { width:10px; height:10px; border-radius:50%; display:inline-block }
.status-dot.green { background: #10b981 }
.status-dot.yellow { background: #f59e0b }
.status-dot.red { background: #ef4444 }
</style>
