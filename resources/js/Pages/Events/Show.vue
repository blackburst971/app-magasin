<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'

const props = defineProps({
  event: Object,
  users: { type: Array, default: () => [] },
})

// ── Édition ──────────────────────────────────────────────────────────────────
const editing = ref(false)
const editForm = ref({ ...props.event })
const saving   = ref(false)
const editErrors = ref({})

function submitEdit() {
  saving.value = true
  editErrors.value = {}
  router.put(route('events.update', props.event.id), editForm.value, {
    onSuccess: () => { editing.value = false },
    onError:   (e) => { editErrors.value = e },
    onFinish:  () => { saving.value = false },
    preserveScroll: true,
  })
}

// ── Créer réservation pour l'événement ────────────────────────────────────────
const showResvForm = ref(false)
const resvForm = ref({ title: props.event.title, user_id: props.event.responsible_user?.id ?? null })
const creatingResv = ref(false)

function submitResv() {
  creatingResv.value = true
  router.post(route('events.reservations.create', props.event.id), resvForm.value, {
    onSuccess: () => { showResvForm.value = false },
    onFinish:  () => { creatingResv.value = false },
    preserveScroll: true,
  })
}

function deleteEvent() {
  if (!confirm('Supprimer définitivement cet événement ?')) return
  router.delete(route('events.destroy', props.event.id))
}
</script>

