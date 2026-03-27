<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import axios from 'axios'
import { Html5Qrcode } from 'html5-qrcode'

const props = defineProps({
  token:        String,
  scannedItems: { type: Array, default: () => [] },
})

// ─── État ──────────────────────────────────────────────────────────────────
const items         = ref([...props.scannedItems])
const scanning      = ref(false)
const cameraError   = ref(null)
const lastResult    = ref(null)   // { type: 'success'|'warning'|'error', message, asset }
const manualCode    = ref('')
const manualLoading = ref(false)
const manualInputEl = ref(null)
const fileInputEl   = ref(null)
let html5QrCode     = null
let feedbackTimer   = null

// ─── QR/Barcode scanner ───────────────────────────────────────────────────
async function startScanner() {
  cameraError.value = null
  // HTTPS requis par les navigateurs mobiles (sauf localhost)
  const isSecure = location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1'
  if (!isSecure) {
    cameraError.value = 'La caméra nécessite une connexion HTTPS. Utilisez la saisie manuelle.'
    focusManual()
    return
  }
  html5QrCode = new Html5Qrcode('mobile-qr-reader')
  try {
    scanning.value = true
    await html5QrCode.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 280, height: 140 } },
      onScanSuccess,
      () => {}
    )
  } catch (e) {
    scanning.value = false
    const msg = String(e).includes('ermission') || String(e).includes('NotAllowed')
      ? 'Permission caméra refusée. Autorisez l\'accès dans les paramètres du navigateur.'
      : 'Caméra inaccessible. Utilisez la saisie manuelle.'
    cameraError.value = msg
    focusManual()
  }
}

async function retryCamera() {
  if (html5QrCode) { try { await html5QrCode.stop() } catch {} html5QrCode = null }
  scanning.value = false
  cameraError.value = null
  await nextTick()
  startScanner()
}

// ─── Fallback : photo depuis l'app caméra (fonctionne en HTTP) ────────────────
async function scanFromFile(event) {
  const file = event.target.files?.[0]
  if (!file) return
  if (!html5QrCode) html5QrCode = new Html5Qrcode('mobile-qr-reader')
  try {
    const code = await html5QrCode.scanFile(file, false)
    await onScanSuccess(code)
  } catch {
    showFeedback('error', 'Aucun code-barres détecté dans la photo. Réessayez.', null)
  }
  event.target.value = ''
}

function focusManual() {
  nextTick(() => manualInputEl.value?.focus())
}

async function onScanSuccess(code) {
  // Pause scanner le temps de traiter
  if (html5QrCode?.isScanning) {
    await html5QrCode.pause(true)
  }
  await submitCode(code)
  // Reprendre le scanner après un délai
  setTimeout(async () => {
    if (html5QrCode && !html5QrCode.isScanning) {
      try { await html5QrCode.resume() } catch {}
    }
  }, 1500)
}

// ─── Soumission d'un code ─────────────────────────────────────────────────
async function submitCode(code) {
  if (!code.trim()) return
  try {
    const { data } = await axios.post(`/scan/mobile/${props.token}/submit`, { code })
    if (data.success) {
      items.value.unshift({
        id:        data.asset.id,
        name:      data.asset.name,
        asset_tag: data.asset.asset_tag,
        status:    data.asset.status,
        image_url: data.asset.image_url ?? null,
      })
      showFeedback('success', `✓ ${data.asset.name} ajouté`, data.asset)
    } else if (data.warning) {
      showFeedback('warning', data.warning, data.asset)
    }
  } catch (e) {
    const msg = e.response?.data?.error ?? 'Équipement introuvable'
    showFeedback('error', msg, null)
  }
}

async function submitManual() {
  if (!manualCode.value.trim()) return
  manualLoading.value = true
  await submitCode(manualCode.value.trim())
  manualCode.value = ''
  manualLoading.value = false
}

// ─── Feedback visuel ──────────────────────────────────────────────────────
function showFeedback(type, message, asset) {
  clearTimeout(feedbackTimer)
  lastResult.value = { type, message, asset }
  if (type !== 'error') {
    feedbackTimer = setTimeout(() => { lastResult.value = null }, 3000)
  }
}

// ─── Lifecycle ───────────────────────────────────────────────────────────
onMounted(() => startScanner())

onUnmounted(() => {
  clearTimeout(feedbackTimer)
  if (html5QrCode?.isScanning) html5QrCode.stop()
})

// ─── Helper statut ───────────────────────────────────────────────────────
function statusLabel(status) {
  const map = { available: 'Disponible', checked_out: 'Sorti', reserved: 'Réservé', maintenance: 'Maintenance' }
  return map[status] ?? status
}
function statusColor(status) {
  const map = { available: '#10b981', checked_out: '#f59e0b', reserved: '#3b82f6', maintenance: '#f97316' }
  return map[status] ?? '#6b7280'
}
</script>

