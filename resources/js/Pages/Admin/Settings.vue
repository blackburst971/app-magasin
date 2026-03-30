<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { useSound } from '@/composables/useSound'
import axios from 'axios'

const props = defineProps({
  settings: Object,
  logoUrls: { type: Object, default: () => ({ lycee: null, bts: null }) },
})

const { playSoundByName } = useSound()

const SOUND_OPTIONS = [
  { value: 'chime',   label: '🔔 Chime (ding montant)' },
  { value: 'ding',    label: '🔕 Ding (note unique)' },
  { value: 'beep',    label: '📡 Bip court' },
  { value: 'buzz',    label: '⚠️ Buzz (erreur grave)' },
  { value: 'success', label: '✅ Mélodie succès' },
  { value: 'error',   label: '❌ Mélodie erreur' },
  { value: 'none',    label: '🔇 Silencieux' },
]

const form = useForm({
  school_name:    props.settings?.general?.school_name    ?? '',
  school_address: props.settings?.general?.school_address ?? '',

  // Visibilité équipements
  filter_by_reservable: props.settings?.visibility?.filter_by_reservable ?? 'true',

  // Visibilité onglets
  tab_categories:    props.settings?.tabs?.tab_categories    ?? 'true',
  tab_locations:     props.settings?.tabs?.tab_locations     ?? 'true',
  tab_manufacturers: props.settings?.tabs?.tab_manufacturers ?? 'true',

  // Réservations
  max_reservation_days:       props.settings?.reservations?.max_reservation_days       ?? '30',
  max_assets_per_reservation: props.settings?.reservations?.max_assets_per_reservation ?? '10',
  auto_approve:               props.settings?.reservations?.auto_approve               ?? 'false',

  // Mode examen
  exam_mode:          props.settings?.exam?.exam_mode          ?? 'false',
  exam_advance_hours: props.settings?.exam?.exam_advance_hours ?? '24',
  exam_process_start: props.settings?.exam?.exam_process_start ?? '7',
  exam_process_end:   props.settings?.exam?.exam_process_end   ?? '9',

  // Rappels de retour
  return_reminder_enabled: props.settings?.reminders?.return_reminder_enabled ?? 'true',
  return_reminder_minutes: props.settings?.reminders?.return_reminder_minutes ?? '30',

  // Emails
  notify_admin_new_reservation: props.settings?.emails?.notify_admin_new_reservation ?? 'true',
  notify_user_approved:         props.settings?.emails?.notify_user_approved         ?? 'true',
  send_checkout_summary:        props.settings?.emails?.send_checkout_summary         ?? 'true',

  // Annulation automatique
  auto_cancel_enabled: props.settings?.reservations?.auto_cancel_enabled ?? 'false',
  auto_cancel_hours:   props.settings?.reservations?.auto_cancel_hours   ?? '24',

  // Visibilité calendrier par rôle
  calendar_visibility_professeur: props.settings?.calendar?.calendar_visibility_professeur ?? 'false',
  calendar_visibility_etudiant:   props.settings?.calendar?.calendar_visibility_etudiant   ?? 'false',
  calendar_visibility_personnel:  props.settings?.calendar?.calendar_visibility_personnel  ?? 'false',

  // Fiche imprimable
  print_show_thumbnails: props.settings?.print?.print_show_thumbnails ?? 'false',
  print_show_notes:      props.settings?.print?.print_show_notes      ?? 'true',
  print_show_category:   props.settings?.print?.print_show_category   ?? 'true',
  print_show_asset_tag:  props.settings?.print?.print_show_asset_tag  ?? 'true',
  print_show_form:       props.settings?.print?.print_show_form       ?? 'true',
  print_proviseur_label: props.settings?.print?.print_proviseur_label ?? 'Signature du proviseur',

  // Sons
  'sounds.enabled':              props.settings?.sounds?.['sounds.enabled']              ?? 'true',
  'sounds.volume':               props.settings?.sounds?.['sounds.volume']               ?? '80',
  'sounds.notification_general': props.settings?.sounds?.['sounds.notification_general'] ?? 'chime',
  'sounds.notification_kit':     props.settings?.sounds?.['sounds.notification_kit']     ?? 'ding',
  'sounds.scan_success':         props.settings?.sounds?.['sounds.scan_success']         ?? 'beep',
  'sounds.scan_error':           props.settings?.sounds?.['sounds.scan_error']           ?? 'buzz',
})

