<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
  ComputerDesktopIcon, MagnifyingGlassIcon,
  WifiIcon, ArrowDownTrayIcon, TableCellsIcon,
} from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  computers: Array,
  isAdmin:   Boolean,
})

/* ── Options ──────────────────────────────────────────────────────────────── */
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
  { value: 'windows_10',     label: 'Windows 10' },
  { value: 'windows_11',     label: 'Windows 11' },
  { value: 'windows_server', label: 'Windows Server' },
  { value: 'macos',          label: 'macOS' },
  { value: 'linux',          label: 'Linux' },
  { value: 'chromeos',       label: 'ChromeOS' },
  { value: 'other',          label: 'Autre' },
]

const typeLabel = (t)  => typeOptions.find(o => o.value === t)?.label ?? t
const typeIcon  = (t)  => typeOptions.find(o => o.value === t)?.icon ?? '💻'
const osLabel   = (os) => osOptions.find(o => o.value === os)?.label ?? os

const osBadge = (os) => ({
  windows_10:     'bg-blue-100 text-blue-700',
  windows_11:     'bg-blue-100 text-blue-700',
  windows_server: 'bg-indigo-100 text-indigo-700',
  macos:          'bg-gray-100 text-gray-700',
  linux:          'bg-orange-100 text-orange-700',
  chromeos:       'bg-teal-100 text-teal-700',
  other:          'bg-gray-100 text-gray-500',
})[os] ?? 'bg-gray-100 text-gray-500'

const licenseTypeBadge = (type) => ({
  per_seat:    'bg-blue-100 text-blue-700',
  volume:      'bg-purple-100 text-purple-700',
  oem:         'bg-orange-100 text-orange-700',
  cloud:       'bg-sky-100 text-sky-700',
  dongle:      'bg-yellow-100 text-yellow-700',
  open_source: 'bg-green-100 text-green-700',
})[type] ?? 'bg-gray-100 text-gray-600'

/* ── Filtres ──────────────────────────────────────────────────────────────── */
const search        = ref('')
const filterType    = ref('')
const filterOs      = ref('')
const filterIpOnly  = ref(false)
const sortField     = ref('name')
const sortDir       = ref('asc')

const filtered = computed(() => {
  let list = props.computers
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(c =>
      c.name.toLowerCase().includes(q) ||
      (c.asset_tag ?? '').toLowerCase().includes(q) ||
      (c.ip_addresses ?? []).some(e => (e.ip ?? '').toLowerCase().includes(q) || (e.description ?? '').toLowerCase().includes(q)) ||
      (c.processor ?? '').toLowerCase().includes(q)
    )
  }
  if (filterType.value) list = list.filter(c => c.type === filterType.value)
  if (filterOs.value)   list = list.filter(c => c.os === filterOs.value)
  if (filterIpOnly.value) list = list.filter(c => c.ip_addresses && c.ip_addresses.length > 0)

  return [...list].sort((a, b) => {
    let va, vb
    if (sortField.value === 'ip_addresses') {
      va = (a.ip_addresses?.[0]?.ip ?? '').toLowerCase()
      vb = (b.ip_addresses?.[0]?.ip ?? '').toLowerCase()
    } else {
      va = (a[sortField.value] ?? '').toString().toLowerCase()
      vb = (b[sortField.value] ?? '').toString().toLowerCase()
    }
    return sortDir.value === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va)
  })
})

function toggleSort(field) {
  if (sortField.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDir.value = 'asc'
  }
}
function sortIndicator(field) {
  if (sortField.value !== field) return '↕'
  return sortDir.value === 'asc' ? '↑' : '↓'
}

/* ── Stats ────────────────────────────────────────────────────────────────── */
const withIp    = computed(() => props.computers.filter(c => c.ip_addresses && c.ip_addresses.length > 0).length)
const withoutIp = computed(() => props.computers.length - withIp.value)
const totalIps  = computed(() => props.computers.reduce((sum, c) => sum + (c.ip_addresses?.length ?? 0), 0))

/* ── Exports ──────────────────────────────────────────────────────────────── */
const exportLoading = ref(false)
const exportError   = ref('')

