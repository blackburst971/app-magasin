<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({ form: Object })

function goBack() {
  if (window.history.length > 1) window.history.back()
  else router.visit(route('checkout-forms.index'))
}

const appForm = useForm({
  name:        props.form?.name        ?? '',
  description: props.form?.description ?? '',
  status:      props.form?.status      ?? 'optional',
  fields: props.form?.fields?.map(f => ({
    ...f,
    col_span: f.col_span ?? (f.type === 'signature' ? 2 : 1),
    options: Array.isArray(f.options) ? f.options : (f.options ? JSON.parse(f.options) : []),
  })) ?? [],
})

const fieldTypes = [
  { value: 'text',      label: '📝 Texte court' },
  { value: 'textarea',  label: '📄 Texte long' },
  { value: 'select',    label: '🔽 Liste déroulante' },
  { value: 'checkbox',  label: '☑️ Cases à cocher' },
  { value: 'date',      label: '📅 Date' },
  { value: 'number',    label: '🔢 Nombre' },
  { value: 'email',     label: '✉️ Email' },
  { value: 'phone',     label: '📞 Téléphone' },
  { value: 'signature', label: '✍️ Signature (Prénom + Nom)' },
]

// ─── Champ ────────────────────────────────────────────────────────────────────
const addField = () => {
  appForm.fields.push({ type: 'text', label: '', placeholder: '', required: false, options: [], col_span: 1 })
}
const removeField = (i) => appForm.fields.splice(i, 1)
const addOption  = (field) => { if (!Array.isArray(field.options)) field.options = []; field.options.push('') }
const removeOption = (field, i) => field.options.splice(i, 1)

function onTypeChange(field) {
  if (field.type === 'signature') field.col_span = 2
  if (!['select', 'checkbox'].includes(field.type)) field.options = []
}

// ─── Drag & Drop ──────────────────────────────────────────────────────────────
const dragIndex     = ref(null)
const dragOverIndex = ref(null)

function onDragStart(e, i) {
  dragIndex.value = i
  e.dataTransfer.effectAllowed = 'move'
}

function onDragOver(e, i) {
  e.preventDefault()
  e.dataTransfer.dropEffect = 'move'
  dragOverIndex.value = i
}

function onDrop(e, i) {
  e.preventDefault()
  if (dragIndex.value === null || dragIndex.value === i) {
    dragIndex.value = dragOverIndex.value = null
    return
  }
  const fields = [...appForm.fields]
  const [moved] = fields.splice(dragIndex.value, 1)
  fields.splice(i, 0, moved)
  appForm.fields = fields
  dragIndex.value = dragOverIndex.value = null
}

function onDragEnd() {
  dragIndex.value = dragOverIndex.value = null
}

// ─── Index du champ sélectionné pour édition ──────────────────────────────────
const expandedIdx = ref(null)
function toggleExpand(i) {
  expandedIdx.value = expandedIdx.value === i ? null : i
}

// ─── Rendu de la grille (colonnes) ────────────────────────────────────────────
// Regroupe les champs en lignes selon col_span pour le rendu WYSIWYG
const fieldRows = computed(() => {
  const rows = []
  let i = 0
  while (i < appForm.fields.length) {
    const f = appForm.fields[i]
    if (f.col_span === 2) {
      rows.push([{ field: f, idx: i }])
      i++
    } else {
      const row = [{ field: f, idx: i }]
      if (i + 1 < appForm.fields.length && appForm.fields[i + 1].col_span !== 2) {
        row.push({ field: appForm.fields[i + 1], idx: i + 1 })
        i++
      }
      rows.push(row)
      i++
    }
  }
  return rows
})

// ─── Soumission ───────────────────────────────────────────────────────────────
const submit = () => {
  if (props.form?.id) {
    appForm.put(route('checkout-forms.update', props.form.id))
  } else {
    appForm.post(route('checkout-forms.store'))
  }
}
</script>

