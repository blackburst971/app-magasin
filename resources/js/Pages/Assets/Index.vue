<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { useCartStore } from '@/stores/cart'
import { useSelectionStore } from '@/stores/selection'
import { Link, router, usePage, useForm } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import axios from 'axios'
import {
  MagnifyingGlassIcon, PlusIcon,
  ShoppingCartIcon, CheckIcon, EyeIcon, PencilIcon, TrashIcon,
  MapPinIcon, TagIcon, BuildingStorefrontIcon,
  ListBulletIcon, Squares2X2Icon, PhotoIcon, XMarkIcon,
  Cog6ToothIcon, EyeSlashIcon, Bars3Icon, WrenchScrewdriverIcon,
  DocumentArrowDownIcon, TableCellsIcon,
} from '@heroicons/vue/24/outline'

const page      = usePage()
const cart      = useCartStore()
const selection = useSelectionStore()
const isAdmin   = computed(() => page.props.auth.user?.is_admin)

// Statuts dynamiques triés par sort_order
// Les admins voient tous les statuts, les utilisateurs seulement ceux marqués user_visible
const assetStatuses = computed(() =>
  Object.values(page.props.assetStatuses ?? {})
    .filter(s => isAdmin.value || s.user_visible)
    .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
)

const props = defineProps({
  assets:                 Object,
  categories:             Array,
  locations:              Array,
  places:                 Array,
  manufacturers:          Array,
  models:                 Array,
  filters:                Object,
  reservableFilterActive: Boolean,
})

const search           = ref(props.filters?.search       ?? '')
const selectedCategory = ref(props.filters?.category     ?? '')
const selectedStatus   = ref(props.filters?.status       ?? '')
const selectedLocation = ref(props.filters?.location     ?? '')
const selectedPlace    = ref(props.filters?.place        ?? '')
const selectedMfr      = ref(props.filters?.manufacturer ?? '')
const selectedModel    = ref(props.filters?.model        ?? '')

// ── Persistance de la recherche via sessionStorage ──────────────────────────
const STORAGE_KEY = 'assets_filters'

// Restaurer les filtres sauvegardés si aucun filtre serveur n'est actif
onMounted(() => {
  const hasServerFilters =
    props.filters?.search || props.filters?.category ||
    props.filters?.status || props.filters?.location ||
    props.filters?.place || props.filters?.manufacturer || props.filters?.model

  if (!hasServerFilters) {
    try {
      const saved = sessionStorage.getItem(STORAGE_KEY)
      if (saved) {
        const f = JSON.parse(saved)
        const hasData = f.search || f.category || f.status || f.location || f.place || f.manufacturer
        if (hasData) {
          search.value           = f.search       || ''
          selectedCategory.value = f.category     || ''
          selectedStatus.value   = f.status       || ''
          selectedLocation.value = f.location     || ''
          selectedPlace.value    = f.place        || ''
          selectedMfr.value      = f.manufacturer || ''
          nextTick(() => applyFilters())
        }
      }
    } catch (_) {}
  }
})

// Sauvegarder les filtres à chaque changement (ne PAS vider la sélection)
watch([search, selectedCategory, selectedStatus, selectedLocation, selectedPlace, selectedMfr], () => {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
      search:       search.value,
      category:     selectedCategory.value,
      status:       selectedStatus.value,
      location:     selectedLocation.value,
      place:        selectedPlace.value,
      manufacturer: selectedMfr.value,
    }))
  } catch (_) {}
})
// ─────────────────────────────────────────────────────────────────────────────

// Onglet actif — depuis le paramètre URL tab
const activeTab = ref(props.filters?.tab ?? 'list')

// View mode: 'list' ou 'grid' — persisté dans localStorage
const _defaultView = isAdmin.value ? 'list' : 'grid'
const viewMode = ref(localStorage.getItem('assets_view') ?? _defaultView)
watch(viewMode, v => localStorage.setItem('assets_view', v))

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
    place:        selectedPlace.value    || undefined,
    manufacturer: selectedMfr.value      || undefined,
    model:        selectedModel.value    || undefined,
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

