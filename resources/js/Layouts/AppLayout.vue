<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import { useCartStore } from '@/stores/cart'
import { useSound } from '@/composables/useSound'
import axios from 'axios'

const { playSound } = useSound()
import {
  HomeIcon, CubeIcon, CalendarDaysIcon, ArrowUpTrayIcon,
  QrCodeIcon, ChartBarIcon, UsersIcon, Cog6ToothIcon,
  Bars3Icon, XMarkIcon, ClipboardDocumentListIcon,
  ArrowRightOnRectangleIcon, ShoppingCartIcon,
  TagIcon, MapPinIcon, BuildingStorefrontIcon,
  BellIcon, CubeTransparentIcon, ArrowLeftOnRectangleIcon,
  ArrowUturnLeftIcon, UserGroupIcon, ArchiveBoxIcon,
  ShieldCheckIcon, InboxStackIcon, WrenchScrewdriverIcon,
  KeyIcon, ComputerDesktopIcon, BuildingOfficeIcon,
  MagnifyingGlassIcon, ArchiveBoxXMarkIcon,
  ClipboardDocumentCheckIcon, SparklesIcon,
  CheckCircleIcon,
  ShoppingBagIcon,
  QueueListIcon,
} from '@heroicons/vue/24/outline'
import { BellIcon as BellSolid } from '@heroicons/vue/24/solid'

const page         = usePage()
const cart         = useCartStore()
const user         = computed(() => page.props.auth.user)
const isAdmin      = computed(() => user.value?.is_admin)
const isProfesseur = computed(() => user.value?.is_professeur)
const flash        = computed(() => page.props.flash)
const tabSettings  = computed(() => page.props.tabSettings)
const unreadCount      = computed(() => page.props.unreadNotifications ?? 0)
const localUnreadCount = ref(unreadCount.value)
const appLogos     = computed(() => page.props.appLogos)

const sidebarOpen = ref(false)

// ── Swipe pour ouvrir/fermer la sidebar sur mobile ───────────────────────────
const touchStartX = ref(0)
const touchStartY = ref(0)

function onTouchStart(e) {
  touchStartX.value = e.touches[0].clientX
  touchStartY.value = e.touches[0].clientY
}

function onTouchEnd(e) {
  const dx = e.changedTouches[0].clientX - touchStartX.value
  const dy = e.changedTouches[0].clientY - touchStartY.value
  // Ignorer les swipes trop verticaux (scroll)
  if (Math.abs(dy) > Math.abs(dx) * 0.8) return
  // Swipe droit depuis le bord gauche (≤40px) → ouvrir
  if (dx > 55 && touchStartX.value <= 40 && !sidebarOpen.value) {
    sidebarOpen.value = true
    return
  }
  // Swipe gauche sur la sidebar ou n'importe où → fermer
  if (dx < -55 && sidebarOpen.value) {
    sidebarOpen.value = false
  }
}

// Notifications dropdown
const showNotifDropdown = ref(false)
const notifications = ref([])
const loadingNotifs = ref(false)

async function toggleNotifDropdown() {
  showNotifDropdown.value = !showNotifDropdown.value
  if (showNotifDropdown.value) {
    await loadNotifications()
  }
}

async function loadNotifications() {
  loadingNotifs.value = true
  try {
    const { data } = await axios.get('/api/notifications')
    notifications.value = data
  } catch (e) {
    // silently fail
  } finally {
    loadingNotifs.value = false
  }
}

async function markAllRead() {
  await axios.post('/api/notifications/read-all')
  notifications.value = notifications.value.map(n => ({ ...n, read_at: new Date().toISOString() }))
  localUnreadCount.value = 0
  polledPrev = 0
}

function closeDropdown() {
  showNotifDropdown.value = false
}

// Jouer un son quand le compteur de notifications non lues augmente
let prevUnread = unreadCount.value
watch(unreadCount, (newVal) => {
  if (newVal > prevUnread && isAdmin.value) {
    playSound('notification_general')
  }
  prevUnread = newVal
})

