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
            <EditButton v-if="product?.id" :to="`/products/${product.id}/edit`" />
            <div class="back-menu-group">
              <button type="button" class="muted back-btn" @click="goBack">Volver</button>
              <div v-if="product?.id" class="quick-actions" ref="quickActionsRef">
                <button
                  type="button"
                  class="muted quick-trigger menu-right-btn"
                  @click="toggleQuickActions"
                  :disabled="deleting"
                  aria-label="Acciones"
                  title="Acciones"
                >
                  <svg class="quick-trigger-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <circle cx="12" cy="5" r="1.8" fill="currentColor" />
                    <circle cx="12" cy="12" r="1.8" fill="currentColor" />
                    <circle cx="12" cy="19" r="1.8" fill="currentColor" />
                  </svg>
                </button>
                <div v-if="quickActionsOpen" class="quick-menu">
                  <BtnTrash class="quick-item danger" :disabled="deleting" @click.prevent="runDelete">{{ deleting ? 'Eliminando...' : 'Eliminar' }}</BtnTrash>
                </div>
              </div>
            </div>
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
import { onMounted, onBeforeUnmount, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import Swal from 'sweetalert2'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import BtnTrash from '../../components/BtnTrash.vue'
import api from '../../services/api'
import { getLoadErrorMessage } from '../../shared/httpErrors'
import { goBackWithPriority } from '../../shared/navigationHelpers'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const loading = ref(false)
const deleting = ref(false)
const product = ref(null)
const quickActionsOpen = ref(false)
const quickActionsRef = ref(null)

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

function toggleQuickActions() {
  quickActionsOpen.value = !quickActionsOpen.value
}

function closeQuickActions() {
  quickActionsOpen.value = false
}

function handleClickOutsideQuickActions(event) {
  if (!quickActionsOpen.value) return
  if (!quickActionsRef.value) return
  if (!quickActionsRef.value.contains(event.target)) {
    closeQuickActions()
  }
}

function runDelete() {
  closeQuickActions()
  handleDelete()
}

async function handleDelete() {
  if (deleting.value) return
  const result = await Swal.fire({
    title: 'Eliminar producto',
    text: `¿Estás seguro de eliminar "${product.value?.name}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Eliminar',
    cancelButtonText: 'Cancelar',
    buttonsStyling: false,
    customClass: {
      popup: 'swal-popup-card',
      confirmButton: 'primary',
      cancelButton: 'muted'
    }
  })
  if (!result.isConfirmed) return

  deleting.value = true
  try {
    await api.delete(`/products/${route.params.id}`)
    toast.success('Producto eliminado')
    router.push('/products')
  } catch (e) {
    const msg = e?.response?.data?.message || 'Error al eliminar el producto'
    toast.error(msg)
  } finally {
    deleting.value = false
  }
}

async function load() {
  loading.value = true
  try {
    const res = await api.get(`/products/${route.params.id}`)
    product.value = res.data || null
  } catch (e) {
    product.value = null
      toast.error(getLoadErrorMessage(e, 'producto'))
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  load()
  document.addEventListener('click', handleClickOutsideQuickActions)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutsideQuickActions)
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

.back-menu-group { display:inline-flex; align-items:center; gap:0 }
.quick-trigger { padding:11px 12px; display:inline-flex; align-items:center; justify-content:center }
.quick-trigger-icon { width:18px; height:18px; color:#4b5563 }
.quick-actions { position:relative }
.quick-menu { position:absolute; right:0; top:calc(100% + 6px); min-width:180px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 10px 24px rgba(2,6,23,0.10); padding:6px; display:flex; flex-direction:column; gap:4px; z-index:20 }
.quick-item { text-align:left; padding:8px 10px; border:1px solid transparent; background:#fff; border-radius:8px; font-size:14px; color:#111827 }
.quick-item:hover { background:#f9fafb }
.quick-item.danger { color:#b91c1c }

@media (max-width: 768px) {
  .details-grid { grid-template-columns:1fr }
}
</style>
