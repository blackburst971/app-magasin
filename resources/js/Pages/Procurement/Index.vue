<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import axios from 'axios'
import {
  PlusIcon, TrashIcon, PencilIcon, DocumentArrowDownIcon,
  TableCellsIcon, ArrowUpTrayIcon, XMarkIcon, LinkIcon,
  AdjustmentsHorizontalIcon, DocumentIcon, ArrowDownTrayIcon,
  ShoppingBagIcon, FunnelIcon, ArchiveBoxIcon, ArrowUturnLeftIcon,
  ClockIcon, ChevronUpIcon, ChevronDownIcon, CheckCircleIcon,
  Cog6ToothIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  items:         { type: Array, default: () => [] },
  archivedItems: { type: Array, default: () => [] },
  formFields:    { type: Array, default: () => [] },
  allFormFields: { type: Array, default: () => [] },
  files:         { type: Array, default: () => [] },
  isAdmin:       { type: Boolean, default: false },
  canWrite:      { type: Boolean, default: false },
  canExport:     { type: Boolean, default: false },
  myUserId:      { type: Number, default: 0 },
})

// ─── Onglets ──────────────────────────────────────────────────────────────────
const activeTab = ref('active')

// ─── Filtres liste active ─────────────────────────────────────────────────────
const search         = ref('')
const filterStatus   = ref('')
const filterPriority = ref('')
const showFilters    = ref(false)

const filteredItems = computed(() => {
  let list = [...props.items]
  if (filterStatus.value)   list = list.filter(i => i.status === filterStatus.value)
  if (filterPriority.value) list = list.filter(i => i.priority === filterPriority.value)
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(i =>
      i.designation.toLowerCase().includes(q) ||
      (i.reference     ?? '').toLowerCase().includes(q) ||
      (i.manufacturer  ?? '').toLowerCase().includes(q) ||
      (i.supplier      ?? '').toLowerCase().includes(q)
    )
  }
  const prio = { haute: 0, normale: 1, basse: 2 }
  return list.sort((a, b) => (prio[a.priority] ?? 1) - (prio[b.priority] ?? 1))
})

const totalEstime = computed(() =>
  filteredItems.value.reduce((acc, i) => acc + (i.total_price ?? 0), 0)
)

// ─── Filtres archives ─────────────────────────────────────────────────────────
const archiveSearch = ref('')
const filteredArchived = computed(() => {
  let list = [...props.archivedItems]
  if (archiveSearch.value) {
    const q = archiveSearch.value.toLowerCase()
    list = list.filter(i => i.designation.toLowerCase().includes(q) || (i.manufacturer ?? '').toLowerCase().includes(q))
  }
  return list
})

// ─── Sélection liste active ───────────────────────────────────────────────────
const selectedIds = ref(new Set())
const allSelected = computed(() =>
  filteredItems.value.length > 0 && filteredItems.value.every(i => selectedIds.value.has(i.id))
)
function toggleSelectAll() {
  selectedIds.value = allSelected.value
    ? new Set()
    : new Set(filteredItems.value.map(i => i.id))
}
function toggleSelect(id) {
  const s = new Set(selectedIds.value)
  s.has(id) ? s.delete(id) : s.add(id)
  selectedIds.value = s
}
watch([search, filterStatus, filterPriority], () => { selectedIds.value = new Set() })

// ─── Sélection archives ───────────────────────────────────────────────────────
const selectedArchiveIds = ref(new Set())
const allArchiveSelected = computed(() =>
  filteredArchived.value.length > 0 && filteredArchived.value.every(i => selectedArchiveIds.value.has(i.id))
)
function toggleSelectArchiveAll() {
  selectedArchiveIds.value = allArchiveSelected.value
    ? new Set()
    : new Set(filteredArchived.value.map(i => i.id))
}
function toggleSelectArchive(id) {
  const s = new Set(selectedArchiveIds.value)
  s.has(id) ? s.delete(id) : s.add(id)
  selectedArchiveIds.value = s
}

// ─── Extra fields state ───────────────────────────────────────────────────────
const extraValues = ref({})

function buildExtraValues(item) {
  const vals = {}
  for (const f of props.formFields) {
    const stored = item?.extra_fields?.[f.label]
    if (f.type === 'checkboxes') {
      vals[f.id] = Array.isArray(stored) ? [...stored] : []
    } else if (f.type === 'checkbox') {
      vals[f.id] = stored === true || stored === 'true' || stored === 1
    } else {
      vals[f.id] = stored ?? ''
    }
  }
  return vals
}

// ─── Modal item (create/edit) ─────────────────────────────────────────────────
const showModal   = ref(false)
const editingItem = ref(null)

const form = useForm({
  designation:  '',
  type:         'materiel',
  quantity:     1,
  unit:         'unité',
  reference:    '',
  manufacturer: '',
  supplier:     '',
  unit_price:   '',
  product_url:  '',
  notes:        '',
  status:       'besoin',
  priority:     'normale',
  extra_fields: {},
})

function openAdd() {
  editingItem.value = null
  form.designation  = ''
  form.type         = 'materiel'
  form.quantity     = 1
  form.unit         = 'unité'
  form.reference    = ''
  form.manufacturer = ''
  form.supplier     = ''
  form.unit_price   = ''
  form.product_url  = ''
  form.notes        = ''
  form.status       = 'besoin'
  form.priority     = 'normale'
  form.extra_fields = {}
  extraValues.value = buildExtraValues(null)
  form.clearErrors()
  showModal.value = true
}

