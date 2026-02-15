<template>
  <div class="option-select" :class="{ disabled }">
    <button type="button" class="select-btn input" @click="toggle" :aria-expanded="open" :disabled="disabled">
      <div class="label-wrap">
        <span v-if="selectedOption" class="dot" :style="{ backgroundColor: selectedOption.color }" aria-hidden="true"></span>
        <span class="label-text">{{ selectedOption ? selectedOption.label : placeholder }}</span>
      </div>
      <span class="chev">▾</span>
    </button>

    <ul v-if="open" class="dropdown" role="listbox">
      <li v-for="opt in normalizedOptions" :key="opt.value" class="dropdown-item" role="option" @click="select(opt.value)" :aria-selected="opt.value === modelValue">
        <span class="dot" :style="{ backgroundColor: opt.color }" aria-hidden="true"></span>
        <span class="item-label">{{ opt.label }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number, null], default: null },
  options: { type: [Array, Object], default: () => [] },
  placeholder: { type: String, default: 'Selecciona...' },
  disabled: { type: Boolean, default: false }
})
const emit = defineEmits(['update:modelValue'])

const open = ref(false)

const normalizedOptions = computed(() => {
  if (Array.isArray(props.options)) return props.options.map(o => ({ value: o.value ?? o.key ?? o, label: o.label ?? o.value ?? String(o), color: o.color ?? null }))
  // object form: { key: 'Label' }
  return Object.keys(props.options || {}).map(k => ({ value: k, label: props.options[k], color: null }))
})

const selectedOption = computed(() => normalizedOptions.value.find(o => String(o.value) === String(props.modelValue)) || null)

function toggle() {
  if (props.disabled) return
  open.value = !open.value
}

function select(val) {
  emit('update:modelValue', val)
  open.value = false
}

function onClickOutside(e) {
  const el = e.target
  if (!el) return
  const root = document.querySelector('.option-select')
  // close if click outside this component's root element
  if (root && !root.contains(el)) open.value = false
}

onMounted(() => document.addEventListener('click', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside))
</script>

<style scoped>
.option-select { position:relative; display:inline-block; width:100% }
.select-btn { display:flex; align-items:center; justify-content:space-between; width:100%; cursor:pointer }
.select-btn.disabled { opacity:0.6; pointer-events:none }
.label-wrap { display:flex; align-items:center; gap:8px }
.dot { width:10px; height:10px; border-radius:50%; display:inline-block; background:#e5e7eb }
.label-text { color:#111827 }
.chev { color:#6b7280 }
.dropdown { position:absolute; left:0; right:0; margin-top:8px; background:#fff; border:1px solid #e5e7eb; border-radius:8px; box-shadow:0 8px 20px rgba(2,6,23,0.08); z-index:40; max-height:240px; overflow:auto; padding:6px }
.dropdown-item { display:flex; align-items:center; gap:8px; padding:8px; border-radius:6px; cursor:pointer }
.dropdown-item:hover { background:#f8fafc }
.item-label { color:#374151 }
.option-select.disabled .select-btn { opacity:0.6; pointer-events:none }
</style>
