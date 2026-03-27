<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import AssetThumbnail from '@/Components/AssetThumbnail.vue'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import axios from 'axios'
import { useSound } from '@/composables/useSound'
import { Html5Qrcode } from 'html5-qrcode'

const { playSound } = useSound()

// ─── Scanner local (caméra bureau) ──────────────────────────────────────────
const scanResult   = ref(null)
const loading      = ref(false)
const error        = ref(null)
const manualCode   = ref('')
const returning    = ref(false)
const returnSuccess = ref(null)
let html5QrCode    = null

const startScanner = async () => {
  html5QrCode = new Html5Qrcode('qr-reader')
  try {
    await html5QrCode.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 250, height: 250 } },
      onScanSuccess,
      () => {}
    )
  } catch (e) {
    error.value = 'Impossible d\'accéder à la caméra: ' + e
  }
}

const onScanSuccess = async (code) => {
  if (html5QrCode?.isScanning) await html5QrCode.stop()
  await lookupCode(code)
}

const lookupCode = async (code) => {
  loading.value = true
  error.value = null
  scanResult.value = null
  try {
    const { data } = await axios.post(route('scanner.lookup'), { code })
    scanResult.value = data
    playSound('scan_success')
  } catch (e) {
    error.value = e.response?.data?.error ?? 'Équipement non trouvé'
    playSound('scan_error')
  } finally {
    loading.value = false
  }
}

const confirmReturn = async () => {
  if (!scanResult.value?.asset) return
  returning.value = true
  try {
    const { data } = await axios.post(route('scanner.return'), {
      asset_id:       scanResult.value.asset.id,
      reservation_id: scanResult.value.reservation?.id ?? null,
    })
    returnSuccess.value = data.message
    scanResult.value.asset.status       = 'available'
    scanResult.value.asset.status_label = 'Disponible'
    scanResult.value.reservation        = null
  } catch (e) {
    error.value = 'Erreur lors du retour'
  } finally {
    returning.value = false
  }
}

const reset = () => {
  scanResult.value    = null
  error.value         = null
  returnSuccess.value = null
  manualCode.value    = ''
  startScanner()
}

// ─── Session apairée (smartphone) ────────────────────────────────────────────
const pairingOpen    = ref(false)
const pairSession    = ref(null)   // { token, mobile_url, qr_svg, expires_in }
const pairLoading    = ref(false)
const pairItems      = ref([])
const pairStatus     = ref(null)   // 'active' | 'expired' | 'closed'
const pairExpiresAt  = ref(null)
const pairError      = ref(null)
const pairReturning  = ref(new Set())
const pairReturnDone = ref(new Set())
let pollInterval     = null

const pairTimeLeft = computed(() => {
  if (!pairExpiresAt.value) return null
  const diff = Math.max(0, Math.floor((new Date(pairExpiresAt.value) - Date.now()) / 1000))
  const m = Math.floor(diff / 60)
  const s = diff % 60
  return `${m}:${s.toString().padStart(2, '0')}`
})

async function openPairing() {
  pairLoading.value = true
  pairError.value   = null
  try {
    const { data } = await axios.post(route('scan-sessions.store'))
    pairSession.value    = data
    pairItems.value      = []
    pairStatus.value     = 'active'
    pairExpiresAt.value  = null
    pairReturning.value  = new Set()
    pairReturnDone.value = new Set()
    pairingOpen.value    = true
    startPolling()
  } catch (e) {
    pairError.value = 'Impossible de créer la session d\'apairage.'
  } finally {
    pairLoading.value = false
  }
}

async function closePairing() {
  stopPolling()
  if (pairSession.value) {
    try { await axios.post(route('scan-sessions.close', pairSession.value.token)) } catch {}
  }
  pairSession.value    = null
  pairItems.value      = []
  pairStatus.value     = null
  pairExpiresAt.value  = null
  pairingOpen.value    = false
  pairError.value      = null
  pairReturning.value  = new Set()
  pairReturnDone.value = new Set()
}

function startPolling() {
  stopPolling()
  pollInterval = setInterval(pollSession, 2000)
}

function stopPolling() {
  if (pollInterval) { clearInterval(pollInterval); pollInterval = null }
}

async function pollSession() {
  if (!pairSession.value) return
  try {
    const { data } = await axios.get(route('scan-sessions.poll', pairSession.value.token))
    pairItems.value     = data.items ?? []
    pairStatus.value    = data.status
    pairExpiresAt.value = data.expires_at ?? null
    if (data.status === 'expired' || data.status === 'closed') stopPolling()
  } catch {}
}

async function returnPairedItem(item) {
  if (pairReturning.value.has(item.id) || pairReturnDone.value.has(item.id)) return
  pairReturning.value = new Set([...pairReturning.value, item.id])
  try {
    const { data: lookup } = await axios.post(route('scanner.lookup'), { code: item.asset_tag })
    if (lookup.reservation) {
      await axios.post(route('scanner.return'), {
        asset_id:       lookup.asset.id,
        reservation_id: lookup.reservation.id,
      })
    }
    pairReturnDone.value = new Set([...pairReturnDone.value, item.id])
  } catch {}
  pairReturning.value = new Set([...pairReturning.value].filter(id => id !== item.id))
}