function formatNotifTime(date) {
  if (!date) return ''
  const d = new Date(date)
  const now = new Date()
  const diffMs = now - d
  const diffMins = Math.floor(diffMs / 60000)
  if (diffMins < 1) return 'À l\'instant'
  if (diffMins < 60) return `Il y a ${diffMins} min`
  const diffHours = Math.floor(diffMins / 60)
  if (diffHours < 24) return `Il y a ${diffHours}h`
  return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })
}

// Detect current tab query param from URL
const currentTabParam = computed(() => {
  try {
    const url = new URL(page.props.ziggy?.location ?? window.location.href)
    return url.searchParams.get('tab') ?? ''
  } catch { return '' }
})

const isSupervisor = computed(() => page.props.isSupervisor ?? false)
const todoEnabled  = computed(() => page.props.todoEnabled ?? false)

const navItems = computed(() => {
  const onA = route().current('assets.*')
  const tab = currentTabParam.value

  const items = [
    { name: 'Tableau de bord', href: route('dashboard'),    icon: HomeIcon,  current: route().current('dashboard') },
    { name: 'Équipements',     href: route('assets.index'), icon: CubeIcon,  current: onA && (!tab || tab === 'list') },
  ]

  if (isAdmin.value) {
    items.push(
      { name: 'Catégories', href: route('assets.index', { tab: 'categories' }), icon: TagIcon,    current: onA && tab === 'categories', sub: true },
      { name: 'Lieux',      href: route('assets.index', { tab: 'locations' }),  icon: MapPinIcon, current: onA && tab === 'locations',  sub: true },
    )
  }

  items.push({ name: 'Fabricants', href: route('manufacturers.index'), icon: BuildingStorefrontIcon, current: route().current('manufacturers.*') })
  items.push({ name: 'Calendrier', href: route('calendar.index'),      icon: CalendarDaysIcon,       current: route().current('calendar.*') })
  items.push({ name: 'Salles',     href: route('rooms.index'),         icon: BuildingOfficeIcon,     current: route().current('rooms.*') || route().current('room-reservations.*') })
  items.push({ name: 'Kits',       href: route('kits.index'),          icon: ArchiveBoxIcon,         current: route().current('kits.*') })

  // To-Do List (admin toujours, autres utilisateurs si activé)
  if (todoEnabled.value) {
    items.push({ name: 'To-Do List', href: route('todo.index'), icon: CheckCircleIcon, current: route().current('todo.*') })
  }

  // Commandes (admin toujours, autres si accès activé)
  const procRole = user.value?.is_professeur ? 'professeur'
                 : (user.value?.roles?.some(r => r.name === 'personnel') ? 'personnel' : 'etudiant')
  const procReadKey = `section_procurement_${procRole}_read`
  if (isAdmin.value || (page.props.tabSettings && page.props.tabSettings[procReadKey] === 'true')) {
    items.push({ name: 'Commandes', href: route('procurement.index'), icon: ShoppingBagIcon, current: route().current('procurement.*') })
  }

  if (!isAdmin.value) {
    items.push({ name: 'Mes réservations', href: route('reservations.index'), icon: ClipboardDocumentListIcon, current: route().current('reservations.*') })
    items.push({ name: 'Consommables',     href: route('consommables.index'), icon: InboxStackIcon,            current: route().current('consommables.*') })
    if (isSupervisor.value || isProfesseur.value) {
      items.push({ name: 'Mon groupe', href: route('supervisor.group'), icon: UserGroupIcon, current: route().current('supervisor.*') })
    }
  }

  if (isAdmin.value) {
    // ── Réservations ──────────────────────────────────────────
    items.push(
      { type: 'sep', label: 'Réservations' },
      { name: 'Réservations', href: route('reservations.index'), icon: CalendarDaysIcon,        current: route().current('reservations.*') },
      { name: 'Événements',   href: route('events.index'),       icon: SparklesIcon,             current: route().current('events.*') },
      { name: 'Attribution',       href: route('attribution.index'),   icon: ArrowLeftOnRectangleIcon, current: route().current('attribution.*') },
      { name: 'Affectation lieux', href: route('asset-places.index'), icon: MapPinIcon,               current: route().current('asset-places.*') },
      { name: 'Retours',           href: route('returns.index'),      icon: ArrowUturnLeftIcon,       current: route().current('returns.*') },
      { name: 'Scanner',      href: route('scanner.index'),      icon: QrCodeIcon,               current: route().current('scanner.*') },
    )
    // ── Inventaire ────────────────────────────────────────────
    items.push(
      { type: 'sep', label: 'Inventaire' },
      { name: 'Faire inventaire', href: route('inventory.index'),  icon: ClipboardDocumentCheckIcon, current: route().current('inventory.*') },
      { name: 'Étiquettes',   href: route('assets.labels'),           icon: QueueListIcon,           current: route().current('assets.labels') },
      { name: 'Consommables', href: route('consommables.index'),      icon: InboxStackIcon,         current: route().current('consommables.*') },
      { name: 'Modèles',      href: route('models.index'),            icon: CubeTransparentIcon,    current: route().current('models.*') },
      { name: 'Lieux',        href: route('places.index'),            icon: MapPinIcon,              current: route().current('places.*') },
      { name: 'Maintenance',   href: route('maintenance.index'),       icon: WrenchScrewdriverIcon,  current: route().current('maintenance.*') },
      { name: 'Decheterie',   href: route('recycling.index'),         icon: ArchiveBoxXMarkIcon,    current: route().current('recycling.*') },
      { name: 'Sauvegardes',  href: route('backups.index'),           icon: ShieldCheckIcon,         current: route().current('backups.*') },
      { name: 'Licences',     href: route('licenses.index'),          icon: KeyIcon,                current: route().current('licenses.*') },
      { name: 'Postes',       href: route('computers.index'),         icon: ComputerDesktopIcon,    current: route().current('computers.*') },
      { name: 'Fournisseurs', href: route('suppliers.index'),         icon: BuildingStorefrontIcon, current: route().current('suppliers.*') },
    )
    // ── Rapports ──────────────────────────────────────────────
    items.push(
      { type: 'sep', label: 'Rapports' },
      { name: 'Rapports',    href: route('reports.index'),        icon: ChartBarIcon,              current: route().current('reports.*') },
      { name: 'Import',      href: route('import.index'),         icon: ArrowUpTrayIcon,           current: route().current('import.*') },
      { name: 'Formulaires', href: route('checkout-forms.index'), icon: ClipboardDocumentListIcon, current: route().current('checkout-forms.*') },
    )
    // ── Administration ────────────────────────────────────────
    items.push(
      { type: 'sep', label: 'Administration' },
      { name: 'Utilisateurs', href: route('admin.users'),         icon: UsersIcon,      current: route().current('admin.users') },
      { name: 'Groupes',      href: route('admin.groups'),        icon: UserGroupIcon,  current: route().current('admin.groups') },
      { name: 'Rôles',        href: route('admin.roles'),         icon: ShieldCheckIcon,current: route().current('admin.roles') },
      { name: 'Statuts',      href: route('asset-statuses.index'), icon: TagIcon,       current: route().current('asset-statuses.*') },
      { name: 'Paramètres',   href: route('settings.index'),      icon: Cog6ToothIcon,  current: route().current('settings.*') },
    )
  }
  return items
})

