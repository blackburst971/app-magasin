<template>
    <AppLayout title="Mon profil">
        <div class="max-w-2xl mx-auto space-y-6">
            <h1 class="text-2xl font-bold text-gray-900">Mon profil</h1>

            <!-- Flash message -->
            <div v-if="$page.props.flash?.success"
                 class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-green-800 text-sm">
                ✓ {{ $page.props.flash.success }}
            </div>

            <!-- Infos personnelles -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Informations personnelles</h2>
                <form @submit.prevent="submitProfile" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nom complet</label>
                            <input v-model="profileForm.name" type="text" required
                                   class="input-field" />
                            <p v-if="profileErrors.name" class="text-red-500 text-xs mt-1">{{ profileErrors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input v-model="profileForm.email" type="email" required
                                   class="input-field" />
                            <p v-if="profileErrors.email" class="text-red-500 text-xs mt-1">{{ profileErrors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                            <input v-model="profileForm.phone" type="tel"
                                   class="input-field" placeholder="06 xx xx xx xx" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Département / Classe</label>
                            <input v-model="profileForm.department" type="text"
                                   class="input-field" placeholder="BTS SIO, Terminale..." />
                        </div>
                    </div>
                    <div class="flex justify-end pt-2">
                        <button type="submit" :disabled="profileLoading"
                                class="btn-primary">
                            {{ profileLoading ? 'Enregistrement…' : 'Enregistrer' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Changement de mot de passe -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Changer le mot de passe</h2>
                <form @submit.prevent="submitPassword" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe actuel</label>
                        <input v-model="passwordForm.current_password" type="password"
                               class="input-field" autocomplete="current-password" />
                        <p v-if="passwordErrors.current_password" class="text-red-500 text-xs mt-1">
                            {{ passwordErrors.current_password }}
                        </p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nouveau mot de passe</label>
                            <input v-model="passwordForm.password" type="password"
                                   class="input-field" autocomplete="new-password" />
                            <p v-if="passwordErrors.password" class="text-red-500 text-xs mt-1">
                                {{ passwordErrors.password }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmer</label>
                            <input v-model="passwordForm.password_confirmation" type="password"
                                   class="input-field" autocomplete="new-password" />
                        </div>
                    </div>
                    <div class="flex justify-end pt-2">
                        <button type="submit" :disabled="passwordLoading"
                                class="btn-primary">
                            {{ passwordLoading ? 'Enregistrement…' : 'Modifier le mot de passe' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Zone de danger -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-red-100">
                <h2 class="text-base font-semibold text-red-700 mb-2">Zone de danger</h2>
                <p class="text-sm text-gray-600 mb-4">
                    La suppression de votre compte est permanente. Toutes vos réservations seront également supprimées.
                </p>
                <button @click="confirmDelete = true"
                        class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                    Supprimer mon compte
                </button>
            </div>
        </div>

        <!-- Modal de confirmation suppression -->
        <div v-if="confirmDelete"
             class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-sm w-full">
                <h3 class="text-lg font-bold text-gray-900 mb-2">Supprimer le compte ?</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Cette action est irréversible. Entrez votre mot de passe pour confirmer.
                </p>
                <input v-model="deletePassword" type="password"
                       class="input-field mb-3" placeholder="Votre mot de passe" />
                <div class="flex gap-3">
                    <button @click="confirmDelete = false"
                            class="flex-1 btn-secondary text-sm">Annuler</button>
                    <button @click="submitDelete"
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white text-sm font-medium py-2 rounded-lg transition">
                        Supprimer
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { ref, reactive } from 'vue'

const page = usePage()
const user = page.props.auth.user

const profileForm = reactive({
    name: user.name,
    email: user.email,
    phone: user.phone ?? '',
    department: user.department ?? '',
})
const profileErrors = ref({})
const profileLoading = ref(false)

const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
})
const passwordErrors = ref({})
const passwordLoading = ref(false)

const confirmDelete = ref(false)
const deletePassword = ref('')

function submitProfile() {
    profileLoading.value = true
    profileErrors.value = {}
    router.put(route('profile.update'), profileForm, {
        onError: (errors) => { profileErrors.value = errors },
        onFinish: () => { profileLoading.value = false },
    })
}

function submitPassword() {
    passwordLoading.value = true
    passwordErrors.value = {}
    router.put(route('password.update'), passwordForm, {
        onSuccess: () => {
            passwordForm.current_password = ''
            passwordForm.password = ''
            passwordForm.password_confirmation = ''
        },
        onError: (errors) => { passwordErrors.value = errors },
        onFinish: () => { passwordLoading.value = false },
    })
}

function submitDelete() {
    router.delete(route('profile.destroy'), {
        data: { password: deletePassword.value },
        onError: () => { confirmDelete.value = false },
    })
}
</script>

<style scoped>
.input-field {
    @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent;
}
.btn-primary {
    @apply bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition disabled:opacity-50;
}
.btn-secondary {
    @apply bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium px-4 py-2 rounded-lg text-sm transition;
}
</style>
