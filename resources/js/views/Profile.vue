<template>
  <MainLayout>
    <div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
        <div>
          <h1>Mi cuenta</h1>
          <div class="page-subtitle">Tus datos personales y seguridad.</div>
        </div>
      </div>

      <AppLoading v-if="loading" message="Cargando perfil..." />

      <div v-else>
        <div class="profile-container">
          <div class="tabs">
            <button :class="['tab', { active: activeTab==='datos' }]" @click="activeTab='datos'">Datos</button>
            <button :class="['tab', { active: activeTab==='seguridad' }]" @click="activeTab='seguridad'">Seguridad</button>
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
            </div>

            <div class="action-plane">
              <div v-if="activeTab==='datos'" class="action-row">
                <button class="btn btn-sm save-button" type="button" :disabled="saving" @click.prevent="save">Guardar</button>
              </div>

              <div v-else-if="activeTab==='seguridad'" class="action-row">
                <button class="btn btn-sm save-button" type="button" :disabled="pwSaving" @click.prevent="changePassword">Cambiar contraseña</button>
                <button class="btn btn-sm save-button" type="button" @click.prevent="pwReset">Limpiar</button>
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
import { ref, onMounted } from 'vue'
import MainLayout from '../layouts/MainLayout.vue'
import AppLoading from '../components/AppLoading.vue'
import api from '../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

const user = ref(null)
const loading = ref(true)
const saving = ref(false)

const form = ref({
  name: '',
  email: '',
})

const activeTab = ref('datos')

const pw = ref({ current_password: '', password: '', password_confirmation: '' })
const pwSaving = ref(false)
const pwMessage = ref('')

onMounted(async () => {
  await load()
})

async function load() {
  loading.value = true
  try {
    const res = await api.get('/me')
    user.value = res.data.user
    form.value.name = user.value?.name ?? ''
    form.value.email = user.value?.email ?? ''
  } catch (e) {
    console.error('Error cargando /me', e)
    const status = e?.response?.status
    const message = e?.response?.data?.message
    toast.error((status === 402 || status === 403) && message ? `Error cargando datos de usuario - ${message}` : 'Error cargando datos de usuario')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    const payload = {
      name: form.value.name,
      email: form.value.email,
    }

    const res = await api.put('/me', payload)
    toast.success('Datos guardados')
    user.value = res.data.user ?? user.value
  } catch (e) {
    console.error('Error guardando perfil', e)
    const msg = e.response?.data?.message || 'Error guardando datos'
    toast.error(msg)
  } finally {
    saving.value = false
  }
}

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
    toast.success('Contraseña actualizada')
  } catch (e) {
    console.error('Error cambiando contraseña', e)
    if (e.response && e.response.status === 422) {
      const errs = e.response.data.errors || {}
      const first = Object.values(errs)[0]
      pwMessage.value = Array.isArray(first) ? first[0] : String(first)
    } else {
      pwMessage.value = e.response?.data?.message || 'Error cambiando contraseña'
    }
  } finally {
    pwSaving.value = false
  }
}
</script>

<style scoped>
.label { display:block; font-weight:600; margin-bottom:6px }
.input { width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7eb }
.page-subtitle { color:#6b7280; font-size:13px; margin-top:4px }

.profile-container {
  width: 100%;
  max-width: 860px;
}
.profile-shell { display:grid; gap:14px }
.card-stage { min-height:420px }
.tabs {
  display:grid;
  grid-template-columns:repeat(2, minmax(0, 1fr));
  gap:8px;
  margin-bottom:12px;
}
.tab {
  width: 100%;
  text-align: center;
  padding:8px 12px;
  border-radius:8px;
  background:transparent;
  border:1px solid transparent;
  cursor:pointer;
}
.tab.active { background:#eef2ff; border-color:#c7d2fe; font-weight:600 }
.tab-panel { background:transparent }
.tab-card {
  min-height:420px;
  width: 100%;
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
.field-error { color:#b91c1c; font-size:13px; margin-bottom:10px }

@media (max-width: 768px) {
  .tab-card { min-height:auto; }
  .card-stage { min-height:auto; }
  .action-plane {
    position:static;
    bottom:auto;
  }
}
</style>
