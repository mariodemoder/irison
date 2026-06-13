<template>
  <div class="entity-select" ref="container">
    <div class="es-input-wrap">
      <input
        ref="inputEl"
        v-model="searchQuery"
        type="text"
        class="input"
        :placeholder="placeholder"
        @input="onInput"
        @focus="open = true"
        @blur="onBlur"
        @keydown.escape="open = false"
        autocomplete="off"
      />
      <div v-if="open && hasContent" class="es-dropdown">
        <div v-if="loading" class="es-loading">Buscando…</div>
        <template v-else>
          <div
            v-for="item in items"
            :key="item.value"
            class="es-item"
            @mousedown.prevent="selectItem(item)"
          >
            <span class="es-label">{{ item.label }}</span>
            <span v-if="item.sublabel" class="es-sublabel">{{ item.sublabel }}</span>
          </div>
          <div v-if="items.length && searchQuery.length" class="es-divider"></div>
          <div
            v-if="searchQuery.length && allowCustom"
            class="es-item es-action"
            @mousedown.prevent="chooseCustom"
          >
            Otro: <strong>"{{ searchQuery }}"</strong>
          </div>
          <div
            v-if="allowCreate"
            class="es-item es-action"
            @mousedown.prevent="$emit('create'), reset()"
          >
            + Crear {{ entityLabel }}
          </div>
        </template>
      </div>
    </div>
    <div v-if="customMode" class="es-custom-panel">
      <div class="es-custom-row">
        <input v-model="customDescription" class="input" :placeholder="'Descripción del ' + entityLabel" />
        <input v-model.number="customPrice" type="number" step="0.01" min="0" class="input es-custom-price" placeholder="Precio €" />
        <input v-model.number="customTax" type="number" step="0.01" min="0" max="100" class="input es-custom-tax" placeholder="IVA %" />
      </div>
      <div class="es-custom-actions">
        <button class="muted" @click="cancelCustom">Cancelar</button>
        <button class="primary" @click="confirmCustom" :disabled="!customDescription.trim()">Añadir</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  placeholder: { type: String, default: 'Buscar…' },
  entityLabel: { type: String, default: 'elemento' },
  allowCustom: { type: Boolean, default: true },
  allowCreate: { type: Boolean, default: true },
})

const emit = defineEmits(['update:query', 'select', 'custom', 'create'])

const inputEl = ref(null)
const container = ref(null)
const open = ref(false)
const searchQuery = ref('')
const customMode = ref(false)
const customDescription = ref('')
const customPrice = ref(0)
const customTax = ref(0)

const hasContent = computed(() => {
  return props.loading || props.items.length > 0 || searchQuery.value.length > 0
})

function onInput() {
  open.value = true
  emit('update:query', searchQuery.value)
}

function onBlur() {
  setTimeout(() => { open.value = false }, 200)
}

function selectItem(item) {
  emit('select', item.payload)
  reset()
}

function chooseCustom() {
  customDescription.value = searchQuery.value
  customMode.value = true
  searchQuery.value = ''
  open.value = false
}

function confirmCustom() {
  emit('custom', {
    description: customDescription.value.trim(),
    price: customPrice.value,
    tax: customTax.value,
  })
  cancelCustom()
}

function cancelCustom() {
  customMode.value = false
  customDescription.value = ''
  customPrice.value = 0
  customTax.value = 0
  inputEl.value?.focus()
}

function reset() {
  searchQuery.value = ''
  customMode.value = false
  customDescription.value = ''
  customPrice.value = 0
  customTax.value = 0
  open.value = false
}

defineExpose({ reset })
</script>

<style scoped>
.entity-select { position:relative; width:100% }
.es-input-wrap { position:relative }
.es-input-wrap .input { width:100%; box-sizing:border-box }

.es-dropdown {
  position:absolute; top:calc(100% + 4px); left:0; right:0;
  background:#fff; border:1px solid #e5e7eb; border-radius:10px;
  box-shadow:0 8px 24px rgba(2,6,23,0.12);
  max-height:260px; overflow-y:auto; z-index:100;
  padding:6px;
}
.es-loading { padding:12px; text-align:center; color:#6b7280; font-size:13px }
.es-item {
  display:flex; align-items:center; gap:8px;
  padding:8px 10px; border-radius:8px; cursor:pointer;
}
.es-item:hover { background:#eff6ff }
.es-label { font-size:14px; color:#111827 }
.es-sublabel { font-size:12px; color:#6b7280; margin-left:auto; white-space:nowrap }
.es-action { color:#3b82f6; font-size:13px; font-weight:600 }
.es-divider { height:1px; background:#f3f4f6; margin:4px 0 }

.es-custom-panel {
  margin-top:8px; padding:12px; background:#f9fafb; border:1px solid #e5e7eb;
  border-radius:10px;
}
.es-custom-row { display:flex; gap:8px; margin-bottom:8px }
.es-custom-row .input { flex:1 }
.es-custom-price { max-width:120px }
.es-custom-tax { max-width:90px }
.es-custom-actions { display:flex; gap:8px; justify-content:flex-end }
</style>
