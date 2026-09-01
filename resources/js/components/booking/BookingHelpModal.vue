<template>
  <div
    class="modal-backdrop"
    @mousedown.left="onBackdropMouseDown"
    @mouseup.left="onBackdropMouseUp"
  >
    <div class="help-modal" role="dialog" aria-modal="true" aria-label="Ayuda de Reserva Online">
      <div class="help-header">
        <h2>Reserva Online</h2>
        <button class="close-btn" @click="$emit('close')" aria-label="Cerrar ayuda">✕</button>
      </div>

      <div class="help-body">
        <section>
          <h3>¿Qué es?</h3>
          <p>La Reserva Online es la página pública donde tus pacientes reservan citas por internet, sin instalar nada ni llamar por teléfono. El enlace tiene la forma <strong>/booking/&lt;identificador&gt;</strong> y puedes compartirlo con "Ver página pública" o "Copiar enlace público".</p>
        </section>

        <section>
          <h3>El circuito, paso a paso</h3>

          <article class="usecase">
            <div class="uc-number">1</div>
            <div class="uc-body">
              <h4>Define tu URL pública</h4>
              <p>En <strong>Configuración</strong> elige un identificador único (letras minúsculas, números y guiones). Mantén el <strong>Estado activado</strong> para que la página acepte reservas.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">2</div>
            <div class="uc-body">
              <h4>Configura tus servicios</h4>
              <p>En <strong>Servicios</strong> crea los servicios reservables con duración y precio, y déjalos <strong>activos</strong>. Solo los servicios activos aparecen en la página pública.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">3</div>
            <div class="uc-body">
              <h4>Habilita profesionales y horarios</h4>
              <p>En <strong>Profesionales</strong> pon a cada profesional en <strong>Online</strong> y revisa su horario semanal. Si usa el horario de Equipo, se sincroniza automáticamente; puedes personalizarlo con "Guardar horarios". Añade excepciones o bloqueos para días sueltos.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">4</div>
            <div class="uc-body">
              <h4>Comparte el enlace público</h4>
              <p>Usa <strong>Copiar enlace público</strong> para difundir tu página de reserva (WhatsApp, email, web o redes sociales) y <strong>Ver página pública</strong> para comprobar cómo la ven tus pacientes.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">5</div>
            <div class="uc-body">
              <h4>El paciente reserva y recibe confirmación</h4>
              <p>El paciente elige servicio, profesional (o "Cualquier profesional disponible"), día y hora en franjas de 15 minutos. Recibe un <strong>email de confirmación</strong> con un enlace para cancelar, y los owners de la clínica reciben un aviso.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">6</div>
            <div class="uc-body">
              <h4>Gestiona el día a día</h4>
              <p>Las citas entran en tu agenda normal con sus recordatorios automáticos (24 h y 2 h antes). Puedes pausar las reservas desactivando el Estado, o quitar a un profesional poniéndolo en <strong>Offline</strong>.</p>
            </div>
          </article>
        </section>

        <section>
          <h3>Qué puede hacer el paciente</h3>
          <p>Ver los servicios con duración y precio, elegir profesional (o "Cualquier profesional disponible"), seleccionar día y hora entre los huecos libres, introducir sus datos y recibir la confirmación con enlace de cancelación. No puede pagar online en la página de reserva.</p>
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