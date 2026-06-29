<template>
  <div class="signpad-wrapper">
    <canvas
      ref="canvas"
      :width="width"
      :height="height"
      class="signpad-canvas"
      @mousedown="startDraw"
      @mousemove="draw"
      @mouseup="stopDraw"
      @mouseleave="stopDraw"
      @touchstart.prevent="startDrawTouch"
      @touchmove.prevent="drawTouch"
      @touchend="stopDraw"
    />
    <div class="signpad-actions">
      <button type="button" class="signpad-btn" @click="clear">Limpiar</button>
      <button v-if="hasContent" type="button" class="signpad-btn primary" @click="emitConfirm">Confirmar firma</button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const emit = defineEmits(['confirm'])
const props = defineProps({
  width: { type: Number, default: 500 },
  height: { type: Number, default: 200 },
})

const canvas = ref(null)
const isDrawing = ref(false)
const hasContent = ref(false)
const ctx = ref(null)

onMounted(() => {
  if (canvas.value) {
    ctx.value = canvas.value.getContext('2d')
    ctx.value.strokeStyle = '#1f2937'
    ctx.value.lineWidth = 2
    ctx.value.lineCap = 'round'
    ctx.value.lineJoin = 'round'
  }
})

function getPos(e) {
  const rect = canvas.value.getBoundingClientRect()
  return {
    x: (e.clientX - rect.left) * (props.width / rect.width),
    y: (e.clientY - rect.top) * (props.height / rect.height),
  }
}

function startDraw(e) {
  isDrawing.value = true
  hasContent.value = true
  const pos = getPos(e)
  ctx.value.beginPath()
  ctx.value.moveTo(pos.x, pos.y)
}

function draw(e) {
  if (!isDrawing.value) return
  const pos = getPos(e)
  ctx.value.lineTo(pos.x, pos.y)
  ctx.value.stroke()
}

function stopDraw() {
  isDrawing.value = false
}

function startDrawTouch(e) {
  const touch = e.touches[0]
  const mouseEvent = new MouseEvent('mousedown', {
    clientX: touch.clientX,
    clientY: touch.clientY,
  })
  canvas.value.dispatchEvent(mouseEvent)
}

function drawTouch(e) {
  const touch = e.touches[0]
  const mouseEvent = new MouseEvent('mousemove', {
    clientX: touch.clientX,
    clientY: touch.clientY,
  })
  canvas.value.dispatchEvent(mouseEvent)
}

function clear() {
  ctx.value.clearRect(0, 0, props.width, props.height)
  hasContent.value = false
}

function getSVG() {
  const dataUrl = canvas.value.toDataURL('image/png')
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${props.width} ${props.height}" width="${props.width}" height="${props.height}">
    <foreignObject width="${props.width}" height="${props.height}">
      <img xmlns="http://www.w3.org/1999/xhtml" src="${dataUrl}" style="width:100%;height:100%" />
    </foreignObject>
  </svg>`
}

function emitConfirm() {
  emit('confirm', getSVG())
}

defineExpose({ clear, getSVG })
</script>

<style scoped>
.signpad-wrapper {
  border: 2px dashed #d1d5db;
  border-radius: 8px;
  padding: 4px;
  display: inline-block;
  background: #fff;
}
.signpad-canvas {
  display: block;
  cursor: crosshair;
  touch-action: none;
}
.signpad-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 8px;
}
.signpad-btn {
  padding: 6px 16px;
  border-radius: 6px;
  border: 1px solid #d1d5db;
  background: #fff;
  cursor: pointer;
  font-size: 13px;
}
.signpad-btn.primary {
  background: #4338ca;
  color: #fff;
  border-color: #4338ca;
}
</style>
