<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import CameraCapture from '@/Components/CameraCapture.vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import { ref, reactive, computed, watch, onMounted, nextTick } from 'vue'
import axios from 'axios'
import {
  PlusIcon, PencilIcon, TrashIcon, CubeTransparentIcon, PhotoIcon, Squares2X2Icon, MagnifyingGlassIcon,
  DocumentArrowUpIcon, DocumentIcon, XMarkIcon, ListBulletIcon, CheckIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  models: Array,
  categories: Array,
  manufacturers: Array,
  suppliers: { type: Array, default: () => [] },
})

// ─── Recherche ───────────────────────────────────────────────────────────────
const searchQuery = ref('')
const focusedModelId = ref(null)   // quand arrivé depuis ?highlight=id : n'affiche que ce modèle

const filteredModels = computed(() => {
  // Mode focus : un seul modèle affiché
  if (focusedModelId.value !== null) {
    return props.models.filter(m => m.id === focusedModelId.value)
  }
  const q = searchQuery.value.trim().toLowerCase()
  if (!q) return props.models
  return props.models.filter(m =>
    m.name.toLowerCase().includes(q) ||
    (m.manufacturer ?? '').toLowerCase().includes(q) ||
    (m.category?.name ?? '').toLowerCase().includes(q)
  )
})

// Grouping toggle
const groupByModel = ref(false)

// ─── Create ────────────────────────────────────────────────────────────────
const showCreateModal = ref(false)
const createForm = useForm({
  name: '',
  manufacturer: '',
  manufacturer_id: '',
  category_id: '',
  supplier_id: '',
  description: '',
  image: null,
  quantity: 0,
  purchase_cost: '',
  purchase_date: '',
})

function submitCreate() {
  createForm.post(route('models.store'), {
    forceFormData: true,
    onSuccess: () => {
      showCreateModal.value = false
      createForm.reset()
      createImagePreview.value = null
    },
  })
}

// ─── Edit ───────────────────────────────────────────────────────────────────
const showEditModal = ref(false)
const editingModel = ref(null)
const editForm = useForm({
  name: '',
  manufacturer: '',
  manufacturer_id: '',
  category_id: '',
  supplier_id: '',
  description: '',
  image: null,
  purchase_cost: '',
  purchase_date: '',
  _method: 'PUT',
})

function openEdit(model) {
  resetQuickCreate()
  editingModel.value = model
  editForm.name            = model.name
  editForm.manufacturer    = model.manufacturer ?? ''
  editForm.manufacturer_id = model.manufacturer_id ?? ''
  editForm.category_id     = model.category_id ?? ''
  editForm.supplier_id     = model.supplier_id ?? ''
  editForm.description     = model.description ?? ''
  editForm.purchase_cost   = model.purchase_cost ?? ''
  editForm.purchase_date   = model.purchase_date ?? ''
  editForm.image           = null
  editImagePreview.value   = null
  showEditModal.value = true
}

function submitEdit() {
  editForm.post(route('models.update', editingModel.value.id), {
    forceFormData: true,
    onSuccess: () => { showEditModal.value = false },
  })
}

// ─── Add assets (increment stock) ──────────────────────────────────────────
const showAddAssetsModal = ref(false)
const addAssetsTarget = ref(null)
const addAssetsForm = useForm({ quantity: 1 })

function openAddAssets(model) {
  addAssetsTarget.value = model
  addAssetsForm.quantity = 1
  showAddAssetsModal.value = true
}

function submitAddAssets() {
  addAssetsForm.post(route('models.add-assets', addAssetsTarget.value.id), {
    onSuccess: () => { showAddAssetsModal.value = false },
  })
}

// ─── Delete ─────────────────────────────────────────────────────────────────
const deleteForm = useForm({})
function deleteModel(model) {
  if (confirm(`Supprimer le modèle "${model.name}" ? Les équipements liés ne seront pas supprimés.`)) {
    deleteForm.delete(route('models.destroy', model.id))
  }
}

// ─── Duplicate ──────────────────────────────────────────────────────────────
function duplicateModel(model) {
  if (confirm(`Dupliquer le modèle "${model.name}" ? Une copie sera créée sans les équipements liés.`)) {
    router.post(route('models.duplicate', model.id))
  }
}

// ─── Bulk select ─────────────────────────────────────────────────────────────
const selectedIds  = ref([])
const selectAll    = ref(false)

function toggleSelectAll() {
  if (selectAll.value) {
    selectedIds.value = filteredModels.value.map(m => m.id)
  } else {
    selectedIds.value = []
  }
}

watch(filteredModels, () => {
  // Vider la sélection dès qu'un filtre change
  selectedIds.value = []
  selectAll.value   = false
})

watch(selectedIds, (val) => {
  selectAll.value = val.length > 0 && val.length === filteredModels.value.length
})

// ─── Bulk edit ───────────────────────────────────────────────────────────────
const showBulkEditModal = ref(false)
// '' = ne pas modifier, '0' = effacer (set null), id = changer
const bulkForm = ref({
  manufacturer_id: '',
  category_id:     '',
  supplier_id:     '',
  purchase_date:   '',
  purchase_cost:   '',
})
const bulkProcessing = ref(false)

function openBulkEdit() {
  bulkForm.value = { manufacturer_id: '', category_id: '', supplier_id: '', purchase_date: '', purchase_cost: '' }
  showBulkEditModal.value = true
}

// Champs qui ont été explicitement modifiés dans le formulaire bulk
const bulkChangedFields = computed(() => {
  const f = bulkForm.value
  const changed = []
  if (f.manufacturer_id !== '') changed.push('Fabricant')
  if (f.category_id     !== '') changed.push('Catégorie')
  if (f.supplier_id     !== '') changed.push('Fournisseur')
  if (f.purchase_date   !== '') changed.push('Date d\'achat')
  if (f.purchase_cost   !== '') changed.push('Coût d\'achat')
  return changed
})

