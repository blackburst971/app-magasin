<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import {
  ClipboardDocumentCheckIcon, ArchiveBoxIcon, TrashIcon,
  DocumentArrowDownIcon, EyeIcon, MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  reports:         Object,
  filters:         Object,
  availableMonths: Array,
})

const showArchived  = ref(props.filters?.archived === 'true' || props.filters?.archived === true)
const selectedMonth = ref(props.filters?.month ?? '')

function applyFilters() {
  router.get(route('inventory.history'), {
    archived: showArchived.value ? '1' : undefined,
    month:    selectedMonth.value || undefined,
  }, { preserveState: true, replace: true })
}

function toggleArchive(report) {
  router.patch(route('inventory.reports.archive', report.id), {}, {
    preserveScroll: true,
  })
}

function deleteReport(report) {
  if (!confirm(`Supprimer définitivement ce rapport du ${report.session_date} ?`)) return
  router.delete(route('inventory.reports.destroy', report.id))
}

function exportReport(report, format) {
  window.open(route('inventory.reports.export', { report: report.id, format }), '_blank')
}

const monthLabel = (m) => {
  if (!m) return ''
  const [y, mo] = m.split('-')
  const names = ['Janvier','Février','Mars','Avril','Mai','Juin',
                 'Juillet','Août','Septembre','Octobre','Novembre','Décembre']
  return names[parseInt(mo, 10) - 1] + ' ' + y
}

const statusBadge = computed(() => showArchived.value
  ? { label: 'Archivés', cls: 'bg-amber-100 text-amber-700 border border-amber-200' }
  : { label: 'Actifs', cls: 'bg-green-100 text-green-700 border border-green-200' })
</script>

