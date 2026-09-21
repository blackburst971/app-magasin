<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
  CheckCircleIcon, XCircleIcon, ClockIcon,
  ArrowPathIcon, ComputerDesktopIcon, WifiIcon,
  ArchiveBoxIcon, ArchiveBoxArrowDownIcon, TrashIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  requests: Array,
  view: {
    type: String,
    default: 'active'
  }
})

const processing = ref(null)
const currentView = ref(props.view)

const isActiveView = computed(() => currentView.value === 'active')
const isHistoryView = computed(() => currentView.value === 'history')

function approveRequest(request) {
  if (!confirm('Êtes-vous sûr de vouloir approuver cette demande ?')) return

  processing.value = request.id

  const routeName = request.type === 'computer'
    ? 'computers.approve-ip-change'
    : 'ip-devices.approve-ip-change'

  router.post(route(routeName, request.id), {}, {
    preserveScroll: true,
    onFinish: () => {
      processing.value = null
    }
  })
}

function rejectRequest(request) {
  if (!confirm('Êtes-vous sûr de vouloir rejeter cette demande ?')) return

  processing.value = request.id

  const routeName = request.type === 'computer'
    ? 'computers.reject-ip-change'
    : 'ip-devices.reject-ip-change'

  router.post(route(routeName, request.id), {}, {
    preserveScroll: true,
    onFinish: () => {
      processing.value = null
    }
  })
}

function archiveRequest(request) {
  if (!confirm('Archiver cette demande ?')) return

  processing.value = request.id
  router.post(route('computers.archive-ip-change', { type: request.type, id: request.id }), {}, {
    preserveScroll: true,
    onFinish: () => {
      processing.value = null
    }
  })
}

function unarchiveRequest(request) {
  if (!confirm('Désarchiver cette demande ?')) return

  processing.value = request.id
  router.post(route('computers.unarchive-ip-change', { type: request.type, id: request.id }), {}, {
    preserveScroll: true,
    onFinish: () => {
      processing.value = null
    }
  })
}

function deleteRequest(request) {
  if (!confirm('Supprimer définitivement cette demande ? Cette action est irréversible.')) return

  processing.value = request.id
  router.delete(route('computers.delete-ip-change', { type: request.type, id: request.id }), {
    preserveScroll: true,
    onFinish: () => {
      processing.value = null
    }
  })
}

const statusBadge = (status) => ({
  pending:  'bg-yellow-100 text-yellow-700',
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
})[status] || 'bg-gray-100 text-gray-700'

const statusLabel = (status) => ({
  pending:  'En attente',
  approved: 'Approuvée',
  rejected: 'Rejetée',
})[status] || status

// Fonction simplifiée pour affichage rapide (si nécessaire)
const formatIpListSimple = (ips) => {
  if (!ips || ips.length === 0) return '—'
  return ips.map(ip => `${ip.ip}${ip.description ? ' (' + ip.description + ')' : ''}`).join(', ')
}

const getDeviceName = (req) => {
  return req.type === 'computer' ? req.computer_name : req.ip_device_name
}

const getDeviceType = (req) => {
  return req.type === 'computer' ? 'Ordinateur' : 'Périphérique IP'
}
</script>