async function exportPdf() {
  exportLoading.value = true
  exportError.value   = ''
  try {
    const resp = await axios.post(route('computers.ip.export-pdf'), {}, { responseType: 'blob' })
    const url  = URL.createObjectURL(resp.data)
    const a    = document.createElement('a')
    a.href     = url
    a.download = 'adressage-ip-' + new Date().toISOString().slice(0,10) + '.pdf'
    a.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    if (err?.response?.data instanceof Blob) {
      try { const t = await err.response.data.text(); const j = JSON.parse(t); exportError.value = j.message || `Erreur ${err.response.status}` }
      catch { exportError.value = `Erreur ${err?.response?.status ?? ''} lors de l'export.` }
    } else {
      exportError.value = err?.response?.data?.message || 'Erreur lors de l\'export.'
    }
  } finally {
    exportLoading.value = false
  }
}

async function exportCsv() {
  exportLoading.value = true
  exportError.value   = ''
  try {
    const resp = await axios.post(route('computers.ip.export-csv'), {}, { responseType: 'blob' })
    const url  = URL.createObjectURL(resp.data)
    const a    = document.createElement('a')
    a.href     = url
    a.download = 'adressage-ip-' + new Date().toISOString().slice(0,10) + '.csv'
    a.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    exportError.value = 'Erreur lors de l\'export CSV.'
  } finally {
    exportLoading.value = false
  }
}
</script>

