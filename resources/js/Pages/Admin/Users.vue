<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  users:           Array,
  roles:           Array,
  supervisors:     Array,
  allSupervisors:  Array,
})

const page = usePage()

// ── Recherche & filtres ────────────────────────────────────────────────────────
const search     = ref('')
const filterRole = ref('')

const filteredUsers = computed(() => {
  if (!props.users || !Array.isArray(props.users)) {
    return []
  }
  let list = [...props.users]
  if (filterRole.value) {
    list = list.filter(u => u.roles?.[0] === filterRole.value)
  }
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(u =>
      u.name.toLowerCase().includes(q) ||
      u.email.toLowerCase().includes(q) ||
      (u.department ?? '').toLowerCase().includes(q)
    )
  }
  return list
})

// ── Sélection ─────────────────────────────────────────────────────────────────
const selectedIds = ref(new Set())

const allSelected = computed(() =>
  filteredUsers.value.length > 0 &&
  filteredUsers.value.every(u => selectedIds.value.has(u.id))
)

function toggleSelectAll() {
  if (allSelected.value) {
    selectedIds.value = new Set()
  } else {
    selectedIds.value = new Set(filteredUsers.value.map(u => u.id))
  }
}

function toggleSelect(id) {
  const s = new Set(selectedIds.value)
  s.has(id) ? s.delete(id) : s.add(id)
  selectedIds.value = s
}

// Réinitialiser la sélection quand les filtres changent
watch([search, filterRole], () => { selectedIds.value = new Set() })

// ── Bulk edit modal ────────────────────────────────────────────────────────────
const showBulkEditModal = ref(false)

const bulkForm = useForm({
  ids:              [],
  apply_role:       false,
  role:             'user',
  apply_supervisor: false,
  supervisor_id:    null,
  apply_department: false,
  department:       '',
})

function openBulkEdit() {
  bulkForm.ids              = [...selectedIds.value]
  bulkForm.apply_role       = false
  bulkForm.role             = 'user'
  bulkForm.apply_supervisor = false
  bulkForm.supervisor_id    = null
  bulkForm.apply_department = false
  bulkForm.department       = ''
  bulkForm.clearErrors()
  showBulkEditModal.value = true
}

function submitBulkEdit() {
  bulkForm.post(route('admin.users.bulk-update'), {
    onSuccess: () => {
      showBulkEditModal.value = false
      selectedIds.value = new Set()
    },
    preserveScroll: true,
  })
}

// ── Bulk delete ────────────────────────────────────────────────────────────────
const bulkDeleting = ref(false)

function bulkDelete() {
  const count = selectedIds.value.size
  if (!confirm(`Supprimer définitivement ${count} utilisateur(s) ? Cette action est irréversible.`)) return
  bulkDeleting.value = true
  router.post(
    route('admin.users.bulk-destroy'),
    { ids: [...selectedIds.value] },
    {
      onSuccess: () => { selectedIds.value = new Set() },
      onFinish:  () => { bulkDeleting.value = false },
      preserveScroll: true,
    }
  )
}

// Utilisateurs sélectionnés pour l'affichage dans la modal
const selectedUserNames = computed(() =>
  props.users
    .filter(u => selectedIds.value.has(u.id))
    .map(u => u.name)
    .slice(0, 5)
)

// ── Création ────────────────────────────────────────────────────────────────
const showCreateForm = ref(false)

const createForm = useForm({
  name:                   '',
  email:                  '',
  password:               '',
  department:             '',
  phone:                  '',
  role:                   'user',
  supervisor_id:          null,
  send_credentials:       true,
  allow_duplicate_email:  false,
})

const submitCreate = () =>
  createForm.post(route('admin.users.store'), {
    onSuccess: () => {
      showCreateForm.value = false
      createForm.reset()
    },
  })

// ── Édition individuelle (modal) ──────────────────────────────────────────────
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
  // Charger les superviseurs multiples
  selectedSupervisors.value = user.supervisors?.map(s => s.id) ?? []
}

const closeEdit = () => {
  editingUser.value = null
  editForm.reset()
  selectedSupervisors.value = []
}

// ── Gestion des superviseurs multiples ────────────────────────────────────────
const selectedSupervisors = ref([])
const addSupervisorSelect = ref(null)

// Liste des superviseurs disponibles (exclure l'utilisateur édité)
const availableSupervisors = computed(() => {
  const supervisorList = props.allSupervisors ?? props.supervisors
  if (!supervisorList || !Array.isArray(supervisorList)) {
    return []
  }
  if (!editingUser.value) {
    return supervisorList
  }
  return supervisorList.filter(s => s && s.id !== editingUser.value.id)
})

