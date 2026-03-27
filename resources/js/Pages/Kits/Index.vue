<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import AssetThumbnail from '@/Components/AssetThumbnail.vue'
import { ref, computed } from 'vue'
import { useForm, router, usePage, Link } from '@inertiajs/vue3'
import axios from 'axios'
import { useCartStore } from '@/stores/cart'
import {
  PlusIcon, PencilIcon, TrashIcon, WrenchScrewdriverIcon,
  ExclamationTriangleIcon, CheckCircleIcon, ShoppingCartIcon,
} from '@heroicons/vue/24/outline'

const page    = usePage()
const isAdmin = computed(() => page.props.auth.user?.is_admin)
const cart    = useCartStore()

function toggleKit(kit) {
  cart.hasKit(kit.id) ? cart.removeKit(kit.id) : cart.addKit(kit)
}

const props = defineProps({
  kits:            Array,
  availableAssets: { type: Array, default: () => [] },
})

// ─── Créer un kit ─────────────────────────────────────────────────────────────
const showCreateModal    = ref(false)
const createImagePreview = ref(null)
const createForm = useForm({
  name:        '',
  description: '',
  notes:       '',
  asset_ids:   [],
  image:       null,
})
const assetSearch = ref('')

function onCreateImageChange(e) {
  const file = e.target.files[0]
  if (file) {
    createForm.image = file
    createImagePreview.value = URL.createObjectURL(file)
  }
}

const filteredAvailableAssets = computed(() =>
  props.availableAssets.filter(a =>
    !assetSearch.value ||
    a.name.toLowerCase().includes(assetSearch.value.toLowerCase()) ||
    a.asset_tag.toLowerCase().includes(assetSearch.value.toLowerCase())
  )
)

function toggleCreateAsset(id) {
  const idx = createForm.asset_ids.indexOf(id)
  if (idx === -1) createForm.asset_ids.push(id)
  else createForm.asset_ids.splice(idx, 1)
}

function submitCreate() {
  createForm.post(route('kits.store'), {
    forceFormData: true,
    onSuccess: () => {
      showCreateModal.value = false
      createForm.reset()
      createImagePreview.value = null
      assetSearch.value = ''
    },
  })
}

// ─── Éditer un kit ────────────────────────────────────────────────────────────
const showEditModal    = ref(false)
const editTarget       = ref(null)
const editImagePreview = ref(null)
const editForm         = useForm({
  name:          '',
  description:   '',
  notes:         '',
  status:        'available',
  is_reservable: true,
  asset_ids:     [],
  image:         null,
  remove_image:  false,
})
const editAssetSearch = ref('')

function onEditImageChange(e) {
  const file = e.target.files[0]
  if (file) {
    editForm.image = file
    editForm.remove_image = false
    editImagePreview.value = URL.createObjectURL(file)
  }
}

function removeEditImage() {
  editForm.image = null
  editForm.remove_image = true
  editImagePreview.value = null
}

const allAssetsForEdit = computed(() => {
  if (!editTarget.value) return props.availableAssets
  const currentIds = editTarget.value.assets.map(a => a.id)
  // current kit assets + available assets (no duplicates)
  const combined = [...editTarget.value.assets, ...props.availableAssets.filter(a => !currentIds.includes(a.id))]
  if (!editAssetSearch.value) return combined
  return combined.filter(a =>
    a.name.toLowerCase().includes(editAssetSearch.value.toLowerCase()) ||
    a.asset_tag.toLowerCase().includes(editAssetSearch.value.toLowerCase())
  )
})

function openEdit(kit) {
  editTarget.value       = kit
  editForm.name          = kit.name
  editForm.description   = kit.description ?? ''
  editForm.notes         = kit.notes ?? ''
  editForm.status        = kit.status
  editForm.is_reservable = kit.is_reservable
  editForm.asset_ids     = kit.assets.map(a => a.id)
  editForm.image         = null
  editForm.remove_image  = false
  editImagePreview.value = kit.image ?? null
  editAssetSearch.value  = ''
  showEditModal.value    = true
}

function toggleEditAsset(id) {
  const idx = editForm.asset_ids.indexOf(id)
  if (idx === -1) editForm.asset_ids.push(id)
  else editForm.asset_ids.splice(idx, 1)
}

