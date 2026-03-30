<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, nextTick } from 'vue'
import axios from 'axios'
import { useSound } from '@/composables/useSound'
import { CheckCircleIcon, ClockIcon, QrCodeIcon, UserIcon, CalendarIcon, ListBulletIcon, BoltIcon } from '@heroicons/vue/24/outline'
import { CheckCircleIcon as CheckCircleSolid } from '@heroicons/vue/24/solid'

const { playSound } = useSound()

const props = defineProps({
  reservations: Array,
  users:        Array,
})

// Local state for scanned assets (updated after API calls)
const localReservations = ref(props.reservations.map(r => ({
  ...r,
  assets: r.assets.map(a => ({ ...a })),
  scanInput: '',
  scanning: false,
  scanResult: null, // { type: 'success'|'error', message }
  showMultiScan: false,
  multiScanInput: '',
  multiScanResults: [],
  multiScanning: false,
  manualCheckingAssetId: null,  // id de l'asset en cours d'attribution manuelle
  sendCheckoutEmail: true,       // option : envoyer récap email à la récupération
  // ── Gestion équipements ──
  manageOpen: false,
  manageSearch: '',
  manageAllAvailable: [],
  manageLoading: false,
  managingAddId: null,
  managingRemoveId: null,
  manageError: null,
})))

// ── Refs des inputs de scan (pour refocus automatique) ─────────────────────
const scanInputRefs = ref({})
function setScanInputRef(el, resId) {
  if (el) scanInputRefs.value[resId] = el
}
function focusScanInput(resId) {
  nextTick(() => {
    scanInputRefs.value[resId]?.focus()
  })
}

// ── Modal substitution équivalent ──────────────────────────────────────────
const substituteModal = ref(null)
// { reservation, scanned_asset, original_asset, model_name, confirming }

// ── Modal ajout hors-reservation ────────────────────────────────────────────
const addToResvModal = ref(null)
// { reservation, scanned_asset, confirming }

async function confirmAddToReservation() {
  if (!addToResvModal.value || addToResvModal.value.confirming) return
  addToResvModal.value.confirming = true
  const { reservation, scanned_asset } = addToResvModal.value
  try {
    const { data } = await axios.post(route('attribution.add-to-reservation'), {
      reservation_id:      reservation.id,
      asset_id:            scanned_asset.id,
      send_checkout_email: reservation.sendCheckoutEmail,
    })
    playSound('scan_success')
    reservation.assets.push({
      id:          scanned_asset.id,
      name:        scanned_asset.name,
      asset_tag:   scanned_asset.asset_tag,
      checkout_at: new Date().toISOString(),
      qr_code:     true,
    })
    const emailMsg = data.checkout_email_sent ? ' — 📧 Email envoyé' : ''
    reservation.scanResult = {
      type: 'success',
      message: `✅ ${scanned_asset.name} (${scanned_asset.asset_tag}) ajouté et attribué${emailMsg}`,
    }
    if (reservation.assets.every(a => a.checkout_at)) reservation.all_checked_out = true
    addToResvModal.value = null
    setTimeout(() => { reservation.scanResult = null }, 5000)
  } catch (err) {
    playSound('scan_error')
    const resv = addToResvModal.value?.reservation
    addToResvModal.value = null
    if (resv) {
      resv.scanResult = { type: 'error', message: err.response?.data?.error ?? 'Erreur' }
    }
  }
}

// ── Mode libre (attribution sans réservation, multi-scan) ───────────────────
const freeMode          = ref(false)
const freeScanInput     = ref('')
const freeUserId        = ref('')
const freeEndDate       = ref('')
const freeTitle         = ref('')
const freeScanError     = ref(null)       // erreur du scan en cours
const freeScanning      = ref(false)      // chargement validation tag
const freePendingAssets = ref([])         // [{ asset_tag, name, id }]
const freeSubmitting    = ref(false)      // chargement lors de la validation finale
const freeSubmitResult  = ref(null)       // { type, lines[] }
const freeSendEmail     = ref(true)

async function freeAddAsset() {
  const tag = freeScanInput.value.trim()
  if (!tag) return
  if (freePendingAssets.value.some(a => a.asset_tag === tag)) {
    freeScanError.value = `${tag} est déjà dans la liste.`
    freeScanInput.value = ''
    return
  }
  freeScanning.value = true
  freeScanError.value = null
  try {
    const { data } = await axios.post(route('attribution.validate-tag'), { asset_tag: tag })
    freePendingAssets.value.push({ asset_tag: data.asset.asset_tag, name: data.asset.name, id: data.asset.id })
    freeScanInput.value = ''
    playSound('scan_success')
  } catch (err) {
    freeScanError.value = err.response?.data?.error ?? `Équipement "${tag}" introuvable ou non disponible.`
    playSound('scan_error')
  } finally {
    freeScanning.value = false
  }
}

