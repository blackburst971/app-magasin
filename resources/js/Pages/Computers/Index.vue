<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
  ComputerDesktopIcon, PlusIcon, PencilIcon, TrashIcon,
  XMarkIcon, MagnifyingGlassIcon, KeyIcon, PhotoIcon,
  CubeIcon, WifiIcon, MinusCircleIcon,
  CheckCircleIcon, Square2StackIcon,
  Squares2X2Icon, ListBulletIcon,
} from '@heroicons/vue/24/outline'
import { useViewMode } from '@/composables/useViewMode'

const props = defineProps({
  computers:          Array,
  availableAssets:    Array,
  places:             Array,
  isAdmin:            Boolean,
  canEdit:            { type: Boolean, default: false },
  canRequestIpChange: { type: Boolean, default: false },
  editableFields:     { type: Array, default: () => [] },
})

/* ── Listes d'options ────────────────────────────────────────────────────── */
const typeOptions = [
  { value: 'desktop',    label: 'PC de bureau',  icon: '🖥️' },
  { value: 'laptop',     label: 'PC portable',   icon: '💻' },
  { value: 'imac',       label: 'iMac',          icon: '🖥️' },
  { value: 'macbook',    label: 'MacBook',       icon: '💻' },
  { value: 'mac_mini',   label: 'Mac mini',      icon: '🟫' },
  { value: 'mac_studio', label: 'Mac Studio',    icon: '🟫' },
  { value: 'server',     label: 'Serveur',       icon: '🗄️' },
  { value: 'tablet',     label: 'Tablette',      icon: '📱' },
  { value: 'other',      label: 'Autre',         icon: '💻' },
]
const osOptions = [
  { value: 'windows_10',     label: 'Windows 10',     color: 'blue' },
  { value: 'windows_11',     label: 'Windows 11',     color: 'blue' },
  { value: 'windows_server', label: 'Windows Server', color: 'indigo' },
  { value: 'macos',          label: 'macOS',           color: 'gray' },
  { value: 'linux',          label: 'Linux',           color: 'orange' },
  { value: 'chromeos',       label: 'ChromeOS',        color: 'teal' },
  { value: 'other',          label: 'Autre',           color: 'gray' },
]

const typeIcon  = (t)  => typeOptions.find(o => o.value === t)?.icon ?? '💻'
const typeLabel = (t)  => typeOptions.find(o => o.value === t)?.label ?? t
const osLabel   = (os) => osOptions.find(o => o.value === os)?.label ?? os
const osBadge   = (os) => ({
  windows_10:     'bg-blue-100 text-blue-700',
  windows_11:     'bg-blue-100 text-blue-700',
  windows_server: 'bg-indigo-100 text-indigo-700',
  macos:          'bg-gray-100 text-gray-700',
  linux:          'bg-orange-100 text-orange-700',
  chromeos:       'bg-teal-100 text-teal-700',
  other:          'bg-gray-100 text-gray-500',
})[os] ?? 'bg-gray-100 text-gray-500'

const typeLicenseBadge = (type) => ({
  per_seat:    'bg-blue-100 text-blue-700',
  volume:      'bg-purple-100 text-purple-700',
  oem:         'bg-orange-100 text-orange-700',
  cloud:       'bg-sky-100 text-sky-700',
  dongle:      'bg-yellow-100 text-yellow-700',
  open_source: 'bg-green-100 text-green-700',
})[type] ?? 'bg-gray-100 text-gray-600'

const typeLicenseLabel = (type) => ({
  per_seat: 'Par poste', volume: 'Volume', oem: 'OEM',
  cloud: 'Cloud', dongle: 'Dongle', open_source: 'OS',
})[type] ?? type

/* ── Filtres ─────────────────────────────────────────────────────────────── */
const search     = ref('')
const filterType = ref('')
const filterOs   = ref('')

const filtered = computed(() => {
  let list = props.computers
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(c =>
      c.name.toLowerCase().includes(q) ||
      (c.asset_tag ?? '').toLowerCase().includes(q) ||
      (c.serial_number ?? '').toLowerCase().includes(q) ||
      (c.ip_addresses ?? []).some(e => (e.ip ?? '').toLowerCase().includes(q))
    )
  }
  if (filterType.value) list = list.filter(c => c.type === filterType.value)
  if (filterOs.value)   list = list.filter(c => c.os === filterOs.value)
  return list
})

/* ── Mode d'affichage ────────────────────────────────────────────────────── */
const viewMode = useViewMode('computers_view', 'grid')

/* ── Modal Détail ─────────────────────────────────────────────────────────── */
const showDetail     = ref(false)
const detailComputer = ref(null)
function openDetail(c) { detailComputer.value = c; showDetail.value = true }
function closeDetail()  { showDetail.value = false; detailComputer.value = null }

/* ── Modal Créer / Modifier ───────────────────────────────────────────────── */
const showFormModal = ref(false)
const editing       = ref(null)
const processing    = ref(false)
const photoPreview  = ref(null)
const removePhoto   = ref(false)
const photoFile     = ref(null)

// Mode création : 'manual' ou 'from_asset'
const createMode      = ref('manual')
const assetSearch     = ref('')
const selectedAssets  = ref([])  // multi-select

const filteredAssets = computed(() => {
  const q = assetSearch.value.toLowerCase()
  return props.availableAssets.filter(a =>
    (!q ||
      a.name.toLowerCase().includes(q) ||
      (a.asset_tag ?? '').toLowerCase().includes(q) ||
      (a.serial ?? '').toLowerCase().includes(q))
  )
})

const selectedAssetIds = computed(() => new Set(selectedAssets.value.map(a => a.id)))

function toggleAsset(a) {
  const idx = selectedAssets.value.findIndex(s => s.id === a.id)
  if (idx === -1) selectedAssets.value.push(a)
  else selectedAssets.value.splice(idx, 1)
}
function removeSelectedAsset(id) {
  selectedAssets.value = selectedAssets.value.filter(a => a.id !== id)
}
function clearSelectedAssets() {
  selectedAssets.value = []
}

const emptyForm = () => ({
  name: '', place_id: null, asset_tag: '', type: 'desktop', os: 'windows_11',
  os_version: '', serial_number: '', ip_addresses: [],
  processor: '', ram: '', notes: '',
})

function addIp() {
  form.value.ip_addresses.push({
    ip: '',
    description: '',
    mac_address: '',
    subnet_mask: '255.255.255.0',
    gateway: '',
    dns_primary: '',
    dns_secondary: ''
  })
}
function removeIp(index) {
  form.value.ip_addresses.splice(index, 1)
}
const form = ref(emptyForm())

