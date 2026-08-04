<template>
  <MainLayout>
    <div class="entity-card">
      <div class="page-header">
        <div style="display:flex;align-items:center;gap:12px">
          <button v-if="returnTo" type="button" class="btn btn-sm" style="padding:6px 12px;font-size:13px" @click="router.push(returnTo)">
            ← Volver
          </button>
          <div>
            <h1>Servicios</h1>
            <div class="form-sub">Gestiona sesiones, bonos y reserva online</div>
          </div>
        </div>
      </div>

      <AppLoading v-if="loading" message="Cargando servicios..." />

      <div v-else>
        <div class="tabs">
            <button :class="['tab', { active: activeTab==='sesiones' }]" @click="activeTab='sesiones'">Sesiones</button>
            <button :class="['tab', { active: activeTab==='bonos' }]" @click="activeTab='bonos'">Bonos</button>
            <button :class="['tab', { active: activeTab==='booking' }]" @click="activeTab='booking'">Reserva Online</button>
          </div>

          <div class="profile-shell">
            <div class="card-stage">
              <div class="tab-panel tab-card" v-show="activeTab==='sesiones'">
                <div class="section-head">
                  <h2>Sesiones</h2>
                  <NewButton v-if="canCreateWithSubscription" label="Nueva Sesión" @click.prevent="addCesionType" title="Agregar tipo" aria-label="Agregar tipo" />
                </div>
                <div style="margin-top:8px;color:#6b7280;font-size:13px">
                  Crea todos los tipos que necesites para tu clinica.
                </div>

                <div class="counter-table-wrap" style="margin-top:14px">
                  <table class="counter-table sesiones-table">
                    <colgroup>
                      <col class="cesion-col-description">
                      <col class="cesion-col-time">
                      <col class="cesion-col-price">
                      <col class="cesion-col-color">
                      <col class="cesion-col-actions">
                    </colgroup>
                    <thead>
                      <tr>
                        <th>Descripcion</th>
                        <th>Tiempo estimado</th>
                        <th>Precio</th>
                        <th>Color</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="item in cesionTypes" :key="item.id">
                        <td data-label="Descripcion">
                          <input class="input counter-input" v-model="item.description" placeholder="Ej: Sesion individual" />
                        </td>
                        <td data-label="Tiempo estimado">
                          <div style="display:grid; grid-template-columns:1fr 1fr; gap:4px">
                            <div>
                              <label style="display:block; font-size:11px; color:#6b7280; margin-bottom:2px">Horas</label>
                              <input class="input counter-input" type="number" min="0" step="1" v-model.number="item.estimated_hours" style="font-size:13px; padding:6px" />
                            </div>
                            <div>
                              <label style="display:block; font-size:11px; color:#6b7280; margin-bottom:2px">Min</label>
                              <input class="input counter-input" type="number" min="0" max="59" step="1" v-model.number="item.estimated_minutes" style="font-size:13px; padding:6px" />
                            </div>
                          </div>
                        </td>
                        <td data-label="Precio">
                          <input class="input counter-input" type="number" min="0" step="0.01" v-model.number="item.price" />
                        </td>
                        <td data-label="Color">
                          <div class="color-palette" style="margin-bottom:0">
                            <button
                                type="button"
                                class="color-option"
                                v-for="c in themeColors"
                                :key="c.value"
                                :style="{ backgroundColor: c.value }"
                                :class="{ selected: item.color === c.value }"
                                @click="item.color = c.value"
                                :title="c.name"
                              ></button>
                          </div>
                        </td>
                        <td data-label="Acciones">
                          <BtnTrash @click.prevent="removeCesionType(item)"></BtnTrash>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div class="sesiones-list" style="margin-top:12px">
                  <div v-if="cesionTypes.length === 0" class="subscription-history-empty">
                    Aun no hay tipos de sesiones. Usa el botón Agregar para crear el primero.
                  </div>
                </div>
              </div>

              <div class="tab-panel tab-card" v-show="activeTab==='bonos'">
                <div class="section-head">
                  <h2>Bonos</h2>
                  <NewButton v-if="canCreateWithSubscription" label="Nuevo Bono" @click.prevent="addBonusType" title="Agregar bono" aria-label="Agregar bono" />
                </div>
                <div style="margin-top:8px;color:#6b7280;font-size:13px">
                  Arma paquete combinando sesiones existentes. Define cantidad por tipo de sesión y el precio final.
                </div>

                <div class="bonus-list" style="margin-top:14px">
                  <div v-for="item in bonusTypes" :key="item.id ?? item._key" class="bonus-card" :class="{ 'accordion-collapsed': item.id && expandedBonusKey !== (item.id ?? item._key) }">
                    <!-- Accordion header -->
                    <div class="bonus-accordion-header" :class="{ clickable: !!item.id }" @click="item.id && toggleBonusAccordion(item.id ?? item._key)">
                      <div class="accordion-header-info">
                        <strong>{{ item.description || 'Sin nombre' }}</strong>
                        <span class="accordion-meta">{{ bonusTotalSessions(item) }} sesiones · {{ bonusDetailsTotal(item).toFixed(2) }}€</span>
                      </div>
                      <div class="accordion-header-actions">
                        <div v-if="!item.id || expandedBonusKey === (item.id ?? item._key)" class="bonus-top-actions">
                          <button class="btn btn-sm bonus-top-btn" type="button" @click.stop="addBonusLine(item)">+ Sesión</button>
                          <BtnTrash variant="danger" class="bonus-top-btn" @click.stop="removeBonusType(item)">Eliminar Bono</BtnTrash>
                        </div>
                        <svg v-if="item.id" class="accordion-chevron" :class="{ open: expandedBonusKey === (item.id ?? item._key) }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                      </div>
                    </div>

                    <!-- Collapsible body -->
                    <div v-if="!item.id || expandedBonusKey === (item.id ?? item._key)" class="bonus-accordion-body">
                      <div class="bonus-pack-top">
                        <div class="bonus-field-inline">
                          <label class="label bonus-inline-label">Nombre</label>
                          <input class="input counter-input" v-model="item.description" placeholder="Ej: Pack bienestar" />
                        </div>
                        <div class="bonus-field-inline bonus-field-inline-price">
                          <label class="label bonus-inline-label">Precio final</label>
                          <input class="input counter-input" type="number" min="0" step="0.01" v-model.number="item.price" />
                        </div>
                      </div>

                      <div class="bonus-lines-wrap">
                        <div class="bonus-lines-head">
                          <span>Cantidad</span>
                          <span>Sesión</span>
                          <span>Precio</span>
                          <span></span>
                        </div>

                        <div v-for="(line, lineIndex) in item.lines" :key="line._key" class="bonus-line-row">
                          <input class="input counter-input" type="number" min="1" step="1" v-model.number="line.quantity" @input="syncBonusAmount(item)" />
                          <select class="input counter-input" v-model="line.cesion_key" @change="applyLineSessionPrice(item, line)">
                            <option value="">Seleccionar sesión</option>
                            <option v-for="(cesion, cesionIndex) in cesionTypes" :key="`bonus-opt-${item.id ?? item._key}-${line._key}-${cesion.id ?? cesionIndex}`" :value="getCesionOptionValue(cesion, cesionIndex)">
                              {{ cesion.description || `Sesión ${cesionIndex + 1}` }}
                            </option>
                          </select>
                          <input class="input counter-input" type="number" min="0" step="0.01" v-model.number="line.unit_price" @input="syncBonusAmount(item)" />
                          <BtnTrash @click.prevent="removeBonusLine(item, lineIndex)"></BtnTrash>
                        </div>
                      </div>

                      <div class="bonus-summary">
                        <span>Total de sesiones del pack:</span>
                        <strong>{{ bonusTotalSessions(item) }}</strong>
                        <span>·</span>
                        <span>Total detalle:</span>
                        <strong>{{ bonusDetailsTotal(item).toFixed(2) }}€</strong>
                      </div>
                    </div>
                  </div>
                </div>

                <div style="margin-top:12px">
                  <div v-if="bonusTypes.length === 0" class="subscription-history-empty">
                    Aún no hay tipos de bono. Usa el botón + para crear el primero.
                  </div>
                </div>
              </div>

              <div class="tab-panel tab-card" v-show="activeTab==='booking'">
                <BookingSettings ref="bookingSettingsRef" :cesionTypes="cesionTypes" />
              </div>
            </div>

            <div class="action-plane">
              <div v-if="activeTab==='sesiones' || activeTab==='bonos' || activeTab==='booking'" class="action-row action-row-save">
                <SaveButton class="save-button" :saving="saving" @click.prevent="save" />
              </div>
              <div v-else class="action-row action-row-empty"></div>
            </div>
          </div>
      </div>
    </div>
  </MainLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import MainLayout from '../../layouts/MainLayout.vue'
