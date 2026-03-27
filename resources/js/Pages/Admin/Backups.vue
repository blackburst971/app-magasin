<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import {
  ArrowDownTrayIcon, ArrowPathIcon, TrashIcon,
  CloudArrowUpIcon, CircleStackIcon, ArchiveBoxIcon,
  ExclamationTriangleIcon, CheckCircleIcon, InformationCircleIcon,
} from '@heroicons/vue/24/outline'
import { ShieldCheckIcon } from '@heroicons/vue/24/solid'

const props = defineProps({
  backups:              { type: Array,  default: () => [] },
  dbSizeFormatted:      { type: String, default: '—' },
  storageSizeFormatted: { type: String, default: '—' },
})

// ── Création d'une sauvegarde ─────────────────────────────────────────────────
const creating    = ref(false)
const createFlash = ref(null)

async function createBackup(download = false) {
  creating.value    = true
  createFlash.value = null

  if (download) {
    // Téléchargement direct via form POST (contourne axios pour les blobs)
    const form = document.createElement('form')
    form.method = 'POST'
    form.action = route('backups.create')
    const csrfInput = document.createElement('input')
    csrfInput.type  = 'hidden'
    csrfInput.name  = '_token'
    csrfInput.value = document.querySelector('meta[name="csrf-token"]')?.content ?? ''
    const dlInput   = document.createElement('input')
    dlInput.type    = 'hidden'
    dlInput.name    = 'download'
    dlInput.value   = '1'
    form.append(csrfInput, dlInput)
    document.body.appendChild(form)
    form.submit()
    document.body.removeChild(form)
    creating.value = false
    return
  }

  router.post(route('backups.create'), {}, {
    preserveScroll: true,
    onSuccess:  () => { createFlash.value = 'success' },
    onError:    () => { createFlash.value = 'error'   },
    onFinish:   () => { creating.value = false         },
  })
}

// ── Restauration par upload ───────────────────────────────────────────────────
const restoreFile   = ref(null)
const restoreInput  = ref(null)
const restoring     = ref(false)
const showConfirm   = ref(false)

const uploadForm = useForm({ backup: null })

function pickFile(e) {
  uploadForm.backup = e.target.files?.[0] ?? null
  restoreFile.value = uploadForm.backup?.name ?? null
}

function triggerRestore() {
  if (!uploadForm.backup) return
  showConfirm.value = true
}

function confirmRestore() {
  showConfirm.value = false
  restoring.value   = true
  uploadForm.post(route('backups.restore'), {
    forceFormData:  true,
    preserveScroll: true,
    onFinish:       () => { restoring.value = false },
  })
}

// ── Restauration depuis un backup existant ────────────────────────────────────
const confirmingRestore = ref(null)

function askRestoreExisting(filename) {
  confirmingRestore.value = filename
}

function doRestoreExisting() {
  const filename = confirmingRestore.value
  confirmingRestore.value = null
  router.post(route('backups.restore-existing', filename), {}, { preserveScroll: true })
}

// ── Suppression ───────────────────────────────────────────────────────────────
function deleteBackup(filename) {
  if (!confirm(`Supprimer la sauvegarde "${filename}" ?`)) return
  router.delete(route('backups.destroy', filename), { preserveScroll: true })
}
</script>

