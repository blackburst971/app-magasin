<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import SignaturePad from '@/Components/SignaturePad.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import {
  UserGroupIcon, ClipboardDocumentListIcon,
  CheckCircleIcon, XCircleIcon, ClockIcon,
  UserIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  supervisedGroups:    Array,
  directSubordinates:  Array,
  pendingApprovals:    Array,
  recentReservations:  Array,
})

// État du panneau de signature pour une demande sélectionnée
const selectedReservation = ref(null)
const supervisorSignature = ref(null)
const supervisorSignError = ref('')
const processing          = ref('')
const showRejectModal     = ref(false)
const rejectNotes         = ref('')
const rejectingId         = ref(null)

function openApproval(reservation) {
  selectedReservation.value = reservation
  supervisorSignature.value = null
  supervisorSignError.value = ''
}

function closeApproval() {
  selectedReservation.value = null
  supervisorSignature.value = null
  supervisorSignError.value = ''
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
  rejectingId.value  = reservationId
  rejectNotes.value  = ''
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
</script>

<template>
  <AppLayout title="Mon groupe">
    <div class="max-w-5xl space-y-8">

      <!-- En-tête -->
      <div class="flex items-center gap-3">
        <UserGroupIcon class="w-8 h-8 text-purple-600" />
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Mon groupe</h1>
          <p class="text-sm text-gray-500">Gestion des demandes de vos élèves et membres de groupe</p>
        </div>
      </div>

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
               class="bg-white rounded-xl border-2 border-purple-200 p-5 shadow-sm">
            <div class="flex items-start justify-between gap-4 flex-wrap">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                  <span class="font-semibold text-gray-900">{{ res.title }}</span>
                  <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusColorClass(res.status)]">
                    {{ res.status_label }}
                  </span>
                </div>
                <p class="text-sm text-gray-500 mb-2">
                  <UserIcon class="inline w-3.5 h-3.5 mr-1" />
                  <strong>{{ res.user.name }}</strong>
                  <span class="text-gray-400 ml-1">{{ res.user.email }}</span>
                </p>
                <div class="flex items-center gap-4 text-xs text-gray-500 mb-3 flex-wrap">
                  <span>📅 {{ res.start_date }} → {{ res.end_date }}</span>
                  <span>📦 {{ res.assets_count }} équipement(s)</span>
                </div>

                <!-- Liste équipements demandés -->
                <div v-if="res.assets?.length" class="flex flex-wrap gap-1 mb-3">
                  <span v-for="a in res.assets" :key="a.id"
                        class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded font-mono">
                    {{ a.name }} ({{ a.asset_tag }})
                  </span>
                </div>

                <p v-if="res.notes" class="text-xs text-gray-500 italic bg-gray-50 px-3 py-2 rounded">
                  Notes : {{ res.notes }}
                </p>
              </div>

              <!-- Actions -->
              <div class="flex gap-2 flex-shrink-0">
                <Link :href="res.url"
                      class="border border-gray-300 text-gray-600 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-gray-50 transition">
                  Détails
                </Link>
                <button @click="openReject(res.id)"
                        class="border border-red-300 text-red-600 px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-red-50 transition">
                  ❌ Refuser
                </button>
                <button @click="openApproval(res)"
                        class="bg-purple-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-purple-700 transition">
                  ✍️ Signer et approuver
                </button>
              </div>
            </div>

            <!-- Panneau de signature inline (si sélectionné) -->
            <div v-if="selectedReservation?.id === res.id"
                 class="mt-4 pt-4 border-t border-purple-200">
              <p class="text-sm font-semibold text-purple-900 mb-2">
                Signez ci-dessous pour approuver la demande de {{ res.user.name }} :
              </p>
              <SignaturePad v-model="supervisorSignature" />
              <div class="mt-1 mb-3">
                <span v-if="!supervisorSignature" class="text-xs text-red-500 font-medium">⚠ Signature requise</span>
                <span v-else class="text-xs text-green-600 font-medium">✓ Signature capturée</span>
              </div>
              <p v-if="supervisorSignError" class="text-xs text-red-600 mb-2">{{ supervisorSignError }}</p>
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
           RESPONSABLES DIRECTS (supervisor_id)
      ═══════════════════════════════════════════ -->
      <section v-if="directSubordinates.length > 0">
        <div class="flex items-center gap-2 mb-4">
          <UserIcon class="w-5 h-5 text-indigo-600" />
          <h2 class="text-lg font-semibold text-gray-900">Mes responsables directs</h2>
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

      <!-- Message si ni groupes ni subordonné(s) direct(s) -->
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