function openEdit(item) {
  editingItem.value = item
  form.designation  = item.designation
  form.type         = item.type
  form.quantity     = item.quantity
  form.unit         = item.unit
  form.reference    = item.reference    ?? ''
  form.manufacturer = item.manufacturer ?? ''
  form.supplier     = item.supplier     ?? ''
  form.unit_price   = item.unit_price   !== null ? item.unit_price : ''
  form.product_url  = item.product_url  ?? ''
  form.notes        = item.notes        ?? ''
  form.status       = item.status
  form.priority     = item.priority
  form.extra_fields = item.extra_fields ?? {}
  extraValues.value = buildExtraValues(item)
  form.clearErrors()
  showModal.value   = true
}

function buildExtraFieldsPayload() {
  const result = {}
  for (const f of props.formFields) {
    result[f.label] = extraValues.value[f.id] ?? null
  }
  return result
}

function submitItem() {
  form.extra_fields = buildExtraFieldsPayload()
  if (editingItem.value) {
    form.put(route('procurement.update', editingItem.value.id), {
      onSuccess: () => { showModal.value = false },
    })
  } else {
    form.post(route('procurement.store'), {
      onSuccess: () => { showModal.value = false },
    })
  }
}

const deleteForm = useForm({})
function deleteItem(item) {
  if (!confirm(`Supprimer « ${item.designation} » ?`)) return
  deleteForm.delete(route('procurement.destroy', item.id))
}

// ─── Archive / Restore ────────────────────────────────────────────────────────
const archiveForm = useForm({ ids: [] })
function bulkArchive() {
  if (!selectedIds.value.size) return
  if (!confirm(`Archiver ${selectedIds.value.size} article(s) ?`)) return
  archiveForm.ids = [...selectedIds.value]
  archiveForm.post(route('procurement.bulk-archive'), {
    onSuccess: () => { selectedIds.value = new Set() },
  })
}

const restoreForm = useForm({})
function restoreItem(item) {
  if (!confirm(`Restaurer « ${item.designation} » ?`)) return
  restoreForm.post(route('procurement.unarchive', item.id))
}

// ─── Fichiers ─────────────────────────────────────────────────────────────────
const showFileModal = ref(false)
const fileForm      = useForm({ file: null, description: '' })
const fileInput     = ref(null)

function onFileChange(e) { fileForm.file = e.target.files?.[0] ?? null }
function submitFile() {
  fileForm.post(route('procurement.files.store'), {
    forceFormData: true,
    onSuccess: () => {
      showFileModal.value = false
      fileForm.reset()
      if (fileInput.value) fileInput.value.value = ''
    },
  })
}
const deleteFileForm = useForm({})
function deleteFile(f) {
  if (!confirm(`Supprimer « ${f.original_name} » ?`)) return
  deleteFileForm.delete(route('procurement.files.destroy', f.id))
}

// ─── Export ───────────────────────────────────────────────────────────────────
const exportLoading = ref(false)
const exportError   = ref('')

async function doExport(format, ids) {
  exportLoading.value = true
  exportError.value   = ''
  const payload = ids && ids.length > 0 ? { ids } : {}
  try {
    const res = await axios.post(
      route(format === 'pdf' ? 'procurement.export-pdf' : 'procurement.export-csv'),
      payload,
      { responseType: 'blob' }
    )
    const ext  = format === 'pdf' ? 'pdf' : 'csv'
    const mime = format === 'pdf' ? 'application/pdf' : 'text/csv'
    const blob = res.data instanceof Blob ? res.data : new Blob([res.data], { type: mime })
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement('a')
    a.href     = url
    a.download = `commandes-${new Date().toISOString().slice(0, 10)}.${ext}`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(url), 200)
  } catch (err) {
    if (err?.response?.data instanceof Blob) {
      try {
        const text = await err.response.data.text()
        const json = JSON.parse(text)
        exportError.value = json.message || `Erreur ${err.response.status}`
      } catch {
        exportError.value = `Erreur ${err?.response?.status ?? ''} lors de l'export.`
      }
    } else {
      exportError.value = err?.response?.data?.message || "Erreur lors de l'export."
    }
  }
  exportLoading.value = false
}

// ─── Historique ───────────────────────────────────────────────────────────────
const showHistoryPanel = ref(false)
const historyItem      = ref(null)
const historyEntries   = ref([])
const historyLoading   = ref(false)

async function openHistory(item) {
  historyItem.value    = item
  showHistoryPanel.value = true
  historyLoading.value = true
  historyEntries.value = []
  try {
    const { data } = await axios.get(route('procurement.history', item.id))
    historyEntries.value = data
  } catch {
    historyEntries.value = []
  }
  historyLoading.value = false
}

const ACTION_LABELS = {
  created:    'Créé',
  updated:    'Modifié',
  archived:   'Archivé',
  unarchived: 'Restauré',
}

// ─── Constructeur de formulaire ───────────────────────────────────────────────
const showFormBuilder  = ref(false)
const builderFields    = ref([])
const newFieldType     = ref('text')
const newFieldLabel    = ref('')
const newFieldOptions  = ref('')
const newFieldRequired = ref(false)
const builderError     = ref('')

function openFormBuilder() {
  builderFields.value = props.allFormFields.map(f => ({ ...f, options: f.options ? [...f.options] : [] }))
  newFieldType.value     = 'text'
  newFieldLabel.value    = ''
  newFieldOptions.value  = ''
  newFieldRequired.value = false
  builderError.value     = ''
  showFormBuilder.value  = true
}

