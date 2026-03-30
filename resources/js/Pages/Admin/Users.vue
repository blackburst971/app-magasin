<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  users:       Array,
  roles:       Array,
  supervisors: Array,
})

const page = usePage()

// ── Création ────────────────────────────────────────────────────────────────
const showCreateForm = ref(false)

const createForm = useForm({
  name:             '',
  email:            '',
  password:         '',
  department:       '',
  phone:            '',
  role:             'user',
  supervisor_id:    null,
  send_credentials: true,
})

const submitCreate = () =>
  createForm.post(route('admin.users.store'), {
    onSuccess: () => {
      showCreateForm.value = false
      createForm.reset()
    },
  })

// ── Édition (modal) ──────────────────────────────────────────────────────────
const editingUser = ref(null)

const editForm = useForm({
  name:          '',
  email:         '',
  department:    '',
  phone:         '',
  password:      '',
  supervisor_id: null,
  role:          'user',
})

const openEdit = (user) => {
  editingUser.value = user
  editForm.name          = user.name
  editForm.email         = user.email
  editForm.department    = user.department ?? ''
  editForm.phone         = user.phone ?? ''
  editForm.password      = ''
  editForm.supervisor_id = user.supervisor_id ?? null
  editForm.role          = user.roles?.[0] ?? 'user'
}

const closeEdit = () => {
  editingUser.value = null
  editForm.reset()
}

const submitEdit = () => {
  editForm.put(route('admin.users.update', editingUser.value.id), {
    onSuccess: () => closeEdit(),
    preserveScroll: true,
  })
}

// ── Avatar admin ──────────────────────────────────────────────────────────────
const avatarInputRefs = ref({})
function setAvatarInputRef(el, userId) { if (el) avatarInputRefs.value[userId] = el }

function triggerAvatarUpload(userId) {
  avatarInputRefs.value[userId]?.click()
}

