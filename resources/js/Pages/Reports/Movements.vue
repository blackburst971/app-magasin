<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { MagnifyingGlassIcon, DocumentArrowDownIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  movements: Object,
  filters:   Object,
  actions:   Object,
})

const search     = ref(props.filters?.search ?? '')
const selAction  = ref(props.filters?.action ?? '')
const dateFrom   = ref(props.filters?.from ?? '')
const dateTo     = ref(props.filters?.to ?? '')

let debounceTimer = null
function applyFilters() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    router.get(route('reports.movements'), {
      search:  search.value     || undefined,
      action:  selAction.value  || undefined,
      from:    dateFrom.value   || undefined,
      to:      dateTo.value     || undefined,
    }, { preserveState: true, replace: true })
  }, 300)
}

function clearFilters() {
  search.value    = ''
  selAction.value = ''
  dateFrom.value  = ''
  dateTo.value    = ''
  router.get(route('reports.movements'), {}, { preserveState: true, replace: true })
}

function exportMovements(format) {
  const params = new URLSearchParams({
    format,
    ...(search.value    ? { search:  search.value }    : {}),
    ...(selAction.value ? { action:  selAction.value }  : {}),
    ...(dateFrom.value  ? { from:    dateFrom.value }   : {}),
    ...(dateTo.value    ? { to:      dateTo.value }     : {}),
  })
  window.open(route('reports.export-movements') + '?' + params.toString(), '_blank')
}

const actionColor = {
  checkout:       'bg-orange-100 text-orange-700',
  return:         'bg-green-100 text-green-700',
  maintenance:    'bg-yellow-100 text-yellow-700',
  import:         'bg-blue-100 text-blue-700',
  status_change:  'bg-purple-100 text-purple-700',
  place_assigned: 'bg-cyan-100 text-cyan-700',
  place_returned: 'bg-indigo-100 text-indigo-700',
}
</script>

<template>
  <AppLayout>
    <div class="max-w-6xl">

      <!-- En-tête -->
      <div class="flex items-start justify-between mb-6 gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <button @click="router.get(route('reports.index'))"
              class="text-sm text-gray-400 hover:text-gray-600 transition">← Rapports</button>
          </div>
          <h1 class="text-2xl font-bold text-gray-900">📋 Historique des mouvements</h1>
          <p class="text-sm text-gray-500 mt-0.5">
            Tous les mouvements d'équipements enregistrés — {{ movements.total }} au total.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <div class="relative group">
            <button class="flex items-center gap-1.5 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition shadow-sm">
              <DocumentArrowDownIcon class="w-4 h-4" /> Exporter ▾
            </button>
            <div class="absolute right-0 top-full mt-1 bg-white border shadow-lg rounded-lg z-10 min-w-32 hidden group-hover:block">
              <button @click="exportMovements('pdf')" class="block w-full text-left px-3 py-2.5 text-sm hover:bg-gray-50 text-gray-700">📄 PDF</button>
              <button @click="exportMovements('csv')" class="block w-full text-left px-3 py-2.5 text-sm hover:bg-gray-50 text-gray-700">📊 Excel / CSV</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Filtres -->
      <div class="bg-white rounded-xl border shadow-sm p-4 mb-5 flex flex-wrap gap-3 items-end">
        <!-- Recherche -->
        <div class="relative flex-1 min-w-52">
          <MagnifyingGlassIcon class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
          <input v-model="search" @input="applyFilters" type="search"
            placeholder="Rechercher un équipement, tag…"
            class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
        </div>
        <!-- Type d'action -->
        <select v-model="selAction" @change="applyFilters"
          class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none min-w-44">
          <option value="">Toutes les actions</option>
          <option v-for="(label, key) in actions" :key="key" :value="key">{{ label }}</option>
        </select>
        <!-- Date de -->
        <div class="flex flex-col gap-1">
          <label class="text-xs text-gray-500">Du</label>
          <input v-model="dateFrom" @change="applyFilters" type="date"
            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
        </div>
        <!-- Date à -->
        <div class="flex flex-col gap-1">
          <label class="text-xs text-gray-500">Au</label>
          <input v-model="dateTo" @change="applyFilters" type="date"
            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
        </div>
        <button @click="clearFilters"
          class="flex items-center gap-1.5 text-xs text-gray-500 border border-gray-300 hover:bg-gray-50 px-3 py-2 rounded-lg transition self-end">
          <ArrowPathIcon class="w-3.5 h-3.5" /> Réinitialiser
        </button>
      </div>

      <!-- Tableau -->
      <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
        <div v-if="movements.data.length === 0" class="text-center py-16 text-gray-400 text-sm">
          Aucun mouvement trouvé pour ces critères.
        </div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b">
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Date</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600">Équipement</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600 hidden sm:table-cell">Action</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600 hidden md:table-cell">Utilisateur</th>
              <th class="text-left px-4 py-3 text-xs font-semibold text-gray-600 hidden lg:table-cell">Notes</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="m in movements.data" :key="m.id" class="hover:bg-gray-50 transition">
              <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap font-mono">{{ m.date }}</td>
              <td class="px-4 py-3">
                <div class="font-medium text-gray-900 text-sm">{{ m.asset }}</div>
                <div class="text-xs text-gray-400 font-mono">{{ m.asset_tag }}</div>
              </td>
              <td class="px-4 py-3 hidden sm:table-cell">
                <span :class="['inline-flex items-center text-xs font-medium px-2.5 py-1 rounded-full', actionColor[m.action] ?? 'bg-gray-100 text-gray-600']">
                  {{ m.action_label }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600 hidden md:table-cell">{{ m.user }}</td>
              <td class="px-4 py-3 text-sm text-gray-400 hidden lg:table-cell max-w-xs truncate" :title="m.notes">
                {{ m.notes || '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="movements.last_page > 1" class="flex items-center justify-between mt-5">
        <span class="text-sm text-gray-500">
          Page {{ movements.current_page }} / {{ movements.last_page }} — {{ movements.total }} mouvement(s)
        </span>
        <div class="flex gap-1">
          <button
            @click="router.get(route('reports.movements'), { ...filters, page: movements.current_page - 1 }, { preserveState: true })"
            :disabled="movements.current_page === 1"
            class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition">
            ← Précédent
          </button>
          <!-- Pages courantes -->
          <template v-for="p in movements.last_page" :key="p">
            <button v-if="Math.abs(p - movements.current_page) <= 2 || p === 1 || p === movements.last_page"
              @click="router.get(route('reports.movements'), { ...filters, page: p }, { preserveState: true })"
              :class="['px-3 py-1.5 rounded-lg text-sm transition border',
                p === movements.current_page
                  ? 'bg-blue-600 text-white border-blue-600'
                  : 'border-gray-300 text-gray-600 hover:bg-gray-50']">
              {{ p }}
            </button>
            <span v-else-if="p === movements.current_page - 3 || p === movements.current_page + 3"
              class="px-2 py-1.5 text-gray-400 text-sm">…</span>
          </template>
          <button
            @click="router.get(route('reports.movements'), { ...filters, page: movements.current_page + 1 }, { preserveState: true })"
            :disabled="movements.current_page === movements.last_page"
            class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition">
            Suivant →
          </button>
        </div>
      </div>

    </div>
  </AppLayout>
</template>
