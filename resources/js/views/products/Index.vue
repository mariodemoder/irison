<template>
  <MainLayout>
    <div>
      <div class="entity-card">
        <div class="page-header">
          <div>
            <h1>Productos</h1>
            <div class="form-sub">Listado y búsqueda de productos</div>
          </div>

          <div class="search-center">
            <div class="search-wrapper">
              <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
              <input
                v-model="query"
                class="search-input"
                placeholder="Buscar por referencia, nombre, familia o lote"
                @input="debouncedLoad"
              />
            </div>
          </div>

          <NewButton label="Nuevo producto" to="/products/create" />
        </div>

        <AppLoading v-if="loading" message="Cargando productos..." />

        <template v-else>
          <EntityTable v-if="products.length > 0" :columns="tableColumns" table-class="products-table">
            <template #default>
                <tr
                  v-for="product in products"
                  :key="product.id"
                  class="entity-table-row"
                  role="button"
                  tabindex="0"
                  @click="goToShow(product.id)"
                  @keydown.enter="goToShow(product.id)"
                >
                  <td class="col-min">{{ product.id }}</td>
                  <td class="col-min">{{ product.reference }}</td>
                  <td class="col-mid name-col">{{ product.name }}</td>
                  <td class="col-min">{{ formatMoney(product.sale_price) }}</td>
                  <td class="col-min">{{ formatTax(product.sale_tax) }}</td>
                  <td class="col-min">{{ product.family || '—' }}</td>
                  <td class="col-min">{{ product.lot || '—' }}</td>
                </tr>
            </template>
          </EntityTable>

          <EmptyIndexState v-else-if="!hasActiveFilters" />
          <div v-else class="empty">No hay resultados para los filtros aplicados.</div>

          <div v-if="meta" class="pagination">
            <div class="pagination-info">Página {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} productos</div>
            <div class="pagination-actions">
              <button :disabled="meta.current_page <= 1" @click="load(meta.current_page - 1)" class="icon-btn">‹</button>
              <button :disabled="meta.current_page >= meta.last_page" @click="load(meta.current_page + 1)" class="icon-btn">›</button>
            </div>
          </div>
        </template>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import EmptyIndexState from '../../components/EmptyIndexState.vue'
import EntityTable from '../../components/EntityTable.vue'
import api from '../../services/api'
import { useToast } from 'vue-toastification'
import { getLoadErrorMessage } from '../../shared/httpErrors'

const toast = useToast()
const router = useRouter()
const loading = ref(false)
const products = ref([])
const meta = ref(null)
const query = ref('')
let searchTimer = null

const tableColumns = [
  { key: 'id', label: 'ID', thClass: 'col-min' },
  { key: 'reference', label: 'Referencia', thClass: 'col-min' },
  { key: 'name', label: 'Nombre', thClass: 'col-mid' },
  { key: 'sale_price', label: 'Precio venta', thClass: 'col-min' },
  { key: 'sale_tax', label: 'IVA venta', thClass: 'col-min' },
  { key: 'family', label: 'Familia', thClass: 'col-min' },
  { key: 'lot', label: 'Lote', thClass: 'col-min' },
]

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
    toast.error(getLoadErrorMessage(e, 'productos'))
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
.search-center { width: 100%; max-width: 520px; }

.name-col { font-weight:600 }

.action-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:8px; text-decoration:none; color:#374151; font-size:13px; border:1px solid #e5e7eb; background:#fff }

.empty { color:#6b7280; padding:12px }

.pagination { margin-top:12px; display:flex; justify-content:flex-end; gap:12px; align-items:center }
.pagination-info { color:#6b7280; font-size:13px }
.pagination-actions { display:flex; gap:8px }
.icon-btn { width:32px; height:32px; border-radius:8px; border:1px solid #e5e7eb; background:#fff }
.icon-btn:disabled { opacity:0.45 }

@media (max-width: 900px) {
  .search-center { max-width: 100%; }
}
</style>
