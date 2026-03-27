<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import axios from 'axios'
import {
  PlusIcon, PencilIcon, TrashIcon, CubeTransparentIcon, PhotoIcon, Squares2X2Icon, MagnifyingGlassIcon,
  DocumentArrowUpIcon, DocumentIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  models: Array,
  categories: Array,
  manufacturers: Array,
  suppliers: { type: Array, default: () => [] },
})

// ─── Recherche ───────────────────────────────────────────────────────────────
const searchQuery = ref('')

const filteredModels = computed(() => {
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
})

function submitCreate() {
  createForm.post(route('models.store'), {
    forceFormData: true,
    onSuccess: () => {
      showCreateModal.value = false
      createForm.reset()
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
  _method: 'PUT',
})

function openEdit(model) {
  editingModel.value = model
  editForm.name            = model.name
  editForm.manufacturer    = model.manufacturer ?? ''
  editForm.manufacturer_id = model.manufacturer_id ?? ''
  editForm.category_id     = model.category_id ?? ''
  editForm.supplier_id     = model.supplier_id ?? ''
  editForm.description     = model.description ?? ''
  editForm.image           = null
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

// ─── File inputs ────────────────────────────────────────────────────────────
function onCreateImageChange(e) { createForm.image = e.target.files[0] ?? null }
function onEditImageChange(e) { editForm.image = e.target.files[0] ?? null }

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
            {{ filteredModels.length }}<template v-if="searchQuery"> / {{ models.length }}</template> modèle(s)
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
          <button @click="showCreateModal = true"
                  class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            <PlusIcon class="w-4 h-4" />
            Nouveau modèle
          </button>
        </div>
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

      <!-- Info box -->
      <div class="mb-5 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-sm text-blue-800">
        <strong>💡 Structure :</strong> Créez d'abord un <strong>modèle</strong> (ex : "MacBook Pro 14"), puis ajoutez la quantité d'équipements physiques.
        Chaque équipement créé hérite du modèle, de la catégorie et de l'image.
        Le bouton <strong>➕ Stock</strong> permet d'incrémenter le stock d'un modèle existant.
      </div>

      <!-- ── Grouped / card view ── -->
      <div v-if="groupByModel" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div v-for="model in filteredModels" :key="model.id"
             class="bg-white rounded-xl border shadow-sm overflow-hidden hover:shadow-md transition">
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
              <th class="px-4 py-3 text-left">Modèle</th>
              <th class="px-4 py-3 text-left hidden md:table-cell">Fabricant</th>
              <th class="px-4 py-3 text-left hidden lg:table-cell">Catégorie</th>
              <th class="px-4 py-3 text-center">Stock</th>
              <th class="px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="model in filteredModels" :key="model.id" class="border-b hover:bg-gray-50 transition">
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
              <td colspan="5" class="px-4 py-12 text-center text-gray-400">
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
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Fabricant</label>
              <select v-model="createForm.manufacturer_id"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">— Sans fabricant —</option>
                <option v-for="mfr in manufacturers" :key="mfr.id" :value="mfr.id">{{ mfr.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Fournisseur</label>
              <select v-model="createForm.supplier_id"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">— Sans fournisseur —</option>
                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
              <select v-model="createForm.category_id"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">— Sans catégorie —</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="createForm.description" rows="2" placeholder="Caractéristiques, notes…"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Image</label>
              <label class="flex items-center gap-2 cursor-pointer border-2 border-dashed border-gray-300 rounded-lg px-4 py-3 hover:border-blue-400 transition">
                <PhotoIcon class="w-5 h-5 text-gray-400" />
                <span class="text-sm text-gray-500">{{ createForm.image ? createForm.image.name : 'Choisir une image…' }}</span>
                <input type="file" accept="image/*" class="hidden" @change="onCreateImageChange" />
              </label>
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
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Fabricant</label>
              <select v-model="editForm.manufacturer_id"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">— Sans fabricant —</option>
                <option v-for="mfr in manufacturers" :key="mfr.id" :value="mfr.id">{{ mfr.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Fournisseur</label>
              <select v-model="editForm.supplier_id"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">— Sans fournisseur —</option>
                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
              <select v-model="editForm.category_id"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">— Sans catégorie —</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="editForm.description" rows="3"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nouvelle image</label>
              <div v-if="editingModel?.image_url" class="mb-2 flex items-center gap-3">
                <img :src="editingModel.image_url" class="w-16 h-16 rounded-lg object-cover border" :alt="editingModel.name" />
                <span class="text-xs text-gray-500">Image actuelle — sera propagée à tous les équipements.</span>
              </div>
              <label class="flex items-center gap-2 cursor-pointer border-2 border-dashed border-gray-300 rounded-lg px-4 py-3 hover:border-blue-400 transition">
                <PhotoIcon class="w-5 h-5 text-gray-400" />
                <span class="text-sm text-gray-500">{{ editForm.image ? editForm.image.name : 'Remplacer l\'image…' }}</span>
                <input type="file" accept="image/*" class="hidden" @change="onEditImageChange" />
              </label>
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
  </AppLayout>
</template>
