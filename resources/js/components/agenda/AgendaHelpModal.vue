<template>
  <div class="modal-backdrop" @click.self="$emit('close')">
    <div class="help-modal" role="dialog" aria-modal="true" aria-label="Ayuda de agenda">
      <div class="help-header">
        <h2>Gestión de la Agenda</h2>
        <button class="close-btn" @click="$emit('close')">✕</button>
      </div>

      <div class="help-body">
        <section>
          <h3>¿Cómo gestionar una cita?</h3>

          <article class="usecase">
            <div class="uc-number">1</div>
            <div class="uc-body">
              <h4>Crear cita</h4>
              <p>Pulsa <strong>Nueva cita</strong> en la parte superior de la agenda. Selecciona paciente, servicio (sesión), profesional y horario disponible. La cita se crea con estado <strong>Confirmada</strong>.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">2</div>
            <div class="uc-body">
              <h4>Editar cita</h4>
              <p>Haz clic sobre una cita existente en la agenda para ver su detalle. Desde ahí puedes modificar paciente, servicio, fecha, hora o profesional, así como añadir notas internas.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">3</div>
            <div class="uc-body">
              <h4>Cambiar estado</h4>
              <p>Desde el detalle de la cita puedes marcar los estados: <strong>Confirmada</strong>, <strong>Completada</strong>, <strong>Reprogramada</strong> o <strong>Cancelada</strong>. Una cita completada descontará automáticamente una sesión del bono si el paciente tiene uno asignado.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">4</div>
            <div class="uc-body">
              <h4>Filtros y vista</h4>
              <p>Usa los filtros de profesional y fecha para navegar la agenda. Cambia entre vista <strong>Semanal</strong> y <strong>Diaria</strong> según prefieras. Los días no laborables aparecen marcados.</p>
            </div>
          </article>
        </section>

        <section>
          <h3>¿Cómo gestionar un bono?</h3>

          <article class="usecase">
            <div class="uc-number">1</div>
            <div class="uc-body">
              <h4>¿Qué es un bono?</h4>
              <p>Un bono es un paquete de sesiones prepagadas que se asigna a un paciente. Por ejemplo: <em>"10 sesiones de Fisioterapia"</em>. El paciente paga por adelantado y consume las sesiones con cada cita.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">2</div>
            <div class="uc-body">
              <h4>Asignar bono a paciente</h4>
              <p>Desde la ficha del paciente, ve a la sección <strong>Bonos</strong> y pulsa <strong>Asignar bono</strong>. Selecciona el tipo de bono (sesiones, precio, validez) y se asignará automáticamente al paciente.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">3</div>
            <div class="uc-body">
              <h4>Consumir sesiones</h4>
              <p>Al completar una cita, si el paciente tiene un bono activo para ese tipo de sesión, Irison descuenta automáticamente una sesión del bono. Puedes ver el saldo restante en la ficha del paciente o al crear una cita.</p>
            </div>
          </article>

          <article class="usecase">
            <div class="uc-number">4</div>
            <div class="uc-body">
              <h4>Ver saldo y vencimiento</h4>
              <p>En la sección <strong>Bonos</strong> del paciente puedes consultar las sesiones totales, consumidas y restantes, así como la fecha de vencimiento. Los bonos vencidos se marcan automáticamente.</p>
            </div>
          </article>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
defineEmits(['close'])
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
.usecase { display: flex; gap: 14px; margin-top: 16px; padding: 14px; background: #f9fafb; border-radius: 10px; }
.uc-number { width: 28px; height: 28px; background: #4338ca; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0; }
.uc-body h4 { font-size: 14px; font-weight: 600; margin: 0 0 4px; }
.uc-body p { font-size: 13px; color: #4b5563; }
</style>
