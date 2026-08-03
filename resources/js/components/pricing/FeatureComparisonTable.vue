<template>
  <div class="comparison-wrap">
    <h3>Comparativa de planes</h3>
    <table class="comparison-table">
      <thead>
        <tr>
          <th>Característica</th>
          <th class="col-basic">Basic</th>
          <th class="col-pro">Pro</th>
          <th class="col-enterprise">Enterprise</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in features" :key="row.label">
          <td class="feature-label">{{ row.label }}</td>
          <td><span v-if="row.basic === true" class="check">✓</span><span v-else-if="row.basic === false" class="cross">—</span><span v-else class="val">{{ resolveCount('basic', row) }}</span></td>
          <td><span v-if="row.pro === true" class="check">✓</span><span v-else-if="row.pro === false" class="cross">—</span><span v-else class="val">{{ resolveCount('pro', row) }}</span></td>
          <td><span v-if="row.enterprise === true" class="check">✓</span><span v-else-if="row.enterprise === false" class="cross">—</span><span v-else class="val">{{ resolveCount('enterprise', row) }}</span></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
const props = defineProps({
  allPlans: { type: Object, default: () => ({}) },
})

const features = [
  { label: 'Usuarios', basic: 'users', pro: 'users', enterprise: 'users' },
  { label: 'Pacientes e historia clínica', basic: true, pro: true, enterprise: true },
  { label: 'Agenda de citas', basic: true, pro: true, enterprise: true },
  { label: 'Booking online', basic: true, pro: true, enterprise: true },
  { label: 'Consentimientos informados', basic: true, pro: true, enterprise: true },
  { label: 'Firma digital', basic: true, pro: true, enterprise: true },
  { label: 'Facturación', basic: true, pro: true, enterprise: true },
  { label: 'Notificaciones email', basic: true, pro: true, enterprise: true },
  { label: 'Dashboard', basic: true, pro: true, enterprise: true },
  { label: 'Gestión financiera', basic: false, pro: true, enterprise: true },
  { label: 'Portal del paciente', basic: false, pro: true, enterprise: true },
  { label: 'Notificaciones WhatsApp', basic: false, pro: true, enterprise: true },
  { label: 'API de integración', basic: false, pro: true, enterprise: true },
  { label: 'Informes avanzados', basic: false, pro: true, enterprise: true },
  { label: 'Multi sede', basic: false, pro: false, enterprise: true },
  { label: 'White Label', basic: false, pro: false, enterprise: true },
  { label: 'Soporte', basic: 'Email', pro: 'Prioritario', enterprise: 'Dedicado' },
]

function resolveCount(planKey, row) {
  if (row[planKey] !== 'users') return row[planKey]
  const plan = props.allPlans[planKey]
  const users = plan?.users ?? 0
  return users > 0 ? `${users} usuarios` : 'Ilimitados'
}
</script>

<style scoped>
.comparison-wrap { margin-top: 32px; }
.comparison-wrap h3 { font-size: 18px; font-weight: 700; margin-bottom: 16px; color: #1f2937; }
.comparison-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.comparison-table th { padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 700; color: #6b7280; background: #f9fafb; border-bottom: 2px solid #e5e7eb; text-transform: uppercase; letter-spacing: 0.05em; }
.comparison-table th.col-pro { color: #4338ca; }
.comparison-table th.col-enterprise { color: #065f46; }
.comparison-table td { padding: 10px 16px; font-size: 13px; border-bottom: 1px solid #f3f4f6; }
.feature-label { font-weight: 500; color: #374151; }
.check { color: #10b981; font-weight: 700; font-size: 16px; }
.cross { color: #d1d5db; }
.val { color: #6b7280; }
</style>
