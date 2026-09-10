<template>
    <AppLayout title="Signalements d'équipements">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900">📋 Signalements d'équipements</h1>
            </div>

            <!-- Filtres -->
            <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Statut</label>
                        <select v-model="filters.status" @change="applyFilters"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                            <option value="">Tous</option>
                            <option value="pending">En attente</option>
                            <option value="validated">Validés</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Type</label>
                        <select v-model="filters.type" @change="applyFilters"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                            <option value="">Tous</option>
                            <option value="damage">⚠️ Dégradation</option>
                            <option value="loss">❌ Perte</option>
                            <option value="malfunction">🔧 Dysfonctionnement</option>
                            <option value="observation">📋 Observation</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button @click="resetFilters"
                                class="w-full px-4 py-2 border border-gray-300 text-gray-600 text-sm rounded-lg hover:bg-gray-50 transition">
                            Réinitialiser
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Total</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ reports.total }}</p>
                </div>
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                    <p class="text-xs text-amber-700 uppercase tracking-wide">En attente</p>
                    <p class="text-2xl font-bold text-amber-900 mt-1">{{ pendingCount }}</p>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                    <p class="text-xs text-green-700 uppercase tracking-wide">Validés</p>
                    <p class="text-2xl font-bold text-green-900 mt-1">{{ validatedCount }}</p>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <p class="text-xs text-blue-700 uppercase tracking-wide">Cette semaine</p>
                    <p class="text-2xl font-bold text-blue-900 mt-1">{{ thisWeekCount }}</p>
                </div>
            </div>

            <!-- Liste des signalements -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div v-if="reports.data.length === 0" class="text-center py-12 text-gray-400">
                    <p class="text-lg">Aucun signalement</p>
                </div>
                <div v-else class="divide-y">
                    <div v-for="report in reports.data" :key="report.id"
                         class="p-4 hover:bg-gray-50 transition"
                         :class="{'bg-amber-50/30': !report.is_validated}">
                        <div class="flex items-start gap-4">
                            <!-- Icône -->
                            <div class="flex-shrink-0 text-2xl mt-1">
                                {{ reportTypeIcon(report.type) }}
                            </div>

                            <!-- Contenu -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <h3 class="text-sm font-semibold text-gray-900">{{ report.title }}</h3>
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                          :class="reportTypeBadgeClass(report.type)">
                                        {{ report.type_label }}
                                    </span>
                                    <span v-if="!report.is_validated"
                                          class="text-xs bg-amber-200 text-amber-800 px-2 py-0.5 rounded-full font-medium">
                                        En attente
                                    </span>
                                    <span v-else
                                          class="text-xs bg-green-200 text-green-800 px-2 py-0.5 rounded-full font-medium">
                                        ✓ Validé
                                    </span>
                                </div>

                                <!-- Équipement et utilisateur -->
                                <div class="flex items-center gap-3 text-xs text-gray-500 mb-2">
                                    <Link v-if="report.asset" :href="route('assets.show', report.asset.id)"
                                          class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 hover:underline">
                                        {{ report.asset.name }} ({{ report.asset.asset_tag }})
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                        </svg>
                                    </Link>
                                    <span>•</span>
                                    <span>Par {{ report.user?.name ?? 'N/A' }}</span>
                                    <span>•</span>
                                    <span>{{ report.created_at }}</span>
                                </div>

                                <!-- Contenu du signalement -->
                                <p class="text-sm text-gray-700 mb-2">{{ report.content }}</p>

                                <!-- Notes admin -->
                                <div v-if="report.admin_notes" class="mt-2 pt-2 border-t border-gray-200">
                                    <p class="text-xs text-gray-500">
                                        <span class="font-semibold text-gray-700">Note admin :</span>
                                        {{ report.admin_notes }}
                                    </p>
                                    <p v-if="report.validated_at" class="text-xs text-gray-400 mt-1">
                                        Validé par {{ report.validated_by?.name ?? 'N/A' }} le {{ report.validated_at }}
                                    </p>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex-shrink-0 flex items-center gap-1">
                                <button v-if="!report.is_validated"
                                        @click="openValidateModal(report)"
                                        class="text-xs bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg font-medium transition">
                                    ✓ Valider
                                </button>
                                <button @click="openEditModal(report)"
                                        class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition"
                                        title="Modifier">
                                    <PencilIcon class="w-4 h-4" />
                                </button>
                                <button @click="deleteReport(report.id)"
                                        class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                                        title="Supprimer">
                                    <TrashIcon class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="reports.last_page > 1" class="border-t px-4 py-3 flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        Affichage de {{ reports.from }} à {{ reports.to }} sur {{ reports.total }} signalements
                    </div>
                    <div class="flex gap-1">
                        <Link v-for="link in reports.links" :key="link.label"
                              :href="link.url"
                              :class="[
                                  'px-3 py-1 text-sm rounded-lg transition',
                                  link.active ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                              ]"
                              v-html="link.label" />
                    </div>
                </div>
            </div>

            <!-- Modal validation -->
            <div v-if="showValidateModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-screen items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showValidateModal = false"></div>
                    <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Valider le signalement</h2>
                        <div class="space-y-3 mb-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Notes admin (optionnel)</label>
                                <textarea v-model="validateForm.admin_notes" rows="3"
                                          placeholder="Ajouter des notes sur les actions prises..."
                                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-400 outline-none resize-none" />
                            </div>
                        </div>
                        <div class="flex gap-2 justify-end">
                            <button @click="showValidateModal = false"
                                    class="px-4 py-2 border border-gray-300 text-gray-600 text-sm rounded-lg hover:bg-gray-50 transition">
                                Annuler
                            </button>
                            <button @click="confirmValidate" :disabled="validating"
                                    class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-60 text-white text-sm font-semibold rounded-lg transition">
                                {{ validating ? 'Validation…' : '✓ Valider' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal édition -->
            <div v-if="showEditModal" class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-screen items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showEditModal = false"></div>
                    <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
                        <h2 class="text-lg font-bold text-gray-900 mb-4">Modifier le signalement</h2>
                        <div class="space-y-3 mb-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Titre</label>
                                <input v-model="editForm.title" type="text"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Contenu</label>
                                <textarea v-model="editForm.content" rows="4"
                                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Notes admin</label>
                                <textarea v-model="editForm.admin_notes" rows="2"
                                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none" />
                            </div>
                        </div>
                        <div class="flex gap-2 justify-end">
                            <button @click="showEditModal = false"
                                    class="px-4 py-2 border border-gray-300 text-gray-600 text-sm rounded-lg hover:bg-gray-50 transition">
                                Annuler
                            </button>
                            <button @click="confirmEdit" :disabled="editing"
                                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-semibold rounded-lg transition">
                                {{ editing ? 'Enregistrement…' : 'Enregistrer' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { PencilIcon, TrashIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    reports: Object,
    filters: Object,
})

const filters = ref({
    status: props.filters?.status ?? '',
    type: props.filters?.type ?? '',
})

const pendingCount = computed(() => props.reports.data.filter(r => !r.is_validated).length)
const validatedCount = computed(() => props.reports.data.filter(r => r.is_validated).length)
const thisWeekCount = computed(() => {
    const oneWeekAgo = new Date()
    oneWeekAgo.setDate(oneWeekAgo.getDate() - 7)
    return props.reports.data.filter(r => {
        const [day, month, yearTime] = r.created_at.split('/')
        const [year] = yearTime.split(' ')
        const reportDate = new Date(`${year}-${month}-${day}`)
        return reportDate >= oneWeekAgo
    }).length
})

function applyFilters() {
    router.get(route('asset-reports.index'), filters.value, { preserveState: true })
}

function resetFilters() {
    filters.value = { status: '', type: '' }
    applyFilters()
}

function reportTypeIcon(type) {
    switch(type) {
        case 'damage': return '⚠️'
        case 'loss': return '❌'
        case 'malfunction': return '🔧'
        case 'observation': return '📋'
        default: return '📋'
    }
}

function reportTypeBadgeClass(type) {
    switch(type) {
        case 'damage': return 'bg-orange-100 text-orange-800'
        case 'loss': return 'bg-red-100 text-red-800'
        case 'malfunction': return 'bg-blue-100 text-blue-800'
        case 'observation': return 'bg-gray-100 text-gray-800'
        default: return 'bg-gray-100 text-gray-800'
    }
}

// Validation
const showValidateModal = ref(false)
const validateForm = ref({ admin_notes: '' })
const validating = ref(false)
const currentReport = ref(null)

function openValidateModal(report) {
    currentReport.value = report
    validateForm.value = { admin_notes: report.admin_notes ?? '' }
    showValidateModal.value = true
}

async function confirmValidate() {
    if (!currentReport.value) return
    validating.value = true

    router.post(route('asset-reports.validate', currentReport.value.id), validateForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showValidateModal.value = false
            validating.value = false
        },
        onError: () => {
            validating.value = false
        }
    })
}

// Édition
const showEditModal = ref(false)
const editForm = ref({ title: '', content: '', admin_notes: '' })
const editing = ref(false)

function openEditModal(report) {
    currentReport.value = report
    editForm.value = {
        title: report.title,
        content: report.content,
        admin_notes: report.admin_notes ?? ''
    }
    showEditModal.value = true
}

async function confirmEdit() {
    if (!currentReport.value) return
    editing.value = true

    router.patch(route('asset-reports.update', currentReport.value.id), editForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false
            editing.value = false
        },
        onError: () => {
            editing.value = false
        }
    })
}

// Suppression
function deleteReport(reportId) {
    if (!confirm('Voulez-vous vraiment supprimer ce signalement ?')) return

    router.delete(route('asset-reports.destroy', reportId), {
        preserveScroll: true
    })
}
</script>
