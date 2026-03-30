<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { CheckIcon, XMarkIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  assets:     Array,
  modelNames: Array,
})

// Map assetId → scanned (true/false)
const scanned   = ref({})  // true = présent
const scanInput = ref('')
const scanError = ref('')
const scanning  = ref(false)

// Stats
const totalCount    = computed(() => props.assets.length)
const scannedCount  = computed(() => Object.values(scanned.value).filter(Boolean).length)
const missingCount  = computed(() => Object.values(scanned.value).filter(v => v === false).length)
const remainingCount = computed(() => totalCount.value - scannedCount.value - missingCount.value)
const progress      = computed(() => totalCount.value > 0 ? Math.round((scannedCount.value + missingCount.value) / totalCount.value * 100) : 0)

// Listes
const allScanned  = computed(() => props.assets.filter(a => scanned.value[a.id] === true))
const allMissing  = computed(() => props.assets.filter(a => scanned.value[a.id] === false))
const allPending  = computed(() => props.assets.filter(a => scanned.value[a.id] === undefined))

function processTag(tag) {
  const t = tag.trim()
  if (!t) return
  const asset = props.assets.find(a => a.asset_tag === t || a.serial === t)
  if (!asset) {
    scanError.value = `Tag "${t}" non trouvé dans cette session`
    return
  }
  if (scanned.value[asset.id] !== undefined) {
    scanError.value = `"${asset.name}" déjà traité`
    return
  }
  scanned.value = { ...scanned.value, [asset.id]: true }
  scanError.value = ''
}

function onScanKeydown(e) {
  if (e.key === 'Enter') {
    e.preventDefault()
    if (scanning.value) return
    scanning.value = true
    const tag = scanInput.value
    scanInput.value = ''
    processTag(tag)
    scanning.value = false
  }
}

function markPresent(assetId) {
  scanned.value = { ...scanned.value, [assetId]: true }
}

function markMissing(assetId) {
  scanned.value = { ...scanned.value, [assetId]: false }
}

function unmark(assetId) {
  const s = { ...scanned.value }
  delete s[assetId]
  scanned.value = s
}

function resetSession() {
  if (!confirm('Réinitialiser la session ? Tous les scans seront perdus.')) return
  scanned.value = {}
  scanError.value = ''
}
</script>