import AppLoading from '../../components/AppLoading.vue'
import BtnTrash from '../../components/BtnTrash.vue'
import SaveButton from '../../components/SaveButton.vue'
import BookingSettings from '../settings/BookingSettings.vue'
import api from '../../services/api'
import { useToast } from 'vue-toastification'
import { meUser } from '../../shared/meCache'
import { getLoadErrorMessage } from '../../shared/httpErrors'

const toast = useToast()
const route = useRoute()
const router = useRouter()
const returnTo = route.query.returnTo || null

const loading = ref(true)
const saving = ref(false)
const activeTab = ref('sesiones')
const cesionTypes = ref([])
const bonusTypes = ref([])
const expandedBonusKey = ref(null)
const bookingSettingsRef = ref(null)
const status = ref('active')

const IRISON_COLOR = '#F8FAFC'

const themeColors = [
  { name: 'Irison', value: IRISON_COLOR },
  { name: 'Negro', value: '#CDD6E9' },
  { name: 'Rosa pastel', value: '#FFE0E7' },
  { name: 'Durazno pastel', value: '#FCE0CC' },
  { name: 'Amarillo pastel', value: '#FAF6CD' },
  { name: 'Verde pastel', value: '#E0FFEC' },
  { name: 'Azul pastel', value: '#CAE3FA' },
  { name: 'Lila pastel', value: '#D8C0FA' },
]

