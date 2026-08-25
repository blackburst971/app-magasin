<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const page    = usePage()
const isAdmin = computed(() => page.props.auth.user?.is_admin)

const props = defineProps({ stats: Object, topAssets: Array, recentMovements: Array, period: String })
const selectedPeriod = ref(props.period ?? '30')

const changePeriod  = () => router.get(route('reports.index'), { period: selectedPeriod.value }, { preserveState: true })
const exportReport  = (type) => window.open(route('reports.export', { type, period: selectedPeriod.value }), '_blank')
const exportCsv     = (type) => {
  const routeMap = {
    assets:      route('reports.export-csv'),
    licenses:    route('reports.export-csv-licenses'),
    computers:   route('reports.export-csv-computers'),
    consommables:route('reports.export-csv-consommables'),
    models:      route('reports.export-csv-models'),
  }
  window.open(routeMap[type], '_blank')
}
</script>

<template>
  <AppLayout>
    <div>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <h1 class="text-2xl font-bold text-gray-900">📊 Rapports</h1>
        <div class="flex flex-wrap items-center gap-2">
          <select v-model="selectedPeriod" @change="changePeriod" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <option value="7">7 derniers jours</option>
            <option value="30">30 derniers jours</option>
            <option value="90">90 derniers jours</option>
            <option value="365">1 an</option>
          </select>
          <button @click="exportReport('assets')" class="text-sm border border-gray-300 px-3 py-2 rounded-lg hover:bg-gray-50">📄 PDF équipements</button>
          <button @click="exportReport('reservations')" class="text-sm border border-gray-300 px-3 py-2 rounded-lg hover:bg-gray-50">📄 PDF réservations</button>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl p-4 shadow-sm border text-center">
          <div class="text-3xl font-bold text-blue-600">{{ stats.total_reservations }}</div>
          <div class="text-sm text-gray-500 mt-1">Réservations</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm border text-center">
          <div class="text-3xl font-bold text-green-600">{{ stats.returned }}</div>
          <div class="text-sm text-gray-500 mt-1">Retournées</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm border text-center">
          <div class="text-3xl font-bold text-purple-600">{{ stats.active }}</div>
          <div class="text-sm text-gray-500 mt-1">En cours</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm border text-center">
          <div class="text-3xl font-bold text-yellow-600">{{ stats.pending }}</div>
          <div class="text-sm text-gray-500 mt-1">En attente</div>
        </div>
      </div>

      <div class="grid lg:grid-cols-2 gap-6 mb-6">
        <!-- Top assets -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="font-semibold mb-4">🏆 Équipements les plus empruntés</h2>
          <div class="space-y-3">
            <div v-for="(asset, i) in topAssets" :key="i" class="flex items-center gap-3">
              <span class="text-sm font-bold text-gray-400 w-5">{{ i+1 }}</span>
              <div class="flex-1">
                <div class="flex justify-between text-sm mb-1">
                  <span class="font-medium text-gray-900">{{ asset.name }}</span>
                  <span class="text-gray-500">{{ asset.count }} emprunt(s)</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-1.5">
                  <div class="bg-blue-600 h-1.5 rounded-full" :style="{width: `${(asset.count / (topAssets[0]?.count || 1)) * 100}%`}"></div>
                </div>
              </div>
            </div>
            <p v-if="topAssets.length === 0" class="text-center text-gray-400 text-sm py-4">Aucune donnée</p>
          </div>
        </div>

        <!-- Recent movements -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold">📋 Mouvements récents</h2>
            <a v-if="isAdmin" :href="route('reports.movements')"
              class="text-xs text-blue-600 hover:underline font-medium">
              Voir tout l'historique →
            </a>
          </div>
          <div class="space-y-2 max-h-64 overflow-y-auto">
            <div v-for="m in recentMovements" :key="m.asset" class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-lg text-sm">
              <div>
                <span class="font-medium">{{ m.asset }}</span>
                <span class="text-gray-400 text-xs ml-1">{{ m.asset_tag }}</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-700">{{ m.action }}</span>
                <span class="text-xs text-gray-400">{{ m.date }}</span>
              </div>
            </div>
          </div>
          <a v-if="isAdmin" :href="route('reports.movements')"
            class="mt-3 block text-center text-xs text-gray-500 hover:text-blue-600 transition border border-dashed border-gray-200 rounded-lg py-2 hover:border-blue-300">
            Historique complet des mouvements
          </a>
        </div>
      </div>

      <!-- Exports CSV (admin only) -->
      <div v-if="isAdmin" class="bg-white rounded-xl shadow-sm border p-6">
        <h2 class="font-semibold mb-4">⬇️ Exports CSV</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
          <button @click="exportCsv('assets')"
            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-blue-100 hover:border-blue-400 hover:bg-blue-50 transition text-center">
            <span class="text-2xl">🖥️</span>
            <span class="text-sm font-medium text-gray-700">Équipements</span>
          </button>
          <button @click="exportCsv('licenses')"
            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-purple-100 hover:border-purple-400 hover:bg-purple-50 transition text-center">
            <span class="text-2xl">🔑</span>
            <span class="text-sm font-medium text-gray-700">Licences</span>
          </button>
          <button @click="exportCsv('computers')"
            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-cyan-100 hover:border-cyan-400 hover:bg-cyan-50 transition text-center">
            <span class="text-2xl">💻</span>
            <span class="text-sm font-medium text-gray-700">Postes</span>
          </button>
          <button @click="exportCsv('consommables')"
            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-orange-100 hover:border-orange-400 hover:bg-orange-50 transition text-center">
            <span class="text-2xl">📦</span>
            <span class="text-sm font-medium text-gray-700">Consommables</span>
          </button>
          <button @click="exportCsv('models')"
            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 border-green-100 hover:border-green-400 hover:bg-green-50 transition text-center">
            <span class="text-2xl">📐</span>
            <span class="text-sm font-medium text-gray-700">Modèles</span>
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
