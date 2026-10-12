<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({
  report: Object,
})

const form = useForm({
  type: props.report.type,
  title: props.report.title,
  content: props.report.content,
  return_condition: props.report.return_condition,
})

const typeOptions = [
  { value: 'damage', label: 'Dégradation / Dommage' },
  { value: 'loss', label: 'Perte' },
  { value: 'malfunction', label: 'Dysfonctionnement' },
  { value: 'observation', label: 'Observation' },
  { value: 'return_issue', label: 'Problème au retour' },
]

function submit() {
  form.patch(route('admin-reports.update', props.report.id))
}

function deleteReport() {
  if (confirm('Êtes-vous sûr de vouloir supprimer ce rapport ?')) {
    router.delete(route('admin-reports.destroy', props.report.id))
  }
}
</script>

<template>
  <AppLayout title="Modifier le rapport">
    <div class="max-w-3xl mx-auto py-8 px-4">
      <div class="bg-white rounded-xl shadow-sm border p-6">
        <!-- En-tête -->
        <div class="mb-6">
          <h1 class="text-2xl font-bold text-gray-900">Modifier le rapport</h1>
          <p class="text-sm text-gray-500 mt-1">
            Équipement : <span class="font-semibold">{{ report.asset.name }}</span> ({{ report.asset.asset_tag }})
          </p>
          <p class="text-xs text-gray-400 mt-1">Créé le {{ report.created_at }}</p>
        </div>

        <!-- Formulaire -->
        <form @submit.prevent="submit" class="space-y-5">
          <!-- Type -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Type de signalement *</label>
            <select v-model="form.type" required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
              <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
            <p v-if="form.errors.type" class="text-sm text-red-600 mt-1">{{ form.errors.type }}</p>
          </div>

          <!-- Titre -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Titre *</label>
            <input v-model="form.title" type="text" required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
            <p v-if="form.errors.title" class="text-sm text-red-600 mt-1">{{ form.errors.title }}</p>
          </div>

          <!-- Contenu -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description détaillée *</label>
            <textarea v-model="form.content" rows="6" required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
            <p v-if="form.errors.content" class="text-sm text-red-600 mt-1">{{ form.errors.content }}</p>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 pt-4">
            <button type="submit" :disabled="form.processing"
              class="flex-1 bg-blue-600 text-white px-4 py-2.5 rounded-lg hover:bg-blue-700 disabled:opacity-50 font-medium">
              {{ form.processing ? 'Enregistrement...' : 'Mettre à jour' }}
            </button>
            <button type="button" @click="router.visit(route('assets.show', report.asset_id))"
              class="px-4 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 font-medium">
              Annuler
            </button>
            <button type="button" @click="deleteReport"
              class="px-4 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 font-medium">
              Supprimer
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
