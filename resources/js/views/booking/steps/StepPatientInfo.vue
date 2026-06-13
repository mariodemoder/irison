<script setup>
import { ref } from 'vue'

defineProps({
  submitting: { type: Boolean, default: false },
  error: { type: String, default: null },
})

const emit = defineEmits(['submit', 'back'])

const form = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  notes: '',
})

const errors = ref({})

function validate() {
  const errs = {}
  if (!form.value.first_name.trim()) errs.first_name = 'El nombre es obligatorio.'
  if (!form.value.last_name.trim()) errs.last_name = 'Los apellidos son obligatorios.'
  if (!form.value.email.trim()) errs.email = 'El email es obligatorio.'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) errs.email = 'Email no válido.'
  return errs
}

function handleSubmit() {
  errors.value = validate()
  if (Object.keys(errors.value).length > 0) return
  emit('submit', { ...form.value })
}
</script>

<template>
  <div class="step-card">
    <h2 class="step-title">Tus datos</h2>
    <p class="step-subtitle">Completa tus datos para confirmar la reserva.</p>

    <div v-if="error" class="form-error-banner">{{ error }}</div>

    <form class="patient-form" @submit.prevent="handleSubmit">
      <div class="form-row">
        <div class="form-group">
          <label for="first_name">Nombre</label>
          <input
            id="first_name"
            v-model="form.first_name"
            type="text"
            placeholder="Tu nombre"
            class="form-input"
            :class="{ 'has-error': errors.first_name }"
          />
          <span v-if="errors.first_name" class="field-error">{{ errors.first_name }}</span>
        </div>
        <div class="form-group">
          <label for="last_name">Apellidos</label>
          <input
            id="last_name"
            v-model="form.last_name"
            type="text"
            placeholder="Tus apellidos"
            class="form-input"
            :class="{ 'has-error': errors.last_name }"
          />
          <span v-if="errors.last_name" class="field-error">{{ errors.last_name }}</span>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="email">Email</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            placeholder="tucorreo@ejemplo.com"
            class="form-input"
            :class="{ 'has-error': errors.email }"
          />
          <span v-if="errors.email" class="field-error">{{ errors.email }}</span>
        </div>
        <div class="form-group">
          <label for="phone">Teléfono (opcional)</label>
          <input
            id="phone"
            v-model="form.phone"
            type="tel"
            placeholder="+34 600 000 000"
            class="form-input"
          />
        </div>
      </div>
      <div class="form-group">
        <label for="notes">Motivo de la consulta (opcional)</label>
        <textarea
          id="notes"
          v-model="form.notes"
          placeholder="Breve descripción del motivo de tu visita..."
          class="form-input form-textarea"
          rows="3"
        />
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn--ghost booking-btn-ghost" @click="$emit('back')">Volver</button>
        <button type="submit" class="btn btn--solid booking-btn-main" :disabled="submitting">
          {{ submitting ? 'Reservando...' : 'Confirmar reserva' }}
        </button>
      </div>
    </form>
  </div>
</template>

<style scoped>
.step-card {
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(17, 32, 59, 0.08);
  border-radius: 26px;
  padding: 28px;
  box-shadow: 0 14px 36px rgba(17, 32, 59, 0.06);
}

.step-title {
  margin: 0 0 4px;
  font-size: 1.3rem;
  font-weight: 800;
  letter-spacing: -0.03em;
}

.step-subtitle {
  margin: 0 0 20px;
  color: #556176;
}

.form-error-banner {
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 12px;
  padding: 12px 16px;
  margin-bottom: 16px;
  color: #b91c1c;
  font-size: 13px;
  font-weight: 600;
}

.patient-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.form-group label {
  font-size: 13px;
  font-weight: 700;
  color: #11203b;
}

.form-input {
  padding: 12px 14px;
  border-radius: 12px;
  border: 2px solid rgba(17, 32, 59, 0.08);
  font-size: 15px;
  font-family: inherit;
  background: #fff;
  transition: border-color 0.15s;
  color: #11203b;
}

.form-input:focus {
  outline: none;
  border-color: rgb(106, 48, 252);
}

.form-input.has-error {
  border-color: #ef4444;
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

.field-error {
  font-size: 12px;
  color: #ef4444;
  font-weight: 600;
}

.form-actions {
  display: flex;
  gap: 12px;
  justify-content: flex-end;
  margin-top: 8px;
}

.booking-btn-main {
  background: rgb(86, 39, 221);
  box-shadow: 0 12px 32px rgba(106, 48, 252, 0.3);
}

.booking-btn-main:hover {
  background: rgb(106, 48, 252);
}

.booking-btn-ghost {
  border-color: rgb(86, 39, 221);
  color: rgb(86, 39, 221);
}

@media (max-width: 600px) {
  .form-row {
    grid-template-columns: 1fr;
  }
}
</style>
