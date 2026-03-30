<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'

const page = usePage()

const props = defineProps({
  canSeeAllEvents: { type: Boolean, default: false },
})

// --- State ---
const today = new Date()
const currentYear = ref(today.getFullYear())
const currentMonth = ref(today.getMonth()) // 0-indexed

const events = ref([])
const loading = ref(true)
const selectedDay = ref(null)
const selectedDayEvents = ref([])
const showDayModal = ref(false)

// ── Vues (month / week / day) ────────────────────────────────────────────────
const currentView     = ref('month')  // 'month' | 'week' | 'day'
const viewDate        = ref(new Date(today))

// --- Load calendar events (reservations + salles) ---
async function loadEvents() {
  loading.value = true
  try {
    const [resvRes, roomRes] = await Promise.all([
      axios.get(route('reservations.calendar')),
      axios.get(route('rooms.calendar')),
    ])
    events.value = [...resvRes.data, ...roomRes.data]
  } catch (e) {
    // silently fail
  } finally {
    loading.value = false
  }
}

onMounted(loadEvents)

// --- Calendar computation ---
const monthNames = [
  'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
  'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
]
const dayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']

function prevMonth() {
  if (currentMonth.value === 0) { currentMonth.value = 11; currentYear.value-- }
  else currentMonth.value--
}
function nextMonth() {
  if (currentMonth.value === 11) { currentMonth.value = 0; currentYear.value++ }
  else currentMonth.value++
}
function goToday() {
  currentYear.value = today.getFullYear()
  currentMonth.value = today.getMonth()
  viewDate.value = new Date(today)
}

function prevPeriod() {
  if (currentView.value === 'month') { prevMonth(); return }
  const d = new Date(viewDate.value)
  if (currentView.value === 'week') d.setDate(d.getDate() - 7)
  else d.setDate(d.getDate() - 1)
  viewDate.value = d
}
function nextPeriod() {
  if (currentView.value === 'month') { nextMonth(); return }
  const d = new Date(viewDate.value)
  if (currentView.value === 'week') d.setDate(d.getDate() + 7)
  else d.setDate(d.getDate() + 1)
  viewDate.value = d
}

// ── Vue semaine ──────────────────────────────────────────────────────────────
const weekDays = computed(() => {
  const d = new Date(viewDate.value)
  let dow = d.getDay() - 1; if (dow < 0) dow = 6
  const monday = new Date(d); monday.setDate(d.getDate() - dow); monday.setHours(0,0,0,0)
  return Array.from({ length: 7 }, (_, i) => { const x = new Date(monday); x.setDate(monday.getDate() + i); return x })
})

const weekHeader = computed(() => {
  const d = weekDays.value
  const s = d[0].toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' })
  const e = d[6].toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
  return `Semaine du ${s} au ${e}`
})

// ── Vue jour ─────────────────────────────────────────────────────────────────
const dayHeader = computed(() =>
  viewDate.value.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
)
const dayViewEvents = computed(() => eventsForDay(viewDate.value))

// Libellé de la navigation (titre au centre)
const navTitle = computed(() => {
  if (currentView.value === 'month') return `${monthNames[currentMonth.value]} ${currentYear.value}`
  if (currentView.value === 'week')  return weekHeader.value
  return dayHeader.value.charAt(0).toUpperCase() + dayHeader.value.slice(1)
})

// Naviguer vers un jour depuis la vue semaine → passe en vue jour
function switchToDay(date) {
  viewDate.value = new Date(date)
  currentView.value = 'day'
}

// Generate calendar grid (6 weeks × 7 days)
const calendarDays = computed(() => {
  const year = currentYear.value
  const month = currentMonth.value

  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)

  // Weekday of first day (Monday = 0)
  let startDow = firstDay.getDay() - 1
  if (startDow < 0) startDow = 6

  const days = []

  // Previous month padding
  for (let i = startDow - 1; i >= 0; i--) {
    const d = new Date(year, month, -i)
    days.push({ date: d, isCurrentMonth: false })
  }

  // Current month
  for (let d = 1; d <= lastDay.getDate(); d++) {
    days.push({ date: new Date(year, month, d), isCurrentMonth: true })
  }

  // Next month padding
  const remaining = 42 - days.length
  for (let d = 1; d <= remaining; d++) {
    days.push({ date: new Date(year, month + 1, d), isCurrentMonth: false })
  }

  return days
})

