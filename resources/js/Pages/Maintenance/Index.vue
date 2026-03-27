<template>
    <AppLayout title="Maintenance">
        <div class="max-w-6xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Maintenance</h1>
                    <p class="text-sm text-gray-500 mt-0.5">
                        {{ activeCount }} en cours · {{ maintenances.length }} total
                    </p>
                </div>
                <button @click="openCreate" class="btn-primary flex items-center gap-2">
                    <PlusIcon class="w-4 h-4" />
                    Nouvelle fiche
                </button>
            </div>

            <!-- Filtres -->
            <div class="flex gap-3 mb-6">
                <button v-for="f in statusFilters" :key="f.value"
                        @click="filterStatus = f.value"
                        :class="['px-3 py-1.5 text-xs font-medium rounded-full border transition',
                            filterStatus === f.value
                                ? 'bg-blue-600 text-white border-blue-600'
                                : 'bg-white text-gray-600 border-gray-300 hover:border-blue-400']">
                    {{ f.label }}
                </button>
            </div>

            <!-- Liste -->
            <div v-if="filtered.length === 0" class="bg-white rounded-xl shadow-sm border p-12 text-center text-gray-400">
                <WrenchScrewdriverIcon class="w-12 h-12 mx-auto mb-3 opacity-30" />
                <p class="text-sm">Aucune fiche de maintenance</p>
            </div>

            <div class="space-y-3">
                <div v-for="m in filtered" :key="m.id"
                     class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', statusBadge(m.status_color)]">
                                    {{ m.status_label }}
                                </span>
                                <span class="text-xs text-gray-400">{{ m.asset_name }} · {{ m.asset_tag }}</span>
                            </div>
                            <h3 class="font-semibold text-gray-900">{{ m.title }}</h3>
                            <p v-if="m.description" class="text-sm text-gray-500 mt-1 line-clamp-2">{{ m.description }}</p>
                            <div class="flex flex-wrap gap-4 mt-2 text-xs text-gray-400">
                                <span v-if="m.provider">👷 {{ m.provider }}</span>
                                <span>📅 Début: {{ m.started_at }}</span>
                                <span v-if="m.expected_return_at">🔙 Retour prévu: {{ m.expected_return_at }}</span>
                                <span v-if="m.cost !== null">💶 {{ Number(m.cost).toFixed(2) }} €</span>
                            </div>
                            <div v-if="m.resolution_notes" class="mt-2 text-xs text-green-700 bg-green-50 rounded px-2 py-1">
                                ✅ {{ m.resolution_notes }}
                            </div>
                        </div>
                        <div class="flex gap-2 flex-shrink-0">
                            <button v-if="m.status !== 'resolved'" @click="openResolve(m)"
                                    class="text-xs bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 transition">
                                Résoudre
                            </button>
                            <button @click="openEdit(m)"
                                    class="text-xs border border-gray-300 text-gray-600 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition">
                                Modifier
                            </button>
                            <button @click="confirmDelete(m)"
                                    class="text-xs text-red-500 hover:text-red-700 px-2">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal création -->
            <div v-if="showCreateModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="px-6 py-4 border-b flex items-center justify-between sticky top-0 bg-white">
                        <h2 class="font-semibold text-gray-900">Nouvelle fiche de maintenance</h2>
                        <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                    <form @submit.prevent="submitCreate" class="px-6 py-4 space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Équipement <span class="text-red-500">*</span></label>
                            <select v-model="createForm.asset_id" required
                                    class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">— Choisir un équipement —</option>
                                <option v-for="a in assets" :key="a.id" :value="a.id">{{ a.name }} ({{ a.asset_tag }})</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Titre <span class="text-red-500">*</span></label>
                            <input v-model="createForm.title" type="text" required
                                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Description / Pièces</label>
                            <textarea v-model="createForm.description" rows="3"
                                      class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Prestataire</label>
                                <input v-model="createForm.provider" type="text"
                                       class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Coût (€)</label>
                                <input v-model="createForm.cost" type="number" step="0.01" min="0"
                                       class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Début <span class="text-red-500">*</span></label>
                                <input v-model="createForm.started_at" type="date" required
                                       class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Retour prévu</label>
                                <input v-model="createForm.expected_return_at" type="date"
                                       class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showCreateModal = false" class="btn-secondary">Annuler</button>
                            <button type="submit" class="btn-primary">Créer</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal résolution -->
            <div v-if="showResolveModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-md">
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h2 class="font-semibold text-gray-900">Résoudre la maintenance</h2>
                        <button @click="showResolveModal = false" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                    <form @submit.prevent="submitResolve" class="px-6 py-4 space-y-4">
                        <p class="text-sm text-gray-600">
                            L'équipement <strong>{{ resolvingMaintenance?.asset_name }}</strong> sera remis en <span class="text-green-600 font-medium">disponible</span>.
                        </p>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Notes de résolution</label>
                            <textarea v-model="resolveNotes" rows="3" placeholder="Pièces remplacées, interventions réalisées..."
                                      class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"></textarea>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showResolveModal = false" class="btn-secondary">Annuler</button>
                            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700">
                                Marquer comme résolu
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal édition -->
            <div v-if="showEditModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                    <div class="px-6 py-4 border-b flex items-center justify-between sticky top-0 bg-white">
                        <h2 class="font-semibold text-gray-900">Modifier la fiche</h2>
                        <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                    <form @submit.prevent="submitEdit" class="px-6 py-4 space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Titre <span class="text-red-500">*</span></label>
                            <input v-model="editForm.title" type="text" required
                                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                            <textarea v-model="editForm.description" rows="3"
                                      class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Prestataire</label>
                                <input v-model="editForm.provider" type="text"
                                       class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Coût (€)</label>
                                <input v-model="editForm.cost" type="number" step="0.01" min="0"
                                       class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Début</label>
                                <input v-model="editForm.started_at" type="date"
                                       class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Retour prévu</label>
                                <input v-model="editForm.expected_return_at" type="date"
                                       class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Statut</label>
                            <select v-model="editForm.status"
                                    class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="pending">En attente</option>
                                <option value="in_progress">En cours</option>
                                <option value="resolved">Résolu</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showEditModal = false" class="btn-secondary">Annuler</button>
                            <button type="submit" class="btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { PlusIcon, TrashIcon, XMarkIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    maintenances: { type: Array, default: () => [] },
    assets:       { type: Array, default: () => [] },
    filters:      { type: Object, default: () => ({}) },
})

