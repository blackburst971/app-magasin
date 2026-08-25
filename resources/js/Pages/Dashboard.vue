<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import { Link, usePage } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Bar, Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale, LinearScale, BarElement, ArcElement,
  Title, Tooltip, Legend, PointElement, LineElement,
} from 'chart.js'
import { EyeIcon, EyeSlashIcon, ClockIcon, PencilSquareIcon } from '@heroicons/vue/24/outline'

ChartJS.register(
  CategoryScale, LinearScale, BarElement, ArcElement,
  Title, Tooltip, Legend, PointElement, LineElement,
)

const page = usePage()
const isAdmin = computed(() => page.props.auth.user?.is_admin)

const props = defineProps({
  stats:                 { type: Object,  default: () => ({}) },
  pendingReservations:   { type: Array,   default: () => [] },
  approvedReservations:  { type: Array,   default: () => [] },
  recentMovements:       { type: Array,   default: () => [] },
  myReservations:        { type: Array,   default: () => [] },
  upcomingReturns:       { type: Array,   default: () => [] },
  overdueReturns:        { type: Array,   default: () => [] },
  checkoutReservations:  { type: Array,   default: () => [] },
  reminderMinutes:       { type: Number,  default: 30 },
  reminderEnabled:       { type: Boolean, default: true },
  autoCancelEnabled:     { type: Boolean, default: false },
  autoCancelHours:       { type: Number,  default: 24 },
  reservationsByMonth:   { type: Array,   default: () => [] },
  assetsByStatus:        { type: Array,   default: () => [] },
  topAssets:             { type: Array,   default: () => [] },
  todoTasks:             { type: Array,   default: () => [] },
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

// ─── Compte à rebours ────────────────────────────────────────────────────────
const showCountdowns = ref(localStorage.getItem('dashboard_show_countdowns') !== 'false')
const ticker = ref(0)
let countdownInterval = null

onMounted(() => {
  countdownInterval = setInterval(() => { ticker.value++ }, 1000)
})
onUnmounted(() => {
  if (countdownInterval) clearInterval(countdownInterval)
})

function toggleCountdowns() {
  showCountdowns.value = !showCountdowns.value
  localStorage.setItem('dashboard_show_countdowns', String(showCountdowns.value))
}

/**
 * Retourne { text, urgency } pour une date de fin (timestamp Unix en secondes).
 * urgency: 'safe' | 'warning' | 'critical' | 'overdue'
 */
function getCountdown(endTs) {
  ticker.value // dépendance réactive — se met à jour chaque seconde
  const diff = endTs * 1000 - Date.now() // ms

  const abs = Math.abs(diff)
  const d = Math.floor(abs / 86400000)
  const h = Math.floor((abs % 86400000) / 3600000)
  const m = Math.floor((abs % 3600000) / 60000)
  const s = Math.floor((abs % 60000) / 1000)
  const pad = (n) => String(n).padStart(2, '0')

  if (diff > 0) {
    const text = d > 0
      ? `${d}j ${pad(h)}h ${pad(m)}m ${pad(s)}s`
      : h > 0
        ? `${pad(h)}h ${pad(m)}m ${pad(s)}s`
        : `${pad(m)}m ${pad(s)}s`
    const urgency = d >= 2 ? 'safe' : d === 1 || h >= 4 ? 'warning' : 'critical'
    return { text, urgency }
  } else {
    const text = d > 0
      ? `+${d}j ${pad(h)}h ${pad(m)}m ${pad(s)}s`
      : h > 0
        ? `+${pad(h)}h ${pad(m)}m ${pad(s)}s`
        : `+${pad(m)}m ${pad(s)}s`
    return { text, urgency: 'overdue' }
  }
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

// ─── Todo Summary ─────────────────────────────────────────────────────────────
const todoOverdue   = computed(() => props.todoTasks.filter(t => t.is_overdue))
const todoToday     = computed(() => props.todoTasks.filter(t => t.is_today))
const todoTomorrow  = computed(() => props.todoTasks.filter(t => t.is_tomorrow))
const todoUpcoming  = computed(() => props.todoTasks.filter(t => !t.is_overdue && !t.is_today && !t.is_tomorrow))

// Grouper les tâches à venir par date
const todoUpcomingByDate = computed(() => {
  const groups = {}
  for (const t of todoUpcoming.value) {
    if (!groups[t.due_date]) groups[t.due_date] = { label: t.due_label, tasks: [] }
    groups[t.due_date].tasks.push(t)
  }
  return Object.values(groups)
})

const todoPriorityStyle = {
  urgent: 'bg-red-100 text-red-700 border border-red-200',
  high:   'bg-orange-100 text-orange-700 border border-orange-200',
  medium: 'bg-yellow-100 text-yellow-700 border border-yellow-200',
  low:    'bg-blue-100 text-blue-700 border border-blue-200',
  none:   'bg-gray-100 text-gray-500 border border-gray-200',
}
const todoPriorityLabel = { urgent: '🔴 Urgent', high: '🟠 Haute', medium: '🟡 Normale', low: '🔵 Basse', none: '—' }
const todoStatusStyle = {
  todo:        'bg-gray-100 text-gray-600',
  in_progress: 'bg-indigo-100 text-indigo-700',
}
</script>

<template>
  <AppLayout>
    <div>
      <div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Tableau de bord</h1>
          <p class="text-gray-500 text-sm mt-1">Bienvenue, {{ $page.props.auth.user?.name }}</p>
        </div>
        <!-- Toggle compteurs -->
        <button
          v-if="isAdmin && checkoutReservations.length > 0"
          @click="toggleCountdowns"
          class="flex items-center gap-2 px-3 py-1.5 rounded-lg border text-sm font-medium transition"
          :class="showCountdowns
            ? 'bg-indigo-50 border-indigo-300 text-indigo-700 hover:bg-indigo-100'
            : 'bg-gray-50 border-gray-300 text-gray-500 hover:bg-gray-100'">
          <EyeSlashIcon v-if="showCountdowns" class="w-4 h-4" />
          <EyeIcon v-else class="w-4 h-4" />
          {{ showCountdowns ? 'Masquer les compteurs' : 'Afficher les compteurs' }}
        </button>
        <button
          v-if="!isAdmin && myReservations.some(r => r.status === 'active')"
          @click="toggleCountdowns"
          class="flex items-center gap-2 px-3 py-1.5 rounded-lg border text-sm font-medium transition"
          :class="showCountdowns
            ? 'bg-indigo-50 border-indigo-300 text-indigo-700 hover:bg-indigo-100'
            : 'bg-gray-50 border-gray-300 text-gray-500 hover:bg-gray-100'">
          <EyeSlashIcon v-if="showCountdowns" class="w-4 h-4" />
          <EyeIcon v-else class="w-4 h-4" />
          {{ showCountdowns ? 'Masquer les compteurs' : 'Afficher les compteurs' }}
        </button>
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

      <!-- Admin: alerte réservations en attente de signature superviseur -->
      <div v-if="isAdmin && (stats?.awaiting_supervisor ?? 0) > 0"
           class="rounded-xl border border-purple-300 bg-purple-50 shadow-sm mb-6 flex items-center justify-between gap-4 px-6 py-4">
        <div class="flex items-center gap-3">
          <PencilSquareIcon class="w-6 h-6 text-purple-600 flex-shrink-0" />
          <div>
            <p class="font-semibold text-purple-900">
              {{ stats.awaiting_supervisor }} réservation(s) en attente de signature responsable
            </p>
            <p class="text-xs text-purple-600 mt-0.5">
              Faites signer le responsable sur tablette pour débloquer ces demandes.
            </p>
          </div>
        </div>
        <Link :href="route('admin.proxy-sign.index')"
              class="flex-shrink-0 flex items-center gap-1.5 bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition shadow-sm">
          <PencilSquareIcon class="w-4 h-4" />
          Faire signer
        </Link>
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

      <!-- Admin: sorties en cours — compteurs individuels -->
      <div v-if="isAdmin && checkoutReservations.length > 0"
           class="rounded-xl shadow-sm border mb-6 overflow-hidden"
           :class="checkoutReservations.some(r => r.is_overdue) ? 'border-red-300' : 'border-blue-200'">
        <div class="flex items-center justify-between px-6 py-4 border-b"
             :class="checkoutReservations.some(r => r.is_overdue) ? 'bg-red-50' : 'bg-blue-50'">
          <div class="flex items-center gap-2">
            <span class="text-lg">📦</span>
            <div>
              <h2 class="font-semibold"
                  :class="checkoutReservations.some(r => r.is_overdue) ? 'text-red-900' : 'text-blue-900'">
                Matériel sorti
                <span class="ml-1.5 text-xs font-bold px-2 py-0.5 rounded-full"
                      :class="checkoutReservations.some(r => r.is_overdue) ? 'bg-red-200 text-red-700' : 'bg-blue-100 text-blue-700'">
                  {{ checkoutReservations.length }}
                </span>
              </h2>
              <p class="text-xs mt-0.5"
                 :class="checkoutReservations.some(r => r.is_overdue) ? 'text-red-600' : 'text-blue-600'">
                Compteurs en temps réel — {{ checkoutReservations.filter(r => r.is_overdue).length }} en retard
              </p>
            </div>
          </div>
          <Link :href="route('returns.index')"
                class="text-sm font-medium hover:underline"
                :class="checkoutReservations.some(r => r.is_overdue) ? 'text-red-700' : 'text-blue-600'">
            Gérer les retours
          </Link>
        </div>
        <div class="divide-y bg-white"
             :class="checkoutReservations.some(r => r.is_overdue) ? 'divide-red-100' : 'divide-blue-50'">
          <div v-for="r in checkoutReservations" :key="'co-' + r.id" class="overflow-hidden">
            <!-- Countdown individuel -->
            <Transition name="slide-down">
              <div v-if="showCountdowns"
                   class="px-6 py-3 flex items-center justify-between gap-4"
                   :class="{
                     'bg-red-600':     getCountdown(r.end_date_ts).urgency === 'overdue',
                     'bg-red-500':     getCountdown(r.end_date_ts).urgency === 'critical',
                     'bg-amber-500':   getCountdown(r.end_date_ts).urgency === 'warning',
                     'bg-emerald-600': getCountdown(r.end_date_ts).urgency === 'safe',
                   }">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0 text-sm font-bold text-white">
                    {{ r.user.charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <p class="text-xs text-white/75 leading-none mb-0.5">
                      {{ r.user }} —
                      {{ getCountdown(r.end_date_ts).urgency === 'overdue' ? 'retard de' : 'retour dans' }}
                    </p>
                    <p class="text-3xl font-mono font-black text-white tracking-tight leading-none"
                       :class="{ 'countdown-pulse': ['overdue','critical'].includes(getCountdown(r.end_date_ts).urgency) }">
                      {{ getCountdown(r.end_date_ts).text }}
                    </p>
                  </div>
                </div>
                <div class="text-right flex-shrink-0">
                  <p class="text-xs text-white/70">{{ r.is_overdue ? 'Devait être rendu le' : 'À rendre le' }}</p>
                  <p class="text-sm font-bold text-white">{{ r.end_date }}</p>
                  <p class="text-xs text-white/70 mt-0.5">{{ r.assets_count }} équipement(s)</p>
                </div>
              </div>
            </Transition>
            <!-- Ligne résumé -->
            <div class="px-6 py-3 flex items-center justify-between hover:bg-gray-50 transition"
                 :class="{ 'bg-red-50': r.is_overdue && !showCountdowns }">
              <div class="min-w-0 flex-1">
                <p class="font-medium text-sm text-gray-900 truncate flex items-center gap-2">
                  {{ r.title }}
                  <span v-if="r.is_overdue" class="text-xs font-bold text-red-600 bg-red-100 px-1.5 py-0.5 rounded-full">
                    En retard
                  </span>
                </p>
                <p class="text-xs text-gray-500 truncate">{{ r.user }} — {{ r.assets }}</p>
              </div>
              <div class="flex items-center gap-3 ml-4 flex-shrink-0">
                <span v-if="!showCountdowns"
                      class="text-xs font-semibold px-2 py-0.5 rounded-full font-mono"
                      :class="r.is_overdue
                        ? 'text-red-700 bg-red-100'
                        : getCountdown(r.end_date_ts).urgency === 'warning'
                          ? 'text-amber-700 bg-amber-100'
                          : getCountdown(r.end_date_ts).urgency === 'critical'
                            ? 'text-red-700 bg-red-100'
                            : 'text-blue-700 bg-blue-100'">
                  {{ getCountdown(r.end_date_ts).text }}
                </span>
                <span class="text-xs text-gray-400">{{ r.end_date }}</span>
                <Link :href="route('returns.index')"
                      class="text-xs text-white px-3 py-1 rounded-full transition"
                      :class="r.is_overdue ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'">
                  Retour
                </Link>
              </div>
            </div>
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
          <div v-for="r in overdueReturns" :key="r.id" class="overflow-hidden">
            <!-- Countdown retard -->
            <Transition name="slide-down">
              <div v-if="showCountdowns"
                   class="px-6 py-2.5 bg-red-600 flex items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                  <ClockIcon class="w-4 h-4 text-white/80 flex-shrink-0" />
                  <div>
                    <p class="text-xs text-red-200 leading-none mb-0.5">{{ r.user }} — retard</p>
                    <p class="text-2xl font-mono font-black text-white tracking-tight leading-none countdown-pulse">
                      {{ getCountdown(r.end_date_ts).text }}
                    </p>
                  </div>
                </div>
                <div class="text-right flex-shrink-0">
                  <p class="text-xs text-red-200">Devait être rendu le</p>
                  <p class="text-sm font-bold text-white">{{ r.end_date }}</p>
                </div>
              </div>
            </Transition>
            <div class="px-6 py-3 flex items-center justify-between hover:bg-red-50 transition">
              <div class="min-w-0 flex-1">
                <p class="font-medium text-sm text-gray-900 truncate">{{ r.title }}</p>
                <p class="text-xs text-gray-500 truncate">{{ r.user }} — {{ r.assets }}</p>
              </div>
              <div class="flex items-center gap-3 ml-4 flex-shrink-0">
                <span v-if="!showCountdowns" class="text-xs font-semibold text-red-600 bg-red-100 px-2 py-0.5 rounded-full">
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
          <div v-for="r in upcomingReturns" :key="r.id" class="overflow-hidden">
            <!-- Countdown retour imminent -->
            <Transition name="slide-down">
              <div v-if="showCountdowns"
                   class="px-6 py-2.5 flex items-center justify-between gap-4"
                   :class="{
                     'bg-red-500':    getCountdown(r.end_date_ts).urgency === 'critical',
                     'bg-amber-500':  getCountdown(r.end_date_ts).urgency === 'warning',
                     'bg-emerald-600':getCountdown(r.end_date_ts).urgency === 'safe',
                   }">
                <div class="flex items-center gap-2">
                  <ClockIcon class="w-4 h-4 text-white/80 flex-shrink-0" />
                  <div>
                    <p class="text-xs text-white/70 leading-none mb-0.5">{{ r.user }} — retour dans</p>
                    <p class="text-2xl font-mono font-black text-white tracking-tight leading-none"
                       :class="{ 'countdown-pulse': getCountdown(r.end_date_ts).urgency === 'critical' }">
                      {{ getCountdown(r.end_date_ts).text }}
                    </p>
                  </div>
                </div>
                <div class="text-right flex-shrink-0">
                  <p class="text-xs text-white/70">À rendre le</p>
                  <p class="text-sm font-bold text-white">{{ r.end_date }}</p>
                </div>
              </div>
            </Transition>
            <div class="px-6 py-3 flex items-center justify-between hover:bg-amber-50 transition">
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
          <div v-for="r in myReservations" :key="r.id" class="overflow-hidden">
            <!-- Countdown retour (réservations actives uniquement) -->
            <Transition name="slide-down">
              <div v-if="showCountdowns && r.status === 'active'"
                   class="px-6 py-2.5 flex items-center justify-between gap-4"
                   :class="{
                     'bg-emerald-600': getCountdown(r.end_date_ts).urgency === 'safe',
                     'bg-amber-500':   getCountdown(r.end_date_ts).urgency === 'warning',
                     'bg-red-500':     getCountdown(r.end_date_ts).urgency === 'critical',
                     'bg-red-600':     getCountdown(r.end_date_ts).urgency === 'overdue',
                   }">
                <div class="flex items-center gap-2">
                  <ClockIcon class="w-4 h-4 text-white/80 flex-shrink-0" />
                  <div>
                    <p class="text-xs text-white/70 leading-none mb-0.5">
                      {{ getCountdown(r.end_date_ts).urgency === 'overdue' ? 'En retard de' : 'À rendre dans' }}
                    </p>
                    <p class="text-2xl font-mono font-black text-white tracking-tight leading-none"
                       :class="{ 'countdown-pulse': ['critical','overdue'].includes(getCountdown(r.end_date_ts).urgency) }">
                      {{ getCountdown(r.end_date_ts).text }}
                    </p>
                  </div>
                </div>
                <div class="text-right flex-shrink-0">
                  <p class="text-xs text-white/70">Date limite</p>
                  <p class="text-sm font-bold text-white">{{ r.end_date }}</p>
                </div>
              </div>
            </Transition>
            <div class="px-6 py-4 flex items-center justify-between hover:bg-gray-50">
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
      </div>

      <!-- Admin: To-Do récapitulatif -->
      <div v-if="isAdmin && todoTasks.length > 0" class="bg-white rounded-xl shadow-sm border mb-6 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b bg-indigo-50">
          <div class="flex items-center gap-2">
            <span class="text-lg">📋</span>
            <h2 class="font-semibold text-indigo-900">Tâches à faire</h2>
            <span class="ml-2 text-xs font-bold px-2 py-0.5 rounded-full bg-indigo-200 text-indigo-800">
              {{ todoTasks.length }}
            </span>
          </div>
          <Link :href="route('todo.index')" class="text-xs text-indigo-600 hover:underline font-medium">
            Voir tout →
          </Link>
        </div>

        <div class="divide-y">

          <!-- En retard -->
          <div v-if="todoOverdue.length > 0">
            <div class="px-5 py-2 bg-red-50 flex items-center gap-2">
              <span class="text-xs font-bold text-red-700 uppercase tracking-wide">🔴 En retard</span>
              <span class="text-xs font-bold px-1.5 py-0.5 rounded-full bg-red-200 text-red-800">{{ todoOverdue.length }}</span>
            </div>
            <div v-for="task in todoOverdue" :key="task.id"
                 class="px-5 py-3 flex items-start gap-3 hover:bg-red-50/40 transition">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-medium text-sm text-gray-900 truncate">{{ task.title }}</span>
                  <span v-if="task.priority !== 'none'" class="text-xs px-1.5 py-0.5 rounded-full font-semibold" :class="todoPriorityStyle[task.priority]">
                    {{ todoPriorityLabel[task.priority] }}
                  </span>
                  <span v-if="task.status === 'in_progress'" class="text-xs px-1.5 py-0.5 rounded-full font-medium" :class="todoStatusStyle[task.status]">
                    En cours
                  </span>
                </div>
                <div class="flex items-center gap-3 mt-1 flex-wrap">
                  <span class="text-xs text-red-600 font-semibold">Échéance : {{ task.due_label }}</span>
                  <span class="text-xs text-gray-500" v-if="task.assigned_to">👤 {{ task.assigned_to }}</span>
                  <span class="text-xs font-medium px-1.5 py-0.5 rounded" :style="{ backgroundColor: task.project.color + '22', color: task.project.color }">
                    {{ task.project.name }}
                  </span>
                  <span v-for="lbl in task.labels" :key="lbl.name"
                        class="text-xs px-1.5 py-0.5 rounded-full font-medium"
                        :style="{ backgroundColor: lbl.color + '22', color: lbl.color }">
                    {{ lbl.name }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Aujourd'hui -->
          <div v-if="todoToday.length > 0">
            <div class="px-5 py-2 bg-amber-50 flex items-center gap-2">
              <span class="text-xs font-bold text-amber-700 uppercase tracking-wide">⚡ Aujourd'hui</span>
              <span class="text-xs font-bold px-1.5 py-0.5 rounded-full bg-amber-200 text-amber-800">{{ todoToday.length }}</span>
            </div>
            <div v-for="task in todoToday" :key="task.id"
                 class="px-5 py-3 flex items-start gap-3 hover:bg-amber-50/40 transition">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-medium text-sm text-gray-900 truncate">{{ task.title }}</span>
                  <span v-if="task.priority !== 'none'" class="text-xs px-1.5 py-0.5 rounded-full font-semibold" :class="todoPriorityStyle[task.priority]">
                    {{ todoPriorityLabel[task.priority] }}
                  </span>
                  <span v-if="task.status === 'in_progress'" class="text-xs px-1.5 py-0.5 rounded-full font-medium" :class="todoStatusStyle[task.status]">
                    En cours
                  </span>
                </div>
                <div class="flex items-center gap-3 mt-1 flex-wrap">
                  <span class="text-xs text-gray-500" v-if="task.assigned_to">👤 {{ task.assigned_to }}</span>
                  <span class="text-xs font-medium px-1.5 py-0.5 rounded" :style="{ backgroundColor: task.project.color + '22', color: task.project.color }">
                    {{ task.project.name }}
                  </span>
                  <span v-for="lbl in task.labels" :key="lbl.name"
                        class="text-xs px-1.5 py-0.5 rounded-full font-medium"
                        :style="{ backgroundColor: lbl.color + '22', color: lbl.color }">
                    {{ lbl.name }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Demain -->
          <div v-if="todoTomorrow.length > 0">
            <div class="px-5 py-2 bg-blue-50 flex items-center gap-2">
              <span class="text-xs font-bold text-blue-700 uppercase tracking-wide">📅 Demain</span>
              <span class="text-xs font-bold px-1.5 py-0.5 rounded-full bg-blue-200 text-blue-800">{{ todoTomorrow.length }}</span>
            </div>
            <div v-for="task in todoTomorrow" :key="task.id"
                 class="px-5 py-3 flex items-start gap-3 hover:bg-blue-50/40 transition">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-medium text-sm text-gray-900 truncate">{{ task.title }}</span>
                  <span v-if="task.priority !== 'none'" class="text-xs px-1.5 py-0.5 rounded-full font-semibold" :class="todoPriorityStyle[task.priority]">
                    {{ todoPriorityLabel[task.priority] }}
                  </span>
                  <span v-if="task.status === 'in_progress'" class="text-xs px-1.5 py-0.5 rounded-full font-medium" :class="todoStatusStyle[task.status]">
                    En cours
                  </span>
                </div>
                <div class="flex items-center gap-3 mt-1 flex-wrap">
                  <span class="text-xs text-gray-500" v-if="task.assigned_to">👤 {{ task.assigned_to }}</span>
                  <span class="text-xs font-medium px-1.5 py-0.5 rounded" :style="{ backgroundColor: task.project.color + '22', color: task.project.color }">
                    {{ task.project.name }}
                  </span>
                  <span v-for="lbl in task.labels" :key="lbl.name"
                        class="text-xs px-1.5 py-0.5 rounded-full font-medium"
                        :style="{ backgroundColor: lbl.color + '22', color: lbl.color }">
                    {{ lbl.name }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- À venir (groupé par date) -->
          <template v-for="group in todoUpcomingByDate" :key="group.label">
            <div>
              <div class="px-5 py-2 bg-gray-50 flex items-center gap-2">
                <span class="text-xs font-bold text-gray-600 uppercase tracking-wide">📆 {{ group.label }}</span>
                <span class="text-xs font-bold px-1.5 py-0.5 rounded-full bg-gray-200 text-gray-700">{{ group.tasks.length }}</span>
              </div>
              <div v-for="task in group.tasks" :key="task.id"
                   class="px-5 py-3 flex items-start gap-3 hover:bg-gray-50 transition">
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-medium text-sm text-gray-900 truncate">{{ task.title }}</span>
                    <span v-if="task.priority !== 'none'" class="text-xs px-1.5 py-0.5 rounded-full font-semibold" :class="todoPriorityStyle[task.priority]">
                      {{ todoPriorityLabel[task.priority] }}
                    </span>
                    <span v-if="task.status === 'in_progress'" class="text-xs px-1.5 py-0.5 rounded-full font-medium" :class="todoStatusStyle[task.status]">
                      En cours
                    </span>
                  </div>
                  <div class="flex items-center gap-3 mt-1 flex-wrap">
                    <span class="text-xs text-gray-500" v-if="task.assigned_to">👤 {{ task.assigned_to }}</span>
                    <span class="text-xs font-medium px-1.5 py-0.5 rounded" :style="{ backgroundColor: task.project.color + '22', color: task.project.color }">
                      {{ task.project.name }}
                    </span>
                    <span v-for="lbl in task.labels" :key="lbl.name"
                          class="text-xs px-1.5 py-0.5 rounded-full font-medium"
                          :style="{ backgroundColor: lbl.color + '22', color: lbl.color }">
                      {{ lbl.name }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </template>

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

<style scoped>
.slide-down-enter-active { transition: all 0.3s ease; }
.slide-down-leave-active { transition: all 0.25s ease; }
.slide-down-enter-from   { opacity: 0; transform: translateY(-6px); max-height: 0; }
.slide-down-leave-to     { opacity: 0; transform: translateY(-6px); max-height: 0; }
.slide-down-enter-to,
.slide-down-leave-from   { opacity: 1; transform: translateY(0); max-height: 100px; }

@keyframes countdown-blink {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.55; }
}
.countdown-pulse {
  animation: countdown-blink 1s ease-in-out infinite;
}
</style>