// Réinitialiser le per_page quand les filtres changent (ne PAS vider la sélection)
function resetAndFilter(extra = {}) {
  currentPerPage.value = 24
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
  selectedLocation.value = ''; selectedPlace.value = ''; selectedMfr.value = ''; selectedModel.value = ''
  resetAndFilter()
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

// Alerte panier pour équipement sorti
const checkedOutAlert = ref(null) // asset à confirmer

function toggleCart(asset) {
  if (cart.hasItem(asset.id)) {
    cart.removeItem(asset.id)
    return
  }
  // Si l'équipement est sorti, afficher une alerte avant d'ajouter
  if (asset.status === 'checked_out') {
    checkedOutAlert.value = asset
    return
  }
  cart.addItem(asset)
}

function confirmAddCheckedOut() {
  if (checkedOutAlert.value) {
    cart.addItem(checkedOutAlert.value)
    checkedOutAlert.value = null
  }
}

const catColors = [
  'bg-blue-100 text-blue-600', 'bg-purple-100 text-purple-600',
  'bg-green-100 text-green-600', 'bg-yellow-100 text-yellow-600',
  'bg-red-100 text-red-600', 'bg-indigo-100 text-indigo-600',
  'bg-pink-100 text-pink-600', 'bg-orange-100 text-orange-600',
]
const catColor = (i) => catColors[i % catColors.length]

// ─── Gestion des catégories (admin) ──────────────────────────────────────────

// Édition
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

// Création
const showCatCreateModal = ref(false)
const catCreateForm      = useForm({ name: '', description: '', color: '#3B82F6', image: null })
const catCreatePreview   = ref(null)

function openCatCreate(e) {
  e.stopPropagation()
  catCreateForm.reset()
  catCreateForm.color    = '#3B82F6'
  catCreatePreview.value = null
  showCatCreateModal.value = true
}

function onCatCreateImage(e) {
  const file = e.target.files[0]
  if (!file) return
  catCreateForm.image = file
  catCreatePreview.value = URL.createObjectURL(file)
}

function submitCatCreate() {
  catCreateForm.post(route('categories.store'), {
    forceFormData: true,
    onSuccess: () => { showCatCreateModal.value = false },
  })
}

// Suppression
const showCatDeleteModal = ref(false)
const catDeleteTarget    = ref(null)
const catDeleteReassign  = ref('')
const catDeleteForm      = useForm({})

function openCatDelete(cat, e) {
  e.stopPropagation()
  catDeleteTarget.value   = cat
  catDeleteReassign.value = ''
  showCatDeleteModal.value = true
}

function submitCatDelete() {
  catDeleteForm.transform(() => ({
    reassign_to: catDeleteReassign.value || null,
  })).delete(route('categories.destroy', catDeleteTarget.value.id), {
    onSuccess: () => { showCatDeleteModal.value = false; catDeleteTarget.value = null },
  })
}

// ─── Configuration des colonnes (admin) ──────────────────────────────────────

const ALL_ADMIN_COLUMNS = [
  { key: 'name',           label: 'Équipement'   },
  { key: 'asset_tag',      label: 'Tag'          },
  { key: 'category',       label: 'Catégorie'    },
  { key: 'model',          label: 'Modèle'       },
  { key: 'location',       label: 'Emplacement'  },
  { key: 'manufacturer',   label: 'Fabricant'    },
  { key: 'status',         label: 'Statut'       },
  { key: 'condition',      label: 'État'         },
  { key: 'checked_out_to', label: 'Affecté à'    },
  { key: 'return_date',    label: 'Retour prévu' },
  { key: 'accessories',    label: 'Accessoires'  },
  { key: 'label',          label: 'Étiquette'    },
  { key: 'reservable',     label: 'Réservable'   },
]
const COL_STORAGE_KEY = 'admin_assets_cols_v2'

function getDefaultCols() {
  return ALL_ADMIN_COLUMNS.map(c => ({ key: c.key, visible: true }))
}
function loadCols() {
  try {
    const raw = localStorage.getItem(COL_STORAGE_KEY)
    if (!raw) return getDefaultCols()
    const parsed = JSON.parse(raw)
    const allKeys = ALL_ADMIN_COLUMNS.map(c => c.key)
    const valid = parsed.filter(c => allKeys.includes(c.key))
    allKeys.forEach(k => { if (!valid.find(c => c.key === k)) valid.push({ key: k, visible: true }) })
    return valid
  } catch { return getDefaultCols() }
}

const showColPanel  = ref(false)
const colDragKey    = ref(null)
const colDragTarget = ref(null)
const columnsConfig = ref(loadCols())

watch(columnsConfig, val => localStorage.setItem(COL_STORAGE_KEY, JSON.stringify(val)), { deep: true })

const visibleAdminCols = computed(() => columnsConfig.value.filter(c => c.visible))

function colLabel(key) { return ALL_ADMIN_COLUMNS.find(c => c.key === key)?.label ?? key }
function toggleCol(key) { const c = columnsConfig.value.find(c => c.key === key); if (c) c.visible = !c.visible }

function onColDragStart(e, key) {
  colDragKey.value = key
  e.dataTransfer.effectAllowed = 'move'
}
function onColDragEnter(key) { colDragTarget.value = key }
function onColDragOver(e)    { e.preventDefault() }
function onColDrop(targetKey) {
  if (!colDragKey.value || colDragKey.value === targetKey) { colDragKey.value = null; colDragTarget.value = null; return }
  const arr  = [...columnsConfig.value]
  const from = arr.findIndex(c => c.key === colDragKey.value)
  const to   = arr.findIndex(c => c.key === targetKey)
  const [item] = arr.splice(from, 1)
  arr.splice(to, 0, item)
  columnsConfig.value = arr
  colDragKey.value = null; colDragTarget.value = null
}
function onColDragEnd() { colDragKey.value = null; colDragTarget.value = null }
function resetCols() { columnsConfig.value = getDefaultCols() }

// ─────────────────────────────────────────────────────────────────────────────

const activeFiltersCount = computed(() =>
  [selectedCategory.value, selectedStatus.value, selectedLocation.value, selectedPlace.value, selectedMfr.value, search.value, selectedModel.value]
    .filter(Boolean).length
)

const activeModelName = computed(() => {
  if (!selectedModel.value) return null
  return props.models?.find(m => String(m.id) === String(selectedModel.value))?.name ?? null
})

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
// Sélection multiple (tous utilisateurs)
// ═══════════════════════════════════════
const showBulkDeleteModal = ref(false)
const showBulkEditModal   = ref(false)
const bulkProcessing      = ref(false)
const exportingPdf        = ref(false)
const exportingExcel      = ref(false)

const bulkEditForm = ref({
  updateStatus:       false, status:             '',
  updateCategory:     false, category_id:        '',
  updateLocation:     false, location:           '',
  updateReservable:   false, is_reservable:      '',
  updateModel:        false, equipment_model_id: '',
  updateManufacturer: false, manufacturer_id:    '',
  updatePlace:        false, place_id:           '',
  // Champs affectation formelle de lieu
  assignReturn:       false, return_expected_at: '',
  assignNotes:        '',
})

// Lieu sélectionné dans le bulk edit (pour détecter storage vs affectation)
const bulkSelectedPlace = computed(() =>
  bulkEditForm.value.place_id
    ? props.places?.find(p => p.id === bulkEditForm.value.place_id || String(p.id) === String(bulkEditForm.value.place_id))
    : null
)
const bulkPlaceIsStorage = computed(() => bulkSelectedPlace.value?.is_storage ?? false)
const bulkPlaceNeedsFormalAssign = computed(() =>
  bulkEditForm.value.updatePlace &&
  bulkEditForm.value.place_id &&
  !bulkPlaceIsStorage.value
)

const allOnPageSelected = computed(() => {
  if (!props.assets?.data) return false
  const pageIds = props.assets.data.map(a => a.id)
  return pageIds.length > 0 && pageIds.every(id => selection.isSelected(id))
})

function toggleSelectAll() {
  if (allOnPageSelected.value) {
    selection.deselectAll(props.assets.data)
  } else {
    selection.selectAll(props.assets.data)
  }
}

function toggleSelect(asset) {
  selection.toggleAsset(asset)
}

function openBulkEdit() {
  bulkEditForm.value = {
    updateStatus:       false, status:             '',
    updateCategory:     false, category_id:        '',
    updateLocation:     false, location:           '',
    updateReservable:   false, is_reservable:      '',
    updateModel:        false, equipment_model_id: '',
    updateManufacturer: false, manufacturer_id:    '',
    updatePlace:        false, place_id:           '',
    assignReturn:       false, return_expected_at: '',
    assignNotes:        '',
  }
  showBulkEditModal.value = true
}

function executeBulkDelete() {
  bulkProcessing.value = true
  router.post(route('assets.bulk-delete'), { ids: [...selection.selectedIds] }, {
    onSuccess: () => { showBulkDeleteModal.value = false; selection.clearSelection() },
    onFinish:  () => { bulkProcessing.value = false },
  })
}

function executeBulkUpdate() {
  // ── Cas affectation formelle à un lieu non-stockage ──────────────────────
  if (bulkPlaceNeedsFormalAssign.value) {
    // Si d'autres champs sont également cochés, les appliquer d'abord via bulk-update
    const hasOtherUpdates =
      bulkEditForm.value.updateStatus || bulkEditForm.value.updateCategory ||
      bulkEditForm.value.updateLocation || bulkEditForm.value.updateReservable ||
      bulkEditForm.value.updateModel || bulkEditForm.value.updateManufacturer

    const doAssign = () => {
      const payload = {
        ids:      [...selection.selectedIds],
        place_id: bulkEditForm.value.place_id,
        notes:    bulkEditForm.value.assignNotes || null,
        return_expected_at: bulkEditForm.value.assignReturn
          ? (bulkEditForm.value.return_expected_at || null)
          : null,
      }
      router.post(route('assets.bulk-assign-place'), payload, {
        onSuccess: () => { showBulkEditModal.value = false; selection.clearSelection() },
        onFinish:  () => { bulkProcessing.value = false },
      })
    }

    if (hasOtherUpdates) {
      const otherPayload = { ids: [...selection.selectedIds] }
      if (bulkEditForm.value.updateStatus)       otherPayload.status             = bulkEditForm.value.status
      if (bulkEditForm.value.updateCategory)     otherPayload.category_id        = bulkEditForm.value.category_id || null
      if (bulkEditForm.value.updateLocation)     otherPayload.location           = bulkEditForm.value.location
      if (bulkEditForm.value.updateReservable)   otherPayload.is_reservable      = bulkEditForm.value.is_reservable
      if (bulkEditForm.value.updateModel)        otherPayload.equipment_model_id = bulkEditForm.value.equipment_model_id || null
      if (bulkEditForm.value.updateManufacturer) otherPayload.manufacturer_id    = bulkEditForm.value.manufacturer_id || null

      bulkProcessing.value = true
      router.post(route('assets.bulk-update'), otherPayload, {
        onSuccess: doAssign,
        onError:   () => { bulkProcessing.value = false },
      })
    } else {
      bulkProcessing.value = true
      doAssign()
    }
    return
  }

  // ── Cas standard ─────────────────────────────────────────────────────────
  const payload = { ids: [...selection.selectedIds] }
  if (bulkEditForm.value.updateStatus)       payload.status             = bulkEditForm.value.status
  if (bulkEditForm.value.updateCategory)     payload.category_id        = bulkEditForm.value.category_id || null
  if (bulkEditForm.value.updateLocation)     payload.location           = bulkEditForm.value.location
  if (bulkEditForm.value.updateReservable)   payload.is_reservable      = bulkEditForm.value.is_reservable
  if (bulkEditForm.value.updateModel)        payload.equipment_model_id = bulkEditForm.value.equipment_model_id || null
  if (bulkEditForm.value.updateManufacturer) payload.manufacturer_id    = bulkEditForm.value.manufacturer_id || null
  if (bulkEditForm.value.updatePlace) {
    payload.place_id = bulkEditForm.value.place_id || null
  }
  bulkProcessing.value = true
  router.post(route('assets.bulk-update'), payload, {
    onSuccess: () => { showBulkEditModal.value = false; selection.clearSelection() },
    onFinish:  () => { bulkProcessing.value = false },
  })
}

function duplicateAsset(assetId) {
  if (!confirm('Dupliquer cet équipement ? Un nouvel asset identique sera créé avec un tag unique.')) return
  router.post(route('assets.duplicate', assetId))
}

// ─── Étiquettes en masse ─────────────────────────────────────────────────────
const showBulkLabelModal  = ref(false)
const bulkLabelProcessing = ref(false)
const bulkLabelForm       = ref({
  updateLabelToCreate: false, label_to_create: true,
  updateLabelToApply:  false, label_to_apply:  true,
})

function openBulkLabel() {
  bulkLabelForm.value = {
    updateLabelToCreate: false, label_to_create: true,
    updateLabelToApply:  false, label_to_apply:  true,
  }
  showBulkLabelModal.value = true
}

function executeBulkLabel() {
  const payload = { ids: [...selection.selectedIds] }
  if (bulkLabelForm.value.updateLabelToCreate) payload.label_to_create = bulkLabelForm.value.label_to_create
  if (bulkLabelForm.value.updateLabelToApply)  payload.label_to_apply  = bulkLabelForm.value.label_to_apply
  bulkLabelProcessing.value = true
  router.post(route('assets.bulk-label'), payload, {
    onSuccess: () => { showBulkLabelModal.value = false; selection.clearSelection() },
    onFinish:  () => { bulkLabelProcessing.value = false },
  })
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
    asset_ids: [...selection.selectedIds],
  }, {
    onSuccess: () => { showBulkReserveModal.value = false; selection.clearSelection() },
    onError:   (errors) => { bulkReserveErrors.value = errors },
    onFinish:  () => { bulkReserveProcessing.value = false },
  })
}

// ─── Export de la sélection ──────────────────────────────────────────────────
const showExportModal = ref(false)
const exportFormat = ref('pdf')
const exportGroupByModel = ref(false)
const exportTitle = ref('Liste des Équipements')