function freeRemoveAsset(tag) {
  freePendingAssets.value = freePendingAssets.value.filter(a => a.asset_tag !== tag)
}

function freeReset() {
  freePendingAssets.value = []
  freeScanInput.value     = ''
  freeScanError.value     = null
  freeSubmitResult.value  = null
  freeTitle.value         = ''
}

async function freeSubmit() {
  if (!freePendingAssets.value.length || !freeUserId.value || !freeEndDate.value) return
  freeSubmitting.value   = true
  freeSubmitResult.value = null
  try {
    const { data } = await axios.post(route('attribution.free-checkout-bulk'), {
      asset_tags:           freePendingAssets.value.map(a => a.asset_tag),
      user_id:              freeUserId.value,
      end_date:             freeEndDate.value,
      title:                freeTitle.value || null,
      send_checkout_email:  freeSendEmail.value,
    })
    playSound('scan_success')
    const lines = [
      `Réservation #${data.reservation_id} créée pour ${data.user}`,
      `Retour prévu : ${data.end_date}`,
      `${data.assets.length} équipement(s) : ${data.assets.map(a => a.asset_tag).join(', ')}`,
    ]
    if (data.errors?.length) lines.push(`Avertissements : ${data.errors.join(', ')}`)
    if (data.checkout_email_sent) lines.push('Email récapitulatif envoyé')
    freeSubmitResult.value = {
      type: 'success',
      lines,
      reservationId: data.reservation_id,
      pendingForms:  data.pending_forms ?? [],
    }
    freePendingAssets.value = []
    freeTitle.value         = ''
  } catch (err) {
    playSound('scan_error')
    freeSubmitResult.value = {
      type:  'error',
      lines: [err.response?.data?.error ?? 'Erreur lors de la validation'],
    }
  } finally {
    freeSubmitting.value = false
  }
}

// ── Gestion équipements par carte (ajouter / retirer) ────────────────────────
async function fetchManageAssets(res) {
  if (res.manageAllAvailable.length > 0 || res.manageLoading) return
  res.manageLoading = true
  try {
    const { data } = await axios.get(route('assets.available'), {
      params: { start: res.start_date, end: res.end_date }
    })
    const existingIds = new Set(res.assets.map(a => a.id))
    res.manageAllAvailable = data.filter(a => !existingIds.has(a.id))
  } catch {}
  res.manageLoading = false
}

function manageFilteredAssets(res) {
  const q = (res.manageSearch ?? '').trim().toLowerCase()
  if (!q) return res.manageAllAvailable.slice(0, 10)
  return res.manageAllAvailable
    .filter(a => a.name.toLowerCase().includes(q) || (a.asset_tag ?? '').toLowerCase().includes(q))
    .slice(0, 10)
}

async function manageAddAsset(res, asset) {
  res.managingAddId  = asset.id
  res.manageError    = null
  try {
    const { data } = await axios.post(route('reservations.add-asset', res.id), { asset_id: asset.id })
    // Mise à jour locale
    res.assets.push({
      id:          data.asset.id,
      name:        data.asset.name,
      asset_tag:   data.asset.asset_tag,
      checkout_at: null,
      qr_code:     data.asset.qr_code,
    })
    res.manageAllAvailable = res.manageAllAvailable.filter(a => a.id !== asset.id)
    res.manageSearch = ''
  } catch (err) {
    res.manageError = err.response?.data?.errors?.asset_id?.[0]
      ?? err.response?.data?.message
      ?? 'Erreur lors de l\'ajout'
  } finally {
    res.managingAddId = null
  }
}

async function manageRemoveAsset(res, asset) {
  if (!confirm(`Retirer « ${asset.name} » de la réservation ?`)) return
  res.managingRemoveId = asset.id
  res.manageError      = null
  try {
    await axios.delete(route('reservations.remove-asset', { reservation: res.id, asset: asset.id }))
    res.assets = res.assets.filter(a => a.id !== asset.id)
    // Invalide le cache dispo pour que le prochain fetchManageAssets récupère l'asset retiré
    res.manageAllAvailable = []
  } catch (err) {
    res.manageError = err.response?.data?.errors?.asset_id?.[0]
      ?? err.response?.data?.message
      ?? 'Erreur lors du retrait'
  } finally {
    res.managingRemoveId = null
  }
}

