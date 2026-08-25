<script setup>
import { ref, onUnmounted, nextTick } from 'vue'
import { CameraIcon, ArrowPathIcon, CheckIcon } from '@heroicons/vue/24/outline'

const emit = defineEmits(['captured'])

const showModal    = ref(false)
const videoEl      = ref(null)
const canvasEl     = ref(null)
const capturedUrl  = ref(null)
const stream       = ref(null)
const facingMode   = ref('environment') // 'environment' = arrière, 'user' = avant
const errorMsg     = ref('')
const loading      = ref(false)
const canToggle    = ref(false)

async function openCamera() {
  showModal.value   = true
  capturedUrl.value = null
  errorMsg.value    = ''
  // Detect if device has multiple cameras to decide whether to show toggle button
  try {
    const devices = await navigator.mediaDevices.enumerateDevices()
    const cameras = devices.filter(d => d.kind === 'videoinput')
    canToggle.value = cameras.length > 1
  } catch (_) {
    canToggle.value = false
  }
  await nextTick()
  await startStream()
}

async function startStream() {
  stopStream()
  loading.value  = true
  errorMsg.value = ''
  try {
    stream.value = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: facingMode.value, width: { ideal: 1280 }, height: { ideal: 720 } },
      audio: false,
    })
    await nextTick()
    if (videoEl.value) {
      videoEl.value.srcObject = stream.value
      await videoEl.value.play()
    }
  } catch (err) {
    if (err.name === 'NotAllowedError') {
      errorMsg.value = 'Accès à la caméra refusé. Vérifiez les permissions du navigateur.'
    } else if (err.name === 'NotFoundError') {
      errorMsg.value = 'Aucune caméra détectée sur cet appareil.'
    } else {
      errorMsg.value = 'Impossible d\'accéder à la caméra : ' + (err.message ?? err)
    }
  } finally {
    loading.value = false
  }
}

function stopStream() {
  stream.value?.getTracks().forEach(t => t.stop())
  stream.value = null
}

function capture() {
  if (!videoEl.value || !canvasEl.value) return
  const v = videoEl.value
  canvasEl.value.width  = v.videoWidth
  canvasEl.value.height = v.videoHeight
  canvasEl.value.getContext('2d').drawImage(v, 0, 0)
  capturedUrl.value = canvasEl.value.toDataURL('image/jpeg', 0.92)
  stopStream()
}

function retake() {
  capturedUrl.value = null
  startStream()
}

function usePhoto() {
  if (!canvasEl.value || !capturedUrl.value) return
  canvasEl.value.toBlob(blob => {
    const file = new File([blob], `photo-${Date.now()}.jpg`, { type: 'image/jpeg' })
    emit('captured', file)
    close()
  }, 'image/jpeg', 0.92)
}

function close() {
  stopStream()
  showModal.value   = false
  capturedUrl.value = null
  errorMsg.value    = ''
}

async function toggleCamera() {
  facingMode.value = facingMode.value === 'environment' ? 'user' : 'environment'
  await startStream()
}

onUnmounted(() => stopStream())
</script>

<template>
  <!-- Trigger button — styled to match file upload zones -->
  <button type="button" @click="openCamera"
          class="inline-flex items-center gap-2 px-4 py-2.5 border-2 border-dashed border-gray-300 rounded-xl text-sm text-gray-500 hover:border-blue-400 hover:bg-blue-50 hover:text-blue-600 transition cursor-pointer w-full justify-center">
    <CameraIcon class="w-5 h-5" />
    <span class="font-medium">Prendre une photo</span>
  </button>

  <Teleport to="body">
    <div v-if="showModal"
         class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-4"
         @keydown.esc="close">

      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden">

        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-3 border-b">
          <h3 class="font-semibold text-gray-900 flex items-center gap-2">
            <CameraIcon class="w-5 h-5 text-blue-600" />
            Prendre une photo
          </h3>
          <button type="button" @click="close"
                  class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <!-- Camera / Captured preview -->
        <div class="relative bg-black" style="aspect-ratio:16/9">

          <!-- Loading spinner -->
          <div v-if="loading"
               class="absolute inset-0 flex flex-col items-center justify-center text-white gap-3">
            <div class="w-10 h-10 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            <span class="text-sm">Ouverture de la caméra…</span>
          </div>

          <!-- Error -->
          <div v-else-if="errorMsg"
               class="absolute inset-0 flex flex-col items-center justify-center text-white gap-3 px-8 text-center">
            <CameraIcon class="w-12 h-12 opacity-40" />
            <p class="text-sm">{{ errorMsg }}</p>
          </div>

          <!-- Live video -->
          <video v-show="!capturedUrl && !loading && !errorMsg"
                 ref="videoEl"
                 autoplay
                 playsinline
                 muted
                 class="absolute inset-0 w-full h-full object-cover" />

          <!-- Captured image preview -->
          <img v-if="capturedUrl"
               :src="capturedUrl"
               class="absolute inset-0 w-full h-full object-cover" />

          <!-- Shutter ring overlay when live -->
          <div v-if="!capturedUrl && !loading && !errorMsg"
               class="absolute bottom-4 left-1/2 -translate-x-1/2 pointer-events-none">
            <div class="w-14 h-14 rounded-full border-4 border-white/60 opacity-70"></div>
          </div>

          <!-- Hidden canvas for capturing -->
          <canvas ref="canvasEl" class="hidden" />
        </div>

        <!-- Controls -->
        <div class="px-5 py-4 flex items-center justify-between gap-3 bg-gray-50 border-t">

          <!-- Left: toggle camera (only when live and multi-camera) -->
          <div class="flex-1 flex justify-start">
            <button v-if="!capturedUrl && !loading && !errorMsg && canToggle"
                    type="button"
                    @click="toggleCamera"
                    class="inline-flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 bg-white px-3 py-2 rounded-lg hover:bg-gray-100 transition"
                    title="Basculer caméra avant / arrière">
              <ArrowPathIcon class="w-4 h-4" />
              Retourner
            </button>
          </div>

          <!-- Center: main action -->
          <div class="flex items-center gap-2">
            <!-- Retake -->
            <button v-if="capturedUrl"
                    type="button"
                    @click="retake"
                    class="inline-flex items-center gap-1.5 text-sm text-gray-600 border border-gray-300 bg-white px-4 py-2 rounded-lg hover:bg-gray-100 transition">
              <ArrowPathIcon class="w-4 h-4" />
              Reprendre
            </button>

            <!-- Capture shutter -->
            <button v-if="!capturedUrl && !loading && !errorMsg"
                    type="button"
                    @click="capture"
                    class="w-14 h-14 rounded-full bg-white border-4 border-blue-400 hover:bg-blue-50 shadow-lg transition flex items-center justify-center"
                    title="Capturer">
              <div class="w-10 h-10 rounded-full bg-blue-600 hover:bg-blue-700 transition"></div>
            </button>

            <!-- Use photo -->
            <button v-if="capturedUrl"
                    type="button"
                    @click="usePhoto"
                    class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-xl font-medium text-sm transition shadow">
              <CheckIcon class="w-4 h-4" />
              Utiliser cette photo
            </button>
          </div>

          <!-- Right: spacer for symmetry -->
          <div class="flex-1"></div>
        </div>

      </div>
    </div>
  </Teleport>
</template>
