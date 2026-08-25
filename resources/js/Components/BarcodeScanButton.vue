<script setup>
/**
 * BarcodeScanButton — bouton caméra inline réutilisable.
 *
 * Comportement :
 * - HTTPS / localhost → scanner caméra en direct via Html5Qrcode
 * - HTTP (LAN)        → ouvre directement l'app caméra native via <input capture>
 *                        (solution fiable sur mobile même sans HTTPS)
 *
 * Usage :
 *   <BarcodeScanButton @scanned="onCode" title="Scanner un retour" />
 *
 * Événements :
 *   scanned(code: string) — code barre détecté
 */
import { ref, onUnmounted } from 'vue'
import { CameraIcon, XMarkIcon, ArrowPathIcon, PhotoIcon } from '@heroicons/vue/24/outline'
import { Html5Qrcode } from 'html5-qrcode'

const props = defineProps({
  title:     { type: String, default: 'Scanner un code barre' },
  /** Apparence du bouton déclencheur */
  variant:   { type: String, default: 'icon' }, // 'icon' | 'button'
})

const emit = defineEmits(['scanned'])

// ID unique pour le div html5-qrcode (plusieurs composants sur la même page)
const uid        = `bcr-${Math.random().toString(36).slice(2, 8)}`
const showModal  = ref(false)
const scanning   = ref(false)
const errorMsg   = ref(null)
const fileInputEl = ref(null)
let qrInstance   = null

// L'app tourne en HTTP sur le LAN ; la caméra en direct nécessite HTTPS ou localhost
const isSecure = location.protocol === 'https:'
  || location.hostname === 'localhost'
  || location.hostname === '127.0.0.1'

// ─── Ouverture du panneau ─────────────────────────────────────────────────────
async function open() {
  showModal.value = true
  errorMsg.value  = null
  scanning.value  = false

  if (isSecure) {
    // Laisser le temps au DOM de créer le div avant d'initialiser le scanner
    setTimeout(startLiveScanner, 100)
  }
  // En HTTP : l'utilisateur clique sur le bouton photo → pas de live scanner
}

// ─── Scanner caméra en direct (HTTPS uniquement) ──────────────────────────────
async function startLiveScanner() {
  try {
    qrInstance = new Html5Qrcode(uid)
    scanning.value = true
    await qrInstance.start(
      { facingMode: 'environment' },
      { fps: 12, qrbox: { width: 260, height: 110 } },
      (code) => {
        emit('scanned', code.trim())
        close()
      },
      () => {} // ignore scan errors (frame par frame)
    )
  } catch (e) {
    scanning.value = false
    errorMsg.value = String(e).includes('NotAllowed') || String(e).includes('ermission')
      ? 'Accès caméra refusé — autorisez-le dans les paramètres du navigateur.'
      : 'Caméra inaccessible. Utilisez le bouton photo ci-dessous.'
  }
}

// ─── Fallback : photo depuis l'app caméra native (HTTP + HTTPS) ───────────────
async function scanFromFile(event) {
  const file = event.target.files?.[0]
  if (!file) return
  errorMsg.value = null

  // Créer une instance temporaire pour décoder le fichier
  const tempDiv = document.createElement('div')
  tempDiv.id    = `${uid}-file`
  tempDiv.style.display = 'none'
  document.body.appendChild(tempDiv)
  const tempQr = new Html5Qrcode(`${uid}-file`)

  try {
    const code = await tempQr.scanFile(file, false)
    emit('scanned', code.trim())
    close()
  } catch {
    errorMsg.value = 'Aucun code-barres détecté dans la photo. Réessayez en cadrant mieux le code.'
  } finally {
    try { tempDiv.remove() } catch {}
    event.target.value = ''
  }
}

// ─── Fermeture ────────────────────────────────────────────────────────────────
async function close() {
  if (qrInstance?.isScanning) {
    try { await qrInstance.stop() } catch {}
  }
  qrInstance     = null
  scanning.value = false
  showModal.value = false
  errorMsg.value  = null
}

