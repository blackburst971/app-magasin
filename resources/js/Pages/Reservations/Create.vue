<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import AssetThumbnail from '@/Components/AssetThumbnail.vue'
import { useForm, Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  requiredForm:           Object,
  examMode:               Boolean,
  examAdvanceHours:       { type: Number, default: 24 },
  examProcessStart:       { type: Number, default: 7 },
  examProcessEnd:         { type: Number, default: 9 },
  userSupervisors:        { type: Array, default: () => [] },
  principalSupervisorId:  { type: Number, default: null },
})

function goBack() {
  if (window.history.length > 1) window.history.back()
  else router.visit(route('reservations.index'))
}

const form = useForm({
  title: '',
  start_date: '',
  end_date: '',
  asset_ids: [],
  notes: '',
  form_id: props.requiredForm?.id ?? null,
  form_responses: {},
  // Présélectionner le superviseur principal par défaut
  supervisor_chosen_id: props.principalSupervisorId,
})

// ─── Équipements disponibles ──────────────────────────────────────────────────
const availableAssets = ref([])
const loadingAssets = ref(false)
const assetSearch = ref('')

const loadAvailableAssets = async () => {
  if (!form.start_date || !form.end_date) return
  loadingAssets.value = true
  try {
    const { data } = await axios.get(route('assets.available'), {
      params: { start: form.start_date, end: form.end_date }
    })
    availableAssets.value = data
  } finally {
    loadingAssets.value = false
  }
}

const toggleAsset = (assetId) => {
  const idx = form.asset_ids.indexOf(assetId)
  if (idx === -1) form.asset_ids.push(assetId)
  else form.asset_ids.splice(idx, 1)
}

// Séparer disponibles (+ warnings) et indisponibles (conflit de dates)
const selectableAssets = computed(() =>
  availableAssets.value.filter(a => a.availability?.status !== 'unavailable')
)

const unavailableAssets = computed(() =>
  availableAssets.value.filter(a => a.availability?.status === 'unavailable')
)

const filteredAssets = computed(() => {
  const pool = selectableAssets.value
  if (!assetSearch.value) return pool
  const q = assetSearch.value.toLowerCase()
  return pool.filter(a =>
    a.name.toLowerCase().includes(q) || a.asset_tag.toLowerCase().includes(q) || (a.category ?? '').toLowerCase().includes(q)
  )
})

// ─── Formulaire de sortie inline ─────────────────────────────────────────────
function getOrInitResponse(fieldId, type) {
  if (type === 'signature' && !form.form_responses[fieldId]) {
    form.form_responses[fieldId] = { first_name: '', last_name: '' }
  }
  return form.form_responses[fieldId]
}

const requiredFormValid = computed(() => {
  if (!props.requiredForm) return true
  for (const field of props.requiredForm.fields) {
    if (!field.required) continue
    const val = form.form_responses[field.id]
    if (field.type === 'signature') {
      if (!val?.first_name?.trim() || !val?.last_name?.trim()) return false
    } else {
      if (!val || String(val).trim() === '') return false
    }
  }
  return true
})

const canSubmit = computed(() =>
  form.asset_ids.length > 0 && requiredFormValid.value
)

// ─── Mode examen ─────────────────────────────────────────────────────────────
const examMinDate = computed(() => {
  if (!props.examMode) return null
  const d = new Date()
  d.setHours(d.getHours() + props.examAdvanceHours)
  d.setSeconds(0, 0)
  return d.toISOString().slice(0, 16)
})

const submit = () => form.post(route('reservations.store'))
</script>

