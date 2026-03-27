<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { Bar, Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale, LinearScale, BarElement, ArcElement,
  Title, Tooltip, Legend, PointElement, LineElement,
} from 'chart.js'

ChartJS.register(
  CategoryScale, LinearScale, BarElement, ArcElement,
  Title, Tooltip, Legend, PointElement, LineElement,
)

const page = usePage()
const isAdmin = computed(() => page.props.auth.user?.is_admin)

const props = defineProps({
  stats:                { type: Object,  default: () => ({}) },
  pendingReservations:  { type: Array,   default: () => [] },
  approvedReservations: { type: Array,   default: () => [] },
  recentMovements:      { type: Array,   default: () => [] },
  myReservations:       { type: Array,   default: () => [] },
  upcomingReturns:      { type: Array,   default: () => [] },
  overdueReturns:       { type: Array,   default: () => [] },
  reminderMinutes:      { type: Number,  default: 30 },
  reminderEnabled:      { type: Boolean, default: true },
  autoCancelEnabled:    { type: Boolean, default: false },
  autoCancelHours:      { type: Number,  default: 24 },
  reservationsByMonth:  { type: Array,   default: () => [] },
  assetsByStatus:       { type: Array,   default: () => [] },
  topAssets:            { type: Array,   default: () => [] },
})

const reservationsChartData = computed(() => ({
  labels: props.reservationsByMonth.map(m => m.label),
  datasets: [{
    label: 'Réservations',
    data: props.reservationsByMonth.map(m => m.count),
    backgroundColor: 'rgba(99, 102, 241, 0.7)',
    borderColor: 'rgba(99, 102, 241, 1)',
    borderWidth: 1,
    borderRadius: 4,
  }],
}))

const reservationsChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
}

const statusChartData = computed(() => ({
  labels: props.assetsByStatus.map(s => s.label),
  datasets: [{
    data: props.assetsByStatus.map(s => s.count),
    backgroundColor: props.assetsByStatus.map(s => s.color || '#94a3b8'),
    borderWidth: 2,
    borderColor: '#fff',
  }],
}))

const statusChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
  cutout: '60%',
}

const topAssetsChartData = computed(() => ({
  labels: props.topAssets.map(a => a.name.length > 20 ? a.name.slice(0, 20) + '…' : a.name),
  datasets: [{
    label: 'Réservations',
    data: props.topAssets.map(a => a.count),
    backgroundColor: [
      'rgba(59,130,246,0.7)',
      'rgba(16,185,129,0.7)',
      'rgba(245,158,11,0.7)',
      'rgba(239,68,68,0.7)',
      'rgba(139,92,246,0.7)',
    ],
    borderRadius: 4,
  }],
}))

const topAssetsChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  plugins: { legend: { display: false } },
  scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
}

function minutesLabel(minutes) {
  if (minutes >= 1440) return `${Math.floor(minutes/1440)}j ${Math.floor((minutes%1440)/60)}h`
  if (minutes >= 60) {
    const h = Math.floor(minutes / 60)
    const m = minutes % 60
    return m > 0 ? `${h}h${String(m).padStart(2,'0')}` : `${h}h`
  }
  return `${minutes} min`
}

function overdueLabel(minutes) {
  if (minutes >= 1440) return `${Math.floor(minutes/1440)}j de retard`
  if (minutes >= 60)   return `${Math.floor(minutes/60)}h de retard`
  return `${minutes} min de retard`
}

function hoursLeftLabel(h) {
  if (h === null || h === undefined) return null
  if (h <= 0) return 'Annulation imminente'
  if (h === 1) return '1h restante'
  return `${h}h restantes`
}
</script>

