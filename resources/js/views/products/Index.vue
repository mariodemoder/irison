<template>
  <MainLayout>
    <div>
      <div class="page-header">
        <div>
          <h1>Productos</h1>
          <div class="form-sub">Listado y búsqueda de productos</div>
        </div>
        <router-link to="/products/create" class="primary">Nuevo producto</router-link>
      </div>

      <div class="filters">
        <input
          v-model="query"
          class="search-input"
          placeholder="Buscar por referencia, nombre, familia o lote"
          @input="debouncedLoad"
        />
      </div>

      <AppLoading v-if="loading" message="Cargando productos..." />

      <template v-else>
        <div class="list-header">
          <div>ID</div>
          <div>Referencia</div>
          <div>Nombre</div>
          <div>Precio venta</div>
          <div>IVA venta</div>
          <div>Familia</div>
          <div>Lote</div>
          <div></div>
        </div>

        <div class="list">
          <div
            v-for="product in products"
            :key="product.id"
            class="product-row"
            role="button"
            tabindex="0"
            @click="goToShow(product.id)"
            @keydown.enter="goToShow(product.id)"
          >
            <div>{{ product.id }}</div>
            <div>{{ product.reference }}</div>
            <div class="name-col">{{ product.name }}</div>
            <div>{{ formatMoney(product.sale_price) }}</div>
            <div>{{ formatTax(product.sale_tax) }}</div>
            <div>{{ product.family || '—' }}</div>
            <div>{{ product.lot || '—' }}</div>
            <div class="row-action">
              <router-link :to="`/products/${product.id}/edit`" class="action-btn datos" @click.stop>✎ Editar</router-link>
            </div>
          </div>

          <EmptyIndexState v-if="products.length === 0 && !hasActiveFilters" />
          <div v-else-if="products.length === 0" class="empty">No hay resultados para los filtros aplicados.</div>
        </div>

        <div v-if="meta" class="pagination">
          <div class="pagination-info">Página {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} productos</div>
          <div class="pagination-actions">
            <button :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)" class="icon-btn">‹</button>
            <button :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)" class="icon-btn">›</button>
          </div>
        </div>
      </template>
    </div>
  </MainLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import EmptyIndexState from '../../components/EmptyIndexState.vue'
import api from '../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()
const router = useRouter()
const loading = ref(false)
const products = ref([])
const meta = ref(null)
const query = ref('')
let searchTimer = null

const hasActiveFilters = computed(() => Boolean(String(query.value || '').trim()))

function formatMoney(value) {
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(Number(value || 0))
}

function formatTax(value) {
  return `${Number(value || 0).toFixed(2)} %`
}

async function load(page = 1) {
  loading.value = true
  try {
    const res = await api.get('/products', {
      params: {
        page,
        per_page: 15,
        q: query.value || undefined,
      },
    })

    products.value = Array.isArray(res.data?.data) ? res.data.data : []
    meta.value = res.data?.meta ?? null
  } catch (e) {
    products.value = []
    meta.value = null
    toast.error('Error cargando productos')
  } finally {
    loading.value = false
  }
}

function debouncedLoad() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(1), 250)
}

function goToShow(productId) {
  router.push(`/products/${productId}`)
}

onMounted(() => {
  load(1)
})
</script>

<style scoped>
.primary { padding:8px 14px; border-radius:9999px; border:2px solid #3b82f6; color:#3b82f6; background:#fff; font-weight:600 }
.primary:hover { background:#eff6ff }

.filters { margin-bottom:10px }
.search-input { padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; width:100% }

.list { display:flex; flex-direction:column; gap:8px }
.list-header { display:grid; grid-template-columns: 80px 1.2fr 2fr 1fr 1fr 1fr 1fr 170px; gap:10px; color:#6b7280; font-size:13px; font-weight:600; padding:6px 10px }
.product-row { display:grid; grid-template-columns: 80px 1.2fr 2fr 1fr 1fr 1fr 1fr 170px; gap:10px; background:#fff; border:1px solid #eef2ff22; border-radius:10px; padding:10px; align-items:center; font-size:13px }
.product-row { cursor:pointer; transition: border-color .2s ease, box-shadow .2s ease }
.product-row:hover { border-color:#bfdbfe; box-shadow:0 4px 14px rgba(59,130,246,.08) }
.name-col { font-weight:600 }

.row-action { display:flex; align-items:center; gap:8px; justify-content:flex-start }
.action-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:8px; text-decoration:none; color:#374151; font-size:13px; border:1px solid #e5e7eb; background:#fff }

.empty { color:#6b7280; padding:12px }

.pagination { margin-top:12px; display:flex; justify-content:flex-end; gap:12px; align-items:center }
.pagination-info { color:#6b7280; font-size:13px }
.pagination-actions { display:flex; gap:8px }
.icon-btn { width:32px; height:32px; border-radius:8px; border:1px solid #e5e7eb; background:#fff }
.icon-btn:disabled { opacity:0.45 }

@media (max-width: 900px) {
  .list-header,
  .product-row { grid-template-columns: 1fr }
}
</style>