<template>
  <AppLayout :title="event.title">
    <div class="max-w-4xl">
      <!-- En-tête -->
      <div class="flex items-center gap-3 mb-6">
        <button @click="router.visit(route('events.index'))" class="text-gray-400 hover:text-gray-600">←</button>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-3">
            <div class="w-4 h-4 rounded-full flex-shrink-0" :style="{ backgroundColor: event.color }" />
            <h1 class="text-2xl font-bold text-gray-900 truncate">{{ event.title }}</h1>
          </div>
          <div class="flex items-center gap-4 text-xs text-gray-500 mt-1 flex-wrap">
            <span>📅 {{ event.start_date_label }} → {{ event.end_date_label }}</span>
            <span v-if="event.location">📍 {{ event.location }}</span>
            <span v-if="event.responsible_user">👤 {{ event.responsible_user.name }}</span>
          </div>
        </div>
        <div class="flex gap-2">
          <button @click="editing = !editing"
            class="border border-gray-300 text-gray-600 px-3 py-1.5 rounded-lg text-sm hover:bg-gray-50 transition">
            ✏️ Modifier
          </button>
          <button @click="deleteEvent"
            class="border border-red-200 text-red-600 px-3 py-1.5 rounded-lg text-sm hover:bg-red-50 transition">
            Supprimer
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 space-y-6">

          <!-- Formulaire d'édition -->
          <div v-if="editing" class="bg-white rounded-xl shadow-sm border border-indigo-200 p-6">
            <h2 class="font-semibold text-gray-900 mb-4">Modifier l'événement</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Titre *</label>
                <input v-model="editForm.title" type="text" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Date de début *</label>
                <input v-model="editForm.start_date" type="datetime-local" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Date de fin *</label>
                <input v-model="editForm.end_date" type="datetime-local" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Lieu</label>
                <input v-model="editForm.location" type="text" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Responsable</label>
                <select v-model="editForm.responsible_user_id" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                  <option :value="null">— Aucun —</option>
                  <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
              <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                <textarea v-model="editForm.description" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none resize-none" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Couleur</label>
                <div class="flex items-center gap-2">
                  <input v-model="editForm.color" type="color" class="w-10 h-9 rounded border cursor-pointer" />
                  <span class="text-xs text-gray-500 font-mono">{{ editForm.color }}</span>
                </div>
              </div>
            </div>
            <div class="flex gap-3 mt-4">
              <button @click="submitEdit" :disabled="saving"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50">
                {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
              </button>
              <button @click="editing = false" class="border border-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
            </div>
          </div>

          <!-- Description -->
          <div v-if="event.description && !editing" class="bg-white rounded-xl shadow-sm border p-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Description</h2>
            <p class="text-gray-700 text-sm whitespace-pre-line">{{ event.description }}</p>
          </div>

          <!-- Réservations liées -->
          <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">
                Réservations de matériel
                <span v-if="event.reservations?.length" class="text-indigo-600 ml-1">({{ event.reservations.length }})</span>
              </h2>
              <button @click="showResvForm = !showResvForm"
                class="text-xs bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 px-3 py-1.5 rounded-lg font-medium transition">
                + Créer une réservation
              </button>
            </div>

            <!-- Form création réservation -->
            <div v-if="showResvForm" class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 mb-4 space-y-3">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Titre de la réservation</label>
                <input v-model="resvForm.title" type="text" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Utilisateur responsable (optionnel)</label>
                <select v-model="resvForm.user_id" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                  <option :value="null">— Moi (admin) —</option>
                  <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
              <div class="flex gap-2">
                <button @click="submitResv" :disabled="creatingResv"
                  class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1.5 rounded-lg text-sm font-medium transition disabled:opacity-50">
                  {{ creatingResv ? 'Création…' : 'Créer' }}
                </button>
                <button @click="showResvForm = false" class="border border-gray-300 text-gray-600 px-3 py-1.5 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
              </div>
              <p class="text-xs text-gray-500">
                La réservation sera automatiquement approuvée. Vous pourrez ensuite y ajouter des équipements depuis la page <Link :href="route('attribution.index')" class="text-indigo-600 underline">Attribution</Link>.
              </p>
            </div>

            <div v-if="!event.reservations?.length" class="text-center text-gray-400 text-sm py-6">
              Aucune réservation de matériel pour cet événement.
            </div>
            <div v-else class="space-y-2">
              <Link v-for="resv in event.reservations" :key="resv.id"
                :href="route('reservations.show', resv.id)"
                class="flex items-center justify-between px-4 py-3 rounded-xl border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50/30 transition">
                <div>
                  <p class="font-medium text-gray-900 text-sm">{{ resv.title }}</p>
                  <p class="text-xs text-gray-500">{{ resv.user_name ?? 'Admin' }} · {{ resv.assets_count }} équipement(s)</p>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                    :class="resv.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'">
                    {{ resv.status === 'active' ? '✅ En cours' : '📋 Approuvée' }}
                  </span>
                  <span class="text-xs text-gray-400">{{ resv.checked_out }}/{{ resv.assets_count }} sortis</span>
                </div>
              </Link>
            </div>
          </div>
        </div>

        <!-- Panneau latéral -->
        <div class="space-y-4">
          <div class="bg-white rounded-xl shadow-sm border p-5">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Infos</h2>
            <dl class="space-y-3 text-sm">
              <div>
                <dt class="text-xs text-gray-400">Début</dt>
                <dd class="font-medium">{{ event.start_date_label }}</dd>
              </div>
              <div>
                <dt class="text-xs text-gray-400">Fin</dt>
                <dd class="font-medium">{{ event.end_date_label }}</dd>
              </div>
              <div v-if="event.location">
                <dt class="text-xs text-gray-400">Lieu</dt>
                <dd class="font-medium">{{ event.location }}</dd>
              </div>
              <div v-if="event.responsible_user">
                <dt class="text-xs text-gray-400">Responsable</dt>
                <dd class="font-medium">{{ event.responsible_user.name }}</dd>
              </div>
              <div v-if="event.created_by">
                <dt class="text-xs text-gray-400">Créé par</dt>
                <dd class="text-gray-600">{{ event.created_by }}</dd>
              </div>
            </dl>
          </div>

          <div class="bg-white rounded-xl shadow-sm border p-5">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Calendrier</h2>
            <Link :href="route('calendar.index')" class="text-xs text-indigo-600 hover:underline">
              Voir dans le calendrier →
            </Link>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