const submit = () => form.post(route('settings.update'))

// ── Logos ────────────────────────────────────────────────────────────────────
const lyceePreview = ref(props.logoUrls?.lycee ?? null)
const btsPreview   = ref(props.logoUrls?.bts   ?? null)
const lyceeForm    = useForm({ type: 'lycee', logo: null })
const btsForm      = useForm({ type: 'bts',   logo: null })

function handleLogoFile(type, event) {
  const file = event.target.files?.[0]
  if (!file) return
  // Prévisualisation locale immédiate
  const objectUrl = URL.createObjectURL(file)
  if (type === 'lycee') { lyceePreview.value = objectUrl; lyceeForm.logo = file; lyceeForm.post(route('settings.logo.upload')) }
  else                   { btsPreview.value   = objectUrl; btsForm.logo   = file; btsForm.post(route('settings.logo.upload'))  }
}

function deleteLogo(type) {
  router.delete(route('settings.logo.delete', type), {
    onSuccess: () => {
      if (type === 'lycee') lyceePreview.value = null
      else                  btsPreview.value   = null
    },
  })
}

function testSound(key) {
  const soundName = form[key]
  const volume = parseInt(form['sounds.volume']) / 100
  playSoundByName(soundName, volume)
}

// ── Test email ────────────────────────────────────────────────────────────────
const testEmailSending = ref(false)

function sendTestEmail() {
  testEmailSending.value = true
  router.post(route('settings.test-email'), {}, {
    onFinish: () => { testEmailSending.value = false },
  })
}
</script>

