<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import Sortable from 'sortablejs'
import { TagIcon, CheckCircleIcon, QrCodeIcon, ChevronUpIcon, ChevronDownIcon } from '@heroicons/vue/24/outline'
import { CheckCircleIcon as CheckCircleSolid } from '@heroicons/vue/24/solid'
import { useSound } from '@/composables/useSound'

const { playSound } = useSound()

const props = defineProps({
  assets: { type: Array, default: () => [] },
})

// ─── État local optimiste ──────────────────────────────────────────────────────
const localAssets = ref(props.assets.map(a => ({ ...a })))

const toCreate = computed(() => localAssets.value.filter(a => a.label_to_create))
const toApply  = computed(() => localAssets.value.filter(a => a.label_to_apply))

// ─── Tri ──────────────────────────────────────────────────────────────────────
// 'custom' = ordre personnalisé (drag-and-drop) | 'label_flagged_at' | 'name'
const sortField = ref('custom')
const sortDir   = ref('asc')

function toggleSort(field) {
  if (field === 'custom') {
    sortField.value = 'custom'
    return
  }
  if (sortField.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDir.value = 'asc'
  }
}

const sortedAssets = computed(() => {
  // Mode personnalisé : on retourne les assets dans leur ordre actuel (localAssets)
  if (sortField.value === 'custom') {
    return localAssets.value
  }
  return [...localAssets.value].sort((a, b) => {
    let va = a[sortField.value]
    let vb = b[sortField.value]

    // Les nulls vont toujours à la fin
    if (va == null && vb == null) return 0
    if (va == null) return 1
    if (vb == null) return -1

    const cmp = va < vb ? -1 : va > vb ? 1 : 0
    return sortDir.value === 'asc' ? cmp : -cmp
  })
})

// ─── Drag-and-drop (SortableJS) ───────────────────────────────────────────────
const tableBodyRef = ref(null)
let sortableInstance = null

function initSortable() {
  if (!tableBodyRef.value || sortableInstance) return
  sortableInstance = Sortable.create(tableBodyRef.value, {
    animation: 150,
    handle: '.drag-handle',
    ghostClass: 'sortable-ghost',
    dragClass: 'sortable-drag',
    onEnd: handleDragEnd,
  })
}

function destroySortable() {
  if (sortableInstance) {
    sortableInstance.destroy()
    sortableInstance = null
  }
}

async function handleDragEnd(event) {
  const { oldIndex, newIndex } = event
  if (oldIndex === newIndex) return

  // Réordonner localAssets de manière optimiste
  const moved = localAssets.value.splice(oldIndex, 1)[0]
  localAssets.value.splice(newIndex, 0, moved)

  // Persister l'ordre en base
  const ids = localAssets.value.map(a => a.id)
  try {
    await axios.post(route('assets.labels-reorder'), { ids })
  } catch {
    // En cas d'erreur, on ne rollback pas (ordre visuel reste en place)
    // pour ne pas perturber l'utilisateur
  }
}

// Gérer le cycle de vie de SortableJS selon le mode de tri
watch(sortField, async (field) => {
  if (field === 'custom') {
    await nextTick()
    initSortable()
  } else {
    destroySortable()
  }
}, { immediate: false })

onMounted(async () => {
  scanInputEl.value?.focus()
  // Initialiser le drag-and-drop si on démarre en mode custom
  if (sortField.value === 'custom') {
    await nextTick()
    initSortable()
  }
})

onUnmounted(() => {
  destroySortable()
})

// ─── Scanner ──────────────────────────────────────────────────────────────────
// Modes : 'validate' = valider l'étiquettage | 'register' = enregistrer "à coller"
const scanMode    = ref('validate')
const scanInput   = ref('')
const scanInputEl = ref(null)
const scanFeedback = ref(null) // { type: 'success'|'error'|'warning', message: string }
let feedbackTimer = null

function showFeedback(type, message) {
  if (feedbackTimer) clearTimeout(feedbackTimer)
  scanFeedback.value = { type, message }
  feedbackTimer = setTimeout(() => { scanFeedback.value = null }, 4000)
}

function switchMode(mode) {
  scanMode.value = mode
  scanInput.value = ''
  scanFeedback.value = null
  scanInputEl.value?.focus()
}

