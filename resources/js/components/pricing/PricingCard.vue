<template>
  <article class="pricing-card" :class="{ 'pricing-card--featured': plan.recommended }">
    <span class="pricing-card__tag">{{ plan.name }}</span>
    <h3>{{ plan.price }}€ <span class="pricing-card__period">/mes</span></h3>
    <strong class="pricing-card__users">{{ plan.users > 0 ? 'Hasta ' + plan.users + ' usuarios' : 'Usuarios ilimitados' }}</strong>
    <ul>
      <li v-for="item in plan.features" :key="item">
        <a v-if="item.startsWith('Todo ')" class="plan-link" href="#" @click.prevent="showFeaturesModal = true">{{ item }}</a>
        <template v-else>{{ item }}</template>
      </li>
    </ul>
    <slot name="cta">
      <a class="btn" :class="plan.recommended ? 'btn--solid landing-btn-main' : 'btn--ghost landing-btn-ghost'" :href="ctaHref">{{ ctaText }}</a>
    </slot>
  </article>

  <Teleport to="body">
    <div v-if="showFeaturesModal" class="plan-modal-backdrop" @click.self="showFeaturesModal = false">
      <div class="plan-modal-content">
        <h3>Funcionalidades del plan Basic</h3>
        <ul class="plan-modal-features">
          <li v-for="f in basicFeatures" :key="f">{{ f }}</li>
        </ul>
        <button class="btn btn--ghost plan-modal-close" @click="showFeaturesModal = false">Cerrar</button>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  plan: { type: Object, required: true },
  ctaText: { type: String, default: 'Empezar gratis' },
  ctaHref: { type: String, default: '/register' },
  allPlans: { type: Object, default: () => ({}) },
})

const showFeaturesModal = ref(false)

const basicFeatures = computed(() => {
  return props.allPlans?.basic?.features || []
})
</script>

<style scoped>
.pricing-card {
  display: flex;
  flex-direction: column;
  gap: 14px;
  background: rgba(255, 255, 255, 0.78);
  border: 1px solid rgba(17, 32, 59, 0.08);
  border-radius: 26px;
  padding: 24px;
  box-shadow: 0 14px 36px rgba(17, 32, 59, 0.06);
}
.pricing-card--featured {
  background: linear-gradient(180deg, var(--violet-900), var(--violet-700));
  color: #f8fafc;
  transform: translateY(-6px);
}
.pricing-card--featured .pricing-card__tag {
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.16);
  color: var(--violet-100);
}
.pricing-card--featured p,
.pricing-card--featured li,
.pricing-card--featured h3 {
  color: #f8fafc;
}
.pricing-card__tag {
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
  width: fit-content;
}
.pricing-card h3 {
  margin: 0;
  font-size: 1.28rem;
  line-height: 1.15;
}
.pricing-card--featured h3 {
  color: #f8fafc;
}
.pricing-card__period {
  font-size: 0.85rem;
  font-weight: 400;
  color: #556176;
}
.pricing-card--featured .pricing-card__period {
  color: rgba(248, 250, 252, 0.7);
}
.pricing-card__users {
  display: block;
  font-size: 0.95rem;
}
.pricing-card--featured .pricing-card__users {
  color: #f8fafc;
}
.pricing-card ul {
  margin: 0;
  padding: 0;
  list-style: none;
}
.pricing-card li {
  position: relative;
  padding-left: 18px;
}
.pricing-card li + li {
  margin-top: 10px;
}
.pricing-card li::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0.7em;
  width: 8px;
  height: 8px;
  border-radius: 999px;
  background: rgba(17, 32, 59, 0.2);
}
.pricing-card--featured li::before {
  background: rgba(248, 250, 252, 0.86);
}
.pricing-card .btn {
  margin-top: auto;
  width: fit-content;
}
.plan-link {
  color: inherit;
  text-decoration: underline;
  text-decoration-style: dotted;
  text-underline-offset: 3px;
  cursor: pointer;
}
.plan-link:hover {
  text-decoration-style: solid;
}
</style>

<style>
.plan-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.plan-modal-content {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  min-width: 360px;
  max-width: 90vw;
  max-height: 80vh;
  overflow-y: auto;
}
.plan-modal-content h3 {
  font-size: 18px;
  font-weight: 700;
  margin: 0 0 16px;
  color: #1f2937;
}
.plan-modal-features {
  list-style: none;
  padding: 0;
  margin: 0;
}
.plan-modal-features li {
  position: relative;
  padding-left: 20px;
  font-size: 14px;
  line-height: 2.2;
  color: #374151;
}
.plan-modal-features li::before {
  content: '\2713';
  position: absolute;
  left: 0;
  font-weight: 700;
  color: #10b981;
}
.plan-modal-close {
  margin-top: 16px;
}
</style>
