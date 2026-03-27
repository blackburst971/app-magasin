<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useCartStore } from '@/stores/cart'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import { TrashIcon, ShoppingCartIcon, CalendarDaysIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  requiredForm:     Object,
  examMode:         Boolean,
  examAdvanceHours: { type: Number, default: 24 },
  examProcessStart: { type: Number, default: 7 },
  examProcessEnd:   { type: Number, default: 9 },
})

const cart = useCartStore()

const form = ref({
  title: '',
  start_date: '',
  end_date: '',
  notes: '',
  form_id: props.requiredForm?.id ?? null,
  form_responses: {},
})
const errors = ref({})
const loading = ref(false)

// ─── Formulaire obligatoire ───────────────────────────────────────────────────
function getOrInitResponse(fieldId, type) {
  if (type === 'signature' && !form.value.form_responses[fieldId]) {
    form.value.form_responses[fieldId] = { first_name: '', last_name: '' }
  }
  return form.value.form_responses[fieldId]
}

const requiredFormValid = computed(() => {
  if (!props.requiredForm) return true
  for (const field of props.requiredForm.fields) {
    if (!field.required) continue
    const val = form.value.form_responses[field.id]
    if (field.type === 'signature') {
      if (!val?.first_name?.trim() || !val?.last_name?.trim()) return false
    } else {
      if (!val || String(val).trim() === '') return false
    }
  }
  return true
})

// --- Availability check ---
const availabilityMap = ref({})   // { assetId: { status, reason } }
const checkingAvailability = ref(false)

async function checkAvailability() {
  if (!form.value.start_date || !form.value.end_date || cart.isEmpty) {
    availabilityMap.value = {}
    return
  }
  checkingAvailability.value = true
  try {
    const { data } = await axios.get(route('assets.available'), {
      params: { start: form.value.start_date, end: form.value.end_date },
    })
    const map = {}
    data.forEach(a => { map[a.id] = a.availability })
    availabilityMap.value = map
  } catch {
    // silently ignore
  } finally {
    checkingAvailability.value = false
  }
}

// Re-check whenever dates change
watch([() => form.value.start_date, () => form.value.end_date], () => {
  checkAvailability()
})

// Per-item availability helper
function itemAvailability(itemId) {
  return availabilityMap.value[itemId] ?? null
}

// Per-kit availability helper (worst status among kit assets)
function kitAvailabilityStatus(kit) {
  if (!datesSelected.value || checkingAvailability.value) return null
  const statuses = (kit.assets ?? []).map(a => availabilityMap.value[a.id]?.status).filter(Boolean)
  if (statuses.includes('unavailable')) return 'unavailable'
  if (statuses.includes('warning')) return 'warning'
  if (statuses.length > 0) return 'available'
  return null
}

const hasHardConflict = computed(() =>
  cart.items.some(i => itemAvailability(i.id)?.status === 'unavailable') ||
  cart.kitItems.some(k => kitAvailabilityStatus(k) === 'unavailable')
)
const hasWarning = computed(() =>
  cart.items.some(i => itemAvailability(i.id)?.status === 'warning') ||
  cart.kitItems.some(k => kitAvailabilityStatus(k) === 'warning')
)
const datesSelected = computed(() => !!form.value.start_date && !!form.value.end_date)

// datetime-local min: now (or +examAdvanceHours in exam mode)
const nowDtLocal = computed(() => {
  const now = new Date()
  if (props.examMode) now.setHours(now.getHours() + props.examAdvanceHours)
  now.setSeconds(0, 0)
  return now.toISOString().slice(0, 16)
})

const minEndDate = computed(() => {
  if (!form.value.start_date) return nowDtLocal.value
  const start = new Date(form.value.start_date)
  start.setMinutes(start.getMinutes() + 1)
  return start.toISOString().slice(0, 16)
})

function submit() {
  errors.value = {}

  if (!form.value.title.trim()) {
    errors.value.title = 'Le titre est obligatoire.'
    return
  }
  if (!form.value.start_date) {
    errors.value.start_date = 'La date de début est obligatoire.'
    return
  }
  if (!form.value.end_date) {
    errors.value.end_date = 'La date de fin est obligatoire.'
    return
  }
  if (cart.isEmpty) {
    errors.value.cart = 'Votre panier est vide.'
    return
  }
  if (hasHardConflict.value) {
    errors.value.asset_ids = 'Certains équipements sont indisponibles sur la période choisie. Retirez-les du panier ou changez les dates.'
    return
  }
  if (!requiredFormValid.value) {
    errors.value.form_responses = 'Veuillez compléter tous les champs obligatoires du formulaire.'
    return
  }

  loading.value = true
  router.post(route('reservations.store'), {
    title:          form.value.title,
    start_date:     form.value.start_date,
    end_date:       form.value.end_date,
    notes:          form.value.notes,
    asset_ids:      cart.items.map(i => i.id),
    kit_ids:        cart.kitItems.map(k => k.id),
    form_id:        form.value.form_id,
    form_responses: form.value.form_responses,
  }, {
    onSuccess: () => cart.clearCart(),
    onError:   (e) => { errors.value = e },
    onFinish:  () => { loading.value = false },
  })
}
</script>

