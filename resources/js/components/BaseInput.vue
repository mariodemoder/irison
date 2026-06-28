<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  label: String,
  modelValue: [String, Number],
  type: { type: String, default: 'text' },
  autocomplete: { type: String, default: 'off' },
  showPasswordToggle: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue'])

const showPassword = ref(false)

const inputType = computed(() => {
  if (props.type === 'password' && showPassword.value) return 'text'
  return props.type
})

function hidePassword() {
  showPassword.value = false
}
</script>

<template>
  <div class="input-group">
    <label class="input-label">{{ label }}</label>
    <div class="input-password-wrap" :class="{ 'has-toggle': type === 'password' && showPasswordToggle }">
      <input
        :type="inputType"
        :value="modelValue"
        @input="e => emit('update:modelValue', e.target.value)"
        :autocomplete="autocomplete"
        class="input-field"
      />
      <button
        v-if="type === 'password' && showPasswordToggle"
        type="button"
        class="password-toggle"
        @mousedown.prevent="showPassword = true"
        @mouseup="hidePassword"
        @mouseleave="hidePassword"
        @touchstart.prevent="showPassword = true"
        @touchend="hidePassword"
        tabindex="-1"
      >
        <svg
          v-if="showPassword"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
          <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
          <line x1="1" y1="1" x2="23" y2="23"/>
          <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
        </svg>
        <svg
          v-else
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
          <circle cx="12" cy="12" r="3"/>
        </svg>
      </button>
    </div>
  </div>
</template>

<style scoped>
.input-password-wrap {
  position: relative;
}
.input-password-wrap.has-toggle .input-field {
  padding-right: 40px;
}
.password-toggle {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  display: none;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--text-muted, #6b7280);
  cursor: pointer;
  border-radius: 6px;
}
.has-toggle .password-toggle {
  display: flex;
}
.password-toggle svg {
  width: 20px;
  height: 20px;
}
.password-toggle:hover {
  background: rgba(0, 0, 0, 0.05);
}
</style>
