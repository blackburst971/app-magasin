<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import { PlusIcon, ShoppingCartIcon, TrashIcon, CheckIcon, LinkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  reservations: Object,
  filters:      Object,
  isAdmin:      Boolean,
  users:        { type: Array, default: () => [] },
})

// ─── Filters ─────────────────────────────────────────────────────────────────
const selectedStatus = ref(props.filters?.status ?? '')
const filter = () => router.get(route('reservations.index'), { status: selectedStatus.value || undefined }, { preserveState: true })

// ─── Bulk selection ───────────────────────────────────────────────────────────
const selected    = ref([])
const selectAll   = ref(false)

function toggleSelectAll() {
  if (selectAll.value) {
    selected.value = (props.reservations.data ?? []).map(r => r.id)
  } else {
    selected.value = []
  }
}

watch(() => props.reservations.data, () => {
  selected.value = []
  selectAll.value = false
})

const bulkStatusForm = useForm({
  ids:         [],
  status:      '',
  admin_notes: '',
})

function executeBulkUpdate() {
  bulkStatusForm.ids = [...selected.value]
  bulkStatusForm.post(route('reservations.bulk-update'), {
    onSuccess: () => { selected.value = []; selectAll.value = false; bulkStatusForm.reset() },
  })
}

const bulkDeleting = ref(false)
function executeBulkDelete() {
  if (!confirm(`Supprimer définitivement ${selected.value.length} réservation(s) ? Cette action est irréversible.`)) return
  bulkDeleting.value = true
  router.post(route('reservations.bulk-delete'), { ids: [...selected.value] }, {
    onSuccess: () => { selected.value = []; selectAll.value = false },
    onFinish:  () => { bulkDeleting.value = false },
  })
}

// ─── Bulk association ─────────────────────────────────────────────────────────
const showAssociateModal = ref(false)
const associateParentId = ref('')
const associating = ref(false)

function openAssociateModal() {
  if (selected.value.length < 2) {
    alert('Sélectionnez au moins 2 réservations à associer.')
    return
  }
  associateParentId.value = ''
  showAssociateModal.value = true
}

function executeAssociate() {
  if (!associateParentId.value) {
    alert('Veuillez choisir une réservation parente.')
    return
  }
  const childIds = selected.value.filter(id => id !== parseInt(associateParentId.value))
  if (childIds.length === 0) {
    alert('Aucune réservation enfant sélectionnée.')
    return
  }

  associating.value = true
  router.post(route('reservations.associate'), {
    parent_id: associateParentId.value,
    child_ids: childIds
  }, {
    onSuccess: () => {
      showAssociateModal.value = false
      selected.value = []
      selectAll.value = false
    },
    onFinish: () => { associating.value = false }
  })
}

function dissociateReservation(reservationId) {
  if (!confirm('Dissocier cette réservation de sa parente ?')) return
  router.post(route('reservations.dissociate', reservationId))
}

// ─── Admin create ─────────────────────────────────────────────────────────────
const showCreateModal  = ref(false)
const createAssets     = ref([])
const createLoading    = ref(false)
const createAssetSearch = ref('')

const filteredCreateAssets = computed(() => {
  const q = createAssetSearch.value.trim().toLowerCase()
  if (!q) return createAssets.value
  return createAssets.value.filter(a =>
    a.name.toLowerCase().includes(q) || (a.asset_tag ?? '').toLowerCase().includes(q)
  )
})

const createForm = useForm({
  user_id:    '',
  title:      '',
  start_date: '',
  end_date:   '',
  notes:      '',
  status:     'approved',
  asset_ids:  [],
})

async function openCreateModal() {
  createForm.reset()
  createForm.status    = 'approved'
  createForm.user_id   = ''
  createForm.asset_ids = []
  createAssets.value   = []
  createAssetSearch.value = ''
  showCreateModal.value = true
}

async function fetchAvailableAssets() {
  if (!createForm.start_date || !createForm.end_date) return
  createLoading.value = true
  try {
    const { data } = await axios.get(route('assets.available'), {
      params: { start: createForm.start_date, end: createForm.end_date },
    })
    createAssets.value = data
    // Remove selected assets that are no longer available
    createForm.asset_ids = createForm.asset_ids.filter(id => data.some(a => a.id === id))
  } catch {}
  createLoading.value = false
}

watch(() => [createForm.start_date, createForm.end_date], () => {
  if (createForm.start_date && createForm.end_date) fetchAvailableAssets()
})

function submitCreate() {
  createForm.post(route('reservations.store'), {
    onSuccess: () => { showCreateModal.value = false },
  })
}

