<template>
  <div
    v-if="open"
    class="modal-backdrop"
    @mousedown.left="onBackdropMouseDown"
    @mouseup.left="onBackdropMouseUp"
  >
    <div class="modal-content">
      <h3>Solicitar reactivación de la cuenta</h3>
      <p class="modal-intro">Cuéntanos el motivo por el que quieres volver a activar tu cuenta. El equipo de Irison revisará tu solicitud y se pondrá en contacto contigo.</p>
      <form @submit.prevent="submit">
        <label class="field">
          <span>Motivo <em class="req">*</em></span>
          <textarea v-model="comments" class="input" rows="3" maxlength="2000" required placeholder="¿Por qué quieres reactivar tu cuenta?"></textarea>
        </label>
        <div class="form-actions">
          <button type="button" class="muted" @click="close">Cancelar</button>
          <SaveButton :saving="sending">Enviar solicitud</SaveButton>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, toRef } from 'vue'
import { useToast } from 'vue-toastification'
import api from '../services/api'
import SaveButton from './SaveButton.vue'
import { useModalClose } from '../composables/useModalClose'

const props = defineProps({
  open: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'submitted'])

const toast = useToast()
const sending = ref(false)
const comments = ref('')

const { onBackdropMouseDown, onBackdropMouseUp } = useModalClose(close, toRef(props, 'open'))

async function submit() {
  const motive = String(comments.value || '').trim()
  if (!motive) {
    toast.error('Indica un motivo para la solicitud')
    return
  }

  sending.value = true
  try {
    await api.post('/settings/subscription/reactivate', { comments: motive })
    toast.success('Solicitud de reactivación enviada. Te contactaremos pronto.')
    comments.value = ''
    emit('submitted')
    close()
  } catch (e) {
    const message = e?.response?.data?.message || 'Error al enviar la solicitud'
    toast.error(message)
  } finally {
    sending.value = false
  }
}

function close() {
  emit('close')
}
</script>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}
.modal-content {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  min-width: 400px;
  max-width: 90vw;
}
.modal-content h3 {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 8px;
}
.modal-intro {
  font-size: 14px;
  color: #6b7280;
  margin: 0 0 16px;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 14px;
}
.field span {
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}
.field .req {
  color: #ef4444;
  font-style: normal;
}
.input {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 14px;
  outline: none;
}
.input:focus {
  border-color: #4338ca;
  box-shadow: 0 0 0 2px rgba(67, 56, 202, 0.15);
}
textarea.input {
  resize: vertical;
}
.form-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 16px;
}
</style>