function onScanKeydown(e) {
  if (e.key !== 'Enter') return
  e.preventDefault()
  const tag = scanInput.value.trim()
  scanInput.value = ''
  if (!tag) return
  if (scanMode.value === 'validate') {
    processScanValidate(tag)
  } else {
    processScanRegister(tag)
  }
}

// ── Mode validation : marquer l'étiquette comme faite ──────────────────────
async function processScanValidate(tag) {
  const asset = localAssets.value.find(
    a => a.asset_tag === tag || a.asset_tag?.toLowerCase() === tag.toLowerCase()
  )

  if (!asset) {
    playSound('scan_error')
    showFeedback('error', `Code « ${tag} » introuvable dans la liste des étiquettes en attente`)
    return
  }

  try {
    const calls = []
    if (asset.label_to_create) calls.push(axios.patch(route('assets.toggle-label-create', asset.id)))
    if (asset.label_to_apply)  calls.push(axios.patch(route('assets.toggle-label-apply',  asset.id)))
    const results = await Promise.all(calls)

    localAssets.value = localAssets.value.filter(a => a.id !== asset.id)
    playSound('scan_success')

    const tasksCompleted = results.reduce((sum, r) => sum + (r.data?.tasks_auto_completed ?? 0), 0)
    const extra = tasksCompleted > 0
      ? ` — ${tasksCompleted} tâche(s) Todo terminée(s)`
      : ''
    showFeedback('success', `✓ Étiquette validée : ${asset.name} [${asset.asset_tag}]${extra}`)
  } catch {
    playSound('scan_error')
    showFeedback('error', `Erreur lors de la validation de ${asset.name}`)
  }
}

// ── Mode enregistrement : ajouter un asset "à coller" ─────────────────────
async function processScanRegister(tag) {
  try {
    const { data } = await axios.post(route('assets.label-scan-apply'), { asset_tag: tag })
    // Vérifier si l'asset est déjà dans la liste locale (cas: label_to_create déjà true)
    const existing = localAssets.value.find(a => a.id === data.asset.id)
    if (existing) {
      existing.label_to_apply       = true
      existing.label_flagged_at       = data.asset.label_flagged_at ?? existing.label_flagged_at
      existing.label_flagged_at_label = data.asset.label_flagged_at_label ?? existing.label_flagged_at_label
    } else {
      localAssets.value.push(data.asset)
    }
    playSound('scan_success')
    showFeedback('success', `➕ Ajouté à la liste : ${data.asset.name} [${data.asset.asset_tag}] — tâche Todo créée`)
  } catch (err) {
    if (err.response?.status === 409) {
      playSound('scan_error')
      showFeedback('warning', `⚠️ ${err.response.data.warning ?? 'Déjà dans la liste'}`)
    } else if (err.response?.status === 404) {
      playSound('scan_error')
      showFeedback('error', err.response.data.error ?? `Code « ${tag} » introuvable dans l'inventaire`)
    } else {
      playSound('scan_error')
      showFeedback('error', `Erreur lors de l'enregistrement`)
    }
  }
}

// ─── Toggles manuels ──────────────────────────────────────────────────────────
async function toggleCreate(asset) {
  const prev = asset.label_to_create
  asset.label_to_create = !prev
  try {
    const { data } = await axios.patch(route('assets.toggle-label-create', asset.id))
    // Synchroniser l'horodatage retourné par le serveur
    asset.label_flagged_at       = data.label_flagged_at ?? null
    asset.label_flagged_at_label = data.label_flagged_at_label ?? null
    if (data.status)       asset.status       = data.status
    if (data.status_label) asset.status_label = data.status_label
    if (!asset.label_to_create && !asset.label_to_apply) {
      localAssets.value = localAssets.value.filter(a => a.id !== asset.id)
      if ((data?.tasks_auto_completed ?? 0) > 0) {
        showFeedback('success', `${data.tasks_auto_completed} tâche(s) Todo marquée(s) comme terminée(s) automatiquement`)
      }
    }
  } catch {
    asset.label_to_create = prev
  }
}