const logout = () => router.post(route('logout'))

// ── Barre de recherche rapide admin (scan code-barres) ──────────────────────
const quickSearchInput   = ref('')
const quickSearchError   = ref(null)
const quickSearchLoading = ref(false)

async function quickSearch() {
  const q = quickSearchInput.value.trim()
  if (!q) return
  quickSearchLoading.value = true
  quickSearchError.value   = null
  try {
    const { data } = await axios.post(route('scanner.lookup'), { code: q })
    quickSearchInput.value = ''
    router.visit(route('assets.show', data.asset.id))
  } catch {
    quickSearchError.value = 'Introuvable'
    setTimeout(() => { quickSearchError.value = null }, 3000)
  } finally {
    quickSearchLoading.value = false
  }
}

// Click-outside handler for notification dropdown
const notifButtonRef = ref(null)
function handleOutsideClick(e) {
  if (showNotifDropdown.value && notifButtonRef.value && !notifButtonRef.value.contains(e.target)) {
    showNotifDropdown.value = false
  }
}

// ── Polling toutes les 15s pour détecter les nouvelles notifications ──────────
let pollTimer     = null
let polledPrev    = unreadCount.value

async function pollUnread() {
  try {
    const { data } = await axios.get('/api/notifications')
    const unread = data.filter(n => !n.read_at).length
    if (unread > polledPrev && isAdmin.value) {
      playSound('notification_general')
    }
    polledPrev = unread
    localUnreadCount.value = unread          // ← met à jour le badge en temps réel
    if (showNotifDropdown.value) notifications.value = data
  } catch {}
}

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)
  pollTimer = setInterval(pollUnread, 15000)
})
onUnmounted(() => {
  document.removeEventListener('click', handleOutsideClick)
  if (pollTimer) clearInterval(pollTimer)
})
</script>

