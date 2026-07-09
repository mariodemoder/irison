<script setup>
import { computed, ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'
import logo from '../assets/logoIni.svg'
import PricingCard from '../components/pricing/PricingCard.vue'
import FeatureComparisonTable from '../components/pricing/FeatureComparisonTable.vue'
import FaqPricing from '../components/pricing/FaqPricing.vue'

const currentYear = new Date().getFullYear()
const plans = ref([])
const pricingData = ref({})
const pricingLoaded = ref(false)

onMounted(async () => {
  try {
    const res = await axios.get('/api/pricing')
    pricingData.value = res.data.data || {}
    plans.value = Object.values(pricingData.value)
    pricingLoaded.value = true
  } catch (_) {
    pricingLoaded.value = true
  }
})

const metrics = [
  { value: '15 min', label: 'para tener la cuenta lista y empezar a trabajar' },
  { value: '24/7', label: 'acceso seguro desde cualquier dispositivo' },
  { value: '1 lugar', label: 'para agenda, historia, pagos y facturación' },
]

const featureCards = [
  {
    eyebrow: 'Operación clínica',
    title: 'Agenda, pacientes e historia clínica conectados',
    body: 'Evita duplicidades, revisa el contexto de cada paciente antes de la visita y trabaja con toda la información ordenada en el mismo flujo.',
  },
  {
    eyebrow: 'Cobro y control',
    title: 'Pagos, bonos y facturación sin fricción',
    body: 'Centraliza cobros, seguimiento de bonos, documentos y facturas para que el cierre administrativo no dependa de hojas sueltas ni procesos manuales.',
  },
  {
    eyebrow: 'Automatización',
    title: 'Recordatorios y tareas repetitivas bajo control',
    body: 'Reduce llamadas, olvidos y trabajo operativo con recordatorios, estados claros y una operación más predecible para recepción y dirección.',
  },
]

const workflows = [
  'Recepción más ágil: citas, cambios y seguimiento desde una vista clara.',
  'Dirección con más visibilidad: actividad, cobros y rendimiento en la misma plataforma.',
  'Equipo clínico con menos interrupciones: historial y contexto accesibles cuando toca atender.',
  'Crece sin rehacer procesos: el modelo SaaS permite escalar sin cambiar de sistema cada pocos meses.',
]

const faqList = [
  { question: '¿Irison es SaaS o requiere instalación?', answer: 'Irison funciona en la nube. No necesitas instalaciones complejas ni mantenimiento local para empezar a trabajar.' },
  { question: '¿Puedo probarlo antes de pagar?', answer: 'Sí. La propuesta base del sitio institucional parte de una prueba gratuita para que el centro valide el flujo real antes de contratar.' },
  { question: '¿Está orientado solo a una especialidad?', answer: 'No. El enfoque es servir a clínicas que necesitan una base sólida de agenda, pacientes, pagos y facturación, con capacidad de evolucionar por tipo de centro.' },
  { question: '¿Puedo cambiar de plan más adelante?', answer: 'Sí. Puedes solicitar un cambio de plan cuando tu clínica lo necesite. El nuevo plan se activa al inicio del siguiente ciclo de facturación.' },
  { question: '¿Qué pasa con mis datos si dejo de pagar?', answer: 'Tus datos nunca se pierden. Si cancelas, tienes un periodo de solo lectura para exportar tu información antes de que se elimine definitivamente.' },
  { question: '¿Cómo funciona la facturación?', answer: 'La suscripción es mensual. Recibirás una factura al inicio de cada ciclo. Aceptamos pago con tarjeta y transferencia.' },
]

const brandStatement = computed(() => 'Gestiona tu clínica con una plataforma clara, segura y preparada para crecer contigo.')
</script>

<template>
  <div class="landing-shell">
    <section class="landing-hero">
      <header class="landing-nav">
        <RouterLink class="brand-mark" to="/">
          <img :src="logo" alt="Irison" class="brand-mark__logo" />
          <div>
            <strong>Irison</strong>
            <span>Software SaaS para clínicas</span>
          </div>
        </RouterLink>

        <nav class="landing-nav__links" aria-label="Principal">
          <a href="#producto">Producto</a>
          <a href="#modelo">Modelo SaaS</a>
          <a href="#planes">Planes</a>
          <RouterLink to="/login">Iniciar sesión</RouterLink>
        </nav>
      </header>

      <div class="hero-grid">
        <div class="hero-copy">
          <span class="eyebrow">Sitio institucional</span>
          <h1>El software que ordena la gestión clínica sin complicar tu día.</h1>
          <p class="hero-lead">{{ brandStatement }}</p>
          <p class="hero-body">
            La propuesta para Irison combina el discurso SaaS de flexibilidad y escalabilidad con una promesa muy concreta: menos fricción operativa para recepción, dirección y equipo clínico.
          </p>

          <div class="hero-actions">
            <RouterLink class="btn btn--solid landing-btn-main" to="/register">Empieza tu prueba</RouterLink>
            <a class="btn btn--ghost landing-btn-ghost" href="#planes">Ver enfoque comercial</a>
          </div>

          <ul class="hero-metrics" aria-label="Resumen">
            <li v-for="metric in metrics" :key="metric.label">
              <strong>{{ metric.value }}</strong>
              <span>{{ metric.label }}</span>
            </li>
          </ul>
        </div>

        <div class="hero-panel">
          <div class="hero-card hero-card--primary">
            <span class="card-kicker">Narrativa recomendada</span>
            <h2>SaaS claro para clínicas que quieren control y simplicidad</h2>
            <p>
              La home debe vender una plataforma completa, no un listado de módulos inconexos. El eje de mensaje funciona mejor si une tres ideas: operación clínica, cobro/facturación y automatización.
            </p>
          </div>

          <div class="hero-card hero-card--secondary">
            <span class="card-kicker">CTA principal</span>
            <p>Prueba gratuita o demo guiada, con acceso rápido y sin pedir demasiado contexto en el primer paso.</p>
          </div>
        </div>
      </div>
    </section>

    <section id="producto" class="landing-section landing-section--light">
      <div class="section-head">
        <span class="eyebrow">Producto</span>
        <h2>Una propuesta institucional pensada para transmitir solidez desde la primera pantalla</h2>
        <p>
          Las referencias revisadas convergen en el mismo patrón: promesa clara, bloques de confianza, recorrido por capacidades y una entrada comercial muy visible. Para Irison conviene bajar eso a un tono más cercano y menos corporativo.
        </p>
      </div>

      <div class="feature-grid">
        <article v-for="card in featureCards" :key="card.title" class="feature-card">
          <span class="feature-card__eyebrow">{{ card.eyebrow }}</span>
          <h3>{{ card.title }}</h3>
          <p>{{ card.body }}</p>
        </article>
      </div>
    </section>

    <section id="modelo" class="landing-section landing-section--accent">
      <div class="section-head section-head--split">
        <div>
          <span class="eyebrow">Modelo SaaS</span>
          <h2>Qué conviene explicar en irison.es</h2>
        </div>
        <p>
          Igual que en las referencias SaaS sanitarias, el sitio debería explicar por qué el modelo en la nube reduce coste, mantenimiento y fricción técnica, pero aterrizándolo en beneficios diarios para una clínica real.
        </p>
      </div>

      <div class="workflow-card">
        <ul>
          <li v-for="item in workflows" :key="item">{{ item }}</li>
        </ul>
      </div>
    </section>

    <section id="planes" class="landing-section landing-section--light">
      <div class="section-head">
        <span class="eyebrow">Planes y captación</span>
        <h2>Enfoque comercial recomendado</h2>
        <p>
          La web institucional puede convivir con precios orientativos y una salida clara a prueba o demo. El objetivo no es cerrar toda la venta en la home, sino reducir incertidumbre y abrir conversación.
        </p>
      </div>

      <div v-if="!pricingLoaded" class="loading-pricing">Cargando planes...</div>
      <div v-else class="pricing-grid">
        <PricingCard
          v-for="plan in plans"
          :key="plan.name"
          :plan="plan"
          :all-plans="pricingData"
          :cta-text="plan.name === 'Basic' ? 'Empezar gratis' : plan.name === 'Pro' ? 'Solicitar demo' : 'Hablar con ventas'"
          :cta-href="'/register'"
        />
      </div>
      <FeatureComparisonTable v-if="pricingLoaded" />
    </section>

    <section class="landing-section landing-section--dark">
      <div class="section-head section-head--narrow">
        <span class="eyebrow">Confianza</span>
        <h2>El sitio institucional debe reducir dudas antes del primer clic comercial</h2>
      </div>

      <div class="trust-grid">
        <article>
          <strong>Seguridad y normativa</strong>
          <p>Hablar de protección de datos, operación en la nube y trazabilidad sin convertir la página en una ficha legal.</p>
        </article>
        <article>
          <strong>Adopción sencilla</strong>
          <p>Reforzar que el equipo puede empezar rápido, con curva de aprendizaje razonable y sin depender de instalaciones.</p>
        </article>
        <article>
          <strong>Mensaje transversal</strong>
          <p>Dirigirse a clínicas y centros de salud sin cerrarse a una sola especialidad desde la home principal.</p>
        </article>
      </div>
    </section>

    <section class="landing-section landing-section--light">
      <div class="section-head section-head--narrow">
        <span class="eyebrow">Preguntas clave</span>
        <h2>FAQ base para la primera versión</h2>
      </div>

      <FaqPricing :faqs="faqList" />
    </section>

    <footer class="landing-footer">
      <div>
        <strong>Irison</strong>
        <p>Software institucional y operativo para clínicas que quieren crecer con menos fricción.</p>
      </div>

      <nav>
        <RouterLink to="/privacy">Privacidad</RouterLink>
        <RouterLink to="/terms">Términos</RouterLink>
        <a href="mailto:hola@irison.es">hola@irison.es</a>
      </nav>

      <p>© {{ currentYear }} Irison</p>
    </footer>
  </div>
</template>

<style scoped>
.landing-shell {
  --violet-rgb: 106, 48, 252;
  --violet-950: rgb(45, 19, 118);
  --violet-900: rgb(58, 24, 154);
  --violet-800: rgb(72, 31, 192);
  --violet-700: rgb(86, 39, 221);
  --violet-600: rgb(var(--violet-rgb));
  --violet-500: rgb(125, 78, 255);
  --violet-400: rgb(158, 123, 255);
  --violet-300: rgb(188, 164, 255);
  --violet-200: rgb(214, 198, 255);
  --violet-100: rgb(235, 226, 255);
  --violet-050: rgb(247, 243, 255);

  color: #11203b;
  background:
    radial-gradient(circle at top left, rgba(var(--violet-rgb), 0.34), transparent 30%),
    radial-gradient(circle at top right, rgba(var(--violet-rgb), 0.2), transparent 28%),
    linear-gradient(180deg, var(--violet-050) 0%, #ffffff 42%, var(--violet-100) 100%);
}

.landing-hero,
.landing-section,
.landing-footer {
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto;
}

.landing-hero {
  padding: 28px 0 48px;
}

.landing-nav {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 18px;
  padding: 10px 0 30px;
}

.brand-mark {
  display: inline-flex;
  align-items: center;
  gap: 14px;
  color: inherit;
  text-decoration: none;
}

.brand-mark__logo {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  box-shadow: 0 10px 30px rgba(17, 32, 59, 0.12);
}

.brand-mark strong,
.brand-mark span {
  display: block;
}

.brand-mark span,
.landing-nav__links a {
  color: #5e6b80;
}

.landing-nav__links {
  display: flex;
  align-items: center;
  gap: 18px;
  flex-wrap: wrap;
}

.landing-nav__links a {
  text-decoration: none;
  font-weight: 600;
}

.hero-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
  gap: 28px;
  align-items: stretch;
}

.hero-copy,
.hero-panel,
.workflow-card,
.feature-card,
.trust-grid article {
  backdrop-filter: blur(8px);
}

.hero-copy {
  padding: 8px 0;
}

.eyebrow,
.card-kicker,
.feature-card__eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(17, 32, 59, 0.08);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--violet-900);
}

