<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SignaturePad from '@/Components/SignaturePad.vue'
import SignPairQr from '@/Components/SignPairQr.vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useSound } from '@/composables/useSound'
import axios from 'axios'
import {
  UserGroupIcon, ClipboardDocumentListIcon,
  CheckCircleIcon, XCircleIcon, ClockIcon,
  UserIcon, BellAlertIcon, ExclamationTriangleIcon,
  ArrowPathIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  supervisedGroups:    Array,
  directSubordinates:  Array,
  pendingApprovals:    Array,
  recentReservations:  Array,
})

const { playSound } = useSound()
const currentUserId = computed(() => usePage().props.auth?.user?.id)

// ─── Signature ────────────────────────────────────────────────────────────────
const selectedReservation = ref(null)
const supervisorSignature = ref(null)
const supervisorSignError = ref('')
const processing          = ref('')
const showRejectModal     = ref(false)
const rejectNotes         = ref('')
const rejectingId         = ref(null)
const signMode            = ref('manual') // 'manual' | 'mobile'

function openApproval(reservation) {
  selectedReservation.value = reservation
  supervisorSignature.value = null
  supervisorSignError.value = ''
  signMode.value = 'manual'
}
function closeApproval() {
  selectedReservation.value = null
  supervisorSignature.value = null
  supervisorSignError.value = ''
  signMode.value = 'manual'
}
function onMobileSigned(dataUrl) {
  supervisorSignature.value = dataUrl
  // Rester en mode 'mobile' pour afficher l'aperçu — ne pas basculer vers le pad vide
}

function confirmApprove() {
  supervisorSignError.value = ''
  if (!supervisorSignature.value) {
    supervisorSignError.value = 'Votre signature est obligatoire.'
    return
  }
  processing.value = 'approve-' + selectedReservation.value.id
  router.post(route('reservations.supervisor-approve', selectedReservation.value.id), {
    supervisor_signature: supervisorSignature.value,
  }, {
    onSuccess: () => { closeApproval() },
    onError:   (e) => { supervisorSignError.value = Object.values(e).join(' ') },
    onFinish:  () => { processing.value = '' },
  })
}

function openReject(reservationId) {
  rejectingId.value     = reservationId
  rejectNotes.value     = ''
  showRejectModal.value = true
}
function confirmReject() {
  if (!rejectNotes.value.trim()) return
  processing.value = 'reject-' + rejectingId.value
  router.post(route('reservations.supervisor-reject', rejectingId.value), {
    admin_notes: rejectNotes.value,
  }, {
    onSuccess: () => { showRejectModal.value = false; rejectingId.value = null },
    onFinish:  () => { processing.value = '' },
  })
}

// ─── Urgence / couleurs ───────────────────────────────────────────────────────
function urgencyBorder(u) {
  return { critical: 'border-red-400', high: 'border-orange-400', medium: 'border-amber-300', low: 'border-purple-200' }[u] ?? 'border-purple-200'
}
function urgencyBg(u) {
  return { critical: 'bg-red-50', high: 'bg-orange-50', medium: 'bg-amber-50', low: 'bg-white' }[u] ?? 'bg-white'
}
function urgencyBadge(u) {
  const map = {
    critical: 'bg-red-600 text-white',
    high:     'bg-orange-500 text-white',
    medium:   'bg-amber-400 text-white',
    low:      'bg-purple-100 text-purple-700',
  }
  return map[u] ?? 'bg-purple-100 text-purple-700'
}
function urgencyLabel(u) {
  return { critical: '🔴 Urgent', high: '🟠 Important', medium: '🟡 À traiter', low: '🟣 En attente' }[u] ?? '🟣 En attente'
}

function statusColorClass(status) {
  const map = {
    awaiting_supervisor: 'bg-purple-100 text-purple-800',
    pending:             'bg-amber-100 text-amber-800',
    approved:            'bg-blue-100 text-blue-800',
    active:              'bg-green-100 text-green-800',
    returned:            'bg-gray-100 text-gray-700',
    cancelled:           'bg-red-100 text-red-700',
    rejected:            'bg-red-100 text-red-700',
  }
  return map[status] ?? 'bg-gray-100 text-gray-700'
}

