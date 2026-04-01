<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'
import axios from 'axios'
import {
  PlusIcon, TrashIcon, PencilIcon, XMarkIcon,
  CalendarDaysIcon, FlagIcon, TagIcon,
  Squares2X2Icon, ListBulletIcon,
  ChevronRightIcon, ChevronDownIcon,
} from '@heroicons/vue/24/outline'
import { CheckCircleIcon } from '@heroicons/vue/24/solid'

// ── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
  projects: Array,
  labels:   Array,
  users:    Array,
  isAdmin:  Boolean,
  myUserId: Number,
})

// ── État ─────────────────────────────────────────────────────────────────────
const view            = ref('list')
const projects        = ref([...props.projects])
const labels          = ref([...props.labels])
const selectedProject = ref(null)
const tasks           = ref([])
const loadingTasks    = ref(false)
const expandedTasks   = ref(new Set())

const showProjectModal = ref(false)
const showTaskModal    = ref(false)
const showDetailModal  = ref(false)
const showLabelModal   = ref(false)
const editingProject   = ref(null)
const editingTask      = ref(null)
const detailTask       = ref(null)
const parentTaskId     = ref(null)

const projectForm = ref({ name: '', color: '#6366f1', description: '', is_shared: false })
const taskForm    = ref({ title: '', description: '', due_date: '', priority: 'none', status: 'todo', assigned_to: null, label_ids: [] })
const labelForm   = ref({ name: '', color: '#64748b' })

// ── Constantes ───────────────────────────────────────────────────────────────
const PRIORITY_LABELS = { none: '—', low: 'Basse', medium: 'Moyenne', high: 'Haute', urgent: 'Urgent' }
const PRIORITY_COLORS = { none: 'text-gray-400', low: 'text-blue-500', medium: 'text-yellow-500', high: 'text-orange-500', urgent: 'text-red-600' }
const STATUS_LABELS   = { todo: 'À faire', in_progress: 'En cours', done: 'Terminé' }
const STATUS_COLORS   = { todo: 'bg-gray-100 text-gray-700', in_progress: 'bg-blue-100 text-blue-700', done: 'bg-green-100 text-green-700' }
const KANBAN_COLS     = [
  { key: 'todo',        label: 'À faire',   ring: 'ring-gray-200' },
  { key: 'in_progress', label: 'En cours',  ring: 'ring-blue-200' },
  { key: 'done',        label: 'Terminé',   ring: 'ring-green-200' },
]
const PALETTE = ['#6366f1','#8b5cf6','#ec4899','#ef4444','#f97316','#eab308','#22c55e','#14b8a6','#3b82f6','#64748b']

// ── Computed ─────────────────────────────────────────────────────────────────
const tasksByStatus = computed(() => ({
  todo:        tasks.value.filter(t => t.status === 'todo'),
  in_progress: tasks.value.filter(t => t.status === 'in_progress'),
  done:        tasks.value.filter(t => t.status === 'done'),
}))

const progressPct = computed(() => {
  if (!selectedProject.value?.task_count) return 0
  return Math.round((selectedProject.value.done_count / selectedProject.value.task_count) * 100)
})

// ─────────────────────────────────────────────────────────────────────────────
// PROJETS
// ─────────────────────────────────────────────────────────────────────────────

async function selectProject(project) {
  selectedProject.value = project
  expandedTasks.value = new Set()
  await loadTasks()
}

async function loadTasks() {
  if (!selectedProject.value) return
  loadingTasks.value = true
  try {
    const { data } = await axios.get(route('todo.tasks.index', selectedProject.value.id))
    tasks.value = data
  } finally {
    loadingTasks.value = false
  }
}

function openCreateProject() {
  editingProject.value = null
  projectForm.value = { name: '', color: '#6366f1', description: '', is_shared: false }
  showProjectModal.value = true
}

function openEditProject(project, e) {
  e.stopPropagation()
  editingProject.value = project
  projectForm.value = { name: project.name, color: project.color, description: project.description ?? '', is_shared: project.is_shared }
  showProjectModal.value = true
}

async function saveProject() {
  try {
    if (editingProject.value) {
      await axios.put(route('todo.projects.update', editingProject.value.id), projectForm.value)
      const idx = projects.value.findIndex(p => p.id === editingProject.value.id)
      if (idx !== -1) Object.assign(projects.value[idx], projectForm.value)
      if (selectedProject.value?.id === editingProject.value.id) Object.assign(selectedProject.value, projectForm.value)
    } else {
      const { data } = await axios.post(route('todo.projects.store'), projectForm.value)
      projects.value.push(data)
    }
    showProjectModal.value = false
  } catch (e) { alert(e.response?.data?.message ?? 'Erreur') }
}

