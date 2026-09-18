<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import CameraCapture from '@/Components/CameraCapture.vue'
import { useForm, usePage, router, Link } from '@inertiajs/vue3'
import { ref, onMounted, computed } from 'vue'
import { PhotoIcon, XCircleIcon, PlusIcon } from '@heroicons/vue/24/outline'
import JsBarcode from 'jsbarcode'

const props = defineProps({
  asset:         Object,
  categories:    Array,
  places:        Array,
  manufacturers: Array,
})

// Statuts dynamiques depuis les props Inertia globales
const page = usePage()
const assetStatuses = computed(() =>
  Object.values(page.props.assetStatuses ?? {})
    .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
)

// Modèle lié → champs hérités en lecture seule
const linkedModel = computed(() => props.asset?.equipment_model ?? null)
const hasModel    = computed(() => !!linkedModel.value)

function goBack() {
  if (window.history.length > 1) window.history.back()
  else router.visit(route('assets.index'))
}

const form = useForm({
  name:             props.asset?.name             ?? '',
  asset_tag:        props.asset?.asset_tag        ?? '',
  serial:           props.asset?.serial           ?? '',
  model:            props.asset?.model            ?? '',
  manufacturer:     props.asset?.manufacturer     ?? '',
  manufacturer_id:  props.asset?.manufacturer_id  ?? '',
  category_id:      props.asset?.category_id      ?? '',
  place_id:         props.asset?.place_id         ?? '',
  status:           props.asset?.status           ?? 'available',
  purchase_date:    props.asset?.purchase_date    ?? '',
  purchase_cost:    props.asset?.purchase_cost    ?? '',
  notes:            props.asset?.notes            ?? '',
  is_reservable:    props.asset?.is_reservable    ?? true,
  image:            null,
  remove_image:     false,
})

// ── Liste locale des lieux (mise à jour si on en crée un) ─────────────────────
const placesList = ref(props.places ?? [])

// ── Création rapide de lieu ───────────────────────────────────────────────────
const showCreatePlace = ref(false)
const newPlaceName    = ref('')
const newPlaceColor   = ref('#3b82f6')
const newPlaceStorage = ref(false)
const creatingPlace   = ref(false)
const createPlaceError = ref('')

async function createPlaceInline() {
  if (!newPlaceName.value.trim()) {
    createPlaceError.value = 'Le nom est obligatoire.'
    return
  }
  creatingPlace.value   = true
  createPlaceError.value = ''
  try {
    const token = document.cookie.split('; ')
      .find(r => r.startsWith('XSRF-TOKEN='))
      ?.split('=')[1]

    const res = await fetch(route('places.store'), {
      method: 'POST',
      headers: {
        'Content-Type':  'application/json',
        'Accept':        'application/json',
        'X-XSRF-TOKEN':  token ? decodeURIComponent(token) : '',
      },
      body: JSON.stringify({
        name:       newPlaceName.value.trim(),
        color:      newPlaceColor.value,
        is_storage: newPlaceStorage.value,
      }),
    })
    if (!res.ok) {
      const err = await res.json()
      createPlaceError.value = err.message ?? 'Erreur lors de la création.'
      return
    }
    const place = await res.json()
    placesList.value.push({ id: place.id, name: place.name, color: place.color, is_storage: place.is_storage })
    placesList.value.sort((a, b) => a.name.localeCompare(b.name))
    form.place_id      = place.id
    showCreatePlace.value = false
    newPlaceName.value    = ''
    newPlaceColor.value   = '#3b82f6'
    newPlaceStorage.value = false
  } catch {
    createPlaceError.value = 'Erreur réseau.'
  } finally {
    creatingPlace.value = false
  }
}

// ── Image ─────────────────────────────────────────────────────────────────────
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

const onCameraCapture = (file) => {
  form.image = file
  form.remove_image = false
  imagePreview.value = URL.createObjectURL(file)
}

const removeImage = () => {
  form.remove_image = true
  form.image = null
  imagePreview.value = null
}

