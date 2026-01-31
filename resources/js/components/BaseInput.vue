<script setup>
defineProps({
  label: String,
  modelValue: [String, Number],
  type: { type: String, default: 'text' },
  autocomplete: { type: String, default: 'off' }
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div class="input-group">
    <label class="input-label">{{ label }}</label>
    <input
      :type="type"
      :value="modelValue"
      @input="e => emit('update:modelValue', e.target.value)"
      :autocomplete="autocomplete"
      class="input-field"
    />
  </div>
</template>

<style scoped>
.input-group { margin-bottom: 16px; }
.input-label { font-size: 13px; color: var(--muted); display: block; margin-bottom: 8px; font-weight: 600 }
.input-field { width: 100%; padding: 12px 14px; border-radius: 10px; border: 1px solid var(--border); background: #fff; transition: box-shadow .15s ease, border-color .15s ease; font-size: 15px }
.input-field::placeholder { color: #9ca3af }
.input-field:focus { outline: none; box-shadow: 0 6px 18px rgba(15,23,42,0.06); border-color: rgba(255,90,90,0.9) }
</style>
