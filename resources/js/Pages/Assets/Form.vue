<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { ref, onMounted, computed } from 'vue'
import { PhotoIcon, XCircleIcon } from '@heroicons/vue/24/outline'
import JsBarcode from 'jsbarcode'

const props = defineProps({ asset: Object, categories: Array })

// Statuts dynamiques depuis les props Inertia globales
const page = usePage()
const assetStatuses = computed(() =>
  Object.values(page.props.assetStatuses ?? {})
    .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
)

const form = useForm({
  name:          props.asset?.name          ?? '',
  asset_tag:     props.asset?.asset_tag     ?? '',
  serial:        props.asset?.serial        ?? '',
  model:         props.asset?.model         ?? '',
  manufacturer:  props.asset?.manufacturer  ?? '',
  category_id:   props.asset?.category_id   ?? '',
  location:      props.asset?.location      ?? '',
  status:        props.asset?.status        ?? 'available',
  purchase_date: props.asset?.purchase_date ?? '',
  purchase_cost: props.asset?.purchase_cost ?? '',
  notes:         props.asset?.notes         ?? '',
  image:         null,
  remove_image:  false,
})

const imagePreview = ref(props.asset?.image_url ?? null)
const barcodeSvg   = ref(null)

onMounted(() => {
  if (barcodeSvg.value && props.asset?.asset_tag) {
    try {
      JsBarcode(barcodeSvg.value, props.asset.asset_tag, {
        format: 'CODE128',
        lineColor: '#1e293b',
        width: 2,
        height: 60,
        displayValue: true,
        fontSize: 13,
        margin: 10,
        background: '#ffffff',
      })
    } catch (e) { /* tag incompatible avec CODE128 */ }
  }
})

function downloadBarcode() {
  if (!barcodeSvg.value) return
  const svgData = new XMLSerializer().serializeToString(barcodeSvg.value)
  const blob = new Blob([svgData], { type: 'image/svg+xml' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `barcode-${props.asset.asset_tag}.svg`
  a.click()
  URL.revokeObjectURL(url)
}

const onImageChange = (e) => {
  const file = e.target.files[0]
  if (file) {
    form.image = file
    form.remove_image = false
    imagePreview.value = URL.createObjectURL(file)
  }
}

const removeImage = () => {
  form.remove_image = true
  form.image = null
  imagePreview.value = null
}

const submit = () => {
  if (props.asset) {
    // PUT avec method spoofing pour que PHP accepte les fichiers multipart
    form.transform(data => ({ ...data, _method: 'put' }))
        .post(route('assets.update', props.asset.id))
  } else {
    form.post(route('assets.store'))
  }
}
</script>

<template>
  <AppLayout>
    <div class="max-w-2xl">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">
        {{ asset ? 'Modifier l\'équipement' : 'Ajouter un équipement' }}
      </h1>

      <form @submit.prevent="submit" class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="font-semibold mb-4">Informations générales</h2>
          <div class="grid grid-cols-2 gap-4">

            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Nom *</label>
              <input v-model="form.name" required
                     class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                     :class="{ 'border-red-400': form.errors.name }" />
              <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Tag / Numéro *</label>
              <input v-model="form.asset_tag" required
                     class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none font-mono"
                     :class="{ 'border-red-400': form.errors.asset_tag }"
                     placeholder="ASSET-001" />
              <p v-if="form.errors.asset_tag" class="mt-1 text-xs text-red-600">{{ form.errors.asset_tag }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Numéro de série</label>
              <input v-model="form.serial"
                     class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
              <select v-model="form.category_id"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">Aucune catégorie</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Statut *</label>
              <select v-model="form.status"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option v-for="s in assetStatuses" :key="s.key" :value="s.key">{{ s.label }}</option>
              </select>
              <p v-if="form.errors.status" class="mt-1 text-xs text-red-600">{{ form.errors.status }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Marque</label>
              <input v-model="form.manufacturer"
                     class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Modèle</label>
              <input v-model="form.model"
                     class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>

            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Emplacement</label>
              <input v-model="form.location"
                     class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                     placeholder="Salle 101, Réserve…" />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date d'achat</label>
              <input v-model="form.purchase_date" type="date"
                     class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Coût d'achat (€)</label>
              <input v-model="form.purchase_cost" type="number" step="0.01" min="0"
                     class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>

            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
              <textarea v-model="form.notes" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
            </div>

            <!-- Photo -->
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Photo</label>
              <div class="flex items-start gap-4">

                <!-- Aperçu actuel -->
                <div v-if="imagePreview" class="relative flex-shrink-0">
                  <img :src="imagePreview" class="w-28 h-28 object-cover rounded-xl border border-gray-200 shadow-sm" />
                  <button type="button" @click="removeImage"
                          title="Supprimer la photo"
                          class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow transition">
                    <XCircleIcon class="w-4 h-4" />
                  </button>
                </div>

                <!-- Zone d'upload -->
                <label class="flex-1 cursor-pointer">
                  <div class="flex flex-col items-center gap-2 px-6 py-5 border-2 border-dashed border-gray-300 rounded-xl text-sm text-gray-500 hover:border-blue-400 hover:bg-blue-50 hover:text-blue-600 transition">
                    <PhotoIcon class="w-8 h-8" />
                    <span class="font-medium">{{ imagePreview ? 'Changer la photo' : 'Ajouter une photo' }}</span>
                    <span class="text-xs text-gray-400">JPG, PNG, WEBP · max 2 Mo</span>
                  </div>
                  <input type="file" accept="image/*" class="hidden" @change="onImageChange" />
                </label>

              </div>
              <p v-if="form.errors.image" class="mt-1 text-xs text-red-600">{{ form.errors.image }}</p>
            </div>

          </div>
        </div>

        <!-- Code-barres (mode édition uniquement) -->
        <div v-if="asset" class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="font-semibold mb-4">Code-barres (Code 128)</h2>
          <div class="flex flex-col items-center gap-3">
            <div class="border rounded-xl p-3 bg-gray-50 overflow-x-auto w-full flex justify-center">
              <svg ref="barcodeSvg" class="max-w-full" />
            </div>
            <button type="button" @click="downloadBarcode"
                    class="text-sm text-blue-600 hover:text-blue-800 underline">
              Télécharger le code-barres (.svg)
            </button>
          </div>
        </div>

        <div class="flex gap-3">
          <a :href="route('assets.index')"
             class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm text-center hover:bg-gray-50">
            Annuler
          </a>
          <button type="submit" :disabled="form.processing"
                  class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ form.processing ? 'Sauvegarde…' : (asset ? 'Mettre à jour' : 'Créer l\'équipement') }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
