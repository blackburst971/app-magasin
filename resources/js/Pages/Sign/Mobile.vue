<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  token:   { type: String, required: true },
  context: { type: Object, required: true },
})

// ─── Canvas signature (même pattern que SignaturePad.vue) ─────────────────────
const canvasRef  = ref(null)
const isDrawing  = ref(false)
const hasSigned  = ref(false)
let ctx          = null
let lastX = 0, lastY = 0

function getPos(e, canvas) {
  const rect   = canvas.getBoundingClientRect()
  const scaleX = canvas.width  / rect.width
  const scaleY = canvas.height / rect.height
  if (e.touches) {
    return {
      x: (e.touches[0].clientX - rect.left) * scaleX,
      y: (e.touches[0].clientY - rect.top)  * scaleY,
    }
  }
  return {
    x: (e.clientX - rect.left) * scaleX,
    y: (e.clientY - rect.top)  * scaleY,
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
  ctx.lineWidth   = 3
  ctx.lineCap     = 'round'
  ctx.lineJoin    = 'round'
  ctx.strokeStyle = '#1e293b'
  ctx.lineTo(pos.x, pos.y)
  ctx.stroke()
  lastX = pos.x
  lastY = pos.y
  hasSigned.value = true
}

function stopDraw(e) {
  e.preventDefault()
  isDrawing.value = false
  ctx.closePath()
}

function clearCanvas() {
  ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height)
  hasSigned.value = false
  error.value = ''
}

onMounted(() => {
  const canvas  = canvasRef.value
  ctx           = canvas.getContext('2d')
  canvas.width  = canvas.offsetWidth  || 400
  canvas.height = canvas.offsetHeight || 260

  // Enregistrement avec passive:false indispensable pour preventDefault sur mobile
  canvas.addEventListener('mousedown',  startDraw)
  canvas.addEventListener('mousemove',  draw)
  canvas.addEventListener('mouseup',    stopDraw)
  canvas.addEventListener('mouseleave', stopDraw)
  canvas.addEventListener('touchstart', startDraw, { passive: false })
  canvas.addEventListener('touchmove',  draw,      { passive: false })
  canvas.addEventListener('touchend',   stopDraw,  { passive: false })
})

onUnmounted(() => {
  const canvas = canvasRef.value
  if (!canvas) return
  canvas.removeEventListener('mousedown',  startDraw)
  canvas.removeEventListener('mousemove',  draw)
  canvas.removeEventListener('mouseup',    stopDraw)
  canvas.removeEventListener('mouseleave', stopDraw)
  canvas.removeEventListener('touchstart', startDraw)
  canvas.removeEventListener('touchmove',  draw)
  canvas.removeEventListener('touchend',   stopDraw)
})

// ─── Soumission ───────────────────────────────────────────────────────────────
const submitting = ref(false)
const submitted  = ref(false)
const error      = ref('')

async function submit() {
  if (!hasSigned.value) {
    error.value = 'Veuillez signer avant de valider.'
    return
  }
  error.value      = ''
  submitting.value = true

  const signature = canvasRef.value.toDataURL('image/png')

  try {
    await axios.post(`/sign/mobile/${props.token}/submit`, { signature })
    submitted.value = true
  } catch (e) {
    error.value      = e.response?.data?.error ?? 'Erreur lors de l\'envoi. Veuillez réessayer.'
    submitting.value = false
  }
}
</script>