.hero-copy h1,
.section-head h2 {
  margin: 16px 0 0;
  font-size: clamp(2.6rem, 5vw, 4.8rem);
  line-height: 0.96;
  letter-spacing: -0.04em;
  font-weight: 800;
}

.section-head h2 {
  font-size: clamp(1.9rem, 3vw, 3rem);
  line-height: 1.02;
}

.hero-lead {
  margin: 18px 0 0;
  font-size: 1.2rem;
  line-height: 1.5;
  color: #213451;
  max-width: 54ch;
}

.hero-body,
.section-head p,
.feature-card p,
.trust-grid p,
.landing-footer p,
.workflow-card li,
.hero-card p {
  color: #556176;
  line-height: 1.7;
}

.hero-body {
  margin: 14px 0 0;
  max-width: 60ch;
}

.hero-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 28px;
}

.landing-btn-main {
  background: var(--violet-700);
  box-shadow: 0 16px 40px rgba(var(--violet-rgb), 0.34);
}

.landing-btn-main:hover {
  background: var(--violet-600);
  box-shadow: 0 18px 42px rgba(var(--violet-rgb), 0.4);
}

.landing-btn-ghost {
  border-color: var(--violet-700);
  color: var(--violet-700);
}

.hero-metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
  margin: 32px 0 0;
  padding: 0;
  list-style: none;
}