<template>
  <AppLayout>
    <Head title="Nouvelle réservation" />
    <div class="max-w-2xl">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">📅 Nouvelle réservation</h1>

      <form @submit.prevent="submit" class="space-y-6">

        <!-- ── Bannière Mode examen ── -->
        <div v-if="examMode"
          class="bg-orange-50 border border-orange-300 rounded-xl p-4 flex items-start gap-3">
          <span class="text-2xl flex-shrink-0">🎓</span>
          <div>
            <p class="font-semibold text-orange-900">Mode examen activé</p>
            <ul class="mt-1 space-y-0.5 text-sm text-orange-700">
              <li>• La réservation doit être effectuée au moins <strong>{{ examAdvanceHours }}h</strong> à l'avance.</li>
              <li>• Les demandes sont traitées par l'administrateur entre <strong>{{ examProcessStart }}h</strong> et <strong>{{ examProcessEnd }}h</strong>.</li>
            </ul>
          </div>
        </div>

        <!-- ── Informations générales ── -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="font-semibold mb-4">Informations</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Titre / Motif *</label>
              <input v-model="form.title" required placeholder="Ex: Présentation cours, Projet BTS..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date de début *</label>
                <input v-model="form.start_date" @change="loadAvailableAssets" type="datetime-local" required
                  :min="examMinDate ?? undefined"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date de fin *</label>
                <input v-model="form.end_date" @change="loadAvailableAssets" type="datetime-local" required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optionnel)</label>
              <textarea v-model="form.notes" rows="2" placeholder="Informations supplémentaires..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
            </div>

            <!-- ── Sélection du superviseur si plusieurs disponibles ── -->
            <div v-if="userSupervisors.length >= 2">
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Responsable pour validation *
                <span class="text-xs text-gray-500 font-normal ml-1">
                  {{ principalSupervisorId ? '(Votre responsable principal est présélectionné par défaut)' : '(Choisissez le responsable qui validera votre demande)' }}
                </span>
              </label>
              <select v-model="form.supervisor_chosen_id" required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                <option :value="null" disabled>-- Sélectionnez un responsable --</option>
                <option v-for="supervisor in userSupervisors" :key="supervisor.id" :value="supervisor.id">
                  {{ supervisor.name }}{{ supervisor.id === principalSupervisorId ? ' (principal)' : '' }}
                </option>
              </select>
              <p v-if="form.errors.supervisor_chosen_id" class="mt-1 text-xs text-red-600">{{ form.errors.supervisor_chosen_id }}</p>
            </div>
          </div>
        </div>

        <!-- ── Sélection équipements ── -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="font-semibold mb-1">Équipements *</h2>
          <p v-if="!form.start_date || !form.end_date" class="text-sm text-gray-400 mb-4">Sélectionnez d'abord les dates pour voir les équipements disponibles.</p>
          <div v-else>
            <p class="text-sm text-gray-500 mb-3">
              {{ selectableAssets.length }} équipement(s) disponible(s)
              <span v-if="unavailableAssets.length" class="text-red-500 ml-1">· {{ unavailableAssets.length }} indisponible(s)</span>
            </p>
            <input v-model="assetSearch" type="search" placeholder="Rechercher un équipement..."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none mb-3" />
            <div v-if="loadingAssets" class="text-center py-4 text-gray-400">Chargement...</div>
            <div v-else class="space-y-2 max-h-64 overflow-y-auto">
              <!-- Équipements disponibles (ou avec warning) -->
              <label v-for="asset in filteredAssets" :key="asset.id"
                :class="['flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition',
                  form.asset_ids.includes(asset.id) ? 'border-blue-500 bg-blue-50' :
                  asset.availability?.status === 'warning' ? 'border-amber-200 hover:bg-amber-50' :
                  'border-gray-200 hover:bg-gray-50']">
                <input type="checkbox" :checked="form.asset_ids.includes(asset.id)" @change="toggleAsset(asset.id)" class="w-4 h-4 text-blue-600 rounded flex-shrink-0" />
                <AssetThumbnail :src="asset.image" :alt="asset.name" size="sm" />
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium truncate">{{ asset.name }}</p>
                  <p class="text-xs text-gray-500">{{ asset.asset_tag }}{{ asset.category ? ' • ' + asset.category : '' }}</p>
                  <!-- Badge warning (retard de retour, résa en attente…) -->
                  <p v-if="asset.availability?.status === 'warning'"
                     class="text-xs text-amber-700 font-medium mt-0.5">
                    ⚠️ {{ asset.availability.reason }}
                  </p>
                </div>
              </label>
              <p v-if="filteredAssets.length === 0" class="text-center py-4 text-gray-400 text-sm">Aucun équipement disponible sur cette période</p>

              <!-- Équipements indisponibles (conflit de dates) — affichés mais non sélectionnables -->
              <template v-if="unavailableAssets.length && !assetSearch">
                <div class="pt-2 pb-1">
                  <p class="text-xs font-semibold text-red-500 uppercase tracking-wider">Indisponibles sur cette période</p>
                </div>
                <div v-for="asset in unavailableAssets" :key="'unavail-'+asset.id"
                     class="flex items-center gap-3 p-3 border border-red-100 bg-red-50 rounded-lg opacity-60 cursor-not-allowed">
                  <div class="w-4 h-4 rounded border border-gray-300 bg-gray-100 flex-shrink-0"></div>
                  <AssetThumbnail :src="asset.image" :alt="asset.name" size="sm" />
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-500 truncate">{{ asset.name }}</p>
                    <p class="text-xs text-red-500">{{ asset.availability?.reason }}</p>
                  </div>
                </div>
              </template>
            </div>
          </div>
          <p v-if="form.errors.asset_ids" class="mt-2 text-xs text-red-600">{{ form.errors.asset_ids }}</p>
          <p v-if="form.asset_ids.length > 0" class="mt-3 text-sm text-blue-600 font-medium">{{ form.asset_ids.length }} équipement(s) sélectionné(s)</p>
        </div>

        <!-- ── Formulaire de sortie obligatoire ── -->
        <div v-if="requiredForm" class="bg-white rounded-xl shadow-sm border p-6 border-l-4 border-l-amber-400">
          <div class="flex items-center gap-2 mb-4">
            <span class="text-lg">📋</span>
            <div>
              <h2 class="font-semibold">{{ requiredForm.name }}</h2>
              <p class="text-xs text-amber-600 font-medium">Formulaire obligatoire — doit être rempli avant l'envoi</p>
            </div>
          </div>

          <div class="space-y-4">
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
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
                  <input type="text" placeholder="Nom"
                    :value="getOrInitResponse(field.id, 'signature')?.last_name ?? ''"
                    @input="form.form_responses[field.id] = { ...form.form_responses[field.id], last_name: $event.target.value }"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
                </div>
              </template>

              <!-- Textarea -->
              <template v-else-if="field.type === 'textarea'">
                <textarea v-model="form.form_responses[field.id]" rows="3" :placeholder="field.placeholder ?? ''"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
              </template>

              <!-- Select -->
              <template v-else-if="field.type === 'select'">
                <select v-model="form.form_responses[field.id]"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                  <option value="">— Sélectionner —</option>
                  <option v-for="opt in (field.options ?? [])" :key="opt" :value="opt">{{ opt }}</option>
                </select>
              </template>

              <!-- Checkbox -->
              <template v-else-if="field.type === 'checkbox'">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" v-model="form.form_responses[field.id]"
                    class="w-4 h-4 text-blue-600 rounded" />
                  <span class="text-sm text-gray-600">{{ field.placeholder ?? 'Cocher pour confirmer' }}</span>
                </label>
              </template>

              <!-- Date -->
              <template v-else-if="field.type === 'date'">
                <input type="date" v-model="form.form_responses[field.id]"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </template>

              <!-- Default: text / email / phone / number -->
              <template v-else>
                <input :type="field.type" v-model="form.form_responses[field.id]"
                  :placeholder="field.placeholder ?? ''"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </template>
            </div>
          </div>

          <!-- Alerte si formulaire incomplet -->
          <p v-if="!requiredFormValid && form.asset_ids.length > 0"
            class="mt-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            ⚠️ Veuillez compléter tous les champs obligatoires du formulaire.
          </p>
          <p v-if="form.errors.form_responses" class="mt-2 text-xs text-red-600">{{ form.errors.form_responses }}</p>
        </div>

        <!-- ── Actions ── -->
        <div class="flex gap-3">
          <button type="button" @click="goBack" class="flex-1 border border-gray-300 py-2.5 rounded-lg text-sm text-center hover:bg-gray-50">Annuler</button>
          <button type="submit" :disabled="form.processing || !canSubmit"
            class="flex-1 bg-blue-600 text-white py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ form.processing ? 'Envoi...' : 'Soumettre la demande' }}
          </button>
        </div>

      </form>
    </div>
  </AppLayout>
</template>