<template>
  <!-- Page standalone — plein écran mobile, optimisée tactile -->
  <div class="min-h-screen bg-slate-50 flex flex-col" style="overscroll-behavior: none;">

    <!-- ── Succès ── -->
    <div v-if="submitted"
         class="flex-1 flex flex-col items-center justify-center px-6 text-center">
      <div class="text-7xl mb-6">✅</div>
      <h1 class="text-2xl font-bold text-green-700 mb-2">Signature enregistrée</h1>
      <p class="text-gray-500 text-sm leading-relaxed">
        Votre signature a été transmise.<br>
        Vous pouvez fermer cette page.
      </p>
      <div class="mt-8 bg-green-50 border border-green-200 rounded-xl px-5 py-4 text-sm text-green-800 max-w-xs">
        La réservation de <strong>{{ context.student_name }}</strong> va maintenant être traitée.
      </div>
    </div>

    <!-- ── Formulaire ── -->
    <template v-else>

      <!-- En-tête -->
      <div class="bg-purple-700 text-white px-5 pt-6 pb-5 flex-shrink-0">
        <p class="text-xs font-semibold uppercase tracking-widest text-purple-300 mb-1">
          Signature responsable
        </p>
        <h1 class="text-lg font-bold leading-tight">{{ context.reservation_title }}</h1>
        <p class="text-purple-200 text-sm mt-1">
          Demandeur : <strong class="text-white">{{ context.student_name }}</strong>
        </p>
      </div>

      <!-- Résumé réservation -->
      <div class="px-5 py-3 bg-white border-b border-gray-100 space-y-1.5 flex-shrink-0">
        <div class="flex items-center gap-2 text-sm text-gray-600">
          <span class="text-gray-400">📅</span>
          <span>{{ context.start_date }} → {{ context.end_date }}</span>
        </div>
        <div v-if="context.assets" class="flex items-start gap-2 text-sm text-gray-600">
          <span class="text-gray-400 mt-0.5">📦</span>
          <span>{{ context.assets }}</span>
        </div>
        <div class="flex items-center gap-2 text-sm">
          <span class="text-gray-400">✍️</span>
          <span class="font-semibold text-purple-700">Signataire : {{ context.supervisor_name }}</span>
        </div>
      </div>

      <!-- Zone de signature + boutons -->
      <div class="flex-1 flex flex-col px-4 py-4 gap-3 min-h-0">

        <p class="text-sm font-semibold text-gray-700 text-center flex-shrink-0">
          Signez dans la zone ci-dessous avec votre doigt
        </p>

        <!-- Canvas wrapper — hauteur fixe pour éviter les problèmes de flex -->
        <div class="relative rounded-2xl overflow-hidden border-2 flex-shrink-0"
             :class="hasSigned ? 'border-purple-400 bg-white' : 'border-dashed border-gray-300 bg-white'"
             style="height: 260px;">
          <canvas
            ref="canvasRef"
            class="block w-full h-full"
            style="touch-action: none; user-select: none; cursor: crosshair;"
          />
          <!-- Watermark quand vide -->
          <div v-if="!hasSigned"
               class="absolute inset-0 flex items-center justify-center pointer-events-none select-none">
            <p class="text-gray-300 text-base">Signez ici</p>
          </div>
        </div>

        <!-- Effacer -->
        <button v-if="hasSigned"
                @click="clearCanvas"
                class="text-xs text-gray-400 underline text-center flex-shrink-0">
          Effacer et recommencer
        </button>

        <!-- Erreur -->
        <p v-if="error" class="text-sm text-red-600 text-center font-medium flex-shrink-0">{{ error }}</p>

        <!-- Bouton valider -->
        <button
          @click="submit"
          :disabled="submitting"
          class="w-full py-4 rounded-2xl font-bold text-base transition shadow-md flex-shrink-0"
          :class="hasSigned
            ? 'bg-purple-600 text-white active:bg-purple-700'
            : 'bg-gray-200 text-gray-400 cursor-not-allowed'">
          {{ submitting ? 'Envoi en cours…' : '✅ Valider ma signature' }}
        </button>

        <!-- Info légale -->
        <p class="text-xs text-gray-400 text-center leading-relaxed flex-shrink-0">
          En validant, vous confirmez avoir pris connaissance de cette demande et l'approuvez.
        </p>

      </div>

    </template>
  </div>
</template>