function addBuilderField() {
  if (!newFieldLabel.value.trim()) { builderError.value = 'Le libellé est requis.'; return }
  builderError.value = ''
  const needsOptions = ['select', 'radio', 'checkboxes'].includes(newFieldType.value)
  const options = needsOptions
    ? newFieldOptions.value.split('\n').map(o => o.trim()).filter(Boolean)
    : null
  builderFields.value.push({
    id:         null,
    label:      newFieldLabel.value.trim(),
    type:       newFieldType.value,
    options,
    required:   newFieldRequired.value,
    sort_order: builderFields.value.length,
    active:     true,
  })
  newFieldLabel.value    = ''
  newFieldOptions.value  = ''
  newFieldRequired.value = false
}

function removeBuilderField(idx) { builderFields.value.splice(idx, 1) }
function moveBuilderField(idx, dir) {
  const newIdx = idx + dir
  if (newIdx < 0 || newIdx >= builderFields.value.length) return
  const arr = [...builderFields.value]
  ;[arr[idx], arr[newIdx]] = [arr[newIdx], arr[idx]]
  builderFields.value = arr
}

const saveBuilderForm = useForm({})
function saveFormBuilder() {
  const fields = builderFields.value.map((f, i) => ({ ...f, sort_order: i }))
  router.post(route('procurement.form-fields.save'), { fields }, {
    onSuccess: () => { showFormBuilder.value = false },
  })
}

const FIELD_TYPE_LABELS = {
  text: 'Texte', number: 'Nombre', textarea: 'Texte long', url: 'URL',
  date: 'Date', select: 'Liste déroulante', radio: 'Boutons radio',
  checkbox: 'Case à cocher', checkboxes: 'Cases multiples',
}

// ─── Helpers visuels ──────────────────────────────────────────────────────────
const STATUS_COLORS = {
  besoin:      'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
  en_commande: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
  commande:    'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
  livre:       'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
  annule:      'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
}
const PRIO_COLORS = {
  haute:   'text-red-600 font-bold',
  normale: 'text-gray-500 dark:text-gray-400',
  basse:   'text-gray-400 dark:text-gray-500',
}
const TYPE_COLORS = {
  materiel:    'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
  consommable: 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-300',
  logiciel:    'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300',
  autre:       'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
}
const FILE_ICONS = { pdf: '📄', excel: '📊', word: '📝', image: '🖼️', file: '📎' }

function formatPrice(v) {
  if (v === null || v === undefined || v === '') return '—'
  return Number(v).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'
}

function canEditItem(item) {
  return props.canWrite && (props.isAdmin || item.created_by_user_id === props.myUserId)
}

function canDeleteFile(file) {
  return props.isAdmin || (props.canWrite && file.uploaded_by_user_id === props.myUserId)
}

const needsOptions = computed(() => ['select', 'radio', 'checkboxes'].includes(newFieldType.value))
</script>