function submitBulkEdit() {
  if (bulkChangedFields.value.length === 0) return
  bulkProcessing.value = true

  const payload = { ids: selectedIds.value }
  const f = bulkForm.value
  // N'envoyer que les champs touchés ; '0' → null côté serveur
  if (f.manufacturer_id !== '') payload.manufacturer_id = f.manufacturer_id === '0' ? null : f.manufacturer_id
  if (f.category_id     !== '') payload.category_id     = f.category_id     === '0' ? null : f.category_id
  if (f.supplier_id     !== '') payload.supplier_id     = f.supplier_id     === '0' ? null : f.supplier_id
  if (f.purchase_date   !== '') payload.purchase_date   = f.purchase_date   || null
  if (f.purchase_cost   !== '') payload.purchase_cost   = f.purchase_cost   || null

  router.post(route('models.bulk-update'), payload, {
    onSuccess: () => {
      showBulkEditModal.value = false
      selectedIds.value = []
      selectAll.value = false
    },
    onFinish: () => { bulkProcessing.value = false },
  })
}

// ─── Listes réactives locales (enrichies par création rapide sans rechargement) ─
const localManufacturers = ref([...props.manufacturers])
const localCategories    = ref([...props.categories])
const localSuppliers     = ref([...props.suppliers])

// État de la création rapide inline pour chaque type
const quickCreate = reactive({
  manufacturer: { open: false, name: '', loading: false, error: '' },
  category:     { open: false, name: '', loading: false, error: '' },
  supplier:     { open: false, name: '', loading: false, error: '' },
})

function resetQuickCreate() {
  for (const k of Object.keys(quickCreate)) {
    quickCreate[k].open    = false
    quickCreate[k].name    = ''
    quickCreate[k].error   = ''
    quickCreate[k].loading = false
  }
}

async function submitQuickCreate(type, targetForm) {
  const state = quickCreate[type]
  if (!state.name.trim()) return
  state.loading = true
  state.error   = ''
  const routeMap = {
    manufacturer: route('manufacturers.quick-store'),
    category:     route('categories.quick-store'),
    supplier:     route('suppliers.quick-store'),
  }
  try {
    const { data } = await axios.post(routeMap[type], { name: state.name.trim() }, {
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    })
    if (type === 'manufacturer') {
      localManufacturers.value.push(data)
      targetForm.manufacturer_id = data.id
    } else if (type === 'category') {
      localCategories.value.push(data)
      targetForm.category_id = data.id
    } else if (type === 'supplier') {
      localSuppliers.value.push(data)
      targetForm.supplier_id = data.id
    }
    state.open  = false
    state.name  = ''
  } catch (err) {
    state.error = err.response?.data?.errors?.name?.[0]
      ?? err.response?.data?.message
      ?? 'Erreur lors de la création.'
  } finally {
    state.loading = false
  }
}

// ─── Focus/highlight depuis ?highlight=id (navigation depuis une fiche équipement) ──
const highlightedModelId = ref(null)

onMounted(() => {
  const params = new URLSearchParams(window.location.search)
  const id = params.get('highlight')
  if (!id) return
  const numId = Number(id)
  focusedModelId.value   = numId
  highlightedModelId.value = numId
  nextTick(() => {
    const el = document.getElementById(`model-row-${id}`)
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    setTimeout(() => { highlightedModelId.value = null }, 3000)
  })
})

// ─── File inputs + caméra ───────────────────────────────────────────────────
const createImagePreview = ref(null)
const editImagePreview   = ref(null)

function onCreateImageChange(e) {
  const file = e.target.files[0] ?? null
  createForm.image = file
  createImagePreview.value = file ? URL.createObjectURL(file) : null
}
function onCreateCameraCapture(file) {
  createForm.image = file
  createImagePreview.value = URL.createObjectURL(file)
}

function onEditImageChange(e) {
  const file = e.target.files[0] ?? null
  editForm.image = file
  editImagePreview.value = file ? URL.createObjectURL(file) : null
}
function onEditCameraCapture(file) {
  editForm.image = file
  editImagePreview.value = URL.createObjectURL(file)
}

// ─── Documents PDF ───────────────────────────────────────────────────────────
const docUploadFile   = ref(null)
const docUploadName   = ref('')
const docUploading    = ref(false)
const docUploadError  = ref('')
const docDeleteId     = ref(null)

function onDocFileChange(e) {
  docUploadFile.value  = e.target.files[0] ?? null
  docUploadError.value = ''
  if (docUploadFile.value && !docUploadName.value) {
    // Pre-fill name from file name without extension
    docUploadName.value = docUploadFile.value.name.replace(/\.[^.]+$/, '')
  }
}

async function submitDocUpload() {
  if (!docUploadFile.value || !editingModel.value) return
  docUploading.value   = true
  docUploadError.value = ''

  const fd = new FormData()
  fd.append('file', docUploadFile.value)
  fd.append('name', docUploadName.value || docUploadFile.value.name.replace(/\.[^.]+$/, ''))

  try {
    await axios.post(route('models.documents.upload', editingModel.value.id), fd, {
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    })
    docUploadFile.value  = null
    docUploadName.value  = ''
    // Refresh page data
    router.reload({ only: ['models'] })
  } catch (err) {
    docUploadError.value = err.response?.data?.message ?? 'Erreur lors de l\'upload.'
  } finally {
    docUploading.value = false
  }
}

async function deleteDocument(doc) {
  if (!editingModel.value) return
  if (!confirm(`Supprimer le document "${doc.name}" ?`)) return
  docDeleteId.value = doc.id
  try {
    await axios.delete(route('models.documents.delete', { model: editingModel.value.id, document: doc.id }), {
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    })
    router.reload({ only: ['models'] })
  } finally {
    docDeleteId.value = null
  }
}

// Computed: documents du modèle en cours d'édition (réactif après reload)
const editingModelDocs = computed(() => {
  if (!editingModel.value) return []
  const fresh = props.models.find(m => m.id === editingModel.value.id)
  return fresh?.documents ?? []
})
</script>

