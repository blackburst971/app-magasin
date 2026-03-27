<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'
import axios from 'axios'

// ─── Type d'import ────────────────────────────────────────────────────────────
const importType = ref('assets') // 'assets' | 'licenses'

// ─── État commun ──────────────────────────────────────────────────────────────
const file          = ref(null)
const preview       = ref(null)
const importing     = ref(false)
const importResult  = ref(null)
const error         = ref(null)
const mapping       = ref({})
const step          = ref(1)

function setImportType(type) {
  importType.value   = type
  file.value         = null
  preview.value      = null
  importResult.value = null
  error.value        = null
  mapping.value      = {}
  step.value         = 1
}

// ─── Options de champs par type ───────────────────────────────────────────────
const assetFieldOptions = [
  { value: 'name',            label: 'Nom' },
  { value: 'asset_tag',       label: 'Tag équipement' },
  { value: 'serial',          label: 'Numéro de série' },
  { value: 'equipment_model', label: '🔗 Modèle (lié aux modèles)' },
  { value: 'model',           label: 'Modèle — n° (texte libre)' },
  { value: 'manufacturer',    label: 'Fabricant' },
  { value: 'category',        label: 'Catégorie' },
  { value: 'location',        label: 'Emplacement' },
  { value: 'status',          label: 'Statut' },
  { value: 'purchase_date',   label: "Date d'achat" },
  { value: 'purchase_cost',   label: "Coût d'achat" },
  { value: 'order_number',    label: 'N° de commande' },
  { value: 'warranty',        label: 'Garantie (mois)' },
  { value: 'notes',           label: 'Notes' },
  { value: 'image',           label: 'Image (URL)' },
  { value: 'is_reservable',   label: 'Réservable' },
]

const licenseFieldOptions = [
  { value: 'name',          label: 'Nom du logiciel' },
  { value: 'license_key',   label: 'Clé de licence (serial)' },
  { value: 'version',       label: 'Version' },
  { value: 'manufacturer',  label: 'Éditeur / Fabricant' },
  { value: 'seats',         label: 'Nombre de sièges' },
  { value: 'type',          label: 'Type (par poste, volume, cloud…)' },
  { value: 'is_cloud',      label: 'Cloud / SaaS' },
  { value: 'purchase_date', label: "Date d'achat" },
  { value: 'purchase_cost', label: "Coût d'achat" },
  { value: 'expiry_date',   label: "Date d'expiration" },
  { value: 'order_number',  label: 'N° de commande' },
  { value: 'supplier',      label: 'Fournisseur' },
  { value: 'notes',         label: 'Notes' },
]

const fieldOptions = computed(() =>
  importType.value === 'licenses' ? licenseFieldOptions : assetFieldOptions
)

// ─── Routes dynamiques ────────────────────────────────────────────────────────
const previewRoute = computed(() =>
  importType.value === 'licenses' ? route('import.licenses.preview') : route('import.preview')
)
const processRoute = computed(() =>
  importType.value === 'licenses' ? route('import.licenses.process') : route('import.process')
)
const successRoute = computed(() =>
  importType.value === 'licenses' ? route('licenses.index') : route('assets.index')
)
const successLabel = computed(() =>
  importType.value === 'licenses' ? 'Voir les licences →' : 'Voir les équipements →'
)

// ─── Couleurs par type ────────────────────────────────────────────────────────
const accentColor = computed(() =>
  importType.value === 'licenses'
    ? { btn: 'bg-purple-600 hover:bg-purple-700', badge: 'bg-purple-100 text-purple-700', step: 'bg-purple-600', check: 'text-purple-600' }
    : { btn: 'bg-blue-600 hover:bg-blue-700',   badge: 'bg-blue-100 text-blue-700',   step: 'bg-blue-600',   check: 'text-blue-600'   }
)

// ─── Handlers ─────────────────────────────────────────────────────────────────
const onFileChange = (e) => {
  file.value         = e.target.files[0]
  preview.value      = null
  importResult.value = null
  step.value         = 1
  error.value        = null
}

const loadPreview = async () => {
  if (!file.value) return
  error.value = null
  const fd = new FormData()
  fd.append('file', file.value)
  try {
    const { data } = await axios.post(previewRoute.value, fd)
    preview.value = data
    mapping.value = data.suggested_mapping
    step.value    = 2
  } catch (e) {
    error.value = e.response?.data?.errors?.file?.[0]
      ?? e.response?.data?.message
      ?? 'Erreur de lecture du fichier'
  }
}

const processImport = async () => {
  importing.value = true
  error.value     = null
  const fd = new FormData()
  fd.append('file', file.value)
  Object.entries(mapping.value).forEach(([col, field]) => {
    fd.append(`mapping[${col}]`, field || '')
  })
  try {
    const { data } = await axios.post(processRoute.value, fd)
    importResult.value = data
    step.value = 3
  } catch (e) {
    const errs = e.response?.data?.errors
    error.value = errs
      ? Object.values(errs).flat().join(' ')
      : (e.response?.data?.message ?? "Erreur lors de l'import")
  } finally {
    importing.value = false
  }
}