function openCreate() {
  editing.value       = null
  createMode.value    = 'manual'
  selectedAssets.value = []
  assetSearch.value   = ''
  form.value          = emptyForm()
  photoPreview.value  = null
  removePhoto.value   = false
  photoFile.value     = null
  showFormModal.value = true
}
function openEdit(c) {
  editing.value = c
  form.value = {
    name:          c.name ?? '',
    place_id:      c.place_id ?? null,
    asset_tag:     c.asset_tag ?? '',
    type:          c.type,
    os:            c.os,
    os_version:    c.os_version ?? '',
    serial_number: c.serial_number ?? '',
    ip_addresses:  (c.ip_addresses ?? []).map(e => ({
      ip: e.ip ?? '',
      description: e.description ?? '',
      mac_address: e.mac_address ?? '',
      subnet_mask: e.subnet_mask ?? '255.255.255.0',
      gateway: e.gateway ?? '',
      dns_primary: e.dns_primary ?? '',
      dns_secondary: e.dns_secondary ?? ''
    })),
    processor:     c.processor ?? '',
    ram:           c.ram ?? '',
    notes:         c.notes ?? '',
  }
  photoPreview.value  = c.photo_url ?? null
  removePhoto.value   = false
  photoFile.value     = null
  showFormModal.value = true
}
function closeFormModal() {
  showFormModal.value  = false
  editing.value        = null
  selectedAssets.value = []
}

function onPhotoChange(e) {
  const file = e.target.files[0]
  if (!file) return
  photoFile.value   = file
  removePhoto.value = false
  const reader = new FileReader()
  reader.onload = (ev) => { photoPreview.value = ev.target.result }
  reader.readAsDataURL(file)
}
function clearPhoto() {
  photoPreview.value = null
  photoFile.value    = null
  removePhoto.value  = true
}

function submitForm() {
  // ── Modification non-admin (demande de changement réseau) ─────────────
  if (editing.value && !props.isAdmin) {
    processing.value = true
    // Envoyer toutes les configurations réseau complètes
    const payload = {
      ip_addresses: JSON.stringify(form.value.ip_addresses.filter(e => e.ip.trim())),
      ip_change_notes: form.value.notes || ''
    }
    router.post(route('computers.request-ip-change', editing.value.id), payload, {
      onSuccess: closeFormModal,
      onFinish:  () => { processing.value = false },
    })
    return
  }

  // ── Batch depuis l'inventaire ─────────────────────────────────────────
  if (!editing.value && createMode.value === 'from_asset') {
    if (selectedAssets.value.length === 0) {
      alert('Veuillez sélectionner au moins un équipement.')
      return
    }
    processing.value = true
    router.post(route('computers.batch-store'), {
      asset_ids:  selectedAssets.value.map(a => a.id),
      type:       form.value.type,
      os:         form.value.os,
      os_version: form.value.os_version,
      processor:  form.value.processor,
      ram:        form.value.ram,
      notes:      form.value.notes,
    }, {
      onSuccess: closeFormModal,
      onFinish:  () => { processing.value = false },
    })
    return
  }

  // ── Création manuelle ou modification admin ───────────────────────────
  processing.value = true
  const fd = new FormData()
  Object.entries(form.value).forEach(([k, v]) => {
    if (k === 'ip_addresses') {
      fd.append('ip_addresses', JSON.stringify(v.filter(e => e.ip.trim())))
    } else if (v !== null && v !== undefined) {
      fd.append(k, v)
    }
  })
  if (photoFile.value) fd.append('photo', photoFile.value)
  if (removePhoto.value) fd.append('remove_photo', '1')

  if (editing.value) {
    fd.append('_method', 'PUT')
    router.post(route('computers.update', editing.value.id), fd, {
      forceFormData: true,
      onSuccess: closeFormModal,
      onFinish:  () => { processing.value = false },
    })
  } else {
    fd.append('create_mode', 'manual')
    router.post(route('computers.store'), fd, {
      forceFormData: true,
      onSuccess: closeFormModal,
      onFinish:  () => { processing.value = false },
    })
  }
}

function confirmDelete(c) {
  if (confirm(`Supprimer le poste « ${c.name} » ?`)) {
    router.delete(route('computers.destroy', c.id))
  }
}

/* ── Sélection multiple & Bulk Edit (admin) ──────────────────────────────── */
const selectionMode       = ref(false)
const selectedComputerIds = ref(new Set())

function toggleSelectionMode() {
  selectionMode.value = !selectionMode.value
  if (!selectionMode.value) selectedComputerIds.value = new Set()
}
function toggleSelectComputer(id) {
  const s = new Set(selectedComputerIds.value)
  if (s.has(id)) s.delete(id)
  else s.add(id)
  selectedComputerIds.value = s
}
function selectAllFiltered() {
  selectedComputerIds.value = new Set(filtered.value.map(c => c.id))
}
function clearSelection() {
  selectedComputerIds.value = new Set()
}

// Vider la sélection dès qu'un filtre change
watch([search, filterType, filterOs], () => {
  selectedComputerIds.value = new Set()
})
const selectedCount = computed(() => selectedComputerIds.value.size)
const allFilteredSelected = computed(() =>
  filtered.value.length > 0 && filtered.value.every(c => selectedComputerIds.value.has(c.id))
)

// ── Modal Bulk Edit ──────────────────────────────────────────────────────────
const showBulkModal  = ref(false)
const bulkProcessing = ref(false)

const bulkFields = ref({
  type:       { enabled: false, value: 'desktop' },
  os:         { enabled: false, value: 'windows_11' },
  os_version: { enabled: false, value: '' },
  processor:  { enabled: false, value: '' },
  ram:        { enabled: false, value: '' },
  notes:      { enabled: false, value: '' },
})

function openBulkEdit() {
  // Réinitialiser le formulaire bulk
  bulkFields.value = {
    type:       { enabled: false, value: 'desktop' },
    os:         { enabled: false, value: 'windows_11' },
    os_version: { enabled: false, value: '' },
    processor:  { enabled: false, value: '' },
    ram:        { enabled: false, value: '' },
    notes:      { enabled: false, value: '' },
  }
  showBulkModal.value = true
}

function submitBulkEdit() {
  const fields = Object.entries(bulkFields.value)
    .filter(([, f]) => f.enabled)
    .map(([k]) => k)

  if (fields.length === 0) {
    alert('Sélectionnez au moins un champ à modifier.')
    return
  }

  const payload = {
    ids:    Array.from(selectedComputerIds.value),
    fields,
  }
  fields.forEach(f => { payload[f] = bulkFields.value[f].value })

  bulkProcessing.value = true
  router.post(route('computers.bulk-update'), payload, {
    onSuccess: () => {
      showBulkModal.value       = false
      selectionMode.value       = false
      selectedComputerIds.value = new Set()
    },
    onFinish: () => { bulkProcessing.value = false },
  })
}

function confirmBulkDelete() {
  const n = selectedCount.value
  if (!confirm(`Supprimer ${n} poste${n > 1 ? 's' : ''} définitivement ?`)) return
  bulkProcessing.value = true
  router.post(route('computers.bulk-delete'), {
    ids: Array.from(selectedComputerIds.value),
  }, {
    onSuccess: () => {
      selectionMode.value       = false
      selectedComputerIds.value = new Set()
    },
    onFinish: () => { bulkProcessing.value = false },
  })
}
</script>

