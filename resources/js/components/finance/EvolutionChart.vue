<template>
  <div class="evolution-chart-wrap">
    <Line :data="chartData" :options="chartOptions" />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
  Legend,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip, Legend)

const props = defineProps({
  evolution: { type: Array, default: () => [] },
})

const monthLabels = {
  '01': 'Ene', '02': 'Feb', '03': 'Mar', '04': 'Abr',
  '05': 'May', '06': 'Jun', '07': 'Jul', '08': 'Ago',
  '09': 'Sep', '10': 'Oct', '11': 'Nov', '12': 'Dic',
}

function formatMonth(raw) {
  const parts = raw.split('-')
  return monthLabels[parts[1]] + ' ' + parts[0].slice(2)
}

const chartData = computed(() => ({
  labels: props.evolution.map(e => formatMonth(e.month)),
  datasets: [
    {
      label: 'Ingresos',
      data: props.evolution.map(e => e.revenue),
      borderColor: '#4338ca',
      backgroundColor: 'rgba(67, 56, 202, 0.08)',
      fill: true,
      tension: 0.3,
      pointRadius: 3,
      pointBackgroundColor: '#4338ca',
    },
    {
      label: 'Gastos',
      data: props.evolution.map(e => e.expenses),
      borderColor: '#dc2626',
      backgroundColor: 'rgba(220, 38, 38, 0.06)',
      fill: true,
      tension: 0.3,
      pointRadius: 3,
      pointBackgroundColor: '#dc2626',
    },
    {
      label: 'Beneficio',
      data: props.evolution.map(e => e.profit),
      borderColor: '#059669',
      backgroundColor: 'rgba(5, 150, 105, 0.06)',
      fill: true,
      tension: 0.3,
      pointRadius: 3,
      pointBackgroundColor: '#059669',
    },
  ],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { intersect: false, mode: 'index' },
  plugins: {
    legend: {
      position: 'top',
      labels: { boxWidth: 12, padding: 16, font: { size: 12 } },
    },
    tooltip: {
      callbacks: {
        label(ctx) {
          return ctx.dataset.label + ': ' + new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(ctx.raw)
        },
      },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { font: { size: 11 } },
    },
    y: {
      beginAtZero: true,
      grid: { color: '#f3f4f6' },
      ticks: {
        font: { size: 11 },
        callback(val) {
          return new Intl.NumberFormat('es-ES', { notation: 'compact', maximumFractionDigits: 0 }).format(val) + ' €'
        },
      },
    },
  },
}
</script>

<style scoped>
.evolution-chart-wrap {
  position: relative;
  height: 280px;
}
</style>
