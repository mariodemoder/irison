<template>
  <div class="faq-list">
    <article v-for="faq in faqs" :key="faq.question" class="faq-item">
      <button class="faq-question" @click="toggle(faq.question)">
        <h3>{{ faq.question }}</h3>
        <span class="faq-arrow" :class="{ open: openItems.includes(faq.question) }">▼</span>
      </button>
      <div v-if="openItems.includes(faq.question)" class="faq-answer">
        <p>{{ faq.answer }}</p>
      </div>
    </article>
  </div>
</template>

<script setup>
import { ref } from 'vue'

defineProps({
  faqs: { type: Array, required: true },
})

const openItems = ref([])

function toggle(q) {
  if (openItems.value.includes(q)) {
    openItems.value = openItems.value.filter(x => x !== q)
  } else {
    openItems.value.push(q)
  }
}
</script>

<style scoped>
.faq-list { display: grid; gap: 12px; margin-top: 16px; }
.faq-item { background: rgba(255, 255, 255, 0.78); border: 1px solid rgba(17, 32, 59, 0.08); border-radius: 16px; overflow: hidden; }
.faq-question { width: 100%; display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: none; border: none; cursor: pointer; text-align: left; }
.faq-question h3 { margin: 0; font-size: 15px; font-weight: 600; color: #1f2937; }
.faq-arrow { font-size: 11px; color: #6b7280; transition: transform .2s; }
.faq-arrow.open { transform: rotate(180deg); }
.faq-answer { padding: 0 20px 16px; }
.faq-answer p { margin: 0; color: #556176; line-height: 1.6; font-size: 14px; }
</style>