<template>
  <!-- Page standalone mobile — pas de layout d'application -->
  <div class="min-h-screen bg-gray-900 text-white flex flex-col">

    <!-- En-tête -->
    <div class="bg-gray-800 px-4 py-3 flex items-center gap-3 shadow-lg">
      <span class="text-2xl">📷</span>
      <div>
        <h1 class="font-bold text-base leading-tight">Scanner d'équipements</h1>
        <p class="text-xs text-gray-400">Session apairée · {{ items.length }} scan(s)</p>
      </div>
      <div class="ml-auto flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
        <span class="text-xs text-green-400">Connecté</span>
      </div>
    </div>

    <!-- Feedback résultat dernier scan -->
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0">
      <div v-if="lastResult"
           class="mx-3 mt-3 rounded-xl px-4 py-3 text-sm font-semibold flex items-center gap-2"
           :class="{
             'bg-green-500/20 border border-green-500/40 text-green-300': lastResult.type === 'success',
             'bg-yellow-500/20 border border-yellow-500/40 text-yellow-300': lastResult.type === 'warning',
             'bg-red-500/20 border border-red-500/40 text-red-300': lastResult.type === 'error',
           }">
        <span>{{ lastResult.type === 'success' ? '✓' : lastResult.type === 'warning' ? '⚠' : '✗' }}</span>
        <span>{{ lastResult.message }}</span>
      </div>
    </Transition>

    <!-- Scanner caméra -->
    <div class="mx-3 mt-3 bg-gray-800 rounded-xl overflow-hidden">
      <div id="mobile-qr-reader" class="w-full" :style="cameraError ? 'display:none' : 'min-height:220px'"></div>
      <div v-if="cameraError" class="px-4 py-5 text-center space-y-3">
        <p class="text-sm text-red-400">⚠️ {{ cameraError }}</p>
        <!-- Fallback : ouvre l'app caméra native → fonctionne en HTTP sur mobile -->
        <label class="flex items-center justify-center gap-2 w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm px-4 py-3 rounded-xl cursor-pointer transition">
          📷 Prendre une photo du code-barres
          <input ref="fileInputEl" type="file" accept="image/*" capture="environment"
                 @change="scanFromFile" class="hidden" />
        </label>
        <!-- Aussi proposer depuis la galerie -->
        <label class="flex items-center justify-center gap-2 w-full bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm px-4 py-2 rounded-xl cursor-pointer transition">
          🖼️ Importer une image
          <input type="file" accept="image/*" @change="scanFromFile" class="hidden" />
        </label>
        <button @click="retryCamera"
                class="text-xs text-gray-500 hover:text-gray-300 underline transition">
          Réessayer la caméra en direct
        </button>
      </div>
      <p v-else-if="!scanning" class="text-center text-xs text-gray-500 py-3">
        Chargement de la caméra…
      </p>
    </div>

    <!-- Saisie manuelle -->
    <div class="mx-3 mt-3">
      <div class="flex gap-2">
        <input ref="manualInputEl" v-model="manualCode" type="text" placeholder="Tag manuel (ex: ASSET-001)"
          @keyup.enter="submitManual"
          class="flex-1 bg-gray-800 border border-gray-600 rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500" />
        <button @click="submitManual" :disabled="!manualCode.trim() || manualLoading"
          class="bg-blue-600 text-white px-5 py-3 rounded-xl text-sm font-semibold hover:bg-blue-700 disabled:opacity-40 transition">
          {{ manualLoading ? '…' : 'OK' }}
        </button>
      </div>
    </div>

    <!-- Liste des équipements scannés -->
    <div class="mx-3 mt-4 flex-1 pb-6">
      <div class="flex items-center justify-between mb-2 px-1">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">
          Scannés ({{ items.length }})
        </p>
      </div>

      <div v-if="items.length === 0"
           class="bg-gray-800 rounded-xl px-4 py-10 text-center text-gray-500">
        <p class="text-3xl mb-2">📦</p>
        <p class="text-sm">Scannez un code-barres ou QR code<br>pour commencer</p>
      </div>

      <ul class="space-y-2">
        <li v-for="(item, i) in items" :key="item.id"
            class="bg-gray-800 rounded-xl px-4 py-3 flex items-center gap-3"
            :class="{ 'ring-2 ring-green-500/50': i === 0 && lastResult?.type === 'success' }">
          <!-- Miniature (dark theme) -->
          <div class="w-10 h-10 rounded-lg overflow-hidden flex-shrink-0 flex items-center justify-center bg-gray-700">
            <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="w-full h-full object-cover" />
            <span v-else class="text-lg">📦</span>
          </div>
          <!-- Infos -->
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-white truncate">{{ item.name }}</p>
            <p class="text-xs text-gray-400 font-mono">{{ item.asset_tag }}</p>
          </div>
          <!-- Statut -->
          <span class="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0"
                :style="`background: ${statusColor(item.status)}22; color: ${statusColor(item.status)};`">
            {{ statusLabel(item.status) }}
          </span>
        </li>
      </ul>
    </div>

    <!-- Pied de page -->
    <div class="px-4 py-3 bg-gray-800 border-t border-gray-700 text-center">
      <p class="text-xs text-gray-500">
        Session synchronisée avec le poste de travail
      </p>
    </div>

  </div>
</template>
