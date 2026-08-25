<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import AssetThumbnail from '@/Components/AssetThumbnail.vue'
import { ref, computed } from 'vue'
import { useForm, router, usePage, Link } from '@inertiajs/vue3'
import axios from 'axios'
import { useCartStore } from '@/stores/cart'
import { useViewMode } from '@/composables/useViewMode'
import {
  PlusIcon, PencilIcon, TrashIcon, WrenchScrewdriverIcon,
  ExclamationTriangleIcon, CheckCircleIcon, ShoppingCartIcon,
  QrCodeIcon, ArrowPathIcon, Squares2X2Icon, ListBulletIcon,
} from '@heroicons/vue/24/outline'

// ── Note d'état des kits (condition_note) ─────────────────────────────────────
// État par kit.id pour permettre l'édition inline indépendante de chaque kit
const conditionNotes    = ref({})   // { [kitId]: string }
const editingNote       = ref({})   // { [kitId]: boolean }
const savingNote        = ref({})   // { [kitId]: boolean }
const originalNotes     = {}        // valeurs originales (non réactif)

function initNotes(kits) {
  kits.forEach(k => {
    conditionNotes.value[k.id] = k.condition_note ?? ''
    originalNotes[k.id] = k.condition_note ?? ''
  })
}

function cancelNote(kitId) {
  conditionNotes.value[kitId] = originalNotes[kitId]
  editingNote.value[kitId] = false
}

async function saveNote(kitId) {
  savingNote.value[kitId] = true
  try {
    await axios.patch(route('kits.condition-note', kitId), {
      condition_note: conditionNotes.value[kitId] || null,
    })
    originalNotes[kitId] = conditionNotes.value[kitId]
    editingNote.value[kitId] = false
  } catch {
    alert('Erreur lors de la sauvegarde.')
  } finally {
    savingNote.value[kitId] = false
  }
}

const page     = usePage()
const isAdmin  = computed(() => page.props.auth.user?.is_admin)
const cart     = useCartStore()
const viewMode = useViewMode('kits_view', 'grid')

function toggleKit(kit) {
  cart.hasKit(kit.id) ? cart.removeKit(kit.id) : cart.addKit(kit)
}

const props = defineProps({
  kits:            Array,
  kitCategories:   { type: Array, default: () => [] },
  availableAssets: { type: Array, default: () => [] },
})

// Initialiser les notes d'état dès le chargement
initNotes(props.kits ?? [])

// ─── Créer un kit ─────────────────────────────────────────────────────────────
const showCreateModal    = ref(false)
const createImagePreview = ref(null)
const createForm = useForm({
  name:            '',
  description:     '',
  notes:           '',
  kit_category_id: null,
  asset_ids:       [],
  image:           null,
})
const assetSearch    = ref('')
const createScanInput   = ref('')
const createScanLoading = ref(false)
const createScanMsg     = ref(null)   // { ok, message }

async function scanAssetForCreate() {
  const tag = createScanInput.value.trim()
  if (!tag) return
  createScanLoading.value = true
  createScanMsg.value = null
  try {
    const { data } = await axios.get(route('kits.asset-lookup'), { params: { asset_tag: tag } })
    if (data.in_kit) {
      createScanMsg.value = { ok: false, message: `${data.name} est déjà dans le kit « ${data.in_kit.name} »` }
    } else if (createForm.asset_ids.includes(data.id)) {
      createScanMsg.value = { ok: false, message: `${data.name} est déjà sélectionné` }
    } else {
      createForm.asset_ids.push(data.id)
      createScanMsg.value = { ok: true, message: `✓ ${data.name} (${data.asset_tag}) ajouté` }
      setTimeout(() => { createScanMsg.value = null }, 3000)
    }
  } catch (e) {
    createScanMsg.value = { ok: false, message: e.response?.data?.error ?? 'Équipement introuvable' }
  } finally {
    createScanLoading.value = false
    createScanInput.value = ''
  }
}

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
      createScanInput.value = ''
      createScanMsg.value = null
    },
  })
}

// ─── Éditer un kit ────────────────────────────────────────────────────────────
const showEditModal    = ref(false)
const editTarget       = ref(null)
const editImagePreview = ref(null)
const editForm         = useForm({
  name:            '',
  description:     '',
  notes:           '',
  status:          'available',
  is_reservable:   true,
  kit_category_id: null,
  asset_ids:       [],
  image:           null,
  remove_image:    false,
})
const editAssetSearch   = ref('')
const editScanInput     = ref('')
const editScanLoading   = ref(false)
const editScanMsg       = ref(null)   // { ok, message }

