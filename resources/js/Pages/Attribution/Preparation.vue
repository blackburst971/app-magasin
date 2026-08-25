<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import { ref, computed, nextTick } from 'vue'
import axios from 'axios'
import {
  PlusIcon, TrashIcon, XMarkIcon, CheckCircleIcon,
  QrCodeIcon, UserIcon, CalendarIcon, EnvelopeIcon,
  ArrowPathIcon, DocumentTextIcon, ArrowRightIcon,
  ArchiveBoxIcon, MagnifyingGlassIcon, ClipboardDocumentListIcon,
  ClockIcon, BoltIcon,
} from '@heroicons/vue/24/outline'
import BarcodeScanButton from '@/Components/BarcodeScanButton.vue'

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps({
  preparations:            Array,
  reservationPreparations: Array,
  approvedReservations:    Array,
  users:                   Array,
  defaultTab:              { type: String, default: 'active' },
})

// ── État global ───────────────────────────────────────────────────────────────
const tab          = ref(props.defaultTab)   // 'create' | 'active' | 'reservations'
const preparations = ref([...props.preparations])
const resvPreparations = ref([...props.reservationPreparations])

// ── Formulaire de création ────────────────────────────────────────────────────
const createMode       = ref('free')   // 'free' | 'from-reservation'
const selectedResaId   = ref(null)
const createForm       = ref({
  title:                 '',
  user_id:               null,
  planned_start_at:      '',
  planned_end_at:        '',
  notes:                 '',
  send_preparation_mail: false,
  send_checkout_mail:    false,
})
const createLoading    = ref(false)
const createError      = ref('')

// Remise automatique (séparé du formulaire principal)
const enableAutoCheckout = ref(false)
const autoCheckoutTime   = ref('')   // HH:mm

/** Construit le datetime complet auto_checkout_at à partir de planned_start_at + autoCheckoutTime */
function buildAutoCheckoutAt() {
  if (!enableAutoCheckout.value || !autoCheckoutTime.value || !createForm.value.planned_start_at) return null
  const datePart = createForm.value.planned_start_at.slice(0, 10) // YYYY-MM-DD
  return `${datePart}T${autoCheckoutTime.value}`
}

// ── Scan par préparation ───────────────────────────────────────────────────────
const scanInputs   = ref({})   // prepId → tag string
const scanLoading  = ref({})   // prepId → bool
const scanResult   = ref({})   // prepId → { ok, message }
const scanRefs     = ref({})   // prepId → input element

// ── Kit search par préparation ────────────────────────────────────────────────
const kitSearch    = ref({})   // prepId → query string
const kitResults   = ref({})   // prepId → array of kits
const kitLoading   = ref({})   // prepId → bool
const kitSearchTimer = {}       // prepId → timeout handle
const addingKitId  = ref({})   // prepId → bool (adding in progress)
const kitResult    = ref({})   // prepId → { ok, message } feedback
const showKitSearch = ref({})  // prepId → bool (toggle kit panel)

// ── États de confirmation ─────────────────────────────────────────────────────
const validatingId  = ref(null)
const cancellingId  = ref(null)
const notifyingId   = ref(null)
const notifyResult  = ref({})   // prepId → { ok, message }

// ── Computed ───────────────────────────────────────────────────────────────────
const activeCount     = computed(() => preparations.value.length)

/** Regroupe les assets d'une préparation par kit (ou "individuel" si pas de kit). */
function groupedAssets(assets) {
  const kits = {}
  const individual = []
  for (const asset of assets) {
    if (asset.kit) {
      const key = asset.kit.id
      if (!kits[key]) kits[key] = { kit: asset.kit, assets: [] }
      kits[key].assets.push(asset)
    } else {
      individual.push(asset)
    }
  }
  return { kitGroups: Object.values(kits), individual }
}

const approvedReservations = computed(() => props.approvedReservations)

const selectedResa = computed(() =>
  selectedResaId.value ? approvedReservations.value.find(r => r.id === selectedResaId.value) : null
)

// ─────────────────────────────────────────────────────────────────────────────
// SÉLECTION D'UNE RÉSERVATION POUR PRÉ-REMPLISSAGE
// ─────────────────────────────────────────────────────────────────────────────

function onResaSelected() {
  const resa = selectedResa.value
  if (!resa) return
  createForm.value.title            = resa.title
  createForm.value.user_id          = resa.user.id
  createForm.value.planned_start_at = resa.start_date ?? ''
  createForm.value.planned_end_at   = resa.end_date ?? ''
}

// ─────────────────────────────────────────────────────────────────────────────
// CRÉATION D'UNE PRÉPARATION
// ─────────────────────────────────────────────────────────────────────────────

