<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, nextTick } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import axios from 'axios'
import {
  PlusIcon, TrashIcon, PencilIcon, PhotoIcon,
  DocumentArrowDownIcon, TableCellsIcon, ArrowUpTrayIcon,
  MagnifyingGlassIcon, XMarkIcon, AdjustmentsHorizontalIcon,
  ArchiveBoxXMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  items:  Array,
  assets: Array,
})

// ─── Sélection ───────────────────────────────────────────────────────────────
const selectedIds = ref(new Set())
const allSelected = computed(() =>
  props.items.length > 0 && props.items.every(i => selectedIds.value.has(i.id))
)
function toggleSelectAll() {
  if (allSelected.value) {
    selectedIds.value = new Set()
  } else {
    selectedIds.value = new Set(props.items.map(i => i.id))
  }
}
function toggleSelect(id) {
  const s = new Set(selectedIds.value)
  s.has(id) ? s.delete(id) : s.add(id)
  selectedIds.value = s
}

// ─── Colonnes visibles (persistées en localStorage) ──────────────────────────
const ALL_COLS = [
  { key: 'serial_number', label: 'N° de série' },
  { key: 'reference',     label: 'Référence' },
  { key: 'brand',         label: 'Marque' },
  { key: 'model_name',    label: 'Modèle' },
  { key: 'notes',         label: 'Notes' },
  { key: 'photo',         label: 'Photo' },
  { key: 'created_by',   label: 'Créé par' },
]
const savedCols = (() => {
  try { return JSON.parse(localStorage.getItem('recycling_cols')) } catch { return null }
})()
const visibleCols = ref(new Set(savedCols ?? ['serial_number', 'reference', 'brand', 'model_name', 'photo']))
function toggleCol(key) {
  const s = new Set(visibleCols.value)
  s.has(key) ? s.delete(key) : s.add(key)
  visibleCols.value = s
  localStorage.setItem('recycling_cols', JSON.stringify([...s]))
}
const showColToggle = ref(false)

// ─── Formulaire Add / Edit ────────────────────────────────────────────────────
const showFormModal  = ref(false)
const formMode       = ref('add') // 'add' | 'edit'
const editingItem    = ref(null)
const photoPreview   = ref(null)
const photoInputEl   = ref(null)

const form = useForm({
  asset_id:      null,
  designation:   '',
  serial_number: '',
  reference:     '',
  brand:         '',
  model_name:    '',
  quantity:      1,
  notes:         '',
  photo:         null,
  extra_fields:  {},
  remove_photo:  false,
})
const extraFieldPairs = ref([]) // [{key:'', value:''}]

function addExtraField() {
  extraFieldPairs.value.push({ key: '', value: '' })
}
function removeExtraField(i) {
  extraFieldPairs.value.splice(i, 1)
}
function buildExtraFields() {
  const obj = {}
  for (const { key, value } of extraFieldPairs.value) {
    if (key.trim()) obj[key.trim()] = value
  }
  return obj
}

function openAdd(prefill = null) {
  formMode.value  = 'add'
  editingItem.value = null
  form.reset()
  photoPreview.value = null
  extraFieldPairs.value = []
  if (prefill) {
    form.asset_id      = prefill.id
    form.designation   = prefill.name
    form.serial_number = prefill.serial_number ?? ''
    form.brand         = prefill.brand ?? ''
    form.model_name    = prefill.model_name ?? ''
  }
  showFormModal.value = true
  showAddInventoryModal.value = false
}
function openEdit(item) {
  formMode.value     = 'edit'
  editingItem.value  = item
  form.asset_id      = item.asset_id
  form.designation   = item.designation
  form.serial_number = item.serial_number ?? ''
  form.reference     = item.reference ?? ''
  form.brand         = item.brand ?? ''
  form.model_name    = item.model_name ?? ''
  form.quantity      = item.quantity
  form.notes         = item.notes ?? ''
  form.photo         = null
  form.remove_photo  = false
  photoPreview.value = item.photo_url ?? null
  extraFieldPairs.value = Object.entries(item.extra_fields ?? {}).map(([key, value]) => ({ key, value }))
  showFormModal.value = true
}

function onPhotoChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  form.photo = file
  photoPreview.value = URL.createObjectURL(file)
}
function removePhoto() {
  form.photo        = null
  form.remove_photo = true
  photoPreview.value = null
  if (photoInputEl.value) photoInputEl.value.value = ''
}

