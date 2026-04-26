<template>
  <MainLayout>
    <div>
      <div class="entity-card">
        <div class="page-header">
          <div>
            <h1>Productos</h1>
            <div class="form-sub">Listado y búsqueda de productos</div>
          </div>
          <router-link to="/products/create" class="btn btn-sm small">Nuevo producto</router-link>
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
                  <td class="row-action products-action-col">
                    <router-link :to="`/products/${product.id}/edit`" class="action-btn datos" @click.stop>✎ Editar</router-link>
                  </td>
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
  { key: 'actions', label: '', thClass: 'products-action-col' },
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
    const status = e?.response?.status
    const message = e?.response?.data?.message
    toast.error((status === 402 || status === 403) && message ? `Error cargando productos - ${message}` : 'Error cargando productos')
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
.filters { margin-bottom:10px }
.search-input { padding:8px; border:1px solid #e5e7eb; border-radius:8px; font-size:13px; width:100% }

.name-col { font-weight:600 }

.row-action { display:flex; align-items:center; gap:8px; justify-content:flex-start }
.products-action-col { width:130px }
.action-btn { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:8px; text-decoration:none; color:#374151; font-size:13px; border:1px solid #e5e7eb; background:#fff }

.empty { color:#6b7280; padding:12px }

.pagination { margin-top:12px; display:flex; justify-content:flex-end; gap:12px; align-items:center }
.pagination-info { color:#6b7280; font-size:13px }
.pagination-actions { display:flex; gap:8px }
.icon-btn { width:32px; height:32px; border-radius:8px; border:1px solid #e5e7eb; background:#fff }
.icon-btn:disabled { opacity:0.45 }

@media (max-width: 900px) {
  .page-header { grid-template-columns: 1fr auto }
}
</style>