async function createPreparation() {
  createError.value = ''
  if (!createForm.value.title || !createForm.value.user_id || !createForm.value.planned_start_at || !createForm.value.planned_end_at) {
    createError.value = 'Veuillez remplir tous les champs obligatoires.'
    return
  }
  if (enableAutoCheckout.value && !autoCheckoutTime.value) {
    createError.value = 'Veuillez indiquer l\'heure de remise automatique ou désactiver l\'option.'
    return
  }
  createLoading.value = true
  try {
    const payload = {
      ...createForm.value,
      reservation_id:   createMode.value === 'from-reservation' ? selectedResaId.value : null,
      auto_checkout_at: buildAutoCheckoutAt(),
    }
    const { data } = await axios.post(route('preparation.store'), payload)
    preparations.value.unshift(data)
    createForm.value = {
      title: '', user_id: null, planned_start_at: '', planned_end_at: '',
      notes: '', send_preparation_mail: false, send_checkout_mail: false,
    }
    enableAutoCheckout.value = false
    autoCheckoutTime.value   = ''
    selectedResaId.value = null
    createMode.value = 'free'
    tab.value = 'active'
  } catch (e) {
    createError.value = e.response?.data?.error ?? 'Erreur lors de la création.'
  } finally {
    createLoading.value = false
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// SCAN D'UN ÉQUIPEMENT
// ─────────────────────────────────────────────────────────────────────────────

function focusScan(prepId) {
  nextTick(() => scanRefs.value[prepId]?.focus())
}

async function handleScan(prepId) {
  const tag = (scanInputs.value[prepId] ?? '').trim()
  if (!tag) return

  scanLoading.value[prepId] = true
  scanResult.value[prepId]  = null

  try {
    const { data } = await axios.post(route('preparation.add-asset', { preparation: prepId }), { asset_tag: tag })
    const prep = preparations.value.find(p => p.id === prepId)
    if (prep) prep.assets.push(data.asset)
    scanInputs.value[prepId] = ''
    scanResult.value[prepId] = { ok: true, message: `✓ ${data.asset.name} ajouté` }
    setTimeout(() => { scanResult.value[prepId] = null }, 3000)
    focusScan(prepId)
  } catch (e) {
    scanResult.value[prepId] = { ok: false, message: e.response?.data?.error ?? 'Erreur lors du scan.' }
    scanInputs.value[prepId] = ''
    focusScan(prepId)
  } finally {
    scanLoading.value[prepId] = false
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// RECHERCHE DE KITS
// ─────────────────────────────────────────────────────────────────────────────

function onKitSearchInput(prepId) {
  clearTimeout(kitSearchTimer[prepId])
  kitSearchTimer[prepId] = setTimeout(() => fetchKits(prepId), 250)
}

async function fetchKits(prepId) {
  const q = (kitSearch.value[prepId] ?? '').trim()
  kitLoading.value[prepId] = true
  try {
    const { data } = await axios.get(route('preparation.search-kits'), { params: { q } })
    kitResults.value[prepId] = data.kits
  } catch (e) {
    kitResults.value[prepId] = []
  } finally {
    kitLoading.value[prepId] = false
  }
}

function toggleKitSearch(prepId) {
  showKitSearch.value[prepId] = !showKitSearch.value[prepId]
  if (showKitSearch.value[prepId] && !kitResults.value[prepId]) {
    fetchKits(prepId)
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// AJOUTER UN KIT
// ─────────────────────────────────────────────────────────────────────────────

async function addKit(prepId, kit) {
  addingKitId.value[prepId] = kit.id
  kitResult.value[prepId]   = null

  try {
    const { data } = await axios.post(route('preparation.add-kit', { preparation: prepId }), { kit_id: kit.id })
    const prep = preparations.value.find(p => p.id === prepId)
    if (prep) {
      for (const asset of data.added) {
        if (!prep.assets.find(a => a.id === asset.id)) {
          prep.assets.push(asset)
        }
      }
    }

    const addedCount   = data.added.length
    const skippedCount = data.skipped.length
    let msg = `✓ Kit « ${data.kit_name} » : ${addedCount} équipement${addedCount > 1 ? 's' : ''} ajouté${addedCount > 1 ? 's' : ''}`
    if (skippedCount) msg += `, ${skippedCount} ignoré${skippedCount > 1 ? 's' : ''}`
    kitResult.value[prepId] = { ok: true, message: msg, skipped: data.skipped }
    setTimeout(() => { kitResult.value[prepId] = null }, 6000)

    // Refresh kit list to update state
    fetchKits(prepId)
  } catch (e) {
    kitResult.value[prepId] = { ok: false, message: e.response?.data?.error ?? 'Erreur lors de l\'ajout du kit.' }
  } finally {
    addingKitId.value[prepId] = null
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// RETIRER UN ÉQUIPEMENT
// ─────────────────────────────────────────────────────────────────────────────

async function removeAsset(prepId, assetId) {
  try {
    await axios.delete(route('preparation.remove-asset', { preparation: prepId, asset: assetId }))
    const prep = preparations.value.find(p => p.id === prepId)
    if (prep) prep.assets = prep.assets.filter(a => a.id !== assetId)
  } catch (e) {
    alert(e.response?.data?.error ?? 'Erreur lors de la suppression.')
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// VALIDER LA SORTIE
// ─────────────────────────────────────────────────────────────────────────────

async function validateCheckout(prepId) {
  if (validatingId.value) return
  validatingId.value = prepId
  try {
    const { data } = await axios.post(route('preparation.validate', { preparation: prepId }))
    preparations.value = preparations.value.filter(p => p.id !== prepId)
    if (data.reservation_id) {
      window.location.href = route('reservations.show', { reservation: data.reservation_id })
    }
  } catch (e) {
    alert(e.response?.data?.error ?? 'Erreur lors de la validation.')
  } finally {
    validatingId.value = null
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// ANNULER UNE PRÉPARATION
// ─────────────────────────────────────────────────────────────────────────────

async function cancelPreparation(prepId) {
  if (!confirm('Annuler cette préparation ? Les statuts des équipements seront restaurés.')) return
  cancellingId.value = prepId
  try {
    await axios.post(route('preparation.cancel', { preparation: prepId }))
    preparations.value = preparations.value.filter(p => p.id !== prepId)
  } catch (e) {
    alert(e.response?.data?.error ?? 'Erreur lors de l\'annulation.')
  } finally {
    cancellingId.value = null
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// ENVOYER LA NOTIFICATION
// ─────────────────────────────────────────────────────────────────────────────

async function sendNotification(prepId) {
  notifyingId.value = prepId
  notifyResult.value[prepId] = null
  try {
    await axios.post(route('preparation.notify', { preparation: prepId }))
    notifyResult.value[prepId] = { ok: true, message: '✉️ Notification envoyée.' }
    setTimeout(() => { notifyResult.value[prepId] = null }, 4000)
  } catch (e) {
    notifyResult.value[prepId] = { ok: false, message: e.response?.data?.error ?? 'Erreur lors de l\'envoi.' }
    setTimeout(() => { notifyResult.value[prepId] = null }, 5000)
  } finally {
    notifyingId.value = null
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// PRÉPARATIONS DE RÉSERVATION — SCAN ÉQUIPEMENT PAR ÉQUIPEMENT
// ─────────────────────────────────────────────────────────────────────────────

const resvScanInputs  = ref({})   // prepId → tag string
const resvScanLoading = ref({})   // prepId → bool
const resvScanResult  = ref({})   // prepId → { ok, message }
const resvScanRefs    = ref({})   // prepId → input element

function focusResvScan(prepId) {
  nextTick(() => resvScanRefs.value[prepId]?.focus())
}

async function handleResvScan(prepId) {
  const tag = (resvScanInputs.value[prepId] ?? '').trim()
  if (!tag) return

  resvScanLoading.value[prepId] = true
  resvScanResult.value[prepId]  = null

  try {
    const { data } = await axios.post(route('preparation.scan-reservation', { preparation: prepId }), { asset_tag: tag })

    // Mettre à jour l'asset dans la liste locale
    const prep = resvPreparations.value.find(p => p.id === prepId)
    if (prep) {
      const asset = prep.reservation_assets.find(a => a.asset_tag === data.asset.asset_tag)
      if (asset) asset.scanned = true
      prep.scanned_count = data.scanned_count
      prep.all_scanned   = data.all_scanned
    }

    resvScanInputs.value[prepId] = ''
    resvScanResult.value[prepId] = {
      ok:      true,
      message: `✓ ${data.asset.name} (${data.asset.asset_tag}) mis en préparation`,
    }
    setTimeout(() => { resvScanResult.value[prepId] = null }, 3500)
    focusResvScan(prepId)
  } catch (e) {
    resvScanResult.value[prepId] = {
      ok:      false,
      message: e.response?.data?.error ?? 'Erreur lors du scan.',
    }
    resvScanInputs.value[prepId] = ''
    focusResvScan(prepId)
  } finally {
    resvScanLoading.value[prepId] = false
  }
}

async function validateResvCheckout(prepId) {
  if (validatingId.value) return
  validatingId.value = prepId
  try {
    const { data } = await axios.post(route('preparation.validate', { preparation: prepId }))
    resvPreparations.value = resvPreparations.value.filter(p => p.id !== prepId)
    if (data.reservation_id) {
      window.location.href = route('reservations.show', { reservation: data.reservation_id })
    }
  } catch (e) {
    alert(e.response?.data?.error ?? 'Erreur lors de la validation.')
  } finally {
    validatingId.value = null
  }
}

async function cancelResvPreparation(prepId) {
  if (!confirm('Annuler cette préparation ? Les équipements scannés seront restitués et la réservation repassera en "Validée".')) return
  cancellingId.value = prepId
  try {
    await axios.post(route('preparation.cancel', { preparation: prepId }))
    resvPreparations.value = resvPreparations.value.filter(p => p.id !== prepId)
  } catch (e) {
    alert(e.response?.data?.error ?? 'Erreur lors de l\'annulation.')
  } finally {
    cancellingId.value = null
  }
}
</script>

<template>
  <AppLayout title="Préparation du matériel">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

      <!-- ══ EN-TÊTE ══════════════════════════════════════════════════════ -->
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Préparation du matériel</h1>
          <p class="text-sm text-gray-500 mt-0.5">Préparez les équipements avant la sortie officielle.</p>
        </div>
        <button @click="tab = 'create'"
          class="flex items-center gap-1.5 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition">
          <PlusIcon class="h-4 w-4" /> Nouvelle préparation
        </button>
      </div>

      <!-- ══ ONGLETS ══════════════════════════════════════════════════════ -->
      <div class="flex border-b gap-6">
        <button @click="tab = 'active'"
          :class="tab === 'active' ? 'border-b-2 border-blue-600 text-blue-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
          class="pb-3 text-sm transition flex items-center gap-1.5">
          📦 Préparations libres
          <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-medium">
            {{ activeCount }}
          </span>
        </button>
        <button @click="tab = 'reservations'"
          :class="tab === 'reservations' ? 'border-b-2 border-sky-600 text-sky-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
          class="pb-3 text-sm transition flex items-center gap-1.5">
          📋 Réservations en préparation
          <span v-if="resvPreparations.length" class="text-xs bg-sky-100 text-sky-700 px-1.5 py-0.5 rounded-full font-medium">
            {{ resvPreparations.length }}
          </span>
        </button>
        <button @click="tab = 'create'"
          :class="tab === 'create' ? 'border-b-2 border-blue-600 text-blue-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
          class="pb-3 text-sm transition">
          ➕ Nouvelle préparation
        </button>
      </div>

      <!-- ══ ONGLET : NOUVELLE PRÉPARATION ══════════════════════════════ -->
      <div v-if="tab === 'create'" class="bg-white rounded-xl border shadow-sm p-6 space-y-5">

        <!-- Toggle mode -->
        <div class="flex gap-3">
          <button @click="createMode = 'free'"
            :class="createMode === 'free' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
            class="px-4 py-2 rounded-lg text-sm font-medium transition">
            Préparation libre
          </button>
          <button @click="createMode = 'from-reservation'"
            :class="createMode === 'from-reservation' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
            class="px-4 py-2 rounded-lg text-sm font-medium transition">
            Depuis une réservation
          </button>
        </div>

        <!-- Sélection réservation -->
        <div v-if="createMode === 'from-reservation'" class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">Réservation approuvée</label>
          <select v-model="selectedResaId" @change="onResaSelected"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            <option :value="null">— Choisir une réservation —</option>
            <option v-for="r in approvedReservations" :key="r.id" :value="r.id">
              #{{ r.id }} — {{ r.title }} ({{ r.user.name }}) — {{ r.status_label }}
            </option>
          </select>
          <p v-if="!approvedReservations.length" class="text-xs text-gray-400">
            Aucune réservation approuvée ou en attente disponible.
          </p>
        </div>

        <!-- Champs du formulaire -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Objet / titre <span class="text-red-500">*</span></label>
            <input v-model="createForm.title" type="text" placeholder="Ex: Tournage documentaire lycée…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Utilisateur <span class="text-red-500">*</span></label>
            <select v-model="createForm.user_id"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
              <option :value="null">— Choisir un utilisateur —</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date de retrait prévue <span class="text-red-500">*</span></label>
            <input v-model="createForm.planned_start_at" type="datetime-local"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date de retour prévue <span class="text-red-500">*</span></label>
            <input v-model="createForm.planned_end_at" type="datetime-local"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" />
          </div>

          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optionnel)</label>
            <textarea v-model="createForm.notes" rows="2"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
              placeholder="Informations complémentaires…" />
          </div>
        </div>

        <!-- Remise automatique -->
        <div class="border border-amber-200 rounded-xl p-4 bg-amber-50/50 space-y-3">
          <div class="flex items-start gap-3">
            <div class="flex items-center gap-2 flex-1">
              <BoltIcon class="h-4 w-4 text-amber-600 shrink-0" />
              <div>
                <p class="text-sm font-semibold text-amber-900">Remise automatique</p>
                <p class="text-xs text-amber-700 mt-0.5">
                  À l'heure choisie (date = date de retrait prévue), la préparation sera automatiquement validée et le matériel considéré comme sorti — sans intervention de l'admin.
                </p>
              </div>
            </div>
            <button type="button"
              @click="enableAutoCheckout = !enableAutoCheckout"
              :class="enableAutoCheckout ? 'bg-amber-500' : 'bg-gray-200'"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none shrink-0 mt-0.5">
              <span :class="enableAutoCheckout ? 'translate-x-6' : 'translate-x-1'"
                class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform" />
            </button>
          </div>

          <Transition
            enter-active-class="transition-all duration-200 ease-out overflow-hidden"
            leave-active-class="transition-all duration-150 ease-in overflow-hidden"
            enter-from-class="opacity-0 max-h-0"
            enter-to-class="opacity-100 max-h-40"
            leave-from-class="opacity-100 max-h-40"
            leave-to-class="opacity-0 max-h-0">
            <div v-if="enableAutoCheckout" class="space-y-2">
              <div class="flex items-center gap-3">
                <div class="flex-1">
                  <label class="block text-xs font-medium text-amber-800 mb-1 flex items-center gap-1">
                    <ClockIcon class="h-3.5 w-3.5" /> Heure de remise <span class="text-red-500">*</span>
                  </label>
                  <input v-model="autoCheckoutTime" type="time"
                    class="w-full border border-amber-300 bg-white rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-400 outline-none" />
                </div>
                <div v-if="createForm.planned_start_at && autoCheckoutTime"
                  class="flex-1 bg-white border border-amber-200 rounded-lg px-3 py-2 text-xs text-amber-800">
                  <p class="font-semibold">Déclenchement automatique :</p>
                  <p class="font-mono mt-0.5">
                    {{ createForm.planned_start_at.slice(0,10).split('-').reverse().join('/') }} à {{ autoCheckoutTime }}
                  </p>
                </div>
              </div>
              <p class="text-xs text-amber-600 flex items-start gap-1.5">
                <span class="shrink-0">ℹ️</span>
                Le matériel doit être physiquement déposé dans un lieu accessible avant cette heure. L'utilisateur pourra récupérer le matériel sans que l'admin soit présent.
              </p>
            </div>
          </Transition>
        </div>

        <!-- Options email -->
        <div class="border-t pt-4 space-y-2">
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Options email</p>
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="createForm.send_preparation_mail" type="checkbox"
              class="h-4 w-4 text-blue-600 rounded border-gray-300" />
            <span class="text-sm text-gray-700">Notifier l'utilisateur que le matériel est en préparation</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="createForm.send_checkout_mail" type="checkbox"
              class="h-4 w-4 text-blue-600 rounded border-gray-300" />
            <span class="text-sm text-gray-700">Envoyer le récapitulatif de sortie lors de la validation</span>
          </label>
        </div>

        <!-- Erreur -->
        <div v-if="createError" class="bg-red-50 text-red-700 text-sm px-4 py-3 rounded-lg border border-red-200">
          {{ createError }}
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3 pt-2">
          <button @click="createPreparation" :disabled="createLoading"
            class="flex items-center gap-2 bg-blue-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition disabled:opacity-50">
            <ArrowPathIcon v-if="createLoading" class="h-4 w-4 animate-spin" />
            <CheckCircleIcon v-else class="h-4 w-4" />
            Créer la préparation
          </button>
          <button @click="tab = 'active'" class="text-sm text-gray-500 hover:text-gray-700">Annuler</button>
        </div>
      </div>

      <!-- ══ ONGLET : RÉSERVATIONS EN PRÉPARATION ══════════════════════════ -->
      <div v-if="tab === 'reservations'">

        <div v-if="!resvPreparations.length" class="text-center py-20 text-gray-400">
          <ClipboardDocumentListIcon class="h-12 w-12 mx-auto mb-3 opacity-20" />
          <p class="text-sm">Aucune réservation en préparation.</p>
          <p class="text-xs mt-1 text-gray-400">
            Depuis la page d'une réservation validée, cliquez sur "Mettre en préparation".
          </p>
        </div>

        <div v-else class="space-y-5">
          <div v-for="prep in resvPreparations" :key="prep.id"
            class="bg-white rounded-xl border border-sky-200 shadow-sm overflow-hidden">

            <!-- En-tête -->
            <div class="flex items-start justify-between gap-3 p-4 border-b bg-sky-50/60">
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-semibold text-gray-900 text-sm">{{ prep.title }}</span>
                  <span class="text-xs bg-sky-100 text-sky-700 px-2 py-0.5 rounded-full font-medium">
                    📋 Réservation #{{ prep.reservation_id }}
                  </span>
                </div>
                <div class="flex items-center gap-3 mt-1 text-xs text-gray-500 flex-wrap">
                  <span class="flex items-center gap-1">
                    <UserIcon class="h-3.5 w-3.5" /> {{ prep.user?.name }}
                  </span>
                  <span class="flex items-center gap-1">
                    <CalendarIcon class="h-3.5 w-3.5" />
                    {{ prep.planned_start_label }} → {{ prep.planned_end_label }}
                  </span>
                  <span class="text-gray-400">Par {{ prep.prepared_by?.name }}</span>
                </div>
              </div>
              <div class="flex items-center gap-1 shrink-0">
                <button @click="sendNotification(prep.id)" :disabled="notifyingId === prep.id"
                  title="Notifier l'utilisateur que sa réservation est en cours de préparation"
                  class="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition disabled:opacity-50">
                  <ArrowPathIcon v-if="notifyingId === prep.id" class="h-3.5 w-3.5 animate-spin" />
                  <EnvelopeIcon v-else class="h-3.5 w-3.5" />
                </button>
                <button @click="cancelResvPreparation(prep.id)" :disabled="cancellingId === prep.id"
                  title="Annuler la préparation"
                  class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition disabled:opacity-50">
                  <ArrowPathIcon v-if="cancellingId === prep.id" class="h-3.5 w-3.5 animate-spin text-red-500" />
                  <XMarkIcon v-else class="h-4 w-4" />
                </button>
              </div>
            </div>

            <!-- Corps -->
            <div class="p-4 space-y-4">

              <!-- Feedback notification -->
              <div v-if="notifyResult[prep.id]" class="text-sm px-3 py-2 rounded-lg"
                :class="notifyResult[prep.id].ok ? 'bg-blue-50 text-blue-700' : 'bg-red-50 text-red-700'">
                {{ notifyResult[prep.id].message }}
              </div>

              <!-- Barre de progression -->
              <div class="space-y-1.5">
                <div class="flex items-center justify-between text-xs text-gray-600">
                  <span class="font-medium">Progression</span>
                  <span :class="prep.all_scanned ? 'text-green-700 font-semibold' : 'text-sky-700'">
                    {{ prep.scanned_count }} / {{ prep.total_count }} équipement{{ prep.total_count > 1 ? 's' : '' }} scanné{{ prep.scanned_count > 1 ? 's' : '' }}
                  </span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                  <div class="h-2 rounded-full transition-all duration-500"
                    :class="prep.all_scanned ? 'bg-green-500' : 'bg-sky-500'"
                    :style="{ width: prep.total_count > 0 ? (prep.scanned_count / prep.total_count * 100) + '%' : '0%' }">
                  </div>
                </div>
              </div>

              <!-- Liste des équipements de la réservation -->
              <div class="space-y-1">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                  Équipements de la réservation
                </p>
                <div class="border rounded-lg overflow-hidden divide-y">
                  <div v-for="asset in prep.reservation_assets" :key="asset.id"
                    class="flex items-center gap-3 px-3 py-2"
                    :class="asset.scanned ? 'bg-green-50' : 'bg-white'">
                    <div class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center shrink-0 overflow-hidden">
                      <img v-if="asset.image" :src="asset.image" class="w-full h-full object-cover" alt="" />
                      <QrCodeIcon v-else class="h-3.5 w-3.5 text-gray-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                      <span class="text-sm font-medium text-gray-800">{{ asset.name }}</span>
                      <span class="text-xs text-gray-400 font-mono ml-2">{{ asset.asset_tag }}</span>
                      <span v-if="asset.category" class="text-xs text-gray-500 ml-1">· {{ asset.category }}</span>
                    </div>
                    <span v-if="asset.scanned"
                      class="flex items-center gap-1 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium shrink-0">
                      <CheckCircleIcon class="h-3.5 w-3.5" /> Scanné
                    </span>
                    <span v-else
                      class="text-xs bg-gray-100 text-gray-400 px-2 py-0.5 rounded-full shrink-0">
                      En attente
                    </span>
                  </div>
                </div>
              </div>

              <!-- Zone de scan -->
              <div class="space-y-2">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Scanner un équipement</p>
                <div class="flex items-center gap-2">
                  <div class="flex-1 relative">
                    <QrCodeIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input
                      :ref="el => resvScanRefs[prep.id] = el"
                      v-model="resvScanInputs[prep.id]"
                      type="text"
                      placeholder="Scanner un équipement de la réservation…"
                      class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-sky-500"
                      @keydown.enter="handleResvScan(prep.id)"
                      :disabled="resvScanLoading[prep.id] || prep.all_scanned"
                    />
                  </div>
                  <BarcodeScanButton
                    title="Scanner un équipement"
                    @scanned="(code) => { resvScanInputs[prep.id] = code; handleResvScan(prep.id) }"
                  />
                  <button @click="handleResvScan(prep.id)"
                    :disabled="resvScanLoading[prep.id] || prep.all_scanned"
                    class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm transition disabled:opacity-50">
                    <ArrowPathIcon v-if="resvScanLoading[prep.id]" class="h-4 w-4 animate-spin" />
                    <PlusIcon v-else class="h-4 w-4" />
                  </button>
                </div>
                <div v-if="resvScanResult[prep.id]" class="text-sm px-3 py-2 rounded-lg"
                  :class="resvScanResult[prep.id].ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                  {{ resvScanResult[prep.id].message }}
                </div>
                <p v-if="prep.all_scanned" class="text-xs text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2 font-medium">
                  ✅ Tous les équipements sont prêts — vous pouvez valider la sortie.
                </p>
              </div>

              <!-- Valider la sortie -->
              <div class="flex items-center justify-between pt-3 border-t">
                <p v-if="!prep.all_scanned" class="text-xs text-gray-400">
                  Scannez tous les équipements avant de valider.
                </p>
                <span v-else class="text-xs text-green-600 font-medium">
                  {{ prep.scanned_count }} équipement{{ prep.scanned_count > 1 ? 's' : '' }} prêt{{ prep.scanned_count > 1 ? 's' : '' }}
                </span>
                <button @click="validateResvCheckout(prep.id)"
                  :disabled="validatingId === prep.id || !prep.all_scanned"
                  class="flex items-center gap-2 bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition disabled:opacity-50">
                  <ArrowPathIcon v-if="validatingId === prep.id" class="h-4 w-4 animate-spin" />
                  <ArrowRightIcon v-else class="h-4 w-4" />
                  Valider la sortie
                </button>
              </div>

            </div>
          </div>
        </div>
      </div>

      <!-- ══ ONGLET : EN COURS ════════════════════════════════════════════ -->
      <div v-if="tab === 'active'">

        <!-- État vide -->
        <div v-if="!preparations.length" class="text-center py-20 text-gray-400">
          <DocumentTextIcon class="h-12 w-12 mx-auto mb-3 opacity-20" />
          <p class="text-sm">Aucune préparation en cours.</p>
          <button @click="tab = 'create'"
            class="mt-3 inline-flex items-center gap-1 text-blue-500 hover:text-blue-700 text-sm">
            <PlusIcon class="h-4 w-4" /> Créer une première préparation
          </button>
        </div>

        <!-- Cartes de préparations -->
        <div v-else class="space-y-5">
          <div v-for="prep in preparations" :key="prep.id"
            class="bg-white rounded-xl border shadow-sm overflow-hidden">

            <!-- En-tête de la carte -->
            <div class="flex items-start justify-between gap-3 p-4 border-b bg-blue-50/50">
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-semibold text-gray-900 text-sm">{{ prep.title }}</span>
                  <span v-if="prep.reservation_id"
                    class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full">
                    Résa #{{ prep.reservation_id }}
                  </span>
                </div>
                <div class="flex items-center gap-3 mt-1 text-xs text-gray-500 flex-wrap">
                  <span class="flex items-center gap-1">
                    <UserIcon class="h-3.5 w-3.5" /> {{ prep.user?.name }}
                  </span>
                  <span class="flex items-center gap-1">
                    <CalendarIcon class="h-3.5 w-3.5" />
                    {{ prep.planned_start_label }} → {{ prep.planned_end_label }}
                  </span>
                  <span class="text-gray-400">Par {{ prep.prepared_by?.name }}</span>
                </div>
                <!-- Badge remise automatique -->
                <div v-if="prep.auto_checkout_label"
                  class="inline-flex items-center gap-1.5 mt-1.5 px-2.5 py-1 bg-amber-100 border border-amber-300 rounded-full text-xs font-semibold text-amber-800">
                  <BoltIcon class="h-3 w-3" />
                  Remise auto le {{ prep.auto_checkout_label }}
                </div>
                <p v-if="prep.notes" class="text-xs text-gray-500 italic mt-1">{{ prep.notes }}</p>
              </div>

              <!-- Actions de la carte -->
              <div class="flex items-center gap-2 shrink-0">
                <button @click="sendNotification(prep.id)" :disabled="notifyingId === prep.id"
                  title="Envoyer notification au client"
                  class="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition disabled:opacity-50">
                  <ArrowPathIcon v-if="notifyingId === prep.id" class="h-3.5 w-3.5 animate-spin" />
                  <EnvelopeIcon v-else class="h-3.5 w-3.5" />
                </button>
                <button @click="cancelPreparation(prep.id)" :disabled="cancellingId === prep.id"
                  title="Annuler la préparation"
                  class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition disabled:opacity-50">
                  <ArrowPathIcon v-if="cancellingId === prep.id" class="h-3.5 w-3.5 animate-spin text-red-500" />
                  <XMarkIcon v-else class="h-4 w-4" />
                </button>
              </div>
            </div>

            <!-- Corps de la carte -->
            <div class="p-4 space-y-3">

              <!-- Feedback notification -->
              <div v-if="notifyResult[prep.id]" class="text-sm px-3 py-2 rounded-lg"
                :class="notifyResult[prep.id].ok ? 'bg-blue-50 text-blue-700' : 'bg-red-50 text-red-700'">
                {{ notifyResult[prep.id].message }}
              </div>

              <!-- Liste des équipements -->
              <div v-if="prep.assets.length" class="space-y-2">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                  Équipements ({{ prep.assets.length }})
                </p>

                <!-- Groupes par kit -->
                <template v-for="group in groupedAssets(prep.assets).kitGroups" :key="group.kit.id">
                  <div class="border border-purple-200 rounded-lg overflow-hidden">
                    <!-- En-tête du kit -->
                    <div class="flex items-center gap-2 px-3 py-2 bg-purple-50 border-b border-purple-100">
                      <ArchiveBoxIcon class="h-4 w-4 text-purple-500 shrink-0" />
                      <span class="text-sm font-semibold text-purple-800 flex-1">{{ group.kit.name }}</span>
                      <span class="text-xs text-purple-400">{{ group.assets.length }} équip.</span>
                    </div>
                    <!-- Assets du kit -->
                    <div class="divide-y divide-gray-50">
                      <div v-for="asset in group.assets" :key="asset.id"
                        class="flex items-center gap-3 py-1.5 px-3 hover:bg-gray-50 group bg-white">
                        <div class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center shrink-0 overflow-hidden">
                          <img v-if="asset.image" :src="asset.image" class="w-full h-full object-cover" alt="" />
                          <QrCodeIcon v-else class="h-3.5 w-3.5 text-gray-400" />
                        </div>
                        <div class="min-w-0 flex-1">
                          <span class="text-sm font-medium text-gray-800">{{ asset.name }}</span>
                          <span class="text-xs text-gray-400 font-mono ml-2">{{ asset.asset_tag }}</span>
                          <span v-if="asset.category" class="text-xs text-gray-500 ml-1">· {{ asset.category }}</span>
                        </div>
                        <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full shrink-0">
                          En préparation
                        </span>
                        <button @click="removeAsset(prep.id, asset.id)"
                          class="opacity-0 group-hover:opacity-100 p-1 rounded text-gray-400 hover:text-red-500 transition">
                          <TrashIcon class="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </div>
                  </div>
                </template>

                <!-- Équipements individuels (sans kit) -->
                <template v-if="groupedAssets(prep.assets).individual.length">
                  <p v-if="groupedAssets(prep.assets).kitGroups.length"
                    class="text-xs font-semibold text-gray-400 uppercase tracking-wide pt-1">
                    Équipements individuels
                  </p>
                  <div class="space-y-1">
                    <div v-for="asset in groupedAssets(prep.assets).individual" :key="asset.id"
                      class="flex items-center gap-3 py-1.5 px-2 rounded-lg hover:bg-gray-50 group">
                      <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center shrink-0 overflow-hidden">
                        <img v-if="asset.image" :src="asset.image" class="w-full h-full object-cover" alt="" />
                        <QrCodeIcon v-else class="h-4 w-4 text-gray-400" />
                      </div>
                      <div class="min-w-0 flex-1">
                        <span class="text-sm font-medium text-gray-800">{{ asset.name }}</span>
                        <span class="text-xs text-gray-400 font-mono ml-2">{{ asset.asset_tag }}</span>
                        <span v-if="asset.category" class="text-xs text-gray-500 ml-1">· {{ asset.category }}</span>
                      </div>
                      <span class="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full shrink-0">
                        En préparation
                      </span>
                      <button @click="removeAsset(prep.id, asset.id)"
                        class="opacity-0 group-hover:opacity-100 p-1 rounded text-gray-400 hover:text-red-500 transition">
                        <TrashIcon class="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </div>
                </template>
              </div>

              <div v-else class="text-sm text-gray-400 text-center py-3 border-2 border-dashed rounded-lg">
                Aucun équipement — scannez ou ajoutez un kit ci-dessous
              </div>

              <!-- ── Zone de scan (équipement individuel) ── -->
              <div class="space-y-2">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Ajouter par scan</p>
                <div class="flex items-center gap-2">
                  <div class="flex-1 relative">
                    <QrCodeIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input
                      :ref="el => scanRefs[prep.id] = el"
                      v-model="scanInputs[prep.id]"
                      type="text"
                      placeholder="Scanner ou saisir un tag…"
                      class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                      @keydown.enter="handleScan(prep.id)"
                      :disabled="scanLoading[prep.id]"
                    />
                  </div>
                  <BarcodeScanButton
                    title="Scanner un équipement"
                    @scanned="(code) => { scanInputs[prep.id] = code; handleScan(prep.id) }"
                  />
                  <button @click="handleScan(prep.id)" :disabled="scanLoading[prep.id]"
                    class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm transition disabled:opacity-50">
                    <ArrowPathIcon v-if="scanLoading[prep.id]" class="h-4 w-4 animate-spin" />
                    <PlusIcon v-else class="h-4 w-4" />
                  </button>
                </div>

                <!-- Résultat scan -->
                <div v-if="scanResult[prep.id]" class="text-sm px-3 py-2 rounded-lg"
                  :class="scanResult[prep.id].ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                  {{ scanResult[prep.id].message }}
                </div>

              </div>

              <!-- ── Zone ajout de kit ── -->
              <div class="space-y-2">
                <button @click="toggleKitSearch(prep.id)"
                  class="flex items-center gap-1.5 text-xs font-semibold text-purple-700 hover:text-purple-900 transition">
                  <ArchiveBoxIcon class="h-3.5 w-3.5" />
                  {{ showKitSearch[prep.id] ? '▲ Masquer' : '▼ Ajouter un kit' }}
                </button>

                <div v-if="showKitSearch[prep.id]" class="border border-purple-100 rounded-lg p-3 bg-purple-50/40 space-y-2">
                  <div class="relative">
                    <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input v-model="kitSearch[prep.id]" type="text"
                      placeholder="Rechercher un kit par nom…"
                      class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 bg-white"
                      @input="onKitSearchInput(prep.id)"
                      @focus="() => { if (!kitResults[prep.id]) fetchKits(prep.id) }" />
                  </div>

                  <!-- Loader -->
                  <div v-if="kitLoading[prep.id]" class="text-xs text-gray-400 text-center py-2">
                    <ArrowPathIcon class="h-4 w-4 animate-spin mx-auto" />
                  </div>

                  <!-- Résultats -->
                  <div v-else-if="kitResults[prep.id]?.length" class="space-y-1">
                    <button v-for="kit in kitResults[prep.id]" :key="kit.id"
                      @click="addKit(prep.id, kit)"
                      :disabled="addingKitId[prep.id] === kit.id"
                      class="w-full flex items-center justify-between gap-2 px-3 py-2 bg-white border border-gray-200 rounded-lg hover:border-purple-300 hover:bg-purple-50 transition text-left disabled:opacity-50">
                      <div class="min-w-0">
                        <div class="flex items-center gap-2">
                          <ArchiveBoxIcon class="h-4 w-4 text-purple-500 shrink-0" />
                          <span class="font-medium text-gray-900 text-sm">{{ kit.name }}</span>
                          <span class="text-xs px-1.5 py-0.5 rounded-full"
                            :class="kit.status === 'available' ? 'bg-green-100 text-green-700' :
                                    kit.status === 'partial'   ? 'bg-yellow-100 text-yellow-700' :
                                                                 'bg-gray-100 text-gray-500'">
                            {{ kit.status === 'available' ? 'Complet' : kit.status === 'partial' ? 'Incomplet' : kit.status }}
                          </span>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5 pl-6 truncate">
                          {{ kit.assets.length }} équip. :
                          {{ kit.assets.map(a => a.asset_tag).join(', ') }}
                        </p>
                      </div>
                      <ArrowPathIcon v-if="addingKitId[prep.id] === kit.id" class="h-4 w-4 animate-spin text-purple-500 shrink-0" />
                      <PlusIcon v-else class="h-4 w-4 text-purple-400 shrink-0" />
                    </button>
                  </div>

                  <p v-else-if="kitResults[prep.id] && !kitResults[prep.id].length"
                    class="text-xs text-gray-400 text-center py-2">
                    Aucun kit réservable trouvé
                  </p>

                  <!-- Résultat ajout de kit -->
                  <div v-if="kitResult[prep.id]" class="text-sm px-3 py-2 rounded-lg"
                    :class="kitResult[prep.id].ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                    <p>{{ kitResult[prep.id].message }}</p>
                    <ul v-if="kitResult[prep.id].skipped?.length" class="mt-1 text-xs space-y-0.5 opacity-80">
                      <li v-for="s in kitResult[prep.id].skipped" :key="s.asset_tag">
                        ↳ {{ s.asset_tag }} {{ s.name }} — {{ s.reason }}
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Valider la sortie -->
              <div class="flex items-center justify-between pt-3 border-t">
                <p v-if="!prep.assets.length" class="text-xs text-gray-400">
                  Ajoutez au moins un équipement pour valider.
                </p>
                <span v-else class="text-xs text-gray-500">
                  {{ prep.assets.length }} équipement{{ prep.assets.length > 1 ? 's' : '' }} prêt{{ prep.assets.length > 1 ? 's' : '' }}
                </span>
                <button @click="validateCheckout(prep.id)"
                  :disabled="validatingId === prep.id || !prep.assets.length"
                  class="flex items-center gap-2 bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition disabled:opacity-50">
                  <ArrowPathIcon v-if="validatingId === prep.id" class="h-4 w-4 animate-spin" />
                  <ArrowRightIcon v-else class="h-4 w-4" />
                  Valider la sortie
                </button>
              </div>
            </div>

          </div>
        </div>
      </div>

    </div>
  </AppLayout>
</template>
