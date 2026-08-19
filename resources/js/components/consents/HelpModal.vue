<template>
  <div
    class="modal-backdrop"
    @mousedown.left="onBackdropMouseDown"
    @mouseup.left="onBackdropMouseUp"
  >
    <div class="help-modal" role="dialog" aria-modal="true" aria-label="Ayuda de consentimientos">
      <div class="help-header">
        <h2>Consentimientos Informados</h2>
        <button class="close-btn" @click="$emit('close')">✕</button>
      </div>

      <div class="help-body">
        <section>
          <h3>¿Qué son?</h3>
          <p>Documentos legales que el paciente firma autorizando un tratamiento. Se generan bajo demanda, se firman en clínica o desde casa, y el PDF final se descarga solo si está firmado.</p>
        </section>

        <section>
          <h3>Variables disponibles</h3>
          <table class="vars-table">
            <thead><tr><th>Variable</th><th>Se reemplaza por</th></tr></thead>
            <tbody>
              <tr><td><code>{paciente_nombre}</code></td><td>Nombre del paciente</td></tr>
              <tr><td><code>{paciente_apellidos}</code></td><td>Apellidos del paciente</td></tr>
              <tr><td><code>{dni}</code></td><td>DNI / NIF del paciente</td></tr>
              <tr><td><code>{telefono}</code></td><td>Teléfono del paciente</td></tr>
              <tr><td><code>{email}</code></td><td>Email del paciente</td></tr>
              <tr><td><code>{fecha}</code></td><td>Fecha actual (dd/mm/aaaa)</td></tr>
              <tr><td><code>{profesional}</code></td><td>Nombre del profesional que crea el documento</td></tr>
              <tr><td><code>{clinica}</code></td><td>Nombre de la clínica</td></tr>
              <tr><td><code>{tratamiento}</code></td><td>Tratamiento (pendiente de implementar)</td></tr>
              <tr><td><code>{especialidad}</code></td><td>Especialidad del profesional</td></tr>
            </tbody>
          </table>
        </section>

        <section>
          <h3>Casos de uso</h3>

          <article class="usecase">
            <div class="uc-number">1</div>
            <div class="uc-body">
              <h4>Crear plantilla</h4>
              <p>El administrador crea una plantilla desde <strong>Consentimientos → Nueva plantilla</strong>. Escribe el contenido con las variables <code>{...}</code> que se reemplazarán automáticamente al generar el documento.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">2</div>
            <div class="uc-body">
              <h4>Generar consentimiento</h4>
              <p>Al agendar una sesión, recepción pulsa <strong>Crear consentimiento</strong> desde la ficha del paciente, selecciona la plantilla e Irison genera el documento reemplazando las variables con los datos reales del paciente, profesional y clínica.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">3</div>
            <div class="uc-body">
              <h4>Firma presencial</h4>
              <p>El paciente llega a consulta. Recepción gira la tablet, el paciente firma con el dedo en el pad digital. La firma se guarda automáticamente y el PDF queda disponible para descargar.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">4</div>
            <div class="uc-body">
              <h4>Firma remota (online)</h4>
              <p>Si el paciente reserva online, recibe un email con un enlace seguro. Hace clic, lee el documento y firma desde casa con el dedo o ratón. Cuando llega a consulta ya está firmado.</p>
            </div>
          </article>
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
.uc-body code { background: #e5e7eb; padding: 1px 4px; border-radius: 3px; font-size: 12px; }
</style>
