<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'
import axios from 'axios'

const step         = ref(1)   // 1=upload, 2=choix, 3=résultat
const file         = ref(null)
const analyzing    = ref(false)
const importing    = ref(false)
const analysis     = ref(null) // { token, counts, image_count }
const importResult = ref(null) // { stats, errors }
const errorMsg     = ref(null)

// Options d'import (cochées par défaut sauf utilisateurs)
const options = ref({
  manufacturers: true,
  categories:    true,
  models:        true,
  assets:        true,
  licenses:      false,
  users:         false,
  images:        true,
})

const labels = {
  manufacturers: 'Fabricants',
  categories:    'Catégories',
  models:        'Modèles d\'équipements',
  assets:        'Équipements',
  licenses:      'Licences logicielles',
  users:         'Utilisateurs',
}

// ─── Étape 1 : Upload + Analyse ──────────────────────────────────────────
const onFileChange = (e) => {
  file.value    = e.target.files[0]
  analysis.value = null
  errorMsg.value = null
}

const analyze = async () => {
  if (!file.value) return
  analyzing.value = true
  errorMsg.value  = null

  const fd = new FormData()
  fd.append('file', file.value)

  try {
    const { data } = await axios.post(route('import.snipe-backup.analyze'), fd)
    analysis.value = data
    step.value = 2
  } catch (e) {
    errorMsg.value = e.response?.data?.error
      ?? e.response?.data?.message
      ?? 'Erreur lors de l\'analyse du fichier'
  } finally {
    analyzing.value = false
  }
}

// ─── Étape 2 : Import ─────────────────────────────────────────────────────
const startImport = async () => {
  importing.value = true
  errorMsg.value  = null

  try {
    const { data } = await axios.post(route('import.snipe-backup.import'), {
      token:   analysis.value.token,
      options: options.value,
    })
    importResult.value = data
    step.value = 3
  } catch (e) {
    errorMsg.value = e.response?.data?.error
      ?? e.response?.data?.message
      ?? "Erreur lors de l'import"
  } finally {
    importing.value = false
  }
}

const cancel = async () => {
  await axios.post(route('import.snipe-backup.cancel')).catch(() => {})
  reset()
}

const reset = () => {
  step.value         = 1
  file.value         = null
  analysis.value     = null
  importResult.value = null
  errorMsg.value     = null
  options.value      = { manufacturers: true, categories: true, models: true, assets: true, licenses: false, users: false, images: true }
}

// ─── Synchronisation images ───────────────────────────────────────────────
const syncingImages  = ref(false)
const syncImagesDone = ref(null) // { updated: N }

const syncImages = async () => {
  syncingImages.value  = true
  syncImagesDone.value = null
  try {
    const { data } = await axios.post(route('import.snipe-backup.sync-images'))
    syncImagesDone.value = data
  } catch (e) {
    syncImagesDone.value = { error: e.response?.data?.error ?? 'Erreur' }
  } finally {
    syncingImages.value = false
  }
}

// ─── Calculs affichage résultats ──────────────────────────────────────────
const totalImported = computed(() => {
  if (!importResult.value) return 0
  return Object.values(importResult.value.stats).reduce((s, r) => s + (r.imported ?? 0), 0)
})

const totalUpdated = computed(() => {
  if (!importResult.value) return 0
  return Object.values(importResult.value.stats).reduce((s, r) => s + (r.updated ?? 0), 0)
})

const resultRows = computed(() => {
  if (!importResult.value) return []
  return Object.entries(importResult.value.stats).map(([key, r]) => ({
    key,
    label:    labels[key] ?? key,
    imported: r.imported ?? 0,
    updated:  r.updated  ?? 0,
    errors:   r.errors?.length ?? 0,
  }))
})
</script>

