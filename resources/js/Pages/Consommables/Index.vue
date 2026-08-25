<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { useForm, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import {
  MagnifyingGlassIcon, PlusIcon, PencilIcon, TrashIcon,
  ArrowUpTrayIcon, PhotoIcon, XMarkIcon, ArchiveBoxIcon,
  ExclamationTriangleIcon, CheckCircleIcon, ClockIcon,
  AdjustmentsHorizontalIcon, ArrowPathIcon,
  Squares2X2Icon, ListBulletIcon, ViewColumnsIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  consommables:        Object,
  consommableRequests: Object,
  categories:          Array,
  pendingCount:        Number,
  filters:             Object,
  isAdmin:             Boolean,
})

// ─── Onglets ──────────────────────────────────────────────────────────────────
const activeTab = ref(props.filters?.tab ?? 'list')

function switchTab(tab) {
  activeTab.value = tab
  router.get(route('consommables.index'), { ...currentFilters(), tab }, {
    preserveState: true, preserveScroll: true, replace: true,
  })
}

// ─── Mode d'affichage ─────────────────────────────────────────────────────────
const viewMode = ref(localStorage.getItem('consommables_view') ?? 'grid')
watch(viewMode, v => localStorage.setItem('consommables_view', v))

// ─── Filtres liste ────────────────────────────────────────────────────────────
const search          = ref(props.filters?.search ?? '')
const selectedCat     = ref(props.filters?.category ?? '')
const onlyRequestable = ref(props.filters?.requestable === '1')

function currentFilters() {
  return {
    search:      search.value      || undefined,
    category:    selectedCat.value || undefined,
    requestable: onlyRequestable.value ? '1' : undefined,
    tab:         activeTab.value !== 'list' ? activeTab.value : undefined,
  }
}

function applyFilters() {
  router.get(route('consommables.index'), currentFilters(), {
    preserveState: true, preserveScroll: true, replace: true,
  })
}

let searchTimer = null
watch(search, () => { clearTimeout(searchTimer); searchTimer = setTimeout(applyFilters, 350) })
watch([selectedCat, onlyRequestable], applyFilters)

// ─── Modal création / édition ─────────────────────────────────────────────────
const showFormModal = ref(false)
const editTarget    = ref(null)
const imagePreview  = ref(null)

const form = useForm({
  name:           '',
  reference:      '',
  unit:           'pièce',
  quantity:       0,
  min_stock:      0,
  category_id:    '',
  is_requestable: false,
  unit_cost:      '',
  description:    '',
  image:          null,
  remove_image:   false,
})

function openCreate() {
  editTarget.value   = null
  imagePreview.value = null
  form.reset()
  form.unit           = 'pièce'
  form.quantity       = 0
  form.min_stock      = 0
  form.is_requestable = false
  showFormModal.value = true
}

function openEdit(c) {
  editTarget.value    = c
  imagePreview.value  = c.image_url ?? null
  form.name           = c.name
  form.reference      = c.reference
  form.unit           = c.unit
  form.quantity       = c.quantity  ?? 0
  form.min_stock      = c.min_stock ?? 0
  form.category_id    = c.category_id ?? ''
  form.is_requestable = c.is_requestable
  form.unit_cost      = c.unit_cost ?? ''
  form.description    = c.description ?? ''
  form.image          = null
  form.remove_image   = false
  showFormModal.value = true
}

function onImageChange(e) {
  const file = e.target.files[0]
  if (!file) return
  form.image         = file
  form.remove_image  = false
  imagePreview.value = URL.createObjectURL(file)
}

function removeImage() {
  form.image         = null
  form.remove_image  = true
  imagePreview.value = null
}

function submitForm() {
  if (editTarget.value) {
    form
      .transform(data => ({ ...data, _method: 'put' }))
      .post(route('consommables.update', editTarget.value.id), {
        forceFormData: true,
        onSuccess: () => { showFormModal.value = false },
      })
  } else {
    form.post(route('consommables.store'), {
      forceFormData: true,
      onSuccess: () => { showFormModal.value = false; form.reset() },
    })
  }
}

function deleteConsommable(c) {
  if (!confirm(`Supprimer « ${c.name} » ? Cette action est irréversible.`)) return
  router.delete(route('consommables.destroy', c.id))
}

// ─── Ajustement de stock (admin) ──────────────────────────────────────────────
const adjustTarget  = ref(null)
const adjustDelta   = ref(1)
const adjustNotes   = ref('')
const adjustLoading = ref(false)
const adjustError   = ref(null)

function openAdjust(c) {
  adjustTarget.value = c
  adjustDelta.value  = 1
  adjustNotes.value  = ''
  adjustError.value  = null
}

function closeAdjust() { adjustTarget.value = null }

function quickAdjust(c, delta) {
  adjustTarget.value = c
  adjustDelta.value  = delta
  adjustNotes.value  = ''
  adjustError.value  = null
}

async function doAdjust() {
  if (!adjustTarget.value) return
  adjustLoading.value = true
  adjustError.value   = null
  try {
    await axios.post(route('consommables.adjust-stock', adjustTarget.value.id), {
      delta: adjustDelta.value,
      notes: adjustNotes.value || null,
    })
    router.reload({ only: ['consommables'] })
    adjustTarget.value = null
  } catch (err) {
    adjustError.value = err.response?.data?.errors?.delta?.[0] ?? 'Erreur lors de l\'ajustement.'
  } finally {
    adjustLoading.value = false
  }
}

// ─── Modal demande utilisateur ────────────────────────────────────────────────
const showRequestModal = ref(false)
const requestTarget    = ref(null)
const requestForm      = useForm({ quantity: 1, notes: '' })

function openRequest(c) {
  requestTarget.value    = c
  requestForm.quantity   = 1
  requestForm.notes      = ''
  showRequestModal.value = true
}

function submitRequest() {
  requestForm.post(route('consommable-requests.store', requestTarget.value.id), {
    onSuccess: () => { showRequestModal.value = false },
  })
}

// ─── Approbation / Refus (admin) ──────────────────────────────────────────────
const showRejectModal = ref(false)
const rejectTarget    = ref(null)
const rejectForm      = useForm({ admin_notes: '' })
const approveForm     = useForm({ admin_notes: '' })

