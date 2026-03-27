<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, onUnmounted, watch, computed } from 'vue'
import axios from 'axios'
import { router, useForm } from '@inertiajs/vue3'
import { useSound } from '@/composables/useSound'
import {
  ExclamationTriangleIcon, CheckCircleIcon, QrCodeIcon,
  UserIcon, CalendarIcon, BellIcon, ClockIcon, ArchiveBoxIcon,
  WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline'
import { CheckCircleIcon as CheckCircleSolid } from '@heroicons/vue/24/solid'

const { playSound } = useSound()

const props = defineProps({
  overdueReservations:        Array,
  activeReservations:         Array,
  kitsAwaitingRegularization: Array,
  users:                      Array,
})

// ─── Retour manuel avec modal état ────────────────────────────────────────────
const manualReturning      = ref(null)
const showManualReturnModal = ref(false)
const manualReturnTarget    = ref(null)   // { reservationId, assetId, assetName, assetTag }
const manualReturnCondition = ref('good')
const manualReturnNotes     = ref('')

function openManualReturnModal(reservationId, assetId, assetName, assetTag) {
  manualReturnTarget.value    = { reservationId, assetId, assetName, assetTag }
  manualReturnCondition.value = 'good'
  manualReturnNotes.value     = ''
  showManualReturnModal.value = true
}

function submitManualReturn() {
  if (!manualReturnTarget.value) return
  const { reservationId, assetId } = manualReturnTarget.value
  const key = `${reservationId}-${assetId}`
  manualReturning.value      = key
  showManualReturnModal.value = false
  router.post(
    route('reservations.return-asset', [reservationId, assetId]),
    { condition: manualReturnCondition.value, return_notes: manualReturnNotes.value || null },
    { onFinish: () => { manualReturning.value = null } }
  )
}

// ─── Panneau scan USB ─────────────────────────────────────────────────────────
const scanInputEl    = ref(null)
const scanTag        = ref('')
const scanCondition  = ref('good')   // 'good' | 'damaged' | 'lost'
const scanNotes      = ref('')
const scanReturnedBy = ref('')       // ID utilisateur qui rapporte physiquement le matériel
const scanResult     = ref(null)
const scanning       = ref(false)

// Auto-focus + scan USB debounce
onMounted(() => scanInputEl.value?.focus())

let scanDebounceTimer = null
watch(scanTag, (val) => {
  clearTimeout(scanDebounceTimer)
  if (!val.trim() || scanning.value) return
  scanDebounceTimer = setTimeout(() => {
    if (scanTag.value.trim() && !scanning.value) scanReturn()
  }, 200)
})

async function scanReturn() {
  // Garde anti-doublon : empêche un 2e appel simultané (debounce + Enter peuvent se cumuler)
  if (scanning.value) return
  const tag = scanTag.value.trim()
  if (!tag) return

  scanning.value   = true
  scanResult.value = null

  // Vider le champ AVANT la requête pour éviter qu'un 2e scan USB rapide se déclenche
  scanTag.value = ''

  try {
    const { data } = await axios.post(route('returns.scan'), {
      asset_tag:           tag,
      condition:           scanCondition.value,
      return_notes:        scanNotes.value || null,
      returned_by_user_id: scanReturnedBy.value || null,
    })

    playSound('scan_success')
    scanNotes.value     = ''
    scanCondition.value = 'good'

    const conditionEmoji  = { good: '✅', damaged: '⚠️', lost: '❌' }[data.condition ?? 'good'] ?? '✅'
    const reservationInfo = data.reservation
      ? ` — « ${data.reservation.title} »${data.reservation.all_returned ? ' ✅ Complet !' : ''}${data.reservation.is_kit ? ' 📦 KIT' : ''}`
      : ''
    scanResult.value = {
      type:    'success',
      message: `${conditionEmoji} ${data.asset.name} (${data.asset.asset_tag}) retourné${reservationInfo}`,
    }

    // Rechargement partiel Inertia pour mettre à jour les listes
    router.reload({
      only:      ['overdueReservations', 'activeReservations', 'kitsAwaitingRegularization'],
      onSuccess: () => { scanInputEl.value?.focus() },
    })

  } catch (err) {
    playSound('scan_error')
    // En cas d'erreur, remettre le tag pour que l'opérateur puisse réessayer
    scanTag.value = tag
    scanResult.value = {
      type:    'error',
      message: err.response?.data?.error ?? 'Erreur lors du scan',
    }
  } finally {
    scanning.value = false
    setTimeout(() => {
      scanInputEl.value?.focus()
      setTimeout(() => { scanResult.value = null }, 6000)
    }, 100)
  }
}

// ─── Signaler un équipement manquant (kit) ────────────────────────────────────
const showMissingModal   = ref(false)
const missingTargetAsset = ref(null)
const missingTargetRes   = ref(null)
const missingForm = ref({
  notes:               '',
  responsible_user_id: '',
})
const missingLoading = ref(false)
const missingError   = ref('')

function openMissingModal(reservation, asset) {
  missingTargetRes.value   = reservation
  missingTargetAsset.value = asset
  missingForm.value = { notes: '', responsible_user_id: reservation.user.id }
  missingError.value = ''
  showMissingModal.value = true
}

async function submitMissing() {
  missingLoading.value = true
  missingError.value   = ''
  try {
    await axios.post(route('kits.missing-item'), {
      kit_id:               missingTargetRes.value.kit?.id,
      asset_id:             missingTargetAsset.value.id,
      reservation_id:       missingTargetRes.value.id,
      responsible_user_id:  missingForm.value.responsible_user_id || null,
      notes:                missingForm.value.notes || null,
    })
    showMissingModal.value = false
    router.reload({ only: ['overdueReservations', 'activeReservations', 'kitsAwaitingRegularization'] })
  } catch (err) {
    missingError.value = err.response?.data?.error ?? 'Erreur lors du signalement.'
  } finally {
    missingLoading.value = false
  }
}

// ─── Rappels email ────────────────────────────────────────────────────────────
const reminderForms = {}
function getReminderForm(id) {
  if (!reminderForms[id]) reminderForms[id] = useForm({})
  return reminderForms[id]
}
function sendReminder(reservation) {
  getReminderForm(reservation.id).post(route('returns.reminder', reservation.id))
}

// ─── Session apairée smartphone ───────────────────────────────────────────────
const pairingOpen    = ref(false)
const pairSession    = ref(null)
const pairLoading    = ref(false)
const pairItems      = ref([])
const pairStatus     = ref(null)
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
  } catch {
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
    await axios.post(route('returns.scan'), {
      asset_tag:   item.asset_tag,
      condition:   scanCondition.value,
      return_notes: scanNotes.value || null,
      returned_by_user_id: scanReturnedBy.value || null,
    })
    playSound('scan_success')
    pairReturnDone.value = new Set([...pairReturnDone.value, item.id])
    router.reload({ only: ['overdueReservations', 'activeReservations', 'kitsAwaitingRegularization'] })
  } catch {
    playSound('scan_error')
  }
  pairReturning.value = new Set([...pairReturning.value].filter(id => id !== item.id))
}

