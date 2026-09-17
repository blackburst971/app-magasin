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
  ipDevices:          Array,
  availableAssets:    Array,
  places:             Array,
  isAdmin:            Boolean,
  canEdit:            { type: Boolean, default: false },
  canRequestIpChange: { type: Boolean, default: false },
  editableFields:     { type: Array, default: () => [] },
})

/* ── Listes d'options ────────────────────────────────────────────────────── */

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
const search = ref('')

const filtered = computed(() => {
  let list = props.ipDevices
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(c =>
      c.name.toLowerCase().includes(q) ||
      (c.asset_tag ?? '').toLowerCase().includes(q) ||
      (c.mac_address ?? '').toLowerCase().includes(q) ||
      (c.ip_address ?? '').toLowerCase().includes(q) ||
      (c.device_type ?? '').toLowerCase().includes(q)
    )
  }
  return list
})

/* ── Mode d'affichage ────────────────────────────────────────────────────── */
const viewMode = useViewMode('ip_devices_view', 'grid')

/* ── Modal Détail ─────────────────────────────────────────────────────────── */
const showDetail     = ref(false)
const detailIpDevice = ref(null)
function openDetail(c) { detailIpDevice.value = c; showDetail.value = true }
function closeDetail()  { showDetail.value = false; detailIpDevice.value = null }

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
  name: '', place_id: null, device_type: '', mac_address: '',
  ip_address: '', subnet_mask: '255.255.255.0', gateway: '',
  dns_primary: '', dns_secondary: '', location: '',
  brand: '', model: '', notes: '', ip_change_reason: '',
})

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
    device_type:   c.device_type ?? '',
    mac_address:   c.mac_address ?? '',
    ip_address:    c.ip_address ?? '',
    subnet_mask:   c.subnet_mask ?? '255.255.255.0',
    gateway:       c.gateway ?? '',
    dns_primary:   c.dns_primary ?? '',
    dns_secondary: c.dns_secondary ?? '',
    location:      c.location ?? '',
    brand:         c.brand ?? '',
    model:         c.model ?? '',
    notes:         c.notes ?? '',
    ip_change_reason: '',  // For non-admin IP change request
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
  // ── Modification non-admin (demande de changement IP) ─────────────────
  if (editing.value && !props.isAdmin) {
    processing.value = true
    const payload = {
      ip_address:       form.value.ip_address || '',
      subnet_mask:      form.value.subnet_mask || '',
      gateway:          form.value.gateway || '',
      dns_primary:      form.value.dns_primary || '',
      dns_secondary:    form.value.dns_secondary || '',
      ip_change_reason: form.value.ip_change_reason || ''
    }
    router.post(route('ip-devices.request-ip-change', editing.value.id), payload, {
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
    router.post(route('ip-devices.batch-store'), {
      asset_ids:      selectedAssets.value.map(a => a.id),
      device_type:    form.value.device_type,
      mac_address:    form.value.mac_address,
      ip_address:     form.value.ip_address,
      subnet_mask:    form.value.subnet_mask,
      gateway:        form.value.gateway,
      dns_primary:    form.value.dns_primary,
      dns_secondary:  form.value.dns_secondary,
      notes:          form.value.notes,
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
    if (k === 'ip_change_reason') return  // Skip this field for admin forms
    if (v !== null && v !== undefined && v !== '') {
      fd.append(k, v)
    }
  })
  if (photoFile.value) fd.append('photo', photoFile.value)
  if (removePhoto.value) fd.append('remove_photo', '1')

  if (editing.value) {
    fd.append('_method', 'PUT')
    router.post(route('ip-devices.update', editing.value.id), fd, {
      forceFormData: true,
      onSuccess: closeFormModal,
      onFinish:  () => { processing.value = false },
    })
  } else {
    fd.append('create_mode', 'manual')
    router.post(route('ip-devices.store'), fd, {
      forceFormData: true,
      onSuccess: closeFormModal,
      onFinish:  () => { processing.value = false },
    })
  }
}

function confirmDelete(c) {
  if (confirm(`Supprimer le périphérique IP « ${c.name} » ?`)) {
    router.delete(route('ip-devices.destroy', c.id))
  }
}

/* ── Sélection multiple & Bulk Edit (admin) ──────────────────────────────── */
const selectionMode       = ref(false)
const selectedIpDeviceIds = ref(new Set())

function toggleSelectionMode() {
  selectionMode.value = !selectionMode.value
  if (!selectionMode.value) selectedIpDeviceIds.value = new Set()
}
function toggleSelectIpDevice(id) {
  const s = new Set(selectedIpDeviceIds.value)
  if (s.has(id)) s.delete(id)
  else s.add(id)
  selectedIpDeviceIds.value = s
}
function selectAllFiltered() {
  selectedIpDeviceIds.value = new Set(filtered.value.map(c => c.id))
}
function clearSelection() {
  selectedIpDeviceIds.value = new Set()
}

// Vider la sélection dès qu'un filtre change
watch([search], () => {
  selectedIpDeviceIds.value = new Set()
})
const selectedCount = computed(() => selectedIpDeviceIds.value.size)
const allFilteredSelected = computed(() =>
  filtered.value.length > 0 && filtered.value.every(c => selectedIpDeviceIds.value.has(c.id))
)

// ── Modal Bulk Edit ──────────────────────────────────────────────────────────
const showBulkModal  = ref(false)
const bulkProcessing = ref(false)

const bulkFields = ref({
  device_type:   { enabled: false, value: '' },
  mac_address:   { enabled: false, value: '' },
  subnet_mask:   { enabled: false, value: '255.255.255.0' },
  gateway:       { enabled: false, value: '' },
  dns_primary:   { enabled: false, value: '' },
  dns_secondary: { enabled: false, value: '' },
  location:      { enabled: false, value: '' },
  brand:         { enabled: false, value: '' },
  model:         { enabled: false, value: '' },
  notes:         { enabled: false, value: '' },
})

function openBulkEdit() {
  // Réinitialiser le formulaire bulk
  bulkFields.value = {
    device_type:   { enabled: false, value: '' },
    mac_address:   { enabled: false, value: '' },
    subnet_mask:   { enabled: false, value: '255.255.255.0' },
    gateway:       { enabled: false, value: '' },
    dns_primary:   { enabled: false, value: '' },
    dns_secondary: { enabled: false, value: '' },
    location:      { enabled: false, value: '' },
    brand:         { enabled: false, value: '' },
    model:         { enabled: false, value: '' },
    notes:         { enabled: false, value: '' },
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
    ids:    Array.from(selectedIpDeviceIds.value),
    fields,
  }
  fields.forEach(f => { payload[f] = bulkFields.value[f].value })

  bulkProcessing.value = true
  router.post(route('ip-devices.bulk-update'), payload, {
    onSuccess: () => {
      showBulkModal.value       = false
      selectionMode.value       = false
      selectedIpDeviceIds.value = new Set()
    },
    onFinish: () => { bulkProcessing.value = false },
  })
}

function confirmBulkDelete() {
  const n = selectedCount.value
  if (!confirm(`Supprimer ${n} périphérique${n > 1 ? 's' : ''} IP définitivement ?`)) return
  bulkProcessing.value = true
  router.post(route('ip-devices.bulk-delete'), {
    ids: Array.from(selectedIpDeviceIds.value),
  }, {
    onSuccess: () => {
      selectionMode.value       = false
      selectedIpDeviceIds.value = new Set()
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
            <h1 class="text-2xl font-bold text-gray-900">Périphériques IP</h1>
            <p class="text-sm text-gray-500">{{ ipDevices.length }} périphérique{{ ipDevices.length > 1 ? 's' : '' }} enregistré{{ ipDevices.length > 1 ? 's' : '' }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Lien adressage IP -->
          <a :href="route('ip-devices.ip-addressing')"
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
            <a :href="route('ip-devices.ip-csv')"
               class="flex items-center gap-2 border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm font-medium transition">
              ⬇ CSV
            </a>
            <button @click="openCreate"
              class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium transition">
              <PlusIcon class="w-4 h-4" /> Nouveau périphérique IP
            </button>
          </template>
        </div>
      </div>

      <!-- Filtres -->
      <div class="bg-white rounded-xl border p-4 flex flex-wrap gap-3">
        <div class="relative flex-1 min-w-48">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
          <input v-model="search" type="text" placeholder="Nom, type, MAC, IP..."
            class="w-full pl-9 pr-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent outline-none" />
        </div>
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
        <p class="text-sm">Aucun périphérique IP trouvé</p>
      </div>
      <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <div v-for="c in filtered" :key="c.id"
          class="bg-white rounded-xl border hover:shadow-md transition-shadow overflow-hidden group"
          :class="selectionMode && selectedIpDeviceIds.has(c.id) ? 'ring-2 ring-violet-500 border-violet-300' : ''">

          <!-- Photo ou icône -->
          <div class="relative h-40 bg-gradient-to-br from-gray-50 to-gray-100 overflow-hidden"
            :class="selectionMode ? 'cursor-pointer' : 'cursor-pointer'"
            @click="selectionMode ? toggleSelectIpDevice(c.id) : openDetail(c)">
            <img v-if="c.photo_url" :src="c.photo_url" :alt="c.name"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
            <div v-else class="w-full h-full flex items-center justify-center">
              <span class="text-5xl opacity-40 select-none">🌐</span>
            </div>
            <!-- Checkbox de sélection -->
            <div v-if="selectionMode"
              class="absolute top-2 left-2 z-10">
              <div class="w-6 h-6 rounded-full flex items-center justify-center border-2 transition"
                :class="selectedIpDeviceIds.has(c.id)
                  ? 'bg-violet-600 border-violet-600'
                  : 'bg-white/90 border-gray-300 hover:border-violet-400'">
                <CheckCircleIcon v-if="selectedIpDeviceIds.has(c.id)" class="w-5 h-5 text-white" />
              </div>
            </div>
            <!-- Badge licences -->
            <div v-if="c.license_assignments_count > 0"
              class="absolute top-2 right-2 bg-blue-600 text-white text-xs font-bold px-2 py-0.5 rounded-full flex items-center gap-1">
              <KeyIcon class="w-3 h-3" />
              {{ c.license_assignments_count }}
            </div>
            <!-- Badge inventaire lié -->
            <div v-if="c.asset_id"
              class="absolute top-2 left-2 text-xs font-semibold px-2 py-0.5 rounded-full flex items-center gap-1"
              :class="c.asset_is_reservable ? 'bg-green-600 text-white' : 'bg-gray-700 text-white'"
              :title="c.asset_is_reservable ? 'Réservable par les utilisateurs' : 'Non réservable'">
              <CubeIcon class="w-3 h-3" />
              {{ c.asset_is_reservable ? 'Réservable' : 'Inventaire' }}
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

            <!-- Type -->
            <div class="flex flex-wrap gap-1.5 mb-3">
              <span v-if="c.device_type" class="text-xs font-medium px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">{{ c.device_type }}</span>
              <span v-else class="text-xs text-gray-400 px-2 py-0.5 bg-gray-50 rounded-full">Type non défini</span>
            </div>

            <!-- Détails -->
            <div class="space-y-1 text-xs text-gray-500">
              <p v-if="c.brand || c.model" class="truncate">🏷️ {{ c.brand }} {{ c.model }}</p>
              <p v-if="c.ip_address" class="font-mono">🌐 {{ c.ip_address }}</p>
              <p v-if="c.mac_address" class="truncate font-mono text-gray-400">MAC: {{ c.mac_address }}</p>
              <p v-if="c.location" class="truncate">📍 {{ c.location }}</p>
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
        <table class="min-w-full divide-y divide-gray-100">
          <thead class="bg-gray-50">
            <tr>
              <th class="w-10 px-3 py-2.5"></th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Périphérique IP</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Type</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Adresse IP</th>
              <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">MAC / Localisation</th>
              <th class="px-4 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Licences</th>
              <th class="px-4 py-2.5"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="c in filtered" :key="c.id"
              class="hover:bg-gray-50 transition cursor-pointer"
              :class="selectionMode && selectedIpDeviceIds.has(c.id) ? 'bg-violet-50' : ''"
              @click="selectionMode ? toggleSelectIpDevice(c.id) : openDetail(c)">
              <!-- Photo / sélection -->
              <td class="px-3 py-2.5">
                <div class="relative w-10 h-10 flex-shrink-0">
                  <img v-if="c.photo_url" :src="c.photo_url" :alt="c.name"
                    class="w-10 h-10 rounded-lg object-cover" />
                  <div v-else class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-xl">
                    🌐
                  </div>
                  <!-- checkbox overlay -->
                  <div v-if="selectionMode" class="absolute -top-1 -left-1">
                    <div class="w-5 h-5 rounded-full flex items-center justify-center border-2 transition shadow-sm"
                      :class="selectedIpDeviceIds.has(c.id) ? 'bg-violet-600 border-violet-600' : 'bg-white border-gray-300'">
                      <CheckCircleIcon v-if="selectedIpDeviceIds.has(c.id)" class="w-4 h-4 text-white" />
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
              <!-- Type -->
              <td class="px-4 py-2.5">
                <span v-if="c.device_type" class="text-xs font-medium px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">{{ c.device_type }}</span>
                <span v-else class="text-xs text-gray-400">—</span>
                <p v-if="c.brand || c.model" class="text-xs text-gray-400 mt-0.5">{{ c.brand }} {{ c.model }}</p>
              </td>
              <!-- IP -->
              <td class="px-4 py-2.5">
                <p v-if="c.ip_address" class="font-mono text-sm text-gray-700">{{ c.ip_address }}</p>
                <span v-else class="text-xs text-gray-300">—</span>
              </td>
              <!-- MAC / Location (hidden on small screens) -->
              <td class="px-4 py-2.5 hidden lg:table-cell">
                <p v-if="c.mac_address" class="text-xs text-gray-600 font-mono truncate max-w-[160px]">{{ c.mac_address }}</p>
                <p v-if="c.location" class="text-xs text-gray-400">{{ c.location }}</p>
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

    <!-- ── Modal Détail ────────────────────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showDetail && detailIpDevice" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeDetail" />
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <!-- Photo header -->
          <div class="h-48 bg-gradient-to-br from-indigo-50 to-gray-100 overflow-hidden rounded-t-2xl relative">
            <img v-if="detailIpDevice.photo_url" :src="detailIpDevice.photo_url"
              class="w-full h-full object-cover" :alt="detailIpDevice.name" />
            <div v-else class="w-full h-full flex items-center justify-center">
              <span class="text-8xl opacity-30">🌐</span>
            </div>
            <button @click="closeDetail" class="absolute top-3 right-3 p-1.5 bg-white/80 rounded-lg text-gray-600 hover:bg-white">
              <XMarkIcon class="w-4 h-4" />
            </button>
            <!-- Badge inventaire dans le détail -->
            <div v-if="detailIpDevice.asset_id"
              class="absolute bottom-3 left-3 text-xs font-semibold px-2.5 py-1 rounded-full flex items-center gap-1.5"
              :class="detailIpDevice.asset_is_reservable ? 'bg-green-600 text-white' : 'bg-gray-800/80 text-white'">
              <CubeIcon class="w-3.5 h-3.5" />
              {{ detailIpDevice.asset_is_reservable ? 'Réservable par les utilisateurs' : 'Non réservable (inventaire uniquement)' }}
            </div>
          </div>
          <div class="p-6">
            <div class="flex items-start gap-3 mb-5">
              <div>
                <h2 class="text-xl font-bold text-gray-900">{{ detailIpDevice.name }}</h2>
                <div class="flex items-center gap-2 mt-1 flex-wrap">
                  <span v-if="detailIpDevice.device_type" class="text-xs font-medium px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">{{ detailIpDevice.device_type }}</span>
                  <span v-if="detailIpDevice.brand || detailIpDevice.model" class="text-xs text-gray-500">{{ detailIpDevice.brand }} {{ detailIpDevice.model }}</span>
                </div>
              </div>
            </div>
            <!-- Infos techniques -->
            <div class="grid grid-cols-2 gap-3 mb-5 text-sm">
              <div v-if="detailIpDevice.ip_address" class="col-span-2">
                <p class="text-xs text-gray-400 mb-1">Adresse IP</p>
                <div class="flex items-center gap-2 bg-indigo-50 px-2.5 py-1 rounded-lg">
                  <WifiIcon class="w-3.5 h-3.5 text-indigo-400 flex-shrink-0" />
                  <span class="font-mono text-sm font-semibold text-indigo-700">{{ detailIpDevice.ip_address }}</span>
                </div>
              </div>
              <div v-if="detailIpDevice.mac_address">
                <p class="text-xs text-gray-400">Adresse MAC</p>
                <p class="font-mono text-xs text-gray-700">{{ detailIpDevice.mac_address }}</p>
              </div>
              <div v-if="detailIpDevice.subnet_mask">
                <p class="text-xs text-gray-400">Masque de sous-réseau</p>
                <p class="font-mono text-xs text-gray-700">{{ detailIpDevice.subnet_mask }}</p>
              </div>
              <div v-if="detailIpDevice.gateway">
                <p class="text-xs text-gray-400">Passerelle</p>
                <p class="font-mono text-xs text-gray-700">{{ detailIpDevice.gateway }}</p>
              </div>
              <div v-if="detailIpDevice.dns_primary">
                <p class="text-xs text-gray-400">DNS primaire</p>
                <p class="font-mono text-xs text-gray-700">{{ detailIpDevice.dns_primary }}</p>
              </div>
              <div v-if="detailIpDevice.dns_secondary">
                <p class="text-xs text-gray-400">DNS secondaire</p>
                <p class="font-mono text-xs text-gray-700">{{ detailIpDevice.dns_secondary }}</p>
              </div>
              <div v-if="detailIpDevice.location" class="col-span-2">
                <p class="text-xs text-gray-400">Localisation</p>
                <p class="text-gray-700">{{ detailIpDevice.location }}</p>
              </div>
            </div>
            <!-- Licences -->
            <div class="border-t pt-4">
              <h3 class="text-sm font-semibold text-gray-900 mb-3 flex items-center gap-2">
                <KeyIcon class="w-4 h-4 text-blue-500" />
                Licences installées ({{ detailIpDevice.licenses.length }})
              </h3>
              <div v-if="detailIpDevice.licenses.length === 0" class="text-xs text-gray-400 italic">
                Aucune licence attribuée à ce périphérique IP.
              </div>
              <div v-else class="space-y-2">
                <div v-for="l in detailIpDevice.licenses" :key="l.id"
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
            <div v-if="detailIpDevice.notes" class="border-t pt-4 mt-4">
              <p class="text-xs text-gray-400 mb-1">Notes</p>
              <p class="text-sm text-gray-700">{{ detailIpDevice.notes }}</p>
            </div>
            <!-- Actions -->
            <div v-if="isAdmin || canEdit || canRequestIpChange" class="flex gap-3 mt-5 pt-4 border-t">
              <button @click="openEdit(detailIpDevice); closeDetail()"
                class="flex-1 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition flex items-center justify-center gap-2">
                <PencilIcon class="w-4 h-4" /> Modifier
              </button>
              <button v-if="isAdmin" @click="confirmDelete(detailIpDevice); closeDetail()"
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
              {{ editing ? 'Modifier le périphérique IP' : 'Nouveau périphérique IP' }}
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
                  ℹ️ Seuls les équipements non encore associés à un périphérique IP sont listés.
                </p>
              </div>

              <!-- Info batch -->
              <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
                ⚡ Les paramètres ci-dessous (type, adresses réseau) s'appliquent à <strong>tous les périphériques IP créés</strong>. Les noms sont repris automatiquement depuis chaque équipement. Vous pourrez personnaliser chaque périphérique IP individuellement ensuite.
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
              ✏️ Vous pouvez uniquement demander des modifications des paramètres réseau (IP, passerelle, DNS). Les autres informations (nom, type, MAC, localisation, marque, modèle) sont gérées par l'administrateur.
            </div>

            <!-- Photo (masquée en mode from_asset) -->
            <div v-if="isAdmin && (editing || createMode !== 'from_asset')">
              <label class="block text-xs font-semibold text-gray-600 mb-2">Photo du périphérique</label>
              <div class="flex items-center gap-4">
                <div class="w-24 h-24 rounded-xl bg-gray-100 overflow-hidden flex items-center justify-center border-2 border-dashed border-gray-300 flex-shrink-0">
                  <img v-if="photoPreview" :src="photoPreview" class="w-full h-full object-cover" />
                  <span v-else class="text-3xl opacity-30">🌐</span>
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
              <!-- ═══════════════════════════════════════════════════════════════
                   MODE ADMIN : Formulaire complet
                   ═══════════════════════════════════════════════════════════════ -->
              <template v-if="isAdmin">
                <!-- Nom : masqué en from_asset -->
                <template v-if="editing || createMode !== 'from_asset'">
                  <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Nom du périphérique IP *</label>
                    <input v-model="form.name" required type="text" placeholder="ex : IMPRIMANTE-SALLE101"
                      class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
                  </div>
                  <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">📍 Emplacement</label>
                    <select v-model="form.place_id"
                      class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                      <option :value="null">-- Aucun emplacement --</option>
                      <option v-for="place in places" :key="place.id" :value="place.id">{{ place.name }}</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Choisissez le lieu où se trouve physiquement le périphérique IP</p>
                  </div>
                </template>

                <!-- Type de périphérique (texte libre) -->
                <div class="col-span-2">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Type de périphérique</label>
                  <input v-model="form.device_type" type="text" placeholder="ex : Imprimante, Switch, Point d'accès, Caméra..."
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
                </div>

                <!-- Adresse MAC -->
                <div class="col-span-2 sm:col-span-1">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Adresse MAC</label>
                  <input v-model="form.mac_address" type="text" placeholder="ex : 00:1A:2B:3C:4D:5E"
                    class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none" />
                </div>

                <!-- Adresse IP -->
                <div class="col-span-2 sm:col-span-1">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Adresse IP</label>
                  <input v-model="form.ip_address" type="text" placeholder="ex : 192.168.1.100"
                    class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none" />
                </div>

                <!-- Masque de sous-réseau -->
                <div class="col-span-2 sm:col-span-1">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Masque de sous-réseau</label>
                  <input v-model="form.subnet_mask" type="text" placeholder="ex : 255.255.255.0"
                    class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none" />
                </div>

                <!-- Passerelle -->
                <div class="col-span-2 sm:col-span-1">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Passerelle</label>
                  <input v-model="form.gateway" type="text" placeholder="ex : 192.168.1.1"
                    class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none" />
                </div>

                <!-- DNS Primaire -->
                <div class="col-span-2 sm:col-span-1">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">DNS Primaire</label>
                  <input v-model="form.dns_primary" type="text" placeholder="ex : 8.8.8.8"
                    class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none" />
                </div>

                <!-- DNS Secondaire -->
                <div class="col-span-2 sm:col-span-1">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">DNS Secondaire</label>
                  <input v-model="form.dns_secondary" type="text" placeholder="ex : 8.8.4.4"
                    class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none" />
                </div>

                <!-- Localisation -->
                <div class="col-span-2">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Localisation physique</label>
                  <input v-model="form.location" type="text" placeholder="ex : Salle 101, Bureau principal, Étage 2..."
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
                </div>

                <!-- Marque -->
                <div class="col-span-2 sm:col-span-1">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Marque</label>
                  <input v-model="form.brand" type="text" placeholder="ex : HP, Cisco, Ubiquiti..."
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
                </div>

                <!-- Modèle -->
                <div class="col-span-2 sm:col-span-1">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Modèle</label>
                  <input v-model="form.model" type="text" placeholder="ex : LaserJet Pro M404dn"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
                </div>

                <!-- Notes -->
                <div class="col-span-2">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Notes</label>
                  <textarea v-model="form.notes" rows="2" placeholder="Notes additionnelles..."
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none resize-none"></textarea>
                </div>
              </template>

              <!-- ═══════════════════════════════════════════════════════════════
                   MODE NON-ADMIN : Demande de modification
                   ═══════════════════════════════════════════════════════════════ -->
              <template v-else-if="editing">
                <!-- Informations en lecture seule -->
                <div class="col-span-2 p-4 bg-gray-50 border border-gray-200 rounded-lg space-y-3">
                  <h3 class="text-sm font-semibold text-gray-700 mb-3">Informations du périphérique (lecture seule)</h3>

                  <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                      <p class="text-xs text-gray-400">Nom</p>
                      <p class="font-medium text-gray-900">{{ editing.name }}</p>
                    </div>
                    <div v-if="editing.device_type">
                      <p class="text-xs text-gray-400">Type</p>
                      <p class="text-sm text-gray-700">{{ editing.device_type }}</p>
                    </div>
                    <div v-if="editing.mac_address">
                      <p class="text-xs text-gray-400">Adresse MAC</p>
                      <p class="text-sm text-gray-700 font-mono">{{ editing.mac_address }}</p>
                    </div>
                    <div v-if="editing.location" class="col-span-2">
                      <p class="text-xs text-gray-400">Localisation</p>
                      <p class="text-sm text-gray-700">{{ editing.location }}</p>
                    </div>
                    <div v-if="editing.brand">
                      <p class="text-xs text-gray-400">Marque</p>
                      <p class="text-sm text-gray-700">{{ editing.brand }}</p>
                    </div>
                    <div v-if="editing.model">
                      <p class="text-xs text-gray-400">Modèle</p>
                      <p class="text-sm text-gray-700">{{ editing.model }}</p>
                    </div>
                  </div>
                </div>

                <!-- Bandeau info demande de changement -->
                <div class="col-span-2 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
                  🔔 <strong>Demande de modification des paramètres réseau :</strong> Les modifications ne seront effectives qu'après validation par l'administrateur. Vous recevrez une notification une fois la demande traitée.
                </div>

                <!-- Paramètres réseau MODIFIABLES -->
                <div class="col-span-2 p-4 bg-blue-50 border border-blue-200 rounded-lg space-y-3">
                  <h3 class="text-sm font-semibold text-blue-900 mb-3">Paramètres réseau (modifiables)</h3>

                  <div class="grid grid-cols-2 gap-3">
                    <!-- Adresse IP -->
                    <div class="col-span-2 sm:col-span-1">
                      <label class="block text-xs font-semibold text-gray-600 mb-1">Adresse IP</label>
                      <input v-model="form.ip_address" type="text" placeholder="ex : 192.168.1.100"
                        class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 outline-none bg-white" />
                    </div>

                    <!-- Passerelle -->
                    <div class="col-span-2 sm:col-span-1">
                      <label class="block text-xs font-semibold text-gray-600 mb-1">Passerelle</label>
                      <input v-model="form.gateway" type="text" placeholder="ex : 192.168.1.1"
                        class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 outline-none bg-white" />
                    </div>

                    <!-- Masque de sous-réseau -->
                    <div class="col-span-2 sm:col-span-1">
                      <label class="block text-xs font-semibold text-gray-600 mb-1">Masque de sous-réseau</label>
                      <input v-model="form.subnet_mask" type="text" placeholder="ex : 255.255.255.0"
                        class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 outline-none bg-white" />
                    </div>

                    <!-- DNS Primaire -->
                    <div class="col-span-2 sm:col-span-1">
                      <label class="block text-xs font-semibold text-gray-600 mb-1">DNS Primaire</label>
                      <input v-model="form.dns_primary" type="text" placeholder="ex : 8.8.8.8"
                        class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 outline-none bg-white" />
                    </div>

                    <!-- DNS Secondaire -->
                    <div class="col-span-2">
                      <label class="block text-xs font-semibold text-gray-600 mb-1">DNS Secondaire</label>
                      <input v-model="form.dns_secondary" type="text" placeholder="ex : 8.8.4.4"
                        class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 outline-none bg-white" />
                    </div>
                  </div>
                </div>

                <!-- Raison de la demande -->
                <div class="col-span-2">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Raison de la demande *</label>
                  <textarea v-model="form.ip_change_reason" rows="3" required
                    placeholder="Expliquez pourquoi vous demandez ces modifications (ex : Changement de réseau, conflit IP, nouvelle configuration...)"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
                </div>
              </template>
            </div>

            <div class="flex justify-end gap-3 pt-2 border-t">
              <button type="button" @click="closeFormModal"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                Annuler
              </button>
              <button type="submit"
                :disabled="processing || (!editing && createMode === 'from_asset' && selectedAssets.length === 0)"
                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition">
                <template v-if="editing && !isAdmin">Envoyer la demande de modification</template>
                <template v-else-if="editing">Enregistrer</template>
                <template v-else-if="createMode === 'from_asset'">
                  {{ selectedAssets.length > 0 ? `Créer ${selectedAssets.length} périphérique${selectedAssets.length > 1 ? 's' : ''} IP` : 'Créer les périphériques IP' }}
                </template>
                <template v-else>Créer le périphérique IP</template>
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
          {{ selectedCount }} périphérique{{ selectedCount > 1 ? 's' : '' }} IP sélectionné{{ selectedCount > 1 ? 's' : '' }}
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
                {{ selectedCount }} périphérique{{ selectedCount > 1 ? 's' : '' }} IP sélectionné{{ selectedCount > 1 ? 's' : '' }} — cochez les champs à modifier
              </p>
            </div>
            <button @click="showBulkModal = false" class="text-gray-400 hover:text-gray-600">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <div class="p-6 space-y-3">
            <!-- Info -->
            <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
              ⚠️ Seuls les champs cochés seront modifiés. Les autres champs de chaque périphérique IP resteront inchangés.
            </div>

            <!-- Type de périphérique -->
            <div class="flex items-start gap-3 p-3 rounded-xl border transition"
              :class="bulkFields.device_type.enabled ? 'border-violet-200 bg-violet-50' : 'border-gray-100 bg-gray-50'">
              <input type="checkbox" v-model="bulkFields.device_type.enabled" id="bf-type"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <label for="bf-type" class="block text-sm font-semibold text-gray-700 mb-1.5 cursor-pointer">Type de périphérique</label>
                <input v-model="bulkFields.device_type.value" :disabled="!bulkFields.device_type.enabled"
                  type="text" placeholder="ex : Imprimante, Switch, Point d'accès..."
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-violet-500 outline-none disabled:bg-gray-100 disabled:text-gray-400" />
              </div>
            </div>

            <!-- Masque de sous-réseau -->
            <div class="flex items-start gap-3 p-3 rounded-xl border transition"
              :class="bulkFields.subnet_mask.enabled ? 'border-violet-200 bg-violet-50' : 'border-gray-100 bg-gray-50'">
              <input type="checkbox" v-model="bulkFields.subnet_mask.enabled" id="bf-subnet"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <label for="bf-subnet" class="block text-sm font-semibold text-gray-700 mb-1.5 cursor-pointer">Masque de sous-réseau</label>
                <input v-model="bulkFields.subnet_mask.value" :disabled="!bulkFields.subnet_mask.enabled"
                  type="text" placeholder="ex : 255.255.255.0"
                  class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-violet-500 outline-none disabled:bg-gray-100 disabled:text-gray-400" />
              </div>
            </div>

            <!-- Passerelle -->
            <div class="flex items-start gap-3 p-3 rounded-xl border transition"
              :class="bulkFields.gateway.enabled ? 'border-violet-200 bg-violet-50' : 'border-gray-100 bg-gray-50'">
              <input type="checkbox" v-model="bulkFields.gateway.enabled" id="bf-gateway"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <label for="bf-gateway" class="block text-sm font-semibold text-gray-700 mb-1.5 cursor-pointer">Passerelle</label>
                <input v-model="bulkFields.gateway.value" :disabled="!bulkFields.gateway.enabled"
                  type="text" placeholder="ex : 192.168.1.1"
                  class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-violet-500 outline-none disabled:bg-gray-100 disabled:text-gray-400" />
              </div>
            </div>

            <!-- DNS Primaire -->
            <div class="flex items-start gap-3 p-3 rounded-xl border transition"
              :class="bulkFields.dns_primary.enabled ? 'border-violet-200 bg-violet-50' : 'border-gray-100 bg-gray-50'">
              <input type="checkbox" v-model="bulkFields.dns_primary.enabled" id="bf-dns1"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <label for="bf-dns1" class="block text-sm font-semibold text-gray-700 mb-1.5 cursor-pointer">DNS Primaire</label>
                <input v-model="bulkFields.dns_primary.value" :disabled="!bulkFields.dns_primary.enabled"
                  type="text" placeholder="ex : 8.8.8.8"
                  class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-violet-500 outline-none disabled:bg-gray-100 disabled:text-gray-400" />
              </div>
            </div>

            <!-- DNS Secondaire -->
            <div class="flex items-start gap-3 p-3 rounded-xl border transition"
              :class="bulkFields.dns_secondary.enabled ? 'border-violet-200 bg-violet-50' : 'border-gray-100 bg-gray-50'">
              <input type="checkbox" v-model="bulkFields.dns_secondary.enabled" id="bf-dns2"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <label for="bf-dns2" class="block text-sm font-semibold text-gray-700 mb-1.5 cursor-pointer">DNS Secondaire</label>
                <input v-model="bulkFields.dns_secondary.value" :disabled="!bulkFields.dns_secondary.enabled"
                  type="text" placeholder="ex : 8.8.4.4"
                  class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-violet-500 outline-none disabled:bg-gray-100 disabled:text-gray-400" />
              </div>
            </div>

            <!-- Localisation -->
            <div class="flex items-start gap-3 p-3 rounded-xl border transition"
              :class="bulkFields.location.enabled ? 'border-violet-200 bg-violet-50' : 'border-gray-100 bg-gray-50'">
              <input type="checkbox" v-model="bulkFields.location.enabled" id="bf-location"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <label for="bf-location" class="block text-sm font-semibold text-gray-700 mb-1.5 cursor-pointer">Localisation physique</label>
                <input v-model="bulkFields.location.value" :disabled="!bulkFields.location.enabled"
                  type="text" placeholder="ex : Salle 101, Bureau principal..."
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-violet-500 outline-none disabled:bg-gray-100 disabled:text-gray-400" />
              </div>
            </div>

            <!-- Marque -->
            <div class="flex items-start gap-3 p-3 rounded-xl border transition"
              :class="bulkFields.brand.enabled ? 'border-violet-200 bg-violet-50' : 'border-gray-100 bg-gray-50'">
              <input type="checkbox" v-model="bulkFields.brand.enabled" id="bf-brand"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <label for="bf-brand" class="block text-sm font-semibold text-gray-700 mb-1.5 cursor-pointer">Marque</label>
                <input v-model="bulkFields.brand.value" :disabled="!bulkFields.brand.enabled"
                  type="text" placeholder="ex : HP, Cisco, Ubiquiti..."
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-violet-500 outline-none disabled:bg-gray-100 disabled:text-gray-400" />
              </div>
            </div>

            <!-- Modèle -->
            <div class="flex items-start gap-3 p-3 rounded-xl border transition"
              :class="bulkFields.model.enabled ? 'border-violet-200 bg-violet-50' : 'border-gray-100 bg-gray-50'">
              <input type="checkbox" v-model="bulkFields.model.enabled" id="bf-model"
                class="mt-0.5 w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500 flex-shrink-0" />
              <div class="flex-1 min-w-0">
                <label for="bf-model" class="block text-sm font-semibold text-gray-700 mb-1.5 cursor-pointer">Modèle</label>
                <input v-model="bulkFields.model.value" :disabled="!bulkFields.model.enabled"
                  type="text" placeholder="ex : LaserJet Pro M404dn"
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
                  rows="2" placeholder="Notes communes à tous les périphériques IP sélectionnés…"
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
              {{ bulkProcessing ? 'Enregistrement…' : `Appliquer sur ${selectedCount} périphérique${selectedCount > 1 ? 's' : ''} IP` }}
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