.hero-metrics li {
  padding: 18px;
  border-radius: 24px;
  background: rgba(255, 255, 255, 0.76);
  border: 1px solid rgba(17, 32, 59, 0.08);
}

.hero-metrics strong {
  display: block;
  font-size: 1.4rem;
  color: #11203b;
}

.hero-metrics span {
  display: block;
  margin-top: 6px;
  color: #5e6b80;
  line-height: 1.5;
}

.hero-panel {
  display: grid;
  gap: 16px;
}

.hero-card {
  padding: 28px;
  border-radius: 28px;
  border: 1px solid rgba(17, 32, 59, 0.08);
}

.hero-card--primary {
  background: linear-gradient(180deg, rgba(17, 32, 59, 0.96), rgba(67, 142, 202, 0.94));
  color: #f8fafc;
  box-shadow: 0 24px 50px rgba(17, 32, 59, 0.2);
}

.hero-card--primary .card-kicker,
.hero-card--secondary .card-kicker {
  background: rgba(255, 255, 255, 0.12);
  color: inherit;
  border-color: rgba(255, 255, 255, 0.12);
}

.hero-card--primary h2 {
  margin: 16px 0 10px;
  font-size: clamp(1.7rem, 2vw, 2.4rem);
  line-height: 1.05;
}

