
<template>
  <MainLayout>
    <div style="max-width:640px">
      <h1>Nuevo paciente</h1>

      <form @submit.prevent="submit">
      <div style="margin-bottom:8px">
        <label>Nombre</label>
        <input v-model="form.name" type="text" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px" />
        <div v-if="errors.name" style="color:#b91c1c;font-size:13px">{{ errors.name[0] }}</div>
      </div>

      <div style="margin-bottom:8px">
        <label>Teléfono</label>
        <input v-model="form.phone" type="text" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px" />
        <div v-if="errors.phone" style="color:#b91c1c;font-size:13px">{{ errors.phone[0] }}</div>
      </div>

      <div style="margin-bottom:8px">
        <label>Email</label>
        <input v-model="form.email" type="email" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px" />
        <div v-if="errors.email" style="color:#b91c1c;font-size:13px">{{ errors.email[0] }}</div>
      </div>

      <div style="margin-bottom:8px">
        <label>Notas</label>
        <textarea v-model="form.notes" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px" rows="4"></textarea>
        <div v-if="errors.notes" style="color:#b91c1c;font-size:13px">{{ errors.notes[0] }}</div>
      </div>

      <div style="display:flex;gap:8px">
        <button class="btn" type="submit" :disabled="submitting">Guardar</button>
        <router-link to="/patients">Cancelar</router-link>
      </div>
    </form>
    </div>
  </MainLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../services/api'
import MainLayout from '../../layouts/MainLayout.vue'

const router = useRouter()
const form = reactive({ name: '', phone: '', email: '', notes: '' })
const errors = reactive({})
const submitting = ref(false)

async function submit() {
  submitting.value = true
  Object.keys(errors).forEach(k => delete errors[k])
  try {
    const res = await api.post('/patients', { ...form })
    router.push('/patients')
  } catch (e) {
    if (e.response && e.response.status === 422) {
      const eobj = e.response.data.errors || {}
      Object.assign(errors, eobj)
    } else {
      console.error('Error guardando paciente', e)
    }
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.btn { background:#111827;color:#fff;padding:8px 12px;border-radius:6px }
</style>