<template>
  <AppLayout>
    <div>
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Tableau de bord</h1>
        <p class="text-gray-500 text-sm mt-1">Bienvenue, {{ $page.props.auth.user?.name }}</p>
      </div>

      <!-- Stats grid -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <template v-if="isAdmin">
          <div class="bg-white rounded-xl p-4 shadow-sm border">
            <div class="text-2xl font-bold text-blue-600">{{ stats?.total_assets ?? 0 }}</div>
            <div class="text-sm text-gray-500 mt-1">Équipements total</div>
          </div>
          <div class="bg-white rounded-xl p-4 shadow-sm border">
            <div class="text-2xl font-bold text-green-600">{{ stats?.available_assets ?? 0 }}</div>
            <div class="text-sm text-gray-500 mt-1">Disponibles</div>
          </div>
          <div class="bg-white rounded-xl p-4 shadow-sm border">
            <div class="text-2xl font-bold text-yellow-600">{{ stats?.pending_reservations ?? 0 }}</div>
            <div class="text-sm text-gray-500 mt-1">En attente de validation</div>
          </div>
          <div class="bg-white rounded-xl p-4 shadow-sm border"
               :class="(stats?.approved_reservations ?? 0) > 0 ? 'border-blue-200 bg-blue-50' : ''">
            <div class="text-2xl font-bold text-blue-600">{{ stats?.approved_reservations ?? 0 }}</div>
            <div class="text-sm mt-1" :class="(stats?.approved_reservations ?? 0) > 0 ? 'text-blue-600' : 'text-gray-500'">
              Validées — en attente de retrait
            </div>
          </div>
          <div class="bg-white rounded-xl p-4 shadow-sm border">
            <div class="text-2xl font-bold text-purple-600">{{ stats?.active_reservations ?? 0 }}</div>
            <div class="text-sm text-gray-500 mt-1">Sorties en cours</div>
          </div>
          <!-- Retours imminents -->
          <div v-if="reminderEnabled && stats?.upcoming_returns > 0"
               class="bg-amber-50 rounded-xl p-4 shadow-sm border border-amber-300 col-span-1">
            <div class="text-2xl font-bold text-amber-600">{{ stats?.upcoming_returns ?? 0 }}</div>
            <div class="text-sm text-amber-700 mt-1 font-medium">⏰ Retours imminents</div>
            <div class="text-xs text-amber-500 mt-0.5">Dans les {{ reminderMinutes }} prochaines min</div>
          </div>
          <!-- Retours en retard -->
          <div v-if="(stats?.overdue_returns ?? 0) > 0"
               class="bg-red-50 rounded-xl p-4 shadow-sm border border-red-300 col-span-1">
            <div class="text-2xl font-bold text-red-600">{{ stats?.overdue_returns ?? 0 }}</div>
            <div class="text-sm text-red-700 mt-1 font-medium">🔴 En retard</div>
            <div class="text-xs text-red-500 mt-0.5">Retour dépassé, non rendu</div>
          </div>
        </template>
        <template v-else>
          <div class="bg-white rounded-xl p-4 shadow-sm border">
            <div class="text-2xl font-bold text-yellow-600">{{ stats?.my_pending ?? 0 }}</div>
            <div class="text-sm text-gray-500 mt-1">Mes demandes en attente</div>
          </div>
          <div class="bg-white rounded-xl p-4 shadow-sm border"
               :class="(stats?.my_approved ?? 0) > 0 ? 'border-blue-200 bg-blue-50' : ''">
            <div class="text-2xl font-bold text-blue-600">{{ stats?.my_approved ?? 0 }}</div>
            <div class="text-sm mt-1" :class="(stats?.my_approved ?? 0) > 0 ? 'text-blue-600 font-medium' : 'text-gray-500'">
              {{ (stats?.my_approved ?? 0) > 0 ? '✅ Validée(s) — à retirer !' : 'Réservations validées' }}
            </div>
          </div>
          <div class="bg-white rounded-xl p-4 shadow-sm border">
            <div class="text-2xl font-bold text-green-600">{{ stats?.my_active ?? 0 }}</div>
            <div class="text-sm text-gray-500 mt-1">Mes emprunts actifs</div>
          </div>
          <div class="bg-white rounded-xl p-4 shadow-sm border">
            <div class="text-2xl font-bold text-purple-600">{{ stats?.available_assets ?? 0 }}</div>
            <div class="text-sm text-gray-500 mt-1">Équipements disponibles</div>
          </div>
        </template>
      </div>

      <!-- Admin: graphiques -->
      <div v-if="isAdmin" class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Réservations par mois -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border p-5">
          <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Réservations — 6 derniers mois</h2>
          <div class="h-52">
            <Bar v-if="reservationsByMonth.length" :data="reservationsChartData" :options="reservationsChartOptions" />
            <p v-else class="text-center text-gray-400 text-sm pt-16">Aucune donnée</p>
          </div>
        </div>

        <!-- Répartition par statut -->
        <div class="bg-white rounded-xl shadow-sm border p-5">
          <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Équipements par statut</h2>
          <div class="h-52">
            <Doughnut v-if="assetsByStatus.length" :data="statusChartData" :options="statusChartOptions" />
            <p v-else class="text-center text-gray-400 text-sm pt-16">Aucune donnée</p>
          </div>
        </div>

        <!-- Top 5 assets -->
        <div class="lg:col-span-3 bg-white rounded-xl shadow-sm border p-5">
          <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Top 5 équipements les plus réservés</h2>
          <div class="h-40">
            <Bar v-if="topAssets.length" :data="topAssetsChartData" :options="topAssetsChartOptions" />
            <p v-else class="text-center text-gray-400 text-sm pt-12">Aucune donnée</p>
          </div>
        </div>
      </div>

      <!-- Admin: retours en retard -->
      <div v-if="isAdmin && overdueReturns.length > 0" class="rounded-xl shadow-sm border mb-6 border-red-300 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b bg-red-50">
          <div class="flex items-center gap-2">
            <span class="text-lg">🔴</span>
            <div>
              <h2 class="font-semibold text-red-900">Retours en retard</h2>
              <p class="text-xs text-red-600">Ces réservations ont dépassé leur date de retour et sont toujours actives</p>
            </div>
          </div>
          <Link :href="route('returns.index')" class="text-sm text-red-600 hover:underline font-medium">Gérer les retours</Link>
        </div>
        <div class="divide-y divide-red-100 bg-white">
          <div v-for="r in overdueReturns" :key="r.id"
               class="px-6 py-3 flex items-center justify-between hover:bg-red-50 transition">
            <div class="min-w-0 flex-1">
              <p class="font-medium text-sm text-gray-900 truncate">{{ r.title }}</p>
              <p class="text-xs text-gray-500 truncate">{{ r.user }} — {{ r.assets }}</p>
            </div>
            <div class="flex items-center gap-3 ml-4 flex-shrink-0">
              <span class="text-xs font-semibold text-red-600 bg-red-100 px-2 py-0.5 rounded-full">
                +{{ overdueLabel(r.minutes_overdue) }}
              </span>
              <span class="text-xs text-gray-400">prévu le {{ r.end_date }}</span>
              <Link :href="route('reservations.show', r.id)"
                    class="text-xs bg-red-600 text-white px-3 py-1 rounded-full hover:bg-red-700 transition">
                Traiter
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- Admin: retours imminents -->
      <div v-if="isAdmin && reminderEnabled && upcomingReturns.length > 0"
           class="rounded-xl shadow-sm border mb-6 border-amber-300 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b bg-amber-50">
          <div class="flex items-center gap-2">
            <span class="text-lg">⏰</span>
            <div>
              <h2 class="font-semibold text-amber-900">Retours imminents</h2>
              <p class="text-xs text-amber-600">
                Retours prévus dans les prochaines {{ minutesLabel(reminderMinutes) }}
              </p>
            </div>
          </div>
          <Link :href="route('returns.index')" class="text-sm text-amber-700 hover:underline font-medium">Voir tous les retours</Link>
        </div>
        <div class="divide-y divide-amber-100 bg-white">
          <div v-for="r in upcomingReturns" :key="r.id"
               class="px-6 py-3 flex items-center justify-between hover:bg-amber-50 transition">
            <div class="min-w-0 flex-1">
              <p class="font-medium text-sm text-gray-900 truncate">{{ r.title }}</p>
              <p class="text-xs text-gray-500 truncate">{{ r.user }} — {{ r.assets }}</p>
            </div>
            <div class="flex items-center gap-3 ml-4 flex-shrink-0">
              <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full',
                r.minutes_left <= 15
                  ? 'text-red-700 bg-red-100'
                  : r.minutes_left <= 30
                    ? 'text-orange-700 bg-orange-100'
                    : 'text-amber-700 bg-amber-100']">
                dans {{ minutesLabel(r.minutes_left) }}
              </span>
              <span class="text-xs text-gray-400">{{ r.end_date }}</span>
              <Link :href="route('reservations.show', r.id)"
                    class="text-xs bg-amber-500 text-white px-3 py-1 rounded-full hover:bg-amber-600 transition">
                Voir
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- Admin: réservations validées en attente de retrait -->
      <div v-if="isAdmin && approvedReservations.length > 0"
           class="rounded-xl shadow-sm border mb-6 overflow-hidden"
           :class="approvedReservations.some(r => r.at_risk) ? 'border-orange-300' : 'border-blue-200'">
        <div class="flex items-center justify-between px-6 py-4 border-b"
             :class="approvedReservations.some(r => r.at_risk) ? 'bg-orange-50' : 'bg-blue-50'">
          <div class="flex items-center gap-2">
            <span class="text-lg">✅</span>
            <div>
              <h2 class="font-semibold"
                  :class="approvedReservations.some(r => r.at_risk) ? 'text-orange-900' : 'text-blue-900'">
                Validées — en attente de retrait
                <span class="ml-1.5 text-xs font-bold px-2 py-0.5 rounded-full"
                      :class="approvedReservations.some(r => r.at_risk) ? 'bg-orange-200 text-orange-700' : 'bg-blue-100 text-blue-700'">
                  {{ approvedReservations.length }}
                </span>
              </h2>
              <p class="text-xs mt-0.5"
                 :class="approvedReservations.some(r => r.at_risk) ? 'text-orange-600' : 'text-blue-600'">
                <template v-if="autoCancelEnabled">
                  Annulation automatique après {{ autoCancelHours }}h sans retrait
                </template>
                <template v-else>
                  Ces réservations sont approuvées mais le matériel n'a pas encore été retiré
                </template>
              </p>
            </div>
          </div>
          <Link :href="route('reservations.index', {status: 'approved'})"
                class="text-sm font-medium hover:underline"
                :class="approvedReservations.some(r => r.at_risk) ? 'text-orange-700' : 'text-blue-600'">
            Voir tout
          </Link>
        </div>
        <div class="divide-y bg-white">
          <div v-for="r in approvedReservations" :key="r.id"
               class="px-6 py-3 flex items-center justify-between hover:bg-gray-50 transition">
            <div class="min-w-0 flex-1">
              <p class="font-medium text-sm text-gray-900 truncate">{{ r.title }}</p>
              <p class="text-xs text-gray-500 truncate">
                {{ r.user }} · validée le {{ r.approved_at }} · retrait prévu le {{ r.start_date }}
              </p>
            </div>
            <div class="flex items-center gap-3 ml-4 flex-shrink-0">
              <!-- Indicateur temps avant auto-annulation -->
              <span v-if="autoCancelEnabled && r.hours_left !== null"
                    class="text-xs font-semibold px-2 py-0.5 rounded-full"
                    :class="r.at_risk
                      ? 'bg-orange-100 text-orange-700'
                      : 'bg-blue-100 text-blue-700'">
                <template v-if="r.at_risk">⚠️ </template>
                {{ hoursLeftLabel(r.hours_left) }}
              </span>
              <span class="text-xs text-gray-400">{{ r.assets_count }} équip.</span>
              <Link :href="route('reservations.show', r.id)"
                    class="text-xs text-white px-3 py-1 rounded-full transition"
                    :class="r.at_risk ? 'bg-orange-500 hover:bg-orange-600' : 'bg-blue-600 hover:bg-blue-700'">
                Voir
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- User: alerte réservation approuvée en attente de retrait -->
      <div v-if="!isAdmin && autoCancelEnabled && (stats?.my_approved ?? 0) > 0"
           class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 flex items-start gap-3">
        <span class="text-2xl flex-shrink-0">✅</span>
        <div class="flex-1">
          <p class="font-semibold text-blue-900 text-sm">
            {{ stats.my_approved > 1 ? `${stats.my_approved} réservations validées` : 'Une réservation validée' }} en attente de retrait
          </p>
          <p class="text-xs text-blue-700 mt-0.5">
            Vous avez {{ autoCancelHours }}h pour retirer le matériel depuis l'approbation,
            après quoi la réservation sera annulée automatiquement.
          </p>
          <Link :href="route('reservations.index', {status: 'approved'})"
                class="mt-2 inline-block text-xs font-semibold text-blue-600 hover:underline">
            Voir mes réservations validées →
          </Link>
        </div>
      </div>

      <!-- Admin: pending reservations -->
      <div v-if="isAdmin && pendingReservations.length > 0" class="bg-white rounded-xl shadow-sm border mb-6">
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <h2 class="font-semibold text-gray-900">⏳ Réservations en attente</h2>
          <Link :href="route('reservations.index', {status: 'pending'})" class="text-sm text-blue-600 hover:underline">Voir tout</Link>
        </div>
        <div class="divide-y">
          <div v-for="r in pendingReservations" :key="r.id" class="px-6 py-4 flex items-center justify-between hover:bg-gray-50">
            <div>
              <p class="font-medium text-sm text-gray-900">{{ r.title }}</p>
              <p class="text-xs text-gray-500">{{ r.user }} — {{ r.start_date }} → {{ r.end_date }}</p>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-xs text-gray-500">{{ r.assets_count }} équip.</span>
              <Link :href="route('reservations.show', r.id)" class="text-xs bg-blue-600 text-white px-3 py-1 rounded-full hover:bg-blue-700">Valider</Link>
            </div>
          </div>
        </div>
      </div>

      <!-- User: my reservations -->
      <div v-if="!isAdmin" class="bg-white rounded-xl shadow-sm border mb-6">
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <h2 class="font-semibold text-gray-900">📅 Mes réservations</h2>
          <div class="flex gap-2">
            <Link :href="route('reservations.create')" class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700">+ Nouvelle</Link>
            <Link :href="route('reservations.index')" class="text-xs text-blue-600 hover:underline">Voir tout</Link>
          </div>
        </div>
        <div v-if="myReservations.length === 0" class="px-6 py-8 text-center text-gray-400">
          <p class="text-4xl mb-2">📭</p>
          <p class="text-sm">Aucune réservation en cours</p>
          <Link :href="route('reservations.create')" class="mt-3 inline-block text-sm text-blue-600 hover:underline">Faire une demande</Link>
        </div>
        <div v-else class="divide-y">
          <div v-for="r in myReservations" :key="r.id" class="px-6 py-4 flex items-center justify-between hover:bg-gray-50">
            <div>
              <p class="font-medium text-sm text-gray-900">{{ r.title }}</p>
              <p class="text-xs text-gray-500">{{ r.start_date }} → {{ r.end_date }}</p>
            </div>
            <div class="flex items-center gap-2">
              <StatusBadge :status="r.status" type="reservation" />
              <Link :href="route('reservations.show', r.id)" class="text-xs text-blue-600 hover:underline">Voir</Link>
            </div>
          </div>
        </div>
      </div>

      <!-- Admin: recent movements -->
      <div v-if="isAdmin && recentMovements.length > 0" class="bg-white rounded-xl shadow-sm border">
        <div class="px-6 py-4 border-b">
          <h2 class="font-semibold text-gray-900">📋 Mouvements récents</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead><tr class="text-xs text-gray-500 uppercase border-b bg-gray-50">
              <th class="px-4 py-3 text-left">Équipement</th>
              <th class="px-4 py-3 text-left">Tag</th>
              <th class="px-4 py-3 text-left">Action</th>
              <th class="px-4 py-3 text-left">Utilisateur</th>
              <th class="px-4 py-3 text-left">Date</th>
            </tr></thead>
            <tbody>
              <tr v-for="m in recentMovements" :key="m.id" class="border-b hover:bg-gray-50">
                <td class="px-4 py-3 font-medium">{{ m.asset }}</td>
                <td class="px-4 py-3 text-gray-500">{{ m.asset_tag }}</td>
                <td class="px-4 py-3"><span class="text-xs font-medium px-2 py-1 rounded bg-blue-50 text-blue-700">{{ m.action }}</span></td>
                <td class="px-4 py-3 text-gray-600">{{ m.user }}</td>
                <td class="px-4 py-3 text-gray-500 text-xs">{{ m.created_at }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
