<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import {
  PlusIcon, UserGroupIcon, UserIcon, TrashIcon, PencilIcon,
  ShieldCheckIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  groups: Array,
  users: Array,
})

// Create group form
const showCreateModal = ref(false)
const createForm = useForm({
  name: '',
  description: '',
})

function submitCreate() {
  createForm.post(route('admin.groups.store'), {
    onSuccess: () => {
      showCreateModal.value = false
      createForm.reset()
    },
  })
}

// Edit group form
const showEditModal = ref(false)
const editingGroup = ref(null)
const editForm = useForm({
  name: '',
  description: '',
  _method: 'PUT',
})

function openEdit(group) {
  editingGroup.value = group
  editForm.name = group.name
  editForm.description = group.description ?? ''
  showEditModal.value = true
}

function submitEdit() {
  editForm.post(route('admin.groups.update', editingGroup.value.id), {
    onSuccess: () => {
      showEditModal.value = false
    },
  })
}

// Delete group
const deleteForm = useForm({})
function deleteGroup(group) {
  if (confirm(`Supprimer le groupe "${group.name}" ? Tous les membres seront retirés.`)) {
    deleteForm.delete(route('admin.groups.destroy', group.id))
  }
}

// Add member
const addMemberForms = {}
function getAddMemberForm(groupId) {
  if (!addMemberForms[groupId]) {
    addMemberForms[groupId] = ref(useForm({
      user_id: '',
      role: 'member',
    }))
  }
  return addMemberForms[groupId]
}

function addMember(group) {
  const form = getAddMemberForm(group.id).value
  if (!form.user_id) return
  form.post(route('admin.groups.members.add', group.id), {
    onSuccess: () => {
      form.user_id = ''
      form.role = 'member'
    },
  })
}

// Remove member
const removeMemberForms = {}
function removeMember(group, user) {
  const key = `${group.id}-${user.id}`
  if (!removeMemberForms[key]) {
    removeMemberForms[key] = useForm({})
  }
  removeMemberForms[key].delete(route('admin.groups.members.remove', { group: group.id, user: user.id }))
}

function roleBadgeClass(role) {
  return role === 'supervisor'
    ? 'bg-purple-100 text-purple-700'
    : 'bg-blue-100 text-blue-700'
}

function roleLabel(role) {
  return role === 'supervisor' ? 'Superviseur' : 'Membre'
}
</script>

