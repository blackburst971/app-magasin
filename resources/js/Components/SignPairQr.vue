<script setup>
/**
 * SignPairQr.vue
 * Affiche un QR code pour faire signer un responsable depuis un mobile.
 * Polle le serveur et émet 'signed' avec la signature base64 quand reçue.
 *
 * Props:
 *   reservationId  - ID de la réservation
 *   supervisorId   - ID du superviseur qui signe
 *   purpose        - 'supervisor' | 'admin_proxy'
 *
 * Emits:
 *   signed(signatureDataUrl) - signature reçue du mobile
 *   cancel                   - l'utilisateur annule
 */
import { ref, onUnmounted } from 'vue'
import axios from 'axios'
import {
  QrCodeIcon,
  ArrowPathIcon,
  XMarkIcon,
  CheckCircleIcon,
  DevicePhoneMobileIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  reservationId: { type: Number, required: true },
  supervisorId:  { type: Number, required: true },
  purpose:       { type: String, default: 'supervisor' },
})

const emit = defineEmits(['signed', 'cancel'])

const state      = ref('idle')   // idle | loading | waiting | completed | expired | error
const qrSvg      = ref('')
const mobileUrl  = ref('')
const token      = ref('')
const countdown  = ref(15 * 60) // secondes
const errorMsg   = ref('')

let pollInterval   = null
let countdownTimer = null

async function start() {
  state.value = 'loading'
  errorMsg.value = ''
  try {
    const { data } = await axios.post('/sign-sessions', {
      reservation_id: props.reservationId,
      supervisor_id:  props.supervisorId,
      purpose:        props.purpose,
    })
    qrSvg.value     = data.qr_svg
    mobileUrl.value  = data.mobile_url
    token.value      = data.token
    countdown.value  = data.expires_in * 60

    state.value = 'waiting'
    startPolling()
    startCountdown()
  } catch (e) {
    errorMsg.value = 'Erreur lors de la création de la session. Réessayez.'
    state.value = 'error'
  }
}

function startPolling() {
  pollInterval = setInterval(async () => {
    try {
      const { data } = await axios.get(`/sign-sessions/${token.value}/poll`)
      if (data.status === 'completed' && data.signature) {
        stop()
        state.value = 'completed'
        emit('signed', data.signature)
      } else if (data.status === 'expired') {
        stop()
        state.value = 'expired'
      }
    } catch {}
  }, 2000)
}

function startCountdown() {
  countdownTimer = setInterval(() => {
    countdown.value--
    if (countdown.value <= 0) {
      stop()
      state.value = 'expired'
    }
  }, 1000)
}

function stop() {
  clearInterval(pollInterval)
  clearInterval(countdownTimer)
}

function retry() {
  stop()
  state.value  = 'idle'
  qrSvg.value  = ''
  token.value  = ''
  countdown.value = 15 * 60
}

function cancel() {
  stop()
  emit('cancel')
}

function formatCountdown(s) {
  const m = Math.floor(s / 60)
  const ss = String(s % 60).padStart(2, '0')
  return `${m}:${ss}`
}

onUnmounted(() => stop())
</script>

<template>
  <div class="flex flex-col items-center gap-4 py-2">

    <!-- État initial : bouton pour démarrer -->
    <template v-if="state === 'idle'">
      <button @click="start"
              class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm px-5 py-3 rounded-xl transition shadow-sm w-full justify-center">
        <DevicePhoneMobileIcon class="w-5 h-5" />
        Signer depuis un mobile (QR code)
      </button>
    </template>

    <!-- Chargement -->
    <template v-else-if="state === 'loading'">
      <div class="flex items-center gap-2 text-indigo-600 text-sm">
        <ArrowPathIcon class="w-4 h-4 animate-spin" />
        Génération du QR code…
      </div>
    </template>

    <!-- En attente de signature -->
    <template v-else-if="state === 'waiting'">
      <div class="w-full bg-indigo-50 border border-indigo-200 rounded-2xl p-4 flex flex-col items-center gap-4">

        <!-- QR Code -->
        <div class="bg-white rounded-xl p-3 shadow-sm border border-indigo-100">
          <div v-html="qrSvg" class="w-52 h-52" />
        </div>

        <!-- Instructions -->
        <div class="text-center space-y-1">
          <p class="text-sm font-semibold text-indigo-900">
            📱 Scannez avec le smartphone du responsable
          </p>
          <p class="text-xs text-indigo-600">
            Ou partagez ce lien :
            <a :href="mobileUrl" target="_blank"
               class="underline font-mono text-indigo-700 break-all">
              {{ mobileUrl }}
            </a>
          </p>
        </div>

        <!-- Countdown -->
        <div class="flex items-center gap-2 text-xs font-mono"
             :class="countdown < 60 ? 'text-red-600' : 'text-indigo-500'">
          <span class="inline-block w-2 h-2 rounded-full bg-green-400 animate-pulse" />
          En attente… expire dans {{ formatCountdown(countdown) }}
        </div>

        <!-- Annuler -->
        <button @click="cancel"
                class="text-xs text-gray-400 hover:text-gray-600 underline flex items-center gap-1">
          <XMarkIcon class="w-3.5 h-3.5" />
          Annuler et signer manuellement
        </button>
      </div>
    </template>

    <!-- Signature reçue -->
    <template v-else-if="state === 'completed'">
      <div class="w-full bg-green-50 border border-green-300 rounded-xl px-4 py-3 flex items-center gap-3">
        <CheckCircleIcon class="w-5 h-5 text-green-600 flex-shrink-0" />
        <p class="text-sm font-semibold text-green-800">Signature reçue depuis le mobile !</p>
      </div>
    </template>

    <!-- Expirée -->
    <template v-else-if="state === 'expired'">
      <div class="w-full bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 space-y-3">
        <p class="text-sm text-amber-800 font-medium">⏰ Session expirée.</p>
        <div class="flex gap-2">
          <button @click="retry"
                  class="flex-1 flex items-center justify-center gap-1.5 text-xs bg-amber-600 text-white font-semibold px-3 py-2 rounded-lg hover:bg-amber-700 transition">
            <ArrowPathIcon class="w-3.5 h-3.5" />
            Nouveau QR code
          </button>
          <button @click="cancel"
                  class="flex-1 text-xs border border-gray-300 text-gray-600 px-3 py-2 rounded-lg hover:bg-gray-50 transition">
            Signer manuellement
          </button>
        </div>
      </div>
    </template>

    <!-- Erreur -->
    <template v-else-if="state === 'error'">
      <div class="w-full bg-red-50 border border-red-200 rounded-xl px-4 py-3 space-y-2">
        <p class="text-sm text-red-700">{{ errorMsg }}</p>
        <button @click="retry"
                class="text-xs text-red-600 underline">Réessayer</button>
      </div>
    </template>

  </div>
</template>