function toggleAsset(id) {
  const idx = createForm.asset_ids.indexOf(id)
  if (idx === -1) createForm.asset_ids.push(id)
  else createForm.asset_ids.splice(idx, 1)
}

// ─── Admin cancel with reason ─────────────────────────────────────────────────
const showAdminCancelModal = ref(false)
const adminCancelTarget    = ref(null)
const adminCancelReason    = ref('')
const adminCancelling      = ref(false)

function openAdminCancelModal(reservation) {
  adminCancelTarget.value = reservation
  adminCancelReason.value = ''
  showAdminCancelModal.value = true
}

function submitAdminCancel() {
  if (!adminCancelTarget.value) return
  adminCancelling.value = true
  router.post(route('reservations.cancel', adminCancelTarget.value.id), { reason: adminCancelReason.value }, {
    onSuccess: () => { showAdminCancelModal.value = false },
    onFinish:  () => { adminCancelling.value = false },
  })
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
const statusOptions = [
  { v: 'pending',  l: 'En attente admin' },
  { v: 'approved', l: 'Validée (auto-approuvée)' },
]

const bulkStatuses = [
  { v: 'approved',  l: '✅ Valider' },
  { v: 'rejected',  l: '❌ Refuser' },
  { v: 'cancelled', l: '🚫 Annuler' },
  { v: 'pending',   l: '⏳ Remettre en attente' },
]

const filterBtns = [
  { v: '', l: 'Toutes' },
  { v: 'awaiting_supervisor', l: 'En attente responsable' },
  { v: 'pending',   l: 'En attente admin' },
  { v: 'approved',  l: 'Validées' },
  { v: 'active',    l: 'En cours' },
  { v: 'returned',  l: 'Retournées' },
  { v: 'cancelled', l: 'Annulées' },
]
</script>

<template>
  <AppLayout>
    <div>
      <!-- ── Header ── -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">
            {{ isAdmin ? 'Toutes les réservations' : 'Mes réservations' }}
          </h1>
          <p v-if="reservations.total !== undefined" class="text-sm text-gray-500 mt-0.5">
            {{ reservations.total }} réservation(s) au total
          </p>
        </div>
        <div class="flex gap-2">
          <Link v-if="!isAdmin" :href="route('cart.index')"
            class="flex items-center gap-2 text-sm border border-gray-300 px-3 py-2 rounded-lg hover:bg-gray-50 text-gray-700">
            <ShoppingCartIcon class="w-4 h-4" /> Nouveau panier
          </Link>
          <button v-if="isAdmin" @click="openCreateModal"
            class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            <PlusIcon class="w-4 h-4" />
            Créer une réservation
          </button>
        </div>
      </div>

      <!-- ── Status filter ── -->
      <div class="flex gap-2 mb-4 flex-wrap">
        <button v-for="s in filterBtns" :key="s.v"
          @click="selectedStatus = s.v; filter()"
          :class="['px-3 py-1.5 rounded-full text-xs font-medium transition',
            selectedStatus === s.v ? 'bg-blue-600 text-white' : 'bg-white border text-gray-600 hover:bg-gray-50']">
          {{ s.l }}
        </button>
      </div>

      <!-- ── Bulk action panel (admin only, visible when items selected) ── -->
      <Transition name="slide-down">
        <div v-if="isAdmin && selected.length > 0"
             class="mb-4 flex flex-wrap items-center gap-3 px-4 py-3 bg-blue-50 border border-blue-200 rounded-xl">
          <span class="text-sm font-semibold text-blue-800">
            {{ selected.length }} réservation(s) sélectionnée(s)
          </span>
          <div class="flex items-center gap-2 flex-1 flex-wrap">
            <select v-model="bulkStatusForm.status"
                    class="text-sm border border-blue-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
              <option value="">— Changer le statut —</option>
              <option v-for="s in bulkStatuses" :key="s.v" :value="s.v">{{ s.l }}</option>
            </select>
            <input v-model="bulkStatusForm.admin_notes" type="text"
                   placeholder="Note admin (optionnel)"
                   class="flex-1 min-w-32 text-sm border border-blue-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-blue-500 outline-none" />
            <button @click="executeBulkUpdate"
                    :disabled="!bulkStatusForm.status || bulkStatusForm.processing"
                    class="flex items-center gap-1.5 px-4 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition disabled:opacity-50 font-medium">
              <CheckIcon class="w-4 h-4" />
              {{ bulkStatusForm.processing ? 'Application…' : 'Appliquer' }}
            </button>
            <button @click="executeBulkDelete"
                    :disabled="bulkDeleting"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition disabled:opacity-50 font-medium">
              <TrashIcon class="w-4 h-4" />
              {{ bulkDeleting ? 'Suppression…' : 'Supprimer' }}
            </button>
            <button @click="openAssociateModal"
                    :disabled="selected.length < 2"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-purple-600 text-white text-sm rounded-lg hover:bg-purple-700 transition disabled:opacity-50 font-medium">
              <LinkIcon class="w-4 h-4" />
              Associer
            </button>
            <button @click="selected = []; selectAll = false"
                    class="text-xs text-gray-500 hover:text-gray-700 underline">
              Désélectionner
            </button>
          </div>
        </div>
      </Transition>

      <!-- ── Table ── -->
      <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="overflow-x-auto">
          <table class="text-sm" style="min-width:max-content;width:100%">
          <thead>
            <tr class="text-xs text-gray-500 uppercase bg-gray-50 border-b">
              <!-- Checkbox col for admin -->
              <th v-if="isAdmin" class="px-3 py-3 w-10">
                <input type="checkbox" v-model="selectAll" @change="toggleSelectAll"
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
              </th>
              <th class="px-4 py-3 text-left">Réservation</th>
              <th v-if="isAdmin" class="px-4 py-3 text-left hidden md:table-cell">Demandeur</th>
              <th class="px-4 py-3 text-left hidden lg:table-cell">Période</th>
              <th class="px-4 py-3 text-left">Statut</th>
              <th class="px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in reservations.data" :key="r.id"
                :class="['border-b hover:bg-gray-50 transition-colors',
                  selected.includes(r.id) ? 'bg-blue-50 hover:bg-blue-50' : '']">

              <!-- Checkbox -->
              <td v-if="isAdmin" class="px-3 py-3">
                <input type="checkbox" :value="r.id" v-model="selected"
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
              </td>

              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div class="flex-1">
                    <p class="font-medium text-gray-900">{{ r.title }}</p>
                    <p class="text-xs text-gray-500">{{ r.assets_count }} équipement(s)</p>
                  </div>
                  <span v-if="r.parent_reservation_id"
                        class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full flex items-center gap-1"
                        title="Cette réservation est associée à une réservation parente">
                    <LinkIcon class="w-3 h-3" />
                    Associée
                  </span>
                </div>
              </td>
              <td v-if="isAdmin" class="px-4 py-3 text-gray-600 hidden md:table-cell">{{ r.user }}</td>
              <td class="px-4 py-3 text-gray-500 text-xs hidden lg:table-cell">
                {{ r.start_date }}<br>{{ r.end_date }}
              </td>
              <td class="px-4 py-3">
                <StatusBadge :status="r.status" type="reservation" />
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-3 flex-wrap">
                  <Link :href="route('reservations.show', r.id)"
                        class="text-xs text-blue-600 hover:underline font-medium">
                    Voir
                  </Link>
                  <!-- User: cancel -->
                  <button v-if="!isAdmin && (r.status === 'pending' || r.status === 'awaiting_supervisor')"
                    @click="() => { if (confirm('Annuler cette réservation ?')) router.post(route('reservations.cancel', r.id)) }"
                    class="text-xs text-red-500 hover:underline">
                    Annuler
                  </button>
                  <!-- Admin: cancel with reason (for pending/approved/active) -->
                  <button v-if="isAdmin && ['pending','approved','active'].includes(r.status)"
                    @click="openAdminCancelModal(r)"
                    class="text-xs text-orange-500 hover:text-orange-700 hover:underline">
                    Annuler
                  </button>
                  <!-- Admin: dissociate -->
                  <button v-if="isAdmin && r.parent_reservation_id"
                    @click="dissociateReservation(r.id)"
                    class="text-xs text-purple-500 hover:text-purple-700 hover:underline flex items-center gap-0.5">
                    <LinkIcon class="w-3 h-3" /> Dissocier
                  </button>
                  <!-- Admin: delete -->
                  <button v-if="isAdmin"
                    @click="() => {
                      const warn = r.status === 'active'
                        ? 'Cette réservation est ACTIVE. Les équipements en sortie seront libérés. Supprimer ?'
                        : 'Supprimer définitivement cette réservation ?'
                      if (confirm(warn)) router.delete(route('reservations.destroy', r.id))
                    }"
                    class="text-xs text-red-400 hover:text-red-600 hover:underline flex items-center gap-0.5">
                    <TrashIcon class="w-3 h-3" /> Supprimer
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        </div>

        <!-- Empty state -->
        <div v-if="!reservations.data || reservations.data.length === 0" class="py-16 text-center text-gray-400">
          <p class="text-5xl mb-3">📭</p>
          <p class="font-medium text-gray-500 mb-1">Aucune réservation</p>
          <p v-if="!isAdmin" class="text-sm text-gray-400 mb-4">Ajoutez des équipements au panier pour créer votre première réservation.</p>
          <Link v-if="!isAdmin" :href="route('assets.index')"
            class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            Parcourir les équipements
          </Link>
        </div>
      </div>

      <!-- ── Pagination ── -->
      <div v-if="reservations.last_page > 1" class="flex items-center justify-between mt-4 text-sm">
        <p class="text-gray-500">Page {{ reservations.current_page }} / {{ reservations.last_page }}</p>
        <div class="flex gap-2">
          <Link v-if="reservations.prev_page_url" :href="reservations.prev_page_url"
            class="px-3 py-1.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">← Précédent</Link>
          <Link v-if="reservations.next_page_url" :href="reservations.next_page_url"
            class="px-3 py-1.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">Suivant →</Link>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════
         Modal — Admin create reservation
    ══════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between px-6 py-4 border-b sticky top-0 bg-white z-10">
            <h2 class="text-lg font-semibold text-gray-900">Créer une réservation</h2>
            <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
          </div>

          <form @submit.prevent="submitCreate" class="px-6 py-5 space-y-4">

            <!-- User selector -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Demandeur <span class="text-red-500">*</span>
              </label>
              <select v-model="createForm.user_id" required
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">— Sélectionner un utilisateur —</option>
                <option v-for="u in users" :key="u.id" :value="u.id">
                  {{ u.name }} ({{ u.email }})
                </option>
              </select>
              <p v-if="createForm.errors.user_id" class="text-red-500 text-xs mt-1">{{ createForm.errors.user_id }}</p>
            </div>

            <!-- Title -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Titre <span class="text-red-500">*</span></label>
              <input v-model="createForm.title" type="text" required placeholder="ex : Sortie matériel photo classe 3B"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              <p v-if="createForm.errors.title" class="text-red-500 text-xs mt-1">{{ createForm.errors.title }}</p>
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date de début <span class="text-red-500">*</span></label>
                <input v-model="createForm.start_date" type="datetime-local" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date de fin <span class="text-red-500">*</span></label>
                <input v-model="createForm.end_date" type="datetime-local" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
            </div>
            <p v-if="createForm.errors.end_date" class="text-red-500 text-xs -mt-2">{{ createForm.errors.end_date }}</p>

            <!-- Status -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Statut initial</label>
              <select v-model="createForm.status"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option v-for="s in statusOptions" :key="s.v" :value="s.v">{{ s.l }}</option>
              </select>
            </div>

            <!-- Notes -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
              <textarea v-model="createForm.notes" rows="2" placeholder="Informations complémentaires…"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
            </div>

            <!-- Asset picker -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium text-gray-700">
                  Équipements <span class="text-red-500">*</span>
                  <span v-if="createForm.asset_ids.length" class="ml-1 text-blue-600 font-semibold">
                    ({{ createForm.asset_ids.length }} sélectionné(s))
                  </span>
                </label>
                <span v-if="!createForm.start_date || !createForm.end_date"
                      class="text-xs text-gray-400 italic">Renseignez les dates pour voir les équipements disponibles</span>
              </div>

              <!-- Loading -->
              <div v-if="createLoading" class="py-6 text-center text-gray-400 text-sm">
                Chargement des équipements disponibles…
              </div>

              <!-- Assets grid -->
              <template v-else-if="createAssets.length > 0">
                <input v-model="createAssetSearch" type="text"
                       placeholder="Rechercher par nom ou tag…"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-2 focus:ring-2 focus:ring-blue-500 outline-none" />
                <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg divide-y">
                <label v-for="asset in filteredCreateAssets" :key="asset.id"
                       class="flex items-center gap-3 px-3 py-2.5 hover:bg-blue-50 cursor-pointer transition"
                       :class="createForm.asset_ids.includes(asset.id) ? 'bg-blue-50' : ''">
                  <input type="checkbox"
                         :checked="createForm.asset_ids.includes(asset.id)"
                         @change="toggleAsset(asset.id)"
                         class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                  <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ asset.name }}</p>
                    <p class="text-xs text-gray-500 font-mono">{{ asset.asset_tag }}</p>
                  </div>
                  <span class="text-xs text-green-600 font-medium flex-shrink-0">Disponible</span>
                </label>
                <div v-if="filteredCreateAssets.length === 0"
                     class="py-3 text-center text-gray-400 text-sm">
                  Aucun résultat pour "{{ createAssetSearch }}"
                </div>
                </div>
              </template>

              <div v-else-if="createForm.start_date && createForm.end_date && !createLoading"
                   class="py-4 text-center text-gray-400 text-sm border border-gray-200 rounded-lg">
                Aucun équipement disponible sur cette période
              </div>

              <p v-if="createForm.errors.asset_ids" class="text-red-500 text-xs mt-1">{{ createForm.errors.asset_ids }}</p>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-2 border-t">
              <button type="button" @click="showCreateModal = false"
                      class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Annuler
              </button>
              <button type="submit" :disabled="createForm.processing"
                      class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-60">
                {{ createForm.processing ? 'Création…' : 'Créer la réservation' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ═══ Modal : annulation admin avec motif ═══ -->
    <Teleport to="body">
      <div v-if="showAdminCancelModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
          <div class="flex items-center gap-3 mb-4">
            <span class="text-2xl">🚫</span>
            <h3 class="font-bold text-lg text-gray-900">Annuler la réservation</h3>
          </div>

          <div v-if="adminCancelTarget && ['approved','active'].includes(adminCancelTarget.status)"
               class="mb-4 bg-orange-50 border border-orange-200 rounded-lg p-3 text-sm text-orange-800">
            ⚠️ <strong>Attention :</strong> cette réservation est
            <strong>{{ adminCancelTarget?.status === 'active' ? 'active (matériel sorti)' : 'validée' }}</strong>.
            Les équipements seront libérés et l'utilisateur sera notifié par email.
          </div>

          <div class="mb-4 bg-gray-50 rounded-lg p-3 text-sm space-y-1">
            <div class="flex justify-between">
              <span class="text-gray-500">Réservation</span>
              <span class="font-medium">{{ adminCancelTarget?.title }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Demandeur</span>
              <span>{{ adminCancelTarget?.user }}</span>
            </div>
          </div>

          <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Motif <span class="text-gray-400 font-normal">(optionnel)</span>
            </label>
            <textarea v-model="adminCancelReason" rows="3"
              placeholder="Ex : Double réservation, créneaux réattribués…"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-400 outline-none resize-none" />
            <p class="text-xs text-gray-400 mt-1">Communiqué à l'utilisateur dans l'email de notification.</p>
          </div>

          <div class="flex gap-3">
            <button @click="showAdminCancelModal = false"
              class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">
              Retour
            </button>
            <button @click="submitAdminCancel" :disabled="adminCancelling"
              class="flex-1 bg-orange-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-orange-700 disabled:opacity-50">
              {{ adminCancelling ? 'Annulation…' : '🚫 Confirmer' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ═══ Modal : associer des réservations ═══ -->
    <Teleport to="body">
      <div v-if="showAssociateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6">
          <div class="flex items-center gap-3 mb-4">
            <span class="text-2xl">🔗</span>
            <h3 class="font-bold text-lg text-gray-900">Associer des réservations</h3>
          </div>

          <div class="mb-4 bg-purple-50 border border-purple-200 rounded-lg p-3 text-sm text-purple-800">
            <strong>ℹ️ Groupement visuel :</strong> Les réservations associées seront affichées ensemble
            dans les pages Retours et Attribution pour faciliter la gestion.
          </div>

          <div class="mb-4">
            <p class="text-sm text-gray-600 mb-3">
              <strong>{{ selected.length }} réservation(s) sélectionnée(s)</strong>
            </p>
            <div class="bg-gray-50 rounded-lg p-3 max-h-32 overflow-y-auto text-sm space-y-1">
              <div v-for="id in selected" :key="id" class="text-gray-700">
                • {{ reservations.data.find(r => r.id === id)?.title }}
                <span class="text-gray-400 text-xs">(#{{ id }})</span>
              </div>
            </div>
          </div>

          <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Choisir la réservation parente <span class="text-red-500">*</span>
            </label>
            <p class="text-xs text-gray-500 mb-2">
              La réservation parente sera celle affichée en premier. Les autres seront regroupées sous celle-ci.
            </p>
            <select v-model="associateParentId" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500 outline-none">
              <option value="">— Sélectionner une réservation parente —</option>
              <option v-for="id in selected" :key="id" :value="id">
                {{ reservations.data.find(r => r.id === id)?.title }} (#{{ id }})
              </option>
            </select>
          </div>

          <div class="flex gap-3">
            <button @click="showAssociateModal = false"
              class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">
              Annuler
            </button>
            <button @click="executeAssociate" :disabled="!associateParentId || associating"
              class="flex-1 bg-purple-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-purple-700 disabled:opacity-50">
              {{ associating ? 'Association…' : '🔗 Associer' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AppLayout>
</template>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.2s ease; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-8px); }
</style>
