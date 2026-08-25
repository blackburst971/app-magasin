<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import SignaturePad from '@/Components/SignaturePad.vue'
import AssetThumbnail from '@/Components/AssetThumbnail.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'
import axios from 'axios'

const page    = usePage()
const isAdmin = computed(() => page.props.auth.user?.is_admin)
const isOwner = computed(() => page.props.auth.user?.id === props.reservation?.user_id)

const props = defineProps({
  reservation:        Object,
  isAdmin:            Boolean,
  isSupervisor:       Boolean,
  checkoutForms: { type: Array, default: () => [] },
  allUsers:           { type: Array, default: () => [] },
})

const showRejectModal    = ref(false)
const rejectNotes        = ref('')
const supervisorRejectNotes = ref('')
const showSupervisorRejectModal = ref(false)
const showDeleteModal    = ref(false)
const showAdminCancelModal = ref(false)
const adminCancelReason  = ref('')
const processing         = ref('')
const manualReturning    = ref(null)  // asset.id en cours de retour manuel

// ─── Modal retour avec état ───────────────────────────────────────────────────
const showReturnModal   = ref(false)
const returnModalAsset  = ref(null)
const returnCondition   = ref('good')
const returnNotes       = ref('')

function openReturnModal(asset) {
  returnModalAsset.value = asset
  returnCondition.value  = 'good'
  returnNotes.value      = ''
  showReturnModal.value  = true
}

function submitManualReturn() {
  if (!returnModalAsset.value) return
  manualReturning.value = returnModalAsset.value.id
  showReturnModal.value = false
  router.post(
    route('reservations.return-asset', [props.reservation.id, returnModalAsset.value.id]),
    { condition: returnCondition.value, return_notes: returnNotes.value },
    { onFinish: () => { manualReturning.value = null } }
  )
}

// Edit dates modal
const showEditDatesModal = ref(false)
const editDatesForm      = ref({
  start_date: props.reservation.start_date_input ?? '',
  end_date:   props.reservation.end_date_input   ?? '',
})
const editDatesError = ref('')

function openEditDates() {
  editDatesForm.value.start_date = props.reservation.start_date_input ?? ''
  editDatesForm.value.end_date   = props.reservation.end_date_input   ?? ''
  editDatesError.value = ''
  showEditDatesModal.value = true
}

function submitEditDates() {
  editDatesError.value = ''
  if (!editDatesForm.value.start_date || !editDatesForm.value.end_date) {
    editDatesError.value = 'Les deux dates sont obligatoires.'
    return
  }
  processing.value = 'dates'
  router.patch(route('reservations.update-dates', props.reservation.id), {
    start_date: editDatesForm.value.start_date,
    end_date:   editDatesForm.value.end_date,
  }, {
    onSuccess: () => { showEditDatesModal.value = false },
    onError:   (e) => { editDatesError.value = Object.values(e).join(' ') },
    onFinish:  () => { processing.value = '' },
  })
}

// Supervisor approval — signature + approve
const supervisorSignature = ref(null)
const supervisorSignError = ref('')

function confirmSupervisorApprove() {
  supervisorSignError.value = ''
  if (!supervisorSignature.value) {
    supervisorSignError.value = 'Votre signature est obligatoire pour valider la demande.'
    return
  }
  processing.value = 'supervisor-approve'
  router.post(route('reservations.supervisor-approve', props.reservation.id), {
    supervisor_signature: supervisorSignature.value,
  }, {
    onError:  (e) => { supervisorSignError.value = Object.values(e).join(' ') },
    onFinish: () => { processing.value = '' },
  })
}

function confirmSupervisorReject() {
  if (!supervisorRejectNotes.value.trim()) return
  processing.value = 'supervisor-reject'
  router.post(route('reservations.supervisor-reject', props.reservation.id), {
    admin_notes: supervisorRejectNotes.value,
  }, {
    onFinish: () => { processing.value = ''; showSupervisorRejectModal.value = false },
  })
}

// Admin actions
const approveError = ref('')
const approveReservation = () => {
  approveError.value = ''
  processing.value = 'approve'
  router.post(route('reservations.approve', props.reservation.id), {}, {
    onError: (e) => { approveError.value = e.approve ?? Object.values(e).join(' ') },
    onFinish: () => { processing.value = '' },
  })
}

const rejectReservation = () => {
  if (!rejectNotes.value.trim()) return
  processing.value = 'reject'
  router.post(route('reservations.reject', props.reservation.id), { admin_notes: rejectNotes.value }, {
    onFinish: () => { processing.value = ''; showRejectModal.value = false },
  })
}

const cancelReservation = () => {
  if (!confirm('Annuler cette réservation ?')) return
  router.post(route('reservations.cancel', props.reservation.id))
}

const adminCancelReservation = () => {
  processing.value = 'admin-cancel'
  router.post(route('reservations.cancel', props.reservation.id), { reason: adminCancelReason.value }, {
    onFinish: () => { processing.value = ''; showAdminCancelModal.value = false },
  })
}

const deleteReservation = () => {
  processing.value = 'delete'
  router.delete(route('reservations.destroy', props.reservation.id), {}, {
    onFinish: () => { processing.value = ''; showDeleteModal.value = false },
  })
}

const sendEmail = () => {
  processing.value = 'email'
  router.post(route('reservations.send-email', props.reservation.id), {}, {
    onFinish: () => { processing.value = '' },
  })
}

// ─── Admin edit modal ─────────────────────────────────────────────────────────
const showAdminEditModal = ref(false)
const adminEditForm = ref({
  title:       props.reservation.title,
  start_date:  props.reservation.start_date_input ?? '',
  end_date:    props.reservation.end_date_input   ?? '',
  notes:       props.reservation.notes       ?? '',
  admin_notes: props.reservation.admin_notes ?? '',
  status:      props.reservation.status,
})
const adminEditError = ref('')

function openAdminEdit() {
  adminEditForm.value = {
    title:       props.reservation.title,
    start_date:  props.reservation.start_date_input ?? '',
    end_date:    props.reservation.end_date_input   ?? '',
    notes:       props.reservation.notes       ?? '',
    admin_notes: props.reservation.admin_notes ?? '',
    status:      props.reservation.status,
  }
  adminEditError.value    = ''
  modalAssetSearch.value  = ''
  modalAssetResults.value = []
  showAdminEditModal.value = true
}

function submitAdminEdit() {
  adminEditError.value = ''
  if (!adminEditForm.value.title.trim()) { adminEditError.value = 'Le titre est obligatoire.'; return }
  if (!adminEditForm.value.start_date || !adminEditForm.value.end_date) { adminEditError.value = 'Les dates sont obligatoires.'; return }
  processing.value = 'admin-edit'
  router.patch(route('reservations.admin-update', props.reservation.id), adminEditForm.value, {
    onSuccess: () => { showAdminEditModal.value = false },
    onError:   (e) => { adminEditError.value = Object.values(e).join(' ') },
    onFinish:  () => { processing.value = '' },
  })
}

// ─── Chat messagerie ──────────────────────────────────────────────────────────
const messages     = ref([])
const newMessage   = ref('')
const chatLoading  = ref(false)
const chatSending  = ref(false)
const chatError    = ref('')
const messagesContainer = ref(null)
let   pollInterval = null