// Superviseurs disponibles non encore sélectionnés
const unselectedSupervisors = computed(() => {
  const available = availableSupervisors.value
  if (!available || !Array.isArray(available)) {
    return []
  }
  return available.filter(s => s && !selectedSupervisors.value.includes(s.id))
})

// Sauvegarder les superviseurs secondaires
const saveSupervisors = () => {
  if (!editingUser.value) return

  router.put(route('admin.users.update-supervisors', editingUser.value.id), {
    supervisor_ids: selectedSupervisors.value,
  }, {
    preserveScroll: true,
    preserveState: true,
  })
}

// Ajouter un superviseur secondaire
const addSupervisor = (supervisorId) => {
  if (!supervisorId || selectedSupervisors.value.includes(supervisorId)) {
    return
  }
  selectedSupervisors.value.push(supervisorId)
  saveSupervisors()
}

// Ajouter un superviseur depuis le select
const handleAddSupervisorClick = () => {
  if (!addSupervisorSelect.value || !addSupervisorSelect.value.value) {
    return
  }
  const supervisorId = parseInt(addSupervisorSelect.value.value)
  addSupervisor(supervisorId)
  addSupervisorSelect.value.value = ''
}

// Retirer un superviseur secondaire
const removeSupervisor = (supervisorId) => {
  selectedSupervisors.value = selectedSupervisors.value.filter(id => id !== supervisorId)
  saveSupervisors()
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
    router.reload({ preserveScroll: true })
  } catch (err) {
    alert(err.response?.data?.message ?? 'Erreur lors de l\'upload')
  }
  e.target.value = ''
}

// Exclure l'utilisateur édité de sa propre liste de superviseurs
const editSupervisors = computed(() => {
  const supervisorList = props.allSupervisors ?? []
  if (!Array.isArray(supervisorList)) {
    return []
  }
  return editingUser.value
    ? supervisorList.filter(s => s && s.id !== editingUser.value.id)
    : supervisorList
})

// ── Actions diverses ──────────────────────────────────────────────────────────
const changeRole = (user, role) => {
  router.post(route('admin.users.role', user.id), { role }, { preserveScroll: true })
}

const deleteUser = (user) => {
  if (confirm(`Supprimer ${user.name} ?`))
    router.delete(route('admin.users.destroy', user.id), { preserveScroll: true })
}

// ── Helpers visuels ───────────────────────────────────────────────────────────
const roleLabel = (r) => ({ admin:'Admin', professeur:'Prof.', personnel:'Personnel', user:'Étudiant' }[r] ?? r)
const roleClass = (r) => ({
  admin:      'bg-purple-100 text-purple-700',
  professeur: 'bg-indigo-100 text-indigo-700',
  personnel:  'bg-teal-100 text-teal-700',
  user:       'bg-blue-100 text-blue-700',
}[r] ?? 'bg-gray-100 text-gray-700')

// ── Import CSV ────────────────────────────────────────────────────────────────
const showImportModal = ref(false)
const importStep      = ref(1)
const importFile      = ref(null)
const importFileInput = ref(null)
const previewData     = ref(null)
const importLoading   = ref(false)
const importError     = ref(null)

const importForm = useForm({ file: null })

const validRows   = computed(() => previewData.value?.preview.filter(r => r.errors.length === 0) ?? [])
const invalidRows = computed(() => previewData.value?.preview.filter(r => r.errors.length >  0) ?? [])

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