// ─── Compteurs live ───────────────────────────────────────────────────────────
const ticker = ref(0)
let tickerInterval = null

function formatWaiting(createdAtTs) {
  void ticker.value // dépendance réactive
  const diffMs  = Date.now() - createdAtTs * 1000
  const diffMin = Math.floor(diffMs / 60000)
  if (diffMin < 60)   return `${diffMin} min`
  const diffH = Math.floor(diffMin / 60)
  if (diffH < 24)     return `${diffH}h${String(diffMin % 60).padStart(2, '0')}`
  return `${Math.floor(diffH / 24)}j ${diffH % 24}h`
}

function formatUntilStart(startTs) {
  void ticker.value
  const diffMs  = startTs * 1000 - Date.now()
  if (diffMs < 0) return 'passé'
  const diffMin = Math.floor(diffMs / 60000)
  if (diffMin < 60)   return `dans ${diffMin} min`
  const diffH = Math.floor(diffMin / 60)
  if (diffH < 24)     return `dans ${diffH}h${String(diffMin % 60).padStart(2, '0')}`
  return `dans ${Math.floor(diffH / 24)}j`
}

// ─── Polling & alertes ────────────────────────────────────────────────────────
const newAlertIds   = ref(new Set())   // IDs nouvellement arrivés (flash)
const knownIds      = ref(new Set(props.pendingApprovals.map(r => r.id)))
const isPageVisible = ref(!document.hidden)
let pollInterval    = null

async function pollPending() {
  if (!isPageVisible.value) return
  try {
    const { data } = await axios.get('/api/supervisor/pending-approvals')
    const incoming = new Set(data.ids)
    const freshIds = [...incoming].filter(id => !knownIds.value.has(id))

    if (freshIds.length > 0) {
      freshIds.forEach(id => newAlertIds.value.add(id))
      knownIds.value = new Set([...knownIds.value, ...freshIds])
      playSound('supervisor_request')
      sendBrowserNotification(freshIds.length)
      // Recharger la page Inertia pour afficher les nouvelles demandes
      router.reload({ only: ['pendingApprovals'] })
      // Effacer le flash après 6s
      setTimeout(() => { freshIds.forEach(id => newAlertIds.value.delete(id)) }, 6000)
    }

    // Retirer les IDs qui ne sont plus pending (traités)
    const removed = [...knownIds.value].filter(id => !incoming.has(id))
    if (removed.length > 0) {
      knownIds.value = incoming
      // Recharger si des demandes ont été traitées par quelqu'un d'autre
      if (freshIds.length === 0) router.reload({ only: ['pendingApprovals', 'recentReservations'] })
    }
  } catch {}
}

// ─── Notifications navigateur ─────────────────────────────────────────────────
function sendBrowserNotification(count) {
  if (!('Notification' in window) || Notification.permission !== 'granted') return
  new Notification('Demande de signature en attente', {
    body: `${count} nouvelle${count > 1 ? 's' : ''} demande${count > 1 ? 's' : ''} de vos élèves à valider.`,
    icon: '/favicon.ico',
    tag:  'supervisor-pending',
    requireInteraction: true,
  })
}

async function requestNotificationPermission() {
  if (!('Notification' in window)) return
  if (Notification.permission === 'default') {
    await Notification.requestPermission()
  }
}

// ─── Visibilité page ──────────────────────────────────────────────────────────
function onVisibilityChange() {
  isPageVisible.value = !document.hidden
  if (!document.hidden) pollPending() // poll immédiat au retour
}

// ─── Cycle de vie ─────────────────────────────────────────────────────────────
onMounted(() => {
  requestNotificationPermission()
  tickerInterval = setInterval(() => ticker.value++, 1000)
  pollInterval   = setInterval(pollPending, 30000)
  document.addEventListener('visibilitychange', onVisibilityChange)
})

onUnmounted(() => {
  clearInterval(tickerInterval)
  clearInterval(pollInterval)
  document.removeEventListener('visibilitychange', onVisibilityChange)
})
</script>

