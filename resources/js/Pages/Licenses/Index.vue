<script setup>
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
  KeyIcon, PlusIcon, PencilIcon, TrashIcon, XMarkIcon,
  ComputerDesktopIcon, EyeIcon, EyeSlashIcon, MagnifyingGlassIcon,
  CloudIcon, CpuChipIcon, CheckCircleIcon, ExclamationTriangleIcon, UserIcon,
  InformationCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  licenses: Array,
  computers: Array,
  users: Array,
  suppliers: Array,
  stats: Object,
})

/* ── Types ──────────────────────────────────────────────────────────────── */
const typeOptions = [
  { value: 'per_seat',    label: 'Par poste',     color: 'blue' },
  { value: 'volume',      label: 'Volume',         color: 'purple' },
  { value: 'oem',         label: 'OEM',            color: 'orange' },
  { value: 'cloud',       label: 'Cloud / SaaS',   color: 'sky' },
  { value: 'dongle',      label: 'Dongle',         color: 'yellow' },
  { value: 'open_source', label: 'Open Source',    color: 'green' },
]

const typeBadge = (type) => {
  const map = {
    per_seat:    'bg-blue-100 text-blue-700',
    volume:      'bg-purple-100 text-purple-700',
    oem:         'bg-orange-100 text-orange-700',
    cloud:       'bg-sky-100 text-sky-700',
    dongle:      'bg-yellow-100 text-yellow-700',
    open_source: 'bg-green-100 text-green-700',
  }
  return map[type] ?? 'bg-gray-100 text-gray-600'
}
const typeLabel = (type) => typeOptions.find(t => t.value === type)?.label ?? type

/* ── Filtres ────────────────────────────────────────────────────────────── */
const search   = ref('')
const filterType   = ref('')
const filterStatus = ref('')

const filtered = computed(() => {
  let list = props.licenses
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(l =>
      l.name.toLowerCase().includes(q) ||
      (l.manufacturer ?? '').toLowerCase().includes(q) ||
      (l.version ?? '').toLowerCase().includes(q)
    )
  }
  if (filterType.value)   list = list.filter(l => l.type === filterType.value)
  if (filterStatus.value === 'expired')       list = list.filter(l => l.is_expired)
  if (filterStatus.value === 'expiring_soon') list = list.filter(l => l.is_expiring_soon)
  if (filterStatus.value === 'cloud')         list = list.filter(l => l.is_cloud)
  if (filterStatus.value === 'dongle')        list = list.filter(l => l.type === 'dongle')
  return list
})

// Grouper les licences filtrées par type
const groupedByType = computed(() => {
  const groups = {}
  filtered.value.forEach(license => {
    if (!groups[license.type]) {
      groups[license.type] = []
    }
    groups[license.type].push(license)
  })
  return groups
})

/* ── Modal Création / Édition ───────────────────────────────────────────── */
const showFormModal = ref(false)
const editing       = ref(null)
const processing    = ref(false)
const showKey       = ref(false)

const emptyForm = () => ({
  name: '', version: '', manufacturer: '', license_key: '',
  seats: '', type: 'per_seat', is_cloud: false,
  purchase_date: '', expiry_date: '', purchase_cost: '',
  order_number: '', supplier_id: '', notes: '',
  license_keys: [],
})
const form = ref(emptyForm())

function openCreate() {
  editing.value  = null
  form.value     = emptyForm()
  showKey.value  = false
  showFormModal.value = true
}
function openEdit(license) {
  editing.value  = license
  showKey.value  = false
  form.value = {
    name:          license.name ?? '',
    version:       license.version ?? '',
    manufacturer:  license.manufacturer ?? '',
    license_key:   license.license_key ?? '',
    seats:         license.seats ?? '',
    type:          license.type,
    is_cloud:      license.is_cloud,
    purchase_date: license.purchase_date ?? '',
    expiry_date:   license.expiry_date ?? '',
    purchase_cost: license.purchase_cost ?? '',
    order_number:  license.order_number ?? '',
    supplier_id:   license.supplier?.id ?? '',
    notes:         license.notes ?? '',
    license_keys:  (license.keys ?? []).map(k => ({
      id: k.id,
      key_code: k.key_code,
      notes: k.notes ?? '',
    })),
  }
  showFormModal.value = true
}
function closeFormModal() { showFormModal.value = false; editing.value = null }

/* ── Gestion des clés de licence ───────────────────────────────────────────── */
function addLicenseKey() {
  form.value.license_keys.push({ key_code: '', notes: '' })
}

function removeLicenseKey(index) {
  form.value.license_keys.splice(index, 1)
}

function submitForm() {
  processing.value = true
  const payload = { ...form.value }
  if (editing.value) {
    router.put(route('licenses.update', editing.value.id), payload, {
      onSuccess: closeFormModal,
      onFinish: () => { processing.value = false },
    })
  } else {
    router.post(route('licenses.store'), payload, {
      onSuccess: closeFormModal,
      onFinish: () => { processing.value = false },
    })
  }
}

function confirmDelete(license) {
  if (confirm(`Supprimer la licence « ${license.name} » et toutes ses attributions ?`)) {
    router.delete(route('licenses.destroy', license.id))
  }
}

/* ── Modal Détails ──────────────────────────────────────────────────────── */
const showDetailModal = ref(false)
const detailLicense = ref(null)

function openDetail(license) {
  detailLicense.value = license
  showDetailModal.value = true
}
function closeDetail() {
  showDetailModal.value = false
  detailLicense.value = null
}

