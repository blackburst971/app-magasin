<template>
    <AppLayout title="Fournisseurs">
        <div class="max-w-5xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Fournisseurs</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ suppliers.length }} fournisseur{{ suppliers.length > 1 ? 's' : '' }}</p>
                </div>
                <button @click="openCreate" class="btn-primary flex items-center gap-2">
                    <PlusIcon class="w-4 h-4" />
                    Ajouter
                </button>
            </div>

            <!-- Liste -->
            <div v-if="suppliers.length === 0" class="bg-white rounded-xl shadow-sm border p-12 text-center text-gray-400">
                <BuildingStorefrontIcon class="w-12 h-12 mx-auto mb-3 opacity-30" />
                <p class="text-sm">Aucun fournisseur enregistré</p>
            </div>

            <div v-else class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-gray-500 uppercase border-b bg-gray-50">
                            <th class="px-4 py-3 text-left">Nom</th>
                            <th class="px-4 py-3 text-left">Contact</th>
                            <th class="px-4 py-3 text-left">Email / Tél.</th>
                            <th class="px-4 py-3 text-center">Équipements</th>
                            <th class="px-4 py-3 text-center">Modèles</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in suppliers" :key="s.id" class="border-b hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ s.name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ s.contact_name || '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">
                                <div>{{ s.email || '—' }}</div>
                                <div class="text-xs text-gray-400">{{ s.phone || '' }}</div>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-500">{{ s.assets_count ?? 0 }}</td>
                            <td class="px-4 py-3 text-center text-gray-500">{{ s.equipment_models_count ?? 0 }}</td>
                            <td class="px-4 py-3 text-right flex justify-end gap-2">
                                <button @click="openEdit(s)" class="text-xs text-blue-600 hover:underline">Modifier</button>
                                <button @click="confirmDelete(s)" class="text-xs text-red-500 hover:underline">Supprimer</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Modal création / édition -->
            <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg">
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h2 class="font-semibold text-gray-900">{{ editing ? 'Modifier' : 'Nouveau' }} fournisseur</h2>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                    <form @submit.prevent="submitForm" class="px-6 py-4 space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                            <input v-model="form.name" type="text" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Contact</label>
                                <input v-model="form.contact_name" type="text" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Téléphone</label>
                                <input v-model="form.phone" type="text" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
                                <input v-model="form.email" type="email" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Site web</label>
                                <input v-model="form.website" type="url" placeholder="https://" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Adresse</label>
                            <textarea v-model="form.address" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                            <textarea v-model="form.notes" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="closeModal" class="btn-secondary">Annuler</button>
                            <button type="submit" :disabled="processing" class="btn-primary">
                                {{ editing ? 'Enregistrer' : 'Créer' }}
                            </button>
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
import { ref } from 'vue'
import { PlusIcon, XMarkIcon, BuildingStorefrontIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ suppliers: { type: Array, default: () => [] } })

const showModal  = ref(false)
const editing    = ref(null)
const processing = ref(false)

const emptyForm = () => ({ name: '', contact_name: '', email: '', phone: '', address: '', website: '', notes: '' })
const form = ref(emptyForm())

function openCreate() {
    editing.value = null
    form.value = emptyForm()
    showModal.value = true
}

function openEdit(supplier) {
    editing.value = supplier
    form.value = { ...supplier }
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    editing.value = null
}

function submitForm() {
    processing.value = true
    if (editing.value) {
        router.put(route('suppliers.update', editing.value.id), form.value, {
            onFinish: () => { processing.value = false; closeModal() },
        })
    } else {
        router.post(route('suppliers.store'), form.value, {
            onFinish: () => { processing.value = false; closeModal() },
        })
    }
}

function confirmDelete(supplier) {
    if (confirm(`Supprimer le fournisseur "${supplier.name}" ?`)) {
        router.delete(route('suppliers.destroy', supplier.id))
    }
}
</script>
