<template>
  <div
    class="modal-backdrop"
    @mousedown.left="onBackdropMouseDown"
    @mouseup.left="onBackdropMouseUp"
  >
    <div class="help-modal" role="dialog" aria-modal="true" aria-label="Ayuda de beneficios">
      <div class="help-header">
        <h2>Dashboard de Beneficios</h2>
        <button class="close-btn" @click="$emit('close')">✕</button>
      </div>

      <div class="help-body">
        <section>
          <h3>¿Qué es?</h3>
          <p>El dashboard de beneficios muestra la rentabilidad de tu clínica en un período de tiempo. Compara ingresos, costes y gastos para calcular el beneficio real y el margen de ganancia.</p>
        </section>

        <section>
          <h3>Indicadores (KPIs)</h3>

          <article class="usecase">
            <div class="uc-number">1</div>
            <div class="uc-body">
              <h4>Ingresos</h4>
              <p>Suma de todos los ingresos del período: facturas emitidas + otros ingresos registrados - abonos emitidos.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">2</div>
            <div class="uc-body">
              <h4>Coste personal</h4>
              <p>Coste/hora de cada miembro del equipo × duración de sus citas en el período. Se calcula automáticamente a partir de las tarifas configuradas en la pestaña <strong>Tarifas</strong>.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">3</div>
            <div class="uc-body">
              <h4>Gastos registrados</h4>
              <p>Total de gastos introducidos manualmente en el período, incluyendo IVA. Se gestionan en la pestaña <strong>Gastos</strong>.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">4</div>
            <div class="uc-body">
              <h4>Coste total</h4>
              <p>Gastos registrados + coste personal. Representa el gasto total de la clínica en el período.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">5</div>
            <div class="uc-body">
              <h4>Beneficio</h4>
              <p>Ingresos - Coste total. Si es negativo, la clínica gasta más de lo que ingresa.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">6</div>
            <div class="uc-body">
              <h4>Margen</h4>
              <p>(Beneficio / Ingresos) × 100. Porcentaje de beneficio respecto a los ingresos. Un margen del 40% significa que por cada €100 de ingreso, quedan €40 de beneficio.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">7</div>
            <div class="uc-body">
              <h4>Ticket medio</h4>
              <p>Ingresos totales / operaciones pagadas. Ingreso promedio por cada cita que se ha cobrado.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">8</div>
            <div class="uc-body">
              <h4>Operaciones pagadas</h4>
              <p>Número total de pagos completados en el período. Solo se cuentan pagos con estado "completado", excluyendo reembolsos.</p>
            </div>
          </article>
        </section>

        <section>
          <h3>Tablas de desglose</h3>
          <p>Debajo de los KPIs encontrarás cuatro tablas con el desglose detallado:</p>
          <table class="vars-table">
            <thead><tr><th>Tabla</th><th>Qué muestra</th></tr></thead>
            <tbody>
              <tr><td><code>Ingresos por servicio</code></td><td>Cuánto ingresa cada tipo de cita (consulta, tratamiento, etc.)</td></tr>
              <tr><td><code>Contribución por profesional</code></td><td>Ingresos, coste laboral y contribución individual de cada miembro del equipo</td></tr>
              <tr><td><code>Gastos por categoría</code></td><td>Distribución de los gastos según su categoría (alquiler, suministros, etc.)</td></tr>
              <tr><td><code>Ingresos por método de pago</code></td><td>Cómo se pagan las citas: efectivo, tarjeta o transferencia</td></tr>
            </tbody>
          </table>
        </section>

        <section>
          <h3>Comparativa con periodo anterior</h3>
          <p>Si seleccionas un rango de fechas, el dashboard calcula automáticamente la variación porcentual respecto al periodo anterior de la misma duración. Aparece una flecha verde (subida) o roja (bajada) junto al valor.</p>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useModalClose } from '../../composables/useModalClose'

const emit = defineEmits(['close'])

const { onBackdropMouseDown, onBackdropMouseUp } = useModalClose(() => emit('close'))
</script>

<style scoped>
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 200; padding: 24px; }
.help-modal { background: #fff; border-radius: 16px; max-width: 640px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 8px 32px rgba(0,0,0,.2); }
.help-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; background: #fff; border-radius: 16px 16px 0 0; }
.help-header h2 { font-size: 18px; font-weight: 700; margin: 0; }
.close-btn { background: none; border: none; font-size: 20px; cursor: pointer; color: #6b7280; padding: 4px 8px; border-radius: 6px; }
.close-btn:hover { background: #f3f4f6; }
.help-body { padding: 24px; }
.help-body section { margin-bottom: 28px; }
.help-body h3 { font-size: 15px; font-weight: 700; color: #1f2937; margin: 0 0 8px; }
.help-body p { font-size: 14px; line-height: 1.6; color: #374151; margin: 0; }
.vars-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.vars-table th { text-align: left; padding: 6px 12px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; font-weight: 600; color: #6b7280; }
.vars-table td { padding: 6px 12px; border-bottom: 1px solid #f3f4f6; }
.vars-table code { background: #f3f4f6; padding: 1px 5px; border-radius: 3px; font-size: 12px; }
.usecase { display: flex; gap: 14px; margin-top: 12px; padding: 14px; background: #f9fafb; border-radius: 10px; }
.uc-number { width: 28px; height: 28px; background: #4338ca; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0; }
.uc-body h4 { font-size: 14px; font-weight: 600; margin: 0 0 4px; }
.uc-body p { font-size: 13px; color: #4b5563; }
</style>
