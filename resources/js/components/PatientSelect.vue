<template>
  <select
    :value="modelValue == null ? '' : String(modelValue)"
    @change="handleChange"
    v-bind="$attrs"
  >
    <option value="" :disabled="required">{{ placeholder }}</option>
    <option
      v-for="patient in normalizedPatients"
      :key="patient.id"
      :value="String(patient.id)"
    >
      {{ formatPatientLabel(patient) }}
    </option>
    <option v-if="allowCreate" value="__create">+ Crear paciente...</option>
  </select>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: '',
  },
  patients: {
    type: Array,
    default: () => [],
  },
  currentPatient: {
    type: Object,
    default: null,
  },
  placeholder: {
    type: String,
    default: 'Selecciona paciente',
  },
  allowCreate: {
    type: Boolean,
    default: true,
  },
  required: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'change'])

const normalizedPatients = computed(() => {
  const rows = Array.isArray(props.patients) ? [...props.patients] : []
  const currentId = props.currentPatient?.id != null ? String(props.currentPatient.id) : ''
  const exists = currentId && rows.some(patient => String(patient.id) === currentId)

  if (!exists && props.currentPatient?.id != null) {
    rows.unshift(props.currentPatient)
  }

  return rows
})

function formatPatientLabel(patient) {
  const prefix = patient?.counter ? `${patient.counter} · ` : ''
  const name = patient?.name || `Paciente #${patient?.id ?? ''}`
  const nif = patient?.nif ? ` — ${patient.nif}` : ''
  return `${prefix}${name}${nif}`
}

function handleChange(event) {
  const value = event.target.value
  emit('update:modelValue', value)
  emit('change', value)
}
</script>