async function confirmSubstitute() {
  if (!substituteModal.value || substituteModal.value.confirming) return
  substituteModal.value.confirming = true

  const { reservation, scanned_asset, original_asset } = substituteModal.value

  try {
    const subData = await axios.post(route('attribution.substitute'), {
      reservation_id:       reservation.id,
      original_asset_id:    original_asset.id,
      scanned_asset_id:     scanned_asset.id,
      send_checkout_email:  reservation.sendCheckoutEmail,
    })
    const data = subData.data

    playSound('scan_success')

    // Replace original by scanned in local reservation assets
    const idx = reservation.assets.findIndex(a => a.id === original_asset.id)
    if (idx !== -1) {
      reservation.assets[idx] = {
        ...reservation.assets[idx],
        id:          scanned_asset.id,
        name:        scanned_asset.name,
        asset_tag:   scanned_asset.asset_tag,
        checkout_at: new Date().toISOString(),
      }
    }

    const emailMsg = data.checkout_email_sent ? ' — 📧 Email récapitulatif envoyé' : ''
    reservation.scanResult = {
      type: 'success',
      message: `✅ Substitution confirmée : ${scanned_asset.name} (${scanned_asset.asset_tag}) attribué à la place de ${original_asset.asset_tag}${emailMsg}`,
    }

    if (reservation.assets.every(a => a.checkout_at)) reservation.all_checked_out = true

    substituteModal.value = null
    setTimeout(() => { reservation.scanResult = null }, 5000)
  } catch (err) {
    playSound('scan_error')
    const resv = substituteModal.value?.reservation
    substituteModal.value = null
    if (resv) {
      resv.scanResult = {
        type: 'error',
        message: err.response?.data?.error ?? 'Erreur lors de la substitution',
      }
    }
  }
}


async function scanAsset(reservation) {
  const tag = reservation.scanInput.trim()
  if (!tag) return

  reservation.scanning = true
  reservation.scanResult = null

  try {
    const { data } = await axios.post(route('attribution.scan'), {
      asset_tag:           tag,
      reservation_id:      reservation.id,
      send_checkout_email: reservation.sendCheckoutEmail,
    })

    // ── Offre de substitution (meme modele) ───────────────────────────────
    if (data.equivalent_offer) {
      substituteModal.value = {
        reservation,
        scanned_asset:  data.scanned_asset,
        original_asset: data.original_asset,
        model_name:     data.model_name,
        confirming:     false,
      }
      reservation.scanning = false
      reservation.scanInput = ''
      return
    }

    // ── Offre d'ajout hors-reservation ────────────────────────────────────
    if (data.add_to_reservation_offer) {
      addToResvModal.value = {
        reservation,
        scanned_asset: data.scanned_asset,
        confirming:    false,
      }
      reservation.scanning = false
      reservation.scanInput = ''
      return
    }

    playSound('scan_success')

    // Update local asset state
    const asset = reservation.assets.find(a => a.asset_tag === tag)
    if (asset) {
      asset.checkout_at = new Date().toISOString()
    }

    if (data.already_checked_out_accessory) {
      reservation.scanResult = {
        type: 'success',
        message: `ℹ️ ${data.asset.name} (${data.asset.asset_tag}) — déjà attribué automatiquement en tant qu'accessoire`,
      }
    } else {
      const emailMsg = data.checkout_email_sent ? ' — 📧 Email récapitulatif envoyé' : ''
      reservation.scanResult = {
        type: 'success',
        message: `${data.asset.name} (${data.asset.asset_tag}) — attribué avec succès${emailMsg}`,
      }
    }

    // Check if all done
    if (reservation.assets.every(a => a.checkout_at)) {
      reservation.all_checked_out = true
    }
  } catch (err) {
    playSound('scan_error')
    reservation.scanResult = {
      type: 'error',
      message: err.response?.data?.error ?? 'Erreur lors du scan',
    }
  } finally {
    reservation.scanning = false
    reservation.scanInput = ''
    focusScanInput(reservation.id)
    // Auto-clear result after 4 seconds
    setTimeout(() => {
      reservation.scanResult = null
    }, 4000)
  }
}

async function manualCheckoutAsset(reservation, asset) {
  reservation.manualCheckingAssetId = asset.id
  reservation.scanResult = null

  try {
    const { data } = await axios.post(route('attribution.manual-checkout'), {
      reservation_id:      reservation.id,
      asset_id:            asset.id,
      send_checkout_email: reservation.sendCheckoutEmail,
    })

    playSound('scan_success')
    asset.checkout_at = new Date().toISOString()

    const emailMsg = data.checkout_email_sent ? ' — 📧 Email récapitulatif envoyé' : ''
    reservation.scanResult = {
      type: 'success',
      message: `${data.asset.name} — attribué manuellement (sans scan)${emailMsg}`,
    }

    if (reservation.assets.every(a => a.checkout_at)) {
      reservation.all_checked_out = true
    }
  } catch (err) {
    playSound('scan_error')
    reservation.scanResult = {
      type: 'error',
      message: err.response?.data?.error ?? 'Erreur lors de l\'attribution manuelle',
    }
  } finally {
    reservation.manualCheckingAssetId = null
    setTimeout(() => { reservation.scanResult = null }, 4000)
  }
}