async function deleteProject(project, e) {
  e.stopPropagation()
  if (!confirm(`Supprimer le projet "${project.name}" et toutes ses tâches ?`)) return
  await axios.delete(route('todo.projects.destroy', project.id))
  projects.value = projects.value.filter(p => p.id !== project.id)
  if (selectedProject.value?.id === project.id) { selectedProject.value = null; tasks.value = [] }
}

// ─────────────────────────────────────────────────────────────────────────────
// TÂCHES
// ─────────────────────────────────────────────────────────────────────────────

function openCreateTask(status = 'todo', parentId = null) {
  editingTask.value = null
  parentTaskId.value = parentId
  taskForm.value = { title: '', description: '', due_date: '', priority: 'none', status, assigned_to: null, label_ids: [] }
  showTaskModal.value = true
}

function openEditTask(task) {
  editingTask.value = task
  parentTaskId.value = task.parent_task_id
  taskForm.value = {
    title: task.title, description: task.description ?? '', due_date: task.due_date ?? '',
    priority: task.priority, status: task.status,
    assigned_to: task.assigned_to?.id ?? null,
    label_ids: task.labels.map(l => l.id),
  }
  showTaskModal.value = true
}

function openDetail(task) {
  detailTask.value = task
  showDetailModal.value = true
}

async function saveTask() {
  try {
    const payload = { ...taskForm.value, parent_task_id: parentTaskId.value }
    if (editingTask.value) {
      const { data } = await axios.patch(route('todo.tasks.update', editingTask.value.id), payload)
      replaceTask(data)
      if (detailTask.value?.id === data.id) detailTask.value = data
    } else {
      const { data } = await axios.post(route('todo.tasks.store', selectedProject.value.id), payload)
      if (parentTaskId.value) { addSubtask(data) }
      else { tasks.value.push(data) }
      updateCounts(1, data.status === 'done' ? 1 : 0)
    }
    showTaskModal.value = false
  } catch (e) { alert(e.response?.data?.message ?? 'Erreur') }
}

async function quickStatus(task, newStatus) {
  const old = task.status
  task.status = newStatus
  try {
    const { data } = await axios.patch(route('todo.tasks.update', task.id), { status: newStatus })
    replaceTask(data)
    if (detailTask.value?.id === data.id) detailTask.value = data
    if (old !== 'done' && newStatus === 'done') updateCounts(0, 1)
    else if (old === 'done' && newStatus !== 'done') updateCounts(0, -1)
  } catch { task.status = old }
}

async function deleteTask(task) {
  if (!confirm(`Supprimer "${task.title}" ?`)) return
  await axios.delete(route('todo.tasks.destroy', task.id))
  removeTask(task.id)
  if (showDetailModal.value && detailTask.value?.id === task.id) showDetailModal.value = false
  updateCounts(-1, task.status === 'done' ? -1 : 0)
}

// helpers
function replaceTask(updated) {
  const idx = tasks.value.findIndex(t => t.id === updated.id)
  if (idx !== -1) { tasks.value[idx] = updated; return }
  for (const t of tasks.value) {
    const si = t.subtasks.findIndex(s => s.id === updated.id)
    if (si !== -1) { t.subtasks[si] = updated; return }
  }
}
function removeTask(id) {
  const idx = tasks.value.findIndex(t => t.id === id)
  if (idx !== -1) { tasks.value.splice(idx, 1); return }
  for (const t of tasks.value) {
    const si = t.subtasks.findIndex(s => s.id === id)
    if (si !== -1) { t.subtasks.splice(si, 1); return }
  }
}
function addSubtask(newTask) {
  const parent = tasks.value.find(t => t.id === newTask.parent_task_id)
  if (parent) parent.subtasks.push(newTask)
}
function updateCounts(taskDelta, doneDelta) {
  if (!selectedProject.value) return
  selectedProject.value.task_count += taskDelta
  selectedProject.value.done_count += doneDelta
  const p = projects.value.find(p => p.id === selectedProject.value.id)
  if (p) { p.task_count += taskDelta; p.done_count += doneDelta }
}
function toggleExpand(id) {
  const s = new Set(expandedTasks.value)
  if (s.has(id)) s.delete(id); else s.add(id)
  expandedTasks.value = s
}

// ─────────────────────────────────────────────────────────────────────────────
// LABELS
// ─────────────────────────────────────────────────────────────────────────────

async function saveLabel() {
  try {
    const { data } = await axios.post(route('todo.labels.store'), labelForm.value)
    labels.value.push(data)
    labelForm.value = { name: '', color: '#64748b' }
  } catch (e) { alert(e.response?.data?.message ?? 'Erreur') }
}