const reset = () => {
  file.value         = null
  preview.value      = null
  importResult.value = null
  step.value         = 1
  error.value        = null
  mapping.value      = {}
}
</script>

<template>
  <AppLayout>
    <div class="max-w-4xl">
      <h1 class="text-2xl font-bold text-gray-900 mb-1">⬆️ Import Snipe-IT</h1>
      <p class="text-gray-500 text-sm mb-6">Importez vos données depuis un export CSV Snipe-IT</p>

      <!-- Sélecteur de type d'import -->
      <div class="flex gap-3 mb-6">
        <button
          @click="setImportType('assets')"
          :class="[
            'flex items-center gap-2 px-5 py-3 rounded-xl font-medium text-sm border-2 transition',
            importType === 'assets'
              ? 'border-blue-600 bg-blue-50 text-blue-700'
              : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
          ]"
        >
          <span class="text-lg">🖥️</span>
          <div class="text-left">
            <div class="font-semibold">Équipements</div>
            <div class="text-xs opacity-70">Assets Snipe-IT</div>
          </div>
        </button>
        <button
          @click="setImportType('licenses')"
          :class="[
            'flex items-center gap-2 px-5 py-3 rounded-xl font-medium text-sm border-2 transition',
            importType === 'licenses'
              ? 'border-purple-600 bg-purple-50 text-purple-700'
              : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
          ]"
        >
          <span class="text-lg">🔑</span>
          <div class="text-left">
            <div class="font-semibold">Licences</div>
            <div class="text-xs opacity-70">Licences Snipe-IT</div>
          </div>
        </button>
      </div>

      <!-- Bandeau info contextuel -->
      <div v-if="importType === 'assets'" class="flex items-start gap-3 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 mb-6 text-sm text-blue-800">
        <span class="text-base mt-0.5">ℹ️</span>
        <div>
          <span class="font-semibold">Équipements</span> — Exportez depuis Snipe-IT : <em>Assets → Exporter</em> (toutes les colonnes). Les colonnes sont détectées automatiquement.
        </div>
      </div>
      <div v-else class="flex items-start gap-3 bg-purple-50 border border-purple-200 rounded-xl px-4 py-3 mb-6 text-sm text-purple-800">
        <span class="text-base mt-0.5">ℹ️</span>
        <div>
          <span class="font-semibold">Licences</span> — Exportez depuis Snipe-IT : <em>Licences → Exporter</em>. La colonne <code class="bg-purple-100 px-1 rounded">serial</code> sera mappée automatiquement sur la clé de licence.
        </div>
      </div>

      <!-- Steps indicator -->
      <div class="flex items-center gap-2 mb-6 text-sm">
        <div
          v-for="(s, i) in ['Fichier', 'Mapping', 'Résultat']"
          :key="i"
          :class="[
            'flex items-center gap-1.5 px-3 py-1.5 rounded-full font-medium transition',
            step === i + 1
              ? (importType === 'licenses' ? 'bg-purple-600 text-white' : 'bg-blue-600 text-white')
              : step > i + 1
              ? 'bg-green-100 text-green-700'
              : 'bg-gray-100 text-gray-400'
          ]"
        >
          <span v-if="step > i + 1">✓</span>
          <span v-else>{{ i + 1 }}</span>
          {{ s }}
        </div>
      </div>

      <!-- Erreur globale -->
      <div v-if="error" class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">
        <span>❌</span> {{ error }}
      </div>

      <!-- ── Étape 1 : Fichier ────────────────────────────────────────────── -->
      <div v-if="step === 1" class="bg-white rounded-xl shadow-sm border p-6">
        <h2 class="font-semibold mb-4 text-gray-800">
          {{ importType === 'licenses' ? '🔑 Sélectionner le fichier de licences' : '🖥️ Sélectionner le fichier d\'équipements' }}
        </h2>
        <div
          class="border-2 border-dashed rounded-xl p-8 text-center transition cursor-pointer"
          :class="importType === 'licenses'
            ? 'border-purple-200 hover:border-purple-400'
            : 'border-gray-300 hover:border-blue-400'"
          @click="$refs.fileInput.click()"
        >
          <p class="text-4xl mb-2">📄</p>
          <p class="text-gray-600 font-medium">{{ file?.name ?? 'Cliquer pour choisir un fichier CSV' }}</p>
          <p class="text-gray-400 text-xs mt-1">
            {{ importType === 'licenses'
              ? 'Export CSV depuis Snipe-IT (Licences → Exporter)'
              : 'Export CSV depuis Snipe-IT (Assets → Exporter)' }}
          </p>
          <input ref="fileInput" type="file" accept=".csv,.txt" class="hidden" @change="onFileChange" />
        </div>
        <button
          v-if="file"
          @click="loadPreview"
          :class="['mt-4 w-full text-white py-2.5 rounded-lg font-medium transition', accentColor.btn]"
        >
          Analyser le fichier →
        </button>
      </div>

      <!-- ── Étape 2 : Mapping ───────────────────────────────────────────── -->
      <div v-if="step === 2 && preview" class="bg-white rounded-xl shadow-sm border p-6">
        <h2 class="font-semibold mb-1 text-gray-800">Correspondance des colonnes</h2>
        <p class="text-sm text-gray-500 mb-4">
          <strong>{{ preview.total_rows }}</strong> lignes détectées.
          Les colonnes Snipe-IT ont été mappées automatiquement — vérifiez et ajustez si besoin.
        </p>

        <!-- Hint licences -->
        <div v-if="importType === 'licenses'" class="text-xs bg-purple-50 border border-purple-200 text-purple-700 rounded-lg px-3 py-2 mb-4">
          💡 La colonne <code class="bg-purple-100 px-1 rounded">serial</code> de Snipe-IT correspond à la <strong>clé de licence</strong> dans cette application.
        </div>

        <div class="overflow-x-auto mb-6 rounded-lg border">
          <table class="text-xs border-collapse w-full min-w-max">
            <thead>
              <tr class="bg-gray-50">
                <th class="border-b border-r px-3 py-2 text-left font-semibold text-gray-600">Colonne CSV</th>
                <th class="border-b border-r px-3 py-2 text-left font-semibold text-gray-600">→ Champ</th>
                <th v-for="(row, i) in preview.preview" :key="i" class="border-b border-r px-3 py-2 text-gray-500 font-normal">
                  Aperçu {{ i + 1 }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="col in preview.headers" :key="col" class="hover:bg-gray-50">
                <td class="border-b border-r px-3 py-1.5 font-mono text-gray-700">{{ col }}</td>
                <td class="border-b border-r px-2 py-1">
                  <select
                    v-model="mapping[col]"
                    :class="[
                      'text-xs border rounded px-1.5 py-1 w-full',
                      mapping[col]
                        ? (importType === 'licenses' ? 'border-purple-300 bg-purple-50' : 'border-blue-300 bg-blue-50')
                        : 'border-gray-200'
                    ]"
                  >
                    <option value="">— ignorer —</option>
                    <option v-for="f in fieldOptions" :key="f.value" :value="f.value">{{ f.label }}</option>
                  </select>
                </td>
                <td
                  v-for="(row, i) in preview.preview"
                  :key="i"
                  class="border-b border-r px-3 py-1.5 text-gray-500 max-w-[8rem] truncate"
                >
                  {{ row[col] || '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex gap-3">
          <button
            @click="step = 1"
            class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm hover:bg-gray-50 transition"
          >
            ← Retour
          </button>
          <button
            @click="processImport"
            :disabled="importing"
            :class="['flex-1 text-white py-2.5 rounded-lg font-medium transition disabled:opacity-50', accentColor.btn]"
          >
            {{ importing ? 'Import en cours…' : '✅ Lancer l\'import' }}
          </button>
        </div>
      </div>

      <!-- ── Étape 3 : Résultat ──────────────────────────────────────────── -->
      <div v-if="step === 3 && importResult" class="bg-white rounded-xl shadow-sm border p-6">
        <div class="text-center mb-6">
          <p class="text-5xl mb-3">{{ importResult.errors.length === 0 ? '🎉' : '⚠️' }}</p>
          <h2 class="text-xl font-bold text-gray-900">Import terminé</h2>
          <p class="text-sm text-gray-500 mt-1">
            {{ importResult.total }} {{ importType === 'licenses' ? 'licences traitées' : 'équipements traités' }}
          </p>
        </div>

        <div class="grid grid-cols-3 gap-4 mb-6">
          <div class="bg-green-50 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-green-600">{{ importResult.imported }}</p>
            <p class="text-xs text-green-700 mt-1 font-medium">Nouveaux</p>
          </div>
          <div class="bg-blue-50 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-blue-600">{{ importResult.updated }}</p>
            <p class="text-xs text-blue-700 mt-1 font-medium">Mis à jour</p>
          </div>
          <div class="bg-red-50 rounded-xl p-4 text-center">
            <p class="text-3xl font-bold text-red-600">{{ importResult.errors.length }}</p>
            <p class="text-xs text-red-700 mt-1 font-medium">Erreurs</p>
          </div>
        </div>

        <div v-if="importResult.errors.length > 0" class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
          <p class="font-semibold text-red-800 text-sm mb-2">Erreurs détectées :</p>
          <ul class="text-xs text-red-700 space-y-1 max-h-40 overflow-y-auto">
            <li v-for="e in importResult.errors" :key="e" class="flex gap-1">
              <span class="shrink-0">•</span> {{ e }}
            </li>
          </ul>
        </div>

        <div class="flex gap-3">
          <button
            @click="reset"
            class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm hover:bg-gray-50 transition"
          >
            Nouvel import
          </button>
          <a
            :href="successRoute"
            :class="['flex-1 text-white py-2.5 rounded-lg text-sm font-medium text-center transition', accentColor.btn]"
          >
            {{ successLabel }}
          </a>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
