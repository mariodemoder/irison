<template>
  <div class="profile-page">
    <h1>Mi perfil</h1>

    <div v-if="loading" class="loading">Cargando...</div>

    <form v-else @submit.prevent="handleUpdate" class="profile-form">
      <div v-if="message" class="success-message">{{ message }}</div>
      <div v-if="error" class="error-message">{{ error }}</div>

      <div class="form-group">
        <label for="first_name">Nombre</label>
        <input id="first_name" v-model="form.first_name" type="text" required />
      </div>

      <div class="form-group">
        <label for="last_name">Apellidos</label>
        <input id="last_name" v-model="form.last_name" type="text" required />
      </div>

      <div class="form-group">
        <label for="email">Email</label>
        <input id="email" :value="patient?.email" type="email" disabled />
        <span class="field-note">El email no se puede cambiar desde aquí. Contacta con la clínica.</span>
      </div>

      <div class="form-group">
        <label for="phone">Teléfono</label>
        <input id="phone" v-model="form.phone" type="tel" />
      </div>

      <div class="form-group">
        <label for="address">Dirección</label>
        <input id="address" v-model="form.address" type="text" />
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="zip">Código postal</label>
          <input id="zip" v-model="form.zip" type="text" />
        </div>
        <div class="form-group">
          <label for="city">Ciudad</label>
          <input id="city" v-model="form.city" type="text" />
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="province">Provincia</label>
          <input id="province" v-model="form.province" type="text" />
        </div>
        <div class="form-group">
          <label for="country">País</label>
          <input id="country" v-model="form.country" type="text" />
        </div>
      </div>

      <button type="submit" class="save-btn" :disabled="saving">
        {{ saving ? 'Guardando...' : 'Guardar cambios' }}
      </button>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import patientApi from '../../patient/services/patientApi'
import { usePatientAuth } from '../../patient/composables/usePatientAuth'

const { patient, fetchMe } = usePatientAuth()

const form = ref({
  first_name: '',
  last_name: '',
  phone: '',
  address: '',
  zip: '',
  city: '',
  province: '',
  country: '',
})

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const message = ref('')

onMounted(async () => {
  try {
    await fetchMe()
    if (patient.value) {
      form.value = {
        first_name: patient.value.first_name || '',
        last_name: patient.value.last_name || '',
        phone: patient.value.phone || '',
        address: patient.value.address || '',
        zip: patient.value.zip || '',
        city: patient.value.city || '',
        province: patient.value.province || '',
        country: patient.value.country || '',
      }
    }
  } catch (e) {
    console.error('Error loading profile:', e)
  } finally {
    loading.value = false
  }
})

async function handleUpdate() {
  saving.value = true
  error.value = ''
  message.value = ''

  try {
    await patientApi.put('/profile', form.value)
    await fetchMe()
    message.value = 'Perfil actualizado correctamente.'
  } catch (e) {
    error.value = e?.response?.data?.message || 'Error al actualizar el perfil.'
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.profile-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

h1 {
  font-size: 20px;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.loading {
  text-align: center;
  padding: 40px;
  color: #64748b;
}

.profile-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.success-message {
  padding: 12px;
  border-radius: 8px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #16a34a;
  font-size: 14px;
}

.error-message {
  padding: 12px;
  border-radius: 8px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  font-size: 14px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 14px;
  font-weight: 600;
  color: #374151;
}

.form-group input {
  padding: 10px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 15px;
}

.form-group input:focus {
  outline: none;
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-group input:disabled {
  background: #f1f5f9;
  color: #64748b;
}

.field-note {
  font-size: 12px;
  color: #94a3b8;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.save-btn {
  padding: 12px;
  border: none;
  border-radius: 8px;
  background: #6366f1;
  color: #ffffff;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
}

.save-btn:hover:not(:disabled) {
  background: #4f46e5;
}

.save-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
