<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { useCartStore } from '@/stores/cart'
import { Link, router, usePage, useForm } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import axios from 'axios'
import {
  MagnifyingGlassIcon, PlusIcon,
  ShoppingCartIcon, CheckIcon, EyeIcon, PencilIcon,
  MapPinIcon, TagIcon, BuildingStorefrontIcon,
  ListBulletIcon, Squares2X2Icon, PhotoIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const page    = usePage()
const cart    = useCartStore()
const isAdmin = computed(() => page.props.auth.user?.is_admin)

// Statuts dynamiques triés par sort_order
const assetStatuses = computed(() =>
  Object.values(page.props.assetStatuses ?? {})
    .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
)

const props = defineProps({
  assets:                 Object,
  categories:             Array,
  locations:              Array,
  manufacturers:          Array,
  models:                 Array,
  filters:                Object,
  reservableFilterActive: Boolean,
})

const search           = ref(props.filters?.search       ?? '')
const selectedCategory = ref(props.filters?.category     ?? '')
const selectedStatus   = ref(props.filters?.status       ?? '')
const selectedLocation = ref(props.filters?.location     ?? '')
const selectedMfr      = ref(props.filters?.manufacturer ?? '')

// ── Persistance de la recherche via sessionStorage ──────────────────────────
const STORAGE_KEY = 'assets_filters'

// Restaurer les filtres sauvegardés si aucun filtre serveur n'est actif
onMounted(() => {
  const hasServerFilters =
    props.filters?.search || props.filters?.category ||
    props.filters?.status || props.filters?.location ||
    props.filters?.manufacturer

  if (!hasServerFilters) {
    try {
      const saved = sessionStorage.getItem(STORAGE_KEY)
      if (saved) {
        const f = JSON.parse(saved)
        const hasData = f.search || f.category || f.status || f.location || f.manufacturer
        if (hasData) {
          search.value           = f.search       || ''
          selectedCategory.value = f.category     || ''
          selectedStatus.value   = f.status       || ''
          selectedLocation.value = f.location     || ''
          selectedMfr.value      = f.manufacturer || ''
          nextTick(() => applyFilters())
        }
      }
    } catch (_) {}
  }
})

// Sauvegarder les filtres à chaque changement
watch([search, selectedCategory, selectedStatus, selectedLocation, selectedMfr], () => {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
      search:       search.value,
      category:     selectedCategory.value,
      status:       selectedStatus.value,
      location:     selectedLocation.value,
      manufacturer: selectedMfr.value,
    }))
  } catch (_) {}
})
// ─────────────────────────────────────────────────────────────────────────────

// Onglet actif — depuis le paramètre URL tab
const activeTab = ref(props.filters?.tab ?? 'list')

// View mode: 'list' or 'grid' — default to 'grid' for non-admin users
const viewMode = ref(isAdmin.value ? 'list' : 'grid')

// Model grouping toggle (for list view)
const groupByModel = ref(false)

const localReservable = ref({})

// Nombre d'éléments visibles — initialisé depuis per_page de la réponse courante
const currentPerPage = ref(props.assets?.per_page ?? 24)
const loadingMore    = ref(false)

function routeParams(extra = {}) {
  const tab = activeTab.value !== 'list' ? activeTab.value : undefined
  return {
    search:       search.value           || undefined,
    category:     selectedCategory.value || undefined,
    status:       selectedStatus.value   || undefined,
    location:     selectedLocation.value || undefined,
    manufacturer: selectedMfr.value      || undefined,
    per_page:     currentPerPage.value,
    tab,
    ...extra,
  }
}

function applyFilters(extra = {}) {
  router.get(route('assets.index'), routeParams({ page: 1, ...extra }),
    { preserveState: true, replace: true })
}

function loadMore() {
  loadingMore.value = true
  const newPerPage  = currentPerPage.value + 24
  currentPerPage.value = newPerPage
  router.get(route('assets.index'), routeParams({ per_page: newPerPage }), {
    preserveState: true, preserveScroll: true, replace: true,
    onFinish: () => { loadingMore.value = false },
  })
}

function showAll() {
  loadingMore.value = true
  currentPerPage.value = 200
  router.get(route('assets.index'), routeParams({ per_page: 200, page: 1 }), {
    preserveState: true, preserveScroll: true, replace: true,
    onFinish: () => { loadingMore.value = false },
  })
}

function goToPage(p) {
  router.get(route('assets.index'), routeParams({ page: p }), {
    preserveState: true, preserveScroll: false, replace: true,
  })
}

function changePerPage(n) {
  currentPerPage.value = n
  router.get(route('assets.index'), routeParams({ per_page: n, page: 1 }), {
    preserveState: true, preserveScroll: false, replace: true,
  })
}

// Pages visibles dans la pagination (max 7 boutons)
const visiblePages = computed(() => {
  const total = props.assets?.last_page ?? 1
  const cur   = props.assets?.current_page ?? 1
  if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1)
  const pages = new Set([1, total, cur])
  for (let i = cur - 2; i <= cur + 2; i++) { if (i > 0 && i <= total) pages.add(i) }
  return [...pages].sort((a, b) => a - b)
})

// Réinitialiser le per_page quand les filtres changent + vider la sélection
function resetAndFilter(extra = {}) {
  currentPerPage.value = 24
  selectedIds.value = []
  applyFilters({ per_page: 24, ...extra })
}

function filterByLocation(loc) {
  selectedLocation.value = loc; selectedMfr.value = ''
  activeTab.value = 'list'; resetAndFilter()
}

function filterByManufacturer(mfr) {
  selectedMfr.value = mfr; selectedLocation.value = ''
  activeTab.value = 'list'; resetAndFilter()
}

function filterByCategory(catId) {
  selectedCategory.value = catId; selectedLocation.value = ''; selectedMfr.value = ''
  activeTab.value = 'list'; resetAndFilter()
}

function clearFilters() {
  search.value = ''; selectedCategory.value = ''; selectedStatus.value = ''
  selectedLocation.value = ''; selectedMfr.value = ''; resetAndFilter()
}

async function toggleReservable(asset) {
  const current = localReservable.value[asset.id] ?? asset.is_reservable
  localReservable.value[asset.id] = !current
  try {
    const { data } = await axios.patch(route('assets.toggle-reservable', asset.id))
    localReservable.value[asset.id] = data.is_reservable
  } catch {
    localReservable.value[asset.id] = current
  }
}

function isReservable(asset) {
  return localReservable.value[asset.id] ?? asset.is_reservable
}

function toggleCart(asset) {
  cart.hasItem(asset.id) ? cart.removeItem(asset.id) : cart.addItem(asset)
}

const catColors = [
  'bg-blue-100 text-blue-600', 'bg-purple-100 text-purple-600',
  'bg-green-100 text-green-600', 'bg-yellow-100 text-yellow-600',
  'bg-red-100 text-red-600', 'bg-indigo-100 text-indigo-600',
  'bg-pink-100 text-pink-600', 'bg-orange-100 text-orange-600',
]
const catColor = (i) => catColors[i % catColors.length]

// ─── Édition de catégorie (admin) ────────────────────────────────────────────
const showCatEditModal = ref(false)
const catEditTarget    = ref(null)
const catEditForm      = useForm({ name: '', description: '', color: '#3B82F6', image: null })
const catEditPreview   = ref(null)