const statusFilters = [
    { value: '',           label: 'Toutes' },
    { value: 'pending',    label: 'En attente' },
    { value: 'in_progress',label: 'En cours' },
    { value: 'resolved',   label: 'Résolues' },
]

const filterStatus = ref(props.filters?.status ?? '')

const filtered = computed(() =>
    filterStatus.value
        ? props.maintenances.filter(m => m.status === filterStatus.value)
        : props.maintenances
)

const activeCount = computed(() =>
    props.maintenances.filter(m => m.status !== 'resolved').length
)

function statusBadge(color) {
    const map = {
        yellow: 'bg-yellow-100 text-yellow-700',
        blue:   'bg-blue-100 text-blue-700',
        green:  'bg-green-100 text-green-700',
        gray:   'bg-gray-100 text-gray-700',
    }
    return map[color] ?? 'bg-gray-100 text-gray-700'
}

// ─── Create ────────────────────────────────────────────────────────────────
const showCreateModal = ref(false)
const createForm = ref({ asset_id: '', title: '', description: '', provider: '', cost: '', started_at: '', expected_return_at: '' })

function openCreate() {
    createForm.value = { asset_id: '', title: '', description: '', provider: '', cost: '', started_at: new Date().toISOString().slice(0, 10), expected_return_at: '' }
    showCreateModal.value = true
}

function submitCreate() {
    router.post(route('maintenance.store'), createForm.value, {
        onSuccess: () => { showCreateModal.value = false },
    })
}

// ─── Resolve ────────────────────────────────────────────────────────────────
const showResolveModal   = ref(false)
const resolvingMaintenance = ref(null)
const resolveNotes       = ref('')

function openResolve(m) {
    resolvingMaintenance.value = m
    resolveNotes.value = ''
    showResolveModal.value = true
}

function submitResolve() {
    router.post(route('maintenance.resolve', resolvingMaintenance.value.id), {
        resolution_notes: resolveNotes.value,
    }, {
        onSuccess: () => { showResolveModal.value = false },
    })
}

// ─── Edit ────────────────────────────────────────────────────────────────────
const showEditModal = ref(false)
const editingId     = ref(null)
const editForm      = ref({ title: '', description: '', provider: '', cost: '', started_at: '', expected_return_at: '', status: '' })

function openEdit(m) {
    editingId.value = m.id
    editForm.value = {
        title:               m.title,
        description:         m.description ?? '',
        provider:            m.provider ?? '',
        cost:                m.cost ?? '',
        started_at:          m.started_at ?? '',
        expected_return_at:  m.expected_return_at ?? '',
        status:              m.status,
    }
    showEditModal.value = true
}

function submitEdit() {
    router.put(route('maintenance.update', editingId.value), editForm.value, {
        onSuccess: () => { showEditModal.value = false },
    })
}

// ─── Delete ────────────────────────────────────────────────────────────────
function confirmDelete(m) {
    if (confirm(`Supprimer la fiche « ${m.title} » ?`)) {
        router.delete(route('maintenance.destroy', m.id))
    }
}
</script>
