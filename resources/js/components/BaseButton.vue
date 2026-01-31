<script setup>
defineProps({
  type: { type: String, default: 'button' }
})
</script>

<template>
  <button :type="type" class="btn">
    <slot />
  </button>
</template>

<style scoped>
.btn { width: 100%; padding: 12px 14px; background: var(--black); color: white; border-radius: 10px; font-weight: 700; margin-top: 16px; box-shadow: 0 8px 24px rgba(2,6,23,0.12); border: 1px solid rgba(255,255,255,0.04); transition: transform .08s ease, box-shadow .12s ease, opacity .12s ease }
.btn:hover { transform: translateY(-1px); opacity: 0.98 }
.btn:active { transform: translateY(0) }
.btn[disabled] { opacity: 0.6; pointer-events: none; filter: grayscale(.02) }
</style>