<template>
  <AppLayout>
    <div class="space-y-6">

      <!-- En-tête -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
            <ComputerDesktopIcon class="w-5 h-5 text-indigo-600" />
          </div>
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Postes informatiques</h1>
            <p class="text-sm text-gray-500">{{ computers.length }} poste{{ computers.length > 1 ? 's' : '' }} enregistré{{ computers.length > 1 ? 's' : '' }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Lien adressage IP -->
          <a :href="route('computers.ip-addressing')"
            class="flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 border border-indigo-200 hover:border-indigo-400 px-3 py-2 rounded-lg transition">
            <WifiIcon class="w-4 h-4" />
            Adressage IP
          </a>
          <!-- Toggle vue -->
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
            <!-- Bouton sélection multiple -->
            <button @click="toggleSelectionMode"
              class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium border transition"
              :class="selectionMode
                ? 'bg-violet-600 text-white border-violet-600 hover:bg-violet-700'
                : 'border-gray-300 text-gray-700 hover:bg-gray-50'">
              <Square2StackIcon class="w-4 h-4" />
              {{ selectionMode ? 'Annuler sélection' : 'Sélectionner' }}
            </button>
            <a :href="route('reports.export-csv-computers')"
               class="flex items-center gap-2 border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm font-medium transition">
              ⬇ CSV
            </a>
            <button @click="openCreate"
              class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium transition">
              <PlusIcon class="w-4 h-4" /> Nouveau poste
            </button>
          </template>
        </div>
      </div>

      <!-- Filtres -->
      <div class="bg-white rounded-xl border p-4 flex flex-wrap gap-3">
        <div class="relative flex-1 min-w-48">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
          <input v-model="search" type="text" placeholder="Nom, tag, IP, numéro de série..."
            class="w-full pl-9 pr-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none" />
        </div>
        <select v-model="filterType"
          class="px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
          <option value="">Tous les types</option>
          <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.icon }} {{ t.label }}</option>
        </select>
        <select v-model="filterOs"
          class="px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none">
          <option value="">Tous les OS</option>
          <option v-for="o in osOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
        </select>
      </div>

      <!-- Barre de sélection rapide (mode sélection actif) -->
      <div v-if="selectionMode" class="bg-violet-50 border border-violet-200 rounded-xl px-4 py-2.5 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-violet-700 select-none">
            <input type="checkbox"
              :checked="allFilteredSelected"
              @change="allFilteredSelected ? clearSelection() : selectAllFiltered()"
              class="w-4 h-4 rounded border-violet-400 text-violet-600 focus:ring-violet-500" />
            Tout sélectionner ({{ filtered.length }})
          </label>
          <span v-if="selectedCount > 0" class="text-xs text-violet-600 font-medium bg-violet-100 px-2 py-0.5 rounded-full">
            {{ selectedCount }} sélectionné{{ selectedCount > 1 ? 's' : '' }}
          </span>
        </div>
        <button v-if="selectedCount > 0" @click="clearSelection"
          class="text-xs text-violet-400 hover:text-violet-600 transition">
          Tout désélectionner
        </button>
      </div>

      <!-- Grille de cartes / Vue liste -->
      <div v-if="filtered.length === 0" class="bg-white rounded-xl border py-16 text-center text-gray-400">
        <ComputerDesktopIcon class="w-10 h-10 mx-auto mb-3 opacity-30" />
        <p class="text-sm">Aucun poste trouvé</p>
      </div>
      <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <div v-for="c in filtered" :key="c.id"
          class="bg-white rounded-xl border hover:shadow-md transition-shadow overflow-hidden group"
          :class="selectionMode && selectedComputerIds.has(c.id) ? 'ring-2 ring-violet-500 border-violet-300' : ''">

          <!-- Photo ou icône -->
          <div class="relative h-40 bg-gradient-to-br from-gray-50 to-gray-100 overflow-hidden"
            :class="selectionMode ? 'cursor-pointer' : 'cursor-pointer'"
            @click="selectionMode ? toggleSelectComputer(c.id) : openDetail(c)">
            <img v-if="c.photo_url" :src="c.photo_url" :alt="c.name"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
            <div v-else class="w-full h-full flex items-center justify-center">
              <span class="text-5xl opacity-40 select-none">{{ typeIcon(c.type) }}</span>
            </div>
            <!-- Checkbox de sélection -->
            <div v-if="selectionMode"
              class="absolute top-2 left-2 z-10">
              <div class="w-6 h-6 rounded-full flex items-center justify-center border-2 transition"
                :class="selectedComputerIds.has(c.id)
                  ? 'bg-violet-600 border-violet-600'
                  : 'bg-white/90 border-gray-300 hover:border-violet-400'">
                <CheckCircleIcon v-if="selectedComputerIds.has(c.id)" class="w-5 h-5 text-white" />
              </div>
            </div>
            <!-- Badge licences -->
            <div v-if="c.license_assignments_count > 0"
              class="absolute top-2 right-2 bg-blue-600 text-white text-xs font-bold px-2 py-0.5 rounded-full flex items-center gap-1">
              <KeyIcon class="w-3 h-3" />
              {{ c.license_assignments_count }}
            </div>
          </div>

          <!-- Infos -->
          <div class="p-4">
            <div class="flex items-start justify-between gap-2 mb-2">
              <div class="min-w-0">
                <h3 class="font-semibold text-gray-900 truncate cursor-pointer hover:text-indigo-600 transition"
                  @click="openDetail(c)">{{ c.name }}</h3>
                <p v-if="c.asset_tag" class="text-xs text-gray-400 font-mono">{{ c.asset_tag }}</p>
              </div>
              <div v-if="isAdmin || canEdit || canRequestIpChange" class="flex gap-1 flex-shrink-0">
                <button @click="openEdit(c)"
                  class="p-1 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded transition">
                  <PencilIcon class="w-3.5 h-3.5" />
                </button>
                <button v-if="isAdmin" @click="confirmDelete(c)"
                  class="p-1 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition">
                  <TrashIcon class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            <!-- OS + Type -->
            <div class="flex flex-wrap gap-1.5 mb-3">
              <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', osBadge(c.os)]">{{ osLabel(c.os) }}</span>
              <span class="text-xs text-gray-500 px-2 py-0.5 bg-gray-50 rounded-full">{{ typeLabel(c.type) }}</span>
            </div>

            <!-- Détails -->
            <div class="space-y-1 text-xs text-gray-500">
              <p v-if="c.processor" class="truncate">⚙️ {{ c.processor }}</p>
              <p v-if="c.ram">💾 {{ c.ram }}</p>
              <template v-if="c.ip_addresses && c.ip_addresses.length > 0">
                <p v-for="(e, i) in c.ip_addresses.slice(0,2)" :key="i" class="font-mono">
                  🌐 {{ e.ip }}<span v-if="e.description" class="font-sans text-gray-400"> — {{ e.description }}</span>
                </p>
                <p v-if="c.ip_addresses.length > 2" class="text-gray-400">+{{ c.ip_addresses.length - 2 }} adresse(s)</p>
              </template>
              <p v-if="c.serial_number" class="truncate font-mono text-gray-400">{{ c.serial_number }}</p>
            </div>

            <!-- Licences assignées (aperçu) -->
            <div v-if="c.licenses.length > 0" class="mt-3 pt-3 border-t">
              <p class="text-xs font-medium text-gray-500 mb-1.5">Licences installées :</p>
              <div class="flex flex-wrap gap-1">
                <span v-for="l in c.licenses.slice(0, 3)" :key="l.id"
                  :class="['text-xs px-1.5 py-0.5 rounded', l.is_expired ? 'bg-red-100 text-red-600' : typeLicenseBadge(l.type)]"
                  :title="l.name + (l.version ? ' v'+l.version : '')">
                  {{ l.name.slice(0, 12) }}{{ l.name.length > 12 ? '…' : '' }}
                </span>
                <span v-if="c.licenses.length > 3" class="text-xs text-gray-400">+{{ c.licenses.length - 3 }}</span>
              </div>
            </div>
            <p v-else class="mt-3 pt-3 border-t text-xs text-gray-400 italic">Aucune licence attribuée</p>
          </div>
        </div>
      </div>

      <!-- Vue liste -->
      <div v-else-if="viewMode === 'list'" class="bg-white rounded-xl border overflow-hidden">
        <div class="overflow-x-auto">
          <table class="divide-y divide-gray-100" style="min-width:max-content;width:100%">
          <thead class="bg-gray-50">
            <tr>
              <th class="w-10 px-3 py-2.5"></th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Poste</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Système</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Adresse IP</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Config.</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Licences</th>
              <th class="px-4 py-2.5"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="c in filtered" :key="c.id"
              class="hover:bg-gray-50 transition cursor-pointer"
              :class="selectionMode && selectedComputerIds.has(c.id) ? 'bg-violet-50' : ''"
              @click="selectionMode ? toggleSelectComputer(c.id) : openDetail(c)">
              <!-- Photo / sélection -->
              <td class="px-3 py-2.5">
                <div class="relative w-10 h-10 flex-shrink-0">
                  <img v-if="c.photo_url" :src="c.photo_url" :alt="c.name"
                    class="w-10 h-10 rounded-lg object-cover" />
                  <div v-else class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-xl">
                    {{ typeIcon(c.type) }}
                  </div>
                  <!-- checkbox overlay -->
                  <div v-if="selectionMode" class="absolute -top-1 -left-1">
                    <div class="w-5 h-5 rounded-full flex items-center justify-center border-2 transition shadow-sm"
                      :class="selectedComputerIds.has(c.id) ? 'bg-violet-600 border-violet-600' : 'bg-white border-gray-300'">
                      <CheckCircleIcon v-if="selectedComputerIds.has(c.id)" class="w-4 h-4 text-white" />
                    </div>
                  </div>
                </div>
              </td>
              <!-- Nom + tag + emplacement -->
              <td class="px-4 py-2.5">
                <p class="font-semibold text-gray-900 text-sm">{{ c.name }}</p>
                <p v-if="c.place_name" class="text-xs text-gray-500 flex items-center gap-1 mt-0.5">
                  📍 {{ c.place_name }}
                </p>
                <p v-if="c.asset_tag" class="text-xs font-mono text-gray-400">{{ c.asset_tag }}</p>
              </td>
              <!-- OS + type -->
              <td class="px-4 py-2.5">
                <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', osBadge(c.os)]">{{ osLabel(c.os) }}</span>
                <p class="text-xs text-gray-400 mt-0.5">{{ typeLabel(c.type) }}</p>
              </td>
              <!-- IP -->
              <td class="px-4 py-2.5">
                <template v-if="c.ip_addresses && c.ip_addresses.length > 0">
                  <p class="font-mono text-sm text-gray-700">{{ c.ip_addresses[0].ip }}</p>
                  <p v-if="c.ip_addresses[0].description" class="text-xs text-gray-400">{{ c.ip_addresses[0].description }}</p>
                  <p v-if="c.ip_addresses.length > 1" class="text-xs text-indigo-500">+{{ c.ip_addresses.length - 1 }}</p>
                </template>
                <span v-else class="text-xs text-gray-300">—</span>
              </td>
              <!-- Config (hidden on small screens) -->
              <td class="px-4 py-2.5 hidden lg:table-cell">
                <p v-if="c.processor" class="text-xs text-gray-600 truncate max-w-[160px]">{{ c.processor }}</p>
                <p v-if="c.ram" class="text-xs text-gray-400">{{ c.ram }}</p>
              </td>
              <!-- Licences -->
              <td class="px-4 py-2.5 text-center">
                <span v-if="c.license_assignments_count > 0"
                  class="inline-flex items-center gap-1 text-xs bg-blue-100 text-blue-700 font-semibold px-2 py-0.5 rounded-full">
                  <KeyIcon class="w-3 h-3" />
                  {{ c.license_assignments_count }}
                </span>
                <span v-else class="text-xs text-gray-300">—</span>
              </td>
              <!-- Actions -->
              <td class="px-3 py-2.5" @click.stop>
                <div v-if="isAdmin || canEdit || canRequestIpChange" class="flex gap-1 justify-end">
                  <button @click="openEdit(c)"
                    class="p-1.5 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition">
                    <PencilIcon class="w-4 h-4" />
                  </button>
                  <button v-if="isAdmin" @click="confirmDelete(c)"
                    class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition">
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <!-- ── Modal Détail ────────────────────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showDetail && detailComputer" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeDetail" />
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <!-- Photo header -->
          <div class="h-48 bg-gradient-to-br from-indigo-50 to-gray-100 overflow-hidden rounded-t-2xl relative">
            <img v-if="detailComputer.photo_url" :src="detailComputer.photo_url"
              class="w-full h-full object-cover" :alt="detailComputer.name" />
            <div v-else class="w-full h-full flex items-center justify-center">
              <span class="text-8xl opacity-30">{{ typeIcon(detailComputer.type) }}</span>
            </div>
            <button @click="closeDetail" class="absolute top-3 right-3 p-1.5 bg-white/80 rounded-lg text-gray-600 hover:bg-white">
              <XMarkIcon class="w-4 h-4" />
            </button>
            <!-- Badge inventaire dans le détail -->
            <div v-if="detailComputer.asset_id"
              class="absolute bottom-3 left-3 text-xs font-semibold px-2.5 py-1 rounded-full flex items-center gap-1.5"
              :class="detailComputer.asset_is_reservable ? 'bg-green-600 text-white' : 'bg-gray-800/80 text-white'">
              <CubeIcon class="w-3.5 h-3.5" />
              {{ detailComputer.asset_is_reservable ? 'Réservable par les utilisateurs' : 'Non réservable (inventaire uniquement)' }}
            </div>
          </div>
          <div class="p-6">
            <div class="flex items-start gap-3 mb-5">
              <div>
                <h2 class="text-xl font-bold text-gray-900">{{ detailComputer.name }}</h2>
                <div class="flex items-center gap-2 mt-1 flex-wrap">
                  <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', osBadge(detailComputer.os)]">{{ osLabel(detailComputer.os) }}</span>
                  <span v-if="detailComputer.os_version" class="text-xs text-gray-400">{{ detailComputer.os_version }}</span>
                  <span class="text-xs text-gray-500">· {{ typeLabel(detailComputer.type) }}</span>
                </div>
              </div>
            </div>
            <!-- Infos techniques -->
            <div class="grid grid-cols-2 gap-3 mb-5 text-sm">
              <div v-if="detailComputer.processor">
                <p class="text-xs text-gray-400">Processeur</p>
                <p class="font-medium text-gray-800">{{ detailComputer.processor }}</p>
              </div>
              <div v-if="detailComputer.ram">
                <p class="text-xs text-gray-400">Mémoire RAM</p>
                <p class="font-medium text-gray-800">{{ detailComputer.ram }}</p>
              </div>
              <div v-if="detailComputer.serial_number">
                <p class="text-xs text-gray-400">Numéro de série</p>
                <p class="font-mono text-xs text-gray-700">{{ detailComputer.serial_number }}</p>
              </div>
              <div v-if="detailComputer.ip_addresses && detailComputer.ip_addresses.length > 0" class="col-span-2">
                <p class="text-xs text-gray-400 mb-1">Adresses IP</p>
                <div class="space-y-1">
                  <div v-for="(e, i) in detailComputer.ip_addresses" :key="i"
                    class="flex items-center gap-2 bg-indigo-50 px-2.5 py-1 rounded-lg">
                    <WifiIcon class="w-3.5 h-3.5 text-indigo-400 flex-shrink-0" />
                    <span class="font-mono text-sm font-semibold text-indigo-700">{{ e.ip }}</span>
                    <span v-if="e.description" class="text-xs text-gray-500 truncate">— {{ e.description }}</span>
                  </div>
                </div>
              </div>
              <div v-if="detailComputer.mac_address">
                <p class="text-xs text-gray-400">Adresse MAC</p>
                <p class="font-mono text-sm text-gray-700">{{ detailComputer.mac_address }}</p>
              </div>
              <div v-if="detailComputer.subnet_mask">
                <p class="text-xs text-gray-400">Masque de sous-réseau</p>
                <p class="font-mono text-sm text-gray-700">{{ detailComputer.subnet_mask }}</p>
              </div>
              <div v-if="detailComputer.gateway">
                <p class="text-xs text-gray-400">Passerelle</p>
                <p class="font-mono text-sm text-gray-700">{{ detailComputer.gateway }}</p>
              </div>
              <div v-if="detailComputer.dns_primary">
                <p class="text-xs text-gray-400">DNS Primaire</p>
                <p class="font-mono text-sm text-gray-700">{{ detailComputer.dns_primary }}</p>
              </div>
              <div v-if="detailComputer.dns_secondary">
                <p class="text-xs text-gray-400">DNS Secondaire</p>
                <p class="font-mono text-sm text-gray-700">{{ detailComputer.dns_secondary }}</p>
              </div>
              <div v-if="detailComputer.asset_tag">
                <p class="text-xs text-gray-400">Tag</p>
                <p class="text-gray-700 font-mono">{{ detailComputer.asset_tag }}</p>
              </div>
            </div>
            <!-- Licences -->
            <div class="border-t pt-4">
              <h3 class="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <KeyIcon class="w-4 h-4 text-blue-500" />
                Licences installées ({{ detailComputer.licenses.length }})
              </h3>
              <div v-if="detailComputer.licenses.length === 0" class="text-xs text-gray-400 italic">
                Aucune licence attribuée à ce poste.
              </div>
              <div v-else class="space-y-2">
                <div v-for="l in detailComputer.licenses" :key="l.id"
                  class="flex items-center justify-between p-2.5 rounded-lg"
                  :class="l.is_expired ? 'bg-red-50' : 'bg-gray-50'">
                  <div>
                    <p class="text-sm font-medium text-gray-900">
                      {{ l.name }}
                      <span v-if="l.version" class="text-xs font-normal text-gray-400"> v{{ l.version }}</span>
                    </p>
                    <div class="flex items-center gap-2 mt-0.5">
                      <span :class="['text-xs px-1.5 py-0.5 rounded', typeLicenseBadge(l.type)]">{{ typeLicenseLabel(l.type) }}</span>
                      <span v-if="l.is_expired" class="text-xs text-red-500">Expirée</span>
                      <span v-else-if="l.expiry_date" class="text-xs text-gray-400">Exp. {{ l.expiry_date }}</span>
                    </div>
                  </div>
                  <p class="text-xs text-gray-400">{{ l.assigned_at }}</p>
                </div>
              </div>
            </div>
            <!-- Notes -->
            <div v-if="detailComputer.notes" class="border-t pt-4 mt-4">
              <p class="text-xs text-gray-400 mb-1">Notes</p>
              <p class="text-sm text-gray-700">{{ detailComputer.notes }}</p>
            </div>
            <!-- Actions -->
            <div v-if="isAdmin || canEdit || canRequestIpChange" class="flex gap-3 mt-5 pt-4 border-t">
              <button @click="openEdit(detailComputer); closeDetail()"
                class="flex-1 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition flex items-center justify-center gap-2">
                <PencilIcon class="w-4 h-4" /> Modifier
              </button>
              <button v-if="isAdmin" @click="confirmDelete(detailComputer); closeDetail()"
                class="py-2 px-4 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition flex items-center justify-center gap-2">
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Modal Créer / Modifier ────────────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="(isAdmin || (canEdit && editing) || (canRequestIpChange && editing)) && showFormModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeFormModal" />
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between p-6 border-b">
            <h2 class="text-lg font-bold text-gray-900">
              {{ editing ? 'Modifier le poste' : 'Nouveau poste' }}
            </h2>
            <button @click="closeFormModal" class="text-gray-400 hover:text-gray-600">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Sélecteur de mode (création — admin uniquement) -->
          <div v-if="!editing && isAdmin" class="px-6 pt-5 pb-0">
            <div class="flex rounded-xl overflow-hidden border border-gray-200 divide-x divide-gray-200">
              <button type="button" @click="createMode = 'manual'; clearSelectedAssets()"
                class="flex-1 flex items-center justify-center gap-2 py-3 text-sm font-semibold transition"
                :class="createMode === 'manual' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50'">
                <PlusIcon class="w-4 h-4" />
                Créer manuellement
              </button>
              <button type="button" @click="createMode = 'from_asset'"
                class="flex-1 flex items-center justify-center gap-2 py-3 text-sm font-semibold transition"
                :class="createMode === 'from_asset' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50'">
                <CubeIcon class="w-4 h-4" />
                Depuis l'inventaire
              </button>
            </div>

            <!-- Sélecteur multi-équipements -->
            <div v-if="createMode === 'from_asset'" class="mt-4 space-y-3">

              <!-- Équipements sélectionnés (tags) -->
              <div v-if="selectedAssets.length > 0"
                class="p-3 bg-indigo-50 border border-indigo-200 rounded-xl">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-xs font-semibold text-indigo-700">
                    {{ selectedAssets.length }} équipement{{ selectedAssets.length > 1 ? 's' : '' }} sélectionné{{ selectedAssets.length > 1 ? 's' : '' }}
                  </span>
                  <button type="button" @click="clearSelectedAssets"
                    class="text-xs text-indigo-400 hover:text-red-500 transition">
                    Tout désélectionner
                  </button>
                </div>
                <div class="flex flex-wrap gap-1.5">
                  <span v-for="a in selectedAssets" :key="a.id"
                    class="flex items-center gap-1 bg-white border border-indigo-200 text-indigo-800 text-xs font-medium px-2 py-1 rounded-lg">
                    {{ a.name }}
                    <span class="text-indigo-400 font-mono text-[10px]">{{ a.asset_tag }}</span>
                    <button type="button" @click="removeSelectedAsset(a.id)"
                      class="ml-0.5 text-indigo-300 hover:text-red-500 transition">
                      <XMarkIcon class="w-3 h-3" />
                    </button>
                  </span>
                </div>
              </div>

              <!-- Recherche + liste avec checkboxes -->
              <div>
                <div class="flex items-center justify-between mb-1.5">
                  <label class="text-xs font-semibold text-gray-600">
                    Sélectionner les équipements <span class="text-red-500">*</span>
                  </label>
                  <span v-if="filteredAssets.length > 0" class="text-xs text-gray-400">
                    {{ filteredAssets.length }} disponible{{ filteredAssets.length > 1 ? 's' : '' }}
                  </span>
                </div>
                <div class="relative mb-2">
                  <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
                  <input v-model="assetSearch" type="text" placeholder="Filtrer par nom, tag, numéro de série…"
                    class="w-full pl-9 pr-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none" />
                </div>
                <div class="max-h-52 overflow-y-auto border rounded-xl divide-y divide-gray-100">
                  <p v-if="filteredAssets.length === 0"
                    class="text-center text-gray-400 text-sm py-4 italic">
                    Aucun équipement disponible
                  </p>
                  <label v-for="a in filteredAssets" :key="a.id"
                    class="flex items-center gap-3 px-4 py-2.5 cursor-pointer transition"
                    :class="selectedAssetIds.has(a.id) ? 'bg-indigo-50 hover:bg-indigo-100' : 'hover:bg-gray-50'">
                    <input type="checkbox"
                      :checked="selectedAssetIds.has(a.id)"
                      @change="toggleAsset(a)"
                      class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 flex-shrink-0" />
                    <div class="flex-1 min-w-0">
                      <p class="font-medium text-sm text-gray-900 truncate">{{ a.name }}</p>
                      <p class="text-xs text-gray-400 font-mono">{{ a.asset_tag }}</p>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full flex-shrink-0"
                      :class="a.is_reservable ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                      {{ a.is_reservable ? 'Réservable' : 'Non réservable' }}
                    </span>
                  </label>
                </div>
                <p class="text-xs text-gray-400 mt-1.5">
                  ℹ️ Seuls les équipements non encore associés à un poste sont listés.
                </p>
              </div>

              <!-- Info batch -->
              <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
                ⚡ Les paramètres ci-dessous (type, OS, processeur, RAM) s'appliquent à <strong>tous les postes créés</strong>. Les noms, tags et numéros de série sont repris automatiquement depuis chaque équipement. Vous pourrez personnaliser chaque poste individuellement ensuite.
              </div>
            </div>

            <!-- Info mode manuel -->
            <div v-if="createMode === 'manual'" class="mt-3 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
              💡 Un équipement sera automatiquement créé dans l'inventaire avec le même nom. Il sera <strong>non réservable par défaut</strong> — vous pourrez modifier cela depuis la fiche équipement.
            </div>
          </div>

          <form @submit.prevent="submitForm" class="p-6 space-y-4">

            <!-- Bandeau info pour utilisateurs non-admin -->
            <div v-if="!isAdmin && editing" class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-700 mb-2">
              ✏️ Vous pouvez modifier uniquement les champs autorisés par l'administrateur. Nom, tag, série et photo sont gérés par l'admin.
            </div>

            <!-- Photo (masquée en mode from_asset — héritée de chaque équipement, et pour les non-admins) -->
            <div v-if="isAdmin && (editing || createMode !== 'from_asset')">
              <label class="block text-xs font-semibold text-gray-600 mb-2">Photo de l'appareil</label>
              <div class="flex items-center gap-4">
                <div class="w-24 h-24 rounded-xl bg-gray-100 overflow-hidden flex items-center justify-center border-2 border-dashed border-gray-300 flex-shrink-0">
                  <img v-if="photoPreview" :src="photoPreview" class="w-full h-full object-cover" />
                  <span v-else class="text-3xl opacity-30">{{ typeIcon(form.type) }}</span>
                </div>
                <div class="space-y-2">
                  <label class="cursor-pointer inline-flex items-center gap-2 px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-200 transition">
                    <PhotoIcon class="w-4 h-4" />
                    {{ photoPreview ? 'Changer la photo' : 'Ajouter une photo' }}
                    <input type="file" accept="image/*" @change="onPhotoChange" class="hidden" />
                  </label>
                  <button v-if="photoPreview" type="button" @click="clearPhoto"
                    class="block text-xs text-red-500 hover:text-red-700 transition">
                    Supprimer la photo
                  </button>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <!-- Nom / Tag / Série : masqués en from_asset et pour les non-admins -->
              <template v-if="isAdmin && (editing || createMode !== 'from_asset')">
                <div class="col-span-2">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Nom du poste *</label>
                  <input v-model="form.name" required type="text" placeholder="ex : PC-SALLE101-01"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
                </div>
                <div class="col-span-2">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">📍 Emplacement</label>
                  <select v-model="form.place_id"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                    <option :value="null">-- Aucun emplacement --</option>
                    <option v-for="place in places" :key="place.id" :value="place.id">{{ place.name }}</option>
                  </select>
                  <p class="text-xs text-gray-400 mt-1">Choisissez le lieu où se trouve physiquement le poste</p>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 mb-1">
                    Tag d'inventaire
                    <span class="font-normal text-gray-400">(auto-généré si vide)</span>
                  </label>
                  <input v-model="form.asset_tag" type="text" placeholder="ex : PC-0042"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Numéro de série</label>
                  <input v-model="form.serial_number" type="text"
                    class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none" />
                </div>
              </template>
              <!-- Type (admin uniquement) -->
              <div v-if="isAdmin">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Type *</label>
                <select v-model="form.type"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                  <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.icon }} {{ t.label }}</option>
                </select>
              </div>
              <div v-if="isAdmin || editableFields.includes('os')">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Système d'exploitation *</label>
                <select v-model="form.os"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                  <option v-for="o in osOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
              </div>
              <!-- Version OS -->
              <div v-if="isAdmin || editableFields.includes('os_version')" class="col-span-2 sm:col-span-1">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Version OS</label>
                <input v-model="form.os_version" type="text" placeholder="ex : 23H2"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
              </div>

              <!-- Adresses IP multiples -->
              <!-- Adresses IP : pour admin + pour non-admins en mode édition (demande de changement) -->
              <div v-if="(isAdmin && (editing || createMode !== 'from_asset')) || (!isAdmin && editing)" class="col-span-2">
                <!-- Bandeau info pour les non-admins -->
                <div v-if="!isAdmin && editing" class="mb-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
                  🔔 <strong>Demande de changement réseau :</strong> Les modifications d'adresses IP et de configuration réseau ne seront effectives qu'après validation par l'administrateur. Vous recevrez une notification une fois la demande traitée.
                </div>
                <div class="flex items-center justify-between mb-2">
                  <label class="text-xs font-semibold text-gray-600">
                    Adresses IP
                    <span class="font-normal text-gray-400">(plusieurs cartes réseau possibles)</span>
                  </label>
                  <button type="button" @click="addIp"
                    class="flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium px-2 py-1 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition">
                    <PlusIcon class="w-3.5 h-3.5" /> Ajouter une IP
                  </button>
                </div>
                <div v-if="form.ip_addresses.length === 0" class="text-xs text-gray-400 italic py-2 px-3 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                  Aucune adresse IP renseignée — cliquez sur "Ajouter une IP" pour en ajouter une.
                </div>
                <div class="space-y-4">
                  <div v-for="(entry, i) in form.ip_addresses" :key="i"
                    class="p-4 bg-gradient-to-br from-indigo-50/80 to-blue-50/50 border border-indigo-200 rounded-xl">
                    <!-- Header de la configuration -->
                    <div class="flex items-center justify-between mb-3">
                      <div class="flex items-center gap-2">
                        <WifiIcon class="w-5 h-5 text-indigo-500" />
                        <h4 class="text-sm font-semibold text-gray-700">Configuration réseau #{{ i + 1 }}</h4>
                      </div>
                      <button type="button" @click="removeIp(i)"
                        class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                        <MinusCircleIcon class="w-5 h-5" />
                      </button>
                    </div>

                    <!-- Grille de champs -->
                    <div class="grid grid-cols-2 gap-3">
                      <!-- Adresse IP -->
                      <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Adresse IP *</label>
                        <input v-model="entry.ip" type="text" placeholder="ex : 192.168.1.50"
                          class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none bg-white" />
                      </div>

                      <!-- Description -->
                      <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                        <input v-model="entry.description" type="text" placeholder="ex : Ethernet, WiFi…"
                          class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none bg-white" />
                      </div>

                      <!-- Adresse MAC (admin only pour modification) -->
                      <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                          Adresse MAC
                          <span v-if="!isAdmin" class="text-xs text-amber-600">(lecture seule)</span>
                        </label>
                        <input v-model="entry.mac_address" type="text" placeholder="ex : 00:1A:2B:3C:4D:5E"
                          :disabled="!isAdmin"
                          class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none bg-white disabled:bg-gray-100 disabled:text-gray-500 disabled:cursor-not-allowed" />
                      </div>

                      <!-- Masque de sous-réseau -->
                      <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Masque de sous-réseau</label>
                        <input v-model="entry.subnet_mask" type="text" placeholder="ex : 255.255.255.0"
                          class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none bg-white" />
                      </div>

                      <!-- Passerelle -->
                      <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Passerelle</label>
                        <input v-model="entry.gateway" type="text" placeholder="ex : 192.168.1.1"
                          class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none bg-white" />
                      </div>

                      <!-- DNS Primaire -->
                      <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">DNS Primaire</label>
                        <input v-model="entry.dns_primary" type="text" placeholder="ex : 8.8.8.8"
                          class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none bg-white" />
                      </div>

                      <!-- DNS Secondaire -->
                      <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">DNS Secondaire</label>
                        <input v-model="entry.dns_secondary" type="text" placeholder="ex : 8.8.4.4"
                          class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none bg-white" />
                      </div>
                    </div>

                    <!-- Avertissement pour non-admin -->
                    <div v-if="!isAdmin" class="mt-3 p-2 bg-amber-50 border border-amber-200 rounded-lg">
                      <p class="text-xs text-amber-700">
                        ⚠️ En tant qu'utilisateur, vous ne pouvez pas modifier directement ces champs.
                        Vos modifications seront soumises comme demande de changement (sauf pour l'adresse MAC).
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- CPU + RAM -->
              <div v-if="isAdmin || editableFields.includes('processor')">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Processeur</label>
                <input v-model="form.processor" type="text" placeholder="ex : Intel Core i5-1235U"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
              </div>
              <div v-if="isAdmin || editableFields.includes('ram')">
                <label class="block text-xs font-semibold text-gray-600 mb-1">RAM</label>
                <input v-model="form.ram" type="text" placeholder="ex : 16 Go"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
              </div>
              <!-- Notes -->
              <div v-if="isAdmin || editableFields.includes('notes')" class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Notes</label>
                <textarea v-model="form.notes" rows="2"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none resize-none"></textarea>
              </div>
            </div>

            <div class="flex justify-end gap-3 pt-2 border-t">
              <button type="button" @click="closeFormModal"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                Annuler
              </button>
              <button type="submit"
                :disabled="processing || (!editing && createMode === 'from_asset' && selectedAssets.length === 0)"
                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition">
                <template v-if="editing">Enregistrer</template>
                <template v-else-if="createMode === 'from_asset'">
                  {{ selectedAssets.length > 0 ? `Créer ${selectedAssets.length} poste${selectedAssets.length > 1 ? 's' : ''}` : 'Créer les postes' }}
                </template>
                <template v-else>Créer le poste</template>
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
    <!-- ── Barre d'actions flottante (sélection active) ────────────────────── -->
    <Transition name="slide-up">
      <div v-if="selectionMode && selectedCount > 0"
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 flex items-center gap-3 bg-gray-900 text-white px-5 py-3 rounded-2xl shadow-2xl border border-gray-700">
        <span class="text-sm font-semibold text-violet-300">
          {{ selectedCount }} poste{{ selectedCount > 1 ? 's' : '' }} sélectionné{{ selectedCount > 1 ? 's' : '' }}
        </span>
        <div class="w-px h-5 bg-gray-600" />
        <button @click="openBulkEdit" :disabled="bulkProcessing"
          class="flex items-center gap-1.5 text-sm font-medium px-3 py-1.5 bg-violet-600 hover:bg-violet-500 rounded-lg transition disabled:opacity-50">
          <PencilIcon class="w-4 h-4" />
          Modifier
        </button>
        <button @click="confirmBulkDelete" :disabled="bulkProcessing"
          class="flex items-center gap-1.5 text-sm font-medium px-3 py-1.5 bg-red-600 hover:bg-red-500 rounded-lg transition disabled:opacity-50">
          <TrashIcon class="w-4 h-4" />
          Supprimer
        </button>
        <button @click="clearSelection"
          class="text-gray-400 hover:text-white transition ml-1">
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>
    </Transition>

    <!-- ── Modal Bulk Edit ───────────────────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showBulkModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showBulkModal = false" />
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between p-6 border-b">
            <div>
              <h2 class="text-lg font-bold text-gray-900">Modification groupée</h2>
              <p class="text-sm text-gray-500 mt-0.5">
                {{ selectedCount }} poste{{ selectedCount > 1 ? 's' : '' }} sélectionné{{ selectedCount > 1 ? 's' : '' }} — cochez les champs à modifier
              </p>
            </div>
            <button @click="showBulkModal = false" class="text-gray-400 hover:text-gray-600">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-6 space-y-3">
            <!-- Info -->
            <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
              ⚠️ Seuls les champs cochés seront modifiés. Les autres champs de chaque poste resteront inchangés.
            </div>

            <!-- Type -->
            <div class="flex items-start gap-3 p-3 rounded-xl border transition"
              :class="bulkFields.type.enabled ? 'border-violet-200 bg-violet-50' : 'border-gray-100 bg-gray-50'">
              <input type="checkbox" v-model="bulkFields.type.enabled" id="bf-type"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <label for="bf-type" class="block text-sm font-semibold text-gray-700 mb-1.5 cursor-pointer">Type de poste</label>
                <select v-model="bulkFields.type.value" :disabled="!bulkFields.type.enabled"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-violet-500 outline-none disabled:bg-gray-100 disabled:text-gray-400">
                  <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.icon }} {{ t.label }}</option>
                </select>
              </div>
            </div>

            <!-- OS -->
            <div class="flex items-start gap-3 p-3 rounded-xl border transition"
              :class="bulkFields.os.enabled ? 'border-violet-200 bg-violet-50' : 'border-gray-100 bg-gray-50'">
              <input type="checkbox" v-model="bulkFields.os.enabled" id="bf-os"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <label for="bf-os" class="block text-sm font-semibold text-gray-700 mb-1.5 cursor-pointer">Système d'exploitation</label>
                <select v-model="bulkFields.os.value" :disabled="!bulkFields.os.enabled"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-violet-500 outline-none disabled:bg-gray-100 disabled:text-gray-400">
                  <option v-for="o in osOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
              </div>
            </div>

            <!-- Version OS -->
            <div class="flex items-start gap-3 p-3 rounded-xl border transition"
              :class="bulkFields.os_version.enabled ? 'border-violet-200 bg-violet-50' : 'border-gray-100 bg-gray-50'">
              <input type="checkbox" v-model="bulkFields.os_version.enabled" id="bf-osv"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <label for="bf-osv" class="block text-sm font-semibold text-gray-700 mb-1.5 cursor-pointer">Version OS</label>
                <input v-model="bulkFields.os_version.value" :disabled="!bulkFields.os_version.enabled"
                  type="text" placeholder="ex : 23H2, 14.4, 22.04 LTS…"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-violet-500 outline-none disabled:bg-gray-100 disabled:text-gray-400" />
              </div>
            </div>

            <!-- Processeur -->
            <div class="flex items-start gap-3 p-3 rounded-xl border transition"
              :class="bulkFields.processor.enabled ? 'border-violet-200 bg-violet-50' : 'border-gray-100 bg-gray-50'">
              <input type="checkbox" v-model="bulkFields.processor.enabled" id="bf-cpu"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <label for="bf-cpu" class="block text-sm font-semibold text-gray-700 mb-1.5 cursor-pointer">Processeur</label>
                <input v-model="bulkFields.processor.value" :disabled="!bulkFields.processor.enabled"
                  type="text" placeholder="ex : Intel Core i5-1235U"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-violet-500 outline-none disabled:bg-gray-100 disabled:text-gray-400" />
              </div>
            </div>

            <!-- RAM -->
            <div class="flex items-start gap-3 p-3 rounded-xl border transition"
              :class="bulkFields.ram.enabled ? 'border-violet-200 bg-violet-50' : 'border-gray-100 bg-gray-50'">
              <input type="checkbox" v-model="bulkFields.ram.enabled" id="bf-ram"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <label for="bf-ram" class="block text-sm font-semibold text-gray-700 mb-1.5 cursor-pointer">RAM</label>
                <input v-model="bulkFields.ram.value" :disabled="!bulkFields.ram.enabled"
                  type="text" placeholder="ex : 8 Go, 16 Go DDR4…"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-violet-500 outline-none disabled:bg-gray-100 disabled:text-gray-400" />
              </div>
            </div>

            <!-- Notes -->
            <div class="flex items-start gap-3 p-3 rounded-xl border transition"
              :class="bulkFields.notes.enabled ? 'border-violet-200 bg-violet-50' : 'border-gray-100 bg-gray-50'">
              <input type="checkbox" v-model="bulkFields.notes.enabled" id="bf-notes"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <label for="bf-notes" class="block text-sm font-semibold text-gray-700 mb-1.5 cursor-pointer">Notes</label>
                <textarea v-model="bulkFields.notes.value" :disabled="!bulkFields.notes.enabled"
                  rows="2" placeholder="Notes communes à tous les postes sélectionnés…"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-violet-500 outline-none resize-none disabled:bg-gray-100 disabled:text-gray-400"></textarea>
              </div>
            </div>
          </div>

          <div class="flex justify-end gap-3 px-6 pb-6">
            <button type="button" @click="showBulkModal = false"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
              Annuler
            </button>
            <button type="button" @click="submitBulkEdit" :disabled="bulkProcessing"
              class="px-5 py-2 text-sm font-medium text-white bg-violet-600 rounded-lg hover:bg-violet-700 disabled:opacity-50 transition">
              {{ bulkProcessing ? 'Enregistrement…' : `Appliquer sur ${selectedCount} poste${selectedCount > 1 ? 's' : ''}` }}
            </button>
          </div>
        </div>
      </div>
    </Transition>

  </AppLayout>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.slide-up-enter-active, .slide-up-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.slide-up-enter-from, .slide-up-leave-to { opacity: 0; transform: translateX(-50%) translateY(1rem); }
</style>