function approveRequest(r) {
  approveForm.post(route('consommable-requests.approve', r.id))
}

function openReject(r) {
  rejectTarget.value     = r
  rejectForm.admin_notes = ''
  showRejectModal.value  = true
}

function submitReject() {
  rejectForm.post(route('consommable-requests.reject', rejectTarget.value.id), {
    onSuccess: () => { showRejectModal.value = false },
  })
}

function cancelRequest(r) {
  if (!confirm('Annuler cette demande ?')) return
  router.delete(route('consommable-requests.cancel', r.id))
}

// ─── Import CSV ───────────────────────────────────────────────────────────────
const showImport     = ref(false)
const importStep     = ref(1)
const importSep      = ref('semicolon')
const importFile     = ref(null)
const importHeaders  = ref([])
const importPreview  = ref([])
const importRowCount = ref(0)
const importMapping  = ref({})
const importQtyMode  = ref('increment')
const importLoading  = ref(false)
const importResults  = ref(null)
const importError    = ref(null)

const csvFields = [
  { value: '',            label: '— Ignorer —' },
  { value: 'name',        label: 'Nom' },
  { value: 'reference',   label: 'Référence' },
  { value: 'unit',        label: 'Unité' },
  { value: 'quantity',    label: 'Quantité' },
  { value: 'min_stock',   label: 'Stock minimum' },
  { value: 'unit_cost',   label: 'Prix unitaire' },
  { value: 'category',    label: 'Catégorie' },
  { value: 'description', label: 'Description' },
]

function openImport() {
  importStep.value    = 1
  importFile.value    = null
  importHeaders.value = []
  importPreview.value = []
  importMapping.value = {}
  importResults.value = null
  importError.value   = null
  showImport.value    = true
}

function onImportFile(e) {
  importFile.value = e.target.files[0] ?? null
}

async function previewImport() {
  if (!importFile.value) return
  importLoading.value = true
  importError.value   = null
  const fd = new FormData()
  fd.append('file', importFile.value)
  fd.append('separator', importSep.value)
  try {
    const { data } = await axios.post(route('consommables.import.preview'), fd)
    importHeaders.value  = data.headers
    importPreview.value  = data.preview
    importRowCount.value = data.row_count
    importMapping.value  = { ...data.suggested_mapping }
    importStep.value     = 2
  } catch (err) {
    importError.value = err.response?.data?.message ?? 'Impossible de lire le fichier.'
  } finally {
    importLoading.value = false
  }
}

async function processImport() {
  importLoading.value = true
  importError.value   = null
  const fd = new FormData()
  fd.append('file', importFile.value)
  fd.append('separator', importSep.value)
  fd.append('qty_mode', importQtyMode.value)
  Object.entries(importMapping.value).forEach(([k, v]) => fd.append(`mapping[${k}]`, v))
  try {
    const { data } = await axios.post(route('consommables.import.process'), fd)
    importResults.value = data
    importStep.value    = 3
    router.reload({ only: ['consommables'] })
  } catch (err) {
    importError.value = err.response?.data?.message ?? 'Erreur lors de l\'import.'
  } finally {
    importLoading.value = false
  }
}

// ─── Filtre demandes ──────────────────────────────────────────────────────────
const reqStatus = ref(props.filters?.req_status ?? '')
watch(reqStatus, (val) => {
  router.get(route('consommables.index'), { ...currentFilters(), tab: 'requests', req_status: val || undefined }, {
    preserveState: true, replace: true,
  })
})

// ─── Helpers ──────────────────────────────────────────────────────────────────
function stockBadge(c) {
  if (c.is_out_of_stock) return { label: 'Épuisé',      cls: 'bg-red-100 text-red-700' }
  if (c.is_low_stock)    return { label: 'Stock faible', cls: 'bg-orange-100 text-orange-700' }
  return null
}