onMounted(async () => {
  if (route.query.tab && ['sesiones', 'bonos', 'booking'].includes(route.query.tab)) {
    activeTab.value = route.query.tab
  }
  await load()
})

const canCreateWithSubscription = computed(() => {
  return status.value === 'active' || status.value === 'trial'
})

async function load() {
  loading.value = true
  try {
    const res = await api.get('/company-services')
    const incoming = Array.isArray(res.data.cesiones) ? res.data.cesiones : []
    cesionTypes.value = incoming.map((item) => sanitizeCesionType(item))

    const incomingBonusTypes = Array.isArray(res.data.bonus_types) ? res.data.bonus_types : []
    bonusTypes.value = incomingBonusTypes.map((item) => sanitizeBonusType(item))

    const meRes = await api.get('/me')
    status.value = meRes.data.status || 'active'
  } catch (e) {
    console.error('Error cargando servicios', e)
    toast.error(getLoadErrorMessage(e, 'servicios'))
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    const payload = {
      cesiones: cesionTypes.value.map((item) => ({
        id: item.id != null ? String(item.id) : null,
        description: item.description,
        estimated_hours: item.estimated_hours,
        estimated_minutes: item.estimated_minutes,
        price: item.price,
        color: item.color || null,
      })),
      bonus_types: bonusTypes.value.map((item) => ({
        id: item.id != null ? String(item.id) : null,
        description: item.description,
        sessions: Math.max(bonusTotalSessions(item), 1),
        price: item.price,
        expires_at: null,
        lines: (Array.isArray(item.lines) ? item.lines : []).map((line) => {
          const parsed = parseCesionOptionValue(line?.cesion_key)
          return {
            appointment_type_id: parsed?.id ?? null,
            appointment_type_index: parsed?.index ?? null,
            quantity: Number.isFinite(Number(line?.quantity)) ? Math.max(Number(line.quantity), 1) : 1,
            unit_price: Number.isFinite(Number(line?.unit_price)) ? Math.max(Number(line.unit_price), 0) : 0,
          }
        }),
      })),
    }

    const res = await api.put('/company-services', payload)

    const incoming = Array.isArray(res.data.cesiones) ? res.data.cesiones : []
    cesionTypes.value = incoming.map((item) => sanitizeCesionType(item))

    const incomingBonusTypesSave = Array.isArray(res.data.bonus_types) ? res.data.bonus_types : []
    bonusTypes.value = incomingBonusTypesSave.map((item) => sanitizeBonusType(item))

    if (bookingSettingsRef.value) {
      try {
        await bookingSettingsRef.value.saveBookingSettings()
      } catch (e) {
        toast.error('Error al guardar la configuración de reserva online.')
      }
    }

    toast.success('Servicios guardados')
  } catch (e) {
    console.error('Error guardando servicios', e)
    const msg = e.response?.data?.message || 'Error guardando datos'
    toast.error(msg)
  } finally {
    saving.value = false
  }
}