async function scanAssetForEdit() {
  const tag = editScanInput.value.trim()
  if (!tag) return
  editScanLoading.value = true
  editScanMsg.value = null
  try {
    const { data } = await axios.get(route('kits.asset-lookup'), { params: { asset_tag: tag } })
    if (data.in_kit && data.in_kit.id !== editTarget.value?.id) {
      editScanMsg.value = { ok: false, message: `${data.name} est déjà dans le kit « ${data.in_kit.name } »` }
    } else if (editForm.asset_ids.includes(data.id)) {
      editScanMsg.value = { ok: false, message: `${data.name} est déjà sélectionné` }
    } else {
      editForm.asset_ids.push(data.id)
      // Si l'asset n'est pas dans la liste combinée (ex: non-réservable et pas encore dans le kit), l'ajouter à editTarget.assets pour qu'il s'affiche
      const alreadyInList = editTarget.value?.assets.some(a => a.id === data.id) || props.availableAssets.some(a => a.id === data.id)
      if (!alreadyInList && editTarget.value) {
        editTarget.value.assets.push({
          id: data.id, name: data.name, asset_tag: data.asset_tag,
          category: data.category, image: data.image, is_reservable: data.is_reservable,
        })
      }
      editScanMsg.value = { ok: true, message: `✓ ${data.name} (${data.asset_tag}) ajouté` }
      setTimeout(() => { editScanMsg.value = null }, 3000)
    }
  } catch (e) {
    editScanMsg.value = { ok: false, message: e.response?.data?.error ?? 'Équipement introuvable' }
  } finally {
    editScanLoading.value = false
    editScanInput.value = ''
  }
}

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
  const q = editAssetSearch.value.toLowerCase()
  return combined.filter(a =>
    a.name.toLowerCase().includes(q) ||
    a.asset_tag.toLowerCase().includes(q)
  )
})

