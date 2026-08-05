<template>
  <MainLayout>
    <div>
      <div class="entity-card">
        <div class="page-header">
          <div>
            <h1>Finanzas</h1>
            <div class="form-sub">Control de gastos, tarifas y dashboard de beneficios</div>
          </div>
        </div>

        <div class="finance-tabs">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            class="finance-tab"
            :class="{ active: activeTab === tab.key }"
            @click="activeTab = tab.key"
          >
            {{ tab.label }}
          </button>
        </div>

        <!-- ============ GASTOS ============ -->
        <section v-if="activeTab === 'gastos'">
          <div class="finance-toolbar">
            <div class="search-wrapper">
              <input
                v-model="expenseQuery"
                class="search-input"
                placeholder="Buscar por concepto, proveedor o nº de factura"
                @input="debouncedLoadExpenses"
              />
            </div>
            <select v-model="expenseCategoryFilter" class="input input-sm" @change="loadExpenses(1)">
              <option value="">Todas las categorías</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <div class="toolbar-actions">
              <button class="btn btn-sm small" @click="openCategoryModal">Categorías</button>
              <NewButton label="Nuevo gasto" @click="openExpenseModal()" />
            </div>
          </div>

          <AppLoading v-if="expensesLoading" message="Cargando gastos..." />

          <template v-else>
            <div v-if="expenses.length === 0" class="empty-card">No hay gastos registrados.</div>

            <div v-else class="table-wrap">
              <table class="entity-table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th>Categoría</th>
                    <th>Proveedor</th>
                    <th>Base</th>
                    <th>IVA</th>
                    <th>Total</th>
                    <th>Pago</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="e in expenses" :key="e.id">
                    <td>{{ formatDate(e.date) }}</td>
                    <td class="concept-cell">{{ e.concept }}<span v-if="e.receipt_number" class="receipt-num">· {{ e.receipt_number }}</span></td>
                    <td>
                      <span v-if="e.category" class="category-chip" :style="categoryStyle(e.category)">{{ e.category.name }}</span>
                      <span v-else class="muted">—</span>
                    </td>
                    <td>{{ e.supplier || '—' }}</td>
                    <td>{{ formatMoney(e.amount) }}</td>
                    <td>{{ formatTax(e.tax_rate) }}</td>
                    <td class="total-cell">{{ formatMoney(e.total) }}</td>
                    <td>{{ paymentLabel(e.payment_method) }}</td>
                    <td class="row-action">
                      <EditButton @click="openExpenseModal(e)" />
                      <BtnTrash @click="removeExpense(e)" title="Eliminar gasto" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="expensesMeta" class="pagination">
              <div class="pagination-info">Página {{ expensesMeta.current_page }} / {{ expensesMeta.last_page }} — {{ expensesMeta.total }} gastos</div>
              <div class="pagination-actions">
                <button :disabled="expensesMeta.current_page <= 1" class="icon-btn" @click="loadExpenses(expensesMeta.current_page - 1)">‹</button>
                <button :disabled="expensesMeta.current_page >= expensesMeta.last_page" class="icon-btn" @click="loadExpenses(expensesMeta.current_page + 1)">›</button>
              </div>
            </div>
          </template>
        </section>

        <!-- ============ TARIFAS ============ -->
        <section v-if="activeTab === 'tarifas'">
          <div class="section-copy">
            Define el <strong>coste por hora</strong> de cada profesional. Se usa para calcular el coste laboral y el margen por cita en el dashboard de beneficios.
          </div>

          <AppLoading v-if="ratesLoading" message="Cargando profesionales..." />

          <template v-else>
            <div v-if="professionals.length === 0" class="empty-card">
              No hay profesionales en el equipo. Añádelos desde <router-link to="/team" class="link">Equipo</router-link>.
            </div>

            <div v-else class="table-wrap">
              <table class="entity-table">
                <thead>
                  <tr>
                    <th>Profesional</th>
                    <th>Coste/hora (€)</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="p in professionals" :key="p.id">
                    <td>{{ p.name }}</td>
                    <td class="rate-cell">
                      <input v-model.number="p.cost_per_hour" type="number" min="0" step="0.5" class="input counter-input" @change="markDirty(p)" />
                    </td>
                    <td class="row-action">
                      <button class="btn btn-sm small primary" :disabled="p._saving" @click="saveRate(p)">{{ p._saving ? 'Guardando...' : 'Guardar' }}</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
        </section>

        <!-- ============ BENEFICIOS ============ -->
        <section v-if="activeTab === 'beneficios'">
          <div class="finance-toolbar">
            <label class="date-field">
              <span>Desde</span>
              <input v-model="fromDate" type="date" class="filter-date" aria-label="Desde" />
            </label>
            <label class="date-field">
              <span>Hasta</span>
              <input v-model="toDate" type="date" class="filter-date" aria-label="Hasta" />
            </label>
            <button class="btn btn-sm small primary" @click="loadBenefits" :disabled="benefitsLoading">{{ benefitsLoading ? 'Calculando...' : 'Calcular' }}</button>
          </div>

          <AppLoading v-if="benefitsLoading" message="Calculando beneficios..." />

          <template v-else-if="benefits">
            <div class="benefits-cards">
              <div class="benefit-card">
                <div class="benefit-label">Ingresos</div>
                <div class="benefit-value">{{ formatMoney(benefits.totals.revenue) }}</div>
                <div v-if="variationOf('revenue') !== null" class="variation" :class="variationClass('revenue')">
                  {{ variationText('revenue') }} vs. periodo anterior
                </div>
              </div>
              <div class="benefit-card">
                <div class="benefit-label">Coste personal</div>
                <div class="benefit-value">{{ formatMoney(benefits.totals.labor_cost) }}</div>
              </div>
              <div class="benefit-card">
                <div class="benefit-label">Gastos registrados</div>
                <div class="benefit-value">{{ formatMoney(benefits.totals.expenses) }}</div>
                <div v-if="variationOf('expenses') !== null" class="variation" :class="variationClass('expenses')">
                  {{ variationText('expenses') }} vs. periodo anterior
                </div>
              </div>
              <div class="benefit-card">
                <div class="benefit-label">Coste total</div>
                <div class="benefit-value">{{ formatMoney(benefits.totals.cost) }}</div>
              </div>
              <div class="benefit-card accent" :class="{ negative: (benefits.totals.profit || 0) < 0 }">
                <div class="benefit-label">Beneficio</div>
                <div class="benefit-value">{{ formatMoney(benefits.totals.profit) }}</div>
                <div v-if="variationOf('profit') !== null" class="variation" :class="variationClass('profit')">
                  {{ variationText('profit') }} vs. periodo anterior
                </div>
              </div>
              <div class="benefit-card accent">
                <div class="benefit-label">Margen</div>
                <div class="benefit-value">{{ benefits.totals.margin_percentage === null ? '—' : benefits.totals.margin_percentage + ' %' }}</div>
                <div v-if="variationOf('margin_percentage') !== null" class="variation" :class="variationClass('margin_percentage')">
                  {{ variationText('margin_percentage') }} p.p. vs. periodo anterior
                </div>
              </div>
            </div>

            <div class="benefits-grid">
              <div v-if="benefits.by_service.length" class="table-wrap">
                <h3 class="benefit-section-title">Ingresos por servicio</h3>
                <table class="entity-table">
                  <thead>
                    <tr><th>Servicio</th><th>Citas</th><th>Ingresos</th></tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in benefits.by_service" :key="row.name">
                      <td>{{ row.name }}</td>
                      <td>{{ row.count }}</td>
                      <td>{{ formatMoney(row.revenue) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div v-if="benefits.by_professional.length" class="table-wrap">
                <h3 class="benefit-section-title">Contribución por profesional</h3>
                <table class="entity-table">
                  <thead>
                    <tr><th>Profesional</th><th>Ingresos</th><th>Coste laboral</th><th>Contribución</th></tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in benefits.by_professional" :key="row.user_id">
                      <td>{{ row.user_name }}</td>
                      <td>{{ formatMoney(row.revenue) }}</td>
                      <td>{{ formatMoney(row.labor_cost) }}</td>
                      <td class="total-cell">{{ formatMoney(row.contribution) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div v-if="benefits.by_category.length" class="table-wrap">
                <h3 class="benefit-section-title">Gastos por categoría</h3>
                <table class="entity-table">
                  <thead>
                    <tr><th>Categoría</th><th>Total</th></tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in benefits.by_category" :key="row.name">
                      <td>{{ row.name }}</td>
                      <td>{{ formatMoney(row.total) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div v-if="!benefits.by_service.length && !benefits.by_professional.length && !benefits.by_category.length" class="empty-card">
              No hay datos para el período seleccionado.
            </div>
          </template>
        </section>
      </div>

      <!-- Modal: gasto -->
      <FormModal :show="showExpenseModal" :title="editingExpense ? 'Editar gasto' : 'Nuevo gasto'" @close="showExpenseModal = false">
        <form @submit.prevent="saveExpense">
          <div class="field">
            <label class="label">Concepto *</label>
            <input v-model="expenseForm.concept" class="input" required />
          </div>
          <div class="grid-2">
            <div class="field">
              <label class="label">Categoría</label>
              <select v-model.number="expenseForm.category_id" class="input">
                <option :value="null">Sin categoría</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="field">
              <label class="label">Proveedor</label>
              <input v-model="expenseForm.supplier" class="input" />
            </div>
          </div>
          <div class="grid-2">
            <div class="field">
              <label class="label">Base (sin IVA) *</label>
              <input v-model.number="expenseForm.amount" type="number" min="0" step="0.01" class="input" required />
            </div>
            <div class="field">
              <label class="label">IVA (%)</label>
              <input v-model.number="expenseForm.tax_rate" type="number" min="0" max="100" step="0.01" class="input" />
            </div>
          </div>
          <div class="grid-2">
            <div class="field">
              <label class="label">Fecha</label>
              <input v-model="expenseForm.date" type="date" class="date-field-input" aria-label="Fecha del gasto" />
            </div>
            <div class="field">
              <label class="label">Forma de pago</label>
              <select v-model="expenseForm.payment_method" class="input">
                <option value="">Sin especificar</option>
                <option value="cash">Efectivo</option>
                <option value="card">Tarjeta</option>
                <option value="transfer">Transferencia</option>
              </select>
            </div>
          </div>
          <div class="grid-2">
            <div class="field">
              <label class="label">Nº de factura / recibo</label>
              <input v-model="expenseForm.receipt_number" class="input" />
            </div>
            <div class="field">
              <label class="label">Total (con IVA)</label>
              <div class="total-preview">{{ formatMoney(computedExpenseTotal) }}</div>
            </div>
          </div>
          <div class="field">
            <label class="label">Notas</label>
            <textarea v-model="expenseForm.notes" class="input" rows="2"></textarea>
          </div>
          <div class="actions">
            <SaveButton type="submit" :disabled="savingExpense" :saving="savingExpense" />
            <button type="button" class="muted" @click="showExpenseModal = false">Cancelar</button>
          </div>
        </form>
      </FormModal>

      <!-- Modal: categorías -->
      <FormModal :show="showCategoryModal" title="Categorías de gasto" @close="showCategoryModal = false">
        <div class="cat-row" v-for="c in categories" :key="c.id">
          <span class="category-dot" :style="{ background: c.color || '#9ca3af' }"></span>
          <span class="cat-name">{{ c.name }}</span>
          <span class="cat-desc">{{ c.description || '' }}</span>
          <BtnTrash variant="danger" @click="removeCategory(c)" title="Eliminar categoría" />
        </div>

        <form class="cat-new" @submit.prevent="saveCategory">
          <input v-model="categoryForm.name" class="input" placeholder="Nombre de la categoría" required />
          <input v-model="categoryForm.color" type="color" class="color-input" />
          <NewButton type="submit" label="Añadir" :disabled="savingCategory" />
        </form>
      </FormModal>
    </div>
  </MainLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from 'vue-toastification'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import FormModal from '../../components/FormModal.vue'
import BtnTrash from '../../components/BtnTrash.vue'
import api from '../../services/api'
import { getLoadErrorMessage } from '../../shared/httpErrors'

const toast = useToast()

const tabs = [
  { key: 'gastos', label: 'Gastos' },
  { key: 'tarifas', label: 'Tarifas' },
  { key: 'beneficios', label: 'Beneficios' },
]
const activeTab = ref('gastos')

// ---------- Gastos ----------
const expenses = ref([])
const expensesMeta = ref(null)
const expensesLoading = ref(false)
const expenseQuery = ref('')
const expenseCategoryFilter = ref('')
const categories = ref([])
const showExpenseModal = ref(false)
const editingExpense = ref(null)
const savingExpense = ref(false)
let expenseSearchTimer = null

const emptyExpenseForm = () => ({
  concept: '',
  category_id: null,
  supplier: '',
  amount: null,
  tax_rate: 0,
  date: new Date().toISOString().slice(0, 10),
  payment_method: '',
  receipt_number: '',
  notes: '',
})
const expenseForm = reactive(emptyExpenseForm())

const computedExpenseTotal = computed(() => {
  const base = Number(expenseForm.amount || 0)
  const tax = Number(expenseForm.tax_rate || 0)
  return base * (1 + tax / 100)
})

async function loadCategories() {
  try {
    const res = await api.get('/finance/expense-categories')
    categories.value = Array.isArray(res.data?.data) ? res.data.data : []
  } catch {
    categories.value = []
  }
}

async function loadExpenses(page = 1) {
  expensesLoading.value = true
  try {
    const res = await api.get('/finance/expenses', {
      params: {
        page,
        per_page: 15,
        q: expenseQuery.value || undefined,
        category_id: expenseCategoryFilter.value || undefined,
      },
    })
    expenses.value = Array.isArray(res.data?.data) ? res.data.data : []
    expensesMeta.value = res.data?.meta ?? null
  } catch (e) {
    expenses.value = []
    expensesMeta.value = null
    toast.error(getLoadErrorMessage(e, 'gastos'))
  } finally {
    expensesLoading.value = false
  }
}

function debouncedLoadExpenses() {
  clearTimeout(expenseSearchTimer)
  expenseSearchTimer = setTimeout(() => loadExpenses(1), 250)
}

function openExpenseModal(expense = null) {
  editingExpense.value = expense
  Object.assign(expenseForm, emptyExpenseForm())
  if (expense) {
    Object.assign(expenseForm, {
      concept: expense.concept,
      category_id: expense.category_id ?? null,
      supplier: expense.supplier || '',
      amount: expense.amount,
      tax_rate: expense.tax_rate,
      date: expense.date || new Date().toISOString().slice(0, 10),
      payment_method: expense.payment_method || '',
      receipt_number: expense.receipt_number || '',
      notes: expense.notes || '',
    })
  }
  showExpenseModal.value = true
}

async function saveExpense() {
  savingExpense.value = true
  try {
    const payload = {
      concept: expenseForm.concept,
      category_id: expenseForm.category_id || null,
      supplier: expenseForm.supplier || null,
      amount: Number(expenseForm.amount || 0),
      tax_rate: Number(expenseForm.tax_rate || 0),
      date: expenseForm.date || null,
      payment_method: expenseForm.payment_method || null,
      receipt_number: expenseForm.receipt_number || null,
      notes: expenseForm.notes || null,
    }
    if (editingExpense.value) {
      await api.put(`/finance/expenses/${editingExpense.value.id}`, payload)
      toast.success('Gasto actualizado')
    } else {
      await api.post('/finance/expenses', payload)
      toast.success('Gasto registrado')
    }
    showExpenseModal.value = false
    loadExpenses(expensesMeta.value?.current_page || 1)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error guardando el gasto')
  } finally {
    savingExpense.value = false
  }
}

async function removeExpense(expense) {
  try {
    await api.delete(`/finance/expenses/${expense.id}`)
    toast.success('Gasto eliminado')
    loadExpenses(expensesMeta.value?.current_page || 1)
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error eliminando el gasto')
  }
}

// ---------- Categorías ----------
const showCategoryModal = ref(false)
const savingCategory = ref(false)
const categoryForm = reactive({ name: '', color: '#6366f1' })

function openCategoryModal() {
  categoryForm.name = ''
  showCategoryModal.value = true
}

async function saveCategory() {
  if (!categoryForm.name.trim()) return
  savingCategory.value = true
  try {
    await api.post('/finance/expense-categories', {
      name: categoryForm.name.trim(),
      color: categoryForm.color || null,
    })
    toast.success('Categoría creada')
    categoryForm.name = ''
    await loadCategories()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error creando la categoría')
  } finally {
    savingCategory.value = false
  }
}

async function removeCategory(category) {
  try {
    await api.delete(`/finance/expense-categories/${category.id}`)
    toast.success('Categoría eliminada')
    await loadCategories()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error eliminando la categoría')
  }
}

// ---------- Tarifas ----------
const professionals = ref([])
const ratesLoading = ref(false)

async function loadRates() {
  ratesLoading.value = true
  try {
    const [teamRes, ratesRes] = await Promise.all([
      api.get('/team/users', { params: { per_page: 100 } }),
      api.get('/finance/professional-rates'),
    ])

    const ratesMap = {}
    for (const r of ratesRes.data?.data || []) {
      ratesMap[r.user_id] = Number(r.cost_per_hour || 0)
    }

    professionals.value = (teamRes.data?.data || [])
      .filter(u => u.profile?.slug === 'professional')
      .map(u => ({
        id: u.id,
        name: u.name,
        cost_per_hour: ratesMap[u.id] ?? 0,
        _dirty: false,
        _saving: false,
      }))
  } catch (e) {
    professionals.value = []
    toast.error(getLoadErrorMessage(e, 'profesionales'))
  } finally {
    ratesLoading.value = false
  }
}

function markDirty(p) {
  p._dirty = true
}

async function saveRate(p) {
  p._saving = true
  try {
    await api.put(`/finance/professional-rates/${p.id}`, {
      cost_per_hour: Number(p.cost_per_hour || 0),
    })
    p._dirty = false
    toast.success('Tarifa actualizada')
  } catch (e) {
    toast.error(e.response?.data?.message || 'Error guardando la tarifa')
  } finally {
    p._saving = false
  }
}

// ---------- Beneficios ----------
const fromDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10))
const toDate = ref(new Date().toISOString().slice(0, 10))
const benefits = ref(null)
const benefitsLoading = ref(false)

async function loadBenefits() {
  benefitsLoading.value = true
  benefits.value = null
  try {
    const res = await api.get('/finance/benefits', {
      params: {
        from_date: fromDate.value || undefined,
        to_date: toDate.value || undefined,
      },
    })
    benefits.value = res.data?.data ?? null
  } catch (e) {
    benefits.value = null
    toast.error(getLoadErrorMessage(e, 'beneficios'))
  } finally {
    benefitsLoading.value = false
  }
}

function variationOf(key) {
  const value = benefits.value?.variation?.[key]
  return value === null || value === undefined ? null : Number(value)
}

function variationText(key) {
  const value = variationOf(key)
  const prefix = value > 0 ? '+' : ''
  return `${prefix}${Number(value).toFixed(2)} %`
}

function variationClass(key) {
  const value = variationOf(key)
  return value === 0 ? '' : (value > 0 ? 'up' : 'down')
}

// ---------- Helpers ----------
function formatMoney(value) {
  return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(Number(value || 0))
}

function formatTax(value) {
  return `${Number(value || 0).toFixed(2)} %`
}

function formatDate(value) {
  if (!value) return '—'
  const parts = String(value).split('-')
  if (parts.length !== 3) return value
  return `${parts[2]}/${parts[1]}/${parts[0]}`
}

function paymentLabel(method) {
  const labels = { cash: 'Efectivo', card: 'Tarjeta', transfer: 'Transferencia' }
  return labels[method] || '—'
}

function categoryStyle(category) {
  return { background: (category.color || '#e5e7eb') + '22', color: category.color || '#374151', borderColor: (category.color || '#e5e7eb') }
}

onMounted(async () => {
  await loadCategories()
  await loadExpenses(1)
})
</script>

<style scoped>
.finance-tabs { display: flex; gap: 8px; border-bottom: 1px solid #e5e7eb; margin-bottom: 16px; }
.finance-tab {
  padding: 8px 16px; font-size: 14px; font-weight: 600; color: #6b7280;
  background: none; border: none; border-bottom: 2px solid transparent; cursor: pointer;
}
.finance-tab.active { color: #4338ca; border-bottom-color: #4338ca; }

.finance-toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
.finance-toolbar .search-wrapper { flex: 1; min-width: 220px; }
.toolbar-actions { display: flex; gap: 8px; margin-left: auto; }
.input-sm { min-width: 180px; }
.date-field { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #374151; }
.date-field .input { padding: 8px 10px; }

.section-copy { color: #6b7280; font-size: 14px; margin-bottom: 14px; }
.muted { color: #9ca3af; }
.link { color: #4338ca; }

.concept-cell { font-weight: 600; }
.receipt-num { color: #9ca3af; font-weight: 400; margin-left: 4px; }
.total-cell { font-weight: 700; color: #111827; }
.category-chip { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; border: 1px solid; }

.row-action { display: flex; align-items: center; gap: 8px; }
.action-btn {
  display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 8px;
  color: #374151; font-size: 13px; border: 1px solid #e5e7eb; background: #fff; cursor: pointer;
}
.action-btn.danger { color: #b91c1c; border-color: #fecaca; }

.rate-cell { width: 180px; }
.counter-input { width: 120px; }

.pagination { margin-top: 12px; display: flex; justify-content: flex-end; gap: 12px; align-items: center; }
.pagination-info { color: #6b7280; font-size: 13px; }
.pagination-actions { display: flex; gap: 8px; }
.icon-btn { width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e5e7eb; background: #fff; cursor: pointer; }
.icon-btn:disabled { opacity: 0.45; }

.benefits-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px; }
.benefit-card { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; }
.benefit-card.accent { background: #eef2ff; border-color: #c7d2fe; }
.benefit-card.accent.negative { background: #fef2f2; border-color: #fecaca; }
.benefit-label { font-size: 12px; color: #6b7280; margin-bottom: 4px; }
.benefit-value { font-size: 18px; font-weight: 700; color: #111827; }
.variation { font-size: 12px; font-weight: 600; margin-top: 6px; }
.variation.up { color: #059669; }
.variation.down { color: #b91c1c; }

.benefits-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
.benefit-section-title { font-size: 15px; font-weight: 700; color: #111827; margin: 0 0 8px; }

.field { display: flex; flex-direction: column; margin-bottom: 10px; }
.label { font-weight: 600; margin-bottom: 6px; color: #374151; font-size: 13px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.input { padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; background: #fff; width: 100%; box-sizing: border-box; }
textarea.input { resize: vertical; font-family: inherit; }
.total-preview { padding: 10px 12px; border: 1px dashed #e5e7eb; border-radius: 8px; color: #4338ca; font-weight: 700; background: #fafbff; }
.actions { display: flex; gap: 12px; align-items: center; margin-top: 16px; }
.actions .primary, .actions .muted { padding: 8px 16px; font-size: 14px; border-radius: 9999px; cursor: pointer; }
.actions .primary { background: #4338ca; color: #fff; border: 1px solid #4338ca; font-weight: 600; }
.actions .muted { border: 1px solid #d1d5db; color: #374151; background: #fff; font-weight: 600; }

.cat-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f3f4f6; }
.category-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
.cat-name { font-weight: 600; color: #111827; }
.cat-desc { color: #9ca3af; font-size: 13px; flex: 1; }
.cat-new { display: flex; gap: 8px; margin-top: 14px; align-items: center; }
.color-input { width: 40px; height: 40px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 2px; cursor: pointer; }

@media (max-width: 768px) {
  .grid-2 { grid-template-columns: 1fr; }
  .toolbar-actions { margin-left: 0; width: 100%; }
}
</style>