onUnmounted(() => stopPolling())

// ─── Helpers ─────────────────────────────────────────────────────────────────
function formatDate(date) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
function pendingCount(reservation) {
  return reservation.assets.filter(a => !a.returned_at && !a.is_missing).length
}
</script>

<template>
  <AppLayout>
    <div class="space-y-6">

      <!-- ══ Header ══ -->
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Gestion des retours</h1>
        <p class="text-gray-500 text-sm mt-1 flex flex-wrap gap-x-4">
          <span v-if="overdueReservations.length" class="text-red-600 font-semibold">
            ⚠️ {{ overdueReservations.length }} réservation(s) en retard
          </span>
          <span v-if="activeReservations.length" class="text-blue-600 font-semibold">
            📋 {{ activeReservations.length }} réservation(s) active(s)
          </span>
          <span v-if="kitsAwaitingRegularization.length" class="text-orange-600 font-semibold">
            📦 {{ kitsAwaitingRegularization.length }} kit(s) à régulariser
          </span>
          <span v-if="!overdueReservations.length && !activeReservations.length" class="text-green-600 font-semibold">
            ✅ Tout le matériel est rentré
          </span>
        </p>
      </div>

      <!-- ══════════════════════════════════════════
           Panneau de scan (scanner USB ou clavier)
      ══════════════════════════════════════════ -->
      <div class="bg-white rounded-xl shadow-sm border-2 border-blue-300 p-5">
        <h2 class="text-sm font-semibold text-blue-800 mb-3 flex items-center gap-2">
          <QrCodeIcon class="w-4 h-4" />
          Scanner un retour
          <span class="ml-auto text-xs font-normal text-gray-400">
            🔌 Scanner USB → pointe ici → lit le code → Entrée automatique
          </span>
        </h2>

        <div class="flex flex-wrap gap-3 items-end">
          <!-- Champ scan principal -->
          <div class="flex-1 min-w-52">
            <label class="block text-xs font-medium text-gray-600 mb-1">Code barre / Tag équipement</label>
            <input
              ref="scanInputEl"
              v-model="scanTag"
              type="text"
              placeholder="Scannez ou tapez le tag (ex: EQ-A1B2C)…"
              :disabled="scanning"
              @keydown.enter="scanReturn"
              class="w-full border-2 border-blue-200 bg-blue-50 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-400 outline-none disabled:opacity-60 placeholder-gray-400"
            />
          </div>

          <!-- État de retour (3 états) -->
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">État à la réception</label>
            <select v-model="scanCondition"
                    :class="['border rounded-lg px-3 py-2 text-sm focus:ring-2 outline-none font-medium',
                      scanCondition === 'good'    ? 'border-green-400 bg-green-50 text-green-800 focus:ring-green-400'  :
                      scanCondition === 'damaged' ? 'border-orange-400 bg-orange-50 text-orange-800 focus:ring-orange-400' :
                                                    'border-red-400 bg-red-50 text-red-800 focus:ring-red-400']">
              <option value="good">✅ Bon état</option>
              <option value="damaged">⚠️ Endommagé</option>
              <option value="lost">❌ Perdu / Manquant</option>
            </select>
          </div>

          <!-- Sélecteur "Retourné par" (si différent du réservant) -->
          <div class="flex-1 min-w-48">
            <label class="block text-xs font-medium text-gray-600 mb-1">
              Retourné par
              <span class="text-gray-400 font-normal">(si différent du réservant)</span>
            </label>
            <select v-model="scanReturnedBy"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
              <option value="">— Même personne que le réservant —</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>

          <!-- Observations -->
          <div class="flex-1 min-w-44">
            <label class="block text-xs font-medium text-gray-600 mb-1">Observations (optionnel)</label>
            <input v-model="scanNotes" type="text" placeholder="Rayures, câble manquant…"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>

          <button
            @click="scanReturn"
            :disabled="scanning || !scanTag.trim()"
            class="flex items-center gap-1.5 px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition disabled:opacity-50 whitespace-nowrap shadow-sm">
            <QrCodeIcon class="w-4 h-4" />
            {{ scanning ? 'Traitement…' : 'Valider retour' }}
          </button>
        </div>

        <!-- Résultat du scan -->
        <Transition name="fade">
          <div v-if="scanResult"
               class="mt-3 flex items-start gap-2 px-3 py-2.5 rounded-lg text-sm font-medium"
               :class="scanResult.type === 'success'
                 ? 'bg-green-100 text-green-800 border border-green-300'
                 : 'bg-red-100 text-red-800 border border-red-300'">
            <CheckCircleSolid v-if="scanResult.type === 'success'" class="w-4 h-4 text-green-600 flex-shrink-0 mt-0.5" />
            <ExclamationTriangleIcon v-else class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
            {{ scanResult.message }}
          </div>
        </Transition>

        <!-- Bouton apairage smartphone -->
        <div class="mt-4 flex items-center justify-between border-t pt-3">
          <span class="text-xs text-gray-400">Utiliser la caméra d'un smartphone</span>
          <div class="flex items-center gap-2">
            <p v-if="pairError" class="text-xs text-red-600">{{ pairError }}</p>
            <button v-if="!pairingOpen"
                    @click="openPairing"
                    :disabled="pairLoading"
                    class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition disabled:opacity-50">
              📱 {{ pairLoading ? 'Connexion…' : 'Appairer un smartphone' }}
            </button>
            <button v-else @click="closePairing"
                    class="flex items-center gap-1.5 bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition">
              ✕ Fermer la session
            </button>
          </div>
        </div>
      </div>

      <!-- ══ Panneau session apairée smartphone ══ -->
      <div v-if="pairingOpen && pairSession" class="bg-white rounded-xl shadow-sm border-2 border-indigo-300 p-5">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-indigo-800 flex items-center gap-2">
            📱 Session smartphone active
            <span v-if="pairTimeLeft" class="text-xs font-normal text-gray-500">— expire dans {{ pairTimeLeft }}</span>
          </h2>
          <span v-if="pairStatus === 'expired'" class="text-xs text-red-600 font-semibold">Session expirée</span>
        </div>

        <div class="flex gap-6 items-start">
          <!-- QR code -->
          <div class="flex-shrink-0 text-center">
            <div v-html="pairSession.qr_svg" class="w-32 h-32" />
            <p class="text-xs text-gray-500 mt-1">Scanner avec le smartphone</p>
          </div>

          <!-- Liste des scans reçus -->
          <div class="flex-1">
            <p v-if="pairItems.length === 0" class="text-sm text-gray-400 italic">
              En attente de scans depuis le smartphone…
            </p>
            <div v-else class="space-y-2">
              <div v-for="item in pairItems" :key="item.id"
                   class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2 text-sm border">
                <div>
                  <span class="font-mono font-medium text-gray-800">{{ item.asset_tag }}</span>
                  <span v-if="item.asset_name" class="ml-2 text-gray-500">{{ item.asset_name }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <span v-if="pairReturnDone.has(item.id)"
                        class="text-xs text-green-700 font-semibold bg-green-100 px-2 py-0.5 rounded-full">
                    ✓ Retourné
                  </span>
                  <button v-else
                          @click="returnPairedItem(item)"
                          :disabled="pairReturning.has(item.id)"
                          class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg font-medium transition disabled:opacity-50">
                    {{ pairReturning.has(item.id) ? '…' : 'Valider retour' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ Vide ══ -->
      <div v-if="overdueReservations.length === 0 && activeReservations.length === 0 && kitsAwaitingRegularization.length === 0"
           class="bg-white rounded-xl shadow-sm border px-8 py-16 text-center text-gray-400">
        <CheckCircleIcon class="w-14 h-14 mx-auto mb-3 opacity-30 text-green-500" />
        <p class="text-lg font-medium text-gray-500">Tout le matériel est rentré !</p>
        <p class="text-sm mt-1">Aucune réservation active en cours.</p>
      </div>

      <!-- ══════════════════════════════════════════
           SECTION KITS EN ATTENTE DE RÉGULARISATION
      ══════════════════════════════════════════ -->
      <section v-if="kitsAwaitingRegularization.length > 0">
        <h2 class="flex items-center gap-2 text-base font-semibold text-orange-700 mb-3">
          <WrenchScrewdriverIcon class="w-5 h-5" />
          Kits en attente de régularisation — {{ kitsAwaitingRegularization.length }}
        </h2>
        <div class="space-y-3">
          <div v-for="res in kitsAwaitingRegularization" :key="'kreg-' + res.id"
               class="bg-white rounded-xl shadow-sm border border-orange-200 overflow-hidden">
            <div class="px-5 py-4 bg-orange-50 border-b border-orange-100 flex items-start justify-between gap-4">
              <div>
                <h3 class="font-semibold text-gray-900">{{ res.title }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                  📦 Kit : {{ res.kit?.name }} · 👤 {{ res.user.name }}
                  <span v-if="res.return_at"> · Retourné le {{ res.return_at }}</span>
                </p>
              </div>
              <a :href="route('kits.index')" class="text-xs bg-orange-500 text-white px-3 py-1.5 rounded-lg font-medium hover:bg-orange-600 transition flex-shrink-0">
                Régulariser →
              </a>
            </div>
            <div class="px-5 py-3">
              <p class="text-xs font-semibold text-gray-400 uppercase mb-2">Éléments manquants :</p>
              <div class="flex flex-wrap gap-2">
                <span v-for="a in res.missing" :key="a.id"
                  class="text-xs bg-red-100 text-red-700 border border-red-200 px-2 py-1 rounded-lg font-mono">
                  ⚠️ {{ a.name }} ({{ a.asset_tag }})
                </span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ══════════════════════════════════════════
           SECTION 1 — RÉSERVATIONS EN RETARD
      ══════════════════════════════════════════ -->
      <section v-if="overdueReservations.length > 0">
        <h2 class="flex items-center gap-2 text-base font-semibold text-red-700 mb-3">
          <ExclamationTriangleIcon class="w-5 h-5" />
          En retard — {{ overdueReservations.length }} réservation(s)
        </h2>

        <div class="space-y-4">
          <div v-for="res in overdueReservations" :key="'late-' + res.id"
               class="bg-white rounded-xl shadow-sm border border-red-200 overflow-hidden">

            <div class="px-5 py-4 bg-red-50 border-b border-red-100 flex items-start justify-between gap-4 flex-wrap">
              <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0 text-sm font-bold text-red-700">
                  {{ res.user.name.charAt(0).toUpperCase() }}
                </div>
                <div class="min-w-0">
                  <h3 class="font-semibold text-gray-900 truncate flex items-center gap-2">
                    {{ res.title }}
                    <span v-if="res.kit" class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">
                      📦 Kit
                    </span>
                  </h3>
                  <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-gray-500 mt-0.5">
                    <span class="flex items-center gap-1">
                      <UserIcon class="w-3 h-3" />
                      {{ res.user.name }}
                      <a :href="`mailto:${res.user.email}`" class="text-blue-500 hover:underline ml-0.5">({{ res.user.email }})</a>
                    </span>
                    <span class="flex items-center gap-1">
                      <CalendarIcon class="w-3 h-3" />
                      {{ formatDate(res.start_date) }} → {{ formatDate(res.end_date) }}
                    </span>
                    <span class="text-orange-600 font-medium">
                      {{ pendingCount(res) }} équipement(s) à rendre
                    </span>
                  </div>
                </div>
              </div>
              <div class="flex items-center gap-2 flex-shrink-0 flex-wrap">
                <span class="bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full">
                  +{{ res.days_overdue }} jour(s) de retard
                </span>
                <button
                  @click="sendReminder(res)"
                  :disabled="getReminderForm(res.id).processing"
                  class="flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-xs font-medium rounded-lg transition disabled:opacity-60">
                  <BellIcon class="w-3.5 h-3.5" />
                  {{ getReminderForm(res.id).processing ? 'Envoi…' : 'Rappel email' }}
                </button>
              </div>
            </div>

            <!-- Equipements -->
            <div class="px-5 py-4">
              <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">
                Équipements — scannez ou validez manuellement :
              </p>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                <div v-for="asset in res.assets" :key="asset.id"
                     class="flex items-start gap-2 px-3 py-2.5 rounded-lg border text-sm transition"
                     :class="asset.returned_at
                       ? 'bg-green-50 border-green-200 text-green-800'
                       : asset.is_missing
                         ? 'bg-gray-100 border-gray-200 text-gray-500 opacity-70'
                         : 'bg-red-50 border-red-200 text-red-800'">
                  <CheckCircleSolid v-if="asset.returned_at" class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                  <ExclamationTriangleIcon v-else-if="!asset.is_missing" class="w-4 h-4 text-red-400 flex-shrink-0 mt-0.5" />
                  <span v-else class="w-4 h-4 flex-shrink-0 mt-0.5 text-center text-xs">🚫</span>
                  <div class="min-w-0 flex-1">
                    <p class="font-semibold truncate">{{ asset.name }}</p>
                    <p class="text-xs font-mono opacity-60">{{ asset.asset_tag }}</p>
                    <p v-if="asset.returned_at" class="text-xs text-green-600 mt-0.5">✓ Retourné</p>
                    <p v-else-if="asset.is_missing" class="text-xs text-gray-400 mt-0.5">🚫 Signalé manquant</p>
                    <p v-else-if="asset.qr_code" class="text-xs text-blue-700 font-medium mt-0.5">
                      🔍 Scannez le code barre physique
                    </p>
                    <template v-else>
                      <p class="text-xs text-gray-500 mt-0.5 italic">Pas d'étiquette physique</p>
                      <div class="flex gap-1 mt-1.5">
                        <button
                          @click="openManualReturnModal(res.id, asset.id, asset.name, asset.asset_tag)"
                          :disabled="manualReturning === `${res.id}-${asset.id}`"
                          class="flex-1 text-xs bg-white text-red-700 hover:bg-red-100 border border-red-300 px-2 py-1 rounded-md font-semibold transition disabled:opacity-50 text-center">
                          {{ manualReturning === `${res.id}-${asset.id}` ? '…' : '↩ Retour manuel' }}
                        </button>
                        <!-- Signaler manquant (kit seulement) -->
                        <button v-if="res.kit"
                          @click="openMissingModal(res, asset)"
                          class="text-xs bg-orange-50 text-orange-700 hover:bg-orange-100 border border-orange-200 px-2 py-1 rounded-md font-semibold transition">
                          ⚠️
                        </button>
                      </div>
                    </template>
                    <!-- Bouton signaler manquant pour les assets avec QR (kit) -->
                    <button v-if="res.kit && asset.qr_code && !asset.returned_at && !asset.is_missing"
                      @click="openMissingModal(res, asset)"
                      class="mt-1.5 w-full text-xs bg-orange-50 text-orange-700 hover:bg-orange-100 border border-orange-200 px-2 py-1 rounded-md font-semibold transition text-center">
                      ⚠️ Signaler manquant
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ══════════════════════════════════════════
           SECTION 2 — RÉSERVATIONS ACTIVES
      ══════════════════════════════════════════ -->
      <section>
        <h2 class="flex items-center gap-2 text-base font-semibold text-blue-700 mb-3">
          <ArchiveBoxIcon class="w-5 h-5" />
          Matériel sorti — dans les délais ({{ activeReservations.length }})
        </h2>

        <div v-if="activeReservations.length === 0"
             class="bg-white rounded-xl shadow-sm border px-8 py-10 text-center text-gray-400">
          <ClockIcon class="w-10 h-10 mx-auto mb-2 opacity-30" />
          <p class="font-medium text-gray-500">Aucun matériel sorti dans les délais en ce moment</p>
        </div>

        <div v-else class="space-y-4">
          <div v-for="res in activeReservations" :key="'active-' + res.id"
               class="bg-white rounded-xl shadow-sm border border-blue-200 overflow-hidden">

            <div class="px-5 py-4 bg-blue-50 border-b border-blue-100 flex items-start justify-between gap-4 flex-wrap">
              <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0 text-sm font-bold text-blue-700">
                  {{ res.user.name.charAt(0).toUpperCase() }}
                </div>
                <div class="min-w-0">
                  <h3 class="font-semibold text-gray-900 truncate flex items-center gap-2">
                    {{ res.title }}
                    <span v-if="res.kit" class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">
                      📦 Kit
                    </span>
                  </h3>
                  <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-gray-500 mt-0.5">
                    <span class="flex items-center gap-1">
                      <UserIcon class="w-3 h-3" />
                      {{ res.user.name }}
                      <a :href="`mailto:${res.user.email}`" class="text-blue-500 hover:underline ml-0.5">({{ res.user.email }})</a>
                    </span>
                    <span class="flex items-center gap-1">
                      <CalendarIcon class="w-3 h-3" />
                      {{ formatDate(res.start_date) }} → {{ formatDate(res.end_date) }}
                    </span>
                    <span class="text-blue-600 font-medium">
                      {{ pendingCount(res) }} / {{ res.assets.length }} équipement(s) à rendre
                    </span>
                  </div>
                </div>
              </div>
              <div class="flex-shrink-0">
                <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 border border-blue-300 text-xs font-semibold px-3 py-1 rounded-full">
                  <ClockIcon class="w-3 h-3" />
                  {{ res.days_remaining === 0 ? "À rendre aujourd'hui" : `J-${res.days_remaining}` }}
                </span>
              </div>
            </div>

            <!-- Équipements -->
            <div class="px-5 py-4">
              <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">
                Équipements — scannez le code barre physique pour valider chaque retour :
              </p>
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                <div v-for="asset in res.assets" :key="asset.id"
                     class="flex items-start gap-2 px-3 py-2.5 rounded-lg border text-sm transition"
                     :class="asset.returned_at
                       ? 'bg-green-50 border-green-200 text-green-800'
                       : asset.is_missing
                         ? 'bg-gray-100 border-gray-200 text-gray-500 opacity-70'
                         : 'bg-gray-50 border-gray-200 text-gray-700'">
                  <CheckCircleSolid v-if="asset.returned_at" class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                  <ArchiveBoxIcon v-else-if="!asset.is_missing" class="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" />
                  <span v-else class="w-4 h-4 flex-shrink-0 mt-0.5 text-center text-xs">🚫</span>
                  <div class="min-w-0 flex-1">
                    <p class="font-semibold truncate">{{ asset.name }}</p>
                    <p class="text-xs font-mono opacity-60">{{ asset.asset_tag }}</p>
                    <p v-if="asset.returned_at" class="text-xs text-green-600 mt-0.5">✓ Retourné</p>
                    <p v-else-if="asset.is_missing" class="text-xs text-gray-400 mt-0.5">🚫 Signalé manquant</p>
                    <p v-else-if="asset.qr_code" class="text-xs text-blue-600 font-medium mt-0.5">
                      🔍 Scannez le code barre physique
                    </p>
                    <template v-else>
                      <p class="text-xs text-gray-400 mt-0.5 italic">Pas d'étiquette physique</p>
                      <div class="flex gap-1 mt-1.5">
                        <button
                          @click="openManualReturnModal(res.id, asset.id, asset.name, asset.asset_tag)"
                          :disabled="manualReturning === `${res.id}-${asset.id}`"
                          class="flex-1 text-xs bg-white text-green-700 hover:bg-green-50 border border-green-300 px-2 py-1 rounded-md font-semibold transition disabled:opacity-50 text-center">
                          {{ manualReturning === `${res.id}-${asset.id}` ? '…' : '↩ Retour manuel' }}
                        </button>
                        <button v-if="res.kit"
                          @click="openMissingModal(res, asset)"
                          class="text-xs bg-orange-50 text-orange-700 hover:bg-orange-100 border border-orange-200 px-2 py-1 rounded-md font-semibold transition">
                          ⚠️
                        </button>
                      </div>
                    </template>
                    <button v-if="res.kit && asset.qr_code && !asset.returned_at && !asset.is_missing"
                      @click="openMissingModal(res, asset)"
                      class="mt-1.5 w-full text-xs bg-orange-50 text-orange-700 hover:bg-orange-100 border border-orange-200 px-2 py-1 rounded-md font-semibold transition text-center">
                      ⚠️ Signaler manquant
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

    </div>

    <!-- ═══ Modal : Signaler un élément manquant ═══ -->
    <div v-if="showMissingModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg text-orange-700">⚠️ Signaler un élément manquant</h3>
          <button @click="showMissingModal=false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>

        <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 mb-4">
          <p class="text-sm font-semibold text-gray-900">{{ missingTargetAsset?.name }}</p>
          <p class="text-xs font-mono text-gray-500">{{ missingTargetAsset?.asset_tag }}</p>
          <p class="text-xs text-gray-500 mt-1">
            Kit : <span class="font-medium">{{ missingTargetRes?.kit?.name }}</span> ·
            Réservé par : <span class="font-medium">{{ missingTargetRes?.user?.name }}</span>
          </p>
        </div>

        <div class="space-y-4">
          <!-- Utilisateur responsable (peut être différent du réservant) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Utilisateur responsable de la perte
              <span class="text-gray-400 font-normal">(si différent du réservant)</span>
            </label>
            <select v-model="missingForm.responsible_user_id"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 outline-none">
              <option value="">— Non attribué —</option>
              <option v-for="u in users" :key="u.id" :value="u.id"
                :selected="u.id === missingTargetRes?.user?.id">
                {{ u.name }}{{ u.id === missingTargetRes?.user?.id ? ' (réservant)' : '' }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Observations</label>
            <textarea v-model="missingForm.notes" rows="3"
              placeholder="Équipement introuvable lors du retour, câble manquant…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 outline-none resize-none" />
          </div>

          <p v-if="missingError" class="text-red-500 text-sm bg-red-50 border border-red-200 rounded px-3 py-2">
            {{ missingError }}
          </p>
        </div>

        <div class="flex gap-3 mt-5">
          <button @click="showMissingModal=false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitMissing" :disabled="missingLoading"
            class="flex-1 bg-orange-500 text-white py-2 rounded-lg text-sm font-medium hover:bg-orange-600 disabled:opacity-50">
            {{ missingLoading ? 'Signalement…' : '⚠️ Signaler manquant' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : retour manuel avec état ═══ -->
    <div v-if="showManualReturnModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">↩ Retour de matériel</h3>
          <button @click="showManualReturnModal=false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <!-- Résumé équipement -->
        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border mb-4">
          <div class="w-10 h-10 rounded-lg bg-gray-200 flex items-center justify-center text-gray-500 text-lg flex-shrink-0">📦</div>
          <div>
            <p class="font-medium text-sm">{{ manualReturnTarget?.assetName }}</p>
            <p class="text-xs text-gray-500 font-mono">{{ manualReturnTarget?.assetTag }}</p>
          </div>
        </div>

        <!-- État constaté -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">État constaté <span class="text-red-500">*</span></label>
          <div class="space-y-2">
            <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition"
                   :class="manualReturnCondition === 'good' ? 'border-green-400 bg-green-50' : 'border-gray-200 hover:border-gray-300'">
              <input type="radio" v-model="manualReturnCondition" value="good" class="text-green-600" />
              <span class="text-sm font-medium text-green-800">✅ Bon état</span>
            </label>
            <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition"
                   :class="manualReturnCondition === 'damaged' ? 'border-orange-400 bg-orange-50' : 'border-gray-200 hover:border-gray-300'">
              <input type="radio" v-model="manualReturnCondition" value="damaged" class="text-orange-600" />
              <span class="text-sm font-medium text-orange-800">⚠️ Endommagé — Une fiche maintenance sera créée</span>
            </label>
            <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition"
                   :class="manualReturnCondition === 'lost' ? 'border-red-400 bg-red-50' : 'border-gray-200 hover:border-gray-300'">
              <input type="radio" v-model="manualReturnCondition" value="lost" class="text-red-600" />
              <span class="text-sm font-medium text-red-800">❌ Perdu / Manquant — L'équipement sera mis en maintenance</span>
            </label>
          </div>
        </div>

        <!-- Observations -->
        <div class="mb-5">
          <label class="block text-sm font-medium text-gray-700 mb-1">Observations <span class="text-gray-400 font-normal">(optionnel)</span></label>
          <textarea v-model="manualReturnNotes" rows="2" placeholder="Rayures, panne, détails de la perte…"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500 outline-none resize-none" />
        </div>

        <div class="flex gap-3">
          <button @click="showManualReturnModal=false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitManualReturn"
            class="flex-1 py-2 rounded-lg text-sm font-medium transition"
            :class="manualReturnCondition === 'good'    ? 'bg-green-600 text-white hover:bg-green-700'
                  : manualReturnCondition === 'damaged'  ? 'bg-orange-500 text-white hover:bg-orange-600'
                  : 'bg-red-600 text-white hover:bg-red-700'">
            Confirmer le retour
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