<template>
  <AppLayout title="Mon panier">
    <div class="max-w-3xl mx-auto">

      <!-- En-tête -->
      <div class="flex items-center gap-3 mb-6">
        <ShoppingCartIcon class="w-7 h-7 text-blue-600" />
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Mon panier de réservation</h1>
          <p class="text-sm text-gray-500">
            <span v-if="cart.items.length > 0">{{ cart.items.length }} équipement(s)</span>
            <span v-if="cart.items.length > 0 && cart.kitItems.length > 0"> · </span>
            <span v-if="cart.kitItems.length > 0">{{ cart.kitItems.length }} kit(s)</span>
            <span v-if="cart.isEmpty">Panier vide</span>
          </p>
        </div>
      </div>

      <!-- Bannière mode examen -->
      <div v-if="examMode" class="mb-5 bg-orange-50 border border-orange-300 rounded-2xl p-4 flex items-start gap-3">
        <span class="text-2xl flex-shrink-0">🎓</span>
        <div>
          <p class="font-semibold text-orange-900">Mode examen activé</p>
          <ul class="mt-1 space-y-0.5 text-sm text-orange-700">
            <li>• La réservation doit être effectuée au moins <strong>{{ examAdvanceHours }}h</strong> à l'avance.</li>
            <li>• Les demandes sont traitées par l'administrateur entre <strong>{{ examProcessStart }}h</strong> et <strong>{{ examProcessEnd }}h</strong>.</li>
          </ul>
        </div>
      </div>

      <!-- Panier vide -->
      <div v-if="cart.isEmpty" class="bg-white rounded-2xl shadow-sm border p-12 text-center">
        <ShoppingCartIcon class="w-16 h-16 text-gray-200 mx-auto mb-4" />
        <p class="text-gray-500 text-lg font-medium mb-2">Votre panier est vide</p>
        <p class="text-gray-400 text-sm mb-6">Ajoutez des équipements ou des kits depuis la liste des équipements.</p>
        <Link :href="route('assets.index')"
              class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium transition">
          Parcourir les équipements
        </Link>
      </div>

      <!-- Contenu panier -->
      <div v-else class="space-y-5">

        <!-- Formulaire dates EN PREMIER pour vérification avant la liste -->
        <div class="bg-white rounded-2xl shadow-sm border p-6">
          <h2 class="font-semibold text-gray-900 mb-5 flex items-center gap-2">
            <CalendarDaysIcon class="w-5 h-5 text-blue-600" />
            Détails de la réservation
          </h2>

          <div class="space-y-4">
            <!-- Titre -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Titre / Motif <span class="text-red-500">*</span>
              </label>
              <input v-model="form.title" type="text"
                     placeholder="Ex: Projet photo BTS, Stage réseau…"
                     class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                     :class="{ 'border-red-400': errors.title }" />
              <p v-if="errors.title" class="text-red-500 text-xs mt-1">{{ errors.title }}</p>
            </div>

            <!-- Dates avec heure -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Date et heure de début <span class="text-red-500">*</span>
                </label>
                <input v-model="form.start_date" type="datetime-local" :min="nowDtLocal"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                       :class="{ 'border-red-400': errors.start_date }" />
                <p v-if="errors.start_date" class="text-red-500 text-xs mt-1">{{ errors.start_date }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Date et heure de retour <span class="text-red-500">*</span>
                </label>
                <input v-model="form.end_date" type="datetime-local" :min="minEndDate"
                       class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"
                       :class="{ 'border-red-400': errors.end_date }" />
                <p v-if="errors.end_date" class="text-red-500 text-xs mt-1">{{ errors.end_date }}</p>
              </div>
            </div>

            <!-- Notes -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Notes (facultatif)</label>
              <textarea v-model="form.notes" rows="3"
                        placeholder="Contexte, précisions pour l'administrateur…"
                        class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none resize-none" />
            </div>
          </div>
        </div>

        <!-- Indicateur de vérification -->
        <div v-if="checkingAvailability" class="flex items-center gap-2 text-sm text-blue-600 px-1">
          <div class="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
          Vérification de la disponibilité…
        </div>

        <!-- Avertissement global conflits pending -->
        <div v-if="datesSelected && !checkingAvailability && hasWarning && !hasHardConflict"
             class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
          <span class="text-amber-500 text-lg flex-shrink-0">⚠</span>
          <div>
            <p class="text-sm font-semibold text-amber-800">Demande(s) en attente sur cette période</p>
            <p class="text-xs text-amber-700 mt-0.5">
              Un ou plusieurs équipements ont déjà une demande non encore validée sur cette période.
              Votre réservation sera quand même acceptée, mais l'administrateur devra trancher en cas de conflit.
            </p>
          </div>
        </div>

        <!-- Erreur conflits hard -->
        <div v-if="datesSelected && !checkingAvailability && hasHardConflict"
             class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-start gap-3">
          <span class="text-red-500 text-lg flex-shrink-0">🚫</span>
          <div>
            <p class="text-sm font-semibold text-red-800">Équipements indisponibles sur cette période</p>
            <p class="text-xs text-red-700 mt-0.5">
              Certains équipements sont déjà réservés ou en cours d'utilisation. Retirez-les ou choisissez d'autres dates.
            </p>
          </div>
        </div>

        <!-- ── Liste des équipements individuels ── -->
        <div v-if="cart.items.length > 0" class="bg-white rounded-2xl shadow-sm border overflow-hidden">
          <div class="px-5 py-4 border-b bg-gray-50 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">
              Équipements individuels
              <span class="text-sm font-normal text-gray-500 ml-1">({{ cart.items.length }})</span>
            </h2>
            <p v-if="!datesSelected" class="text-xs text-gray-400">← Renseignez les dates pour voir la disponibilité</p>
          </div>
          <ul class="divide-y">
            <li v-for="item in cart.items" :key="item.id"
                class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 transition"
                :class="{ 'bg-red-50/50': itemAvailability(item.id)?.status === 'unavailable' }">
              <!-- Icône -->
              <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center flex-shrink-0">
                <img v-if="item.image" :src="item.image" class="w-10 h-10 rounded-xl object-cover" />
                <span v-else class="text-xl">📦</span>
              </div>
              <!-- Infos -->
              <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-900 truncate">{{ item.name }}</p>
                <p class="text-xs text-gray-500 mt-0.5">
                  <span class="font-mono">{{ item.asset_tag }}</span>
                  <span v-if="item.category" class="ml-2 text-gray-400">· {{ item.category }}</span>
                </p>
                <!-- Raison du conflit -->
                <p v-if="itemAvailability(item.id)?.reason"
                   class="text-xs mt-1"
                   :class="{
                     'text-red-600': itemAvailability(item.id)?.status === 'unavailable',
                     'text-amber-600': itemAvailability(item.id)?.status === 'warning',
                   }">
                  {{ itemAvailability(item.id)?.reason }}
                </p>
              </div>
              <!-- Badge disponibilité (quand dates saisies) -->
              <div v-if="datesSelected && !checkingAvailability && itemAvailability(item.id)" class="flex-shrink-0">
                <span v-if="itemAvailability(item.id)?.status === 'available'"
                      class="text-xs px-2.5 py-1 rounded-full font-semibold bg-green-100 text-green-700">
                  ✓ Disponible
                </span>
                <span v-else-if="itemAvailability(item.id)?.status === 'warning'"
                      class="text-xs px-2.5 py-1 rounded-full font-semibold bg-amber-100 text-amber-700">
                  ⚠ En attente
                </span>
                <span v-else
                      class="text-xs px-2.5 py-1 rounded-full font-semibold bg-red-100 text-red-700">
                  ✗ Indisponible
                </span>
              </div>
              <!-- Badge statut (sans dates) -->
              <div v-else-if="!datesSelected" class="flex-shrink-0">
                <span class="text-xs px-2.5 py-1 rounded-full font-medium"
                      :class="item.status === 'available' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'">
                  {{ item.status === 'available' ? 'Disponible' : item.status === 'checked_out' ? 'Sorti' : item.status }}
                </span>
              </div>
              <!-- Supprimer -->
              <button @click="cart.removeItem(item.id)"
                      class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition flex-shrink-0">
                <TrashIcon class="w-4 h-4" />
              </button>
            </li>
          </ul>
          <!-- Ajouter d'autres équipements -->
          <div class="px-5 py-3 border-t bg-gray-50">
            <Link :href="route('assets.index')"
                  class="text-sm text-blue-600 hover:text-blue-800 font-medium">
              + Ajouter des équipements
            </Link>
          </div>
        </div>

        <!-- ── Liste des kits ── -->
        <div v-if="cart.kitItems.length > 0" class="bg-white rounded-2xl shadow-sm border overflow-hidden">
          <div class="px-5 py-4 border-b bg-gray-50 flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">
              Kits
              <span class="text-sm font-normal text-gray-500 ml-1">({{ cart.kitItems.length }})</span>
            </h2>
            <p v-if="!datesSelected" class="text-xs text-gray-400">← Renseignez les dates pour voir la disponibilité</p>
          </div>
          <ul class="divide-y">
            <li v-for="kit in cart.kitItems" :key="kit.id"
                class="flex items-start gap-4 px-5 py-4 hover:bg-gray-50 transition"
                :class="{ 'bg-red-50/50': kitAvailabilityStatus(kit) === 'unavailable' }">
              <!-- Image/Icône -->
              <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                <img v-if="kit.image" :src="kit.image" class="w-10 h-10 rounded-xl object-cover" />
                <span v-else class="text-xl">📦</span>
              </div>
              <!-- Infos -->
              <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-900 truncate">{{ kit.name }}</p>
                <p class="text-xs text-gray-500 mt-0.5">
                  Kit · {{ kit.assets?.length ?? 0 }} équipement(s)
                </p>
                <!-- Composants du kit -->
                <div class="mt-1.5 flex flex-wrap gap-1">
                  <span v-for="asset in (kit.assets ?? []).slice(0, 4)" :key="asset.id"
                        class="text-xs bg-gray-100 text-gray-600 rounded px-1.5 py-0.5 truncate max-w-32">
                    {{ asset.name }}
                  </span>
                  <span v-if="(kit.assets?.length ?? 0) > 4"
                        class="text-xs text-gray-400 italic">
                    +{{ kit.assets.length - 4 }} de plus
                  </span>
                </div>
                <!-- Conflits -->
                <p v-if="kitAvailabilityStatus(kit) === 'unavailable'"
                   class="text-xs text-red-600 mt-1">
                  Un ou plusieurs équipements de ce kit sont indisponibles.
                </p>
                <p v-else-if="kitAvailabilityStatus(kit) === 'warning'"
                   class="text-xs text-amber-600 mt-1">
                  Un ou plusieurs équipements ont une réservation en attente sur cette période.
                </p>
              </div>
              <!-- Badge disponibilité (quand dates saisies) -->
              <div v-if="datesSelected && !checkingAvailability && kitAvailabilityStatus(kit)" class="flex-shrink-0">
                <span v-if="kitAvailabilityStatus(kit) === 'available'"
                      class="text-xs px-2.5 py-1 rounded-full font-semibold bg-green-100 text-green-700">
                  ✓ Disponible
                </span>
                <span v-else-if="kitAvailabilityStatus(kit) === 'warning'"
                      class="text-xs px-2.5 py-1 rounded-full font-semibold bg-amber-100 text-amber-700">
                  ⚠ En attente
                </span>
                <span v-else-if="kitAvailabilityStatus(kit) === 'unavailable'"
                      class="text-xs px-2.5 py-1 rounded-full font-semibold bg-red-100 text-red-700">
                  ✗ Indisponible
                </span>
              </div>
              <!-- Badge statut du kit (sans dates) -->
              <div v-else-if="!datesSelected" class="flex-shrink-0">
                <span class="text-xs px-2.5 py-1 rounded-full font-medium"
                      :class="kit.status === 'available'   ? 'bg-green-100 text-green-700' :
                              kit.status === 'partial'     ? 'bg-yellow-100 text-yellow-700' :
                                                             'bg-red-100 text-red-700'">
                  {{ kit.status_label }}
                </span>
              </div>
              <!-- Supprimer -->
              <button @click="cart.removeKit(kit.id)"
                      class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition flex-shrink-0">
                <TrashIcon class="w-4 h-4" />
              </button>
            </li>
          </ul>
          <!-- Ajouter des kits -->
          <div class="px-5 py-3 border-t bg-gray-50">
            <Link :href="route('assets.index')"
                  class="text-sm text-blue-600 hover:text-blue-800 font-medium">
              + Ajouter des kits
            </Link>
          </div>
        </div>

        <p v-if="errors.cart" class="text-red-500 text-sm px-1">{{ errors.cart }}</p>
        <p v-if="errors.asset_ids" class="text-red-500 text-sm px-1">{{ errors.asset_ids }}</p>
        <p v-if="errors.kit_ids" class="text-red-500 text-sm px-1">{{ errors.kit_ids }}</p>

        <!-- ── Formulaire obligatoire ── -->
        <div v-if="requiredForm" class="bg-white rounded-2xl shadow-sm border overflow-hidden border-l-4 border-l-amber-400">
          <div class="px-5 py-4 border-b flex items-center gap-3">
            <span class="text-lg">📋</span>
            <div>
              <h2 class="font-semibold text-gray-900">{{ requiredForm.name }}</h2>
              <p class="text-xs text-amber-600 font-medium">Formulaire obligatoire — doit être rempli avant l'envoi</p>
            </div>
          </div>

          <div class="p-5 space-y-4">
            <div v-for="field in requiredForm.fields" :key="field.id">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                {{ field.label }}
                <span v-if="field.required" class="text-red-500 ml-0.5">*</span>
              </label>

              <!-- Signature -->
              <template v-if="field.type === 'signature'">
                <div class="grid grid-cols-2 gap-3">
                  <input type="text" placeholder="Prénom"
                    :value="getOrInitResponse(field.id, 'signature')?.first_name ?? ''"
                    @input="form.form_responses[field.id] = { ...form.form_responses[field.id], first_name: $event.target.value }"
                    class="px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
                  <input type="text" placeholder="Nom"
                    :value="getOrInitResponse(field.id, 'signature')?.last_name ?? ''"
                    @input="form.form_responses[field.id] = { ...form.form_responses[field.id], last_name: $event.target.value }"
                    class="px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
                </div>
              </template>

              <!-- Textarea -->
              <template v-else-if="field.type === 'textarea'">
                <textarea v-model="form.form_responses[field.id]" rows="3" :placeholder="field.placeholder ?? ''"
                  class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
              </template>

              <!-- Select -->
              <template v-else-if="field.type === 'select'">
                <select v-model="form.form_responses[field.id]"
                  class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                  <option value="">— Sélectionner —</option>
                  <option v-for="opt in (field.options ?? [])" :key="opt" :value="opt">{{ opt }}</option>
                </select>
              </template>

              <!-- Checkbox -->
              <template v-else-if="field.type === 'checkbox'">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" v-model="form.form_responses[field.id]" class="w-4 h-4 text-blue-600 rounded" />
                  <span class="text-sm text-gray-600">{{ field.placeholder ?? 'Cocher pour confirmer' }}</span>
                </label>
              </template>

              <!-- Date -->
              <template v-else-if="field.type === 'date'">
                <input type="date" v-model="form.form_responses[field.id]"
                  class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </template>

              <!-- Default: text / email / phone / number -->
              <template v-else>
                <input :type="field.type" v-model="form.form_responses[field.id]"
                  :placeholder="field.placeholder ?? ''"
                  class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </template>
            </div>

            <!-- Alerte si formulaire incomplet -->
            <p v-if="!requiredFormValid && !cart.isEmpty"
              class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
              ⚠️ Veuillez compléter tous les champs obligatoires du formulaire.
            </p>
            <p v-if="errors.form_responses" class="text-xs text-red-600">{{ errors.form_responses }}</p>
          </div>
        </div>

        <!-- Récapitulatif + bouton -->
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-5 flex items-center justify-between gap-4">
          <div>
            <p class="font-semibold text-blue-900">
              <span v-if="cart.items.length > 0">{{ cart.items.length }} équipement(s)</span>
              <span v-if="cart.items.length > 0 && cart.kitItems.length > 0"> + </span>
              <span v-if="cart.kitItems.length > 0">{{ cart.kitItems.length }} kit(s)</span>
              · Réservation en attente de validation
            </p>
            <p class="text-sm text-blue-700 mt-0.5">
              Un administrateur examinera votre demande et vous notifiera par email.
            </p>
          </div>
          <button @click="submit" :disabled="loading || cart.isEmpty || hasHardConflict || !requiredFormValid"
                  class="flex-shrink-0 font-semibold px-6 py-3 rounded-xl transition text-sm whitespace-nowrap disabled:opacity-50"
                  :class="(hasHardConflict || !requiredFormValid)
                    ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                    : 'bg-blue-600 hover:bg-blue-700 text-white'">
            {{ loading ? 'Envoi…' : 'Confirmer la réservation' }}
          </button>
        </div>

      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.animate-spin {
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
