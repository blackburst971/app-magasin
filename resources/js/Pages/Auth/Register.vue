<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'
const form = useForm({ name: '', email: '', password: '', password_confirmation: '', department: '' })
const submit = () => form.post(route('register'))
</script>

<template>
  <Head title="Inscription" />
  <div class="min-h-screen bg-gradient-to-br from-blue-900 via-blue-800 to-purple-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-white/10 backdrop-blur rounded-2xl mb-4">
          <span class="text-3xl">🏫</span>
        </div>
        <h1 class="text-2xl font-bold text-white">Créer un compte</h1>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl p-8">
        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom complet</label>
            <input v-model="form.name" type="text" required autofocus
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition" />
            <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input v-model="form.email" type="email" required
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition" />
            <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Classe / Département</label>
            <input v-model="form.department" type="text" placeholder="BTS SIO, Terminale, Administration..."
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
            <input v-model="form.password" type="password" required
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition" />
            <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmer le mot de passe</label>
            <input v-model="form.password_confirmation" type="password" required
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none transition" />
          </div>
          <button type="submit" :disabled="form.processing"
            class="w-full py-2.5 bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold rounded-lg transition disabled:opacity-60">
            {{ form.processing ? 'Inscription...' : "S'inscrire" }}
          </button>
        </form>
        <p class="mt-6 text-center text-sm text-gray-500">
          Déjà un compte ? <Link :href="route('login')" class="text-blue-600 font-medium hover:underline">Se connecter</Link>
        </p>
      </div>
    </div>
  </div>
</template>
