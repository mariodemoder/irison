<template>
  <MainLayout>
    <div class="legal-wrapper">
      <h1>Términos y Condiciones</h1>
      <p class="legal-updated">Última actualización: mayo 2026</p>

      <section class="legal-section">
        <h2>1. Aceptación de los términos</h2>
        <p>Al acceder y usar Irison, aceptas estos términos y condiciones en su totalidad. Si no estás de acuerdo, no debes usar la plataforma.</p>
      </section>

      <section class="legal-section">
        <h2>2. Descripción del servicio</h2>
        <p>Irison es una plataforma SaaS de gestión para clínicas que permite administrar pacientes, citas, pagos y facturación.</p>
      </section>

      <section class="legal-section">
        <h2>3. Cuenta de usuario</h2>
        <p>Eres responsable de mantener la confidencialidad de tus credenciales y de toda la actividad que ocurra bajo tu cuenta.</p>
      </section>

      <section class="legal-section">
        <h2>4. Suscripción y pagos</h2>
        <p>El acceso a determinadas funcionalidades requiere una suscripción de pago gestionada a través de Stripe. Los precios y condiciones se muestran en el proceso de suscripción.</p>
      </section>

      <section class="legal-section">
        <h2>5. Uso aceptable</h2>
        <p>Te comprometes a usar Irison únicamente para fines legales y de conformidad con la normativa aplicable. Queda prohibido cualquier uso que pueda dañar, deshabilitar o sobrecargar la plataforma.</p>
      </section>

      <section class="legal-section">
        <h2>6. Propiedad intelectual</h2>
        <p>Todo el contenido, código y diseño de Irison es propiedad exclusiva de sus creadores. No se permite su reproducción sin autorización expresa.</p>
      </section>

      <section class="legal-section">
        <h2>7. Limitación de responsabilidad</h2>
        <p>Irison se proporciona "tal cual". No garantizamos disponibilidad ininterrumpida ni ausencia de errores. En ningún caso seremos responsables de pérdidas indirectas o consecuentes.</p>
      </section>

      <section class="legal-section">
        <h2>8. Modificaciones</h2>
        <p>Nos reservamos el derecho de modificar estos términos en cualquier momento. Te notificaremos los cambios relevantes a través de la plataforma o por email.</p>
      </section>

      <section class="legal-section">
        <h2>9. Contacto</h2>
        <p>Para cualquier consulta legal, escríbenos a <a href="mailto:hola@irison.es">hola@irison.es</a>.</p>
      </section>
    </div>
  </MainLayout>
</template>

<script setup>
import MainLayout from '../layouts/MainLayout.vue'
</script>

<style scoped>
.legal-wrapper {
  max-width: 720px;
  margin: 0 auto;
  padding: 24px 0 48px;
}

.legal-wrapper h1 {
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 4px;
}

.legal-updated {
  color: #6b7280;
  font-size: 13px;
  margin-bottom: 32px;
}

.legal-section {
  margin-bottom: 24px;
}

.legal-section h2 {
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 8px;
  color: #1e3a8a;
}

.legal-section p {
  font-size: 14px;
  line-height: 1.7;
  color: #374151;
}

.legal-section a {
  color: #2563eb;
  text-decoration: underline;
}
</style>