function openCatEdit(cat, e) {
  e.stopPropagation()
  catEditTarget.value      = cat
  catEditForm.name         = cat.name
  catEditForm.description  = cat.description ?? ''
  catEditForm.color        = cat.color ?? '#3B82F6'
  catEditForm.image        = null
  catEditPreview.value     = cat.image_url ?? null
  showCatEditModal.value   = true
}

function onCatEditImage(e) {
  const file = e.target.files[0]
  if (!file) return
  catEditForm.image = file
  catEditPreview.value = URL.createObjectURL(file)
}

function submitCatEdit() {
  catEditForm.put(route('categories.update', catEditTarget.value.id), {
    forceFormData: true,
    onSuccess: () => { showCatEditModal.value = false },
  })
}

const activeFiltersCount = computed(() =>
  [selectedCategory.value, selectedStatus.value, selectedLocation.value, selectedMfr.value, search.value]
    .filter(Boolean).length
)

// Grouped assets by model
const groupedAssets = computed(() => {
  if (!groupByModel.value) return null
  const groups = {}
  const ungrouped = []
  for (const asset of props.assets.data) {
    if (asset.equipment_model_id) {
      const key = asset.equipment_model_id
      if (!groups[key]) {
        groups[key] = { modelName: asset.model_name ?? 'Modèle inconnu', modelId: key, assets: [] }
      }
      groups[key].assets.push(asset)
    } else {
      ungrouped.push(asset)
    }
  }
  const result = Object.values(groups)
  if (ungrouped.length > 0) {
    result.push({ modelName: 'Sans modèle', modelId: null, assets: ungrouped })
  }
  return result
})

// Status label helper
function statusLabel(status) {
  const map = { available: 'Disponible', reserved: 'Réservé', checked_out: 'Sorti', maintenance: 'Maintenance', retired: 'Retiré' }
  return map[status] ?? status
}

function statusColor(status) {
  const map = {
    available: 'bg-green-100 text-green-700',
    reserved: 'bg-blue-100 text-blue-700',
    checked_out: 'bg-orange-100 text-orange-700',
    maintenance: 'bg-yellow-100 text-yellow-700',
    retired: 'bg-gray-100 text-gray-500',
  }
  return map[status] ?? 'bg-gray-100 text-gray-500'
}

// ═══════════════════════════════════════
// Sélection multiple (admin)
// ═══════════════════════════════════════
const selectedIds         = ref([])
const showBulkDeleteModal = ref(false)
const showBulkEditModal   = ref(false)
const bulkProcessing      = ref(false)

const bulkEditForm = ref({
  updateStatus:    false, status:              '',
  updateCategory:  false, category_id:         '',
  updateLocation:  false, location:            '',
  updateReservable:false, is_reservable:       '',
  updateModel:     false, equipment_model_id:  '',
})

const allOnPageSelected = computed(() => {
  const pageIds = props.assets.data.map(a => a.id)
  return pageIds.length > 0 && pageIds.every(id => selectedIds.value.includes(id))
})

function toggleSelectAll() {
  const pageIds = props.assets.data.map(a => a.id)
  if (allOnPageSelected.value) {
    selectedIds.value = selectedIds.value.filter(id => !pageIds.includes(id))
  } else {
    selectedIds.value = [...new Set([...selectedIds.value, ...pageIds])]
  }
}

function toggleSelect(id) {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter(i => i !== id)
  } else {
    selectedIds.value = [...selectedIds.value, id]
  }
}

function openBulkEdit() {
  bulkEditForm.value = {
    updateStatus:    false, status:             '',
    updateCategory:  false, category_id:        '',
    updateLocation:  false, location:           '',
    updateReservable:false, is_reservable:      '',
    updateModel:     false, equipment_model_id: '',
  }
  showBulkEditModal.value = true
}

function executeBulkDelete() {
  bulkProcessing.value = true
  router.post(route('assets.bulk-delete'), { ids: [...selectedIds.value] }, {
    onSuccess: () => { showBulkDeleteModal.value = false; selectedIds.value = [] },
    onFinish:  () => { bulkProcessing.value = false },
  })
}

function executeBulkUpdate() {
  const payload = { ids: [...selectedIds.value] }
  if (bulkEditForm.value.updateStatus)     payload.status               = bulkEditForm.value.status
  if (bulkEditForm.value.updateCategory)   payload.category_id          = bulkEditForm.value.category_id || null
  if (bulkEditForm.value.updateLocation)   payload.location             = bulkEditForm.value.location
  if (bulkEditForm.value.updateReservable) payload.is_reservable        = bulkEditForm.value.is_reservable
  if (bulkEditForm.value.updateModel)      payload.equipment_model_id   = bulkEditForm.value.equipment_model_id || null
  bulkProcessing.value = true
  router.post(route('assets.bulk-update'), payload, {
    onSuccess: () => { showBulkEditModal.value = false; selectedIds.value = [] },
    onFinish:  () => { bulkProcessing.value = false },
  })
}

function duplicateAsset(assetId) {
  if (!confirm('Dupliquer cet équipement ? Un nouvel asset identique sera créé avec un tag unique.')) return
  router.post(route('assets.duplicate', assetId))
}

// ─── Réservation groupée depuis la sélection ─────────────────────────────────
const showBulkReserveModal  = ref(false)
const bulkReserveProcessing = ref(false)
const bulkReserveErrors     = ref({})
const bulkReserveForm       = ref({ title: '', start_date: '', end_date: '', notes: '' })

function openBulkReserve() {
  bulkReserveForm.value  = { title: '', start_date: '', end_date: '', notes: '' }
  bulkReserveErrors.value = {}
  showBulkReserveModal.value = true
}

function executeBulkReserve() {
  bulkReserveProcessing.value = true
  bulkReserveErrors.value = {}
  router.post(route('reservations.store'), {
    ...bulkReserveForm.value,
    asset_ids: [...selectedIds.value],
  }, {
    onSuccess: () => { showBulkReserveModal.value = false; selectedIds.value = [] },
    onError:   (errors) => { bulkReserveErrors.value = errors },
    onFinish:  () => { bulkReserveProcessing.value = false },
  })
}

</script>