function makeCesionType() {
  return {
    description: '',
    estimated_hours: 1,
    estimated_minutes: 0,
    price: 0,
    color: '',
  }
}

function sanitizeCesionType(item) {
  return {
    id: item?.id,
    description: (item?.description ?? '').toString(),
    estimated_hours: Number.isFinite(Number(item?.estimated_hours))
      ? Math.max(Number(item.estimated_hours), 0)
      : 0,
    estimated_minutes: Number.isFinite(Number(item?.estimated_minutes))
      ? Math.min(Math.max(Number(item.estimated_minutes), 0), 59)
      : 0,
    price: Number.isFinite(Number(item?.price))
      ? Math.max(Number(item.price), 0)
      : 0,
    color: item?.color ?? '',
  }
}

function addCesionType() {
  cesionTypes.value.push(makeCesionType())
}

let _bonusKey = 0
function makeBonusType() {
  return {
    _key: ++_bonusKey,
    description: '',
    sessions: 1,
    price: 0,
    lines: [makeBonusLine(1)],
  }
}

let _bonusLineKey = 0
function makeBonusLine(quantity = 1, cesionKey = '') {
  return {
    _key: ++_bonusLineKey,
    quantity: Number.isFinite(Number(quantity)) ? Math.max(Number(quantity), 1) : 1,
    cesion_key: String(cesionKey || ''),
    unit_price: 0,
  }
}

function sanitizeBonusType(item) {
  const normalizedSessions = Number.isFinite(Number(item?.sessions)) ? Math.max(Number(item.sessions), 1) : 1
  const incomingLines = Array.isArray(item?.lines) ? item.lines : []
  const lines = incomingLines.length > 0
    ? incomingLines.map((line) => {
      const optionValue = Number.isFinite(Number(line?.appointment_type_id))
        ? `id:${Number(line.appointment_type_id)}`
        : String(line?.cesion_key || '')
      const nextLine = makeBonusLine(line?.quantity, optionValue)
      nextLine.unit_price = Number.isFinite(Number(line?.unit_price)) ? Math.max(Number(line.unit_price), 0) : 0
      return nextLine
    })
    : [makeBonusLine(normalizedSessions)]

  return {
    id: item?.id,
    description: (item?.description ?? '').toString(),
    sessions: normalizedSessions,
    price: Number.isFinite(Number(item?.price)) ? Math.max(Number(item.price), 0) : 0,
    lines,
  }
}

function getCesionOptionValue(cesion, index) {
  if (cesion?.id != null) return `id:${cesion.id}`
  return `draft:${index}`
}

function getCesionFromOptionValue(optionValue) {
  const value = String(optionValue || '')
  if (!value) return null

  if (value.startsWith('id:')) {
    const id = Number(value.slice(3))
    if (!Number.isFinite(id)) return null
    return cesionTypes.value.find((item) => Number(item?.id) === id) || null
  }

  if (value.startsWith('draft:')) {
    const index = Number(value.slice(6))
    if (!Number.isInteger(index) || index < 0) return null
    return cesionTypes.value[index] || null
  }

  return null
}

function parseCesionOptionValue(optionValue) {
  const value = String(optionValue || '')
  if (!value) return null

  if (value.startsWith('id:')) {
    const id = Number(value.slice(3))
    if (!Number.isFinite(id)) return null
    return { id: Math.trunc(id), index: null }
  }

  if (value.startsWith('draft:')) {
    const index = Number(value.slice(6))
    if (!Number.isInteger(index) || index < 0) return null
    return { id: null, index }
  }

  return null
}

