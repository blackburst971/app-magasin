<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
  PlusIcon, PencilIcon, TrashIcon, CheckIcon,
  LockClosedIcon, Bars3Icon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  statuses:     Array,
  colorPalette: Object,
})

// ── Palette (toutes classes explicites pour que Tailwind ne les purge pas) ────
const colorClasses = {
  green:  'bg-green-100 text-green-800',
  blue:   'bg-blue-100 text-blue-800',
  purple: 'bg-purple-100 text-purple-800',
  yellow: 'bg-yellow-100 text-yellow-800',
  orange: 'bg-orange-100 text-orange-800',
  red:    'bg-red-100 text-red-800',
  gray:   'bg-gray-100 text-gray-800',
  indigo: 'bg-indigo-100 text-indigo-800',
  pink:   'bg-pink-100 text-pink-800',
  teal:   'bg-teal-100 text-teal-800',
}
const dotClasses = {
  green:  'bg-green-500',  blue:   'bg-blue-500',   purple: 'bg-purple-500',
  yellow: 'bg-yellow-500', orange: 'bg-orange-500', red:    'bg-red-500',
  gray:   'bg-gray-400',   indigo: 'bg-indigo-500', pink:   'bg-pink-500',
  teal:   'bg-teal-500',
}

// ── État ──────────────────────────────────────────────────────────────────────
const editingId  = ref(null)   // null = aucun, -1 = nouveau
const editForm   = ref({})
const processing = ref(false)
const errors     = ref({})
const keyManual  = ref(false)

const systemStatuses = computed(() => props.statuses.filter(s => s.is_system))
const customStatuses = computed(() => props.statuses.filter(s => !s.is_system))

// ── Formulaire création ───────────────────────────────────────────────────────
function openCreate() {
  editingId.value = -1
  keyManual.value = false
  editForm.value  = {
    key: '', label: '', color: 'blue',
    blocks_reservation: false, is_active: true,
    sort_order: props.statuses.length * 10,
  }
  errors.value = {}
}

function openEdit(status) {
  editingId.value = status.id
  keyManual.value = true
  editForm.value  = { ...status }
  errors.value    = {}
}

function cancelEdit() {
  editingId.value = null
  editForm.value  = {}
  errors.value    = {}
}

// ── Auto-slug ─────────────────────────────────────────────────────────────────
function slugify(str) {
  return str.toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_|_$/g, '')
}

function onLabelInput(val) {
  editForm.value.label = val
  if (!keyManual.value) editForm.value.key = slugify(val)
}

function onKeyInput(val) {
  keyManual.value = true
  editForm.value.key = val
}

// ── Sauvegarder ───────────────────────────────────────────────────────────────
function save() {
  errors.value     = {}
  processing.value = true
  const isNew  = editingId.value === -1
  const url    = isNew ? route('asset-statuses.store') : route('asset-statuses.update', editingId.value)
  const method = isNew ? 'post' : 'put'

  router[method](url, { ...editForm.value }, {
    onError:   (e) => { errors.value = e },
    onFinish:  () => { processing.value = false },
    onSuccess: () => { cancelEdit() },
  })
}

// ── Supprimer ─────────────────────────────────────────────────────────────────
function destroy(status) {
  if (!confirm(`Supprimer le statut "${status.label}" ?\nLes équipements associés seront remis à "Disponible".`)) return
  router.delete(route('asset-statuses.destroy', status.id))
}
</script>