async function fetchMessages() {
  try {
    const { data } = await axios.get(route('reservations.messages.index', props.reservation.id))
    messages.value = data
    await nextTick()
    scrollToBottom()
  } catch {}
}

function scrollToBottom() {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
  }
}

async function sendMessage() {
  if (!newMessage.value.trim() || chatSending.value) return
  chatError.value = ''
  chatSending.value = true
  try {
    const { data } = await axios.post(route('reservations.messages.store', props.reservation.id), {
      body: newMessage.value.trim(),
    })
    messages.value.push(data)
    newMessage.value = ''
    await nextTick()
    scrollToBottom()
  } catch (e) {
    chatError.value = 'Erreur lors de l\'envoi du message.'
  } finally {
    chatSending.value = false
  }
}

onMounted(async () => {
  await fetchMessages()
  pollInterval = setInterval(fetchMessages, 6000)
})

onBeforeUnmount(() => {
  clearInterval(pollInterval)
})

// ─── Admin : ajouter / retirer un equipement (panneau page Show) ──────────────
const showAddAssetPanel  = ref(false)
const assetSearch        = ref('')
const assetSearchResults = ref([])
const assetSearchLoading = ref(false)
const addingAssetId      = ref(null)
const removingAssetId    = ref(null)
let   searchTimeout      = null

// ─── Admin : ajouter / retirer un equipement dans le modal Modifier ───────────
const modalAssetSearch   = ref('')
const modalAssetResults  = ref([])
const modalAssetLoading  = ref(false)
const modalAddingId      = ref(null)
const modalRemovingId    = ref(null)
let   modalSearchTimeout = null

watch(modalAssetSearch, (val) => {
  clearTimeout(modalSearchTimeout)
  if (!val.trim()) { modalAssetResults.value = []; return }
  modalAssetLoading.value = true
  modalSearchTimeout = setTimeout(async () => {
    try {
      const { data } = await axios.get(route('assets.available'), {
        params: {
          start: adminEditForm.value.start_date || props.reservation.start_date_input,
          end:   adminEditForm.value.end_date   || props.reservation.end_date_input,
        }
      })
      const q = val.toLowerCase()
      const existingIds = new Set((props.reservation.assets ?? []).map(a => a.id))
      modalAssetResults.value = data
        .filter(a => !existingIds.has(a.id))
        .filter(a => a.name.toLowerCase().includes(q) || (a.asset_tag ?? '').toLowerCase().includes(q))
        .slice(0, 8)
    } catch {
      modalAssetResults.value = []
    } finally {
      modalAssetLoading.value = false
    }
  }, 350)
})

function modalAddAsset(asset) {
  modalAddingId.value = asset.id
  router.post(route('reservations.add-asset', props.reservation.id), { asset_id: asset.id }, {
    preserveState: true,
    onSuccess: () => {
      modalAssetSearch.value  = ''
      modalAssetResults.value = []
    },
    onError: (e) => { adminEditError.value = Object.values(e).join(' ') },
    onFinish: () => { modalAddingId.value = null },
  })
}

function modalRemoveAsset(asset) {
  if (!confirm(`Retirer « ${asset.name} » de la reservation ?`)) return
  modalRemovingId.value = asset.id
  router.delete(route('reservations.remove-asset', [props.reservation.id, asset.id]), {}, {
    preserveState: true,
    onError: (e) => { adminEditError.value = Object.values(e).join(' ') },
    onFinish: () => { modalRemovingId.value = null },
  })
}

// ─── Participants supplémentaires (admin) ──────────────────────────────────────
const participants      = ref([...(props.reservation.additional_users ?? [])])
const participantSearch = ref('')
const participantAdding = ref(false)
const participantError  = ref('')

const participantCandidates = computed(() => {
  const q     = participantSearch.value.trim().toLowerCase()
  const taken = new Set([props.reservation.user_id, ...participants.value.map(u => u.id)])
  return props.allUsers.filter(u => !taken.has(u.id) && (
    !q || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)
  )).slice(0, 8)
})

async function addParticipant(user) {
  participantAdding.value = true
  participantError.value  = ''
  try {
    await axios.post(route('reservations.users.add', props.reservation.id), { user_id: user.id })
    participants.value.push({ id: user.id, name: user.name, email: user.email })
    participantSearch.value = ''
  } catch (e) {
    participantError.value = e.response?.data?.error ?? 'Erreur'
  } finally {
    participantAdding.value = false
  }
}

async function removeParticipant(user) {
  try {
    await axios.delete(route('reservations.users.remove', [props.reservation.id, user.id]))
    participants.value = participants.value.filter(u => u.id !== user.id)
  } catch {}
}

watch(assetSearch, (val) => {
  clearTimeout(searchTimeout)
  if (!val.trim()) { assetSearchResults.value = []; return }
  assetSearchLoading.value = true
  searchTimeout = setTimeout(async () => {
    try {
      const { data } = await axios.get(route('assets.available'), {
        params: {
          start: props.reservation.start_date,
          end:   props.reservation.end_date,
        }
      })
      const q = val.toLowerCase()
      // Filtre local sur le resultat + exclure les assets deja dans la reservation
      const existingIds = new Set((props.reservation.assets ?? []).map(a => a.id))
      assetSearchResults.value = data
        .filter(a => !existingIds.has(a.id))
        .filter(a => a.name.toLowerCase().includes(q) || a.asset_tag.toLowerCase().includes(q))
        .slice(0, 10)
    } catch {
      assetSearchResults.value = []
    } finally {
      assetSearchLoading.value = false
    }
  }, 350)
})

function addAssetToReservation(asset) {
  addingAssetId.value = asset.id
  router.post(route('reservations.add-asset', props.reservation.id), { asset_id: asset.id }, {
    onSuccess: () => {
      assetSearch.value        = ''
      assetSearchResults.value = []
      showAddAssetPanel.value  = false
    },
    onFinish: () => { addingAssetId.value = null },
  })
}

function removeAssetFromReservation(asset) {
  if (!confirm(`Retirer « ${asset.name} » de la reservation ?`)) return
  removingAssetId.value = asset.id
  router.delete(route('reservations.remove-asset', [props.reservation.id, asset.id]), {}, {
    onFinish: () => { removingAssetId.value = null },
  })
}

// Labels statut sidebar
const statusIcon = computed(() => {
  const s = props.reservation.status
  if (s === 'awaiting_supervisor') return '✍️'
  if (s === 'pending')        return '⏳'
  if (s === 'approved')       return '✅'
  if (s === 'en_preparation') return '📋'
  if (s === 'active')         return '📦'
  if (s === 'returned')       return '✓'
  return '❌'
})

const statusDescription = computed(() => {
  const s = props.reservation.status
  if (s === 'awaiting_supervisor') return 'En attente de la signature du responsable'
  if (s === 'pending')        return 'En attente de validation administrateur'
  if (s === 'approved')       return 'Validée — Prête au retrait'
  if (s === 'en_preparation') return 'Équipements en cours de préparation'
  if (s === 'active')         return 'Matériel en cours d\'utilisation'
  if (s === 'returned')       return 'Matériel retourné'
  return 'Réservation annulée ou refusée'
})

