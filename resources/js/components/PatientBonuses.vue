<template>
  <div class="patient-bonuses">
    <div style="display:flex; justify-content:space-between; align-items:center">
      <h3 style="margin:0"></h3>
      <div style="display:flex;gap:8px;align-items:center">
        <button
          class="toggle-canceled-btn"
          @click="toggleInactiveVisibility"
          :title="showInactiveBonuses ? 'Ocultar bonos expirados o agotados' : 'Ver bonos expirados o agotados'"
        >
          🔎
        </button>
        <button @click="showForm = !showForm" class="primary" style="padding:6px 10px;font-size:13px">Crear</button>
      </div>
    </div>

    <div v-if="showForm" style="margin-top:8px">
      <div class="create-card">
        <form @submit.prevent="create" class="create-form">
          <div class="create-row">
            <label>Nombre</label>
            <input v-model="form.name" type="text" required />
          </div>
          <div class="create-row">
            <label>Nº sesiones</label>
            <input v-model.number="form.total_sessions" type="number" min="1" required />
          </div>
          <div class="create-row">
            <label>Precio</label>
            <input v-model.number="form.price" type="number" step="0.01" min="0" />
          </div>
          <div class="create-row">
            <label>Expira (opcional)</label>
            <input v-model="form.expires_at" type="date" />
          </div>
          <div class="create-actions">
            <button type="submit" class="primary">Crear</button>
            <button type="button" class="muted" @click="cancelForm">Cancelar</button>
          </div>
        </form>
      </div>
    </div>

    <div class="bonus-list">
      <div
        v-for="b in visibleBonuses"
        :key="b.id"
        class="bonus-card"
        :class="[b.status, { new: b.justCreated }]"
      >
        <div class="bonus-header">
          <div>
            <strong>{{ b.name || 'Bono' }}</strong>
          </div>
          <div class="bonus-badge">
            {{ statusLabel(b.status) }}
          </div>
        </div>

        <div class="bonus-body">
          <div>
            <span class="big-number">{{ b.remaining_sessions }}</span>
            <span class="muted-text">/ {{ b.total_sessions }} sesiones</span>
          </div>
          <div class="expiry">
            Expira: {{ b.expires_at ? formatDMY(b.expires_at) : '—' }}
          </div>
        </div>

        <div class="bonus-actions">
          <button
            v-if="b.status === 'last'"
            class="renew-btn"
            @click="prefillRenew(b)"
          >
            Renovar
          </button>

          <button @click="confirmDeleteBonus(b)" class="action-btn">
            🗑️
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import Swal from 'sweetalert2'
import api from '../services/api'
import { useToast } from 'vue-toastification'
import { formatDMY } from '../shared/dateHelpers'

const props = defineProps({ patientId: { type: [String, Number], required: true } })
const emit = defineEmits(['active-bonus-count'])
const bonuses = ref([])
const showForm = ref(false)
const showInactiveBonuses = ref(false)
const form = ref({ name: 'Bono', total_sessions: 1, price: 0, expires_at: '' })
const toast = useToast()

function statusLabel(status) {
  switch (status) {
    case 'active': return 'Activo'
    case 'last': return 'Última sesión'
    case 'exhausted': return 'Agotado'
    case 'expired': return 'Expirado'
    default: return ''
  }
}

function normalizeBonus(b) {
  return {
    id: b.id,
    name: b.name ?? null,
    total_sessions: b.total_sessions != null ? Number(b.total_sessions) : 0,
    remaining_sessions: b.remaining_sessions != null ? Number(b.remaining_sessions) : 0,
    expires_at: b.expires_at ?? null,
    status: b.status ?? (b.remaining_sessions <= 0 ? 'exhausted' : 'active'),
    justCreated: b.justCreated ?? false,
  }
}

const visibleBonuses = computed(() => {
  const filtered = showInactiveBonuses.value
    ? bonuses.value
    : bonuses.value.filter(b => b.status !== 'expired' && b.status !== 'exhausted')

  const order = { active: 0, last: 1, exhausted: 2, expired: 2 }
  return [...filtered].sort((a, b) => {
    const oa = order[a.status] ?? 99
    const ob = order[b.status] ?? 99
    if (oa !== ob) return oa - ob
    return (b.id || 0) - (a.id || 0)
  })
})

function toggleInactiveVisibility() {
  showInactiveBonuses.value = !showInactiveBonuses.value
}

function emitActiveCount() {
  const count = bonuses.value.filter(b => b.status === 'active' || b.status === 'last').length
  emit('active-bonus-count', count)
}

watch(bonuses, () => emitActiveCount(), { immediate: true })



