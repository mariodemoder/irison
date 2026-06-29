<template>
  <MainLayout>
    <div class="sub-page">
      <h1>Suscripción</h1>

      <div v-if="loading" class="loading-card">Cargando...</div>

      <template v-if="!loading && sub">
        <!-- Plan Actual -->
        <section class="sub-section">
          <div class="current-plan-card">
            <div class="plan-badge-lg" :class="'plan-' + sub.plan">{{ planLabel }}</div>
            <div class="plan-meta">
              <div class="plan-price">{{ sub.plan_price }}€ <span class="plan-period">/mes</span></div>
              <div class="plan-status">
                <span class="status-dot" :class="'dot-' + sub.status"></span>
                {{ statusText }}
              </div>
            </div>
            <div class="plan-usage">
              <div class="usage-label">Usuarios</div>
              <div class="usage-bar-wrap">
                <div class="usage-bar" :style="{ width: usagePercent + '%' }"></div>
              </div>
              <div class="usage-text">{{ sub.users_used }} / {{ sub.max_users > 0 ? sub.max_users : '∞' }} usado{{ sub.users_used !== 1 ? 's' : '' }}</div>
            </div>
          </div>
        </section>

        <!-- Funcionalidades del plan actual -->
        <section class="sub-section">
          <h2>Funcionalidades incluidas</h2>
          <div class="features-card">
            <ul class="features-list">
              <li v-for="f in currentFeatures" :key="f">{{ f }}</li>
            </ul>
          </div>
        </section>

        <!-- Upgrade -->
        <section v-if="sub.next_plan" class="sub-section">
          <div class="upgrade-card">
            <div class="upgrade-info">
              <h3>Actualiza a {{ nextPlanName }}</h3>
              <ul class="upgrade-features">
                <li v-for="f in nextFeatures" :key="f">{{ f }}</li>
              </ul>
            </div>
            <button class="btn btn-primary" @click="showModal = true">Solicitar upgrade</button>
          </div>
        </section>

        <!-- Historial -->
        <section class="sub-section">
          <h2>Historial de solicitudes</h2>
          <div v-if="history.length === 0" class="empty-card">Sin solicitudes previas</div>
          <table v-else class="history-table">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Desde</th>
                <th>Hacia</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in history" :key="r.id">
                <td>{{ fmtDate(r.created_at) }}</td>
                <td>{{ r.current_plan }}</td>
                <td>{{ r.requested_plan }}</td>
                <td><span class="req-status" :class="'req-' + r.status">{{ statusLabel(r.status) }}</span></td>
              </tr>
            </tbody>
          </table>
        </section>
      </template>

      <!-- Modal solicitud -->
      <div v-if="showModal" class="modal-backdrop" @click.self="showModal = false">
        <div class="modal-content">
          <h3>Solicitar upgrade</h3>
          <form @submit.prevent="submitRequest">
            <label class="field">
              <span>Plan deseado</span>
              <select v-model="form.requested_plan" class="input">
                <option value="">Selecciona</option>
                <option value="pro">Pro — 89€/mes</option>
                <option value="enterprise">Enterprise — 189€/mes</option>
              </select>
            </label>
            <label class="field">
              <span>Comentarios (opcional)</span>
              <textarea v-model="form.comments" class="input" rows="3" placeholder="Cuéntanos por qué necesitas el cambio..."></textarea>
            </label>
            <div class="form-actions">
              <button type="button" class="muted" @click="showModal = false">Cancelar</button>
              <SaveButton :saving="sending" :disabled="!form.requested_plan">Enviar solicitud</SaveButton>
            </div>
          </form>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'
import { mePlan, planLabel, isBasic, isPro, isEnterprise } from '../../shared/meCache'
import SaveButton from '../../components/SaveButton.vue'

const toast = useToast()
const loading = ref(true)
const sub = ref(null)
const history = ref([])
const showModal = ref(false)
const sending = ref(false)
const form = ref({ requested_plan: '', comments: '' })
const pricingMap = ref({})

const statusText = computed(() => {
  if (!sub.value) return ''
  const map = { active: 'Activa', trial: 'Prueba', canceled: 'Cancelada', cancelled: 'Cancelada', blocked: 'Vencida', trial_read_only: 'Solo lectura' }
  return map[sub.value.status] || sub.value.status
})

const usagePercent = computed(() => {
  if (!sub.value || sub.value.max_users <= 0) return 0
  return Math.min(100, Math.round((sub.value.users_used / sub.value.max_users) * 100))
})

const currentFeatures = computed(() => {
  if (!sub.value) return []
  const p = pricingMap.value[sub.value.plan]
  return p?.features || []
})

const nextPlanName = computed(() => {
  if (!sub.value?.next_plan) return ''
  const names = { pro: 'Pro', enterprise: 'Enterprise' }
  return names[sub.value.next_plan] || sub.value.next_plan
})

const nextFeatures = computed(() => {
  if (!sub.value?.next_plan) return []
  const p = pricingMap.value[sub.value.next_plan]
  return p?.features || []
})

