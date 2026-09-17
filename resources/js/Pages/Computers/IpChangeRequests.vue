<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
  CheckCircleIcon, XCircleIcon, ClockIcon,
  ArrowPathIcon, ComputerDesktopIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  requests: Array,
})

const processing = ref(null)

function approveRequest(requestId) {
  if (!confirm('Êtes-vous sûr de vouloir approuver cette demande ?')) return

  processing.value = requestId
  router.post(route('computers.approve-ip-change', requestId), {}, {
    preserveScroll: true,
    onFinish: () => {
      processing.value = null
    }
  })
}

function rejectRequest(requestId) {
  if (!confirm('Êtes-vous sûr de vouloir rejeter cette demande ?')) return

  processing.value = requestId
  router.post(route('computers.reject-ip-change', requestId), {}, {
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

const formatIpList = (ips) => {
  if (!ips || ips.length === 0) return '—'
  return ips.map(ip => `${ip.ip}${ip.description ? ' (' + ip.description + ')' : ''}`).join(', ')
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

        <!-- Statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
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
          <p class="text-gray-500 text-lg">Aucune demande de changement d'IP</p>
        </div>

        <div v-else class="space-y-4">
          <div v-for="req in requests" :key="req.id"
            class="bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition overflow-hidden">
            <div class="p-6">
              <div class="flex items-start justify-between mb-4">
                <div>
                  <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    {{ req.computer_name }}
                    <span :class="['text-xs font-medium px-2.5 py-1 rounded-full', statusBadge(req.status)]">
                      {{ statusLabel(req.status) }}
                    </span>
                  </h3>
                  <p class="text-sm text-gray-500 mt-1">
                    Demandé par <strong>{{ req.requested_by_name }}</strong> le {{ req.created_at }}
                  </p>
                </div>

                <div v-if="req.status === 'pending'" class="flex gap-2">
                  <button @click="approveRequest(req.id)"
                    :disabled="processing === req.id"
                    class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition disabled:opacity-50">
                    <CheckCircleIcon class="w-4 h-4" />
                    <span v-if="processing === req.id">
                      <ArrowPathIcon class="w-4 h-4 animate-spin" />
                    </span>
                    <span v-else>Approuver</span>
                  </button>
                  <button @click="rejectRequest(req.id)"
                    :disabled="processing === req.id"
                    class="flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition disabled:opacity-50">
                    <XCircleIcon class="w-4 h-4" />
                    Rejeter
                  </button>
                </div>
              </div>

              <!-- Comparaison des adresses IP -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 rounded-lg p-4">
                <div>
                  <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Adresses IP actuelles</p>
                  <div class="bg-white rounded-md p-3 border border-gray-200">
                    <p class="text-sm text-gray-700 font-mono">{{ formatIpList(req.old_ip_addresses) }}</p>
                  </div>
                </div>
                <div>
                  <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Nouvelles adresses IP demandées</p>
                  <div class="bg-green-50 rounded-md p-3 border border-green-200">
                    <p class="text-sm text-green-700 font-mono font-semibold">{{ formatIpList(req.new_ip_addresses) }}</p>
                  </div>
                </div>
              </div>

              <!-- Notes -->
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
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
