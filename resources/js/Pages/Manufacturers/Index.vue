<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { PlusIcon, PencilIcon, TrashIcon, GlobeAltIcon, PhotoIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  manufacturers: Array,
})

// ─── Création ────────────────────────────────────────────────────────────────
const showCreateModal = ref(false)
const createForm = useForm({ name: '', description: '', website: '', image: null })
const createImagePreview = ref(null)

function onCreateImage(e) {
  const file = e.target.files[0]
  if (!file) return
  createForm.image = file
  createImagePreview.value = URL.createObjectURL(file)
}

function submitCreate() {
  createForm.post(route('manufacturers.store'), {
    forceFormData: true,
    onSuccess: () => { showCreateModal.value = false; createForm.reset(); createImagePreview.value = null },
  })
}

// ─── Édition ─────────────────────────────────────────────────────────────────
const showEditModal = ref(false)
const editTarget = ref(null)
const editForm = useForm({ name: '', description: '', website: '', image: null })
const editImagePreview = ref(null)

function openEdit(m) {
  editTarget.value = m
  editForm.name        = m.name
  editForm.description = m.description ?? ''
  editForm.website     = m.website ?? ''
  editForm.image       = null
  editImagePreview.value = m.image_url ?? null
  showEditModal.value  = true
}

function onEditImage(e) {
  const file = e.target.files[0]
  if (!file) return
  editForm.image = file
  editImagePreview.value = URL.createObjectURL(file)
}

function submitEdit() {
  editForm.put(route('manufacturers.update', editTarget.value.id), {
    forceFormData: true,
    onSuccess: () => { showEditModal.value = false },
  })
}

// ─── Suppression ─────────────────────────────────────────────────────────────
const deleteForm = useForm({})
function deleteManufacturer(m) {
  if (!confirm(`Supprimer « ${m.name} » ?`)) return
  deleteForm.delete(route('manufacturers.destroy', m.id))
}
</script>

<template>
  <AppLayout>
    <div class="max-w-5xl">

      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Fabricants</h1>
          <p class="text-sm text-gray-500 mt-0.5">{{ manufacturers.length }} fabricant(s) enregistré(s)</p>
        </div>
        <button @click="showCreateModal = true"
          class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition shadow-sm">
          <PlusIcon class="w-4 h-4" />
          Nouveau fabricant
        </button>
      </div>

      <!-- Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-if="manufacturers.length === 0" class="col-span-full text-center py-16 text-gray-400">
          Aucun fabricant enregistré.
        </div>
        <div v-for="m in manufacturers" :key="m.id"
          class="bg-white rounded-xl shadow-sm border hover:shadow-md transition flex flex-col">
          <!-- Image -->
          <div class="h-32 bg-gray-100 rounded-t-xl overflow-hidden flex items-center justify-center">
            <img v-if="m.image_url" :src="m.image_url" :alt="m.name"
              class="h-full w-full object-contain p-2" />
            <PhotoIcon v-else class="w-10 h-10 text-gray-300" />
          </div>
          <!-- Body -->
          <div class="p-4 flex-1 flex flex-col gap-1">
            <p class="font-semibold text-gray-900">{{ m.name }}</p>
            <p v-if="m.description" class="text-xs text-gray-500 line-clamp-2">{{ m.description }}</p>
            <a v-if="m.website" :href="m.website" target="_blank" rel="noopener"
              class="flex items-center gap-1 text-blue-500 hover:underline text-xs mt-1">
              <GlobeAltIcon class="w-3.5 h-3.5" />{{ m.website.replace(/^https?:\/\//, '').slice(0, 30) }}
            </a>
            <div class="flex gap-2 mt-2">
              <span class="bg-purple-100 text-purple-700 text-xs font-semibold px-2 py-0.5 rounded-full">
                {{ m.equipment_models_count ?? 0 }} modèle(s)
              </span>
              <span class="bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded-full">
                {{ m.assets_count ?? 0 }} équipement(s)
              </span>
            </div>
          </div>
          <!-- Actions -->
          <div class="flex items-center gap-2 px-4 pb-3">
            <button @click="openEdit(m)"
              class="flex items-center gap-1.5 text-xs text-gray-600 hover:text-blue-600 border border-gray-200 hover:border-blue-300 px-3 py-1.5 rounded-lg transition">
              <PencilIcon class="w-3.5 h-3.5" /> Modifier
            </button>
            <button @click="deleteManufacturer(m)"
              class="flex items-center gap-1.5 text-xs text-gray-400 hover:text-red-600 border border-gray-200 hover:border-red-300 px-3 py-1.5 rounded-lg transition ml-auto">
              <TrashIcon class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- ═══ Modal Créer ═══ -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">Nouveau fabricant</h3>
          <button @click="showCreateModal=false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <div class="space-y-4">
          <!-- Image upload -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Logo / Photo</label>
            <div class="flex items-center gap-3">
              <div class="w-16 h-16 rounded-lg bg-gray-100 border flex items-center justify-center overflow-hidden">
                <img v-if="createImagePreview" :src="createImagePreview" class="w-full h-full object-contain p-1" />
                <PhotoIcon v-else class="w-7 h-7 text-gray-300" />
              </div>
              <label class="cursor-pointer text-sm text-blue-600 hover:underline">
                Choisir une image
                <input type="file" accept="image/*" class="hidden" @change="onCreateImage" />
              </label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
            <input v-model="createForm.name" type="text" placeholder="Apple, Dell, HP…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            <p v-if="createForm.errors.name" class="text-red-500 text-xs mt-1">{{ createForm.errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="createForm.description" rows="2"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Site web</label>
            <input v-model="createForm.website" type="url" placeholder="https://…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="showCreateModal=false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitCreate" :disabled="createForm.processing || !createForm.name.trim()"
            class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ createForm.processing ? 'Création…' : 'Créer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══ Modal Éditer ═══ -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">Modifier « {{ editTarget?.name }} »</h3>
          <button @click="showEditModal=false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <div class="space-y-4">
          <!-- Image upload -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Logo / Photo</label>
            <div class="flex items-center gap-3">
              <div class="w-16 h-16 rounded-lg bg-gray-100 border flex items-center justify-center overflow-hidden">
                <img v-if="editImagePreview" :src="editImagePreview" class="w-full h-full object-contain p-1" />
                <PhotoIcon v-else class="w-7 h-7 text-gray-300" />
              </div>
              <label class="cursor-pointer text-sm text-blue-600 hover:underline">
                Changer l'image
                <input type="file" accept="image/*" class="hidden" @change="onEditImage" />
              </label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
            <input v-model="editForm.name" type="text"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="editForm.description" rows="2"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Site web</label>
            <input v-model="editForm.website" type="url"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>
        </div>
        <div class="flex gap-3 mt-5">
          <button @click="showEditModal=false"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">Annuler</button>
          <button @click="submitEdit" :disabled="editForm.processing"
            class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ editForm.processing ? 'Sauvegarde…' : 'Enregistrer' }}
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>
