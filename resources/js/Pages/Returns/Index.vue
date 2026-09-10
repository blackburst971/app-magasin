<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, onUnmounted, watch, computed, nextTick } from 'vue'
import axios from 'axios'
import { router, useForm } from '@inertiajs/vue3'
import { useSound } from '@/composables/useSound'
import {
  ExclamationTriangleIcon, CheckCircleIcon, QrCodeIcon,
  UserIcon, CalendarIcon, BellIcon, ClockIcon, ArchiveBoxIcon,
  WrenchScrewdriverIcon, EyeIcon, EyeSlashIcon,
  Squares2X2Icon, ListBulletIcon, MapPinIcon, ChevronDownIcon,
  LockClosedIcon, LockOpenIcon,
} from '@heroicons/vue/24/outline'
import { CheckCircleIcon as CheckCircleSolid } from '@heroicons/vue/24/solid'
import BarcodeScanButton from '@/Components/BarcodeScanButton.vue'

const { playSound } = useSound()

const props = defineProps({
  overdueReservations:        Array,
  activeReservations:         Array,
  kitsAwaitingRegularization: Array,
  users:                      Array,
  placeAssignments:           { type: Array, default: () => [] },
  storagePlaces:              { type: Array, default: () => [] },
})

// ─── Retour manuel avec modal état ────────────────────────────────────────────
const manualReturning       = ref(null)
const showManualReturnModal = ref(false)
const manualReturnTarget    = ref(null)   // { reservationId, assetId, assetName, assetTag, hasScratch, hasBelge }
const manualReturnCondition = ref('good')
const manualReturnNotes     = ref('')
const manualScratchPresent  = ref(null)   // null=non répondu, true=présent, false=manquant
const manualBelgePresent    = ref(null)
const manualLostConfirm     = ref(false)  // vrai quand l'admin confirme "déclarer comme perdu"

function findAssetInfo(assetTag) {
  const allRes = [...props.overdueReservations, ...props.activeReservations]
  for (const res of allRes) {
    for (const a of res.assets) {
      if (a.asset_tag === assetTag && !a.returned_at && !a.is_missing) {
        return { hasScratch: !!a.has_scratch, hasBelge: !!a.has_belge }
      }
    }
  }
  return { hasScratch: false, hasBelge: false }
}

function openManualReturnModal(reservationId, assetId, assetName, assetTag) {
  const info = findAssetInfo(assetTag)
  manualReturnTarget.value    = { reservationId, assetId, assetName, assetTag, ...info }
  manualReturnCondition.value = 'good'
  manualReturnNotes.value     = ''
  manualScratchPresent.value  = info.hasScratch ? null : true
  manualBelgePresent.value    = info.hasBelge   ? null : true
  manualLostConfirm.value     = false
  showManualReturnModal.value = true
}

const manualAccessoriesAnyMissing = computed(() =>
  (manualReturnTarget.value?.hasScratch && manualScratchPresent.value === false) ||
  (manualReturnTarget.value?.hasBelge   && manualBelgePresent.value  === false)
)

const manualAccessoriesReady = computed(() => {
  if (!manualReturnTarget.value) return false
  const scratchOk = !manualReturnTarget.value.hasScratch || manualScratchPresent.value !== null
  const belgeOk   = !manualReturnTarget.value.hasBelge   || manualBelgePresent.value  !== null
  if (!scratchOk || !belgeOk) return false
  // Manquant sans confirmation "perdu" → bloqué
  if (manualAccessoriesAnyMissing.value && !manualLostConfirm.value) return false
  return true
})

function submitManualReturn() {
  if (!manualReturnTarget.value || !manualAccessoriesReady.value) return
  const { reservationId, assetId } = manualReturnTarget.value
  const key = `${reservationId}-${assetId}`
  const scratchLost = manualReturnTarget.value.hasScratch && manualScratchPresent.value === false
  const belgeLost   = manualReturnTarget.value.hasBelge   && manualBelgePresent.value  === false
  manualReturning.value      = key
  showManualReturnModal.value = false
  router.post(
    route('reservations.return-asset', [reservationId, assetId]),
    {
      condition:    manualReturnCondition.value,
      return_notes: manualReturnNotes.value || null,
      scratch_lost: scratchLost || undefined,
      belge_lost:   belgeLost   || undefined,
    },
    {
      onSuccess: () => {
        recentReturns.value.unshift({
          reservationId,
          assetId,
          assetName:  manualReturnTarget.value?.assetName ?? '',
          returnedAt: new Date(),
          undoLoading: false,
        })
        manualReturning.value = null
      },
      onError: () => { manualReturning.value = null },
    }
  )
}

// ─── Annulation retour (délai 5 min) ─────────────────────────────────────────
const recentReturns = ref([]) // [{ reservationId, assetId, assetName, returnedAt, undoLoading }]

const activeUndos = computed(() => {
  ticker.value // dépend du ticker pour rester réactif
  const limit = 5 * 60 * 1000
  return recentReturns.value.filter(r => r.reservationId && r.assetId && (Date.now() - r.returnedAt.getTime()) < limit)
})

function remainingUndoSeconds(entry) {
  ticker.value
  return Math.max(0, Math.ceil((5 * 60 * 1000 - (Date.now() - entry.returnedAt.getTime())) / 1000))
}

async function undoReturn(entry) {
  entry.undoLoading = true
  try {
    await axios.post(`/returns/${entry.reservationId}/${entry.assetId}/undo`)
    recentReturns.value = recentReturns.value.filter(
      r => !(r.reservationId === entry.reservationId && r.assetId === entry.assetId)
    )
    playSound('scan_success')
    scanResult.value = {
      type:    'warning',
      message: `↩ Retour annulé pour « ${entry.assetName} »`,
    }
    router.reload({
      only:      ['overdueReservations', 'activeReservations', 'kitsAwaitingRegularization', 'placeAssignments'],
      onSuccess: () => { focusPreferred() },
    })
    setTimeout(() => { scanResult.value = null }, 5000)
  } catch (err) {
    entry.undoLoading = false
    scanResult.value = {
      type:    'error',
      message: err.response?.data?.error ?? "Impossible d'annuler ce retour",
    }
  }
}

// ─── Célébration retour complet ───────────────────────────────────────────────
const completionFlash = ref(null)   // { title, isKit } | null
let completionTimer   = null

function showCompletionFlash(title, isKit) {
  if (completionTimer) clearTimeout(completionTimer)
  completionFlash.value = { title, isKit }
  completionTimer = setTimeout(() => { completionFlash.value = null }, 5500)
}

// ─── Panneau scan USB ─────────────────────────────────────────────────────────
const scanInputEl    = ref(null)
const scanTag        = ref('')
const scanCondition  = ref('good')   // 'good' | 'damaged' | 'lost' | 'pending'
const scanNotes      = ref('')
const scanReturnedBy = ref('')       // ID utilisateur qui rapporte physiquement le matériel
const scanResult     = ref(null)
const scanning       = ref(false)

// ─── Modal pré-retour : vérification scratch / belge ─────────────────────────
const accessoriesModal = ref({
  open:            false,
  pendingTag:      '',
  hasScratch:      false,
  hasBelge:        false,
  scratchPresent:  null,  // null=non répondu, true=présent, false=manquant
  belgePresent:    null,
  confirmingLost:  false, // mode "déclarer comme perdu"
})

function findAssetAccessories(tag) {
  const allRes = [...props.overdueReservations, ...props.activeReservations]
  for (const res of allRes) {
    for (const a of res.assets) {
      if (a.asset_tag === tag && !a.returned_at && !a.is_missing) {
        if (a.has_scratch || a.has_belge) {
          return { hasScratch: !!a.has_scratch, hasBelge: !!a.has_belge }
        }
      }
    }
  }
  return null
}

const accessoriesAllAnswered = computed(() => {
  const m = accessoriesModal.value
  const scratchOk = !m.hasScratch || m.scratchPresent !== null
  const belgeOk   = !m.hasBelge   || m.belgePresent   !== null
  return scratchOk && belgeOk
})

const accessoriesAnyMissing = computed(() =>
  (accessoriesModal.value.hasScratch && accessoriesModal.value.scratchPresent === false) ||
  (accessoriesModal.value.hasBelge   && accessoriesModal.value.belgePresent   === false)
)

const accessoriesCanProceed = computed(() =>
  accessoriesAllAnswered.value && (!accessoriesAnyMissing.value || accessoriesModal.value.confirmingLost)
)

function cancelAccessoriesCheck() {
  accessoriesModal.value.open = false
  scanResult.value = {
    type:    'warning',
    message: `⚠️ Retour bloqué — accessoire(s) manquant(s). Résolvez le problème et rescannez l'équipement.`,
  }
  playSound('scan_error')
  setTimeout(() => { scanResult.value = null; focusPreferred() }, 7000)
}

// ─── Modal sélection lieu de stockage ─────────────────────────────────────────
const storagePlaceModal = ref({
  open:       false,
  asset:      null,
  fromPlace:  null,
  assetTag:   '',
  selectedId: null,
})

function openStoragePlaceModal(asset, fromPlace, assetTag) {
  // Trouver le lieu "magasin 211" par défaut
  const defaultPlace = props.storagePlaces.find(p => p.name.toLowerCase().includes('magasin 211'))
  storagePlaceModal.value = {
    open:       true,
    asset,
    fromPlace,
    assetTag,
    selectedId: defaultPlace?.id || (props.storagePlaces[0]?.id ?? null),
  }
}

async function confirmStoragePlace() {
  if (!storagePlaceModal.value.selectedId) return

  const tag = storagePlaceModal.value.assetTag
  const placeId = storagePlaceModal.value.selectedId

  storagePlaceModal.value.open = false
  scanning.value = true

  try {
    const { data } = await axios.post(route('returns.scan'), {
      asset_tag:         tag,
      storage_place_id:  placeId,
      condition:         'good',
    })

    playSound('scan_success')

    const storagePlace = props.storagePlaces.find(p => p.id === placeId)
    scanResult.value = {
      type:    'success',
      message: `✅ ${data.asset.name} retourné depuis « ${data.from_place.name} » → stocké au « ${storagePlace?.name || data.storage_place?.name || 'lieu de stockage'} »`,
    }

    router.reload({
      only:      ['overdueReservations', 'activeReservations', 'kitsAwaitingRegularization', 'placeAssignments'],
      onSuccess: () => { focusPreferred() },
    })

    setTimeout(() => { scanResult.value = null }, 8000)
  } catch (err) {
    playSound('scan_error')
    scanResult.value = {
      type:    'error',
      message: err.response?.data?.error ?? 'Erreur lors de la sélection du lieu',
    }
  } finally {
    scanning.value = false
    setTimeout(() => { focusPreferred() }, 100)
  }
}

function cancelStoragePlace() {
  storagePlaceModal.value.open = false
  scanResult.value = {
    type:    'warning',
    message: `⚠️ Retour annulé — veuillez choisir un lieu de stockage`,
  }
  playSound('scan_error')
  setTimeout(() => { scanResult.value = null; focusPreferred() }, 5000)
}

function proceedWithAccessories() {
  const scratchLost = accessoriesModal.value.hasScratch && accessoriesModal.value.scratchPresent === false
  const belgeLost   = accessoriesModal.value.hasBelge   && accessoriesModal.value.belgePresent   === false
  const tag         = accessoriesModal.value.pendingTag
  accessoriesModal.value.open = false
  doScanReturn(tag, { scratchLost, belgeLost })
}

// ─── Pop-up vérification des composants ──────────────────────────────────────
const compModal = ref({
    open:          false,
    components:    [],   // liste complète des composants à vérifier
    currentIndex:  0,    // index du composant actuellement affiché
    reservationId: null,
    assetId:       null,
    assetTag:      '',
    assetName:     '',
    missingIds:    [],   // IDs des composants signalés manquants
})

// Composant courant affiché dans le popup
const currentComp = computed(() =>
    compModal.value.components[compModal.value.currentIndex] ?? null
)

// Ouvre le popup composants après un retour réussi
function openComponentModal(data) {
    compModal.value = {
        open:          true,
        components:    data.components,
        currentIndex:  0,
        reservationId: data.reservation.id,
        assetId:       data.asset.id,
        assetTag:      data.asset.asset_tag,
        assetName:     data.asset.name,
        missingIds:    [],
    }
}

// Valider le composant courant comme présent (appelé par re-scan du code barre)
function confirmComponent() {
    advanceComponent(false)
}

// Marquer le composant courant comme manquant
function markComponentMissing() {
    compModal.value.missingIds.push(currentComp.value.id)
    advanceComponent(true)
}

// Passer au composant suivant ou clore le popup si tous vérifiés
async function advanceComponent(wasMissing) {
    const next = compModal.value.currentIndex + 1
    if (next < compModal.value.components.length) {
        compModal.value.currentIndex = next
    } else {
        // Tous les composants ont été vérifiés → enregistrer + fermer
        await finishComponentCheck()
    }
}

