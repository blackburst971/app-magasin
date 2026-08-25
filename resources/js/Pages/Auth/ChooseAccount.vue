<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'

const props = defineProps({
    accounts: Array,
})

const form = useForm({ user_id: null })

function select(id) {
    form.user_id = id
    form.post(route('login.select'))
}
</script>

<template>
  <Head title="Choisir un compte" />
  <div class="min-h-screen bg-gradient-to-br from-blue-900 via-blue-800 to-purple-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <!-- En-tête -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-white/10 backdrop-blur rounded-2xl mb-4">
          <span class="text-3xl">👥</span>
        </div>
        <h1 class="text-2xl font-bold text-white">Plusieurs comptes trouvés</h1>
        <p class="text-blue-200 mt-1">Sélectionnez le compte auquel vous souhaitez vous connecter</p>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl p-8">
        <p v-if="form.errors.user_id" class="mb-4 text-sm text-red-600 bg-red-50 p-3 rounded-lg">
          {{ form.errors.user_id }}
        </p>

        <div class="space-y-3">
          <button
            v-for="account in accounts"
            :key="account.id"
            type="button"
            :disabled="form.processing"
            @click="select(account.id)"
            class="w-full flex items-center gap-4 p-4 border-2 border-gray-200 rounded-xl text-left hover:border-blue-500 hover:bg-blue-50 transition-all disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <!-- Avatar initiales -->
            <div class="flex-shrink-0 w-11 h-11 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg">
              {{ account.name.charAt(0).toUpperCase() }}
            </div>
            <!-- Infos -->
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-gray-900 truncate">{{ account.name }}</p>
              <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                  :class="{
                    'bg-red-100 text-red-700':    account.role_label === 'Administrateur',
                    'bg-blue-100 text-blue-700':  account.role_label === 'Professeur',
                    'bg-green-100 text-green-700':account.role_label === 'Personnel',
                    'bg-gray-100 text-gray-600':  account.role_label === 'Étudiant',
                  }">
                  {{ account.role_label }}
                </span>
                <span v-if="account.department" class="text-xs text-gray-500 truncate">
                  {{ account.department }}
                </span>
              </div>
            </div>
            <!-- Flèche -->
            <svg class="flex-shrink-0 w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>

        <div class="mt-6 text-center">
          <Link :href="route('login')" class="text-sm text-blue-600 hover:underline">
            ← Retour à la connexion
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>