const importResults = computed(() => page.props.flash?.import_results ?? null)

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
      <!-- En-tête -->
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">👥 Utilisateurs</h1>
        <div class="flex items-center gap-2">
          <button @click="showImportModal = true"
            class="border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-1.5">
            📥 Importer CSV
          </button>
          <button @click="showCreateForm = !showCreateForm"
            class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
            {{ showCreateForm ? 'Annuler' : '+ Ajouter' }}
          </button>
        </div>
      </div>

      <!-- Formulaire de création -->
      <div v-if="showCreateForm" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <h2 class="font-semibold mb-4 text-gray-900 dark:text-white">Nouvel utilisateur</h2>
        <form @submit.prevent="submitCreate" class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom *</label>
            <input v-model="createForm.name" required
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white dark:bg-gray-900 text-gray-900 dark:text-white" />
            <p v-if="createForm.errors.name" class="mt-1 text-xs text-red-600">{{ createForm.errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email *</label>
            <input v-model="createForm.email" type="email" required
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white dark:bg-gray-900 text-gray-900 dark:text-white" />
            <p v-if="createForm.errors.email" class="mt-1 text-xs text-red-600">{{ createForm.errors.email }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mot de passe *</label>
            <input v-model="createForm.password" type="password" required
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white dark:bg-gray-900 text-gray-900 dark:text-white" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Département / Classe</label>
            <input v-model="createForm.department"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white dark:bg-gray-900 text-gray-900 dark:text-white" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Téléphone</label>
            <input v-model="createForm.phone"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white dark:bg-gray-900 text-gray-900 dark:text-white" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rôle *</label>
            <select v-model="createForm.role"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
              <option value="user">Étudiant</option>
              <option value="personnel">Personnel</option>
              <option value="professeur">Professeur</option>
              <option value="admin">Administrateur</option>
            </select>
          </div>
          <div class="col-span-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Responsable (superviseur)</label>
            <select v-model="createForm.supervisor_id"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
              <option :value="null">— Aucun —</option>
              <option v-for="s in supervisors" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <p v-if="createForm.errors.supervisor_id" class="mt-1 text-xs text-red-600">{{ createForm.errors.supervisor_id }}</p>
          </div>
          <div class="col-span-2 flex flex-wrap items-center gap-x-6 gap-y-2">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" v-model="createForm.send_credentials"
                class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer" />
              <span class="text-sm text-gray-600 dark:text-gray-400">📧 Envoyer les identifiants par email</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" v-model="createForm.allow_duplicate_email"
                class="w-4 h-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500 cursor-pointer" />
              <span class="text-sm text-amber-700 dark:text-amber-400">⚠️ Autoriser un email déjà utilisé</span>
            </label>
          </div>
          <div v-if="createForm.allow_duplicate_email"
               class="col-span-2 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg px-3 py-2 text-xs text-amber-800 dark:text-amber-300">
            <strong>Attention :</strong> un autre compte existant partage déjà ou partagera cette adresse email.
          </div>
          <div class="col-span-2 flex justify-end">
            <button type="submit" :disabled="createForm.processing"
              class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
              Créer l'utilisateur
            </button>
          </div>
        </form>
      </div>

      <!-- Recherche + Filtres + Barre de sélection -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">

        <!-- Barre du haut : recherche, filtre rôle, compteur -->
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center gap-3">
          <input v-model="search" type="text" placeholder="Rechercher un utilisateur…"
            class="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-900 text-gray-900 dark:text-white w-56" />
          <select v-model="filterRole"
            class="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
            <option value="">Tous les rôles</option>
            <option value="admin">Admin</option>
            <option value="professeur">Professeur</option>
            <option value="personnel">Personnel</option>
            <option value="user">Étudiant</option>
          </select>
          <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ filteredUsers.length }} utilisateur(s)
          </span>

          <!-- Barre d'actions bulk (visible quand sélection) -->
          <Transition
            enter-active-class="transition duration-150"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="selectedIds.size > 0" class="ml-auto flex items-center gap-2 flex-wrap">
              <span class="text-sm font-semibold text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-3 py-1 rounded-full">
                {{ selectedIds.size }} sélectionné(s)
              </span>
              <button @click="openBulkEdit()"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition">
                ✏️ Modifier la sélection
              </button>
              <button @click="bulkDelete()" :disabled="bulkDeleting"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-red-600 hover:bg-red-700 text-white font-semibold rounded-lg transition disabled:opacity-50">
                {{ bulkDeleting ? '⏳ Suppression…' : `🗑️ Supprimer (${selectedIds.size})` }}
              </button>
              <button @click="selectedIds = new Set()"
                class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 px-2 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                Désélectionner
              </button>
            </div>
          </Transition>
        </div>

        <!-- Tableau -->
        <table class="w-full text-sm">
          <thead>
            <tr class="text-xs text-gray-500 dark:text-gray-400 uppercase bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
              <th class="w-10 px-4 py-3">
                <input type="checkbox" :checked="allSelected" @change="toggleSelectAll"
                  class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500" />
              </th>
              <th class="px-4 py-3 text-left">Utilisateur</th>
              <th class="px-4 py-3 text-left hidden md:table-cell">Département</th>
              <th class="px-4 py-3 text-left hidden lg:table-cell">Responsable</th>
              <th class="px-4 py-3 text-left">Rôle</th>
              <th class="px-4 py-3 text-left hidden lg:table-cell">Créé le</th>
              <th class="px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr v-for="user in filteredUsers" :key="user.id"
              :class="[
                'hover:bg-gray-50 dark:hover:bg-gray-700/30 transition',
                selectedIds.has(user.id) ? 'bg-blue-50 dark:bg-blue-900/10' : '',
              ]">
              <!-- Checkbox -->
              <td class="px-4 py-3">
                <input type="checkbox" :checked="selectedIds.has(user.id)" @change="toggleSelect(user.id)"
                  class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500" />
              </td>

              <!-- Utilisateur -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
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
                    <p class="font-medium text-gray-900 dark:text-white">{{ user.name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                  </div>
                </div>
              </td>

              <!-- Département -->
              <td class="px-4 py-3 text-gray-600 dark:text-gray-400 hidden md:table-cell">{{ user.department ?? '—' }}</td>

              <!-- Responsable -->
              <td class="px-4 py-3 hidden lg:table-cell">
                <div class="flex flex-col gap-1">
                  <!-- Responsable principal -->
                  <span v-if="user.supervisor_name"
                    class="inline-flex items-center gap-1 text-xs bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 px-2 py-0.5 rounded-full font-medium w-fit">
                    👤 {{ user.supervisor_name }}
                  </span>
                  <!-- Superviseurs multiples -->
                  <div v-if="user.supervisors && user.supervisors.length > 0" class="flex flex-wrap gap-1">
                    <span v-for="sup in user.supervisors" :key="sup.id"
                      class="inline-flex items-center gap-1 text-xs bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 px-2 py-0.5 rounded-full font-medium">
                      {{ sup.name }}
                    </span>
                  </div>
                  <!-- Aucun -->
                  <span v-if="!user.supervisor_name && (!user.supervisors || user.supervisors.length === 0)" class="text-gray-400 text-xs">—</span>
                </div>
              </td>

              <!-- Rôle -->
              <td class="px-4 py-3">
                <select :value="user.roles[0]" @change="changeRole(user, $event.target.value)"
                  :class="['text-xs px-2 py-1 rounded-full border-0 font-semibold cursor-pointer',
                    user.roles[0] === 'admin'      ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300' :
                    user.roles[0] === 'professeur' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300' :
                    user.roles[0] === 'personnel'  ? 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300' :
                    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300']">
                  <option value="user">Étudiant</option>
                  <option value="personnel">Personnel</option>
                  <option value="professeur">Professeur</option>
                  <option value="admin">Admin</option>
                </select>
              </td>

              <!-- Créé le -->
              <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs hidden lg:table-cell">{{ user.created_at }}</td>

              <!-- Actions -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <button @click="openEdit(user)" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-medium">Modifier</button>
                  <a :href="route('admin.users.show', user.id)" class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium">Voir</a>
                  <button @click="deleteUser(user)" class="text-xs text-red-600 dark:text-red-400 hover:underline">Supprimer</button>
                </div>
              </td>
            </tr>

            <tr v-if="filteredUsers.length === 0">
              <td colspan="7" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                Aucun utilisateur trouvé.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Modal : Modification groupée ──────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showBulkEditModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
        @click.self="showBulkEditModal = false">
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-lg flex flex-col max-h-[90vh]">

          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
            <div>
              <h2 class="text-lg font-bold text-gray-900 dark:text-white">Modifier la sélection</h2>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ selectedIds.size }} utilisateur(s) concerné(s)
              </p>
            </div>
            <button @click="showBulkEditModal = false" class="text-gray-400 hover:text-gray-600 text-2xl leading-none font-light">&times;</button>
          </div>

          <!-- Utilisateurs sélectionnés (aperçu) -->
          <div class="px-6 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
            <p class="text-xs text-gray-500 dark:text-gray-400">
              <span v-for="(name, i) in selectedUserNames" :key="i">
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ name }}</span><span v-if="i < selectedUserNames.length - 1">, </span>
              </span>
              <span v-if="selectedIds.size > 5" class="text-gray-400"> et {{ selectedIds.size - 5 }} autre(s)…</span>
            </p>
          </div>

          <!-- Body scrollable -->
          <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">
            <p class="text-xs text-gray-500 dark:text-gray-400 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg px-3 py-2">
              Cochez les champs que vous souhaitez modifier. Les champs non cochés resteront inchangés pour chaque utilisateur.
            </p>

            <!-- Rôle -->
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
              <label class="flex items-center gap-3 px-4 py-3 bg-gray-50 dark:bg-gray-800/60 cursor-pointer select-none">
                <input type="checkbox" v-model="bulkForm.apply_role"
                  class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" />
                <div>
                  <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Rôle</span>
                  <p class="text-xs text-gray-400">Changer le rôle de tous les utilisateurs sélectionnés</p>
                </div>
              </label>
              <div v-if="bulkForm.apply_role" class="px-4 pb-4 pt-2">
                <select v-model="bulkForm.role"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                  <option value="user">Étudiant</option>
                  <option value="personnel">Personnel</option>
                  <option value="professeur">Professeur</option>
                  <option value="admin">Administrateur</option>
                </select>
              </div>
            </div>

            <!-- Responsable -->
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
              <label class="flex items-center gap-3 px-4 py-3 bg-gray-50 dark:bg-gray-800/60 cursor-pointer select-none">
                <input type="checkbox" v-model="bulkForm.apply_supervisor"
                  class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" />
                <div>
                  <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Responsable (superviseur)</span>
                  <p class="text-xs text-gray-400">Affecter un responsable commun à tous les utilisateurs sélectionnés</p>
                </div>
              </label>
              <div v-if="bulkForm.apply_supervisor" class="px-4 pb-4 pt-2">
                <select v-model="bulkForm.supervisor_id"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                  <option :value="null">— Aucun (retirer le responsable) —</option>
                  <option v-for="s in supervisors" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                  Un utilisateur ne peut pas être son propre responsable — les auto-affectations seront ignorées.
                </p>
              </div>
            </div>

            <!-- Département -->
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
              <label class="flex items-center gap-3 px-4 py-3 bg-gray-50 dark:bg-gray-800/60 cursor-pointer select-none">
                <input type="checkbox" v-model="bulkForm.apply_department"
                  class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" />
                <div>
                  <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">Département / Classe</span>
                  <p class="text-xs text-gray-400">Affecter le même département à tous les utilisateurs sélectionnés</p>
                </div>
              </label>
              <div v-if="bulkForm.apply_department" class="px-4 pb-4 pt-2">
                <input v-model="bulkForm.department" type="text" placeholder="Ex: BTS MAV, Terminale…"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Laisser vide pour effacer le département.</p>
              </div>
            </div>

            <!-- Alerte si rien n'est coché -->
            <div v-if="!bulkForm.apply_role && !bulkForm.apply_supervisor && !bulkForm.apply_department"
              class="text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg px-3 py-2">
              Aucun champ sélectionné — cochez au moins un champ pour appliquer des modifications.
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 flex-shrink-0 flex gap-3 justify-between">
            <button type="button" @click="showBulkEditModal = false"
              class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition">
              Annuler
            </button>
            <button @click="submitBulkEdit"
              :disabled="bulkForm.processing || (!bulkForm.apply_role && !bulkForm.apply_supervisor && !bulkForm.apply_department)"
              class="px-5 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition disabled:opacity-50">
              {{ bulkForm.processing ? 'Enregistrement…' : `Appliquer à ${selectedIds.size} utilisateur(s)` }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Modal d'import CSV ── -->
    <Teleport to="body">
      <div v-if="showImportModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
        @click.self="closeImportModal">
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">

          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
            <div>
              <h2 class="text-lg font-bold text-gray-900 dark:text-white">📥 Importer des utilisateurs</h2>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                <span v-if="importStep === 1">Importez des utilisateurs depuis un fichier CSV</span>
                <span v-else-if="importStep === 2">Vérifiez les données avant de confirmer l'import</span>
                <span v-else>Résultats de l'import</span>
              </p>
            </div>
            <button @click="closeImportModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none font-light">&times;</button>
          </div>

          <div class="overflow-y-auto flex-1 p-6">

            <!-- Étape 1 : Upload -->
            <div v-if="importStep === 1" class="space-y-5">
              <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4 flex items-start gap-3">
                <span class="text-2xl flex-shrink-0">📄</span>
                <div class="min-w-0">
                  <p class="font-medium text-blue-900 dark:text-blue-200 text-sm">Modèle CSV</p>
                  <p class="text-xs text-blue-700 dark:text-blue-400 mt-0.5">Téléchargez le modèle pour préparer votre fichier d'import.</p>
                  <a :href="route('admin.users.import-template')"
                    class="inline-block mt-2 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">
                    ⬇ Télécharger le modèle template_utilisateurs.csv
                  </a>
                </div>
              </div>
              <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Colonnes attendues</p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                  <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-2.5 border border-gray-200 dark:border-gray-700">
                    <span class="font-mono font-semibold text-gray-800 dark:text-gray-200">name</span>
                    <span class="text-red-500 ml-1">*</span>
                    <p class="text-gray-500 mt-0.5">Nom complet</p>
                  </div>
                  <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-2.5 border border-gray-200 dark:border-gray-700">
                    <span class="font-mono font-semibold text-gray-800 dark:text-gray-200">email</span>
                    <span class="text-red-500 ml-1">*</span>
                    <p class="text-gray-500 mt-0.5">Adresse email (unique)</p>
                  </div>
                  <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-2.5 border border-gray-200 dark:border-gray-700">
                    <span class="font-mono font-semibold text-gray-800 dark:text-gray-200">password</span>
                    <p class="text-gray-500 mt-0.5">Laissez vide pour auto-générer</p>
                  </div>
                  <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-2.5 border border-gray-200 dark:border-gray-700">
                    <span class="font-mono font-semibold text-gray-800 dark:text-gray-200">role</span>
                    <p class="text-gray-500 mt-0.5">admin / professeur / personnel / user</p>
                  </div>
                  <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-2.5 border border-gray-200 dark:border-gray-700">
                    <span class="font-mono font-semibold text-gray-800 dark:text-gray-200">department</span>
                    <p class="text-gray-500 mt-0.5">Classe ou département (optionnel)</p>
                  </div>
                  <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-2.5 border border-gray-200 dark:border-gray-700">
                    <span class="font-mono font-semibold text-gray-800 dark:text-gray-200">phone</span>
                    <p class="text-gray-500 mt-0.5">Téléphone (optionnel)</p>
                  </div>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fichier CSV <span class="text-red-500">*</span></label>
                <input ref="importFileInput" type="file" accept=".csv,.txt"
                  @change="handleFileChange"
                  class="block w-full text-sm text-gray-700 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer border border-gray-300 dark:border-gray-600 rounded-lg p-1.5 bg-white dark:bg-gray-800" />
              </div>
              <div v-if="importError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 text-sm text-red-700 dark:text-red-400">
                ⚠️ {{ importError }}
              </div>
            </div>

            <!-- Étape 2 : Aperçu -->
            <div v-else-if="importStep === 2" class="space-y-4">
              <div class="grid grid-cols-3 gap-3">
                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-3 text-center border border-gray-200 dark:border-gray-700">
                  <div class="text-2xl font-bold text-gray-800 dark:text-white">{{ previewData.total }}</div>
                  <div class="text-xs text-gray-500 mt-0.5">Total lignes</div>
                </div>
                <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-3 text-center border border-green-200 dark:border-green-800">
                  <div class="text-2xl font-bold text-green-700 dark:text-green-400">{{ validRows.length }}</div>
                  <div class="text-xs text-green-600 mt-0.5">✓ Valides</div>
                </div>
                <div class="bg-red-50 dark:bg-red-900/20 rounded-xl p-3 text-center border border-red-200 dark:border-red-800">
                  <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ invalidRows.length }}</div>
                  <div class="text-xs text-red-600 mt-0.5">✗ Erreurs</div>
                </div>
              </div>
              <div v-if="invalidRows.length > 0">
                <p class="text-sm font-medium text-red-700 dark:text-red-400 mb-2">⚠️ Lignes ignorées ({{ invalidRows.length }})</p>
                <div class="space-y-1">
                  <div v-for="r in invalidRows" :key="r.row"
                    class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg px-3 py-2 text-xs">
                    <span class="font-semibold text-red-700 dark:text-red-400">Ligne {{ r.row }}</span>
                    <span v-if="r.name || r.email" class="text-red-600 ml-1">— {{ r.name || r.email }}</span>
                    <span class="text-red-600"> : {{ r.errors.join(', ') }}</span>
                  </div>
                </div>
              </div>
              <div v-if="validRows.length > 0">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">✅ Utilisateurs à importer ({{ validRows.length }})</p>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                  <table class="w-full text-xs">
                    <thead>
                      <tr class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 uppercase">
                        <th class="px-3 py-2 text-left">Nom</th>
                        <th class="px-3 py-2 text-left">Email</th>
                        <th class="px-3 py-2 text-left">Rôle</th>
                        <th class="px-3 py-2 text-left">Département</th>
                        <th class="px-3 py-2 text-center">Mot de passe</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="r in validRows" :key="r.row" class="border-b border-gray-100 dark:border-gray-700 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="px-3 py-2 font-medium text-gray-800 dark:text-gray-200">{{ r.name }}</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ r.email }}</td>
                        <td class="px-3 py-2">
                          <span :class="['px-1.5 py-0.5 rounded-full text-xs font-semibold', roleClass(r.role)]">
                            {{ roleLabel(r.role) }}
                          </span>
                        </td>
                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ r.department || '—' }}</td>
                        <td class="px-3 py-2 text-center">
                          <span v-if="r.hasPassword" class="text-green-600 dark:text-green-400 font-medium">fourni</span>
                          <span v-else class="text-amber-600 dark:text-amber-400 italic">auto-généré</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div v-if="importError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 text-sm text-red-700 dark:text-red-400">
                ⚠️ {{ importError }}
              </div>
            </div>

            <!-- Étape 3 : Résultats -->
            <div v-else-if="importStep === 3 && importResults" class="space-y-4">
              <div class="grid grid-cols-2 gap-3">
                <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-4 text-center border border-green-200 dark:border-green-800">
                  <div class="text-3xl font-bold text-green-700 dark:text-green-400">{{ importResults.imported.length }}</div>
                  <div class="text-sm text-green-600 mt-1 font-medium">utilisateur(s) importé(s)</div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-4 text-center border border-gray-200 dark:border-gray-700">
                  <div class="text-3xl font-bold text-gray-600 dark:text-gray-400">{{ importResults.skipped.length }}</div>
                  <div class="text-sm text-gray-500 mt-1">ignoré(s)</div>
                </div>
              </div>
              <div v-if="importResults.imported.some(u => u.generated_password)"
                class="bg-amber-50 dark:bg-amber-900/20 border border-amber-300 dark:border-amber-700 rounded-xl p-4">
                <p class="font-semibold text-amber-900 dark:text-amber-200 mb-1 flex items-center gap-2">
                  <span>🔑</span> Mots de passe générés automatiquement
                </p>
                <p class="text-xs text-amber-700 dark:text-amber-400 mb-3">
                  Notez ces mots de passe maintenant — ils ne seront plus affichés.
                </p>
                <div class="overflow-x-auto rounded-lg border border-amber-200 dark:border-amber-700">
                  <table class="w-full text-xs bg-white dark:bg-gray-900">
                    <thead>
                      <tr class="bg-amber-50 dark:bg-amber-900/30 border-b border-amber-200 dark:border-amber-700 text-amber-800 dark:text-amber-300 uppercase">
                        <th class="px-3 py-2 text-left">Nom</th>
                        <th class="px-3 py-2 text-left">Email</th>
                        <th class="px-3 py-2 text-left font-mono">Mot de passe</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="u in importResults.imported.filter(x => x.generated_password)" :key="u.email"
                        class="border-b border-amber-100 dark:border-amber-800 last:border-0">
                        <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">{{ u.name }}</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ u.email }}</td>
                        <td class="px-3 py-2 font-mono font-bold text-amber-800 dark:text-amber-300 select-all">{{ u.generated_password }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div v-if="importResults.skipped.length > 0">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Lignes ignorées</p>
                <div class="space-y-1">
                  <div v-for="s in importResults.skipped" :key="s.email"
                    class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 text-xs flex justify-between">
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ s.email }}</span>
                    <span class="text-gray-500 dark:text-gray-400">{{ s.reason }}</span>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- Footer -->
          <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 rounded-b-2xl flex-shrink-0">
            <div v-if="importStep === 1" class="flex gap-3 justify-end">
              <button @click="closeImportModal" class="border border-gray-300 dark:border-gray-600 px-4 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Annuler</button>
              <button @click="analyzeCSV" :disabled="!importFile || importLoading"
                class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
                <span v-if="importLoading" class="animate-spin">⏳</span>
                {{ importLoading ? 'Analyse…' : 'Analyser le fichier' }}
              </button>
            </div>
            <div v-else-if="importStep === 2" class="flex gap-3 justify-between">
              <button @click="importStep = 1; importError = null" class="border border-gray-300 dark:border-gray-600 px-4 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">← Retour</button>
              <div class="flex gap-2">
                <button @click="closeImportModal" class="border border-gray-300 dark:border-gray-600 px-4 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Annuler</button>
                <button @click="processImport" :disabled="validRows.length === 0 || importForm.processing"
                  class="bg-green-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50 flex items-center gap-2">
                  <span v-if="importForm.processing" class="animate-spin">⏳</span>
                  {{ importForm.processing ? 'Import en cours…' : `Importer ${validRows.length} utilisateur(s)` }}
                </button>
              </div>
            </div>
            <div v-else class="flex justify-end">
              <button @click="closeImportModal" class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">Fermer</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ── Modal d'édition individuelle ── -->
    <Teleport to="body">
      <div v-if="editingUser"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
        @click.self="closeEdit">
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-lg">

          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <div>
              <h2 class="text-lg font-bold text-gray-900 dark:text-white">Modifier l'utilisateur</h2>
              <p class="text-sm text-gray-500 dark:text-gray-400">{{ editingUser.email }}</p>
            </div>
            <button @click="closeEdit" class="text-gray-400 hover:text-gray-600 text-2xl leading-none font-light">&times;</button>
          </div>

          <form @submit.prevent="submitEdit" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom *</label>
                <input v-model="editForm.name" required
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
                <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email *</label>
                <input v-model="editForm.email" type="email" required
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
                <p v-if="editForm.errors.email" class="mt-1 text-xs text-red-600">{{ editForm.errors.email }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Département / Classe</label>
                <input v-model="editForm.department"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Téléphone</label>
                <input v-model="editForm.phone"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
              </div>
              <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rôle *</label>
                <select v-model="editForm.role"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                  <option value="user">Étudiant</option>
                  <option value="personnel">Personnel</option>
                  <option value="professeur">Professeur</option>
                  <option value="admin">Administrateur</option>
                </select>
                <p v-if="editForm.errors.role" class="mt-1 text-xs text-red-600">{{ editForm.errors.role }}</p>
              </div>
              <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Responsable principal (par défaut)
                  <span class="text-xs text-gray-500 font-normal ml-1">Ce superviseur sera présélectionné automatiquement pour ses demandes</span>
                </label>
                <select v-model="editForm.supervisor_id"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                  <option :value="null">— Aucun responsable principal —</option>
                  <option v-for="s in editSupervisors" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <p v-if="editForm.errors.supervisor_id" class="mt-1 text-xs text-red-600">{{ editForm.errors.supervisor_id }}</p>
              </div>

              <!-- ── Superviseurs secondaires ── -->
              <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Responsables secondaires (optionnels)
                  <span class="text-xs text-gray-500 font-normal ml-1">L'utilisateur pourra choisir parmi ces superviseurs alternatifs si besoin</span>
                </label>

                <!-- Liste des superviseurs actuels -->
                <div v-if="selectedSupervisors.length > 0" class="mb-3 flex flex-wrap gap-2">
                  <div v-for="supId in selectedSupervisors" :key="supId"
                    class="inline-flex items-center gap-1.5 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 px-2.5 py-1 rounded-full text-xs font-medium border border-indigo-200 dark:border-indigo-800">
                    <span>{{ (availableSupervisors || []).find(s => s && s.id === supId)?.name ?? 'Superviseur inconnu' }}</span>
                    <button type="button" @click="removeSupervisor(supId)"
                      class="text-indigo-500 hover:text-indigo-700 dark:hover:text-indigo-200 ml-0.5">
                      ×
                    </button>
                  </div>
                </div>

                <!-- Aucun superviseur secondaire -->
                <div v-else class="mb-3 text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50 rounded-lg px-3 py-2 border border-gray-200 dark:border-gray-700">
                  Aucun responsable secondaire. L'utilisateur utilisera uniquement le responsable principal ci-dessus (ou les superviseurs de ses groupes).
                </div>

                <!-- Ajouter un superviseur -->
                <div class="flex gap-2">
                  <select ref="addSupervisorSelect"
                    class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 outline-none bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    <option value="">— Ajouter un superviseur —</option>
                    <option v-for="s in unselectedSupervisors"
                      :key="s.id" :value="s.id">
                      {{ s.name }}
                    </option>
                  </select>
                  <button type="button" @click="handleAddSupervisorClick"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                    Ajouter
                  </button>
                </div>
              </div>

              <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Nouveau mot de passe
                  <span class="text-gray-400 dark:text-gray-500 font-normal">(laisser vide pour conserver l'actuel)</span>
                </label>
                <input v-model="editForm.password" type="password" autocomplete="new-password"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
              </div>
            </div>
            <div class="flex gap-3 pt-2">
              <button type="button" @click="closeEdit"
                class="flex-1 border border-gray-300 dark:border-gray-600 py-2 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
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
