<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api'
import { useToast } from 'vue-toastification'
import PatientPortalHelpModal from '../../components/patient/PatientPortalHelpModal.vue'

const toast = useToast()
const localLoading = ref(true)
const slug = ref('')
const slugError = ref('')
const slugTimeout = ref(null)
const saved = ref(false)
const showHelp = ref(false)
const isActive = ref(true)
const maxHorizonDays = ref(60)
const cancellationHours = ref(24)

const portalLoginUrl = computed(() => {
  if (!slug.value) return ''
  return `${window.location.origin}/patient/login?clinic=${encodeURIComponent(slug.value)}`
})

async function load() {
  localLoading.value = true
  try {
    const res = await api.get('/patient-portal/settings')
    // Si la clínica aún no tiene slug, sugerimos uno (Str::slug del nombre).
    slug.value = res.data.slug ?? res.data.suggested_slug ?? ''
    isActive.value = res.data.is_active ?? true
    maxHorizonDays.value = res.data.max_horizon_days ?? 60
    cancellationHours.value = res.data.cancellation_hours ?? 24
  } catch (e) {
    toast.error('Error al cargar configuración del portal del paciente.')
  } finally {
    localLoading.value = false
  }
}

async function checkSlugAvailability() {
  const value = slug.value?.trim()
  if (!value) {
    slugError.value = 'El slug de la URL es obligatorio.'
    return false
  }
  if (!/^[a-z0-9-]+$/.test(value)) {
    slugError.value = 'Solo letras minúsculas, números y guiones.'
    return false
  }
  try {
    const res = await api.get('/patient-portal/slug-check', { params: { slug: value } })
    if (!res.data.available) {
      slugError.value = 'Esa URL ya está en uso. Elige otra.'
      return false
    }
    slugError.value = ''
    return true
  } catch {
    slugError.value = ''
    return true
  }
}

function onSlugInput() {
  if (slugTimeout.value) clearTimeout(slugTimeout.value)
  slugTimeout.value = setTimeout(checkSlugAvailability, 400)
}

async function save() {
  saved.value = false
  const available = await checkSlugAvailability()
  if (!available) throw new Error('Slug no disponible')

  const res = await api.put('/patient-portal/settings', {
    slug: slug.value.trim(),
    is_active: isActive.value,
    max_horizon_days: maxHorizonDays.value,
    cancellation_hours: cancellationHours.value,
  })
  slug.value = res.data.slug
  saved.value = true
}

async function copyPublicUrl() {
  try {
    await navigator.clipboard.writeText(portalLoginUrl.value)
    toast.success('Enlace copiado')
  } catch (e) {
    toast.error('No se pudo copiar el enlace')
  }
}

defineExpose({ save })

onMounted(load)
</script>

<template>
  <div v-if="localLoading" style="text-align:center;padding:24px;color:#6b7280;">Cargando configuración del portal...</div>
  <div v-else>
    <div class="section-head portal-section-head">
      <h2>Portal del Paciente</h2>
      <button class="help-btn" @click="showHelp = true" title="Ayuda del Portal del Paciente">?</button>
      <div v-if="portalLoginUrl" class="public-link-actions">
        <a :href="portalLoginUrl" target="_blank" rel="noopener" class="btn btn-sm btn-outline">
          Ver página pública&nbsp;↗
        </a>
        <button type="button" class="btn btn-sm btn-outline" @click="copyPublicUrl">Copiar enlace público</button>
      </div>
    </div>
    <div style="margin-top:8px;color:#6b7280;font-size:13px">
      Define la URL pública de acceso al portal del paciente. Los pacientes entran en esta dirección y la clínica aparece con su propio nombre y logo.
    </div>

    <PatientPortalHelpModal v-if="showHelp" @close="showHelp = false" />

    <div class="form-group" style="margin-top:16px;max-width:420px">
      <label>URL del portal</label>
      <div class="url-preview">/patient/login?clinic=<strong>{{ slug || '…' }}</strong></div>
      <input
        v-model="slug"
        class="input"
        :class="{ 'input-error': slugError }"
        placeholder="mi-clinica"
        @input="onSlugInput"
      />
      <span v-if="slugError" class="slug-error">{{ slugError }}</span>
      <span v-else-if="saved" style="font-size:12px;color:#16a34a;">Guardado.</span>
    </div>

    <div class="portal-policy-block">
      <div class="portal-policy-title">Políticas del portal</div>
      <p class="portal-policy-desc">
        Acota las solicitudes, cancelaciones y reprogramaciones de citas que los pacientes pueden hacer desde el portal.
      </p>

      <div class="form-row-2">
        <div class="form-group">
          <label>Estado</label>
          <select v-model="isActive" class="input">
            <option :value="true">Activado</option>
            <option :value="false">Desactivado</option>
          </select>
        </div>
        <div class="form-group">
          <label>Horizonte máximo de reserva (días)</label>
          <select v-model.number="maxHorizonDays" class="input">
            <option :value="30">30 días</option>
            <option :value="60">60 días</option>
            <option :value="90">90 días</option>
            <option :value="180">180 días</option>
          </select>
        </div>
      </div>
      <div class="form-row-2">
        <div class="form-group">
          <label>Política de cancelación (horas antes)</label>
          <select v-model.number="cancellationHours" class="input">
            <option :value="1">1 hora</option>
            <option :value="12">12 horas</option>
            <option :value="24">24 horas</option>
            <option :value="48">48 horas</option>
            <option :value="72">72 horas</option>
          </select>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.section-head h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: #111827;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-group label {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

.url-preview {
  font-size: 13px;
  color: #6b7280;
  background: #f9fafb;
  padding: 6px 10px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.url-preview strong {
  color: #1d4ed8;
  word-break: break-all;
}

.input {
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  font-size: 14px;
  width: 100%;
  box-sizing: border-box;
  font-family: inherit;
}

.input:focus {
  outline: none;
  border-color: #3b82f6;
}

.input-error {
  border-color: #ef4444;
}

.slug-error {
  font-size: 12px;
  color: #ef4444;
}

.public-link-actions {
  margin-left: auto;
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.portal-section-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.portal-section-head h2 { margin: 0; }
.help-btn { width: 26px; height: 26px; border-radius: 50%; border: 1px solid #d1d5db; background: #fff; cursor: pointer; font-size: 13px; font-weight: 700; color: #6b7280; display: inline-flex; align-items: center; justify-content: center; line-height: 1; flex-shrink: 0; }
.help-btn:hover { background: #f3f4f6; color: #374151; }

.portal-policy-block {
  margin-top: 20px;
  padding-top: 16px;
  border-top: 1px solid #e5e7eb;
}

.portal-policy-title {
  font-size: 14px;
  font-weight: 700;
  color: #111827;
}

.portal-policy-desc {
  margin: 4px 0 0;
  font-size: 13px;
  color: #6b7280;
}

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-top: 16px;
  max-width: 560px;
}

select.input {
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  font-size: 14px;
  width: 100%;
  box-sizing: border-box;
  font-family: inherit;
  background: #fff;
  color: #111827;
}

select.input:focus {
  outline: none;
  border-color: #3b82f6;
}
</style>
