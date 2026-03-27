<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ShieldCheckIcon, LockClosedIcon, InformationCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  roles:       { type: Array, default: () => [] },
  permissions: { type: Array, default: () => [] },
  matrix:      { type: Object, default: () => ({}) },
})

// On travaille sur une copie locale de la matrice (pour les toggles)
const localMatrix = ref(JSON.parse(JSON.stringify(props.matrix)))

// Rôles éditables (tout sauf admin)
const editableRoles = computed(() => props.roles.filter(r => r.name !== 'admin'))

// Labels des rôles
const roleLabels = {
  admin:      'Administrateur',
  professeur: 'Professeur',
  user:       'Étudiant',
}
const roleColors = {
  admin:      'bg-purple-100 text-purple-800 ring-purple-300',
  professeur: 'bg-indigo-100 text-indigo-800 ring-indigo-300',
  user:       'bg-blue-100 text-blue-800 ring-blue-300',
}

// Vérifie si un rôle a une permission
function hasPermission(roleName, permName) {
  return (localMatrix.value[roleName] ?? []).includes(permName)
}

// Vérifie si une permission est verrouillée pour un rôle
function isLocked(roleName, permMeta) {
  return permMeta.locked_for?.includes(roleName)
}

// Bascule une permission pour un rôle
function toggle(roleName, permName) {
  const perms = localMatrix.value[roleName] ?? []
  if (perms.includes(permName)) {
    localMatrix.value[roleName] = perms.filter(p => p !== permName)
  } else {
    localMatrix.value[roleName] = [...perms, permName]
  }
}

// Sauvegarde les permissions d'un rôle
const saving = ref(null)
function save(roleName) {
  saving.value = roleName
  router.post(route('admin.roles.update'), {
    role:        roleName,
    permissions: localMatrix.value[roleName] ?? [],
  }, {
    preserveScroll: true,
    onFinish: () => { saving.value = null },
  })
}

// Tooltip visible
const tooltip = ref(null)
</script>

<template>
  <AppLayout title="Rôles & Permissions">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

      <!-- En-tête -->
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center">
          <ShieldCheckIcon class="w-6 h-6 text-purple-600" />
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Rôles &amp; Permissions</h1>
          <p class="text-sm text-gray-500 mt-0.5">Contrôlez les accès et capacités de chaque rôle</p>
        </div>
      </div>

      <!-- Explication des rôles -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div v-for="role in roles" :key="role.name"
             class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
          <div class="flex items-center gap-2 mb-2">
            <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full ring-1', roleColors[role.name] ?? 'bg-gray-100 text-gray-700 ring-gray-300']">
              {{ roleLabels[role.name] ?? role.name }}
            </span>
            <LockClosedIcon v-if="role.name === 'admin'" class="w-4 h-4 text-gray-400" />
          </div>
          <p class="text-xs text-gray-500">
            <template v-if="role.name === 'admin'">Accès total, toutes les permissions sont fixes et non modifiables.</template>
            <template v-else-if="role.name === 'professeur'">Rôle intermédiaire : peut superviser des étudiants et valider leurs réservations.</template>
            <template v-else>Utilisateur standard (étudiant) : accès limité, doit passer par un responsable pour réserver.</template>
          </p>
          <div v-if="role.name !== 'admin'" class="mt-3">
            <button @click="save(role.name)"
                    :disabled="saving === role.name"
                    class="w-full text-xs font-medium px-3 py-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 transition">
              {{ saving === role.name ? 'Sauvegarde…' : 'Sauvegarder' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Matrice permissions -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
          <h2 class="font-semibold text-gray-900">Matrice des permissions</h2>
          <p class="text-xs text-gray-500 mt-0.5">
            Les colonnes en violet (Admin) sont verrouillées. Modifiez les autres rôles selon vos besoins.
          </p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-100">
                <th class="px-6 py-3 text-left font-semibold text-gray-700 w-1/2">Permission</th>
                <th v-for="role in roles" :key="role.name"
                    class="px-4 py-3 text-center font-semibold min-w-[120px]">
                  <span :class="['text-xs px-2 py-0.5 rounded-full ring-1', roleColors[role.name] ?? 'bg-gray-100 text-gray-700 ring-gray-300']">
                    {{ roleLabels[role.name] ?? role.name }}
                  </span>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="perm in permissions" :key="perm.name"
                  class="hover:bg-gray-50/50 transition-colors">

                <!-- Nom + description -->
                <td class="px-6 py-4">
                  <div class="flex items-start gap-2">
                    <div>
                      <div class="font-medium text-gray-900">{{ perm.label }}</div>
                      <div class="text-xs text-gray-400 mt-0.5">{{ perm.description }}</div>
                    </div>
                  </div>
                </td>

                <!-- Toggle par rôle -->
                <td v-for="role in roles" :key="role.name" class="px-4 py-4 text-center">

                  <!-- Admin: toujours ON, verrouillé -->
                  <div v-if="role.name === 'admin'" class="flex justify-center">
                    <div class="w-11 h-6 bg-purple-400 rounded-full opacity-60 relative cursor-not-allowed">
                      <div class="absolute right-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow"></div>
                    </div>
                  </div>

                  <!-- Autres rôles: toggle interactif -->
                  <div v-else class="flex justify-center">
                    <button @click="toggle(role.name, perm.name)"
                            :class="['w-11 h-6 rounded-full transition-colors duration-200 relative focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1',
                              hasPermission(role.name, perm.name) ? 'bg-indigo-600' : 'bg-gray-300'
                            ]">
                      <span :class="['absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200',
                              hasPermission(role.name, perm.name) ? 'translate-x-5' : 'translate-x-0.5'
                            ]" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Footer avec actions rapides -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex flex-wrap gap-3 justify-end">
          <button v-for="role in editableRoles" :key="role.name"
                  @click="save(role.name)"
                  :disabled="saving === role.name"
                  :class="['inline-flex items-center gap-1.5 text-sm font-medium px-4 py-2 rounded-lg border transition',
                    role.name === 'professeur'
                      ? 'bg-indigo-600 text-white border-indigo-600 hover:bg-indigo-700 disabled:opacity-50'
                      : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 disabled:opacity-50'
                  ]">
            <ShieldCheckIcon class="w-4 h-4" />
            {{ saving === role.name ? 'Sauvegarde…' : 'Sauvegarder ' + (roleLabels[role.name] ?? role.name) }}
          </button>
        </div>
      </div>

      <!-- Note info -->
      <div class="flex gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800">
        <InformationCircleIcon class="w-5 h-5 flex-shrink-0 text-amber-500 mt-0.5" />
        <div>
          <strong>Note :</strong> Les modifications prennent effet immédiatement.
          Les utilisateurs connectés devront se reconnecter pour que les nouveaux droits soient appliqués.
          Le rôle <strong>Administrateur</strong> conserve toujours toutes les permissions et ne peut pas être restreint.
        </div>
      </div>

    </div>
  </AppLayout>
</template>