<template>
  <AppLayout>
    <div class="max-w-4xl space-y-6">

      <!-- En-tête -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
            <ShieldCheckIcon class="w-7 h-7 text-green-600" />
            Sauvegardes
          </h1>
          <p class="text-gray-500 text-sm mt-1">
            Sauvegardez et restaurez la base de données et les fichiers uploadés.
          </p>
        </div>
      </div>

      <!-- Flash messages Inertia -->
      <div v-if="$page.props.flash?.success"
           class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-800 text-sm">
        <CheckCircleIcon class="w-5 h-5 text-green-500 flex-shrink-0" />
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors?.error || $page.props.errors?.backup"
           class="flex items-center gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-800 text-sm">
        <ExclamationTriangleIcon class="w-5 h-5 text-red-500 flex-shrink-0" />
        {{ $page.props.errors?.error ?? $page.props.errors?.backup }}
      </div>

      <!-- ══ Infos état actuel ══ -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm p-5 flex items-center gap-4">
          <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <CircleStackIcon class="w-5 h-5 text-blue-600" />
          </div>
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Base de données</p>
            <p class="text-lg font-bold text-gray-900">{{ dbSizeFormatted }}</p>
          </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-5 flex items-center gap-4">
          <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
            <ArchiveBoxIcon class="w-5 h-5 text-purple-600" />
          </div>
          <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Fichiers uploadés</p>
            <p class="text-lg font-bold text-gray-900">{{ storageSizeFormatted }}</p>
          </div>
        </div>
      </div>

      <!-- ══ Créer une sauvegarde ══ -->
      <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-1">Créer une sauvegarde</h2>
        <p class="text-sm text-gray-500 mb-4">
          Inclut la base de données complète et tous les fichiers uploadés (images, logos, PDFs…).
        </p>
        <div class="flex flex-wrap gap-3">
          <button @click="createBackup(false)" :disabled="creating"
                  class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 disabled:opacity-60 text-white font-semibold py-2.5 px-5 rounded-xl text-sm transition">
            <ArchiveBoxIcon class="w-4 h-4" />
            {{ creating ? 'Création en cours…' : 'Créer une sauvegarde' }}
          </button>
          <button @click="createBackup(true)" :disabled="creating"
                  class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold py-2.5 px-5 rounded-xl text-sm transition">
            <ArrowDownTrayIcon class="w-4 h-4" />
            Créer et télécharger
          </button>
        </div>
      </div>

      <!-- ══ Restaurer depuis un fichier ══ -->
      <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-1">Restaurer depuis un fichier</h2>
        <p class="text-sm text-gray-500 mb-4">
          Importez un fichier <code class="bg-gray-100 px-1 rounded text-xs">.zip</code> exporté depuis cette application.
          <span class="text-amber-700 font-medium">⚠️ Remplace toutes les données actuelles.</span>
        </p>

        <div class="flex flex-wrap items-end gap-3">
          <div>
            <input ref="restoreInput" type="file" accept=".zip" class="hidden" @change="pickFile" />
            <button @click="restoreInput?.click()"
                    class="inline-flex items-center gap-2 border-2 border-dashed border-gray-300 hover:border-blue-400 text-gray-600 hover:text-blue-700 py-2.5 px-5 rounded-xl text-sm transition">
              <CloudArrowUpIcon class="w-4 h-4" />
              {{ restoreFile ?? 'Choisir un fichier…' }}
            </button>
          </div>
          <button v-if="restoreFile" @click="triggerRestore" :disabled="restoring"
                  class="inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-60 text-white font-semibold py-2.5 px-5 rounded-xl text-sm transition">
            <ArrowPathIcon class="w-4 h-4" />
            {{ restoring ? 'Restauration…' : 'Restaurer' }}
          </button>
        </div>

        <!-- Confirmation modale restore upload -->
        <div v-if="showConfirm"
             class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                <ExclamationTriangleIcon class="w-5 h-5 text-red-600" />
              </div>
              <h3 class="text-base font-bold text-gray-900">Confirmer la restauration</h3>
            </div>
            <p class="text-sm text-gray-600 mb-2">
              Cette action va <strong>remplacer toutes les données actuelles</strong>
              (utilisateurs, équipements, réservations, fichiers) par le contenu de la sauvegarde.
            </p>
            <p class="text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2 mb-4">
              ✅ Une sauvegarde de sécurité sera créée automatiquement avant la restauration.
            </p>
            <div class="flex gap-3">
              <button @click="confirmRestore"
                      class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 rounded-xl text-sm transition">
                Confirmer la restauration
              </button>
              <button @click="showConfirm = false"
                      class="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-xl text-sm hover:bg-gray-50 transition">
                Annuler
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ Sauvegardes disponibles ══ -->
      <div class="bg-white rounded-xl shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-900 mb-4 flex items-center gap-2">
          Sauvegardes stockées sur le serveur
          <span v-if="backups.length" class="text-xs font-normal text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">
            {{ backups.length }}
          </span>
        </h2>

        <div v-if="backups.length === 0"
             class="text-center py-10 text-gray-400">
          <ArchiveBoxIcon class="w-12 h-12 mx-auto mb-3 opacity-30" />
          <p class="text-sm">Aucune sauvegarde pour l'instant.</p>
          <p class="text-xs mt-1">Cliquez "Créer une sauvegarde" pour commencer.</p>
        </div>

        <div v-else class="space-y-2">
          <div v-for="backup in backups" :key="backup.name"
               class="flex items-center justify-between gap-3 p-3 rounded-xl border border-gray-100 hover:border-gray-200 hover:bg-gray-50 transition group">
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-9 h-9 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <ArchiveBoxIcon class="w-5 h-5 text-green-600" />
              </div>
              <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">{{ backup.name }}</p>
                <p class="text-xs text-gray-400">{{ backup.created_at }} · {{ backup.size }}</p>
              </div>
            </div>
            <div class="flex items-center gap-1 flex-shrink-0">
              <!-- Télécharger -->
              <a :href="route('backups.download', backup.name)"
                 class="p-2 text-gray-400 hover:text-blue-600 rounded-lg hover:bg-blue-50 transition"
                 title="Télécharger">
                <ArrowDownTrayIcon class="w-4 h-4" />
              </a>
              <!-- Restaurer -->
              <button @click="askRestoreExisting(backup.name)"
                      class="p-2 text-gray-400 hover:text-amber-600 rounded-lg hover:bg-amber-50 transition"
                      title="Restaurer cette sauvegarde">
                <ArrowPathIcon class="w-4 h-4" />
              </button>
              <!-- Supprimer -->
              <button @click="deleteBackup(backup.name)"
                      class="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition"
                      title="Supprimer">
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Note informative -->
      <div class="flex items-start gap-3 bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-blue-800 text-sm">
        <InformationCircleIcon class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" />
        <div>
          <p class="font-semibold mb-0.5">Protection automatique</p>
          <p class="text-blue-700">
            Le script de mise à jour (<code class="bg-blue-100 px-1 rounded text-xs">update.ps1</code>)
            crée automatiquement une sauvegarde <strong>avant chaque déploiement</strong>.
            Vos données ne peuvent plus être perdues lors d'une mise à jour.
          </p>
        </div>
      </div>

    </div>

    <!-- Modale confirmation restauration depuis serveur -->
    <div v-if="confirmingRestore"
         class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-md w-full">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
            <ArrowPathIcon class="w-5 h-5 text-amber-600" />
          </div>
          <h3 class="text-base font-bold text-gray-900">Restaurer cette sauvegarde ?</h3>
        </div>
        <p class="text-sm text-gray-600 mb-1 font-medium truncate">{{ confirmingRestore }}</p>
        <p class="text-sm text-gray-600 mb-2">
          Toutes les données actuelles seront remplacées par celles de cette sauvegarde.
        </p>
        <p class="text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2 mb-4">
          ✅ Une sauvegarde de sécurité sera créée automatiquement avant la restauration.
        </p>
        <div class="flex gap-3">
          <button @click="doRestoreExisting"
                  class="flex-1 bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2.5 rounded-xl text-sm transition">
            Oui, restaurer
          </button>
          <button @click="confirmingRestore = null"
                  class="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-xl text-sm hover:bg-gray-50 transition">
            Annuler
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>