<template>
  <AppLayout>
    <div>
      <!-- Header -->
      <div class="flex items-center justify-between mb-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Modèles d'équipements</h1>
          <p class="text-gray-500 text-sm mt-1">
            {{ filteredModels.length }}<template v-if="searchQuery || focusedModelId"> / {{ models.length }}</template> modèle(s)
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button @click="groupByModel = !groupByModel"
                  :class="['flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm border transition',
                    groupByModel ? 'bg-blue-100 text-blue-700 border-blue-300' : 'border-gray-300 text-gray-600 hover:bg-gray-50']">
            <Squares2X2Icon class="w-4 h-4" />
            {{ groupByModel ? 'Vue groupée active' : 'Grouper par modèle' }}
          </button>
          <a :href="route('reports.export-csv-models')"
             class="flex items-center gap-2 border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm font-medium transition">
            ⬇ CSV
          </a>
          <button @click="resetQuickCreate(); showCreateModal = true"
                  class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            <PlusIcon class="w-4 h-4" />
            Nouveau modèle
          </button>
        </div>
      </div>

      <!-- Bandeau focus modèle (depuis navigation fiche équipement) -->
      <div v-if="focusedModelId" class="mb-4 flex items-center gap-3 bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3 text-sm text-indigo-800">
        <span>🔍</span>
        <span class="flex-1">
          Affichage filtré — modèle : <strong>{{ filteredModels[0]?.name }}</strong>
        </span>
        <button @click="focusedModelId = null"
                class="inline-flex items-center gap-1 text-xs text-indigo-600 border border-indigo-300 bg-white px-2.5 py-1 rounded-lg hover:bg-indigo-50 transition font-medium">
          <XMarkIcon class="w-3.5 h-3.5" /> Afficher tous les modèles
        </button>
      </div>

      <!-- Barre de recherche -->
      <div class="mb-5 relative">
        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
        <input v-model="searchQuery" type="text"
               placeholder="Rechercher par nom, fabricant, catégorie…"
               class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white" />
        <button v-if="searchQuery" @click="searchQuery = ''"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-lg leading-none">
          &times;
        </button>
      </div>

      <!-- ── Bandeau sélection / actions groupées ── -->
      <Transition name="slide-down">
        <div v-if="selectedIds.length > 0"
             class="mb-4 flex flex-wrap items-center gap-3 px-4 py-3 bg-indigo-50 border border-indigo-200 rounded-xl">
          <span class="text-sm font-semibold text-indigo-800">
            {{ selectedIds.length }} modèle(s) sélectionné(s)
          </span>
          <div class="flex items-center gap-2 flex-1 flex-wrap">
            <button @click="openBulkEdit"
                    class="flex items-center gap-1.5 px-4 py-1.5 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition font-medium">
              <PencilIcon class="w-4 h-4" />
              Modifier en lot
            </button>
            <button @click="selectedIds = []; selectAll = false"
                    class="text-xs text-gray-500 hover:text-gray-700 underline">
              Désélectionner
            </button>
          </div>
        </div>
      </Transition>

      <!-- Info box -->
      <div class="mb-5 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-sm text-blue-800">
        <strong>💡 Structure :</strong> Créez d'abord un <strong>modèle</strong> (ex : "MacBook Pro 14"), puis ajoutez la quantité d'équipements physiques.
        Chaque équipement créé hérite du modèle, de la catégorie et de l'image.
        Le bouton <strong>➕ Stock</strong> permet d'incrémenter le stock d'un modèle existant.
      </div>

      <!-- ── Grouped / card view ── -->
      <div v-if="groupByModel" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div v-for="model in filteredModels" :key="model.id"
             :id="`model-row-${model.id}`"
             :class="['bg-white rounded-xl border shadow-sm overflow-hidden transition',
               highlightedModelId === model.id ? 'ring-2 ring-indigo-400 shadow-indigo-100' : 'hover:shadow-md']">
          <!-- Image banner -->
          <div class="h-28 bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center relative overflow-hidden">
            <img v-if="model.image_url" :src="model.image_url" class="w-full h-full object-cover" :alt="model.name" />
            <CubeTransparentIcon v-else class="w-12 h-12 text-gray-300" />
            <!-- Stock badge -->
            <div class="absolute top-2 right-2 flex items-center gap-1">
              <span class="bg-blue-600 text-white text-xs font-bold px-2 py-1 rounded-full">
                {{ model.assets_count }} éq.
              </span>
              <button @click.stop="openAddAssets(model)"
                      class="bg-green-500 hover:bg-green-600 text-white text-xs font-bold px-2 py-1 rounded-full transition"
                      title="Ajouter des équipements">
                ➕
              </button>
            </div>
          </div>
          <!-- Info -->
          <div class="p-4">
            <h3 class="font-semibold text-gray-900">{{ model.name }}</h3>
            <p v-if="model.manufacturer" class="text-xs text-gray-500 mt-0.5">{{ model.manufacturer }}</p>
            <p v-if="model.category" class="text-xs text-blue-600 mt-0.5">{{ model.category.name }}</p>
            <p v-if="model.description" class="text-xs text-gray-400 mt-1 truncate">{{ model.description }}</p>
            <div class="flex items-center gap-1.5 mt-3 flex-wrap">
              <Link :href="route('assets.index', { model: model.id })"
                    class="flex-1 text-center text-xs text-indigo-700 border border-indigo-300 bg-indigo-50 py-1.5 rounded-lg hover:bg-indigo-100 transition flex items-center justify-center gap-1">
                <ListBulletIcon class="w-3 h-3" /> Équipements
              </Link>
              <button @click="openAddAssets(model)"
                      class="flex-1 text-center text-xs text-green-700 border border-green-300 bg-green-50 py-1.5 rounded-lg hover:bg-green-100 transition flex items-center justify-center gap-1">
                ➕ Stock
              </button>
              <button @click="openEdit(model)"
                      class="flex-1 text-center text-xs text-blue-600 border border-blue-200 py-1.5 rounded-lg hover:bg-blue-50 transition flex items-center justify-center gap-1">
                <PencilIcon class="w-3 h-3" /> Modifier
              </button>
              <button @click="duplicateModel(model)"
                      class="flex-1 text-center text-xs text-purple-600 border border-purple-200 py-1.5 rounded-lg hover:bg-purple-50 transition flex items-center justify-center gap-1">
                📋 Dupliquer
              </button>
              <button @click="deleteModel(model)"
                      class="flex-1 text-center text-xs text-red-500 border border-red-200 py-1.5 rounded-lg hover:bg-red-50 transition flex items-center justify-center gap-1">
                <TrashIcon class="w-3 h-3" /> Supprimer
              </button>
            </div>
          </div>
        </div>

        <div v-if="filteredModels.length === 0" class="col-span-full text-center text-gray-400 py-12">
          <CubeTransparentIcon class="w-12 h-12 mx-auto mb-2 opacity-30" />
          <p class="font-medium">Aucun modèle enregistré</p>
        </div>
      </div>

      <!-- ── Table view ── -->
      <div v-else class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-xs text-gray-500 uppercase bg-gray-50 border-b">
              <th class="px-3 py-3 w-10">
                <input type="checkbox" v-model="selectAll" @change="toggleSelectAll"
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
              </th>
              <th class="px-4 py-3 text-left">Modèle</th>
              <th class="px-4 py-3 text-left hidden md:table-cell">Fabricant</th>
              <th class="px-4 py-3 text-left hidden lg:table-cell">Catégorie</th>
              <th class="px-4 py-3 text-center">Stock</th>
              <th class="px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="model in filteredModels" :key="model.id"
                :id="`model-row-${model.id}`"
                :class="['border-b transition',
                  selectedIds.includes(model.id) ? 'bg-indigo-50 hover:bg-indigo-50' :
                  highlightedModelId === model.id ? 'bg-indigo-50 ring-2 ring-inset ring-indigo-300' : 'hover:bg-gray-50']">
              <!-- Checkbox -->
              <td class="px-3 py-3">
                <input type="checkbox" :value="model.id" v-model="selectedIds"
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
              </td>
              <!-- Modèle -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0 overflow-hidden">
                    <img v-if="model.image_url" :src="model.image_url" class="w-10 h-10 object-cover rounded-lg" :alt="model.name" />
                    <CubeTransparentIcon v-else class="w-5 h-5 text-gray-400" />
                  </div>
                  <div>
                    <p class="font-medium text-gray-900">{{ model.name }}</p>
                    <p v-if="model.description" class="text-xs text-gray-500 truncate max-w-48">{{ model.description }}</p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-gray-600 hidden md:table-cell">{{ model.manufacturer ?? '—' }}</td>
              <td class="px-4 py-3 text-gray-600 hidden lg:table-cell">{{ model.category?.name ?? '—' }}</td>

              <!-- Stock avec bouton + inline -->
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1.5">
                  <span class="inline-flex items-center justify-center w-8 h-8 bg-blue-100 text-blue-700 rounded-full text-sm font-semibold">
                    {{ model.assets_count }}
                  </span>
                  <button @click="openAddAssets(model)"
                          class="inline-flex items-center justify-center w-7 h-7 bg-green-100 hover:bg-green-200 text-green-700 rounded-full text-sm transition"
                          title="Ajouter des équipements à ce modèle">
                    +
                  </button>
                </div>
              </td>

              <!-- Actions -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-2 flex-wrap">
                  <Link :href="route('assets.index', { model: model.id })"
                        class="inline-flex items-center gap-1 text-xs text-indigo-600 bg-indigo-50 border border-indigo-200 px-2 py-1 rounded-md hover:bg-indigo-100 transition font-medium">
                    <ListBulletIcon class="w-3.5 h-3.5" /> Équipements
                  </Link>
                  <button @click="openAddAssets(model)"
                          class="inline-flex items-center gap-1 text-xs text-green-700 bg-green-50 border border-green-200 px-2 py-1 rounded-md hover:bg-green-100 transition font-medium">
                    ➕ Stock
                  </button>
                  <button @click="openEdit(model)"
                          class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                    <PencilIcon class="w-3.5 h-3.5" /> Modifier
                  </button>
                  <button @click="duplicateModel(model)"
                          class="inline-flex items-center gap-1 text-xs text-purple-600 hover:underline">
                    📋 Dupliquer
                  </button>
                  <button @click="deleteModel(model)"
                          class="inline-flex items-center gap-1 text-xs text-red-500 hover:underline">
                    <TrashIcon class="w-3.5 h-3.5" /> Supprimer
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="filteredModels.length === 0">
              <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                <CubeTransparentIcon class="w-12 h-12 mx-auto mb-2 opacity-30" />
                <p class="font-medium">Aucun modèle enregistré</p>
                <p class="text-sm mt-1">Créez votre premier modèle pour organiser vos équipements.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════
         Modal — Créer un modèle
    ══════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between px-6 py-4 border-b sticky top-0 bg-white z-10">
            <h2 class="text-lg font-semibold text-gray-900">Nouveau modèle d'équipement</h2>
            <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
          </div>
          <form @submit.prevent="submitCreate" class="px-6 py-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nom du modèle <span class="text-red-500">*</span></label>
              <input v-model="createForm.name" type="text" required placeholder="ex : MacBook Pro 14, Canon EOS R50…"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              <p v-if="createForm.errors.name" class="text-red-500 text-xs mt-1">{{ createForm.errors.name }}</p>
            </div>
            <!-- Fabricant -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="text-sm font-medium text-gray-700">Fabricant</label>
                <button type="button"
                        @click="quickCreate.manufacturer.open = !quickCreate.manufacturer.open; quickCreate.manufacturer.error = ''"
                        class="flex items-center gap-0.5 text-xs font-medium text-blue-600 hover:text-blue-800 transition">
                  <PlusIcon class="w-3 h-3" />
                  {{ quickCreate.manufacturer.open ? 'Annuler' : 'Nouveau' }}
                </button>
              </div>
              <select v-model="createForm.manufacturer_id"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">— Sans fabricant —</option>
                <option v-for="mfr in localManufacturers" :key="mfr.id" :value="mfr.id">{{ mfr.name }}</option>
              </select>
              <div v-if="quickCreate.manufacturer.open" class="mt-1.5 flex gap-2 items-start">
                <div class="flex-1">
                  <input v-model="quickCreate.manufacturer.name" type="text"
                         placeholder="Nom du fabricant…"
                         @keydown.enter.prevent="submitQuickCreate('manufacturer', createForm)"
                         @keydown.escape="quickCreate.manufacturer.open = false"
                         class="w-full border border-blue-300 bg-blue-50 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none placeholder-blue-300" />
                  <p v-if="quickCreate.manufacturer.error" class="text-xs text-red-500 mt-0.5">{{ quickCreate.manufacturer.error }}</p>
                </div>
                <button type="button"
                        @click="submitQuickCreate('manufacturer', createForm)"
                        :disabled="quickCreate.manufacturer.loading || !quickCreate.manufacturer.name.trim()"
                        class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg font-medium transition disabled:opacity-50 whitespace-nowrap">
                  {{ quickCreate.manufacturer.loading ? '…' : '✓ Créer' }}
                </button>
              </div>
            </div>
            <!-- Fournisseur -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="text-sm font-medium text-gray-700">Fournisseur</label>
                <button type="button"
                        @click="quickCreate.supplier.open = !quickCreate.supplier.open; quickCreate.supplier.error = ''"
                        class="flex items-center gap-0.5 text-xs font-medium text-blue-600 hover:text-blue-800 transition">
                  <PlusIcon class="w-3 h-3" />
                  {{ quickCreate.supplier.open ? 'Annuler' : 'Nouveau' }}
                </button>
              </div>
              <select v-model="createForm.supplier_id"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">— Sans fournisseur —</option>
                <option v-for="s in localSuppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
              <div v-if="quickCreate.supplier.open" class="mt-1.5 flex gap-2 items-start">
                <div class="flex-1">
                  <input v-model="quickCreate.supplier.name" type="text"
                         placeholder="Nom du fournisseur…"
                         @keydown.enter.prevent="submitQuickCreate('supplier', createForm)"
                         @keydown.escape="quickCreate.supplier.open = false"
                         class="w-full border border-blue-300 bg-blue-50 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none placeholder-blue-300" />
                  <p v-if="quickCreate.supplier.error" class="text-xs text-red-500 mt-0.5">{{ quickCreate.supplier.error }}</p>
                </div>
                <button type="button"
                        @click="submitQuickCreate('supplier', createForm)"
                        :disabled="quickCreate.supplier.loading || !quickCreate.supplier.name.trim()"
                        class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg font-medium transition disabled:opacity-50 whitespace-nowrap">
                  {{ quickCreate.supplier.loading ? '…' : '✓ Créer' }}
                </button>
              </div>
            </div>
            <!-- Catégorie -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="text-sm font-medium text-gray-700">Catégorie</label>
                <button type="button"
                        @click="quickCreate.category.open = !quickCreate.category.open; quickCreate.category.error = ''"
                        class="flex items-center gap-0.5 text-xs font-medium text-blue-600 hover:text-blue-800 transition">
                  <PlusIcon class="w-3 h-3" />
                  {{ quickCreate.category.open ? 'Annuler' : 'Nouvelle' }}
                </button>
              </div>
              <select v-model="createForm.category_id"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">— Sans catégorie —</option>
                <option v-for="cat in localCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
              <div v-if="quickCreate.category.open" class="mt-1.5 flex gap-2 items-start">
                <div class="flex-1">
                  <input v-model="quickCreate.category.name" type="text"
                         placeholder="Nom de la catégorie…"
                         @keydown.enter.prevent="submitQuickCreate('category', createForm)"
                         @keydown.escape="quickCreate.category.open = false"
                         class="w-full border border-blue-300 bg-blue-50 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none placeholder-blue-300" />
                  <p v-if="quickCreate.category.error" class="text-xs text-red-500 mt-0.5">{{ quickCreate.category.error }}</p>
                </div>
                <button type="button"
                        @click="submitQuickCreate('category', createForm)"
                        :disabled="quickCreate.category.loading || !quickCreate.category.name.trim()"
                        class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg font-medium transition disabled:opacity-50 whitespace-nowrap">
                  {{ quickCreate.category.loading ? '…' : '✓ Créer' }}
                </button>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="createForm.description" rows="2" placeholder="Caractéristiques, notes…"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
            </div>
            <!-- Achat — propagé à tous les équipements créés -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date d'achat</label>
                <input v-model="createForm.purchase_date" type="date"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Coût d'achat (€)</label>
                <input v-model="createForm.purchase_cost" type="number" step="0.01" min="0" placeholder="0.00"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Image</label>
              <div class="flex items-start gap-3">
                <!-- Preview -->
                <div v-if="createImagePreview" class="relative flex-shrink-0">
                  <img :src="createImagePreview" class="w-20 h-20 object-cover rounded-xl border border-gray-200 shadow-sm" />
                  <button type="button" @click="createForm.image = null; createImagePreview = null"
                          class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow transition text-xs leading-none">
                    &times;
                  </button>
                </div>
                <!-- Upload + Camera -->
                <div class="flex-1 flex flex-col gap-2">
                  <label class="flex items-center gap-2 cursor-pointer border-2 border-dashed border-gray-300 rounded-lg px-4 py-2.5 hover:border-blue-400 transition">
                    <PhotoIcon class="w-5 h-5 text-gray-400 flex-shrink-0" />
                    <span class="text-sm text-gray-500 truncate">{{ createForm.image ? createForm.image.name : 'Choisir une image…' }}</span>
                    <input type="file" accept="image/*" class="hidden" @change="onCreateImageChange" />
                  </label>
                  <CameraCapture @captured="onCreateCameraCapture" />
                </div>
              </div>
            </div>
            <!-- Quantity -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
              <label class="block text-sm font-semibold text-green-800 mb-1">
                📦 Quantité d'équipements à créer
              </label>
              <p class="text-xs text-green-600 mb-2">Les équipements seront créés automatiquement dans l'inventaire avec des tags uniques.</p>
              <input v-model.number="createForm.quantity" type="number" min="0" max="500"
                     class="w-full border border-green-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none bg-white" />
              <p v-if="createForm.quantity > 0" class="text-xs text-green-700 mt-1.5 font-medium">
                ✓ {{ createForm.quantity }} équipement(s) seront créés et liés à ce modèle.
              </p>
              <p v-else class="text-xs text-gray-500 mt-1.5">Laisser à 0 pour créer le modèle sans équipements.</p>
            </div>
            <div class="flex justify-end gap-3 pt-2">
              <button type="button" @click="showCreateModal = false"
                      class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Annuler
              </button>
              <button type="submit" :disabled="createForm.processing"
                      class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-60">
                {{ createForm.processing ? 'Création…' : 'Créer le modèle' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ══════════════════════════════════════════════
         Modal — Ajouter des équipements (incrémenter stock)
    ══════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showAddAssetsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h2 class="text-lg font-semibold text-gray-900">➕ Ajouter des équipements</h2>
            <button @click="showAddAssetsModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
          </div>
          <div class="px-6 py-5">
            <div class="flex items-center gap-3 mb-4 p-3 bg-gray-50 rounded-xl border">
              <CubeTransparentIcon class="w-8 h-8 text-gray-400 flex-shrink-0" />
              <div>
                <p class="font-semibold text-gray-900 text-sm">{{ addAssetsTarget?.name }}</p>
                <p class="text-xs text-gray-500">
                  {{ addAssetsTarget?.manufacturer ?? '' }}
                  <span class="ml-1 text-blue-600 font-medium">{{ addAssetsTarget?.assets_count }} équipement(s) actuellement</span>
                </p>
              </div>
            </div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Quantité à ajouter au stock <span class="text-red-500">*</span>
            </label>
            <input v-model.number="addAssetsForm.quantity" type="number" min="1" max="500"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 outline-none" />
            <p v-if="addAssetsForm.errors.quantity" class="text-red-500 text-xs mt-1">{{ addAssetsForm.errors.quantity }}</p>
            <p v-if="addAssetsForm.quantity >= 1" class="text-xs text-green-700 mt-2 font-medium">
              ✓ {{ addAssetsForm.quantity }} nouvel(aux) équipement(s) seront créés avec des tags EQ-XXXXX générés automatiquement,
              numérotés à partir de #{{ (addAssetsTarget?.assets_count ?? 0) + 1 }}.
            </p>
          </div>
          <div class="flex justify-end gap-3 px-6 pb-5">
            <button type="button" @click="showAddAssetsModal = false"
                    class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
              Annuler
            </button>
            <button @click="submitAddAssets"
                    :disabled="addAssetsForm.processing || !addAssetsForm.quantity || addAssetsForm.quantity < 1"
                    class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-60">
              {{ addAssetsForm.processing ? 'Création…' : `Ajouter ${addAssetsForm.quantity || 0} équipement(s)` }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ══════════════════════════════════════════════
         Modal — Modifier un modèle
    ══════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between px-6 py-4 border-b sticky top-0 bg-white z-10">
            <h2 class="text-lg font-semibold text-gray-900">Modifier : {{ editingModel?.name }}</h2>
            <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
          </div>
          <form @submit.prevent="submitEdit" class="px-6 py-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
              <input v-model="editForm.name" type="text" required
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              <p v-if="editForm.errors.name" class="text-red-500 text-xs mt-1">{{ editForm.errors.name }}</p>
            </div>
            <!-- Fabricant -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="text-sm font-medium text-gray-700">Fabricant</label>
                <button type="button"
                        @click="quickCreate.manufacturer.open = !quickCreate.manufacturer.open; quickCreate.manufacturer.error = ''"
                        class="flex items-center gap-0.5 text-xs font-medium text-blue-600 hover:text-blue-800 transition">
                  <PlusIcon class="w-3 h-3" />
                  {{ quickCreate.manufacturer.open ? 'Annuler' : 'Nouveau' }}
                </button>
              </div>
              <select v-model="editForm.manufacturer_id"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">— Sans fabricant —</option>
                <option v-for="mfr in localManufacturers" :key="mfr.id" :value="mfr.id">{{ mfr.name }}</option>
              </select>
              <div v-if="quickCreate.manufacturer.open" class="mt-1.5 flex gap-2 items-start">
                <div class="flex-1">
                  <input v-model="quickCreate.manufacturer.name" type="text"
                         placeholder="Nom du fabricant…"
                         @keydown.enter.prevent="submitQuickCreate('manufacturer', editForm)"
                         @keydown.escape="quickCreate.manufacturer.open = false"
                         class="w-full border border-blue-300 bg-blue-50 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none placeholder-blue-300" />
                  <p v-if="quickCreate.manufacturer.error" class="text-xs text-red-500 mt-0.5">{{ quickCreate.manufacturer.error }}</p>
                </div>
                <button type="button"
                        @click="submitQuickCreate('manufacturer', editForm)"
                        :disabled="quickCreate.manufacturer.loading || !quickCreate.manufacturer.name.trim()"
                        class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg font-medium transition disabled:opacity-50 whitespace-nowrap">
                  {{ quickCreate.manufacturer.loading ? '…' : '✓ Créer' }}
                </button>
              </div>
            </div>
            <!-- Fournisseur -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="text-sm font-medium text-gray-700">Fournisseur</label>
                <button type="button"
                        @click="quickCreate.supplier.open = !quickCreate.supplier.open; quickCreate.supplier.error = ''"
                        class="flex items-center gap-0.5 text-xs font-medium text-blue-600 hover:text-blue-800 transition">
                  <PlusIcon class="w-3 h-3" />
                  {{ quickCreate.supplier.open ? 'Annuler' : 'Nouveau' }}
                </button>
              </div>
              <select v-model="editForm.supplier_id"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">— Sans fournisseur —</option>
                <option v-for="s in localSuppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
              <div v-if="quickCreate.supplier.open" class="mt-1.5 flex gap-2 items-start">
                <div class="flex-1">
                  <input v-model="quickCreate.supplier.name" type="text"
                         placeholder="Nom du fournisseur…"
                         @keydown.enter.prevent="submitQuickCreate('supplier', editForm)"
                         @keydown.escape="quickCreate.supplier.open = false"
                         class="w-full border border-blue-300 bg-blue-50 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none placeholder-blue-300" />
                  <p v-if="quickCreate.supplier.error" class="text-xs text-red-500 mt-0.5">{{ quickCreate.supplier.error }}</p>
                </div>
                <button type="button"
                        @click="submitQuickCreate('supplier', editForm)"
                        :disabled="quickCreate.supplier.loading || !quickCreate.supplier.name.trim()"
                        class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg font-medium transition disabled:opacity-50 whitespace-nowrap">
                  {{ quickCreate.supplier.loading ? '…' : '✓ Créer' }}
                </button>
              </div>
            </div>
            <!-- Catégorie -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="text-sm font-medium text-gray-700">Catégorie</label>
                <button type="button"
                        @click="quickCreate.category.open = !quickCreate.category.open; quickCreate.category.error = ''"
                        class="flex items-center gap-0.5 text-xs font-medium text-blue-600 hover:text-blue-800 transition">
                  <PlusIcon class="w-3 h-3" />
                  {{ quickCreate.category.open ? 'Annuler' : 'Nouvelle' }}
                </button>
              </div>
              <select v-model="editForm.category_id"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">— Sans catégorie —</option>
                <option v-for="cat in localCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
              <div v-if="quickCreate.category.open" class="mt-1.5 flex gap-2 items-start">
                <div class="flex-1">
                  <input v-model="quickCreate.category.name" type="text"
                         placeholder="Nom de la catégorie…"
                         @keydown.enter.prevent="submitQuickCreate('category', editForm)"
                         @keydown.escape="quickCreate.category.open = false"
                         class="w-full border border-blue-300 bg-blue-50 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none placeholder-blue-300" />
                  <p v-if="quickCreate.category.error" class="text-xs text-red-500 mt-0.5">{{ quickCreate.category.error }}</p>
                </div>
                <button type="button"
                        @click="submitQuickCreate('category', editForm)"
                        :disabled="quickCreate.category.loading || !quickCreate.category.name.trim()"
                        class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg font-medium transition disabled:opacity-50 whitespace-nowrap">
                  {{ quickCreate.category.loading ? '…' : '✓ Créer' }}
                </button>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="editForm.description" rows="3"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
            </div>
            <!-- Achat — propagé à tous les équipements liés lors de la sauvegarde -->
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 space-y-3">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-sm font-semibold text-amber-800">💰 Informations d'achat</span>
                <span class="text-xs text-amber-600">(propagées à tous les équipements liés)</span>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Date d'achat</label>
                  <input v-model="editForm.purchase_date" type="date"
                         class="w-full border border-amber-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 outline-none bg-white" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">Coût d'achat (€)</label>
                  <input v-model="editForm.purchase_cost" type="number" step="0.01" min="0" placeholder="0.00"
                         class="w-full border border-amber-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 outline-none bg-white" />
                </div>
              </div>
              <p class="text-xs text-amber-600">
                ⚠ En sauvegardant, ces valeurs seront écrites sur tous les équipements de ce modèle.
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Image</label>
              <div class="flex items-start gap-3">
                <!-- Preview : nouvelle photo capturée/sélectionnée en priorité, sinon image actuelle -->
                <div v-if="editImagePreview || editingModel?.image_url" class="relative flex-shrink-0">
                  <img :src="editImagePreview ?? editingModel.image_url"
                       class="w-20 h-20 object-cover rounded-xl border border-gray-200 shadow-sm"
                       :alt="editingModel?.name" />
                  <span v-if="!editImagePreview"
                        class="absolute -bottom-1 left-0 right-0 text-center text-xs bg-black/50 text-white py-0.5 rounded-b-xl">
                    Actuelle
                  </span>
                  <button v-if="editImagePreview" type="button"
                          @click="editForm.image = null; editImagePreview = null"
                          class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow transition text-xs leading-none">
                    &times;
                  </button>
                </div>
                <!-- Upload + Camera -->
                <div class="flex-1 flex flex-col gap-2">
                  <p v-if="editingModel?.image_url && !editImagePreview" class="text-xs text-gray-500">
                    Sera propagée à tous les équipements.
                  </p>
                  <label class="flex items-center gap-2 cursor-pointer border-2 border-dashed border-gray-300 rounded-lg px-4 py-2.5 hover:border-blue-400 transition">
                    <PhotoIcon class="w-5 h-5 text-gray-400 flex-shrink-0" />
                    <span class="text-sm text-gray-500 truncate">{{ editForm.image ? editForm.image.name : 'Remplacer l\'image…' }}</span>
                    <input type="file" accept="image/*" class="hidden" @change="onEditImageChange" />
                  </label>
                  <CameraCapture @captured="onEditCameraCapture" />
                </div>
              </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
              <button type="button" @click="showEditModal = false"
                      class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Annuler
              </button>
              <button type="submit" :disabled="editForm.processing"
                      class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-60">
                {{ editForm.processing ? 'Sauvegarde…' : 'Enregistrer' }}
              </button>
            </div>
          </form>

          <!-- ── Section documents PDF (hors du <form> principal) ── -->
          <div class="px-6 pb-6 border-t mt-2 pt-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
              <DocumentIcon class="w-4 h-4 text-gray-500" />
              Documents / Notices (PDF)
            </h3>

            <!-- Liste des documents existants -->
            <div v-if="editingModelDocs.length" class="mb-4 space-y-2">
              <div v-for="doc in editingModelDocs" :key="doc.id"
                   class="flex items-center justify-between gap-3 p-3 bg-gray-50 rounded-lg border">
                <div class="flex items-center gap-2 min-w-0">
                  <DocumentIcon class="w-5 h-5 text-red-500 flex-shrink-0" />
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ doc.name }}</p>
                    <p class="text-xs text-gray-400">{{ doc.formatted_size }} — {{ doc.original_name }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <a :href="doc.url" target="_blank"
                     class="text-xs text-blue-600 hover:underline">
                    Ouvrir
                  </a>
                  <button @click="deleteDocument(doc)"
                          :disabled="docDeleteId === doc.id"
                          class="text-red-400 hover:text-red-600 transition disabled:opacity-40">
                    <XMarkIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
            <p v-else class="text-xs text-gray-400 mb-4 italic">Aucun document pour ce modèle.</p>

            <!-- Upload d'un nouveau PDF -->
            <div class="space-y-2">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Nom du document</label>
                <input v-model="docUploadName" type="text" placeholder="ex : Notice utilisateur, Fiche technique…"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <div>
                <label class="flex items-center gap-2 cursor-pointer border-2 border-dashed border-red-200 rounded-lg px-4 py-3 hover:border-red-400 transition">
                  <DocumentArrowUpIcon class="w-5 h-5 text-red-400" />
                  <span class="text-sm text-gray-500">
                    {{ docUploadFile ? docUploadFile.name : 'Choisir un fichier PDF…' }}
                  </span>
                  <input type="file" accept=".pdf,application/pdf" class="hidden" @change="onDocFileChange" />
                </label>
              </div>
              <p v-if="docUploadError" class="text-red-500 text-xs">{{ docUploadError }}</p>
              <button @click="submitDocUpload"
                      :disabled="!docUploadFile || docUploading"
                      class="w-full flex items-center justify-center gap-2 px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                <DocumentArrowUpIcon class="w-4 h-4" />
                {{ docUploading ? 'Envoi en cours…' : 'Ajouter ce document' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
    <!-- ══════════════════════════════════════════════
         Modal — Modifier en lot
    ══════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showBulkEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between px-6 py-4 border-b sticky top-0 bg-white z-10">
            <div>
              <h2 class="text-lg font-semibold text-gray-900">✏️ Modifier en lot</h2>
              <p class="text-xs text-gray-500 mt-0.5">{{ selectedIds.length }} modèle(s) sélectionné(s)</p>
            </div>
            <button @click="showBulkEditModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
          </div>

          <div class="px-6 py-5 space-y-5">

            <!-- Info -->
            <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800">
              <p class="font-semibold mb-1">⚠️ Seuls les champs que vous modifiez seront appliqués.</p>
              <p class="text-xs text-amber-700">Laissez un champ sur « Ne pas modifier » pour le laisser inchangé sur les modèles sélectionnés.</p>
            </div>

            <!-- Fabricant -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Fabricant
                <span v-if="bulkForm.manufacturer_id !== ''" class="ml-1.5 text-xs font-semibold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded">sera modifié</span>
              </label>
              <select v-model="bulkForm.manufacturer_id"
                      class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none"
                      :class="bulkForm.manufacturer_id !== '' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300'">
                <option value="">— Ne pas modifier —</option>
                <option value="0">✕ Aucun (retirer le fabricant)</option>
                <option v-for="mfr in localManufacturers" :key="mfr.id" :value="String(mfr.id)">{{ mfr.name }}</option>
              </select>
            </div>

            <!-- Fournisseur -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Fournisseur
                <span v-if="bulkForm.supplier_id !== ''" class="ml-1.5 text-xs font-semibold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded">sera modifié</span>
              </label>
              <select v-model="bulkForm.supplier_id"
                      class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none"
                      :class="bulkForm.supplier_id !== '' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300'">
                <option value="">— Ne pas modifier —</option>
                <option value="0">✕ Aucun (retirer le fournisseur)</option>
                <option v-for="s in localSuppliers" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
              </select>
            </div>

            <!-- Catégorie -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Catégorie
                <span v-if="bulkForm.category_id !== ''" class="ml-1.5 text-xs font-semibold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded">sera modifié</span>
              </label>
              <select v-model="bulkForm.category_id"
                      class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none"
                      :class="bulkForm.category_id !== '' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300'">
                <option value="">— Ne pas modifier —</option>
                <option value="0">✕ Aucune (retirer la catégorie)</option>
                <option v-for="cat in localCategories" :key="cat.id" :value="String(cat.id)">{{ cat.name }}</option>
              </select>
            </div>

            <!-- Date d'achat + Coût -->
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 space-y-3">
              <p class="text-xs font-semibold text-amber-800 flex items-center gap-1.5">
                💰 Informations d'achat
                <span class="font-normal text-amber-700">(propagées aux équipements liés)</span>
              </p>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">
                    Date d'achat
                    <span v-if="bulkForm.purchase_date !== ''" class="ml-1 text-indigo-600 font-semibold">✓</span>
                  </label>
                  <input v-model="bulkForm.purchase_date" type="date"
                         class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 outline-none"
                         :class="bulkForm.purchase_date !== '' ? 'border-indigo-400 bg-indigo-50' : 'border-amber-300 bg-white'" />
                  <button v-if="bulkForm.purchase_date !== ''" type="button"
                          @click="bulkForm.purchase_date = ''"
                          class="text-xs text-gray-400 hover:text-gray-600 mt-0.5">
                    ✕ Ne pas modifier
                  </button>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-1">
                    Coût d'achat (€)
                    <span v-if="bulkForm.purchase_cost !== ''" class="ml-1 text-indigo-600 font-semibold">✓</span>
                  </label>
                  <input v-model="bulkForm.purchase_cost" type="number" step="0.01" min="0" placeholder="—"
                         class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 outline-none"
                         :class="bulkForm.purchase_cost !== '' ? 'border-indigo-400 bg-indigo-50' : 'border-amber-300 bg-white'" />
                  <button v-if="bulkForm.purchase_cost !== ''" type="button"
                          @click="bulkForm.purchase_cost = ''"
                          class="text-xs text-gray-400 hover:text-gray-600 mt-0.5">
                    ✕ Ne pas modifier
                  </button>
                </div>
              </div>
            </div>

            <!-- Résumé des modifications -->
            <div v-if="bulkChangedFields.length > 0"
                 class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-sm">
              <p class="font-semibold text-green-800 mb-1 flex items-center gap-1.5">
                <CheckIcon class="w-4 h-4" />
                Modifications qui seront appliquées :
              </p>
              <ul class="text-green-700 text-xs space-y-0.5 list-disc list-inside">
                <li v-for="field in bulkChangedFields" :key="field">{{ field }}</li>
              </ul>
              <p class="text-xs text-green-600 mt-2">
                → Sur <strong>{{ selectedIds.length }}</strong> modèle(s) et leurs équipements liés.
              </p>
            </div>
            <div v-else class="text-sm text-gray-400 text-center py-2">
              Aucun champ modifié — sélectionnez au moins une valeur à appliquer.
            </div>

          </div>

          <div class="flex gap-3 px-6 pb-6">
            <button @click="showBulkEditModal = false"
                    class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">
              Annuler
            </button>
            <button @click="submitBulkEdit"
                    :disabled="bulkChangedFields.length === 0 || bulkProcessing"
                    class="flex-1 bg-indigo-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition">
              {{ bulkProcessing ? 'Application…' : `Appliquer à ${selectedIds.length} modèle(s)` }}
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