function stockColor(c) {
  if (c.is_out_of_stock) return 'text-red-500'
  if (c.is_low_stock)    return 'text-orange-500'
  return 'text-emerald-600'
}
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto">

      <!-- ══ En-tête ══ -->
      <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <ArchiveBoxIcon class="w-7 h-7 text-blue-600" />
            Consommables
          </h1>
          <p class="text-sm text-gray-500 mt-0.5">Gestion du stock de consommables</p>
        </div>
        <div v-if="isAdmin" class="flex items-center gap-2 flex-wrap">
          <button @click="openImport"
            class="flex items-center gap-2 px-4 py-2 border border-gray-300 bg-white rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-blue-300 transition shadow-sm">
            <ArrowUpTrayIcon class="w-4 h-4" /> Importer CSV
          </button>
          <a :href="route('reports.export-csv-consommables')"
             class="flex items-center gap-2 px-4 py-2 border border-gray-300 bg-white rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition shadow-sm">
            ⬇ Exporter CSV
          </a>
          <button @click="openCreate"
            class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition shadow-sm">
            <PlusIcon class="w-4 h-4" /> Nouveau consommable
          </button>
        </div>
      </div>

      <!-- ══ Onglets ══ -->
      <div class="flex gap-1 mb-6 border-b border-gray-200">
        <button @click="switchTab('list')"
          :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition -mb-px', activeTab === 'list'
            ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700']">
          Stock
        </button>
        <button @click="switchTab('requests')"
          :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition -mb-px flex items-center gap-2', activeTab === 'requests'
            ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700']">
          {{ isAdmin ? 'Demandes' : 'Mes demandes' }}
          <span v-if="pendingCount > 0"
            class="bg-orange-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[1.2rem] text-center">
            {{ pendingCount }}
          </span>
        </button>
      </div>

      <!-- ══════════════════════════════════ -->
      <!-- ONGLET STOCK                       -->
      <!-- ══════════════════════════════════ -->
      <div v-if="activeTab === 'list'">

        <!-- Barre outils : filtres + mode d'affichage -->
        <div class="flex flex-wrap gap-3 mb-5 items-center">
          <!-- Recherche -->
          <div class="relative flex-1 min-w-48">
            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input v-model="search" type="text" placeholder="Rechercher…"
              class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>
          <!-- Catégorie -->
          <select v-model="selectedCat"
            class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white">
            <option value="">Toutes les catégories</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <!-- Demandables -->
          <label v-if="isAdmin" class="flex items-center gap-2 text-sm text-gray-600 px-3 py-2 border border-gray-300 rounded-lg bg-white cursor-pointer hover:bg-gray-50">
            <input type="checkbox" v-model="onlyRequestable" class="rounded" />
            Demandables seulement
          </label>

          <!-- Sélecteur de mode d'affichage -->
          <div class="flex border border-gray-200 rounded-lg overflow-hidden shadow-sm flex-shrink-0 ml-auto">
            <button @click="viewMode = 'gallery'" :title="'Galerie'"
              :class="['px-3 py-2 transition flex items-center gap-1.5 text-sm', viewMode === 'gallery'
                ? 'bg-blue-600 text-white'
                : 'bg-white text-gray-500 hover:bg-gray-50']">
              <PhotoIcon class="w-4 h-4" />
              <span class="hidden sm:inline text-xs font-medium">Galerie</span>
            </button>
            <button @click="viewMode = 'grid'" :title="'Grille'"
              :class="['px-3 py-2 border-l border-gray-200 transition flex items-center gap-1.5 text-sm', viewMode === 'grid'
                ? 'bg-blue-600 text-white'
                : 'bg-white text-gray-500 hover:bg-gray-50']">
              <Squares2X2Icon class="w-4 h-4" />
              <span class="hidden sm:inline text-xs font-medium">Grille</span>
            </button>
            <button @click="viewMode = 'compact'" :title="'Compact'"
              :class="['px-3 py-2 border-l border-gray-200 transition flex items-center gap-1.5 text-sm', viewMode === 'compact'
                ? 'bg-blue-600 text-white'
                : 'bg-white text-gray-500 hover:bg-gray-50']">
              <ViewColumnsIcon class="w-4 h-4" />
              <span class="hidden sm:inline text-xs font-medium">Compact</span>
            </button>
            <button @click="viewMode = 'list'" :title="'Liste'"
              :class="['px-3 py-2 border-l border-gray-200 transition flex items-center gap-1.5 text-sm', viewMode === 'list'
                ? 'bg-blue-600 text-white'
                : 'bg-white text-gray-500 hover:bg-gray-50']">
              <ListBulletIcon class="w-4 h-4" />
              <span class="hidden sm:inline text-xs font-medium">Liste</span>
            </button>
          </div>
        </div>

        <!-- État vide -->
        <div v-if="consommables.data.length === 0"
          class="text-center text-gray-400 py-20 bg-white rounded-xl border border-gray-200">
          <ArchiveBoxIcon class="w-14 h-14 mx-auto mb-3 opacity-20" />
          <p class="font-medium text-gray-500">Aucun consommable trouvé</p>
          <p class="text-sm mt-1">Modifiez les filtres ou créez un nouveau consommable.</p>
        </div>

        <!-- ══ MODE GALERIE ══ : grandes cartes, image en vedette, pleine vue -->
        <div v-else-if="viewMode === 'gallery'"
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
          <div v-for="c in consommables.data" :key="c.id"
            class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-lg transition-shadow flex flex-col overflow-hidden group">

            <!-- Zone image : grande, fond blanc, image entière visible -->
            <div class="relative h-52 bg-white flex items-center justify-center p-5 border-b border-gray-100 flex-shrink-0">
              <img v-if="c.image_url" :src="c.image_url" :alt="c.name"
                class="max-h-full max-w-full object-contain" />
              <ArchiveBoxIcon v-else class="w-16 h-16 text-gray-150 opacity-20" />

              <!-- Badges superposés -->
              <div class="absolute top-2.5 left-2.5 flex flex-col gap-1">
                <span v-if="stockBadge(c)"
                  :class="['text-xs font-semibold px-2 py-0.5 rounded-full shadow-sm', stockBadge(c).cls]">
                  {{ stockBadge(c).label }}
                </span>
                <span v-if="c.is_requestable && !isAdmin"
                  class="text-xs font-medium px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 shadow-sm">
                  Demandable
                </span>
              </div>

              <!-- Overlay actions admin au survol -->
              <div v-if="isAdmin"
                class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                <button @click.stop="openEdit(c)"
                  class="p-2 bg-white rounded-xl text-gray-700 hover:text-blue-600 shadow-md transition">
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button @click.stop="deleteConsommable(c)"
                  class="p-2 bg-white rounded-xl text-gray-700 hover:text-red-600 shadow-md transition">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- Corps -->
            <div class="p-4 flex-1 flex flex-col gap-1">
              <p class="font-bold text-gray-900 text-sm leading-tight">{{ c.name }}</p>
              <p class="text-xs text-gray-400 font-mono">{{ c.reference }}</p>
              <span v-if="c.category"
                class="self-start text-xs text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full mt-0.5">
                {{ c.category }}
              </span>

              <!-- Stock -->
              <div class="mt-auto pt-3 flex items-end justify-between gap-2">
                <div class="flex items-baseline gap-1.5">
                  <span class="text-3xl font-black leading-none" :class="stockColor(c)">
                    {{ c.quantity }}
                  </span>
                  <span class="text-xs text-gray-400 font-medium">{{ c.unit }}(s)</span>
                </div>
                <div class="text-right">
                  <p v-if="c.min_stock > 0" class="text-xs text-gray-400">seuil : {{ c.min_stock }}</p>
                  <p v-if="c.unit_cost" class="text-xs text-gray-500 font-medium">{{ Number(c.unit_cost).toFixed(2) }} €/u</p>
                </div>
              </div>
            </div>

            <!-- Barre actions admin -->
            <div v-if="isAdmin" class="border-t border-gray-100 px-3 py-2.5 flex items-center gap-1.5">
              <button @click="quickAdjust(c, -1)"
                class="px-2.5 py-1.5 text-xs border border-gray-200 rounded-lg text-gray-600 hover:bg-red-50 hover:border-red-300 hover:text-red-600 transition font-mono font-bold">
                −1
              </button>
              <button @click="quickAdjust(c, 1)"
                class="px-2.5 py-1.5 text-xs border border-gray-200 rounded-lg text-gray-600 hover:bg-green-50 hover:border-green-300 hover:text-green-600 transition font-mono font-bold">
                +1
              </button>
              <button @click="quickAdjust(c, 5)"
                class="px-2.5 py-1.5 text-xs border border-gray-200 rounded-lg text-gray-600 hover:bg-green-50 hover:border-green-300 hover:text-green-600 transition font-mono font-bold">
                +5
              </button>
              <button @click="openAdjust(c)"
                class="px-2 py-1.5 text-xs border border-blue-200 bg-blue-50 rounded-lg text-blue-600 hover:bg-blue-100 transition">
                <AdjustmentsHorizontalIcon class="w-3.5 h-3.5" />
              </button>
            </div>

            <!-- Bouton Demander (user) -->
            <div v-if="!isAdmin && c.is_requestable" class="p-3 border-t border-gray-100">
              <button @click="openRequest(c)" :disabled="c.is_out_of_stock"
                class="w-full py-2 text-sm font-medium rounded-lg transition"
                :class="c.is_out_of_stock
                  ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                  : 'bg-blue-600 text-white hover:bg-blue-700'">
                {{ c.is_out_of_stock ? 'Stock épuisé' : 'Demander' }}
              </button>
            </div>
          </div>
        </div>

        <!-- ══ MODE GRILLE ══ : cartes moyennes équilibrées -->
        <div v-else-if="viewMode === 'grid'"
          class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
          <div v-for="c in consommables.data" :key="c.id"
            class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow flex flex-col overflow-hidden group">

            <!-- Image carrée, pleine vue -->
            <div class="relative aspect-square bg-white flex items-center justify-center p-4 border-b border-gray-100 flex-shrink-0">
              <img v-if="c.image_url" :src="c.image_url" :alt="c.name"
                class="max-h-full max-w-full object-contain" />
              <ArchiveBoxIcon v-else class="w-10 h-10 opacity-15 text-gray-400" />

              <div class="absolute top-1.5 left-1.5 flex flex-col gap-1">
                <span v-if="stockBadge(c)"
                  :class="['text-xs font-semibold px-1.5 py-0.5 rounded-full', stockBadge(c).cls]">
                  {{ stockBadge(c).label }}
                </span>
              </div>

              <div v-if="isAdmin"
                class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                <button @click.stop="openEdit(c)"
                  class="p-1.5 bg-white rounded-lg text-gray-700 hover:text-blue-600 shadow transition">
                  <PencilIcon class="w-3.5 h-3.5" />
                </button>
                <button @click.stop="deleteConsommable(c)"
                  class="p-1.5 bg-white rounded-lg text-gray-700 hover:text-red-600 shadow transition">
                  <TrashIcon class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- Info -->
            <div class="p-3 flex-1 flex flex-col gap-1">
              <p class="font-semibold text-gray-900 text-xs leading-snug line-clamp-2">{{ c.name }}</p>
              <p class="text-xs text-gray-400 font-mono truncate">{{ c.reference }}</p>
              <span v-if="c.category" class="self-start text-xs text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded-full">
                {{ c.category }}
              </span>

              <div class="mt-auto pt-2 flex items-baseline justify-between gap-1">
                <span class="text-2xl font-black leading-none" :class="stockColor(c)">{{ c.quantity }}</span>
                <span class="text-xs text-gray-400">{{ c.unit }}</span>
              </div>
            </div>

            <!-- Actions rapides admin -->
            <div v-if="isAdmin" class="flex border-t border-gray-100">
              <button @click="quickAdjust(c, -1)"
                class="flex-1 py-1.5 text-xs text-gray-500 hover:bg-red-50 hover:text-red-600 transition font-mono font-bold border-r border-gray-100">
                −1
              </button>
              <button @click="quickAdjust(c, 1)"
                class="flex-1 py-1.5 text-xs text-gray-500 hover:bg-green-50 hover:text-green-600 transition font-mono font-bold border-r border-gray-100">
                +1
              </button>
              <button @click="openAdjust(c)"
                class="px-3 py-1.5 text-xs text-blue-500 hover:bg-blue-50 transition">
                <AdjustmentsHorizontalIcon class="w-3.5 h-3.5" />
              </button>
            </div>

            <!-- Bouton Demander (user) -->
            <div v-if="!isAdmin && c.is_requestable" class="p-2 border-t border-gray-100">
              <button @click="openRequest(c)" :disabled="c.is_out_of_stock"
                class="w-full py-1.5 text-xs font-medium rounded-lg transition"
                :class="c.is_out_of_stock
                  ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                  : 'bg-blue-600 text-white hover:bg-blue-700'">
                {{ c.is_out_of_stock ? 'Épuisé' : 'Demander' }}
              </button>
            </div>
          </div>
        </div>

        <!-- ══ MODE COMPACT ══ : petites vignettes, max d'items visibles -->
        <div v-else-if="viewMode === 'compact'"
          class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-3">
          <div v-for="c in consommables.data" :key="c.id"
            class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow flex flex-col overflow-hidden group cursor-pointer"
            @click="isAdmin ? openEdit(c) : (c.is_requestable ? openRequest(c) : null)">

            <!-- Vignette image, pleine vue -->
            <div class="relative aspect-square bg-white flex items-center justify-center p-2 flex-shrink-0">
              <img v-if="c.image_url" :src="c.image_url" :alt="c.name"
                class="max-h-full max-w-full object-contain" />
              <ArchiveBoxIcon v-else class="w-6 h-6 opacity-15 text-gray-400" />

              <!-- Indicateur stock -->
              <div class="absolute bottom-1 right-1">
                <span :class="['w-2 h-2 rounded-full block',
                  c.is_out_of_stock ? 'bg-red-500' : c.is_low_stock ? 'bg-orange-400' : 'bg-emerald-500']" />
              </div>
            </div>

            <!-- Label -->
            <div class="px-2 pb-2 flex-1 flex flex-col border-t border-gray-100 pt-1.5">
              <p class="text-xs font-semibold text-gray-800 leading-tight line-clamp-2">{{ c.name }}</p>
              <div class="mt-auto flex items-center justify-between pt-1">
                <span class="text-sm font-black" :class="stockColor(c)">{{ c.quantity }}</span>
                <span class="text-xs text-gray-400">{{ c.unit }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- ══ MODE LISTE ══ : tableau dense avec toutes les infos -->
        <div v-else-if="viewMode === 'list'"
          class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
              <tr>
                <th class="px-4 py-3 w-16 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Photo</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Désignation</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Catégorie</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide">Stock</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Prix/u</th>
                <th v-if="isAdmin" class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide hidden xl:table-cell">Ajuster</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="c in consommables.data" :key="c.id"
                class="hover:bg-blue-50/30 transition group">

                <!-- Miniature -->
                <td class="px-4 py-2.5">
                  <div class="w-14 h-14 bg-white rounded-lg border border-gray-100 flex items-center justify-center overflow-hidden flex-shrink-0 shadow-sm">
                    <img v-if="c.image_url" :src="c.image_url" :alt="c.name"
                      class="max-h-full max-w-full object-contain p-1" />
                    <ArchiveBoxIcon v-else class="w-6 h-6 text-gray-200" />
                  </div>
                </td>

                <!-- Nom + Ref -->
                <td class="px-4 py-2.5">
                  <p class="font-semibold text-gray-900 text-sm">{{ c.name }}</p>
                  <p class="text-xs text-gray-400 font-mono mt-0.5">{{ c.reference }}</p>
                  <div class="flex gap-1 mt-1 flex-wrap">
                    <span v-if="stockBadge(c)"
                      :class="['text-xs font-medium px-1.5 py-0.5 rounded-full', stockBadge(c).cls]">
                      {{ stockBadge(c).label }}
                    </span>
                    <span v-if="c.is_requestable && !isAdmin"
                      class="text-xs px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700">
                      Demandable
                    </span>
                    <span v-if="c.is_requestable && isAdmin"
                      class="text-xs px-1.5 py-0.5 rounded-full bg-blue-50 text-blue-500">
                      Demandable
                    </span>
                  </div>
                </td>

                <!-- Catégorie -->
                <td class="px-4 py-2.5 hidden md:table-cell">
                  <span v-if="c.category"
                    class="text-xs text-indigo-600 bg-indigo-50 px-2 py-1 rounded-full font-medium">
                    {{ c.category }}
                  </span>
                  <span v-else class="text-gray-300 text-sm">—</span>
                </td>

                <!-- Stock -->
                <td class="px-4 py-2.5 text-right">
                  <span class="text-xl font-black" :class="stockColor(c)">{{ c.quantity }}</span>
                  <p class="text-xs text-gray-400">{{ c.unit }}(s)</p>
                  <p v-if="c.min_stock > 0" class="text-xs text-gray-300">min {{ c.min_stock }}</p>
                </td>

                <!-- Prix unitaire -->
                <td class="px-4 py-2.5 text-right hidden lg:table-cell">
                  <span v-if="c.unit_cost" class="text-sm font-semibold text-gray-700">
                    {{ Number(c.unit_cost).toFixed(2) }} €
                  </span>
                  <span v-else class="text-gray-300">—</span>
                </td>

                <!-- Ajustement rapide (admin) -->
                <td v-if="isAdmin" class="px-4 py-2.5 hidden xl:table-cell">
                  <div class="flex items-center justify-center gap-1">
                    <button @click="quickAdjust(c, -1)"
                      class="px-2 py-1 text-xs border border-gray-200 rounded-lg text-gray-600 hover:bg-red-50 hover:border-red-300 hover:text-red-600 transition font-mono font-bold">
                      −1
                    </button>
                    <button @click="quickAdjust(c, 1)"
                      class="px-2 py-1 text-xs border border-gray-200 rounded-lg text-gray-600 hover:bg-green-50 hover:border-green-300 hover:text-green-600 transition font-mono font-bold">
                      +1
                    </button>
                    <button @click="quickAdjust(c, 5)"
                      class="px-2 py-1 text-xs border border-gray-200 rounded-lg text-gray-600 hover:bg-green-50 hover:border-green-300 hover:text-green-600 transition font-mono font-bold">
                      +5
                    </button>
                    <button @click="openAdjust(c)"
                      class="p-1.5 text-xs border border-blue-200 bg-blue-50 rounded-lg text-blue-600 hover:bg-blue-100 transition">
                      <AdjustmentsHorizontalIcon class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </td>

                <!-- Actions -->
                <td class="px-4 py-2.5">
                  <div class="flex items-center justify-center gap-1.5">
                    <!-- Admin : ajuster (visible sur mobile, cachés en xl) -->
                    <button v-if="isAdmin" @click="openAdjust(c)"
                      class="xl:hidden p-1.5 text-gray-400 hover:text-blue-600 border border-gray-200 hover:border-blue-300 rounded-lg transition">
                      <AdjustmentsHorizontalIcon class="w-4 h-4" />
                    </button>
                    <button v-if="isAdmin" @click="openEdit(c)"
                      class="p-1.5 text-gray-400 hover:text-blue-600 border border-gray-200 hover:border-blue-300 rounded-lg transition">
                      <PencilIcon class="w-4 h-4" />
                    </button>
                    <button v-if="isAdmin" @click="deleteConsommable(c)"
                      class="p-1.5 text-gray-300 hover:text-red-500 border border-gray-200 hover:border-red-200 rounded-lg transition">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                    <!-- User -->
                    <button v-if="!isAdmin && c.is_requestable" @click="openRequest(c)"
                      :disabled="c.is_out_of_stock"
                      class="px-3 py-1.5 text-xs font-medium rounded-lg transition"
                      :class="c.is_out_of_stock
                        ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                        : 'bg-blue-600 text-white hover:bg-blue-700'">
                      {{ c.is_out_of_stock ? 'Épuisé' : 'Demander' }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="consommables.last_page > 1" class="flex justify-center gap-2 mt-6 flex-wrap">
          <a v-for="link in consommables.links" :key="link.label"
            :href="link.url ?? '#'"
            @click.prevent="link.url && router.get(link.url, {}, { preserveScroll: true })"
            v-html="link.label"
            :class="['px-3 py-1.5 rounded-lg text-sm border transition', link.active
              ? 'bg-blue-600 text-white border-blue-600'
              : link.url ? 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' : 'bg-white text-gray-300 border-gray-200 cursor-not-allowed']" />
        </div>
      </div>

      <!-- ══════════════════════════════════ -->
      <!-- ONGLET DEMANDES                    -->
      <!-- ══════════════════════════════════ -->
      <div v-if="activeTab === 'requests'">

        <!-- Filtre statut -->
        <div class="flex gap-2 mb-5">
          <button v-for="s in [{ v: '', l: 'Toutes' }, { v: 'pending', l: 'En attente' }, { v: 'approved', l: 'Approuvées' }, { v: 'rejected', l: 'Refusées' }]"
            :key="s.v" @click="reqStatus = s.v"
            :class="['px-3 py-1.5 rounded-lg text-sm border transition', reqStatus === s.v
              ? 'bg-blue-600 text-white border-blue-600'
              : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50']">
            {{ s.l }}
          </button>
        </div>

        <!-- Table des demandes -->
        <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="text-left px-4 py-3 font-medium text-gray-600">Consommable</th>
                <th class="text-left px-4 py-3 font-medium text-gray-600">Quantité</th>
                <th v-if="isAdmin" class="text-left px-4 py-3 font-medium text-gray-600">Demandeur</th>
                <th class="text-left px-4 py-3 font-medium text-gray-600">Date</th>
                <th class="text-left px-4 py-3 font-medium text-gray-600">Statut</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-if="consommableRequests.data.length === 0">
                <td :colspan="isAdmin ? 6 : 5" class="text-center text-gray-400 py-10">
                  <ClockIcon class="w-8 h-8 mx-auto mb-2 opacity-30" />
                  Aucune demande
                </td>
              </tr>
              <tr v-for="r in consommableRequests.data" :key="r.id"
                class="hover:bg-gray-50 transition">
                <td class="px-4 py-3">
                  <p class="font-medium text-gray-900">{{ r.consommable }}</p>
                  <p class="text-xs text-gray-400 font-mono">{{ r.reference }}</p>
                </td>
                <td class="px-4 py-3 font-semibold">{{ r.quantity }} {{ r.unit }}</td>
                <td v-if="isAdmin" class="px-4 py-3 text-gray-600">{{ r.user }}</td>
                <td class="px-4 py-3 text-gray-500 text-xs">{{ r.created_at }}</td>
                <td class="px-4 py-3">
                  <StatusBadge :status="r.status" :label="r.status_label" :color="r.status_color" />
                </td>
                <td class="px-4 py-3">
                  <div v-if="isAdmin && r.status === 'pending'" class="flex gap-2">
                    <button @click="approveRequest(r)" :disabled="approveForm.processing"
                      class="flex items-center gap-1 text-xs bg-green-50 text-green-700 border border-green-200 hover:bg-green-100 px-2.5 py-1.5 rounded-lg transition disabled:opacity-50">
                      <CheckCircleIcon class="w-3.5 h-3.5" /> Approuver
                    </button>
                    <button @click="openReject(r)"
                      class="flex items-center gap-1 text-xs bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 px-2.5 py-1.5 rounded-lg transition">
                      <XMarkIcon class="w-3.5 h-3.5" /> Refuser
                    </button>
                  </div>
                  <p v-else-if="r.admin_notes" class="text-xs text-gray-500 italic max-w-xs truncate" :title="r.admin_notes">
                    {{ r.admin_notes }}
                  </p>
                  <button v-if="!isAdmin && r.status === 'pending'" @click="cancelRequest(r)"
                    class="text-xs text-red-500 hover:text-red-700 hover:underline">
                    Annuler
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination demandes -->
        <div v-if="consommableRequests.last_page > 1" class="flex justify-center gap-2 mt-4 flex-wrap">
          <a v-for="link in consommableRequests.links" :key="link.label"
            :href="link.url ?? '#'"
            @click.prevent="link.url && router.get(link.url, { tab: 'requests' }, { preserveScroll: true })"
            v-html="link.label"
            :class="['px-3 py-1.5 rounded-lg text-sm border transition', link.active
              ? 'bg-blue-600 text-white border-blue-600'
              : link.url ? 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' : 'bg-white text-gray-300 border-gray-200 cursor-not-allowed']" />
        </div>
      </div>

    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!-- MODAL : Création / Édition consommable      -->
    <!-- ═══════════════════════════════════════════ -->
    <div v-if="showFormModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showFormModal = false">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
          <h3 class="font-bold text-lg">{{ editTarget ? 'Modifier le consommable' : 'Nouveau consommable' }}</h3>
          <button @click="showFormModal = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <div class="space-y-4">
          <!-- Image : prévisualisation pleine vue -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Photo</label>
            <div class="flex items-center gap-4">
              <div class="w-24 h-24 rounded-xl border-2 border-dashed border-gray-200 flex items-center justify-center overflow-hidden bg-gray-50 flex-shrink-0 p-2">
                <img v-if="imagePreview" :src="imagePreview" class="max-h-full max-w-full object-contain" />
                <PhotoIcon v-else class="w-8 h-8 text-gray-300" />
              </div>
              <div class="flex flex-col gap-2">
                <label class="cursor-pointer inline-flex items-center gap-1.5 text-sm text-blue-600 hover:underline font-medium">
                  <PhotoIcon class="w-4 h-4" />
                  {{ imagePreview ? 'Changer la photo' : 'Ajouter une photo' }}
                  <input type="file" accept="image/*" class="hidden" @change="onImageChange" />
                </label>
                <button v-if="imagePreview" @click="removeImage"
                  class="inline-flex items-center gap-1 text-xs text-red-500 hover:underline">
                  <XMarkIcon class="w-3.5 h-3.5" /> Supprimer
                </button>
                <p class="text-xs text-gray-400">JPEG, PNG, GIF, WEBP — max 2 Mo</p>
              </div>
            </div>
          </div>

          <!-- Nom + Référence -->
          <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
              <input v-model="form.name" type="text" placeholder="Papier A4, Toner, …"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                :class="{ 'border-red-400': form.errors.name }" />
              <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Référence <span class="text-red-500">*</span></label>
              <input v-model="form.reference" type="text" placeholder="REF-001"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none font-mono"
                :class="{ 'border-red-400': form.errors.reference }" />
              <p v-if="form.errors.reference" class="text-red-500 text-xs mt-1">{{ form.errors.reference }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Unité <span class="text-red-500">*</span></label>
              <input v-model="form.unit" type="text" placeholder="pièce, ramette, L…"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
          </div>

          <!-- Quantité + Stock min -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Quantité en stock</label>
              <input v-model.number="form.quantity" type="number" min="0"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Seuil d'alerte</label>
              <input v-model.number="form.min_stock" type="number" min="0"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
          </div>

          <!-- Catégorie + Prix -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
              <select v-model="form.category_id"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                <option value="">Aucune</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Prix unitaire (€)</label>
              <input v-model="form.unit_cost" type="number" step="0.01" min="0" placeholder="0.00"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
          </div>

          <!-- Demandable -->
          <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50 transition">
            <input type="checkbox" v-model="form.is_requestable" class="w-4 h-4 rounded text-blue-600" />
            <div>
              <p class="text-sm font-medium text-gray-900">Demandable par les utilisateurs</p>
              <p class="text-xs text-gray-500">Les utilisateurs pourront faire une demande pour ce consommable</p>
            </div>
          </label>

          <!-- Description -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="form.description" rows="2"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
          </div>
        </div>

        <div class="flex gap-3 mt-5">
          <button @click="showFormModal = false"
            class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitForm" :disabled="form.processing || !form.name.trim() || !form.reference.trim()"
            class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ form.processing ? 'Enregistrement…' : (editTarget ? 'Mettre à jour' : 'Créer') }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!-- MODAL : Ajustement stock libre              -->
    <!-- ═══════════════════════════════════════════ -->
    <div v-if="adjustTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeAdjust">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">Ajuster le stock</h3>
          <button @click="closeAdjust" class="text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>
        <p class="text-sm text-gray-600 mb-4">
          <span class="font-semibold">{{ adjustTarget.name }}</span> —
          Stock actuel : <span class="font-bold">{{ adjustTarget.quantity }}</span> {{ adjustTarget.unit }}
        </p>

        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Variation (positif = ajout, négatif = retrait)</label>
            <input v-model.number="adjustDelta" type="number"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            <p class="text-xs mt-1" :class="(adjustTarget.quantity + adjustDelta) < 0 ? 'text-red-500' : 'text-gray-400'">
              Nouveau stock : <span class="font-semibold">{{ adjustTarget.quantity + adjustDelta }}</span>
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Note (optionnelle)</label>
            <input v-model="adjustNotes" type="text" placeholder="Motif de l'ajustement…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>
          <p v-if="adjustError" class="text-red-500 text-xs">{{ adjustError }}</p>
        </div>

        <div class="flex gap-3 mt-5">
          <button @click="closeAdjust" class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="doAdjust" :disabled="adjustLoading || adjustDelta === 0 || (adjustTarget.quantity + adjustDelta) < 0"
            class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ adjustLoading ? 'Ajustement…' : 'Confirmer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!-- MODAL : Demande utilisateur                 -->
    <!-- ═══════════════════════════════════════════ -->
    <div v-if="showRequestModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showRequestModal = false">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">Faire une demande</h3>
          <button @click="showRequestModal = false" class="text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <div class="mb-4 p-3 bg-blue-50 rounded-lg">
          <p class="font-semibold text-blue-900 text-sm">{{ requestTarget?.name }}</p>
          <p class="text-xs text-blue-600 font-mono">{{ requestTarget?.reference }}</p>
          <p class="text-xs text-blue-700 mt-1">Stock disponible : {{ requestTarget?.quantity }} {{ requestTarget?.unit }}</p>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Quantité souhaitée <span class="text-red-500">*</span>
            </label>
            <input v-model.number="requestForm.quantity" type="number" min="1" :max="requestTarget?.quantity"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
              :class="{ 'border-red-400': requestForm.errors.quantity }" />
            <p v-if="requestForm.errors.quantity" class="text-red-500 text-xs mt-1">{{ requestForm.errors.quantity }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Motif (optionnel)</label>
            <textarea v-model="requestForm.notes" rows="2" placeholder="Précisez l'utilisation…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
          </div>
        </div>

        <div class="flex gap-3 mt-5">
          <button @click="showRequestModal = false"
            class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitRequest" :disabled="requestForm.processing || requestForm.quantity < 1"
            class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ requestForm.processing ? 'Envoi…' : 'Envoyer la demande' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!-- MODAL : Refus admin                         -->
    <!-- ═══════════════════════════════════════════ -->
    <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showRejectModal = false">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg text-red-700">Refuser la demande</h3>
          <button @click="showRejectModal = false" class="text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>
        <p class="text-sm text-gray-600 mb-4">
          Demande de <span class="font-semibold">{{ rejectTarget?.user }}</span> pour
          <span class="font-semibold">{{ rejectTarget?.quantity }} {{ rejectTarget?.unit }}</span>
          de <span class="font-semibold">{{ rejectTarget?.consommable }}</span>.
        </p>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Motif du refus <span class="text-red-500">*</span></label>
          <textarea v-model="rejectForm.admin_notes" rows="3" placeholder="Expliquez la raison du refus…"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 outline-none resize-none"
            :class="{ 'border-red-400': rejectForm.errors.admin_notes }" />
          <p v-if="rejectForm.errors.admin_notes" class="text-red-500 text-xs mt-1">{{ rejectForm.errors.admin_notes }}</p>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="showRejectModal = false"
            class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitReject" :disabled="rejectForm.processing || !rejectForm.admin_notes.trim()"
            class="flex-1 bg-red-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
            {{ rejectForm.processing ? 'Refus…' : 'Confirmer le refus' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════ -->
    <!-- PANNEAU : Import CSV                        -->
    <!-- ═══════════════════════════════════════════ -->
    <div v-if="showImport" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 p-4" @click.self="showImport = false">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
          <h3 class="font-bold text-lg flex items-center gap-2">
            <ArrowUpTrayIcon class="w-5 h-5 text-blue-600" /> Import CSV
          </h3>
          <button @click="showImport = false" class="text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- Étapes -->
        <div class="flex items-center gap-2 mb-6 text-sm">
          <span :class="['w-6 h-6 rounded-full text-xs flex items-center justify-center font-bold', importStep >= 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-400']">1</span>
          <span class="text-gray-500">Fichier</span>
          <span class="flex-1 h-px bg-gray-200"></span>
          <span :class="['w-6 h-6 rounded-full text-xs flex items-center justify-center font-bold', importStep >= 2 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-400']">2</span>
          <span class="text-gray-500">Mapping</span>
          <span class="flex-1 h-px bg-gray-200"></span>
          <span :class="['w-6 h-6 rounded-full text-xs flex items-center justify-center font-bold', importStep >= 3 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-400']">3</span>
          <span class="text-gray-500">Résultats</span>
        </div>

        <!-- Étape 1 -->
        <div v-if="importStep === 1" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Séparateur CSV</label>
            <div class="flex gap-3">
              <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="radio" v-model="importSep" value="semicolon" class="text-blue-600" /> Point-virgule (;)
              </label>
              <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="radio" v-model="importSep" value="comma" class="text-blue-600" /> Virgule (,)
              </label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Fichier CSV <span class="text-red-500">*</span></label>
            <label class="flex flex-col items-center gap-3 p-8 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition">
              <ArrowUpTrayIcon class="w-8 h-8 text-gray-400" />
              <span class="text-sm text-gray-600 font-medium">
                {{ importFile ? importFile.name : 'Cliquer pour choisir un fichier CSV' }}
              </span>
              <span class="text-xs text-gray-400">CSV, max 5 Mo</span>
              <input type="file" accept=".csv,.txt" class="hidden" @change="onImportFile" />
            </label>
          </div>
          <p v-if="importError" class="text-red-500 text-sm p-3 bg-red-50 rounded-lg">{{ importError }}</p>
          <div class="flex justify-end gap-3">
            <button @click="showImport = false" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
            <button @click="previewImport" :disabled="!importFile || importLoading"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
              <ArrowPathIcon v-if="importLoading" class="w-4 h-4 animate-spin" /> Suivant
            </button>
          </div>
        </div>

        <!-- Étape 2 -->
        <div v-if="importStep === 2" class="space-y-4">
          <div class="flex items-center justify-between">
            <p class="text-sm text-gray-600">{{ importRowCount }} ligne(s) détectée(s)</p>
            <div class="flex gap-3">
              <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="radio" v-model="importQtyMode" value="increment" class="text-blue-600" /> Additionner
              </label>
              <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="radio" v-model="importQtyMode" value="replace" class="text-blue-600" /> Remplacer
              </label>
            </div>
          </div>
          <div class="border rounded-xl overflow-hidden">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 border-b">
                <tr>
                  <th class="text-left px-4 py-2.5 font-medium text-gray-600">Colonne CSV</th>
                  <th class="text-left px-4 py-2.5 font-medium text-gray-600">Champ destination</th>
                </tr>
              </thead>
              <tbody class="divide-y">
                <tr v-for="header in importHeaders" :key="header">
                  <td class="px-4 py-2.5 font-mono text-xs text-gray-700">{{ header }}</td>
                  <td class="px-4 py-2.5">
                    <select v-model="importMapping[header]"
                      class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                      <option v-for="f in csvFields" :key="f.value" :value="f.value">{{ f.label }}</option>
                    </select>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="importPreview.length > 0">
            <p class="text-sm font-medium text-gray-700 mb-2">Aperçu (5 premières lignes)</p>
            <div class="overflow-x-auto rounded-xl border">
              <table class="text-xs w-full">
                <thead class="bg-gray-50 border-b">
                  <tr>
                    <th v-for="h in importHeaders" :key="h" class="px-3 py-2 text-left font-medium text-gray-600 whitespace-nowrap">{{ h }}</th>
                  </tr>
                </thead>
                <tbody class="divide-y">
                  <tr v-for="(row, i) in importPreview" :key="i" class="hover:bg-gray-50">
                    <td v-for="h in importHeaders" :key="h" class="px-3 py-2 text-gray-600 whitespace-nowrap max-w-32 truncate">{{ row[h] }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <p v-if="importError" class="text-red-500 text-sm p-3 bg-red-50 rounded-lg">{{ importError }}</p>
          <div class="flex justify-between gap-3">
            <button @click="importStep = 1" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">← Retour</button>
            <button @click="processImport" :disabled="importLoading"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
              <ArrowPathIcon v-if="importLoading" class="w-4 h-4 animate-spin" /> Importer
            </button>
          </div>
        </div>

        <!-- Étape 3 -->
        <div v-if="importStep === 3 && importResults" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="p-4 bg-green-50 rounded-xl border border-green-200 text-center">
              <p class="text-3xl font-bold text-green-700">{{ importResults.created }}</p>
              <p class="text-sm text-green-600 mt-1">Créé(s)</p>
            </div>
            <div class="p-4 bg-blue-50 rounded-xl border border-blue-200 text-center">
              <p class="text-3xl font-bold text-blue-700">{{ importResults.updated }}</p>
              <p class="text-sm text-blue-600 mt-1">Mis à jour</p>
            </div>
          </div>
          <div v-if="importResults.errors.length > 0" class="p-4 bg-red-50 rounded-xl border border-red-200">
            <p class="text-sm font-semibold text-red-700 mb-2 flex items-center gap-1.5">
              <ExclamationTriangleIcon class="w-4 h-4" /> {{ importResults.errors.length }} erreur(s)
            </p>
            <ul class="space-y-1">
              <li v-for="e in importResults.errors" :key="e" class="text-xs text-red-600">{{ e }}</li>
            </ul>
          </div>
          <button @click="showImport = false"
            class="w-full py-2.5 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-gray-800">
            Fermer
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>