<template>
  <AppLayout title="Mon groupe">
    <div class="max-w-5xl space-y-8">

      <!-- ══ En-tête ══ -->
      <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3">
          <UserGroupIcon class="w-8 h-8 text-purple-600" />
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Mon groupe</h1>
            <p class="text-sm text-gray-500">Gestion des demandes de vos élèves et membres de groupe</p>
          </div>
        </div>
        <!-- Compteur demandes urgentes -->
        <div v-if="pendingApprovals.length > 0"
             class="flex items-center gap-2 bg-red-50 border border-red-300 text-red-700 px-4 py-2.5 rounded-xl shadow-sm">
          <BellAlertIcon class="w-5 h-5 animate-pulse flex-shrink-0" />
          <span class="font-bold text-sm">
            {{ pendingApprovals.length }} demande{{ pendingApprovals.length > 1 ? 's' : '' }} en attente
          </span>
        </div>
      </div>

      <!-- ══ Bandeau d'alerte urgente ══ -->
      <Transition name="slide-down">
        <div v-if="pendingApprovals.some(r => r.urgency === 'critical')"
             class="bg-red-600 text-white rounded-xl px-5 py-4 flex items-center gap-3 shadow-md">
          <ExclamationTriangleIcon class="w-6 h-6 flex-shrink-0 animate-pulse" />
          <div class="flex-1 min-w-0">
            <p class="font-bold text-sm">Action requise immédiatement !</p>
            <p class="text-red-100 text-xs mt-0.5">
              {{ pendingApprovals.filter(r => r.urgency === 'critical').length }}
              demande{{ pendingApprovals.filter(r => r.urgency === 'critical').length > 1 ? 's' : '' }}
              dont le début est imminent ou passé — validez avant de partir.
            </p>
          </div>
        </div>
      </Transition>

      <!-- ═══════════════════════════════════════════
           DEMANDES EN ATTENTE DE SIGNATURE
      ═══════════════════════════════════════════ -->
      <section>
        <div class="flex items-center gap-2 mb-4">
          <ClipboardDocumentListIcon class="w-5 h-5 text-purple-600" />
          <h2 class="text-lg font-semibold text-gray-900">Demandes en attente de votre signature</h2>
          <span v-if="pendingApprovals.length > 0"
                class="ml-1 bg-purple-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
            {{ pendingApprovals.length }}
          </span>
        </div>

        <!-- Aucune demande -->
        <div v-if="pendingApprovals.length === 0"
             class="bg-white rounded-xl border p-8 text-center text-gray-400">
          <CheckCircleIcon class="w-12 h-12 mx-auto mb-3 text-green-300" />
          <p class="font-medium">Aucune demande en attente</p>
          <p class="text-sm mt-1">Toutes les demandes de vos élèves ont été traitées.</p>
        </div>

        <!-- Liste des demandes en attente -->
        <div v-else class="space-y-4">
          <div v-for="res in pendingApprovals" :key="res.id"
               class="rounded-xl border-2 p-5 shadow-sm transition-all duration-500"
               :class="[
                 urgencyBorder(res.urgency),
                 urgencyBg(res.urgency),
                 newAlertIds.has(res.id) ? 'ring-2 ring-purple-400 ring-offset-2 new-alert-flash' : ''
               ]">

            <!-- Bandeau urgence + temps -->
            <div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
              <span :class="['inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full', urgencyBadge(res.urgency)]">
                {{ urgencyLabel(res.urgency) }}
              </span>
              <div class="flex items-center gap-3 text-xs text-gray-500 font-mono">
                <span class="flex items-center gap-1">
                  <ClockIcon class="w-3.5 h-3.5 text-gray-400" />
                  En attente depuis <strong class="ml-1"
                    :class="res.urgency === 'critical' ? 'text-red-600' : res.urgency === 'high' ? 'text-orange-600' : 'text-gray-700'">
                    {{ formatWaiting(res.created_at_ts) }}
                  </strong>
                </span>
                <span class="text-gray-300">·</span>
                <span :class="res.start_is_past ? 'text-red-600 font-bold' : 'text-gray-500'">
                  Début {{ formatUntilStart(res.start_date_ts) }}
                </span>
              </div>
            </div>

            <div class="flex items-start justify-between gap-4 flex-wrap">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                  <span class="font-semibold text-gray-900">{{ res.title }}</span>
                  <!-- Badge "nouveau" -->
                  <span v-if="newAlertIds.has(res.id)"
                        class="text-xs bg-purple-600 text-white px-2 py-0.5 rounded-full font-bold animate-pulse">
                    Nouveau !
                  </span>
                </div>
                <p class="text-sm text-gray-500 mb-2">
                  <UserIcon class="inline w-3.5 h-3.5 mr-1" />
                  <strong>{{ res.user.name }}</strong>
                  <span class="text-gray-400 ml-1">{{ res.user.email }}</span>
                </p>
                <div class="flex items-center gap-4 text-xs text-gray-500 mb-3 flex-wrap">
                  <span>📅 {{ res.start_date }} → {{ res.end_date }}</span>
                  <span v-if="res.kits?.length">🎒 {{ res.kits.length }} kit(s)</span>
                  <span v-if="res.standalone_assets?.length">📦 {{ res.standalone_assets.length }} équipement(s) individuel(s)</span>
                  <span v-if="!res.kits?.length && !res.standalone_assets?.length">📦 {{ res.assets_count }} équipement(s)</span>
                </div>

                <!-- Kits réservés -->
                <div v-if="res.kits?.length" class="mb-3 space-y-2">
                  <div v-for="kit in res.kits" :key="kit.id"
                       class="bg-purple-50 border border-purple-200 rounded-lg p-2.5">
                    <p class="text-xs font-semibold text-purple-800 mb-1.5">🎒 Kit : {{ kit.name }}</p>
                    <div class="flex flex-wrap gap-1">
                      <span v-for="a in kit.assets" :key="a.id"
                            class="text-xs bg-white border border-purple-100 text-gray-700 px-2 py-0.5 rounded font-mono">
                        {{ a.name }} <span class="text-gray-400">({{ a.asset_tag }})</span>
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Équipements individuels (hors kits) -->
                <div v-if="res.standalone_assets?.length" class="flex flex-wrap gap-1 mb-3">
                  <span v-for="a in res.standalone_assets" :key="a.id"
                        class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded font-mono">
                    {{ a.name }} <span class="text-gray-400">({{ a.asset_tag }})</span>
                  </span>
                </div>

                <p v-if="res.notes" class="text-xs text-gray-500 italic bg-white/70 px-3 py-2 rounded border border-gray-100">
                  Notes : {{ res.notes }}
                </p>
              </div>

              <!-- Actions -->
              <div class="flex gap-2 flex-shrink-0 flex-wrap">
                <Link :href="res.url"
                      class="border border-gray-300 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-gray-50 transition">
                  Détails
                </Link>
                <button @click="openReject(res.id)"
                        class="border border-red-300 text-red-600 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-red-50 transition">
                  ❌ Refuser
                </button>
                <button @click="openApproval(res)"
                        :class="['px-3 py-1.5 rounded-lg text-xs font-medium transition',
                          res.urgency === 'critical'
                            ? 'bg-red-600 hover:bg-red-700 text-white'
                            : 'bg-purple-600 hover:bg-purple-700 text-white']">
                  ✍️ Signer et approuver
                </button>
              </div>
            </div>

            <!-- Panneau de signature inline (si sélectionné) -->
            <div v-if="selectedReservation?.id === res.id"
                 class="mt-4 pt-4 border-t border-purple-200 space-y-4">

              <p class="text-sm font-semibold text-purple-900">
                Signez pour approuver la demande de {{ res.user.name }} :
              </p>

              <!-- Sélecteur de mode (masqué si signature déjà reçue) -->
              <div v-if="!supervisorSignature" class="flex rounded-lg border border-gray-200 overflow-hidden text-xs font-medium">
                <button @click="signMode = 'manual'"
                        :class="signMode === 'manual'
                          ? 'flex-1 bg-purple-600 text-white py-2 px-3'
                          : 'flex-1 bg-white text-gray-600 hover:bg-gray-50 py-2 px-3 transition'">
                  ✍️ Signature manuelle
                </button>
                <button @click="signMode = 'mobile'"
                        :class="signMode === 'mobile'
                          ? 'flex-1 bg-indigo-600 text-white py-2 px-3'
                          : 'flex-1 bg-white text-gray-600 hover:bg-gray-50 py-2 px-3 transition'">
                  📱 Signer depuis mobile
                </button>
              </div>

              <!-- Mode : signature manuelle -->
              <div v-if="signMode === 'manual'">
                <SignaturePad v-model="supervisorSignature" />
                <div class="mt-1">
                  <span v-if="!supervisorSignature" class="text-xs text-red-500 font-medium">⚠ Signature requise</span>
                  <span v-else class="text-xs text-green-600 font-medium">✓ Signature capturée</span>
                </div>
              </div>

              <!-- Mode : QR mobile -->
              <div v-else>
                <!-- Aperçu signature reçue du mobile -->
                <div v-if="supervisorSignature" class="space-y-3">
                  <div class="bg-green-50 border border-green-300 rounded-xl px-4 py-3 flex items-center gap-3">
                    <span class="text-green-600 text-xl flex-shrink-0">✅</span>
                    <p class="text-sm font-semibold text-green-800">Signature reçue depuis le mobile</p>
                  </div>
                  <div class="border border-gray-200 rounded-xl bg-white p-2">
                    <img :src="supervisorSignature" alt="Signature" class="w-full h-20 object-contain" />
                  </div>
                  <button @click="supervisorSignature = null"
                          class="text-xs text-gray-400 underline w-full text-center">
                    Recommencer (nouvelle signature)
                  </button>
                </div>
                <!-- QR code (si pas encore signé) -->
                <SignPairQr
                  v-else
                  :reservation-id="res.id"
                  :supervisor-id="currentUserId"
                  purpose="supervisor"
                  @signed="onMobileSigned"
                  @cancel="signMode = 'manual'"
                />
              </div>

              <p v-if="supervisorSignError" class="text-xs text-red-600">{{ supervisorSignError }}</p>

              <div class="flex gap-2">
                <button @click="closeApproval"
                        class="border border-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">
                  Annuler
                </button>
                <button @click="confirmApprove"
                        :disabled="!supervisorSignature || processing === 'approve-' + res.id"
                        :class="['px-4 py-2 rounded-lg text-sm font-medium transition',
                          supervisorSignature
                            ? 'bg-purple-600 text-white hover:bg-purple-700 disabled:opacity-50'
                            : 'bg-gray-200 text-gray-400 cursor-not-allowed']">
                  {{ processing === 'approve-' + res.id ? 'Envoi…' : '✅ Confirmer et transmettre à l\'admin' }}
                </button>
              </div>
            </div>

          </div>
        </div>
      </section>

      <!-- ═══════════════════════════════════════════
           UTILISATEURS SOUS MA SUPERVISION (supervisor_id)
      ═══════════════════════════════════════════ -->
      <section v-if="directSubordinates.length > 0">
        <div class="flex items-center gap-2 mb-4">
          <UserIcon class="w-5 h-5 text-indigo-600" />
          <h2 class="text-lg font-semibold text-gray-900">Les utilisateurs sous ma supervision</h2>
          <span class="ml-1 bg-indigo-100 text-indigo-700 text-xs font-bold px-2 py-0.5 rounded-full">
            {{ directSubordinates.length }}
          </span>
        </div>
        <div class="bg-white rounded-xl border shadow-sm p-5">
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
            <div v-for="sub in directSubordinates" :key="sub.id"
                 class="flex items-center gap-3 p-3 rounded-lg bg-indigo-50 border border-indigo-100">
              <div class="w-9 h-9 rounded-full bg-indigo-200 flex items-center justify-center flex-shrink-0 text-indigo-700 font-bold text-sm">
                {{ sub.name.charAt(0).toUpperCase() }}
              </div>
              <div class="min-w-0">
                <p class="font-medium text-gray-900 text-sm leading-tight truncate">{{ sub.name }}</p>
                <p class="text-xs text-gray-500 truncate">{{ sub.email }}</p>
                <p v-if="sub.department" class="text-xs text-indigo-600 font-medium">{{ sub.department }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ═══════════════════════════════════════════
           MES GROUPES SUPERVISÉS
      ═══════════════════════════════════════════ -->
      <section v-if="supervisedGroups.length > 0">
        <div class="flex items-center gap-2 mb-4">
          <UserGroupIcon class="w-5 h-5 text-blue-600" />
          <h2 class="text-lg font-semibold text-gray-900">Mes groupes</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="group in supervisedGroups" :key="group.id"
               class="bg-white rounded-xl border shadow-sm p-5">
            <h3 class="font-semibold text-gray-900 mb-1">{{ group.name }}</h3>
            <p v-if="group.description" class="text-xs text-gray-500 mb-3">{{ group.description }}</p>
            <p class="text-xs text-gray-400 mb-3">{{ group.members.length }} membre(s)</p>
            <div class="space-y-2">
              <div v-for="member in group.members" :key="member.id"
                   class="flex items-center gap-2 text-sm">
                <div class="w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                  <UserIcon class="w-4 h-4 text-blue-600" />
                </div>
                <div>
                  <p class="font-medium text-gray-800 leading-tight">{{ member.name }}</p>
                  <p class="text-xs text-gray-400">{{ member.email }}</p>
                </div>
              </div>
              <p v-if="group.members.length === 0" class="text-xs text-gray-400 italic">Aucun membre</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Message si aucun supervisé -->
      <section v-if="supervisedGroups.length === 0 && directSubordinates.length === 0">
        <div class="bg-white rounded-xl border p-6 text-center text-gray-400">
          <UserGroupIcon class="w-10 h-10 mx-auto mb-2 text-gray-300" />
          <p>Vous n'avez aucun membre sous votre responsabilité.</p>
        </div>
      </section>

      <!-- ═══════════════════════════════════════════
           RÉSERVATIONS RÉCENTES DES SUPERVISÉS
      ═══════════════════════════════════════════ -->
      <section>
        <div class="flex items-center gap-2 mb-4">
          <ClockIcon class="w-5 h-5 text-gray-500" />
          <h2 class="text-lg font-semibold text-gray-900">Réservations récentes</h2>
        </div>

        <div v-if="recentReservations.length === 0"
             class="bg-white rounded-xl border p-6 text-center text-gray-400">
          <p>Aucune réservation récente.</p>
        </div>

        <div v-else class="bg-white rounded-xl border shadow-sm overflow-hidden">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50 border-b text-xs text-gray-500 uppercase tracking-wide">
                <th class="px-4 py-3 text-left">Titre</th>
                <th class="px-4 py-3 text-left hidden sm:table-cell">Élève</th>
                <th class="px-4 py-3 text-left hidden md:table-cell">Période</th>
                <th class="px-4 py-3 text-left">Statut</th>
                <th class="px-4 py-3 text-left">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="res in recentReservations" :key="res.id"
                  class="border-b hover:bg-gray-50 transition">
                <td class="px-4 py-3 font-medium text-gray-900">{{ res.title }}</td>
                <td class="px-4 py-3 text-gray-600 hidden sm:table-cell">{{ res.user.name }}</td>
                <td class="px-4 py-3 text-gray-500 text-xs hidden md:table-cell">
                  {{ res.start_date }} → {{ res.end_date }}
                </td>
                <td class="px-4 py-3">
                  <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusColorClass(res.status)]">
                    {{ res.status_label }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <Link :href="res.url"
                        class="text-xs text-blue-600 hover:underline font-medium">
                    Voir →
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

    </div>

    <!-- ═══ Modal : refus superviseur ═══ -->
    <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <h3 class="font-bold text-lg mb-2">Refuser la demande</h3>
        <p class="text-sm text-gray-500 mb-4">L'élève sera notifié avec le motif que vous indiquez.</p>
        <textarea v-model="rejectNotes" rows="3" placeholder="Motif du refus (obligatoire)"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 outline-none mb-4" />
        <div class="flex gap-3">
          <button @click="showRejectModal=false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="confirmReject"
            :disabled="!rejectNotes.trim() || processing.startsWith('reject-')"
            class="flex-1 bg-red-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
            Refuser la demande
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>

<style scoped>
.slide-down-enter-active { transition: all 0.3s ease; }
.slide-down-leave-active { transition: all 0.2s ease; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-8px); }

@keyframes alert-flash {
  0%, 100% { box-shadow: 0 0 0 0 rgba(147, 51, 234, 0); }
  25%       { box-shadow: 0 0 0 6px rgba(147, 51, 234, 0.35); }
  75%       { box-shadow: 0 0 0 3px rgba(147, 51, 234, 0.15); }
}
.new-alert-flash {
  animation: alert-flash 1.5s ease 3;
}
</style>