<template>
  <AppLayout>
    <div class="max-w-3xl">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Import sauvegarde Snipe-IT</h1>
        <p class="text-sm text-gray-500 mt-1">
          Importez une sauvegarde complète (ZIP) depuis Snipe-IT — équipements, modèles et images inclus.
        </p>
      </div>

      <!-- Steps -->
      <div class="flex items-center gap-2 mb-6 text-sm">
        <div
          v-for="(s, i) in ['Fichier ZIP', 'Sélection', 'Résultat']"
          :key="i"
          :class="[
            'flex items-center gap-1.5 px-3 py-1.5 rounded-full font-medium transition',
            step === i + 1  ? 'bg-green-600 text-white'
            : step > i + 1  ? 'bg-green-100 text-green-700'
                             : 'bg-gray-100 text-gray-400'
          ]"
        >
          <span v-if="step > i + 1">✓</span>
          <span v-else>{{ i + 1 }}</span>
          {{ s }}
        </div>
      </div>

      <!-- Erreur globale -->
      <div v-if="errorMsg" class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm">
        <span>❌</span> {{ errorMsg }}
      </div>

      <!-- ── Étape 1 : Upload ─────────────────────────────────────────── -->
      <div v-if="step === 1" class="bg-white rounded-xl shadow-sm border p-6 space-y-5">

        <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-sm text-blue-800">
          <p class="font-semibold mb-1">Comment obtenir la sauvegarde Snipe-IT ?</p>
          <ol class="list-decimal list-inside space-y-0.5 text-blue-700">
            <li>Dans Snipe-IT : <strong>Admin → Sauvegardes</strong></li>
            <li>Cliquez sur <strong>Créer une sauvegarde</strong></li>
            <li>Téléchargez le fichier <code class="bg-blue-100 px-1 rounded">.zip</code></li>
          </ol>
        </div>

        <div
          class="border-2 border-dashed border-gray-300 hover:border-green-400 rounded-xl p-8 text-center cursor-pointer transition"
          @click="$refs.fileInput.click()"
        >
          <p class="text-4xl mb-2">📦</p>
          <p class="text-gray-700 font-medium">{{ file?.name ?? 'Cliquer pour choisir le fichier ZIP' }}</p>
          <p class="text-gray-400 text-xs mt-1">Sauvegarde Snipe-IT — fichier .zip</p>
          <input ref="fileInput" type="file" accept=".zip" class="hidden" @change="onFileChange" />
        </div>

        <div v-if="file" class="text-sm text-gray-500 text-center">
          Fichier sélectionné : <strong>{{ file.name }}</strong>
          ({{ (file.size / 1024 / 1024).toFixed(1) }} Mo)
        </div>

        <button
          v-if="file"
          @click="analyze"
          :disabled="analyzing"
          class="w-full bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white py-2.5 rounded-lg font-medium transition"
        >
          {{ analyzing ? 'Analyse en cours…' : 'Analyser le fichier →' }}
        </button>
      </div>

      <!-- ── Étape 2 : Sélection des données à importer ──────────────── -->
      <div v-if="step === 2 && analysis" class="space-y-4">

        <!-- Résumé de l'analyse -->
        <div class="bg-white rounded-xl shadow-sm border p-5">
          <h2 class="font-semibold text-gray-800 mb-3">Contenu détecté dans la sauvegarde</h2>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div
              v-for="[key, label] in Object.entries(labels)"
              :key="key"
              class="bg-gray-50 rounded-lg p-3 text-center"
            >
              <p class="text-2xl font-bold text-gray-800">{{ analysis.counts[key] ?? 0 }}</p>
              <p class="text-xs text-gray-500 mt-0.5">{{ label }}</p>
            </div>
          </div>
          <div v-if="analysis.image_count > 0" class="mt-3 flex items-center gap-2 bg-green-50 border border-green-200 rounded-lg px-3 py-2 text-sm text-green-800">
            <span>🖼️</span>
            <strong>{{ analysis.image_count }} images</strong> détectées dans l'archive (modèles, fabricants, assets)
          </div>
        </div>

        <!-- Options d'import -->
        <div class="bg-white rounded-xl shadow-sm border p-5">
          <h2 class="font-semibold text-gray-800 mb-1">Que souhaitez-vous importer ?</h2>
          <p class="text-sm text-gray-500 mb-4">Les données existantes ne seront pas écrasées — seuls les champs manquants sont mis à jour.</p>

          <div class="space-y-3">
            <!-- Option images -->
            <label class="flex items-start gap-3 p-3 rounded-lg border-2 cursor-pointer transition"
              :class="options.images ? 'border-green-300 bg-green-50' : 'border-gray-200'"
            >
              <input type="checkbox" v-model="options.images" class="mt-0.5 accent-green-600" />
              <div>
                <p class="font-medium text-gray-800">
                  🖼️ Images
                  <span v-if="analysis.image_count > 0" class="ml-2 text-xs font-normal bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                    {{ analysis.image_count }} images disponibles
                  </span>
                </p>
                <p class="text-xs text-gray-500 mt-0.5">Importe les images des modèles, fabricants et équipements</p>
              </div>
            </label>

            <div class="border-t pt-3">
              <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Données à importer</p>
            </div>

            <label
              v-for="[key, label] in Object.entries(labels)"
              :key="key"
              class="flex items-start gap-3 p-3 rounded-lg border-2 cursor-pointer transition"
              :class="options[key] ? 'border-green-300 bg-green-50' : 'border-gray-200'"
            >
              <input type="checkbox" v-model="options[key]" class="mt-0.5 accent-green-600" />
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <p class="font-medium text-gray-800">{{ label }}</p>
                  <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                    {{ analysis.counts[key] ?? 0 }} enregistrements
                  </span>
                  <span v-if="key === 'users'" class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full">
                    ⚠️ Rôle etudiant attribué
                  </span>
                </div>
                <p v-if="key === 'models'" class="text-xs text-gray-500 mt-0.5">
                  Inclut les images des modèles si l'option Images est cochée
                </p>
                <p v-if="key === 'users'" class="text-xs text-gray-500 mt-0.5">
                  Les utilisateurs existants (même email) ne seront pas modifiés
                </p>
                <p v-if="key === 'assets'" class="text-xs text-gray-500 mt-0.5">
                  Nécessite que les modèles soient importés (ou déjà présents)
                </p>
              </div>
            </label>
          </div>
        </div>

        <div class="flex gap-3">
          <button @click="cancel" class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm hover:bg-gray-50 transition">
            ← Annuler
          </button>
          <button
            @click="startImport"
            :disabled="importing || !Object.values(options).some(Boolean)"
            class="flex-1 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white py-2.5 rounded-lg font-medium transition"
          >
            {{ importing ? 'Import en cours… (peut prendre quelques minutes)' : '✅ Lancer l\'import' }}
          </button>
        </div>
      </div>

      <!-- ── Étape 3 : Résultats ─────────────────────────────────────── -->
      <div v-if="step === 3 && importResult" class="bg-white rounded-xl shadow-sm border p-6">

        <div class="text-center mb-6">
          <p class="text-5xl mb-3">{{ importResult.errors.length === 0 ? '🎉' : '✅' }}</p>
          <h2 class="text-xl font-bold text-gray-900">Import terminé</h2>
          <p class="text-sm text-gray-500 mt-1">
            {{ totalImported }} créés · {{ totalUpdated }} mis à jour
          </p>
        </div>

        <!-- Tableau par type -->
        <div class="rounded-xl border overflow-hidden mb-5">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="text-left px-4 py-2.5 font-semibold text-gray-600">Type</th>
                <th class="text-right px-4 py-2.5 font-semibold text-green-600">Créés</th>
                <th class="text-right px-4 py-2.5 font-semibold text-blue-600">Mis à jour</th>
                <th class="text-right px-4 py-2.5 font-semibold text-red-500">Erreurs</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in resultRows" :key="row.key" class="border-b last:border-0">
                <td class="px-4 py-2.5 font-medium text-gray-800">{{ row.label }}</td>
                <td class="px-4 py-2.5 text-right text-green-600 font-semibold">{{ row.imported }}</td>
                <td class="px-4 py-2.5 text-right text-blue-600 font-semibold">{{ row.updated }}</td>
                <td class="px-4 py-2.5 text-right" :class="row.errors > 0 ? 'text-red-600 font-semibold' : 'text-gray-400'">
                  {{ row.errors }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Erreurs détaillées -->
        <div v-if="importResult.errors.length > 0" class="bg-red-50 border border-red-200 rounded-xl p-4 mb-5">
          <p class="font-semibold text-red-800 text-sm mb-2">Détail des erreurs ({{ importResult.errors.length }}) :</p>
          <ul class="text-xs text-red-700 space-y-1 max-h-48 overflow-y-auto">
            <li v-for="e in importResult.errors" :key="e" class="flex gap-1">
              <span class="shrink-0">•</span> {{ e }}
            </li>
          </ul>
        </div>

        <div class="flex gap-3">
          <button @click="reset" class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm hover:bg-gray-50 transition">
            Nouvel import
          </button>
          <a href="/models" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2.5 rounded-lg text-sm font-medium text-center transition">
            Voir les modèles →
          </a>
        </div>

        <!-- Synchronisation images post-import -->
        <div class="mt-4 border-t pt-4">
          <p class="text-xs text-gray-500 mb-2">Si des équipements manquent encore d'image, forcez la synchronisation depuis les modèles :</p>
          <button @click="syncImages" :disabled="syncingImages"
            class="w-full border border-indigo-300 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50">
            {{ syncingImages ? 'Synchronisation…' : '🖼️ Synchroniser les images modèles → équipements' }}
          </button>
          <p v-if="syncImagesDone && !syncImagesDone.error" class="text-xs text-green-700 mt-1.5 text-center">
            ✓ {{ syncImagesDone.updated }} équipement(s) mis à jour
          </p>
          <p v-if="syncImagesDone?.error" class="text-xs text-red-600 mt-1.5 text-center">
            {{ syncImagesDone.error }}
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
