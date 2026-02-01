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

<!-- Styles are provided globally in resources/css/theme.css -->