onUnmounted(() => {
  if (qrInstance?.isScanning) {
    try { qrInstance.stop() } catch {}
  }
})
</script>

<template>
  <!-- ── Bouton déclencheur ── -->
  <button v-if="variant === 'icon'"
    type="button"
    @click="open"
    :title="title"
    class="p-2 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition flex-shrink-0">
    <CameraIcon class="w-5 h-5" />
  </button>
  <button v-else
    type="button"
    @click="open"
    class="flex items-center gap-1.5 px-3 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg text-sm font-medium transition flex-shrink-0">
    <CameraIcon class="w-4 h-4" />
    Scanner avec la caméra
  </button>

  <!-- ── Modal scanner ── -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-4"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0 translate-y-4">

      <div v-if="showModal"
        class="fixed inset-0 z-[500] flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-sm p-3 sm:p-6"
        @click.self="close">

        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">

          <!-- En-tête -->
          <div class="flex items-center gap-2 px-4 py-3 bg-gray-50 border-b">
            <CameraIcon class="w-5 h-5 text-blue-600 flex-shrink-0" />
            <p class="font-semibold text-gray-800 text-sm flex-1">{{ title }}</p>
            <button @click="close" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-200 transition">
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Corps -->
          <div class="p-4 space-y-3">

            <!-- Cas HTTPS : viewer caméra en direct -->
            <template v-if="isSecure">
              <div v-if="!errorMsg" class="relative rounded-xl overflow-hidden bg-black min-h-[200px] flex items-center justify-center">
                <!-- Le div est le conteneur html5-qrcode -->
                <div :id="uid" class="w-full" style="min-height:200px" />
                <!-- Spinner pendant l'init -->
                <div v-if="!scanning" class="absolute inset-0 flex flex-col items-center justify-center bg-black gap-2">
                  <ArrowPathIcon class="w-6 h-6 text-white animate-spin" />
                  <p class="text-white text-xs">Démarrage de la caméra…</p>
                </div>
              </div>
            </template>

            <!-- Cas HTTP : pas de caméra en direct → bouton photo direct -->
            <template v-else>
              <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-center">
                <CameraIcon class="w-8 h-8 text-blue-400 mx-auto mb-2" />
                <p class="text-sm text-blue-800 font-medium mb-1">Scanner avec la caméra</p>
                <p class="text-xs text-blue-600">Appuyez sur le bouton ci-dessous pour prendre une photo du code barre.</p>
              </div>
            </template>

            <!-- Message d'erreur -->
            <div v-if="errorMsg" class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-sm text-amber-800">
              <p class="font-medium mb-1">⚠️ Caméra indisponible</p>
              <p class="text-xs opacity-80">{{ errorMsg }}</p>
            </div>

            <!-- Bouton photo (toujours disponible : fallback si erreur caméra, ou mode HTTP) -->
            <div>
              <input ref="fileInputEl" type="file" accept="image/*" capture="environment"
                class="hidden" @change="scanFromFile" />
              <button @click="fileInputEl.click()" type="button"
                class="w-full flex items-center justify-center gap-2 py-3 rounded-xl font-semibold text-sm transition"
                :class="isSecure && !errorMsg
                  ? 'bg-gray-100 hover:bg-gray-200 text-gray-600 border border-gray-200'
                  : 'bg-blue-600 hover:bg-blue-700 text-white shadow-sm'">
                <PhotoIcon class="w-5 h-5" />
                {{ isSecure && !errorMsg ? 'Ou prendre une photo' : 'Prendre une photo du code barre' }}
              </button>
            </div>

            <p class="text-xs text-gray-400 text-center">
              {{ isSecure ? 'Pointez la caméra vers le code — détection automatique' : 'La photo sera analysée automatiquement' }}
            </p>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
