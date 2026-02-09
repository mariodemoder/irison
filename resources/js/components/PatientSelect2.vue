<template>
  <div class="search-wrapper patient-select" @keydown.stop.prevent="onKeydown">
    <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
    <input
      ref="input"
      type="text"
      :placeholder="placeholder || 'Buscar paciente por nombre o NIF'"
      v-model="query"
      @focus="open()"
      @input="onInput"
      @blur="onBlur"
      class="search-input"
      autocomplete="off"
    />

    <ul v-if="show && suggestions.length" class="autocomplete-dropdown">
      <li v-for="(s, idx) in suggestions" :key="s.id" :class="{ highlighted: idx === index }" @mousedown.prevent="select(s)">
        <div class="item-name">{{ s.name }}</div>
        <div class="item-sub">{{ s.nif ?? '—' }}</div>
      </li>
    </ul>

    <ul v-if="show && !suggestions.length && query.trim().length" class="autocomplete-dropdown">
      <li class="create-item" @mousedown.prevent="createPatient">
        <div class="item-name">Crear paciente "{{ query }}"</div>
        <div class="item-sub">Crear nuevo paciente con este nombre</div>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import api from '../services/api'
import { useToast } from 'vue-toastification'

const props = defineProps({ modelValue: [String, Number], placeholder: String })
const emit = defineEmits(['update:modelValue'])

const query = ref('')
const suggestions = ref([])
const show = ref(false)
const loading = ref(false)
const creating = ref(false)
const index = ref(0)
const input = ref(null)
let debounceTimer = null
const toast = useToast()

async function fetchSuggestions(q) {
  if (!q) { suggestions.value = []; return }
  loading.value = true
  try {
    const res = await api.get('/patients', { params: { q, per_page: 8 } })
    const data = res.data.data || []
    suggestions.value = data
  } catch (e) {
    suggestions.value = []
  } finally {
    loading.value = false
  }
}

function onInput() {
  show.value = true
  index.value = 0
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchSuggestions(query.value.trim()), 250)
}

function open() { show.value = true }
function close() { show.value = false }

function onBlur() {
  // small timeout to allow click selection
  setTimeout(() => close(), 150)
}

function select(item) {
  emit('update:modelValue', item.id)
  query.value = item.name
  close()
}

async function createPatient() {
  const name = (query.value || '').trim()
  if (!name) return
  creating.value = true
  try {
    const res = await api.post('/patients', { name })
    const patient = res.data || {}
    toast.success('Paciente creado')
    select(patient)
  } catch (e) {
    const msg = e.response?.data?.message || 'Error creando paciente'
    toast.error(msg)
  } finally {
    creating.value = false
  }
}

function onKeydown(e) {
  if (!show.value) return
  if (e.key === 'ArrowDown') { index.value = Math.min(index.value + 1, suggestions.value.length - 1); e.preventDefault() }
  else if (e.key === 'ArrowUp') { index.value = Math.max(index.value - 1, 0); e.preventDefault() }
  else if (e.key === 'Enter') {
    const s = suggestions.value[index.value]
    if (s) select(s)
    else if (!suggestions.value.length) createPatient()
  }
  else if (e.key === 'Escape') { close() }
}

// If modelValue is set (e.g., editing), fetch patient to display name
onMounted(async () => {
  if (props.modelValue) {
    try {
      const res = await api.get(`/patients/${props.modelValue}`)
      query.value = res.data.name || ''
    } catch (e) {
      // ignore
    }
  }
})

watch(() => props.modelValue, async (v) => {
  if (!v) { query.value = '' }
  else {
    try {
      const res = await api.get(`/patients/${v}`)
      query.value = res.data.name || ''
    } catch (e) {}
  }
})
</script>

<style scoped>
.patient-select { width:100%; max-width:480px }
.autocomplete-dropdown { position:absolute; left:0; right:0; margin-top:8px; background:#fff; border-radius:8px; box-shadow:0 10px 30px rgba(2,6,23,0.08); list-style:none; padding:6px; z-index:40; max-height:260px; overflow:auto; border:1px solid #eef2ff22 }
.autocomplete-dropdown li { padding:8px 10px; border-radius:8px; display:flex; justify-content:space-between; gap:8px; cursor:pointer }
.autocomplete-dropdown li.highlighted { background:#f8fafc }
.create-item { font-weight:700; display:flex; justify-content:space-between }
.item-name { font-weight:600 }
.item-sub { color:#6b7280; font-size:13px }
</style>
