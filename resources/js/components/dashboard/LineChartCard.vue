<template>
  <div class="card">
    <div class="card-title">{{ title }}</div>
    <div class="chart-wrap">
      <canvas ref="canvasRef" />
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Chart, LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler } from 'chart.js'

Chart.register(LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend, Filler)

const props = defineProps({
  title: { type: String, required: true },
  labels: { type: Array, default: () => [] },
  values: { type: Array, default: () => [] },
})

const canvasRef = ref(null)
let chartInstance = null

function renderChart() {
  if (!canvasRef.value) return

  if (chartInstance) {
    chartInstance.destroy()
  }

  chartInstance = new Chart(canvasRef.value, {
    type: 'line',
    data: {
      labels: props.labels,
      datasets: [
        {
          label: 'Ingresos',
          data: props.values,
          borderColor: '#16a34a',
          backgroundColor: 'rgba(22, 163, 74, 0.12)',
          tension: 0.35,
          pointRadius: 3,
          pointBackgroundColor: '#16a34a',
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
      },
      scales: {
        x: {
          grid: { color: 'rgba(15, 23, 42, 0.08)' },
        },
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(15, 23, 42, 0.08)' },
        },
      },
    },
  })
}

watch(() => [props.labels, props.values], renderChart, { deep: true })

onMounted(renderChart)

onBeforeUnmount(() => {
  if (chartInstance) {
    chartInstance.destroy()
    chartInstance = null
  }
})
</script>

<style scoped>
.card {
  background: var(--bg-card);
  border: 1px solid #94a3b8;
  border-radius: 20px;
  padding: 24px;
  position: relative;
  min-height: 0;
}

.card-title {
  position: absolute;
  top: -14px;
  left: 24px;
  background: var(--secondary);
  color: white;
  padding: 6px 16px;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 600;
}

.chart-wrap {
  width: 100%;
  height: clamp(220px, 32vw, 320px);
  margin-top: 12px;
}

.chart-wrap canvas {
  display: block;
  width: 100% !important;
  height: 100% !important;
}

@media (max-width: 768px) {
  .card {
    padding: 16px;
  }

  .card-title {
    left: 16px;
    font-size: 13px;
    padding: 5px 12px;
  }

  .chart-wrap {
    height: 220px;
  }
}
</style>