<template>
  <div class="bg-gray-50">
    <!-- Overlay mobile -->
    <div v-if="sidebarOpen" @click="sidebarOpen=false" class="fixed inset-0 z-20 bg-black/50 lg:hidden" />

    <!-- Sidebar : toujours fixed, glisse en/hors écran sur mobile -->
    <aside :class="[
      'fixed inset-y-0 left-0 z-30 w-72 bg-white shadow-lg flex flex-col',
      'transition-transform duration-300 ease-in-out',
      'lg:translate-x-0',
      sidebarOpen ? 'translate-x-0' : '-translate-x-full'
    ]">
      <div class="flex items-center justify-between px-4 pt-4 pb-3 border-b flex-shrink-0">
        <Link :href="route('dashboard')" class="flex flex-col gap-1.5 flex-1 min-w-0">
          <!-- Rangée logos -->
          <div class="flex items-center gap-2">
            <template v-if="appLogos?.lycee || appLogos?.bts">
              <img v-if="appLogos.lycee" :src="appLogos.lycee"
                   class="h-8 w-auto object-contain flex-shrink-0" alt="Logo lycée" />
              <span v-if="appLogos.lycee && appLogos.bts"
                    class="h-6 w-px bg-gray-300 flex-shrink-0"></span>
              <img v-if="appLogos.bts" :src="appLogos.bts"
                   class="h-8 w-auto object-contain flex-shrink-0" alt="Logo BTS" />
            </template>
            <!-- Fallback icône générique -->
            <div v-else
                 class="w-8 h-8 bg-gradient-to-br from-blue-600 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
              M
            </div>
          </div>
          <!-- Nom de l'établissement — affiché en entier, sans troncature -->
          <span class="font-bold text-gray-900 text-sm leading-tight">
            {{ appLogos?.school_name ?? 'App Magasin' }}
          </span>
        </Link>
        <button @click="sidebarOpen=false" class="lg:hidden text-gray-500 hover:text-gray-700 flex-shrink-0 ml-2">
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>

      <div class="px-4 py-2 border-b flex-shrink-0">
        <span :class="['text-xs font-semibold px-2 py-1 rounded-full',
          isAdmin      ? 'bg-purple-100 text-purple-700' :
          isProfesseur ? 'bg-indigo-100 text-indigo-700' :
          user?.role_label === 'Personnel' ? 'bg-teal-100 text-teal-700' :
          'bg-blue-100 text-blue-700']">
          {{ user?.role_label ?? (isAdmin ? 'Administrateur' : isProfesseur ? 'Professeur' : 'Étudiant') }}
        </span>
      </div>

      <nav class="flex-1 overflow-y-auto p-3">
        <template v-for="item in navItems" :key="item.type === 'sep' ? 'sep-' + item.label : item.name">
          <!-- Titre de section -->
          <div v-if="item.type === 'sep'"
               class="px-3 pt-5 pb-1 first:pt-1">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">
              {{ item.label }}
            </span>
          </div>
          <!-- Lien de navigation -->
          <Link v-else :href="item.href"
                :class="['flex items-center gap-3 rounded-lg text-sm font-medium transition-colors mt-0.5',
                  item.sub ? 'px-3 py-1.5 pl-8' : 'px-3 py-2',
                  item.current
                    ? 'bg-blue-50 text-blue-700 font-semibold'
                    : 'text-gray-500 hover:bg-gray-100 hover:text-gray-900']">
            <component :is="item.icon" :class="['flex-shrink-0', item.sub ? 'w-4 h-4' : 'w-5 h-5']" />
            {{ item.name }}
          </Link>
        </template>

        <!-- Panier (utilisateurs uniquement) -->
        <Link v-if="!isAdmin" :href="route('cart.index')"
              :class="['flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors',
                route().current('cart.*') ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900']">
          <ShoppingCartIcon class="w-5 h-5 flex-shrink-0" />
          Mon panier
          <span v-if="cart.count > 0"
                class="ml-auto bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-5 text-center leading-none">
            {{ cart.count }}
          </span>
        </Link>
      </nav>

      <div class="border-t p-3 flex-shrink-0">
        <Link :href="route('profile.edit')" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors">
          <div class="w-8 h-8 rounded-full overflow-hidden bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
            <img v-if="user?.avatar_url" :src="user.avatar_url" class="w-full h-full object-cover" />
            <span v-else>{{ user?.name?.charAt(0)?.toUpperCase() }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ user?.name }}</p>
            <p class="text-xs text-gray-500 truncate">{{ user?.department ?? user?.email }}</p>
          </div>
        </Link>
        <button @click="logout" class="mt-1 w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-red-600 hover:bg-red-50 transition-colors">
          <ArrowRightOnRectangleIcon class="w-5 h-5" />
          Déconnexion
        </button>
      </div>
    </aside>

    <!-- Contenu principal : décalé à droite de la sidebar sur desktop -->
    <!-- Les listeners touch permettent le swipe pour ouvrir/fermer la sidebar sur mobile -->
    <div class="min-h-screen lg:pl-72 flex flex-col"
         @touchstart.passive="onTouchStart"
         @touchend.passive="onTouchEnd">
      <!-- Top bar -->
      <header class="sticky top-0 z-10 bg-white border-b px-4 py-3 flex items-center gap-3">
        <button @click="sidebarOpen=true" class="text-gray-500 hover:text-gray-700 lg:hidden">
          <Bars3Icon class="w-6 h-6" />
        </button>
        <!-- Logos dans le header mobile -->
        <div class="flex items-center gap-2 lg:hidden">
          <img v-if="appLogos?.lycee" :src="appLogos.lycee"
               class="h-7 w-auto object-contain" alt="Logo lycée" />
          <img v-if="appLogos?.bts" :src="appLogos.bts"
               class="h-7 w-auto object-contain" alt="Logo BTS" />
          <span v-if="!appLogos?.lycee && !appLogos?.bts"
                class="font-bold text-gray-900 text-sm">
            {{ appLogos?.school_name ?? 'App Magasin' }}
          </span>
        </div>
        <div class="flex-1" />

        <!-- Barre de recherche rapide admin (scan / tag) -->
        <div v-if="isAdmin" class="relative hidden sm:flex items-center mr-1">
          <div class="relative">
            <MagnifyingGlassIcon class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
            <input
              v-model="quickSearchInput"
              type="text"
              placeholder="Tag ou code-barres…"
              :disabled="quickSearchLoading"
              @keydown.enter="quickSearch"
              class="w-44 pl-8 pr-3 py-1.5 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none disabled:opacity-60 bg-gray-50 focus:bg-white transition"
            />
            <span v-if="quickSearchError"
              class="absolute top-full left-0 mt-1 w-full text-center bg-red-600 text-white text-xs px-2 py-1 rounded shadow z-10">
              {{ quickSearchError }}
            </span>
          </div>
        </div>

        <!-- Notification bell — visible to ALL users -->
        <div class="relative" ref="notifButtonRef">
          <button
            @click="toggleNotifDropdown"
            class="relative flex items-center gap-1.5 text-sm text-gray-600 hover:text-blue-600 transition px-3 py-1.5 rounded-lg hover:bg-blue-50">
            <BellSolid v-if="localUnreadCount > 0" class="w-5 h-5 text-blue-600" />
            <BellIcon v-else class="w-5 h-5" />
            <span v-if="localUnreadCount > 0"
                  class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center leading-none">
              {{ localUnreadCount > 99 ? '99+' : localUnreadCount }}
            </span>
          </button>

          <!-- Dropdown -->
          <Transition name="dropdown">
            <div v-if="showNotifDropdown"
                 class="absolute right-0 top-full mt-2 w-96 bg-white rounded-xl shadow-2xl border border-gray-200 z-50 overflow-hidden">

              <!-- Header -->
              <div class="flex items-center justify-between px-4 py-3 border-b bg-gray-50">
                <h3 class="font-semibold text-gray-900 text-sm">Notifications</h3>
                <button v-if="notifications.some(n => !n.read_at)"
                        @click="markAllRead"
                        class="text-xs text-blue-600 hover:underline">
                  Tout marquer comme lu
                </button>
              </div>

              <!-- Loading -->
              <div v-if="loadingNotifs" class="px-4 py-8 text-center text-gray-400 text-sm">
                Chargement…
              </div>

              <!-- Empty -->
              <div v-else-if="notifications.length === 0"
                   class="px-4 py-8 text-center text-gray-400 text-sm">
                <BellIcon class="w-8 h-8 mx-auto mb-2 opacity-30" />
                Aucune notification
              </div>

              <!-- Notifications list -->
              <div v-else class="max-h-80 overflow-y-auto divide-y divide-gray-100">
                <div v-for="notif in notifications" :key="notif.id"
                     class="px-4 py-3 hover:bg-gray-50 transition"
                     :class="!notif.read_at ? 'bg-blue-50/40' : ''">
                  <div class="flex items-start gap-2">
                    <div :class="['w-2 h-2 rounded-full mt-1.5 flex-shrink-0', !notif.read_at ? 'bg-blue-500' : 'bg-transparent']" />
                    <div class="flex-1 min-w-0">
                      <p class="text-sm text-gray-800 leading-snug">{{ notif.message }}</p>
                      <div class="flex items-center gap-2 mt-1">
                        <span class="text-xs text-gray-400">{{ formatNotifTime(notif.created_at) }}</span>
                        <Link v-if="notif.reservation_id"
                              :href="route('reservations.show', notif.reservation_id)"
                              @click="closeDropdown"
                              class="text-xs text-blue-600 hover:underline">
                          Voir la réservation
                        </Link>
                        <Link v-if="notif.consommable_request_id"
                              :href="route('consommables.index', { tab: 'requests' })"
                              @click="closeDropdown"
                              class="text-xs text-blue-600 hover:underline">
                          Voir la demande
                        </Link>
                        <Link v-if="notif.type === 'consommable_low_stock'"
                              :href="route('consommables.index')"
                              @click="closeDropdown"
                              class="text-xs text-orange-600 hover:underline">
                          Voir le stock
                        </Link>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </Transition>
        </div>

        <!-- Icône panier dans le header (utilisateurs) -->
        <Link v-if="!isAdmin" :href="route('cart.index')"
              class="relative flex items-center gap-1.5 text-sm text-gray-600 hover:text-blue-600 transition px-3 py-1.5 rounded-lg hover:bg-blue-50">
          <ShoppingCartIcon class="w-5 h-5" />
          <span class="hidden sm:inline font-medium">Panier</span>
          <span v-if="cart.count > 0"
                class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center leading-none">
            {{ cart.count }}
          </span>
        </Link>
      </header>

      <!-- Flash messages -->
      <div v-if="flash?.success || flash?.error || flash?.warning" class="px-6 pt-4">
        <div v-if="flash.success" class="flex items-center gap-2 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
          <span>&#10003;</span> {{ flash.success }}
        </div>
        <div v-if="flash.error" class="flex items-center gap-2 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">
          <span>&#10005;</span> {{ flash.error }}
        </div>
        <div v-if="flash.warning" class="flex items-center gap-2 bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg text-sm">
          <span>&#9888;</span> {{ flash.warning }}
        </div>
      </div>

      <main class="flex-1 p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
.dropdown-enter-active, .dropdown-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.dropdown-enter-from, .dropdown-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
