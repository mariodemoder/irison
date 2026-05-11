<template>
  <MainLayout>
    <div class="legal-wrapper">
      <h1>Política de Privacidad</h1>
      <p class="legal-updated">Última actualización: mayo 2026</p>

      <section class="legal-section">
        <h2>1. Responsable del tratamiento</h2>
        <p>Irison es el responsable del tratamiento de los datos personales recogidos a través de esta plataforma.</p>
      </section>

      <section class="legal-section">
        <h2>2. Datos que recogemos</h2>
        <p>Recopilamos los datos que nos proporcionas al registrarte (nombre, email, datos de tu clínica) y los datos de pacientes y actividad que introduces en la aplicación.</p>
      </section>

      <section class="legal-section">
        <h2>3. Finalidad del tratamiento</h2>
        <p>Utilizamos tus datos para prestarte el servicio de gestión de clínica, procesamiento de pagos (a través de Stripe) y comunicaciones relacionadas con tu cuenta.</p>
      </section>

      <section class="legal-section">
        <h2>4. Conservación de datos</h2>
        <p>Conservamos tus datos mientras mantengas una cuenta activa y durante el tiempo necesario para cumplir obligaciones legales.</p>
      </section>

      <section class="legal-section">
        <h2>5. Derechos</h2>
        <p>Puedes ejercer tus derechos de acceso, rectificación, supresión y portabilidad contactando con nosotros en <a href="mailto:hola@irison.es">hola@irison.es</a>.</p>
      </section>

      <section class="legal-section">
        <h2>6. Cookies</h2>
        <p>Utilizamos cookies técnicas necesarias para el funcionamiento de la plataforma. No usamos cookies de terceros con fines publicitarios.</p>
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
