<template>
  <article class="pricing-card" :class="{ 'pricing-card--featured': plan.recommended }">
    <span class="pricing-card__tag">{{ plan.name }}</span>
    <h3>{{ plan.price }}€ <span class="pricing-card__period">/mes</span></h3>
    <strong class="pricing-card__users">{{ plan.users > 0 ? 'Hasta ' + plan.users + ' usuarios' : 'Usuarios ilimitados' }}</strong>
    <ul>
      <li v-for="item in plan.features" :key="item">{{ item }}</li>
    </ul>
    <slot name="cta">
      <a class="btn" :class="plan.recommended ? 'btn--solid landing-btn-main' : 'btn--ghost landing-btn-ghost'" :href="ctaHref">{{ ctaText }}</a>
    </slot>
  </article>
</template>

<script setup>
defineProps({
  plan: { type: Object, required: true },
  ctaText: { type: String, default: 'Empezar gratis' },
  ctaHref: { type: String, default: '/register' },
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
</style>