<template>
  <AppLayout>
    <div class="space-y-5">

      <!-- En-tête -->
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
            <WifiIcon class="w-5 h-5 text-indigo-600" />
          </div>
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Adressage IP</h1>
            <p class="text-sm text-gray-500">
              Tableau des adresses IP des postes informatiques
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
          <!-- Lien vers la page Postes -->
          <a :href="route('computers.index')"
            class="flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 border border-indigo-200 hover:border-indigo-400 px-3 py-2 rounded-lg transition">
            <ComputerDesktopIcon class="w-4 h-4" />
            Voir les postes
          </a>

          <!-- Exports (admin uniquement) -->
          <template v-if="isAdmin">
            <button @click="exportCsv" :disabled="exportLoading"
              class="flex items-center gap-1.5 text-sm text-gray-700 border border-gray-300 hover:bg-gray-50 px-3 py-2 rounded-lg transition disabled:opacity-50">
              <TableCellsIcon class="w-4 h-4" />
              CSV
            </button>
            <button @click="exportPdf" :disabled="exportLoading"
              class="flex items-center gap-1.5 text-sm text-white bg-indigo-600 hover:bg-indigo-700 px-3 py-2 rounded-lg transition disabled:opacity-50">
              <ArrowDownTrayIcon class="w-4 h-4" />
              {{ exportLoading ? 'Export…' : 'Export PDF' }}
            </button>
          </template>
        </div>
      </div>

      <!-- Erreur export -->
      <div v-if="exportError" class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">
        {{ exportError }}
      </div>

      <!-- Statistiques -->
      <div class="grid grid-cols-4 gap-3">
        <div class="bg-white border rounded-xl p-4 text-center">
          <p class="text-2xl font-bold text-indigo-600">{{ computers.length }}</p>
          <p class="text-xs text-gray-500 mt-0.5">Postes total</p>
        </div>
        <div class="bg-white border rounded-xl p-4 text-center">
          <p class="text-2xl font-bold text-green-600">{{ withIp }}</p>
          <p class="text-xs text-gray-500 mt-0.5">Postes avec IP</p>
        </div>
        <div class="bg-white border rounded-xl p-4 text-center">
          <p class="text-2xl font-bold text-red-400">{{ withoutIp }}</p>
          <p class="text-xs text-gray-500 mt-0.5">Sans adresse IP</p>
        </div>
        <div class="bg-white border rounded-xl p-4 text-center">
          <p class="text-2xl font-bold text-blue-600">{{ totalIps }}</p>
          <p class="text-xs text-gray-500 mt-0.5">Adresses IP total</p>
        </div>
      </div>

      <!-- Filtres -->
      <div class="bg-white rounded-xl border p-4 flex flex-wrap gap-3 items-center">
        <div class="relative flex-1 min-w-48">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
          <input v-model="search" type="text" placeholder="Nom, tag, IP, processeur…"
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
        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none">
          <input type="checkbox" v-model="filterIpOnly" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
          Avec IP uniquement
        </label>
        <span class="text-xs text-gray-400 ml-auto">{{ filtered.length }} résultat{{ filtered.length > 1 ? 's' : '' }}</span>
      </div>

      <!-- Tableau -->
      <div class="bg-white rounded-xl border overflow-hidden">
        <div v-if="filtered.length === 0" class="py-16 text-center text-gray-400">
          <WifiIcon class="w-10 h-10 mx-auto mb-3 opacity-30" />
          <p class="text-sm">Aucun poste trouvé</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="w-8 py-3 px-4 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">
                </th>
                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide cursor-pointer hover:text-indigo-600 select-none"
                    @click="toggleSort('name')">
                  Poste <span class="text-gray-300">{{ sortIndicator('name') }}</span>
                </th>
                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide cursor-pointer hover:text-indigo-600 select-none"
                    @click="toggleSort('type')">
                  Type <span class="text-gray-300">{{ sortIndicator('type') }}</span>
                </th>
                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide cursor-pointer hover:text-indigo-600 select-none"
                    @click="toggleSort('os')">
                  OS <span class="text-gray-300">{{ sortIndicator('os') }}</span>
                </th>
                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide cursor-pointer hover:text-indigo-600 select-none"
                    @click="toggleSort('ip_addresses')">
                  Adresses IP <span class="text-gray-300">{{ sortIndicator('ip_addresses') }}</span>
                </th>
                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                  Processeur
                </th>
                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide cursor-pointer hover:text-indigo-600 select-none"
                    @click="toggleSort('ram')">
                  RAM <span class="text-gray-300">{{ sortIndicator('ram') }}</span>
                </th>
                <th class="py-3 px-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                  Logiciels installés
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="c in filtered" :key="c.id"
                class="hover:bg-indigo-50/40 transition-colors">
                <!-- Miniature -->
                <td class="px-4 py-2.5">
                  <div class="w-8 h-8 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center flex-shrink-0">
                    <img v-if="c.photo_url" :src="c.photo_url" :alt="c.name"
                      class="w-full h-full object-cover" />
                    <span v-else class="text-base opacity-50">{{ typeIcon(c.type) }}</span>
                  </div>
                </td>
                <!-- Nom + tag -->
                <td class="py-2.5 px-4">
                  <p class="font-semibold text-gray-900">{{ c.name }}</p>
                  <p v-if="c.asset_tag" class="text-xs text-gray-400 font-mono">{{ c.asset_tag }}</p>
                </td>
                <!-- Type -->
                <td class="py-2.5 px-4 text-gray-600">
                  {{ typeLabel(c.type) }}
                </td>
                <!-- OS -->
                <td class="py-2.5 px-4">
                  <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', osBadge(c.os)]">
                    {{ osLabel(c.os) }}
                  </span>
                  <span v-if="c.os_version" class="text-xs text-gray-400 ml-1">{{ c.os_version }}</span>
                </td>
                <!-- Adresses IP -->
                <td class="py-2.5 px-4">
                  <template v-if="c.ip_addresses && c.ip_addresses.length > 0">
                    <div v-for="(e, i) in c.ip_addresses" :key="i" class="flex items-baseline gap-1.5 mb-0.5 last:mb-0">
                      <span class="font-mono font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded text-sm whitespace-nowrap">
                        {{ e.ip }}
                      </span>
                      <span v-if="e.description" class="text-xs text-gray-400 truncate max-w-[120px]" :title="e.description">
                        {{ e.description }}
                      </span>
                    </div>
                  </template>
                  <span v-else class="text-gray-300 text-xs italic">non définie</span>
                </td>
                <!-- Processeur -->
                <td class="py-2.5 px-4 text-gray-600 max-w-xs">
                  <span v-if="c.processor" class="text-xs truncate block">{{ c.processor }}</span>
                  <span v-else class="text-gray-300 text-xs">—</span>
                </td>
                <!-- RAM -->
                <td class="py-2.5 px-4 text-gray-700 font-medium whitespace-nowrap">
                  {{ c.ram || '—' }}
                </td>
                <!-- Licences -->
                <td class="py-2.5 px-4">
                  <div v-if="c.licenses.length > 0" class="flex flex-wrap gap-1">
                    <span v-for="l in c.licenses" :key="l.name"
                      :class="['text-xs px-1.5 py-0.5 rounded-full', l.is_expired ? 'bg-red-100 text-red-600 line-through' : licenseTypeBadge(l.type)]"
                      :title="l.name + (l.version ? ' v'+l.version : '') + (l.is_expired ? ' (expirée)' : '')">
                      {{ l.name.slice(0, 14) }}{{ l.name.length > 14 ? '…' : '' }}
                      <span v-if="l.version" class="opacity-60">v{{ l.version }}</span>
                    </span>
                  </div>
                  <span v-else class="text-gray-300 text-xs italic">Aucune</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </AppLayout>
</template>