<template>
  <AppLayout>
    <div class="max-w-4xl">
      <!-- En-tête -->
      <div class="flex items-start justify-between mb-4 gap-4">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <button @click="router.get(route('inventory.index'))"
              class="text-sm text-gray-400 hover:text-gray-600 transition">← Retour</button>
          </div>
          <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <ClipboardDocumentCheckIcon class="w-6 h-6 text-blue-600" />
            Session d'inventaire
          </h1>
          <p class="text-sm text-gray-500">{{ modelNames.join(', ') }}</p>
        </div>
        <button @click="resetSession" class="text-xs text-gray-400 hover:text-red-500 border border-gray-200 px-3 py-1.5 rounded-lg transition">
          Réinitialiser
        </button>
      </div>

      <!-- Progression -->
      <div class="bg-white rounded-xl border shadow-sm p-4 mb-4">
        <div class="flex items-center justify-between text-sm mb-2">
          <span class="font-medium text-gray-900">Progression</span>
          <span class="text-gray-500">{{ scannedCount + missingCount }} / {{ totalCount }}</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-3 mb-3">
          <div class="h-3 rounded-full transition-all duration-500"
            :class="progress === 100 ? 'bg-green-500' : 'bg-blue-500'"
            :style="{ width: progress + '%' }" />
        </div>
        <div class="flex gap-4 text-xs">
          <span class="flex items-center gap-1.5 text-green-600">
            <span class="w-3 h-3 bg-green-500 rounded-full"></span>
            Présents : {{ scannedCount }}
          </span>
          <span class="flex items-center gap-1.5 text-red-600">
            <span class="w-3 h-3 bg-red-500 rounded-full"></span>
            Manquants : {{ missingCount }}
          </span>
          <span class="flex items-center gap-1.5 text-gray-400">
            <span class="w-3 h-3 bg-gray-300 rounded-full"></span>
            Restants : {{ remainingCount }}
          </span>
        </div>
        <div v-if="progress === 100" class="mt-3 flex items-center gap-2 text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2 text-sm">
          ✅ Inventaire terminé ! {{ scannedCount }} présents, {{ missingCount }} manquants.
        </div>
      </div>

      <!-- Zone de scan -->
      <div class="bg-white rounded-xl border shadow-sm p-4 mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">Scanner un code-barre</label>
        <div class="flex gap-3">
          <input v-model="scanInput" @keydown="onScanKeydown" type="text"
            autofocus autocomplete="off"
            placeholder="Scanner ou saisir le tag équipement…"
            class="flex-1 border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          <button @click="() => { processTag(scanInput); scanInput = '' }"
            class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition">
            Valider
          </button>
        </div>
        <p v-if="scanError" class="text-red-500 text-xs mt-2">⚠ {{ scanError }}</p>
        <p class="text-xs text-gray-400 mt-1.5">Appuyez sur Entrée ou cliquez Valider. Le tag ou le numéro de série sont acceptés.</p>
      </div>

      <!-- Équipements à vérifier (non traités) -->
      <div v-if="allPending.length > 0" class="bg-white rounded-xl border shadow-sm mb-4">
        <div class="px-4 py-3 border-b flex items-center justify-between">
          <h3 class="font-medium text-gray-900 text-sm">À vérifier ({{ allPending.length }})</h3>
        </div>
        <div class="divide-y">
          <div v-for="asset in allPending" :key="asset.id"
            class="flex items-center gap-3 px-4 py-3">
            <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0 overflow-hidden">
              <img v-if="asset.image" :src="asset.image" class="w-full h-full object-cover" />
              <span v-else class="text-base">📦</span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 truncate">{{ asset.name }}</p>
              <p class="text-xs text-gray-400 font-mono">{{ asset.asset_tag }}</p>
            </div>
            <p class="text-xs text-gray-400 hidden sm:block">{{ asset.model_name }}</p>
            <div class="flex gap-1.5 flex-shrink-0">
              <button @click="markPresent(asset.id)"
                class="flex items-center gap-1 text-xs bg-green-100 text-green-700 hover:bg-green-200 px-2.5 py-1.5 rounded-lg transition font-medium">
                <CheckIcon class="w-3.5 h-3.5" /> Présent
              </button>
              <button @click="markMissing(asset.id)"
                class="flex items-center gap-1 text-xs bg-red-100 text-red-700 hover:bg-red-200 px-2.5 py-1.5 rounded-lg transition font-medium">
                <XMarkIcon class="w-3.5 h-3.5" /> Absent
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Équipements présents -->
      <div v-if="allScanned.length > 0" class="bg-white rounded-xl border border-green-200 shadow-sm mb-4">
        <div class="px-4 py-3 border-b border-green-200 bg-green-50 rounded-t-xl">
          <h3 class="font-medium text-green-800 text-sm">✅ Présents ({{ allScanned.length }})</h3>
        </div>
        <div class="divide-y">
          <div v-for="asset in allScanned" :key="asset.id"
            class="flex items-center gap-3 px-4 py-2.5 bg-green-50/30">
            <div class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0 overflow-hidden">
              <img v-if="asset.image" :src="asset.image" class="w-full h-full object-cover" />
              <span v-else class="text-sm">📦</span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm text-gray-800 truncate">{{ asset.name }}</p>
              <p class="text-xs text-gray-400 font-mono">{{ asset.asset_tag }}</p>
            </div>
            <button @click="unmark(asset.id)" class="text-xs text-gray-300 hover:text-red-400 transition px-2">✕</button>
          </div>
        </div>
      </div>

      <!-- Équipements manquants -->
      <div v-if="allMissing.length > 0" class="bg-white rounded-xl border border-red-200 shadow-sm mb-4">
        <div class="px-4 py-3 border-b border-red-200 bg-red-50 rounded-t-xl">
          <h3 class="font-medium text-red-800 text-sm">⚠ Manquants / Absents ({{ allMissing.length }})</h3>
        </div>
        <div class="divide-y">
          <div v-for="asset in allMissing" :key="asset.id"
            class="flex items-center gap-3 px-4 py-2.5 bg-red-50/30">
            <div class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0 overflow-hidden">
              <img v-if="asset.image" :src="asset.image" class="w-full h-full object-cover" />
              <span v-else class="text-sm">📦</span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm text-gray-800 truncate">{{ asset.name }}</p>
              <p class="text-xs text-gray-400 font-mono">{{ asset.asset_tag }}</p>
            </div>
            <button @click="unmark(asset.id)" class="text-xs text-gray-300 hover:text-blue-400 transition px-2">↩</button>
          </div>
        </div>
      </div>

    </div>
  </AppLayout>
</template>
