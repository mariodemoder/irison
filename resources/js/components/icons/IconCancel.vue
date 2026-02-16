<template>
  <svg class="icon-cancel" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <!-- círculo exterior -->
    <circle cx="12" cy="12" r="12" class="outer" />
    
    <!-- círculo interior -->
    <circle cx="12" cy="12" r="6" class="inner" />
  </svg>
</template>

<script setup>
// Indicador visual cancelado (doble punto)
</script>

<style scoped>
.icon-cancel {
  width:12px;
  height:12px;
  margin-right:8px;
  vertical-align:middle;
  display:inline-block;
}

/* rojo suave exterior */
.outer {
  fill:#fecaca;   /* rojo claro */
}

/* rojo fuerte interior */
.inner {
  fill:#dc2626;   /* rojo principal */
}
</style>