function submitForm() {
  form.extra_fields = buildExtraFields()
  const options = {
    forceFormData: true,
    onSuccess: () => { showFormModal.value = false },
  }
  if (formMode.value === 'add') {
    form.post(route('recycling.store'), options)
  } else {
    form.transform(data => ({ ...data, _method: 'PUT' }))
        .post(route('recycling.update', editingItem.value.id), options)
  }
}

// ─── Supprimer ────────────────────────────────────────────────────────────────
const deleteForm = useForm({})
function deleteItem(item) {
  if (!confirm(`Supprimer la fiche « ${item.designation} » ?`)) return
  deleteForm.delete(route('recycling.destroy', item.id))
}

// ─── Modal inventaire ─────────────────────────────────────────────────────────
const showAddInventoryModal = ref(false)
const inventorySearch = ref('')
const filteredInventory = computed(() => {
  const q = inventorySearch.value.toLowerCase()
  if (!q) return props.assets
  return props.assets.filter(a =>
    a.name.toLowerCase().includes(q) || a.asset_tag.toLowerCase().includes(q)
  )
})

// ─── Export ───────────────────────────────────────────────────────────────────
const exportLoading = ref(false)
async function exportSelected(format) {
  if (selectedIds.value.size === 0) return
  exportLoading.value = true
  try {
    const res = await axios.post(
      route(format === 'pdf' ? 'recycling.export-pdf' : 'recycling.export-csv'),
      { ids: [...selectedIds.value] },
      { responseType: 'blob' }
    )
    const ext = format === 'pdf' ? 'pdf' : 'csv'
    const url = URL.createObjectURL(new Blob([res.data]))
    const a   = document.createElement('a')
    a.href     = url
    a.download = `decheterie-${new Date().toISOString().slice(0, 10)}.${ext}`
    a.click()
    URL.revokeObjectURL(url)
  } catch {}
  exportLoading.value = false
}
</script>