// Get events for a given date
function eventsForDay(date) {
  const dateStr = date.toISOString().split('T')[0]
  return events.value.filter(ev => {
    const start = ev.start.split('T')[0]
    const end = ev.end.split('T')[0]
    return dateStr >= start && dateStr <= end
  })
}

function isToday(date) {
  return date.toDateString() === today.toDateString()
}

function openDay(day) {
  selectedDay.value = day.date
  selectedDayEvents.value = eventsForDay(day.date)
  showDayModal.value = true
}

// Status colors for events (CSS class pour reservations equipements)
function eventBgColor(ev) {
  // Salles : couleur hex directe
  if (ev.extendedProps?.type === 'room') return ''
  const map = {
    pending: 'bg-amber-400',
    approved: 'bg-blue-500',
    active: 'bg-green-500',
    returned: 'bg-gray-400',
    cancelled: 'bg-red-400',
    rejected: 'bg-red-400',
  }
  return map[ev.extendedProps?.status] ?? 'bg-gray-400'
}

// Style inline pour les salles
function eventInlineStyle(ev) {
  if (ev.extendedProps?.type === 'room') return { backgroundColor: ev.backgroundColor }
  return {}
}

function statusLabel(status) {
  const map = {
    pending: 'En attente',
    approved: 'Validée',
    active: 'En cours',
    returned: 'Retourné',
    cancelled: 'Annulée',
    rejected: 'Refusée',
  }
  return map[status] ?? status
}