function applyLineSessionPrice(item, line) {
  const selected = getCesionFromOptionValue(line?.cesion_key)
  if (!selected) {
    syncBonusAmount(item)
    return
  }
  const nextPrice = Number(selected?.price)
  if (!Number.isFinite(nextPrice)) {
    syncBonusAmount(item)
    return
  }
  line.unit_price = Math.max(nextPrice, 0)
  syncBonusAmount(item)
}

function syncBonusAmount(item) {
  item.price = Number(bonusDetailsTotal(item).toFixed(2))
}

function bonusTotalSessions(item) {
  const lines = Array.isArray(item?.lines) ? item.lines : []
  return lines.reduce((total, line) => {
    const qty = Number.isFinite(Number(line?.quantity)) ? Math.max(Number(line.quantity), 0) : 0
    return total + qty
  }, 0)
}

function bonusDetailsTotal(item) {
  const lines = Array.isArray(item?.lines) ? item.lines : []
  return lines.reduce((total, line) => {
    const qty = Number.isFinite(Number(line?.quantity)) ? Math.max(Number(line.quantity), 0) : 0
    const unitPrice = Number.isFinite(Number(line?.unit_price)) ? Math.max(Number(line.unit_price), 0) : 0
    return total + (qty * unitPrice)
  }, 0)
}

function addBonusLine(item) {
  if (!Array.isArray(item.lines)) {
    item.lines = [makeBonusLine(1)]
    syncBonusAmount(item)
    return
  }
  item.lines.push(makeBonusLine(1))
  syncBonusAmount(item)
}

function removeBonusLine(item, lineIndex) {
  if (!Array.isArray(item?.lines) || item.lines.length <= 1) {
    item.lines = [makeBonusLine(1)]
    syncBonusAmount(item)
    return
  }
  item.lines.splice(lineIndex, 1)
  syncBonusAmount(item)
}

function addBonusType() {
  const item = makeBonusType()
  bonusTypes.value.push(item)
  expandedBonusKey.value = item._key
}

function toggleBonusAccordion(key) {
  expandedBonusKey.value = expandedBonusKey.value === key ? null : key
}

function removeBonusType(item) {
  if (item.id != null) {
    bonusTypes.value = bonusTypes.value.filter((b) => b !== item)
  } else {
    bonusTypes.value = bonusTypes.value.filter((b) => b !== item)
  }
}

function removeCesionType(item) {
  if (item.id == null) {
    cesionTypes.value.pop()
    return
  }
  cesionTypes.value = cesionTypes.value.filter((i) => i.id !== item.id)
}
</script>

<style scoped>
.profile-shell {
  display: grid;
  gap: 14px;
}

.card-stage {
  min-height: 560px;
}

.tabs {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 6px;
  margin-bottom: 12px;
}

.tab {
  width: 100%;
  text-align: center;
  padding: 8px 10px;
  border-radius: 8px;
  background: transparent;
  border: 1px solid transparent;
  cursor: pointer;
}

.tab.active {
  background: #eef2ff;
  border-color: #c7d2fe;
  font-weight: 600;
}

.tab-panel {
  background: transparent;
}

.tab-card {
  min-height: 560px;
  width: 100%;
  padding: 20px;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 10px 30px rgba(2, 6, 23, 0.06);
}

.action-plane {
  position: sticky;
  bottom: 16px;
  padding: 12px 16px;
  border: 1px solid #e5e7eb;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.96);
  box-shadow: 0 12px 28px rgba(2, 6, 23, 0.08);
  backdrop-filter: blur(8px);
}

.action-row {
  display: flex;
  gap: 8px;
  min-height: 38px;
  align-items: center;
}

.action-row-save {
  justify-content: center;
}

.save-button {
  width: 50%;
}

.action-row-empty {
  justify-content: flex-end;
}

.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.section-head h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: #111827;
}

.counter-table-wrap {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
}

.counter-table {
  width: 100%;
  border-collapse: collapse;
}

.counter-table th,
.counter-table td {
  padding: 10px;
  border-bottom: 1px solid #f3f4f6;
  text-align: left;
  font-size: 13px;
  vertical-align: middle;
}

.counter-table th {
  background: #f9fafb;
  color: #6b7280;
  font-weight: 600;
}

