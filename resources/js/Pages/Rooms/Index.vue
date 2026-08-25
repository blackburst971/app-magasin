<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { BuildingOfficeIcon, PlusIcon, PencilIcon, TrashIcon, CalendarDaysIcon, CheckCircleIcon, XCircleIcon, UserIcon, Squares2X2Icon, ListBulletIcon } from '@heroicons/vue/24/outline'
import { useViewMode } from '@/composables/useViewMode'

const page = usePage()

const props = defineProps({
  rooms:        Array,
  reservations: Array,
  isAdmin:      Boolean,
  users:        Array,
})

// ─── Onglet actif ─────────────────────────────────────────────────────────────
const activeTab = ref('reservations') // 'reservations' | 'rooms'

// ─── Vue grille / liste pour l'onglet Salles ──────────────────────────────────
const viewMode = useViewMode('rooms_view', 'grid')

// ─── Gestion salles (admin) ───────────────────────────────────────────────────
const showRoomModal = ref(false)
const editingRoom   = ref(null)
const roomForm      = ref({ name: '', location: '', capacity: '', description: '', is_reservable: true, color: '#8b5cf6' })
const roomError     = ref('')

function openCreateRoom() {
  editingRoom.value  = null
  roomForm.value     = { name: '', location: '', capacity: '', description: '', is_reservable: true, color: '#8b5cf6' }
  roomError.value    = ''
  showRoomModal.value = true
}

function openEditRoom(room) {
  editingRoom.value  = room
  roomForm.value     = { ...room, capacity: room.capacity ?? '' }
  roomError.value    = ''
  showRoomModal.value = true
}

function submitRoom() {
  roomError.value = ''
  if (!roomForm.value.name.trim()) { roomError.value = 'Le nom est obligatoire.'; return }
  const data = { ...roomForm.value, capacity: roomForm.value.capacity || null }
  if (editingRoom.value) {
    router.put(route('rooms.update', editingRoom.value.id), data, {
      onSuccess: () => { showRoomModal.value = false },
      onError: (e) => { roomError.value = Object.values(e).join(' ') },
    })
  } else {
    router.post(route('rooms.store'), data, {
      onSuccess: () => { showRoomModal.value = false },
      onError: (e) => { roomError.value = Object.values(e).join(' ') },
    })
  }
}

function deleteRoom(room) {
  if (!confirm(`Supprimer la salle "${room.name}" ? Toutes ses reservations seront supprimees.`)) return
  router.delete(route('rooms.destroy', room.id))
}

// ─── Nouvelle reservation ─────────────────────────────────────────────────────
const showResvModal = ref(false)
const resvForm      = ref({ room_id: '', title: '', start_date: '', end_date: '', notes: '', user_id: '', status: 'pending' })
const resvError     = ref('')
const resvLoading   = ref(false)

function openCreateResv(room = null) {
  resvForm.value  = { room_id: room?.id ?? '', title: '', start_date: '', end_date: '', notes: '', user_id: '', status: 'pending' }
  resvError.value = ''
  showResvModal.value = true
}

function submitResv() {
  resvError.value = ''
  if (!resvForm.value.room_id)    { resvError.value = 'Selectionnez une salle.'; return }
  if (!resvForm.value.title)      { resvError.value = 'Le titre est obligatoire.'; return }
  if (!resvForm.value.start_date) { resvError.value = 'La date de debut est obligatoire.'; return }
  if (!resvForm.value.end_date)   { resvError.value = 'La date de fin est obligatoire.'; return }
  resvLoading.value = true
  router.post(route('room-reservations.store'), resvForm.value, {
    onSuccess: () => { showResvModal.value = false },
    onError:   (e) => { resvError.value = Object.values(e).join(' ') },
    onFinish:  () => { resvLoading.value = false },
  })
}

// ─── Actions admin sur les reservations ──────────────────────────────────────
const showRejectModal  = ref(false)
const rejectingResv    = ref(null)
const rejectNotes      = ref('')

function approveResv(r) {
  if (!confirm(`Approuver la reservation "${r.title}" ?`)) return
  router.post(route('room-reservations.approve', r.id))
}

