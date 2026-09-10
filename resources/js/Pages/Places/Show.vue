<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
  MapPinIcon, BuildingStorefrontIcon, ArrowUturnLeftIcon,
  ClockIcon, ExclamationTriangleIcon, AdjustmentsHorizontalIcon,
  ChevronRightIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  place:             Object,
  activeAssignments: Array,
  history:           Array,
  allPlaces:         Array,
})

const tab = ref('active')

const overdueCount = computed(() => props.activeAssignments.filter(a => a.is_overdue).length)
</script>

<template>
  <AppLayout :title="place.name">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

      <!-- ══ EN-TÊTE ════════════════════════════════════════════════════════ -->
      <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-4">
          <!-- Pastille couleur -->
          <div class="w-14 h-14 rounded-2xl flex items-center justify-center shrink-0"
            :style="{ background: place.color + '22' }">
            <MapPinIcon v-if="!place.is_storage" class="h-7 w-7" :style="{ color: place.color }" />
            <BuildingStorefrontIcon v-else class="h-7 w-7" :style="{ color: place.color }" />
          </div>
          <div>
            <div class="flex items-center gap-2 flex-wrap">
              <h1 class="text-2xl font-bold text-gray-900">{{ place.name }}</h1>
              <span v-if="place.is_storage"
                class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full flex items-center gap-1">
                <BuildingStorefrontIcon class="h-3 w-3" /> Stockage
              </span>
              <span v-if="overdueCount"
                class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full flex items-center gap-1">
                <ExclamationTriangleIcon class="h-3 w-3" /> {{ overdueCount }} en retard
              </span>
            </div>
            <p v-if="place.description" class="text-sm text-gray-500 mt-0.5">{{ place.description }}</p>
            <p class="text-sm text-indigo-600 font-medium mt-0.5">
              {{ place.active_count }} équipement{{ place.active_count !== 1 ? 's' : '' }} actuellement assigné{{ place.active_count !== 1 ? 's' : '' }}
            </p>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2 flex-wrap">
          <Link :href="route('places.index')"
            class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 border border-gray-300 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition">
            ← Tous les lieux
          </Link>
          <Link :href="route('asset-places.index')"
            class="flex items-center gap-1.5 text-sm bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 transition">
            <AdjustmentsHorizontalIcon class="h-4 w-4" />
            Gérer les affectations
          </Link>
        </div>
      </div>

      <!-- ══ NAVIGATION RAPIDE (autres lieux) ══════════════════════════════ -->
      <div class="flex items-center gap-2 overflow-x-auto pb-1 -mx-1 px-1">
        <span class="text-xs text-gray-400 shrink-0">Autres lieux :</span>
        <Link
          v-for="p in allPlaces" :key="p.id"
          :href="route('places.show', p.id)"
          class="flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded-full border transition shrink-0"
          :class="p.id === place.id
            ? 'border-transparent text-white font-semibold'
            : 'border-gray-200 text-gray-600 hover:border-indigo-300 hover:text-indigo-600 bg-white'"
          :style="p.id === place.id ? { background: place.color } : {}">
          <span class="w-2 h-2 rounded-full shrink-0" :style="{ background: p.id === place.id ? 'white' : p.color }" />
          {{ p.name }}
          <span class="opacity-70">{{ p.active_assignments_count }}</span>
        </Link>
      </div>

      <!-- ══ ONGLETS ════════════════════════════════════════════════════════ -->
      <div class="flex border-b gap-6">
        <button @click="tab = 'active'"
          :class="tab === 'active' ? 'border-b-2 border-indigo-600 text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
          class="pb-3 text-sm transition flex items-center gap-1.5">
          📍 Équipements présents
          <span class="text-xs bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded-full font-medium">
            {{ activeAssignments.length }}
          </span>
        </button>
        <button @click="tab = 'history'"
          :class="tab === 'history' ? 'border-b-2 border-indigo-600 text-indigo-700 font-semibold' : 'text-gray-500 hover:text-gray-700'"
          class="pb-3 text-sm transition">
          🕓 Historique
          <span class="text-xs text-gray-400 ml-1">({{ history.length }})</span>
        </button>
      </div>

      <!-- ══ ÉQUIPEMENTS ACTIFS ══════════════════════════════════════════ -->
      <div v-if="tab === 'active'">
        <div v-if="!activeAssignments.length"
          class="text-center py-20 text-gray-400">
          <MapPinIcon class="h-12 w-12 mx-auto mb-3 opacity-20" />
          <p class="text-sm">Aucun équipement actuellement assigné à ce lieu.</p>
          <Link :href="route('asset-places.index')"
            class="mt-3 inline-block text-indigo-500 hover:underline text-sm">
            Affecter des équipements →
          </Link>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="a in activeAssignments" :key="a.id"
            class="bg-white rounded-xl border shadow-sm p-4 flex flex-col gap-3"
            :class="a.is_overdue ? 'border-red-300 bg-red-50/20' : ''">

            <!-- Badge type -->
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                :class="a.is_permanent
                  ? 'bg-purple-100 text-purple-700'
                  : a.is_overdue
                    ? 'bg-red-100 text-red-700'
                    : 'bg-amber-100 text-amber-700'">
                {{ a.is_permanent ? '🔒 Permanent' : a.is_overdue ? '⚠️ En retard' : '⏱ Temporaire' }}
              </span>
            </div>

            <!-- Miniature + nom équipement -->
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center shrink-0 overflow-hidden">
                <img v-if="a.asset?.image" :src="a.asset.image" class="w-full h-full object-cover" alt="" />
                <MapPinIcon v-else class="h-5 w-5 text-gray-400" />
              </div>
              <div class="min-w-0">
                <Link :href="route('assets.show', a.asset?.id)"
                  class="font-semibold text-gray-900 text-sm hover:text-indigo-600 transition truncate block">
                  {{ a.asset?.name }}
                </Link>
                <p class="text-xs text-gray-400 font-mono">{{ a.asset?.asset_tag }}</p>
                <p v-if="a.asset?.category" class="text-xs text-gray-500">{{ a.asset.category }}</p>
              </div>
            </div>

            <!-- Dates -->
            <div class="text-xs text-gray-500 space-y-0.5">
              <div class="flex items-center gap-1">
                <ClockIcon class="h-3.5 w-3.5 shrink-0" />
                Depuis le {{ a.assigned_at }}
              </div>
              <div v-if="!a.is_permanent" class="flex items-center gap-1"
                :class="a.is_overdue ? 'text-red-600 font-medium' : ''">
                <ArrowUturnLeftIcon class="h-3.5 w-3.5 shrink-0" />
                Retour prévu le {{ a.return_expected_at_label }}
              </div>
            </div>

            <p v-if="a.notes" class="text-xs text-gray-500 italic border-t pt-2">{{ a.notes }}</p>
            <p class="text-xs text-gray-400">Par {{ a.assigned_by?.name ?? '—' }}</p>
          </div>
        </div>
      </div>

      <!-- ══ HISTORIQUE ═════════════════════════════════════════════════════ -->
      <div v-if="tab === 'history'">
        <div v-if="!history.length" class="text-center py-16 text-gray-400 text-sm">
          Aucun historique d'affectation pour ce lieu.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">
                <th class="pb-2 pr-4">Équipement</th>
                <th class="pb-2 pr-4">Type</th>
                <th class="pb-2 pr-4">Affecté le</th>
                <th class="pb-2 pr-4">Retour prévu</th>
                <th class="pb-2 pr-4">Retourné le</th>
                <th class="pb-2">Par</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="a in history" :key="a.id" class="hover:bg-gray-50 transition">
                <td class="py-2.5 pr-4">
                  <div class="font-medium text-gray-800">{{ a.asset?.name }}</div>
                  <div class="text-xs text-gray-400 font-mono">{{ a.asset?.asset_tag }}</div>
                </td>
                <td class="py-2.5 pr-4">
                  <span class="text-xs px-2 py-0.5 rounded-full"
                    :class="a.is_permanent ? 'bg-purple-100 text-purple-700' : 'bg-amber-100 text-amber-700'">
                    {{ a.is_permanent ? 'Permanent' : 'Temporaire' }}
                  </span>
                </td>
                <td class="py-2.5 pr-4 text-gray-600">{{ a.assigned_at }}</td>
                <td class="py-2.5 pr-4 text-gray-600">{{ a.return_expected_at_label ?? '—' }}</td>
                <td class="py-2.5 pr-4 text-green-700 font-medium">{{ a.returned_at }}</td>
                <td class="py-2.5 text-gray-500">{{ a.assigned_by?.name ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </AppLayout>
</template>
