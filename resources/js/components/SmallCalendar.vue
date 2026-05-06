<template>
  <div style="display:none"></div>
</template>

<script>
export default {}
</script>

<style scoped>
/* SmallCalendar removed — stub kept to avoid missing-file errors */
</style>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({ modelValue: { type: String, required: false } })
const emit = defineEmits(['update:modelValue'])

const month = ref(props.modelValue ? new Date(props.modelValue) : new Date())

watch(() => props.modelValue, v => {
  if (v) month.value = new Date(v)
})

function startOfMonth(d) { return new Date(d.getFullYear(), d.getMonth(), 1) }
function endOfMonth(d) { return new Date(d.getFullYear(), d.getMonth()+1, 0) }
function startOfWeek(d) { const dt = new Date(d); const day = (dt.getDay() + 6) % 7; dt.setDate(dt.getDate() - day); dt.setHours(0,0,0,0); return dt }
function endOfWeek(d) { const s = startOfWeek(d); const e = new Date(s); e.setDate(s.getDate()+6); e.setHours(23,59,59,999); return e }
function addDays(d, n) { const x = new Date(d); x.setDate(x.getDate() + n); return x }
function isSameDay(a,b){ return a.getFullYear()===b.getFullYear() && a.getMonth()===b.getMonth() && a.getDate()===b.getDate() }
function isSameMonth(a,b){ return a.getFullYear()===b.getFullYear() && a.getMonth()===b.getMonth() }

const monthYear = computed(() => month.value.toLocaleString(undefined, { month: 'short', year: 'numeric' }))
const weekDays = ['L', 'M', 'X', 'J', 'V', 'S', 'D']

const cells = computed(() => {
  const start = startOfWeek(startOfMonth(month.value))
  const end = endOfWeek(endOfMonth(month.value))
  const arr = []
  let cur = new Date(start)
  while (cur <= end) {
    arr.push({ key: cur.toISOString(), date: new Date(cur), label: cur.getDate(), inMonth: isSameMonth(cur, month.value), isToday: isSameDay(cur, new Date()) })
    cur = addDays(cur, 1)
  }
  return arr
})

function select(d) {
  emit('update:modelValue', d.toISOString().slice(0,10))
}

function isSelected(d) {
  if (!props.modelValue) return false
  return isSameDay(new Date(props.modelValue), d)
}

function prevMonth() { month.value = new Date(month.value.getFullYear(), month.value.getMonth()-1, 1) }
function nextMonth() { month.value = new Date(month.value.getFullYear(), month.value.getMonth()+1, 1) }

watch(month, (m) => {
  // no-op
})
</script>

<style scoped>
.small-cal { background:#fff; border-radius:10px; padding:8px; border:1px solid #eef2ff22 }
.cal-header { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:8px }
.cal-title { font-weight:700 }
.cal-grid { display:grid; grid-template-columns: repeat(7,1fr); gap:6px }
.cal-week { color:#6b7280; font-size:12px; text-align:center }
.cal-cell { padding:8px; text-align:center; border-radius:8px; cursor:pointer }
.cal-cell.muted { color:#9ca3af }
.cal-cell.today { border:1px solid #3b82f6 }
.cal-cell.selected { background:#3b82f6; color:#fff }
.icon-btn { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; cursor:pointer }
</style>
