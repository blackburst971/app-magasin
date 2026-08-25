<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import SignaturePad from '@/Components/SignaturePad.vue'
import SignPairQr from '@/Components/SignPairQr.vue'
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import {
  PencilSquareIcon, UserIcon, CalendarIcon,
  ArchiveBoxIcon, CheckCircleIcon, XMarkIcon,
  ExclamationTriangleIcon, DevicePhoneMobileIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  reservations: { type: Array, default: () => [] },
})

const flash = computed(() => usePage().props.flash ?? {})

// ─── Modal signature ──────────────────────────────────────────────────────────
const modalOpen        = ref(false)
const selected         = ref(null)   // réservation sélectionnée
const selectedSupervisorId = ref('')
const signature        = ref(null)
const signError        = ref('')
const processing       = ref(false)
const signMode         = ref('manual') // 'manual' | 'mobile'

function openModal(reservation) {
  selected.value            = reservation
  selectedSupervisorId.value = reservation.supervisors.length === 1
    ? String(reservation.supervisors[0].id)
    : ''
  signature.value  = null
  signError.value  = ''
  modalOpen.value  = true
}

function closeModal() {
  modalOpen.value  = false
  selected.value   = null
  signature.value  = null
  signError.value  = ''
  processing.value = false
  signMode.value   = 'manual'
}

function onMobileSigned(dataUrl) {
  signature.value = dataUrl
  // Rester en mode 'mobile' pour afficher l'aperçu — ne pas basculer vers le pad vide
}

function submitSign() {
  signError.value = ''

  if (!selectedSupervisorId.value) {
    signError.value = 'Veuillez sélectionner le responsable qui signe.'
    return
  }
  if (!signature.value) {
    signError.value = 'La signature est obligatoire pour valider.'
    return
  }

  processing.value = true
  router.post(
    route('admin.proxy-sign.sign', selected.value.id),
    {
      supervisor_id:        selectedSupervisorId.value,
      supervisor_signature: signature.value,
    },
    {
      onSuccess: () => closeModal(),
      onError:   (e) => { signError.value = Object.values(e).join(' ') },
      onFinish:  () => { processing.value = false },
    }
  )
}

function supervisorName(reservation) {
  if (!selectedSupervisorId.value) return ''
  const sup = reservation.supervisors.find(s => String(s.id) === String(selectedSupervisorId.value))
  return sup?.name ?? ''
}
</script>

