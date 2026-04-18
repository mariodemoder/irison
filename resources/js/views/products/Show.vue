<template>
  <MainLayout>
    <div class="show-wrap">
      <div class="show-card">
        <div class="show-header">
          <div>
            <h1>Producto</h1>
            <p class="form-sub">Detalle del producto</p>
          </div>
          <div class="header-actions">
            <router-link v-if="product?.id" :to="`/products/${product.id}/edit`" class="primary">Editar</router-link>
            <button type="button" class="muted back-btn" @click="goBack">Volver</button>
          </div>
        </div>

        <AppLoading v-if="loading" message="Cargando producto..." />

        <div v-else-if="product" class="details-grid">
          <div class="field"><label class="label">ID</label><div class="value">{{ product.id }}</div></div>
          <div class="field"><label class="label">Referencia</label><div class="value">{{ product.reference }}</div></div>

          <div class="field full"><label class="label">Nombre</label><div class="value">{{ product.name }}</div></div>

          <div class="field"><label class="label">Precio venta</label><div class="value">{{ formatMoney(product.sale_price) }}</div></div>
          <div class="field"><label class="label">Precio compra</label><div class="value">{{ formatMoney(product.purchase_price) }}</div></div>

          <div class="field"><label class="label">Tax venta</label><div class="value">{{ formatTax(product.sale_tax) }}</div></div>
          <div class="field"><label class="label">Tax compra</label><div class="value">{{ formatTax(product.purchase_tax) }}</div></div>

          <div class="field"><label class="label">Familia</label><div class="value">{{ product.family || '—' }}</div></div>
          <div class="field"><label class="label">Lote</label><div class="value">{{ product.lot || '—' }}</div></div>
        </div>

        <div v-else class="alert-subtle">No se encontró el producto.</div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import api from '../../services/api'
import { goBackWithPriority } from '../../shared/navigationHelpers'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const loading = ref(false)
const product = ref(null)

function formatMoney(value) {
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(Number(value || 0))
}

function formatTax(value) {
  return `${Number(value || 0).toFixed(2)} %`
}

function goBack() {
  goBackWithPriority(router, {
    priorityPath: '/products',
    fallbackPath: '/products',
  })
}

async function load() {
  loading.value = true
  try {
    const res = await api.get(`/products/${route.params.id}`)
    product.value = res.data || null
  } catch (e) {
    product.value = null
    toast.error('Error cargando producto')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  load()
})
</script>

<style scoped>
.show-wrap { display:flex; justify-content:center; padding:6px 0 }
.show-card { width:100%; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; box-shadow:0 10px 30px rgba(2,6,23,0.06) }
.show-header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px }
.show-header h1 { margin:0; font-size:22px }
.header-actions { display:flex; align-items:center; gap:8px }

.details-grid { display:grid; grid-template-columns:repeat(2, 1fr); gap:12px }
.field { display:flex; flex-direction:column }
.field.full { grid-column:1 / -1 }
.label { font-weight:600; margin-bottom:6px }
.value { padding:10px; border:1px solid #e5e7eb; border-radius:8px; background:#fff }

.alert-subtle { background:#f8fafc; border:1px solid #e6edf3; padding:10px; border-radius:8px; color:#334155; font-size:14px }

@media (max-width: 768px) {
  .details-grid { grid-template-columns:1fr }
}
</style>
