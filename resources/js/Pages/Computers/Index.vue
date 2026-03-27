<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
  ComputerDesktopIcon, PlusIcon, PencilIcon, TrashIcon,
  XMarkIcon, MagnifyingGlassIcon, KeyIcon, PhotoIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({ computers: Array })

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
const search    = ref('')
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
      (c.ip_address ?? '').toLowerCase().includes(q)
    )
  }
  if (filterType.value) list = list.filter(c => c.type === filterType.value)
  if (filterOs.value)   list = list.filter(c => c.os === filterOs.value)
  return list
})

/* ── Modal Détail ─────────────────────────────────────────────────────────── */
const showDetail = ref(false)
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

const emptyForm = () => ({
  name: '', asset_tag: '', type: 'desktop', os: 'windows_11',
  os_version: '', serial_number: '', ip_address: '',
  processor: '', ram: '', notes: '',
})
const form = ref(emptyForm())

function openCreate() {
  editing.value    = null
  form.value       = emptyForm()
  photoPreview.value = null
  removePhoto.value  = false
  photoFile.value    = null
  showFormModal.value = true
}
function openEdit(c) {
  editing.value = c
  form.value = {
    name:          c.name ?? '',
    asset_tag:     c.asset_tag ?? '',
    type:          c.type,
    os:            c.os,
    os_version:    c.os_version ?? '',
    serial_number: c.serial_number ?? '',
    ip_address:    c.ip_address ?? '',
    processor:     c.processor ?? '',
    ram:           c.ram ?? '',
    notes:         c.notes ?? '',
  }
  photoPreview.value = c.photo_url ?? null
  removePhoto.value  = false
  photoFile.value    = null
  showFormModal.value = true
}
function closeFormModal() { showFormModal.value = false; editing.value = null }

function onPhotoChange(e) {
  const file = e.target.files[0]
  if (!file) return
  photoFile.value = file
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
  processing.value = true
  const fd = new FormData()
  Object.entries(form.value).forEach(([k, v]) => { if (v !== null && v !== undefined) fd.append(k, v) })
  if (photoFile.value) fd.append('photo', photoFile.value)
  if (removePhoto.value) fd.append('remove_photo', '1')

  if (editing.value) {
    fd.append('_method', 'PUT')
    router.post(route('computers.update', editing.value.id), fd, {
      forceFormData: true,
      onSuccess: closeFormModal,
      onFinish: () => { processing.value = false },
    })
  } else {
    router.post(route('computers.store'), fd, {
      forceFormData: true,
      onSuccess: closeFormModal,
      onFinish: () => { processing.value = false },
    })
  }
}

function confirmDelete(c) {
  if (confirm(`Supprimer le poste « ${c.name} » ?`)) {
    router.delete(route('computers.destroy', c.id))
  }
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
        <div class="flex items-center gap-2">
          <a :href="route('reports.export-csv-computers')"
             class="flex items-center gap-2 border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm font-medium transition">
            ⬇ CSV
          </a>
          <button @click="openCreate"
            class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium transition">
            <PlusIcon class="w-4 h-4" /> Nouveau poste
          </button>
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

      <!-- Grille de cartes -->
      <div v-if="filtered.length === 0" class="bg-white rounded-xl border py-16 text-center text-gray-400">
        <ComputerDesktopIcon class="w-10 h-10 mx-auto mb-3 opacity-30" />
        <p class="text-sm">Aucun poste trouvé</p>
      </div>
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <div v-for="c in filtered" :key="c.id"
          class="bg-white rounded-xl border hover:shadow-md transition-shadow overflow-hidden group">

          <!-- Photo ou icône -->
          <div class="relative h-40 bg-gradient-to-br from-gray-50 to-gray-100 overflow-hidden cursor-pointer"
            @click="openDetail(c)">
            <img v-if="c.photo_url" :src="c.photo_url" :alt="c.name"
              class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
            <div v-else class="w-full h-full flex items-center justify-center">
              <span class="text-5xl opacity-40 select-none">{{ typeIcon(c.type) }}</span>
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
                <p v-if="c.asset_tag" class="text-xs text-gray-400">{{ c.asset_tag }}</p>
              </div>
              <div class="flex gap-1 flex-shrink-0">
                <button @click="openEdit(c)"
                  class="p-1 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded transition">
                  <PencilIcon class="w-3.5 h-3.5" />
                </button>
                <button @click="confirmDelete(c)"
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
              <p v-if="c.ip_address">🌐 {{ c.ip_address }}</p>
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
          </div>
          <div class="p-6">
            <div class="flex items-start gap-3 mb-5">
              <div>
                <h2 class="text-xl font-bold text-gray-900">{{ detailComputer.name }}</h2>
                <div class="flex items-center gap-2 mt-1">
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
              <div v-if="detailComputer.ip_address">
                <p class="text-xs text-gray-400">Adresse IP</p>
                <p class="font-mono text-xs text-gray-700">{{ detailComputer.ip_address }}</p>
              </div>
              <div v-if="detailComputer.asset_tag">
                <p class="text-xs text-gray-400">Tag</p>
                <p class="text-gray-700">{{ detailComputer.asset_tag }}</p>
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
            <div class="flex gap-3 mt-5 pt-4 border-t">
              <button @click="openEdit(detailComputer); closeDetail()"
                class="flex-1 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition flex items-center justify-center gap-2">
                <PencilIcon class="w-4 h-4" /> Modifier
              </button>
              <button @click="confirmDelete(detailComputer); closeDetail()"
                class="py-2 px-4 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition flex items-center justify-center gap-2">
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Modal Créer / Modifier ──────────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showFormModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
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
          <form @submit.prevent="submitForm" class="p-6 space-y-4">

            <!-- Photo -->
            <div>
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
              <!-- Nom -->
              <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Nom du poste *</label>
                <input v-model="form.name" required type="text" placeholder="ex : PC-SALLE101-01"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
              </div>
              <!-- Tag + Série -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Tag d'inventaire</label>
                <input v-model="form.asset_tag" type="text" placeholder="ex : PC-0042"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Numéro de série</label>
                <input v-model="form.serial_number" type="text"
                  class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none" />
              </div>
              <!-- Type + OS -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Type *</label>
                <select v-model="form.type"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                  <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.icon }} {{ t.label }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Système d'exploitation *</label>
                <select v-model="form.os"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                  <option v-for="o in osOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
              </div>
              <!-- Version OS + IP -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Version OS</label>
                <input v-model="form.os_version" type="text" placeholder="ex : 23H2"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Adresse IP</label>
                <input v-model="form.ip_address" type="text" placeholder="ex : 192.168.1.50"
                  class="w-full px-3 py-2 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 outline-none" />
              </div>
              <!-- CPU + RAM -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Processeur</label>
                <input v-model="form.processor" type="text" placeholder="ex : Intel Core i5-1235U"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">RAM</label>
                <input v-model="form.ram" type="text" placeholder="ex : 16 Go"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
              </div>
              <!-- Notes -->
              <div class="col-span-2">
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
              <button type="submit" :disabled="processing"
                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition">
                {{ editing ? 'Enregistrer' : 'Créer le poste' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>
  </AppLayout>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