<template>
  <AppLayout>
    <div class="max-w-4xl">
      <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
        <h1 class="text-2xl font-bold text-gray-900">
          {{ form ? 'Modifier le formulaire' : 'Créer un formulaire de sortie' }}
        </h1>
      </div>

      <form @submit.prevent="submit" class="space-y-6">

        <!-- ══ Infos générales ══ -->
        <div class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom du formulaire *</label>
            <input v-model="appForm.name" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="appForm.description" rows="2"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
          </div>
          <!-- Statut du formulaire -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Statut &amp; obligation</label>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
              <label v-for="opt in [
                { value: 'inactive',        icon: '🔴', label: 'Inactif',              desc: 'Non visible, non accessible' },
                { value: 'optional',        icon: '🔵', label: 'Optionnel',            desc: 'Remplissable, non obligatoire' },
                { value: 'required_before', icon: '🟡', label: 'Avant réservation',   desc: 'Obligatoire lors de la demande' },
                { value: 'required_after',  icon: '🟢', label: 'Après réservation',   desc: 'Obligatoire après validation' },
              ]" :key="opt.value"
                class="flex flex-col items-start gap-1 px-3 py-3 rounded-xl border-2 cursor-pointer transition select-none"
                :class="appForm.status === opt.value
                  ? 'border-blue-500 bg-blue-50'
                  : 'border-gray-200 bg-white hover:border-blue-300'">
                <input type="radio" v-model="appForm.status" :value="opt.value" class="sr-only" />
                <span class="text-base">{{ opt.icon }}</span>
                <span class="text-xs font-semibold text-gray-800">{{ opt.label }}</span>
                <span class="text-xs text-gray-400 leading-tight">{{ opt.desc }}</span>
              </label>
            </div>
          </div>
        </div>

        <!-- ══ Constructeur de champs ══ -->
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
          <div class="flex items-center justify-between px-6 py-4 border-b bg-gray-50">
            <div class="flex items-center gap-2">
              <h2 class="font-semibold text-gray-900">Champs du formulaire</h2>
              <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">{{ appForm.fields.length }}</span>
            </div>
            <button type="button" @click="addField"
                    class="flex items-center gap-1.5 text-sm bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700">
              + Ajouter un champ
            </button>
          </div>

          <!-- Aide -->
          <div v-if="appForm.fields.length === 0" class="text-center py-12 text-gray-400">
            <p class="text-4xl mb-2">📝</p>
            <p class="text-sm">Aucun champ. Cliquez sur "+ Ajouter un champ"</p>
          </div>

          <!-- Grille WYSIWYG (miroir du rendu imprimé) -->
          <div v-else class="p-4 space-y-2">
            <template v-for="(row, rowIdx) in fieldRows" :key="rowIdx">
              <div :class="['grid gap-2', row.length === 2 ? 'grid-cols-2' : 'grid-cols-1']">
                <div
                  v-for="cell in row" :key="cell.idx"
                  :draggable="expandedIdx !== cell.idx"
                  @dragstart="onDragStart($event, cell.idx)"
                  @dragover="onDragOver($event, cell.idx)"
                  @drop="onDrop($event, cell.idx)"
                  @dragend="onDragEnd"
                  :class="[
                    'border rounded-xl transition-all select-none',
                    expandedIdx === cell.idx ? 'border-blue-400 bg-blue-50/30 shadow-sm' : 'border-gray-200 bg-white hover:border-blue-200 hover:shadow-sm',
                    dragOverIndex === cell.idx && dragIndex !== cell.idx ? 'border-indigo-400 bg-indigo-50 scale-[1.01]' : '',
                    dragIndex === cell.idx ? 'opacity-40' : '',
                  ]">

                  <!-- Barre compacte (toujours visible) -->
                  <div class="flex items-center gap-2 px-3 py-2.5 cursor-pointer"
                       @click="toggleExpand(cell.idx)">

                    <!-- Poignée drag -->
                    <div class="text-gray-300 hover:text-gray-500 cursor-grab active:cursor-grabbing flex-shrink-0"
                         @click.stop
                         :draggable="true"
                         @dragstart="onDragStart($event, cell.idx)"
                         @dragend="onDragEnd">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <circle cx="7" cy="5" r="1.5"/><circle cx="13" cy="5" r="1.5"/>
                        <circle cx="7" cy="10" r="1.5"/><circle cx="13" cy="10" r="1.5"/>
                        <circle cx="7" cy="15" r="1.5"/><circle cx="13" cy="15" r="1.5"/>
                      </svg>
                    </div>

                    <!-- Numéro -->
                    <span class="text-xs font-bold text-gray-400 w-5 text-center flex-shrink-0">{{ cell.idx + 1 }}</span>

                    <!-- Résumé du champ -->
                    <div class="flex-1 min-w-0">
                      <span class="text-sm font-medium text-gray-800 truncate block">
                        {{ cell.field.label || '(sans titre)' }}
                      </span>
                      <span class="text-xs text-gray-400">
                        {{ fieldTypes.find(t => t.value === cell.field.type)?.label ?? cell.field.type }}
                        <span v-if="cell.field.required" class="text-red-400 ml-1">*</span>
                      </span>
                    </div>

                    <!-- Toggle largeur -->
                    <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden text-xs font-semibold flex-shrink-0"
                         @click.stop>
                      <button type="button"
                              @click="cell.field.col_span = 1"
                              :class="cell.field.col_span !== 2
                                ? 'bg-indigo-600 text-white px-2 py-1'
                                : 'bg-white text-gray-400 px-2 py-1 hover:bg-gray-50'"
                              title="Demi-largeur">½</button>
                      <button type="button"
                              @click="cell.field.col_span = 2"
                              :class="cell.field.col_span === 2
                                ? 'bg-indigo-600 text-white px-2 py-1'
                                : 'bg-white text-gray-400 px-2 py-1 hover:bg-gray-50'"
                              title="Pleine largeur">↔</button>
                    </div>

                    <!-- Supprimer -->
                    <button type="button" @click.stop="removeField(cell.idx)"
                            class="text-red-300 hover:text-red-500 flex-shrink-0 transition">✕</button>

                    <!-- Chevron -->
                    <span class="text-gray-400 text-xs flex-shrink-0 transition-transform duration-200"
                          :class="expandedIdx === cell.idx ? 'rotate-180' : ''">▼</span>
                  </div>

                  <!-- Panneau d'édition (déplié) -->
                  <div v-if="expandedIdx === cell.idx" class="px-4 pb-4 border-t border-blue-100 pt-3 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                      <div class="col-span-2 sm:col-span-1">
                        <label class="block text-xs text-gray-500 mb-1">Label *</label>
                        <input v-model="cell.field.label" required
                               placeholder="Ex: Nom de l'emprunteur"
                               class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500 outline-none" />
                      </div>
                      <div class="col-span-2 sm:col-span-1">
                        <label class="block text-xs text-gray-500 mb-1">Type de champ</label>
                        <select v-model="cell.field.type" @change="onTypeChange(cell.field)"
                                class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500 outline-none">
                          <option v-for="t in fieldTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                      </div>

                      <!-- Placeholder (texte, textarea, number, email, phone, date) -->
                      <div v-if="!['checkbox','select','signature'].includes(cell.field.type)" class="col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">Texte indicatif (placeholder)</label>
                        <input v-model="cell.field.placeholder"
                               class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500 outline-none" />
                      </div>

                      <!-- Options : select OU checkbox -->
                      <div v-if="['select','checkbox'].includes(cell.field.type)" class="col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">
                          {{ cell.field.type === 'checkbox' ? 'Options à cocher' : 'Options de la liste' }}
                        </label>
                        <div class="space-y-1.5">
                          <div v-for="(opt, j) in cell.field.options" :key="j" class="flex gap-2 items-center">
                            <span v-if="cell.field.type === 'checkbox'" class="text-gray-400 flex-shrink-0">
                              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3" stroke-width="2"/></svg>
                            </span>
                            <span v-else class="text-gray-400 text-xs flex-shrink-0">•</span>
                            <input v-model="cell.field.options[j]"
                                   :placeholder="cell.field.type === 'checkbox' ? `Option ${j+1}…` : `Option ${j+1}…`"
                                   class="flex-1 px-2 py-1.5 border border-gray-200 rounded-lg text-sm focus:ring-1 focus:ring-blue-500 outline-none" />
                            <button type="button" @click="removeOption(cell.field, j)"
                                    class="text-red-400 hover:text-red-600 text-xs flex-shrink-0">✕</button>
                          </div>
                        </div>
                        <button type="button" @click="addOption(cell.field)"
                                class="mt-2 text-xs text-blue-600 hover:underline flex items-center gap-1">
                          + Ajouter une option
                        </button>
                        <p v-if="cell.field.options.length === 0" class="text-xs text-amber-600 mt-1">
                          ⚠️ Ajoutez au moins une option.
                        </p>
                      </div>

                      <!-- Aperçu bloc signature -->
                      <div v-if="cell.field.type === 'signature'" class="col-span-2">
                        <div class="border-2 border-dashed border-gray-200 rounded-xl p-3 bg-gray-50">
                          <p class="text-xs font-semibold text-gray-600 mb-2">✍️ {{ cell.field.label || 'Signature' }}</p>
                          <div class="grid grid-cols-2 gap-2 mb-2">
                            <div>
                              <p class="text-xs text-gray-500">Prénom</p>
                              <div class="border-b border-gray-400 h-5"></div>
                            </div>
                            <div>
                              <p class="text-xs text-gray-500">Nom</p>
                              <div class="border-b border-gray-400 h-5"></div>
                            </div>
                          </div>
                          <p class="text-xs text-gray-500 mb-1">Signature manuscrite</p>
                          <div class="border border-gray-200 rounded h-10 bg-white"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Pleine largeur forcée pour les blocs signature.</p>
                      </div>

                      <label class="col-span-2 flex items-center gap-2 cursor-pointer">
                        <input v-model="cell.field.required" type="checkbox" class="w-3.5 h-3.5 rounded text-blue-600" />
                        <span class="text-xs text-gray-600">Champ obligatoire</span>
                      </label>
                    </div>
                  </div>

                </div>
              </div>
            </template>
          </div>

          <!-- Légende -->
          <div v-if="appForm.fields.length > 0"
               class="px-6 py-3 border-t bg-gray-50 flex items-center gap-4 text-xs text-gray-400">
            <span>⠿ Glisser pour réordonner</span>
            <span>½ = demi-largeur (côte à côte) · ↔ = pleine largeur</span>
            <span>▼ Cliquer pour modifier</span>
          </div>
        </div>

        <div class="flex gap-3">
          <button type="button" @click="goBack"
             class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm text-center hover:bg-gray-50">Annuler</button>
          <button type="submit" :disabled="appForm.processing"
                  class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ appForm.processing ? 'Sauvegarde…' : 'Sauvegarder le formulaire' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
