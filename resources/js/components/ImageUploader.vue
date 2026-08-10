<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: File, default: null },
  previewUrl: { type: String, default: null },
  label: { type: String, default: 'Imagen' },
  accept: { type: String, default: 'image/*' },
  maxSizeMb: { type: Number, default: 0 },
  maxWidth: { type: Number, default: 0 },
  maxHeight: { type: Number, default: 0 },
  hint: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'validity-change'])

const inputId = `image-uploader-${Math.random().toString(36).slice(2, 10)}`
const errorId = `${inputId}-error`
const localPreviewUrl = ref(null)
const errorMessage = ref('')
const isDragging = ref(false)

const previewSrc = computed(() => localPreviewUrl.value || props.previewUrl || null)

const fileMeta = computed(() => {
  if (!props.modelValue) return ''
  return `${props.modelValue.name} · ${formatSize(props.modelValue.size)}`
})

function formatSize(bytes) {
  if (!bytes) return '0 B'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function onFileSelected(event) {
  const file = event?.target?.files?.[0] || null
  event.target.value = ''
  setFile(file)
}

function onDragEnter() {
  if (!props.disabled && !props.loading) isDragging.value = true
}

function onDragLeave() {
  isDragging.value = false
}

function onDrop(event) {
  isDragging.value = false
  if (props.disabled || props.loading) return
  const file = event.dataTransfer?.files?.[0] || null
  setFile(file)
}

function setFile(file) {
  revokeLocalPreview()
  errorMessage.value = ''

  if (!file) {
    emit('update:modelValue', null)
    emit('validity-change', false)
    return
  }

  if (props.maxSizeMb > 0 && file.size > props.maxSizeMb * 1024 * 1024) {
    errorMessage.value = `El archivo no puede superar ${props.maxSizeMb} MB.`
    emit('update:modelValue', file)
    emit('validity-change', false)
    return
  }

  localPreviewUrl.value = URL.createObjectURL(file)
  emit('update:modelValue', file)
  validateDimensions()
}

function validateDimensions() {
  if (!props.maxWidth && !props.maxHeight) {
    emit('validity-change', !errorMessage.value)
    return
  }

  const img = new Image()
  img.onload = () => {
    if (
      (props.maxWidth && img.naturalWidth > props.maxWidth) ||
      (props.maxHeight && img.naturalHeight > props.maxHeight)
    ) {
      errorMessage.value = `La imagen no puede superar ${props.maxWidth || '∞'} × ${props.maxHeight || '∞'} px.`
    }
    emit('validity-change', !errorMessage.value)
  }
  img.onerror = () => {
    emit('validity-change', !errorMessage.value)
  }
  img.src = localPreviewUrl.value
}

function clearFile() {
  revokeLocalPreview()
  errorMessage.value = ''
  emit('update:modelValue', null)
  emit('validity-change', false)
}

function revokeLocalPreview() {
  if (localPreviewUrl.value) {
    URL.revokeObjectURL(localPreviewUrl.value)
    localPreviewUrl.value = null
  }
}

watch(
  () => props.modelValue,
  (file) => {
    if (!file) {
      revokeLocalPreview()
      errorMessage.value = ''
    }
  }
)

onBeforeUnmount(revokeLocalPreview)
</script>

<template>
  <div class="image-uploader">
    <label v-if="label" class="image-uploader-label" :for="inputId">{{ label }}</label>

    <label
      class="image-uploader-dropzone"
      :class="{
        'is-dragging': isDragging,
        'is-invalid': !!errorMessage,
        'is-disabled': disabled || loading,
      }"
      :for="inputId"
      :aria-invalid="errorMessage ? 'true' : null"
      :aria-describedby="errorMessage ? errorId : undefined"
      @dragenter.prevent="onDragEnter"
      @dragleave.prevent="onDragLeave"
      @dragover.prevent
      @drop.prevent="onDrop"
    >
      <input
        :id="inputId"
        class="image-uploader-input"
        type="file"
        :accept="accept"
        :disabled="disabled || loading"
        @change="onFileSelected"
      />

      <div v-if="loading" class="image-uploader-loading">
        <span class="image-uploader-spinner"></span>
        <span>Subiendo…</span>
      </div>

      <template v-else>
        <img
          v-if="previewSrc"
          :src="previewSrc"
          alt="Vista previa de la imagen"
          class="image-uploader-preview"
        />
        <div v-else class="image-uploader-empty">
          <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
            class="image-uploader-icon"
          >
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="17 8 12 3 7 8"></polyline>
            <line x1="12" y1="3" x2="12" y2="15"></line>
          </svg>
          <span>Arrastrá una imagen o hacé click para seleccionar</span>
        </div>
      </template>
    </label>

    <div v-if="fileMeta && !loading" class="image-uploader-meta">
      <span class="image-uploader-file">{{ fileMeta }}</span>
      <button
        type="button"
        class="image-uploader-clear"
        :disabled="disabled || loading"
        @click="clearFile"
      >
        Quitar
      </button>
    </div>

    <p v-if="errorMessage" :id="errorId" class="image-uploader-error" role="alert">
      {{ errorMessage }}
    </p>

    <p v-else-if="hint" class="image-uploader-hint">{{ hint }}</p>

    <div v-if="$slots.actions" class="image-uploader-actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<style scoped>