<template>
  <AppLayout title="Commandes / Besoins matériel">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

      <!-- En-tête -->
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <ShoppingBagIcon class="w-7 h-7 text-blue-600" />
            Commandes / Besoins matériel
          </h1>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Listez les besoins en matériels et consommables.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <button @click="showFilters = !showFilters"
                  class="inline-flex items-center gap-1.5 px-3 py-2 text-sm bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                  :class="showFilters ? 'border-blue-400 text-blue-600' : ''">
            <FunnelIcon class="w-4 h-4" />
            Filtres
          </button>
          <button v-if="isAdmin" @click="openFormBuilder()"
                  class="inline-flex items-center gap-1.5 px-3 py-2 text-sm bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
            <Cog6ToothIcon class="w-4 h-4" />
            Personnaliser le formulaire
          </button>
          <button v-if="canWrite" @click="showFileModal = true"
                  class="inline-flex items-center gap-1.5 px-3 py-2 text-sm bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
            <ArrowUpTrayIcon class="w-4 h-4" />
            Ajouter un devis
          </button>
          <button v-if="canWrite" @click="openAdd()"
                  class="inline-flex items-center gap-1.5 px-3 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition shadow-sm">
            <PlusIcon class="w-4 h-4" />
            Ajouter un article
          </button>
        </div>
      </div>

      <!-- Onglets -->
      <div class="flex gap-1 border-b border-gray-200 dark:border-gray-700">
        <button @click="activeTab = 'active'"
                class="px-4 py-2 text-sm font-medium rounded-t-lg transition"
                :class="activeTab === 'active'
                  ? 'bg-white dark:bg-gray-800 border border-b-white dark:border-gray-700 dark:border-b-gray-800 text-blue-600 -mb-px'
                  : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
          Liste active ({{ items.length }})
        </button>
        <button v-if="isAdmin" @click="activeTab = 'archives'"
                class="px-4 py-2 text-sm font-medium rounded-t-lg transition"
                :class="activeTab === 'archives'
                  ? 'bg-white dark:bg-gray-800 border border-b-white dark:border-gray-700 dark:border-b-gray-800 text-blue-600 -mb-px'
                  : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'">
          Archives ({{ archivedItems.length }})
        </button>
      </div>

      <!-- ── ONGLET LISTE ACTIVE ─────────────────────────────────────────────── -->
      <template v-if="activeTab === 'active'">

        <!-- Filtres -->
        <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
          <div v-if="showFilters" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 flex flex-wrap gap-3 items-end">
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">Recherche</label>
              <input v-model="search" type="text" placeholder="Désignation, réf…"
                     class="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-900 w-48" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">Statut</label>
              <select v-model="filterStatus" class="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-900">
                <option value="">Tous</option>
                <option value="besoin">Besoin identifié</option>
                <option value="en_commande">En cours de commande</option>
                <option value="commande">Commandé</option>
                <option value="livre">Livré</option>
                <option value="annule">Annulé</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">Priorité</label>
              <select v-model="filterPriority" class="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-900">
                <option value="">Toutes</option>
                <option value="haute">Haute</option>
                <option value="normale">Normale</option>
                <option value="basse">Basse</option>
              </select>
            </div>
            <button @click="search = ''; filterStatus = ''; filterPriority = ''"
                    class="px-3 py-1.5 text-xs text-gray-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition">
              Réinitialiser
            </button>
          </div>
        </Transition>

        <!-- Barre actions sélection -->
        <div class="flex flex-wrap items-center gap-3">
          <p class="text-sm text-gray-500 dark:text-gray-400">
            <template v-if="selectedIds.size > 0">
              <span class="text-blue-700 dark:text-blue-400 font-semibold">{{ selectedIds.size }} sélectionné(s)</span>
            </template>
            <template v-else>
              {{ filteredItems.length }} article(s) — Total estimé :
              <strong class="text-gray-700 dark:text-gray-200">{{ formatPrice(totalEstime) }}</strong>
            </template>
          </p>
          <div class="ml-auto flex items-center gap-2 flex-wrap">
            <p v-if="exportError" class="text-sm text-red-600">{{ exportError }}</p>
            <template v-if="canExport">
              <button @click="doExport('pdf', [...selectedIds])" :disabled="exportLoading"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition disabled:opacity-50">
                <DocumentArrowDownIcon class="w-4 h-4" />
                PDF{{ selectedIds.size > 0 ? ` (${selectedIds.size})` : ' (tout)' }}
              </button>
              <button @click="doExport('csv', [...selectedIds])" :disabled="exportLoading"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition disabled:opacity-50">
                <TableCellsIcon class="w-4 h-4" />
                CSV{{ selectedIds.size > 0 ? ` (${selectedIds.size})` : ' (tout)' }}
              </button>
            </template>
            <button v-if="isAdmin && selectedIds.size > 0" @click="bulkArchive()" :disabled="archiveForm.processing"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-lg transition disabled:opacity-50">
              <ArchiveBoxIcon class="w-4 h-4" />
              Archiver ({{ selectedIds.size }})
            </button>
            <button v-if="selectedIds.size > 0" @click="selectedIds = new Set()"
                    class="text-xs text-gray-500 hover:text-gray-700 px-2 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
              Désélectionner
            </button>
          </div>
        </div>

        <!-- Tableau items actifs -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow border border-gray-200 dark:border-gray-700 overflow-x-auto">
          <div v-if="filteredItems.length === 0" class="py-20 text-center">
            <ShoppingBagIcon class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
            <p class="text-gray-500 dark:text-gray-400">Aucun article trouvé.</p>
            <button v-if="canWrite" @click="openAdd()" class="mt-3 text-sm text-blue-600 hover:underline">+ Ajouter un article</button>
          </div>

          <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
              <tr>
                <th v-if="canExport" class="w-10 px-4 py-3">
                  <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-gray-300" />
                </th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400 min-w-[140px]">Désignation</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Type</th>
                <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-400">Qté</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Fabricant</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Fournisseur</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-400">P.U.</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-400">Total</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Statut</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Priorité</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Lien</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400 min-w-[90px]">Ajouté</th>
                <th v-for="ff in formFields" :key="ff.id" class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">{{ ff.label }}</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-400">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="item in filteredItems" :key="item.id"
                  :class="[
                    selectedIds.has(item.id) ? 'bg-blue-50 dark:bg-blue-900/10' : 'hover:bg-gray-50 dark:hover:bg-gray-700/30',
                    item.status === 'annule' ? 'opacity-50' : '',
                  ]">
                <td v-if="canExport" class="px-4 py-3">
                  <input type="checkbox" :checked="selectedIds.has(item.id)" @change="toggleSelect(item.id)" class="rounded border-gray-300" />
                </td>
                <td class="px-4 py-3">
                  <p class="font-semibold text-gray-900 dark:text-white">{{ item.designation }}</p>
                  <p v-if="item.reference" class="text-xs font-mono text-gray-400">{{ item.reference }}</p>
                  <p v-if="(canExport || canWrite) && item.created_by" class="text-xs text-gray-400">{{ item.created_by }}</p>
                </td>
                <td class="px-4 py-3">
                  <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold" :class="TYPE_COLORS[item.type] ?? 'bg-gray-100 text-gray-600'">
                    {{ item.type_label }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center font-bold text-gray-900 dark:text-white">
                  {{ item.quantity }} <span class="text-xs font-normal text-gray-400">{{ item.unit }}</span>
                </td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ item.manufacturer || '—' }}</td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ item.supplier || '—' }}</td>
                <td class="px-4 py-3 text-right font-mono text-xs text-gray-700 dark:text-gray-300">{{ formatPrice(item.unit_price) }}</td>
                <td class="px-4 py-3 text-right font-mono text-xs font-semibold text-gray-900 dark:text-white">{{ formatPrice(item.total_price) }}</td>
                <td class="px-4 py-3">
                  <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold" :class="STATUS_COLORS[item.status] ?? 'bg-gray-100 text-gray-600'">
                    {{ item.status_label }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <span :class="PRIO_COLORS[item.priority] ?? ''">{{ item.priority_label }}</span>
                </td>
                <td class="px-4 py-3">
                  <a v-if="item.product_url" :href="item.product_url" target="_blank" rel="noopener"
                     class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                    <LinkIcon class="w-3 h-3" /> Lien
                  </a>
                  <span v-else class="text-gray-300">—</span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                  <p>{{ item.created_at }}</p>
                  <p v-if="canExport && item.updated_at" class="text-gray-400">Modifié: {{ item.updated_at }}<template v-if="item.updated_by"> par {{ item.updated_by }}</template></p>
                </td>
                <td v-for="ff in formFields" :key="ff.id" class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">
                  <template v-if="Array.isArray(item.extra_fields?.[ff.label])">
                    {{ item.extra_fields[ff.label].join(', ') || '—' }}
                  </template>
                  <template v-else-if="item.extra_fields?.[ff.label] === true || item.extra_fields?.[ff.label] === 'true'">
                    <CheckCircleIcon class="w-4 h-4 text-green-500" />
                  </template>
                  <template v-else>
                    {{ item.extra_fields?.[ff.label] || '—' }}
                  </template>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center justify-end gap-1">
                    <button v-if="canExport" @click="openHistory(item)"
                            class="p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition" title="Historique">
                      <ClockIcon class="w-4 h-4" />
                    </button>
                    <button v-if="canEditItem(item)" @click="openEdit(item)"
                            class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition" title="Modifier">
                      <PencilIcon class="w-4 h-4" />
                    </button>
                    <button v-if="canEditItem(item)" @click="deleteItem(item)"
                            class="p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition" title="Supprimer">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
            <tfoot v-if="filteredItems.some(i => i.total_price !== null)" class="bg-gray-50 dark:bg-gray-900/50 border-t-2 border-gray-300 dark:border-gray-600">
              <tr>
                <td :colspan="canExport ? 8 : 7" class="px-4 py-2 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Total estimé :</td>
                <td class="px-4 py-2 text-right font-mono text-sm font-bold text-blue-700 dark:text-blue-400">{{ formatPrice(totalEstime) }}</td>
                <td :colspan="4 + formFields.length"></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Section Devis / Fichiers -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow border border-gray-200 dark:border-gray-700">
          <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h2 class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
              <DocumentIcon class="w-5 h-5 text-blue-600" />
              Devis &amp; Documents ({{ files.length }})
            </h2>
            <button v-if="canWrite" @click="showFileModal = true"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
              <PlusIcon class="w-4 h-4" />
              Ajouter
            </button>
          </div>
          <div v-if="files.length === 0" class="py-10 text-center">
            <DocumentIcon class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
            <p class="text-gray-400 text-sm">Aucun document.</p>
          </div>
          <div v-else class="divide-y divide-gray-100 dark:divide-gray-700">
            <div v-for="f in files" :key="f.id"
                 class="px-5 py-3 flex items-center gap-4 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
              <span class="text-2xl flex-shrink-0">{{ FILE_ICONS[f.icon_type] ?? '📎' }}</span>
              <div class="flex-1 min-w-0">
                <p class="font-semibold text-sm text-gray-900 dark:text-white truncate">{{ f.original_name }}</p>
                <p v-if="f.description" class="text-xs text-gray-500">{{ f.description }}</p>
                <p class="text-xs text-gray-400">{{ f.file_size_human }} — {{ f.created_at }}<template v-if="f.uploaded_by"> par {{ f.uploaded_by }}</template></p>
              </div>
              <div class="flex items-center gap-2">
                <a :href="f.download_url" target="_blank"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition">
                  <ArrowDownTrayIcon class="w-4 h-4" />
                  Télécharger
                </a>
                <button v-if="canDeleteFile(f)" @click="deleteFile(f)"
                        class="p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition"
                        :title="isAdmin ? 'Supprimer' : 'Supprimer (votre fichier)'">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- ── ONGLET ARCHIVES ──────────────────────────────────────────────────── -->
      <template v-if="activeTab === 'archives' && isAdmin">

        <!-- Barre actions archives -->
        <div class="flex flex-wrap items-center gap-3">
          <input v-model="archiveSearch" type="text" placeholder="Rechercher dans les archives…"
                 class="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-900 w-60" />
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ filteredArchived.length }} archivé(s)</p>
          <div class="ml-auto flex items-center gap-2">
            <template v-if="canExport">
              <button @click="doExport('pdf', [...selectedArchiveIds])" :disabled="exportLoading"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition disabled:opacity-50">
                <DocumentArrowDownIcon class="w-4 h-4" />
                PDF{{ selectedArchiveIds.size > 0 ? ` (${selectedArchiveIds.size})` : ' (tout)' }}
              </button>
              <button @click="doExport('csv', [...selectedArchiveIds])" :disabled="exportLoading"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition disabled:opacity-50">
                <TableCellsIcon class="w-4 h-4" />
                CSV{{ selectedArchiveIds.size > 0 ? ` (${selectedArchiveIds.size})` : ' (tout)' }}
              </button>
            </template>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow border border-gray-200 dark:border-gray-700 overflow-x-auto">
          <div v-if="filteredArchived.length === 0" class="py-20 text-center">
            <ArchiveBoxIcon class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-4" />
            <p class="text-gray-500 dark:text-gray-400">Aucun article archivé.</p>
          </div>
          <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
              <tr>
                <th v-if="canExport" class="w-10 px-4 py-3">
                  <input type="checkbox" :checked="allArchiveSelected" @change="toggleSelectArchiveAll" class="rounded border-gray-300" />
                </th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Désignation</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Type</th>
                <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-400">Qté</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-400">Total</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Statut</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Créé par</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-400">Archivé le</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-400">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="item in filteredArchived" :key="item.id"
                  class="opacity-75 hover:opacity-100"
                  :class="selectedArchiveIds.has(item.id) ? 'bg-blue-50 dark:bg-blue-900/10' : 'hover:bg-gray-50 dark:hover:bg-gray-700/30'">
                <td v-if="canExport" class="px-4 py-3">
                  <input type="checkbox" :checked="selectedArchiveIds.has(item.id)" @change="toggleSelectArchive(item.id)" class="rounded border-gray-300" />
                </td>
                <td class="px-4 py-3">
                  <p class="font-semibold text-gray-900 dark:text-white">{{ item.designation }}</p>
                  <p v-if="item.reference" class="text-xs font-mono text-gray-400">{{ item.reference }}</p>
                </td>
                <td class="px-4 py-3">
                  <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold" :class="TYPE_COLORS[item.type] ?? 'bg-gray-100 text-gray-600'">
                    {{ item.type_label }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center font-bold text-gray-900 dark:text-white">
                  {{ item.quantity }} <span class="text-xs font-normal text-gray-400">{{ item.unit }}</span>
                </td>
                <td class="px-4 py-3 text-right font-mono text-xs font-semibold text-gray-900 dark:text-white">{{ formatPrice(item.total_price) }}</td>
                <td class="px-4 py-3">
                  <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold" :class="STATUS_COLORS[item.status] ?? 'bg-gray-100 text-gray-600'">
                    {{ item.status_label }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                  {{ item.created_by || '—' }}<br />{{ item.created_at }}
                </td>
                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                  {{ item.archived_at }}<br /><span v-if="item.archived_by">par {{ item.archived_by }}</span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center justify-end gap-1">
                    <button v-if="canExport" @click="openHistory(item)"
                            class="p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition" title="Historique">
                      <ClockIcon class="w-4 h-4" />
                    </button>
                    <button @click="restoreItem(item)"
                            class="inline-flex items-center gap-1 px-2 py-1.5 text-xs text-emerald-700 bg-emerald-50 dark:bg-emerald-900/20 dark:text-emerald-400 hover:bg-emerald-100 rounded-lg transition" title="Restaurer">
                      <ArrowUturnLeftIcon class="w-3.5 h-3.5" />
                      Restaurer
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

    </div>

    <!-- ─── Modal : Ajouter / Modifier article ────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showModal"
           class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-2xl overflow-y-auto max-h-[90vh]">
          <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-bold text-gray-900 dark:text-white">
              {{ editingItem ? "Modifier l'article" : 'Ajouter un article' }}
            </h2>
            <button @click="showModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <form @submit.prevent="submitItem" class="px-5 py-4 space-y-4">

            <!-- Info créateur (edit, canExport) -->
            <div v-if="editingItem && canExport" class="text-xs text-gray-400 bg-gray-50 dark:bg-gray-800 rounded-lg px-3 py-2">
              Créé par <strong>{{ editingItem.created_by || '—' }}</strong> le {{ editingItem.created_at }}
              <template v-if="editingItem.updated_at">
                — Modifié le {{ editingItem.updated_at }}<template v-if="editingItem.updated_by"> par {{ editingItem.updated_by }}</template>
              </template>
            </div>

            <!-- Désignation -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Désignation <span class="text-red-500">*</span></label>
              <input v-model="form.designation" type="text" required placeholder="Ex: Câble HDMI 2m"
                     class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />
              <p v-if="form.errors.designation" class="text-xs text-red-600 mt-1">{{ form.errors.designation }}</p>
            </div>

            <!-- Type + Priorité + Statut -->
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Type <span class="text-red-500">*</span></label>
                <select v-model="form.type" required class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800">
                  <option value="materiel">Matériel</option>
                  <option value="consommable">Consommable</option>
                  <option value="logiciel">Logiciel</option>
                  <option value="autre">Autre</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Priorité</label>
                <select v-model="form.priority" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800">
                  <option value="haute">Haute</option>
                  <option value="normale">Normale</option>
                  <option value="basse">Basse</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Statut</label>
                <select v-model="form.status" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800">
                  <option value="besoin">Besoin identifié</option>
                  <option value="en_commande">En cours de commande</option>
                  <option value="commande">Commandé</option>
                  <option value="livre">Livré</option>
                  <option value="annule">Annulé</option>
                </select>
              </div>
            </div>

            <!-- Quantité + Unité + Prix unitaire -->
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Quantité <span class="text-red-500">*</span></label>
                <input v-model.number="form.quantity" type="number" min="1" required
                       class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Unité</label>
                <input v-model="form.unit" type="text" placeholder="unité, kg, lot…"
                       class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Prix unitaire (€)</label>
                <input v-model="form.unit_price" type="number" min="0" step="0.01" placeholder="0.00"
                       class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />
              </div>
            </div>

            <!-- Référence + Fabricant + Fournisseur -->
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Référence</label>
                <input v-model="form.reference" type="text" placeholder="REF-XXXX"
                       class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Fabricant</label>
                <input v-model="form.manufacturer" type="text" placeholder="Ex: Dell, HP…"
                       class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Fournisseur</label>
                <input v-model="form.supplier" type="text" placeholder="Ex: Amazon, LDLC…"
                       class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />
              </div>
            </div>

            <!-- Lien produit -->
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">
                <LinkIcon class="w-3.5 h-3.5 inline -mt-0.5" /> Lien produit (URL)
              </label>
              <input v-model="form.product_url" type="url" placeholder="https://…"
                     class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />
              <p v-if="form.errors.product_url" class="text-xs text-red-600 mt-1">{{ form.errors.product_url }}</p>
            </div>

            <!-- Notes -->
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Notes</label>
              <textarea v-model="form.notes" rows="2" placeholder="Justification, contexte…"
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800 resize-none" />
            </div>

            <!-- Champs personnalisés -->
            <template v-if="formFields.length > 0">
              <div class="border-t border-gray-100 dark:border-gray-800 pt-4">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-3 uppercase tracking-wide">Informations supplémentaires</p>
                <div class="space-y-3">
                  <div v-for="ff in formFields" :key="ff.id">
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">
                      {{ ff.label }}<span v-if="ff.required" class="text-red-500 ml-0.5">*</span>
                    </label>

                    <!-- text / url / date / number -->
                    <input v-if="['text','url','date','number'].includes(ff.type)"
                           v-model="extraValues[ff.id]"
                           :type="ff.type"
                           :required="ff.required"
                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />

                    <!-- textarea -->
                    <textarea v-else-if="ff.type === 'textarea'"
                              v-model="extraValues[ff.id]"
                              :required="ff.required"
                              rows="2"
                              class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800 resize-none" />

                    <!-- select -->
                    <select v-else-if="ff.type === 'select'"
                            v-model="extraValues[ff.id]"
                            :required="ff.required"
                            class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800">
                      <option value="">— Choisir —</option>
                      <option v-for="opt in ff.options" :key="opt" :value="opt">{{ opt }}</option>
                    </select>

                    <!-- radio -->
                    <div v-else-if="ff.type === 'radio'" class="flex flex-wrap gap-3">
                      <label v-for="opt in ff.options" :key="opt" class="flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                        <input type="radio" :name="`ff_${ff.id}`" :value="opt" v-model="extraValues[ff.id]" class="text-blue-600" />
                        {{ opt }}
                      </label>
                    </div>

                    <!-- checkbox (single boolean) -->
                    <label v-else-if="ff.type === 'checkbox'" class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                      <input type="checkbox" v-model="extraValues[ff.id]" class="rounded text-blue-600" />
                      Oui
                    </label>

                    <!-- checkboxes (multiple) -->
                    <div v-else-if="ff.type === 'checkboxes'" class="flex flex-wrap gap-3">
                      <label v-for="opt in ff.options" :key="opt" class="flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                        <input type="checkbox" :value="opt"
                               :checked="Array.isArray(extraValues[ff.id]) && extraValues[ff.id].includes(opt)"
                               @change="e => {
                                 if (!Array.isArray(extraValues[ff.id])) extraValues[ff.id] = []
                                 if (e.target.checked) extraValues[ff.id] = [...extraValues[ff.id], opt]
                                 else extraValues[ff.id] = extraValues[ff.id].filter(v => v !== opt)
                               }"
                               class="rounded text-blue-600" />
                        {{ opt }}
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <!-- Total calculé -->
            <div v-if="form.unit_price && form.quantity" class="bg-blue-50 dark:bg-blue-900/20 rounded-lg px-4 py-2 text-sm text-blue-800 dark:text-blue-300">
              Total estimé : <strong>{{ formatPrice(form.unit_price * form.quantity) }}</strong>
            </div>

            <!-- Boutons -->
            <div class="flex justify-between gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
              <button type="button" @click="showModal = false"
                      class="px-4 py-2 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition">
                Annuler
              </button>
              <button type="submit" :disabled="form.processing"
                      class="px-5 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition disabled:opacity-50">
                {{ form.processing ? 'Enregistrement…' : (editingItem ? 'Modifier' : 'Ajouter') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ─── Modal : Ajouter un devis ──────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showFileModal"
           class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md">
          <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-bold text-gray-900 dark:text-white">Ajouter un devis / document</h2>
            <button @click="showFileModal = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <form @submit.prevent="submitFile" class="px-5 py-4 space-y-4">
            <div>
              <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                Fichier <span class="text-red-500">*</span>
                <span class="text-xs font-normal text-gray-400 ml-1">(PDF, Word, Excel, image — max 20 Mo)</span>
              </label>
              <input ref="fileInput" type="file" required @change="onFileChange"
                     accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp"
                     class="block w-full text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
              <p v-if="fileForm.errors.file" class="text-xs text-red-600 mt-1">{{ fileForm.errors.file }}</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Description (optionnel)</label>
              <input v-model="fileForm.description" type="text" placeholder="Ex: Devis LDLC juillet 2026"
                     class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800" />
            </div>
            <div class="flex justify-between gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
              <button type="button" @click="showFileModal = false"
                      class="px-4 py-2 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition">
                Annuler
              </button>
              <button type="submit" :disabled="fileForm.processing"
                      class="px-5 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition disabled:opacity-50">
                {{ fileForm.processing ? 'Envoi…' : 'Ajouter' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ─── Panneau Historique ──────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showHistoryPanel"
           class="fixed inset-0 z-50 flex items-end sm:items-center justify-end"
           @click.self="showHistoryPanel = false">
        <div class="bg-white dark:bg-gray-900 w-full sm:w-96 h-full sm:h-auto sm:max-h-[80vh] shadow-2xl overflow-y-auto sm:rounded-l-2xl border-l border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-900">
            <div>
              <h2 class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <ClockIcon class="w-4 h-4 text-blue-600" />
                Historique
              </h2>
              <p class="text-xs text-gray-500 truncate max-w-[220px]">{{ historyItem?.designation }}</p>
            </div>
            <button @click="showHistoryPanel = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="px-5 py-4">
            <div v-if="historyLoading" class="py-10 text-center text-gray-400 text-sm">Chargement…</div>
            <div v-else-if="historyEntries.length === 0" class="py-10 text-center text-gray-400 text-sm">Aucun historique.</div>
            <div v-else class="space-y-4">
              <div v-for="entry in historyEntries" :key="entry.id" class="relative pl-6">
                <span class="absolute left-0 top-1.5 w-2.5 h-2.5 rounded-full"
                      :class="{
                        'bg-green-500': entry.action === 'created',
                        'bg-blue-500':  entry.action === 'updated',
                        'bg-amber-500': entry.action === 'archived',
                        'bg-gray-500':  entry.action === 'unarchived',
                      }"></span>
                <div class="border-l border-gray-200 dark:border-gray-700 pl-4 pb-4">
                  <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                    {{ ACTION_LABELS[entry.action] ?? entry.action }}
                  </p>
                  <p class="text-xs text-gray-400">{{ entry.created_at }}<template v-if="entry.user"> par {{ entry.user }}</template></p>
                  <div v-if="entry.changes && entry.changes.length > 0" class="mt-2 space-y-1">
                    <div v-for="change in entry.changes" :key="change.field" class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800 rounded px-2 py-1">
                      <span class="font-mono font-semibold">{{ change.field }}</span>:
                      <span class="text-red-500 line-through">{{ change.before ?? '—' }}</span>
                      <span class="mx-1">→</span>
                      <span class="text-green-600">{{ change.after ?? '—' }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ─── Modal : Constructeur de formulaire ────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showFormBuilder"
           class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-2xl overflow-y-auto max-h-[90vh]">
          <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
              <Cog6ToothIcon class="w-5 h-5 text-blue-600" />
              Personnaliser le formulaire
            </h2>
            <button @click="showFormBuilder = false" class="p-1.5 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="px-5 py-4 space-y-4">

            <!-- Liste champs existants -->
            <div v-if="builderFields.length === 0" class="text-sm text-gray-400 text-center py-4">
              Aucun champ personnalisé. Ajoutez-en ci-dessous.
            </div>
            <div v-else class="space-y-2">
              <div v-for="(ff, idx) in builderFields" :key="idx"
                   class="flex items-center gap-2 bg-gray-50 dark:bg-gray-800 rounded-lg px-3 py-2 border border-gray-200 dark:border-gray-700">
                <div class="flex flex-col gap-0.5">
                  <button @click="moveBuilderField(idx, -1)" :disabled="idx === 0"
                          class="p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30">
                    <ChevronUpIcon class="w-3.5 h-3.5" />
                  </button>
                  <button @click="moveBuilderField(idx, 1)" :disabled="idx === builderFields.length - 1"
                          class="p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30">
                    <ChevronDownIcon class="w-3.5 h-3.5" />
                  </button>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-gray-800 dark:text-gray-200 truncate">{{ ff.label }}</p>
                  <div class="flex items-center gap-2 mt-0.5">
                    <span class="text-xs px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded">
                      {{ FIELD_TYPE_LABELS[ff.type] ?? ff.type }}
                    </span>
                    <span v-if="ff.required" class="text-xs text-red-500">Requis</span>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <label class="flex items-center gap-1 text-xs text-gray-500 cursor-pointer">
                    <input type="checkbox" v-model="ff.active" class="rounded text-blue-600" />
                    Actif
                  </label>
                  <label class="flex items-center gap-1 text-xs text-gray-500 cursor-pointer">
                    <input type="checkbox" v-model="ff.required" class="rounded text-blue-600" />
                    Requis
                  </label>
                  <button @click="removeBuilderField(idx)" class="p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded">
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Ajouter un champ -->
            <div class="border-t border-gray-100 dark:border-gray-800 pt-4">
              <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Ajouter un champ</p>
              <p v-if="builderError" class="text-xs text-red-600 mb-2">{{ builderError }}</p>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Type</label>
                  <select v-model="newFieldType" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800">
                    <option v-for="(label, val) in FIELD_TYPE_LABELS" :key="val" :value="val">{{ label }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Libellé</label>
                  <input v-model="newFieldLabel" type="text" placeholder="Ex: Budget prévisionnel"
                         class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800" />
                </div>
              </div>
              <div v-if="needsOptions" class="mt-3">
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Options (une par ligne)</label>
                <textarea v-model="newFieldOptions" rows="3" placeholder="Option 1&#10;Option 2&#10;Option 3"
                          class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 resize-none" />
              </div>
              <div class="flex items-center gap-3 mt-3">
                <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                  <input type="checkbox" v-model="newFieldRequired" class="rounded text-blue-600" />
                  Requis
                </label>
                <button @click="addBuilderField()"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
                  <PlusIcon class="w-4 h-4" />
                  Ajouter ce champ
                </button>
              </div>
            </div>

            <!-- Boutons -->
            <div class="flex justify-between gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
              <button type="button" @click="showFormBuilder = false"
                      class="px-4 py-2 text-sm text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition">
                Annuler
              </button>
              <button @click="saveFormBuilder()"
                      class="px-5 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
                Enregistrer le formulaire
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  </AppLayout>
</template>