async function scanMultiple(reservation) {
  if (!reservation.multiScanInput.trim()) return

  // Parse tags: split by newlines or commas
  const rawTags = reservation.multiScanInput.split(/[\n,]+/).map(t => t.trim()).filter(Boolean)
  if (!rawTags.length) return

  reservation.multiScanning = true
  reservation.multiScanResults = []

  try {
    const { data } = await axios.post(route('attribution.scan-multiple'), {
      asset_tags:          rawTags,
      reservation_id:      reservation.id,
      send_checkout_email: reservation.sendCheckoutEmail,
    })

    reservation.multiScanResults = data.results

    // Update local asset states
    data.results.forEach(result => {
      if (result.type === 'success') {
        const asset = reservation.assets.find(a => a.asset_tag === result.tag)
        if (asset) {
          asset.checkout_at = new Date().toISOString()
        }
      }
    })

    const successCount = data.results.filter(r => r.type === 'success').length
    if (successCount > 0) playSound('scan_success')

    if (data.all_checked_out) {
      reservation.all_checked_out = true
      if (data.checkout_email_sent) {
        reservation.multiScanResults.push({
          type: 'success',
          message: '📧 Email récapitulatif envoyé à l\'utilisateur',
        })
      }
    }

    reservation.multiScanInput = ''
  } catch (err) {
    playSound('scan_error')
    reservation.multiScanResults = [{ type: 'error', message: err.response?.data?.error ?? 'Erreur lors du scan multiple' }]
  } finally {
    reservation.multiScanning = false
  }
}

function formatDate(date) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const totalPending = computed(() =>
  localReservations.value.filter(r => !r.all_checked_out).length
)
</script>

