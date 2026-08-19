<template>
  <div
    class="modal-backdrop"
    @mousedown.left="onBackdropMouseDown"
    @mouseup.left="onBackdropMouseUp"
  >
    <div class="help-modal" role="dialog" aria-modal="true" aria-label="Ayuda de notificaciones">
      <div class="help-header">
        <h2>Notificaciones</h2>
        <button class="close-btn" @click="$emit('close')">✕</button>
      </div>

      <div class="help-body">
        <section>
          <h3>¿Qué son?</h3>
          <p>Historial de todos los emails automáticos que Irison envía: citas, recordatorios, reservas online, consentimientos y comunicaciones de facturación. Aquí puedes consultar qué se envió, a quién, cuándo y si llegó correctamente.</p>
        </section>

        <section>
          <h3>Categorías</h3>
          <table class="vars-table">
            <thead><tr><th>Categoría</th><th>Cuándo se envía</th></tr></thead>
            <tbody>
              <tr><td><code>Recordatorio 24h</code></td><td>Recordatorio por email enviado 24 horas antes de la cita</td></tr>
              <tr><td><code>Recordatorio 2h</code></td><td>Recordatorio por email enviado 2 horas antes de la cita</td></tr>
              <tr><td><code>Nueva cita</code></td><td>Aviso cuando se crea una cita manualmente</td></tr>
              <tr><td><code>Cita modificada</code></td><td>Aviso cuando una cita se modifica</td></tr>
              <tr><td><code>Cita cancelada</code></td><td>Aviso cuando una cita se cancela</td></tr>
              <tr><td><code>Reserva online</code></td><td>Confirmación al paciente tras reservar online</td></tr>
              <tr><td><code>Nueva reserva online</code></td><td>Aviso interno a los propietarios de la clínica</td></tr>
              <tr><td><code>Firma de consentimiento</code></td><td>Enlace para firmar un consentimiento (caduca en 72h)</td></tr>
              <tr><td><code>Suscripción / Pago / Factura</code></td><td>Emails de facturación, pagos y cambios de plan</td></tr>
              <tr><td><code>Contacto / Cuenta / Otros</code></td><td>Formulario de contacto, activación de cuenta, hitos de trial, restablecimiento de contraseña y emails genéricos</td></tr>
            </tbody>
          </table>
        </section>

        <section>
          <h3>Estados</h3>
          <article class="usecase">
            <div class="uc-number">✓</div>
            <div class="uc-body">
              <h4>Enviado</h4>
              <p>El email se entregó correctamente. Incluye la fecha del envío, el destinatario y el asunto.</p>
            </div>
          </article>
          <article class="usecase">
            <div class="uc-number">✕</div>
            <div class="uc-body">
              <h4>Fallido</h4>
              <p>El email no pudo enviarse (p. ej. el paciente no tiene email registrado o hubo un error del servidor). El motivo aparece en el detalle. Solo los recordatorios de cita (24h y 2h) pueden reenviarse manualmente.</p>
            </div>
          </article>
        </section>

        <section>
          <h3>Qué puedes hacer</h3>
          <p>Buscar y filtrar por email, asunto, paciente, estado, categoría y rango de fechas. Haz clic en una fila o en <strong>Ver detalle</strong> para consultar la información completa y, si es un recordatorio, el historial de intentos de envío para esa cita.</p>
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
.usecase { display: flex; gap: 14px; margin-top: 16px; padding: 14px; background: #f9fafb; border-radius: 10px; }
.uc-number { width: 28px; height: 28px; background: #4338ca; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0; }
.uc-body h4 { font-size: 14px; font-weight: 600; margin: 0 0 4px; }
.uc-body p { font-size: 13px; color: #4b5563; }
</style>
