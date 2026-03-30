<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  events: { type: Array, default: () => [] },
  users:  { type: Array, default: () => [] },
})

const showCreate = ref(false)
const form = ref({
  title:               '',
  description:         '',
  location:            '',
  color:               '#6366f1',
  start_date:          '',
  end_date:            '',
  responsible_user_id: null,
})
const creating = ref(false)
const errors   = ref({})

function submitCreate() {
  creating.value = true
  errors.value   = {}
  router.post(route('events.store'), form.value, {
    onSuccess: () => { showCreate.value = false; resetForm() },
    onError:   (e) => { errors.value = e },
    onFinish:  () => { creating.value = false },
  })
}

function resetForm() {
  form.value = { title: '', description: '', location: '', color: '#6366f1', start_date: '', end_date: '', responsible_user_id: null }
}

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'
}
</script>

<template>
  <AppLayout title="Événements">
    <div class="max-w-5xl">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">🎯 Événements</h1>
          <p class="text-sm text-gray-500 mt-0.5">Manifestations, projets et événements du lycée</p>
        </div>
        <button @click="showCreate = !showCreate"
          class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition">
          + Nouvel événement
        </button>
      </div>

      <!-- Formulaire création -->
      <div v-if="showCreate" class="bg-white rounded-xl shadow-sm border border-indigo-200 p-6 mb-6">
        <h2 class="font-semibold text-gray-900 mb-4">Créer un événement</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-700 mb-1">Titre *</label>
            <input v-model="form.title" type="text" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
            <p v-if="errors.title" class="text-red-500 text-xs mt-1">{{ errors.title }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Date de début *</label>
            <input v-model="form.start_date" type="datetime-local" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
            <p v-if="errors.start_date" class="text-red-500 text-xs mt-1">{{ errors.start_date }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Date de fin *</label>
            <input v-model="form.end_date" type="datetime-local" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
            <p v-if="errors.end_date" class="text-red-500 text-xs mt-1">{{ errors.end_date }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Lieu</label>
            <input v-model="form.location" type="text" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Responsable (optionnel)</label>
            <select v-model="form.responsible_user_id" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
              <option :value="null">— Aucun —</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="form.description" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 outline-none resize-none" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Couleur calendrier</label>
            <div class="flex items-center gap-2">
              <input v-model="form.color" type="color" class="w-10 h-9 rounded border cursor-pointer" />
              <span class="text-xs text-gray-500 font-mono">{{ form.color }}</span>
            </div>
          </div>
        </div>
        <div class="flex gap-3 mt-4">
          <button @click="submitCreate" :disabled="creating"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50">
            {{ creating ? 'Création…' : 'Créer l\'événement' }}
          </button>
          <button @click="showCreate = false; resetForm()" class="border border-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-50 transition">
            Annuler
          </button>
        </div>
      </div>

      <!-- Liste des événements -->
      <div v-if="events.length === 0" class="bg-white rounded-xl shadow-sm border p-12 text-center text-gray-400">
        <p class="text-4xl mb-3">🎯</p>
        <p class="font-medium text-gray-600">Aucun événement pour le moment</p>
        <p class="text-sm mt-1">Créez votre premier événement pour commencer à réserver du matériel.</p>
      </div>

      <div class="space-y-4">
        <div v-for="event in events" :key="event.id"
          class="bg-white rounded-xl shadow-sm border overflow-hidden hover:shadow-md transition cursor-pointer"
          @click="router.visit(route('events.show', event.id))">
          <div class="flex items-start gap-4 p-5">
            <!-- Couleur indicateur -->
            <div class="w-3 self-stretch rounded-full flex-shrink-0" :style="{ backgroundColor: event.color }" />
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between gap-3 flex-wrap">
                <h3 class="font-semibold text-gray-900 text-lg">{{ event.title }}</h3>
                <div class="flex items-center gap-2 text-xs">
                  <span v-if="event.reservations?.length"
                    class="bg-indigo-100 text-indigo-700 px-2.5 py-1 rounded-full font-medium">
                    {{ event.reservations.length }} réservation(s)
                  </span>
                </div>
              </div>
              <div class="flex items-center gap-4 text-xs text-gray-500 mt-1 flex-wrap">
                <span>📅 {{ event.start_date_label }} → {{ event.end_date_label }}</span>
                <span v-if="event.location">📍 {{ event.location }}</span>
                <span v-if="event.responsible_user">👤 {{ event.responsible_user.name }}</span>
              </div>
              <p v-if="event.description" class="text-sm text-gray-600 mt-2 line-clamp-2">{{ event.description }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