<template>
  <AppLayout title="Déchèterie">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

      <!-- En-tête -->
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <ArchiveBoxXMarkIcon class="w-7 h-7 text-red-600" />
            Déchèterie
          </h1>
          <p class="text-sm text-gray-500 mt-0.5">Répertoriez les équipements à éliminer et exportez des listes.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Colonnes -->
          <div class="relative">
            <button @click="showColToggle = !showColToggle"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 transition">
              <AdjustmentsHorizontalIcon class="w-4 h-4" />
              Colonnes
            </button>
            <div v-if="showColToggle"
                 class="absolute right-0 top-full mt-1 z-30 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl p-3 min-w-[170px]">
              <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Afficher</p>
              <label v-for="col in ALL_COLS" :key="col.key"
                     class="flex items-center gap-2 text-sm py-1 cursor-pointer hover:text-blue-600">
                <input type="checkbox" :checked="visibleCols.has(col.key)" @change="toggleCol(col.key)"
                       class="rounded border-gray-300" />
                {{ col.label }}
              </label>
            </div>
          </div>
          <!-- Ajouter depuis inventaire -->
          <button @click="showAddInventoryModal = true"
                  class="inline-flex items-center gap-1.5 px-3 py-2 text-sm bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 transition">
            <MagnifyingGlassIcon class="w-4 h-4" />
            Depuis l'inventaire
          </button>
          <!-- Ajouter manuellement -->
          <button @click="openAdd()"
                  class="inline-flex items-center gap-1.5 px-3 py-2 text-sm bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition shadow-sm">
            <PlusIcon class="w-4 h-4" />
            Ajouter manuellement
          </button>
        </div>
      </div>

      <!-- Barre de sélection/export -->
      <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0"
                  leave-active-class="transition duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="selectedIds.size > 0"
             class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl px-4 py-3 flex flex-wrap items-center gap-3">
          <span class="text-sm font-semibold text-red-700 dark:text-red-300">
            {{ selectedIds.size }} équipement(s) sélectionné(s)
          </span>
          <div class="flex items-center gap-2 ml-auto flex-wrap">
            <button @click="exportSelected('pdf')" :disabled="exportLoading"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition disabled:opacity-50">
              <DocumentArrowDownIcon class="w-4 h-4" />
              Exporter PDF
            </button>
            <button @click="exportSelected('csv')" :disabled="exportLoading"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition disabled:opacity-50">
              <TableCellsIcon class="w-4 h-4" />
              Exporter CSV/Excel
            </button>
            <button @click="selectedIds = new Set()"
                    class="text-xs text-gray-500 hover:text-gray-700 px-2 py-1.5 rounded-lg hover:bg-gray-100 transition">
              Désélectionner
            </button>
          </div>
        </div>
      </Transition>

      <!-- Tableau -->
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div v-if="items.length === 0" class="py-20 text-center">
          <ArchiveBoxXMarkIcon class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
          <p class="text-gray-500 dark:text-gray-400">Aucun équipement dans la liste déchèterie.</p>
          <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Ajoutez des équipements à éliminer.</p>
        </div>

        <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
          <thead class="bg-gray-50 dark:bg-gray-900/50">
            <tr>
              <th class="w-10 px-4 py-3">
                <input type="checkbox" :checked="allSelected" @change="toggleSelectAll"
                       class="rounded border-gray-300" />
              </th>
              <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Désignation</th>
              <th v-if="visibleCols.has('photo')" class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Photo</th>
              <th v-if="visibleCols.has('serial_number')" class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">N° de série</th>
              <th v-if="visibleCols.has('reference')" class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Référence</th>
              <th v-if="visibleCols.has('brand')" class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Marque</th>
              <th v-if="visibleCols.has('model_name')" class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Modèle</th>
              <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-400">Qté</th>
              <th v-if="visibleCols.has('notes')" class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Notes</th>
              <th v-if="visibleCols.has('created_by')" class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Créé par</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-400">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr v-for="item in items" :key="item.id"
                :class="selectedIds.has(item.id) ? 'bg-red-50 dark:bg-red-900/10' : 'hover:bg-gray-50 dark:hover:bg-gray-700/30'">
              <td class="px-4 py-3">
                <input type="checkbox" :checked="selectedIds.has(item.id)" @change="toggleSelect(item.id)"
                       class="rounded border-gray-300" />
              </td>
              <td class="px-4 py-3">
                <p class="font-semibold text-gray-900 dark:text-white">{{ item.designation }}</p>
                <p v-if="item.asset_tag" class="text-xs text-gray-400 font-mono">{{ item.asset_tag }}</p>
              </td>
              <td v-if="visibleCols.has('photo')" class="px-4 py-3">
                <img v-if="item.photo_url" :src="item.photo_url" :alt="item.designation"
                     class="w-12 h-12 object-cover rounded-lg border border-gray-200" />
                <span v-else class="text-gray-300 dark:text-gray-600 text-lg">—</span>
              </td>
              <td v-if="visibleCols.has('serial_number')" class="px-4 py-3 font-mono text-gray-700 dark:text-gray-300 text-xs">
                {{ item.serial_number || '—' }}
              </td>
              <td v-if="visibleCols.has('reference')" class="px-4 py-3 text-gray-700 dark:text-gray-300">
                {{ item.reference || '—' }}
              </td>
              <td v-if="visibleCols.has('brand')" class="px-4 py-3 text-gray-700 dark:text-gray-300">
                {{ item.brand || '—' }}
              </td>
              <td v-if="visibleCols.has('model_name')" class="px-4 py-3 text-gray-700 dark:text-gray-300">
                {{ item.model_name || '—' }}
              </td>
              <td class="px-4 py-3 text-center font-bold text-gray-900 dark:text-white">
                {{ item.quantity }}
              </td>
              <td v-if="visibleCols.has('notes')" class="px-4 py-3 text-gray-500 dark:text-gray-400 max-w-xs truncate text-xs">
                {{ item.notes || '—' }}
              </td>
              <td v-if="visibleCols.has('created_by')" class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">
                {{ item.created_by }}
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-1">
                  <button @click="openEdit(item)"
                          class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Modifier">
                    <PencilIcon class="w-4 h-4" />
                  </button>
                  <button @click="deleteItem(item)"
                          class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition" title="Supprimer">
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ─── Modal : Ajouter depuis l'inventaire ─────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showAddInventoryModal"
           class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-lg flex flex-col max-h-[80vh]">
          <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-bold text-gray-900 dark:text-white">Choisir depuis l'inventaire</h2>
            <button @click="showAddInventoryModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
            <div class="relative">
              <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
              <input v-model="inventorySearch" type="text" placeholder="Rechercher un équipement…"
                     class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 dark:border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />
            </div>
          </div>
          <div class="overflow-y-auto flex-1 px-5 py-3 space-y-1.5">
            <button v-for="asset in filteredInventory" :key="asset.id"
                    @click="openAdd(asset)"
                    class="w-full text-left px-4 py-2.5 rounded-xl hover:bg-blue-50 dark:hover:bg-blue-900/20 border border-transparent hover:border-blue-200 dark:hover:border-blue-700 transition">
              <p class="font-semibold text-sm text-gray-900 dark:text-white">{{ asset.name }}</p>
              <p class="text-xs text-gray-500 font-mono">{{ asset.asset_tag }}</p>
            </button>
            <p v-if="filteredInventory.length === 0" class="text-center text-gray-400 py-6 text-sm">Aucun résultat.</p>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ─── Modal : Ajouter / Modifier ──────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showFormModal"
           class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-xl overflow-y-auto max-h-[90vh]">
          <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-bold text-gray-900 dark:text-white">
              {{ formMode === 'add' ? 'Ajouter un équipement' : 'Modifier la fiche' }}
            </h2>
            <button @click="showFormModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <form @submit.prevent="submitForm" class="px-5 py-4 space-y-4">

            <!-- Désignation -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                Désignation <span class="text-red-500">*</span>
              </label>
              <input v-model="form.designation" type="text" required placeholder="Ex: Ordinateur portable Dell"
                     class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />
            </div>

            <!-- Grille champs -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">N° de série</label>
                <input v-model="form.serial_number" type="text" placeholder="SN-XXXX"
                       class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Référence</label>
                <input v-model="form.reference" type="text" placeholder="REF-XXXX"
                       class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Marque</label>
                <input v-model="form.brand" type="text" placeholder="Ex: HP, Dell…"
                       class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Modèle</label>
                <input v-model="form.model_name" type="text" placeholder="Ex: EliteBook 840"
                       class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />
              </div>
            </div>

            <!-- Quantité -->
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Quantité <span class="text-red-500">*</span></label>
              <input v-model.number="form.quantity" type="number" min="1" required
                     class="w-32 px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />
            </div>

            <!-- Notes -->
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Notes</label>
              <textarea v-model="form.notes" rows="2" placeholder="État, observations…"
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800 resize-none" />
            </div>

            <!-- Photo -->
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Photo</label>
              <div class="flex items-start gap-3">
                <!-- Prévisualisation -->
                <div v-if="photoPreview" class="relative">
                  <img :src="photoPreview" alt="Photo"
                       class="w-24 h-24 object-cover rounded-xl border border-gray-200 dark:border-gray-700" />
                  <button type="button" @click="removePhoto"
                          class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600">
                    <XMarkIcon class="w-3 h-3" />
                  </button>
                </div>
                <!-- Boutons upload -->
                <div class="flex flex-col gap-2">
                  <!-- Appareil photo (mobile) -->
                  <label class="inline-flex items-center gap-1.5 px-3 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg cursor-pointer transition">
                    <PhotoIcon class="w-4 h-4" />
                    Prendre une photo
                    <input ref="photoInputEl" type="file" accept="image/*" capture="environment"
                           @change="onPhotoChange" class="hidden" />
                  </label>
                  <!-- Fichier depuis galerie/PC -->
                  <label class="inline-flex items-center gap-1.5 px-3 py-2 text-sm bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg cursor-pointer transition">
                    <ArrowUpTrayIcon class="w-4 h-4" />
                    Importer un fichier
                    <input type="file" accept="image/*" @change="onPhotoChange" class="hidden" />
                  </label>
                </div>
              </div>
            </div>

            <!-- Champs personnalisés -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Champs personnalisés</label>
                <button type="button" @click="addExtraField"
                        class="text-xs text-blue-600 hover:text-blue-700 font-semibold flex items-center gap-1">
                  <PlusIcon class="w-3.5 h-3.5" /> Ajouter un champ
                </button>
              </div>
              <div v-for="(pair, i) in extraFieldPairs" :key="i" class="flex items-center gap-2 mb-2">
                <input v-model="pair.key" type="text" placeholder="Nom du champ"
                       class="flex-1 px-2 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 bg-white dark:bg-gray-800" />
                <input v-model="pair.value" type="text" placeholder="Valeur"
                       class="flex-1 px-2 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-500 bg-white dark:bg-gray-800" />
                <button type="button" @click="removeExtraField(i)" class="p-1 text-red-400 hover:text-red-600">
                  <XMarkIcon class="w-4 h-4" />
                </button>
              </div>
              <p v-if="extraFieldPairs.length === 0" class="text-xs text-gray-400 italic">
                Aucun champ personnalisé. Cliquez sur « Ajouter un champ » pour en créer.
              </p>
            </div>

            <!-- Erreurs -->
            <p v-if="form.errors.designation" class="text-xs text-red-600">{{ form.errors.designation }}</p>

            <!-- Boutons -->
            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
              <button type="button" @click="showFormModal = false"
                      class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition">
                Annuler
              </button>
              <button type="submit" :disabled="form.processing"
                      class="px-5 py-2 text-sm bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition disabled:opacity-50">
                {{ form.processing ? 'Enregistrement…' : (formMode === 'add' ? 'Ajouter' : 'Enregistrer') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

  </AppLayout>
</template>