/* ── Modal Attributions ─────────────────────────────────────────────────── */
const showAssignModal = ref(false)
const selectedLicense = ref(null)
const assignForm = ref({
  assignment_type: 'computer',
  computer_id: '',
  user_id: '',
  license_key_id: '',
  assigned_at: new Date().toISOString().slice(0,10),
  notes: ''
})
const assignProcessing = ref(false)

function openAssign(license) {
  selectedLicense.value = license
  assignForm.value = {
    assignment_type: 'computer',
    computer_id: '',
    user_id: '',
    license_key_id: '',
    assigned_at: new Date().toISOString().slice(0,10),
    notes: ''
  }
  showAssignModal.value = true
}
function closeAssignModal() { showAssignModal.value = false; selectedLicense.value = null }

const availableComputers = computed(() => {
  if (!selectedLicense.value) return props.computers
  const assignedIds = selectedLicense.value.assignments
    .filter(a => a.computer)
    .map(a => a.computer.id)
  return props.computers.filter(c => !assignedIds.includes(c.id))
})

const availableUsers = computed(() => {
  if (!selectedLicense.value) return props.users
  const assignedIds = selectedLicense.value.assignments
    .filter(a => a.user)
    .map(a => a.user.id)
  return props.users.filter(u => !assignedIds.includes(u.id))
})

// Clés de licence disponibles pour attribution
const availableLicenseKeys = computed(() => {
  if (!selectedLicense.value) return []
  return selectedLicense.value.keys.filter(k => k.status === 'available')
})

function submitAssign() {
  assignProcessing.value = true
  const payload = {
    computer_id: assignForm.value.assignment_type === 'computer' ? assignForm.value.computer_id : '',
    user_id: assignForm.value.assignment_type === 'user' ? assignForm.value.user_id : '',
    license_key_id: assignForm.value.license_key_id || '',
    assigned_at: assignForm.value.assigned_at,
    notes: assignForm.value.notes
  }
  router.post(route('licenses.assign', selectedLicense.value.id), payload, {
    onSuccess: () => {
      assignForm.value = {
        assignment_type: 'computer',
        computer_id: '',
        user_id: '',
        license_key_id: '',
        assigned_at: new Date().toISOString().slice(0,10),
        notes: ''
      }
    },
    onFinish: () => { assignProcessing.value = false },
  })
}

function removeAssignment(license, assignment) {
  if (confirm('Retirer cette attribution ?')) {
    router.delete(route('licenses.unassign', { license: license.id, assignment: assignment.id }))
  }
}

/* ── Seats progress ─────────────────────────────────────────────────────── */
function seatsPercent(license) {
  if (!license.seats) return 0
  return Math.min(100, Math.round((license.seats_used / license.seats) * 100))
}
function seatsColor(license) {
  const pct = seatsPercent(license)
  if (pct >= 100) return 'bg-red-500'
  if (pct >= 80)  return 'bg-orange-400'
  return 'bg-blue-500'
}

/* ── OS / Type labels pour postes ──────────────────────────────────────── */
const typeIcons = {
  desktop: '🖥️', laptop: '💻', imac: '🖥️', macbook: '💻',
  mac_mini: '🟫', mac_studio: '🟫', server: '🗄️', tablet: '📱', other: '💻',
}
const osLabel = (os) => ({
  windows_10: 'Win 10', windows_11: 'Win 11', windows_server: 'WinSrv',
  macos: 'macOS', linux: 'Linux', chromeos: 'ChromeOS', other: 'Autre',
})[os] ?? os
</script>