// ── Rapports utilisateurs ──────────────────────────────────────────────────────
const showReportModal  = ref(false)
const reportModalAsset = ref(null)
const reportSubmitting = ref(false)
const reportError      = ref('')
const reportSuccess    = ref('')
const reportForm       = ref({ type: '', title: '', content: '' })

const reportTypes = [
  { value: 'damage',      label: '⚠️ Dégradation' },
  { value: 'loss',        label: '❌ Perte' },
  { value: 'malfunction', label: '🔧 Dysfonctionnement' },
  { value: 'observation', label: '📋 Observation' },
]

function openReportModal(asset) {
  reportModalAsset.value = asset
  reportForm.value       = { type: '', title: '', content: '' }
  reportError.value      = ''
  reportSuccess.value    = ''
  showReportModal.value  = true
}

async function submitReport() {
  if (!reportForm.value.type || !reportForm.value.title.trim() || !reportForm.value.content.trim()) return
  reportSubmitting.value = true
  reportError.value      = ''
  reportSuccess.value    = ''
  try {
    const { data } = await axios.post(route('reservations.report', props.reservation.id), {
      asset_id: reportModalAsset.value.id,
      type:     reportForm.value.type,
      title:    reportForm.value.title,
      content:  reportForm.value.content,
    })
    reportSuccess.value = data.message ?? 'Rapport envoyé. L\'administrateur en sera informé.'
    reportForm.value    = { type: '', title: '', content: '' }
    setTimeout(() => { showReportModal.value = false }, 2500)
  } catch (e) {
    reportError.value = e.response?.data?.message ?? 'Erreur lors de l\'envoi.'
  } finally {
    reportSubmitting.value = false
  }
}
</script>

