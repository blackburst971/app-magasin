<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({ form: Object })

const appForm = useForm({
  name: props.form?.name ?? '',
  description: props.form?.description ?? '',
  is_active: props.form?.is_active ?? true,
  is_required_for_reservation: props.form?.is_required_for_reservation ?? false,
  fields: props.form?.fields ?? [],
})

const fieldTypes = [
  { value: 'text',      label: 'Texte court' },
  { value: 'textarea',  label: 'Texte long' },
  { value: 'select',    label: 'Liste déroulante' },
  { value: 'checkbox',  label: 'Case à cocher' },
  { value: 'date',      label: 'Date' },
  { value: 'number',    label: 'Nombre' },
  { value: 'email',     label: 'Email' },
  { value: 'phone',     label: 'Téléphone' },
  { value: 'signature', label: '✍️ Espace signature (Prénom + Nom)' },
]

const addField = () => {
  appForm.fields.push({ type: 'text', label: '', placeholder: '', required: false, options: [] })
}

const removeField = (i) => appForm.fields.splice(i, 1)

const moveField = (i, dir) => {
  const j = i + dir
  if (j < 0 || j >= appForm.fields.length) return
  const tmp = appForm.fields[i]
  appForm.fields[i] = appForm.fields[j]
  appForm.fields[j] = tmp
}

const addOption = (field) => {
  if (!field.options) field.options = []
  field.options.push('')
}

const removeOption = (field, i) => field.options.splice(i, 1)

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
    <div class="max-w-3xl">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">
        {{ form ? 'Modifier le formulaire' : 'Créer un formulaire de sortie' }}
      </h1>

      <form @submit.prevent="submit" class="space-y-6">
        <!-- Form info -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nom du formulaire *</label>
              <input v-model="appForm.name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="appForm.description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
            </div>
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="appForm.is_active" type="checkbox" class="w-4 h-4 rounded text-blue-600" />
              <span class="text-sm font-medium text-gray-700">Formulaire actif</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="appForm.is_required_for_reservation" type="checkbox" class="w-4 h-4 rounded text-amber-500" />
              <span class="text-sm font-medium text-gray-700">
                ⚠️ Obligatoire avant toute réservation
                <span class="text-xs text-amber-600 font-normal ml-1">(l'utilisateur devra remplir ce formulaire pour soumettre)</span>
              </span>
            </label>
          </div>
        </div>

        <!-- Fields -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold">Champs ({{ appForm.fields.length }})</h2>
            <button type="button" @click="addField" class="text-sm bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700">+ Ajouter un champ</button>
          </div>

          <div v-if="appForm.fields.length === 0" class="text-center py-8 text-gray-400 border-2 border-dashed rounded-xl">
            <p class="text-4xl mb-2">📝</p>
            <p class="text-sm">Aucun champ. Cliquez sur "Ajouter un champ"</p>
          </div>

          <div v-else class="space-y-4">
            <div v-for="(field, i) in appForm.fields" :key="i"
              class="border border-gray-200 rounded-xl p-4 hover:border-blue-300 transition">
              <div class="flex items-start gap-3">
                <div class="flex flex-col gap-1 mt-1">
                  <button type="button" @click="moveField(i,-1)" :disabled="i===0" class="text-gray-300 hover:text-gray-600 disabled:opacity-20 text-xs">▲</button>
                  <button type="button" @click="moveField(i,1)" :disabled="i===appForm.fields.length-1" class="text-gray-300 hover:text-gray-600 disabled:opacity-20 text-xs">▼</button>
                </div>
                <div class="flex-1 grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs text-gray-500 mb-1">Label *</label>
                    <input v-model="field.label" required placeholder="Ex: Nom de l'emprunteur" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500 outline-none" />
                  </div>
                  <div>
                    <label class="block text-xs text-gray-500 mb-1">Type</label>
                    <select v-model="field.type" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500 outline-none">
                      <option v-for="t in fieldTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                  </div>
                  <div v-if="!['checkbox','select'].includes(field.type)">
                    <label class="block text-xs text-gray-500 mb-1">Placeholder</label>
                    <input v-model="field.placeholder" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-blue-500 outline-none" />
                  </div>
                  <!-- Select options -->
                  <div v-if="field.type === 'select'" class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">Options</label>
                    <div class="space-y-1">
                      <div v-for="(opt, j) in field.options" :key="j" class="flex gap-2">
                        <input v-model="field.options[j]" placeholder="Option..." class="flex-1 px-2 py-1 border border-gray-200 rounded text-sm" />
                        <button type="button" @click="removeOption(field, j)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                      </div>
                    </div>
                    <button type="button" @click="addOption(field)" class="mt-1 text-xs text-blue-600 hover:underline">+ Ajouter une option</button>
                  </div>
                  <!-- Aperçu bloc signature -->
                  <div v-if="field.type === 'signature'" class="col-span-2">
                    <p class="text-xs text-gray-500 mb-2">Aperçu du bloc signature à imprimer :</p>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-3 bg-gray-50">
                      <p class="text-xs font-semibold text-gray-600 mb-2">✍️ {{ field.label || 'Signature' }}</p>
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
                      <p class="text-xs text-gray-500 mb-1">Signature</p>
                      <div class="border border-gray-300 rounded h-12 bg-white"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Ce bloc sera inclus dans la fiche de sortie imprimable.</p>
                  </div>
                  <label class="flex items-center gap-2 col-span-2 cursor-pointer">
                    <input v-model="field.required" type="checkbox" class="w-3.5 h-3.5 rounded text-blue-600" />
                    <span class="text-xs text-gray-600">Champ obligatoire</span>
                  </label>
                </div>
                <button type="button" @click="removeField(i)" class="text-red-400 hover:text-red-600 mt-1">✕</button>
              </div>
            </div>
          </div>
        </div>

        <div class="flex gap-3">
          <a :href="route('checkout-forms.index')" class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm text-center hover:bg-gray-50">Annuler</a>
          <button type="submit" :disabled="appForm.processing" class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ appForm.processing ? 'Sauvegarde...' : 'Sauvegarder le formulaire' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
