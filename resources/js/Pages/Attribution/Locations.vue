<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import { ref, computed, nextTick } from 'vue'
import axios from 'axios'
import {
  PlusIcon, TrashIcon, PencilIcon, XMarkIcon,
  MapPinIcon, ArrowUturnLeftIcon, ClockIcon,
  QrCodeIcon, MagnifyingGlassIcon, CheckCircleIcon,
  ExclamationTriangleIcon, BuildingStorefrontIcon,
  ArrowTopRightOnSquareIcon,
} from '@heroicons/vue/24/outline'

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps({
  places:      Array,
  assignments: Array,
  history:     Array,
})

// ── État ──────────────────────────────────────────────────────────────────────
const tab              = ref('active')   // 'active' | 'history'
const places           = ref([...props.places])
const assignments      = ref([...props.assignments])
const history          = ref([...props.history])

// ── Scan ──────────────────────────────────────────────────────────────────────
const scanInput        = ref('')
const scanMode         = ref('assign')  // 'assign' | 'return'
const scanLoading      = ref(false)
const scanResult       = ref(null)      // { type: 'success'|'error', message }
const scanInputRef     = ref(null)

// Résultat de validation d'asset tag
const validatedAsset   = ref(null)
const tagError         = ref('')

// ── Modal assignation ──────────────────────────────────────────────────────────
const showAssignModal  = ref(false)
const assignForm       = ref({ asset_tag: '', place_id: null, return_expected_at: '', notes: '', is_permanent: true })
const assignLoading    = ref(false)
const assignError      = ref('')
const assignAsset      = ref(null)   // données de l'asset validé pour la modal

// ── Modal retour ───────────────────────────────────────────────────────────────
const showReturnModal  = ref(false)
const returnAssignment = ref(null)
const returnNotes      = ref('')
const returnLoading    = ref(false)

// ── Lot de scan ───────────────────────────────────────────────────────────────
const batchQueue   = ref([])   // [{ id, asset_tag, name, category }]
const batchForm    = ref({ place_id: null, return_expected_at: '', notes: '', is_permanent: true })
const batchLoading = ref(false)
const batchError   = ref('')


// ── Computed ───────────────────────────────────────────────────────────────────
const nonStoragePlaces = computed(() => places.value.filter(p => !p.is_storage))

const assignmentsByPlace = computed(() => {
  const groups = {}
  for (const a of assignments.value) {
    const pid = a.place?.id ?? 0
    if (!groups[pid]) groups[pid] = { place: a.place, items: [] }
    groups[pid].items.push(a)
  }
  return Object.values(groups).sort((a, b) => a.place?.name?.localeCompare(b.place?.name ?? '') ?? 0)
})

const overdueCount = computed(() => assignments.value.filter(a => a.is_overdue).length)

// ─────────────────────────────────────────────────────────────────────────────
// SCAN
// ─────────────────────────────────────────────────────────────────────────────

function focusScan() {
  nextTick(() => scanInputRef.value?.focus())
}

