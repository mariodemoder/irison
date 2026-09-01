<template>
  <div
    class="modal-backdrop"
    @mousedown.left="onBackdropMouseDown"
    @mouseup.left="onBackdropMouseUp"
  >
    <div class="help-modal" role="dialog" aria-modal="true" aria-label="Ayuda del Portal del Paciente">
      <div class="help-header">
        <h2>Portal del Paciente</h2>
        <button class="close-btn" @click="$emit('close')" aria-label="Cerrar ayuda">✕</button>
      </div>

      <div class="help-body">
        <section>
          <h3>¿Qué es?</h3>
          <p>El Portal del Paciente permite a cada paciente acceder desde su móvil u ordenador a sus citas, bonos, pagos, consentimientos, documentos y notificaciones. El acceso es <strong>opt-in</strong>: tú decides qué pacientes pueden entrar.</p>
        </section>

        <section>
          <h3>El circuito, paso a paso</h3>

          <article class="usecase">
            <div class="uc-number">1</div>
            <div class="uc-body">
              <h4>El paciente tiene un email válido</h4>
              <p>Asegúrate de que la ficha del paciente tiene un email correcto y funcional. Sin email no podrá usar el portal.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">2</div>
            <div class="uc-body">
              <h4>Activa el acceso en su ficha</h4>
              <p>Abre la ficha del paciente y pulsa <strong>«Activar acceso»</strong> en la sección <strong>Portal del Paciente</strong>.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">3</div>
            <div class="uc-body">
              <h4>Informa al paciente</h4>
              <p>El sistema <strong>no envía credenciales automáticamente</strong>. Comparte con el paciente el enlace de acceso del portal y los pasos para crear su contraseña.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">4</div>
            <div class="uc-body">
              <h4>El paciente crea su contraseña</h4>
              <p>En la pantalla de acceso pulsa <strong>«He olvidado mi contraseña»</strong>, introduce su email y crea su propia contraseña. A partir de ahí podrá entrar cuando quiera.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">5</div>
            <div class="uc-body">
              <h4>Desactiva el acceso cuando quieras</h4>
              <p>Desde la misma ficha puedes pulsar <strong>«Desactivar acceso»</strong>. La desactivación cierra las sesiones activas del paciente de inmediato.</p>
            </div>
          </article>
        </section>

        <section>
          <h3>Qué puede hacer el paciente</h3>
          <p>Consultar y gestionar sus citas (solicitar, reprogramar y cancelar con +24 h de antelación), revisar sus bonos y sesiones disponibles, ver su historial de pagos y pendientes, firmar consentimientos, descargar documentos, recibir notificaciones y actualizar su perfil.</p>
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
.usecase { display: flex; gap: 14px; margin-top: 12px; padding: 14px; background: #f9fafb; border-radius: 10px; }
.uc-number { width: 28px; height: 28px; background: #4338ca; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0; }
.uc-body h4 { font-size: 14px; font-weight: 600; margin: 0 0 4px; }
.uc-body p { font-size: 13px; color: #4b5563; }
</style>