async function onAvatarChange(e, user) {
  const file = e.target.files?.[0]
  if (!file) return
  const fd = new FormData()
  fd.append('avatar', file)
  fd.append('_method', 'POST')
  try {
    await axios.post(route('admin.users.avatar', user.id), fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    // Recharger la page pour voir le nouvel avatar
    router.reload({ preserveScroll: true })
  } catch (err) {
    alert(err.response?.data?.message ?? 'Erreur lors de l\'upload')
  }
  e.target.value = ''
}

// Exclure l'utilisateur édité de sa propre liste de superviseurs
const editSupervisors = computed(() =>
  editingUser.value
    ? props.supervisors.filter(s => s.id !== editingUser.value.id)
    : props.supervisors
)

// ── Actions diverses ──────────────────────────────────────────────────────────
const changeRole = (user, role) => {
  router.post(route('admin.users.role', user.id), { role }, { preserveScroll: true })
}

const deleteUser = (user) => {
  if (confirm(`Supprimer ${user.name} ?`))
    router.delete(route('admin.users.destroy', user.id), { preserveScroll: true })
}

// ── Import CSV ────────────────────────────────────────────────────────────────
const showImportModal = ref(false)
const importStep      = ref(1)   // 1=upload  2=preview  3=results
const importFile      = ref(null)
const importFileInput = ref(null)
const previewData     = ref(null) // { preview:[], total, valid }
const importLoading   = ref(false)
const importError     = ref(null)

const importForm = useForm({ file: null })

const validRows   = computed(() => previewData.value?.preview.filter(r => r.errors.length === 0) ?? [])
const invalidRows = computed(() => previewData.value?.preview.filter(r => r.errors.length >  0) ?? [])

const roleLabel = (r) => ({ admin:'Admin', professeur:'Prof.', personnel:'Personnel', user:'Étudiant' }[r] ?? r)
const roleClass = (r) => ({
  admin:      'bg-purple-100 text-purple-700',
  professeur: 'bg-indigo-100 text-indigo-700',
  personnel:  'bg-teal-100 text-teal-700',
  user:       'bg-blue-100 text-blue-700',
}[r] ?? 'bg-gray-100 text-gray-700')

const handleFileChange = (e) => {
  importFile.value = e.target.files[0] ?? null
  importError.value = null
}

const analyzeCSV = async () => {
  if (!importFile.value) { importError.value = 'Veuillez sélectionner un fichier CSV.'; return }
  importLoading.value = true
  importError.value   = null
  const fd = new FormData()
  fd.append('file', importFile.value)
  try {
    const res = await axios.post(route('admin.users.import-preview'), fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    previewData.value = res.data
    if (res.data.total === 0) {
      importError.value = 'Le fichier ne contient aucune ligne de données.'
    } else {
      importStep.value = 2
    }
  } catch (e) {
    importError.value = e.response?.data?.message ?? 'Erreur lors de l\'analyse du fichier.'
  } finally {
    importLoading.value = false
  }
}

const processImport = () => {
  if (validRows.value.length === 0) return
  importForm.file = importFile.value
  importForm.post(route('admin.users.import'), {
    onSuccess: () => { importStep.value = 3 },
    onError:   () => { importError.value = 'Une erreur est survenue lors de l\'import.' },
  })
}

const closeImportModal = () => {
  showImportModal.value = false
  importStep.value  = 1
  importFile.value  = null
  previewData.value = null
  importError.value = null
  importForm.reset()
  if (importFileInput.value) importFileInput.value.value = ''
}

// Résultats (flash après import)
const importResults = computed(() => page.props.flash?.import_results ?? null)

// Affiche automatiquement l'étape résultats si on revient depuis un import
watch(importResults, (val) => {
  if (val) {
    showImportModal.value = true
    importStep.value = 3
  }
}, { immediate: true })
</script>

<template>
  <AppLayout>
    <div>
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">👥 Utilisateurs</h1>
        <div class="flex items-center gap-2">
          <button @click="showImportModal = true"
            class="border border-gray-300 bg-white text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 flex items-center gap-1.5">
            📥 Importer CSV
          </button>
          <button @click="showCreateForm = !showCreateForm"
            class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
            {{ showCreateForm ? 'Annuler' : '+ Ajouter' }}
          </button>
        </div>
      </div>

      <!-- ── Formulaire de création ── -->
      <div v-if="showCreateForm" class="bg-white rounded-xl shadow-sm border p-6 mb-6">
        <h2 class="font-semibold mb-4">Nouvel utilisateur</h2>
        <form @submit.prevent="submitCreate" class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom *</label>
            <input v-model="createForm.name" required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            <p v-if="createForm.errors.name" class="mt-1 text-xs text-red-600">{{ createForm.errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
            <input v-model="createForm.email" type="email" required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            <p v-if="createForm.errors.email" class="mt-1 text-xs text-red-600">{{ createForm.errors.email }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe *</label>
            <input v-model="createForm.password" type="password" required
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Département / Classe</label>
            <input v-model="createForm.department"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
            <input v-model="createForm.phone"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Rôle *</label>
            <select v-model="createForm.role"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
              <option value="user">Étudiant</option>
              <option value="personnel">Personnel</option>
              <option value="professeur">Professeur</option>
              <option value="admin">Administrateur</option>
            </select>
          </div>
          <!-- Responsable — pleine largeur -->
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Responsable (superviseur)</label>
            <select v-model="createForm.supervisor_id"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
              <option :value="null">— Aucun —</option>
              <option v-for="s in supervisors" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <p v-if="createForm.errors.supervisor_id" class="mt-1 text-xs text-red-600">{{ createForm.errors.supervisor_id }}</p>
          </div>
          <div class="col-span-2 flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" v-model="createForm.send_credentials"
                class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer" />
              <span class="text-sm text-gray-600">📧 Envoyer les identifiants par email</span>
            </label>
            <button type="submit" :disabled="createForm.processing"
              class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
              Créer l'utilisateur
            </button>
          </div>
        </form>
      </div>

      <!-- ── Tableau des utilisateurs ── -->
      <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-xs text-gray-500 uppercase bg-gray-50 border-b">
              <th class="px-4 py-3 text-left">Utilisateur</th>
              <th class="px-4 py-3 text-left hidden md:table-cell">Département</th>
              <th class="px-4 py-3 text-left hidden lg:table-cell">Responsable</th>
              <th class="px-4 py-3 text-left">Rôle</th>
              <th class="px-4 py-3 text-left hidden lg:table-cell">Créé le</th>
              <th class="px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id" class="border-b hover:bg-gray-50">
              <!-- Utilisateur -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <!-- Avatar cliquable pour upload -->
                  <div @click="triggerAvatarUpload(user.id)"
                    class="w-8 h-8 rounded-full overflow-hidden bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0 cursor-pointer hover:opacity-80 transition relative group"
                    title="Changer la photo">
                    <img v-if="user.avatar_url" :src="user.avatar_url" class="w-full h-full object-cover" />
                    <span v-else>{{ user.name.charAt(0).toUpperCase() }}</span>
                    <span class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 rounded-full text-white text-[9px] font-bold transition">📷</span>
                  </div>
                  <input :ref="el => setAvatarInputRef(el, user.id)" type="file" accept="image/*" class="hidden"
                    @change="e => onAvatarChange(e, user)" />
                  <div>
                    <p class="font-medium">{{ user.name }}</p>
                    <p class="text-xs text-gray-500">{{ user.email }}</p>
                  </div>
                </div>
              </td>

              <!-- Département -->
              <td class="px-4 py-3 text-gray-600 hidden md:table-cell">{{ user.department ?? '—' }}</td>

              <!-- Responsable -->
              <td class="px-4 py-3 hidden lg:table-cell">
                <span v-if="user.supervisor_name"
                  class="inline-flex items-center gap-1 text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full font-medium">
                  👤 {{ user.supervisor_name }}
                </span>
                <span v-else class="text-gray-400 text-xs">—</span>
              </td>

              <!-- Rôle -->
              <td class="px-4 py-3">
                <select :value="user.roles[0]" @change="changeRole(user, $event.target.value)"
                  :class="['text-xs px-2 py-1 rounded-full border-0 font-semibold cursor-pointer',
                    user.roles[0] === 'admin'      ? 'bg-purple-100 text-purple-700' :
                    user.roles[0] === 'professeur' ? 'bg-indigo-100 text-indigo-700' :
                    user.roles[0] === 'personnel'  ? 'bg-teal-100 text-teal-700' :
                    'bg-blue-100 text-blue-700']">
                  <option value="user">Étudiant</option>
                  <option value="personnel">Personnel</option>
                  <option value="professeur">Professeur</option>
                  <option value="admin">Admin</option>
                </select>
              </td>

              <!-- Créé le -->
              <td class="px-4 py-3 text-gray-500 text-xs hidden lg:table-cell">{{ user.created_at }}</td>

              <!-- Actions -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <button @click="openEdit(user)" class="text-xs text-indigo-600 hover:underline font-medium">Modifier</button>
                  <a :href="route('admin.users.show', user.id)" class="text-xs text-blue-600 hover:underline font-medium">Voir</a>
                  <button @click="deleteUser(user)" class="text-xs text-red-600 hover:underline">Supprimer</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Modal d'import CSV ── -->
    <Teleport to="body">
      <div v-if="showImportModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
        @click.self="closeImportModal">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">

          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b flex-shrink-0">
            <div>
              <h2 class="text-lg font-bold text-gray-900">📥 Importer des utilisateurs</h2>
              <p class="text-sm text-gray-500">
                <span v-if="importStep === 1">Importez des utilisateurs depuis un fichier CSV</span>
                <span v-else-if="importStep === 2">Vérifiez les données avant de confirmer l'import</span>
                <span v-else>Résultats de l'import</span>
              </p>
            </div>
            <button @click="closeImportModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none font-light">&times;</button>
          </div>

          <!-- Body scrollable -->
          <div class="overflow-y-auto flex-1 p-6">

            <!-- ── Étape 1 : Upload ── -->
            <div v-if="importStep === 1" class="space-y-5">

              <!-- Template download -->
              <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-start gap-3">
                <span class="text-2xl flex-shrink-0">📄</span>
                <div class="min-w-0">
                  <p class="font-medium text-blue-900 text-sm">Modèle CSV</p>
                  <p class="text-xs text-blue-700 mt-0.5">Téléchargez le modèle pour préparer votre fichier d'import.</p>
                  <a :href="route('admin.users.import-template')"
                    class="inline-block mt-2 text-xs font-medium text-blue-600 hover:underline">
                    ⬇ Télécharger le modèle template_utilisateurs.csv
                  </a>
                </div>
              </div>

              <!-- Colonnes -->
              <div>
                <p class="text-sm font-medium text-gray-700 mb-2">Colonnes attendues</p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                  <div class="bg-gray-50 rounded-lg p-2.5 border">
                    <span class="font-mono font-semibold text-gray-800">name</span>
                    <span class="text-red-500 ml-1">*</span>
                    <p class="text-gray-500 mt-0.5">Nom complet</p>
                  </div>
                  <div class="bg-gray-50 rounded-lg p-2.5 border">
                    <span class="font-mono font-semibold text-gray-800">email</span>
                    <span class="text-red-500 ml-1">*</span>
                    <p class="text-gray-500 mt-0.5">Adresse email (unique)</p>
                  </div>
                  <div class="bg-gray-50 rounded-lg p-2.5 border">
                    <span class="font-mono font-semibold text-gray-800">password</span>
                    <p class="text-gray-500 mt-0.5">Laissez vide pour auto-générer</p>
                  </div>
                  <div class="bg-gray-50 rounded-lg p-2.5 border">
                    <span class="font-mono font-semibold text-gray-800">role</span>
                    <p class="text-gray-500 mt-0.5">admin / professeur / personnel / user</p>
                  </div>
                  <div class="bg-gray-50 rounded-lg p-2.5 border">
                    <span class="font-mono font-semibold text-gray-800">department</span>
                    <p class="text-gray-500 mt-0.5">Classe ou département (optionnel)</p>
                  </div>
                  <div class="bg-gray-50 rounded-lg p-2.5 border">
                    <span class="font-mono font-semibold text-gray-800">phone</span>
                    <p class="text-gray-500 mt-0.5">Téléphone (optionnel)</p>
                  </div>
                </div>
              </div>

              <!-- File input -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fichier CSV <span class="text-red-500">*</span></label>
                <input ref="importFileInput" type="file" accept=".csv,.txt"
                  @change="handleFileChange"
                  class="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer border border-gray-300 rounded-lg p-1.5" />
              </div>

              <!-- Error -->
              <div v-if="importError" class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
                ⚠️ {{ importError }}
              </div>
            </div>

            <!-- ── Étape 2 : Aperçu ── -->
            <div v-else-if="importStep === 2" class="space-y-4">

              <!-- Stats -->
              <div class="grid grid-cols-3 gap-3">
                <div class="bg-gray-50 rounded-xl p-3 text-center border">
                  <div class="text-2xl font-bold text-gray-800">{{ previewData.total }}</div>
                  <div class="text-xs text-gray-500 mt-0.5">Total lignes</div>
                </div>
                <div class="bg-green-50 rounded-xl p-3 text-center border border-green-200">
                  <div class="text-2xl font-bold text-green-700">{{ validRows.length }}</div>
                  <div class="text-xs text-green-600 mt-0.5">✓ Valides</div>
                </div>
                <div class="bg-red-50 rounded-xl p-3 text-center border border-red-200">
                  <div class="text-2xl font-bold text-red-600">{{ invalidRows.length }}</div>
                  <div class="text-xs text-red-600 mt-0.5">✗ Erreurs</div>
                </div>
              </div>

              <!-- Erreurs en haut -->
              <div v-if="invalidRows.length > 0">
                <p class="text-sm font-medium text-red-700 mb-2">⚠️ Lignes ignorées ({{ invalidRows.length }})</p>
                <div class="space-y-1">
                  <div v-for="r in invalidRows" :key="r.row"
                    class="bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-xs">
                    <span class="font-semibold text-red-700">Ligne {{ r.row }}</span>
                    <span v-if="r.name || r.email" class="text-red-600 ml-1">— {{ r.name || r.email }}</span>
                    <span class="text-red-600"> : {{ r.errors.join(', ') }}</span>
                  </div>
                </div>
              </div>

              <!-- Tableau lignes valides -->
              <div v-if="validRows.length > 0">
                <p class="text-sm font-medium text-gray-700 mb-2">✅ Utilisateurs à importer ({{ validRows.length }})</p>
                <div class="overflow-x-auto rounded-lg border">
                  <table class="w-full text-xs">
                    <thead>
                      <tr class="bg-gray-50 border-b text-gray-500 uppercase">
                        <th class="px-3 py-2 text-left">Nom</th>
                        <th class="px-3 py-2 text-left">Email</th>
                        <th class="px-3 py-2 text-left">Rôle</th>
                        <th class="px-3 py-2 text-left">Département</th>
                        <th class="px-3 py-2 text-center">Mot de passe</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="r in validRows" :key="r.row" class="border-b last:border-0 hover:bg-gray-50">
                        <td class="px-3 py-2 font-medium text-gray-800">{{ r.name }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ r.email }}</td>
                        <td class="px-3 py-2">
                          <span :class="['px-1.5 py-0.5 rounded-full text-xs font-semibold', roleClass(r.role)]">
                            {{ roleLabel(r.role) }}
                          </span>
                        </td>
                        <td class="px-3 py-2 text-gray-500">{{ r.department || '—' }}</td>
                        <td class="px-3 py-2 text-center">
                          <span v-if="r.hasPassword" class="text-green-600 font-medium">fourni</span>
                          <span v-else class="text-amber-600 italic">auto-généré</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Error -->
              <div v-if="importError" class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
                ⚠️ {{ importError }}
              </div>
            </div>

            <!-- ── Étape 3 : Résultats ── -->
            <div v-else-if="importStep === 3 && importResults" class="space-y-4">

              <!-- Bilan -->
              <div class="grid grid-cols-2 gap-3">
                <div class="bg-green-50 rounded-xl p-4 text-center border border-green-200">
                  <div class="text-3xl font-bold text-green-700">{{ importResults.imported.length }}</div>
                  <div class="text-sm text-green-600 mt-1 font-medium">utilisateur(s) importé(s)</div>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 text-center border">
                  <div class="text-3xl font-bold text-gray-600">{{ importResults.skipped.length }}</div>
                  <div class="text-sm text-gray-500 mt-1">ignoré(s)</div>
                </div>
              </div>

              <!-- Mots de passe générés -->
              <div v-if="importResults.imported.some(u => u.generated_password)"
                class="bg-amber-50 border border-amber-300 rounded-xl p-4">
                <p class="font-semibold text-amber-900 mb-1 flex items-center gap-2">
                  <span>🔑</span> Mots de passe générés automatiquement
                </p>
                <p class="text-xs text-amber-700 mb-3">
                  Notez ces mots de passe maintenant — ils ne seront plus affichés.
                </p>
                <div class="overflow-x-auto rounded-lg border border-amber-200">
                  <table class="w-full text-xs bg-white">
                    <thead>
                      <tr class="bg-amber-50 border-b border-amber-200 text-amber-800 uppercase">
                        <th class="px-3 py-2 text-left">Nom</th>
                        <th class="px-3 py-2 text-left">Email</th>
                        <th class="px-3 py-2 text-left font-mono">Mot de passe</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="u in importResults.imported.filter(x => x.generated_password)" :key="u.email"
                        class="border-b border-amber-100 last:border-0">
                        <td class="px-3 py-2 font-medium">{{ u.name }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ u.email }}</td>
                        <td class="px-3 py-2 font-mono font-bold text-amber-800 select-all">{{ u.generated_password }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Ignorés -->
              <div v-if="importResults.skipped.length > 0">
                <p class="text-sm font-medium text-gray-700 mb-2">Lignes ignorées</p>
                <div class="space-y-1">
                  <div v-for="s in importResults.skipped" :key="s.email"
                    class="bg-gray-50 border rounded-lg px-3 py-2 text-xs flex justify-between">
                    <span class="font-medium text-gray-800">{{ s.email }}</span>
                    <span class="text-gray-500">{{ s.reason }}</span>
                  </div>
                </div>
              </div>
            </div>

          </div><!-- /body scrollable -->

          <!-- Footer -->
          <div class="px-6 py-4 border-t bg-gray-50 rounded-b-2xl flex-shrink-0">

            <!-- Étape 1 -->
            <div v-if="importStep === 1" class="flex gap-3 justify-end">
              <button @click="closeImportModal" class="border border-gray-300 px-4 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-100">
                Annuler
              </button>
              <button @click="analyzeCSV" :disabled="!importFile || importLoading"
                class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
                <span v-if="importLoading" class="animate-spin">⏳</span>
                {{ importLoading ? 'Analyse…' : 'Analyser le fichier' }}
              </button>
            </div>

            <!-- Étape 2 -->
            <div v-else-if="importStep === 2" class="flex gap-3 justify-between">
              <button @click="importStep = 1; importError = null" class="border border-gray-300 px-4 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-100">
                ← Retour
              </button>
              <div class="flex gap-2">
                <button @click="closeImportModal" class="border border-gray-300 px-4 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-100">
                  Annuler
                </button>
                <button @click="processImport"
                  :disabled="validRows.length === 0 || importForm.processing"
                  class="bg-green-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50 flex items-center gap-2">
                  <span v-if="importForm.processing" class="animate-spin">⏳</span>
                  {{ importForm.processing ? 'Import en cours…' : `Importer ${validRows.length} utilisateur(s)` }}
                </button>
              </div>
            </div>

            <!-- Étape 3 -->
            <div v-else class="flex justify-end">
              <button @click="closeImportModal" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                Fermer
              </button>
            </div>

          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Modal d'édition ── -->
    <Teleport to="body">
      <div v-if="editingUser"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
        @click.self="closeEdit">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg">

          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <div>
              <h2 class="text-lg font-bold text-gray-900">Modifier l'utilisateur</h2>
              <p class="text-sm text-gray-500">{{ editingUser.email }}</p>
            </div>
            <button @click="closeEdit" class="text-gray-400 hover:text-gray-600 text-2xl leading-none font-light">&times;</button>
          </div>

          <!-- Body -->
          <form @submit.prevent="submitEdit" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom *</label>
                <input v-model="editForm.name" required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
                <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                <input v-model="editForm.email" type="email" required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
                <p v-if="editForm.errors.email" class="mt-1 text-xs text-red-600">{{ editForm.errors.email }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Département / Classe</label>
                <input v-model="editForm.department"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                <input v-model="editForm.phone"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>

              <!-- Rôle -->
              <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Rôle *</label>
                <select v-model="editForm.role"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                  <option value="user">Étudiant</option>
                  <option value="personnel">Personnel</option>
                  <option value="professeur">Professeur</option>
                  <option value="admin">Administrateur</option>
                </select>
                <p v-if="editForm.errors.role" class="mt-1 text-xs text-red-600">{{ editForm.errors.role }}</p>
              </div>

              <!-- Responsable — pleine largeur -->
              <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Responsable (superviseur)
                </label>
                <select v-model="editForm.supervisor_id"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
                  <option :value="null">— Aucun —</option>
                  <option v-for="s in editSupervisors" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <p v-if="editForm.errors.supervisor_id" class="mt-1 text-xs text-red-600">{{ editForm.errors.supervisor_id }}</p>
              </div>

              <!-- Mot de passe — pleine largeur -->
              <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Nouveau mot de passe
                  <span class="text-gray-400 font-normal">(laisser vide pour conserver l'actuel)</span>
                </label>
                <input v-model="editForm.password" type="password" autocomplete="new-password"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
            </div>

            <!-- Footer -->
            <div class="flex gap-3 pt-2">
              <button type="button" @click="closeEdit"
                class="flex-1 border border-gray-300 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                Annuler
              </button>
              <button type="submit" :disabled="editForm.processing"
                class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                {{ editForm.processing ? 'Enregistrement…' : 'Enregistrer' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>