// ─── Lifecycle ───────────────────────────────────────────────────────────────
onMounted(() => startScanner())

onUnmounted(() => {
  if (html5QrCode?.isScanning) html5QrCode.stop()
  stopPolling()
})
</script>

<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto">

      <!-- En-tête + bouton apairage -->
      <div class="flex items-center justify-between mb-6 gap-4">
        <h1 class="text-2xl font-bold text-gray-900 shrink-0">📷 Scanner QR Code</h1>

        <div class="flex items-center gap-3">
          <p v-if="pairError" class="text-sm text-red-600">{{ pairError }}</p>
          <button v-if="!pairingOpen"
                  @click="openPairing"
                  :disabled="pairLoading"
                  class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50 whitespace-nowrap">
            <span>📱</span>
            <span>{{ pairLoading ? 'Connexion…' : 'Appairer un smartphone' }}</span>
          </button>
          <button v-else
                  @click="closePairing"
                  class="flex items-center gap-2 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition whitespace-nowrap">
            <span>✕</span>
            <span>Fermer la session</span>
          </button>
        </div>
      </div>

      <!-- Grille : scanner local | session apairée -->
      <div :class="pairingOpen ? 'grid grid-cols-1 lg:grid-cols-2 gap-6 items-start' : ''">

        <!-- ── Colonne gauche : scanner local ───────────────────────────── -->
        <div :class="pairingOpen ? '' : 'max-w-lg mx-auto w-full'">

          <!-- Résultat scan local -->
          <div v-if="!scanResult" class="bg-white rounded-xl shadow-sm border p-6">
            <div id="qr-reader" class="rounded-lg overflow-hidden mb-4"></div>
            <p v-if="error" class="text-red-600 text-sm bg-red-50 p-3 rounded-lg mb-4">{{ error }}</p>
            <p v-if="loading" class="text-blue-600 text-sm text-center">Recherche en cours...</p>

            <div class="mt-4 border-t pt-4">
              <p class="text-sm text-gray-500 mb-2">Ou saisir le tag manuellement :</p>
              <div class="flex gap-2">
                <input v-model="manualCode" type="text" placeholder="Ex: ASSET-001"
                  class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                  @keyup.enter="lookupCode(manualCode)" />
                <button @click="lookupCode(manualCode)" :disabled="!manualCode || loading"
                  class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-50">
                  Chercher
                </button>
              </div>
            </div>
          </div>

          <!-- Fiche équipement scanné -->
          <div v-else class="bg-white rounded-xl shadow-sm border p-6">
            <div v-if="returnSuccess"
                 class="bg-green-50 border border-green-200 text-green-800 p-4 rounded-lg mb-4 text-center">
              ✅ {{ returnSuccess }}
            </div>

            <!-- En-tête avec miniature -->
            <div class="flex items-center gap-4 mb-5">
              <AssetThumbnail :src="scanResult.asset.image_url" :alt="scanResult.asset.name" size="lg" />
              <div class="min-w-0">
                <h2 class="font-bold text-lg leading-tight truncate">{{ scanResult.asset.name }}</h2>
                <p class="text-xs text-gray-400 font-mono mt-0.5">{{ scanResult.asset.asset_tag }}</p>
              </div>
            </div>

            <div class="space-y-2 text-sm mb-6">
              <div class="flex justify-between">
                <span class="text-gray-500">Tag</span>
                <span class="font-mono">{{ scanResult.asset.asset_tag }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Catégorie</span>
                <span>{{ scanResult.asset.category ?? '—' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Statut</span>
                <span :class="['font-semibold', scanResult.asset.status === 'available' ? 'text-green-600' : 'text-orange-600']">
                  {{ scanResult.asset.status_label }}
                </span>
              </div>
            </div>

            <div v-if="scanResult.reservation"
                 class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4 text-sm">
              <p class="font-semibold text-orange-800 mb-2">🔴 Actuellement sorti par :</p>
              <p><strong>{{ scanResult.reservation.user }}</strong></p>
              <p class="text-gray-600">{{ scanResult.reservation.title }}</p>
              <p class="text-gray-500 text-xs">Retrait le {{ scanResult.reservation.checkout_at }}</p>
            </div>

            <div class="flex gap-3">
              <button v-if="scanResult.reservation && !returnSuccess"
                      @click="confirmReturn"
                      :disabled="returning"
                      class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2.5 rounded-lg font-medium text-sm disabled:opacity-50">
                {{ returning ? 'Traitement...' : '✅ Confirmer le retour' }}
              </button>
              <button @click="reset"
                      class="flex-1 border border-gray-300 text-gray-700 py-2.5 rounded-lg text-sm hover:bg-gray-50">
                🔄 Nouveau scan
              </button>
            </div>
          </div>
        </div>

        <!-- ── Colonne droite : session apairée ─────────────────────────── -->
        <div v-if="pairingOpen && pairSession" class="space-y-4">

          <!-- Carte QR code -->
          <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex items-center justify-between mb-4">
              <h2 class="font-bold text-base text-gray-900">📱 Session smartphone</h2>
              <div class="flex items-center gap-1.5">
                <template v-if="pairStatus === 'active'">
                  <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                  <span class="text-xs text-green-600 font-medium">Active</span>
                </template>
                <template v-else>
                  <span class="w-2 h-2 rounded-full bg-red-400"></span>
                  <span class="text-xs text-red-500 font-medium">
                    {{ pairStatus === 'expired' ? 'Expirée' : 'Fermée' }}
                  </span>
                </template>
              </div>
            </div>

            <!-- QR Code SVG inline -->
            <div class="flex justify-center mb-3">
              <div class="border-2 border-gray-100 rounded-xl p-2 shadow-inner bg-white"
                   v-html="pairSession.qr_svg"></div>
            </div>

            <p class="text-center text-xs text-gray-500 mb-1">
              Scannez ce code depuis le smartphone à appairer
            </p>
            <p v-if="pairTimeLeft" class="text-center text-xs font-mono text-gray-400">
              ⏱ Expire dans <strong>{{ pairTimeLeft }}</strong>
            </p>

            <details class="mt-3">
              <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-600 text-center select-none">
                Afficher l'URL manuelle
              </summary>
              <p class="mt-1 text-xs text-blue-600 break-all bg-gray-50 border border-gray-200 p-2 rounded-lg select-all font-mono">
                {{ pairSession.mobile_url }}
              </p>
            </details>

            <!-- Bouton nouvelle session si expirée -->
            <div v-if="pairStatus === 'expired' || pairStatus === 'closed'" class="mt-4 text-center">
              <p class="text-sm text-red-500 mb-2">
                {{ pairStatus === 'expired' ? '⚠️ La session a expiré.' : '⚠️ La session est fermée.' }}
              </p>
              <button @click="openPairing" :disabled="pairLoading"
                      class="text-sm font-medium text-indigo-600 hover:text-indigo-800 hover:underline disabled:opacity-50">
                {{ pairLoading ? 'Création…' : '↺ Nouvelle session' }}
              </button>
            </div>
          </div>

          <!-- Carte équipements scannés en direct -->
          <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-semibold text-sm text-gray-800">
                Équipements scannés
                <span class="ml-1.5 bg-indigo-100 text-indigo-700 text-xs font-bold px-2 py-0.5 rounded-full">
                  {{ pairItems.length }}
                </span>
              </h3>
              <div v-if="pairStatus === 'active'" class="flex items-center gap-1 text-xs text-gray-400">
                <span class="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse"></span>
                En direct
              </div>
            </div>

            <div v-if="pairItems.length === 0"
                 class="text-center py-8 text-gray-400">
              <p class="text-3xl mb-2">📦</p>
              <p class="text-sm">En attente de scans depuis le smartphone…</p>
            </div>

            <ul v-else class="space-y-2">
              <li v-for="item in [...pairItems].reverse()"
                  :key="item.id"
                  class="flex items-center gap-3 p-3 rounded-lg border transition"
                  :class="pairReturnDone.has(item.id)
                    ? 'bg-green-50 border-green-200'
                    : 'bg-gray-50 border-gray-200'">

                <!-- Miniature -->
                <AssetThumbnail :src="item.image_url" :alt="item.name" size="sm" />

                <!-- Infos équipement -->
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-gray-900 truncate">{{ item.name }}</p>
                  <p class="text-xs text-gray-500 font-mono">{{ item.asset_tag }}</p>
                </div>

                <!-- Badge statut -->
                <span class="text-xs px-2 py-0.5 rounded-full font-medium flex-shrink-0"
                      :class="item.status === 'available' ? 'bg-green-100 text-green-700' :
                              item.status === 'checked_out' ? 'bg-orange-100 text-orange-700' :
                              item.status === 'reserved'    ? 'bg-blue-100 text-blue-700' :
                              'bg-gray-100 text-gray-600'">
                  {{ item.status === 'available'   ? 'Disponible' :
                     item.status === 'checked_out' ? 'Sorti' :
                     item.status === 'reserved'    ? 'Réservé' :
                     item.status === 'maintenance' ? 'Maintenance' : item.status }}
                </span>

                <!-- Action retour -->
                <div class="flex-shrink-0">
                  <span v-if="pairReturnDone.has(item.id)"
                        class="text-xs font-semibold text-green-600">
                    ✅ Retourné
                  </span>
                  <button v-else-if="item.status !== 'available'"
                          @click="returnPairedItem(item)"
                          :disabled="pairReturning.has(item.id)"
                          class="text-xs bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg font-medium transition disabled:opacity-50">
                    {{ pairReturning.has(item.id) ? '…' : 'Retourner' }}
                  </button>
                </div>
              </li>
            </ul>
          </div>
        </div>

      </div>
    </div>
  </AppLayout>
</template>
