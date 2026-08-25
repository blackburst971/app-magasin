<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { PlusIcon, PencilIcon, TrashIcon, PhotoIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  categories: Array,
})

// ─── Création ────────────────────────────────────────────────────────────────
const showCreateModal = ref(false)
const createForm = useForm({ name: '', description: '', color: '#3B82F6', image: null })
const createImagePreview = ref(null)

function onCreateImage(e) {
  const file = e.target.files[0]
  if (!file) return
  createForm.image = file
  createImagePreview.value = URL.createObjectURL(file)
}

function submitCreate() {
  createForm.post(route('categories.store'), {
    forceFormData: true,
    onSuccess: () => { showCreateModal.value = false; createForm.reset(); createForm.color = '#3B82F6'; createImagePreview.value = null },
  })
}

// ─── Édition ─────────────────────────────────────────────────────────────────
const showEditModal = ref(false)
const editTarget = ref(null)
const editForm = useForm({ name: '', description: '', color: '#3B82F6', image: null })
const editImagePreview = ref(null)

function openEdit(c) {
  editTarget.value = c
  editForm.name        = c.name
  editForm.description = c.description ?? ''
  editForm.color       = c.color ?? '#3B82F6'
  editForm.image       = null
  editImagePreview.value = c.image_url ?? null
  showEditModal.value  = true
}

function onEditImage(e) {
  const file = e.target.files[0]
  if (!file) return
  editForm.image = file
  editImagePreview.value = URL.createObjectURL(file)
}

function submitEdit() {
  editForm.put(route('categories.update', editTarget.value.id), {
    forceFormData: true,
    onSuccess: () => { showEditModal.value = false },
  })
}

// ─── Suppression ─────────────────────────────────────────────────────────────
const showDeleteModal = ref(false)
const deleteTarget   = ref(null)
const deleteForm     = useForm({})

function openDeleteModal(c) {
  deleteTarget.value = c
  showDeleteModal.value = true
}

function confirmDelete() {
  deleteForm.delete(route('categories.destroy', deleteTarget.value.id), {
    onSuccess: () => { showDeleteModal.value = false; deleteTarget.value = null },
  })
}
</script>

<template>
  <AppLayout>
    <div class="max-w-5xl">

      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Catégories</h1>
          <p class="text-sm text-gray-500 mt-0.5">{{ categories.length }} catégorie(s)</p>
        </div>
        <button @click="showCreateModal = true"
          class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition shadow-sm">
          <PlusIcon class="w-4 h-4" />
          Nouvelle catégorie
        </button>
      </div>

      <!-- Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-if="categories.length === 0" class="col-span-full text-center py-16 text-gray-400">
          Aucune catégorie enregistrée.
        </div>
        <div v-for="c in categories" :key="c.id"
          class="bg-white rounded-xl shadow-sm border hover:shadow-md transition flex flex-col">
          <!-- Image / Couleur -->
          <div class="h-28 rounded-t-xl overflow-hidden flex items-center justify-center"
            :style="!c.image_url ? `background-color: ${c.color}20` : ''">
            <img v-if="c.image_url" :src="c.image_url" :alt="c.name"
              class="h-full w-full object-contain p-2" />
            <div v-else class="flex flex-col items-center gap-1">
              <div class="w-10 h-10 rounded-full flex items-center justify-center"
                :style="`background-color: ${c.color}`">
                <span class="text-white font-bold text-lg">{{ c.name.charAt(0).toUpperCase() }}</span>
              </div>
            </div>
          </div>
          <!-- Body -->
          <div class="p-4 flex-1 flex flex-col gap-1">
            <div class="flex items-center gap-2">
              <span class="w-3 h-3 rounded-full flex-shrink-0" :style="`background-color: ${c.color}`"></span>
              <p class="font-semibold text-gray-900">{{ c.name }}</p>
            </div>
            <p v-if="c.description" class="text-xs text-gray-500 line-clamp-2">{{ c.description }}</p>
            <span class="bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded-full self-start mt-1">
              {{ c.assets_count ?? 0 }} équipement(s)
            </span>
          </div>
          <!-- Actions -->
          <div class="flex items-center gap-2 px-4 pb-3">
            <button @click="openEdit(c)"
              class="flex items-center gap-1.5 text-xs text-gray-600 hover:text-blue-600 border border-gray-200 hover:border-blue-300 px-3 py-1.5 rounded-lg transition">
              <PencilIcon class="w-3.5 h-3.5" /> Modifier
            </button>
            <button @click="openDeleteModal(c)"
              class="flex items-center gap-1.5 text-xs text-red-500 hover:text-red-700 border border-red-200 hover:border-red-400 hover:bg-red-50 px-3 py-1.5 rounded-lg transition ml-auto">
              <TrashIcon class="w-3.5 h-3.5" /> Supprimer
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- ═══ Modal Créer ═══ -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">Nouvelle catégorie</h3>
          <button @click="showCreateModal=false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <div class="space-y-4">
          <!-- Image upload -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Image</label>
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
            <input v-model="createForm.name" type="text" placeholder="Audio, Vidéo, Réseau…"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            <p v-if="createForm.errors.name" class="text-red-500 text-xs mt-1">{{ createForm.errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="createForm.description" rows="2"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
            <div class="flex items-center gap-3">
              <input v-model="createForm.color" type="color"
                class="w-10 h-10 rounded-lg border border-gray-300 cursor-pointer p-1" />
              <span class="text-sm text-gray-500">{{ createForm.color }}</span>
            </div>
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Image</label>
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
            <div class="flex items-center gap-3">
              <input v-model="editForm.color" type="color"
                class="w-10 h-10 rounded-lg border border-gray-300 cursor-pointer p-1" />
              <span class="text-sm text-gray-500">{{ editForm.color }}</span>
            </div>
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

    <!-- ═══ Modal Supprimer ═══ -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
        <div class="flex items-start gap-3 mb-4">
          <div class="flex-shrink-0 w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
            <TrashIcon class="w-5 h-5 text-red-600" />
          </div>
          <div>
            <h3 class="font-bold text-gray-900">Supprimer « {{ deleteTarget?.name }} » ?</h3>
            <p class="text-sm text-gray-500 mt-1">Cette action est irréversible.</p>
          </div>
        </div>

        <div v-if="deleteTarget?.assets_count > 0"
          class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-4 text-sm text-amber-800">
          <span class="font-semibold">{{ deleteTarget.assets_count }} équipement(s)</span>
          associé(s) à cette catégorie seront déliés (catégorie mise à vide).
          Les équipements ne seront pas supprimés.
        </div>
        <div v-else class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 mb-4 text-sm text-gray-600">
          Aucun équipement n'est associé à cette catégorie.
        </div>

        <div class="flex gap-3">
          <button @click="showDeleteModal = false; deleteTarget = null"
            class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50">
            Annuler
          </button>
          <button @click="confirmDelete" :disabled="deleteForm.processing"
            class="flex-1 bg-red-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-red-700 disabled:opacity-50">
            {{ deleteForm.processing ? 'Suppression…' : 'Supprimer' }}
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>