<template>
  <AppLayout>
    <div class="max-w-5xl">

      <!-- En-tête -->
      <div class="flex items-start justify-between mb-6 gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <button @click="router.get(route('inventory.index'))"
              class="text-sm text-gray-400 hover:text-gray-600 transition">← Inventaire</button>
          </div>
          <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <ClipboardDocumentCheckIcon class="w-7 h-7 text-blue-600" />
            Historique des inventaires
          </h1>
          <p class="text-sm text-gray-500 mt-0.5">
            Consultez et exportez les rapports d'inventaires passés.
          </p>
        </div>
        <button @click="router.get(route('inventory.index'))"
          class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition shadow-sm">
          <ClipboardDocumentCheckIcon class="w-4 h-4" />
          Nouvel inventaire
        </button>
      </div>

      <!-- Filtres -->
      <div class="bg-white rounded-xl border shadow-sm p-4 mb-5 flex flex-wrap gap-3 items-center">
        <!-- Mois -->
        <select v-model="selectedMonth" @change="applyFilters"
          class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none min-w-44">
          <option value="">Tous les mois</option>
          <option v-for="m in availableMonths" :key="m" :value="m">{{ monthLabel(m) }}</option>
        </select>

        <!-- Toggle archivés -->
        <label class="flex items-center gap-2 cursor-pointer select-none text-sm">
          <div class="relative">
            <input type="checkbox" v-model="showArchived" @change="applyFilters" class="sr-only peer" />
            <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-500"></div>
          </div>
          <span class="text-gray-700">Afficher les archivés</span>
        </label>

        <span :class="['text-xs font-medium px-2.5 py-1 rounded-full', statusBadge.cls]">
          {{ statusBadge.label }}
        </span>

        <span class="ml-auto text-sm text-gray-400">{{ reports.total }} rapport(s)</span>
      </div>

      <!-- Liste des rapports -->
      <div v-if="reports.data.length === 0" class="bg-white rounded-xl border shadow-sm p-16 text-center text-gray-400">
        <ClipboardDocumentCheckIcon class="w-12 h-12 mx-auto mb-3 opacity-30" />
        <p class="text-sm">Aucun rapport d'inventaire trouvé.</p>
        <p class="text-xs mt-1">Lancez un inventaire et sauvegardez-le pour le retrouver ici.</p>
      </div>

      <div v-else class="space-y-3">
        <div v-for="report in reports.data" :key="report.id"
          class="bg-white rounded-xl border shadow-sm hover:shadow-md transition"
          :class="report.is_archived ? 'opacity-70' : ''">
          <div class="p-4 flex items-start gap-4">

            <!-- Icône + statut -->
            <div class="flex-shrink-0 flex flex-col items-center gap-1">
              <div :class="['w-10 h-10 rounded-xl flex items-center justify-center',
                report.missing_count > 0 ? 'bg-red-100' : 'bg-green-100']">
                <ClipboardDocumentCheckIcon :class="['w-5 h-5', report.missing_count > 0 ? 'text-red-600' : 'text-green-600']" />
              </div>
              <span v-if="report.is_archived" class="text-xs text-amber-600 font-medium">Archivé</span>
            </div>

            <!-- Infos principales -->
            <div class="flex-1 min-w-0">
              <div class="flex items-start gap-2 flex-wrap">
                <span class="font-semibold text-gray-900 text-sm">
                  {{ report.label || 'Inventaire du ' + report.session_date }}
                </span>
                <span class="text-xs text-gray-400">par {{ report.creator }}</span>
              </div>
              <p class="text-xs text-gray-500 mt-0.5">
                📅 {{ report.session_date }} &nbsp;·&nbsp;
                Modèles : {{ report.model_names.join(', ') }}
              </p>
              <p v-if="report.notes" class="text-xs text-gray-400 mt-1 italic">{{ report.notes }}</p>

              <!-- Stats -->
              <div class="flex gap-3 mt-2">
                <span class="text-xs text-gray-500">{{ report.total_count }} total</span>
                <span class="text-xs text-green-600 font-medium">✓ {{ report.present_count }} présents</span>
                <span v-if="report.missing_count > 0" class="text-xs text-red-600 font-medium">✗ {{ report.missing_count }} manquants</span>
                <span v-if="report.pending_count > 0" class="text-xs text-gray-400">— {{ report.pending_count }} non traités</span>
              </div>

              <!-- Barre de progression -->
              <div class="w-full bg-gray-100 rounded-full h-1.5 mt-2 max-w-xs">
                <div class="h-1.5 rounded-full"
                  :class="report.missing_count > 0 ? 'bg-red-400' : 'bg-green-500'"
                  :style="{ width: report.total_count > 0
                    ? Math.round((report.present_count + report.missing_count) / report.total_count * 100) + '%'
                    : '0%' }" />
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-1.5 flex-shrink-0 flex-wrap justify-end">
              <a :href="route('inventory.reports.show', report.id)"
                class="flex items-center gap-1 text-xs text-blue-600 border border-blue-200 hover:bg-blue-50 px-2.5 py-1.5 rounded-lg transition">
                <EyeIcon class="w-3.5 h-3.5" /> Voir
              </a>
              <div class="relative group">
                <button class="flex items-center gap-1 text-xs text-gray-600 border border-gray-200 hover:bg-gray-50 px-2.5 py-1.5 rounded-lg transition">
                  <DocumentArrowDownIcon class="w-3.5 h-3.5" /> Export ▾
                </button>
                <div class="absolute right-0 top-full mt-1 bg-white border shadow-lg rounded-lg z-10 min-w-28 hidden group-hover:block">
                  <button @click="exportReport(report, 'pdf')"
                    class="block w-full text-left px-3 py-2 text-xs hover:bg-gray-50 text-gray-700">
                    📄 PDF
                  </button>
                  <button @click="exportReport(report, 'csv')"
                    class="block w-full text-left px-3 py-2 text-xs hover:bg-gray-50 text-gray-700">
                    📊 Excel / CSV
                  </button>
                </div>
              </div>
              <button @click="toggleArchive(report)"
                :title="report.is_archived ? 'Désarchiver' : 'Archiver'"
                class="flex items-center gap-1 text-xs text-amber-600 border border-amber-200 hover:bg-amber-50 px-2.5 py-1.5 rounded-lg transition">
                <ArchiveBoxIcon class="w-3.5 h-3.5" />
                {{ report.is_archived ? 'Désarchiver' : 'Archiver' }}
              </button>
              <button @click="deleteReport(report)"
                class="flex items-center gap-1 text-xs text-red-400 border border-red-200 hover:bg-red-50 px-2.5 py-1.5 rounded-lg transition">
                <TrashIcon class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="reports.last_page > 1" class="flex justify-center gap-1 mt-6">
        <button v-for="p in reports.last_page" :key="p"
          @click="router.get(route('inventory.history'), { ...filters, page: p }, { preserveState: true })"
          :class="['px-3 py-1.5 rounded-lg text-sm transition',
            p === reports.current_page
              ? 'bg-blue-600 text-white'
              : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50']">
          {{ p }}
        </button>
      </div>

    </div>
  </AppLayout>
</template>
