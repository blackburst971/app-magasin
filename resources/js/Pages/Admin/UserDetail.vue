<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  user:           Object,
  reservations:   Array,
  stats:          Object,
  missing_items:  Array,
  damage_reports: Array,
})

const statusColors = {
  pending:             'bg-amber-100 text-amber-800',
  approved:            'bg-blue-100 text-blue-800',
  active:              'bg-green-100 text-green-800',
  returned:            'bg-gray-100 text-gray-700',
  cancelled:           'bg-red-100 text-red-700',
  rejected:            'bg-red-100 text-red-700',
  awaiting_supervisor: 'bg-purple-100 text-purple-700',
}

const statusLabels = {
  pending:             'En attente',
  approved:            'Validée',
  active:              'En cours',
  returned:            'Retourné',
  cancelled:           'Annulée',
  rejected:            'Refusée',
  awaiting_supervisor: 'En attente responsable',
}

const expandedReservation = ref(null)
const activeTab           = ref('reservations')

function toggleExpand(id) {
  expandedReservation.value = expandedReservation.value === id ? null : id
}

const isAdmin = computed(() => props.user.roles?.includes('admin'))
const pendingMissingCount = computed(() => props.missing_items?.filter(m => !m.regularized).length ?? 0)
</script>

<template>
  <AppLayout>
    <div class="max-w-5xl">

      <!-- Breadcrumb -->
      <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
        <a :href="route('admin.users')" class="hover:text-blue-600">👥 Utilisateurs</a>
        <span>›</span>
        <span class="text-gray-900 font-medium">{{ user.name }}</span>
      </nav>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left: Profile card -->
        <div class="space-y-4">

          <div class="bg-white rounded-2xl shadow-sm border p-6">
            <!-- Avatar -->
            <div class="flex flex-col items-center text-center mb-5">
              <div v-if="user.profile_photo_url" class="w-20 h-20 rounded-full mb-3 shadow-md overflow-hidden">
                <img :src="user.profile_photo_url" :alt="user.name" class="w-full h-full object-cover">
              </div>
              <div v-else class="w-20 h-20 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-3xl font-bold mb-3 shadow-md">
                {{ user.name.charAt(0).toUpperCase() }}
              </div>
              <h1 class="text-xl font-bold text-gray-900">{{ user.name }}</h1>
              <span :class="['mt-1.5 text-xs font-semibold px-3 py-1 rounded-full',
                isAdmin ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700']">
                {{ isAdmin ? '🔑 Administrateur' : '👤 Utilisateur' }}
              </span>
              <!-- Badge éléments manquants -->
              <span v-if="pendingMissingCount > 0"
                class="mt-2 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-red-100 text-red-700">
                ⚠️ {{ pendingMissingCount }} perte(s) non régularisée(s)
              </span>
            </div>

            <!-- Contact info -->
            <dl class="space-y-3 text-sm">
              <div class="flex items-start gap-3">
                <span class="text-gray-400 mt-0.5 flex-shrink-0">✉️</span>
                <div>
                  <dt class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Email</dt>
                  <dd class="font-medium text-gray-800 break-all">{{ user.email }}</dd>
                  <dd v-if="user.email_verified_at" class="text-xs text-green-600">✓ Vérifié le {{ user.email_verified_at }}</dd>
                  <dd v-else class="text-xs text-amber-600">⚠ Non vérifié</dd>
                </div>
              </div>
              <div v-if="user.department" class="flex items-start gap-3">
                <span class="text-gray-400 mt-0.5 flex-shrink-0">🏫</span>
                <div>
                  <dt class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Classe / Département</dt>
                  <dd class="font-medium text-gray-800">{{ user.department }}</dd>
                </div>
              </div>
              <div v-if="user.phone" class="flex items-start gap-3">
                <span class="text-gray-400 mt-0.5 flex-shrink-0">📞</span>
                <div>
                  <dt class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Téléphone</dt>
                  <dd class="font-medium text-gray-800">{{ user.phone }}</dd>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <span class="text-gray-400 mt-0.5 flex-shrink-0">📅</span>
                <div>
                  <dt class="text-xs text-gray-400 uppercase tracking-wide mb-0.5">Inscrit le</dt>
                  <dd class="font-medium text-gray-800">{{ user.created_at }}</dd>
                </div>
              </div>
            </dl>
          </div>

          <!-- Statistics card -->
          <div class="bg-white rounded-2xl shadow-sm border p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Statistiques</h2>
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600">Total réservations</span>
                <span class="text-lg font-bold text-gray-900">{{ stats.total }}</span>
              </div>
              <div class="w-full bg-gray-100 rounded-full h-1.5 mb-3">
                <div class="bg-blue-500 h-1.5 rounded-full" style="width: 100%"></div>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <div class="bg-amber-50 rounded-xl p-3 text-center">
                  <p class="text-2xl font-bold text-amber-700">{{ stats.pending }}</p>
                  <p class="text-xs text-amber-600">En attente</p>
                </div>
                <div class="bg-blue-50 rounded-xl p-3 text-center">
                  <p class="text-2xl font-bold text-blue-700">{{ stats.approved }}</p>
                  <p class="text-xs text-blue-600">Validées</p>
                </div>
                <div class="bg-green-50 rounded-xl p-3 text-center">
                  <p class="text-2xl font-bold text-green-700">{{ stats.active }}</p>
                  <p class="text-xs text-green-600">En cours</p>
                </div>
                <div class="bg-gray-50 rounded-xl p-3 text-center">
                  <p class="text-2xl font-bold text-gray-600">{{ stats.returned }}</p>
                  <p class="text-xs text-gray-500">Retournées</p>
                </div>
              </div>
              <div v-if="stats.cancelled > 0" class="bg-red-50 rounded-xl p-3 text-center">
                <p class="text-lg font-bold text-red-600">{{ stats.cancelled }}</p>
                <p class="text-xs text-red-500">Annulées / Refusées</p>
              </div>
            </div>
          </div>

        </div>

        <!-- Right: Tabs (reservations + missing items) -->
        <div class="lg:col-span-2 space-y-4">

          <!-- Onglets -->
          <div class="flex border-b border-gray-200">
            <button @click="activeTab = 'reservations'"
              :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition',
                activeTab === 'reservations'
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700']">
              📋 Réservations ({{ reservations.length }})
            </button>
            <button @click="activeTab = 'missing'"
              :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition flex items-center gap-1.5',
                activeTab === 'missing'
                  ? 'border-red-500 text-red-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700']">
              ⚠️ Pertes d'équipement
              <span v-if="pendingMissingCount > 0"
                class="text-xs bg-red-100 text-red-700 px-1.5 py-0.5 rounded-full font-semibold">
                {{ pendingMissingCount }}
              </span>
            </button>
            <button @click="activeTab = 'damages'"
              :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition flex items-center gap-1.5',
                activeTab === 'damages'
                  ? 'border-orange-500 text-orange-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700']">
              🔧 Dégradations
              <span v-if="damage_reports?.length > 0"
                class="text-xs bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded-full font-semibold">
                {{ damage_reports.length }}
              </span>
            </button>
          </div>

          <!-- Onglet réservations -->
          <div v-if="activeTab === 'reservations'" class="bg-white rounded-2xl shadow-sm border overflow-hidden">
            <div class="px-5 py-4 border-b bg-gray-50 flex items-center justify-between">
              <h2 class="font-semibold text-gray-900">Historique des réservations</h2>
              <span class="text-sm text-gray-500">{{ reservations.length }}</span>
            </div>

            <div v-if="reservations.length === 0" class="text-center py-16 text-gray-400">
              <p class="text-4xl mb-3">📋</p>
              <p class="text-sm">Aucune réservation pour cet utilisateur</p>
            </div>

            <div v-else class="divide-y">
              <div v-for="res in reservations" :key="res.id" class="hover:bg-gray-50 transition">
                <div @click="toggleExpand(res.id)"
                     class="px-5 py-4 cursor-pointer flex items-center gap-4">
                  <div :class="['w-2.5 h-2.5 rounded-full flex-shrink-0',
                    res.status === 'pending' ? 'bg-amber-400' :
                    res.status === 'approved' ? 'bg-blue-500' :
                    res.status === 'active' ? 'bg-green-500' :
                    res.status === 'returned' ? 'bg-gray-400' : 'bg-red-400']">
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900 text-sm truncate flex items-center gap-1.5">
                      {{ res.title }}
                      <span v-if="res.is_kit" class="text-xs text-blue-500">📦</span>
                      <span v-if="res.has_missing_items" class="text-xs text-red-500">⚠️</span>
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ res.start_date }} → {{ res.end_date }}</p>
                  </div>
                  <div class="flex items-center gap-3 flex-shrink-0">
                    <span :class="['text-xs font-semibold px-2.5 py-1 rounded-full', statusColors[res.status] ?? 'bg-gray-100 text-gray-700']">
                      {{ statusLabels[res.status] ?? res.status }}
                    </span>
                    <span class="text-xs text-gray-400">{{ res.assets_count }} éq.</span>
                    <span class="text-gray-300 text-sm">{{ expandedReservation === res.id ? '▲' : '▼' }}</span>
                  </div>
                </div>

                <div v-if="expandedReservation === res.id"
                     class="px-5 pb-4 border-t border-gray-100 bg-gray-50/80">
                  <div class="pt-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Équipements réservés</p>
                    <ul v-if="res.assets.length" class="space-y-1.5">
                      <li v-for="asset in res.assets" :key="asset.id" class="flex items-center gap-3 text-sm">
                        <span class="text-base">📦</span>
                        <div class="flex-1 min-w-0">
                          <span class="font-medium text-gray-800">{{ asset.name }}</span>
                          <span v-if="asset.category" class="text-gray-400 text-xs ml-2">{{ asset.category }}</span>
                        </div>
                        <span class="text-xs font-mono text-gray-400 flex-shrink-0">{{ asset.asset_tag }}</span>
                      </li>
                    </ul>
                    <p v-else class="text-xs text-gray-400 italic">Aucun équipement</p>
                    <div class="mt-3 pt-3 border-t border-gray-200">
                      <a :href="route('reservations.show', res.id)" class="text-xs text-blue-600 hover:underline font-medium">
                        Ouvrir la réservation #{{ res.id }} →
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Onglet éléments manquants -->
          <div v-if="activeTab === 'missing'" class="bg-white rounded-2xl shadow-sm border overflow-hidden">
            <div class="px-5 py-4 border-b bg-gray-50 flex items-center justify-between">
              <h2 class="font-semibold text-gray-900">⚠️ Pertes d'équipement imputées à {{ user.name }}</h2>
              <span class="text-sm text-gray-500">{{ missing_items?.length ?? 0 }} élément(s)</span>
            </div>

            <div v-if="!missing_items?.length" class="text-center py-16 text-gray-400">
              <p class="text-4xl mb-3">✅</p>
              <p class="text-sm font-medium text-gray-500">Aucune perte enregistrée</p>
              <p class="text-xs text-gray-400 mt-1">Cet utilisateur n'a pas d'éléments manquants à son actif.</p>
            </div>

            <div v-else class="divide-y">
              <div v-for="item in missing_items" :key="item.id"
                   class="px-5 py-4"
                   :class="item.regularized ? 'bg-gray-50/50' : 'bg-red-50/30'">
                <div class="flex items-start justify-between gap-3">
                  <div class="flex-1 min-w-0">
                    <!-- Équipement -->
                    <div class="flex items-center gap-2 mb-1">
                      <span v-if="!item.regularized" class="text-red-500">⚠️</span>
                      <span v-else class="text-green-500">✅</span>
                      <p class="font-semibold text-gray-900 text-sm">{{ item.asset.name }}</p>
                      <span class="text-xs font-mono text-gray-400">{{ item.asset.asset_tag }}</span>
                    </div>

                    <!-- Kit et réservation -->
                    <div class="flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-gray-500">
                      <span v-if="item.kit">📦 Kit : <span class="font-medium text-gray-700">{{ item.kit.name }}</span></span>
                      <span v-if="item.reservation">
                        📋 Réservation :
                        <a :href="route('reservations.show', item.reservation.id)" class="text-blue-600 hover:underline font-medium">
                          #{{ item.reservation.id }} - {{ item.reservation.title }}
                        </a>
                      </span>
                      <span>📅 Signalé le {{ item.reported_at }}</span>
                    </div>

                    <!-- Notes -->
                    <p v-if="item.notes" class="text-xs text-gray-500 italic mt-1 bg-yellow-50 border border-yellow-100 px-2 py-1 rounded">
                      Observations : {{ item.notes }}
                    </p>

                    <!-- Régularisation -->
                    <div v-if="item.regularized" class="mt-2 text-xs text-green-700 bg-green-50 border border-green-200 px-2 py-1.5 rounded-lg">
                      ✅ Régularisé le {{ item.regularized_at }}
                      <span v-if="item.regularization_notes"> — {{ item.regularization_notes }}</span>
                    </div>
                  </div>

                  <!-- Badge statut -->
                  <span :class="['text-xs font-semibold px-2.5 py-1 rounded-full flex-shrink-0',
                    item.regularized ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']">
                    {{ item.regularized ? 'Régularisé' : 'En attente' }}
                  </span>
                </div>
              </div>
            </div>

            <div v-if="missing_items?.length" class="px-5 py-3 bg-gray-50 border-t text-right">
              <a :href="route('kits.index')" class="text-xs text-orange-600 hover:underline font-medium">
                Gérer les régularisations →
              </a>
            </div>
          </div>

          <!-- Onglet dégradations -->
          <div v-if="activeTab === 'damages'" class="bg-white rounded-2xl shadow-sm border overflow-hidden">
            <div class="px-5 py-4 border-b bg-gray-50 flex items-center justify-between">
              <h2 class="font-semibold text-gray-900">🔧 Rapports de dégradation liés à {{ user.name }}</h2>
              <span class="text-sm text-gray-500">{{ damage_reports?.length ?? 0 }} rapport(s)</span>
            </div>

            <div v-if="!damage_reports?.length" class="text-center py-16 text-gray-400">
              <p class="text-4xl mb-3">✅</p>
              <p class="text-sm">Aucun rapport de dégradation pour cet utilisateur</p>
            </div>

            <div v-else class="divide-y">
              <div v-for="report in damage_reports" :key="report.id" class="px-5 py-4 hover:bg-gray-50 transition">
                <div class="flex items-start gap-4">
                  <!-- Icône type -->
                  <div class="flex-shrink-0 w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 text-lg">
                    {{ report.type === 'damage' ? '⚠️' : report.type === 'malfunction' ? '🔧' : '📝' }}
                  </div>

                  <div class="flex-1 min-w-0">
                    <!-- Titre et équipement -->
                    <div class="flex items-start justify-between gap-3 mb-2">
                      <div class="flex-1">
                        <h3 class="font-semibold text-gray-900">{{ report.title }}</h3>
                        <p class="text-sm text-gray-600 mt-0.5">
                          Équipement :
                          <a :href="route('assets.show', report.asset.id)" class="text-blue-600 hover:underline font-medium">
                            {{ report.asset.name }} ({{ report.asset.asset_tag }})
                          </a>
                        </p>
                      </div>
                      <span class="text-xs text-gray-400 flex-shrink-0">{{ report.created_at }}</span>
                    </div>

                    <!-- Description -->
                    <p class="text-sm text-gray-700 bg-gray-50 rounded-lg p-3 mb-2">{{ report.content }}</p>

                    <!-- Condition de retour -->
                    <div v-if="report.return_condition" class="flex items-center gap-2 text-xs">
                      <span class="text-gray-500">État au retour :</span>
                      <span :class="['px-2 py-0.5 rounded-full font-semibold',
                        report.return_condition === 'damaged' ? 'bg-orange-100 text-orange-700' :
                        report.return_condition === 'lost' ? 'bg-red-100 text-red-700' :
                        'bg-amber-100 text-amber-700']">
                        {{ report.return_condition === 'damaged' ? 'Endommagé' :
                           report.return_condition === 'lost' ? 'Perdu' :
                           report.return_condition === 'pending' ? 'À vérifier' : report.return_condition }}
                      </span>
                    </div>

                    <!-- Réservation liée -->
                    <div v-if="report.reservation" class="mt-2 text-xs text-blue-600">
                      Réservation :
                      <a :href="route('reservations.show', report.reservation.id)" class="hover:underline font-medium">
                        {{ report.reservation.title }} (#{{ report.reservation.id }})
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="damage_reports?.length" class="px-5 py-3 bg-gray-50 border-t text-right">
              <p class="text-xs text-gray-500">Rapports générés par les retours de réservations</p>
            </div>
          </div>

        </div>
      </div>
    </div>
  </AppLayout>
</template>