async function handleScan() {
  const tag = scanInput.value.trim()
  if (!tag) return
  scanLoading.value = true
  scanResult.value = null

  try {
    if (scanMode.value === 'return') {
      // Retour par scan
      const { data } = await axios.post(route('asset-places.scan-return'), { asset_tag: tag })

      // Vérifier si l'équipement a des accessoires (velcro/belge)
      if (data.has_scratch || data.has_belge) {
        const accessories = []
        if (data.has_scratch) accessories.push('🟡 Scratch / Velcro')
        if (data.has_belge) accessories.push('🟣 Belge / Housse')

        const accessoriesText = accessories.join(' + ')
        const confirmMsg = `✓ ${data.asset_name} retourné depuis « ${data.place_name} »\n\n⚠️ RAPPEL ACCESSOIRES :\n${accessoriesText}\n\nVérifiez que les accessoires sont bien présents !`

        alert(confirmMsg)
      }

      // Retirer l'affectation de la liste active
      assignments.value = assignments.value.filter(a => a.asset?.asset_tag !== tag)
      scanResult.value = { type: 'success', message: data.message ?? `✓ ${data.asset_name} retourné depuis « ${data.place_name} »` }
    } else {
      // Mode lot : vérifier les doublons
      if (batchQueue.value.some(a => a.asset_tag === tag)) {
        scanResult.value = { type: 'warning', message: `${tag} est déjà dans la liste d'affectation` }
      } else {
        const { data } = await axios.post(route('asset-places.validate-tag'), { asset_tag: tag })

        if (data.active_assignment) {
          scanResult.value = {
            type: 'warning',
            message: `${data.name} est déjà affecté à « ${data.active_assignment.place?.name} »`,
            action: () => openReturnFromAssignment(data.active_assignment),
            actionLabel: 'Retourner',
          }
        } else if (data.active_reservation) {
          scanResult.value = { type: 'error', message: `${data.name} est en cours de réservation (#${data.active_reservation.id})` }
        } else {
          // Ajouter au lot
          if (!batchForm.value.place_id) {
            batchForm.value.place_id = nonStoragePlaces.value[0]?.id ?? null
          }
          batchQueue.value.push({ id: data.id, asset_tag: data.asset_tag, name: data.name, category: data.category })
          const n = batchQueue.value.length
          scanResult.value = { type: 'success', message: `✓ ${data.name} ajouté — ${n} équipement${n > 1 ? 's' : ''} en attente d'affectation` }
        }
      }
    }
  } catch (e) {
    scanResult.value = { type: 'error', message: e.response?.data?.error ?? 'Erreur de scan' }
  } finally {
    scanLoading.value = false
    scanInput.value = ''
    focusScan()
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// ASSIGNATION
// ─────────────────────────────────────────────────────────────────────────────

function openAssignModal(asset = null) {
  assignAsset.value = asset
  assignForm.value = {
    asset_tag: asset?.asset_tag ?? '',
    place_id: nonStoragePlaces.value[0]?.id ?? null,
    return_expected_at: '',
    notes: '',
    is_permanent: true,
  }
  assignError.value = ''
  showAssignModal.value = true
}

async function submitAssign() {
  if (!assignForm.value.asset_tag || !assignForm.value.place_id) return
  if (!assignForm.value.is_permanent && !assignForm.value.return_expected_at) {
    assignError.value = 'Précisez la date de retour prévue pour une affectation temporaire.'
    return
  }
  assignLoading.value = true
  assignError.value = ''
  try {
    const payload = {
      asset_tag:          assignForm.value.asset_tag,
      place_id:           assignForm.value.place_id,
      return_expected_at: assignForm.value.is_permanent ? null : assignForm.value.return_expected_at,
      notes:              assignForm.value.notes || null,
    }
    const { data } = await axios.post(route('asset-places.store'), payload)
    assignments.value.push(data)
    assignments.value.sort((a, b) => a.assigned_at?.localeCompare(b.assigned_at ?? '') ?? 0)
    showAssignModal.value = false
    scanResult.value = { type: 'success', message: `✓ ${data.asset?.name} affecté à « ${data.place?.name} »` }
  } catch (e) {
    assignError.value = e.response?.data?.error ?? 'Erreur lors de l\'affectation.'
  } finally {
    assignLoading.value = false
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// RETOUR
// ─────────────────────────────────────────────────────────────────────────────

function openReturnModal(assignment) {
  returnAssignment.value = assignment
  returnNotes.value = ''
  showReturnModal.value = true
}

function openReturnFromAssignment(assignment) {
  openReturnModal(assignment)
}

async function submitReturn() {
  if (!returnAssignment.value) return
  returnLoading.value = true
  try {
    await axios.patch(route('asset-places.return', returnAssignment.value.id), { notes: returnNotes.value || null })
    // Déplacer vers l'historique
    const a = { ...returnAssignment.value, returned_at: new Date().toLocaleString('fr-FR') }
    assignments.value = assignments.value.filter(x => x.id !== returnAssignment.value.id)
    history.value.unshift(a)
    showReturnModal.value = false
    scanResult.value = { type: 'success', message: `✓ ${a.asset?.name} retourné depuis « ${a.place?.name} »` }
  } catch (e) {
    alert(e.response?.data?.error ?? 'Erreur lors du retour.')
  } finally {
    returnLoading.value = false
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// LOT D'AFFECTATION
// ─────────────────────────────────────────────────────────────────────────────

function removeFromBatch(assetTag) {
  batchQueue.value = batchQueue.value.filter(a => a.asset_tag !== assetTag)
}

function clearBatch() {
  batchQueue.value = []
  batchError.value = ''
}

async function submitBatch() {
  if (!batchQueue.value.length || !batchForm.value.place_id) return
  if (!batchForm.value.is_permanent && !batchForm.value.return_expected_at) {
    batchError.value = 'Précisez la date de retour prévue pour une affectation temporaire.'
    return
  }
  batchLoading.value = true
  batchError.value = ''
  try {
    const { data } = await axios.post(route('asset-places.bulk-store'), {
      asset_tags:         batchQueue.value.map(a => a.asset_tag),
      place_id:           batchForm.value.place_id,
      return_expected_at: batchForm.value.is_permanent ? null : batchForm.value.return_expected_at,
      notes:              batchForm.value.notes || null,
    })

    assignments.value.push(...data.created)
    assignments.value.sort((a, b) => (a.assigned_at ?? '').localeCompare(b.assigned_at ?? ''))

    const n = data.created.length
    const placeName = data.created[0]?.place?.name ?? ''
    let msg = `✓ ${n} équipement${n > 1 ? 's affectés' : ' affecté'} à « ${placeName} »`
    if (data.skipped.length) {
      msg += ` · ${data.skipped.length} ignoré${data.skipped.length > 1 ? 's' : ''}`
    }
    scanResult.value = { type: n > 0 ? 'success' : 'warning', message: msg }

    batchQueue.value = []
    batchForm.value = { place_id: nonStoragePlaces.value[0]?.id ?? null, return_expected_at: '', notes: '', is_permanent: true }
  } catch (e) {
    batchError.value = e.response?.data?.error ?? e.response?.data?.message ?? 'Erreur lors de l\'affectation en lot.'
  } finally {
    batchLoading.value = false
  }
}

// ── Utils ──────────────────────────────────────────────────────────────────────
function isOverdue(a) { return a.is_overdue }
</script>

<template>
  <AppLayout title="Lieux">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

      <!-- ══ EN-TÊTE ════════════════════════════════════════════════════════ -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <MapPinIcon class="h-6 w-6 text-indigo-600" /> Affectations Lieux
          </h1>
          <p class="text-sm text-gray-500 mt-0.5">
            Affectez des équipements à des lieux de manière temporaire ou permanente.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <span v-if="overdueCount" class="flex items-center gap-1 text-sm font-medium text-red-600 bg-red-50 border border-red-200 px-3 py-1.5 rounded-lg">
            <ExclamationTriangleIcon class="h-4 w-4" /> {{ overdueCount }} en retard
          </span>
          <button @click="openAssignModal()"
            class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            <PlusIcon class="h-4 w-4" /> Affecter un équipement
          </button>
        </div>
      </div>

      <!-- ══ ZONE DE SCAN ════════════════════════════════════════════════════ -->
      <div class="bg-white rounded-2xl border shadow-sm p-4">
        <div class="flex items-center gap-3 flex-wrap">
          <!-- Mode -->
          <div class="flex rounded-lg border overflow-hidden text-sm font-medium shrink-0">
            <button @click="scanMode = 'assign'; focusScan()"
              :class="scanMode === 'assign' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
              class="px-3 py-2 transition">
              📍 Affecter
            </button>
            <button @click="scanMode = 'return'; focusScan()"
              :class="scanMode === 'return' ? 'bg-amber-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
              class="px-3 py-2 border-l transition">
              ↩ Retourner
            </button>
          </div>

          <!-- Champ scan -->
          <form @submit.prevent="handleScan" class="flex-1 flex items-center gap-2 min-w-48">
            <div class="flex-1 relative">
              <QrCodeIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none" />
              <input ref="scanInputRef" v-model="scanInput"
                :placeholder="scanMode === 'return' ? 'Scanner pour retourner…' : 'Scanner pour affecter…'"
                :class="scanMode === 'return' ? 'focus:ring-amber-400 border-amber-200' : 'focus:ring-indigo-400'"
                class="w-full border rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2"
                autofocus />
            </div>
            <button type="submit" :disabled="scanLoading"
              class="px-3 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded-lg text-sm font-medium transition disabled:opacity-50">
              {{ scanLoading ? '…' : 'OK' }}
            </button>
          </form>
        </div>

        <!-- Résultat scan -->
        <Transition enter-from-class="opacity-0 translate-y-1" enter-active-class="transition duration-200" leave-active-class="transition duration-150" leave-to-class="opacity-0">
          <div v-if="scanResult" class="mt-3 flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium"
            :class="{
              'bg-green-50 border border-green-200 text-green-800': scanResult.type === 'success',
              'bg-red-50 border border-red-200 text-red-800': scanResult.type === 'error',
              'bg-amber-50 border border-amber-200 text-amber-800': scanResult.type === 'warning',
            }">
            <CheckCircleIcon v-if="scanResult.type === 'success'" class="h-4 w-4 shrink-0" />
            <ExclamationTriangleIcon v-else class="h-4 w-4 shrink-0" />
            <span class="flex-1">{{ scanResult.message }}</span>
            <button v-if="scanResult.action" @click="scanResult.action()"
              class="text-xs underline font-semibold">{{ scanResult.actionLabel }}</button>
            <button @click="scanResult = null" class="ml-1 text-gray-400 hover:text-gray-600">
              <XMarkIcon class="h-4 w-4" />
            </button>
          </div>
        </Transition>
      </div>

      <!-- ══ LOT D'AFFECTATION ════════════════════════════════════════════════ -->
      <Transition
        enter-from-class="opacity-0 -translate-y-2" enter-active-class="transition duration-200"
        leave-to-class="opacity-0 -translate-y-2"   leave-active-class="transition duration-150">
        <div v-if="batchQueue.length && scanMode === 'assign'"
          class="bg-indigo-50 border border-indigo-200 rounded-2xl p-4 space-y-4">

          <!-- En-tête du lot -->
          <div class="flex items-center justify-between">
            <h3 class="font-semibold text-indigo-900 flex items-center gap-2 text-sm">
              <QrCodeIcon class="h-4 w-4 shrink-0" />
              Lot d'affectation
              <span class="bg-indigo-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                {{ batchQueue.length }}
              </span>
            </h3>
            <button @click="clearBatch"
              class="text-xs text-indigo-400 hover:text-red-500 flex items-center gap-1 transition">
              <XMarkIcon class="h-3.5 w-3.5" /> Vider la liste
            </button>
          </div>

          <!-- Liste des assets scannés -->
          <div class="space-y-1.5 max-h-48 overflow-y-auto">
            <div v-for="a in batchQueue" :key="a.asset_tag"
              class="flex items-center gap-3 bg-white border border-indigo-100 rounded-lg px-3 py-2">
              <CheckCircleIcon class="h-4 w-4 text-indigo-500 shrink-0" />
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">{{ a.name }}</p>
                <p class="text-xs text-gray-400 font-mono">{{ a.asset_tag }}{{ a.category ? ` · ${a.category}` : '' }}</p>
              </div>
              <button @click="removeFromBatch(a.asset_tag)"
                class="p-1 rounded hover:bg-red-50 text-gray-300 hover:text-red-400 transition shrink-0"
                title="Retirer">
                <XMarkIcon class="h-4 w-4" />
              </button>
            </div>
          </div>

          <!-- Formulaire commun -->
          <div class="bg-white rounded-xl border border-indigo-100 p-4 space-y-4">
            <!-- Lieu -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Lieu d'affectation *</label>
              <div v-if="!nonStoragePlaces.length" class="text-xs text-gray-400 italic">
                Aucun lieu configuré — <Link :href="route('places.index')" class="text-indigo-500 underline">en créer un</Link>
              </div>
              <div v-else class="flex flex-wrap gap-2">
                <button v-for="p in nonStoragePlaces" :key="p.id" type="button"
                  @click="batchForm.place_id = p.id"
                  class="flex items-center gap-2 px-3 py-2 rounded-xl border-2 transition text-sm"
                  :class="batchForm.place_id === p.id
                    ? 'border-indigo-500 bg-indigo-50 font-medium text-indigo-800'
                    : 'border-gray-200 hover:border-indigo-200 text-gray-700'">
                  <span class="w-3 h-3 rounded-full shrink-0" :style="{ background: p.color }" />
                  {{ p.name }}
                </button>
              </div>
            </div>

            <!-- Type d'affectation -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Type d'affectation</label>
              <div class="flex gap-3">
                <button type="button" @click="batchForm.is_permanent = true"
                  class="flex-1 py-2.5 rounded-xl border-2 text-sm font-medium transition"
                  :class="batchForm.is_permanent
                    ? 'border-purple-500 bg-purple-50 text-purple-800'
                    : 'border-gray-200 text-gray-600 hover:border-purple-200'">
                  🔒 Permanente
                </button>
                <button type="button" @click="batchForm.is_permanent = false"
                  class="flex-1 py-2.5 rounded-xl border-2 text-sm font-medium transition"
                  :class="!batchForm.is_permanent
                    ? 'border-amber-500 bg-amber-50 text-amber-800'
                    : 'border-gray-200 text-gray-600 hover:border-amber-200'">
                  ⏱ Temporaire
                </button>
              </div>
            </div>

            <!-- Date de retour (temporaire) -->
            <div v-if="!batchForm.is_permanent">
              <label class="block text-sm font-medium text-gray-700 mb-1">Date et heure de retour prévue *</label>
              <input v-model="batchForm.return_expected_at" type="datetime-local"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400" />
            </div>

            <!-- Notes communes -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Notes communes</label>
              <textarea v-model="batchForm.notes" rows="2"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                placeholder="Raison de l'affectation, instructions…" />
            </div>

            <p v-if="batchError" class="text-sm text-red-600">{{ batchError }}</p>

            <div class="flex gap-3 pt-1">
              <button @click="clearBatch"
                class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm hover:bg-gray-50 transition">
                Annuler
              </button>
              <button @click="submitBatch"
                :disabled="batchLoading || !batchForm.place_id"
                class="flex-1 bg-indigo-600 text-white py-2.5 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition disabled:opacity-50 flex items-center justify-center gap-2">
                <MapPinIcon v-if="!batchLoading" class="h-4 w-4 shrink-0" />
                {{ batchLoading
                  ? 'Affectation en cours…'
                  : `📍 Affecter ${batchQueue.length} équipement${batchQueue.length > 1 ? 's' : ''}` }}
              </button>
            </div>
          </div>
        </div>
      </Transition>

      <!-- ══ ONGLETS ═════════════════════════════════════════════════════════ -->
      <div class="flex border-b gap-6">
        <button @click="tab = 'active'"
          :class="tab === 'active' ? 'border-b-2 border-indigo-600 text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
          class="pb-3 text-sm transition flex items-center gap-1.5">
          📍 Affectations actives
          <span class="text-xs bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded-full font-medium">{{ assignments.length }}</span>
        </button>
        <button @click="tab = 'history'"
          :class="tab === 'history' ? 'border-b-2 border-indigo-600 text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
          class="pb-3 text-sm transition">
          🕓 Historique
        </button>
      </div>

      <!-- ══ AFFECTATIONS ACTIVES ════════════════════════════════════════════ -->
      <div v-if="tab === 'active'">
        <div v-if="!assignments.length" class="text-center py-16 text-gray-400">
          <MapPinIcon class="h-12 w-12 mx-auto mb-3 opacity-30" />
          <p class="text-sm">Aucun équipement affecté à un lieu pour le moment.</p>
          <button @click="openAssignModal()"
            class="mt-4 inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
            <PlusIcon class="h-4 w-4" /> Affecter un équipement
          </button>
        </div>

        <div v-else class="space-y-6">
          <div v-for="group in assignmentsByPlace" :key="group.place?.id">
            <!-- En-tête lieu -->
            <div class="flex items-center gap-2 mb-3">
              <span class="w-3 h-3 rounded-full shrink-0" :style="{ background: group.place?.color ?? '#6366f1' }" />
              <h2 class="font-semibold text-gray-800 text-sm">{{ group.place?.name }}</h2>
              <span class="text-xs text-gray-400">({{ group.items.length }} équipement{{ group.items.length > 1 ? 's' : '' }})</span>
            </div>

            <!-- Cartes équipements -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
              <div v-for="a in group.items" :key="a.id"
                class="bg-white rounded-xl border shadow-sm p-4 flex flex-col gap-2 relative"
                :class="a.is_overdue ? 'border-red-300 bg-red-50/30' : ''">

                <!-- Badge type affectation -->
                <div class="flex items-center justify-between">
                  <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                    :class="a.is_permanent
                      ? 'bg-purple-100 text-purple-700'
                      : a.is_overdue
                        ? 'bg-red-100 text-red-700'
                        : 'bg-amber-100 text-amber-700'">
                    {{ a.is_permanent ? '🔒 Permanent' : a.is_overdue ? '⚠️ En retard' : '⏱ Temporaire' }}
                  </span>
                  <button @click="openReturnModal(a)"
                    class="p-1.5 rounded-lg hover:bg-indigo-50 hover:text-indigo-600 text-gray-400 transition" title="Retourner">
                    <ArrowUturnLeftIcon class="h-4 w-4" />
                  </button>
                </div>

                <!-- Équipement -->
                <div>
                  <p class="font-semibold text-gray-900 text-sm truncate">{{ a.asset?.name }}</p>
                  <p class="text-xs text-gray-400 font-mono">{{ a.asset?.asset_tag }}</p>
                  <p v-if="a.asset?.category" class="text-xs text-gray-500 mt-0.5">{{ a.asset.category }}</p>
                </div>

                <!-- Dates -->
                <div class="text-xs text-gray-500 space-y-0.5">
                  <div class="flex items-center gap-1">
                    <ClockIcon class="h-3.5 w-3.5 shrink-0" />
                    <span>Depuis le {{ a.assigned_at }}</span>
                  </div>
                  <div v-if="!a.is_permanent" class="flex items-center gap-1"
                    :class="a.is_overdue ? 'text-red-600 font-medium' : ''">
                    <ArrowUturnLeftIcon class="h-3.5 w-3.5 shrink-0" />
                    <span>Retour prévu le {{ a.return_expected_at_label }}</span>
                  </div>
                </div>

                <!-- Notes -->
                <p v-if="a.notes" class="text-xs text-gray-500 italic border-t pt-2">{{ a.notes }}</p>

                <!-- Affecté par -->
                <p class="text-xs text-gray-400">
                  Par {{ a.assigned_by?.name ?? '—' }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ HISTORIQUE ══════════════════════════════════════════════════════ -->
      <div v-if="tab === 'history'">
        <div v-if="!history.length" class="text-center py-16 text-gray-400">
          <p class="text-sm">Aucun historique d'affectation.</p>
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                <th class="pb-2 pr-4">Équipement</th>
                <th class="pb-2 pr-4">Lieu</th>
                <th class="pb-2 pr-4">Type</th>
                <th class="pb-2 pr-4">Affecté le</th>
                <th class="pb-2 pr-4">Retour prévu</th>
                <th class="pb-2 pr-4">Retourné le</th>
                <th class="pb-2">Par</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="a in history" :key="a.id" class="hover:bg-gray-50 transition">
                <td class="py-2.5 pr-4">
                  <div class="font-medium text-gray-800">{{ a.asset?.name }}</div>
                  <div class="text-xs text-gray-400 font-mono">{{ a.asset?.asset_tag }}</div>
                </td>
                <td class="py-2.5 pr-4">
                  <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: a.place?.color ?? '#6366f1' }" />
                    {{ a.place?.name }}
                  </div>
                </td>
                <td class="py-2.5 pr-4">
                  <span class="text-xs px-2 py-0.5 rounded-full"
                    :class="a.is_permanent ? 'bg-purple-100 text-purple-700' : 'bg-amber-100 text-amber-700'">
                    {{ a.is_permanent ? 'Permanent' : 'Temporaire' }}
                  </span>
                </td>
                <td class="py-2.5 pr-4 text-gray-600">{{ a.assigned_at }}</td>
                <td class="py-2.5 pr-4 text-gray-600">{{ a.return_expected_at_label ?? '—' }}</td>
                <td class="py-2.5 pr-4 text-green-700 font-medium">{{ a.returned_at }}</td>
                <td class="py-2.5 text-gray-500">{{ a.assigned_by?.name ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- ════ MODAL ASSIGNATION ════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showAssignModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="showAssignModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="font-semibold text-gray-900 flex items-center gap-2">
              <MapPinIcon class="h-5 w-5 text-indigo-600" /> Affecter un équipement
            </h3>
            <button @click="showAssignModal = false"><XMarkIcon class="h-5 w-5 text-gray-400 hover:text-gray-600" /></button>
          </div>

          <form @submit.prevent="submitAssign" class="p-6 space-y-4">
            <!-- Asset info (si venu du scan) -->
            <div v-if="assignAsset" class="flex items-center gap-3 px-3 py-2.5 bg-indigo-50 border border-indigo-200 rounded-xl text-sm">
              <CheckCircleIcon class="h-5 w-5 text-indigo-500 shrink-0" />
              <div>
                <p class="font-semibold text-indigo-800">{{ assignAsset.name }}</p>
                <p class="text-xs text-indigo-500 font-mono">{{ assignAsset.asset_tag }} · {{ assignAsset.category }}</p>
              </div>
            </div>

            <!-- Code barre (si modal ouverte manuellement) -->
            <div v-if="!assignAsset">
              <label class="block text-sm font-medium text-gray-700 mb-1">Code barre / tag équipement *</label>
              <input v-model="assignForm.asset_tag" required autofocus
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                placeholder="Scannez ou entrez le tag…" />
            </div>

            <!-- Lieu -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Lieu d'affectation *</label>
              <div v-if="!nonStoragePlaces.length" class="text-xs text-gray-400 italic">
                Aucun lieu d'affectation configuré —
                <Link :href="route('places.index')" class="text-indigo-500 hover:underline">en créer un</Link>
              </div>
              <div v-else class="grid grid-cols-2 gap-2">
                <button v-for="p in nonStoragePlaces" :key="p.id" type="button"
                  @click="assignForm.place_id = p.id"
                  class="flex items-center gap-2 px-3 py-2.5 rounded-xl border-2 transition text-sm text-left"
                  :class="assignForm.place_id === p.id
                    ? 'border-indigo-500 bg-indigo-50 font-medium text-indigo-800'
                    : 'border-gray-200 hover:border-indigo-200 text-gray-700'">
                  <span class="w-3 h-3 rounded-full shrink-0" :style="{ background: p.color }" />
                  <span class="truncate">{{ p.name }}</span>
                </button>
              </div>
            </div>

            <!-- Type affectation -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Type d'affectation</label>
              <div class="flex gap-3">
                <button type="button" @click="assignForm.is_permanent = true"
                  class="flex-1 py-2.5 rounded-xl border-2 text-sm font-medium transition"
                  :class="assignForm.is_permanent ? 'border-purple-500 bg-purple-50 text-purple-800' : 'border-gray-200 text-gray-600 hover:border-purple-200'">
                  🔒 Permanente
                </button>
                <button type="button" @click="assignForm.is_permanent = false"
                  class="flex-1 py-2.5 rounded-xl border-2 text-sm font-medium transition"
                  :class="!assignForm.is_permanent ? 'border-amber-500 bg-amber-50 text-amber-800' : 'border-gray-200 text-gray-600 hover:border-amber-200'">
                  ⏱ Temporaire
                </button>
              </div>
              <p v-if="assignForm.is_permanent" class="text-xs text-gray-400 mt-1.5 italic">
                L'équipement reste affecté jusqu'au retour manuel. Il ne sera pas réservable.
              </p>
            </div>

            <!-- Date de retour (temporaire seulement) -->
            <div v-if="!assignForm.is_permanent">
              <label class="block text-sm font-medium text-gray-700 mb-1">Date et heure de retour prévue *</label>
              <input v-model="assignForm.return_expected_at" type="datetime-local" required
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400" />
            </div>

            <!-- Notes -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
              <textarea v-model="assignForm.notes" rows="2"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                placeholder="Raison de l'affectation, instructions…" />
            </div>

            <p v-if="assignError" class="text-sm text-red-600">{{ assignError }}</p>

            <div class="flex gap-3 pt-1">
              <button type="button" @click="showAssignModal = false"
                class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50 transition">Annuler</button>
              <button type="submit" :disabled="assignLoading || !assignForm.place_id"
                class="flex-1 bg-indigo-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition disabled:opacity-50">
                {{ assignLoading ? 'Affectation…' : '📍 Affecter' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ════ MODAL RETOUR ═════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showReturnModal && returnAssignment"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="showReturnModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="font-semibold text-gray-900 flex items-center gap-2">
              <ArrowUturnLeftIcon class="h-5 w-5 text-amber-500" /> Retour de l'équipement
            </h3>
            <button @click="showReturnModal = false"><XMarkIcon class="h-5 w-5 text-gray-400 hover:text-gray-600" /></button>
          </div>

          <div class="p-6 space-y-4">
            <!-- Infos équipement -->
            <div class="bg-gray-50 border rounded-xl px-4 py-3 text-sm space-y-1">
              <p class="font-semibold text-gray-800">{{ returnAssignment.asset?.name }}</p>
              <p class="text-xs text-gray-400 font-mono">{{ returnAssignment.asset?.asset_tag }}</p>
              <div class="flex items-center gap-1.5 mt-1">
                <span class="w-2.5 h-2.5 rounded-full" :style="{ background: returnAssignment.place?.color }" />
                <span class="text-xs text-gray-600">{{ returnAssignment.place?.name }}</span>
                <span class="text-xs text-gray-400 ml-auto">Depuis le {{ returnAssignment.assigned_at }}</span>
              </div>
            </div>

            <!-- Notes -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Note de retour (optionnel)</label>
              <textarea v-model="returnNotes" rows="2"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400"
                placeholder="État au retour, commentaires…" />
            </div>

            <div class="flex gap-3">
              <button @click="showReturnModal = false"
                class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50 transition">Annuler</button>
              <button @click="submitReturn" :disabled="returnLoading"
                class="flex-1 bg-amber-500 hover:bg-amber-600 text-white py-2 rounded-lg text-sm font-semibold transition disabled:opacity-50">
                {{ returnLoading ? 'Retour…' : '↩ Confirmer le retour' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  </AppLayout>
</template>