.hero-card--primary p,
.hero-card--secondary p {
  color: rgba(248, 250, 252, 0.84);
}

.hero-card--secondary {
  background: linear-gradient(135deg, var(--violet-900), var(--violet-600));
  color: #f8fafc;
}

.landing-section {
  padding: 46px 0;
}

.landing-section--light {
  color: #11203b;
}

.landing-section--accent {
  padding-top: 12px;
}

.landing-section--dark {
  width: 100%;
  margin-top: 16px;
  padding: 54px 16px;
  background: linear-gradient(180deg, #102341, #0d1830);
}

.landing-section--dark .section-head,
.landing-section--dark .trust-grid {
  width: min(1180px, calc(100% - 32px));
  margin-left: auto;
  margin-right: auto;
}

.landing-section--dark .section-head h2,
.landing-section--dark strong {
  color: #f8fafc;
}

.landing-section--dark .eyebrow {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.12);
  color: var(--violet-200);
}

.landing-section--dark p {
  color: rgba(226, 232, 240, 0.76);
}

.section-head {
  max-width: 74ch;
}

.section-head--split {
  display: grid;
  grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
  gap: 22px;
  align-items: end;
  max-width: none;
}

.section-head--narrow {
  max-width: 62ch;
}

.feature-grid,
.pricing-grid,
.trust-grid {
  display: grid;
  gap: 18px;
  margin-top: 24px;
}

.feature-grid,
.pricing-grid,
.trust-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.feature-card,
.workflow-card,
.trust-grid article {
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(17, 32, 59, 0.08);
  border-radius: 26px;
  padding: 24px;
  box-shadow: 0 14px 36px rgba(17, 32, 59, 0.06);
}

.feature-card h3 {
  margin: 16px 0 10px;
  font-size: 1.28rem;
  line-height: 1.15;
}

.workflow-card {
  margin-top: 24px;
  background: linear-gradient(135deg, rgba(17, 32, 59, 0.96), rgba(36, 101, 146, 0.92));
}

.workflow-card ul {
  margin: 0;
  padding: 0;
  list-style: none;
}

.workflow-card li {
  position: relative;
  padding-left: 18px;
}

.workflow-card li + li {
  margin-top: 10px;
}

.workflow-card li::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0.7em;
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: rgba(248, 250, 252, 0.86);
}

.workflow-card li {
  color: rgba(248, 250, 252, 0.86);
}

.trust-grid article {
  background: rgba(255, 255, 255, 0.05);
  border-color: rgba(255, 255, 255, 0.08);
}

.landing-footer {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) auto auto;
  gap: 18px;
  align-items: center;
  padding: 28px 0 42px;
}

.landing-footer nav {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.landing-footer a {
  color: #4b5d76;
  text-decoration: none;
  font-weight: 600;
}

@media (max-width: 1080px) {
  .hero-grid,
  .section-head--split,
  .feature-grid,
  .pricing-grid,
  .trust-grid,
  .landing-footer {
    grid-template-columns: 1fr;
  }

}

@media (max-width: 720px) {
  .landing-hero {
    padding-top: 16px;
  }

  .landing-nav {
    align-items: flex-start;
    flex-direction: column;
  }

  .hero-metrics {
    grid-template-columns: 1fr;
  }

  .hero-card,
  .feature-card,
  .workflow-card,
  .trust-grid article {
    padding: 20px;
    border-radius: 22px;
  }
}
</style>