<template>
  <AppLayout>
    <div>
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Attribution — Sortie de matériel</h1>
          <p class="text-gray-500 text-sm mt-1">
            {{ localReservations.length }} réservation(s) approuvée(s)
            <span v-if="totalPending > 0" class="ml-2 bg-orange-100 text-orange-700 text-xs px-2 py-0.5 rounded-full font-medium">
              {{ totalPending }} en attente d'attribution
            </span>
          </p>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="localReservations.length === 0"
           class="bg-white rounded-xl shadow-sm border px-8 py-16 text-center text-gray-400">
        <QrCodeIcon class="w-14 h-14 mx-auto mb-3 opacity-30" />
        <p class="text-lg font-medium text-gray-500">Aucune réservation approuvée</p>
        <p class="text-sm mt-1">Les réservations approuvées en attente de sortie apparaîtront ici.</p>
      </div>

      <!-- ── Mode libre (attribution sans réservation, multi-scan) ── -->
      <div class="mb-6">
        <button @click="freeMode = !freeMode; if(!freeMode) freeReset()"
          :class="['flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium border transition',
            freeMode ? 'bg-orange-600 text-white border-orange-600' : 'border-orange-300 text-orange-700 hover:bg-orange-50']">
          <BoltIcon class="w-4 h-4" />
          Mode libre — Attribution sans réservation
        </button>

        <div v-if="freeMode" class="mt-3 bg-orange-50 border border-orange-200 rounded-xl p-5 space-y-4">
          <p class="text-sm text-orange-800 font-medium">
            Scannez un ou plusieurs équipements, puis validez pour créer une réservation active.
          </p>

          <!-- Formulaire -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Utilisateur <span class="text-red-500">*</span></label>
              <select v-model="freeUserId"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 outline-none bg-white">
                <option value="">Sélectionner un utilisateur...</option>
                <option v-for="u in props.users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Date de retour prévue <span class="text-red-500">*</span></label>
              <input v-model="freeEndDate" type="datetime-local"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 outline-none" />
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Titre (optionnel)</label>
            <input v-model="freeTitle" type="text" placeholder="Ex: Prêt matériel photo..."
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 outline-none" />
          </div>

          <!-- Zone de scan -->
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Scanner un équipement (Entrée pour ajouter)</label>
            <div class="flex gap-2">
              <input
                v-model="freeScanInput"
                type="text"
                placeholder="Tag d'équipement (ex: EQ-001)…"
                :disabled="freeScanning"
                @keydown.enter="freeAddAsset"
                class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-orange-500 outline-none disabled:opacity-60"
              />
              <button
                @click="freeAddAsset"
                :disabled="freeScanning || !freeScanInput.trim()"
                class="flex items-center gap-1.5 px-4 py-2 bg-orange-600 text-white text-sm rounded-lg hover:bg-orange-700 transition disabled:opacity-50">
                <QrCodeIcon class="w-4 h-4" />
                {{ freeScanning ? '…' : 'Ajouter' }}
              </button>
            </div>
            <Transition name="fade">
              <p v-if="freeScanError" class="mt-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded px-3 py-1.5">
                ⚠ {{ freeScanError }}
              </p>
            </Transition>
          </div>

          <!-- Liste des équipements en attente -->
          <div v-if="freePendingAssets.length > 0" class="bg-white border border-orange-200 rounded-xl p-3">
            <p class="text-xs font-semibold text-gray-600 mb-2">
              {{ freePendingAssets.length }} équipement(s) à attribuer
            </p>
            <div class="space-y-1.5">
              <div v-for="a in freePendingAssets" :key="a.asset_tag"
                   class="flex items-center justify-between px-3 py-2 bg-orange-50 border border-orange-200 rounded-lg text-sm">
                <div>
                  <span class="font-medium text-gray-900">{{ a.name }}</span>
                  <span class="ml-2 text-xs font-mono text-gray-500">{{ a.asset_tag }}</span>
                </div>
                <button @click="freeRemoveAsset(a.asset_tag)"
                  class="text-red-500 hover:text-red-700 text-xs font-medium px-2 py-0.5 rounded hover:bg-red-50 transition">
                  ✕ Retirer
                </button>
              </div>
            </div>
          </div>

          <!-- Options + bouton valider -->
          <div class="flex items-center justify-between flex-wrap gap-3">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" v-model="freeSendEmail"
                class="w-4 h-4 rounded border-gray-300 text-orange-600 focus:ring-orange-500 cursor-pointer" />
              <span class="text-xs text-gray-600 font-medium">📧 Email récapitulatif à l'utilisateur</span>
            </label>
            <div class="flex gap-2">
              <button @click="freeReset" class="px-3 py-2 text-xs border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition">
                Réinitialiser
              </button>
              <button
                @click="freeSubmit"
                :disabled="freeSubmitting || !freePendingAssets.length || !freeUserId || !freeEndDate"
                class="flex items-center gap-1.5 px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition disabled:opacity-50 font-medium">
                <CheckCircleSolid class="w-4 h-4" />
                {{ freeSubmitting ? 'Validation…' : `Valider l'attribution (${freePendingAssets.length})` }}
              </button>
            </div>
          </div>

          <!-- Résultat final -->
          <Transition name="fade">
            <div v-if="freeSubmitResult"
              class="rounded-lg border p-4 text-sm space-y-1"
              :class="freeSubmitResult.type === 'success'
                ? 'bg-green-50 border-green-200 text-green-800'
                : 'bg-red-50 border-red-200 text-red-800'">
              <p v-for="(line, i) in freeSubmitResult.lines" :key="i" :class="i === 0 ? 'font-semibold' : ''">
                <span v-if="i === 0">{{ freeSubmitResult.type === 'success' ? '✅ ' : '❌ ' }}</span>{{ line }}
              </p>
              <!-- Formulaires obligatoires à remplir -->
              <div v-if="freeSubmitResult.pendingForms?.length" class="mt-2 pt-2 border-t border-green-300">
                <p class="text-xs font-semibold text-orange-700 mb-1.5">📋 Formulaire(s) requis à remplir :</p>
                <div class="flex flex-wrap gap-2">
                  <a v-for="f in freeSubmitResult.pendingForms" :key="f.id"
                     :href="route('checkout-forms.fill', [f.id, freeSubmitResult.reservationId])"
                     class="inline-flex items-center gap-1 bg-orange-100 hover:bg-orange-200 text-orange-800 text-xs font-medium px-3 py-1.5 rounded-lg transition">
                    📝 {{ f.name }} →
                  </a>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </div>

      <!-- Reservation cards -->
      <div class="space-y-6">
        <div v-for="res in localReservations" :key="res.id"
             class="bg-white rounded-xl shadow-sm border overflow-hidden"
             :class="res.all_checked_out ? 'border-green-300' : 'border-gray-200'">

          <!-- Card header -->
          <div class="px-5 py-4 border-b flex items-start justify-between gap-4"
               :class="res.all_checked_out ? 'bg-green-50' : 'bg-gray-50'">
            <div class="flex items-center gap-3 flex-1 min-w-0">
              <div :class="['w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold',
                res.all_checked_out ? 'bg-green-200 text-green-700' : 'bg-blue-100 text-blue-700']">
                {{ res.user.name.charAt(0).toUpperCase() }}
              </div>
              <div class="min-w-0">
                <h3 class="font-semibold text-gray-900 truncate">{{ res.title }}</h3>
                <div class="flex items-center gap-3 text-xs text-gray-500 mt-0.5 flex-wrap">
                  <span class="flex items-center gap-1">
                    <UserIcon class="w-3 h-3" />
                    {{ res.user.name }}
                  </span>
                  <span class="flex items-center gap-1">
                    <CalendarIcon class="w-3 h-3" />
                    {{ formatDate(res.start_date) }} → {{ formatDate(res.end_date) }}
                  </span>
                  <span class="flex items-center gap-1">
                    {{ res.assets.length }} équipement(s)
                  </span>
                </div>
              </div>
            </div>

            <!-- Status badge -->
            <div v-if="res.all_checked_out"
                 class="flex items-center gap-1.5 text-green-700 bg-green-100 px-3 py-1 rounded-full text-sm font-medium flex-shrink-0">
              <CheckCircleSolid class="w-4 h-4" />
              Tout attribué !
            </div>
            <div v-else class="flex items-center gap-1.5 text-orange-600 bg-orange-50 px-3 py-1 rounded-full text-sm font-medium flex-shrink-0">
              <ClockIcon class="w-4 h-4" />
              {{ res.assets.filter(a => !a.checkout_at).length }}/{{ res.assets.length }} restant(s)
            </div>
          </div>

          <!-- Assets list -->
          <div class="px-5 py-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 mb-4">
              <div v-for="asset in res.assets" :key="asset.id"
                   class="flex items-start gap-2 px-3 py-2 rounded-lg border text-sm"
                   :class="asset.checkout_at
                     ? 'bg-green-50 border-green-200 text-green-800'
                     : 'bg-gray-50 border-gray-200 text-gray-700'">
                <CheckCircleSolid v-if="asset.checkout_at" class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                <ClockIcon v-else class="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" />
                <div class="min-w-0 flex-1">
                  <p class="font-medium truncate">{{ asset.name }}</p>
                  <p class="text-xs font-mono opacity-70">{{ asset.asset_tag }}</p>
                  <!-- Pas d'étiquette QR → bouton attribution manuelle -->
                  <button
                    v-if="!asset.qr_code && !asset.checkout_at"
                    @click="manualCheckoutAsset(res, asset)"
                    :disabled="res.manualCheckingAssetId === asset.id"
                    class="mt-1.5 text-xs bg-blue-100 text-blue-700 hover:bg-blue-200 px-2 py-0.5 rounded font-medium transition disabled:opacity-50">
                    {{ res.manualCheckingAssetId === asset.id ? '…' : '📋 Attribution manuelle' }}
                  </button>
                  <span v-else-if="!asset.qr_code && asset.checkout_at"
                    class="mt-1 block text-xs text-green-600">Manuel</span>
                  <span v-else-if="asset.qr_code && !asset.checkout_at"
                    class="mt-1 block text-xs text-gray-400">🔍 Scan requis</span>
                </div>
              </div>
            </div>

            <!-- ── Gérer les équipements (ajouter / retirer) ── -->
            <div class="mb-4">
              <button
                @click="res.manageOpen = !res.manageOpen; if(res.manageOpen) fetchManageAssets(res)"
                class="flex items-center gap-1.5 text-xs font-medium text-gray-500 hover:text-gray-800 border border-gray-200 rounded-lg px-3 py-1.5 hover:bg-gray-50 transition">
                <span>⚙️</span>
                {{ res.manageOpen ? '▲ Masquer' : '▼ Gérer les équipements' }}
              </button>

              <div v-if="res.manageOpen" class="mt-3 bg-gray-50 border border-gray-200 rounded-xl p-4 space-y-3">
                <!-- Liste actuelle avec retrait -->
                <div>
                  <p class="text-xs font-semibold text-gray-600 mb-2">Équipements actuels</p>
                  <div class="space-y-1">
                    <div v-for="asset in res.assets" :key="'mg-'+asset.id"
                         class="flex items-center justify-between px-2 py-1.5 bg-white rounded-lg border text-xs">
                      <span class="font-mono text-gray-700 truncate">{{ asset.asset_tag }} — {{ asset.name }}</span>
                      <div class="flex items-center gap-1 ml-2 flex-shrink-0">
                        <span v-if="asset.checkout_at" class="text-green-600 text-xs">✓ Attribué</span>
                        <button
                          v-else
                          @click="manageRemoveAsset(res, asset)"
                          :disabled="res.managingRemoveId === asset.id"
                          class="text-red-500 hover:text-red-700 font-medium text-xs disabled:opacity-50 px-1.5 py-0.5 rounded hover:bg-red-50">
                          {{ res.managingRemoveId === asset.id ? '…' : '✕ Retirer' }}
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Recherche pour ajouter -->
                <div>
                  <p class="text-xs font-semibold text-gray-600 mb-2">Ajouter un équipement disponible</p>
                  <div v-if="res.manageLoading" class="text-xs text-gray-400">Chargement des disponibilités…</div>
                  <template v-else>
                    <input v-model="res.manageSearch" type="text"
                           placeholder="Rechercher par nom ou tag…"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 outline-none bg-white" />
                    <div v-if="manageFilteredAssets(res).length" class="mt-1 border border-gray-200 rounded-lg divide-y bg-white max-h-40 overflow-y-auto">
                      <div v-for="a in manageFilteredAssets(res)" :key="'ma-'+a.id"
                           class="flex items-center justify-between px-3 py-2 text-xs hover:bg-blue-50">
                        <span>
                          <span class="font-medium">{{ a.name }}</span>
                          <span class="text-gray-400 font-mono ml-1.5">{{ a.asset_tag }}</span>
                        </span>
                        <button @click="manageAddAsset(res, a)"
                                :disabled="res.managingAddId === a.id"
                                class="ml-2 flex-shrink-0 bg-blue-600 text-white px-2 py-1 rounded font-medium hover:bg-blue-700 disabled:opacity-50">
                          {{ res.managingAddId === a.id ? '…' : '+ Ajouter' }}
                        </button>
                      </div>
                    </div>
                    <p v-else-if="res.manageSearch && !manageFilteredAssets(res).length"
                       class="mt-1 text-xs text-gray-400">Aucun équipement disponible correspondant.</p>
                    <p v-else-if="!res.manageSearch && !res.manageAllAvailable.length"
                       class="mt-1 text-xs text-gray-400">Aucun autre équipement disponible sur cette période.</p>
                  </template>
                  <p v-if="res.manageError" class="mt-2 text-xs text-red-600 bg-red-50 border border-red-200 rounded px-2 py-1">{{ res.manageError }}</p>
                </div>
              </div>
            </div>

            <!-- Scan zone -->
            <div v-if="!res.all_checked_out" class="mt-3">

              <!-- Option : email récapitulatif -->
              <label class="flex items-center gap-2 mb-3 cursor-pointer w-fit select-none">
                <input type="checkbox" v-model="res.sendCheckoutEmail"
                  class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer" />
                <span class="text-xs text-gray-600 font-medium">
                  📧 Envoyer un email récapitulatif à la récupération
                </span>
              </label>

              <!-- Toggle between single and multi scan -->
              <div class="flex items-center gap-2 mb-3">
                <button @click="res.showMultiScan = false"
                        :class="['flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border transition',
                          !res.showMultiScan ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50']">
                  <QrCodeIcon class="w-3.5 h-3.5" />
                  Scan unitaire
                </button>
                <button @click="res.showMultiScan = true"
                        :class="['flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border transition',
                          res.showMultiScan ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50']">
                  <ListBulletIcon class="w-3.5 h-3.5" />
                  Scan multiple
                </button>
              </div>

              <!-- Single scan -->
              <div v-if="!res.showMultiScan">
                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                  Scanner un tag d'équipement pour cette réservation
                </label>
                <div class="flex gap-2">
                  <input
                    v-model="res.scanInput"
                    :ref="el => setScanInputRef(el, res.id)"
                    type="text"
                    placeholder="Tag d'équipement (ex: EQ-001)…"
                    :disabled="res.scanning"
                    @keydown.enter="scanAsset(res)"
                    autocomplete="off"
                    class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500 outline-none disabled:opacity-60"
                  />
                  <button
                    @click="scanAsset(res)"
                    :disabled="res.scanning || !res.scanInput.trim()"
                    class="flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition disabled:opacity-50">
                    <QrCodeIcon class="w-4 h-4" />
                    {{ res.scanning ? 'Scan…' : 'Scanner' }}
                  </button>
                </div>

                <!-- Scan result feedback -->
                <Transition name="fade">
                  <div v-if="res.scanResult"
                       class="mt-2 flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium"
                       :class="res.scanResult.type === 'success'
                         ? 'bg-green-100 text-green-800 border border-green-200'
                         : 'bg-red-100 text-red-800 border border-red-200'">
                    <CheckCircleSolid v-if="res.scanResult.type === 'success'" class="w-4 h-4 text-green-600 flex-shrink-0" />
                    <span v-else class="w-4 h-4 flex-shrink-0 font-bold text-red-600 leading-none">!</span>
                    {{ res.scanResult.message }}
                  </div>
                </Transition>
              </div>

              <!-- Multi scan -->
              <div v-else>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                  Coller/saisir plusieurs tags (un par ligne ou séparés par des virgules)
                </label>
                <textarea
                  v-model="res.multiScanInput"
                  rows="4"
                  placeholder="EQ-ABC12&#10;EQ-DEF34&#10;EQ-GHI56"
                  :disabled="res.multiScanning"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500 outline-none resize-none disabled:opacity-60"
                ></textarea>
                <div class="flex justify-end mt-2">
                  <button
                    @click="scanMultiple(res)"
                    :disabled="res.multiScanning || !res.multiScanInput.trim()"
                    class="flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition disabled:opacity-50">
                    <ListBulletIcon class="w-4 h-4" />
                    {{ res.multiScanning ? 'Traitement…' : 'Scanner tout' }}
                  </button>
                </div>

                <!-- Multi scan results -->
                <div v-if="res.multiScanResults.length > 0" class="mt-3 space-y-1">
                  <div v-for="(result, idx) in res.multiScanResults" :key="idx"
                       class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium"
                       :class="result.type === 'success'
                         ? 'bg-green-50 text-green-800 border border-green-200'
                         : result.type === 'warning'
                           ? 'bg-yellow-50 text-yellow-800 border border-yellow-200'
                           : 'bg-red-50 text-red-800 border border-red-200'">
                    <CheckCircleSolid v-if="result.type === 'success'" class="w-3.5 h-3.5 text-green-600 flex-shrink-0" />
                    <span v-else-if="result.type === 'warning'" class="text-yellow-600 flex-shrink-0">⚠</span>
                    <span v-else class="text-red-600 flex-shrink-0 font-bold">!</span>
                    {{ result.message }}
                  </div>
                </div>
              </div>
            </div>

            <!-- All done message -->
            <div v-else class="mt-2 flex items-center gap-2 text-green-700 text-sm">
              <CheckCircleSolid class="w-5 h-5" />
              <span>Tous les équipements ont été attribués. La réservation est maintenant active.</span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- ═══ Modal : ajouter equipement hors-reservation ═══ -->
    <div v-if="addToResvModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center gap-3 mb-4">
          <span class="text-2xl">➕</span>
          <h3 class="font-bold text-lg text-gray-900">Équipement hors réservation</h3>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-5 text-sm">
          <p class="text-blue-900 font-semibold mb-2">
            Cet équipement n'est pas dans la réservation :
          </p>
          <div class="bg-white border border-blue-200 rounded px-3 py-2 font-mono text-xs">
            {{ addToResvModal.scanned_asset.asset_tag }} — {{ addToResvModal.scanned_asset.name }}
          </div>
        </div>

        <p class="text-sm text-gray-600 mb-5">
          Voulez-vous <strong>ajouter cet équipement</strong> à la réservation et l'attribuer immédiatement ?
          Il sera ajouté à la liste des équipements et marqué comme sorti.
        </p>

        <div class="flex gap-3">
          <button @click="addToResvModal = null"
            class="flex-1 border border-gray-300 py-2.5 rounded-xl text-sm text-gray-700 hover:bg-gray-50 transition">
            Annuler
          </button>
          <button @click="confirmAddToReservation"
            :disabled="addToResvModal.confirming"
            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-xl text-sm font-semibold transition disabled:opacity-50">
            {{ addToResvModal.confirming ? 'En cours…' : '✅ Ajouter et attribuer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : substitution équipement équivalent ═══ -->
    <div v-if="substituteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center gap-3 mb-4">
          <span class="text-2xl">🔄</span>
          <h3 class="font-bold text-lg text-gray-900">Équipement équivalent détecté</h3>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5 text-sm">
          <p class="font-semibold text-amber-900 mb-3">
            L'équipement scanné n'est pas dans la réservation, mais il est du même modèle :
            <span class="font-bold text-amber-700">{{ substituteModal.model_name }}</span>
          </p>
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <span class="text-red-500 font-medium text-xs w-20 flex-shrink-0">Prévu :</span>
              <span class="bg-white border border-red-200 rounded px-2 py-1 font-mono text-xs flex-1">
                {{ substituteModal.original_asset.asset_tag }} — {{ substituteModal.original_asset.name }}
              </span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-green-600 font-medium text-xs w-20 flex-shrink-0">Scanné :</span>
              <span class="bg-white border border-green-200 rounded px-2 py-1 font-mono text-xs flex-1">
                {{ substituteModal.scanned_asset.asset_tag }} — {{ substituteModal.scanned_asset.name }}
              </span>
            </div>
          </div>
        </div>

        <p class="text-sm text-gray-600 mb-5">
          Voulez-vous <strong>substituer</strong> l'équipement prévu par celui-ci ?
          L'équipement original sera retiré de la réservation et remplacé par l'équipement scanné.
        </p>

        <div class="flex gap-3">
          <button @click="substituteModal = null"
            class="flex-1 border border-gray-300 py-2.5 rounded-xl text-sm text-gray-700 hover:bg-gray-50 transition">
            Annuler
          </button>
          <button @click="confirmSubstitute"
            :disabled="substituteModal.confirming"
            class="flex-1 bg-amber-500 hover:bg-amber-600 text-white py-2.5 rounded-xl text-sm font-semibold transition disabled:opacity-50">
            {{ substituteModal.confirming ? 'En cours…' : '✅ Confirmer la substitution' }}
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