onMounted(async () => {
  try {
    const [pricingRes, subRes, historyRes] = await Promise.all([
      api.get('/pricing'),
      api.get('/settings/subscription'),
      api.get('/settings/subscription/history'),
    ])
    pricingMap.value = pricingRes.data.data || {}
    sub.value = subRes.data.data
    history.value = historyRes.data.data
  } catch (_) {
    toast.error('Error al cargar datos de suscripción')
  } finally {
    loading.value = false
  }
})

async function submitRequest() {
  if (!form.value.requested_plan) return
  sending.value = true
  try {
    await api.post('/settings/subscription/request', form.value)
    toast.success('Solicitud enviada. Te contactaremos pronto.')
    showModal.value = false
    form.value = { requested_plan: '', comments: '' }
    const res = await api.get('/settings/subscription/history')
    history.value = res.data.data
  } catch (_) {
    toast.error('Error al enviar solicitud')
  } finally {
    sending.value = false
  }
}

function fmtDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function statusLabel(s) {
  const labels = { pending: 'Pendiente', approved: 'Aprobada', rejected: 'Rechazada', completed: 'Completada' }
  return labels[s] || s
}
</script>

<style scoped>
.sub-page { padding: 24px; max-width: 800px; margin: 0 auto; }
.sub-page h1 { font-size: 20px; font-weight: 700; margin-bottom: 24px; }
.sub-section { margin-bottom: 32px; }
.sub-section h2 { font-size: 16px; font-weight: 600; margin-bottom: 12px; color: #374151; }
.loading-card, .empty-card { padding: 32px; text-align: center; color: #9ca3af; }
.current-plan-card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); display: grid; gap: 16px; }
.plan-badge-lg { display: inline-block; padding: 4px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; letter-spacing: 0.5px; width: fit-content; }
.plan-basic { background: #f3f4f6; color: #6b7280; }
.plan-pro { background: #dbeafe; color: #1e40af; }
.plan-enterprise { background: #d1fae5; color: #065f46; }
.plan-meta { display: flex; align-items: baseline; gap: 16px; }
.plan-price { font-size: 28px; font-weight: 700; color: #1f2937; }
.plan-period { font-size: 14px; font-weight: 400; color: #6b7280; }
.plan-status { font-size: 14px; color: #6b7280; display: flex; align-items: center; gap: 6px; }
.status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.dot-active { background: #10b981; }
.dot-trial { background: #f59e0b; }
.dot-blocked, .dot-canceled, .dot-cancelled { background: #ef4444; }
.plan-usage { }
.usage-label { font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
.usage-bar-wrap { height: 8px; background: #e5e7eb; border-radius: 999px; overflow: hidden; }
.usage-bar { height: 100%; background: #4338ca; border-radius: 999px; transition: width .3s; }
.usage-text { font-size: 12px; color: #6b7280; margin-top: 4px; }
.features-card { background: #fff; border-radius: 12px; padding: 20px 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.features-list { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 4px 16px; }
.features-list li { position: relative; padding-left: 20px; font-size: 14px; line-height: 2.2; color: #374151; }
.features-list li::before { content: '✓'; position: absolute; left: 0; font-weight: 700; color: #10b981; }
.upgrade-card { background: linear-gradient(135deg, #312e81, #4338ca); color: #fff; border-radius: 12px; padding: 24px; }
.upgrade-info h3 { font-size: 16px; font-weight: 700; margin: 0 0 12px; }
.upgrade-features { list-style: none; padding: 0; margin: 0; }
.upgrade-features li { position: relative; padding-left: 16px; font-size: 13px; line-height: 1.8; }
.upgrade-features li::before { content: '+'; position: absolute; left: 0; font-weight: 700; color: #a5b4fc; }
.upgrade-card .btn-primary { background: #fff; color: #4338ca; border: none; margin-top: 16px; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
.upgrade-card .btn-primary:hover { background: #f3f4f6; }
.history-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.history-table th { text-align: left; padding: 10px 14px; font-size: 12px; font-weight: 600; color: #6b7280; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
.history-table td { padding: 10px 14px; font-size: 13px; border-bottom: 1px solid #f3f4f6; }
.req-status { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
.req-pending { background: #fef3c7; color: #92400e; }
.req-approved { background: #d1fae5; color: #065f46; }
.req-rejected { background: #fee2e2; color: #991b1b; }
.req-completed { background: #dbeafe; color: #1e40af; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 100; }
.modal-content { background: #fff; border-radius: 12px; padding: 24px; min-width: 400px; max-width: 90vw; }
.modal-content h3 { font-size: 18px; font-weight: 700; margin-bottom: 16px; }
.field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 14px; }
.field span { font-size: 13px; font-weight: 600; color: #374151; }
.input { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; }
.input:focus { border-color: #4338ca; box-shadow: 0 0 0 2px rgba(67,56,202,.15); }
textarea.input { resize: vertical; }
.form-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; }
</style>