async function load() {
  try {
    const res = await api.get(`/patients/${props.patientId}/bonuses`)
    bonuses.value = Array.isArray(res.data.data) ? res.data.data.map(normalizeBonus) : []
  } catch (e) {
    bonuses.value = []
  }
}

function cancelForm() {
  showForm.value = false
  form.value = { name: 'Bono', total_sessions: 1, price: 0, expires_at: '' }
}

async function create() {
  try {
    const bonus = { ...form.value }
    if (!bonus.expires_at) delete bonus.expires_at
    const res = await api.post(`/patients/${props.patientId}/bonuses`, bonus)
    const b = (res.data && res.data.data) ? res.data.data : res.data
    const nb = normalizeBonus(b)
    nb.justCreated = true
    bonuses.value.unshift(nb)
    // remove highlight after a short delay
    setTimeout(() => { nb.justCreated = false }, 4000)
    toast.success('Bono creado')
    cancelForm()
  } catch (e) {
    toast.error('Error creando bono')
  }
}

async function deleteBonus(id) {
  try {
    await api.delete(`/bonuses/${id}`)
    bonuses.value = bonuses.value.filter(b => b.id !== id)
    toast.success('Bono eliminado')
  } catch (e) {
    toast.error('Error eliminando bono')
  }
}

async function confirmDeleteBonus(bonus) {
  const res = await Swal.fire({
    title: `Eliminar bono`,
    text: `¿Eliminar el bono de ${bonus.total_sessions} sesiones?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  })

  if (!res.isConfirmed) return
  await deleteBonus(bonus.id)
}

onMounted(load)

function prefillRenew(b) {
  form.value.name = b.name || 'Bono'
  form.value.total_sessions = b.total_sessions != null ? Number(b.total_sessions) : 1
  form.value.price = b.price != null ? b.price : 0
  form.value.expires_at = ''
  showForm.value = true
  // focus could be added if needed
}
</script>

<style scoped>
.patient-bonuses label { display:block; font-weight:600; margin-bottom:4px }
.patient-bonuses input { padding:8px; border:1px solid #e5e7eb; border-radius:6px }
.primary { padding:6px 12px; border-radius:9999px; border:2px solid #3b82f6; color:#3b82f6; background:#fff }
.muted { padding:6px 12px; border-radius:8px; border:1px solid #e5e7eb; background:#fff }
.action-btn { padding:4px 8px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; font-size:12px }

.toggle-canceled-btn {
  width:32px;
  height:32px;
  border-radius:9999px;
  border:1px solid #fca5a5;
  color:#f87171;
  background:transparent;
  font-size:13px;
  display:flex;
  align-items:center;
  justify-content:center;
  opacity:0.9;
}

.toggle-canceled-btn:hover {
  background:transparent;
  border-color:#f87171;
  color:#ef4444;
}

/* 6️⃣ Estilos UX profesional */
.bonus-list {
  margin-top:12px;
  display:flex;
  flex-direction:column;
  gap:10px;
}

.bonus-card {
  padding:14px;
  border-radius:12px;
  border:1px solid #e5e7eb;
  background:#fff;
  transition:all 0.2s ease;
}

.bonus-card.active {
  background:#f0fdf4;
  border-color:#bbf7d0;
}

.bonus-card.last {
  background:#fffbeb;
  border-color:#fde68a;
}

.bonus-card.exhausted {
  background:#fef2f2;
  border-color:#fecaca;
  opacity:0.85;
}

/* Newly created highlight */
.bonus-card.new {
  background: #f3f4f6; /* light gray */
  border-color: #e5e7eb;
  box-shadow: 0 6px 18px rgba(2,6,23,0.04);
}

.bonus-header {
  display:flex;
  justify-content:space-between;
  align-items:center;
}

.bonus-badge {
  padding:4px 8px;
  border-radius:9999px;
  font-size:11px;
  font-weight:700;
}

.big-number {
  font-size:20px;
  font-weight:700;
  margin-right:6px;
}

.muted-text {
  color:#64748b;
  font-size:13px;
}

.expiry {
  font-size:12px;
  color:#6b7280;
  margin-top:4px;
}

.bonus-actions {
  margin-top:8px;
  display:flex;
  justify-content:space-between;
  align-items:center;
}

.renew-btn {
  background:#f59e0b;
  color:white;
  border:none;
  padding:6px 10px;
  border-radius:9999px;
  font-size:12px;
  font-weight:600;
}

/* Create form card */
.create-card {
  padding:12px;
  border-radius:10px;
  border:1px solid #e5e7eb;
  background:#f8fafc;
}
.create-row { margin-bottom:8px }
.create-row label { display:block; font-weight:600; margin-bottom:6px }
.create-row input { width:100%; padding:8px; border:1px solid #e5e7eb; border-radius:6px }
.create-actions { margin-top:10px; display:flex; gap:8px }

</style>