async function deleteLabel(label) {
  if (!confirm(`Supprimer l'étiquette "${label.name}" ?`)) return
  await axios.delete(route('todo.labels.destroy', label.id))
  labels.value = labels.value.filter(l => l.id !== label.id)
}

function toggleLabel(id) {
  const ids = taskForm.value.label_ids
  const i = ids.indexOf(id)
  if (i === -1) ids.push(id); else ids.splice(i, 1)
}

// ─────────────────────────────────────────────────────────────────────────────
// UTILS
// ─────────────────────────────────────────────────────────────────────────────
function formatDate(d) {
  if (!d) return null
  return new Date(d + 'T00:00:00').toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' })
}
function isOverdue(d) {
  return d && new Date(d + 'T23:59:59') < new Date()
}
function initials(name) {
  return name?.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2) ?? '?'
}
</script>

<template>
  <AppLayout title="To-Do List">
    <div class="flex h-[calc(100vh-4rem)] overflow-hidden">

      <!-- ══ SIDEBAR PROJETS ═══════════════════════════════════════════════ -->
      <aside class="w-64 shrink-0 bg-gray-50 border-r flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b bg-white">
          <h2 class="font-semibold text-sm text-gray-800">Projets</h2>
          <button @click="openCreateProject" title="Nouveau projet"
            class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 hover:text-indigo-600 transition">
            <PlusIcon class="h-4 w-4" />
          </button>
        </div>

        <nav class="flex-1 overflow-y-auto py-2 px-2 space-y-0.5">
          <button v-for="p in projects" :key="p.id"
            @click="selectProject(p)"
            class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-left transition group text-sm"
            :class="selectedProject?.id === p.id ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:bg-gray-100'">
            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: p.color }" />
            <span class="flex-1 truncate">{{ p.name }}</span>
            <span class="text-xs text-gray-400 shrink-0">{{ p.done_count }}/{{ p.task_count }}</span>
            <span class="hidden group-hover:flex items-center">
              <button @click="openEditProject(p, $event)" class="p-0.5 hover:text-indigo-600 rounded" title="Modifier">
                <PencilIcon class="h-3 w-3" />
              </button>
              <button @click="deleteProject(p, $event)" class="p-0.5 hover:text-red-600 rounded" title="Supprimer">
                <TrashIcon class="h-3 w-3" />
              </button>
            </span>
          </button>
          <p v-if="!projects.length" class="text-xs text-gray-400 text-center px-3 py-6">
            Aucun projet —
            <button @click="openCreateProject" class="text-indigo-500 hover:underline">en créer un</button>
          </p>
        </nav>

        <div v-if="isAdmin" class="border-t px-3 py-2.5">
          <button @click="showLabelModal = true"
            class="w-full flex items-center gap-2 text-xs text-gray-500 hover:text-indigo-600 transition">
            <TagIcon class="h-3.5 w-3.5" /> Gérer les étiquettes
          </button>
        </div>
      </aside>

      <!-- ══ ZONE PRINCIPALE ═══════════════════════════════════════════════ -->
      <div class="flex-1 flex flex-col overflow-hidden bg-white">

        <!-- Aucun projet -->
        <div v-if="!selectedProject" class="flex-1 flex items-center justify-center text-center p-8">
          <div>
            <p class="text-5xl mb-4">📋</p>
            <p class="text-lg font-semibold text-gray-700 mb-1">Votre espace de tâches</p>
            <p class="text-sm text-gray-500 mb-4">Sélectionnez un projet à gauche ou créez-en un nouveau.</p>
            <button @click="openCreateProject"
              class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
              <PlusIcon class="h-4 w-4" /> Nouveau projet
            </button>
          </div>
        </div>

        <!-- Projet sélectionné -->
        <template v-else>
          <!-- En-tête -->
          <div class="border-b px-6 py-4 flex items-center gap-4 shrink-0">
            <span class="w-3 h-3 rounded-full shrink-0" :style="{ background: selectedProject.color }" />
            <div class="flex-1 min-w-0">
              <h1 class="text-lg font-bold text-gray-900 truncate">{{ selectedProject.name }}</h1>
              <p v-if="selectedProject.description" class="text-xs text-gray-500 truncate mt-0.5">
                {{ selectedProject.description }}
              </p>
            </div>
            <!-- Barre de progression -->
            <div class="hidden sm:flex items-center gap-2 shrink-0">
              <div class="w-24 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500"
                  :style="{ width: progressPct + '%', background: selectedProject.color }" />
              </div>
              <span class="text-xs text-gray-500 w-9 text-right">{{ progressPct }}%</span>
            </div>
            <!-- Vues -->
            <div class="flex rounded-lg border overflow-hidden shrink-0">
              <button @click="view = 'list'"
                class="px-2.5 py-1.5 transition"
                :class="view === 'list' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-gray-50'"
                title="Vue liste">
                <ListBulletIcon class="h-4 w-4" />
              </button>
              <button @click="view = 'kanban'"
                class="px-2.5 py-1.5 border-l transition"
                :class="view === 'kanban' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:bg-gray-50'"
                title="Vue kanban">
                <Squares2X2Icon class="h-4 w-4" />
              </button>
            </div>
            <!-- Nouvelle tâche -->
            <button @click="openCreateTask('todo')"
              class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition shrink-0">
              <PlusIcon class="h-4 w-4" /> Tâche
            </button>
          </div>

          <!-- Chargement -->
          <div v-if="loadingTasks" class="flex-1 flex items-center justify-center">
            <span class="text-gray-400 text-sm animate-pulse">Chargement des tâches…</span>
          </div>

          <!-- ══ VUE LISTE ════════════════════════════════════════════════ -->
          <div v-else-if="view === 'list'" class="flex-1 overflow-y-auto px-6 py-5">
            <div v-for="col in KANBAN_COLS" :key="col.key" class="mb-8">
              <!-- Entête groupe -->
              <div class="flex items-center gap-2 mb-3">
                <span class="text-xs font-semibold uppercase tracking-wide px-2.5 py-1 rounded-full"
                  :class="STATUS_COLORS[col.key]">
                  {{ col.label }}
                </span>
                <span class="text-xs text-gray-400">{{ tasksByStatus[col.key].length }}</span>
                <button @click="openCreateTask(col.key)"
                  class="ml-auto p-0.5 text-gray-400 hover:text-indigo-600 transition" title="Ajouter une tâche">
                  <PlusIcon class="h-4 w-4" />
                </button>
              </div>

              <!-- Tâches -->
              <div class="space-y-1.5">
                <p v-if="!tasksByStatus[col.key].length" class="text-xs text-gray-400 italic pl-1">
                  Aucune tâche —
                  <button @click="openCreateTask(col.key)" class="hover:underline text-indigo-400">en ajouter une</button>
                </p>

                <template v-for="task in tasksByStatus[col.key]" :key="task.id">
                  <!-- Tâche principale -->
                  <div class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl border hover:border-indigo-200 hover:shadow-sm transition group cursor-pointer bg-white"
                    @click="openDetail(task)">
                    <!-- Checkbox -->
                    <button @click.stop="quickStatus(task, task.status === 'done' ? 'todo' : 'done')"
                      class="shrink-0 transition">
                      <CheckCircleIcon v-if="task.status === 'done'" class="h-5 w-5 text-green-500" />
                      <div v-else class="h-5 w-5 rounded-full border-2 border-gray-300 group-hover:border-indigo-400 transition" />
                    </button>
                    <!-- Titre -->
                    <span class="flex-1 text-sm font-medium text-gray-800 truncate"
                      :class="{ 'line-through text-gray-400': task.status === 'done' }">
                      {{ task.title }}
                    </span>
                    <!-- Labels -->
                    <div class="hidden sm:flex gap-1 shrink-0">
                      <span v-for="l in task.labels.slice(0, 2)" :key="l.id"
                        class="px-1.5 py-0.5 rounded-full text-white text-xs"
                        :style="{ background: l.color }">
                        {{ l.name }}
                      </span>
                    </div>
                    <!-- Priorité -->
                    <FlagIcon v-if="task.priority !== 'none'" class="h-4 w-4 shrink-0"
                      :class="PRIORITY_COLORS[task.priority]" :title="PRIORITY_LABELS[task.priority]" />
                    <!-- Date -->
                    <span v-if="task.due_date" class="text-xs shrink-0"
                      :class="task.status !== 'done' && isOverdue(task.due_date) ? 'text-red-600 font-semibold' : 'text-gray-400'">
                      {{ formatDate(task.due_date) }}
                    </span>
                    <!-- Assigné -->
                    <span v-if="task.assigned_to"
                      class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold flex items-center justify-center shrink-0"
                      :title="task.assigned_to.name">
                      {{ initials(task.assigned_to.name) }}
                    </span>
                    <!-- Sous-tâches toggle -->
                    <button v-if="task.subtasks.length"
                      @click.stop="toggleExpand(task.id)"
                      class="text-xs text-gray-400 hover:text-indigo-600 flex items-center gap-0.5 shrink-0">
                      <component :is="expandedTasks.has(task.id) ? ChevronDownIcon : ChevronRightIcon" class="h-3.5 w-3.5" />
                      {{ task.subtasks.filter(s => s.status === 'done').length }}/{{ task.subtasks.length }}
                    </button>
                    <!-- Actions -->
                    <div class="hidden group-hover:flex items-center gap-1 shrink-0">
                      <button @click.stop="openCreateTask(task.status, task.id)"
                        class="p-1 rounded hover:bg-indigo-50 hover:text-indigo-600 text-gray-400" title="Ajouter sous-tâche">
                        <PlusIcon class="h-3.5 w-3.5" />
                      </button>
                      <button @click.stop="openEditTask(task)"
                        class="p-1 rounded hover:bg-indigo-50 hover:text-indigo-600 text-gray-400" title="Modifier">
                        <PencilIcon class="h-3.5 w-3.5" />
                      </button>
                      <button @click.stop="deleteTask(task)"
                        class="p-1 rounded hover:bg-red-50 hover:text-red-500 text-gray-400" title="Supprimer">
                        <TrashIcon class="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </div>

                  <!-- Sous-tâches -->
                  <div v-if="expandedTasks.has(task.id)" class="ml-8 space-y-1 mt-1">
                    <div v-for="sub in task.subtasks" :key="sub.id"
                      class="flex items-center gap-2.5 px-3 py-2 rounded-xl border border-dashed hover:border-indigo-200 transition group cursor-pointer bg-gray-50/50"
                      @click="openDetail(sub)">
                      <button @click.stop="quickStatus(sub, sub.status === 'done' ? 'todo' : 'done')" class="shrink-0">
                        <CheckCircleIcon v-if="sub.status === 'done'" class="h-4 w-4 text-green-500" />
                        <div v-else class="h-4 w-4 rounded-full border-2 border-gray-300 group-hover:border-indigo-400 transition" />
                      </button>
                      <span class="flex-1 text-sm text-gray-700 truncate"
                        :class="{ 'line-through text-gray-400': sub.status === 'done' }">
                        {{ sub.title }}
                      </span>
                      <FlagIcon v-if="sub.priority !== 'none'" class="h-3.5 w-3.5 shrink-0"
                        :class="PRIORITY_COLORS[sub.priority]" />
                      <span v-if="sub.due_date" class="text-xs shrink-0"
                        :class="sub.status !== 'done' && isOverdue(sub.due_date) ? 'text-red-600 font-semibold' : 'text-gray-400'">
                        {{ formatDate(sub.due_date) }}
                      </span>
                      <div class="hidden group-hover:flex items-center gap-1 shrink-0">
                        <button @click.stop="openEditTask(sub)"
                          class="p-0.5 rounded hover:text-indigo-600 text-gray-400">
                          <PencilIcon class="h-3 w-3" />
                        </button>
                        <button @click.stop="deleteTask(sub)"
                          class="p-0.5 rounded hover:text-red-500 text-gray-400">
                          <TrashIcon class="h-3 w-3" />
                        </button>
                      </div>
                    </div>
                  </div>
                </template>
              </div>
            </div>
          </div>

          <!-- ══ VUE KANBAN ══════════════════════════════════════════════ -->
          <div v-else class="flex-1 flex gap-4 overflow-x-auto px-6 py-5">
            <div v-for="col in KANBAN_COLS" :key="col.key" class="flex flex-col w-72 shrink-0">
              <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-semibold px-2.5 py-1 rounded-full"
                  :class="STATUS_COLORS[col.key]">
                  {{ col.label }}
                  <span class="ml-1 opacity-70 text-xs">{{ tasksByStatus[col.key].length }}</span>
                </span>
                <button @click="openCreateTask(col.key)"
                  class="p-1 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-indigo-600 transition">
                  <PlusIcon class="h-4 w-4" />
                </button>
              </div>

              <div class="flex-1 space-y-2.5 overflow-y-auto">
                <!-- Carte kanban -->
                <div v-for="task in tasksByStatus[col.key]" :key="task.id"
                  @click="openDetail(task)"
                  class="bg-white rounded-xl border shadow-sm p-3.5 cursor-pointer hover:shadow-md hover:border-indigo-200 transition group">
                  <!-- Labels -->
                  <div v-if="task.labels.length" class="flex flex-wrap gap-1 mb-2">
                    <span v-for="l in task.labels" :key="l.id"
                      class="px-1.5 py-0.5 rounded-full text-white text-xs font-medium"
                      :style="{ background: l.color }">{{ l.name }}</span>
                  </div>
                  <!-- Titre + check -->
                  <div class="flex items-start gap-2">
                    <button @click.stop="quickStatus(task, task.status === 'done' ? 'todo' : 'done')" class="mt-0.5 shrink-0">
                      <CheckCircleIcon v-if="task.status === 'done'" class="h-4 w-4 text-green-500" />
                      <div v-else class="h-4 w-4 rounded-full border-2 border-gray-300 group-hover:border-indigo-400 transition" />
                    </button>
                    <p class="text-sm font-medium text-gray-800 flex-1 leading-snug"
                      :class="{ 'line-through text-gray-400': task.status === 'done' }">
                      {{ task.title }}
                    </p>
                    <div class="hidden group-hover:flex gap-1 shrink-0">
                      <button @click.stop="openEditTask(task)"
                        class="p-0.5 rounded hover:text-indigo-600 text-gray-400"><PencilIcon class="h-3.5 w-3.5"/></button>
                      <button @click.stop="deleteTask(task)"
                        class="p-0.5 rounded hover:text-red-500 text-gray-400"><TrashIcon class="h-3.5 w-3.5"/></button>
                    </div>
                  </div>
                  <!-- Description preview -->
                  <p v-if="task.description" class="text-xs text-gray-400 mt-1.5 line-clamp-2">{{ task.description }}</p>
                  <!-- Footer -->
                  <div class="mt-2.5 flex items-center gap-2 flex-wrap">
                    <span v-if="task.priority !== 'none'" class="text-xs font-medium flex items-center gap-0.5"
                      :class="PRIORITY_COLORS[task.priority]">
                      <FlagIcon class="h-3 w-3" /> {{ PRIORITY_LABELS[task.priority] }}
                    </span>
                    <span v-if="task.due_date" class="text-xs flex items-center gap-0.5"
                      :class="task.status !== 'done' && isOverdue(task.due_date) ? 'text-red-600 font-semibold' : 'text-gray-400'">
                      <CalendarDaysIcon class="h-3 w-3" /> {{ formatDate(task.due_date) }}
                    </span>
                    <span v-if="task.subtasks.length" class="text-xs text-gray-400">
                      ↳ {{ task.subtasks.filter(s => s.status === 'done').length }}/{{ task.subtasks.length }}
                    </span>
                    <span v-if="task.assigned_to"
                      class="ml-auto w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold flex items-center justify-center"
                      :title="task.assigned_to.name">
                      {{ initials(task.assigned_to.name) }}
                    </span>
                  </div>
                </div>

                <!-- Carte vide -->
                <button v-if="!tasksByStatus[col.key].length"
                  @click="openCreateTask(col.key)"
                  class="w-full border-2 border-dashed border-gray-200 rounded-xl p-5 text-xs text-gray-400 hover:border-indigo-300 hover:text-indigo-500 transition text-center">
                  + Ajouter une tâche
                </button>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- ════ MODAL PROJET ════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showProjectModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="showProjectModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="font-semibold text-gray-900">{{ editingProject ? 'Modifier le projet' : 'Nouveau projet' }}</h3>
            <button @click="showProjectModal = false"><XMarkIcon class="h-5 w-5 text-gray-400 hover:text-gray-600" /></button>
          </div>
          <form @submit.prevent="saveProject" class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nom *</label>
              <input v-model="projectForm.name" required autofocus
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Couleur</label>
              <div class="flex flex-wrap gap-2">
                <button v-for="c in PALETTE" :key="c" type="button" @click="projectForm.color = c"
                  class="w-7 h-7 rounded-full transition ring-offset-2"
                  :style="{ background: c }"
                  :class="projectForm.color === c ? 'ring-2 ring-gray-700' : 'hover:scale-110'" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="projectForm.description" rows="2"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                placeholder="Description optionnelle…" />
            </div>
            <div v-if="isAdmin" class="flex items-center gap-3">
              <input type="checkbox" id="is_shared" v-model="projectForm.is_shared" class="rounded border-gray-300" />
              <label for="is_shared" class="text-sm text-gray-700">Projet partagé (visible par tous les utilisateurs)</label>
            </div>
            <div class="flex gap-3 pt-1">
              <button type="button" @click="showProjectModal = false"
                class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50 transition">Annuler</button>
              <button type="submit"
                class="flex-1 bg-indigo-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                {{ editingProject ? 'Modifier' : 'Créer le projet' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ════ MODAL TÂCHE ══════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showTaskModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="showTaskModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between px-6 py-4 border-b sticky top-0 bg-white rounded-t-2xl">
            <h3 class="font-semibold text-gray-900">
              {{ editingTask ? 'Modifier la tâche' : parentTaskId ? 'Nouvelle sous-tâche' : 'Nouvelle tâche' }}
            </h3>
            <button @click="showTaskModal = false"><XMarkIcon class="h-5 w-5 text-gray-400 hover:text-gray-600" /></button>
          </div>
          <form @submit.prevent="saveTask" class="p-6 space-y-4">
            <!-- Titre -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Titre *</label>
              <input v-model="taskForm.title" required autofocus
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
            </div>
            <!-- Description -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea v-model="taskForm.description" rows="3"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400"
                placeholder="Détails, notes, liens…" />
            </div>
            <!-- Date + Priorité -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Échéance</label>
                <input v-model="taskForm.due_date" type="date"
                  class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Priorité</label>
                <select v-model="taskForm.priority"
                  class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                  <option v-for="(lbl, key) in PRIORITY_LABELS" :key="key" :value="key">{{ lbl }}</option>
                </select>
              </div>
            </div>
            <!-- Statut -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
              <div class="flex gap-2">
                <button v-for="(lbl, key) in STATUS_LABELS" :key="key" type="button"
                  @click="taskForm.status = key"
                  class="flex-1 py-1.5 rounded-lg text-xs font-medium border transition"
                  :class="taskForm.status === key ? STATUS_COLORS[key] + ' border-transparent' : 'border-gray-200 text-gray-500 hover:bg-gray-50'">
                  {{ lbl }}
                </button>
              </div>
            </div>
            <!-- Assigné à (admin) -->
            <div v-if="isAdmin && users.length">
              <label class="block text-sm font-medium text-gray-700 mb-1">Assigné à</label>
              <select v-model="taskForm.assigned_to"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option :value="null">— Non assigné</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <!-- Étiquettes -->
            <div v-if="labels.length">
              <label class="block text-sm font-medium text-gray-700 mb-2">Étiquettes</label>
              <div class="flex flex-wrap gap-2">
                <button v-for="label in labels" :key="label.id" type="button"
                  @click="toggleLabel(label.id)"
                  class="px-2.5 py-1 rounded-full text-xs font-medium transition border"
                  :class="taskForm.label_ids.includes(label.id)
                    ? 'text-white border-transparent'
                    : 'text-gray-600 border-gray-200 hover:border-gray-400'"
                  :style="taskForm.label_ids.includes(label.id) ? { background: label.color, borderColor: label.color } : {}">
                  {{ label.name }}
                </button>
              </div>
            </div>
            <div class="flex gap-3 pt-1">
              <button type="button" @click="showTaskModal = false"
                class="flex-1 border border-gray-300 py-2 rounded-lg text-sm hover:bg-gray-50 transition">Annuler</button>
              <button type="submit"
                class="flex-1 bg-indigo-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                {{ editingTask ? 'Modifier' : 'Créer' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ════ MODAL DÉTAIL ════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showDetailModal && detailTask"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="showDetailModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl max-h-[90vh] overflow-y-auto">
          <!-- En-tête -->
          <div class="flex items-start justify-between px-6 py-5 border-b">
            <div class="flex-1 pr-4">
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium mb-2"
                :class="STATUS_COLORS[detailTask.status]">
                {{ STATUS_LABELS[detailTask.status] }}
              </span>
              <h3 class="font-bold text-gray-900 text-xl leading-tight">{{ detailTask.title }}</h3>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <button @click="openEditTask(detailTask); showDetailModal = false"
                class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-indigo-600 transition" title="Modifier">
                <PencilIcon class="h-4 w-4" />
              </button>
              <button @click="deleteTask(detailTask)"
                class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-red-500 transition" title="Supprimer">
                <TrashIcon class="h-4 w-4" />
              </button>
              <button @click="showDetailModal = false" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 transition">
                <XMarkIcon class="h-5 w-5" />
              </button>
            </div>
          </div>

          <div class="p-6 space-y-5">
            <!-- Description -->
            <div v-if="detailTask.description">
              <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Description</p>
              <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{{ detailTask.description }}</p>
            </div>

            <!-- Infos -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Priorité</p>
                <span class="text-sm font-medium flex items-center gap-1" :class="PRIORITY_COLORS[detailTask.priority]">
                  <FlagIcon class="h-3.5 w-3.5" /> {{ PRIORITY_LABELS[detailTask.priority] }}
                </span>
              </div>
              <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Échéance</p>
                <span v-if="detailTask.due_date" class="text-sm flex items-center gap-1"
                  :class="detailTask.status !== 'done' && isOverdue(detailTask.due_date) ? 'text-red-600 font-semibold' : 'text-gray-700'">
                  <CalendarDaysIcon class="h-3.5 w-3.5" /> {{ formatDate(detailTask.due_date) }}
                  <span v-if="detailTask.status !== 'done' && isOverdue(detailTask.due_date)" class="text-xs">(en retard)</span>
                </span>
                <span v-else class="text-sm text-gray-400">—</span>
              </div>
              <div v-if="detailTask.assigned_to">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Assigné à</p>
                <div class="flex items-center gap-2">
                  <span class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold flex items-center justify-center">
                    {{ initials(detailTask.assigned_to.name) }}
                  </span>
                  <span class="text-sm text-gray-700">{{ detailTask.assigned_to.name }}</span>
                </div>
              </div>
            </div>

            <!-- Labels -->
            <div v-if="detailTask.labels.length">
              <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Étiquettes</p>
              <div class="flex flex-wrap gap-1.5">
                <span v-for="l in detailTask.labels" :key="l.id"
                  class="px-2.5 py-0.5 rounded-full text-white text-xs font-medium"
                  :style="{ background: l.color }">
                  {{ l.name }}
                </span>
              </div>
            </div>

            <!-- Changer statut -->
            <div>
              <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Changer le statut</p>
              <div class="flex gap-2">
                <button v-for="(lbl, key) in STATUS_LABELS" :key="key"
                  @click="quickStatus(detailTask, key)"
                  class="flex-1 py-2 rounded-lg text-xs font-medium border transition"
                  :class="detailTask.status === key ? STATUS_COLORS[key] + ' border-transparent' : 'border-gray-200 text-gray-500 hover:bg-gray-50'">
                  {{ lbl }}
                </button>
              </div>
            </div>

            <!-- Sous-tâches -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                  Sous-tâches
                  <span v-if="detailTask.subtasks.length" class="normal-case text-gray-400 ml-1">
                    ({{ detailTask.subtasks.filter(s => s.status === 'done').length }}/{{ detailTask.subtasks.length }})
                  </span>
                </p>
                <button @click="openCreateTask(detailTask.status, detailTask.id); showDetailModal = false"
                  class="text-xs text-indigo-600 hover:underline flex items-center gap-1">
                  <PlusIcon class="h-3 w-3" /> Ajouter
                </button>
              </div>
              <div v-if="detailTask.subtasks.length" class="space-y-1.5">
                <div v-for="sub in detailTask.subtasks" :key="sub.id"
                  class="flex items-center gap-2.5 py-2 px-3 rounded-lg hover:bg-gray-50 cursor-pointer"
                  @click="detailTask = sub">
                  <button @click.stop="quickStatus(sub, sub.status === 'done' ? 'todo' : 'done')">
                    <CheckCircleIcon v-if="sub.status === 'done'" class="h-4 w-4 text-green-500" />
                    <div v-else class="h-4 w-4 rounded-full border-2 border-gray-300 hover:border-indigo-400 transition" />
                  </button>
                  <span class="text-sm flex-1" :class="{ 'line-through text-gray-400': sub.status === 'done' }">
                    {{ sub.title }}
                  </span>
                  <FlagIcon v-if="sub.priority !== 'none'" class="h-3.5 w-3.5 shrink-0" :class="PRIORITY_COLORS[sub.priority]" />
                </div>
              </div>
              <p v-else class="text-xs text-gray-400 italic">Aucune sous-tâche pour le moment</p>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ════ MODAL ÉTIQUETTES ════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showLabelModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        @click.self="showLabelModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm">
          <div class="flex items-center justify-between px-5 py-4 border-b">
            <h3 class="font-semibold text-gray-900">Étiquettes</h3>
            <button @click="showLabelModal = false"><XMarkIcon class="h-5 w-5 text-gray-400 hover:text-gray-600" /></button>
          </div>
          <div class="p-5">
            <div class="space-y-1.5 mb-5 max-h-52 overflow-y-auto">
              <div v-for="label in labels" :key="label.id"
                class="flex items-center gap-2.5 py-1.5 px-2 rounded-lg hover:bg-gray-50">
                <span class="w-3.5 h-3.5 rounded-full shrink-0" :style="{ background: label.color }" />
                <span class="text-sm flex-1 text-gray-700">{{ label.name }}</span>
                <button v-if="isAdmin" @click="deleteLabel(label)"
                  class="text-gray-400 hover:text-red-500 transition"><TrashIcon class="h-3.5 w-3.5"/></button>
              </div>
              <p v-if="!labels.length" class="text-xs text-gray-400 italic text-center py-3">Aucune étiquette</p>
            </div>
            <form @submit.prevent="saveLabel" class="flex gap-2 items-center">
              <input v-model="labelForm.color" type="color" class="h-9 w-9 rounded border cursor-pointer p-0.5 shrink-0" />
              <input v-model="labelForm.name" placeholder="Nouvelle étiquette…" required
                class="flex-1 border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400" />
              <button type="submit"
                class="bg-indigo-600 text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 shrink-0">
                <PlusIcon class="h-4 w-4" />
              </button>
            </form>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>