<template>
  <AppLayout title="Statuts des équipements">
    <div class="max-w-3xl mx-auto space-y-6">

      <!-- En-tête -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Statuts des équipements</h1>
          <p class="text-sm text-gray-500 mt-0.5">Personnalisez les statuts affichés sur les équipements.</p>
        </div>
        <button v-if="editingId === null" @click="openCreate"
          class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition shadow-sm">
          <PlusIcon class="w-4 h-4" /> Nouveau statut
        </button>
      </div>

      <!-- ── Formulaire création ──────────────────────────────────────────── -->
      <div v-if="editingId === -1"
           class="bg-white rounded-2xl border-2 border-blue-200 shadow-sm p-6">
        <h2 class="font-semibold text-gray-900 mb-5 flex items-center gap-2 text-sm">
          <PlusIcon class="w-4 h-4 text-blue-600" /> Nouveau statut
        </h2>

        <!-- Champs -->
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">
                Libellé <span class="text-red-500">*</span>
              </label>
              <input :value="editForm.label"
                @input="onLabelInput($event.target.value)"
                type="text" placeholder="Ex: En réparation"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                :class="{ 'border-red-400': errors.label }" />
              <p v-if="errors.label" class="text-red-500 text-xs mt-1">{{ errors.label }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">
                Clé unique <span class="text-red-500">*</span>
                <span class="text-gray-400 font-normal">— auto-générée</span>
              </label>
              <input :value="editForm.key"
                @input="onKeyInput($event.target.value)"
                type="text" placeholder="ex: en_reparation"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500 outline-none"
                :class="{ 'border-red-400': errors.key }" />
              <p v-if="errors.key" class="text-red-500 text-xs mt-1">{{ errors.key }}</p>
            </div>
          </div>

          <!-- Couleur -->
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-2">Couleur du badge</label>
            <div class="flex flex-wrap gap-2">
              <button v-for="(col, key) in colorPalette" :key="key" type="button"
                @click="editForm.color = key"
                :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border-2 transition',
                  colorClasses[key],
                  editForm.color === key ? 'border-gray-700 ring-2 ring-offset-1 ring-gray-400' : 'border-transparent hover:border-gray-300']">
                {{ col.label }}
              </button>
            </div>
            <div class="mt-2.5 flex items-center gap-2">
              <span class="text-xs text-gray-500">Aperçu :</span>
              <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', colorClasses[editForm.color] ?? 'bg-gray-100 text-gray-800']">
                {{ editForm.label || 'Mon statut' }}
              </span>
            </div>
          </div>

          <!-- Options -->
          <div class="flex flex-wrap gap-5">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="editForm.blocks_reservation"
                class="w-4 h-4 text-red-600 border-gray-300 rounded" />
              <span class="text-sm text-gray-700">Bloque les réservations</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="editForm.is_active"
                class="w-4 h-4 text-blue-600 border-gray-300 rounded" />
              <span class="text-sm text-gray-700">Statut actif</span>
            </label>
          </div>
        </div>

        <div class="flex gap-3 mt-5 pt-4 border-t">
          <button @click="cancelEdit"
            class="px-4 py-2 border border-gray-300 rounded-xl text-sm hover:bg-gray-50 transition">
            Annuler
          </button>
          <button @click="save" :disabled="processing"
            class="flex items-center gap-2 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium transition disabled:opacity-50">
            <CheckIcon class="w-4 h-4" />
            {{ processing ? 'Enregistrement…' : 'Créer le statut' }}
          </button>
        </div>
      </div>

      <!-- ── Statuts système ──────────────────────────────────────────────── -->
      <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">
        <div class="px-5 py-3.5 border-b bg-gray-50 flex items-center gap-2">
          <LockClosedIcon class="w-4 h-4 text-gray-400" />
          <h2 class="font-semibold text-gray-800 text-sm">Statuts système</h2>
          <span class="text-xs text-gray-400 ml-1">— Modifiables mais non supprimables</span>
        </div>
        <ul class="divide-y">
          <li v-for="status in systemStatuses" :key="status.id">

            <!-- Affichage -->
            <div v-if="editingId !== status.id"
                 class="flex items-center gap-4 px-5 py-3.5 hover:bg-gray-50/70 transition group">
              <span :class="['w-2.5 h-2.5 rounded-full flex-shrink-0', dotClasses[status.color] ?? 'bg-gray-400']" />
              <div class="flex-1 min-w-0 flex items-center gap-2.5 flex-wrap">
                <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', colorClasses[status.color] ?? 'bg-gray-100 text-gray-800']">
                  {{ status.label }}
                </span>
                <code class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded font-mono">{{ status.key }}</code>
                <span v-if="status.blocks_reservation" class="text-xs text-red-500 bg-red-50 px-2 py-0.5 rounded-full font-medium">
                  bloque les réservations
                </span>
              </div>
              <button @click="openEdit(status)" title="Modifier"
                class="p-1.5 text-gray-300 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition opacity-0 group-hover:opacity-100">
                <PencilIcon class="w-4 h-4" />
              </button>
            </div>

            <!-- Édition inline -->
            <div v-else class="px-5 py-5 bg-blue-50/40 border-l-4 border-blue-400">
              <p class="text-xs font-medium text-blue-700 mb-4">✏️ Modification du statut système — la clé ne peut pas être modifiée.</p>
              <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Libellé</label>
                    <input v-model="editForm.label" type="text"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                      :class="{ 'border-red-400': errors.label }" />
                    <p v-if="errors.label" class="text-red-500 text-xs mt-1">{{ errors.label }}</p>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Clé (non modifiable)</label>
                    <input :value="editForm.key" type="text" disabled
                      class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm font-mono bg-gray-100 text-gray-500 cursor-not-allowed" />
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-700 mb-2">Couleur</label>
                  <div class="flex flex-wrap gap-2">
                    <button v-for="(col, key) in colorPalette" :key="key" type="button"
                      @click="editForm.color = key"
                      :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border-2 transition',
                        colorClasses[key],
                        editForm.color === key ? 'border-gray-700 ring-2 ring-offset-1 ring-gray-400' : 'border-transparent hover:border-gray-300']">
                      {{ col.label }}
                    </button>
                  </div>
                  <div class="mt-2 flex items-center gap-2">
                    <span class="text-xs text-gray-500">Aperçu :</span>
                    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', colorClasses[editForm.color] ?? 'bg-gray-100 text-gray-800']">
                      {{ editForm.label || '—' }}
                    </span>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <input type="checkbox" v-model="editForm.blocks_reservation" :id="'br-sys-'+status.id"
                    class="w-4 h-4 text-red-600 border-gray-300 rounded" />
                  <label :for="'br-sys-'+status.id" class="text-sm text-gray-700 cursor-pointer">
                    Bloque les réservations
                  </label>
                </div>
              </div>
              <div class="flex gap-3 mt-4">
                <button @click="cancelEdit"
                  class="px-4 py-1.5 border border-gray-300 rounded-lg text-sm hover:bg-white transition">
                  Annuler
                </button>
                <button @click="save" :disabled="processing"
                  class="flex items-center gap-2 px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition disabled:opacity-50">
                  <CheckIcon class="w-3.5 h-3.5" />
                  {{ processing ? '…' : 'Enregistrer' }}
                </button>
              </div>
            </div>
          </li>
        </ul>
      </div>

      <!-- ── Statuts personnalisés ────────────────────────────────────────── -->
      <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">
        <div class="px-5 py-3.5 border-b bg-gray-50 flex items-center justify-between">
          <h2 class="font-semibold text-gray-800 text-sm">Statuts personnalisés</h2>
          <span class="text-xs text-gray-400">{{ customStatuses.length }} statut(s)</span>
        </div>

        <div v-if="customStatuses.length === 0" class="py-12 text-center text-gray-400">
          <p class="text-4xl mb-3">🏷️</p>
          <p class="font-medium text-gray-500 text-sm mb-1">Aucun statut personnalisé</p>
          <p class="text-xs text-gray-400">Utilisez le bouton "Nouveau statut" pour en créer un.</p>
        </div>

        <ul v-else class="divide-y">
          <li v-for="status in customStatuses" :key="status.id">

            <!-- Affichage -->
            <div v-if="editingId !== status.id"
                 class="flex items-center gap-4 px-5 py-3.5 hover:bg-gray-50/70 transition group">
              <Bars3Icon class="w-4 h-4 text-gray-300 flex-shrink-0 cursor-grab" title="Réordonner" />
              <span :class="['w-2.5 h-2.5 rounded-full flex-shrink-0', dotClasses[status.color] ?? 'bg-gray-400']" />
              <div class="flex-1 min-w-0 flex items-center gap-2.5 flex-wrap">
                <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', colorClasses[status.color] ?? 'bg-gray-100 text-gray-800']">
                  {{ status.label }}
                </span>
                <code class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded font-mono">{{ status.key }}</code>
                <span v-if="status.blocks_reservation" class="text-xs text-red-500 bg-red-50 px-2 py-0.5 rounded-full font-medium">
                  bloque les réservations
                </span>
                <span v-if="!status.is_active" class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
                  inactif
                </span>
              </div>
              <div class="flex gap-1 flex-shrink-0 opacity-0 group-hover:opacity-100 transition">
                <button @click="openEdit(status)" title="Modifier"
                  class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition">
                  <PencilIcon class="w-4 h-4" />
                </button>
                <button @click="destroy(status)" title="Supprimer"
                  class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>

            <!-- Édition inline -->
            <div v-else class="px-5 py-5 bg-blue-50/40 border-l-4 border-blue-400">
              <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Libellé</label>
                    <input v-model="editForm.label" type="text"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                      :class="{ 'border-red-400': errors.label }" />
                    <p v-if="errors.label" class="text-red-500 text-xs mt-1">{{ errors.label }}</p>
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Clé unique</label>
                    <input v-model="editForm.key" type="text"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-blue-500 outline-none"
                      :class="{ 'border-red-400': errors.key }" />
                    <p v-if="errors.key" class="text-red-500 text-xs mt-1">{{ errors.key }}</p>
                  </div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-700 mb-2">Couleur</label>
                  <div class="flex flex-wrap gap-2">
                    <button v-for="(col, key) in colorPalette" :key="key" type="button"
                      @click="editForm.color = key"
                      :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border-2 transition',
                        colorClasses[key],
                        editForm.color === key ? 'border-gray-700 ring-2 ring-offset-1 ring-gray-400' : 'border-transparent hover:border-gray-300']">
                      {{ col.label }}
                    </button>
                  </div>
                  <div class="mt-2 flex items-center gap-2">
                    <span class="text-xs text-gray-500">Aperçu :</span>
                    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold', colorClasses[editForm.color] ?? 'bg-gray-100 text-gray-800']">
                      {{ editForm.label || '—' }}
                    </span>
                  </div>
                </div>
                <div class="flex flex-wrap gap-5">
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="editForm.blocks_reservation"
                      class="w-4 h-4 text-red-600 border-gray-300 rounded" />
                    <span class="text-sm text-gray-700">Bloque les réservations</span>
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="editForm.is_active"
                      class="w-4 h-4 text-blue-600 border-gray-300 rounded" />
                    <span class="text-sm text-gray-700">Statut actif</span>
                  </label>
                </div>
              </div>
              <div class="flex gap-3 mt-4">
                <button @click="cancelEdit"
                  class="px-4 py-1.5 border border-gray-300 rounded-lg text-sm hover:bg-white transition">
                  Annuler
                </button>
                <button @click="save" :disabled="processing"
                  class="flex items-center gap-2 px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition disabled:opacity-50">
                  <CheckIcon class="w-3.5 h-3.5" />
                  {{ processing ? '…' : 'Enregistrer' }}
                </button>
              </div>
            </div>
          </li>
        </ul>
      </div>

      <!-- ── Info ────────────────────────────────────────────────────────── -->
      <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
        <p class="text-sm font-semibold text-blue-800 mb-2">ℹ️ À propos des statuts</p>
        <ul class="text-xs text-blue-700 space-y-1 list-disc list-inside">
          <li>Les statuts <strong>système</strong> sont protégés et ne peuvent pas être supprimés.</li>
          <li>Un statut <strong>"bloque les réservations"</strong> empêche les utilisateurs de réserver l'équipement concerné.</li>
          <li>Supprimer un statut personnalisé remet tous les équipements associés à <strong>"Disponible"</strong>.</li>
          <li>La <strong>clé</strong> est l'identifiant interne unique (minuscules, chiffres, tirets bas).</li>
        </ul>
      </div>

    </div>
  </AppLayout>
</template>