<template>
  <AppLayout>
    <div class="space-y-6">

      <!-- ══ Header ══ -->
      <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <PencilSquareIcon class="w-6 h-6 text-purple-600" />
            Signature responsable — proxy admin
          </h1>
          <p class="text-gray-500 text-sm mt-1">
            Faites signer le responsable directement sur cette page ou via un appareil mobile jumelé — pour débloquer une réservation sans que le responsable se connecte.
          </p>
        </div>
        <span class="inline-flex items-center gap-1.5 bg-purple-100 text-purple-700 border border-purple-200 px-3 py-1.5 rounded-full text-sm font-semibold">
          {{ reservations.length }} en attente
        </span>
      </div>

      <!-- Flash success -->
      <Transition name="fade">
        <div v-if="flash.success"
             class="flex items-center gap-2 bg-green-50 border border-green-300 text-green-800 rounded-lg px-4 py-3 text-sm font-medium">
          <CheckCircleIcon class="w-4 h-4 flex-shrink-0" />
          {{ flash.success }}
        </div>
      </Transition>

      <!-- ══ Liste vide ══ -->
      <div v-if="reservations.length === 0"
           class="bg-white rounded-xl border shadow-sm px-8 py-16 text-center">
        <CheckCircleIcon class="w-14 h-14 mx-auto text-green-400 opacity-60 mb-3" />
        <p class="text-lg font-medium text-gray-500">Aucune réservation en attente de signature</p>
        <p class="text-sm text-gray-400 mt-1">Toutes les demandes ont été traitées par les responsables.</p>
      </div>

      <!-- ══ Cartes réservations ══ -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div v-for="res in reservations" :key="res.id"
             class="bg-white rounded-xl border border-purple-200 shadow-sm overflow-hidden flex flex-col">

          <!-- En-tête carte -->
          <div class="px-5 py-4 bg-purple-50 border-b border-purple-100">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0 text-sm font-bold text-purple-700">
                  {{ res.user.name.charAt(0).toUpperCase() }}
                </div>
                <div class="min-w-0">
                  <h3 class="font-semibold text-gray-900 truncate">{{ res.title }}</h3>
                  <p class="text-xs text-gray-500 flex items-center gap-1 mt-0.5">
                    <UserIcon class="w-3 h-3" />
                    {{ res.user.name }}
                    <a :href="`mailto:${res.user.email}`" class="text-blue-500 hover:underline">({{ res.user.email }})</a>
                  </p>
                </div>
              </div>
              <span class="flex-shrink-0 text-xs font-semibold bg-purple-600 text-white px-2.5 py-1 rounded-full">
                En attente
              </span>
            </div>
          </div>

          <!-- Corps carte -->
          <div class="px-5 py-4 flex-1 space-y-3">

            <!-- Dates -->
            <div class="flex items-center gap-2 text-sm text-gray-600">
              <CalendarIcon class="w-4 h-4 text-gray-400 flex-shrink-0" />
              <span>{{ res.start_date }} → {{ res.end_date }}</span>
            </div>

            <!-- Équipements -->
            <div>
              <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1.5 flex items-center gap-1">
                <ArchiveBoxIcon class="w-3.5 h-3.5" />
                {{ res.assets_count }} équipement(s) demandé(s)
              </p>
              <div class="flex flex-wrap gap-1.5">
                <span v-for="a in res.assets" :key="a.id"
                      class="text-xs bg-gray-100 text-gray-700 border border-gray-200 px-2 py-0.5 rounded-full font-mono">
                  {{ a.name }}
                </span>
              </div>
            </div>

            <!-- Responsable(s) -->
            <div>
              <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1.5">
                Responsable(s)
              </p>
              <div v-if="res.supervisors.length === 0"
                   class="flex items-center gap-1.5 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                <ExclamationTriangleIcon class="w-4 h-4 flex-shrink-0" />
                Aucun responsable associé à cet étudiant
              </div>
              <div v-else class="flex flex-wrap gap-1.5">
                <span v-for="s in res.supervisors" :key="s.id"
                      class="text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 px-2.5 py-1 rounded-full font-medium">
                  {{ s.name }}
                </span>
              </div>
            </div>

            <!-- Notes -->
            <p v-if="res.notes" class="text-xs text-gray-500 italic border-t border-gray-100 pt-2">
              « {{ res.notes }} »
            </p>
          </div>

          <!-- Pied carte -->
          <div class="px-5 py-3 border-t border-gray-100 bg-gray-50">
            <button
              v-if="res.supervisors.length > 0"
              @click="openModal(res)"
              class="w-full flex items-center justify-center gap-2 bg-purple-600 hover:bg-purple-700 text-white font-semibold text-sm px-4 py-2.5 rounded-lg transition shadow-sm">
              <PencilSquareIcon class="w-4 h-4" />
              Faire signer le responsable
            </button>
            <p v-else class="text-center text-xs text-gray-400 italic py-1">
              Impossible — aucun responsable défini pour cet étudiant
            </p>
          </div>
        </div>
      </div>

    </div>

    <!-- ═══════════════════════════════════════════════
         Modal signature — optimisé tablette
    ═══════════════════════════════════════════════ -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="modalOpen && selected"
             class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl flex flex-col max-h-[95vh] overflow-y-auto">

            <!-- En-tête modal -->
            <div class="px-6 pt-6 pb-4 border-b bg-purple-50 rounded-t-2xl">
              <div class="flex items-center justify-between mb-3">
                <h2 class="text-xl font-bold text-purple-900 flex items-center gap-2">
                  <PencilSquareIcon class="w-5 h-5" />
                  Signature du responsable
                </h2>
                <button @click="closeModal"
                        class="text-gray-400 hover:text-gray-600 transition p-1 rounded-lg hover:bg-gray-100">
                  <XMarkIcon class="w-5 h-5" />
                </button>
              </div>

              <!-- Résumé réservation -->
              <div class="bg-white rounded-xl border border-purple-200 px-4 py-3 space-y-1.5">
                <p class="font-semibold text-gray-900 text-sm">{{ selected.title }}</p>
                <p class="text-xs text-gray-500 flex items-center gap-1">
                  <UserIcon class="w-3 h-3" />
                  Demandeur : <span class="font-medium text-gray-700 ml-1">{{ selected.user.name }}</span>
                </p>
                <p class="text-xs text-gray-500 flex items-center gap-1">
                  <CalendarIcon class="w-3 h-3" />
                  {{ selected.start_date }} → {{ selected.end_date }}
                </p>
                <div class="flex flex-wrap gap-1 pt-0.5">
                  <span v-for="a in selected.assets" :key="a.id"
                        class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-mono border border-gray-200">
                    {{ a.name }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Corps modal -->
            <div class="px-6 py-5 space-y-5">

              <!-- Sélection du responsable -->
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                  Responsable qui signe
                  <span class="text-red-500">*</span>
                </label>

                <!-- Un seul superviseur → affiché en lecture seule -->
                <div v-if="selected.supervisors.length === 1"
                     class="flex items-center gap-3 bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3">
                  <div class="w-9 h-9 rounded-full bg-indigo-600 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                    {{ selected.supervisors[0].name.charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <p class="font-semibold text-indigo-900">{{ selected.supervisors[0].name }}</p>
                    <p class="text-xs text-indigo-600">Responsable de l'étudiant</p>
                  </div>
                </div>

                <!-- Plusieurs superviseurs → sélecteur -->
                <div v-else class="space-y-2">
                  <label v-for="sup in selected.supervisors" :key="sup.id"
                         class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition"
                         :class="String(selectedSupervisorId) === String(sup.id)
                           ? 'border-indigo-400 bg-indigo-50'
                           : 'border-gray-200 hover:border-gray-300'">
                    <input type="radio" v-model="selectedSupervisorId" :value="String(sup.id)"
                           class="text-indigo-600 w-4 h-4" />
                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-sm flex-shrink-0">
                      {{ sup.name.charAt(0).toUpperCase() }}
                    </div>
                    <span class="font-medium text-gray-800 text-sm">{{ sup.name }}</span>
                  </label>
                </div>
              </div>

              <!-- Zone de signature -->
              <div class="space-y-3">
                <label class="block text-sm font-semibold text-gray-700">
                  Signature de
                  <span class="text-indigo-700">
                    {{ supervisorName(selected) || 'le responsable' }}
                  </span>
                  <span class="text-red-500">*</span>
                </label>

                <!-- Sélecteur de mode (désactivé si signature mobile déjà reçue) -->
                <div v-if="!signature" class="flex rounded-lg border border-gray-200 overflow-hidden text-xs font-medium">
                  <button @click="signMode = 'manual'"
                          :class="signMode === 'manual'
                            ? 'flex-1 bg-purple-600 text-white py-2 px-3'
                            : 'flex-1 bg-white text-gray-600 hover:bg-gray-50 py-2 px-3 transition'">
                    ✍️ Signature ici (tablette)
                  </button>
                  <button @click="signMode = 'mobile'"
                          :class="signMode === 'mobile'
                            ? 'flex-1 bg-indigo-600 text-white py-2 px-3'
                            : 'flex-1 bg-white text-gray-600 hover:bg-gray-50 py-2 px-3 transition'">
                    📱 Depuis son mobile
                  </button>
                </div>

                <!-- Mode manuel -->
                <div v-if="signMode === 'manual'">
                  <div class="border-2 border-dashed border-indigo-300 rounded-xl bg-indigo-50/30 p-1">
                    <SignaturePad v-model="signature" />
                  </div>
                  <p class="text-xs text-gray-400 mt-1.5 text-center">
                    Utilisez le doigt ou un stylet sur la tablette
                  </p>
                </div>

                <!-- Mode mobile QR -->
                <div v-else-if="selectedSupervisorId">
                  <!-- Aperçu signature reçue -->
                  <div v-if="signature" class="space-y-3">
                    <div class="bg-green-50 border border-green-300 rounded-xl px-4 py-3 flex items-center gap-3">
                      <span class="text-green-600 text-xl flex-shrink-0">✅</span>
                      <p class="text-sm font-semibold text-green-800">Signature reçue depuis le mobile</p>
                    </div>
                    <div class="border border-gray-200 rounded-xl bg-white p-2">
                      <img :src="signature" alt="Signature" class="w-full h-20 object-contain" />
                    </div>
                    <button @click="signature = null"
                            class="text-xs text-gray-400 underline w-full text-center">
                      Recommencer (nouvelle signature)
                    </button>
                  </div>
                  <!-- QR code (si pas encore signé) -->
                  <SignPairQr
                    v-else
                    :reservation-id="selected.id"
                    :supervisor-id="Number(selectedSupervisorId)"
                    purpose="admin_proxy"
                    @signed="onMobileSigned"
                    @cancel="signMode = 'manual'"
                  />
                </div>
                <div v-else class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs text-amber-700">
                  Sélectionnez d'abord le responsable ci-dessus pour générer le QR code.
                </div>
              </div>

              <!-- Message d'info -->
              <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 flex items-start gap-2">
                <ExclamationTriangleIcon class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5" />
                <p class="text-xs text-amber-800">
                  En signant, le responsable valide cette demande. La réservation sera transmise à l'administrateur pour approbation finale. Cette action est enregistrée.
                </p>
              </div>

              <!-- Erreur -->
              <Transition name="fade">
                <p v-if="signError"
                   class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm font-medium">
                  <ExclamationTriangleIcon class="w-4 h-4 flex-shrink-0" />
                  {{ signError }}
                </p>
              </Transition>
            </div>

            <!-- Pied modal -->
            <div class="px-6 pb-6 flex gap-3">
              <button @click="closeModal"
                      class="flex-1 border border-gray-300 text-gray-700 font-medium py-3 rounded-xl hover:bg-gray-50 transition text-sm">
                Annuler
              </button>
              <button @click="submitSign"
                      :disabled="processing"
                      class="flex-1 bg-purple-600 hover:bg-purple-700 disabled:opacity-60 text-white font-semibold py-3 rounded-xl transition text-sm flex items-center justify-center gap-2 shadow-sm">
                <CheckCircleIcon v-if="!processing" class="w-4 h-4" />
                <span>{{ processing ? 'Enregistrement…' : 'Valider la signature' }}</span>
              </button>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

  </AppLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }

.modal-fade-enter-active { transition: all 0.25s ease; }
.modal-fade-leave-active { transition: all 0.2s ease; }
.modal-fade-enter-from   { opacity: 0; transform: scale(0.95); }
.modal-fade-leave-to     { opacity: 0; transform: scale(0.95); }
</style>