async function finishComponentCheck() {
    const missingCount    = compModal.value.missingIds.length
    const total           = compModal.value.components.length
    const assetName       = compModal.value.assetName
    const missingIds      = [...compModal.value.missingIds]
    const components      = [...compModal.value.components]

    // Fermer la modale immédiatement
    compModal.value.open = false

    // Enregistrer le résultat côté serveur (le serveur clos la réservation si tous les assets sont rendus)
    let allReturned    = false
    let resTitle       = ''
    let isKit          = false
    try {
        const { data } = await axios.post(route('returns.components-check'), {
            reservation_id:        compModal.value.reservationId,
            asset_id:              compModal.value.assetId,
            missing_component_ids: missingIds,
        })
        allReturned = data.all_returned === true
        resTitle    = data.reservation_title ?? ''
        isKit       = data.is_kit ?? false
    } catch (e) { /* non bloquant */ }

    // Recharger la liste maintenant que la vérification est terminée
    router.reload({
        only:      ['overdueReservations', 'activeReservations', 'kitsAwaitingRegularization', 'placeAssignments'],
        onSuccess: () => { focusPreferred() },
    })

    // Célébration si la réservation est maintenant complète
    if (allReturned) {
        playSound('reservation_complete')
        showCompletionFlash(resTitle, isKit)
    }

    // Afficher un résumé dans le scanResult
    if (missingCount > 0) {
        playSound('scan_error')
        const names = components
            .filter(c => missingIds.includes(c.id))
            .map(c => c.name).join(', ')
        scanResult.value = {
            type:    'warning',
            message: `⚠️ ${assetName} — ${missingCount} composant(s) manquant(s) : ${names}`,
        }
    } else {
        scanResult.value = {
            type:    'success',
            message: `✅ ${assetName} — tous les composants (${total}) sont présents.`,
        }
    }
    setTimeout(() => { scanResult.value = null; focusPreferred() }, 7000)
}

// ─── Pop-up vérification retour ───────────────────────────────────────────────
const checkNoteModal    = ref(false)
const checkNoteData     = ref(null)   // { assetTag, assetName, note }
let   pendingScanTag    = ''          // tag en attente de soumission après confirmation

/** Cherche return_check_note dans les listes pour un asset_tag donné */
function findReturnCheckNote(tag) {
  const allReservations = [...props.overdueReservations, ...props.activeReservations]
  for (const res of allReservations) {
    for (const a of res.assets) {
      if (a.asset_tag === tag && a.return_check_note && !a.returned_at && !a.is_missing) {
        return { assetName: a.name, note: a.return_check_note }
      }
    }
  }
  return null
}

function confirmCheckNote() {
  checkNoteModal.value = false
  const tag  = pendingScanTag
  pendingScanTag = ''
  // Après la note de vérification, vérifier aussi les accessoires
  const acc = findAssetAccessories(tag)
  if (acc) {
    accessoriesModal.value = {
      open: true, pendingTag: tag,
      hasScratch: acc.hasScratch, hasBelge: acc.hasBelge,
      scratchPresent: acc.hasScratch ? null : true,
      belgePresent:   acc.hasBelge   ? null : true,
      confirmingLost: false,
    }
    return
  }
  doScanReturn(tag)
}

function cancelCheckNote() {
  checkNoteModal.value = false
  pendingScanTag = ''
  scanResult.value = {
    type:    'warning',
    message: `⚠️ Retour annulé — anomalie signalée pour ${checkNoteData.value?.assetName ?? ''}. Vérifiez l'état de l'équipement.`,
  }
  playSound('scan_error')
  setTimeout(() => { scanResult.value = null; focusPreferred() }, 6000)
}

// Auto-focus + scan USB debounce + countdown ticker
onMounted(() => {
  focusPreferred()
  countdownInterval = setInterval(() => { ticker.value++ }, 1000)
})

let scanDebounceTimer = null
watch(scanTag, (val) => {
  clearTimeout(scanDebounceTimer)
  if (!val.trim() || scanning.value) return
  scanDebounceTimer = setTimeout(() => {
    if (scanTag.value.trim() && !scanning.value) scanReturn()
  }, 200)
})

function scanReturn() {
  if (scanning.value) return
  const tag = scanTag.value.trim()
  if (!tag) return

  // Vider le champ immédiatement pour éviter les doubles scans USB
  scanTag.value = ''
  scanResult.value = null

  // Si le popup accessoires est ouvert : re-scan du même équipement confirme la présence des accessoires non encore répondus
  if (accessoriesModal.value.open) {
    if (tag === accessoriesModal.value.pendingTag) {
      // Valider comme "présent" tous les accessoires sans réponse
      if (accessoriesModal.value.hasScratch && accessoriesModal.value.scratchPresent === null) {
        accessoriesModal.value.scratchPresent = true
        accessoriesModal.value.confirmingLost = false
      }
      if (accessoriesModal.value.hasBelge && accessoriesModal.value.belgePresent === null) {
        accessoriesModal.value.belgePresent = true
        accessoriesModal.value.confirmingLost = false
      }
      // Si tout est confirmé présent, valider directement
      if (accessoriesCanProceed.value) {
        proceedWithAccessories()
      }
    }
    setTimeout(() => { focusPreferred() }, 100)
    return
  }

  // Si le popup composants est ouvert : le re-scan du même code barre valide le composant courant
  if (compModal.value.open) {
    if (tag === compModal.value.assetTag) {
      confirmComponent()
    }
    setTimeout(() => { focusPreferred() }, 100)
    return
  }

  // Vérifier si l'asset a une note de vérification
  const found = findReturnCheckNote(tag)
  if (found) {
    pendingScanTag      = tag
    checkNoteData.value = { assetName: found.assetName, note: found.note }
    checkNoteModal.value = true
    return
  }

  // Vérifier les accessoires (scratch/belge) avant le retour
  const acc = findAssetAccessories(tag)
  if (acc) {
    accessoriesModal.value = {
      open: true, pendingTag: tag,
      hasScratch: acc.hasScratch, hasBelge: acc.hasBelge,
      scratchPresent: acc.hasScratch ? null : true,
      belgePresent:   acc.hasBelge   ? null : true,
      confirmingLost: false,
    }
    return
  }

  doScanReturn(tag)
}

