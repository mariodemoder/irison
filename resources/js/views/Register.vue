<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import AuthLayout from '../layouts/AuthLayout.vue'
import BaseInput from '../components/BaseInput.vue'
import BaseButton from '../components/BaseButton.vue'
import ErrorAlert from '../components/ErrorAlert.vue'
import axios from 'axios'

const router = useRouter()
const name = ref('')
const clinic_name = ref('')
const email = ref('')
const password = ref('')
const nif = ref('')
const zip = ref('')
const phone = ref('')
const loading = ref(false)
const error = ref('')
const errors = ref({})
const success = ref('')

const nifError = computed(() => {
  const v = nif.value.trim()
  if (!v) return ''
  if (!/^\d{8}[A-Z]$/.test(v) && !/^[XYZ]\d{7}[A-Z]$/.test(v))
    return 'Formato de NIF/NIE no válido (ej: 12345678Z).'
  return ''
})

const zipError = computed(() => {
  const v = zip.value.trim()
  if (!v) return ''
  if (!/^\d{5}$/.test(v)) return 'Debe tener 5 dígitos.'
  return ''
})

const phoneError = computed(() => {
  const v = phone.value.trim()
  if (!v) return ''
  if (!/^(\+\d{1,3})?\d{6,14}$/.test(v))
    return 'Formato de teléfono no válido.'
  return ''
})

async function submit() {
  loading.value = true
  error.value = ''
  errors.value = {}
  success.value = ''
  try {
    const sendPassword = (password.value || '').toString().trim().replace(/^"|"$/g, '')
    await axios.post('/api/register', {
      name: name.value,
      clinic_name: clinic_name.value,
      email: email.value,
      password: sendPassword,
      nif: nif.value.trim().toUpperCase(),
      zip: zip.value.trim(),
      phone: phone.value.trim(),
    })
    success.value = 'Cuenta creada. Revisa tu correo y haz clic en el enlace de activación para iniciar tu trial.'
    router.push('/login?activation=pending')
  } catch (err) {
    if (err.response?.status === 422 && err.response?.data?.errors) {
      errors.value = err.response.data.errors
    }
    error.value = err.response?.data?.message || 'Error al crear cuenta'
  } finally { loading.value = false }
}
</script>

<template>
  <AuthLayout>
    <h2 class="text-2xl font-semibold">Crear cuenta</h2>

    <form @submit.prevent="submit">
      <BaseInput v-model="name" label="Nombre" />
      <BaseInput v-model="clinic_name" label="Nombre de la clínica" />
      <BaseInput v-model="nif" label="NIF/NIE" placeholder="12345678Z"
                 @input="nif = $event.target.value.toUpperCase()" />
      <p v-if="nifError" class="input-error">{{ nifError }}</p>
      <p v-if="errors.nif" class="input-error">{{ errors.nif[0] }}</p>
      <BaseInput v-model="zip" label="Código postal" placeholder="28001" />
      <p v-if="zipError" class="input-error">{{ zipError }}</p>
      <p v-if="errors.zip" class="input-error">{{ errors.zip[0] }}</p>
      <BaseInput v-model="phone" label="Teléfono" placeholder="612345678" type="tel" />
      <p v-if="phoneError" class="input-error">{{ phoneError }}</p>
      <p v-if="errors.phone" class="input-error">{{ errors.phone[0] }}</p>
      <BaseInput v-model="email" label="Email" autocomplete="email" />
      <p v-if="errors.email" class="input-error">{{ errors.email[0] }}</p>
      <BaseInput v-model="password" label="Contraseña" type="password" autocomplete="new-password" />
      <p v-if="errors.password" class="input-error">{{ errors.password[0] }}</p>

      <BaseButton :type="'submit'">{{ loading ? 'Creando...' : 'Crear cuenta' }}</BaseButton>
    </form>

    <p class="footer text-sm mt-4">
      ¿Ya tienes cuenta?
      <router-link to="/login" class="text-accent">Iniciar sesión</router-link>
    </p>

    <ErrorAlert v-if="error" class="mt-3" title="No se pudo crear la cuenta" :message="error" />
    <p v-if="success" class="mt-3 text-sm success-text">{{ success }}</p>

  </AuthLayout>
</template>

<style scoped>
.input-error {
  color: #dc2626;
  font-size: 0.8rem;
  margin: -0.5rem 0 0.5rem;
}
</style>
