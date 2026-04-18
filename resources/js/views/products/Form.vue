<template>
  <MainLayout>
    <div class="form-wrapper">
      <div class="form-card">
        <div class="form-header">
          <h1>{{ isEdit ? 'Editar producto' : 'Nuevo producto' }}</h1>
          <p class="form-sub">{{ isEdit ? 'Actualiza los datos del producto.' : 'Crea un producto para facturación.' }}</p>
        </div>

        <form class="grid-form" @submit.prevent="submit">
          <div v-if="errors.general" class="field full">
            <div class="field-error">{{ errors.general[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Referencia</label>
            <input v-model="form.reference" type="text" class="input" />
            <div v-if="errors.reference" class="field-error">{{ errors.reference[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Nombre</label>
            <input v-model="form.name" type="text" class="input" />
            <div v-if="errors.name" class="field-error">{{ errors.name[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Precio venta</label>
            <input v-model.number="form.sale_price" type="number" step="0.01" min="0" class="input" />
            <div v-if="errors.sale_price" class="field-error">{{ errors.sale_price[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Precio compra</label>
            <input v-model.number="form.purchase_price" type="number" step="0.01" min="0" class="input" />
            <div v-if="errors.purchase_price" class="field-error">{{ errors.purchase_price[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Tax venta (%)</label>
            <input v-model.number="form.sale_tax" type="number" step="0.01" min="0" max="100" class="input" />
            <div v-if="errors.sale_tax" class="field-error">{{ errors.sale_tax[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Tax compra (%)</label>
            <input v-model.number="form.purchase_tax" type="number" step="0.01" min="0" max="100" class="input" />
            <div v-if="errors.purchase_tax" class="field-error">{{ errors.purchase_tax[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Familia</label>
            <input v-model="form.family" type="text" class="input" />
            <div v-if="errors.family" class="field-error">{{ errors.family[0] }}</div>
          </div>

          <div class="field">
            <label class="label">Lote</label>
            <input v-model="form.lot" type="text" class="input" />
            <div v-if="errors.lot" class="field-error">{{ errors.lot[0] }}</div>
          </div>

          <div class="actions full">
            <button class="primary" type="submit" :disabled="submitting">{{ submitting ? 'Guardando...' : 'Guardar' }}</button>
            <button type="button" class="muted" @click.prevent="cancel">Cancelar</button>
          </div>
        </form>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import MainLayout from '../../layouts/MainLayout.vue'
import api from '../../services/api'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const isEdit = ref(false)
const submitting = ref(false)
const errors = reactive({})

const form = reactive({
  reference: '',
  name: '',
  sale_price: 0,
  purchase_price: 0,
  sale_tax: 0,
  purchase_tax: 0,
  family: '',
  lot: '',
})

function clearErrors() {
  Object.keys(errors).forEach(k => delete errors[k])
}

async function loadForEdit(id) {
  try {
    const res = await api.get(`/products/${id}`)
    const data = res.data || {}
    form.reference = data.reference ?? ''
    form.name = data.name ?? ''
    form.sale_price = Number(data.sale_price ?? 0)
    form.purchase_price = Number(data.purchase_price ?? 0)
    form.sale_tax = Number(data.sale_tax ?? 0)
    form.purchase_tax = Number(data.purchase_tax ?? 0)
    form.family = data.family ?? ''
    form.lot = data.lot ?? ''
  } catch (e) {
    toast.error('No se pudo cargar el producto')
    router.push('/products')
  }
}

function normalizePayload() {
  return {
    reference: String(form.reference || '').trim(),
    name: String(form.name || '').trim(),
    sale_price: Number(form.sale_price || 0),
    purchase_price: Number(form.purchase_price || 0),
    sale_tax: Number(form.sale_tax || 0),
    purchase_tax: Number(form.purchase_tax || 0),
    family: String(form.family || '').trim() || null,
    lot: String(form.lot || '').trim() || null,
  }
}

async function submit() {
  clearErrors()
  submitting.value = true

  try {
    const payload = normalizePayload()

    if (isEdit.value) {
      const res = await api.put(`/products/${route.params.id}`, payload)
      const productId = Number(res.data?.id || route.params.id)
      toast.success('Producto actualizado')
      router.push(`/products/${productId}`)
    } else {
      const res = await api.post('/products', payload)
      const productId = Number(res.data?.id || 0)
      toast.success('Producto creado')
      if (productId > 0) {
        router.push(`/products/${productId}`)
      } else {
        router.push('/products')
      }
    }
  } catch (e) {
    if (e.response?.status === 422) {
      Object.assign(errors, e.response?.data?.errors || {})
      if (!Object.keys(errors).length) {
        errors.general = [e.response?.data?.message || 'Error de validación']
      }
    } else {
      errors.general = ['Error guardando producto']
    }
  } finally {
    submitting.value = false
  }
}

function cancel() {
  if (isEdit.value && route.params.id) {
    router.push(`/products/${route.params.id}`)
    return
  }
  router.push('/products')
}

onMounted(async () => {
  if (route.params.id) {
    isEdit.value = true
    await loadForEdit(route.params.id)
  }
})
</script>

<style scoped>
.form-wrapper { display:flex; justify-content:center; padding:24px }
.form-card { width:100%; max-width:760px; background: #fff; border-radius:12px; box-shadow: 0 10px 30px rgba(2,6,23,0.06); padding:24px }
.form-header h1 { margin:0; font-size:22px }

.grid-form { display:grid; grid-template-columns: repeat(2, 1fr); gap:12px }
.grid-form .full { grid-column: 1 / -1 }
.field { display:flex; flex-direction:column }
.label { font-weight:600; margin-bottom:6px }
.input { padding:12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px }
.field-error { color:#b91c1c; font-size:13px; margin-top:6px }

.actions { display:flex; gap:12px; align-items:center }
.primary {
  padding: 8px 16px;
  font-size: 14px;
  border-radius: 9999px;
  border: 2px solid #3b82f6;
  color: #3b82f6;
  background: #ffffff;
  font-weight: 600;
}
.primary:hover { background: #eff6ff }

@media (max-width: 768px) {
  .grid-form { grid-template-columns: 1fr }
}
</style>
