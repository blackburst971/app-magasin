<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import {
  ClipboardDocumentCheckIcon, ArchiveBoxIcon, TrashIcon,
  DocumentArrowDownIcon, PencilIcon, CheckIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({ report: Object })

// Tri
const sortKey    = ref('asset_name')
const sortDir    = ref('asc')
const filterStatus = ref('')

function sortItems(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = 'asc'
  }
}

const sortedItems = () => {
  let items = [...props.report.items]
  if (filterStatus.value) items = items.filter(i => i.scan_status === filterStatus.value)
  items.sort((a, b) => {
    const va = a[sortKey.value] ?? ''
    const vb = b[sortKey.value] ?? ''
    const cmp = String(va).localeCompare(String(vb), 'fr')
    return sortDir.value === 'asc' ? cmp : -cmp
  })
  return items
}

// Edition label/notes
const editing    = ref(false)
const editLabel  = ref(props.report.label ?? '')
const editNotes  = ref(props.report.notes ?? '')
const saving     = ref(false)

function submitEdit() {
  saving.value = true
  router.patch(route('inventory.reports.update', props.report.id), {
    label: editLabel.value.trim() || null,
    notes: editNotes.value.trim() || null,
  }, {
    onSuccess: () => { editing.value = false },
    onFinish:  () => { saving.value = false },
  })
}

function cancelEdit() {
  editLabel.value = props.report.label ?? ''
  editNotes.value = props.report.notes ?? ''
  editing.value = false
}

function exportReport(format) {
  window.open(route('inventory.reports.export', { report: props.report.id, format }), '_blank')
}

function toggleArchive() {
  router.patch(route('inventory.reports.archive', props.report.id), {}, { preserveScroll: true })
}

function deleteReport() {
  if (!confirm('Supprimer définitivement ce rapport ?')) return
  router.delete(route('inventory.reports.destroy', props.report.id))
}

const statusLabel = { present: 'Présent', missing: 'Manquant', pending: 'Non traité' }
const statusClass = {
  present: 'bg-green-100 text-green-700',
  missing: 'bg-red-100 text-red-700',
  pending: 'bg-gray-100 text-gray-500',
}
const statusIcon = { present: '✓', missing: '✗', pending: '—' }

function colHeader(key, label) {
  const arrow = sortKey.value === key ? (sortDir.value === 'asc' ? ' ↑' : ' ↓') : ''
  return label + arrow
}
</script>