.image-uploader-label {
  display: block;
  font-size: 13px;
  color: var(--text-muted);
  margin-bottom: 8px;
  font-weight: 600;
}

.image-uploader-dropzone {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 128px;
  padding: 20px;
  border: 1px dashed #d1d5db;
  border-radius: 12px;
  background: var(--bg-app, #f8fafc);
  cursor: pointer;
  transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
}

.image-uploader-dropzone:hover,
.image-uploader-dropzone:focus-within {
  border-color: var(--primary);
  background: var(--primary-soft, #dbeafe);
}

.image-uploader-dropzone.is-dragging {
  border-color: var(--primary);
  background: var(--primary-soft, #dbeafe);
  box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15);
}

.image-uploader-dropzone.is-invalid {
  border-color: var(--error);
}

.image-uploader-dropzone.is-disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.image-uploader-input {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
  border: 0;
}

.image-uploader-preview {
  max-width: 100%;
  max-height: 96px;
  object-fit: contain;
  border-radius: 8px;
  background: var(--bg-card, #ffffff);
  padding: 4px;
  border: 1px solid #e5e7eb;
}

.image-uploader-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  color: var(--text-muted);
  font-size: 13px;
  text-align: center;
  padding: 8px;
}

.image-uploader-icon {
  width: 30px;
  height: 30px;
  opacity: 0.7;
}

.image-uploader-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  color: var(--text-muted);
  font-size: 13px;
}

.image-uploader-spinner {
  width: 28px;
  height: 28px;
  border: 3px solid var(--primary-soft, #dbeafe);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: image-uploader-spin 0.8s linear infinite;
}

@keyframes image-uploader-spin {
  to {
    transform: rotate(360deg);
  }
}

.image-uploader-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: 8px;
}

.image-uploader-file {
  font-size: 13px;
  color: var(--text-secondary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.image-uploader-clear {
  flex-shrink: 0;
  border: none;
  background: transparent;
  color: var(--text-muted);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  padding: 2px 4px;
  border-radius: 6px;
}

.image-uploader-clear:hover {
  color: var(--error);
  background: rgba(239, 68, 68, 0.08);
}

.image-uploader-clear:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.image-uploader-error {
  margin: 8px 0 0;
  color: var(--error);
  font-size: 13px;
}

.image-uploader-hint {
  margin: 8px 0 0;
  color: var(--text-muted);
  font-size: 13px;
}

.image-uploader-actions {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
  margin-top: 12px;
}
</style>