function formatDate(date) {
  return date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

function formatEventTime(isoStr) {
  const d = new Date(isoStr)
  return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }) +
    ' ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <AppLayout>
    <div class="max-w-5xl">

      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Calendrier des réservations</h1>
          <p class="text-gray-500 text-sm mt-1">Vue mensuelle · Cliquez sur un jour pour voir les détails</p>
        </div>

        <!-- Legend -->
        <div class="flex items-center gap-3 text-xs flex-wrap">
          <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-amber-400 inline-block"></span> En attente</div>
          <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-blue-500 inline-block"></span> Validée</div>
          <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-green-500 inline-block"></span> En cours</div>
          <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-gray-400 inline-block"></span> Retourné</div>
          <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-violet-500 inline-block"></span> 🏛 Salle</div>
        </div>
      </div>

      <!-- Calendar card -->
      <div class="bg-white rounded-2xl shadow-sm border overflow-hidden">

        <!-- Navigation bar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-5 py-4 border-b bg-gray-50">
          <!-- Vue switcher -->
          <div class="flex rounded-lg border border-gray-200 overflow-hidden text-xs font-medium">
            <button v-for="v in [['month','Mois'],['week','Semaine'],['day','Jour']]" :key="v[0]"
              @click="currentView = v[0]"
              :class="['px-3 py-1.5 transition', currentView === v[0] ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">
              {{ v[1] }}
            </button>
          </div>

          <div class="flex items-center gap-3">
            <button @click="prevPeriod" class="p-2 rounded-lg hover:bg-gray-200 transition text-gray-600">
              <ChevronLeftIcon class="w-5 h-5" />
            </button>
            <h2 class="text-sm font-bold text-gray-900 text-center min-w-0 max-w-56 truncate">{{ navTitle }}</h2>
            <button @click="nextPeriod" class="p-2 rounded-lg hover:bg-gray-200 transition text-gray-600">
              <ChevronRightIcon class="w-5 h-5" />
            </button>
          </div>

          <button @click="goToday"
                  class="text-xs text-blue-600 border border-blue-200 px-3 py-1.5 rounded-lg hover:bg-blue-50 transition">
            Aujourd'hui
          </button>
        </div>

        <!-- Loading overlay -->
        <div v-if="loading" class="flex items-center justify-center py-20 text-gray-400">
          <div class="text-center">
            <div class="w-8 h-8 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
            <p class="text-sm">Chargement…</p>
          </div>
        </div>

        <template v-else>

          <!-- ═══ VUE MOIS ═══ -->
          <template v-if="currentView === 'month'">
            <!-- Day names -->
            <div class="grid grid-cols-7 border-b">
              <div v-for="day in dayNames" :key="day"
                   class="text-center text-xs font-semibold text-gray-500 py-2 uppercase tracking-wide">
                {{ day }}
              </div>
            </div>
            <div class="grid grid-cols-7">
              <div v-for="(day, idx) in calendarDays" :key="idx"
                   @click="openDay(day)"
                   class="min-h-[90px] border-r border-b p-1.5 cursor-pointer hover:bg-blue-50/50 transition relative"
                   :class="[!day.isCurrentMonth ? 'bg-gray-50/70' : '', isToday(day.date) ? 'ring-2 ring-blue-400 ring-inset' : '']">
                <div :class="['text-xs font-semibold mb-1 w-6 h-6 flex items-center justify-center rounded-full',
                  isToday(day.date) ? 'bg-blue-600 text-white' : day.isCurrentMonth ? 'text-gray-800' : 'text-gray-300']">
                  {{ day.date.getDate() }}
                </div>
                <div class="space-y-0.5">
                  <div v-for="(ev, evIdx) in eventsForDay(day.date).slice(0, 3)" :key="evIdx"
                       :class="['text-white text-[10px] font-medium px-1.5 py-0.5 rounded truncate leading-tight', eventBgColor(ev)]"
                       :style="eventInlineStyle(ev)">
                    {{ ev.extendedProps?.type === 'room' ? '🏛 ' : '' }}{{ ev.title }}
                  </div>
                  <div v-if="eventsForDay(day.date).length > 3" class="text-[10px] text-gray-500 pl-1">
                    +{{ eventsForDay(day.date).length - 3 }} autre(s)
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- ═══ VUE SEMAINE ═══ -->
          <template v-else-if="currentView === 'week'">
            <div class="grid grid-cols-7 border-b">
              <div v-for="d in weekDays" :key="d.toISOString()"
                   @click="switchToDay(d)"
                   class="text-center py-2 border-r cursor-pointer hover:bg-blue-50 transition"
                   :class="isToday(d) ? 'bg-blue-50' : ''">
                <p class="text-xs font-semibold text-gray-500 uppercase">
                  {{ d.toLocaleDateString('fr-FR', { weekday: 'short' }) }}
                </p>
                <div :class="['text-sm font-bold mx-auto mt-0.5 w-7 h-7 flex items-center justify-center rounded-full',
                  isToday(d) ? 'bg-blue-600 text-white' : 'text-gray-800']">
                  {{ d.getDate() }}
                </div>
              </div>
            </div>
            <div class="grid grid-cols-7 min-h-[300px]">
              <div v-for="d in weekDays" :key="'ev-'+d.toISOString()"
                   @click="switchToDay(d)"
                   class="border-r p-1.5 cursor-pointer hover:bg-blue-50/50 transition min-h-[300px]"
                   :class="isToday(d) ? 'bg-blue-50/40' : ''">
                <div class="space-y-0.5">
                  <div v-for="ev in eventsForDay(d)" :key="ev.id"
                       :class="['text-white text-[10px] font-medium px-1.5 py-0.5 rounded truncate leading-tight', eventBgColor(ev)]"
                       :style="eventInlineStyle(ev)">
                    {{ ev.extendedProps?.type === 'room' ? '🏛 ' : '' }}{{ ev.title }}
                  </div>
                  <div v-if="!eventsForDay(d).length" class="text-[10px] text-gray-300 pl-1 mt-2">—</div>
                </div>
              </div>
            </div>
          </template>

          <!-- ═══ VUE JOUR ═══ -->
          <template v-else>
            <div class="p-5">
              <div v-if="dayViewEvents.length === 0" class="text-center text-gray-400 py-12">
                <p class="text-3xl mb-2">📅</p>
                <p class="text-sm">Aucune réservation ce jour</p>
              </div>
              <div v-else class="space-y-3 max-w-2xl mx-auto">
                <div v-for="ev in dayViewEvents" :key="ev.id" class="rounded-xl border p-4 hover:shadow-sm transition">
                  <div class="flex items-start gap-3">
                    <div :class="['w-2 flex-shrink-0 rounded-full self-stretch', eventBgColor(ev)]"
                         :style="eventInlineStyle(ev)" style="min-height:40px"></div>
                    <div class="flex-1 min-w-0">
                      <p class="font-semibold text-gray-900 text-sm">
                        <span v-if="ev.extendedProps?.type === 'room'" class="text-xs text-violet-600 font-semibold mr-1">🏛 Salle</span>
                        {{ ev.title }}
                      </p>
                      <p class="text-xs text-gray-500 mt-0.5">{{ formatEventTime(ev.start) }} → {{ formatEventTime(ev.end) }}</p>
                      <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                        <span :class="['text-xs font-medium px-2 py-0.5 rounded-full text-white', eventBgColor(ev)]"
                              :style="eventInlineStyle(ev)">
                          {{ ev.extendedProps?.status_label ?? statusLabel(ev.extendedProps?.status) }}
                        </span>
                        <span v-if="ev.extendedProps?.assets_count" class="text-xs text-gray-400">
                          {{ ev.extendedProps.assets_count }} équipement(s)
                        </span>
                        <span v-if="ev.extendedProps?.room" class="text-xs text-gray-400">📍 {{ ev.extendedProps.room }}</span>
                      </div>
                      <div v-if="ev.extendedProps?.assets?.length" class="mt-2 border-t border-gray-100 pt-2">
                        <ul class="space-y-1">
                          <li v-for="asset in ev.extendedProps.assets" :key="asset.id" class="flex items-center gap-2 text-xs text-gray-700">
                            <span class="text-gray-400">📦</span>
                            <span class="font-medium">{{ asset.name }}</span>
                            <span class="text-gray-400 ml-auto">{{ asset.asset_tag }}</span>
                          </li>
                        </ul>
                      </div>
                      <a :href="ev.extendedProps?.reservation_url ?? ev.extendedProps?.room_url"
                         class="mt-2 block text-xs text-blue-600 hover:underline font-medium">
                        {{ ev.extendedProps?.type === 'room' ? 'Voir les salles →' : 'Voir la réservation →' }}
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>

        </template>
      </div>
    </div>

    <!-- Day detail modal -->
    <Teleport to="body">
      <div v-if="showDayModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[80vh] overflow-hidden flex flex-col">
          <!-- Modal header -->
          <div class="flex items-center justify-between px-5 py-4 border-b bg-gray-50">
            <h3 class="font-bold text-gray-900 capitalize text-sm">{{ selectedDay ? formatDate(selectedDay) : '' }}</h3>
            <button @click="showDayModal=false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
          </div>

          <!-- Events list -->
          <div class="overflow-y-auto flex-1 p-4">
            <div v-if="selectedDayEvents.length === 0" class="text-center text-gray-400 py-8">
              <p class="text-2xl mb-2">📅</p>
              <p class="text-sm">Aucune réservation ce jour</p>
            </div>

            <div v-else class="space-y-3">
              <div v-for="ev in selectedDayEvents" :key="ev.id"
                   class="rounded-xl border p-3 hover:shadow-sm transition">
                <!-- Color bar -->
                <div class="flex items-start gap-3">
                  <div :class="['w-2 flex-shrink-0 rounded-full mt-1 self-stretch', eventBgColor(ev)]"
                       :style="eventInlineStyle(ev)"
                       style="min-height: 40px"></div>
                  <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-900 text-sm truncate">
                      <span v-if="ev.extendedProps?.type === 'room'" class="text-xs text-violet-600 font-semibold mr-1">🏛 Salle</span>
                      {{ ev.title }}
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5">
                      {{ formatEventTime(ev.start) }} → {{ formatEventTime(ev.end) }}
                    </p>
                    <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                      <span :class="['text-xs font-medium px-2 py-0.5 rounded-full text-white', eventBgColor(ev)]"
                            :style="eventInlineStyle(ev)">
                        {{ ev.extendedProps?.status_label ?? statusLabel(ev.extendedProps?.status) }}
                      </span>
                      <span v-if="ev.extendedProps?.assets_count" class="text-xs text-gray-400">
                        {{ ev.extendedProps?.assets_count }} équipement(s)
                      </span>
                      <span v-if="ev.extendedProps?.room" class="text-xs text-gray-400">
                        📍 {{ ev.extendedProps.room }}
                      </span>
                    </div>
                    <!-- Equipment list -->
                    <div v-if="ev.extendedProps?.assets?.length" class="mt-2 border-t border-gray-100 pt-2">
                      <p class="text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Équipements réservés</p>
                      <ul class="space-y-1">
                        <li v-for="asset in ev.extendedProps.assets" :key="asset.id"
                            class="flex items-center gap-2 text-xs text-gray-700">
                          <span class="text-gray-400 flex-shrink-0">📦</span>
                          <span class="font-medium">{{ asset.name }}</span>
                          <span class="text-gray-400 ml-auto flex-shrink-0">{{ asset.asset_tag }}</span>
                        </li>
                      </ul>
                    </div>
                    <!-- Link -->
                    <div class="mt-2">
                      <a :href="ev.extendedProps?.reservation_url ?? ev.extendedProps?.room_url"
                         class="text-xs text-blue-600 hover:underline font-medium">
                        {{ ev.extendedProps?.type === 'room' ? 'Voir les salles →' : 'Voir la réservation →' }}
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<style scoped>
.animate-spin {
  animation: spin 0.8s linear infinite;
}
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