// Colonnes disponibles selon le rôle
const availableExportColumns = computed(() => {
  if (isAdmin.value) {
    return [
      { key: 'name', label: 'Nom' },
      { key: 'asset_tag', label: 'Tag' },
      { key: 'serial', label: 'N° Série' },
      { key: 'category', label: 'Catégorie' },
      { key: 'model', label: 'Modèle' },
      { key: 'manufacturer', label: 'Fabricant' },
      { key: 'location', label: 'Emplacement' },
      { key: 'status', label: 'Statut' },
      { key: 'checked_out_to', label: 'Affecté à' },
    ]
  } else {
    // Non-admin : colonnes fixes (pas de sélection)
    return [
      { key: 'name', label: 'Nom' },
      { key: 'category', label: 'Catégorie' },
      { key: 'model', label: 'Modèle' },
      { key: 'manufacturer', label: 'Fabricant' },
    ]
  }
})

const exportColumns = computed(() => {
  // Pour les non-admin, toutes les colonnes disponibles sont sélectionnées (pas de choix)
  if (!isAdmin.value) {
    return availableExportColumns.value.map(col => col.key)
  }
  // Pour les admin, colonnes configurables
  return []
})

// Colonnes sélectionnées pour admin (checkboxes)
const exportColumnsAdmin = ref({
  name: true,
  asset_tag: true,
  serial: true,
  category: true,
  model: true,
  manufacturer: true,
  location: true,
  status: true,
  checked_out_to: false,
})

function openExportModal(format) {
  if (selection.isEmpty) {
    alert('Veuillez sélectionner au moins un équipement')
    return
  }
  exportFormat.value = format
  showExportModal.value = true
}

function executeExport() {
  const ids = [...selection.selectedIds]

  // Colonnes sélectionnées selon le rôle
  const selectedColumns = isAdmin.value
    ? Object.keys(exportColumnsAdmin.value).filter(key => exportColumnsAdmin.value[key])
    : exportColumns.value

  if (exportFormat.value === 'pdf') {
    // Rafraîchir le token CSRF avant l'export (évite l'erreur 419)
    axios.get('/csrf-token').then(response => {
      // Export PDF via POST (avec option de regroupement)
      const form = document.createElement('form')
      form.method = 'POST'
      form.action = route('assets.export-pdf')
      form.target = '_blank'

      // CSRF Token (rafraîchi)
      const csrfInput = document.createElement('input')
      csrfInput.type = 'hidden'
      csrfInput.name = '_token'
      csrfInput.value = response.data.token
      form.appendChild(csrfInput)

    // IDs
    ids.forEach(id => {
      const input = document.createElement('input')
      input.type = 'hidden'
      input.name = 'ids[]'
      input.value = id
      form.appendChild(input)
    })

    // Columns
    selectedColumns.forEach(col => {
      const input = document.createElement('input')
      input.type = 'hidden'
      input.name = 'columns[]'
      input.value = col
      form.appendChild(input)
    })

    // Title
    if (exportTitle.value && exportTitle.value.trim()) {
      const titleInput = document.createElement('input')
      titleInput.type = 'hidden'
      titleInput.name = 'title'
      titleInput.value = exportTitle.value.trim()
      form.appendChild(titleInput)
    }

    // Group by model option
    if (exportGroupByModel.value) {
      const groupInput = document.createElement('input')
      groupInput.type = 'hidden'
      groupInput.name = 'groupByModel'
      groupInput.value = '1'
      form.appendChild(groupInput)
    }

      document.body.appendChild(form)
      form.submit()
      document.body.removeChild(form)

      showExportModal.value = false
    }).catch(error => {
      console.error('Erreur lors du rafraîchissement du token CSRF:', error)
      alert('Erreur lors de l\'export. Veuillez réessayer.')
    })
  } else {
    // Export CSV/Excel via GET (ancien système)
    const url = route('assets.export-selection', {
      format: exportFormat.value,
      ids: ids.join(','),
      columns: selectedColumns.join(',')
    })
    window.open(url, '_blank')
    showExportModal.value = false
  }
}

// ─── Mise à jour rapide inline (admin only) ──────────────────────────────────
function quickUpdateField(assetId, field, value) {
  axios.patch(route('assets.quick-update', assetId), { field, value })
    .then(response => {
      // Mettre à jour l'asset dans la liste
      const asset = props.assets.data.find(a => a.id === assetId)
      if (asset && response.data.asset) {
        Object.assign(asset, response.data.asset)
      }
    })
    .catch(error => {
      console.error('Erreur lors de la mise à jour:', error)
      alert(error.response?.data?.error || 'Erreur lors de la mise à jour')
    })
}

// Palette de couleurs pour les statuts (assurer que Tailwind ne les purge pas)
const statusColorMap = {
  green:  { bg: 'bg-green-100',  text: 'text-green-800',  border: 'border-green-300'  },
  blue:   { bg: 'bg-blue-100',   text: 'text-blue-800',   border: 'border-blue-300'   },
  purple: { bg: 'bg-purple-100', text: 'text-purple-800', border: 'border-purple-300' },
  yellow: { bg: 'bg-yellow-100', text: 'text-yellow-800', border: 'border-yellow-300' },
  orange: { bg: 'bg-orange-100', text: 'text-orange-800', border: 'border-orange-300' },
  red:    { bg: 'bg-red-100',    text: 'text-red-800',    border: 'border-red-300'    },
  gray:   { bg: 'bg-gray-100',   text: 'text-gray-800',   border: 'border-gray-300'   },
  indigo: { bg: 'bg-indigo-100', text: 'text-indigo-800', border: 'border-indigo-300' },
  pink:   { bg: 'bg-pink-100',   text: 'text-pink-800',   border: 'border-pink-300'   },
  teal:   { bg: 'bg-teal-100',   text: 'text-teal-800',   border: 'border-teal-300'   },
}