.input {
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  font-size: 14px;
  width: 100%;
  box-sizing: border-box;
  font-family: inherit;
}

.input:focus {
  outline: none;
  border-color: #3b82f6;
}

.counter-input {
  padding: 6px 10px;
  font-size: 13px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  width: 100%;
  box-sizing: border-box;
  font-family: inherit;
}

.color-palette {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}

.color-option {
  width: 28px;
  height: 28px;
  border: 2px solid transparent;
  border-radius: 6px;
  cursor: pointer;
  padding: 0;
  transition: all 0.2s;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.color-option:hover {
  transform: scale(1.05);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.color-option.selected {
  border-color: #111827;
  box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.1), 0 4px 12px rgba(0, 0, 0, 0.15);
}

.cesion-col-description { width: 32%; }
.cesion-col-time { width: 20%; }
.cesion-col-price { width: 16%; }
.cesion-col-color { width: 24%; }
.cesion-col-actions { width: 8%; }

.bonus-list {
  display: grid;
  gap: 12px;
}

.bonus-card {
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 12px;
  display: grid;
  gap: 12px;
  background: #fff;
  transition: box-shadow 0.2s;
}

.bonus-card.accordion-collapsed {
  padding: 0;
  gap: 0;
}

.bonus-accordion-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px;
  min-height: 44px;
}

.bonus-accordion-header.clickable {
  cursor: pointer;
  border-radius: 12px;
  user-select: none;
}

.bonus-accordion-header.clickable:hover {
  background: #f9fafb;
}

.accordion-header-info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.accordion-header-info strong {
  font-size: 14px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.accordion-meta {
  font-size: 12px;
  color: #6b7280;
}

.accordion-header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.accordion-chevron {
  width: 18px;
  height: 18px;
  color: #9ca3af;
  transition: transform 0.2s ease;
  flex-shrink: 0;
}

.accordion-chevron.open {
  transform: rotate(180deg);
}

.bonus-accordion-body {
  padding: 0 12px 12px 12px;
  display: grid;
  gap: 12px;
}

.bonus-pack-top {
  display: grid;
  grid-template-columns: minmax(380px, 1.5fr) minmax(140px, 0.45fr);
  gap: 10px;
  align-items: end;
}

.bonus-top-actions {
  display: flex;
  gap: 6px;
  margin-left: auto;
  order: 1;
}

.bonus-top-btn {
  font-size: 12px;
  padding: 4px 10px;
}

.bonus-field-inline {
  display: grid;
  gap: 4px;
}

.bonus-inline-label {
  white-space: nowrap;
  margin-bottom: 0;
  font-size: 13px;
}

.bonus-field-inline-price {
  max-width: 180px;
}

.bonus-lines-wrap {
  margin-top: 12px;
}

.bonus-lines-head {
  display: grid;
  grid-template-columns: 70px 1fr 100px 36px;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: #6b7280;
  padding: 4px 0;
}

.bonus-line-row {
  display: grid;
  grid-template-columns: 70px 1fr 100px 36px;
  gap: 6px;
  align-items: center;
  margin-top: 6px;
}

.bonus-summary {
  margin-top: 10px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  color: #374151;
}

.subscription-history-empty {
  color: #6b7280;
  font-size: 13px;
  padding: 10px;
  border: 1px dashed #d1d5db;
  border-radius: 8px;
}

@media (max-width: 980px) {
  .tabs {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .tabs {
    grid-template-columns: repeat(2, 1fr);
  }

  .tab-card {
    min-height: auto;
  }

  .card-stage {
    min-height: auto;
  }

  .action-plane {
    position: static;
  }

  .counter-table thead {
    display: none;
  }

  .counter-table td {
    display: block;
    padding: 8px 10px;
    border-bottom: 1px solid #e5e7eb;
  }

  .counter-table td:before {
    content: attr(data-label);
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 2px;
  }

  .counter-table tr {
    display: block;
    padding: 8px 0;
    border-bottom: 1px solid #e5e7eb;
  }

  .bonus-pack-top {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 600px) {
  .bonus-lines-head,
  .bonus-line-row {
    grid-template-columns: 60px 1fr 70px 30px;
  }
}
</style>