function openEdit(kit) {
  editTarget.value           = kit
  editForm.name              = kit.name
  editForm.description       = kit.description ?? ''
  editForm.notes             = kit.notes ?? ''
  editForm.status            = kit.status
  editForm.is_reservable     = kit.is_reservable
  editForm.kit_category_id   = kit.category?.id ?? null
  editForm.asset_ids         = kit.assets.map(a => a.id)
  editForm.image             = null
  editForm.remove_image      = false
  editImagePreview.value     = kit.image ?? null
  editAssetSearch.value      = ''
  showEditModal.value        = true
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
      onSuccess: () => {
        showEditModal.value = false
        editScanInput.value = ''
        editScanMsg.value = null
      },
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

// ─── Catégories ───────────────────────────────────────────────────────────────
const showCategoryModal   = ref(false)
const editCategoryTarget  = ref(null)
const categorySubmitting  = ref(false)
const categoryForm        = ref({ name: '', color: '#3B82F6', description: '' })

function openCreateCategory() {
  editCategoryTarget.value = null
  categoryForm.value = { name: '', color: '#3B82F6', description: '' }
  showCategoryModal.value = true
}

function openEditCategory(cat) {
  editCategoryTarget.value = cat
  categoryForm.value = { name: cat.name, color: cat.color, description: cat.description ?? '' }
  showCategoryModal.value = true
}

async function submitCategory() {
  if (!categoryForm.value.name.trim()) return
  categorySubmitting.value = true
  try {
    if (editCategoryTarget.value) {
      await axios.put(route('kit-categories.update', editCategoryTarget.value.id), categoryForm.value)
    } else {
      await axios.post(route('kit-categories.store'), categoryForm.value)
    }
    router.reload({ only: ['kitCategories', 'kits'] })
    showCategoryModal.value = false
  } catch (e) {
    alert('Erreur : ' + (e.response?.data?.message ?? e.message))
  } finally {
    categorySubmitting.value = false
  }
}

function deleteCategory(cat) {
  if (!confirm(`Supprimer la catégorie « ${cat.name} » ?\nLes kits associés ne seront pas supprimés.`)) return
  router.delete(route('kit-categories.destroy', cat.id))
}

// Kits groupés par catégorie (pour l'affichage utilisateur)
const kitsGroupedByCategory = computed(() => {
  const groups = []
  const byCategory = {}
  const uncategorized = []

  props.kits.forEach(kit => {
    if (kit.category) {
      if (!byCategory[kit.category.id]) {
        byCategory[kit.category.id] = { category: kit.category, kits: [] }
      }
      byCategory[kit.category.id].kits.push(kit)
    } else {
      uncategorized.push(kit)
    }
  })

  // Respecter l'ordre des catégories défini par l'admin
  props.kitCategories.forEach(cat => {
    if (byCategory[cat.id]) groups.push(byCategory[cat.id])
  })

  if (uncategorized.length > 0) {
    groups.push({ category: null, kits: uncategorized })
  }

  return groups
})
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
        <div class="flex items-center gap-2">
          <!-- Toggle vue liste / grille (visible par tous) -->
          <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden">
            <button @click="viewMode = 'grid'" :title="'Vue grille'"
              :class="['px-2.5 py-2 transition', viewMode === 'grid' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-gray-50']">
              <Squares2X2Icon class="w-4 h-4" />
            </button>
            <button @click="viewMode = 'list'" :title="'Vue liste'"
              :class="['px-2.5 py-2 transition border-l border-gray-200', viewMode === 'list' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-gray-50']">
              <ListBulletIcon class="w-4 h-4" />
            </button>
          </div>
          <template v-if="isAdmin">
            <button @click="showCategoryModal = true; editCategoryTarget = null; categoryForm = { name: '', color: '#3B82F6', description: '' }"
              class="flex items-center gap-2 border border-gray-300 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" /></svg>
              Catégories
            </button>
            <button @click="showCreateModal = true"
              class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition shadow-sm">
              <PlusIcon class="w-4 h-4" />
              Nouveau kit
            </button>
          </template>
        </div>
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

        <!-- Admin : grille plate avec badge catégorie -->
        <template v-if="isAdmin">
          <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <div v-for="kit in kits" :key="kit.id"
                 class="bg-white rounded-xl shadow-sm border overflow-hidden">
              <div v-if="kit.image" class="h-28 bg-gray-100 overflow-hidden">
                <img :src="kit.image" class="w-full h-full object-cover" :alt="kit.name" />
              </div>
              <div class="px-4 pt-4 pb-3 border-b border-gray-100">
                <!-- Badge catégorie -->
                <div v-if="kit.category" class="mb-1.5">
                  <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full"
                    :style="{ backgroundColor: kit.category.color + '22', color: kit.category.color, border: '1px solid ' + kit.category.color + '44' }">
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :style="{ backgroundColor: kit.category.color }"></span>
                    {{ kit.category.name }}
                  </span>
                </div>
                <div class="flex items-start justify-between gap-2 mb-1">
                  <h3 class="font-semibold text-gray-900 leading-tight">{{ kit.name }}</h3>
                  <div class="flex items-center gap-1 flex-shrink-0 flex-wrap justify-end">
                    <span v-if="kit.in_preparation"
                      class="text-xs border px-2 py-0.5 rounded-full font-medium bg-blue-100 text-blue-700 border-blue-200">
                      🔧 En préparation
                    </span>
                    <span v-else-if="kit.partially_in_preparation"
                      class="text-xs border px-2 py-0.5 rounded-full font-medium bg-blue-50 text-blue-600 border-blue-200">
                      🔧 Partiel. en préparation
                    </span>
                    <span v-else :class="['text-xs border px-2 py-0.5 rounded-full font-medium', statusColors[kit.status]]">
                      {{ kit.status_label }}
                    </span>
                  </div>
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

              <!-- ── Note d'état du kit (admin : éditable inline) ── -->
              <div class="px-4 py-2 border-b border-gray-100">
                <!-- Mode lecture -->
                <div v-if="!editingNote[kit.id] && conditionNotes[kit.id]"
                     class="flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                  <span class="text-lg flex-shrink-0 mt-0.5">📋</span>
                  <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-0.5">État du kit</p>
                    <p class="text-xs text-amber-900 whitespace-pre-wrap">{{ conditionNotes[kit.id] }}</p>
                  </div>
                  <button @click="editingNote[kit.id] = true"
                          class="flex-shrink-0 p-1 text-amber-500 hover:text-amber-700 hover:bg-amber-100 rounded transition"
                          title="Modifier la note d'état">
                    <PencilIcon class="w-3.5 h-3.5" />
                  </button>
                </div>
                <!-- Mode placeholder (pas de note) -->
                <div v-else-if="!editingNote[kit.id]"
                     @click="editingNote[kit.id] = true"
                     class="flex items-center gap-2 border border-dashed border-amber-200 rounded-lg px-3 py-2 cursor-pointer hover:border-amber-400 hover:bg-amber-50 transition group">
                  <span class="text-base opacity-40">📋</span>
                  <p class="text-xs text-gray-400 group-hover:text-amber-600 transition">
                    Ajouter une note d'état visible par les utilisateurs…
                  </p>
                </div>
                <!-- Mode édition -->
                <div v-if="editingNote[kit.id]" class="bg-amber-50 border border-amber-300 rounded-lg p-3">
                  <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1.5">Note d'état du kit</p>
                  <textarea v-model="conditionNotes[kit.id]" rows="3"
                            placeholder="Ex : ⚠️ Câble HDMI manquant — prévoir remplacement. Télécommande fonctionnelle."
                            class="w-full border border-amber-300 bg-white rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-amber-400 outline-none resize-none"
                            @keydown.escape="cancelNote(kit.id)" />
                  <div class="flex gap-2 mt-2">
                    <button @click="saveNote(kit.id)" :disabled="savingNote[kit.id]"
                            class="px-3 py-1 bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-white text-xs font-semibold rounded-lg transition">
                      {{ savingNote[kit.id] ? '…' : '✓ Enregistrer' }}
                    </button>
                    <button @click="cancelNote(kit.id)"
                            class="px-3 py-1 border border-gray-300 text-gray-600 text-xs rounded-lg hover:bg-gray-50 transition">
                      Annuler
                    </button>
                  </div>
                </div>
              </div>

              <div class="px-4 py-3 space-y-1.5 max-h-40 overflow-y-auto">
                <div v-for="asset in kit.assets" :key="asset.id"
                     class="flex items-center gap-2 text-xs text-gray-600">
                  <AssetThumbnail :src="asset.image" :alt="asset.name" size="xs" />
                  <span class="truncate font-medium flex-1">{{ asset.name }}</span>
                  <span v-if="asset.status === 'en_preparation'"
                    class="text-xs bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded-full flex-shrink-0">En prépa.</span>
                  <span class="font-mono text-gray-400 flex-shrink-0">{{ asset.asset_tag }}</span>
                </div>
              </div>
              <div class="px-4 py-3 border-t border-gray-100 flex items-center gap-2">
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
              </div>
            </div>
          </div>

          <!-- Admin : vue liste -->
          <div v-else class="bg-white rounded-xl border overflow-hidden">
            <table class="min-w-full divide-y divide-gray-100">
              <thead class="bg-gray-50">
                <tr>
                  <th class="w-12 px-3 py-2.5"></th>
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Kit</th>
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Catégorie</th>
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Statut</th>
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Équipements</th>
                  <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Note d'état</th>
                  <th class="px-4 py-2.5"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="kit in kits" :key="kit.id" class="hover:bg-gray-50 transition">
                  <!-- Image -->
                  <td class="px-3 py-3">
                    <div class="w-10 h-10 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center flex-shrink-0">
                      <img v-if="kit.image" :src="kit.image" :alt="kit.name" class="w-full h-full object-cover" />
                      <WrenchScrewdriverIcon v-else class="w-5 h-5 text-gray-300" />
                    </div>
                  </td>
                  <!-- Nom + description -->
                  <td class="px-4 py-3">
                    <p class="font-semibold text-gray-900 text-sm">{{ kit.name }}</p>
                    <p v-if="kit.description" class="text-xs text-gray-400 truncate max-w-[200px]">{{ kit.description }}</p>
                  </td>
                  <!-- Catégorie -->
                  <td class="px-4 py-3">
                    <span v-if="kit.category" class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full"
                      :style="{ backgroundColor: kit.category.color + '22', color: kit.category.color, border: '1px solid ' + kit.category.color + '44' }">
                      {{ kit.category.name }}
                    </span>
                    <span v-else class="text-xs text-gray-300">—</span>
                  </td>
                  <!-- Statut -->
                  <td class="px-4 py-3">
                    <span v-if="kit.in_preparation" class="text-xs border px-2 py-0.5 rounded-full font-medium bg-blue-100 text-blue-700 border-blue-200">🔧 En préparation</span>
                    <span v-else :class="['text-xs border px-2 py-0.5 rounded-full font-medium', statusColors[kit.status]]">{{ kit.status_label }}</span>
                  </td>
                  <!-- Équipements -->
                  <td class="px-4 py-3 hidden md:table-cell">
                    <span class="text-sm text-gray-600">{{ kit.assets_count }}</span>
                    <span v-if="kit.pending_missing_count > 0" class="ml-2 text-xs text-orange-600">⚠️ {{ kit.pending_missing_count }} manquant(s)</span>
                  </td>
                  <!-- Note d'état (truncated) -->
                  <td class="px-4 py-3 hidden lg:table-cell max-w-[200px]">
                    <p v-if="conditionNotes[kit.id]" class="text-xs text-amber-700 truncate">📋 {{ conditionNotes[kit.id] }}</p>
                    <span v-else class="text-xs text-gray-300">—</span>
                  </td>
                  <!-- Actions -->
                  <td class="px-3 py-3">
                    <div class="flex gap-1 justify-end">
                      <button @click="openEdit(kit)"
                        class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition">
                        <PencilIcon class="w-4 h-4" />
                      </button>
                      <button @click="deleteKit(kit)"
                        class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                        <TrashIcon class="w-4 h-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <!-- Utilisateurs : affichage groupé par catégorie -->
        <template v-else>
          <div class="space-y-8">
            <div v-for="group in kitsGroupedByCategory" :key="group.category?.id ?? 'uncategorized'">
              <!-- En-tête de catégorie -->
              <div v-if="group.category" class="flex items-center gap-3 mb-4">
                <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: group.category.color }"></span>
                <h3 class="text-base font-semibold text-gray-800">{{ group.category.name }}</h3>
                <span class="text-sm text-gray-400 font-normal">({{ group.kits.length }} kit(s))</span>
                <div class="flex-1 h-px bg-gray-200"></div>
              </div>
              <div v-else class="flex items-center gap-3 mb-4">
                <span class="w-3 h-3 rounded-full bg-gray-300 flex-shrink-0"></span>
                <h3 class="text-base font-semibold text-gray-500">Sans catégorie</h3>
                <span class="text-sm text-gray-400 font-normal">({{ group.kits.length }} kit(s))</span>
                <div class="flex-1 h-px bg-gray-200"></div>
              </div>
              <p v-if="group.category?.description" class="text-sm text-gray-500 -mt-3 mb-4 pl-6">{{ group.category.description }}</p>

              <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <div v-for="kit in group.kits" :key="kit.id"
                     class="bg-white rounded-xl shadow-sm border overflow-hidden">
                  <div v-if="kit.image" class="h-28 bg-gray-100 overflow-hidden">
                    <img :src="kit.image" class="w-full h-full object-cover" :alt="kit.name" />
                  </div>
                  <div class="px-4 pt-4 pb-3 border-b border-gray-100">
                    <div class="flex items-start justify-between gap-2 mb-1">
                      <h3 class="font-semibold text-gray-900 leading-tight">{{ kit.name }}</h3>
                      <span v-if="kit.in_preparation || kit.partially_in_preparation"
                        class="text-xs border px-2 py-0.5 rounded-full font-medium flex-shrink-0 bg-blue-100 text-blue-700 border-blue-200">
                        🔧 En préparation
                      </span>
                      <span v-else :class="['text-xs border px-2 py-0.5 rounded-full font-medium flex-shrink-0', statusColors[kit.status]]">
                        {{ kit.status_label }}
                      </span>
                    </div>
                    <p v-if="kit.description" class="text-xs text-gray-500 mt-1">{{ kit.description }}</p>
                    <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
                      <span>{{ kit.assets_count }} équipement(s)</span>
                    </div>
                  </div>

                  <!-- ── Note d'état du kit (utilisateurs : lecture seule) ── -->
                  <div v-if="kit.condition_note" class="px-4 py-2 border-b border-gray-100">
                    <div class="flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                      <span class="text-base flex-shrink-0 mt-0.5">📋</span>
                      <div>
                        <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide mb-0.5">État du kit</p>
                        <p class="text-xs text-amber-900 whitespace-pre-wrap">{{ kit.condition_note }}</p>
                      </div>
                    </div>
                  </div>

                  <div class="px-4 py-3 space-y-1.5 max-h-40 overflow-y-auto">
                    <div v-for="asset in kit.assets" :key="asset.id"
                         class="flex items-center gap-2 text-xs text-gray-600">
                      <AssetThumbnail :src="asset.image" :alt="asset.name" size="xs" />
                      <span class="truncate font-medium flex-1">{{ asset.name }}</span>
                      <span v-if="asset.status === 'en_preparation'"
                        class="text-xs bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded-full flex-shrink-0">En prépa.</span>
                      <span class="font-mono text-gray-400 flex-shrink-0">{{ asset.asset_tag }}</span>
                    </div>
                  </div>
                  <div class="px-4 py-3 border-t border-gray-100">
                    <button
                      v-if="kit.is_reservable && kit.status !== 'unavailable' && !kit.partially_in_preparation"
                      @click="toggleKit(kit)"
                      :class="['w-full flex items-center justify-center gap-2 py-2 rounded-lg text-sm font-semibold transition',
                        cart.hasKit(kit.id)
                          ? 'bg-green-100 text-green-700 hover:bg-red-100 hover:text-red-600 border border-green-300'
                          : 'bg-blue-600 text-white hover:bg-blue-700']">
                      <ShoppingCartIcon class="w-4 h-4" />
                      {{ cart.hasKit(kit.id) ? '✓ Dans le panier' : 'Ajouter au panier' }}
                    </button>
                    <p v-else-if="kit.partially_in_preparation" class="text-center text-xs text-blue-500 py-2">🔧 En cours de préparation</p>
                    <p v-else class="text-center text-xs text-gray-400 py-2">Non disponible à la réservation</p>
                  </div>
                </div>
              </div>

              <!-- Utilisateurs : vue liste par groupe -->
              <div v-else class="bg-white rounded-xl border overflow-hidden mb-2">
                <div v-for="kit in group.kits" :key="kit.id"
                  class="flex items-center gap-4 px-4 py-3 border-b last:border-0 hover:bg-gray-50 transition">
                  <div class="w-10 h-10 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0 flex items-center justify-center">
                    <img v-if="kit.image" :src="kit.image" :alt="kit.name" class="w-full h-full object-cover" />
                    <WrenchScrewdriverIcon v-else class="w-5 h-5 text-gray-300" />
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm text-gray-900">{{ kit.name }}</p>
                    <p v-if="kit.description" class="text-xs text-gray-400 truncate">{{ kit.description }}</p>
                  </div>
                  <span v-if="kit.in_preparation || kit.partially_in_preparation"
                    class="text-xs border px-2 py-0.5 rounded-full font-medium flex-shrink-0 bg-blue-100 text-blue-700 border-blue-200">
                    🔧 En préparation
                  </span>
                  <span v-else :class="['text-xs border px-2 py-0.5 rounded-full font-medium flex-shrink-0', statusColors[kit.status]]">
                    {{ kit.status_label }}
                  </span>
                  <span class="text-xs text-gray-400 flex-shrink-0">{{ kit.assets_count }} éq.</span>
                  <button @click="toggleKit(kit)"
                    :disabled="!kit.is_reservable"
                    :class="['flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg transition flex-shrink-0',
                      cart.hasKit(kit.id) ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-blue-50 text-blue-700 hover:bg-blue-100',
                      !kit.is_reservable ? 'opacity-50 cursor-not-allowed' : '']">
                    <ShoppingCartIcon class="w-3.5 h-3.5" />
                    {{ cart.hasKit(kit.id) ? 'Retirer' : 'Ajouter' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </template>
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

          <!-- Catégorie -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
            <select v-model="createForm.kit_category_id"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
              <option :value="null">— Sans catégorie —</option>
              <option v-for="cat in kitCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
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
            <!-- Scan par code-barres -->
            <div class="flex items-center gap-2 mb-2">
              <div class="relative flex-1">
                <QrCodeIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input v-model="createScanInput" type="text" placeholder="Scanner ou saisir un code-barres…"
                  @keydown.enter="scanAssetForCreate"
                  class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <button @click="scanAssetForCreate" :disabled="createScanLoading"
                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-600 transition disabled:opacity-50">
                <ArrowPathIcon v-if="createScanLoading" class="h-4 w-4 animate-spin" />
                <PlusIcon v-else class="h-4 w-4" />
              </button>
            </div>
            <div v-if="createScanMsg" class="text-xs px-3 py-1.5 rounded mb-2"
              :class="createScanMsg.ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
              {{ createScanMsg.message }}
            </div>
            <!-- Recherche texte -->
            <input v-model="assetSearch" type="text" placeholder="Filtrer la liste…"
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
                <span v-if="!asset.is_reservable"
                  class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full shrink-0">
                  Hors réservation
                </span>
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

          <!-- Catégorie -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
            <select v-model="editForm.kit_category_id"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
              <option :value="null">— Sans catégorie —</option>
              <option v-for="cat in kitCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
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
            <!-- Scan par code-barres -->
            <div class="flex items-center gap-2 mb-2">
              <div class="relative flex-1">
                <QrCodeIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input v-model="editScanInput" type="text" placeholder="Scanner ou saisir un code-barres…"
                  @keydown.enter="scanAssetForEdit"
                  class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <button @click="scanAssetForEdit" :disabled="editScanLoading"
                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-gray-600 transition disabled:opacity-50">
                <ArrowPathIcon v-if="editScanLoading" class="h-4 w-4 animate-spin" />
                <PlusIcon v-else class="h-4 w-4" />
              </button>
            </div>
            <div v-if="editScanMsg" class="text-xs px-3 py-1.5 rounded mb-2"
              :class="editScanMsg.ok ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
              {{ editScanMsg.message }}
            </div>
            <!-- Recherche texte -->
            <input v-model="editAssetSearch" type="text" placeholder="Filtrer la liste…"
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
                <span v-if="!asset.is_reservable && !editForm.asset_ids.includes(asset.id)"
                  class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full shrink-0">
                  Hors réservation
                </span>
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

    <!-- ═══ Modal : Gérer les catégories ═══ -->
    <div v-if="showCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">Catégories de kits</h3>
          <button @click="showCategoryModal=false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>

        <!-- Liste des catégories existantes -->
        <div v-if="kitCategories.length > 0" class="mb-5">
          <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-2">Catégories existantes</p>
          <div class="space-y-2">
            <div v-for="cat in kitCategories" :key="cat.id"
                 class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
              <span class="w-4 h-4 rounded-full flex-shrink-0" :style="{ backgroundColor: cat.color }"></span>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900">{{ cat.name }}</p>
                <p v-if="cat.description" class="text-xs text-gray-500 truncate">{{ cat.description }}</p>
              </div>
              <div class="flex items-center gap-1 flex-shrink-0">
                <button @click="openEditCategory(cat)"
                  class="p-1.5 text-blue-500 hover:bg-blue-50 rounded-lg transition">
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button @click="deleteCategory(cat)"
                  class="p-1.5 text-red-400 hover:bg-red-50 rounded-lg transition">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="mb-5 text-center py-6 text-gray-400 text-sm border border-dashed rounded-lg">
          Aucune catégorie créée
        </div>

        <!-- Formulaire création / édition -->
        <div class="border-t pt-4">
          <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-3">
            {{ editCategoryTarget ? 'Modifier la catégorie' : 'Nouvelle catégorie' }}
          </p>
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
              <input v-model="categoryForm.name" type="text" placeholder="Son, Vidéo, Éclairage…"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
              <div class="flex items-center gap-3">
                <input v-model="categoryForm.color" type="color"
                  class="h-9 w-16 border border-gray-300 rounded-lg cursor-pointer p-0.5" />
                <span class="text-sm font-mono text-gray-500">{{ categoryForm.color }}</span>
                <!-- Palettes rapides -->
                <div class="flex gap-1.5">
                  <button v-for="c in ['#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#6B7280']"
                    :key="c" type="button" @click="categoryForm.color = c"
                    class="w-6 h-6 rounded-full border-2 transition hover:scale-110"
                    :style="{ backgroundColor: c, borderColor: categoryForm.color === c ? '#1e293b' : 'transparent' }">
                  </button>
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description (facultatif)</label>
              <input v-model="categoryForm.description" type="text" placeholder="Brève description…"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
          </div>
          <div class="flex gap-3 mt-4">
            <button v-if="editCategoryTarget" @click="editCategoryTarget = null; categoryForm = { name: '', color: '#3B82F6', description: '' }"
              class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
            <button @click="submitCategory" :disabled="categorySubmitting || !categoryForm.name.trim()"
              class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
              {{ categorySubmitting ? 'Enregistrement…' : editCategoryTarget ? 'Modifier' : 'Créer la catégorie' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    </template><!-- fin v-if isAdmin -->

  </AppLayout>
</template>
