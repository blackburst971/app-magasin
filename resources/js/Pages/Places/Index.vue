<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import axios from 'axios'
import {
  MapPinIcon, PlusIcon, PencilIcon, TrashIcon, XMarkIcon,
  BuildingStorefrontIcon, CubeIcon, ArrowTopRightOnSquareIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({ places: Array })

const page       = usePage()
const flash      = computed(() => page.props.flash ?? {})
const placesList = ref([...props.places])

// ── CRUD modal ────────────────────────────────────────────────────────────────
const showModal    = ref(false)
const editingPlace = ref(null)
const placeForm    = ref({ name: '', description: '', color: '#6366f1', is_storage: false })
const loading      = ref(false)
const formError    = ref('')

const PALETTE = [
  '#6366f1','#8b5cf6','#ec4899','#ef4444','#f97316',
  '#eab308','#22c55e','#14b8a6','#3b82f6','#64748b','#94a3b8','#f59e0b',
]

function openCreate() {
  editingPlace.value = null
  placeForm.value    = { name: '', description: '', color: '#6366f1', is_storage: false }
  formError.value    = ''
  showModal.value    = true
}

function openEdit(place) {
  editingPlace.value = place
  placeForm.value    = {
    name:        place.name,
    description: place.description ?? '',
    color:       place.color,
    is_storage:  place.is_storage,
  }
  formError.value = ''
  showModal.value = true
}

async function save() {
  if (!placeForm.value.name.trim()) { formError.value = 'Le nom est obligatoire.'; return }
  loading.value   = true
  formError.value = ''
  try {
    if (editingPlace.value) {
      const { data } = await axios.patch(route('places.update', editingPlace.value.id), placeForm.value)
      const idx = placesList.value.findIndex(p => p.id === editingPlace.value.id)
      if (idx !== -1) {
        // Conserver les compteurs existants
        placesList.value[idx] = { ...placesList.value[idx], ...data }
      }
    } else {
      const { data } = await axios.post(route('places.store'), placeForm.value)
      placesList.value.push({ ...data, assets_count: 0, active_assignments_count: 0 })
      placesList.value.sort((a, b) =>
        (b.is_storage ? 1 : 0) - (a.is_storage ? 1 : 0) || a.name.localeCompare(b.name)
      )
    }
    showModal.value = false
  } catch (e) {
    formError.value = e.response?.data?.message ?? 'Erreur lors de la sauvegarde.'
  } finally {
    loading.value = false
  }
}

// ── Suppression ───────────────────────────────────────────────────────────────
const deleteError   = ref({})
async function deletePlace(place) {
  if (!confirm(`Supprimer le lieu « ${place.name} » ?\nLes équipements affectés perdront leur lieu.`)) return
  try {
    await axios.delete(route('places.destroy', place.id))
    placesList.value = placesList.value.filter(p => p.id !== place.id)
    delete deleteError.value[place.id]
  } catch (e) {
    deleteError.value = { ...deleteError.value, [place.id]: e.response?.data?.error ?? 'Impossible de supprimer.' }
  }
}

// ── Groupes d'affichage ───────────────────────────────────────────────────────
const regularPlaces = computed(() => placesList.value.filter(p => !p.is_storage))
const storagePlaces = computed(() => placesList.value.filter(p =>  p.is_storage))
</script>

<template>
  <AppLayout title="Lieux">
    <div class="max-w-4xl space-y-6">

      <!-- En-tête -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <MapPinIcon class="h-6 w-6 text-indigo-600" />
            Lieux
          </h1>
          <p class="text-sm text-gray-500 mt-0.5">
            Gérez les lieux de rangement et d'affectation des équipements.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <Link :href="route('asset-places.index')"
                class="flex items-center gap-1.5 px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">
            <ArrowTopRightOnSquareIcon class="h-4 w-4" />
            Affectations
          </Link>
          <button @click="openCreate"
                  class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            <PlusIcon class="h-4 w-4" /> Nouveau lieu
          </button>
        </div>
      </div>

      <!-- Lieux ordinaires -->
      <div v-if="regularPlaces.length">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
          <MapPinIcon class="h-4 w-4" /> Lieux
          <span class="bg-gray-100 text-gray-600 text-xs font-bold px-2 py-0.5 rounded-full">{{ regularPlaces.length }}</span>
        </h2>
        <div class="grid gap-3 sm:grid-cols-2">
          <div v-for="place in regularPlaces" :key="place.id"
               class="bg-white border border-gray-200 rounded-xl p-4 hover:shadow-sm transition group">
            <div class="flex items-start gap-3">
              <!-- Pastille couleur -->
              <div class="w-10 h-10 rounded-xl flex-shrink-0 flex items-center justify-center shadow-inner mt-0.5"
                   :style="{ backgroundColor: place.color + '22', border: '2px solid ' + place.color }">
                <MapPinIcon class="h-5 w-5" :style="{ color: place.color }" />
              </div>
              <!-- Infos -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <Link :href="route('places.show', place.id)"
                        class="font-semibold text-gray-900 hover:text-indigo-600 truncate transition">
                    {{ place.name }}
                  </Link>
                </div>
                <p v-if="place.description" class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ place.description }}</p>
                <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                  <span class="flex items-center gap-1">
                    <CubeIcon class="h-3.5 w-3.5" />
                    {{ place.assets_count }} équipement{{ place.assets_count !== 1 ? 's' : '' }}
                  </span>
                  <span v-if="place.active_assignments_count" class="flex items-center gap-1 text-indigo-600 font-medium">
                    <MapPinIcon class="h-3.5 w-3.5" />
                    {{ place.active_assignments_count }} affectation{{ place.active_assignments_count !== 1 ? 's' : '' }} active{{ place.active_assignments_count !== 1 ? 's' : '' }}
                  </span>
                </div>
                <p v-if="deleteError[place.id]" class="mt-1 text-xs text-red-600 flex items-center gap-1">
                  <ExclamationTriangleIcon class="h-3.5 w-3.5 flex-shrink-0" /> {{ deleteError[place.id] }}
                </p>
              </div>
              <!-- Actions -->
              <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                <button @click="openEdit(place)"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition"
                        title="Modifier">
                  <PencilIcon class="h-4 w-4" />
                </button>
                <button @click="deletePlace(place)"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition"
                        title="Supprimer">
                  <TrashIcon class="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Zones de stockage -->
      <div v-if="storagePlaces.length">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
          <BuildingStorefrontIcon class="h-4 w-4" /> Zones de stockage
          <span class="bg-gray-100 text-gray-600 text-xs font-bold px-2 py-0.5 rounded-full">{{ storagePlaces.length }}</span>
        </h2>
        <div class="grid gap-3 sm:grid-cols-2">
          <div v-for="place in storagePlaces" :key="place.id"
               class="bg-white border border-gray-200 rounded-xl p-4 hover:shadow-sm transition group">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 rounded-xl flex-shrink-0 flex items-center justify-center shadow-inner mt-0.5"
                   :style="{ backgroundColor: place.color + '22', border: '2px solid ' + place.color }">
                <BuildingStorefrontIcon class="h-5 w-5" :style="{ color: place.color }" />
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <Link :href="route('places.show', place.id)"
                        class="font-semibold text-gray-900 hover:text-indigo-600 truncate transition">
                    {{ place.name }}
                  </Link>
                  <span class="text-xs font-medium bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full flex-shrink-0">
                    Stockage
                  </span>
                </div>
                <p v-if="place.description" class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ place.description }}</p>
                <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                  <span class="flex items-center gap-1">
                    <CubeIcon class="h-3.5 w-3.5" />
                    {{ place.assets_count }} équipement{{ place.assets_count !== 1 ? 's' : '' }}
                  </span>
                </div>
                <p v-if="deleteError[place.id]" class="mt-1 text-xs text-red-600 flex items-center gap-1">
                  <ExclamationTriangleIcon class="h-3.5 w-3.5 flex-shrink-0" /> {{ deleteError[place.id] }}
                </p>
              </div>
              <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                <button @click="openEdit(place)"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition"
                        title="Modifier">
                  <PencilIcon class="h-4 w-4" />
                </button>
                <button @click="deletePlace(place)"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition"
                        title="Supprimer">
                  <TrashIcon class="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Vide -->
      <div v-if="!placesList.length"
           class="bg-white border border-dashed border-gray-300 rounded-xl py-16 text-center">
        <MapPinIcon class="h-10 w-10 text-gray-300 mx-auto mb-3" />
        <p class="text-gray-500 font-medium">Aucun lieu configuré</p>
        <p class="text-sm text-gray-400 mt-1 mb-4">Créez vos premiers lieux pour organiser vos équipements.</p>
        <button @click="openCreate"
                class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
          <PlusIcon class="h-4 w-4" /> Créer un lieu
        </button>
      </div>

    </div>

    <!-- ══ MODAL CREATE / EDIT ════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showModal"
           class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
           @click.self="showModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="font-semibold text-gray-900">
              {{ editingPlace ? 'Modifier le lieu' : 'Nouveau lieu' }}
            </h3>
            <button @click="showModal = false">
              <XMarkIcon class="h-5 w-5 text-gray-400 hover:text-gray-600" />
            </button>
          </div>

          <form @submit.prevent="save" class="p-6 space-y-4">
            <!-- Nom -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nom *</label>
              <input v-model="placeForm.name" required autofocus
                     placeholder="Salle 101, Réserve, Magasin…"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
            </div>

            <!-- Description -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <input v-model="placeForm.description"
                     placeholder="Description optionnelle…"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
            </div>

            <!-- Couleur -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Couleur</label>
              <div class="flex flex-wrap gap-2">
                <button v-for="c in PALETTE" :key="c" type="button" @click="placeForm.color = c"
                        class="w-7 h-7 rounded-full transition-transform"
                        :style="{ backgroundColor: c }"
                        :class="placeForm.color === c ? 'ring-2 ring-offset-2 ring-gray-700 scale-110' : 'hover:scale-110'" />
                <!-- Couleur personnalisée -->
                <label class="w-7 h-7 rounded-full border-2 border-dashed border-gray-300 flex items-center justify-center cursor-pointer hover:border-gray-400 overflow-hidden"
                       title="Couleur personnalisée"
                       :style="!PALETTE.includes(placeForm.color) ? { backgroundColor: placeForm.color } : {}">
                  <input type="color" v-model="placeForm.color" class="opacity-0 w-full h-full absolute cursor-pointer" />
                  <span v-if="PALETTE.includes(placeForm.color)" class="text-gray-400 text-xs">+</span>
                </label>
              </div>
            </div>

            <!-- Zone de stockage -->
            <div>
              <label class="flex items-center gap-3 p-3 border rounded-xl cursor-pointer transition"
                     :class="placeForm.is_storage ? 'bg-amber-50 border-amber-200' : 'bg-gray-50 border-gray-200 hover:border-gray-300'">
                <input type="checkbox" id="is_storage" v-model="placeForm.is_storage"
                       class="rounded border-gray-300 text-amber-500 focus:ring-amber-400" />
                <div>
                  <p class="text-sm font-medium text-gray-800">Zone de stockage</p>
                  <p class="text-xs text-gray-500">Magasin, réserve — non utilisée pour les affectations classiques</p>
                </div>
              </label>
            </div>

            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>

            <div class="flex gap-3 pt-2">
              <button type="button" @click="showModal = false"
                      class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm hover:bg-gray-50 transition">
                Annuler
              </button>
              <button type="submit" :disabled="loading"
                      class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-lg text-sm font-semibold transition disabled:opacity-50">
                {{ loading ? '…' : (editingPlace ? 'Modifier' : 'Créer le lieu') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>
