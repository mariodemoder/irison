<template>
  <div class="consent-section">
    <div class="section-header">
      <div>Consentimientos</div>
      <NewButton v-if="!isProfessional" label="Nuevo" class="small" @click="openCreateModal" />
    </div>

    <div v-if="loading" class="empty-card">Cargando...</div>

    <div v-else-if="consents.length === 0" class="empty-card">Sin consentimientos</div>

    <ul v-else class="consent-list">
      <li v-for="c in consents" :key="c.id" class="consent-item">
        <div class="consent-info">
          <strong>{{ c.template?.title ?? '—' }}</strong>
          <span class="consent-date">{{ formatDate(c.created_at) }}</span>
          <span class="status-badge" :class="c.status">{{ statusLabel(c.status) }}</span>
        </div>
        <div class="consent-actions">
          <button v-if="c.status === 'pending'" class="action-btn" @click="sendConsent(c)">Enviar</button>
          <button v-if="c.status === 'pending' || c.status === 'sent'" class="action-btn" @click="openSignModal(c)">Firmar</button>
          <button v-if="c.status === 'sent'" class="action-btn" @click="resendConsent(c)">Reenviar</button>
          <button v-if="c.status === 'signed'" class="pdf-btn" @click="downloadConsent(c)" title="Descargar PDF">PDF</button>
          <button v-if="c.status === 'signed'" class="action-btn danger" @click="revokeConsent(c)">Revocar</button>
        </div>
      </li>
    </ul>

    <div v-if="showCreateModal" class="modal-backdrop" @click.self="showCreateModal = false">
      <div class="modal-content compact-modal" role="dialog" aria-modal="true" aria-label="Nuevo consentimiento">
        <h3>Nuevo consentimiento</h3>
        <label class="field">
          <span>Plantilla</span>
          <select v-model="createForm.template_id" class="input">
            <option value="">Selecciona una plantilla</option>
            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.title }}</option>
          </select>
        </label>
        <div class="form-actions">
          <button class="muted" @click="showCreateModal = false">Cancelar</button>
          <SaveButton :saving="creatingConsent" @click="createConsent">Crear</SaveButton>
        </div>
      </div>
    </div>

    <div v-if="showSignModal" class="modal-backdrop" @click.self="showSignModal = false">
      <div class="modal-content compact-modal" role="dialog" aria-modal="true" aria-label="Firmar consentimiento">
        <h3>Firmar consentimiento</h3>
        <p style="margin-bottom:12px;color:#6b7280;font-size:14px">
          {{ signingConsent?.template?.title }}
        </p>
        <SignPad ref="signPad" @confirm="confirmSign" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useToast } from 'vue-toastification'
import api from '../../services/api'
import { isProfessional } from '../../shared/meCache'
import { confirmDelete } from '../../shared/confirmDelete'
import SaveButton from '../../components/SaveButton.vue'
import SignPad from '../../components/consents/SignPad.vue'

const props = defineProps({
  patientId: { type: [Number, String], required: true },
})

const toast = useToast()
const consents = ref([])
const templates = ref([])
const loading = ref(true)
const showCreateModal = ref(false)
const showSignModal = ref(false)
const creatingConsent = ref(false)
const signingConsent = ref(null)
const signPad = ref(null)

const createForm = ref({ template_id: '' })

watch(() => props.patientId, (val) => {
  if (val) fetch()
})

onMounted(() => {
  if (props.patientId) fetch()
})

async function fetch() {
  loading.value = true
  try {
    const res = await api.get(`/patients/${props.patientId}/consents`)
    consents.value = res.data.data
  } catch (_) {
    consents.value = []
  } finally {
    loading.value = false
  }
}

async function openCreateModal() {
  try {
    const res = await api.get('/consent-templates')
    templates.value = res.data.data
  } catch (_) {
    templates.value = []
  }
  createForm.value = { template_id: '' }
  showCreateModal.value = true
}

async function createConsent() {
  if (!createForm.value.template_id) {
    toast.warning('Selecciona una plantilla')
    return
  }
  creatingConsent.value = true
  try {
    await api.post(`/patients/${props.patientId}/consents`, createForm.value)
    toast.success('Consentimiento creado')
    showCreateModal.value = false
    await fetch()
  } catch (e) {
    toast.error('Error al crear')
  } finally {
    creatingConsent.value = false
  }
}