<template>
  <AppLayout>
    <div class="space-y-6">

      <!-- En-tête -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
            <KeyIcon class="w-5 h-5 text-blue-600" />
          </div>
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Licences logicielles</h1>
            <p class="text-sm text-gray-500">Inventaire et attribution des licences</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <a :href="route('reports.export-csv-licenses')"
             class="flex items-center gap-2 border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm font-medium transition">
            ⬇ CSV
          </a>
          <button @click="openCreate"
            class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition">
            <PlusIcon class="w-4 h-4" /> Nouvelle licence
          </button>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl border p-4">
          <p class="text-xs text-gray-500 mb-1">Total licences</p>
          <p class="text-2xl font-bold text-gray-900">{{ stats.total }}</p>
        </div>
        <div class="bg-white rounded-xl border p-4">
          <p class="text-xs text-gray-500 mb-1">Sièges utilisés</p>
          <p class="text-2xl font-bold text-gray-900">
            {{ stats.seats_used }}
            <span class="text-sm font-normal text-gray-400">/ {{ stats.seats_total || '∞' }}</span>
          </p>
        </div>
        <div class="bg-white rounded-xl border p-4">
          <p class="text-xs text-gray-500 mb-1">Cloud / SaaS</p>
          <p class="text-2xl font-bold text-sky-600">{{ stats.cloud }}</p>
        </div>
        <div class="bg-white rounded-xl border p-4">
          <p class="text-xs text-gray-500 mb-1">Expirent bientôt</p>
          <p class="text-2xl font-bold text-orange-500">{{ stats.expiring_soon }}</p>
        </div>
        <div class="bg-white rounded-xl border p-4">
          <p class="text-xs text-gray-500 mb-1">Expirées</p>
          <p class="text-2xl font-bold text-red-500">{{ stats.expired }}</p>
        </div>
      </div>

      <!-- Filtres -->
      <div class="bg-white rounded-xl border p-4 flex flex-wrap gap-3">
        <div class="relative flex-1 min-w-48">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
          <input v-model="search" type="text" placeholder="Rechercher un logiciel..."
            class="w-full pl-9 pr-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none" />
        </div>
        <select v-model="filterType"
          class="px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
          <option value="">Tous les types</option>
          <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
        </select>
        <select v-model="filterStatus"
          class="px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
          <option value="">Tous les statuts</option>
          <option value="cloud">Cloud / SaaS</option>
          <option value="dongle">Dongle</option>
          <option value="expiring_soon">Expire bientôt</option>
          <option value="expired">Expirée</option>
        </select>
      </div>

      <!-- Tableau groupé par type -->
      <div v-if="filtered.length === 0" class="bg-white rounded-xl border py-16 text-center text-gray-400">
        <KeyIcon class="w-10 h-10 mx-auto mb-3 opacity-30" />
        <p class="text-sm">Aucune licence trouvée</p>
      </div>

      <!-- Sections par type de licence -->
      <div v-else class="space-y-6">
        <template v-for="(licenses, type) in groupedByType" :key="type">
          <div class="bg-white rounded-xl border overflow-hidden">
            <!-- En-tête du groupe -->
            <div class="px-4 py-3 bg-gradient-to-r from-gray-50 to-white border-b flex items-center justify-between">
              <div class="flex items-center gap-3">
                <span :class="['text-xs font-bold px-3 py-1.5 rounded-full', typeBadge(type)]">
                  {{ typeLabel(type) }}
                </span>
                <span class="text-sm text-gray-600">
                  {{ licenses.length }} licence{{ licenses.length > 1 ? 's' : '' }}
                  <template v-if="props.stats.by_type && props.stats.by_type[type]">
                    · {{ props.stats.by_type[type].seats_used || 0 }} / {{ props.stats.by_type[type].seats_total || '∞' }} sièges
                  </template>
                </span>
              </div>
            </div>

            <!-- Tableau pour ce type -->
            <div class="overflow-x-auto">
              <table class="text-sm" style="min-width:max-content;width:100%">
              <thead class="bg-gray-50 border-b">
                <tr>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Logiciel</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Sièges</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Expiration</th>
                  <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide hidden xl:table-cell">Postes attribués</th>
                  <th class="text-right px-4 py-3"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <template v-for="license in licenses" :key="license.id">
              <tr class="hover:bg-gray-50 transition">
                <!-- Logiciel -->
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                      :class="license.is_cloud ? 'bg-sky-100' : 'bg-blue-100'">
                      <CloudIcon v-if="license.is_cloud" class="w-4 h-4 text-sky-600" />
                      <KeyIcon v-else class="w-4 h-4 text-blue-600" />
                    </div>
                    <div>
                      <div class="flex items-center gap-2">
                        <span class="font-medium text-gray-900">{{ license.name }}</span>
                        <span v-if="license.version" class="text-xs text-gray-400">v{{ license.version }}</span>
                      </div>
                      <p v-if="license.manufacturer" class="text-xs text-gray-500">{{ license.manufacturer }}</p>
                    </div>
                  </div>
                </td>
                <!-- Sièges -->
                <td class="px-4 py-3">
                  <div v-if="license.seats" class="flex items-center gap-2">
                    <div class="flex-1 bg-gray-200 rounded-full h-1.5 w-16">
                      <div class="h-1.5 rounded-full transition-all" :class="seatsColor(license)"
                        :style="`width:${seatsPercent(license)}%`"></div>
                    </div>
                    <span class="text-xs text-gray-700 whitespace-nowrap">
                      {{ license.seats_used }} / {{ license.seats }}
                    </span>
                  </div>
                  <div v-else class="flex items-center gap-1.5">
                    <span class="text-xs text-gray-500">{{ license.seats_used }} utilisé{{ license.seats_used > 1 ? 's' : '' }}</span>
                    <span class="text-xs text-gray-400">(∞)</span>
                  </div>
                </td>
                <!-- Expiration -->
                <td class="px-4 py-3 hidden lg:table-cell">
                  <div v-if="license.expiry_date">
                    <span v-if="license.is_expired"
                      class="inline-flex items-center gap-1 text-xs text-red-600 font-medium">
                      <ExclamationTriangleIcon class="w-3.5 h-3.5" /> Expirée
                    </span>
                    <span v-else-if="license.is_expiring_soon"
                      class="inline-flex items-center gap-1 text-xs text-orange-600 font-medium">
                      <ExclamationTriangleIcon class="w-3.5 h-3.5" /> {{ license.expiry_date }}
                    </span>
                    <span v-else class="text-xs text-gray-600">{{ license.expiry_date }}</span>
                  </div>
                  <span v-else class="text-xs text-gray-400">—</span>
                </td>
                <!-- Postes/Utilisateurs attribués -->
                <td class="px-4 py-3 hidden xl:table-cell">
                  <div class="flex flex-wrap gap-1 max-w-xs">
                    <span v-for="a in license.assignments.slice(0, 3)" :key="a.id"
                      class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full">
                      <ComputerDesktopIcon v-if="a.computer" class="w-3 h-3" />
                      <UserIcon v-else-if="a.user" class="w-3 h-3" />
                      <span v-if="a.computer">{{ a.computer.name }}</span>
                      <span v-else-if="a.user">{{ a.user.name }}</span>
                      <span v-else>—</span>
                    </span>
                    <span v-if="license.assignments.length > 3"
                      class="text-xs text-gray-400">+{{ license.assignments.length - 3 }}</span>
                    <span v-if="license.assignments.length === 0" class="text-xs text-gray-400">Non attribuée</span>
                  </div>
                </td>
                <!-- Actions -->
                <td class="px-4 py-3">
                  <div class="flex items-center justify-end gap-1">
                    <button @click="openAssign(license)" title="Gérer les attributions"
                      class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition">
                      <ComputerDesktopIcon class="w-4 h-4" />
                    </button>
                    <button v-if="$page.props.auth.user.roles.includes('admin')" @click="openDetail(license)" title="Voir détails"
                      class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition">
                      <InformationCircleIcon class="w-4 h-4" />
                    </button>
                    <button @click="openEdit(license)" title="Modifier"
                      class="p-1.5 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition">
                      <PencilIcon class="w-4 h-4" />
                    </button>
                    <button @click="confirmDelete(license)" title="Supprimer"
                      class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
                </template>
              </tbody>
            </table>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- ── Modal Créer / Modifier ──────────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showFormModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeFormModal" />
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between p-6 border-b">
            <h2 class="text-lg font-bold text-gray-900">
              {{ editing ? 'Modifier la licence' : 'Nouvelle licence' }}
            </h2>
            <button @click="closeFormModal" class="text-gray-400 hover:text-gray-600">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <form @submit.prevent="submitForm" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <!-- Nom -->
              <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Nom du logiciel *</label>
                <input v-model="form.name" required type="text" placeholder="ex : Adobe Photoshop"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <!-- Version + Éditeur -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Version</label>
                <input v-model="form.version" type="text" placeholder="ex : 2024"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Éditeur / Fabricant</label>
                <input v-model="form.manufacturer" type="text" placeholder="ex : Adobe"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <!-- Type + Sièges -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Type de licence *</label>
                <select v-model="form.type"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                  <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre de sièges</label>
                <input v-model="form.seats" type="number" min="1" placeholder="Laisser vide = illimité"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <!-- Clé de licence -->
              <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Clé de licence</label>
                <div class="relative">
                  <input v-model="form.license_key" :type="showKey ? 'text' : 'password'"
                    placeholder="XXXX-XXXX-XXXX-XXXX"
                    class="w-full px-3 py-2 pr-10 border rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 outline-none" />
                  <button type="button" @click="showKey = !showKey"
                    class="absolute right-3 top-2 text-gray-400 hover:text-gray-600">
                    <EyeSlashIcon v-if="showKey" class="w-4 h-4" />
                    <EyeIcon v-else class="w-4 h-4" />
                  </button>
                </div>
              </div>
              <!-- Cloud toggle -->
              <div class="col-span-2 flex items-center gap-3">
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="form.is_cloud" class="sr-only peer" />
                  <div class="w-9 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-500 rounded-full peer peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4"></div>
                </label>
                <span class="text-sm text-gray-700 flex items-center gap-1.5">
                  <CloudIcon class="w-4 h-4 text-sky-500" /> Licence cloud / SaaS (pas d'installation locale)
                </span>
              </div>
              <!-- Dates + Prix -->
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Date d'achat</label>
                <input v-model="form.purchase_date" type="date"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Date d'expiration</label>
                <input v-model="form.expiry_date" type="date"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Coût (€)</label>
                <input v-model="form.purchase_cost" type="number" step="0.01" min="0" placeholder="0.00"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">N° commande</label>
                <input v-model="form.order_number" type="text"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <!-- Fournisseur -->
              <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Fournisseur</label>
                <select v-model="form.supplier_id"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                  <option value="">— Aucun —</option>
                  <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </div>
              <!-- Notes -->
              <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Notes</label>
                <textarea v-model="form.notes" rows="2"
                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
              </div>

              <!-- Clés de licence -->
              <div class="col-span-2 border-t pt-4">
                <div class="flex items-center justify-between mb-3">
                  <label class="block text-xs font-semibold text-gray-600">
                    🔑 Clés de licence (codes d'activation)
                  </label>
                  <button type="button" @click="addLicenseKey"
                    class="flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                    <PlusIcon class="w-3.5 h-3.5" />
                    Ajouter une clé
                  </button>
                </div>
                <p class="text-xs text-gray-500 mb-3">
                  Pour les licences par poste, vous pouvez saisir plusieurs clés d'activation (une par siège).
                  Cela facilite le suivi des codes de licence individuels.
                </p>
                <div v-if="form.license_keys.length === 0" class="text-xs text-gray-400 italic py-3 text-center bg-gray-50 rounded-lg">
                  Aucune clé de licence. Cliquez sur "Ajouter une clé" pour en créer.
                </div>
                <div v-else class="space-y-2">
                  <div v-for="(key, index) in form.license_keys" :key="index"
                    class="flex gap-2 items-start p-3 bg-gray-50 rounded-lg">
                    <div class="flex-1 space-y-2">
                      <input v-model="key.key_code" type="text" placeholder="Code d'activation / Clé de licence *" required
                        class="w-full px-2 py-1.5 text-sm border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" />
                      <input v-model="key.notes" type="text" placeholder="Notes (optionnel)"
                        class="w-full px-2 py-1.5 text-xs border rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" />
                    </div>
                    <button type="button" @click="removeLicenseKey(index)" title="Retirer cette clé"
                      class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition mt-1">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
            <div class="flex justify-end gap-3 pt-2 border-t">
              <button type="button" @click="closeFormModal"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                Annuler
              </button>
              <button type="submit" :disabled="processing"
                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition">
                {{ editing ? 'Enregistrer' : 'Créer la licence' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Transition>

    <!-- ── Modal Détails (Lecture seule) ──────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showDetailModal && detailLicense" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeDetail" />
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between p-6 border-b">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                :class="detailLicense.is_cloud ? 'bg-sky-100' : 'bg-blue-100'">
                <CloudIcon v-if="detailLicense.is_cloud" class="w-5 h-5 text-sky-600" />
                <KeyIcon v-else class="w-5 h-5 text-blue-600" />
              </div>
              <div>
                <h2 class="text-lg font-bold text-gray-900">Détails de la licence</h2>
                <p class="text-sm text-gray-500">{{ detailLicense.name }}</p>
              </div>
            </div>
            <button @click="closeDetail" class="text-gray-400 hover:text-gray-600">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <div class="p-6 space-y-6">
            <!-- Informations principales -->
            <div class="grid grid-cols-2 gap-6">
              <div>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Informations générales</h3>
                <div class="space-y-3">
                  <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Nom du logiciel</label>
                    <p class="text-sm font-medium text-gray-900">{{ detailLicense.name }}</p>
                  </div>
                  <div v-if="detailLicense.version">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Version</label>
                    <p class="text-sm text-gray-900">{{ detailLicense.version }}</p>
                  </div>
                  <div v-if="detailLicense.manufacturer">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Éditeur / Fabricant</label>
                    <p class="text-sm text-gray-900">{{ detailLicense.manufacturer }}</p>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Type de licence</label>
                    <span :class="['inline-block text-xs font-medium px-2.5 py-1 rounded-full', typeBadge(detailLicense.type)]">
                      {{ typeLabel(detailLicense.type) }}
                    </span>
                  </div>
                  <div v-if="detailLicense.is_cloud" class="flex items-center gap-2 text-sm text-sky-600">
                    <CloudIcon class="w-4 h-4" />
                    <span>Licence cloud / SaaS</span>
                  </div>
                </div>
              </div>

              <div>
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Licences et sièges</h3>
                <div class="space-y-3">
                  <div v-if="detailLicense.license_key">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Clé de licence principale</label>
                    <p class="text-sm font-mono text-gray-900 bg-gray-50 px-3 py-2 rounded-lg">{{ detailLicense.license_key }}</p>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Sièges</label>
                    <div class="flex items-center gap-3">
                      <div v-if="detailLicense.seats" class="flex-1 bg-gray-200 rounded-full h-2">
                        <div class="h-2 rounded-full transition-all" :class="seatsColor(detailLicense)"
                          :style="`width:${seatsPercent(detailLicense)}%`"></div>
                      </div>
                      <span class="text-sm font-medium text-gray-900">
                        {{ detailLicense.seats_used }} / {{ detailLicense.seats ?? '∞' }}
                      </span>
                    </div>
                  </div>
                  <div v-if="detailLicense.seats && detailLicense.seats_used >= detailLicense.seats"
                    class="flex items-center gap-2 text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg">
                    <ExclamationTriangleIcon class="w-4 h-4" />
                    <span>Tous les sièges sont utilisés</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Dates et informations d'achat -->
            <div class="border-t pt-6">
              <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Dates et achat</h3>
              <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div v-if="detailLicense.purchase_date">
                  <label class="block text-xs font-medium text-gray-500 mb-1">Date d'achat</label>
                  <p class="text-sm text-gray-900">{{ detailLicense.purchase_date }}</p>
                </div>
                <div v-if="detailLicense.expiry_date">
                  <label class="block text-xs font-medium text-gray-500 mb-1">Date d'expiration</label>
                  <div>
                    <p class="text-sm text-gray-900">{{ detailLicense.expiry_date }}</p>
                    <span v-if="detailLicense.is_expired"
                      class="inline-flex items-center gap-1 text-xs text-red-600 font-medium mt-1">
                      <ExclamationTriangleIcon class="w-3.5 h-3.5" /> Expirée
                    </span>
                    <span v-else-if="detailLicense.is_expiring_soon"
                      class="inline-flex items-center gap-1 text-xs text-orange-600 font-medium mt-1">
                      <ExclamationTriangleIcon class="w-3.5 h-3.5" /> Expire bientôt
                    </span>
                    <span v-else class="inline-flex items-center gap-1 text-xs text-green-600 font-medium mt-1">
                      <CheckCircleIcon class="w-3.5 h-3.5" /> Valide
                    </span>
                  </div>
                </div>
                <div v-if="detailLicense.purchase_cost">
                  <label class="block text-xs font-medium text-gray-500 mb-1">Coût d'achat</label>
                  <p class="text-sm font-medium text-gray-900">{{ detailLicense.purchase_cost }} €</p>
                </div>
                <div v-if="detailLicense.order_number">
                  <label class="block text-xs font-medium text-gray-500 mb-1">N° de commande</label>
                  <p class="text-sm text-gray-900">{{ detailLicense.order_number }}</p>
                </div>
              </div>
              <div v-if="detailLicense.supplier" class="mt-4">
                <label class="block text-xs font-medium text-gray-500 mb-1">Fournisseur</label>
                <p class="text-sm text-gray-900">{{ detailLicense.supplier.name }}</p>
              </div>
            </div>

            <!-- Clés de licence -->
            <div v-if="detailLicense.keys && detailLicense.keys.length > 0" class="border-t pt-6">
              <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                🔑 Clés de licence ({{ detailLicense.keys.length }})
              </h3>
              <div class="grid gap-2">
                <div v-for="key in detailLicense.keys" :key="key.id"
                  class="flex items-start gap-3 bg-gray-50 rounded-lg p-3">
                  <div class="flex-1">
                    <p class="text-sm font-mono text-gray-900 break-all">{{ key.key_code }}</p>
                    <p v-if="key.notes" class="text-xs text-gray-500 mt-1">{{ key.notes }}</p>
                  </div>
                  <span v-if="key.is_used"
                    class="text-xs font-medium px-2 py-1 rounded-full bg-blue-100 text-blue-700 whitespace-nowrap">
                    Utilisée
                  </span>
                  <span v-else
                    class="text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-700 whitespace-nowrap">
                    Disponible
                  </span>
                </div>
              </div>
            </div>

            <!-- Attributions actuelles -->
            <div v-if="detailLicense.assignments && detailLicense.assignments.length > 0" class="border-t pt-6">
              <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
                Attributions actuelles ({{ detailLicense.assignments.length }})
              </h3>
              <div class="grid gap-2">
                <div v-for="a in detailLicense.assignments" :key="a.id"
                  class="flex items-center gap-3 bg-gray-50 rounded-lg p-3">
                  <div v-if="a.computer" class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                    <ComputerDesktopIcon class="w-5 h-5 text-blue-600" />
                  </div>
                  <div v-else-if="a.user" class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center flex-shrink-0">
                    <UserIcon class="w-5 h-5 text-purple-600" />
                  </div>
                  <div class="flex-1">
                    <div v-if="a.computer">
                      <p class="text-sm font-medium text-gray-900">{{ a.computer.name }}</p>
                      <p class="text-xs text-gray-500">
                        {{ typeIcons[a.computer.type] ?? '💻' }} {{ osLabel(a.computer.os) }} · Attribué le {{ a.assigned_at }}
                      </p>
                      <p v-if="a.notes" class="text-xs text-gray-600 mt-1">{{ a.notes }}</p>
                    </div>
                    <div v-else-if="a.user">
                      <p class="text-sm font-medium text-gray-900">{{ a.user.name }}</p>
                      <p class="text-xs text-gray-500">{{ a.user.email }} · Attribué le {{ a.assigned_at }}</p>
                      <p v-if="a.notes" class="text-xs text-gray-600 mt-1">{{ a.notes }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="border-t pt-6">
              <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Attributions actuelles</h3>
              <p class="text-sm text-gray-400 italic text-center py-4 bg-gray-50 rounded-lg">Aucune attribution</p>
            </div>

            <!-- Notes -->
            <div v-if="detailLicense.notes" class="border-t pt-6">
              <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Notes</h3>
              <p class="text-sm text-gray-700 whitespace-pre-wrap bg-gray-50 rounded-lg p-3">{{ detailLicense.notes }}</p>
            </div>

            <!-- Bouton fermer -->
            <div class="border-t pt-4">
              <button @click="closeDetail"
                class="w-full px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                Fermer
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Modal Attributions ──────────────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showAssignModal && selectedLicense" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeAssignModal" />
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between p-6 border-b">
            <div>
              <h2 class="text-lg font-bold text-gray-900">Attributions — {{ selectedLicense.name }}</h2>
              <p class="text-xs text-gray-500 mt-0.5">
                {{ selectedLicense.seats_used }} / {{ selectedLicense.seats ?? '∞' }} sièges utilisés
              </p>
            </div>
            <button @click="closeAssignModal" class="text-gray-400 hover:text-gray-600">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          <div class="p-6 space-y-5">
            <!-- Liste des attributions actuelles -->
            <div>
              <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Attributions actuelles</h3>
              <div v-if="selectedLicense.assignments.length === 0"
                class="text-sm text-gray-400 italic py-2">Aucune attribution</div>
              <div v-else class="space-y-2">
                <div v-for="a in selectedLicense.assignments" :key="a.id"
                  class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
                  <div class="flex items-center gap-2">
                    <div v-if="a.computer" class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                      <ComputerDesktopIcon class="w-4 h-4 text-blue-600" />
                    </div>
                    <div v-else-if="a.user" class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center flex-shrink-0">
                      <UserIcon class="w-4 h-4 text-purple-600" />
                    </div>
                    <div>
                      <div v-if="a.computer">
                        <p class="text-sm font-medium text-gray-900">{{ a.computer.name }}</p>
                        <p class="text-xs text-gray-500">
                          {{ osLabel(a.computer.os) }} · Attribué le {{ a.assigned_at }}
                        </p>
                      </div>
                      <div v-else-if="a.user">
                        <p class="text-sm font-medium text-gray-900">{{ a.user.name }}</p>
                        <p class="text-xs text-gray-500">
                          {{ a.user.email }} · Attribué le {{ a.assigned_at }}
                        </p>
                      </div>
                      <div v-else>
                        <p class="text-sm font-medium text-gray-400">Attribution supprimée</p>
                      </div>
                    </div>
                  </div>
                  <button @click="removeAssignment(selectedLicense, a)"
                    class="p-1 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition">
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Ajouter une attribution -->
            <div v-if="!selectedLicense.seats || selectedLicense.seats_used < selectedLicense.seats"
              class="border-t pt-5">
              <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Nouvelle attribution</h3>
              <form @submit.prevent="submitAssign" class="space-y-3">
                <!-- Type d'attribution -->
                <div>
                  <label class="block text-xs font-medium text-gray-600 mb-2">Type d'attribution *</label>
                  <div class="flex gap-3">
                    <label class="flex-1 flex items-center justify-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition"
                      :class="assignForm.assignment_type === 'computer' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'">
                      <input type="radio" v-model="assignForm.assignment_type" value="computer" class="sr-only" />
                      <ComputerDesktopIcon class="w-5 h-5" :class="assignForm.assignment_type === 'computer' ? 'text-blue-600' : 'text-gray-400'" />
                      <span class="text-sm font-medium" :class="assignForm.assignment_type === 'computer' ? 'text-blue-900' : 'text-gray-700'">
                        Poste informatique
                      </span>
                    </label>
                    <label class="flex-1 flex items-center justify-center gap-2 px-4 py-3 border-2 rounded-lg cursor-pointer transition"
                      :class="assignForm.assignment_type === 'user' ? 'border-purple-500 bg-purple-50' : 'border-gray-200 hover:border-gray-300'">
                      <input type="radio" v-model="assignForm.assignment_type" value="user" class="sr-only" />
                      <UserIcon class="w-5 h-5" :class="assignForm.assignment_type === 'user' ? 'text-purple-600' : 'text-gray-400'" />
                      <span class="text-sm font-medium" :class="assignForm.assignment_type === 'user' ? 'text-purple-900' : 'text-gray-700'">
                        Utilisateur
                      </span>
                    </label>
                  </div>
                </div>

                <!-- Select poste informatique -->
                <div v-if="assignForm.assignment_type === 'computer'">
                  <label class="block text-xs font-medium text-gray-600 mb-1">Poste informatique *</label>
                  <select v-model="assignForm.computer_id" required
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">— Sélectionner un poste —</option>
                    <option v-for="c in availableComputers" :key="c.id" :value="c.id">
                      {{ typeIcons[c.type] ?? '💻' }} {{ c.name }} ({{ osLabel(c.os) }})
                    </option>
                  </select>
                  <p v-if="availableComputers.length === 0" class="text-xs text-gray-400 mt-1">
                    Tous les postes disponibles ont déjà cette licence.
                  </p>
                </div>

                <!-- Select utilisateur -->
                <div v-if="assignForm.assignment_type === 'user'">
                  <label class="block text-xs font-medium text-gray-600 mb-1">Utilisateur *</label>
                  <select v-model="assignForm.user_id" required
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-purple-500 outline-none">
                    <option value="">— Sélectionner un utilisateur —</option>
                    <option v-for="u in availableUsers" :key="u.id" :value="u.id">
                      {{ u.name }} ({{ u.email }})
                    </option>
                  </select>
                  <p v-if="availableUsers.length === 0" class="text-xs text-gray-400 mt-1">
                    Tous les utilisateurs disponibles ont déjà cette licence.
                  </p>
                </div>

                <!-- Sélection clé de licence -->
                <div v-if="availableLicenseKeys.length > 0">
                  <label class="block text-xs font-medium text-gray-600 mb-1">
                    Clé de licence
                    <span class="text-gray-400 font-normal">(optionnel)</span>
                  </label>
                  <select v-model="assignForm.license_key_id"
                    class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-green-500 outline-none">
                    <option value="">— Attribution automatique —</option>
                    <option v-for="key in availableLicenseKeys" :key="key.id" :value="key.id">
                      {{ key.key_code }}
                    </option>
                  </select>
                  <p class="text-xs text-gray-500 mt-1">
                    {{ availableLicenseKeys.length }} clé(s) disponible(s). Laissez vide pour attribution automatique.
                  </p>
                </div>
                <div v-else>
                  <div class="bg-orange-50 border border-orange-200 rounded-lg p-3">
                    <p class="text-xs text-orange-700">
                      ⚠️ Aucune clé de licence disponible. L'attribution créera une entrée sans clé associée.
                    </p>
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Date d'attribution *</label>
                    <input v-model="assignForm.assigned_at" type="date" required
                      class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                    <input v-model="assignForm.notes" type="text" placeholder="Optionnel"
                      class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
                  </div>
                </div>
                <button type="submit"
                  :disabled="assignProcessing || (assignForm.assignment_type === 'computer' && !assignForm.computer_id) || (assignForm.assignment_type === 'user' && !assignForm.user_id)"
                  class="w-full py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition">
                  Attribuer la licence
                </button>
              </form>
            </div>
            <div v-else class="border-t pt-4">
              <p class="text-sm text-red-500 text-center">
                Tous les sièges sont utilisés. Augmentez le nombre de sièges pour attribuer davantage.
              </p>
            </div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Modal Détails (Consultation rapide) ──────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showDetailModal && detailLicense" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeDetail" />
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
          <!-- Header -->
          <div class="px-6 py-4 border-b bg-gradient-to-r from-indigo-50 to-blue-50 flex items-center justify-between">
            <div>
              <h2 class="text-lg font-semibold text-gray-900">Détails de la licence</h2>
              <p class="text-sm text-gray-500">{{ detailLicense.name }}</p>
            </div>
            <button @click="closeDetail" class="p-2 hover:bg-white/50 rounded-lg transition">
              <XMarkIcon class="w-5 h-5 text-gray-500" />
            </button>
          </div>

          <!-- Contenu scrollable -->
          <div class="overflow-y-auto max-h-[calc(90vh-180px)] p-6 space-y-6">
            <!-- Informations générales -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Logiciel</label>
                <p class="text-sm text-gray-900 font-medium">{{ detailLicense.name }}</p>
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Version</label>
                <p class="text-sm text-gray-900">{{ detailLicense.version || '—' }}</p>
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Fabricant</label>
                <p class="text-sm text-gray-900">{{ detailLicense.manufacturer || '—' }}</p>
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Type</label>
                <span :class="['inline-flex text-xs font-medium px-2.5 py-1 rounded-full', typeBadge(detailLicense.type)]">
                  {{ typeLabel(detailLicense.type) }}
                </span>
              </div>
            </div>

            <!-- Clé de licence principale -->
            <div v-if="detailLicense.license_key">
              <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Clé de licence</label>
              <div class="bg-gray-50 border rounded-lg p-3">
                <code class="text-sm text-gray-900 font-mono">{{ detailLicense.license_key }}</code>
              </div>
            </div>

            <!-- Sièges -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Sièges disponibles</label>
                <p class="text-sm text-gray-900">
                  <span v-if="detailLicense.seats">{{ detailLicense.seats_used }} / {{ detailLicense.seats }}</span>
                  <span v-else>{{ detailLicense.seats_used }} (Illimité)</span>
                </p>
                <div v-if="detailLicense.seats" class="mt-2 bg-gray-200 rounded-full h-2 w-full">
                  <div class="h-2 rounded-full transition-all" :class="seatsColor(detailLicense)"
                    :style="`width:${seatsPercent(detailLicense)}%`"></div>
                </div>
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Cloud / SaaS</label>
                <span :class="['inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full', detailLicense.is_cloud ? 'bg-sky-100 text-sky-700' : 'bg-gray-100 text-gray-600']">
                  <CloudIcon v-if="detailLicense.is_cloud" class="w-3.5 h-3.5" />
                  {{ detailLicense.is_cloud ? 'Oui' : 'Non' }}
                </span>
              </div>
            </div>

            <!-- Dates -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Date d'achat</label>
                <p class="text-sm text-gray-900">{{ detailLicense.purchase_date || '—' }}</p>
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Date d'expiration</label>
                <div v-if="detailLicense.expiry_date">
                  <p class="text-sm text-gray-900">{{ detailLicense.expiry_date }}</p>
                  <span v-if="detailLicense.is_expired"
                    class="inline-flex items-center gap-1 mt-1 text-xs text-red-600 font-medium">
                    <ExclamationTriangleIcon class="w-3.5 h-3.5" /> Expirée
                  </span>
                  <span v-else-if="detailLicense.is_expiring_soon"
                    class="inline-flex items-center gap-1 mt-1 text-xs text-orange-600 font-medium">
                    <ExclamationTriangleIcon class="w-3.5 h-3.5" /> Expire bientôt
                  </span>
                  <span v-else class="inline-flex items-center gap-1 mt-1 text-xs text-green-600 font-medium">
                    <CheckCircleIcon class="w-3.5 h-3.5" /> Valide
                  </span>
                </div>
                <p v-else class="text-sm text-gray-400">—</p>
              </div>
            </div>

            <!-- Informations commerciales -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Coût d'achat</label>
                <p class="text-sm text-gray-900">{{ detailLicense.purchase_cost ? detailLicense.purchase_cost + ' €' : '—' }}</p>
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">N° de commande</label>
                <p class="text-sm text-gray-900">{{ detailLicense.order_number || '—' }}</p>
              </div>
              <div class="col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Fournisseur</label>
                <p class="text-sm text-gray-900">{{ detailLicense.supplier?.name || '—' }}</p>
              </div>
            </div>

            <!-- Notes -->
            <div v-if="detailLicense.notes">
              <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Notes</label>
              <div class="bg-gray-50 border rounded-lg p-3">
                <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ detailLicense.notes }}</p>
              </div>
            </div>

            <!-- Clés de licence multiples -->
            <div v-if="detailLicense.keys && detailLicense.keys.length > 0">
              <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Clés de licence ({{ detailLicense.keys.length }})</label>
              <div class="space-y-2">
                <div v-for="key in detailLicense.keys" :key="key.id"
                  class="bg-gray-50 border rounded-lg p-3">
                  <div class="flex items-start gap-3">
                    <div class="flex-1">
                      <code class="text-sm text-gray-900 font-mono">{{ key.key_code }}</code>
                      <p v-if="key.notes" class="text-xs text-gray-500 mt-1">{{ key.notes }}</p>
                      <!-- Afficher à qui la clé est attribuée -->
                      <div v-if="key.assigned_to_computer" class="flex items-center gap-1.5 mt-2 text-xs text-blue-600">
                        <ComputerDesktopIcon class="w-3 h-3" />
                        <span>{{ key.assigned_to_computer.name }}</span>
                      </div>
                      <div v-else-if="key.assigned_to_user" class="flex items-center gap-1.5 mt-2 text-xs text-purple-600">
                        <UserIcon class="w-3 h-3" />
                        <span>{{ key.assigned_to_user.name }}</span>
                      </div>
                    </div>
                    <span :class="['text-xs font-medium px-2 py-1 rounded-full whitespace-nowrap', key.status === 'available' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700']">
                      {{ key.status === 'available' ? 'Disponible' : 'Attribuée' }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Attributions -->
            <div v-if="detailLicense.assignments && detailLicense.assignments.length > 0">
              <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                Attributions actuelles ({{ detailLicense.assignments.length }})
              </label>
              <div class="space-y-2">
                <div v-for="assignment in detailLicense.assignments" :key="assignment.id"
                  class="bg-gray-50 border rounded-lg p-3">
                  <div class="flex items-start gap-3">
                    <!-- Ordinateur -->
                    <div v-if="assignment.computer" class="flex items-center gap-2 flex-1">
                      <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                        <ComputerDesktopIcon class="w-4 h-4 text-blue-600" />
                      </div>
                      <div>
                        <p class="text-sm font-medium text-gray-900">{{ assignment.computer.name }}</p>
                        <p class="text-xs text-gray-500">{{ assignment.computer.os }}</p>
                      </div>
                    </div>
                    <!-- Utilisateur -->
                    <div v-else-if="assignment.user" class="flex items-center gap-2 flex-1">
                      <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center flex-shrink-0">
                        <UserIcon class="w-4 h-4 text-purple-600" />
                      </div>
                      <div>
                        <p class="text-sm font-medium text-gray-900">{{ assignment.user.name }}</p>
                        <p class="text-xs text-gray-500">{{ assignment.user.email }}</p>
                      </div>
                    </div>
                    <div class="text-right">
                      <p class="text-xs text-gray-500">{{ assignment.assigned_at }}</p>
                    </div>
                  </div>
                  <p v-if="assignment.notes" class="text-xs text-gray-600 mt-2 pl-10">{{ assignment.notes }}</p>
                </div>
              </div>
            </div>
            <div v-else>
              <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Attributions</label>
              <p class="text-sm text-gray-400">Aucune attribution</p>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t bg-gray-50 flex justify-end">
            <button @click="closeDetail"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border rounded-lg hover:bg-gray-50 transition">
              Fermer
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
</style>