function submitEdit() {
  // forceFormData + _method spoofing pour que l'image soit envoyée en multipart via POST
  editForm.transform(data => ({ ...data, _method: 'PUT' })).post(
    route('kits.update', editTarget.value.id),
    {
      forceFormData: true,
      onSuccess: () => { showEditModal.value = false },
    }
  )
}

// ─── Supprimer un kit ─────────────────────────────────────────────────────────
const deleteForm = useForm({})
function deleteKit(kit) {
  if (!confirm(`Supprimer le kit « ${kit.name} » ?`)) return
  deleteForm.delete(route('kits.destroy', kit.id))
}

// ─── Régulariser un élément manquant ──────────────────────────────────────────
const showRegularizeModal = ref(false)
const regularizeTarget    = ref(null)
const regularizeKitTarget = ref(null)
const regularizeForm      = useForm({
  regularization_notes: '',
  kit_status:           'available',
})

function openRegularize(kit, missingItem) {
  regularizeKitTarget.value = kit
  regularizeTarget.value    = missingItem
  regularizeForm.regularization_notes = ''
  regularizeForm.kit_status           = 'available'
  showRegularizeModal.value = true
}

function submitRegularize() {
  regularizeForm.post(
    route('kits.regularize', { kit: regularizeKitTarget.value.id, missingItem: regularizeTarget.value.id }),
    {
      onSuccess: () => { showRegularizeModal.value = false },
    }
  )
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
const statusColors = {
  available:   'bg-green-100 text-green-700 border-green-200',
  partial:     'bg-yellow-100 text-yellow-700 border-yellow-200',
  unavailable: 'bg-red-100 text-red-700 border-red-200',
}
</script>

<template>
  <AppLayout>
    <div class="max-w-6xl space-y-6">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Kits d'équipements</h1>
          <p class="text-sm text-gray-500 mt-0.5">
            {{ kits.length }} kit(s)
            <template v-if="isAdmin">
              —
              <span class="text-red-600 font-medium">
                {{ kits.filter(k => k.pending_missing_count > 0).length }} en attente de régularisation
              </span>
            </template>
          </p>
        </div>
        <button v-if="isAdmin" @click="showCreateModal = true"
          class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition shadow-sm">
          <PlusIcon class="w-4 h-4" />
          Nouveau kit
        </button>
      </div>

      <!-- Bandeau panier (utilisateurs uniquement) -->
      <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0"
                  leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="!isAdmin && cart.kitItems.length > 0"
             class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 flex items-center justify-between gap-3">
          <div class="flex items-center gap-2 text-sm text-blue-800">
            <ShoppingCartIcon class="w-5 h-5 text-blue-600" />
            <span class="font-semibold">{{ cart.kitItems.length }} kit(s) dans le panier</span>
            <span class="text-blue-500">·</span>
            <span class="text-blue-600">{{ cart.kitItems.map(k => k.name).join(', ') }}</span>
          </div>
          <Link :href="route('reservations.create')"
                class="flex-shrink-0 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
            Réserver →
          </Link>
        </div>
      </Transition>

      <!-- Section : kits en attente de régularisation (admin only) -->
      <section v-if="isAdmin && kits.some(k => k.pending_missing_count > 0)">
        <h2 class="flex items-center gap-2 text-base font-semibold text-orange-700 mb-3">
          <ExclamationTriangleIcon class="w-5 h-5" />
          Kits en attente de régularisation
        </h2>
        <div class="space-y-3">
          <div v-for="kit in kits.filter(k => k.pending_missing_count > 0)" :key="'reg-' + kit.id"
               class="bg-white border border-orange-200 rounded-xl p-5">
            <div class="flex items-start justify-between gap-3 mb-3">
              <div>
                <h3 class="font-semibold text-gray-900">{{ kit.name }}</h3>
                <span class="text-xs text-orange-600 font-medium">
                  {{ kit.pending_missing_count }} élément(s) manquant(s)
                </span>
              </div>
              <span :class="['text-xs border px-2 py-0.5 rounded-full font-medium', statusColors[kit.status]]">
                {{ kit.status_label }}
              </span>
            </div>
            <div class="space-y-2">
              <div v-for="item in kit.pending_missing_items" :key="item.id"
                   class="flex items-center justify-between gap-3 p-3 bg-orange-50 border border-orange-100 rounded-lg">
                <div class="text-sm">
                  <p class="font-medium text-gray-900">{{ item.asset.name }}
                    <span class="font-mono text-gray-400 text-xs ml-1">({{ item.asset.asset_tag }})</span>
                  </p>
                  <p v-if="item.responsible_user" class="text-xs text-gray-500 mt-0.5">
                    👤 Responsable : <span class="font-medium text-orange-700">{{ item.responsible_user.name }}</span>
                  </p>
                  <p v-if="item.notes" class="text-xs text-gray-400 mt-0.5 italic">{{ item.notes }}</p>
                  <p class="text-xs text-gray-400 mt-0.5">Signalé le {{ item.created_at }}</p>
                </div>
                <button @click="openRegularize(kit, item)"
                  class="flex-shrink-0 bg-orange-500 hover:bg-orange-600 text-white text-xs px-3 py-1.5 rounded-lg font-medium transition">
                  Régulariser
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Liste de tous les kits -->
      <section>
        <h2 class="text-base font-semibold text-gray-700 mb-3">
          Tous les kits ({{ kits.length }})
        </h2>

        <div v-if="kits.length === 0"
             class="bg-white rounded-xl shadow-sm border px-8 py-16 text-center text-gray-400">
          <WrenchScrewdriverIcon class="w-12 h-12 mx-auto mb-3 opacity-30" />
          <p class="font-medium text-gray-500">Aucun kit créé</p>
          <p class="text-sm mt-1">Créez un kit pour regrouper des équipements.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          <div v-for="kit in kits" :key="kit.id"
               class="bg-white rounded-xl shadow-sm border overflow-hidden">

            <!-- Image du kit -->
            <div v-if="kit.image" class="h-28 bg-gray-100 overflow-hidden">
              <img :src="kit.image" class="w-full h-full object-cover" :alt="kit.name" />
            </div>

            <!-- En-tête carte kit -->
            <div class="px-4 pt-4 pb-3 border-b border-gray-100">
              <div class="flex items-start justify-between gap-2 mb-1">
                <h3 class="font-semibold text-gray-900 leading-tight">{{ kit.name }}</h3>
                <span :class="['text-xs border px-2 py-0.5 rounded-full font-medium flex-shrink-0', statusColors[kit.status]]">
                  {{ kit.status_label }}
                </span>
              </div>
              <p v-if="kit.description" class="text-xs text-gray-500 mt-1">{{ kit.description }}</p>
              <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
                <span>{{ kit.assets_count }} équipement(s)</span>
                <span v-if="!kit.is_reservable" class="text-red-500 font-medium">🚫 Non réservable</span>
                <span v-if="kit.pending_missing_count > 0" class="text-orange-600 font-medium">
                  ⚠️ {{ kit.pending_missing_count }} manquant(s)
                </span>
              </div>
            </div>

            <!-- Liste composants -->
            <div class="px-4 py-3 space-y-1.5 max-h-40 overflow-y-auto">
              <div v-for="asset in kit.assets" :key="asset.id"
                   class="flex items-center gap-2 text-xs text-gray-600">
                <AssetThumbnail :src="asset.image" :alt="asset.name" size="xs" />
                <span class="truncate font-medium flex-1">{{ asset.name }}</span>
                <span class="font-mono text-gray-400 flex-shrink-0">{{ asset.asset_tag }}</span>
              </div>
            </div>

            <!-- Actions -->
            <div class="px-4 py-3 border-t border-gray-100 flex items-center gap-2">
              <template v-if="isAdmin">
                <button @click="openEdit(kit)"
                  class="flex items-center gap-1 text-xs text-blue-600 hover:bg-blue-50 px-2 py-1 rounded-lg transition font-medium">
                  <PencilIcon class="w-3.5 h-3.5" />
                  Modifier
                </button>
                <button @click="deleteKit(kit)"
                  class="flex items-center gap-1 text-xs text-red-500 hover:bg-red-50 px-2 py-1 rounded-lg transition font-medium ml-auto">
                  <TrashIcon class="w-3.5 h-3.5" />
                  Supprimer
                </button>
              </template>
              <!-- Bouton panier pour les utilisateurs -->
              <template v-else>
                <button
                  v-if="kit.is_reservable && kit.status !== 'unavailable'"
                  @click="toggleKit(kit)"
                  :class="['w-full flex items-center justify-center gap-2 py-2 rounded-lg text-sm font-semibold transition',
                    cart.hasKit(kit.id)
                      ? 'bg-green-100 text-green-700 hover:bg-red-100 hover:text-red-600 border border-green-300'
                      : 'bg-blue-600 text-white hover:bg-blue-700']">
                  <ShoppingCartIcon class="w-4 h-4" />
                  {{ cart.hasKit(kit.id) ? '✓ Dans le panier' : 'Ajouter au panier' }}
                </button>
                <p v-else class="text-center text-xs text-gray-400 py-2">Non disponible à la réservation</p>
              </template>
            </div>
          </div>
        </div>
      </section>

    </div>

    <!-- ═══ Modals admin uniquement ═══ -->
    <template v-if="isAdmin">

    <!-- Modal : Créer un kit -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl p-6 overflow-y-auto max-h-[90vh]">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">Créer un kit</h3>
          <button @click="showCreateModal=false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom du kit <span class="text-red-500">*</span></label>
            <input v-model="createForm.name" type="text" placeholder="Kit vidéo, Kit son scène…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            <p v-if="createForm.errors.name" class="text-red-500 text-xs mt-1">{{ createForm.errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="createForm.description" rows="2"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes internes</label>
            <textarea v-model="createForm.notes" rows="2"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
          </div>

          <!-- Image -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Image (facultatif)</label>
            <input type="file" accept="image/*" @change="onCreateImageChange"
              class="block w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer" />
            <div v-if="createImagePreview" class="mt-2 relative inline-block">
              <img :src="createImagePreview" class="h-24 rounded-lg object-cover border border-gray-200" />
              <button type="button" @click="createForm.image = null; createImagePreview = null"
                class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center hover:bg-red-600">
                ×
              </button>
            </div>
          </div>

          <!-- Sélection des équipements -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Équipements du kit
              <span class="text-gray-400 font-normal">({{ createForm.asset_ids.length }} sélectionné(s))</span>
              <span class="text-red-500 ml-1">*</span>
            </label>
            <input v-model="assetSearch" type="text" placeholder="Rechercher un équipement…"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-2 focus:ring-2 focus:ring-blue-500 outline-none" />
            <div class="border border-gray-200 rounded-lg divide-y max-h-52 overflow-y-auto">
              <div v-if="filteredAvailableAssets.length === 0" class="py-6 text-center text-sm text-gray-400">
                Aucun équipement disponible
              </div>
              <label v-for="asset in filteredAvailableAssets" :key="asset.id"
                class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer">
                <input type="checkbox" :value="asset.id" v-model="createForm.asset_ids"
                  class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4 flex-shrink-0" />
                <AssetThumbnail :src="asset.image" :alt="asset.name" size="xs" />
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-medium text-gray-900 truncate">{{ asset.name }}</p>
                  <p class="text-xs text-gray-500">{{ asset.asset_tag }}{{ asset.category ? ' · ' + asset.category : '' }}</p>
                </div>
              </label>
            </div>
            <p v-if="createForm.errors.asset_ids" class="text-red-500 text-xs mt-1">{{ createForm.errors.asset_ids }}</p>
          </div>
        </div>

        <div class="flex gap-3 mt-5">
          <button @click="showCreateModal=false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitCreate"
            :disabled="createForm.processing || !createForm.name.trim() || createForm.asset_ids.length === 0"
            class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ createForm.processing ? 'Création…' : 'Créer le kit' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : Modifier un kit ═══ -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl p-6 overflow-y-auto max-h-[90vh]">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">Modifier « {{ editTarget?.name }} »</h3>
          <button @click="showEditModal=false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom</label>
            <input v-model="editForm.name" type="text"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
              <select v-model="editForm.status"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="available">Disponible</option>
                <option value="partial">Incomplet (autorisé)</option>
                <option value="unavailable">Indisponible</option>
              </select>
            </div>
            <div class="flex items-end pb-2">
              <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700">
                <input type="checkbox" v-model="editForm.is_reservable"
                  class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4" />
                Réservable par les utilisateurs
              </label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="editForm.description" rows="2"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes internes</label>
            <textarea v-model="editForm.notes" rows="2"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
          </div>

          <!-- Image -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Image</label>
            <!-- Aperçu de l'image actuelle ou nouvelle -->
            <div v-if="editImagePreview" class="mb-2 flex items-start gap-3">
              <img :src="editImagePreview" class="h-20 rounded-lg object-cover border border-gray-200" />
              <button type="button" @click="removeEditImage"
                class="text-xs text-red-500 hover:text-red-700 flex items-center gap-1 mt-1">
                <TrashIcon class="w-3.5 h-3.5" /> Supprimer l'image
              </button>
            </div>
            <input type="file" accept="image/*" @change="onEditImageChange"
              class="block w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer" />
            <p v-if="!editImagePreview" class="text-xs text-gray-400 mt-1">Aucune image. Sélectionnez un fichier pour en ajouter une.</p>
          </div>

          <!-- Sélection équipements -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Équipements
              <span class="text-gray-400 font-normal">({{ editForm.asset_ids.length }} sélectionné(s))</span>
            </label>
            <input v-model="editAssetSearch" type="text" placeholder="Rechercher…"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mb-2 focus:ring-2 focus:ring-blue-500 outline-none" />
            <div class="border border-gray-200 rounded-lg divide-y max-h-48 overflow-y-auto">
              <label v-for="asset in allAssetsForEdit" :key="asset.id"
                class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer">
                <input type="checkbox" :value="asset.id" v-model="editForm.asset_ids"
                  class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4 flex-shrink-0" />
                <AssetThumbnail :src="asset.image" :alt="asset.name" size="xs" />
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-medium text-gray-900 truncate">{{ asset.name }}</p>
                  <p class="text-xs text-gray-500">{{ asset.asset_tag }}{{ asset.category ? ' · ' + asset.category : '' }}</p>
                </div>
              </label>
            </div>
          </div>
        </div>

        <div class="flex gap-3 mt-5">
          <button @click="showEditModal=false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitEdit" :disabled="editForm.processing"
            class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ editForm.processing ? 'Sauvegarde…' : 'Enregistrer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : Régulariser un élément manquant ═══ -->
    <div v-if="showRegularizeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">Régulariser l'élément manquant</h3>
          <button @click="showRegularizeModal=false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>

        <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 mb-4">
          <p class="text-sm font-medium text-gray-900">{{ regularizeTarget?.asset?.name }}</p>
          <p class="text-xs text-gray-500 font-mono">{{ regularizeTarget?.asset?.asset_tag }}</p>
          <p v-if="regularizeTarget?.responsible_user" class="text-xs text-orange-700 mt-1">
            👤 Responsable : {{ regularizeTarget.responsible_user.name }}
          </p>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Après régularisation, le kit devient :</label>
            <select v-model="regularizeForm.kit_status"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 outline-none">
              <option value="available">✅ Disponible (complet ou prêt)</option>
              <option value="partial">⚠️ Incomplet (autorisé à être réservé)</option>
              <option value="unavailable">🚫 Indisponible (ne peut pas être réservé)</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes de régularisation</label>
            <textarea v-model="regularizeForm.regularization_notes" rows="3"
              placeholder="Remplacement prévu, perte assumée, élément retrouvé…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 outline-none resize-none" />
          </div>
        </div>

        <div class="flex gap-3 mt-5">
          <button @click="showRegularizeModal=false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitRegularize" :disabled="regularizeForm.processing"
            class="flex-1 bg-orange-500 text-white py-2 rounded-lg text-sm font-medium hover:bg-orange-600 disabled:opacity-50">
            {{ regularizeForm.processing ? 'Traitement…' : 'Régulariser' }}
          </button>
        </div>
      </div>
    </div>

    </template><!-- fin v-if isAdmin -->

  </AppLayout>
</template>