async function doScanReturn(tag, opts = {}) {
  const { scratchLost = false, belgeLost = false } = opts
  scanning.value = true

  try {
    const payload = {
      asset_tag:           tag,
      condition:           scanCondition.value,
      return_notes:        scanNotes.value || null,
      returned_by_user_id: scanReturnedBy.value || null,
    }
    if (scratchLost) payload.scratch_lost = true
    if (belgeLost)   payload.belge_lost   = true

    const { data } = await axios.post(route('returns.scan'), payload)

    // Si retour d'affectation de lieu → demander le lieu de stockage
    if (data.needs_storage_place) {
      playSound('scan_success')
      openStoragePlaceModal(data.asset, data.from_place, tag)
      scanning.value = false
      return
    }

    const isComplete    = data.reservation?.all_returned === true
    const hasComponents = data.components && data.components.length > 0
    const isPendingMode = data.condition === 'pending'

    scanNotes.value     = ''
    scanCondition.value = 'good'

    // Mémoriser ce retour pour l'annulation dans la fenêtre de 5 min
    if (!isPendingMode && data.reservation?.id && data.asset?.id) {
      recentReturns.value.unshift({
        reservationId: data.reservation.id,
        assetId:       data.asset.id,
        assetName:     data.asset.name,
        returnedAt:    new Date(),
        undoLoading:   false,
      })
      // Nettoyer les anciens (> 10 min) pour ne pas accumuler
      recentReturns.value = recentReturns.value.filter(r => (Date.now() - r.returnedAt.getTime()) < 10 * 60 * 1000)
    }

    // Composants → popup vérification (pas de belge post-retour maintenant, c'était pré-retour)
    if (hasComponents && !isPendingMode) {
      playSound('scan_success')
      openComponentModal(data)
      return
    }

    if (isComplete) {
      playSound('reservation_complete')
      showCompletionFlash(data.reservation.title, data.reservation.is_kit)
    } else {
      playSound('scan_success')
    }

    const conditionEmoji  = { good: '✅', damaged: '⚠️', lost: '❌', pending: '🔍' }[data.condition ?? 'good'] ?? '✅'
    const reservationInfo = data.reservation
      ? ` — « ${data.reservation.title} »${isComplete ? ' ✅ Complet !' : ''}${data.reservation.is_kit ? ' 📦 KIT' : ''}`
      : ''
    const actionLabel = isPendingMode ? 'mis en attente de vérification' : 'retourné'
    let lostBadge = ''
    if (scratchLost) lostBadge += ' · 🪢 velcro noté manquant'
    if (belgeLost)   lostBadge += ' · 👜 belge noté manquant'

    scanResult.value = {
      type:    lostBadge ? 'warning' : 'success',
      message: `${conditionEmoji} ${data.asset.name} (${data.asset.asset_tag}) ${actionLabel}${lostBadge}${reservationInfo}`,
    }

    router.reload({
      only:      ['overdueReservations', 'activeReservations', 'kitsAwaitingRegularization', 'placeAssignments'],
      onSuccess: () => { focusPreferred() },
    })

  } catch (err) {
    playSound('scan_error')
    scanResult.value = {
      type:    'error',
      message: err.response?.data?.error ?? 'Erreur lors du scan',
    }
  } finally {
    scanning.value = false
    setTimeout(() => {
      focusPreferred()
      setTimeout(() => { scanResult.value = null }, 8000)
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

onUnmounted(() => {
  if (countdownInterval) clearInterval(countdownInterval)
  if (completionTimer)   clearTimeout(completionTimer)
})

// ─── Compte à rebours ────────────────────────────────────────────────────────
const showCountdowns = ref(localStorage.getItem('returns_show_countdowns') !== 'false')
const ticker = ref(0)
let countdownInterval = null

function toggleCountdowns() {
  showCountdowns.value = !showCountdowns.value
  localStorage.setItem('returns_show_countdowns', String(showCountdowns.value))
}

/**
 * Retourne { text, urgency } pour une date de fin.
 * urgency: 'safe' | 'warning' | 'critical' | 'overdue'
 */
function getCountdown(endDateStr) {
  ticker.value // depend on ticker so it updates every second
  const end  = new Date(endDateStr)
  const now  = new Date()
  const diff = end - now  // ms, positive = futur, négatif = dépassé

  const abs = Math.abs(diff)
  const d = Math.floor(abs / 86400000)
  const h = Math.floor((abs % 86400000) / 3600000)
  const m = Math.floor((abs % 3600000) / 60000)
  const s = Math.floor((abs % 60000) / 1000)

  const pad = (n) => String(n).padStart(2, '0')

  if (diff > 0) {
    // Temps restant
    const text = d > 0
      ? `${d}j ${pad(h)}h ${pad(m)}m ${pad(s)}s`
      : h > 0
        ? `${pad(h)}h ${pad(m)}m ${pad(s)}s`
        : `${pad(m)}m ${pad(s)}s`
    const urgency = d >= 2 ? 'safe' : d === 1 || h >= 4 ? 'warning' : 'critical'
    return { text, urgency }
  } else {
    // Dépassé
    const text = d > 0
      ? `+${d}j ${pad(h)}h ${pad(m)}m ${pad(s)}s`
      : h > 0
        ? `+${pad(h)}h ${pad(m)}m ${pad(s)}s`
        : `+${pad(m)}m ${pad(s)}s`
    return { text, urgency: 'overdue' }
  }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function formatDate(date) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}
function pendingCount(reservation) {
  // Exclut les items "à vérifier" du compte "à retourner" classique
  return reservation.assets.filter(a => !a.returned_at && !a.is_missing && !a.pending_verification).length
}
function verificationCount(reservation) {
  return reservation.assets.filter(a => a.pending_verification && !a.returned_at).length
}

// ─── Mode d'affichage des équipements (liste / détail) ───────────────────────
const viewModes = ref({})  // resId → 'grid' | 'list'

function getViewMode(resId) {
  return viewModes.value[resId] ?? 'grid'
}

function toggleViewMode(resId, mode) {
  viewModes.value[resId] = mode
}

// ─── Section "Affectations lieux" ────────────────────────────────────────────
const placesSectionOpen   = ref(false)
const placeReturning      = ref(null)    // assignmentId en cours
const placeReturnModal    = ref(false)   // modal confirmé
const placeReturnTarget   = ref(null)    // { id, assetName, assetTag, placeName }
const placeReturnCondition = ref('good')
const placeReturnNotes    = ref('')
const placeReturnError    = ref(null)

const totalPlaceAssets = computed(() =>
  props.placeAssignments.reduce((sum, g) => sum + g.assignments.length, 0)
)

function openPlaceReturnModal(assignment, placeName) {
  placeReturnTarget.value    = {
    id: assignment.id,
    assetName: assignment.asset.name,
    assetTag: assignment.asset.asset_tag,
    placeName,
  }
  placeReturnCondition.value = 'good'
  placeReturnNotes.value     = ''
  placeReturnError.value     = null
  placeReturnModal.value     = true
}

async function submitPlaceReturn() {
  if (!placeReturnTarget.value) return
  placeReturning.value   = placeReturnTarget.value.id
  placeReturnError.value = null
  try {
    await axios.patch(route('asset-places.return', placeReturnTarget.value.id), {
      notes: placeReturnNotes.value || null,
    })
    playSound('scan_success')
    placeReturnModal.value = false
    router.reload({ only: ['placeAssignments'] })
  } catch (err) {
    placeReturnError.value = err.response?.data?.error ?? 'Erreur lors du retour'
  } finally {
    placeReturning.value = null
  }
}

// ─── Scan inline pour valider le retour d'un équipement "à vérifier" ──────────
const inlineScans = ref({})   // { `${resId}-${assetId}`: { tag, condition, notes, loading, error } }

function getInlineScan(resId, assetId) {
  const key = `${resId}-${assetId}`
  if (!inlineScans.value[key]) {
    inlineScans.value[key] = { tag: '', condition: 'good', notes: '', loading: false, error: null }
  }
  return inlineScans.value[key]
}

async function submitInlineScan(resId, assetId, assetTag) {
  const key  = `${resId}-${assetId}`
  const scan = inlineScans.value[key]
  if (!scan) return
  scan.loading = true
  scan.error   = null
  try {
    await axios.post(route('returns.scan'), {
      asset_tag:    assetTag,
      condition:    scan.condition,
      return_notes: scan.notes || null,
    })
    playSound('scan_success')
    router.reload({ only: ['overdueReservations', 'activeReservations', 'kitsAwaitingRegularization'] })
  } catch (err) {
    playSound('scan_error')
    scan.error = err.response?.data?.error ?? 'Erreur lors du retour'
  } finally {
    scan.loading = false
  }
}

// ─── Scan inline par réservation ─────────────────────────────────────────────
// Chaque carte de réservation dispose de sa propre zone de scan complète
// (tag, condition, notes, retourné par) avec verrouillage de focus.
const resScanTags       = ref({})   // { resId: tagString }
const resScanConditions = ref({})   // { resId: 'good'|'damaged'|'lost'|'pending' }
const resScanNotes      = ref({})   // { resId: string }
const resScanReturnedBy = ref({})   // { resId: userId }
const resScanResults    = ref({})   // { resId: { type, message } | null }
const lockedResId       = ref(null) // Réservation dont le scan a le focus verrouillé
const lockedMainScan    = ref(false) // Verrouillage du scan USB principal
const resScanInputRefs  = {}        // DOM refs non-réactifs, keyed by resId
let   lastRescanId      = null      // Pour mirrorer scanResult vers la bonne carte

function focusResInput(resId) {
  resScanInputRefs[resId]?.focus()
}

// Donne le focus à l'input verrouillé si un verrou est actif, sinon au panneau global.
// Remplace tous les appels focusPreferred() pour respecter le verrou.
function focusPreferred() {
  if (lockedResId.value) {
    focusResInput(lockedResId.value)
  } else if (lockedMainScan.value) {
    scanInputEl.value?.focus()
  } else {
    scanInputEl.value?.focus()
  }
}

function toggleLock(resId) {
  lockedResId.value = lockedResId.value === resId ? null : resId
  if (lockedResId.value === resId) {
    nextTick(() => focusResInput(resId))
  }
}

function toggleMainScanLock() {
  lockedMainScan.value = !lockedMainScan.value
  if (lockedMainScan.value) {
    nextTick(() => scanInputEl.value?.focus())
  }
}


// Synchronise le résultat global (scanResult) vers la carte de réservation concernée
watch(scanResult, (val) => {
  if (lastRescanId !== null && val !== null) {
    resScanResults.value = { ...resScanResults.value, [lastRescanId]: val }
    const id = lastRescanId
    lastRescanId = null
    setTimeout(() => {
      const copy = { ...resScanResults.value }
      delete copy[id]
      resScanResults.value = copy
    }, 8500)
  }
})

function getResCondition(resId) {
  return resScanConditions.value[resId] ?? 'good'
}

function scanFromReservation(res) {
  const tag       = (resScanTags.value[res.id] ?? '').trim()
  const condition = getResCondition(res.id)
  const notes     = resScanNotes.value[res.id] ?? ''

  if (!tag || scanning.value) return
  if (condition === 'pending' && !notes.trim()) return

  // Vider le champ immédiatement
  resScanTags.value = { ...resScanTags.value, [res.id]: '' }

  // Vérifier que le tag appartient bien à cette réservation (non rendu, non manquant)
  const found = res.assets.find(a => a.asset_tag === tag && !a.returned_at && !a.is_missing)
  if (!found) {
    resScanResults.value = {
      ...resScanResults.value,
      [res.id]: { type: 'error', message: `Code « ${tag} » introuvable dans cette réservation, ou déjà rendu.` },
    }
    setTimeout(() => {
      const copy = { ...resScanResults.value }
      delete copy[res.id]
      resScanResults.value = copy
    }, 5000)
    setTimeout(() => focusResInput(res.id), 50)
    return
  }

  // Transférer les options per-réservation vers l'état global avant d'appeler scanReturn()
  scanCondition.value  = condition
  scanNotes.value      = notes
  scanReturnedBy.value = resScanReturnedBy.value[res.id] ?? ''

  // Vider les notes (transférées) ; la condition reste pour le prochain scan
  resScanNotes.value = { ...resScanNotes.value, [res.id]: '' }

  // Déléguer au mécanisme de scan global (gère : notes de vérification, accessoires, composants)
  lastRescanId  = res.id
  scanTag.value = tag
  scanReturn()
}
</script>

<template>
  <AppLayout>

    <!-- ═══ Flash "Réservation complète" ═══ -->
    <Teleport to="body">
      <Transition name="completion-flash">
        <div v-if="completionFlash"
             class="fixed inset-0 z-[200] pointer-events-none flex items-center justify-center p-6">
          <!-- Fond semi-transparent vert -->
          <div class="absolute inset-0 bg-emerald-600/20 backdrop-blur-[2px]" />
          <!-- Carte centrale -->
          <div class="relative bg-emerald-600 text-white rounded-2xl shadow-2xl px-10 py-8 text-center max-w-lg w-full border-4 border-emerald-300">
            <div class="text-6xl mb-3 leading-none">🎉</div>
            <p class="text-2xl font-black tracking-tight mb-1">Réservation complète !</p>
            <p class="text-emerald-100 text-base font-medium truncate">
              « {{ completionFlash.title }} »
            </p>
            <p class="text-emerald-200 text-sm mt-1">
              {{ completionFlash.isKit ? '📦 Kit' : '📋 Réservation' }} — Tous les équipements sont rendus
            </p>
            <!-- Barre de progression -->
            <div class="mt-4 h-1.5 bg-emerald-400/40 rounded-full overflow-hidden">
              <div class="h-full bg-white/70 rounded-full completion-bar" />
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <div class="space-y-6">

      <!-- ══ Header ══ -->
      <div class="flex items-start justify-between gap-4 flex-wrap">
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
            <span v-if="totalPlaceAssets > 0" class="text-teal-600 font-semibold">
              📍 {{ totalPlaceAssets }} équipement(s) affecté(s) à un lieu
            </span>
            <span v-if="!overdueReservations.length && !activeReservations.length && !totalPlaceAssets" class="text-green-600 font-semibold">
              ✅ Tout le matériel est rentré
            </span>
          </p>
        </div>
        <!-- Toggle compteurs -->
        <button
          @click="toggleCountdowns"
          class="flex items-center gap-2 px-3 py-1.5 rounded-lg border text-sm font-medium transition"
          :class="showCountdowns
            ? 'bg-indigo-50 border-indigo-300 text-indigo-700 hover:bg-indigo-100'
            : 'bg-gray-50 border-gray-300 text-gray-500 hover:bg-gray-100'">
          <EyeSlashIcon v-if="showCountdowns" class="w-4 h-4" />
          <EyeIcon v-else class="w-4 h-4" />
          {{ showCountdowns ? 'Masquer les compteurs' : 'Afficher les compteurs' }}
        </button>
      </div>

      <!-- ══════════════════════════════════════════
           Panneau de scan (scanner USB ou clavier)
      ══════════════════════════════════════════ -->
      <div class="bg-white rounded-xl shadow-sm border-2 border-blue-300 p-5"
           :class="lockedMainScan ? 'outline outline-2 outline-red-400 outline-offset-2 bg-red-50/10' : ''">
        <h2 class="text-sm font-semibold text-blue-800 mb-3 flex items-center gap-2">
          <QrCodeIcon class="w-4 h-4" />
          Scanner un retour
          <button
            @click="toggleMainScanLock"
            :title="lockedMainScan ? 'Déverrouiller le focus (le curseur pourra aller ailleurs)' : 'Verrouiller le focus ici (le curseur reviendra automatiquement après chaque scan)'"
            :class="['ml-2 p-1 rounded transition hover:bg-blue-100',
              lockedMainScan ? 'text-red-600' : 'text-gray-400 hover:text-blue-600']">
            <LockClosedIcon v-if="lockedMainScan" class="w-4 h-4" />
            <LockOpenIcon v-else class="w-4 h-4" />
          </button>
          <span class="ml-auto text-xs font-normal text-gray-400">
            🔌 Scanner USB → pointe ici → lit le code → Entrée automatique
          </span>
        </h2>

        <div class="flex flex-wrap gap-3 items-end">
          <!-- Champ scan principal -->
          <div class="flex-1 min-w-52">
            <label class="block text-xs font-medium text-gray-600 mb-1">Code barre / Tag équipement</label>
            <div class="flex items-center gap-1.5">
              <input
                ref="scanInputEl"
                v-model="scanTag"
                type="text"
                placeholder="Scannez ou tapez le tag (ex: EQ-A1B2C)…"
                :disabled="scanning"
                @keydown.enter="scanReturn"
                class="flex-1 border-2 border-blue-200 bg-blue-50 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-400 outline-none disabled:opacity-60 placeholder-gray-400"
              />
              <BarcodeScanButton
                title="Scanner un retour"
                @scanned="(code) => { scanTag = code; scanReturn() }"
              />
            </div>
          </div>

          <!-- État de retour (4 états) -->
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">État à la réception</label>
            <select v-model="scanCondition"
                    :class="['border rounded-lg px-3 py-2 text-sm focus:ring-2 outline-none font-medium',
                      scanCondition === 'good'    ? 'border-green-400 bg-green-50 text-green-800 focus:ring-green-400'  :
                      scanCondition === 'damaged' ? 'border-orange-400 bg-orange-50 text-orange-800 focus:ring-orange-400' :
                      scanCondition === 'pending' ? 'border-amber-400 bg-amber-50 text-amber-800 focus:ring-amber-400' :
                                                    'border-red-400 bg-red-50 text-red-800 focus:ring-red-400']">
              <option value="good">✅ Bon état</option>
              <option value="damaged">⚠️ Endommagé</option>
              <option value="lost">❌ Perdu / Manquant</option>
              <option value="pending">🔍 À vérifier</option>
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

          <!-- Observations (obligatoires si "À vérifier") -->
          <div class="flex-1 min-w-44">
            <label class="block text-xs font-medium mb-1 transition"
                   :class="scanCondition === 'pending' ? 'text-amber-700 font-semibold' : 'text-gray-600'">
              {{ scanCondition === 'pending' ? '🔍 Raison de la mise en attente *' : 'Observations (optionnel)' }}
            </label>
            <input v-model="scanNotes" type="text"
                   :placeholder="scanCondition === 'pending' ? 'Obligatoire — décrivez la raison…' : 'Rayures, câble manquant…'"
                   :class="['w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 outline-none transition',
                     scanCondition === 'pending'
                       ? 'border-amber-400 bg-amber-50 focus:ring-amber-500 placeholder-amber-400'
                       : 'border-gray-300 focus:ring-blue-500']" />
            <p v-if="scanCondition === 'pending' && !scanNotes.trim()"
               class="text-xs text-amber-600 mt-0.5 font-medium">
              ⚠️ Note obligatoire pour mettre en attente
            </p>
          </div>

          <button
            @click="scanReturn"
            :disabled="scanning || !scanTag.trim() || (scanCondition === 'pending' && !scanNotes.trim())"
            class="flex items-center gap-1.5 px-5 py-2 text-white text-sm font-semibold rounded-lg transition disabled:opacity-50 whitespace-nowrap shadow-sm"
            :class="scanCondition === 'pending' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-blue-600 hover:bg-blue-700'">
            <QrCodeIcon class="w-4 h-4" />
            {{ scanning ? 'Traitement…' : scanCondition === 'pending' ? '🔍 Mettre en attente' : 'Valider retour' }}
          </button>
        </div>

        <!-- Résultat du scan -->
        <Transition name="fade">
          <div v-if="scanResult"
               class="mt-3 flex items-start gap-2 px-3 py-2.5 rounded-lg text-sm font-medium"
               :class="scanResult.type === 'success'
                 ? 'bg-green-100 text-green-800 border border-green-300'
                 : scanResult.type === 'warning'
                   ? 'bg-orange-100 text-orange-800 border border-orange-300'
                   : 'bg-red-100 text-red-800 border border-red-300'">
            <CheckCircleSolid v-if="scanResult.type === 'success'" class="w-4 h-4 text-green-600 flex-shrink-0 mt-0.5" />
            <ExclamationTriangleIcon v-else class="w-4 h-4 flex-shrink-0 mt-0.5"
              :class="scanResult.type === 'warning' ? 'text-orange-500' : 'text-red-500'" />
            {{ scanResult.message }}
          </div>
        </Transition>

        <!-- Annulations récentes (délai 5 min) -->
        <TransitionGroup name="fade" tag="div" class="mt-2 space-y-1.5">
          <div v-for="entry in activeUndos" :key="`${entry.reservationId}-${entry.assetId}`"
               class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg bg-amber-50 border border-amber-300 text-sm">
            <div class="flex items-center gap-2 min-w-0">
              <span class="text-amber-600 text-base leading-none flex-shrink-0">↩</span>
              <span class="text-amber-800 font-medium truncate">{{ entry.assetName }}</span>
              <span class="text-amber-500 text-xs flex-shrink-0">— annulation possible encore {{ remainingUndoSeconds(entry) }}s</span>
            </div>
            <button
              @click="undoReturn(entry)"
              :disabled="entry.undoLoading"
              class="flex-shrink-0 text-xs font-semibold px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-white transition disabled:opacity-50">
              {{ entry.undoLoading ? '…' : 'Annuler ce retour' }}
            </button>
          </div>
        </TransitionGroup>

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

            <!-- Countdown retard -->
            <Transition name="slide-down">
              <div v-if="showCountdowns" class="px-5 pt-4 pb-3 bg-red-600 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                    <ExclamationTriangleIcon class="w-4 h-4 text-white" />
                  </div>
                  <div>
                    <p class="text-xs font-semibold text-red-100 uppercase tracking-wide">Retard — {{ res.user.name }}</p>
                    <p class="text-3xl font-mono font-black text-white tracking-tight leading-none mt-0.5 countdown-pulse">
                      {{ getCountdown(res.end_date).text }}
                    </p>
                  </div>
                </div>
                <div class="text-right flex-shrink-0">
                  <p class="text-xs text-red-200">Devait être rendu le</p>
                  <p class="text-sm font-bold text-white">{{ formatDate(res.end_date) }}</p>
                  <p class="text-xs text-red-200 mt-0.5">{{ pendingCount(res) }} équipement(s) manquant(s)</p>
                </div>
              </div>
            </Transition>

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

              <!-- En-tête avec toggle vue -->
              <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">
                  Équipements — scannez ou validez manuellement :
                </p>
                <div class="flex items-center gap-0.5 bg-gray-100 rounded-lg p-0.5">
                  <button @click="toggleViewMode(res.id, 'grid')"
                    :class="getViewMode(res.id) === 'grid' ? 'bg-white shadow text-gray-800' : 'text-gray-400 hover:text-gray-600'"
                    class="flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium transition">
                    <Squares2X2Icon class="w-3 h-3" /> Détail
                  </button>
                  <button @click="toggleViewMode(res.id, 'list')"
                    :class="getViewMode(res.id) === 'list' ? 'bg-white shadow text-gray-800' : 'text-gray-400 hover:text-gray-600'"
                    class="flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium transition">
                    <ListBulletIcon class="w-3 h-3" /> Liste
                  </button>
                </div>
              </div>

              <!-- ── MODE LISTE (compact) ── -->
              <div v-if="getViewMode(res.id) === 'list'" class="rounded-lg border border-gray-200 overflow-hidden divide-y divide-gray-100">
                <!-- En-tête kit si réservation kit -->
                <div v-if="res.kit" class="flex items-center gap-2 px-3 py-2 bg-purple-50 border-b border-purple-100">
                  <ArchiveBoxIcon class="w-4 h-4 text-purple-500 flex-shrink-0" />
                  <span class="text-sm font-semibold text-purple-800 flex-1">{{ res.kit.name }}</span>
                  <span class="text-xs text-purple-400">{{ res.assets.length }} équip.</span>
                </div>
                <!-- Ligne par équipement -->
                <div v-for="asset in res.assets" :key="asset.id"
                  class="flex items-center gap-2 px-3 py-2 text-sm transition"
                  :class="asset.returned_at ? 'bg-green-50'
                        : asset.is_missing ? 'bg-gray-50 opacity-70'
                        : asset.pending_verification ? 'bg-amber-50'
                        : 'bg-white hover:bg-red-50/40'">
                  <!-- Icône statut -->
                  <CheckCircleSolid v-if="asset.returned_at" class="w-4 h-4 text-green-500 flex-shrink-0" />
                  <span v-else-if="asset.pending_verification" class="text-sm flex-shrink-0 leading-none">🔍</span>
                  <span v-else-if="asset.is_missing" class="text-sm flex-shrink-0 leading-none">🚫</span>
                  <div v-else class="w-3.5 h-3.5 rounded-full border-2 border-red-300 flex-shrink-0" />
                  <!-- Nom -->
                  <span class="font-medium flex-1 min-w-0 truncate"
                    :class="asset.returned_at ? 'text-green-800' : asset.is_missing ? 'text-gray-400' : 'text-gray-800'">
                    {{ asset.name }}
                  </span>
                  <!-- Tag -->
                  <span class="text-xs font-mono text-gray-400 flex-shrink-0">{{ asset.asset_tag }}</span>
                  <!-- Mini badges -->
                  <span v-if="asset.has_scratch && !asset.returned_at" title="Scratch" class="text-xs leading-none">🪢</span>
                  <span v-if="asset.has_belge && !asset.returned_at" title="Belge" class="text-xs leading-none">👜</span>
                  <span v-if="asset.return_check_note && !asset.returned_at && !asset.is_missing && !asset.pending_verification" title="Vérification requise" class="text-xs leading-none">📋</span>
                  <!-- Badge statut -->
                  <span class="text-xs px-2 py-0.5 rounded-full font-medium flex-shrink-0"
                    :class="asset.returned_at ? 'bg-green-100 text-green-700'
                          : asset.is_missing ? 'bg-gray-100 text-gray-500'
                          : asset.pending_verification ? 'bg-amber-100 text-amber-700'
                          : 'bg-red-100 text-red-700'">
                    {{ asset.returned_at ? '✓ Rendu' : asset.is_missing ? 'Manquant' : asset.pending_verification ? 'À vérifier' : 'À rendre' }}
                  </span>
                  <!-- Actions compactes -->
                  <button v-if="!asset.returned_at && !asset.is_missing && !asset.pending_verification && !asset.qr_code"
                    @click="openManualReturnModal(res.id, asset.id, asset.name, asset.asset_tag)"
                    :disabled="manualReturning === `${res.id}-${asset.id}`"
                    class="text-xs bg-white border border-red-300 px-2 py-0.5 rounded font-semibold text-red-700 hover:bg-red-50 flex-shrink-0 transition disabled:opacity-50">
                    ↩
                  </button>
                  <button v-if="res.kit && !asset.returned_at && !asset.is_missing && !asset.pending_verification"
                    @click="openMissingModal(res, asset)"
                    class="text-xs bg-orange-50 border border-orange-200 px-1.5 py-0.5 rounded text-orange-600 hover:bg-orange-100 flex-shrink-0 transition">
                    ⚠️
                  </button>
                </div>
              </div>

              <!-- ── MODE DÉTAIL (grille, défaut) ── -->
              <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                <!-- En-tête kit si réservation kit -->
                <div v-if="res.kit" class="col-span-full flex items-center gap-2 px-3 py-2 bg-purple-50 border border-purple-100 rounded-lg">
                  <ArchiveBoxIcon class="w-4 h-4 text-purple-500 flex-shrink-0" />
                  <span class="text-sm font-semibold text-purple-800 flex-1">📦 {{ res.kit.name }}</span>
                  <span class="text-xs text-purple-400">{{ res.assets.length }} équipements</span>
                </div>
                <div v-for="asset in res.assets" :key="asset.id"
                     class="flex items-start gap-2 px-3 py-2.5 rounded-lg border text-sm transition"
                     :class="asset.returned_at
                       ? 'bg-green-50 border-green-200 text-green-800'
                       : asset.is_missing
                         ? 'bg-gray-100 border-gray-200 text-gray-500 opacity-70'
                         : asset.pending_verification
                           ? 'bg-amber-50 border-amber-300 text-amber-800'
                           : 'bg-red-50 border-red-200 text-red-800'">
                  <CheckCircleSolid v-if="asset.returned_at" class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                  <span v-else-if="asset.pending_verification" class="flex-shrink-0 mt-0.5 text-sm leading-4">🔍</span>
                  <ExclamationTriangleIcon v-else-if="!asset.is_missing" class="w-4 h-4 text-red-400 flex-shrink-0 mt-0.5" />
                  <span v-else class="w-4 h-4 flex-shrink-0 mt-0.5 text-center text-xs">🚫</span>
                  <div class="min-w-0 flex-1">
                    <p class="font-semibold truncate">{{ asset.name }}</p>
                    <p class="text-xs font-mono opacity-60">{{ asset.asset_tag }}</p>
                    <div v-if="(asset.has_scratch || asset.has_belge) && !asset.returned_at && !asset.is_missing && !asset.pending_verification"
                         class="flex flex-wrap gap-1 mt-1.5">
                      <span v-if="asset.has_scratch"
                            class="inline-flex items-center gap-0.5 text-xs font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-900 border border-amber-400">
                        🪢 Scratch
                      </span>
                      <span v-if="asset.has_belge"
                            class="inline-flex items-center gap-0.5 text-xs font-bold px-1.5 py-0.5 rounded bg-purple-100 text-purple-900 border border-purple-400">
                        👜 Belge
                      </span>
                    </div>
                    <div v-if="asset.return_check_note && !asset.returned_at && !asset.is_missing && !asset.pending_verification"
                         class="mt-1.5 flex items-start gap-1 bg-orange-50 border border-orange-300 rounded px-2 py-1">
                      <span class="text-orange-500 text-xs flex-shrink-0 mt-0.5">📋</span>
                      <p class="text-xs text-orange-800 leading-snug">{{ asset.return_check_note }}</p>
                    </div>
                    <p v-if="asset.returned_at" class="text-xs text-green-600 mt-0.5">✓ Retourné</p>
                    <p v-else-if="asset.is_missing" class="text-xs text-gray-400 mt-0.5">🚫 Signalé manquant</p>
                    <template v-else-if="asset.pending_verification">
                      <div class="mt-1.5 rounded px-2 py-1.5 bg-amber-100 border border-amber-300">
                        <p class="text-xs font-semibold text-amber-800">🔍 En attente de vérification</p>
                        <p v-if="asset.verification_note" class="text-xs text-amber-700 mt-0.5 leading-snug italic">{{ asset.verification_note }}</p>
                      </div>
                      <div class="mt-2 bg-white border border-amber-200 rounded-lg p-2 space-y-1.5">
                        <p class="text-xs font-semibold text-amber-700">Finaliser le retour :</p>
                        <select :value="getInlineScan(res.id, asset.id).condition"
                                @change="getInlineScan(res.id, asset.id).condition = $event.target.value"
                                class="w-full border border-amber-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-amber-500 outline-none bg-white">
                          <option value="good">✅ Bon état</option>
                          <option value="damaged">⚠️ Endommagé</option>
                          <option value="lost">❌ Perdu</option>
                        </select>
                        <input :value="getInlineScan(res.id, asset.id).notes"
                               @input="getInlineScan(res.id, asset.id).notes = $event.target.value"
                               type="text" placeholder="Observations…"
                               class="w-full border border-gray-200 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-amber-500 outline-none" />
                        <p v-if="getInlineScan(res.id, asset.id).error" class="text-xs text-red-600">{{ getInlineScan(res.id, asset.id).error }}</p>
                        <button @click="submitInlineScan(res.id, asset.id, asset.asset_tag)"
                                :disabled="getInlineScan(res.id, asset.id).loading"
                                class="w-full text-xs bg-amber-500 hover:bg-amber-600 text-white px-2 py-1.5 rounded font-semibold transition disabled:opacity-50">
                          {{ getInlineScan(res.id, asset.id).loading ? 'Traitement…' : '✓ Valider le retour' }}
                        </button>
                      </div>
                    </template>
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
                        <button v-if="res.kit"
                          @click="openMissingModal(res, asset)"
                          class="text-xs bg-orange-50 text-orange-700 hover:bg-orange-100 border border-orange-200 px-2 py-1 rounded-md font-semibold transition">
                          ⚠️
                        </button>
                      </div>
                    </template>
                    <button v-if="res.kit && asset.qr_code && !asset.returned_at && !asset.is_missing && !asset.pending_verification"
                      @click="openMissingModal(res, asset)"
                      class="mt-1.5 w-full text-xs bg-orange-50 text-orange-700 hover:bg-orange-100 border border-orange-200 px-2 py-1 rounded-md font-semibold transition text-center">
                      ⚠️ Signaler manquant
                    </button>
                  </div>
                </div>
              </div>

              <!-- Zone de scan inline par réservation -->
              <div class="mt-3 pt-3 border-t border-red-100 rounded-b-xl transition"
                   :class="lockedResId === res.id ? 'outline outline-2 outline-red-400 outline-offset-2 rounded-xl p-2 bg-red-50/30' : ''">

                <!-- En-tête avec verrouillage -->
                <div class="flex items-center gap-2 mb-2">
                  <QrCodeIcon class="w-3.5 h-3.5 text-red-400" />
                  <span class="text-xs font-semibold text-red-600 uppercase tracking-wide flex-1">Scanner un retour pour cette réservation</span>
                  <button @click="toggleLock(res.id)"
                          :title="lockedResId === res.id ? 'Déverrouiller le focus' : 'Verrouiller le focus ici (le curseur reviendra automatiquement après chaque scan)'"
                          class="flex items-center gap-1 text-xs px-2 py-1 rounded-lg border transition"
                          :class="lockedResId === res.id
                            ? 'bg-red-600 text-white border-red-600 shadow-sm'
                            : 'text-gray-400 border-gray-200 hover:border-red-400 hover:text-red-600'">
                    <LockClosedIcon v-if="lockedResId === res.id" class="w-3 h-3" />
                    <LockOpenIcon v-else class="w-3 h-3" />
                    {{ lockedResId === res.id ? 'Verrouillé' : 'Verrouiller' }}
                  </button>
                </div>

                <!-- Champs de scan -->
                <div class="flex flex-wrap gap-2 items-end">
                  <!-- Tag + scan button -->
                  <div class="flex-1 min-w-44">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Code barre / Tag</label>
                    <div class="flex items-center gap-1">
                      <input
                        :ref="(el) => { if (el) resScanInputRefs[res.id] = el; else delete resScanInputRefs[res.id] }"
                        :value="resScanTags[res.id] ?? ''"
                        @input="resScanTags = { ...resScanTags, [res.id]: $event.target.value }"
                        type="text"
                        placeholder="Scannez ou tapez le tag…"
                        :disabled="scanning"
                        @keydown.enter="scanFromReservation(res)"
                        class="flex-1 min-w-0 border-2 border-red-200 bg-red-50 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-red-400 focus:border-red-400 outline-none disabled:opacity-50 placeholder-red-300 text-red-800"
                      />
                      <BarcodeScanButton
                        title="Scanner un retour"
                        @scanned="(code) => { resScanTags = { ...resScanTags, [res.id]: code }; scanFromReservation(res) }"
                      />
                    </div>
                  </div>

                  <!-- État à la réception -->
                  <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">État</label>
                    <select
                      :value="getResCondition(res.id)"
                      @change="resScanConditions = { ...resScanConditions, [res.id]: $event.target.value }"
                      :class="['border rounded-lg px-2 py-2 text-sm focus:ring-2 outline-none font-medium',
                        getResCondition(res.id) === 'good'    ? 'border-green-400 bg-green-50 text-green-800 focus:ring-green-400'  :
                        getResCondition(res.id) === 'damaged' ? 'border-orange-400 bg-orange-50 text-orange-800 focus:ring-orange-400' :
                        getResCondition(res.id) === 'pending' ? 'border-amber-400 bg-amber-50 text-amber-800 focus:ring-amber-400' :
                                                                'border-red-400 bg-red-50 text-red-800 focus:ring-red-400']">
                      <option value="good">✅ Bon état</option>
                      <option value="damaged">⚠️ Endommagé</option>
                      <option value="lost">❌ Perdu</option>
                      <option value="pending">🔍 À vérifier</option>
                    </select>
                  </div>

                  <!-- Observations / Raison -->
                  <div class="flex-1 min-w-36">
                    <label class="block text-xs font-medium mb-1 transition"
                           :class="getResCondition(res.id) === 'pending' ? 'text-amber-700 font-semibold' : 'text-gray-500'">
                      {{ getResCondition(res.id) === 'pending' ? '🔍 Raison *' : 'Observations' }}
                    </label>
                    <input
                      :value="resScanNotes[res.id] ?? ''"
                      @input="resScanNotes = { ...resScanNotes, [res.id]: $event.target.value }"
                      type="text"
                      :placeholder="getResCondition(res.id) === 'pending' ? 'Obligatoire…' : 'Rayures, câble manquant…'"
                      :class="['w-full border rounded-lg px-2 py-2 text-sm focus:ring-2 outline-none transition',
                        getResCondition(res.id) === 'pending'
                          ? 'border-amber-400 bg-amber-50 focus:ring-amber-500 placeholder-amber-400'
                          : 'border-gray-300 focus:ring-red-400']"
                    />
                  </div>

                  <!-- Retourné par -->
                  <div class="flex-1 min-w-36">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Retourné par <span class="font-normal text-gray-400">(opt.)</span></label>
                    <select
                      :value="resScanReturnedBy[res.id] ?? ''"
                      @change="resScanReturnedBy = { ...resScanReturnedBy, [res.id]: $event.target.value }"
                      class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm focus:ring-2 focus:ring-red-400 outline-none">
                      <option value="">— Réservant —</option>
                      <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                  </div>

                  <!-- Bouton valider -->
                  <button
                    @click="scanFromReservation(res)"
                    :disabled="scanning || !(resScanTags[res.id] ?? '').trim() || (getResCondition(res.id) === 'pending' && !(resScanNotes[res.id] ?? '').trim())"
                    class="flex items-center gap-1.5 px-4 py-2 text-white text-sm font-semibold rounded-lg transition disabled:opacity-40 whitespace-nowrap shadow-sm"
                    :class="getResCondition(res.id) === 'pending' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-red-600 hover:bg-red-700'">
                    <QrCodeIcon class="w-4 h-4" />
                    {{ scanning ? 'Traitement…' : getResCondition(res.id) === 'pending' ? 'Mettre en attente' : 'Valider retour' }}
                  </button>
                </div>

                <!-- Avertissement pending sans note -->
                <p v-if="getResCondition(res.id) === 'pending' && !(resScanNotes[res.id] ?? '').trim()"
                   class="text-xs text-amber-600 mt-1 font-medium">
                  ⚠️ Note obligatoire pour mettre en attente
                </p>

                <!-- Résultat du scan -->
                <Transition name="fade">
                  <div v-if="resScanResults[res.id]"
                       class="mt-2 flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium border"
                       :class="resScanResults[res.id].type === 'success'
                         ? 'bg-green-100 text-green-800 border-green-300'
                         : resScanResults[res.id].type === 'warning'
                           ? 'bg-orange-100 text-orange-800 border-orange-300'
                           : 'bg-red-100 text-red-800 border-red-300'">
                    <CheckCircleSolid v-if="resScanResults[res.id].type === 'success'" class="w-4 h-4 text-green-600 flex-shrink-0" />
                    <ExclamationTriangleIcon v-else class="w-4 h-4 flex-shrink-0"
                      :class="resScanResults[res.id].type === 'warning' ? 'text-orange-500' : 'text-red-500'" />
                    {{ resScanResults[res.id].message }}
                  </div>
                </Transition>
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
               class="bg-white rounded-xl shadow-sm border overflow-hidden"
               :class="{
                 'border-green-200':  getCountdown(res.end_date).urgency === 'safe',
                 'border-amber-300':  getCountdown(res.end_date).urgency === 'warning',
                 'border-red-300':    getCountdown(res.end_date).urgency === 'critical',
                 'border-blue-200':   !showCountdowns,
               }">

            <!-- Countdown retour attendu -->
            <Transition name="slide-down">
              <div v-if="showCountdowns"
                   class="px-5 pt-4 pb-3 flex items-center justify-between gap-4"
                   :class="{
                     'bg-emerald-600':  getCountdown(res.end_date).urgency === 'safe',
                     'bg-amber-500':    getCountdown(res.end_date).urgency === 'warning',
                     'bg-red-500':      getCountdown(res.end_date).urgency === 'critical',
                   }">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                    <ClockIcon class="w-4 h-4 text-white" />
                  </div>
                  <div>
                    <p class="text-xs font-semibold text-white/80 uppercase tracking-wide">Retour dans — {{ res.user.name }}</p>
                    <p class="text-3xl font-mono font-black text-white tracking-tight leading-none mt-0.5"
                       :class="{ 'countdown-pulse': getCountdown(res.end_date).urgency === 'critical' }">
                      {{ getCountdown(res.end_date).text }}
                    </p>
                  </div>
                </div>
                <div class="text-right flex-shrink-0">
                  <p class="text-xs text-white/70">À rendre le</p>
                  <p class="text-sm font-bold text-white">{{ formatDate(res.end_date) }}</p>
                  <p class="text-xs text-white/70 mt-0.5">{{ pendingCount(res) }} équipement(s) restant(s)</p>
                </div>
              </div>
            </Transition>

            <div class="px-5 py-4 border-b flex items-start justify-between gap-4 flex-wrap"
                 :class="{
                   'bg-green-50 border-green-100':  getCountdown(res.end_date).urgency === 'safe'     && showCountdowns,
                   'bg-amber-50 border-amber-100':  getCountdown(res.end_date).urgency === 'warning'  && showCountdowns,
                   'bg-red-50 border-red-100':      getCountdown(res.end_date).urgency === 'critical' && showCountdowns,
                   'bg-blue-50 border-blue-100':    !showCountdowns,
                 }">
              <div class="flex items-center gap-3 flex-1 min-w-0">
                <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold"
                     :class="{
                       'bg-green-100 text-green-700': getCountdown(res.end_date).urgency === 'safe'     && showCountdowns,
                       'bg-amber-100 text-amber-700': getCountdown(res.end_date).urgency === 'warning'  && showCountdowns,
                       'bg-red-100 text-red-700':     getCountdown(res.end_date).urgency === 'critical' && showCountdowns,
                       'bg-blue-100 text-blue-700':   !showCountdowns,
                     }">
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
                <span class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1 rounded-full border"
                      :class="{
                        'bg-green-100 text-green-700 border-green-300': getCountdown(res.end_date).urgency === 'safe'     && showCountdowns,
                        'bg-amber-100 text-amber-700 border-amber-300': getCountdown(res.end_date).urgency === 'warning'  && showCountdowns,
                        'bg-red-100 text-red-700 border-red-300':       getCountdown(res.end_date).urgency === 'critical' && showCountdowns,
                        'bg-blue-100 text-blue-700 border-blue-300':    !showCountdowns,
                      }">
                  <ClockIcon class="w-3 h-3" />
                  {{ res.days_remaining === 0 ? "À rendre aujourd'hui" : `J-${res.days_remaining}` }}
                </span>
              </div>
            </div>

            <!-- Équipements -->
            <div class="px-5 py-4">

              <!-- En-tête avec toggle vue -->
              <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">
                  Équipements — scannez le code barre physique pour valider chaque retour :
                </p>
                <div class="flex items-center gap-0.5 bg-gray-100 rounded-lg p-0.5">
                  <button @click="toggleViewMode(res.id, 'grid')"
                    :class="getViewMode(res.id) === 'grid' ? 'bg-white shadow text-gray-800' : 'text-gray-400 hover:text-gray-600'"
                    class="flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium transition">
                    <Squares2X2Icon class="w-3 h-3" /> Détail
                  </button>
                  <button @click="toggleViewMode(res.id, 'list')"
                    :class="getViewMode(res.id) === 'list' ? 'bg-white shadow text-gray-800' : 'text-gray-400 hover:text-gray-600'"
                    class="flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium transition">
                    <ListBulletIcon class="w-3 h-3" /> Liste
                  </button>
                </div>
              </div>

              <!-- ── MODE LISTE (compact) ── -->
              <div v-if="getViewMode(res.id) === 'list'" class="rounded-lg border border-gray-200 overflow-hidden divide-y divide-gray-100">
                <!-- En-tête kit si réservation kit -->
                <div v-if="res.kit" class="flex items-center gap-2 px-3 py-2 bg-purple-50 border-b border-purple-100">
                  <ArchiveBoxIcon class="w-4 h-4 text-purple-500 flex-shrink-0" />
                  <span class="text-sm font-semibold text-purple-800 flex-1">{{ res.kit.name }}</span>
                  <span class="text-xs text-purple-400">{{ res.assets.length }} équip.</span>
                </div>
                <!-- Ligne par équipement -->
                <div v-for="asset in res.assets" :key="asset.id"
                  class="flex items-center gap-2 px-3 py-2 text-sm transition"
                  :class="asset.returned_at ? 'bg-green-50'
                        : asset.is_missing ? 'bg-gray-50 opacity-70'
                        : asset.pending_verification ? 'bg-amber-50'
                        : 'bg-white hover:bg-blue-50/30'">
                  <CheckCircleSolid v-if="asset.returned_at" class="w-4 h-4 text-green-500 flex-shrink-0" />
                  <span v-else-if="asset.pending_verification" class="text-sm flex-shrink-0 leading-none">🔍</span>
                  <span v-else-if="asset.is_missing" class="text-sm flex-shrink-0 leading-none">🚫</span>
                  <div v-else class="w-3.5 h-3.5 rounded-full border-2 border-gray-300 flex-shrink-0" />
                  <span class="font-medium flex-1 min-w-0 truncate"
                    :class="asset.returned_at ? 'text-green-800' : asset.is_missing ? 'text-gray-400' : 'text-gray-800'">
                    {{ asset.name }}
                  </span>
                  <span class="text-xs font-mono text-gray-400 flex-shrink-0">{{ asset.asset_tag }}</span>
                  <span v-if="asset.has_scratch && !asset.returned_at" title="Scratch" class="text-xs leading-none">🪢</span>
                  <span v-if="asset.has_belge && !asset.returned_at" title="Belge" class="text-xs leading-none">👜</span>
                  <span v-if="asset.return_check_note && !asset.returned_at && !asset.is_missing && !asset.pending_verification" title="Vérification requise" class="text-xs leading-none">📋</span>
                  <span class="text-xs px-2 py-0.5 rounded-full font-medium flex-shrink-0"
                    :class="asset.returned_at ? 'bg-green-100 text-green-700'
                          : asset.is_missing ? 'bg-gray-100 text-gray-500'
                          : asset.pending_verification ? 'bg-amber-100 text-amber-700'
                          : 'bg-blue-100 text-blue-700'">
                    {{ asset.returned_at ? '✓ Rendu' : asset.is_missing ? 'Manquant' : asset.pending_verification ? 'À vérifier' : 'À rendre' }}
                  </span>
                  <button v-if="!asset.returned_at && !asset.is_missing && !asset.pending_verification && !asset.qr_code"
                    @click="openManualReturnModal(res.id, asset.id, asset.name, asset.asset_tag)"
                    :disabled="manualReturning === `${res.id}-${asset.id}`"
                    class="text-xs bg-white border border-green-300 px-2 py-0.5 rounded font-semibold text-green-700 hover:bg-green-50 flex-shrink-0 transition disabled:opacity-50">
                    ↩
                  </button>
                  <button v-if="res.kit && !asset.returned_at && !asset.is_missing && !asset.pending_verification"
                    @click="openMissingModal(res, asset)"
                    class="text-xs bg-orange-50 border border-orange-200 px-1.5 py-0.5 rounded text-orange-600 hover:bg-orange-100 flex-shrink-0 transition">
                    ⚠️
                  </button>
                </div>
              </div>

              <!-- ── MODE DÉTAIL (grille, défaut) ── -->
              <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                <!-- En-tête kit si réservation kit -->
                <div v-if="res.kit" class="col-span-full flex items-center gap-2 px-3 py-2 bg-purple-50 border border-purple-100 rounded-lg">
                  <ArchiveBoxIcon class="w-4 h-4 text-purple-500 flex-shrink-0" />
                  <span class="text-sm font-semibold text-purple-800 flex-1">📦 {{ res.kit.name }}</span>
                  <span class="text-xs text-purple-400">{{ res.assets.length }} équipements</span>
                </div>
                <div v-for="asset in res.assets" :key="asset.id"
                     class="flex items-start gap-2 px-3 py-2.5 rounded-lg border text-sm transition"
                     :class="asset.returned_at
                       ? 'bg-green-50 border-green-200 text-green-800'
                       : asset.is_missing
                         ? 'bg-gray-100 border-gray-200 text-gray-500 opacity-70'
                         : asset.pending_verification
                           ? 'bg-amber-50 border-amber-300 text-amber-800'
                           : 'bg-gray-50 border-gray-200 text-gray-700'">
                  <CheckCircleSolid v-if="asset.returned_at" class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                  <span v-else-if="asset.pending_verification" class="flex-shrink-0 mt-0.5 text-sm leading-4">🔍</span>
                  <ArchiveBoxIcon v-else-if="!asset.is_missing" class="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" />
                  <span v-else class="w-4 h-4 flex-shrink-0 mt-0.5 text-center text-xs">🚫</span>
                  <div class="min-w-0 flex-1">
                    <p class="font-semibold truncate">{{ asset.name }}</p>
                    <p class="text-xs font-mono opacity-60">{{ asset.asset_tag }}</p>
                    <div v-if="(asset.has_scratch || asset.has_belge) && !asset.returned_at && !asset.is_missing && !asset.pending_verification"
                         class="flex flex-wrap gap-1 mt-1.5">
                      <span v-if="asset.has_scratch"
                            class="inline-flex items-center gap-0.5 text-xs font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-900 border border-amber-400">
                        🪢 Scratch
                      </span>
                      <span v-if="asset.has_belge"
                            class="inline-flex items-center gap-0.5 text-xs font-bold px-1.5 py-0.5 rounded bg-purple-100 text-purple-900 border border-purple-400">
                        👜 Belge
                      </span>
                    </div>
                    <div v-if="asset.return_check_note && !asset.returned_at && !asset.is_missing && !asset.pending_verification"
                         class="mt-1.5 flex items-start gap-1 bg-orange-50 border border-orange-300 rounded px-2 py-1">
                      <span class="text-orange-500 text-xs flex-shrink-0 mt-0.5">📋</span>
                      <p class="text-xs text-orange-800 leading-snug">{{ asset.return_check_note }}</p>
                    </div>
                    <p v-if="asset.returned_at" class="text-xs text-green-600 mt-0.5">✓ Retourné</p>
                    <p v-else-if="asset.is_missing" class="text-xs text-gray-400 mt-0.5">🚫 Signalé manquant</p>
                    <template v-else-if="asset.pending_verification">
                      <div class="mt-1.5 rounded px-2 py-1.5 bg-amber-100 border border-amber-300">
                        <p class="text-xs font-semibold text-amber-800">🔍 En attente de vérification</p>
                        <p v-if="asset.verification_note" class="text-xs text-amber-700 mt-0.5 leading-snug italic">{{ asset.verification_note }}</p>
                      </div>
                      <div class="mt-2 bg-white border border-amber-200 rounded-lg p-2 space-y-1.5">
                        <p class="text-xs font-semibold text-amber-700">Finaliser le retour :</p>
                        <select :value="getInlineScan(res.id, asset.id).condition"
                                @change="getInlineScan(res.id, asset.id).condition = $event.target.value"
                                class="w-full border border-amber-300 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-amber-500 outline-none bg-white">
                          <option value="good">✅ Bon état</option>
                          <option value="damaged">⚠️ Endommagé</option>
                          <option value="lost">❌ Perdu</option>
                        </select>
                        <input :value="getInlineScan(res.id, asset.id).notes"
                               @input="getInlineScan(res.id, asset.id).notes = $event.target.value"
                               type="text" placeholder="Observations…"
                               class="w-full border border-gray-200 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-amber-500 outline-none" />
                        <p v-if="getInlineScan(res.id, asset.id).error" class="text-xs text-red-600">{{ getInlineScan(res.id, asset.id).error }}</p>
                        <button @click="submitInlineScan(res.id, asset.id, asset.asset_tag)"
                                :disabled="getInlineScan(res.id, asset.id).loading"
                                class="w-full text-xs bg-amber-500 hover:bg-amber-600 text-white px-2 py-1.5 rounded font-semibold transition disabled:opacity-50">
                          {{ getInlineScan(res.id, asset.id).loading ? 'Traitement…' : '✓ Valider le retour' }}
                        </button>
                      </div>
                    </template>
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
                    <button v-if="res.kit && asset.qr_code && !asset.returned_at && !asset.is_missing && !asset.pending_verification"
                      @click="openMissingModal(res, asset)"
                      class="mt-1.5 w-full text-xs bg-orange-50 text-orange-700 hover:bg-orange-100 border border-orange-200 px-2 py-1 rounded-md font-semibold transition text-center">
                      ⚠️ Signaler manquant
                    </button>
                  </div>
                </div>
              </div>

              <!-- Zone de scan inline par réservation -->
              <div class="mt-3 pt-3 border-t border-blue-100 rounded-b-xl transition"
                   :class="lockedResId === res.id ? 'outline outline-2 outline-blue-400 outline-offset-2 rounded-xl p-2 bg-blue-50/30' : ''">

                <!-- En-tête avec verrouillage -->
                <div class="flex items-center gap-2 mb-2">
                  <QrCodeIcon class="w-3.5 h-3.5 text-blue-400" />
                  <span class="text-xs font-semibold text-blue-600 uppercase tracking-wide flex-1">Scanner un retour pour cette réservation</span>
                  <button @click="toggleLock(res.id)"
                          :title="lockedResId === res.id ? 'Déverrouiller le focus' : 'Verrouiller le focus ici (le curseur reviendra automatiquement après chaque scan)'"
                          class="flex items-center gap-1 text-xs px-2 py-1 rounded-lg border transition"
                          :class="lockedResId === res.id
                            ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                            : 'text-gray-400 border-gray-200 hover:border-blue-400 hover:text-blue-600'">
                    <LockClosedIcon v-if="lockedResId === res.id" class="w-3 h-3" />
                    <LockOpenIcon v-else class="w-3 h-3" />
                    {{ lockedResId === res.id ? 'Verrouillé' : 'Verrouiller' }}
                  </button>
                </div>

                <!-- Champs de scan -->
                <div class="flex flex-wrap gap-2 items-end">
                  <!-- Tag + scan button -->
                  <div class="flex-1 min-w-44">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Code barre / Tag</label>
                    <div class="flex items-center gap-1">
                      <input
                        :ref="(el) => { if (el) resScanInputRefs[res.id] = el; else delete resScanInputRefs[res.id] }"
                        :value="resScanTags[res.id] ?? ''"
                        @input="resScanTags = { ...resScanTags, [res.id]: $event.target.value }"
                        type="text"
                        placeholder="Scannez ou tapez le tag…"
                        :disabled="scanning"
                        @keydown.enter="scanFromReservation(res)"
                        class="flex-1 min-w-0 border-2 border-blue-200 bg-blue-50 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-400 focus:border-blue-400 outline-none disabled:opacity-50 placeholder-blue-300 text-blue-800"
                      />
                      <BarcodeScanButton
                        title="Scanner un retour"
                        @scanned="(code) => { resScanTags = { ...resScanTags, [res.id]: code }; scanFromReservation(res) }"
                      />
                    </div>
                  </div>

                  <!-- État à la réception -->
                  <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">État</label>
                    <select
                      :value="getResCondition(res.id)"
                      @change="resScanConditions = { ...resScanConditions, [res.id]: $event.target.value }"
                      :class="['border rounded-lg px-2 py-2 text-sm focus:ring-2 outline-none font-medium',
                        getResCondition(res.id) === 'good'    ? 'border-green-400 bg-green-50 text-green-800 focus:ring-green-400'  :
                        getResCondition(res.id) === 'damaged' ? 'border-orange-400 bg-orange-50 text-orange-800 focus:ring-orange-400' :
                        getResCondition(res.id) === 'pending' ? 'border-amber-400 bg-amber-50 text-amber-800 focus:ring-amber-400' :
                                                                'border-red-400 bg-red-50 text-red-800 focus:ring-red-400']">
                      <option value="good">✅ Bon état</option>
                      <option value="damaged">⚠️ Endommagé</option>
                      <option value="lost">❌ Perdu</option>
                      <option value="pending">🔍 À vérifier</option>
                    </select>
                  </div>

                  <!-- Observations / Raison -->
                  <div class="flex-1 min-w-36">
                    <label class="block text-xs font-medium mb-1 transition"
                           :class="getResCondition(res.id) === 'pending' ? 'text-amber-700 font-semibold' : 'text-gray-500'">
                      {{ getResCondition(res.id) === 'pending' ? '🔍 Raison *' : 'Observations' }}
                    </label>
                    <input
                      :value="resScanNotes[res.id] ?? ''"
                      @input="resScanNotes = { ...resScanNotes, [res.id]: $event.target.value }"
                      type="text"
                      :placeholder="getResCondition(res.id) === 'pending' ? 'Obligatoire…' : 'Rayures, câble manquant…'"
                      :class="['w-full border rounded-lg px-2 py-2 text-sm focus:ring-2 outline-none transition',
                        getResCondition(res.id) === 'pending'
                          ? 'border-amber-400 bg-amber-50 focus:ring-amber-500 placeholder-amber-400'
                          : 'border-gray-300 focus:ring-blue-400']"
                    />
                  </div>

                  <!-- Retourné par -->
                  <div class="flex-1 min-w-36">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Retourné par <span class="font-normal text-gray-400">(opt.)</span></label>
                    <select
                      :value="resScanReturnedBy[res.id] ?? ''"
                      @change="resScanReturnedBy = { ...resScanReturnedBy, [res.id]: $event.target.value }"
                      class="w-full border border-gray-300 rounded-lg px-2 py-2 text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                      <option value="">— Réservant —</option>
                      <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                  </div>

                  <!-- Bouton valider -->
                  <button
                    @click="scanFromReservation(res)"
                    :disabled="scanning || !(resScanTags[res.id] ?? '').trim() || (getResCondition(res.id) === 'pending' && !(resScanNotes[res.id] ?? '').trim())"
                    class="flex items-center gap-1.5 px-4 py-2 text-white text-sm font-semibold rounded-lg transition disabled:opacity-40 whitespace-nowrap shadow-sm"
                    :class="getResCondition(res.id) === 'pending' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-blue-600 hover:bg-blue-700'">
                    <QrCodeIcon class="w-4 h-4" />
                    {{ scanning ? 'Traitement…' : getResCondition(res.id) === 'pending' ? 'Mettre en attente' : 'Valider retour' }}
                  </button>
                </div>

                <!-- Avertissement pending sans note -->
                <p v-if="getResCondition(res.id) === 'pending' && !(resScanNotes[res.id] ?? '').trim()"
                   class="text-xs text-amber-600 mt-1 font-medium">
                  ⚠️ Note obligatoire pour mettre en attente
                </p>

                <!-- Résultat du scan -->
                <Transition name="fade">
                  <div v-if="resScanResults[res.id]"
                       class="mt-2 flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium border"
                       :class="resScanResults[res.id].type === 'success'
                         ? 'bg-green-100 text-green-800 border-green-300'
                         : resScanResults[res.id].type === 'warning'
                           ? 'bg-orange-100 text-orange-800 border-orange-300'
                           : 'bg-red-100 text-red-800 border-red-300'">
                    <CheckCircleSolid v-if="resScanResults[res.id].type === 'success'" class="w-4 h-4 text-green-600 flex-shrink-0" />
                    <ExclamationTriangleIcon v-else class="w-4 h-4 flex-shrink-0"
                      :class="resScanResults[res.id].type === 'warning' ? 'text-orange-500' : 'text-red-500'" />
                    {{ resScanResults[res.id].message }}
                  </div>
                </Transition>
              </div>

            </div>
          </div>
        </div>
      </section>

      <!-- ══════════════════════════════════════════
           SECTION 3 — AFFECTATIONS LIEUX
      ══════════════════════════════════════════ -->
      <section v-if="placeAssignments.length > 0">

        <!-- Header collapsible -->
        <button
          type="button"
          @click="placesSectionOpen = !placesSectionOpen"
          class="w-full flex items-center gap-3 py-3 px-4 bg-teal-50 hover:bg-teal-100 border border-teal-200 rounded-xl transition text-left">
          <MapPinIcon class="w-5 h-5 text-teal-600 flex-shrink-0" />
          <span class="font-semibold text-teal-800 flex-1">Équipements affectés à un lieu</span>
          <span class="bg-teal-600 text-white text-xs font-bold px-2.5 py-1 rounded-full">
            {{ totalPlaceAssets }}
          </span>
          <span class="text-xs text-teal-500 font-medium mr-1">
            {{ placesSectionOpen ? 'Réduire' : 'Afficher' }}
          </span>
          <ChevronDownIcon class="w-4 h-4 text-teal-500 flex-shrink-0 transition-transform"
            :class="placesSectionOpen ? 'rotate-180' : ''" />
        </button>

        <!-- Contenu déroulant -->
        <Transition
          enter-active-class="transition-all duration-200 ease-out overflow-hidden"
          leave-active-class="transition-all duration-150 ease-in overflow-hidden"
          enter-from-class="opacity-0 max-h-0"
          enter-to-class="opacity-100 max-h-[9999px]"
          leave-from-class="opacity-100 max-h-[9999px]"
          leave-to-class="opacity-0 max-h-0">
          <div v-if="placesSectionOpen" class="mt-3 space-y-3">

            <!-- Note de séparation -->
            <div class="flex items-center gap-2 px-3 py-2 bg-teal-50 border border-teal-200 rounded-lg text-xs text-teal-700">
              <MapPinIcon class="w-3.5 h-3.5 flex-shrink-0" />
              <span>Ces équipements sont affectés à un lieu (pas une réservation). Le scan USB fonctionne aussi depuis le panneau en haut de page.</span>
            </div>

            <!-- Une carte par lieu -->
            <div v-for="group in placeAssignments" :key="group.place.id"
                 class="bg-white rounded-xl shadow-sm border border-teal-200 overflow-hidden">

              <!-- Header lieu -->
              <div class="flex items-center gap-3 px-4 py-3 border-b border-teal-100"
                   :style="group.place.color ? `background-color: ${group.place.color}18; border-left: 4px solid ${group.place.color}` : 'background-color: #f0fdfa; border-left: 4px solid #0d9488'">
                <MapPinIcon class="w-4 h-4 flex-shrink-0" :style="group.place.color ? `color: ${group.place.color}` : 'color: #0d9488'" />
                <span class="font-semibold text-gray-900 flex-1">{{ group.place.name }}</span>
                <span v-if="group.place.is_storage" class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Stockage</span>
                <span class="text-xs text-gray-400">{{ group.assignments.length }} équip.</span>
              </div>

              <!-- Liste des équipements -->
              <div class="divide-y divide-gray-100">
                <div v-for="item in group.assignments" :key="item.id"
                     class="flex items-center gap-3 px-4 py-3 text-sm hover:bg-teal-50/40 transition">
                  <!-- Statut retard -->
                  <div class="flex-shrink-0">
                    <span v-if="item.is_overdue" title="Retour en retard" class="text-base leading-none">⚠️</span>
                    <div v-else class="w-3.5 h-3.5 rounded-full border-2 border-teal-300" />
                  </div>
                  <!-- Nom + tag -->
                  <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-800 truncate">{{ item.asset.name }}</p>
                    <p class="text-xs font-mono text-gray-400">{{ item.asset.asset_tag }}</p>
                  </div>
                  <!-- Dates -->
                  <div class="text-right flex-shrink-0 hidden sm:block">
                    <p class="text-xs text-gray-400">Affecté le {{ item.assigned_at }}</p>
                    <p v-if="item.return_expected_at"
                       class="text-xs font-medium"
                       :class="item.is_overdue ? 'text-red-600' : 'text-gray-500'">
                      Retour prévu : {{ item.return_expected_at }}
                    </p>
                    <p v-else class="text-xs text-gray-400 italic">Permanent</p>
                  </div>
                  <!-- Badge retard -->
                  <span v-if="item.is_overdue"
                        class="text-xs font-semibold px-2 py-0.5 rounded-full bg-red-100 text-red-700 flex-shrink-0">
                    En retard
                  </span>
                  <!-- Bouton retour -->
                  <button
                    @click="openPlaceReturnModal(item, group.place.name)"
                    :disabled="placeReturning === item.id"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded-lg transition disabled:opacity-50 flex-shrink-0 shadow-sm">
                    <CheckCircleIcon class="w-3.5 h-3.5" />
                    {{ placeReturning === item.id ? '…' : 'Retourner' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </Transition>
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

        <!-- ── Vérification scratch / belge (si applicable) ── -->
        <div v-if="manualReturnTarget?.hasScratch || manualReturnTarget?.hasBelge" class="mb-4 rounded-xl border-2 border-amber-300 bg-amber-50 p-4">
          <p class="text-sm font-bold text-amber-800 mb-3">🔍 Accessoires à vérifier au retour</p>
          <div class="space-y-3">
            <!-- Scratch -->
            <div v-if="manualReturnTarget?.hasScratch">
              <p class="text-xs font-semibold text-gray-700 mb-1.5">🪢 Velcro / scratch</p>
              <div class="flex gap-2">
                <button @click="manualScratchPresent = true; manualLostConfirm = false"
                  class="flex-1 py-2 rounded-lg text-sm font-semibold border-2 transition"
                  :class="manualScratchPresent === true ? 'bg-green-500 text-white border-green-500' : 'border-gray-300 text-gray-600 hover:border-green-400'">
                  ✓ Présent
                </button>
                <button @click="manualScratchPresent = false; manualLostConfirm = false"
                  class="flex-1 py-2 rounded-lg text-sm font-semibold border-2 transition"
                  :class="manualScratchPresent === false ? 'bg-red-500 text-white border-red-500' : 'border-gray-300 text-gray-600 hover:border-red-400'">
                  ✗ Manquant
                </button>
              </div>
            </div>
            <!-- Belge -->
            <div v-if="manualReturnTarget?.hasBelge">
              <p class="text-xs font-semibold text-gray-700 mb-1.5">👜 Belge</p>
              <div class="flex gap-2">
                <button @click="manualBelgePresent = true; manualLostConfirm = false"
                  class="flex-1 py-2 rounded-lg text-sm font-semibold border-2 transition"
                  :class="manualBelgePresent === true ? 'bg-green-500 text-white border-green-500' : 'border-gray-300 text-gray-600 hover:border-green-400'">
                  ✓ Présent
                </button>
                <button @click="manualBelgePresent = false; manualLostConfirm = false"
                  class="flex-1 py-2 rounded-lg text-sm font-semibold border-2 transition"
                  :class="manualBelgePresent === false ? 'bg-red-500 text-white border-red-500' : 'border-gray-300 text-gray-600 hover:border-red-400'">
                  ✗ Manquant
                </button>
              </div>
            </div>
          </div>

          <!-- Alerte blocage -->
          <div v-if="manualAccessoriesAnyMissing && !manualLostConfirm"
               class="mt-3 rounded-lg bg-red-50 border border-red-300 px-3 py-2.5 text-sm text-red-800">
            <p class="font-semibold mb-1">⛔ Retour bloqué</p>
            <p class="text-xs">Un ou plusieurs accessoires sont manquants. Résolvez le problème ou déclarez-les perdus pour forcer le retour.</p>
            <button @click="manualLostConfirm = true"
              class="mt-2 w-full text-xs bg-red-600 hover:bg-red-700 text-white py-1.5 rounded-lg font-semibold transition">
              ⚠️ Déclarer le(s) accessoire(s) manquant(s) comme perdu(s) et forcer le retour
            </button>
          </div>
          <div v-else-if="manualAccessoriesAnyMissing && manualLostConfirm"
               class="mt-3 rounded-lg bg-orange-50 border border-orange-300 px-3 py-2 text-xs text-orange-800 font-medium">
            ⚠️ Le(s) accessoire(s) manquant(s) seront notés comme perdus. Le toggle correspondant (belge ou velcro) sera automatiquement décoché.
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
            :disabled="!manualAccessoriesReady"
            class="flex-1 py-2 rounded-lg text-sm font-medium transition disabled:opacity-40"
            :class="manualReturnCondition === 'good'    ? 'bg-green-600 text-white hover:bg-green-700'
                  : manualReturnCondition === 'damaged'  ? 'bg-orange-500 text-white hover:bg-orange-600'
                  : 'bg-red-600 text-white hover:bg-red-700'">
            Confirmer le retour
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : Retour d'un équipement affecté à un lieu ═══ -->
    <div v-if="placeReturnModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg text-teal-700 flex items-center gap-2">
            <MapPinIcon class="w-5 h-5" />
            Retour depuis un lieu
          </h3>
          <button @click="placeReturnModal=false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <!-- Résumé -->
        <div class="flex items-center gap-3 p-3 bg-teal-50 rounded-lg border border-teal-200 mb-4">
          <div class="w-10 h-10 rounded-lg bg-teal-100 flex items-center justify-center text-teal-600 flex-shrink-0">
            <MapPinIcon class="w-5 h-5" />
          </div>
          <div>
            <p class="font-semibold text-sm text-gray-900">{{ placeReturnTarget?.assetName }}</p>
            <p class="text-xs font-mono text-gray-500">{{ placeReturnTarget?.assetTag }}</p>
            <p class="text-xs text-teal-600 mt-0.5">📍 {{ placeReturnTarget?.placeName }}</p>
          </div>
        </div>

        <!-- Observations optionnelles -->
        <div class="mb-5">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Observations <span class="text-gray-400 font-normal">(optionnel)</span>
          </label>
          <textarea v-model="placeReturnNotes" rows="2"
            placeholder="État à la récupération, remarques…"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-teal-500 outline-none resize-none" />
        </div>

        <p v-if="placeReturnError" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2 mb-4">
          {{ placeReturnError }}
        </p>

        <div class="flex gap-3">
          <button @click="placeReturnModal=false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitPlaceReturn" :disabled="placeReturning !== null"
            class="flex-1 py-2 rounded-lg text-sm font-semibold bg-teal-600 text-white hover:bg-teal-700 transition disabled:opacity-50 flex items-center justify-center gap-2">
            <CheckCircleIcon class="w-4 h-4" />
            {{ placeReturning ? 'Traitement…' : 'Confirmer le retour' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Pop-up : vérification des composants un par un ═══ -->
    <Teleport to="body">
      <Transition name="comp-check-fade">
        <div v-if="compModal.open"
             class="fixed inset-0 z-[400] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
          <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden">

            <!-- En-tête indigo -->
            <div class="bg-indigo-600 px-8 py-7 text-white">
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-bold text-indigo-200 uppercase tracking-widest">Vérification des composants</span>
                <span class="text-sm font-bold bg-indigo-500 rounded-full px-3 py-1">
                  {{ compModal.currentIndex + 1 }} / {{ compModal.components.length }}
                </span>
              </div>
              <p class="text-xl font-bold text-indigo-100 truncate">{{ compModal.assetName }}</p>
            </div>

            <!-- Progression -->
            <div class="h-2 bg-indigo-100">
              <div class="h-2 bg-indigo-500 transition-all duration-300"
                   :style="{ width: `${((compModal.currentIndex) / compModal.components.length) * 100}%` }"></div>
            </div>

            <!-- Corps : composant courant -->
            <div class="px-8 py-10 text-center" v-if="currentComp">
              <!-- Quantité -->
              <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-100 text-indigo-700 text-3xl font-black mb-6">
                {{ currentComp.quantity }}
              </div>

              <!-- Nom en très grand -->
              <p class="text-4xl font-black text-gray-900 leading-tight mb-3">
                {{ currentComp.name }}
              </p>

              <!-- Description -->
              <p v-if="currentComp.description" class="text-lg text-gray-500 mb-6">
                {{ currentComp.description }}
              </p>
              <p v-else class="mb-6"></p>

              <!-- Instruction -->
              <div class="inline-flex items-center gap-3 bg-indigo-50 border border-indigo-200 rounded-2xl px-6 py-3 text-indigo-800">
                <span class="text-2xl">📦</span>
                <span class="text-base font-semibold">
                  Scannez à nouveau le code barre de l'équipement pour valider
                </span>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-4 px-8 pb-8">
              <button
                @click="markComponentMissing"
                class="flex-1 flex items-center justify-center gap-3 py-5 rounded-2xl border-3 border-red-300 bg-red-50 text-red-700 font-black text-xl hover:bg-red-100 transition active:scale-95">
                <span class="text-3xl">❌</span>
                ABSENT
              </button>
              <button
                @click="confirmComponent"
                class="flex-1 flex items-center justify-center gap-3 py-5 rounded-2xl bg-green-600 text-white font-black text-xl hover:bg-green-700 transition shadow-lg active:scale-95">
                <span class="text-3xl">✅</span>
                PRÉSENT
              </button>
            </div>

            <!-- Composants déjà traités -->
            <div v-if="compModal.currentIndex > 0" class="border-t px-8 pb-6">
              <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Déjà vérifiés :</p>
              <div class="flex flex-wrap gap-2">
                <span v-for="(c, i) in compModal.components.slice(0, compModal.currentIndex)" :key="c.id"
                      :class="compModal.missingIds.includes(c.id)
                        ? 'bg-red-100 text-red-700 border-red-300'
                        : 'bg-green-100 text-green-700 border-green-300'"
                      class="inline-flex items-center gap-1.5 text-sm font-semibold border rounded-full px-3 py-1">
                  <span>{{ compModal.missingIds.includes(c.id) ? '❌' : '✅' }}</span>
                  {{ c.name }}
                </span>
              </div>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ═══ Pop-up pré-retour : vérification scratch / belge ═══ -->
    <Teleport to="body">
      <Transition name="belge-check-fade">
        <div v-if="accessoriesModal.open"
             class="fixed inset-0 z-[400] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
          <div class="bg-white rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden">

            <!-- En-tête amber -->
            <div class="bg-amber-500 px-8 py-6 text-white">
              <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-bold text-amber-100 uppercase tracking-widest">Vérification avant retour</span>
                <span class="text-sm font-bold bg-amber-400 rounded-full px-3 py-1">
                  {{ [accessoriesModal.hasBelge, accessoriesModal.hasScratch].filter(Boolean).length }} accessoire(s)
                </span>
              </div>
              <p class="text-xl font-bold text-white truncate">Accessoires requis pour cet équipement</p>
              <p class="text-xs text-amber-100 mt-2 flex items-center gap-1.5">
                <span>📷</span>
                Scannez à nouveau le code-barres de cet équipement pour confirmer la présence de tous les accessoires
              </p>
            </div>

            <!-- Corps : boutons par accessoire -->
            <div class="px-8 py-7 space-y-5">

              <!-- Scratch -->
              <div v-if="accessoriesModal.hasScratch">
                <div class="flex items-center gap-3 mb-3">
                  <span class="text-4xl leading-none">🪢</span>
                  <p class="text-2xl font-black text-gray-900">Velcro / Scratch</p>
                </div>
                <div class="flex gap-3">
                  <button @click="accessoriesModal.scratchPresent = true; accessoriesModal.confirmingLost = false"
                    class="flex-1 flex items-center justify-center gap-3 py-4 rounded-2xl border-3 font-black text-xl transition active:scale-95"
                    :class="accessoriesModal.scratchPresent === true
                      ? 'bg-green-600 text-white border-green-600 shadow-lg'
                      : 'border-green-300 bg-green-50 text-green-700 hover:bg-green-100'">
                    <span class="text-2xl">✅</span> PRÉSENT
                  </button>
                  <button @click="accessoriesModal.scratchPresent = false; accessoriesModal.confirmingLost = false"
                    class="flex-1 flex items-center justify-center gap-3 py-4 rounded-2xl border-3 font-black text-xl transition active:scale-95"
                    :class="accessoriesModal.scratchPresent === false
                      ? 'bg-red-500 text-white border-red-500 shadow-lg'
                      : 'border-red-300 bg-red-50 text-red-700 hover:bg-red-100'">
                    <span class="text-2xl">❌</span> MANQUANT
                  </button>
                </div>
              </div>

              <!-- Belge -->
              <div v-if="accessoriesModal.hasBelge">
                <div class="flex items-center gap-3 mb-3">
                  <span class="text-4xl leading-none">👜</span>
                  <p class="text-2xl font-black text-gray-900">Belge</p>
                </div>
                <div class="flex gap-3">
                  <button @click="accessoriesModal.belgePresent = true; accessoriesModal.confirmingLost = false"
                    class="flex-1 flex items-center justify-center gap-3 py-4 rounded-2xl border-3 font-black text-xl transition active:scale-95"
                    :class="accessoriesModal.belgePresent === true
                      ? 'bg-green-600 text-white border-green-600 shadow-lg'
                      : 'border-green-300 bg-green-50 text-green-700 hover:bg-green-100'">
                    <span class="text-2xl">✅</span> PRÉSENT
                  </button>
                  <button @click="accessoriesModal.belgePresent = false; accessoriesModal.confirmingLost = false"
                    class="flex-1 flex items-center justify-center gap-3 py-4 rounded-2xl border-3 font-black text-xl transition active:scale-95"
                    :class="accessoriesModal.belgePresent === false
                      ? 'bg-red-500 text-white border-red-500 shadow-lg'
                      : 'border-red-300 bg-red-50 text-red-700 hover:bg-red-100'">
                    <span class="text-2xl">❌</span> MANQUANT
                  </button>
                </div>
              </div>

              <!-- Alerte : accessoire(s) manquant(s) → blocage ou confirmation perte -->
              <Transition name="fade">
                <div v-if="accessoriesAnyMissing && !accessoriesModal.confirmingLost"
                     class="rounded-2xl bg-red-50 border-2 border-red-300 px-5 py-4">
                  <p class="font-black text-red-700 text-base mb-1">⛔ Retour bloqué</p>
                  <p class="text-sm text-red-600 mb-3">L'équipement ne peut pas être retourné sans ses accessoires.</p>
                  <button @click="accessoriesModal.confirmingLost = true"
                    class="w-full py-3 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-sm transition">
                    ⚠️ Déclarer le(s) accessoire(s) comme perdu(s) et forcer le retour
                  </button>
                </div>
                <div v-else-if="accessoriesAnyMissing && accessoriesModal.confirmingLost"
                     class="rounded-2xl bg-orange-50 border-2 border-orange-300 px-5 py-3 text-sm text-orange-800 font-medium">
                  ⚠️ Le(s) accessoire(s) manquant(s) seront notés comme perdus et le toggle correspondant sera automatiquement décoché.
                </div>
              </Transition>
            </div>

            <!-- Actions -->
            <div class="flex gap-4 px-8 pb-8">
              <button @click="cancelAccessoriesCheck"
                class="flex-1 flex items-center justify-center gap-2 py-4 rounded-2xl border-2 border-gray-300 text-gray-600 font-bold text-base hover:bg-gray-50 transition">
                Annuler
              </button>
              <button @click="proceedWithAccessories"
                :disabled="!accessoriesCanProceed"
                class="flex-1 flex items-center justify-center gap-3 py-4 rounded-2xl font-black text-xl transition shadow-lg active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed"
                :class="accessoriesAnyMissing ? 'bg-orange-500 hover:bg-orange-600 text-white' : 'bg-green-600 hover:bg-green-700 text-white'">
                <span class="text-2xl">{{ accessoriesAnyMissing ? '⚠️' : '✅' }}</span>
                {{ accessoriesAnyMissing ? 'FORCER LE RETOUR' : 'VALIDER LE RETOUR' }}
              </button>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ═══ Pop-up : vérification à faire pour le retour ═══ -->
    <Teleport to="body">
      <Transition name="check-note-fade">
        <div v-if="checkNoteModal"
             class="fixed inset-0 z-[300] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">

            <!-- En-tête orange -->
            <div class="bg-orange-500 px-6 py-5 text-white">
              <div class="flex items-center gap-3 mb-1">
                <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0 text-2xl">
                  📋
                </div>
                <div>
                  <p class="text-xs font-semibold text-orange-100 uppercase tracking-widest">Vérification requise</p>
                  <p class="text-base font-bold leading-tight">{{ checkNoteData?.assetName }}</p>
                </div>
              </div>
            </div>

            <!-- Corps -->
            <div class="px-6 py-5">
              <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                Point de contrôle au retour :
              </p>
              <div class="bg-orange-50 border border-orange-200 rounded-xl px-4 py-3 text-sm text-orange-900 leading-relaxed font-medium">
                {{ checkNoteData?.note }}
              </div>

              <p class="text-xs text-gray-500 mt-4 text-center">
                Procédez à la vérification, puis indiquez si tout est conforme ou si une anomalie a été détectée.
              </p>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 px-6 pb-6">
              <button
                @click="cancelCheckNote"
                class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl border-2 border-red-300 bg-red-50 text-red-700 font-bold text-sm hover:bg-red-100 transition">
                ⚠️ Anomalie détectée
              </button>
              <button
                @click="confirmCheckNote"
                class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl bg-green-600 text-white font-bold text-sm hover:bg-green-700 transition shadow-sm">
                ✅ Tout est conforme
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ═══ Pop-up : sélection du lieu de stockage ═══ -->
    <Teleport to="body">
      <Transition name="storage-place-fade">
        <div v-if="storagePlaceModal.open"
             class="fixed inset-0 z-[300] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">

            <!-- En-tête -->
            <div class="bg-blue-600 px-6 py-5 text-white">
              <div class="flex items-center gap-3 mb-1">
                <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0 text-2xl">
                  📍
                </div>
                <div>
                  <p class="text-xs font-semibold text-blue-100 uppercase tracking-widest">Lieu de stockage</p>
                  <p class="text-base font-bold leading-tight">{{ storagePlaceModal.asset?.name }}</p>
                </div>
              </div>
              <p class="text-sm text-blue-100 mt-2">
                Retourné depuis « <span class="font-semibold">{{ storagePlaceModal.fromPlace?.name }}</span> »
              </p>
            </div>

            <!-- Corps -->
            <div class="px-6 py-6">
              <p class="text-sm text-gray-600 mb-4">
                Choisissez le lieu de stockage où l'équipement sera rangé :
              </p>

              <div class="space-y-2">
                <label v-for="place in storagePlaces" :key="place.id"
                  class="flex items-center gap-3 p-3 rounded-lg border-2 transition cursor-pointer hover:bg-gray-50"
                  :class="storagePlaceModal.selectedId === place.id
                    ? 'border-blue-500 bg-blue-50'
                    : 'border-gray-200'">
                  <input type="radio"
                    :value="place.id"
                    v-model="storagePlaceModal.selectedId"
                    class="w-5 h-5 text-blue-600 border-gray-300 focus:ring-blue-500">
                  <div class="flex-1">
                    <div class="flex items-center gap-2">
                      <div class="w-3 h-3 rounded-full"
                        :style="{ backgroundColor: place.color }"></div>
                      <span class="font-medium text-gray-900">{{ place.name }}</span>
                    </div>
                  </div>
                </label>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-3 px-6 pb-6">
              <button @click="cancelStoragePlace"
                class="flex-1 py-3 rounded-xl border-2 border-gray-300 text-gray-600 font-bold text-sm hover:bg-gray-50 transition">
                Annuler
              </button>
              <button @click="confirmStoragePlace"
                :disabled="!storagePlaceModal.selectedId"
                class="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold text-sm hover:bg-blue-700 transition shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
                ✅ Confirmer
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

  </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* Pop-up composants */
.comp-check-fade-enter-active { transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.comp-check-fade-leave-active { transition: all 0.2s ease-in; }
.comp-check-fade-enter-from   { opacity: 0; transform: scale(0.88); }
.comp-check-fade-leave-to     { opacity: 0; transform: scale(0.95); }

/* border-3 Tailwind hack */
.border-3 { border-width: 3px; }

/* Pop-up vérification accessoires pré-retour */
.belge-check-fade-enter-active { transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.belge-check-fade-leave-active { transition: all 0.2s ease-in; }
.belge-check-fade-enter-from   { opacity: 0; transform: scale(0.88); }
.belge-check-fade-leave-to     { opacity: 0; transform: scale(0.95); }

/* Pop-up vérification retour */
.check-note-fade-enter-active { transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1); }
.check-note-fade-leave-active { transition: all 0.2s ease-in; }
.check-note-fade-enter-from   { opacity: 0; transform: scale(0.92); }
.check-note-fade-leave-to     { opacity: 0; transform: scale(0.96); }

/* Pop-up sélection lieu de stockage */
.storage-place-fade-enter-active { transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1); }
.storage-place-fade-leave-active { transition: all 0.2s ease-in; }
.storage-place-fade-enter-from   { opacity: 0; transform: scale(0.92); }
.storage-place-fade-leave-to     { opacity: 0; transform: scale(0.96); }

/* Countdown slide transition */
.slide-down-enter-active { transition: all 0.3s ease; }
.slide-down-leave-active { transition: all 0.25s ease; }
.slide-down-enter-from   { opacity: 0; transform: translateY(-8px); max-height: 0; }
.slide-down-leave-to     { opacity: 0; transform: translateY(-8px); max-height: 0; }
.slide-down-enter-to,
.slide-down-leave-from   { opacity: 1; transform: translateY(0); max-height: 120px; }

/* Pulsation pour les compteurs critiques */
@keyframes countdown-blink {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.6; }
}
.countdown-pulse {
  animation: countdown-blink 1s ease-in-out infinite;
}

/* Flash réservation complète */
.completion-flash-enter-active {
  transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.completion-flash-leave-active {
  transition: all 0.4s ease-in;
}
.completion-flash-enter-from {
  opacity: 0;
  transform: scale(0.75);
}
.completion-flash-leave-to {
  opacity: 0;
  transform: scale(1.05);
}

/* Barre de progression qui se vide en 4.5 s */
@keyframes shrink-bar {
  from { width: 100%; }
  to   { width: 0%; }
}
.completion-bar {
  animation: shrink-bar 4.5s linear forwards;
}
</style>
