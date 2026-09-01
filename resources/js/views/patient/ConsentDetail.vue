<template>
  <div class="detail-page">
    <button class="back-btn" @click="$router.back()">← Volver</button>

    <div v-if="loading" class="loading">Cargando...</div>

    <div v-else-if="consent" class="detail-card">
      <div class="detail-header">
        <h2>{{ consent.template?.title || 'Consentimiento' }}</h2>
        <span class="detail-status" :class="consent.status">
          {{ consent.status === 'sent' ? 'Pendiente' : 'Firmado' }}
        </span>
      </div>

      <div v-if="consent.status === 'signed'" class="signed-info">
        <p>Firmado el {{ new Date(consent.signed_at).toLocaleDateString('es') }}</p>
      </div>

      <div v-if="consent.status === 'sent'" class="sign-section">
        <div class="form-group">
          <label>Tu firma</label>
          <div class="signature-area">
            <canvas ref="signatureCanvas" class="signature-canvas" width="300" height="150"></canvas>
            <button type="button" class="clear-btn" @click="clearSignature">Limpiar</button>
          </div>
        </div>

        <div v-if="error" class="error-message">{{ error }}</div>

        <button class="sign-btn" @click="handleSign" :disabled="signing">
          {{ signing ? 'Firmando...' : 'Firmar consentimiento' }}
        </button>
      </div>

      <div v-if="consent.content_html" class="consent-content" v-html="consent.content_html"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import patientApi from '../../patient/services/patientApi'

const route = useRoute()
const router = useRouter()
const consent = ref(null)
const loading = ref(true)
const signing = ref(false)
const error = ref('')
const signatureCanvas = ref(null)

let ctx = null
let isDrawing = false
let lastX = 0
let lastY = 0

onMounted(async () => {
  try {
    const { data } = await patientApi.get(`/consents/${route.params.id}`)
    consent.value = data.consent

    await nextTick()
    if (consent.value?.status === 'sent' && signatureCanvas.value) {
      initCanvas()
    }
  } catch (e) {
    console.error('Error loading consent:', e)
  } finally {
    loading.value = false
  }
})

function initCanvas() {
  ctx = signatureCanvas.value.getContext('2d')
  ctx.strokeStyle = '#1e293b'
  ctx.lineWidth = 2
  ctx.lineCap = 'round'

  signatureCanvas.value.addEventListener('mousedown', startDrawing)
  signatureCanvas.value.addEventListener('mousemove', draw)
  signatureCanvas.value.addEventListener('mouseup', stopDrawing)
  signatureCanvas.value.addEventListener('mouseleave', stopDrawing)

  // Touch support
  signatureCanvas.value.addEventListener('touchstart', handleTouch(startDrawing))
  signatureCanvas.value.addEventListener('touchmove', handleTouch(draw))
  signatureCanvas.value.addEventListener('touchend', stopDrawing)
}

function handleTouch(fn) {
  return (e) => {
    e.preventDefault()
    const touch = e.touches[0]
    const rect = signatureCanvas.value.getBoundingClientRect()
    fn({ offsetX: touch.clientX - rect.left, offsetY: touch.clientY - rect.top })
  }
}

function startDrawing(e) {
  isDrawing = true
  lastX = e.offsetX
  lastY = e.offsetY
}

function draw(e) {
  if (!isDrawing) return
  ctx.beginPath()
  ctx.moveTo(lastX, lastY)
  ctx.lineTo(e.offsetX, e.offsetY)
  ctx.stroke()
  lastX = e.offsetX
  lastY = e.offsetY
}

function stopDrawing() {
  isDrawing = false
}

function clearSignature() {
  ctx.clearRect(0, 0, signatureCanvas.value.width, signatureCanvas.value.height)
}

function getSignatureSvg() {
  return signatureCanvas.value.toDataURL('image/svg+xml')
}

async function handleSign() {
  signing.value = true
  error.value = ''

  try {
    await patientApi.post(`/consents/${route.params.id}/sign`, {
      signature_svg: getSignatureSvg(),
    })
    router.push('/patient/consents')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Error al firmar el consentimiento.'
  } finally {
    signing.value = false
  }
}
</script>

<style scoped>
.detail-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.back-btn {
  align-self: flex-start;
  padding: 8px 12px;
  border: none;
  background: none;
  color: #6366f1;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}

.loading {
  text-align: center;
  padding: 40px;
  color: #64748b;
}

.detail-card {
  background: #ffffff;
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.detail-header h2 {
  font-size: 18px;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.detail-status {
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

.detail-status.sent { background: #fef3c7; color: #92400e; }
.detail-status.signed { background: #d1fae5; color: #065f46; }

.signed-info {
  padding: 12px;
  background: #f0fdf4;
  border-radius: 8px;
  color: #16a34a;
  font-size: 14px;
}

.sign-section {
  margin-top: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 16px;
}

.form-group label {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}

.signature-area {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.signature-canvas {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #ffffff;
  cursor: crosshair;
}

.clear-btn {
  align-self: flex-start;
  padding: 6px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #ffffff;
  font-size: 13px;
  cursor: pointer;
}

.error-message {
  padding: 12px;
  border-radius: 8px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  font-size: 14px;
  margin-bottom: 12px;
}

.sign-btn {
  width: 100%;
  padding: 12px;
  border: none;
  border-radius: 8px;
  background: #6366f1;
  color: #ffffff;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
}

.sign-btn:hover:not(:disabled) {
  background: #4f46e5;
}

.sign-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.consent-content {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #e2e8f0;
  font-size: 14px;
  color: #374151;
  line-height: 1.6;
}
</style>
