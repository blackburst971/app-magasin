<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({ forms: Array })

const deleteFn = (form) => {
  if (confirm(`Supprimer le formulaire "${form.name}" ?`))
    router.delete(route('checkout-forms.destroy', form.id), { preserveScroll: true })
}
</script>

<template>
  <AppLayout>
    <div>
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">📋 Formulaires de sortie</h1>
        <Link :href="route('checkout-forms.create')" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">+ Créer</Link>
      </div>
      <div class="space-y-4">
        <div v-for="form in forms" :key="form.id" class="bg-white rounded-xl shadow-sm border p-5 flex items-center justify-between">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <h3 class="font-semibold text-gray-900">{{ form.name }}</h3>
              <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', form.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                {{ form.is_active ? 'Actif' : 'Inactif' }}
              </span>
            </div>
            <p class="text-sm text-gray-500">{{ form.description ?? 'Aucune description' }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ form.fields?.length ?? 0 }} champ(s) • Créé par {{ form.creator?.name }}</p>
          </div>
          <div class="flex gap-2">
            <Link :href="route('checkout-forms.edit', form.id)" class="text-sm border border-gray-300 px-3 py-1.5 rounded-lg hover:bg-gray-50">Modifier</Link>
            <button @click="deleteFn(form)" class="text-sm border border-red-200 text-red-600 px-3 py-1.5 rounded-lg hover:bg-red-50">Supprimer</button>
          </div>
        </div>
        <div v-if="forms.length === 0" class="bg-white rounded-xl border p-12 text-center text-gray-400">
          <p class="text-4xl mb-2">📋</p>
          <p>Aucun formulaire créé</p>
          <Link :href="route('checkout-forms.create')" class="mt-3 inline-block text-blue-600 hover:underline text-sm">Créer le premier formulaire</Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