async function toggleApply(asset) {
  const prev = asset.label_to_apply
  asset.label_to_apply = !prev
  try {
    const { data } = await axios.patch(route('assets.toggle-label-apply', asset.id))
    asset.label_flagged_at       = data.label_flagged_at ?? null
    asset.label_flagged_at_label = data.label_flagged_at_label ?? null
    if (data.status)       asset.status       = data.status
    if (data.status_label) asset.status_label = data.status_label
    if (!asset.label_to_create && !asset.label_to_apply) {
      localAssets.value = localAssets.value.filter(a => a.id !== asset.id)
      if ((data?.tasks_auto_completed ?? 0) > 0) {
        showFeedback('success', `${data.tasks_auto_completed} tâche(s) Todo marquée(s) comme terminée(s) automatiquement`)
      }
    }
  } catch {
    asset.label_to_apply = prev
  }
}
</script>

<template>
  <AppLayout>
    <div class="space-y-6">

      <!-- ══ Header ══ -->
      <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <TagIcon class="w-7 h-7 text-indigo-600" />
            Étiquettes à créer / coller
          </h1>
          <p class="text-gray-500 text-sm mt-1">
            Scannez le code-barre d'un équipement pour valider son étiquettage, ou cochez les cases manuellement.
          </p>
        </div>
        <Link :href="route('assets.index')"
              class="text-sm text-gray-500 hover:text-gray-700 underline flex-shrink-0">
          ← Retour aux équipements
        </Link>
      </div>

      <!-- ══ Résumé ══ -->
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4">
          <p class="text-2xl font-black text-amber-700">{{ toCreate.length }}</p>
          <p class="text-xs font-semibold text-amber-600 uppercase tracking-wide mt-0.5">✏️ À créer</p>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-xl px-5 py-4">
          <p class="text-2xl font-black text-blue-700">{{ toApply.length }}</p>
          <p class="text-xs font-semibold text-blue-600 uppercase tracking-wide mt-0.5">📌 À coller</p>
        </div>
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl px-5 py-4 col-span-2 sm:col-span-1">
          <p class="text-2xl font-black text-indigo-700">{{ localAssets.length }}</p>
          <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mt-0.5">Total concernés</p>
        </div>
      </div>

      <!-- ══ Zone de scan ══ -->
      <div class="bg-white rounded-xl shadow-sm border overflow-hidden"
           :class="scanMode === 'validate' ? 'border-green-200' : 'border-blue-200'">

        <!-- En-tête avec toggle de mode -->
        <div class="px-5 py-3 flex items-center justify-between gap-3"
             :class="scanMode === 'validate' ? 'bg-green-600' : 'bg-blue-600'">
          <div class="flex items-center gap-3">
            <QrCodeIcon class="w-5 h-5 flex-shrink-0"
                        :class="scanMode === 'validate' ? 'text-green-200' : 'text-blue-200'" />
            <div>
              <p class="text-white font-semibold text-sm">
                {{ scanMode === 'validate' ? 'Mode Validation — étiquette posée et vérifiée' : 'Mode Enregistrement — étiquette créée, à coller' }}
              </p>
              <p class="text-xs"
                 :class="scanMode === 'validate' ? 'text-green-200' : 'text-blue-200'">
                {{ scanMode === 'validate'
                    ? 'Scannez pour confirmer qu\'une étiquette est collée et fonctionnelle'
                    : 'Scannez une étiquette déjà imprimée pour créer la tâche «à coller» et l\'ajouter à la liste' }}
              </p>
            </div>
          </div>
          <!-- Toggle boutons -->
          <div class="flex rounded-lg overflow-hidden border border-white/30 flex-shrink-0">
            <button @click="switchMode('validate')"
                    :class="[
                      'px-3 py-1.5 text-xs font-semibold transition',
                      scanMode === 'validate'
                        ? 'bg-white text-green-700'
                        : 'bg-transparent text-white/80 hover:text-white hover:bg-white/10'
                    ]">
              ✓ Valider
            </button>
            <button @click="switchMode('register')"
                    :class="[
                      'px-3 py-1.5 text-xs font-semibold transition',
                      scanMode === 'register'
                        ? 'bg-white text-blue-700'
                        : 'bg-transparent text-white/80 hover:text-white hover:bg-white/10'
                    ]">
              ➕ Enregistrer
            </button>
          </div>
        </div>

        <div class="p-5 space-y-3">
          <!-- Input de scan -->
          <div class="relative">
            <QrCodeIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 pointer-events-none"
                        :class="scanMode === 'validate' ? 'text-green-400' : 'text-blue-400'" />
            <input
              ref="scanInputEl"
              v-model="scanInput"
              @keydown="onScanKeydown"
              type="text"
              :placeholder="scanMode === 'validate'
                ? 'Valider : scanner le code-barre d\'un équipement déjà étiquetté…'
                : 'Enregistrer : scanner l\'étiquette créée à coller sur l\'équipement…'"
              :class="[
                'w-full pl-10 pr-4 py-3 border-2 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:border-transparent',
                scanMode === 'validate'
                  ? 'border-green-300 bg-green-50 placeholder-green-300 focus:ring-green-400'
                  : 'border-blue-300 bg-blue-50 placeholder-blue-300 focus:ring-blue-400'
              ]"
              autocomplete="off"
              autocorrect="off"
              spellcheck="false"
            />
          </div>

          <!-- Feedback scan -->
          <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95">
            <div v-if="scanFeedback"
                 :class="[
                   'rounded-xl px-4 py-3 text-sm font-medium flex items-center gap-2',
                   scanFeedback.type === 'success'  ? 'bg-green-50 border border-green-200 text-green-800' :
                   scanFeedback.type === 'warning'  ? 'bg-amber-50 border border-amber-200 text-amber-800' :
                                                      'bg-red-50 border border-red-200 text-red-800'
                 ]">
              {{ scanFeedback.message }}
            </div>
          </Transition>

          <!-- Aide contextuelle -->
          <p class="text-xs text-gray-400">
            <template v-if="scanMode === 'validate'">
              <strong>Mode Validation :</strong> scannez le code-barre sur l'équipement pour confirmer que l'étiquette
              est posée et fonctionnelle. Cela valide simultanément ✏️ et 📌 et termine les tâches Todo liées.
            </template>
            <template v-else>
              <strong>Mode Enregistrement :</strong> scannez l'étiquette déjà imprimée (pas encore collée).
              L'équipement s'ajoute à la liste avec le statut 📌 À coller, et une tâche Todo est créée automatiquement
              dans le projet <em>Étiquettage</em>.
            </template>
          </p>
        </div>
      </div>

      <!-- ══ Liste vide ══ -->
      <div v-if="localAssets.length === 0"
           class="bg-white rounded-xl shadow-sm border px-8 py-16 text-center">
        <CheckCircleIcon class="w-14 h-14 mx-auto mb-3 text-green-400 opacity-60" />
        <p class="text-lg font-semibold text-green-700">Tout est à jour !</p>
        <p class="text-sm text-gray-400 mt-1">Aucun équipement en attente d'étiquette.</p>
      </div>

      <!-- ══ Contrôles de tri ══ -->
      <div v-if="localAssets.length" class="flex items-center gap-2 flex-wrap">
        <span class="text-xs text-gray-500 font-medium">Trier par :</span>

        <!-- Ordre personnalisé -->
        <button @click="toggleSort('custom')"
          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-xs font-medium transition"
          :class="sortField === 'custom'
            ? 'bg-purple-600 border-purple-600 text-white'
            : 'bg-white border-gray-300 text-gray-600 hover:border-purple-300'">
          ⠿ Ordre personnalisé
        </button>

        <!-- Date d'ajout -->
        <button @click="toggleSort('label_flagged_at')"
          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-xs font-medium transition"
          :class="sortField === 'label_flagged_at'
            ? 'bg-indigo-600 border-indigo-600 text-white'
            : 'bg-white border-gray-300 text-gray-600 hover:border-indigo-300'">
          🕐 Date d'ajout
          <ChevronUpIcon   v-if="sortField === 'label_flagged_at' && sortDir === 'asc'"  class="h-3 w-3" />
          <ChevronDownIcon v-if="sortField === 'label_flagged_at' && sortDir === 'desc'" class="h-3 w-3" />
        </button>

        <!-- Nom -->
        <button @click="toggleSort('name')"
          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-xs font-medium transition"
          :class="sortField === 'name'
            ? 'bg-indigo-600 border-indigo-600 text-white'
            : 'bg-white border-gray-300 text-gray-600 hover:border-indigo-300'">
          🔤 Nom
          <ChevronUpIcon   v-if="sortField === 'name' && sortDir === 'asc'"  class="h-3 w-3" />
          <ChevronDownIcon v-if="sortField === 'name' && sortDir === 'desc'" class="h-3 w-3" />
        </button>

        <span class="text-xs text-gray-400 ml-auto">
          <template v-if="sortField === 'custom'">
            Glissez-déposez les lignes pour personnaliser l'ordre
          </template>
          <template v-else>
            {{ sortDir === 'asc' ? '↑ Croissant' : '↓ Décroissant' }}
          </template>
        </span>
      </div>

      <!-- ══ Tableau ══ -->
      <div v-if="localAssets.length" class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-xs text-gray-500 uppercase bg-gray-50 border-b">
              <!-- Colonne poignée drag (custom uniquement) -->
              <th v-if="sortField === 'custom'" class="px-2 py-3 w-8"></th>
              <!-- Colonne Équipement — tri par nom -->
              <th class="px-4 py-3 text-left">
                <button @click="toggleSort('name')"
                  class="flex items-center gap-1 hover:text-gray-800 transition group">
                  Équipement
                  <span v-if="sortField !== 'custom'" class="flex flex-col leading-none opacity-50 group-hover:opacity-100 transition">
                    <ChevronUpIcon  class="h-2.5 w-2.5" :class="sortField === 'name' && sortDir === 'asc'  ? 'text-indigo-600 opacity-100' : ''" />
                    <ChevronDownIcon class="h-2.5 w-2.5" :class="sortField === 'name' && sortDir === 'desc' ? 'text-indigo-600 opacity-100' : ''" />
                  </span>
                </button>
              </th>
              <th class="px-4 py-3 text-left hidden sm:table-cell">Tag</th>
              <th class="px-4 py-3 text-left hidden md:table-cell">Catégorie</th>
              <th class="px-4 py-3 text-left hidden lg:table-cell">Emplacement</th>
              <!-- Colonne Ajouté le — tri par date -->
              <th class="px-4 py-3 text-left hidden xl:table-cell">
                <button @click="toggleSort('label_flagged_at')"
                  class="flex items-center gap-1 hover:text-gray-800 transition group">
                  Ajouté le
                  <span v-if="sortField !== 'custom'" class="flex flex-col leading-none">
                    <ChevronUpIcon  class="h-2.5 w-2.5" :class="sortField === 'label_flagged_at' && sortDir === 'asc'  ? 'text-indigo-600 opacity-100' : 'opacity-50 group-hover:opacity-100 transition'" />
                    <ChevronDownIcon class="h-2.5 w-2.5" :class="sortField === 'label_flagged_at' && sortDir === 'desc' ? 'text-indigo-600 opacity-100' : 'opacity-50 group-hover:opacity-100 transition'" />
                  </span>
                </button>
              </th>
              <th class="px-4 py-3 text-center">✏️ À créer</th>
              <th class="px-4 py-3 text-center">📌 À coller</th>
              <th class="px-4 py-3 text-center">Fiche</th>
            </tr>
          </thead>
          <tbody ref="tableBodyRef">
            <tr v-for="asset in sortedAssets" :key="asset.id"
                class="border-b last:border-0 hover:bg-gray-50 transition"
                :class="{
                  'bg-amber-50/40': asset.label_to_create && asset.label_to_apply,
                  'bg-yellow-50/30': asset.label_to_create && !asset.label_to_apply,
                  'bg-blue-50/30': !asset.label_to_create && asset.label_to_apply,
                }">

              <!-- Poignée drag -->
              <td v-if="sortField === 'custom'" class="px-2 py-3 text-center">
                <span class="drag-handle cursor-grab active:cursor-grabbing text-gray-300 hover:text-gray-500 select-none text-base leading-none"
                      title="Glisser pour réordonner">
                  ⠿
                </span>
              </td>

              <!-- Nom -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0 overflow-hidden">
                    <img v-if="asset.image" :src="asset.image" class="w-9 h-9 object-cover rounded-lg" />
                    <span v-else class="text-lg">📦</span>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-900">{{ asset.name }}</p>
                    <div class="flex flex-wrap gap-1 mt-0.5">
                      <span v-if="asset.label_to_create && asset.label_to_apply"
                            class="text-xs font-bold px-1.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700 border border-indigo-300">
                        ✏️📌 Créer + Coller
                      </span>
                      <span v-else-if="asset.label_to_create"
                            class="text-xs font-bold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 border border-amber-300">
                        ✏️ À créer
                      </span>
                      <span v-else
                            class="text-xs font-bold px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700 border border-blue-300">
                        📌 À coller
                      </span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Tag -->
              <td class="px-4 py-3 hidden sm:table-cell">
                <span class="font-mono text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded">{{ asset.asset_tag }}</span>
              </td>

              <!-- Catégorie -->
              <td class="px-4 py-3 hidden md:table-cell">
                <span v-if="asset.category" class="text-xs text-gray-600">{{ asset.category }}</span>
                <span v-else class="text-gray-300 text-xs">—</span>
              </td>

              <!-- Emplacement -->
              <td class="px-4 py-3 hidden lg:table-cell">
                <span v-if="asset.location" class="text-xs text-gray-600">📍 {{ asset.location }}</span>
                <span v-else class="text-gray-300 text-xs">—</span>
              </td>

              <!-- Ajouté le -->
              <td class="px-4 py-3 hidden xl:table-cell">
                <span v-if="asset.label_flagged_at_label"
                  class="text-xs text-gray-500 tabular-nums"
                  :title="asset.label_flagged_at">
                  {{ asset.label_flagged_at_label }}
                </span>
                <span v-else class="text-gray-300 text-xs">—</span>
              </td>

              <!-- Toggle : À créer -->
              <td class="px-4 py-3 text-center">
                <button @click="toggleCreate(asset); scanInputEl?.focus()"
                        :title="asset.label_to_create ? 'Marquer comme fait' : 'Activer'"
                        class="inline-flex flex-col items-center gap-1 group">
                  <div :class="[
                    'w-8 h-8 rounded-full border-2 flex items-center justify-center transition',
                    asset.label_to_create
                      ? 'bg-amber-500 border-amber-500 text-white hover:bg-amber-600'
                      : 'bg-white border-gray-300 text-gray-400 hover:border-green-400 hover:text-green-500'
                  ]">
                    <CheckCircleSolid v-if="!asset.label_to_create" class="w-5 h-5 opacity-0 group-hover:opacity-60 transition" />
                    <span v-else class="text-sm">✓</span>
                  </div>
                  <span class="text-xs" :class="asset.label_to_create ? 'text-amber-700 font-semibold' : 'text-gray-300'">
                    {{ asset.label_to_create ? 'En attente' : 'Fait' }}
                  </span>
                </button>
              </td>

              <!-- Toggle : À coller -->
              <td class="px-4 py-3 text-center">
                <button @click="toggleApply(asset); scanInputEl?.focus()"
                        :title="asset.label_to_apply ? 'Marquer comme fait' : 'Activer'"
                        class="inline-flex flex-col items-center gap-1 group">
                  <div :class="[
                    'w-8 h-8 rounded-full border-2 flex items-center justify-center transition',
                    asset.label_to_apply
                      ? 'bg-blue-500 border-blue-500 text-white hover:bg-blue-600'
                      : 'bg-white border-gray-300 text-gray-400 hover:border-green-400 hover:text-green-500'
                  ]">
                    <CheckCircleSolid v-if="!asset.label_to_apply" class="w-5 h-5 opacity-0 group-hover:opacity-60 transition" />
                    <span v-else class="text-sm">✓</span>
                  </div>
                  <span class="text-xs" :class="asset.label_to_apply ? 'text-blue-700 font-semibold' : 'text-gray-300'">
                    {{ asset.label_to_apply ? 'En attente' : 'Fait' }}
                  </span>
                </button>
              </td>

              <!-- Lien fiche -->
              <td class="px-4 py-3 text-center">
                <Link :href="route('assets.show', asset.id)"
                      class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline">
                  Voir →
                </Link>
              </td>

            </tr>
          </tbody>
        </table>
      </div>

      <!-- ══ Légende ══ -->
      <p class="text-xs text-gray-400 text-center">
        Scan = valide simultanément ✏️ et 📌 pour l'équipement et vérifie le bon fonctionnement de l'étiquette.
        Les cases à cocher permettent une validation manuelle de chaque tâche séparément.
      </p>

    </div>
  </AppLayout>
</template>

<style scoped>
.sortable-ghost {
  opacity: 0.4;
  background: #e0e7ff;
}
.sortable-drag {
  opacity: 0.9;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>