const submit = () => {
  if (props.asset) {
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

        <!-- ── Bannière modèle lié ── -->
        <div v-if="hasModel"
             class="bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3 flex items-start gap-3">
          <span class="text-lg flex-shrink-0">🔗</span>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-indigo-900">Lié au modèle : {{ linkedModel.name }}</p>
            <p class="text-xs text-indigo-600 mt-0.5">
              La marque, la catégorie et les informations d'achat sont héritées du modèle.
              Pour les modifier, rendez-vous sur la
              <Link :href="route('models.index', { highlight: linkedModel.id })"
                    class="underline font-medium hover:text-indigo-800">page Modèles</Link>.
            </p>
          </div>
        </div>

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

            <!-- Catégorie : héritée du modèle si lié, sinon éditable -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Catégorie</label>
              <template v-if="hasModel">
                <div class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-indigo-50 text-indigo-700 flex items-center gap-2">
                  <span class="text-xs font-medium text-indigo-400 uppercase tracking-wider">Hérité</span>
                  <span>{{ linkedModel.category_name ?? '—' }}</span>
                </div>
              </template>
              <template v-else>
                <select v-model="form.category_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                  <option value="">Aucune catégorie</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </template>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Statut *</label>
              <select v-model="form.status"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option v-for="s in assetStatuses" :key="s.key" :value="s.key">{{ s.label }}</option>
              </select>
              <p v-if="form.errors.status" class="mt-1 text-xs text-red-600">{{ form.errors.status }}</p>
            </div>

            <!-- Marque : héritée du modèle si lié, sinon sélectionnable depuis la liste -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Marque / Fabricant</label>
              <template v-if="hasModel">
                <div class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-indigo-50 text-indigo-700 flex items-center gap-2">
                  <span class="text-xs font-medium text-indigo-400 uppercase tracking-wider">Hérité</span>
                  <span>{{ linkedModel.manufacturer ?? '—' }}</span>
                </div>
              </template>
              <template v-else>
                <select v-model="form.manufacturer_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                  <option value="">Aucun fabricant</option>
                  <option v-for="m in manufacturers" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
              </template>
            </div>

            <!-- Modèle texte : hérité du modèle si lié, sinon éditable -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Modèle</label>
              <template v-if="hasModel">
                <div class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-indigo-50 text-indigo-700 flex items-center gap-2">
                  <span class="text-xs font-medium text-indigo-400 uppercase tracking-wider">Hérité</span>
                  <span>{{ linkedModel.name }}</span>
                </div>
              </template>
              <template v-else>
                <input v-model="form.model"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </template>
            </div>

            <!-- Lieu -->
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Lieu</label>
              <div class="flex gap-2">
                <div class="relative flex-1">
                  <select v-model="form.place_id"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none"
                          :class="{ 'border-red-400': form.errors.place_id }">
                    <option value="">Aucun lieu défini</option>
                    <template v-if="placesList.some(p => !p.is_storage)">
                      <optgroup label="Lieux">
                        <option v-for="p in placesList.filter(p => !p.is_storage)" :key="p.id" :value="p.id">
                          {{ p.name }}
                        </option>
                      </optgroup>
                    </template>
                    <template v-if="placesList.some(p => p.is_storage)">
                      <optgroup label="Zones de stockage">
                        <option v-for="p in placesList.filter(p => p.is_storage)" :key="p.id" :value="p.id">
                          {{ p.name }}
                        </option>
                      </optgroup>
                    </template>
                  </select>
                  <span v-if="form.place_id"
                        class="pointer-events-none absolute right-8 top-1/2 -translate-y-1/2 w-3 h-3 rounded-full border border-white/50"
                        :style="{ backgroundColor: placesList.find(p => p.id == form.place_id)?.color ?? '#94a3b8' }" />
                </div>
                <button type="button"
                        @click="showCreatePlace = !showCreatePlace"
                        class="flex-shrink-0 flex items-center gap-1 px-3 py-2 bg-white border border-gray-300 text-gray-600 rounded-lg text-sm hover:bg-gray-50 hover:border-blue-400 hover:text-blue-600 transition"
                        title="Créer un nouveau lieu">
                  <PlusIcon class="w-4 h-4" />
                  <span class="hidden sm:inline">Nouveau lieu</span>
                </button>
              </div>
              <p v-if="form.errors.place_id" class="mt-1 text-xs text-red-600">{{ form.errors.place_id }}</p>

              <div v-if="showCreatePlace"
                   class="mt-2 border border-blue-200 bg-blue-50 rounded-xl p-4 space-y-3">
                <p class="text-sm font-semibold text-blue-800">Créer un nouveau lieu</p>
                <div class="grid grid-cols-2 gap-3">
                  <div class="col-span-2 sm:col-span-1">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nom *</label>
                    <input v-model="newPlaceName"
                           placeholder="Ex: Salle 101, Réserve…"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Couleur</label>
                    <div class="flex items-center gap-2">
                      <input v-model="newPlaceColor" type="color"
                             class="w-10 h-9 rounded border border-gray-300 cursor-pointer p-0.5 bg-white" />
                      <span class="text-xs text-gray-500">{{ newPlaceColor }}</span>
                    </div>
                  </div>
                  <div class="col-span-2">
                    <label class="flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                      <input v-model="newPlaceStorage" type="checkbox"
                             class="rounded border-gray-300 text-blue-600" />
                      Zone de stockage (magasin, réserve…)
                    </label>
                  </div>
                </div>
                <p v-if="createPlaceError" class="text-xs text-red-600">{{ createPlaceError }}</p>
                <div class="flex gap-2">
                  <button type="button"
                          @click="createPlaceInline"
                          :disabled="creatingPlace"
                          class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 disabled:opacity-50 transition">
                    {{ creatingPlace ? 'Création…' : 'Créer et sélectionner' }}
                  </button>
                  <button type="button"
                          @click="showCreatePlace = false; createPlaceError = ''"
                          class="px-4 py-1.5 border border-gray-300 text-sm text-gray-600 rounded-lg hover:bg-gray-50 transition">
                    Annuler
                  </button>
                </div>
              </div>
            </div>

            <!-- Date d'achat : héritée du modèle si lié, sinon éditable -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Date d'achat</label>
              <template v-if="hasModel">
                <div class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-indigo-50 text-indigo-700 flex items-center gap-2">
                  <span class="text-xs font-medium text-indigo-400 uppercase tracking-wider">Hérité</span>
                  <span>{{ asset?.purchase_date ?? '—' }}</span>
                </div>
              </template>
              <template v-else>
                <input v-model="form.purchase_date" type="date"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </template>
            </div>

            <!-- Coût d'achat : hérité du modèle si lié, sinon éditable -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Coût d'achat (€)</label>
              <template v-if="hasModel">
                <div class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-indigo-50 text-indigo-700 flex items-center gap-2">
                  <span class="text-xs font-medium text-indigo-400 uppercase tracking-wider">Hérité</span>
                  <span>{{ asset?.purchase_cost != null ? Number(asset.purchase_cost).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' }) : '—' }}</span>
                </div>
              </template>
              <template v-else>
                <input v-model="form.purchase_cost" type="number" step="0.01" min="0"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </template>
            </div>

            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
              <textarea v-model="form.notes" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
            </div>

            <!-- Réservable -->
            <div class="col-span-2">
              <label class="flex items-center justify-between gap-4 p-4 border rounded-xl cursor-pointer select-none transition"
                     :class="form.is_reservable ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200'"
                     @click="form.is_reservable = !form.is_reservable">
                <div>
                  <p class="text-sm font-medium text-gray-800">📅 Réservable par les utilisateurs</p>
                  <p class="text-xs mt-0.5" :class="form.is_reservable ? 'text-green-700' : 'text-amber-700'">
                    {{ form.is_reservable
                        ? 'Visible et réservable depuis le catalogue utilisateurs'
                        : 'Masqué du catalogue — non réservable par les utilisateurs' }}
                  </p>
                </div>
                <div class="flex-shrink-0">
                  <div :class="form.is_reservable ? 'bg-green-500' : 'bg-gray-300'"
                       class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors">
                    <span :class="form.is_reservable ? 'translate-x-6' : 'translate-x-1'"
                          class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform" />
                  </div>
                </div>
              </label>
            </div>

            <!-- Photo -->
            <div class="col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Photo</label>
              <div class="flex items-start gap-4">

                <div v-if="imagePreview" class="relative flex-shrink-0">
                  <img :src="imagePreview" class="w-28 h-28 object-cover rounded-xl border border-gray-200 shadow-sm" />
                  <button type="button" @click="removeImage"
                          title="Supprimer la photo"
                          class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow transition">
                    <XCircleIcon class="w-4 h-4" />
                  </button>
                </div>

                <div class="flex-1 flex flex-col gap-2">
                  <label class="cursor-pointer">
                    <div class="flex flex-col items-center gap-2 px-6 py-4 border-2 border-dashed border-gray-300 rounded-xl text-sm text-gray-500 hover:border-blue-400 hover:bg-blue-50 hover:text-blue-600 transition">
                      <PhotoIcon class="w-8 h-8" />
                      <span class="font-medium">{{ imagePreview ? 'Changer la photo' : 'Ajouter une photo' }}</span>
                      <span class="text-xs text-gray-400">JPG, PNG, WEBP, AVIF · max 2 Mo</span>
                    </div>
                    <input type="file" accept="image/*" class="hidden" @change="onImageChange" />
                  </label>
                  <CameraCapture @captured="onCameraCapture" />
                </div>

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
          <button type="button" @click="goBack"
             class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm text-center hover:bg-gray-50">
            Annuler
          </button>
          <button type="submit" :disabled="form.processing"
                  class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ form.processing ? 'Sauvegarde…' : (asset ? 'Mettre à jour' : 'Créer l\'équipement') }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
