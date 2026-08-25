<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { MagnifyingGlassIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  models: Array,
})

const search      = ref('')
const selectedIds = ref([])

const filtered = computed(() =>
  props.models.filter(m =>
    m.name.toLowerCase().includes(search.value.toLowerCase())
  )
)

function toggleModel(id) {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter(i => i !== id)
  } else {
    selectedIds.value = [...selectedIds.value, id]
  }
}

function selectAll() {
  selectedIds.value = filtered.value.map(m => m.id)
}

function clearSelection() {
  selectedIds.value = []
}

watch(search, () => {
  selectedIds.value = []
})

function startSession() {
  if (selectedIds.value.length === 0) return
  router.post(route('inventory.session'), { models: selectedIds.value })
}
</script>

<template>
  <AppLayout>
    <div class="max-w-5xl">
      <!-- En-tête -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <ClipboardDocumentCheckIcon class="w-7 h-7 text-blue-600" />
            Inventaire
          </h1>
          <p class="text-sm text-gray-500 mt-0.5">
            Sélectionnez les modèles à inventorier, puis scannez les équipements un à un.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button @click="router.get(route('inventory.history'))"
            class="flex items-center gap-2 border border-gray-300 text-gray-700 px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
            📋 Historique
          </button>
          <button @click="startSession" :disabled="selectedIds.length === 0"
            class="flex items-center gap-2 bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-40 transition shadow-sm">
            Démarrer l'inventaire
            <span v-if="selectedIds.length > 0" class="bg-white/20 text-white text-xs font-bold px-2 py-0.5 rounded-full">
              {{ selectedIds.length }}
            </span>
          </button>
        </div>
      </div>

      <!-- Barre de recherche + actions -->
      <div class="flex items-center gap-3 mb-4">
        <div class="relative flex-1">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input v-model="search" type="text" placeholder="Rechercher un modèle…"
            class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
        </div>
        <button @click="selectAll" class="text-xs text-blue-600 hover:underline whitespace-nowrap">
          Tout sélectionner
        </button>
        <button v-if="selectedIds.length > 0" @click="clearSelection" class="text-xs text-gray-400 hover:underline whitespace-nowrap">
          Tout désélectionner
        </button>
      </div>

      <!-- Compteur sélection -->
      <div v-if="selectedIds.length > 0" class="mb-4 flex items-center gap-2 bg-blue-50 border border-blue-200 rounded-lg px-4 py-2 text-sm text-blue-800">
        <ClipboardDocumentCheckIcon class="w-4 h-4" />
        {{ selectedIds.length }} modèle(s) sélectionné(s) —
        {{ models.filter(m => selectedIds.includes(m.id)).reduce((s, m) => s + m.assets_count, 0) }} équipement(s) à vérifier
      </div>

      <!-- Grille des modèles -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
        <div v-if="filtered.length === 0" class="col-span-full text-center py-16 text-gray-400">
          Aucun modèle trouvé.
        </div>
        <button v-for="m in filtered" :key="m.id"
          @click="toggleModel(m.id)"
          :class="['relative flex flex-col items-center gap-2 p-3 rounded-xl border-2 transition text-left',
            selectedIds.includes(m.id)
              ? 'border-blue-500 bg-blue-50 shadow-md'
              : 'border-gray-200 bg-white hover:border-blue-300 hover:shadow-sm']">
          <!-- Coche sélection -->
          <div :class="['absolute top-2 right-2 w-5 h-5 rounded-full border-2 flex items-center justify-center transition',
            selectedIds.includes(m.id) ? 'bg-blue-600 border-blue-600' : 'border-gray-300 bg-white']">
            <svg v-if="selectedIds.includes(m.id)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <!-- Image modèle -->
          <div class="w-14 h-14 rounded-lg bg-gray-100 overflow-hidden flex items-center justify-center flex-shrink-0">
            <img v-if="m.image_url" :src="m.image_url" class="w-full h-full object-contain p-1" />
            <span v-else class="text-2xl">📦</span>
          </div>
          <!-- Infos -->
          <div class="text-center w-full">
            <p class="text-xs font-semibold text-gray-900 leading-tight line-clamp-2">{{ m.name }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ m.assets_count }} équipement(s)</p>
          </div>
        </button>
      </div>
    </div>
  </AppLayout>
</template>
