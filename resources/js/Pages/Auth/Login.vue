<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'

defineProps({ canResetPassword: Boolean, status: String })

const form = useForm({ email: '', password: '', remember: false })
const submit = () => form.post(route('login'))
</script>

<template>
  <Head title="Connexion" />
  <div class="min-h-screen bg-gradient-to-br from-blue-900 via-blue-800 to-purple-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-white/10 backdrop-blur rounded-2xl mb-4">
          <span class="text-3xl">🏫</span>
        </div>
        <h1 class="text-2xl font-bold text-white">App Magasin Lycée</h1>
        <p class="text-blue-200 mt-1">Gestion des emprunts de matériel</p>
      </div>

      <div class="bg-white rounded-2xl shadow-2xl p-8">
        <div v-if="status" class="mb-4 text-sm text-green-600 bg-green-50 p-3 rounded-lg">{{ status }}</div>

        <form @submit.prevent="submit" class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input v-model="form.email" type="email" required autofocus
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
              placeholder="votre@email.fr" />
            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
            <input v-model="form.password" type="password" required
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition" />
            <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
          </div>
          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
              <input v-model="form.remember" type="checkbox" class="rounded border-gray-300 text-blue-600" />
              Se souvenir de moi
            </label>
            <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm text-blue-600 hover:underline">
              Mot de passe oublié ?
            </Link>
          </div>
          <button type="submit" :disabled="form.processing"
            class="w-full py-2.5 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold rounded-lg transition disabled:opacity-60">
            {{ form.processing ? 'Connexion...' : 'Se connecter' }}
          </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-500">
          Pas encore de compte ?
          <Link :href="route('register')" class="text-blue-600 font-medium hover:underline">S'inscrire</Link>
        </p>
      </div>
    </div>
  </div>
</template>