function openRejectModal(r) {
  rejectingResv.value = r
  rejectNotes.value   = ''
  showRejectModal.value = true
}

function submitReject() {
  router.post(route('room-reservations.reject', rejectingResv.value.id), { admin_notes: rejectNotes.value }, {
    onSuccess: () => { showRejectModal.value = false; rejectingResv.value = null },
  })
}

function cancelResv(r) {
  if (!confirm('Annuler cette reservation ?')) return
  router.post(route('room-reservations.cancel', r.id))
}

// ─── Filtres reservations ─────────────────────────────────────────────────────
const filterStatus = ref('all')
const filteredReservations = computed(() => {
  if (filterStatus.value === 'all') return props.reservations
  return props.reservations.filter(r => r.status === filterStatus.value)
})

// ─── Helpers ──────────────────────────────────────────────────────────────────
const statusColors = {
  pending:   'bg-yellow-100 text-yellow-800',
  approved:  'bg-green-100 text-green-800',
  rejected:  'bg-red-100 text-red-800',
  cancelled: 'bg-gray-100 text-gray-600',
}

const flash = computed(() => page.props.flash)
</script>

<template>
  <AppLayout>
    <div>
      <!-- Header -->
      <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <BuildingOfficeIcon class="w-7 h-7 text-violet-600" />
            Salles
          </h1>
          <p class="text-gray-500 text-sm mt-0.5">
            {{ rooms.length }} salle(s) — {{ reservations.length }} reservation(s)
          </p>
        </div>
        <div class="flex gap-2">
          <button v-if="isAdmin" @click="activeTab = 'rooms'; openCreateRoom()"
            class="flex items-center gap-1.5 bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 transition">
            <PlusIcon class="w-4 h-4" />
            Nouvelle salle
          </button>
          <button @click="openCreateResv()"
            class="flex items-center gap-1.5 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition">
            <CalendarDaysIcon class="w-4 h-4" />
            Reserver une salle
          </button>
        </div>
      </div>

      <!-- Flash messages -->
      <div v-if="flash?.success"
        class="mb-4 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
        {{ flash.success }}
      </div>
      <div v-if="flash?.error"
        class="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-3 text-sm">
        {{ flash.error }}
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 mb-6 border-b border-gray-200">
        <button @click="activeTab = 'reservations'"
          :class="['px-4 py-2 text-sm font-medium border-b-2 transition -mb-px',
            activeTab === 'reservations'
              ? 'border-blue-600 text-blue-700'
              : 'border-transparent text-gray-500 hover:text-gray-700']">
          Reservations ({{ reservations.length }})
        </button>
        <button @click="activeTab = 'rooms'"
          :class="['px-4 py-2 text-sm font-medium border-b-2 transition -mb-px',
            activeTab === 'rooms'
              ? 'border-violet-600 text-violet-700'
              : 'border-transparent text-gray-500 hover:text-gray-700']">
          Salles ({{ rooms.length }})
        </button>
      </div>

      <!-- ══════════════════ ONGLET RESERVATIONS ══════════════════ -->
      <div v-if="activeTab === 'reservations'">
        <!-- Filtre statut -->
        <div class="flex gap-2 mb-4 flex-wrap">
          <button v-for="s in ['all','pending','approved','rejected','cancelled']" :key="s"
            @click="filterStatus = s"
            :class="['px-3 py-1 rounded-full text-xs font-medium border transition',
              filterStatus === s
                ? 'bg-gray-900 text-white border-gray-900'
                : 'border-gray-300 text-gray-600 hover:bg-gray-50']">
            {{ s === 'all' ? 'Toutes' : s === 'pending' ? 'En attente' : s === 'approved' ? 'Approuvees' : s === 'rejected' ? 'Refusees' : 'Annulees' }}
          </button>
        </div>

        <!-- Liste -->
        <div v-if="filteredReservations.length === 0"
          class="bg-white rounded-xl border shadow-sm px-8 py-16 text-center text-gray-400">
          <CalendarDaysIcon class="w-12 h-12 mx-auto mb-3 opacity-30" />
          <p class="font-medium text-gray-500">Aucune reservation</p>
          <button @click="openCreateResv()"
            class="mt-3 text-sm text-blue-600 underline hover:text-blue-800">
            Reserver une salle
          </button>
        </div>

        <div class="space-y-3">
          <div v-for="r in filteredReservations" :key="r.id"
            class="bg-white rounded-xl border shadow-sm p-4 flex items-start justify-between gap-4 flex-wrap">
            <!-- Bande couleur salle -->
            <div class="flex items-start gap-4 flex-1 min-w-0">
              <div class="w-1 self-stretch rounded-full flex-shrink-0" :style="{ backgroundColor: r.room.color }"></div>
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                  <p class="font-semibold text-gray-900 truncate">{{ r.title }}</p>
                  <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusColors[r.status] ?? 'bg-gray-100 text-gray-600']">
                    {{ r.status_label }}
                  </span>
                </div>
                <p class="text-sm text-gray-600 mt-0.5">
                  <span class="font-medium" :style="{ color: r.room.color }">{{ r.room.name }}</span>
                  <span class="mx-1.5 text-gray-300">•</span>
                  {{ r.start_date }} → {{ r.end_date }}
                </p>
                <p v-if="isAdmin" class="text-xs text-gray-400 flex items-center gap-1 mt-0.5">
                  <UserIcon class="w-3 h-3" />
                  {{ r.user.name }}
                </p>
                <p v-if="r.admin_notes" class="text-xs text-red-600 mt-1 bg-red-50 rounded px-2 py-0.5">
                  {{ r.admin_notes }}
                </p>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex gap-2 items-center flex-shrink-0">
              <!-- Admin : approuver / refuser -->
              <template v-if="isAdmin && r.status === 'pending'">
                <button @click="approveResv(r)"
                  class="flex items-center gap-1 text-xs bg-green-100 text-green-700 hover:bg-green-200 px-2.5 py-1.5 rounded-lg font-medium transition">
                  <CheckCircleIcon class="w-3.5 h-3.5" />
                  Approuver
                </button>
                <button @click="openRejectModal(r)"
                  class="flex items-center gap-1 text-xs bg-red-100 text-red-700 hover:bg-red-200 px-2.5 py-1.5 rounded-lg font-medium transition">
                  <XCircleIcon class="w-3.5 h-3.5" />
                  Refuser
                </button>
              </template>
              <!-- Annuler (proprietaire ou admin, si pending/approved) -->
              <button v-if="['pending','approved'].includes(r.status)"
                @click="cancelResv(r)"
                class="text-xs text-gray-500 border border-gray-300 hover:bg-gray-50 px-2.5 py-1.5 rounded-lg transition">
                Annuler
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ══════════════════ ONGLET SALLES ══════════════════ -->
      <div v-else-if="activeTab === 'rooms'">
        <div v-if="rooms.length === 0"
          class="bg-white rounded-xl border shadow-sm px-8 py-16 text-center text-gray-400">
          <BuildingOfficeIcon class="w-12 h-12 mx-auto mb-3 opacity-30" />
          <p class="font-medium text-gray-500">Aucune salle configuree</p>
          <button v-if="isAdmin" @click="openCreateRoom()"
            class="mt-3 text-sm text-violet-600 underline hover:text-violet-800">
            Ajouter une salle
          </button>
        </div>

        <!-- Toolbar toggle grille/liste -->
        <div class="flex items-center justify-between mb-3">
          <p class="text-sm text-gray-500">{{ rooms.length }} salle(s)</p>
          <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden">
            <button @click="viewMode = 'grid'" :title="'Vue grille'"
              :class="['px-2.5 py-2 transition', viewMode === 'grid' ? 'bg-violet-600 text-white' : 'text-gray-500 hover:bg-gray-50']">
              <Squares2X2Icon class="w-4 h-4" />
            </button>
            <button @click="viewMode = 'list'" :title="'Vue liste'"
              :class="['px-2.5 py-2 transition border-l border-gray-200', viewMode === 'list' ? 'bg-violet-600 text-white' : 'text-gray-500 hover:bg-gray-50']">
              <ListBulletIcon class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Vue grille -->
        <div v-if="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="room in rooms" :key="room.id"
            class="bg-white rounded-xl border shadow-sm overflow-hidden">
            <!-- Header couleur -->
            <div class="h-2" :style="{ backgroundColor: room.color }"></div>
            <div class="p-4">
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <h3 class="font-semibold text-gray-900 truncate">{{ room.name }}</h3>
                  <p v-if="room.location" class="text-xs text-gray-500 mt-0.5">📍 {{ room.location }}</p>
                  <p v-if="room.capacity" class="text-xs text-gray-500">👥 {{ room.capacity }} personnes</p>
                </div>
                <div class="flex gap-1 flex-shrink-0">
                  <span v-if="!room.is_reservable"
                    class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full">Indisponible</span>
                </div>
              </div>
              <p v-if="room.description" class="text-xs text-gray-500 mt-2 line-clamp-2">
                {{ room.description }}
              </p>
              <div class="flex gap-2 mt-3 pt-3 border-t">
                <button @click="openCreateResv(room)"
                  class="flex-1 text-xs text-center bg-blue-50 text-blue-700 hover:bg-blue-100 py-1.5 rounded-lg font-medium transition"
                  :disabled="!room.is_reservable">
                  Reserver
                </button>
                <template v-if="isAdmin">
                  <button @click="openEditRoom(room)"
                    class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition">
                    <PencilIcon class="w-4 h-4" />
                  </button>
                  <button @click="deleteRoom(room)"
                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </template>
              </div>
            </div>
          </div>
        </div>

        <!-- Vue liste -->
        <div v-else class="bg-white rounded-xl border overflow-hidden">
          <div v-if="rooms.length === 0" class="px-8 py-12 text-center text-gray-400">
            <BuildingOfficeIcon class="w-10 h-10 mx-auto mb-2 opacity-30" />
            <p class="text-sm">Aucune salle configurée</p>
            <button v-if="isAdmin" @click="openCreateRoom()" class="mt-2 text-sm text-violet-600 underline">Ajouter une salle</button>
          </div>
          <div v-for="room in rooms" :key="room.id"
            class="flex items-center gap-4 px-4 py-3 border-b last:border-0 hover:bg-gray-50 transition">
            <!-- Color dot -->
            <div class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: room.color }"></div>
            <!-- Info -->
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-sm text-gray-900">{{ room.name }}</p>
              <p class="text-xs text-gray-400">
                <span v-if="room.location">📍 {{ room.location }}</span>
                <span v-if="room.location && room.capacity"> · </span>
                <span v-if="room.capacity">👥 {{ room.capacity }} pers.</span>
              </p>
            </div>
            <!-- Description -->
            <p v-if="room.description" class="text-xs text-gray-400 hidden md:block truncate max-w-[200px]">{{ room.description }}</p>
            <!-- Badges -->
            <span v-if="!room.is_reservable" class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full flex-shrink-0">Indisponible</span>
            <!-- Actions -->
            <div class="flex gap-1 flex-shrink-0">
              <button @click="openCreateResv(room)" :disabled="!room.is_reservable"
                class="text-xs bg-blue-50 text-blue-700 hover:bg-blue-100 px-3 py-1.5 rounded-lg font-medium transition disabled:opacity-50">
                Réserver
              </button>
              <template v-if="isAdmin">
                <button @click="openEditRoom(room)"
                  class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition">
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button @click="deleteRoom(room)"
                  class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : Creer / Modifier salle ═══ -->
    <div v-if="showRoomModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
        <h3 class="font-bold text-lg text-gray-900 mb-5">
          {{ editingRoom ? 'Modifier la salle' : 'Nouvelle salle' }}
        </h3>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
            <input v-model="roomForm.name" type="text" placeholder="Salle A, Labo 2..."
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Emplacement</label>
              <input v-model="roomForm.location" type="text" placeholder="Batiment A, 1er etage..."
                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Capacite</label>
              <input v-model="roomForm.capacity" type="number" min="1" placeholder="30"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="roomForm.description" rows="2" placeholder="Equipements disponibles, specificites..."
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-violet-500 outline-none resize-none"></textarea>
          </div>
          <div class="flex items-center justify-between">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Couleur calendrier</label>
              <input v-model="roomForm.color" type="color"
                class="w-10 h-8 rounded border cursor-pointer" />
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="roomForm.is_reservable" type="checkbox"
                class="w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 cursor-pointer" />
              <span class="text-sm font-medium text-gray-700">Reservable</span>
            </label>
          </div>
        </div>

        <p v-if="roomError" class="mt-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
          {{ roomError }}
        </p>

        <div class="flex gap-3 mt-6">
          <button @click="showRoomModal = false"
            class="flex-1 border border-gray-300 py-2.5 rounded-xl text-sm text-gray-700 hover:bg-gray-50 transition">
            Annuler
          </button>
          <button @click="submitRoom"
            class="flex-1 bg-violet-600 hover:bg-violet-700 text-white py-2.5 rounded-xl text-sm font-semibold transition">
            {{ editingRoom ? 'Enregistrer' : 'Creer la salle' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : Reserver une salle ═══ -->
    <div v-if="showResvModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
        <h3 class="font-bold text-lg text-gray-900 mb-5">Reserver une salle</h3>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Salle <span class="text-red-500">*</span></label>
            <select v-model="resvForm.room_id"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white">
              <option value="">Selectionnez une salle...</option>
              <option v-for="room in rooms.filter(r => r.is_reservable)" :key="room.id" :value="room.id">
                {{ room.name }}{{ room.location ? ` (${room.location})` : '' }}
              </option>
            </select>
          </div>

          <div v-if="isAdmin">
            <label class="block text-sm font-medium text-gray-700 mb-1">Utilisateur</label>
            <select v-model="resvForm.user_id"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white">
              <option value="">Moi-meme</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Titre <span class="text-red-500">*</span></label>
            <input v-model="resvForm.title" type="text" placeholder="Cours de math, Reunion, TP..."
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Debut <span class="text-red-500">*</span></label>
              <input v-model="resvForm.start_date" type="datetime-local"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Fin <span class="text-red-500">*</span></label>
              <input v-model="resvForm.end_date" type="datetime-local"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
          </div>

          <div v-if="isAdmin">
            <label class="block text-sm font-medium text-gray-700 mb-1">Statut initial</label>
            <select v-model="resvForm.status"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white">
              <option value="pending">En attente</option>
              <option value="approved">Approuvee directement</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optionnel)</label>
            <textarea v-model="resvForm.notes" rows="2" placeholder="Informations complementaires..."
              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
          </div>
        </div>

        <p v-if="resvError" class="mt-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
          {{ resvError }}
        </p>

        <div class="flex gap-3 mt-6">
          <button @click="showResvModal = false"
            class="flex-1 border border-gray-300 py-2.5 rounded-xl text-sm text-gray-700 hover:bg-gray-50 transition">
            Annuler
          </button>
          <button @click="submitResv" :disabled="resvLoading"
            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-xl text-sm font-semibold transition disabled:opacity-50">
            {{ resvLoading ? 'Envoi...' : 'Reserver' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : Refuser une reservation ═══ -->
    <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <h3 class="font-bold text-lg text-gray-900 mb-4">Refuser la reservation</h3>
        <p class="text-sm text-gray-600 mb-3">
          Reservation : <strong>{{ rejectingResv?.title }}</strong>
        </p>
        <textarea v-model="rejectNotes" rows="3" placeholder="Motif du refus (optionnel)..."
          class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 outline-none resize-none"></textarea>
        <div class="flex gap-3 mt-4">
          <button @click="showRejectModal = false; rejectingResv = null"
            class="flex-1 border border-gray-300 py-2.5 rounded-xl text-sm text-gray-700 hover:bg-gray-50 transition">
            Annuler
          </button>
          <button @click="submitReject"
            class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2.5 rounded-xl text-sm font-semibold transition">
            Refuser
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>
