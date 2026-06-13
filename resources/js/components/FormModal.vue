<template>
  <Teleport to="body">
    <Transition name="fm">
      <div v-if="show" class="fm-overlay" @click.self="$emit('close')">
        <div class="fm-panel" :style="{ maxWidth: width }">
          <div class="fm-header">
            <h2 class="fm-title">{{ title }}</h2>
            <button class="fm-close" @click="$emit('close')" type="button">✕</button>
          </div>
          <div class="fm-body">
            <slot />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  width: { type: String, default: '640px' },
})

defineEmits(['close'])
</script>

<style scoped>
.fm-overlay {
  position:fixed; inset:0; z-index:9999;
  background:rgba(2,6,23,0.45);
  display:flex; align-items:flex-start; justify-content:center;
  padding:40px 16px; overflow-y:auto;
}
.fm-panel {
  background:#fff; border-radius:14px; width:100%;
  box-shadow:0 20px 60px rgba(2,6,23,0.18);
  margin-top:20px; margin-bottom:20px;
}
.fm-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:16px 20px; border-bottom:1px solid #f3f4f6;
}
.fm-title { margin:0; font-size:18px; font-weight:700; color:#111827 }
.fm-close {
  background:none; border:none; font-size:18px; cursor:pointer;
  color:#9ca3af; padding:4px 8px; border-radius:6px;
}
.fm-close:hover { background:#f3f4f6; color:#111827 }
.fm-body { padding:20px }

.fm-enter-active, .fm-leave-active { transition:opacity .15s ease }
.fm-enter-from, .fm-leave-to { opacity:0 }
</style>
