<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const emit = defineEmits(['update:modelValue'])

const canvasRef = ref(null)
const isDrawing = ref(false)
const hasSignature = ref(false)
let ctx = null
let lastX = 0
let lastY = 0

function getPos(e, canvas) {
  const rect = canvas.getBoundingClientRect()
  const scaleX = canvas.width / rect.width
  const scaleY = canvas.height / rect.height
  if (e.touches) {
    return {
      x: (e.touches[0].clientX - rect.left) * scaleX,
      y: (e.touches[0].clientY - rect.top) * scaleY,
    }
  }
  return {
    x: (e.clientX - rect.left) * scaleX,
    y: (e.clientY - rect.top) * scaleY,
  }
}

function startDraw(e) {
  e.preventDefault()
  isDrawing.value = true
  const pos = getPos(e, canvasRef.value)
  lastX = pos.x
  lastY = pos.y
  ctx.beginPath()
  ctx.moveTo(lastX, lastY)
}

function draw(e) {
  e.preventDefault()
  if (!isDrawing.value) return
  const pos = getPos(e, canvasRef.value)
  ctx.lineWidth = 2
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.strokeStyle = '#1e293b'
  ctx.lineTo(pos.x, pos.y)
  ctx.stroke()
  lastX = pos.x
  lastY = pos.y
  hasSignature.value = true
}

function stopDraw(e) {
  e.preventDefault()
  if (!isDrawing.value) return
  isDrawing.value = false
  ctx.closePath()
  if (hasSignature.value) {
    emit('update:modelValue', canvasRef.value.toDataURL('image/png'))
  }
}

function clearSignature() {
  const canvas = canvasRef.value
  ctx.clearRect(0, 0, canvas.width, canvas.height)
  drawPlaceholder()
  hasSignature.value = false
  emit('update:modelValue', null)
}

function drawPlaceholder() {
  const canvas = canvasRef.value
  ctx.save()
  ctx.fillStyle = '#cbd5e1'
  ctx.font = '14px sans-serif'
  ctx.textAlign = 'center'
  ctx.fillText('Signez ici', canvas.width / 2, canvas.height / 2)
  ctx.restore()
}

onMounted(() => {
  const canvas = canvasRef.value
  ctx = canvas.getContext('2d')
  canvas.width = canvas.offsetWidth || 500
  canvas.height = canvas.offsetHeight || 160
  drawPlaceholder()

  // Mouse events
  canvas.addEventListener('mousedown', startDraw)
  canvas.addEventListener('mousemove', draw)
  canvas.addEventListener('mouseup', stopDraw)
  canvas.addEventListener('mouseleave', stopDraw)

  // Touch events
  canvas.addEventListener('touchstart', startDraw, { passive: false })
  canvas.addEventListener('touchmove', draw, { passive: false })
  canvas.addEventListener('touchend', stopDraw, { passive: false })
})

onUnmounted(() => {
  const canvas = canvasRef.value
  if (!canvas) return
  canvas.removeEventListener('mousedown', startDraw)
  canvas.removeEventListener('mousemove', draw)
  canvas.removeEventListener('mouseup', stopDraw)
  canvas.removeEventListener('mouseleave', stopDraw)
  canvas.removeEventListener('touchstart', startDraw)
  canvas.removeEventListener('touchmove', draw)
  canvas.removeEventListener('touchend', stopDraw)
})
</script>

<template>
  <div class="signature-pad-wrapper">
    <canvas
      ref="canvasRef"
      class="signature-canvas"
      :class="{ 'has-content': hasSignature }"
    ></canvas>
    <div class="flex items-center justify-between mt-2">
      <span class="text-xs text-gray-400">Utilisez la souris ou votre doigt pour signer</span>
      <button
        type="button"
        @click="clearSignature"
        class="text-xs text-red-500 hover:text-red-700 border border-red-200 px-2 py-1 rounded hover:bg-red-50 transition">
        Effacer
      </button>
    </div>
  </div>
</template>

<style scoped>
.signature-pad-wrapper {
  width: 100%;
}
.signature-canvas {
  width: 100%;
  height: 160px;
  border: 2px dashed #cbd5e1;
  border-radius: 8px;
  background: #f8fafc;
  cursor: crosshair;
  display: block;
  touch-action: none;
  user-select: none;
}
.signature-canvas.has-content {
  border-color: #94a3b8;
  border-style: solid;
}
</style>