<template>
  <AppLayout>
    <div class="max-w-4xl">
      <!-- Header -->
      <div class="flex items-start justify-between mb-6 flex-wrap gap-3">
        <div>
          <div class="flex items-center gap-3 mb-1 flex-wrap">
            <h1 class="text-2xl font-bold text-gray-900">{{ reservation.title }}</h1>
            <StatusBadge :status="reservation.status" type="reservation" />
          </div>
          <p class="text-gray-500 text-sm">
            Réservation #{{ reservation.id }}
            <span v-if="isSupervisor && !isAdmin" class="ml-2 text-purple-600 font-medium">
              — Demande de {{ reservation.user?.name }}
            </span>
          </p>
        </div>

        <!-- Admin actions -->
        <div v-if="isAdmin" class="flex gap-2 flex-wrap justify-end">
          <template v-if="reservation.status === 'pending'">
            <div class="flex flex-col items-end gap-1">
              <div class="flex gap-2">
                <button @click="approveReservation" :disabled="processing === 'approve'"
                  class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50">
                  {{ processing === 'approve' ? '…' : '✅ Valider' }}
                </button>
                <button @click="showRejectModal=true"
                  class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700">
                  ❌ Refuser
                </button>
              </div>
              <p v-if="approveError" class="text-xs text-red-600 bg-red-50 border border-red-200 rounded px-2 py-1 max-w-xs text-right">
                🎓 {{ approveError }}
              </p>
            </div>
          </template>
          <button @click="openAdminEdit"
            class="border border-indigo-300 text-indigo-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-50">
            ✏️ Modifier
          </button>
          <template v-if="['pending','approved','awaiting_supervisor'].includes(reservation.status)">
            <button @click="openEditDates"
              class="border border-blue-300 text-blue-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-50">
              📅 Dates
            </button>
          </template>
          <template v-if="reservation.status === 'approved'">
            <a :href="route('attribution.index')"
              class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 inline-flex items-center gap-1.5">
              📦 Page Attribution →
            </a>
          </template>
          <template v-if="reservation.status === 'active'">
            <button @click="sendEmail" :disabled="processing === 'email'"
              class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 disabled:opacity-50">
              {{ processing === 'email' ? '…' : '📧 Envoyer récap' }}
            </button>
          </template>
          <!-- Imprimer fiche de sortie — disponible si approuvé ou actif -->
          <template v-if="['approved','active','returned'].includes(reservation.status)">
            <a :href="route('reservations.print', reservation.id)" target="_blank"
              class="border border-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition inline-flex items-center gap-1.5">
              🖨️ Fiche de sortie
            </a>
          </template>
          <!-- Annuler (admin) — pour les réservations approuvées ou actives -->
          <template v-if="['pending','approved','active'].includes(reservation.status)">
            <button @click="adminCancelReason = ''; showAdminCancelModal = true"
              class="border border-orange-300 text-orange-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-orange-50 transition">
              🚫 Annuler
            </button>
          </template>
          <!-- Supprimer — toujours disponible pour l'admin -->
          <button @click="showDeleteModal = true"
            class="border border-red-200 text-red-500 px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-50 transition">
            🗑 Supprimer
          </button>
        </div>

        <!-- User actions (non-admin, non-supervisor) -->
        <div v-else-if="!isSupervisor" class="flex gap-2 flex-wrap justify-end">
          <template v-if="['pending','approved','awaiting_supervisor'].includes(reservation.status)">
            <button @click="cancelReservation"
              class="border border-red-300 text-red-600 px-4 py-2 rounded-lg text-sm hover:bg-red-50">
              Annuler
            </button>
          </template>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main content -->
        <div class="lg:col-span-2 space-y-6">

          <!-- ══════════════════════════════════════════════════
               SECTION SUPERVISEUR — signature + approbation
               (visible seulement quand status = awaiting_supervisor ET isSupervisor)
          ═══════════════════════════════════════════════════ -->
          <div v-if="isSupervisor && reservation.status === 'awaiting_supervisor'"
               class="bg-purple-50 border-2 border-purple-300 rounded-xl p-6">
            <h2 class="font-bold text-purple-900 mb-1 flex items-center gap-2 text-lg">
              ✍️ Validation requise — Votre signature
            </h2>
            <p class="text-sm text-purple-700 mb-5">
              En tant que responsable de <strong>{{ reservation.user?.name }}</strong>, vous devez signer
              pour valider cette demande avant qu'elle soit transmise à l'administrateur.
            </p>

            <!-- Résumé rapide -->
            <div class="bg-white rounded-lg p-4 mb-5 border border-purple-200 text-sm space-y-1">
              <div class="flex justify-between"><span class="text-gray-500">Demandeur</span><span class="font-medium">{{ reservation.user?.name }}</span></div>
              <div class="flex justify-between"><span class="text-gray-500">Titre</span><span class="font-medium">{{ reservation.title }}</span></div>
              <div class="flex justify-between"><span class="text-gray-500">Début</span><span>{{ reservation.start_date_formatted }}</span></div>
              <div class="flex justify-between"><span class="text-gray-500">Fin</span><span>{{ reservation.end_date_formatted }}</span></div>
              <div class="flex justify-between"><span class="text-gray-500">Équipements</span><span>{{ reservation.assets?.length }} équipement(s)</span></div>
            </div>

            <!-- Pad de signature -->
            <div class="mb-4">
              <label class="block text-sm font-semibold text-gray-800 mb-1">
                Votre signature <span class="text-red-500">*</span>
              </label>
              <p class="text-xs text-gray-500 mb-2">Signez dans le cadre ci-dessous pour approuver la demande.</p>
              <SignaturePad v-model="supervisorSignature" />
              <div class="mt-1">
                <span v-if="!supervisorSignature" class="text-xs text-red-500 font-medium">⚠ Signature requise</span>
                <span v-else class="text-xs text-green-600 font-medium">✓ Signature capturée</span>
              </div>
            </div>

            <!-- Erreur -->
            <div v-if="supervisorSignError" class="mb-4 bg-red-50 border border-red-200 rounded-lg p-3">
              <p class="text-sm text-red-700">{{ supervisorSignError }}</p>
            </div>

            <!-- Actions superviseur -->
            <div class="flex gap-3">
              <button @click="showSupervisorRejectModal = true"
                class="flex-1 border border-red-300 text-red-600 py-2.5 rounded-lg text-sm font-medium hover:bg-red-50 transition">
                ❌ Refuser la demande
              </button>
              <button @click="confirmSupervisorApprove"
                :disabled="processing === 'supervisor-approve' || !supervisorSignature"
                :class="['flex-1 py-2.5 rounded-lg text-sm font-medium transition',
                  supervisorSignature
                    ? 'bg-purple-600 text-white hover:bg-purple-700 disabled:opacity-50'
                    : 'bg-gray-200 text-gray-400 cursor-not-allowed']">
                {{ processing === 'supervisor-approve' ? 'Envoi…' : '✅ Approuver et transmettre à l\'admin' }}
              </button>
            </div>
          </div>

          <!-- Banner : en attente du responsable (côté utilisateur) -->
          <div v-else-if="!isSupervisor && !isAdmin && reservation.status === 'awaiting_supervisor'"
               class="bg-purple-50 border border-purple-200 rounded-xl p-5 flex items-start gap-3">
            <span class="text-purple-500 text-2xl flex-shrink-0">✍️</span>
            <div>
              <p class="text-sm font-semibold text-purple-900">En attente de la signature de votre responsable</p>
              <p class="text-xs text-purple-700 mt-1">
                Votre demande a été transmise à votre responsable. Dès qu'il aura signé et approuvé,
                elle sera transmise à l'administrateur pour validation finale.
              </p>
            </div>
          </div>

          <!-- Détails de la réservation -->
          <div class="bg-white rounded-xl shadow-sm border p-6">
            <h2 class="font-semibold text-gray-900 mb-4">Détails</h2>
            <dl class="space-y-3 text-sm">
              <div class="flex justify-between"><dt class="text-gray-500">Demandeur</dt><dd class="font-medium">{{ reservation.user?.name }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-500">Date début</dt><dd>{{ reservation.start_date_formatted }}</dd></div>
              <div class="flex justify-between"><dt class="text-gray-500">Date fin</dt><dd>{{ reservation.end_date_formatted }}</dd></div>
              <div v-if="reservation.supervisor_approved_at_formatted" class="flex justify-between">
                <dt class="text-gray-500">Validé par responsable</dt>
                <dd class="text-purple-700 font-medium">{{ reservation.supervisor_approved_at_formatted }}</dd>
              </div>
              <div v-if="reservation.approved_at_formatted" class="flex justify-between"><dt class="text-gray-500">Validée le</dt><dd>{{ reservation.approved_at_formatted }}</dd></div>
              <div v-if="reservation.checkout_at_formatted" class="flex justify-between"><dt class="text-gray-500">Retrait le</dt><dd>{{ reservation.checkout_at_formatted }}</dd></div>
              <div v-if="reservation.return_at_formatted" class="flex justify-between"><dt class="text-gray-500">Retour le</dt><dd>{{ reservation.return_at_formatted }}</dd></div>
              <div v-if="reservation.notes" class="pt-2 border-t">
                <dt class="text-gray-500 mb-1">Notes utilisateur</dt>
                <dd class="text-gray-700 bg-gray-50 p-3 rounded-lg">{{ reservation.notes }}</dd>
              </div>
              <div v-if="reservation.admin_notes" class="pt-2 border-t">
                <dt class="text-gray-500 mb-1">Notes admin / responsable</dt>
                <dd class="text-gray-700 bg-blue-50 p-3 rounded-lg">{{ reservation.admin_notes }}</dd>
              </div>
            </dl>
          </div>

          <!-- ── Participants supplémentaires (admin) ── -->
          <div class="bg-white rounded-xl shadow-sm border p-6">
            <h2 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
              👥 Participants
              <span class="text-xs font-normal bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                {{ 1 + participants.length }} personne(s)
              </span>
            </h2>

            <!-- Demandeur principal (non-modifiable) -->
            <div class="flex items-center justify-between py-2 px-3 bg-indigo-50 rounded-lg border border-indigo-100 mb-2 text-sm">
              <div>
                <span class="font-medium text-gray-900">{{ reservation.user?.name }}</span>
                <span class="ml-2 text-xs text-gray-500">{{ reservation.user?.email }}</span>
              </div>
              <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-medium">Demandeur principal</span>
            </div>

            <!-- Participants ajoutés -->
            <div v-for="u in participants" :key="u.id"
                 class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg border border-gray-200 mb-2 text-sm">
              <div>
                <span class="font-medium text-gray-900">{{ u.name }}</span>
                <span class="ml-2 text-xs text-gray-500">{{ u.email }}</span>
              </div>
              <button v-if="isAdmin" @click="removeParticipant(u)"
                class="text-xs text-red-500 hover:text-red-700 px-2 py-0.5 rounded hover:bg-red-50 transition">✕ Retirer</button>
            </div>

            <!-- Ajouter un participant (admin) -->
            <template v-if="isAdmin">
              <div class="mt-3 relative">
                <input v-model="participantSearch" type="text"
                  placeholder="Rechercher un utilisateur à ajouter…"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none" />
                <div v-if="participantSearch.trim() && participantCandidates.length"
                     class="absolute z-10 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                  <button v-for="u in participantCandidates" :key="u.id"
                    @click="addParticipant(u)"
                    :disabled="participantAdding"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-indigo-50 text-left transition disabled:opacity-50">
                    <span class="font-medium text-gray-900">{{ u.name }}</span>
                    <span class="text-xs text-gray-500">{{ u.email }}</span>
                  </button>
                </div>
                <p v-if="participantSearch.trim() && !participantCandidates.length"
                   class="mt-1 text-xs text-gray-400">Aucun utilisateur trouvé</p>
              </div>
              <p v-if="participantError" class="mt-1 text-xs text-red-600">{{ participantError }}</p>
            </template>
          </div>

          <!-- Signature superviseur affichée (après approbation) -->
          <div v-if="reservation.supervisor_signature && reservation.status !== 'awaiting_supervisor'"
               class="bg-white rounded-xl shadow-sm border p-6">
            <h2 class="font-semibold text-gray-900 mb-1 flex items-center gap-2">
              ✍️ Signature du responsable
              <span class="text-xs font-normal text-green-600 bg-green-50 px-2 py-0.5 rounded-full">
                Validée le {{ reservation.supervisor_approved_at_formatted }}
              </span>
            </h2>
            <div class="border rounded-lg p-2 inline-block bg-gray-50 mt-3">
              <img :src="reservation.supervisor_signature" alt="Signature responsable" class="max-h-32 max-w-full" />
            </div>
          </div>

          <!-- Liste des équipements -->
          <div class="bg-white rounded-xl shadow-sm border p-6">
            <h2 class="font-semibold text-gray-900 mb-4">Équipements ({{ reservation.assets?.length }})</h2>
            <div class="space-y-3">
              <div v-for="asset in reservation.assets" :key="asset.id"
                class="flex items-center justify-between p-3 bg-gray-50 rounded-lg gap-3">
                <!-- Miniature équipement -->
                <AssetThumbnail :src="asset.image_url" :alt="asset.name" size="md" />
                <div class="min-w-0 flex-1">
                  <p class="font-medium text-sm">{{ asset.name }}</p>
                  <p class="text-xs text-gray-500">{{ asset.asset_tag }} • {{ asset.category?.name ?? '—' }}</p>
                </div>

                <!-- Zone statut / action -->
                <div class="flex items-center gap-2 flex-shrink-0">
                  <!-- Retourné -->
                  <span v-if="asset.pivot?.returned_at"
                    class="text-xs text-green-600 font-medium">✓ Retourné</span>

                  <!-- Réservation active : retour par asset (admin seulement) -->
                  <template v-else-if="isAdmin && reservation.status === 'active'">
                    <span v-if="asset.qr_code"
                      class="text-xs text-blue-400 font-medium bg-blue-50 px-2 py-1 rounded">
                      🔍 Scan requis
                    </span>
                    <button v-else
                      @click="openReturnModal(asset)"
                      :disabled="manualReturning === asset.id"
                      class="text-xs bg-green-100 text-green-700 hover:bg-green-200 px-2 py-1 rounded-lg font-medium transition disabled:opacity-50">
                      {{ manualReturning === asset.id ? '…' : '↩ Retour manuel' }}
                    </button>
                  </template>

                  <!-- Réservation approuvée : statut attribution par asset (admin seulement) -->
                  <template v-else-if="isAdmin && reservation.status === 'approved'">
                    <span v-if="asset.pivot?.checkout_at"
                      class="text-xs text-blue-600 font-medium">✓ Attribué</span>
                    <span v-else class="text-xs text-gray-400">⏳ À attribuer</span>
                  </template>
                </div>
              </div>
            </div>

            <!-- Lien attribution si approuvée (admin) -->
            <div v-if="isAdmin && reservation.status === 'approved'"
                 class="mt-4 pt-4 border-t flex items-center gap-2 text-sm text-blue-600">
              <span>📦</span>
              <span>Attribuez les équipements depuis la</span>
              <a :href="route('attribution.index')" class="font-medium underline hover:text-blue-800">
                page Attribution
              </a>
            </div>

            <!-- Info retour par scan si active (admin) -->
            <div v-if="isAdmin && reservation.status === 'active'"
                 class="mt-4 pt-4 border-t flex items-center gap-2 text-sm text-gray-500">
              <span>↩️</span>
              <span>Les équipements avec étiquette doivent être retournés par</span>
              <a :href="route('returns.index')" class="font-medium underline hover:text-gray-700">
                scan (page Retours)
              </a>
            </div>

            <!-- ── Admin / Propriétaire : ajouter / retirer un equipement ── -->
            <!-- Admin : tous statuts sauf returned/cancelled/rejected -->
            <!-- Propriétaire : uniquement pending ou awaiting_supervisor (sans re-validation superviseur) -->
            <div v-if="(isAdmin && !['returned','cancelled','rejected'].includes(reservation.status))
                     || (!isAdmin && isOwner && ['pending','awaiting_supervisor'].includes(reservation.status))"
                 class="mt-4 pt-4 border-t">

              <!-- Boutons retirer (sur chaque asset non sorti) -->
              <div class="mb-3 space-y-1">
                <div v-for="asset in reservation.assets.filter(a => !a.pivot?.checkout_at)" :key="'rm-'+asset.id"
                  class="flex items-center justify-between text-xs text-gray-500 bg-gray-50 rounded px-2 py-1">
                  <span class="font-mono">{{ asset.asset_tag }} — {{ asset.name }}</span>
                  <button
                    @click="removeAssetFromReservation(asset)"
                    :disabled="removingAssetId === asset.id"
                    class="ml-3 text-red-500 hover:text-red-700 font-medium disabled:opacity-50 flex-shrink-0">
                    {{ removingAssetId === asset.id ? '…' : '✕ Retirer' }}
                  </button>
                </div>
              </div>

              <!-- Toggle panneau ajout -->
              <button @click="showAddAssetPanel = !showAddAssetPanel"
                class="flex items-center gap-1.5 text-xs text-blue-600 hover:text-blue-800 font-medium">
                <span>{{ showAddAssetPanel ? '▲' : '▼' }}</span>
                Ajouter un équipement à la réservation
              </button>

              <!-- Panneau recherche -->
              <div v-if="showAddAssetPanel" class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <label class="block text-xs font-medium text-gray-700 mb-1.5">
                  Rechercher par nom ou tag
                </label>
                <input
                  v-model="assetSearch"
                  type="text"
                  placeholder="Ex: PC-001, MacBook..."
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                />
                <div v-if="assetSearchLoading" class="mt-2 text-xs text-gray-400">Recherche...</div>
                <div v-else-if="assetSearch && assetSearchResults.length === 0" class="mt-2 text-xs text-gray-400">
                  Aucun équipement disponible pour cette période.
                </div>
                <div v-if="assetSearchResults.length > 0" class="mt-2 space-y-1 max-h-40 overflow-y-auto">
                  <div v-for="a in assetSearchResults" :key="a.id"
                    class="flex items-center justify-between bg-white rounded px-2 py-1.5 border text-xs">
                    <span>
                      <span class="font-medium">{{ a.name }}</span>
                      <span class="text-gray-400 ml-1.5 font-mono">{{ a.asset_tag }}</span>
                      <span v-if="a.category" class="text-gray-400 ml-1">· {{ a.category }}</span>
                    </span>
                    <button
                      @click="addAssetToReservation(a)"
                      :disabled="addingAssetId === a.id"
                      class="ml-3 text-xs bg-blue-600 text-white hover:bg-blue-700 px-2 py-1 rounded font-medium disabled:opacity-50 flex-shrink-0">
                      {{ addingAssetId === a.id ? '…' : '+ Ajouter' }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ══ Formulaires de sortie ══ -->
          <div v-if="checkoutForms.length > 0" class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-5 py-4 border-b bg-gray-50 flex items-center justify-between">
              <h2 class="font-semibold text-gray-900 flex items-center gap-2">
                📋 Formulaires
                <span class="text-xs font-normal text-gray-400">({{ checkoutForms.filter(f => f.has_response).length }}/{{ checkoutForms.length }} rempli(s))</span>
              </h2>
              <a v-if="isAdmin && checkoutForms.some(f => f.has_response)"
                 :href="route('reservations.print', reservation.id)"
                 class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                🖨️ Fiche de sortie
              </a>
            </div>
            <div class="divide-y divide-gray-100">
              <div v-for="cf in checkoutForms" :key="cf.id"
                   class="px-5 py-4 flex items-center justify-between gap-4">
                <div class="min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm font-medium text-gray-800">{{ cf.name }}</span>
                    <!-- Badge statut formulaire -->
                    <span v-if="cf.status === 'required_before'"
                          class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-medium">
                      🟡 Obligatoire avant
                    </span>
                    <span v-else-if="cf.status === 'required_after'"
                          class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">
                      🟢 Obligatoire après
                    </span>
                    <span v-else
                          class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 font-medium">
                      🔵 Optionnel
                    </span>
                    <!-- Badge rempli/non rempli -->
                    <span v-if="cf.has_response"
                          class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-medium">
                      ✓ Rempli
                    </span>
                    <span v-else
                          class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">
                      Non rempli
                    </span>
                  </div>
                  <p v-if="cf.has_response" class="text-xs text-gray-400 mt-0.5">
                    Soumis le {{ cf.submitted_at }}
                  </p>
                  <p v-else-if="cf.status === 'required_after' && ['approved','active'].includes(reservation.status)"
                     class="text-xs text-orange-500 mt-0.5">
                    ⚠️ Ce formulaire doit être rempli avant l'impression de la fiche de sortie
                  </p>
                </div>
                <a :href="route('checkout-forms.fill', [cf.id, reservation.id])"
                   class="flex-shrink-0 text-xs px-3 py-1.5 rounded-lg border transition font-medium"
                   :class="cf.has_response
                     ? 'border-gray-300 text-gray-600 hover:bg-gray-50'
                     : (cf.status !== 'optional' ? 'border-amber-400 text-amber-700 bg-amber-50 hover:bg-amber-100' : 'border-blue-300 text-blue-600 hover:bg-blue-50')">
                  {{ cf.has_response ? '✏️ Modifier' : '📝 Remplir' }}
                </a>
              </div>
            </div>
          </div>

          <!-- ══ Rapports utilisateurs (retours) ══ -->
          <div v-if="reservation.status === 'returned' || (isAdmin && reservation.assets?.length > 0)"
               class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-5 py-4 border-b bg-gray-50 flex items-center justify-between">
              <h2 class="font-semibold text-gray-900 flex items-center gap-2">
                📋 Rapports d'état
              </h2>
              <span class="text-xs text-gray-400">Signalez un problème constaté à l'utilisation</span>
            </div>
            <div class="divide-y divide-gray-100">
              <div v-for="asset in reservation.assets" :key="'rpt-'+asset.id"
                   class="px-5 py-3 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                  <AssetThumbnail :src="asset.image_url" :alt="asset.name" size="sm" />
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ asset.name }}</p>
                    <p class="text-xs text-gray-400 font-mono">{{ asset.asset_tag }}</p>
                  </div>
                </div>
                <button @click="openReportModal(asset)"
                        class="flex-shrink-0 text-xs px-3 py-1.5 rounded-lg border border-orange-300 text-orange-700 hover:bg-orange-50 transition font-medium">
                  ⚠️ Signaler
                </button>
              </div>
            </div>
          </div>

          <!-- ══ Chat messagerie ══ -->
          <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-5 py-4 border-b flex items-center justify-between">
              <h2 class="font-semibold text-gray-900 flex items-center gap-2">
                💬 Messages
                <span v-if="messages.length" class="text-xs font-normal text-gray-400">({{ messages.length }})</span>
              </h2>
              <span class="text-xs text-gray-400">Mise à jour auto toutes les 6 s</span>
            </div>

            <!-- Liste des messages -->
            <div ref="messagesContainer"
              class="h-64 overflow-y-auto px-4 py-3 space-y-3 bg-gray-50">
              <div v-if="messages.length === 0" class="flex items-center justify-center h-full text-gray-400 text-sm">
                Aucun message — démarrez la conversation
              </div>
              <div v-for="msg in messages" :key="msg.id"
                :class="['flex', msg.is_mine ? 'justify-end' : 'justify-start']">
                <div :class="['max-w-xs lg:max-w-sm rounded-2xl px-3 py-2 text-sm',
                  msg.is_mine
                    ? 'bg-blue-600 text-white rounded-br-none'
                    : 'bg-white border border-gray-200 text-gray-800 rounded-bl-none shadow-sm']">
                  <p v-if="!msg.is_mine" class="text-xs font-semibold mb-0.5"
                    :class="msg.sender?.is_admin ? 'text-blue-600' : 'text-gray-600'">
                    {{ msg.sender?.is_admin ? '🔑 ' : '' }}{{ msg.sender?.name }}
                  </p>
                  <p class="break-words">{{ msg.body }}</p>
                  <p :class="['text-xs mt-1', msg.is_mine ? 'text-blue-200' : 'text-gray-400']">
                    {{ msg.created_at }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Zone de saisie -->
            <div class="px-4 py-3 border-t bg-white">
              <p v-if="chatError" class="text-xs text-red-600 mb-2">{{ chatError }}</p>
              <div class="flex gap-2">
                <input
                  v-model="newMessage"
                  @keyup.enter.exact="sendMessage"
                  type="text"
                  placeholder="Écrire un message… (Entrée pour envoyer)"
                  class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                  :disabled="chatSending"
                />
                <button
                  @click="sendMessage"
                  :disabled="chatSending || !newMessage.trim()"
                  class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition flex-shrink-0">
                  {{ chatSending ? '…' : 'Envoyer' }}
                </button>
              </div>
            </div>
          </div>

        </div>

        <!-- Sidebar statut -->
        <div class="space-y-4">
          <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
            <div class="text-4xl mb-2">{{ statusIcon }}</div>
            <p class="font-semibold text-gray-900 text-sm">{{ statusDescription }}</p>

            <!-- Timeline visuelle -->
            <div class="mt-4 space-y-2 text-left">
              <div class="flex items-center gap-2 text-xs"
                :class="['awaiting_supervisor','pending','approved','active','returned'].includes(reservation.status) ? 'text-purple-700' : 'text-gray-400'">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                  :class="['awaiting_supervisor','pending','approved','active','returned'].includes(reservation.status) ? 'bg-purple-100 text-purple-700' : 'bg-gray-100'">1</span>
                Validé par responsable
              </div>
              <div class="flex items-center gap-2 text-xs"
                :class="['pending','approved','active','returned'].includes(reservation.status) ? 'text-amber-700' : 'text-gray-400'">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                  :class="['pending','approved','active','returned'].includes(reservation.status) ? 'bg-amber-100 text-amber-700' : 'bg-gray-100'">2</span>
                En attente admin
              </div>
              <div class="flex items-center gap-2 text-xs"
                :class="['approved','active','returned'].includes(reservation.status) ? 'text-blue-700' : 'text-gray-400'">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                  :class="['approved','active','returned'].includes(reservation.status) ? 'bg-blue-100 text-blue-700' : 'bg-gray-100'">3</span>
                Validée par admin
              </div>
              <div class="flex items-center gap-2 text-xs"
                :class="['active','returned'].includes(reservation.status) ? 'text-green-700' : 'text-gray-400'">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                  :class="['active','returned'].includes(reservation.status) ? 'bg-green-100 text-green-700' : 'bg-gray-100'">4</span>
                Matériel retiré
              </div>
              <div class="flex items-center gap-2 text-xs"
                :class="reservation.status === 'returned' ? 'text-gray-700' : 'text-gray-400'">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                  :class="reservation.status === 'returned' ? 'bg-gray-200 text-gray-700' : 'bg-gray-100'">5</span>
                Matériel retourné
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : refus admin ═══ -->
    <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <h3 class="font-bold text-lg mb-4">Refuser la réservation</h3>
        <textarea v-model="rejectNotes" rows="3" placeholder="Motif du refus (obligatoire)"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 outline-none mb-4" />
        <div class="flex gap-3">
          <button @click="showRejectModal=false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="rejectReservation" :disabled="!rejectNotes.trim() || processing === 'reject'"
            class="flex-1 bg-red-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
            Refuser
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : refus superviseur ═══ -->
    <div v-if="showSupervisorRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <h3 class="font-bold text-lg mb-4">Refuser la demande</h3>
        <p class="text-sm text-gray-500 mb-3">L'utilisateur sera notifié du refus avec le motif indiqué.</p>
        <textarea v-model="supervisorRejectNotes" rows="3" placeholder="Motif du refus (obligatoire)"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 outline-none mb-4" />
        <div class="flex gap-3">
          <button @click="showSupervisorRejectModal=false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="confirmSupervisorReject"
            :disabled="!supervisorRejectNotes.trim() || processing === 'supervisor-reject'"
            class="flex-1 bg-red-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
            Refuser
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : modifier les dates ═══ -->
    <div v-if="showEditDatesModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">Modifier les dates</h3>
          <button @click="showEditDatesModal=false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date de début</label>
            <input v-model="editDatesForm.start_date" type="datetime-local"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date de fin</label>
            <input v-model="editDatesForm.end_date" type="datetime-local"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>
          <p v-if="editDatesError" class="text-red-500 text-sm">{{ editDatesError }}</p>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="showEditDatesModal=false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitEditDates" :disabled="processing === 'dates'"
            class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ processing === 'dates' ? 'Sauvegarde…' : 'Enregistrer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : édition admin complète ═══ -->
    <div v-if="showAdminEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 overflow-y-auto max-h-[90vh]">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">✏️ Modifier la réservation</h3>
          <button @click="showAdminEditModal=false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <div class="space-y-4">
          <!-- Titre -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Titre <span class="text-red-500">*</span></label>
            <input v-model="adminEditForm.title" type="text"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
          </div>

          <!-- Statut -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
            <select v-model="adminEditForm.status"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
              <option value="awaiting_supervisor">En attente du responsable</option>
              <option value="pending">En attente (admin)</option>
              <option value="approved">Validée</option>
              <option value="active">En cours (matériel sorti)</option>
              <option value="returned">Retournée</option>
              <option value="cancelled">Annulée</option>
              <option value="rejected">Refusée</option>
            </select>
          </div>

          <!-- Dates -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date début <span class="text-red-500">*</span></label>
              <input v-model="adminEditForm.start_date" type="datetime-local"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date fin <span class="text-red-500">*</span></label>
              <input v-model="adminEditForm.end_date" type="datetime-local"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
            </div>
          </div>

          <!-- Notes utilisateur -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes utilisateur</label>
            <textarea v-model="adminEditForm.notes" rows="2" placeholder="Commentaire utilisateur…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none resize-none" />
          </div>

          <!-- Notes admin -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes admin / responsable</label>
            <textarea v-model="adminEditForm.admin_notes" rows="2" placeholder="Motif, remarques internes…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none resize-none" />
          </div>

          <!-- ── Équipements de la réservation ── -->
          <div class="border-t pt-4">
            <p class="text-sm font-medium text-gray-700 mb-2">
              Équipements
              <span class="ml-1 text-indigo-600 font-semibold">({{ reservation.assets?.length ?? 0 }})</span>
            </p>

            <!-- Liste actuelle avec boutons Retirer -->
            <div v-if="reservation.assets?.length" class="space-y-1 mb-3 max-h-32 overflow-y-auto">
              <div v-for="asset in reservation.assets" :key="'me-'+asset.id"
                   class="flex items-center justify-between px-2 py-1.5 bg-gray-50 rounded-lg border text-xs">
                <span class="font-mono text-gray-600 truncate">{{ asset.asset_tag }} — {{ asset.name }}</span>
                <button
                  v-if="!asset.pivot?.checkout_at"
                  @click="modalRemoveAsset(asset)"
                  :disabled="modalRemovingId === asset.id"
                  class="ml-2 text-red-500 hover:text-red-700 font-medium disabled:opacity-50 flex-shrink-0 text-xs">
                  {{ modalRemovingId === asset.id ? '…' : '✕' }}
                </button>
                <span v-else class="ml-2 text-green-600 text-xs flex-shrink-0">✓ Sorti</span>
              </div>
            </div>

            <!-- Recherche pour ajouter -->
            <div class="relative">
              <input v-model="modalAssetSearch" type="text"
                     placeholder="Ajouter un équipement (nom ou tag)…"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-indigo-500 outline-none" />
              <div v-if="modalAssetLoading" class="mt-1 text-xs text-gray-400 px-1">Recherche…</div>
              <div v-if="modalAssetResults.length" class="mt-1 border border-gray-200 rounded-lg divide-y max-h-36 overflow-y-auto bg-white shadow-sm">
                <div v-for="a in modalAssetResults" :key="'mr-'+a.id"
                     class="flex items-center justify-between px-3 py-2 hover:bg-indigo-50 text-xs">
                  <span>
                    <span class="font-medium text-gray-900">{{ a.name }}</span>
                    <span class="text-gray-400 font-mono ml-1.5">{{ a.asset_tag }}</span>
                  </span>
                  <button @click="modalAddAsset(a)"
                          :disabled="modalAddingId === a.id"
                          class="ml-2 flex-shrink-0 text-xs bg-indigo-600 text-white px-2 py-1 rounded font-medium hover:bg-indigo-700 disabled:opacity-50">
                    {{ modalAddingId === a.id ? '…' : '+ Ajouter' }}
                  </button>
                </div>
              </div>
              <p v-if="modalAssetSearch && !modalAssetLoading && !modalAssetResults.length"
                 class="mt-1 text-xs text-gray-400 px-1">Aucun équipement disponible correspondant.</p>
            </div>
          </div>

          <p v-if="adminEditError" class="text-red-500 text-sm bg-red-50 border border-red-200 rounded px-3 py-2">{{ adminEditError }}</p>
        </div>

        <div class="flex gap-3 mt-5">
          <button @click="showAdminEditModal=false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitAdminEdit" :disabled="processing === 'admin-edit'"
            class="flex-1 bg-indigo-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
            {{ processing === 'admin-edit' ? 'Sauvegarde…' : 'Enregistrer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : fiche retour avec état ═══ -->
    <div v-if="showReturnModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">↩ Retour de matériel</h3>
          <button @click="showReturnModal=false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <!-- Résumé équipement -->
        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border mb-4">
          <AssetThumbnail :src="returnModalAsset?.image_url" :alt="returnModalAsset?.name" size="sm" />
          <div>
            <p class="font-medium text-sm">{{ returnModalAsset?.name }}</p>
            <p class="text-xs text-gray-500">{{ returnModalAsset?.asset_tag }}</p>
          </div>
        </div>

        <!-- État -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">État constaté <span class="text-red-500">*</span></label>
          <div class="space-y-2">
            <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition"
                   :class="returnCondition === 'good' ? 'border-green-400 bg-green-50' : 'border-gray-200 hover:border-gray-300'">
              <input type="radio" v-model="returnCondition" value="good" class="text-green-600" />
              <span class="text-sm font-medium text-green-800">✅ Bon état</span>
            </label>
            <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition"
                   :class="returnCondition === 'damaged' ? 'border-orange-400 bg-orange-50' : 'border-gray-200 hover:border-gray-300'">
              <input type="radio" v-model="returnCondition" value="damaged" class="text-orange-600" />
              <span class="text-sm font-medium text-orange-800">⚠️ Endommagé</span>
            </label>
            <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition"
                   :class="returnCondition === 'lost' ? 'border-red-400 bg-red-50' : 'border-gray-200 hover:border-gray-300'">
              <input type="radio" v-model="returnCondition" value="lost" class="text-red-600" />
              <span class="text-sm font-medium text-red-800">❌ Perdu — L'équipement sera mis en maintenance</span>
            </label>
          </div>
        </div>

        <!-- Notes -->
        <div class="mb-5">
          <label class="block text-sm font-medium text-gray-700 mb-1">Observations <span class="text-gray-400 font-normal">(optionnel)</span></label>
          <textarea v-model="returnNotes" rows="2" placeholder="Rayures, panne, détails de la perte…"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500 outline-none resize-none" />
        </div>

        <div class="flex gap-3">
          <button @click="showReturnModal=false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitManualReturn"
            class="flex-1 py-2 rounded-lg text-sm font-medium transition"
            :class="returnCondition === 'good' ? 'bg-green-600 text-white hover:bg-green-700'
                  : returnCondition === 'damaged' ? 'bg-orange-500 text-white hover:bg-orange-600'
                  : 'bg-red-600 text-white hover:bg-red-700'">
            Confirmer le retour
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : suppression définitive (admin) ═══ -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center gap-3 mb-4">
          <span class="text-2xl">🗑</span>
          <h3 class="font-bold text-lg text-gray-900">Supprimer la réservation</h3>
        </div>

        <!-- Avertissement selon statut -->
        <div v-if="reservation.status === 'active'"
             class="mb-4 bg-orange-50 border border-orange-200 rounded-lg p-3 text-sm text-orange-800">
          ⚠️ <strong>Attention :</strong> cette réservation est actuellement <strong>active</strong>.
          La suppression libérera automatiquement tous les équipements encore en sortie.
        </div>
        <div v-else
             class="mb-4 bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-800">
          ⚠️ Cette action est <strong>irréversible</strong>. La réservation et toutes ses données seront
          définitivement supprimées.
        </div>

        <div class="mb-5 bg-gray-50 rounded-lg p-3 text-sm space-y-1">
          <div class="flex justify-between">
            <span class="text-gray-500">Réservation</span>
            <span class="font-medium">{{ reservation.title }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Demandeur</span>
            <span>{{ reservation.user?.name }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Équipements</span>
            <span>{{ reservation.assets?.length }}</span>
          </div>
        </div>

        <div class="flex gap-3">
          <button @click="showDeleteModal = false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">
            Annuler
          </button>
          <button @click="deleteReservation" :disabled="processing === 'delete'"
            class="flex-1 bg-red-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
            {{ processing === 'delete' ? 'Suppression…' : '🗑 Supprimer définitivement' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : annulation admin ═══ -->
    <div v-if="showAdminCancelModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center gap-3 mb-4">
          <span class="text-2xl">🚫</span>
          <h3 class="font-bold text-lg text-gray-900">Annuler la réservation</h3>
        </div>

        <div v-if="['approved','active'].includes(reservation.status)"
             class="mb-4 bg-orange-50 border border-orange-200 rounded-lg p-3 text-sm text-orange-800">
          ⚠️ <strong>Attention :</strong> cette réservation est
          <strong>{{ reservation.status === 'active' ? 'active (matériel sorti)' : 'validée' }}</strong>.
          L'annulation libérera automatiquement les équipements concernés et notifiera l'utilisateur par email.
        </div>

        <div class="mb-4 bg-gray-50 rounded-lg p-3 text-sm space-y-1">
          <div class="flex justify-between">
            <span class="text-gray-500">Réservation</span>
            <span class="font-medium">{{ reservation.title }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">Demandeur</span>
            <span>{{ reservation.user?.name }}</span>
          </div>
        </div>

        <div class="mb-5">
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Motif de l'annulation <span class="text-gray-400 font-normal">(optionnel)</span>
          </label>
          <textarea v-model="adminCancelReason" rows="3"
            placeholder="Ex : Double réservation, créneaux réattribués, problème technique…"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-400 outline-none resize-none" />
          <p class="text-xs text-gray-400 mt-1">Le motif sera communiqué à l'utilisateur dans l'email de notification.</p>
        </div>

        <div class="flex gap-3">
          <button @click="showAdminCancelModal = false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">
            Retour
          </button>
          <button @click="adminCancelReservation" :disabled="processing === 'admin-cancel'"
            class="flex-1 bg-orange-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-orange-700 disabled:opacity-50">
            {{ processing === 'admin-cancel' ? 'Annulation…' : '🚫 Confirmer l\'annulation' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : rapport utilisateur ═══ -->
    <div v-if="showReportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">⚠️ Signaler un problème</h3>
          <button @click="showReportModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div v-if="reportModalAsset" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border mb-4">
          <AssetThumbnail :src="reportModalAsset.image_url" :alt="reportModalAsset.name" size="sm" />
          <div>
            <p class="font-medium text-sm">{{ reportModalAsset.name }}</p>
            <p class="text-xs text-gray-400 font-mono">{{ reportModalAsset.asset_tag }}</p>
          </div>
        </div>
        <div class="space-y-3">
          <!-- Type -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Type de signalement <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-2 gap-2">
              <label v-for="t in reportTypes" :key="t.value"
                     class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer transition text-sm font-medium"
                     :class="reportForm.type === t.value ? 'border-indigo-400 bg-indigo-50 text-indigo-800' : 'border-gray-200 hover:border-gray-300 text-gray-700'">
                <input type="radio" v-model="reportForm.type" :value="t.value" class="sr-only" />
                {{ t.label }}
              </label>
            </div>
          </div>
          <!-- Titre -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Titre court <span class="text-red-500">*</span></label>
            <input v-model="reportForm.title" type="text" maxlength="255"
                   placeholder="Ex : Objectif rayé, Câble manquant…"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none" />
          </div>
          <!-- Contenu -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description <span class="text-red-500">*</span></label>
            <textarea v-model="reportForm.content" rows="4" maxlength="5000"
                      placeholder="Décrivez le problème en détail…"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none" />
          </div>
          <p v-if="reportError" class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ reportError }}</p>
          <p v-if="reportSuccess" class="text-xs text-green-700 bg-green-50 px-3 py-2 rounded-lg font-medium">{{ reportSuccess }}</p>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="showReportModal = false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitReport" :disabled="reportSubmitting || !reportForm.type || !reportForm.title.trim() || !reportForm.content.trim()"
            class="flex-1 bg-indigo-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50">
            {{ reportSubmitting ? 'Envoi…' : '📤 Envoyer le rapport' }}
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>