async function sendConsent(c) {
  try {
    await api.post(`/consents/${c.id}/send`)
    toast.success('Enlace de firma enviado')
    await fetch()
  } catch (_) {
    toast.error('Error al enviar')
  }
}

async function resendConsent(c) {
  try {
    await api.post(`/consents/${c.id}/resend`)
    toast.success('Enlace reenviado')
    await fetch()
  } catch (_) {
    toast.error('Error al reenviar')
  }
}

function openSignModal(c) {
  signingConsent.value = c
  showSignModal.value = true
}

async function confirmSign(svg) {
  try {
    await api.post(`/consents/${signingConsent.value.id}/sign`, { signature_svg: svg })
    toast.success('Firma registrada')
    showSignModal.value = false
    signingConsent.value = null
    await fetch()
  } catch (_) {
    toast.error('Error al firmar')
  }
}

async function downloadConsent(c) {
  try {
    const res = await api.get(`/consents/${c.id}/download`, { responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    window.open(url, '_blank')
  } catch (_) {
    toast.error('Error al descargar PDF')
  }
}

async function revokeConsent(c) {
  const confirmed = await confirmDelete({
    title: 'Revocar consentimiento',
    text: '¿Revocar este consentimiento? Esta acción no se puede deshacer.',
    confirmButtonText: 'Sí, revocar',
  })
  if (!confirmed) return
  try {
    await api.post(`/consents/${c.id}/revoke`)
    toast.success('Consentimiento revocado')
    await fetch()
  } catch (_) {
    toast.error('Error al revocar')
  }
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

function statusLabel(s) {
  const labels = { pending: 'Pendiente', sent: 'Enviado', signed: 'Firmado', rejected: 'Rechazado', revoked: 'Revocado' }
  return labels[s] || s
}
</script>

<style scoped>
.consent-section { background: #fff; border-radius: 8px; padding: 16px; }
.section-header { display: flex; justify-content: space-between; align-items: center; font-weight: 600; margin-bottom: 12px; }
.consent-list { list-style: none; padding: 0; margin: 0; }
.consent-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f3f4f6; gap:8px; }
.consent-item:last-child { border-bottom: none; }
.consent-info { display: flex; gap: 8px; align-items: center; flex-shrink:1; min-width:0; }
.consent-info strong { font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:180px; }
.consent-date { font-size: 12px; color: #9ca3af; white-space:nowrap; }
.consent-actions { display: flex; gap: 4px; flex-wrap:nowrap; flex-shrink:0; }
.action-btn { padding: 4px 10px; border-radius: 6px; border: 1px solid #d1d5db; background: #fff; cursor: pointer; font-size: 12px; }
.action-btn.danger { color: #dc2626; }
.pdf-btn { padding: 2px 8px; border-radius: 4px; border: 1px solid #d1d5db; background: #fff; cursor: pointer; font-size: 10px; font-weight: 700; color: #dc2626; letter-spacing: 0.5px; line-height: 1.4; }
.pdf-btn:hover { background: #fef2f2; }
.status-badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 500; }
.status-badge.pending { background: #fef3c7; color: #92400e; }
.status-badge.sent { background: #dbeafe; color: #1e40af; }
.status-badge.signed { background: #d1fae5; color: #065f46; }
.status-badge.revoked { background: #f3f4f6; color: #6b7280; }
.status-badge.rejected { background: #fee2e2; color: #991b1b; }
.empty-card { padding: 24px; text-align: center; color: #9ca3af; font-size: 14px; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 100; }
.modal-content { background: #fff; border-radius: 12px; padding: 24px; min-width: 360px; max-width: 90vw; box-shadow: 0 4px 24px rgba(0,0,0,.2); }
.modal-content h3 { font-size: 18px; font-weight: 700; margin-bottom: 16px; }
.field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
.field span { font-size: 13px; font-weight: 600; color: #374151; }
.input { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; }
.input:focus { border-color: #4338ca; }
.form-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 12px; }
</style>