// Obtenir les classes de couleur pour un statut
function getStatusClasses(statusKey) {
  const assetStatus = Object.values(page.props.assetStatuses || {}).find(s => s.key === statusKey)
  if (!assetStatus) return 'bg-gray-100 text-gray-800 border-gray-300'

  const colors = statusColorMap[assetStatus.color] || statusColorMap.gray
  return `${colors.bg} ${colors.text} ${colors.border}`
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

      <!-- ═══ Barre d'actions groupées (tous utilisateurs, sélection active) ═══ -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2">
        <div v-if="selection.count > 0"
             class="mb-4 flex items-center gap-3 bg-blue-600 text-white rounded-xl px-5 py-3 shadow-lg">
          <div class="flex items-center gap-2">
            <span class="font-bold text-lg">{{ selection.count }}</span>
            <span class="text-blue-200 text-sm">équipement(s) sélectionné(s)</span>
          </div>
          <button @click="selection.clearSelection()"
                  class="text-blue-300 hover:text-white text-xs underline ml-1">
            Tout désélectionner
          </button>
          <div class="ml-auto flex gap-2">
            <!-- Export PDF (tous) -->
            <button @click="openExportModal('pdf')"
                    :disabled="exportingPdf"
                    class="flex items-center gap-1.5 bg-red-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-600 transition disabled:opacity-50">
              <DocumentArrowDownIcon class="w-4 h-4" />
              {{ exportingPdf ? 'Export...' : 'PDF' }}
            </button>
            <!-- Export Excel (tous) -->
            <button @click="openExportModal('excel')"
                    :disabled="exportingExcel"
                    class="flex items-center gap-1.5 bg-green-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-600 transition disabled:opacity-50">
              <TableCellsIcon class="w-4 h-4" />
              {{ exportingExcel ? 'Export...' : 'Excel' }}
            </button>
            <!-- Actions admin uniquement -->
            <template v-if="isAdmin">
              <button @click="openBulkReserve"
                      class="flex items-center gap-1.5 bg-purple-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-purple-600 transition">
                📅 Réserver
              </button>
              <button @click="openBulkLabel"
                      class="flex items-center gap-1.5 bg-amber-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-amber-600 transition">
                🏷 Étiquettes
              </button>
              <button @click="openBulkEdit"
                      class="flex items-center gap-1.5 bg-white text-blue-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-50 transition">
                ✏️ Modifier
              </button>
              <button @click="showBulkDeleteModal = true"
                      class="flex items-center gap-1.5 bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition">
                🗑 Supprimer
              </button>
            </template>
          </div>
        </div>
      </Transition>

      <!-- ══ ONGLET LISTE ══ -->
      <div v-if="activeTab === 'list'">

        <!-- Filtres actifs badges -->
        <div v-if="activeFiltersCount > 0" class="mb-3 flex flex-wrap items-center gap-2 text-sm">
          <span class="text-gray-500 text-xs">Filtres actifs :</span>
          <span v-if="selectedPlace" class="flex items-center gap-1 bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full text-xs">
            <MapPinIcon class="w-3 h-3" /> {{ places.find(p => String(p.id) === String(selectedPlace))?.name ?? selectedPlace }}
            <button @click="selectedPlace=''; applyFilters()" class="ml-1 hover:text-indigo-900 font-bold">×</button>
          </span>
          <span v-if="selectedLocation" class="flex items-center gap-1 bg-blue-100 text-blue-700 px-2.5 py-1 rounded-full text-xs">
            📍 {{ selectedLocation }}
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
          <span v-if="selectedStatus" class="flex items-center gap-1 bg-orange-100 text-orange-700 px-2.5 py-1 rounded-full text-xs">
            🔖 {{ assetStatuses.find(s => s.key === selectedStatus)?.label ?? selectedStatus }}
            <button @click="selectedStatus=''; applyFilters()" class="ml-1 hover:text-orange-900 font-bold">×</button>
          </span>
          <span v-if="selectedModel" class="flex items-center gap-1 bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full text-xs">
            🧩 Modèle : {{ activeModelName }}
            <button @click="selectedModel=''; applyFilters()" class="ml-1 hover:text-indigo-900 font-bold">×</button>
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
          <select v-model="selectedStatus" @change="applyFilters"
                  class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none min-w-36">
            <option value="">Tous statuts</option>
            <option v-for="s in assetStatuses" :key="s.key" :value="s.key">{{ s.label }}</option>
          </select>
          <!-- Filtre par lieu formel (place_id) -->
          <select v-if="isAdmin" v-model="selectedPlace" @change="applyFilters"
                  class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none min-w-36">
            <option value="">Tous les lieux</option>
            <option v-for="p in places" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <!-- Filtre par emplacement legacy (location texte) -->
          <select v-if="isAdmin && locations?.length > 0" v-model="selectedLocation" @change="applyFilters"
                  class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none min-w-36">
            <option value="">Tous emplacements</option>
            <option v-for="loc in locations" :key="loc.location" :value="loc.location">{{ loc.location }} ({{ loc.asset_count }})</option>
          </select>
        </div>

        <!-- ═══ GRID VIEW ═══ -->
        <div v-if="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          <div v-for="asset in assets.data" :key="asset.id"
               class="bg-white rounded-xl border shadow-sm overflow-hidden hover:shadow-md transition group"
               :class="[
                 { 'opacity-60': isAdmin && !isReservable(asset) },
                 selection.isSelected(asset.id) ? 'border-blue-400 ring-2 ring-blue-200' : '',
               ]">

            <!-- Image -->
            <div class="relative h-44 bg-gradient-to-br from-gray-100 to-gray-200 overflow-hidden">
              <img v-if="asset.image" :src="asset.image" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" :alt="asset.name" />
              <div v-else class="w-full h-full flex items-center justify-center">
                <span class="text-5xl opacity-40">📦</span>
              </div>
              <!-- Checkbox pour tous (top-left) -->
              <div class="absolute top-2 left-2 z-10">
                <input type="checkbox" :checked="selection.isSelected(asset.id)"
                       @change="toggleSelect(asset)"
                       class="w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer shadow bg-white/90" />
              </div>
              <!-- Status badge overlay -->
              <div class="absolute top-2 left-9">
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
                <!-- Statut bloquant (maintenance, en préparation…) : bouton désactivé -->
                <div v-if="asset.status_blocks_reservation"
                     class="w-full flex items-center justify-center gap-2 py-2 rounded-lg text-sm font-medium bg-yellow-50 text-yellow-700 border border-yellow-200 cursor-not-allowed">
                  <WrenchScrewdriverIcon class="w-4 h-4" />
                  {{ asset.status_label }}
                </div>
                <button v-else @click="toggleCart(asset)"
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

          <!-- Barre de colonnes (admin, vue liste normale) -->
          <div v-if="isAdmin && !groupByModel" class="flex items-center justify-end gap-2 px-4 py-2 border-b bg-gray-50">
            <span class="text-xs text-gray-400">{{ visibleAdminCols.length }} colonne(s) affichée(s)</span>
            <button @click="showColPanel = true"
                    class="flex items-center gap-1.5 text-xs text-gray-600 hover:text-blue-600 border border-gray-300 hover:border-blue-400 px-3 py-1.5 rounded-lg transition bg-white hover:bg-blue-50">
              <Cog6ToothIcon class="w-3.5 h-3.5" /> Colonnes
            </button>
          </div>

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
                        selection.isSelected(asset.id) ? 'bg-blue-50' : '',
                      ]">
                    <!-- Checkbox sélection -->
                    <td class="px-3 py-3 w-10 text-center">
                      <input type="checkbox" :checked="selection.isSelected(asset.id)"
                             @change="toggleSelect(asset)"
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
                      <template v-else>
                        <span v-if="asset.status_blocks_reservation"
                              class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg bg-yellow-50 text-yellow-700 border border-yellow-200 cursor-not-allowed whitespace-nowrap">
                          <WrenchScrewdriverIcon class="w-3.5 h-3.5" />
                          {{ asset.status_label }}
                        </span>
                        <button v-else @click="toggleCart(asset)"
                                class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg transition"
                                :class="cart.hasItem(asset.id)
                                  ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                  : 'bg-blue-100 text-blue-700 hover:bg-blue-200'">
                          <CheckIcon v-if="cart.hasItem(asset.id)" class="w-3.5 h-3.5" />
                          <ShoppingCartIcon v-else class="w-3.5 h-3.5" />
                          {{ cart.hasItem(asset.id) ? 'Dans le panier' : 'Ajouter' }}
                        </button>
                      </template>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

          <!-- Normal table (no grouping) — Admin : colonnes dynamiques / User : colonnes fixes -->
          <template v-else>

            <!-- ═══ TABLE ADMIN (colonnes configurables + scroll horizontal) ═══ -->
            <div v-if="isAdmin" class="overflow-x-auto">
              <table class="text-sm" style="min-width:max-content;width:100%">
                <thead>
                  <tr class="text-xs text-gray-500 uppercase bg-gray-50 border-b">
                    <!-- Checkbox fixe gauche -->
                    <th class="sticky left-0 z-20 bg-gray-50 px-3 py-3 w-10 text-center border-r border-gray-200">
                      <input type="checkbox" :checked="allOnPageSelected" @change="toggleSelectAll"
                             class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer" />
                    </th>
                    <!-- Colonnes configurables -->
                    <template v-for="col in visibleAdminCols" :key="col.key">
                      <th v-if="col.key==='name'"           class="px-4 py-3 text-left whitespace-nowrap">Équipement</th>
                      <th v-else-if="col.key==='asset_tag'" class="px-4 py-3 text-left whitespace-nowrap">Tag</th>
                      <th v-else-if="col.key==='category'"  class="px-4 py-3 text-left whitespace-nowrap">Catégorie</th>
                      <th v-else-if="col.key==='model'"     class="px-4 py-3 text-left whitespace-nowrap">Modèle</th>
                      <th v-else-if="col.key==='location'"  class="px-4 py-3 text-left whitespace-nowrap">Emplacement</th>
                      <th v-else-if="col.key==='manufacturer'" class="px-4 py-3 text-left whitespace-nowrap">Fabricant</th>
                      <th v-else-if="col.key==='status'"    class="px-4 py-3 text-left whitespace-nowrap">Statut</th>
                      <th v-else-if="col.key==='condition'"  class="px-4 py-3 text-left whitespace-nowrap">État</th>
                      <th v-else-if="col.key==='checked_out_to'" class="px-4 py-3 text-left whitespace-nowrap">Affecté à</th>
                      <th v-else-if="col.key==='return_date'"    class="px-4 py-3 text-left whitespace-nowrap">Retour prévu</th>
                      <th v-else-if="col.key==='accessories'"    class="px-4 py-3 text-left whitespace-nowrap">Accessoires</th>
                      <th v-else-if="col.key==='label'"     class="px-4 py-3 text-center whitespace-nowrap">Étiquette</th>
                      <th v-else-if="col.key==='reservable'" class="px-4 py-3 text-center whitespace-nowrap">Réservable</th>
                    </template>
                    <!-- Actions fixes droite -->
                    <th class="sticky right-0 z-20 bg-gray-50 px-4 py-3 text-left whitespace-nowrap border-l border-gray-200">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="asset in assets.data" :key="asset.id"
                      class="border-b hover:bg-gray-50 transition"
                      :class="[
                        { 'opacity-60': !isReservable(asset) },
                        selection.isSelected(asset.id) ? 'bg-blue-50 hover:bg-blue-100' : '',
                      ]">

                    <!-- Checkbox fixe gauche -->
                    <td class="sticky left-0 z-10 px-3 py-3 w-10 text-center border-r border-gray-200"
                        :class="selection.isSelected(asset.id) ? 'bg-blue-50' : 'bg-white'">
                      <input type="checkbox" :checked="selection.isSelected(asset.id)"
                             @change="toggleSelect(asset)"
                             class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer" />
                    </td>

                    <!-- Colonnes configurables -->
                    <template v-for="col in visibleAdminCols" :key="col.key">
                      <!-- Équipement -->
                      <td v-if="col.key==='name'" class="px-4 py-3">
                        <div class="flex items-center gap-3">
                          <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                            <img v-if="asset.image" :src="asset.image" class="w-9 h-9 rounded-lg object-cover" />
                            <span v-else class="text-lg">📦</span>
                          </div>
                          <div class="min-w-0">
                            <p class="font-medium text-gray-900 whitespace-nowrap">{{ asset.name }}</p>
                            <p class="text-xs text-gray-400 font-mono">{{ asset.asset_tag }}</p>
                          </div>
                        </div>
                      </td>
                      <!-- Tag -->
                      <td v-else-if="col.key==='asset_tag'" class="px-4 py-3 text-gray-500 font-mono text-xs whitespace-nowrap">
                        {{ asset.asset_tag }}
                      </td>
                      <!-- Catégorie -->
                      <td v-else-if="col.key==='category'" class="px-4 py-3">
                        <select v-if="isAdmin"
                                :value="asset.category_id || ''"
                                @change="quickUpdateField(asset.id, 'category_id', $event.target.value || null)"
                                class="text-sm border-gray-300 rounded px-2 py-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer">
                          <option value="">— Aucune —</option>
                          <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                          </option>
                        </select>
                        <button v-else-if="asset.category" @click="filterByCategory(asset.category_id)"
                                class="text-gray-600 hover:text-blue-600 hover:underline text-left text-sm whitespace-nowrap">
                          {{ asset.category }}
                        </button>
                        <span v-else class="text-gray-300 text-xs">—</span>
                      </td>
                      <!-- Modèle -->
                      <td v-else-if="col.key==='model'" class="px-4 py-3">
                        <Link v-if="asset.equipment_model_id"
                              :href="route('models.index') + '?highlight=' + asset.equipment_model_id"
                              class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline whitespace-nowrap">
                          🧩 {{ asset.model_name }}
                        </Link>
                        <span v-else class="text-gray-300 text-xs">—</span>
                      </td>
                      <!-- Emplacement -->
                      <td v-else-if="col.key==='location'" class="px-4 py-3">
                        <select v-if="isAdmin"
                                :value="asset.location || ''"
                                @change="quickUpdateField(asset.id, 'location', $event.target.value || null)"
                                class="text-sm border-gray-300 rounded px-2 py-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer">
                          <option value="">— Aucun —</option>
                          <option v-for="loc in locations" :key="loc" :value="loc">
                            {{ loc }}
                          </option>
                        </select>
                        <button v-else-if="asset.location" @click="filterByLocation(asset.location)"
                                class="text-gray-600 hover:text-blue-600 hover:underline text-sm flex items-center gap-1 whitespace-nowrap">
                          <MapPinIcon class="w-3 h-3 text-gray-400" /> {{ asset.location }}
                        </button>
                        <span v-else class="text-gray-300 text-xs">—</span>
                      </td>
                      <!-- Fabricant -->
                      <td v-else-if="col.key==='manufacturer'" class="px-4 py-3">
                        <select v-if="isAdmin"
                                :value="asset.manufacturer_id || ''"
                                @change="quickUpdateField(asset.id, 'manufacturer_id', $event.target.value || null)"
                                class="text-sm border-gray-300 rounded px-2 py-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer">
                          <option value="">— Aucun —</option>
                          <option v-for="mfr in manufacturers" :key="mfr.id" :value="mfr.id">
                            {{ mfr.name }}
                          </option>
                        </select>
                        <span v-else class="text-sm text-gray-600 whitespace-nowrap">
                          {{ asset.manufacturer || '—' }}
                        </span>
                      </td>
                      <!-- Statut -->
                      <td v-else-if="col.key==='status'" class="px-4 py-3">
                        <div class="flex items-center gap-1.5">
                          <select v-if="isAdmin"
                                  :value="asset.status"
                                  @change="quickUpdateField(asset.id, 'status', $event.target.value)"
                                  :class="['text-xs font-semibold border-2 rounded-full px-3 py-1 pr-8 cursor-pointer focus:ring-2 focus:ring-blue-500 appearance-none transition-colors', getStatusClasses(asset.status)]"
                                  style="background-position: right 0.5rem center; background-size: 1.25em 1.25em; background-repeat: no-repeat; background-image: url(&quot;data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23374151' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e&quot;);">
                            <option v-for="status in assetStatuses" :key="status.key" :value="status.key">
                              {{ status.label }}
                            </option>
                          </select>
                          <StatusBadge v-else :status="asset.status" type="asset" />
                          <span v-if="asset.label_to_create || asset.label_to_apply"
                                class="text-xs font-medium text-orange-600 bg-orange-50 border border-orange-200 rounded px-1.5 py-0.5 whitespace-nowrap"
                                :title="asset.label_to_create && asset.label_to_apply ? 'Étiquette à créer et à coller' : asset.label_to_create ? 'Étiquette à créer' : 'Étiquette à coller'">
                            🏷️
                          </span>
                        </div>
                      </td>
                      <!-- État (condition_note) -->
                      <td v-else-if="col.key==='condition'" class="px-4 py-3">
                        <div v-if="asset.condition_note"
                             class="group relative max-w-xs">
                          <div class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 truncate">
                            📋 {{ asset.condition_note }}
                          </div>
                          <!-- Tooltip au survol -->
                          <div class="invisible group-hover:visible absolute z-10 left-0 top-full mt-1 w-64 px-3 py-2 bg-amber-900 text-white text-xs rounded-lg shadow-lg">
                            {{ asset.condition_note }}
                          </div>
                        </div>
                        <span v-else class="text-gray-300 text-xs">—</span>
                      </td>
                      <!-- Affecté à -->
                      <td v-else-if="col.key==='checked_out_to'" class="px-4 py-3">
                        <span v-if="asset.checked_out_to" class="text-sm text-gray-800 font-medium whitespace-nowrap">
                          👤 {{ asset.checked_out_to.name }}
                        </span>
                        <span v-else class="text-gray-300 text-xs">—</span>
                      </td>
                      <!-- Retour prévu -->
                      <td v-else-if="col.key==='return_date'" class="px-4 py-3">
                        <span v-if="asset.expected_return_at" class="text-sm text-orange-600 font-medium whitespace-nowrap">
                          {{ asset.expected_return_at }}
                        </span>
                        <span v-else class="text-gray-300 text-xs">—</span>
                      </td>
                      <!-- Accessoires -->
                      <td v-else-if="col.key==='accessories'" class="px-4 py-3">
                        <template v-if="asset.accessories?.length">
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
                      <!-- Étiquette -->
                      <td v-else-if="col.key==='label'" class="px-4 py-3 text-center">
                        <template v-if="asset.label_to_create || asset.label_to_apply">
                          <span v-if="asset.label_to_create && asset.label_to_apply"
                                class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 border border-indigo-300 whitespace-nowrap">
                            ✏️📌 Créer + Coller
                          </span>
                          <span v-else-if="asset.label_to_create"
                                class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 border border-amber-300 whitespace-nowrap">
                            ✏️ À créer
                          </span>
                          <span v-else
                                class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 border border-blue-300 whitespace-nowrap">
                            📌 À coller
                          </span>
                        </template>
                        <span v-else class="text-gray-300 text-xs">—</span>
                      </td>
                      <!-- Réservable -->
                      <td v-else-if="col.key==='reservable'" class="px-4 py-3 text-center">
                        <div class="flex flex-col items-center gap-1">
                          <button @click="toggleReservable(asset)"
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
                    </template>

                    <!-- Actions fixes droite -->
                    <td class="sticky right-0 z-10 px-4 py-3 border-l border-gray-200"
                        :class="selection.isSelected(asset.id) ? 'bg-blue-50' : 'bg-white'">
                      <div class="flex gap-2 flex-nowrap whitespace-nowrap">
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
                    </td>
                  </tr>

                  <tr v-if="assets.data.length === 0">
                    <td :colspan="visibleAdminCols.length + 2" class="px-4 py-12 text-center text-gray-400">
                      <p class="text-4xl mb-2">📦</p>
                      <p class="font-medium">Aucun équipement trouvé</p>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- ═══ TABLE NON-ADMIN (colonnes fixes) ═══ -->
            <table v-else class="w-full text-sm">
              <thead>
                <tr class="text-xs text-gray-500 uppercase bg-gray-50 border-b">
                  <th class="px-3 py-3 w-10 text-center">
                    <input type="checkbox" :checked="allOnPageSelected" @change="toggleSelectAll"
                           class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer" />
                  </th>
                  <th class="px-4 py-3 text-left">Équipement</th>
                  <th class="px-4 py-3 text-left hidden md:table-cell">Tag</th>
                  <th class="px-4 py-3 text-left hidden lg:table-cell">Catégorie</th>
                  <th class="px-4 py-3 text-left hidden lg:table-cell">Emplacement</th>
                  <th class="px-4 py-3 text-left hidden xl:table-cell">Accessoires</th>
                  <th class="px-4 py-3 text-left">Statut</th>
                  <th class="px-4 py-3 text-left hidden xl:table-cell">État</th>
                  <th class="px-4 py-3 text-left">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="asset in assets.data" :key="asset.id"
                    class="border-b hover:bg-gray-50 transition"
                    :class="selection.isSelected(asset.id) ? 'bg-blue-50 hover:bg-blue-100' : ''">
                  <td class="px-3 py-3 w-10 text-center">
                    <input type="checkbox" :checked="selection.isSelected(asset.id)"
                           @change="toggleSelect(asset)"
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
                    <span v-if="asset.category" class="text-sm text-gray-600">{{ asset.category }}</span>
                    <span v-else class="text-gray-400">—</span>
                  </td>
                  <td class="px-4 py-3 hidden lg:table-cell">
                    <span v-if="asset.location" class="text-sm text-gray-600 flex items-center gap-1">
                      <MapPinIcon class="w-3 h-3 text-gray-400" /> {{ asset.location }}
                    </span>
                    <span v-else class="text-gray-400">—</span>
                  </td>
                  <td class="px-4 py-3 hidden xl:table-cell">
                    <template v-if="asset.accessories?.length">
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
                  <td class="px-4 py-3"><StatusBadge :status="asset.status" type="asset" /></td>
                  <td class="px-4 py-3 hidden xl:table-cell">
                    <div v-if="asset.condition_note"
                         class="group relative max-w-xs">
                      <div class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 truncate">
                        📋 {{ asset.condition_note }}
                      </div>
                      <!-- Tooltip au survol -->
                      <div class="invisible group-hover:visible absolute z-10 left-0 top-full mt-1 w-64 px-3 py-2 bg-amber-900 text-white text-xs rounded-lg shadow-lg whitespace-pre-wrap">
                        {{ asset.condition_note }}
                      </div>
                    </div>
                    <span v-else class="text-gray-300 text-xs">—</span>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
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
                  <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                    <p class="text-4xl mb-2">📦</p>
                    <p class="font-medium">Aucun équipement disponible à l'emprunt.</p>
                  </td>
                </tr>
              </tbody>
            </table>
          </template>

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
        <div class="flex items-center justify-between mb-4">
          <p class="text-sm text-gray-500">{{ categories.length }} catégorie(s) · Cliquez pour filtrer les équipements</p>
          <button v-if="isAdmin" @click="openCatCreate($event)"
                  class="flex items-center gap-1.5 bg-blue-600 text-white px-3 py-1.5 rounded-lg text-sm font-medium hover:bg-blue-700 transition shadow-sm">
            <PlusIcon class="w-4 h-4" /> Nouvelle catégorie
          </button>
        </div>
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
            <!-- Boutons admin (au survol) -->
            <template v-if="isAdmin">
              <button @click="openCatEdit(cat, $event)"
                      class="absolute top-2 right-10 w-7 h-7 rounded-lg bg-white/90 shadow border border-gray-200 flex items-center justify-center opacity-0 group-hover:opacity-100 transition hover:bg-blue-50 hover:border-blue-300 hover:text-blue-600 text-gray-500"
                      title="Modifier la catégorie">
                <PencilIcon class="w-3.5 h-3.5" />
              </button>
              <button @click="openCatDelete(cat, $event)"
                      class="absolute top-2 right-2 w-7 h-7 rounded-lg bg-white/90 shadow border border-gray-200 flex items-center justify-center opacity-0 group-hover:opacity-100 transition hover:bg-red-50 hover:border-red-300 hover:text-red-600 text-gray-500"
                      title="Supprimer la catégorie">
                <TrashIcon class="w-3.5 h-3.5" />
              </button>
            </template>
          </div>

          <div v-if="categories.length === 0" class="col-span-full text-center text-gray-400 py-12">
            <TagIcon class="w-12 h-12 mx-auto mb-2 opacity-30" />
            <p>Aucune catégorie</p>
          </div>
        </div>
      </div>

      <!-- ══ ONGLET LIEUX ══ -->
      <div v-if="activeTab === 'locations'">
        <div class="flex items-center justify-between mb-4">
          <p class="text-sm text-gray-500">
            {{ places?.length ?? 0 }} lieu(x) configuré(s) ·
            <Link :href="route('asset-places.index')" class="text-indigo-600 hover:underline">
              Gérer les affectations →
            </Link>
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <Link
            v-for="place in places" :key="place.id"
            :href="route('places.show', place.id)"
            class="bg-white rounded-xl border shadow-sm p-5 text-left hover:shadow-md transition group flex items-center gap-4"
            :style="{ borderLeftColor: place.color, borderLeftWidth: '4px' }">

            <!-- Icône colorée -->
            <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0 transition"
              :style="{ background: place.color + '22' }">
              <MapPinIcon v-if="!place.is_storage" class="w-5 h-5" :style="{ color: place.color }" />
              <BuildingStorefrontIcon v-else class="w-5 h-5" :style="{ color: place.color }" />
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <p class="font-semibold text-gray-900 group-hover:text-indigo-600 transition truncate">{{ place.name }}</p>
                <span v-if="place.is_storage"
                  class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full shrink-0">Stockage</span>
              </div>
              <p v-if="place.description" class="text-xs text-gray-400 mt-0.5 truncate">{{ place.description }}</p>
              <p class="text-sm mt-0.5" :class="place.active_assignments_count > 0 ? 'text-indigo-600 font-medium' : 'text-gray-400'">
                {{ place.active_assignments_count }}
                équipement{{ place.active_assignments_count !== 1 ? 's' : '' }} assigné{{ place.active_assignments_count !== 1 ? 's' : '' }}
              </p>
            </div>

            <span class="text-2xl font-bold text-gray-200 group-hover:text-indigo-200 transition shrink-0">
              {{ place.active_assignments_count }}
            </span>
          </Link>

          <div v-if="!places?.length" class="col-span-full text-center text-gray-400 py-12">
            <MapPinIcon class="w-12 h-12 mx-auto mb-2 opacity-30" />
            <p>Aucun lieu configuré</p>
            <Link :href="route('asset-places.index')" class="mt-2 inline-block text-indigo-500 hover:underline text-sm">
              Créer le premier lieu →
            </Link>
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
        <h3 class="font-bold text-lg mb-2">🗑 Supprimer {{ selection.count }} équipement(s)</h3>
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
            {{ bulkProcessing ? 'Suppression…' : `🗑 Supprimer ${selection.count} équipement(s)` }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : modification en masse ═══ -->
    <div v-if="showBulkEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg flex flex-col max-h-[90vh]">
        <!-- En-tête fixe -->
        <div class="flex items-center justify-between px-6 pt-6 pb-2 flex-shrink-0">
          <h3 class="font-bold text-lg">✏️ Modifier {{ selection.count }} équipement(s)</h3>
          <button @click="showBulkEditModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <p class="text-sm text-gray-500 px-6 pb-4 flex-shrink-0">
          Cochez les champs à modifier. Les champs non cochés resteront <strong>inchangés</strong>.
        </p>

        <!-- Contenu défilable -->
        <div class="space-y-3 overflow-y-auto px-6 flex-1 min-h-0 pb-2">
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

          <!-- Fabricant -->
          <div class="flex items-start gap-3 p-3 border rounded-lg transition"
               :class="bulkEditForm.updateManufacturer ? 'border-blue-300 bg-blue-50' : 'border-gray-200'">
            <input type="checkbox" v-model="bulkEditForm.updateManufacturer" id="bulk-manufacturer"
                   class="mt-1 w-4 h-4 rounded text-blue-600 border-gray-300 focus:ring-blue-500 cursor-pointer flex-shrink-0" />
            <div class="flex-1">
              <label for="bulk-manufacturer" class="block text-sm font-medium text-gray-700 cursor-pointer mb-1">Fabricant</label>
              <select v-model="bulkEditForm.manufacturer_id" :disabled="!bulkEditForm.updateManufacturer"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none disabled:opacity-40 disabled:bg-gray-50">
                <option value="">— Aucun fabricant —</option>
                <option v-for="m in manufacturers" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
            </div>
          </div>

          <!-- Lieu -->
          <div class="flex items-start gap-3 p-3 border rounded-lg transition"
               :class="bulkPlaceNeedsFormalAssign ? 'border-indigo-300 bg-indigo-50' : (bulkEditForm.updatePlace ? 'border-blue-300 bg-blue-50' : 'border-gray-200')">
            <input type="checkbox" v-model="bulkEditForm.updatePlace" id="bulk-place"
                   class="mt-1 w-4 h-4 rounded text-blue-600 border-gray-300 focus:ring-blue-500 cursor-pointer flex-shrink-0" />
            <div class="flex-1">
              <label for="bulk-place" class="block text-sm font-medium text-gray-700 cursor-pointer mb-1">
                Lieu
                <span v-if="bulkPlaceNeedsFormalAssign" class="ml-1.5 text-xs bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded-full font-normal">
                  Affectation formelle
                </span>
              </label>
              <select v-model="bulkEditForm.place_id" :disabled="!bulkEditForm.updatePlace"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none disabled:opacity-40 disabled:bg-gray-50">
                <option value="">— Retirer du lieu actuel —</option>
                <option v-for="p in places" :key="p.id" :value="p.id">
                  {{ p.is_storage ? '📦 ' : '📍 ' }}{{ p.name }}
                </option>
              </select>

              <!-- Formulaire d'affectation formelle (lieu non-stockage sélectionné) -->
              <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0">
                <div v-if="bulkPlaceNeedsFormalAssign" class="mt-3 space-y-3 border-t border-indigo-200 pt-3">
                  <p class="text-xs text-indigo-700 flex items-center gap-1.5">
                    <span>📍</span>
                    Une <strong>affectation formelle</strong> sera créée pour chaque équipement sélectionné vers
                    <strong>{{ bulkSelectedPlace?.name }}</strong>.
                    Les équipements déjà affectés à un autre lieu seront ignorés.
                  </p>

                  <!-- Type : Temporaire / Permanent -->
                  <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                      <input type="radio" :value="false" v-model="bulkEditForm.assignReturn"
                             class="text-indigo-600" />
                      <span class="font-medium text-gray-700">Permanent</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-sm">
                      <input type="radio" :value="true" v-model="bulkEditForm.assignReturn"
                             class="text-indigo-600" />
                      <span class="font-medium text-gray-700">Temporaire</span>
                    </label>
                  </div>

                  <!-- Date de retour (si temporaire) -->
                  <Transition name="fade">
                    <div v-if="bulkEditForm.assignReturn">
                      <label class="block text-xs font-medium text-gray-600 mb-1">Date de retour prévue</label>
                      <input v-model="bulkEditForm.return_expected_at" type="datetime-local"
                             class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
                    </div>
                  </Transition>

                  <!-- Notes -->
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Notes (optionnel)</label>
                    <input v-model="bulkEditForm.assignNotes" type="text"
                           placeholder="Motif, contexte, responsable…"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
                  </div>
                </div>
              </Transition>

              <p v-if="bulkEditForm.updatePlace && !bulkPlaceNeedsFormalAssign" class="text-xs text-gray-400 mt-1">
                {{ bulkEditForm.place_id
                  ? (bulkPlaceIsStorage ? '📦 Le lieu de stockage sera mis à jour (sans affectation formelle).' : '')
                  : 'Le lieu de rangement sera retiré des équipements sélectionnés.' }}
              </p>
            </div>
          </div>
        </div>

        <!-- Pied de page fixe -->
        <div class="flex gap-3 px-6 py-4 border-t border-gray-100 flex-shrink-0">
          <button @click="showBulkEditModal = false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">
            Annuler
          </button>
          <button @click="executeBulkUpdate"
            :disabled="bulkProcessing || (!bulkEditForm.updateStatus && !bulkEditForm.updateCategory && !bulkEditForm.updateLocation && !bulkEditForm.updateReservable && !bulkEditForm.updateModel && !bulkEditForm.updateManufacturer && !bulkEditForm.updatePlace)"
            :class="['flex-1 py-2 rounded-lg text-sm font-medium disabled:opacity-50 transition text-white',
              bulkPlaceNeedsFormalAssign ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-blue-600 hover:bg-blue-700']">
            <template v-if="bulkProcessing">Traitement…</template>
            <template v-else-if="bulkPlaceNeedsFormalAssign">
              📍 Affecter {{ selection.count }} équipement(s) à « {{ bulkSelectedPlace?.name }} »
            </template>
            <template v-else>
              ✏️ Appliquer à {{ selection.count }} équipement(s)
            </template>
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
            {{ selection.count }} équipement(s) inclus dans la réservation
          </p>
          <ul class="space-y-1 max-h-32 overflow-y-auto">
            <li v-for="asset in assets.data.filter(a => selection.isSelected(a.id))" :key="asset.id"
                class="flex items-center gap-2 text-xs text-gray-700">
              <span class="w-1.5 h-1.5 rounded-full bg-blue-400 flex-shrink-0" />
              <span class="font-mono text-gray-400">[{{ asset.asset_tag }}]</span>
              {{ asset.name }}
            </li>
          </ul>
          <p v-if="selection.count > assets.data.filter(a => selection.isSelected(a.id)).length"
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
            {{ bulkReserveProcessing ? 'Création en cours…' : `📅 Créer la réservation (${selection.count})` }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : étiquettes en masse ═══ -->
    <div v-if="showBulkLabelModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-2">
          <h3 class="font-bold text-lg">🏷 Étiquettes — {{ selection.count }} équipement(s)</h3>
          <button @click="showBulkLabelModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <p class="text-sm text-gray-500 mb-5">
          Cochez les champs à modifier. Les champs non cochés resteront <strong>inchangés</strong>.
        </p>

        <div class="space-y-3">
          <!-- Étiquette à créer -->
          <div class="flex items-start gap-3 p-3 border rounded-lg transition"
               :class="bulkLabelForm.updateLabelToCreate ? 'border-amber-300 bg-amber-50' : 'border-gray-200'">
            <input type="checkbox" v-model="bulkLabelForm.updateLabelToCreate" id="bulk-label-create"
                   class="mt-1 w-4 h-4 rounded text-amber-600 border-gray-300 focus:ring-amber-500 cursor-pointer flex-shrink-0" />
            <div class="flex-1">
              <label for="bulk-label-create" class="block text-sm font-medium text-gray-700 cursor-pointer mb-2">
                ✏️ Étiquette à créer
              </label>
              <div class="flex gap-5">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="bulkLabelForm.label_to_create" :value="true"
                         :disabled="!bulkLabelForm.updateLabelToCreate"
                         class="text-amber-600 disabled:opacity-40" />
                  <span class="text-sm text-amber-700 font-medium">Activer</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="bulkLabelForm.label_to_create" :value="false"
                         :disabled="!bulkLabelForm.updateLabelToCreate"
                         class="text-amber-600 disabled:opacity-40" />
                  <span class="text-sm text-gray-600 font-medium">Désactiver</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Étiquette à coller -->
          <div class="flex items-start gap-3 p-3 border rounded-lg transition"
               :class="bulkLabelForm.updateLabelToApply ? 'border-blue-300 bg-blue-50' : 'border-gray-200'">
            <input type="checkbox" v-model="bulkLabelForm.updateLabelToApply" id="bulk-label-apply"
                   class="mt-1 w-4 h-4 rounded text-blue-600 border-gray-300 focus:ring-blue-500 cursor-pointer flex-shrink-0" />
            <div class="flex-1">
              <label for="bulk-label-apply" class="block text-sm font-medium text-gray-700 cursor-pointer mb-2">
                📌 Étiquette à coller
              </label>
              <div class="flex gap-5">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="bulkLabelForm.label_to_apply" :value="true"
                         :disabled="!bulkLabelForm.updateLabelToApply"
                         class="text-blue-600 disabled:opacity-40" />
                  <span class="text-sm text-blue-700 font-medium">Activer</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="radio" v-model="bulkLabelForm.label_to_apply" :value="false"
                         :disabled="!bulkLabelForm.updateLabelToApply"
                         class="text-blue-600 disabled:opacity-40" />
                  <span class="text-sm text-gray-600 font-medium">Désactiver</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <div class="flex gap-3 mt-6">
          <button @click="showBulkLabelModal = false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">
            Annuler
          </button>
          <button @click="executeBulkLabel"
            :disabled="bulkLabelProcessing || (!bulkLabelForm.updateLabelToCreate && !bulkLabelForm.updateLabelToApply)"
            class="flex-1 bg-amber-500 text-white py-2 rounded-lg text-sm font-medium hover:bg-amber-600 disabled:opacity-50">
            {{ bulkLabelProcessing ? 'Mise à jour…' : `🏷 Appliquer à ${selection.count} équipement(s)` }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Panneau configuration des colonnes (admin) ═══ -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-x-full"
      enter-to-class="opacity-100 translate-x-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-x-0"
      leave-to-class="opacity-0 translate-x-full">
      <div v-if="showColPanel" class="fixed inset-0 z-50 flex justify-end" @click.self="showColPanel = false">
        <div class="w-80 bg-white h-full shadow-2xl border-l border-gray-200 flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-4 border-b bg-gray-50">
            <div>
              <h3 class="font-bold text-gray-900 text-sm">Colonnes du tableau</h3>
              <p class="text-xs text-gray-400 mt-0.5">Glisser pour réordonner · cliquer pour afficher/masquer</p>
            </div>
            <button @click="showColPanel = false" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-200 transition">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Colonnes list -->
          <div class="flex-1 overflow-y-auto py-2">
            <div v-for="col in columnsConfig" :key="col.key"
                 draggable="true"
                 @dragstart="onColDragStart($event, col.key)"
                 @dragenter="onColDragEnter(col.key)"
                 @dragover="onColDragOver"
                 @drop="onColDrop(col.key)"
                 @dragend="onColDragEnd"
                 class="flex items-center gap-3 px-4 py-2.5 cursor-grab active:cursor-grabbing transition select-none"
                 :class="[
                   colDragTarget === col.key && colDragKey !== col.key ? 'bg-blue-50 border-t-2 border-blue-400' : 'hover:bg-gray-50',
                   colDragKey === col.key ? 'opacity-40' : '',
                 ]">
              <!-- Drag handle -->
              <Bars3Icon class="w-4 h-4 text-gray-300 flex-shrink-0" />
              <!-- Toggle visibility -->
              <button @click="toggleCol(col.key)"
                      class="flex-1 flex items-center gap-2 text-left"
                      :class="col.visible ? 'text-gray-800' : 'text-gray-400'">
                <span class="text-sm font-medium">{{ colLabel(col.key) }}</span>
              </button>
              <button @click="toggleCol(col.key)"
                      :title="col.visible ? 'Masquer' : 'Afficher'"
                      class="flex-shrink-0 p-1 rounded transition"
                      :class="col.visible ? 'text-blue-500 hover:text-blue-700 hover:bg-blue-50' : 'text-gray-300 hover:text-gray-500 hover:bg-gray-100'">
                <EyeIcon v-if="col.visible" class="w-4 h-4" />
                <EyeSlashIcon v-else class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-5 py-4 border-t bg-gray-50 space-y-2">
            <div class="flex items-center justify-between text-xs text-gray-500">
              <span>{{ visibleAdminCols.length }} / {{ columnsConfig.length }} colonnes visibles</span>
            </div>
            <div class="flex gap-2">
              <button @click="columnsConfig.forEach(c => c.visible = true)"
                      class="flex-1 text-xs border border-gray-300 py-1.5 rounded-lg hover:bg-gray-100 transition">
                Tout afficher
              </button>
              <button @click="resetCols"
                      class="flex-1 text-xs border border-gray-300 py-1.5 rounded-lg hover:bg-gray-100 transition">
                Réinitialiser
              </button>
            </div>
            <button @click="showColPanel = false"
                    class="w-full bg-blue-600 text-white text-sm py-2 rounded-lg hover:bg-blue-700 transition font-medium">
              Appliquer
            </button>
          </div>
        </div>
      </div>
    </Transition>

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

    <!-- ═══ Modal création catégorie (admin) ═══ -->
    <div v-if="showCatCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showCatCreateModal = false">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">Nouvelle catégorie</h3>
          <button @click="showCatCreateModal = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 transition">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Photo</label>
            <div class="flex items-center gap-4">
              <div class="w-16 h-16 rounded-xl border-2 border-dashed border-gray-200 flex items-center justify-center overflow-hidden bg-gray-50 flex-shrink-0"
                   :style="catCreatePreview ? '' : (catCreateForm.color ? `background-color: ${catCreateForm.color}22` : '')">
                <img v-if="catCreatePreview" :src="catCreatePreview" class="w-full h-full object-cover rounded-lg" alt="Aperçu" />
                <PhotoIcon v-else class="w-7 h-7 text-gray-300" />
              </div>
              <label class="cursor-pointer text-sm text-blue-600 hover:underline">
                Choisir une image
                <input type="file" accept="image/*" class="hidden" @change="onCatCreateImage" />
              </label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
            <input v-model="catCreateForm.name" type="text" placeholder="Audio, Vidéo, Réseau…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            <p v-if="catCreateForm.errors.name" class="text-red-500 text-xs mt-1">{{ catCreateForm.errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="catCreateForm.description" rows="2"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
            <div class="flex items-center gap-3">
              <input v-model="catCreateForm.color" type="color"
                class="w-10 h-10 rounded-lg border border-gray-300 cursor-pointer p-1" />
              <span class="text-sm text-gray-500">{{ catCreateForm.color }}</span>
            </div>
          </div>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="showCatCreateModal = false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitCatCreate" :disabled="catCreateForm.processing || !catCreateForm.name.trim()"
            class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ catCreateForm.processing ? 'Création…' : 'Créer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal suppression catégorie (admin) ═══ -->
    <div v-if="showCatDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showCatDeleteModal = false">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
        <div class="flex items-start gap-3 mb-4">
          <div class="flex-shrink-0 w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
            <TrashIcon class="w-5 h-5 text-red-600" />
          </div>
          <div>
            <h3 class="font-bold text-gray-900">Supprimer « {{ catDeleteTarget?.name }} » ?</h3>
            <p class="text-sm text-gray-500 mt-0.5">Cette action est irréversible.</p>
          </div>
        </div>

        <!-- Cas : catégorie avec des équipements liés -->
        <div v-if="catDeleteTarget?.assets_count > 0" class="space-y-3 mb-4">
          <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-800">
            <span class="font-semibold">{{ catDeleteTarget.assets_count }} équipement(s)</span>
            sont actuellement liés à cette catégorie.
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Réassigner vers une autre catégorie</label>
            <select v-model="catDeleteReassign"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
              <option value="">— Laisser sans catégorie —</option>
              <option v-for="c in categories.filter(c => c.id !== catDeleteTarget?.id)" :key="c.id" :value="c.id">
                {{ c.name }} ({{ c.assets_count }} équip.)
              </option>
            </select>
            <p class="text-xs text-gray-400 mt-1">
              {{ catDeleteReassign
                ? `Les équipements seront déplacés vers « ${categories.find(c => c.id == catDeleteReassign)?.name} ».`
                : 'Les équipements perdront leur catégorie (champ vide).' }}
            </p>
          </div>
        </div>
        <div v-else class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 mb-4 text-sm text-gray-600">
          Aucun équipement n'est associé à cette catégorie.
        </div>

        <div class="flex gap-3">
          <button @click="showCatDeleteModal = false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitCatDelete" :disabled="catDeleteForm.processing"
            class="flex-1 bg-red-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
            {{ catDeleteForm.processing ? 'Suppression…' : 'Supprimer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Alerte : équipement sorti ═══ -->
    <div v-if="checkedOutAlert" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-start gap-3 mb-4">
          <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
            <span class="text-xl">⚠️</span>
          </div>
          <div>
            <h3 class="font-bold text-gray-900 text-base">Équipement actuellement sorti</h3>
            <p class="text-sm text-gray-500 mt-0.5">{{ checkedOutAlert.name }}</p>
          </div>
        </div>

        <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 mb-5 text-sm text-orange-800 space-y-1">
          <p>Cet équipement est <strong>actuellement sorti</strong> et n'est pas disponible immédiatement.</p>
          <p v-if="checkedOutAlert.expected_return_at" class="mt-2">
            📅 Retour prévu le <strong>{{ checkedOutAlert.expected_return_at }}</strong>
          </p>
          <p v-else class="mt-2 text-orange-600 italic">Aucune date de retour prévue renseignée.</p>
          <p class="mt-2 text-xs text-orange-600">Vous pouvez quand même l'ajouter au panier et choisir des dates de réservation postérieures à sa disponibilité.</p>
        </div>

        <div class="flex gap-3">
          <button @click="checkedOutAlert = null"
                  class="flex-1 border border-gray-300 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-50 transition">
            Annuler
          </button>
          <button @click="confirmAddCheckedOut"
                  class="flex-1 bg-orange-500 hover:bg-orange-600 text-white py-2.5 rounded-xl text-sm font-medium transition">
            Ajouter quand même
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal : Configuration de l'export ═══ -->
    <div v-if="showExportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">{{ exportFormat === 'pdf' ? '📄' : '📊' }} Configuration de l'export</h3>
          <button @click="showExportModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <p class="text-sm text-gray-500 mb-4">
          {{ selection.count }} équipement(s) sélectionné(s)
        </p>

        <!-- Titre personnalisé -->
        <div class="mb-4">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Titre du document</label>
          <input type="text" v-model="exportTitle"
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                 placeholder="Ex: Liste des Équipements Multimédia" />
        </div>

        <!-- Colonnes (admin uniquement) -->
        <div v-if="isAdmin" class="mb-4">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Colonnes à inclure</label>
          <div class="space-y-2 max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-3">
            <label v-for="col in availableExportColumns" :key="col.key"
                   class="flex items-center gap-3 p-2 rounded hover:bg-gray-50 cursor-pointer transition">
              <input type="checkbox" v-model="exportColumnsAdmin[col.key]"
                     class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer" />
              <span class="text-sm text-gray-700">{{ col.label }}</span>
            </label>
          </div>
        </div>

        <!-- Colonnes fixes pour non-admin -->
        <div v-else class="mb-4">
          <label class="block text-sm font-semibold text-gray-700 mb-2">Colonnes incluses</label>
          <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
            <p class="text-xs text-gray-600 mb-2">Colonnes fixes pour cet export :</p>
            <div class="flex flex-wrap gap-2">
              <span v-for="col in availableExportColumns" :key="col.key"
                    class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-medium">
                {{ col.label }}
              </span>
            </div>
          </div>
        </div>

        <!-- Option de regroupement par modèle (PDF uniquement) -->
        <div v-if="exportFormat === 'pdf'" class="mb-5 p-4 bg-blue-50 border border-blue-200 rounded-lg">
          <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" v-model="exportGroupByModel"
                   class="mt-0.5 w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer" />
            <div>
              <span class="text-sm font-semibold text-blue-900">Grouper par modèle</span>
              <p class="text-xs text-blue-700 mt-1">
                Les équipements du même modèle seront regroupés avec leur quantité.
              </p>
            </div>
          </label>
        </div>

        <div class="flex gap-3">
          <button @click="showExportModal = false"
                  class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">
            Annuler
          </button>
          <button @click="executeExport"
                  :disabled="isAdmin && Object.values(exportColumnsAdmin).filter(v => v).length === 0"
                  class="flex-1 py-2 rounded-lg text-sm font-medium text-white transition disabled:opacity-50"
                  :class="exportFormat === 'pdf' ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600'">
            <template v-if="exportFormat === 'pdf'">
              📄 Exporter en PDF
            </template>
            <template v-else>
              📊 Exporter en Excel
            </template>
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>
