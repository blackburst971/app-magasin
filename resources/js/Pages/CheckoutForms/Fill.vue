<template>
    <AppLayout :title="form.name">
        <div class="max-w-2xl mx-auto">
            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('reservations.show', reservation.id)"
                      class="text-gray-500 hover:text-gray-700">
                    <ArrowLeftIcon class="w-5 h-5" />
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ form.name }}</h1>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Réservation : {{ reservation.title }}
                    </p>
                </div>
            </div>

            <!-- Déjà rempli -->
            <div v-if="existingResponse"
                 class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 flex items-start gap-3">
                <CheckCircleIcon class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
                <div>
                    <p class="text-sm font-medium text-blue-800">Formulaire déjà soumis</p>
                    <p class="text-xs text-blue-600 mt-0.5">
                        Le {{ formatDate(existingResponse.submitted_at) }}
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <form @submit.prevent="submit" class="space-y-5">
                    <div v-for="field in form.fields" :key="field.id" class="space-y-1">
                        <label class="block text-sm font-medium text-gray-700">
                            {{ field.label }}
                            <span v-if="field.required" class="text-red-500 ml-0.5">*</span>
                        </label>

                        <!-- Text -->
                        <input v-if="field.type === 'text'"
                               v-model="responses[field.id]"
                               type="text"
                               :required="field.required"
                               :placeholder="field.placeholder"
                               class="input-field" />

                        <!-- Textarea -->
                        <textarea v-else-if="field.type === 'textarea'"
                                  v-model="responses[field.id]"
                                  :required="field.required"
                                  :placeholder="field.placeholder"
                                  rows="3"
                                  class="input-field resize-none" />

                        <!-- Date -->
                        <input v-else-if="field.type === 'date'"
                               v-model="responses[field.id]"
                               type="date"
                               :required="field.required"
                               class="input-field" />

                        <!-- Select -->
                        <select v-else-if="field.type === 'select'"
                                v-model="responses[field.id]"
                                :required="field.required"
                                class="input-field">
                            <option value="">— Choisir —</option>
                            <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
                        </select>

                        <!-- Checkbox -->
                        <label v-else-if="field.type === 'checkbox'" class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox"
                                   v-model="responses[field.id]"
                                   :required="field.required"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            <span class="text-sm text-gray-700">{{ field.placeholder || 'Oui' }}</span>
                        </label>

                        <!-- Signature (Prénom + Nom) -->
                        <div v-else-if="field.type === 'signature'"
                             class="border-2 border-dashed border-gray-200 rounded-xl p-4 bg-gray-50 space-y-3">
                          <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Identification du signataire</p>
                          <div class="grid grid-cols-2 gap-3">
                            <div>
                              <label class="block text-xs text-gray-600 mb-1">Prénom <span v-if="field.required" class="text-red-500">*</span></label>
                              <input v-model="getSignature(field.id).first_name"
                                     type="text"
                                     :required="field.required"
                                     placeholder="Prénom…"
                                     class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
                            </div>
                            <div>
                              <label class="block text-xs text-gray-600 mb-1">Nom <span v-if="field.required" class="text-red-500">*</span></label>
                              <input v-model="getSignature(field.id).last_name"
                                     type="text"
                                     :required="field.required"
                                     placeholder="Nom…"
                                     class="w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
                            </div>
                          </div>
                          <p class="text-xs text-gray-400 italic">La signature manuscrite sera apposée lors de l'impression de la fiche de sortie.</p>
                        </div>

                        <p v-if="errors[field.id]" class="text-red-500 text-xs">{{ errors[field.id] }}</p>
                    </div>

                    <!-- Récapitulatif équipements -->
                    <div class="pt-4 border-t">
                        <p class="text-sm font-medium text-gray-700 mb-2">Équipements empruntés :</p>
                        <ul class="space-y-1">
                            <li v-for="asset in reservation.assets" :key="asset.id"
                                class="flex items-center gap-2 text-sm text-gray-600">
                                <span class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0"></span>
                                {{ asset.name }}
                                <span class="text-gray-400 font-mono text-xs">{{ asset.asset_tag }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <Link :href="route('reservations.show', reservation.id)"
                              class="flex-1 btn-secondary text-center">
                            Annuler
                        </Link>
                        <button type="submit" :disabled="loading"
                                class="flex-1 btn-primary">
                            {{ loading ? 'Envoi…' : existingResponse ? 'Mettre à jour' : 'Soumettre le formulaire' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { ArrowLeftIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import { reactive, ref } from 'vue'

const props = defineProps({
    form: Object,
    reservation: Object,
    existingResponse: { type: Object, default: null },
})

// Pré-remplir avec les réponses existantes
const responses = reactive(
    props.existingResponse
        ? JSON.parse(props.existingResponse.responses_json || '{}')
        : {}
)

const errors = ref({})
const loading = ref(false)

// Pour les champs de type signature, stocker un objet {first_name, last_name}
function getSignature(fieldId) {
  if (!responses[fieldId] || typeof responses[fieldId] !== 'object') {
    responses[fieldId] = { first_name: '', last_name: '' }
  }
  return responses[fieldId]
}

function submit() {
    loading.value = true
    errors.value = {}

    router.post(route('checkout-forms.submit', {
        form: props.form.id,
        reservation: props.reservation.id,
    }), { responses }, {
        onError: (e) => { errors.value = e },
        onFinish: () => { loading.value = false },
    })
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('fr-FR', {
        day: '2-digit', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
}
</script>

<style scoped>
.input-field {
    @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent;
}
.btn-primary {
    @apply bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition disabled:opacity-50;
}
.btn-secondary {
    @apply bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium px-4 py-2 rounded-lg text-sm transition;
}
</style>