<template>
  <AppLayout title="Demandes de changement d'IP">
    <div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        <!-- En-tête -->
        <div class="mb-8">
          <div class="flex items-center gap-3 mb-2">
            <ComputerDesktopIcon class="w-8 h-8 text-indigo-600" />
            <h1 class="text-3xl font-bold text-gray-900">Demandes de changement d'adresse IP</h1>
          </div>
          <p class="text-gray-600">Gérez les demandes de modification d'adresses IP des utilisateurs</p>
        </div>

        <!-- Onglets -->
        <div class="border-b border-gray-200 mb-6">
          <nav class="-mb-px flex space-x-8">
            <Link
              :href="route('computers.ip-change-requests')"
              :class="[
                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm',
                isActiveView
                  ? 'border-indigo-500 text-indigo-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
            >
              Demandes actives
            </Link>
            <Link
              :href="route('computers.ip-change-requests-history')"
              :class="[
                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm',
                isHistoryView
                  ? 'border-indigo-500 text-indigo-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
            >
              Historique
            </Link>
          </nav>
        </div>

        <!-- Statistiques -->
        <div v-if="isActiveView" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
            <div class="flex items-center gap-3">
              <div class="p-3 bg-yellow-100 rounded-lg">
                <ClockIcon class="w-6 h-6 text-yellow-600" />
              </div>
              <div>
                <p class="text-sm text-gray-500">En attente</p>
                <p class="text-2xl font-bold text-gray-900">{{ requests.filter(r => r.status === 'pending').length }}</p>
              </div>
            </div>
          </div>
          <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
            <div class="flex items-center gap-3">
              <div class="p-3 bg-green-100 rounded-lg">
                <CheckCircleIcon class="w-6 h-6 text-green-600" />
              </div>
              <div>
                <p class="text-sm text-gray-500">Approuvées</p>
                <p class="text-2xl font-bold text-gray-900">{{ requests.filter(r => r.status === 'approved').length }}</p>
              </div>
            </div>
          </div>
          <div class="bg-white rounded-xl shadow-sm p-5 border border-gray-200">
            <div class="flex items-center gap-3">
              <div class="p-3 bg-red-100 rounded-lg">
                <XCircleIcon class="w-6 h-6 text-red-600" />
              </div>
              <div>
                <p class="text-sm text-gray-500">Rejetées</p>
                <p class="text-2xl font-bold text-gray-900">{{ requests.filter(r => r.status === 'rejected').length }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Liste des demandes -->
        <div v-if="requests.length === 0" class="bg-white rounded-xl shadow-sm p-12 text-center">
          <ComputerDesktopIcon class="w-16 h-16 text-gray-300 mx-auto mb-4" />
          <p class="text-gray-500 text-lg">
            {{ isActiveView ? 'Aucune demande de changement d\'IP active' : 'Aucune demande archivée' }}
          </p>
        </div>

        <div v-else class="space-y-4">
          <div v-for="req in requests" :key="`${req.type}-${req.id}`"
            class="bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition overflow-hidden">
            <div class="p-6">
              <div class="flex items-start justify-between mb-4">
                <div>
                  <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <component :is="req.type === 'computer' ? ComputerDesktopIcon : WifiIcon" class="w-5 h-5 text-indigo-600" />
                    {{ getDeviceName(req) }}
                    <span class="text-xs font-normal text-gray-500">({{ getDeviceType(req) }})</span>
                    <span :class="['text-xs font-medium px-2.5 py-1 rounded-full', statusBadge(req.status)]">
                      {{ statusLabel(req.status) }}
                    </span>
                  </h3>
                  <p class="text-sm text-gray-500 mt-1">
                    Demandé par <strong>{{ req.requested_by_name }}</strong> le {{ req.created_at }}
                  </p>
                </div>

                <!-- Boutons d'action -->
                <div class="flex gap-2">
                  <!-- Boutons pour demandes actives -->
                  <template v-if="isActiveView">
                    <button v-if="req.status === 'pending'" @click="approveRequest(req)"
                      :disabled="processing === req.id"
                      class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition disabled:opacity-50">
                      <CheckCircleIcon class="w-4 h-4" />
                      <span v-if="processing === req.id">
                        <ArrowPathIcon class="w-4 h-4 animate-spin" />
                      </span>
                      <span v-else>Approuver</span>
                    </button>
                    <button v-if="req.status === 'pending'" @click="rejectRequest(req)"
                      :disabled="processing === req.id"
                      class="flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition disabled:opacity-50">
                      <XCircleIcon class="w-4 h-4" />
                      Rejeter
                    </button>
                    <button v-if="req.status !== 'pending'" @click="archiveRequest(req)"
                      :disabled="processing === req.id"
                      class="flex items-center gap-2 px-3 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition disabled:opacity-50">
                      <ArchiveBoxIcon class="w-4 h-4" />
                      Archiver
                    </button>
                  </template>

                  <!-- Boutons pour historique -->
                  <template v-if="isHistoryView">
                    <button @click="unarchiveRequest(req)"
                      :disabled="processing === req.id"
                      class="flex items-center gap-2 px-3 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition disabled:opacity-50">
                      <ArchiveBoxArrowDownIcon class="w-4 h-4" />
                      Désarchiver
                    </button>
                    <button @click="deleteRequest(req)"
                      :disabled="processing === req.id"
                      class="flex items-center gap-2 px-3 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition disabled:opacity-50">
                      <TrashIcon class="w-4 h-4" />
                      Supprimer
                    </button>
                  </template>
                </div>
              </div>

              <!-- Comparaison détaillée des configurations réseau (Computers) -->
              <div v-if="req.type === 'computer'" class="space-y-4">
                <!-- Affichage détaillé de chaque configuration réseau -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <!-- Configurations actuelles -->
                  <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-3">Configurations actuelles</p>
                    <div class="space-y-3">
                      <div v-if="!req.old_ip_addresses || req.old_ip_addresses.length === 0"
                        class="bg-white rounded-lg p-4 border border-gray-200">
                        <p class="text-sm text-gray-500 italic">Aucune configuration</p>
                      </div>
                      <div v-else v-for="(config, i) in req.old_ip_addresses" :key="`old-${i}`"
                        class="bg-white rounded-lg p-4 border border-gray-200 space-y-2">
                        <div class="flex items-center gap-2 mb-2 pb-2 border-b border-gray-100">
                          <WifiIcon class="w-4 h-4 text-gray-500" />
                          <span class="text-xs font-semibold text-gray-700">Configuration #{{ i + 1 }}</span>
                          <span v-if="config.description" class="text-xs text-gray-500">({{ config.description }})</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                          <div>
                            <p class="text-gray-500 mb-0.5">Adresse IP</p>
                            <p class="text-gray-900 font-mono font-medium">{{ config.ip || '—' }}</p>
                          </div>
                          <div>
                            <p class="text-gray-500 mb-0.5">Adresse MAC</p>
                            <p class="text-gray-900 font-mono font-medium">{{ config.mac_address || '—' }}</p>
                          </div>
                          <div>
                            <p class="text-gray-500 mb-0.5">Masque de sous-réseau</p>
                            <p class="text-gray-900 font-mono font-medium">{{ config.subnet_mask || '—' }}</p>
                          </div>
                          <div>
                            <p class="text-gray-500 mb-0.5">Passerelle</p>
                            <p class="text-gray-900 font-mono font-medium">{{ config.gateway || '—' }}</p>
                          </div>
                          <div>
                            <p class="text-gray-500 mb-0.5">DNS Primaire</p>
                            <p class="text-gray-900 font-mono font-medium">{{ config.dns_primary || '—' }}</p>
                          </div>
                          <div>
                            <p class="text-gray-500 mb-0.5">DNS Secondaire</p>
                            <p class="text-gray-900 font-mono font-medium">{{ config.dns_secondary || '—' }}</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Nouvelles configurations demandées -->
                  <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-3">Nouvelles configurations demandées</p>
                    <div class="space-y-3">
                      <div v-if="!req.new_ip_addresses || req.new_ip_addresses.length === 0"
                        class="bg-green-50 rounded-lg p-4 border border-green-200">
                        <p class="text-sm text-green-700 italic">Aucune configuration</p>
                      </div>
                      <div v-else v-for="(config, i) in req.new_ip_addresses" :key="`new-${i}`"
                        class="bg-green-50 rounded-lg p-4 border border-green-200 space-y-2">
                        <div class="flex items-center gap-2 mb-2 pb-2 border-b border-green-100">
                          <WifiIcon class="w-4 h-4 text-green-600" />
                          <span class="text-xs font-semibold text-green-700">Configuration #{{ i + 1 }}</span>
                          <span v-if="config.description" class="text-xs text-green-600">({{ config.description }})</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                          <div>
                            <p class="text-green-600 mb-0.5">Adresse IP</p>
                            <p class="text-green-900 font-mono font-semibold">{{ config.ip || '—' }}</p>
                          </div>
                          <div>
                            <p class="text-green-600 mb-0.5">Adresse MAC</p>
                            <p class="text-green-900 font-mono font-semibold">{{ config.mac_address || '—' }}</p>
                          </div>
                          <div>
                            <p class="text-green-600 mb-0.5">Masque de sous-réseau</p>
                            <p class="text-green-900 font-mono font-semibold">{{ config.subnet_mask || '—' }}</p>
                          </div>
                          <div>
                            <p class="text-green-600 mb-0.5">Passerelle</p>
                            <p class="text-green-900 font-mono font-semibold">{{ config.gateway || '—' }}</p>
                          </div>
                          <div>
                            <p class="text-green-600 mb-0.5">DNS Primaire</p>
                            <p class="text-green-900 font-mono font-semibold">{{ config.dns_primary || '—' }}</p>
                          </div>
                          <div>
                            <p class="text-green-600 mb-0.5">DNS Secondaire</p>
                            <p class="text-green-900 font-mono font-semibold">{{ config.dns_secondary || '—' }}</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Comparaison des paramètres réseau (IP Devices) -->
              <div v-if="req.type === 'ip_device'" class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 rounded-lg p-4">
                <div>
                  <p class="text-xs font-semibold text-gray-500 uppercase mb-3">Configuration actuelle</p>
                  <div class="space-y-2">
                    <div class="bg-white rounded-md p-3 border border-gray-200">
                      <p class="text-xs text-gray-500 mb-1">Adresse IP</p>
                      <p class="text-sm text-gray-700 font-mono">{{ req.old_ip_address || '—' }}</p>
                    </div>
                    <div class="bg-white rounded-md p-3 border border-gray-200">
                      <p class="text-xs text-gray-500 mb-1">Masque de sous-réseau</p>
                      <p class="text-sm text-gray-700 font-mono">{{ req.old_subnet_mask || '—' }}</p>
                    </div>
                    <div class="bg-white rounded-md p-3 border border-gray-200">
                      <p class="text-xs text-gray-500 mb-1">Passerelle</p>
                      <p class="text-sm text-gray-700 font-mono">{{ req.old_gateway || '—' }}</p>
                    </div>
                    <div class="bg-white rounded-md p-3 border border-gray-200">
                      <p class="text-xs text-gray-500 mb-1">DNS Primaire</p>
                      <p class="text-sm text-gray-700 font-mono">{{ req.old_dns_primary || '—' }}</p>
                    </div>
                    <div class="bg-white rounded-md p-3 border border-gray-200">
                      <p class="text-xs text-gray-500 mb-1">DNS Secondaire</p>
                      <p class="text-sm text-gray-700 font-mono">{{ req.old_dns_secondary || '—' }}</p>
                    </div>
                  </div>
                </div>
                <div>
                  <p class="text-xs font-semibold text-gray-500 uppercase mb-3">Nouvelle configuration demandée</p>
                  <div class="space-y-2">
                    <div class="bg-green-50 rounded-md p-3 border border-green-200">
                      <p class="text-xs text-green-600 mb-1">Adresse IP</p>
                      <p class="text-sm text-green-700 font-mono font-semibold">{{ req.new_ip_address || '—' }}</p>
                    </div>
                    <div class="bg-green-50 rounded-md p-3 border border-green-200">
                      <p class="text-xs text-green-600 mb-1">Masque de sous-réseau</p>
                      <p class="text-sm text-green-700 font-mono font-semibold">{{ req.new_subnet_mask || '—' }}</p>
                    </div>
                    <div class="bg-green-50 rounded-md p-3 border border-green-200">
                      <p class="text-xs text-green-600 mb-1">Passerelle</p>
                      <p class="text-sm text-green-700 font-mono font-semibold">{{ req.new_gateway || '—' }}</p>
                    </div>
                    <div class="bg-green-50 rounded-md p-3 border border-green-200">
                      <p class="text-xs text-green-600 mb-1">DNS Primaire</p>
                      <p class="text-sm text-green-700 font-mono font-semibold">{{ req.new_dns_primary || '—' }}</p>
                    </div>
                    <div class="bg-green-50 rounded-md p-3 border border-green-200">
                      <p class="text-xs text-green-600 mb-1">DNS Secondaire</p>
                      <p class="text-sm text-green-700 font-mono font-semibold">{{ req.new_dns_secondary || '—' }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Raison -->
              <div v-if="req.reason" class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-xs font-semibold text-blue-700 mb-1">Raison de la demande</p>
                <p class="text-sm text-blue-900">{{ req.reason }}</p>
              </div>

              <!-- Notes (pour computers) -->
              <div v-if="req.notes" class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-xs font-semibold text-blue-700 mb-1">Notes de la demande</p>
                <p class="text-sm text-blue-900">{{ req.notes }}</p>
              </div>

              <!-- Info de révision -->
              <div v-if="req.status !== 'pending' && req.reviewed_by_name" class="mt-4 pt-4 border-t border-gray-200">
                <p class="text-xs text-gray-500">
                  {{ req.status === 'approved' ? 'Approuvée' : 'Rejetée' }} par
                  <strong>{{ req.reviewed_by_name }}</strong> le {{ req.reviewed_at }}
                </p>
                <p v-if="req.review_notes" class="text-sm text-gray-700 mt-2 italic">{{ req.review_notes }}</p>
              </div>

              <!-- Info d'archivage (historique uniquement) -->
              <div v-if="isHistoryView && req.archived_by_name" class="mt-4 pt-4 border-t border-gray-200">
                <p class="text-xs text-gray-500">
                  Archivée par <strong>{{ req.archived_by_name }}</strong> le {{ req.archived_at }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
