<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  asset: Object,
  reservationId: Number,
  returnCondition: String,
})

const form = useForm({
  asset_id: props.asset.id,
  reservation_id: props.reservationId || null,
  type: props.returnCondition === 'damaged' ? 'damage' :
        props.returnCondition === 'lost' ? 'loss' : 'return_issue',
  title: '',
  content: '',
  return_condition: props.returnCondition || null,
})

const typeOptions = [
  { value: 'damage', label: 'Dégradation / Dommage' },
  { value: 'loss', label: 'Perte' },
  { value: 'malfunction', label: 'Dysfonctionnement' },
  { value: 'observation', label: 'Observation' },
  { value: 'return_issue', label: 'Problème au retour' },
]

function submit() {
  form.post(route('admin-reports.store'), {
    onSuccess: () => {
      // Fermer l'onglet après succès (si ouvert depuis retour)
      if (window.opener) {
        window.close()
      }
    }
  })
}
</script>

<template>
  <AppLayout title="Créer un rapport de signalement">
    <div class="max-w-3xl mx-auto py-8 px-4">
      <div class="bg-white rounded-xl shadow-sm border p-6">
        <!-- En-tête -->
        <div class="mb-6">
          <h1 class="text-2xl font-bold text-gray-900">Rapport de signalement</h1>
          <p class="text-sm text-gray-500 mt-1">
            Équipement : <span class="font-semibold">{{ asset.name }}</span> ({{ asset.asset_tag }})
          </p>
          <p v-if="asset.return_check_note" class="text-sm text-amber-600 mt-2 p-2 bg-amber-50 rounded">
            📝 Note d'état : {{ asset.return_check_note }}
          </p>
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
              placeholder="Ex: Écran fissuré, Câble manquant..."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none">
            <p v-if="form.errors.title" class="text-sm text-red-600 mt-1">{{ form.errors.title }}</p>
          </div>

          <!-- Contenu -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description détaillée *</label>
            <textarea v-model="form.content" rows="6" required
              placeholder="Décrivez précisément le problème constaté, les circonstances, l'état de l'équipement..."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
            <p v-if="form.errors.content" class="text-sm text-red-600 mt-1">{{ form.errors.content }}</p>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 pt-4">
            <button type="submit" :disabled="form.processing"
              class="flex-1 bg-blue-600 text-white px-4 py-2.5 rounded-lg hover:bg-blue-700 disabled:opacity-50 font-medium">
              {{ form.processing ? 'Enregistrement...' : 'Enregistrer le rapport' }}
            </button>
            <button type="button" @click="router.visit(route('assets.show', asset.id))"
              class="px-4 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 font-medium">
              Annuler
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