<template>
  <AppLayout>
    <div class="max-w-5xl">

      <!-- Breadcrumb -->
      <div class="flex items-center gap-2 text-sm text-gray-400 mb-4">
        <button @click="router.get(route('inventory.index'))" class="hover:text-gray-600 transition">Inventaire</button>
        <span>/</span>
        <button @click="router.get(route('inventory.history'))" class="hover:text-gray-600 transition">Historique</button>
        <span>/</span>
        <span class="text-gray-600">{{ report.label || 'Rapport du ' + report.session_date }}</span>
      </div>

      <!-- En-tête du rapport -->
      <div class="bg-white rounded-xl border shadow-sm p-5 mb-5">
        <div class="flex items-start gap-4 justify-between flex-wrap">
          <div class="flex items-start gap-3 flex-1">
            <div :class="['w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0',
              report.missing_count > 0 ? 'bg-red-100' : 'bg-green-100']">
              <ClipboardDocumentCheckIcon :class="['w-6 h-6', report.missing_count > 0 ? 'text-red-600' : 'text-green-600']" />
            </div>
            <div class="flex-1">
              <!-- Mode lecture -->
              <div v-if="!editing">
                <h1 class="text-xl font-bold text-gray-900">
                  {{ report.label || 'Inventaire du ' + report.session_date }}
                  <span v-if="report.is_archived" class="ml-2 text-xs bg-amber-100 text-amber-600 px-2 py-0.5 rounded-full font-normal">Archivé</span>
                </h1>
                <p class="text-sm text-gray-500 mt-0.5">
                  📅 {{ report.session_date }} &nbsp;·&nbsp; Par {{ report.creator }} &nbsp;·&nbsp; Créé le {{ report.created_at }}
                </p>
                <p class="text-sm text-gray-500">Modèles : {{ report.model_names.join(', ') }}</p>
                <p v-if="report.notes" class="text-sm text-gray-600 mt-1 italic">{{ report.notes }}</p>
              </div>

              <!-- Mode édition -->
              <div v-else class="space-y-2">
                <input v-model="editLabel" type="text" placeholder="Titre du rapport…"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
                <textarea v-model="editNotes" rows="2" placeholder="Notes…"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
                <div class="flex gap-2">
                  <button @click="submitEdit" :disabled="saving"
                    class="flex items-center gap-1 text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 disabled:opacity-50">
                    <CheckIcon class="w-3.5 h-3.5" /> Enregistrer
                  </button>
                  <button @click="cancelEdit"
                    class="flex items-center gap-1 text-xs border border-gray-300 px-3 py-1.5 rounded-lg hover:bg-gray-50">
                    <XMarkIcon class="w-3.5 h-3.5" /> Annuler
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-2 flex-wrap">
            <button v-if="!editing" @click="editing = true"
              class="flex items-center gap-1 text-xs text-gray-600 border border-gray-200 hover:bg-gray-50 px-2.5 py-1.5 rounded-lg transition">
              <PencilIcon class="w-3.5 h-3.5" /> Modifier
            </button>
            <div class="relative group">
              <button class="flex items-center gap-1.5 text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 transition">
                <DocumentArrowDownIcon class="w-3.5 h-3.5" /> Exporter ▾
              </button>
              <div class="absolute right-0 top-full mt-1 bg-white border shadow-lg rounded-lg z-10 min-w-32 hidden group-hover:block">
                <button @click="exportReport('pdf')" class="block w-full text-left px-3 py-2 text-xs hover:bg-gray-50 text-gray-700">📄 PDF</button>
                <button @click="exportReport('csv')" class="block w-full text-left px-3 py-2 text-xs hover:bg-gray-50 text-gray-700">📊 Excel / CSV</button>
              </div>
            </div>
            <button @click="toggleArchive"
              class="flex items-center gap-1.5 text-xs text-amber-600 border border-amber-200 hover:bg-amber-50 px-2.5 py-1.5 rounded-lg transition">
              <ArchiveBoxIcon class="w-3.5 h-3.5" />
              {{ report.is_archived ? 'Désarchiver' : 'Archiver' }}
            </button>
            <button @click="deleteReport"
              class="flex items-center gap-1.5 text-xs text-red-500 border border-red-200 hover:bg-red-50 px-2.5 py-1.5 rounded-lg transition">
              <TrashIcon class="w-3.5 h-3.5" /> Supprimer
            </button>
          </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-4 gap-3 mt-4 pt-4 border-t">
          <div class="text-center">
            <div class="text-2xl font-bold text-gray-900">{{ report.total_count }}</div>
            <div class="text-xs text-gray-500">Total</div>
          </div>
          <div class="text-center">
            <div class="text-2xl font-bold text-green-600">{{ report.present_count }}</div>
            <div class="text-xs text-gray-500">Présents</div>
          </div>
          <div class="text-center">
            <div class="text-2xl font-bold text-red-600">{{ report.missing_count }}</div>
            <div class="text-xs text-gray-500">Manquants</div>
          </div>
          <div class="text-center">
            <div class="text-2xl font-bold text-gray-400">{{ report.pending_count }}</div>
            <div class="text-xs text-gray-500">Non traités</div>
          </div>
        </div>
        <!-- Barre -->
        <div class="w-full bg-gray-100 rounded-full h-2 mt-3 overflow-hidden flex">
          <div class="h-2 bg-green-500 transition-all"
            :style="{ width: report.total_count > 0 ? (report.present_count / report.total_count * 100) + '%' : '0%' }" />
          <div class="h-2 bg-red-400 transition-all"
            :style="{ width: report.total_count > 0 ? (report.missing_count / report.total_count * 100) + '%' : '0%' }" />
        </div>
      </div>

      <!-- Filtres du tableau -->
      <div class="flex items-center gap-3 mb-3">
        <span class="text-sm font-medium text-gray-700">Filtrer :</span>
        <button @click="filterStatus = ''"
          :class="['text-xs px-3 py-1.5 rounded-full border transition',
            filterStatus === '' ? 'bg-gray-800 text-white border-gray-800' : 'border-gray-300 text-gray-600 hover:bg-gray-50']">
          Tous ({{ report.items.length }})
        </button>
        <button @click="filterStatus = 'present'"
          :class="['text-xs px-3 py-1.5 rounded-full border transition',
            filterStatus === 'present' ? 'bg-green-600 text-white border-green-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50']">
          ✓ Présents ({{ report.present_count }})
        </button>
        <button @click="filterStatus = 'missing'"
          :class="['text-xs px-3 py-1.5 rounded-full border transition',
            filterStatus === 'missing' ? 'bg-red-600 text-white border-red-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50']">
          ✗ Manquants ({{ report.missing_count }})
        </button>
        <button v-if="report.pending_count > 0" @click="filterStatus = 'pending'"
          :class="['text-xs px-3 py-1.5 rounded-full border transition',
            filterStatus === 'pending' ? 'bg-gray-600 text-white border-gray-600' : 'border-gray-300 text-gray-600 hover:bg-gray-50']">
          — Non traités ({{ report.pending_count }})
        </button>
      </div>

      <!-- Tableau des équipements -->
      <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b">
              <th @click="sortItems('asset_name')" class="text-left px-4 py-3 text-xs font-semibold text-gray-600 cursor-pointer hover:text-blue-600 select-none">
                {{ colHeader('asset_name', 'Équipement') }}
              </th>
              <th @click="sortItems('asset_tag')" class="text-left px-4 py-3 text-xs font-semibold text-gray-600 cursor-pointer hover:text-blue-600 select-none">
                {{ colHeader('asset_tag', 'Tag') }}
              </th>
              <th @click="sortItems('model_name')" class="text-left px-4 py-3 text-xs font-semibold text-gray-600 cursor-pointer hover:text-blue-600 select-none hidden sm:table-cell">
                {{ colHeader('model_name', 'Modèle') }}
              </th>
              <th @click="sortItems('serial')" class="text-left px-4 py-3 text-xs font-semibold text-gray-600 cursor-pointer hover:text-blue-600 select-none hidden md:table-cell">
                {{ colHeader('serial', 'N° Série') }}
              </th>
              <th @click="sortItems('location')" class="text-left px-4 py-3 text-xs font-semibold text-gray-600 cursor-pointer hover:text-blue-600 select-none hidden lg:table-cell">
                {{ colHeader('location', 'Emplacement') }}
              </th>
              <th @click="sortItems('scan_status')" class="text-center px-4 py-3 text-xs font-semibold text-gray-600 cursor-pointer hover:text-blue-600 select-none">
                {{ colHeader('scan_status', 'Statut') }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="item in sortedItems()" :key="item.id"
              :class="['hover:bg-gray-50 transition', item.scan_status === 'missing' ? 'bg-red-50/40' : '']">
              <td class="px-4 py-3 font-medium text-gray-900">{{ item.asset_name }}</td>
              <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ item.asset_tag }}</td>
              <td class="px-4 py-3 text-gray-600 hidden sm:table-cell">{{ item.model_name || '—' }}</td>
              <td class="px-4 py-3 font-mono text-xs text-gray-500 hidden md:table-cell">{{ item.serial || '—' }}</td>
              <td class="px-4 py-3 text-gray-500 hidden lg:table-cell">{{ item.location || '—' }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="['inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full', statusClass[item.scan_status]]">
                  {{ statusIcon[item.scan_status] }} {{ statusLabel[item.scan_status] }}
                </span>
              </td>
            </tr>
            <tr v-if="sortedItems().length === 0">
              <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">Aucun élément.</td>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </AppLayout>
</template>