<template>
  <AppLayout>
    <div class="max-w-2xl">
      <h1 class="text-2xl font-bold text-gray-900 mb-6">⚙️ Paramètres</h1>

      <form @submit.prevent="submit" class="space-y-6">

        <!-- Général -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="font-semibold text-gray-900 mb-4">Général</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nom de l'établissement</label>
              <input v-model="form.school_name"
                     class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
              <input v-model="form.school_address"
                     class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
          </div>
        </div>

        <!-- Logos -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="font-semibold text-gray-900 mb-1">🖼️ Logos</h2>
          <p class="text-sm text-gray-500 mb-5">
            Ces logos apparaissent dans la barre latérale de l'application et dans l'en-tête de tous les emails envoyés.
          </p>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

            <!-- Logo Lycée -->
            <div class="border-2 border-dashed border-gray-200 rounded-xl p-4 flex flex-col items-center gap-3 hover:border-blue-300 transition"
                 :class="lyceePreview ? 'border-blue-200 bg-blue-50/30' : ''">
              <p class="text-sm font-semibold text-gray-700 self-start">Logo du lycée</p>

              <!-- Prévisualisation -->
              <div v-if="lyceePreview"
                   class="w-full flex justify-center bg-white rounded-lg border border-gray-200 p-3">
                <img :src="lyceePreview" class="max-h-24 max-w-full object-contain" alt="Logo lycée" />
              </div>
              <div v-else class="w-full flex justify-center items-center bg-gray-100 rounded-lg border border-dashed border-gray-300 py-6 text-gray-400">
                <span class="text-3xl">🏫</span>
              </div>

              <!-- Actions -->
              <div class="flex gap-2 w-full">
                <label class="flex-1 cursor-pointer">
                  <input type="file" class="hidden" accept="image/*" @change="handleLogoFile('lycee', $event)" />
                  <span :class="['block text-center text-xs font-medium py-2 px-3 rounded-lg border transition',
                    lyceeForm.processing
                      ? 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed'
                      : 'bg-white text-blue-600 border-blue-300 hover:bg-blue-50']">
                    {{ lyceeForm.processing ? 'Envoi…' : lyceePreview ? '🔄 Changer' : '⬆️ Importer' }}
                  </span>
                </label>
                <button v-if="lyceePreview" type="button"
                        @click="deleteLogo('lycee')"
                        class="text-xs text-red-600 border border-red-200 rounded-lg px-3 py-2 hover:bg-red-50 transition">
                  🗑️
                </button>
              </div>
              <p class="text-xs text-gray-400 self-start">PNG, JPG, SVG · max 2 Mo</p>
            </div>

            <!-- Logo BTS -->
            <div class="border-2 border-dashed border-gray-200 rounded-xl p-4 flex flex-col items-center gap-3 hover:border-indigo-300 transition"
                 :class="btsPreview ? 'border-indigo-200 bg-indigo-50/30' : ''">
              <p class="text-sm font-semibold text-gray-700 self-start">Logo du BTS</p>

              <!-- Prévisualisation -->
              <div v-if="btsPreview"
                   class="w-full flex justify-center bg-white rounded-lg border border-gray-200 p-3">
                <img :src="btsPreview" class="max-h-24 max-w-full object-contain" alt="Logo BTS" />
              </div>
              <div v-else class="w-full flex justify-center items-center bg-gray-100 rounded-lg border border-dashed border-gray-300 py-6 text-gray-400">
                <span class="text-3xl">🎓</span>
              </div>

              <!-- Actions -->
              <div class="flex gap-2 w-full">
                <label class="flex-1 cursor-pointer">
                  <input type="file" class="hidden" accept="image/*" @change="handleLogoFile('bts', $event)" />
                  <span :class="['block text-center text-xs font-medium py-2 px-3 rounded-lg border transition',
                    btsForm.processing
                      ? 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed'
                      : 'bg-white text-indigo-600 border-indigo-300 hover:bg-indigo-50']">
                    {{ btsForm.processing ? 'Envoi…' : btsPreview ? '🔄 Changer' : '⬆️ Importer' }}
                  </span>
                </label>
                <button v-if="btsPreview" type="button"
                        @click="deleteLogo('bts')"
                        class="text-xs text-red-600 border border-red-200 rounded-lg px-3 py-2 hover:bg-red-50 transition">
                  🗑️
                </button>
              </div>
              <p class="text-xs text-gray-400 self-start">PNG, JPG, SVG · max 2 Mo</p>
            </div>

          </div>
        </div>

        <!-- Visibilité des équipements -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="font-semibold text-gray-900 mb-1">Visibilité des équipements</h2>
          <p class="text-sm text-gray-500 mb-4">
            Contrôle ce que les utilisateurs standard voient dans la liste des équipements.
          </p>

          <div class="p-4 rounded-xl border-2 transition"
               :class="form.filter_by_reservable === 'true' ? 'border-blue-400 bg-blue-50' : 'border-gray-200'">
            <div class="flex items-start gap-4">
              <button type="button"
                      @click="form.filter_by_reservable = form.filter_by_reservable === 'true' ? 'false' : 'true'"
                      class="relative mt-0.5 inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                      :class="form.filter_by_reservable === 'true' ? 'bg-blue-600' : 'bg-gray-300'">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200"
                      :class="form.filter_by_reservable === 'true' ? 'translate-x-6' : 'translate-x-1'" />
              </button>
              <div>
                <p class="text-sm font-semibold text-gray-900">
                  Filtrer les équipements visibles par les utilisateurs
                </p>
                <p class="text-sm mt-1" :class="form.filter_by_reservable === 'true' ? 'text-blue-700' : 'text-gray-500'">
                  <template v-if="form.filter_by_reservable === 'true'">
                    <strong>Activé</strong> — les utilisateurs ne voient que les équipements dont
                    le toggle <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 px-1.5 py-0.5 rounded text-xs font-medium">Réservable</span>
                    est activé dans la liste des équipements.
                  </template>
                  <template v-else>
                    <strong>Désactivé</strong> — les utilisateurs voient tous les équipements de l'inventaire.
                  </template>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Onglets visibles pour les utilisateurs -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="font-semibold text-gray-900 mb-1">Onglets visibles pour les utilisateurs</h2>
          <p class="text-sm text-gray-500 mb-4">
            Choisissez quels onglets de navigation sont accessibles aux utilisateurs standard dans la page Équipements.
            Les administrateurs voient toujours tous les onglets.
          </p>
          <div class="space-y-3">
            <label v-for="tab in [
              { key: 'tab_categories',    label: 'Catégories',  desc: 'Parcourir les équipements par catégorie' },
              { key: 'tab_locations',     label: 'Lieux',       desc: 'Visualiser les emplacements de stockage' },
              { key: 'tab_manufacturers', label: 'Fabricants',  desc: 'Parcourir les équipements par fabricant' },
            ]" :key="tab.key"
                  class="flex items-center justify-between p-3 rounded-lg border transition cursor-pointer"
                  :class="form[tab.key] === 'true' ? 'border-blue-200 bg-blue-50' : 'border-gray-200'">
              <div>
                <p class="text-sm font-medium text-gray-900">{{ tab.label }}</p>
                <p class="text-xs text-gray-500">{{ tab.desc }}</p>
              </div>
              <button type="button"
                      @click="form[tab.key] = form[tab.key] === 'true' ? 'false' : 'true'"
                      class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                      :class="form[tab.key] === 'true' ? 'bg-blue-600' : 'bg-gray-300'">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200"
                      :class="form[tab.key] === 'true' ? 'translate-x-6' : 'translate-x-1'" />
              </button>
            </label>
          </div>
        </div>

        <!-- Visibilité Calendrier par rôle -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="font-semibold text-gray-900 mb-1">Visibilité du calendrier par rôle</h2>
          <p class="text-sm text-gray-500 mb-4">
            Activez pour permettre à ces rôles de voir toutes les réservations dans le calendrier
            (comme l'administrateur). Par défaut, chaque utilisateur ne voit que les siennes.
          </p>
          <div class="space-y-3">
            <label v-for="role in [
              { key: 'calendar_visibility_professeur', label: 'Professeurs',  desc: 'Voir toutes les réservations et salles' },
              { key: 'calendar_visibility_etudiant',   label: 'Étudiants',    desc: 'Voir toutes les réservations et salles' },
              { key: 'calendar_visibility_personnel',  label: 'Personnels',   desc: 'Voir toutes les réservations et salles' },
            ]" :key="role.key"
                  class="flex items-center justify-between p-3 rounded-lg border transition cursor-pointer"
                  :class="form[role.key] === 'true' ? 'border-blue-200 bg-blue-50' : 'border-gray-200'">
              <div>
                <p class="text-sm font-medium text-gray-900">{{ role.label }}</p>
                <p class="text-xs text-gray-500">{{ role.desc }}</p>
              </div>
              <button type="button"
                      @click="form[role.key] = form[role.key] === 'true' ? 'false' : 'true'"
                      class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors duration-200 focus:outline-none"
                      :class="form[role.key] === 'true' ? 'bg-blue-600' : 'bg-gray-300'">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200"
                      :class="form[role.key] === 'true' ? 'translate-x-6' : 'translate-x-1'" />
              </button>
            </label>
          </div>
        </div>

        <!-- Réservations -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="font-semibold text-gray-900 mb-4">Réservations</h2>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Durée max (jours)</label>
                <input v-model="form.max_reservation_days" type="number" min="1"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Max équipements / réservation</label>
                <input v-model="form.max_assets_per_reservation" type="number" min="1"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
            </div>
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="form.auto_approve" type="checkbox"
                     true-value="true" false-value="false" class="w-4 h-4 rounded text-blue-600" />
              <span class="text-sm text-gray-700">Validation automatique des réservations</span>
            </label>
          </div>
        </div>

        <!-- Auto-annulation des réservations -->
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden"
             :class="form.auto_cancel_enabled === 'true' ? 'border-red-300' : ''">
          <div class="px-6 py-4 border-b flex items-center justify-between"
               :class="form.auto_cancel_enabled === 'true' ? 'bg-red-50' : 'bg-gray-50'">
            <div>
              <h2 class="font-semibold text-gray-900">🚫 Annulation automatique</h2>
              <p class="text-xs text-gray-500 mt-0.5">
                Annule les réservations approuvées dont le retrait n'a pas été effectué dans le délai imparti
              </p>
            </div>
            <button type="button"
              @click="form.auto_cancel_enabled = form.auto_cancel_enabled === 'true' ? 'false' : 'true'"
              class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1"
              :class="form.auto_cancel_enabled === 'true' ? 'bg-red-500' : 'bg-gray-300'">
              <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200"
                    :class="form.auto_cancel_enabled === 'true' ? 'translate-x-6' : 'translate-x-1'" />
            </button>
          </div>

          <div class="px-6 py-5">
            <div v-if="form.auto_cancel_enabled === 'true'" class="space-y-4">
              <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-800 flex items-start gap-2">
                <span class="text-lg flex-shrink-0">⚠️</span>
                <div>
                  Si une réservation approuvée n'est pas retirée dans les
                  <strong>{{ form.auto_cancel_hours }} heure(s)</strong> suivant l'approbation,
                  elle sera automatiquement annulée et l'utilisateur en sera notifié par email.
                  Les équipements réservés seront remis à disposition.
                </div>
              </div>

              <div class="max-w-xs">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Délai avant annulation
                </label>
                <div class="flex items-center gap-2">
                  <input v-model="form.auto_cancel_hours" type="number" min="1" max="720"
                         class="w-28 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 outline-none" />
                  <span class="text-sm text-gray-600">heures</span>
                  <div class="flex gap-1 ml-2">
                    <button v-for="preset in [{label:'12h', v:'12'},{label:'24h', v:'24'},{label:'48h', v:'48'},{label:'72h', v:'72'}]"
                            :key="preset.v"
                            type="button"
                            @click="form.auto_cancel_hours = preset.v"
                            :class="['text-xs px-2 py-1 rounded border transition',
                              form.auto_cancel_hours === preset.v
                                ? 'bg-red-500 text-white border-red-500'
                                : 'bg-white text-gray-600 border-gray-300 hover:border-red-400']">
                      {{ preset.label }}
                    </button>
                  </div>
                </div>
                <p class="text-xs text-gray-400 mt-1">
                  Le planificateur (scheduler) doit être actif sur le serveur. Vérification toutes les heures.
                </p>
              </div>
            </div>
            <div v-else class="text-sm text-gray-400 py-2">
              L'annulation automatique est désactivée. Les réservations approuvées restent en attente indéfiniment.
            </div>
          </div>
        </div>

        <!-- Emails -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="font-semibold text-gray-900 mb-4">Notifications email</h2>
          <div class="space-y-3">
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="form.notify_admin_new_reservation" type="checkbox"
                     true-value="true" false-value="false" class="w-4 h-4 rounded text-blue-600" />
              <span class="text-sm text-gray-700">Notifier les admins pour chaque nouvelle demande</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="form.notify_user_approved" type="checkbox"
                     true-value="true" false-value="false" class="w-4 h-4 rounded text-blue-600" />
              <span class="text-sm text-gray-700">Notifier l'utilisateur quand sa réservation est validée</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer">
              <input v-model="form.send_checkout_summary" type="checkbox"
                     true-value="true" false-value="false" class="w-4 h-4 rounded text-blue-600" />
              <span class="text-sm text-gray-700">Envoyer le récapitulatif au retrait du matériel</span>
            </label>
          </div>

          <!-- Test email -->
          <div class="mt-5 pt-4 border-t">
            <p class="text-sm text-gray-600 mb-3">
              Envoyez un email de test à votre adresse pour vérifier que la configuration <code>.env</code> est correcte.
            </p>
            <div v-if="$page.props.errors?.email_test"
                 class="mb-3 px-3 py-2 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
              {{ $page.props.errors.email_test }}
            </div>
            <button type="button" @click="sendTestEmail" :disabled="testEmailSending"
                    class="flex items-center gap-2 px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition disabled:opacity-60 font-medium">
              <span v-if="testEmailSending">⏳ Envoi en cours…</span>
              <span v-else>✉️ Envoyer un email de test</span>
            </button>
          </div>
        </div>

        <!-- Sons & Notifications -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="font-semibold text-gray-900 mb-1">🔔 Sons & Alertes</h2>
          <p class="text-sm text-gray-500 mb-4">
            Configurez les sons joués lors des notifications et des scans. Les sons sont synthétisés directement dans le navigateur.
          </p>

          <!-- Activer/désactiver + volume -->
          <div class="flex items-center gap-4 mb-5 p-3 bg-gray-50 rounded-lg border">
            <label class="flex items-center gap-2 cursor-pointer">
              <button type="button"
                      @click="form['sounds.enabled'] = form['sounds.enabled'] === 'true' ? 'false' : 'true'"
                      class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors duration-200 focus:outline-none"
                      :class="form['sounds.enabled'] === 'true' ? 'bg-blue-600' : 'bg-gray-300'">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200"
                      :class="form['sounds.enabled'] === 'true' ? 'translate-x-6' : 'translate-x-1'" />
              </button>
              <span class="text-sm font-medium text-gray-700">Sons activés</span>
            </label>
            <div class="flex items-center gap-2 flex-1">
              <span class="text-xs text-gray-500">Volume</span>
              <input v-model="form['sounds.volume']" type="range" min="0" max="100" step="5"
                     class="flex-1 accent-blue-600" />
              <span class="text-xs text-gray-600 w-8 text-right">{{ form['sounds.volume'] }}%</span>
            </div>
          </div>

          <!-- Sélecteurs de sons -->
          <div class="space-y-3">
            <div v-for="soundDef in [
              { key: 'sounds.notification_general', label: '🔔 Notification réservation (général)', desc: 'Nouvelle réservation en attente d\'approbation' },
              { key: 'sounds.notification_kit',     label: '📦 Notification réservation kit',      desc: 'Réservation d\'un kit reçue' },
              { key: 'sounds.scan_success',         label: '✅ Scan réussi',                        desc: 'Code-barres reconnu avec succès' },
              { key: 'sounds.scan_error',           label: '❌ Scan erreur',                        desc: 'Code-barres non reconnu ou erreur' },
            ]" :key="soundDef.key"
                 class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:border-blue-200 transition">
              <div class="flex-1">
                <p class="text-sm font-medium text-gray-900">{{ soundDef.label }}</p>
                <p class="text-xs text-gray-500">{{ soundDef.desc }}</p>
              </div>
              <select v-model="form[soundDef.key]"
                      class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-500 outline-none bg-white min-w-40">
                <option v-for="opt in SOUND_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
              <button type="button"
                      @click="testSound(soundDef.key)"
                      class="flex-shrink-0 px-3 py-1.5 text-xs border border-blue-300 text-blue-600 rounded-lg hover:bg-blue-50 transition"
                      title="Tester ce son">
                ▶ Tester
              </button>
            </div>
          </div>
        </div>

        <!-- Rappels de retour -->
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden"
             :class="form.return_reminder_enabled === 'true' ? 'border-amber-300' : ''">
          <div class="px-6 py-4 border-b flex items-center justify-between"
               :class="form.return_reminder_enabled === 'true' ? 'bg-amber-50' : 'bg-gray-50'">
            <div>
              <h2 class="font-semibold text-gray-900">⏰ Rappels de retour</h2>
              <p class="text-xs text-gray-500 mt-0.5">
                Notifie les admins avant l'échéance de retour d'un équipement sorti
              </p>
            </div>
            <button type="button"
              @click="form.return_reminder_enabled = form.return_reminder_enabled === 'true' ? 'false' : 'true'"
              class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-1"
              :class="form.return_reminder_enabled === 'true' ? 'bg-amber-500' : 'bg-gray-300'">
              <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200"
                    :class="form.return_reminder_enabled === 'true' ? 'translate-x-6' : 'translate-x-1'" />
            </button>
          </div>

          <div class="px-6 py-5">
            <div v-if="form.return_reminder_enabled === 'true'" class="space-y-4">
              <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800 flex items-start gap-2">
                <span class="text-lg flex-shrink-0">⏰</span>
                <div>
                  Un rappel sera envoyé aux administrateurs (tableau de bord + cloche + email)
                  <strong>{{ form.return_reminder_minutes }} minute(s)</strong> avant l'échéance de retour
                  d'une réservation active. Le rappel est envoyé une seule fois par réservation.
                </div>
              </div>

              <div class="max-w-xs">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Délai avant l'échéance
                </label>
                <div class="flex items-center gap-2">
                  <input v-model="form.return_reminder_minutes" type="number" min="1" max="10080"
                         class="w-28 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 outline-none" />
                  <span class="text-sm text-gray-600">minutes</span>
                  <!-- Raccourcis rapides -->
                  <div class="flex gap-1 ml-2">
                    <button v-for="preset in [{label:'15m', v:'15'},{label:'30m', v:'30'},{label:'1h', v:'60'},{label:'2h', v:'120'},{label:'1j', v:'1440'}]"
                            :key="preset.v"
                            type="button"
                            @click="form.return_reminder_minutes = preset.v"
                            :class="['text-xs px-2 py-1 rounded border transition',
                              form.return_reminder_minutes === preset.v
                                ? 'bg-amber-500 text-white border-amber-500'
                                : 'bg-white text-gray-600 border-gray-300 hover:border-amber-400']">
                      {{ preset.label }}
                    </button>
                  </div>
                </div>
                <p class="text-xs text-gray-400 mt-1">
                  Ex : 30 = rappel 30 min avant le retour prévu. Le planificateur (scheduler) doit être actif sur le serveur.
                </p>
              </div>
            </div>
            <div v-else class="text-sm text-gray-400 py-2">
              Les rappels de retour sont désactivés.
            </div>
          </div>
        </div>

        <!-- Mode examen -->
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden"
             :class="form.exam_mode === 'true' ? 'border-orange-300' : ''">
          <div class="px-6 py-4 border-b flex items-center justify-between"
               :class="form.exam_mode === 'true' ? 'bg-orange-50' : 'bg-gray-50'">
            <div>
              <h2 class="font-semibold text-gray-900">🎓 Mode examen</h2>
              <p class="text-xs text-gray-500 mt-0.5">Restrictions spéciales pour les périodes d'examen</p>
            </div>
            <button type="button"
              @click="form.exam_mode = form.exam_mode === 'true' ? 'false' : 'true'"
              class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-1"
              :class="form.exam_mode === 'true' ? 'bg-orange-500' : 'bg-gray-300'">
              <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200"
                    :class="form.exam_mode === 'true' ? 'translate-x-6' : 'translate-x-1'" />
            </button>
          </div>

          <div class="px-6 py-5 space-y-4">
            <div v-if="form.exam_mode === 'true'"
                 class="bg-orange-50 border border-orange-200 rounded-lg p-3 text-sm text-orange-800 flex items-start gap-2">
              <span class="text-lg flex-shrink-0">⚠️</span>
              <div>
                <strong>Mode examen activé</strong> — les réservations doivent être effectuées
                {{ form.exam_advance_hours }}h à l'avance, et les validations admin sont limitées à la fenêtre
                {{ form.exam_process_start }}h–{{ form.exam_process_end }}h.
              </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Délai d'avance (heures)
                </label>
                <input v-model="form.exam_advance_hours" type="number" min="1" max="168"
                       :disabled="form.exam_mode !== 'true'"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 outline-none disabled:opacity-40 disabled:bg-gray-50" />
                <p class="text-xs text-gray-400 mt-1">Réservation X heures à l'avance</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Début traitement (heure)
                </label>
                <input v-model="form.exam_process_start" type="number" min="0" max="23"
                       :disabled="form.exam_mode !== 'true'"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 outline-none disabled:opacity-40 disabled:bg-gray-50" />
                <p class="text-xs text-gray-400 mt-1">Heure de début (ex: 7 = 7h00)</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Fin traitement (heure)
                </label>
                <input v-model="form.exam_process_end" type="number" min="1" max="24"
                       :disabled="form.exam_mode !== 'true'"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 outline-none disabled:opacity-40 disabled:bg-gray-50" />
                <p class="text-xs text-gray-400 mt-1">Heure de fin (ex: 9 = 9h00)</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Fiche imprimable -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
          <h2 class="font-semibold text-gray-900 mb-1">🖨️ Fiche de sortie imprimable</h2>
          <p class="text-sm text-gray-500 mb-5">
            Personnalisez le contenu et la mise en page de la fiche PDF générée lors du retrait des équipements.
          </p>

          <!-- Label proviseur -->
          <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Intitulé de la zone de signature proviseur
            </label>
            <input v-model="form.print_proviseur_label"
                   placeholder="Signature du proviseur"
                   class="w-full max-w-sm px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none" />
            <p class="text-xs text-gray-400 mt-1">Affiché en bas à droite de la fiche (ex : "Signature du proviseur", "Visa du chef d'établissement"…)</p>
          </div>

          <!-- Colonnes du tableau équipements -->
          <p class="text-sm font-medium text-gray-700 mb-2">Colonnes du tableau des équipements</p>
          <div class="space-y-2 mb-5">
            <label v-for="col in [
              { key: 'print_show_thumbnails', label: '📷 Photos miniatures', desc: 'Affiche une colonne photo pour chaque équipement' },
              { key: 'print_show_asset_tag',  label: '🏷️ Tag / N° inventaire',  desc: 'Affiche la colonne TAG (code-barres)' },
              { key: 'print_show_category',   label: '📂 Catégorie',           desc: 'Affiche la colonne Catégorie' },
            ]" :key="col.key"
                  class="flex items-center justify-between p-3 rounded-lg border transition cursor-pointer"
                  :class="form[col.key] === 'true' ? 'border-blue-200 bg-blue-50' : 'border-gray-200'">
              <div>
                <p class="text-sm font-medium text-gray-900">{{ col.label }}</p>
                <p class="text-xs text-gray-500">{{ col.desc }}</p>
              </div>
              <button type="button"
                      @click="form[col.key] = form[col.key] === 'true' ? 'false' : 'true'"
                      class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                      :class="form[col.key] === 'true' ? 'bg-blue-600' : 'bg-gray-300'">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200"
                      :class="form[col.key] === 'true' ? 'translate-x-6' : 'translate-x-1'" />
              </button>
            </label>
          </div>

          <!-- Sections de la fiche -->
          <p class="text-sm font-medium text-gray-700 mb-2">Sections visibles sur la fiche</p>
          <div class="space-y-2">
            <label v-for="section in [
              { key: 'print_show_notes', label: '📝 Notes de réservation', desc: 'Affiche les notes éventuelles dans le bloc Réservation' },
              { key: 'print_show_form',  label: '📋 Formulaire rempli',    desc: 'Affiche les réponses au formulaire de sortie si disponibles' },
            ]" :key="section.key"
                  class="flex items-center justify-between p-3 rounded-lg border transition cursor-pointer"
                  :class="form[section.key] === 'true' ? 'border-blue-200 bg-blue-50' : 'border-gray-200'">
              <div>
                <p class="text-sm font-medium text-gray-900">{{ section.label }}</p>
                <p class="text-xs text-gray-500">{{ section.desc }}</p>
              </div>
              <button type="button"
                      @click="form[section.key] = form[section.key] === 'true' ? 'false' : 'true'"
                      class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                      :class="form[section.key] === 'true' ? 'bg-blue-600' : 'bg-gray-300'">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition duration-200"
                      :class="form[section.key] === 'true' ? 'translate-x-6' : 'translate-x-1'" />
              </button>
            </label>
          </div>
        </div>

        <div class="flex justify-end">
          <button type="submit" :disabled="form.processing"
                  class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-medium hover:bg-blue-700 disabled:opacity-50">
            {{ form.processing ? 'Sauvegarde...' : 'Sauvegarder' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