<template>
  <AppLayout>
    <div>
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Groupes d'utilisateurs</h1>
          <p class="text-gray-500 text-sm mt-1">{{ groups.length }} groupe(s)</p>
        </div>
        <button @click="showCreateModal = true"
                class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
          <PlusIcon class="w-4 h-4" />
          Nouveau groupe
        </button>
      </div>

      <!-- Empty state -->
      <div v-if="groups.length === 0"
           class="bg-white rounded-xl shadow-sm border px-8 py-16 text-center text-gray-400">
        <UserGroupIcon class="w-14 h-14 mx-auto mb-3 opacity-30" />
        <p class="text-lg font-medium text-gray-500">Aucun groupe créé</p>
        <p class="text-sm mt-1">Créez des groupes pour organiser vos utilisateurs et définir des superviseurs.</p>
      </div>

      <!-- Groups list -->
      <div class="space-y-6">
        <div v-for="group in groups" :key="group.id"
             class="bg-white rounded-xl shadow-sm border overflow-hidden">

          <!-- Group header -->
          <div class="px-5 py-4 bg-gray-50 border-b flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                <UserGroupIcon class="w-5 h-5 text-indigo-600" />
              </div>
              <div>
                <h3 class="font-semibold text-gray-900">{{ group.name }}</h3>
                <p v-if="group.description" class="text-xs text-gray-500">{{ group.description }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ group.members_count }} membre(s)</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button @click="openEdit(group)"
                      class="inline-flex items-center gap-1 text-xs text-blue-600 hover:underline px-2 py-1">
                <PencilIcon class="w-3.5 h-3.5" /> Modifier
              </button>
              <button @click="deleteGroup(group)"
                      class="inline-flex items-center gap-1 text-xs text-red-500 hover:underline px-2 py-1">
                <TrashIcon class="w-3.5 h-3.5" /> Supprimer
              </button>
            </div>
          </div>

          <!-- Members -->
          <div class="px-5 py-4">
            <!-- Current members -->
            <div v-if="group.members.length > 0" class="mb-4">
              <p class="text-xs font-medium text-gray-500 mb-2">Membres actuels :</p>
              <div class="flex flex-wrap gap-2">
                <div v-for="member in group.members" :key="member.id"
                     class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 text-sm">
                  <div class="w-6 h-6 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                    {{ member.name.charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <span class="font-medium text-gray-800">{{ member.name }}</span>
                    <span class="text-xs text-gray-400 ml-1">{{ member.email }}</span>
                  </div>
                  <span :class="['text-xs font-medium px-2 py-0.5 rounded-full flex items-center gap-1', roleBadgeClass(member.role)]">
                    <ShieldCheckIcon v-if="member.role === 'supervisor'" class="w-3 h-3" />
                    <UserIcon v-else class="w-3 h-3" />
                    {{ roleLabel(member.role) }}
                  </span>
                  <button @click="removeMember(group, member)"
                          class="text-gray-400 hover:text-red-500 transition ml-1"
                          title="Retirer du groupe">
                    <TrashIcon class="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            </div>
            <div v-else class="mb-4 text-sm text-gray-400 italic">Aucun membre dans ce groupe.</div>

            <!-- Add member form -->
            <div class="flex flex-wrap items-end gap-2 border-t border-gray-100 pt-3">
              <div class="flex-1 min-w-48">
                <label class="block text-xs text-gray-500 mb-1">Ajouter un utilisateur</label>
                <select :ref="el => {}" v-model="getAddMemberForm(group.id).value.user_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                  <option value="">— Choisir un utilisateur —</option>
                  <option v-for="u in users" :key="u.id" :value="u.id"
                          :disabled="group.members.some(m => m.id === u.id)">
                    {{ u.name }} ({{ u.email }})
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">Rôle</label>
                <select v-model="getAddMemberForm(group.id).value.role"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                  <option value="member">Membre</option>
                  <option value="supervisor">Superviseur</option>
                </select>
              </div>
              <button
                @click="addMember(group)"
                :disabled="!getAddMemberForm(group.id).value.user_id || getAddMemberForm(group.id).value.processing"
                class="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                <PlusIcon class="w-4 h-4" />
                Ajouter
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create modal -->
    <Teleport to="body">
      <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h2 class="text-lg font-semibold text-gray-900">Nouveau groupe</h2>
            <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
          </div>
          <form @submit.prevent="submitCreate" class="px-6 py-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
              <input v-model="createForm.name" type="text" required
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              <p v-if="createForm.errors.name" class="text-red-500 text-xs mt-1">{{ createForm.errors.name }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="createForm.description" rows="3"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2">
              <button type="button" @click="showCreateModal = false"
                      class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Annuler
              </button>
              <button type="submit" :disabled="createForm.processing"
                      class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-60">
                {{ createForm.processing ? 'Création…' : 'Créer' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Edit modal -->
    <Teleport to="body">
      <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h2 class="text-lg font-semibold text-gray-900">Modifier : {{ editingGroup?.name }}</h2>
            <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
          </div>
          <form @submit.prevent="submitEdit" class="px-6 py-4 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
              <input v-model="editForm.name" type="text" required
                     class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="editForm.description" rows="3"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none resize-none"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2">
              <button type="button" @click="showEditModal = false"
                      class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Annuler
              </button>
              <button type="submit" :disabled="editForm.processing"
                      class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-60">
                {{ editForm.processing ? 'Sauvegarde…' : 'Enregistrer' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>
