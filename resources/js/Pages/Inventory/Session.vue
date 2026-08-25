<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { CheckIcon, XMarkIcon, ClipboardDocumentCheckIcon, PrinterIcon, ArchiveBoxIcon } from '@heroicons/vue/24/outline'
import BarcodeScanButton from '@/Components/BarcodeScanButton.vue'
import { useSound } from '@/composables/useSound'

const { playSound } = useSound()

const props = defineProps({
  assets:     Array,
  modelNames: Array,
  modelIds:   Array,
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
    playSound('scan_error')
    scanError.value = `Tag "${t}" non trouvé dans cette session`
    return
  }
  if (scanned.value[asset.id] !== undefined) {
    playSound('scan_error')
    scanError.value = `"${asset.name}" déjà traité`
    return
  }
  scanned.value = { ...scanned.value, [asset.id]: true }
  scanError.value = ''
  playSound('scan_success')
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

// ─── Sauvegarde du rapport ────────────────────────────────────────────────────
const showSaveModal = ref(false)
const saveLabel     = ref('')
const saveNotes     = ref('')
const saving        = ref(false)

function openSaveModal() {
  saveLabel.value = ''
  saveNotes.value = ''
  showSaveModal.value = true
}

function saveReport() {
  if (saving.value) return
  saving.value = true

  const items = props.assets.map(a => ({
    asset_id:    a.id,
    asset_tag:   a.asset_tag,
    asset_name:  a.name,
    model_name:  a.model_name ?? null,
    location:    a.location ?? null,
    serial:      a.serial ?? null,
    scan_status: scanned.value[a.id] === true ? 'present'
                 : scanned.value[a.id] === false ? 'missing'
                 : 'pending',
  }))

  router.post(route('inventory.save-report'), {
    label:       saveLabel.value.trim() || null,
    model_names: props.modelNames,
    notes:       saveNotes.value.trim() || null,
    items,
  }, {
    onSuccess: () => { showSaveModal.value = false },
    onError:   () => { saving.value = false },
    onFinish:  () => { saving.value = false },
  })
}

// ─── Impression PDF inventaire ────────────────────────────────────────────────
function printInventory(mode = 'missing') {
  const now    = new Date().toLocaleString('fr-FR')
  const models = props.modelNames.join(', ')

  let printAssets, title, subtitle
  if (mode === 'missing') {
    printAssets = allMissing.value
    title       = 'Inventaire — Équipements manquants'
    subtitle    = `${printAssets.length} équipement(s) manquant(s) sur ${props.assets.length} au total`
  } else {
    printAssets = [...allScanned.value, ...allMissing.value, ...allPending.value]
    title       = 'Inventaire — Liste complète'
    subtitle    = `${scannedCount.value} présents · ${missingCount.value} manquants · ${remainingCount.value} non traités`
  }

  if (mode === 'missing' && printAssets.length === 0) {
    alert('Aucun équipement manquant à imprimer.')
    return
  }

  const statusLabel = {
    true:      '<span style="color:#16a34a;font-weight:600">✓ Présent</span>',
    false:     '<span style="color:#dc2626;font-weight:600">✗ Manquant</span>',
    undefined: '<span style="color:#9ca3af">— À vérifier</span>',
  }

  const rows = printAssets.map(a => {
    const s = scanned.value[a.id]
    const statusCell = mode === 'full'
      ? `<td style="text-align:center">${statusLabel[String(s)] ?? statusLabel['undefined']}</td>`
      : `<td style="width:80px;border:1px solid #ccc;"></td>`
    return `
      <tr>
        <td>${a.name}</td>
        <td style="font-family:monospace">${a.asset_tag}</td>
        <td style="font-family:monospace">${a.serial || '—'}</td>
        <td>${a.model_name || '—'}</td>
        <td>${a.location || '—'}</td>
        ${statusCell}
      </tr>`
  }).join('')

  const lastColHeader = mode === 'missing' ? 'Retrouvé ✓' : 'Statut'

  const html = `<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>${title}</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: 12px; margin: 24px; color: #111; }
    h1 { font-size: 16px; margin: 0 0 4px; }
    .meta { color: #555; font-size: 11px; margin: 0 0 4px; }
    .stats { font-size: 11px; background: #f3f4f6; padding: 6px 10px; border-radius: 4px; margin-bottom: 14px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #e5e7eb; text-align: left; padding: 6px 8px; border: 1px solid #ccc; font-size: 11px; white-space: nowrap; }
    td { padding: 5px 8px; border: 1px solid #ddd; vertical-align: middle; }
    tr:nth-child(even) td { background: #f9fafb; }
    @media print { @page { margin: 15mm; } }
  </style>
</head>
<body>
  <h1>${title}</h1>
  <p class="meta">Modèles : ${models} &nbsp;·&nbsp; Imprimé le ${now}</p>
  <p class="stats">${subtitle}</p>
  <table>
    <thead>
      <tr>
        <th>Nom</th><th>Tag</th><th>N° Série</th><th>Modèle</th><th>Emplacement</th><th>${lastColHeader}</th>
      </tr>
    </thead>
    <tbody>${rows}</tbody>
  </table>
</body>
</html>`

  const w = window.open('', '_blank', 'width=900,height=650')
  if (!w) { alert('Veuillez autoriser les popups pour imprimer.'); return }
  w.document.write(html)
  w.document.close()
  w.focus()
  setTimeout(() => w.print(), 400)
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
            <span class="text-gray-300">|</span>
            <button @click="router.get(route('inventory.history'))"
              class="text-sm text-blue-500 hover:text-blue-700 transition">📋 Historique</button>
          </div>
          <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <ClipboardDocumentCheckIcon class="w-6 h-6 text-blue-600" />
            Session d'inventaire
          </h1>
          <p class="text-sm text-gray-500">{{ modelNames.join(', ') }}</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap justify-end">
          <!-- Sauvegarder le rapport -->
          <button @click="openSaveModal"
            class="flex items-center gap-1.5 text-xs bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg transition font-medium shadow-sm">
            <ArchiveBoxIcon class="w-3.5 h-3.5" />
            Sauvegarder le rapport
          </button>
          <!-- Bouton imprimer manquants -->
          <button @click="printInventory('missing')"
            :disabled="allMissing.length === 0"
            :title="allMissing.length === 0 ? 'Aucun équipement manquant' : `Imprimer les ${allMissing.length} manquant(s)`"
            class="flex items-center gap-1.5 text-xs bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 px-3 py-1.5 rounded-lg transition disabled:opacity-40 disabled:cursor-not-allowed">
            <PrinterIcon class="w-3.5 h-3.5" />
            Manquants ({{ allMissing.length }})
          </button>
          <!-- Bouton imprimer inventaire complet -->
          <button @click="printInventory('full')"
            class="flex items-center gap-1.5 text-xs bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 px-3 py-1.5 rounded-lg transition">
            <PrinterIcon class="w-3.5 h-3.5" />
            Imprimer tout
          </button>
          <button @click="resetSession" class="text-xs text-gray-400 hover:text-red-500 border border-gray-200 px-3 py-1.5 rounded-lg transition">
            Réinitialiser
          </button>
        </div>
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
          <button @click="openSaveModal" class="ml-auto text-xs bg-green-700 text-white px-3 py-1 rounded-lg hover:bg-green-800 transition font-medium">
            Sauvegarder le rapport →
          </button>
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
          <BarcodeScanButton
            title="Scanner pour l'inventaire"
            @scanned="(code) => { processTag(code) }"
          />
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
        <div class="px-4 py-3 border-b border-red-200 bg-red-50 rounded-t-xl flex items-center justify-between">
          <h3 class="font-medium text-red-800 text-sm">⚠ Manquants / Absents ({{ allMissing.length }})</h3>
          <button @click="printInventory('missing')"
            class="flex items-center gap-1.5 text-xs bg-white hover:bg-red-50 text-red-700 border border-red-300 px-2.5 py-1 rounded-lg transition">
            <PrinterIcon class="w-3.5 h-3.5" />
            Imprimer liste
          </button>
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

    <!-- ═══ Modal Sauvegarder le rapport ═══ -->
    <div v-if="showSaveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg flex items-center gap-2">
            <ArchiveBoxIcon class="w-5 h-5 text-green-600" />
            Sauvegarder le rapport
          </h3>
          <button @click="showSaveModal = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>

        <!-- Résumé -->
        <div class="flex gap-3 mb-4">
          <div class="flex-1 bg-green-50 border border-green-200 rounded-lg p-3 text-center">
            <div class="text-lg font-bold text-green-700">{{ scannedCount }}</div>
            <div class="text-xs text-green-600">Présents</div>
          </div>
          <div class="flex-1 bg-red-50 border border-red-200 rounded-lg p-3 text-center">
            <div class="text-lg font-bold text-red-700">{{ missingCount }}</div>
            <div class="text-xs text-red-600">Manquants</div>
          </div>
          <div class="flex-1 bg-gray-50 border border-gray-200 rounded-lg p-3 text-center">
            <div class="text-lg font-bold text-gray-500">{{ remainingCount }}</div>
            <div class="text-xs text-gray-400">Non traités</div>
          </div>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Titre du rapport (optionnel)</label>
            <input v-model="saveLabel" type="text" placeholder="Ex: Inventaire salle A — Septembre 2026"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optionnel)</label>
            <textarea v-model="saveNotes" rows="3" placeholder="Observations, remarques…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
          </div>
        </div>

        <div class="flex gap-3 mt-4">
          <button @click="showSaveModal = false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="saveReport" :disabled="saving"
            class="flex-1 bg-green-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50 transition">
            {{ saving ? 'Sauvegarde…' : 'Sauvegarder' }}
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>