<template>
  <AppLayout>
    <div>
      <!-- En-tête -->
      <div class="flex items-center justify-between mb-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Équipements</h1>
          <p class="text-gray-500 text-sm">
            {{ assets.total }} équipement(s)
            <span v-if="!isAdmin && reservableFilterActive" class="text-blue-600">disponibles à l'emprunt</span>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <!-- View toggle (only in list tab) -->
          <template v-if="activeTab === 'list'">
            <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
              <button @click="viewMode='list'"
                      :class="['px-3 py-2 text-sm transition', viewMode === 'list' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50']"
                      title="Vue liste">
                <ListBulletIcon class="w-4 h-4" />
              </button>
              <button @click="viewMode='grid'"
                      :class="['px-3 py-2 text-sm transition', viewMode === 'grid' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50']"
                      title="Vue grille">
                <Squares2X2Icon class="w-4 h-4" />
              </button>
            </div>
            <!-- Group by model toggle (list view, admin only) -->
            <button v-if="isAdmin && viewMode === 'list'" @click="groupByModel = !groupByModel"
                    :class="['flex items-center gap-1 px-3 py-2 text-xs border rounded-lg transition',
                      groupByModel ? 'bg-purple-100 text-purple-700 border-purple-300' : 'border-gray-300 text-gray-600 hover:bg-gray-50']">
              Grouper par modèle
            </button>
          </template>
          <Link v-if="!isAdmin" :href="route('cart.index')"
                class="relative flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            <ShoppingCartIcon class="w-4 h-4" />
            Mon panier
            <span v-if="cart.count > 0"
                  class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">
              {{ cart.count }}
            </span>
          </Link>
          <a v-if="isAdmin" :href="route('reports.export-csv')"
             class="flex items-center gap-2 border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm font-medium transition">
            ⬇ CSV
          </a>
          <Link v-if="isAdmin" :href="route('assets.create')"
                class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            <PlusIcon class="w-4 h-4" /> Ajouter
          </Link>
        </div>
      </div>

      <!-- Bandeau admin -->
      <div v-if="isAdmin && reservableFilterActive"
           class="mb-4 flex items-start gap-3 bg-purple-50 border border-purple-200 rounded-xl px-4 py-3 text-sm text-purple-800">
        <span>🔒</span>
        <span>
          Le filtre de visibilité est <strong>actif</strong> : les utilisateurs ne voient que les équipements
          <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 px-1.5 py-0.5 rounded font-medium text-xs">● Réservable</span>.
          Modifiez dans les <Link :href="route('settings.index')" class="underline font-medium">Paramètres</Link>.
        </span>
      </div>

      <!-- Bandeau utilisateur -->
      <div v-if="!isAdmin"
           class="mb-4 flex items-start gap-3 bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 text-sm text-blue-800">
        <ShoppingCartIcon class="w-5 h-5 flex-shrink-0 mt-0.5" />
        <span>
          Ajoutez des équipements à votre panier puis confirmez votre réservation.
          <Link :href="route('cart.index')" class="font-semibold underline ml-1">
            Voir mon panier ({{ cart.count }})
          </Link>
        </span>
      </div>

      <!-- ═══ Barre d'actions groupées (admin, sélection active) ═══ -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2">
        <div v-if="isAdmin && selectedIds.length > 0"
             class="mb-4 flex items-center gap-3 bg-blue-600 text-white rounded-xl px-5 py-3 shadow-lg">
          <div class="flex items-center gap-2">
            <span class="font-bold text-lg">{{ selectedIds.length }}</span>
            <span class="text-blue-200 text-sm">équipement(s) sélectionné(s)</span>
          </div>
          <button @click="selectedIds = []"
                  class="text-blue-300 hover:text-white text-xs underline ml-1">
            Tout désélectionner
          </button>
          <div class="ml-auto flex gap-2">
            <button @click="openBulkReserve"
                    class="flex items-center gap-1.5 bg-green-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition">
              📅 Réserver ({{ selectedIds.length }})
            </button>
            <button @click="openBulkEdit"
                    class="flex items-center gap-1.5 bg-white text-blue-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-50 transition">
              ✏️ Modifier ({{ selectedIds.length }})
            </button>
            <button @click="showBulkDeleteModal = true"
                    class="flex items-center gap-1.5 bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition">
              🗑 Supprimer ({{ selectedIds.length }})
            </button>
          </div>
        </div>
      </Transition>

      <!-- ══ ONGLET LISTE ══ -->
      <div v-if="activeTab === 'list'">

        <!-- Filtres actifs badges -->
        <div v-if="activeFiltersCount > 0" class="mb-3 flex flex-wrap items-center gap-2 text-sm">
          <span class="text-gray-500 text-xs">Filtres actifs :</span>
          <span v-if="selectedLocation" class="flex items-center gap-1 bg-blue-100 text-blue-700 px-2.5 py-1 rounded-full text-xs">
            <MapPinIcon class="w-3 h-3" /> {{ selectedLocation }}
            <button @click="selectedLocation=''; applyFilters()" class="ml-1 hover:text-blue-900 font-bold">×</button>
          </span>
          <span v-if="selectedMfr" class="flex items-center gap-1 bg-green-100 text-green-700 px-2.5 py-1 rounded-full text-xs">
            <BuildingStorefrontIcon class="w-3 h-3" /> {{ manufacturers.find(m => String(m.id) === String(selectedMfr))?.name ?? selectedMfr }}
            <button @click="selectedMfr=''; applyFilters()" class="ml-1 hover:text-green-900 font-bold">×</button>
          </span>
          <span v-if="selectedCategory" class="flex items-center gap-1 bg-purple-100 text-purple-700 px-2.5 py-1 rounded-full text-xs">
            <TagIcon class="w-3 h-3" /> {{ categories.find(c => String(c.id) === String(selectedCategory))?.name }}
            <button @click="selectedCategory=''; applyFilters()" class="ml-1 hover:text-purple-900 font-bold">×</button>
          </span>
          <button @click="clearFilters" class="text-gray-400 hover:text-gray-600 underline text-xs">Tout effacer</button>
        </div>

        <!-- Filtres -->
        <div class="bg-white rounded-xl shadow-sm border p-4 mb-5 flex flex-wrap gap-3">
          <div class="flex-1 min-w-48 relative">
            <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
            <input v-model="search" @input="applyFilters" type="search" placeholder="Rechercher…"
                   class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>
          <select v-model="selectedCategory" @change="applyFilters"
                  class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none min-w-36">
            <option value="">Toutes catégories</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <select v-model="selectedMfr" @change="applyFilters"
                  class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none min-w-36">
            <option value="">Tous fabricants</option>
            <option v-for="m in manufacturers" :key="m.id" :value="m.id">{{ m.name }}</option>
          </select>
          <select v-if="isAdmin" v-model="selectedStatus" @change="applyFilters"
                  class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none min-w-36">
            <option value="">Tous statuts</option>
            <option v-for="s in assetStatuses" :key="s.key" :value="s.key">{{ s.label }}</option>
          </select>
        </div>

        <!-- ═══ GRID VIEW ═══ -->
        <div v-if="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <div v-for="asset in assets.data" :key="asset.id"
               class="bg-white rounded-xl border shadow-sm overflow-hidden hover:shadow-md transition group"
               :class="[
                 { 'opacity-60': isAdmin && !isReservable(asset) },
                 isAdmin && selectedIds.includes(asset.id) ? 'border-blue-400 ring-2 ring-blue-200' : '',
               ]">

            <!-- Image -->
            <div class="relative h-44 bg-gradient-to-br from-gray-100 to-gray-200 overflow-hidden">
              <img v-if="asset.image" :src="asset.image" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" :alt="asset.name" />
              <div v-else class="w-full h-full flex items-center justify-center">
                <span class="text-5xl opacity-40">📦</span>
              </div>
              <!-- Checkbox admin (top-left) -->
              <div v-if="isAdmin" class="absolute top-2 left-2 z-10">
                <input type="checkbox" :checked="selectedIds.includes(asset.id)"
                       @change="toggleSelect(asset.id)"
                       class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer shadow bg-white/90" />
              </div>
              <!-- Status badge overlay -->
              <div :class="['absolute top-2', isAdmin ? 'left-9' : 'left-2']">
                <span :class="['text-xs font-semibold px-2 py-1 rounded-full', statusColor(asset.status)]">
                  {{ statusLabel(asset.status) }}
                </span>
              </div>
              <!-- Cart indicator -->
              <div v-if="!isAdmin && cart.hasItem(asset.id)"
                   class="absolute top-2 right-2 w-6 h-6 bg-green-500 rounded-full flex items-center justify-center">
                <CheckIcon class="w-4 h-4 text-white" />
              </div>
            </div>

            <!-- Content -->
            <div class="p-4">
              <div class="flex items-start justify-between gap-2 mb-2">
                <h3 class="font-semibold text-gray-900 text-sm leading-tight">{{ asset.name }}</h3>
              </div>

              <div class="space-y-1 mb-3">
                <p class="text-xs text-gray-500 font-mono">{{ asset.asset_tag }}</p>
                <div v-if="asset.category" class="flex items-center gap-1">
                  <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-medium">
                    {{ asset.category }}
                  </span>
                </div>
                <div v-if="asset.manufacturer" class="flex items-center gap-1 text-xs text-gray-400">
                  <BuildingStorefrontIcon class="w-3 h-3" />
                  {{ asset.manufacturer }}
                </div>
              </div>

              <!-- Admin actions -->
              <div v-if="isAdmin" class="flex gap-2">
                <Link :href="route('assets.show', asset.id)"
                      class="flex-1 text-center text-xs text-blue-600 border border-blue-200 py-1.5 rounded-lg hover:bg-blue-50 transition">
                  Voir
                </Link>
                <Link :href="route('assets.edit', asset.id)"
                      class="flex-1 text-center text-xs text-gray-600 border border-gray-200 py-1.5 rounded-lg hover:bg-gray-50 transition">
                  Modifier
                </Link>
              </div>

              <!-- User actions : voir + panier -->
              <div v-else class="flex flex-col gap-2">
                <Link :href="route('assets.show', asset.id)"
                      class="w-full flex items-center justify-center gap-1.5 text-xs text-gray-600 border border-gray-200 py-1.5 rounded-lg hover:bg-gray-50 transition">
                  <EyeIcon class="w-3.5 h-3.5" /> Voir la fiche
                </Link>
                <button @click="toggleCart(asset)"
                        class="w-full flex items-center justify-center gap-2 py-2 rounded-lg text-sm font-medium transition"
                        :class="cart.hasItem(asset.id)
                          ? 'bg-green-100 text-green-700 hover:bg-green-200'
                          : 'bg-blue-600 text-white hover:bg-blue-700'">
                  <CheckIcon v-if="cart.hasItem(asset.id)" class="w-4 h-4" />
                  <ShoppingCartIcon v-else class="w-4 h-4" />
                  {{ cart.hasItem(asset.id) ? 'Dans le panier' : 'Ajouter au panier' }}
                </button>
              </div>
            </div>
          </div>

          <div v-if="assets.data.length === 0" class="col-span-full text-center text-gray-400 py-12">
            <p class="text-4xl mb-2">📦</p>
            <p class="font-medium">Aucun équipement trouvé</p>
          </div>
        </div>

        <!-- ═══ LIST VIEW ═══ -->
        <div v-else class="bg-white rounded-xl shadow-sm border overflow-hidden">

          <!-- Grouped by model -->
          <template v-if="groupByModel && groupedAssets">
            <div v-for="group in groupedAssets" :key="group.modelId ?? 'ungrouped'">
              <!-- Group header -->
              <div class="px-4 py-2 bg-blue-50 border-b border-blue-100 flex items-center gap-2">
                <span class="text-xs font-bold text-blue-700 uppercase tracking-wide">{{ group.modelName }}</span>
                <span class="text-xs bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full">{{ group.assets.length }} éq.</span>
              </div>
              <!-- Assets rows -->
              <table class="w-full text-sm">
                <tbody>
                  <tr v-for="asset in group.assets" :key="asset.id"
                      class="border-b hover:bg-gray-50 transition"
                      :class="[
                        { 'opacity-60': isAdmin && !isReservable(asset) },
                        selectedIds.includes(asset.id) ? 'bg-blue-50' : '',
                      ]">
                    <!-- Checkbox sélection -->
                    <td class="px-3 py-3 w-10 text-center">
                      <input type="checkbox" :checked="selectedIds.includes(asset.id)"
                             @change="toggleSelect(asset.id)"
                             class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer" />
                    </td>
                    <td class="px-4 py-3">
                      <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                          <img v-if="asset.image" :src="asset.image" class="w-9 h-9 rounded-lg object-cover" />
                          <span v-else class="text-lg">📦</span>
                        </div>
                        <div>
                          <p class="font-medium text-gray-900">{{ asset.name }}</p>
                          <p class="text-xs text-gray-500 font-mono">{{ asset.asset_tag }}</p>
                        </div>
                      </div>
                    </td>
                    <td class="px-4 py-3 hidden lg:table-cell">
                      <span v-if="asset.category" class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">{{ asset.category }}</span>
                      <span v-else class="text-gray-400 text-xs">—</span>
                    </td>
                    <td class="px-4 py-3"><StatusBadge :status="asset.status" type="asset" /></td>
                    <!-- Accessoires (vue groupée, admin) -->
                    <td class="px-4 py-3 hidden 2xl:table-cell">
                      <template v-if="asset.accessories && asset.accessories.length > 0">
                        <div class="flex flex-col gap-1">
                          <Link v-for="acc in asset.accessories" :key="acc.id"
                                :href="route('assets.show', acc.id)"
                                class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline whitespace-nowrap">
                            🔗 {{ acc.name }}
                          </Link>
                        </div>
                      </template>
                      <span v-else class="text-gray-300 text-xs">—</span>
                    </td>
                    <td class="px-4 py-3">
                      <div v-if="isAdmin" class="flex gap-2">
                        <Link :href="route('assets.show', asset.id)" class="text-xs text-blue-600 hover:underline">Voir</Link>
                        <Link :href="route('assets.edit', asset.id)" class="text-xs text-gray-600 hover:underline">Modifier</Link>
                      </div>
                      <button v-else @click="toggleCart(asset)"
                              class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg transition"
                              :class="cart.hasItem(asset.id)
                                ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                : 'bg-blue-100 text-blue-700 hover:bg-blue-200'">
                        <CheckIcon v-if="cart.hasItem(asset.id)" class="w-3.5 h-3.5" />
                        <ShoppingCartIcon v-else class="w-3.5 h-3.5" />
                        {{ cart.hasItem(asset.id) ? 'Dans le panier' : 'Ajouter' }}
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- Normal table (no grouping) -->
          <table v-else class="w-full text-sm">
            <thead>
              <tr class="text-xs text-gray-500 uppercase bg-gray-50 border-b">
                <!-- Checkbox "Tout sélectionner" (admin) -->
                <th v-if="isAdmin" class="px-3 py-3 w-10 text-center">
                  <input type="checkbox" :checked="allOnPageSelected"
                         @change="toggleSelectAll"
                         :title="allOnPageSelected ? 'Tout désélectionner' : 'Tout sélectionner'"
                         class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer" />
                </th>
                <th class="px-4 py-3 text-left">Équipement</th>
                <th class="px-4 py-3 text-left hidden md:table-cell">Tag</th>
                <th class="px-4 py-3 text-left hidden lg:table-cell">Catégorie</th>
                <th class="px-4 py-3 text-left hidden lg:table-cell">Emplacement</th>
                <th class="px-4 py-3 text-left">Statut</th>
                <th v-if="isAdmin" class="px-4 py-3 text-left hidden xl:table-cell">Affecté à</th>
                <th v-if="isAdmin" class="px-4 py-3 text-left hidden xl:table-cell">Retour prévu</th>
                <th v-if="isAdmin" class="px-4 py-3 text-left hidden 2xl:table-cell">Accessoires</th>
                <th v-if="isAdmin" class="px-4 py-3 text-center">Réservable</th>
                <th class="px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="asset in assets.data" :key="asset.id"
                  class="border-b hover:bg-gray-50 transition"
                  :class="[
                    { 'opacity-60': isAdmin && !isReservable(asset) },
                    isAdmin && selectedIds.includes(asset.id) ? 'bg-blue-50' : '',
                  ]">

                <!-- Checkbox sélection (admin) -->
                <td v-if="isAdmin" class="px-3 py-3 w-10 text-center">
                  <input type="checkbox" :checked="selectedIds.includes(asset.id)"
                         @change="toggleSelect(asset.id)"
                         class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer" />
                </td>

                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                      <img v-if="asset.image" :src="asset.image" class="w-9 h-9 rounded-lg object-cover" />
                      <span v-else class="text-lg">📦</span>
                    </div>
                    <div>
                      <p class="font-medium text-gray-900">{{ asset.name }}</p>
                      <p class="text-xs text-gray-500 md:hidden">{{ asset.asset_tag }}</p>
                    </div>
                  </div>
                </td>

                <td class="px-4 py-3 text-gray-500 hidden md:table-cell font-mono text-xs">{{ asset.asset_tag }}</td>

                <td class="px-4 py-3 hidden lg:table-cell">
                  <button v-if="asset.category" @click="filterByCategory(asset.category_id)"
                          class="text-gray-600 hover:text-blue-600 hover:underline text-left text-sm">
                    {{ asset.category }}
                  </button>
                  <span v-else class="text-gray-400">—</span>
                </td>

                <td class="px-4 py-3 hidden lg:table-cell">
                  <button v-if="asset.location" @click="filterByLocation(asset.location)"
                          class="text-gray-600 hover:text-blue-600 hover:underline text-left text-sm flex items-center gap-1">
                    <MapPinIcon class="w-3 h-3 text-gray-400" /> {{ asset.location }}
                  </button>
                  <span v-else class="text-gray-400">—</span>
                </td>

                <td class="px-4 py-3"><StatusBadge :status="asset.status" type="asset" /></td>

                <!-- Affecté à (admin) -->
                <td v-if="isAdmin" class="px-4 py-3 hidden xl:table-cell">
                  <span v-if="asset.checked_out_to" class="text-sm text-gray-800 font-medium">
                    👤 {{ asset.checked_out_to.name }}
                  </span>
                  <span v-else class="text-gray-300 text-xs">—</span>
                </td>

                <!-- Retour prévu -->
                <td v-if="isAdmin" class="px-4 py-3 hidden xl:table-cell">
                  <span v-if="asset.expected_return_at" class="text-sm text-orange-600 font-medium">
                    {{ asset.expected_return_at }}
                  </span>
                  <span v-else class="text-gray-300">—</span>
                </td>

                <!-- Accessoires (admin) -->
                <td v-if="isAdmin" class="px-4 py-3 hidden 2xl:table-cell">
                  <template v-if="asset.accessories && asset.accessories.length > 0">
                    <div class="flex flex-col gap-1">
                      <Link v-for="acc in asset.accessories" :key="acc.id"
                            :href="route('assets.show', acc.id)"
                            class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline whitespace-nowrap">
                        🔗 {{ acc.name }}
                      </Link>
                    </div>
                  </template>
                  <span v-else class="text-gray-300 text-xs">—</span>
                </td>

                <td v-if="isAdmin" class="px-4 py-3">
                  <div class="flex flex-col items-center gap-1">
                    <button @click="toggleReservable(asset)"
                            :title="isReservable(asset) ? 'Désactiver pour les utilisateurs' : 'Activer pour les utilisateurs'"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                            :class="isReservable(asset) ? 'bg-green-500' : 'bg-gray-300'">
                      <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200"
                            :class="isReservable(asset) ? 'translate-x-6' : 'translate-x-1'" />
                    </button>
                    <span class="text-xs" :class="isReservable(asset) ? 'text-green-600' : 'text-gray-400'">
                      {{ isReservable(asset) ? 'Visible' : 'Masqué' }}
                    </span>
                  </div>
                </td>

                <td class="px-4 py-3">
                  <div v-if="isAdmin" class="flex gap-2 flex-wrap">
                    <Link :href="route('assets.show', asset.id)"
                          class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                      <EyeIcon class="w-3.5 h-3.5" /> Voir
                    </Link>
                    <Link :href="route('assets.edit', asset.id)"
                          class="inline-flex items-center gap-1 text-xs text-gray-600 hover:underline">
                      <PencilIcon class="w-3.5 h-3.5" /> Modifier
                    </Link>
                    <button @click="duplicateAsset(asset.id)"
                            class="inline-flex items-center gap-1 text-xs text-purple-600 hover:underline">
                      📋 Dupliquer
                    </button>
                  </div>
                  <div v-else class="flex items-center gap-2">
                    <Link :href="route('assets.show', asset.id)"
                          class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-blue-600 hover:underline">
                      <EyeIcon class="w-3.5 h-3.5" /> Voir
                    </Link>
                    <button @click="toggleCart(asset)"
                          class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg transition"
                          :class="cart.hasItem(asset.id)
                            ? 'bg-green-100 text-green-700 hover:bg-green-200'
                            : 'bg-blue-100 text-blue-700 hover:bg-blue-200'">
                    <CheckIcon v-if="cart.hasItem(asset.id)" class="w-3.5 h-3.5" />
                    <ShoppingCartIcon v-else class="w-3.5 h-3.5" />
                    {{ cart.hasItem(asset.id) ? 'Dans le panier' : 'Ajouter au panier' }}
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="assets.data.length === 0">
                <td :colspan="isAdmin ? 10 : 6" class="px-4 py-12 text-center text-gray-400">
                  <p class="text-4xl mb-2">📦</p>
                  <p class="font-medium">Aucun équipement trouvé</p>
                  <p v-if="!isAdmin" class="text-sm mt-1">Aucun équipement n'est disponible à l'emprunt pour le moment.</p>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Pagination (liste) -->
          <div class="px-4 py-3 border-t bg-gray-50">
            <div class="flex items-center justify-between gap-4 text-sm text-gray-500 mb-2 flex-wrap">
              <span>{{ assets.from ?? 1 }}–{{ assets.to ?? assets.data.length }} sur {{ assets.total }} équipement(s)</span>
              <div class="flex items-center gap-3">
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                  <span>Par page :</span>
                  <div class="flex gap-1">
                    <button v-for="n in [50, 100, 200, 500]" :key="n" @click="changePerPage(n)"
                      :class="['px-2 py-0.5 rounded border text-xs transition',
                        currentPerPage === n
                          ? 'bg-blue-600 text-white border-blue-600 font-semibold'
                          : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50']">
                      {{ n }}
                    </button>
                  </div>
                </div>
                <span v-if="assets.last_page > 1" class="text-xs text-gray-400">Page {{ assets.current_page }} / {{ assets.last_page }}</span>
              </div>
            </div>
            <!-- Barre de progression -->
            <div class="w-full bg-gray-200 rounded-full h-1.5 mb-3">
              <div class="bg-blue-500 h-1.5 rounded-full transition-all duration-500"
                :style="{ width: assets.total > 0 ? ((assets.to ?? assets.data.length) / assets.total * 100) + '%' : '100%' }" />
            </div>
            <!-- Navigation par pages si plusieurs pages -->
            <div v-if="assets.last_page > 1" class="flex items-center justify-center gap-1 flex-wrap">
              <button @click="goToPage(assets.current_page - 1)" :disabled="assets.current_page <= 1"
                class="px-3 py-1.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed transition">
                ← Préc.
              </button>
              <template v-for="(p, idx) in visiblePages" :key="p">
                <span v-if="idx > 0 && p - visiblePages[idx-1] > 1" class="text-gray-300 text-xs px-1">…</span>
                <button @click="goToPage(p)"
                  :class="['px-3 py-1.5 text-xs rounded-lg border transition',
                    p === assets.current_page
                      ? 'bg-blue-600 text-white border-blue-600 font-semibold'
                      : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50']">
                  {{ p }}
                </button>
              </template>
              <button @click="goToPage(assets.current_page + 1)" :disabled="assets.current_page >= assets.last_page"
                class="px-3 py-1.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed transition">
                Suiv. →
              </button>
            </div>
            <!-- Voir plus si une seule page incomplète -->
            <div v-else-if="assets.next_page_url" class="flex justify-center">
              <button @click="loadMore" :disabled="loadingMore"
                class="flex items-center gap-2 px-5 py-2 bg-white border border-gray-300 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-blue-300 hover:text-blue-700 transition shadow-sm disabled:opacity-50">
                <span v-if="loadingMore" class="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin" />
                {{ loadingMore ? 'Chargement…' : `Voir ${Math.min(24, assets.total - (assets.to ?? 0))} de plus` }}
              </button>
            </div>
            <p v-else-if="assets.last_page <= 1" class="text-center text-xs text-gray-400">✓ Tous les équipements sont affichés</p>
          </div>
        </div>

        <!-- Pagination (grille) -->
        <div v-if="viewMode === 'grid'" class="mt-4 space-y-2">
          <div class="flex items-center justify-between text-sm text-gray-500 flex-wrap gap-2">
            <span>{{ assets.from ?? 1 }}–{{ assets.to ?? assets.data.length }} sur {{ assets.total }} équipement(s)</span>
            <div class="flex items-center gap-3">
              <div class="flex items-center gap-1.5 text-xs text-gray-500">
                <span>Par page :</span>
                <div class="flex gap-1">
                  <button v-for="n in [50, 100, 200, 500]" :key="n" @click="changePerPage(n)"
                    :class="['px-2 py-0.5 rounded border text-xs transition',
                      currentPerPage === n
                        ? 'bg-blue-600 text-white border-blue-600 font-semibold'
                        : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50']">
                    {{ n }}
                  </button>
                </div>
              </div>
              <span v-if="assets.last_page > 1" class="text-xs text-gray-400">Page {{ assets.current_page }} / {{ assets.last_page }}</span>
            </div>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-1.5">
            <div class="bg-blue-500 h-1.5 rounded-full transition-all duration-500"
              :style="{ width: assets.total > 0 ? ((assets.to ?? assets.data.length) / assets.total * 100) + '%' : '100%' }" />
          </div>
          <div v-if="assets.last_page > 1" class="flex items-center justify-center gap-1 flex-wrap pt-1">
            <button @click="goToPage(assets.current_page - 1)" :disabled="assets.current_page <= 1"
              class="px-3 py-1.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-30 transition">
              ← Préc.
            </button>
            <template v-for="(p, idx) in visiblePages" :key="p">
              <span v-if="idx > 0 && p - visiblePages[idx-1] > 1" class="text-gray-300 text-xs px-1">…</span>
              <button @click="goToPage(p)"
                :class="['px-3 py-1.5 text-xs rounded-lg border transition',
                  p === assets.current_page
                    ? 'bg-blue-600 text-white border-blue-600 font-semibold'
                    : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50']">
                {{ p }}
              </button>
            </template>
            <button @click="goToPage(assets.current_page + 1)" :disabled="assets.current_page >= assets.last_page"
              class="px-3 py-1.5 text-xs rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-30 transition">
              Suiv. →
            </button>
          </div>
          <div v-else-if="assets.next_page_url" class="flex justify-center pt-1">
            <button @click="loadMore" :disabled="loadingMore"
              class="flex items-center gap-2 px-5 py-2 bg-white border border-gray-300 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-blue-300 hover:text-blue-700 transition shadow-sm disabled:opacity-50">
              <span v-if="loadingMore" class="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin" />
              {{ loadingMore ? 'Chargement…' : `Voir ${Math.min(24, assets.total - (assets.to ?? 0))} de plus` }}
            </button>
          </div>
          <p v-else class="text-center text-xs text-gray-400 pt-1">✓ Tous les équipements sont affichés</p>
        </div>
      </div>

      <!-- ══ ONGLET CATÉGORIES ══ -->
      <div v-if="activeTab === 'categories'">
        <p class="text-sm text-gray-500 mb-4">{{ categories.length }} catégorie(s) · Cliquez pour voir les équipements</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
          <div v-for="(cat, i) in categories" :key="cat.id" class="relative group">
            <button @click="filterByCategory(cat.id)"
                    class="w-full bg-white rounded-xl border shadow-sm overflow-hidden text-left hover:shadow-md hover:border-blue-300 transition">
              <!-- Image bannière -->
              <div class="h-20 w-full overflow-hidden flex items-center justify-center"
                   :style="!cat.image_url ? `background-color: ${cat.color ? cat.color + '22' : ''}` : ''"
                   :class="!cat.image_url && !cat.color ? catColor(i) : ''">
                <img v-if="cat.image_url" :src="cat.image_url" :alt="cat.name"
                     class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300" />
                <div v-else class="flex flex-col items-center justify-center w-full h-full">
                  <TagIcon class="w-8 h-8 opacity-40" />
                </div>
              </div>
              <!-- Infos -->
              <div class="p-4">
                <p class="font-semibold text-gray-900 group-hover:text-blue-600 transition text-sm truncate">{{ cat.name }}</p>
                <p class="text-2xl font-bold mt-1" :class="catColor(i).split(' ')[1]">{{ cat.assets_count ?? 0 }}</p>
                <p class="text-xs text-gray-400">équipement(s)</p>
              </div>
            </button>
            <!-- Bouton édition (admin seulement) -->
            <button v-if="isAdmin" @click="openCatEdit(cat, $event)"
                    class="absolute top-2 right-2 w-7 h-7 rounded-lg bg-white/90 shadow border border-gray-200 flex items-center justify-center opacity-0 group-hover:opacity-100 transition hover:bg-blue-50 hover:border-blue-300 hover:text-blue-600 text-gray-500"
                    title="Modifier la catégorie">
              <PencilIcon class="w-3.5 h-3.5" />
            </button>
          </div>

          <div v-if="categories.length === 0" class="col-span-full text-center text-gray-400 py-12">
            <TagIcon class="w-12 h-12 mx-auto mb-2 opacity-30" />
            <p>Aucune catégorie</p>
          </div>
        </div>
      </div>

      <!-- ══ ONGLET LIEUX ══ -->
      <div v-if="activeTab === 'locations'">
        <p class="text-sm text-gray-500 mb-4">{{ locations.length }} lieu(x) de stockage · Cliquez pour voir les équipements</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <button v-for="loc in locations" :key="loc.location"
                  @click="filterByLocation(loc.location)"
                  class="bg-white rounded-xl border shadow-sm p-5 text-left hover:shadow-md hover:border-blue-300 transition group flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0 group-hover:bg-blue-100 transition">
              <MapPinIcon class="w-6 h-6 text-blue-500" />
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-gray-900 group-hover:text-blue-600 transition truncate">{{ loc.location }}</p>
              <p class="text-sm text-gray-500 mt-0.5">{{ loc.asset_count }} équipement(s)</p>
            </div>
            <span class="text-2xl font-bold text-gray-200 group-hover:text-blue-200 transition flex-shrink-0">
              {{ loc.asset_count }}
            </span>
          </button>

          <div v-if="locations.length === 0" class="col-span-full text-center text-gray-400 py-12">
            <MapPinIcon class="w-12 h-12 mx-auto mb-2 opacity-30" />
            <p>Aucun emplacement renseigné</p>
          </div>
        </div>
      </div>

      <!-- ══ ONGLET FABRICANTS ══ -->
      <div v-if="activeTab === 'manufacturers'">
        <p class="text-sm text-gray-500 mb-4">{{ manufacturers.length }} fabricant(s) · Cliquez pour voir les équipements</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <button v-for="mfr in manufacturers" :key="mfr.id"
                  @click="filterByManufacturer(mfr.id)"
                  class="bg-white rounded-xl border shadow-sm p-5 text-left hover:shadow-md hover:border-blue-300 transition group flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0 text-xl font-bold text-gray-500 group-hover:bg-blue-50 group-hover:text-blue-600 transition">
              {{ mfr.name.charAt(0).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-gray-900 group-hover:text-blue-600 transition truncate">{{ mfr.name }}</p>
              <p class="text-sm text-gray-500 mt-0.5">{{ mfr.assets_count }} équipement(s)</p>
            </div>
            <span class="text-2xl font-bold text-gray-200 group-hover:text-blue-200 transition flex-shrink-0">
              {{ mfr.assets_count }}
            </span>
          </button>

          <div v-if="manufacturers.length === 0" class="col-span-full text-center text-gray-400 py-12">
            <BuildingStorefrontIcon class="w-12 h-12 mx-auto mb-2 opacity-30" />
            <p>Aucun fabricant renseigné</p>
          </div>
        </div>
      </div>

    </div>

    <!-- ═══ Modal : suppression en masse ═══ -->
    <div v-if="showBulkDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <h3 class="font-bold text-lg mb-2">🗑 Supprimer {{ selectedIds.length }} équipement(s)</h3>
        <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-800">
          ⚠️ Cette action est <strong>irréversible</strong>. Les équipements sélectionnés seront
          définitivement supprimés de l'inventaire.
        </div>
        <div class="flex gap-3">
          <button @click="showBulkDeleteModal = false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">
            Annuler
          </button>
          <button @click="executeBulkDelete" :disabled="bulkProcessing"
            class="flex-1 bg-red-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
            {{ bulkProcessing ? 'Suppression…' : `🗑 Supprimer ${selectedIds.length} équipement(s)` }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : modification en masse ═══ -->
    <div v-if="showBulkEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-2">
          <h3 class="font-bold text-lg">✏️ Modifier {{ selectedIds.length }} équipement(s)</h3>
          <button @click="showBulkEditModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <p class="text-sm text-gray-500 mb-5">
          Cochez les champs à modifier. Les champs non cochés resteront <strong>inchangés</strong>.
        </p>

        <div class="space-y-3">
          <!-- Statut -->
          <div class="flex items-start gap-3 p-3 border rounded-lg transition"
               :class="bulkEditForm.updateStatus ? 'border-blue-300 bg-blue-50' : 'border-gray-200'">
            <input type="checkbox" v-model="bulkEditForm.updateStatus" id="bulk-status"
                   class="mt-1 w-4 h-4 rounded text-blue-600 border-gray-300 focus:ring-blue-500 cursor-pointer flex-shrink-0" />
            <div class="flex-1">
              <label for="bulk-status" class="block text-sm font-medium text-gray-700 cursor-pointer mb-1">Statut</label>
              <select v-model="bulkEditForm.status" :disabled="!bulkEditForm.updateStatus"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none disabled:opacity-40 disabled:bg-gray-50">
                <option value="">— Choisir un statut —</option>
                <option v-for="s in assetStatuses" :key="s.key" :value="s.key">{{ s.label }}</option>
              </select>
            </div>
          </div>

          <!-- Catégorie -->
          <div class="flex items-start gap-3 p-3 border rounded-lg transition"
               :class="bulkEditForm.updateCategory ? 'border-blue-300 bg-blue-50' : 'border-gray-200'">
            <input type="checkbox" v-model="bulkEditForm.updateCategory" id="bulk-category"
                   class="mt-1 w-4 h-4 rounded text-blue-600 border-gray-300 focus:ring-blue-500 cursor-pointer flex-shrink-0" />
            <div class="flex-1">
              <label for="bulk-category" class="block text-sm font-medium text-gray-700 cursor-pointer mb-1">Catégorie</label>
              <select v-model="bulkEditForm.category_id" :disabled="!bulkEditForm.updateCategory"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none disabled:opacity-40 disabled:bg-gray-50">
                <option value="">— Aucune catégorie —</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
          </div>

          <!-- Emplacement -->
          <div class="flex items-start gap-3 p-3 border rounded-lg transition"
               :class="bulkEditForm.updateLocation ? 'border-blue-300 bg-blue-50' : 'border-gray-200'">
            <input type="checkbox" v-model="bulkEditForm.updateLocation" id="bulk-location"
                   class="mt-1 w-4 h-4 rounded text-blue-600 border-gray-300 focus:ring-blue-500 cursor-pointer flex-shrink-0" />
            <div class="flex-1">
              <label for="bulk-location" class="block text-sm font-medium text-gray-700 cursor-pointer mb-1">Emplacement</label>
              <input v-model="bulkEditForm.location" :disabled="!bulkEditForm.updateLocation"
                     type="text" placeholder="Salle, armoire, local…"
                     list="bulk-locations-list"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none disabled:opacity-40 disabled:bg-gray-50" />
              <datalist id="bulk-locations-list">
                <option v-for="loc in locations" :key="loc.location" :value="loc.location">
                  {{ loc.location }} ({{ loc.asset_count }})
                </option>
              </datalist>
            </div>
          </div>

          <!-- Visibilité (réservable) -->
          <div class="flex items-start gap-3 p-3 border rounded-lg transition"
               :class="bulkEditForm.updateReservable ? 'border-blue-300 bg-blue-50' : 'border-gray-200'">
            <input type="checkbox" v-model="bulkEditForm.updateReservable" id="bulk-reservable"
                   class="mt-1 w-4 h-4 rounded text-blue-600 border-gray-300 focus:ring-blue-500 cursor-pointer flex-shrink-0" />
            <div class="flex-1">
              <label for="bulk-reservable" class="block text-sm font-medium text-gray-700 cursor-pointer mb-1">
                Visibilité (réservable par les utilisateurs)
              </label>
              <div class="flex gap-5">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="bulkEditForm.is_reservable" value="1"
                         :disabled="!bulkEditForm.updateReservable"
                         class="text-blue-600 disabled:opacity-40" />
                  <span class="text-sm text-green-700 font-medium">✓ Réservable (visible)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="bulkEditForm.is_reservable" value="0"
                         :disabled="!bulkEditForm.updateReservable"
                         class="text-blue-600 disabled:opacity-40" />
                  <span class="text-sm text-gray-600 font-medium">✕ Masqué</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Modèle -->
          <div class="flex items-start gap-3 p-3 border rounded-lg transition"
               :class="bulkEditForm.updateModel ? 'border-blue-300 bg-blue-50' : 'border-gray-200'">
            <input type="checkbox" v-model="bulkEditForm.updateModel" id="bulk-model"
                   class="mt-1 w-4 h-4 rounded text-blue-600 border-gray-300 focus:ring-blue-500 cursor-pointer flex-shrink-0" />
            <div class="flex-1">
              <label for="bulk-model" class="block text-sm font-medium text-gray-700 cursor-pointer mb-1">Modèle</label>
              <select v-model="bulkEditForm.equipment_model_id" :disabled="!bulkEditForm.updateModel"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none disabled:opacity-40 disabled:bg-gray-50">
                <option value="">— Aucun modèle —</option>
                <option v-for="m in models" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
            </div>
          </div>
        </div>

        <div class="flex gap-3 mt-6">
          <button @click="showBulkEditModal = false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">
            Annuler
          </button>
          <button @click="executeBulkUpdate"
            :disabled="bulkProcessing || (!bulkEditForm.updateStatus && !bulkEditForm.updateCategory && !bulkEditForm.updateLocation && !bulkEditForm.updateReservable && !bulkEditForm.updateModel)"
            class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ bulkProcessing ? 'Mise à jour…' : `✏️ Appliquer à ${selectedIds.length} équipement(s)` }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : Réserver les équipements sélectionnés ═══ -->
    <div v-if="showBulkReserveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">📅 Créer une réservation</h3>
          <button @click="showBulkReserveModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <!-- Équipements sélectionnés -->
        <div class="mb-4 bg-blue-50 border border-blue-100 rounded-lg p-3">
          <p class="text-xs font-semibold text-blue-600 uppercase tracking-wide mb-2">
            {{ selectedIds.length }} équipement(s) inclus dans la réservation
          </p>
          <ul class="space-y-1 max-h-32 overflow-y-auto">
            <li v-for="asset in assets.data.filter(a => selectedIds.includes(a.id))" :key="asset.id"
                class="flex items-center gap-2 text-xs text-gray-700">
              <span class="w-1.5 h-1.5 rounded-full bg-blue-400 flex-shrink-0" />
              <span class="font-mono text-gray-400">[{{ asset.asset_tag }}]</span>
              {{ asset.name }}
            </li>
          </ul>
          <p v-if="selectedIds.length > assets.data.filter(a => selectedIds.includes(a.id)).length"
             class="text-xs text-orange-600 mt-1">
            ⚠️ Certains équipements sélectionnés ne sont pas visibles sur cette page.
          </p>
        </div>

        <div class="space-y-4">
          <!-- Titre -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Titre de la réservation <span class="text-red-500">*</span>
            </label>
            <input v-model="bulkReserveForm.title" type="text"
                   placeholder="Ex : Tournage club cinéma, Conférence salle B…"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            <p v-if="bulkReserveErrors.title" class="text-red-500 text-xs mt-1">{{ bulkReserveErrors.title }}</p>
          </div>

          <!-- Dates -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Début <span class="text-red-500">*</span>
              </label>
              <input v-model="bulkReserveForm.start_date" type="datetime-local"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              <p v-if="bulkReserveErrors.start_date" class="text-red-500 text-xs mt-1">{{ bulkReserveErrors.start_date }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Fin <span class="text-red-500">*</span>
              </label>
              <input v-model="bulkReserveForm.end_date" type="datetime-local"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              <p v-if="bulkReserveErrors.end_date" class="text-red-500 text-xs mt-1">{{ bulkReserveErrors.end_date }}</p>
            </div>
          </div>

          <!-- Notes -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optionnel)</label>
            <textarea v-model="bulkReserveForm.notes" rows="2"
                      placeholder="Informations complémentaires…"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
          </div>

          <!-- Erreurs de conflit de disponibilité -->
          <div v-if="Object.keys(bulkReserveErrors).some(k => k.startsWith('asset_ids'))"
               class="bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-xs text-red-700">
            <p class="font-semibold mb-1">⚠️ Conflits de disponibilité :</p>
            <ul class="space-y-0.5 list-disc list-inside">
              <li v-for="(msg, key) in bulkReserveErrors" :key="key"
                  v-if="String(key).startsWith('asset_ids')">
                {{ msg }}
              </li>
            </ul>
          </div>
        </div>

        <div class="flex gap-3 mt-5">
          <button @click="showBulkReserveModal = false"
                  class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">
            Annuler
          </button>
          <button @click="executeBulkReserve"
                  :disabled="bulkReserveProcessing || !bulkReserveForm.title || !bulkReserveForm.start_date || !bulkReserveForm.end_date"
                  class="flex-1 bg-green-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50">
            {{ bulkReserveProcessing ? 'Création en cours…' : `📅 Créer la réservation (${selectedIds.length})` }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal édition catégorie (admin) ═══ -->
    <div v-if="showCatEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showCatEditModal = false">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">Modifier « {{ catEditTarget?.name }} »</h3>
          <button @click="showCatEditModal = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 transition">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <div class="space-y-4">
          <!-- Upload image -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Photo</label>
            <div class="flex items-center gap-4">
              <!-- Prévisualisation -->
              <div class="w-20 h-20 rounded-xl border-2 border-dashed border-gray-200 flex items-center justify-center overflow-hidden bg-gray-50 flex-shrink-0"
                   :style="catEditPreview ? '' : (catEditForm.color ? `background-color: ${catEditForm.color}22` : '')">
                <img v-if="catEditPreview" :src="catEditPreview" class="w-full h-full object-cover rounded-lg" alt="Aperçu" />
                <PhotoIcon v-else class="w-8 h-8 text-gray-300" />
              </div>
              <!-- Actions -->
              <div class="flex flex-col gap-2">
                <label class="cursor-pointer inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-700 font-medium hover:underline">
                  <PhotoIcon class="w-4 h-4" />
                  {{ catEditPreview ? 'Changer la photo' : 'Ajouter une photo' }}
                  <input type="file" accept="image/*" class="hidden" @change="onCatEditImage" />
                </label>
                <button v-if="catEditPreview" type="button" @click="catEditPreview = null; catEditForm.image = null"
                        class="inline-flex items-center gap-1.5 text-xs text-red-500 hover:text-red-700 hover:underline text-left">
                  <XMarkIcon class="w-3.5 h-3.5" /> Supprimer la photo
                </button>
              </div>
            </div>
          </div>

          <!-- Nom -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
            <input v-model="catEditForm.name" type="text"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            <p v-if="catEditForm.errors.name" class="text-red-500 text-xs mt-1">{{ catEditForm.errors.name }}</p>
          </div>

          <!-- Description -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="catEditForm.description" rows="2"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
          </div>

          <!-- Couleur -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
            <div class="flex items-center gap-3">
              <input v-model="catEditForm.color" type="color"
                class="w-10 h-10 rounded-lg border border-gray-300 cursor-pointer p-1" />
              <span class="text-sm text-gray-500">{{ catEditForm.color }}</span>
            </div>
          </div>
        </div>

        <div class="flex gap-3 mt-5">
          <button @click="showCatEditModal = false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitCatEdit" :disabled="catEditForm.processing || !catEditForm.name.trim()"
            class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ catEditForm.processing ? 'Sauvegarde…' : 'Enregistrer' }}
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>
