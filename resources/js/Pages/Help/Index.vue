<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
  QuestionMarkCircleIcon,
  AcademicCapIcon,
  UserIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  userRole: { type: String, default: 'etudiant' }, // 'admin' | 'professeur' | 'etudiant'
})

// Tab shown: if admin, can switch; otherwise locked to own role
const activeTab = ref(props.userRole === 'professeur' ? 'professeur' : 'etudiant')

const isAdmin = computed(() => props.userRole === 'admin')

const tabs = [
  { key: 'etudiant',   label: 'Étudiant',   emoji: '🎒' },
  { key: 'professeur', label: 'Professeur',  emoji: '👩‍🏫' },
]

const roleBadgeClass = computed(() => {
  if (props.userRole === 'admin')      return 'bg-red-100 text-red-700'
  if (props.userRole === 'professeur') return 'bg-purple-100 text-purple-700'
  return 'bg-blue-100 text-blue-700'
})
const roleBadgeLabel = computed(() => {
  if (props.userRole === 'admin')      return 'Administrateur'
  if (props.userRole === 'professeur') return 'Professeur'
  return 'Étudiant'
})
</script>

<template>
  <AppLayout title="Aide & Tutoriel">
    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6">

      <!-- ── En-tête ──────────────────────────────────────────────────────── -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center shadow-lg">
            <QuestionMarkCircleIcon class="w-7 h-7 text-white" />
          </div>
          <div>
            <h1 class="text-2xl font-bold text-gray-900">Aide &amp; Tutoriel</h1>
            <p class="text-sm text-gray-500 mt-0.5">Guide d'utilisation de l'application de gestion du matériel</p>
          </div>
        </div>
        <span :class="['px-3 py-1.5 rounded-full text-sm font-semibold', roleBadgeClass]">
          {{ roleBadgeLabel }}
        </span>
      </div>

      <!-- ── Onglets (admin seulement ou role-specific label) ────────────── -->
      <div v-if="isAdmin" class="flex gap-2 mb-8 bg-gray-100 rounded-xl p-1 w-fit">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="activeTab = tab.key"
          :class="[
            'px-5 py-2 rounded-lg text-sm font-semibold transition-all duration-200',
            activeTab === tab.key
              ? 'bg-white text-gray-900 shadow-sm'
              : 'text-gray-500 hover:text-gray-700'
          ]"
        >
          {{ tab.emoji }} {{ tab.label }}
        </button>
      </div>
      <div v-else class="flex gap-2 mb-8">
        <div class="inline-flex items-center gap-2 bg-indigo-50 border border-indigo-200 text-indigo-700 px-4 py-2 rounded-xl text-sm font-semibold">
          <AcademicCapIcon class="w-4 h-4" />
          Guide {{ activeTab === 'professeur' ? 'Professeur' : 'Étudiant' }}
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════════════ -->
      <!-- TUTORIEL ÉTUDIANT                                                  -->
      <!-- ══════════════════════════════════════════════════════════════════ -->
      <div v-if="activeTab === 'etudiant'" class="space-y-6">

        <!-- 1. Bienvenue -->
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🏠</div>
            <div>
              <h2 class="text-lg font-bold text-blue-900">Bienvenue !</h2>
              <p class="text-sm text-blue-600">Présentation de l'application</p>
            </div>
          </div>
          <p class="text-sm text-gray-700 leading-relaxed mb-4">
            Bienvenue sur l'application de gestion du matériel du BTS MAV !
            Cette application vous permet de <strong>réserver des équipements audiovisuels et informatiques</strong>
            pour vos projets scolaires. Vous pouvez consulter le catalogue de matériel disponible,
            soumettre des demandes de réservation et suivre leur état en temps réel.
          </p>
          <!-- Aperçu dashboard -->
          <div class="bg-white rounded-xl border-2 border-dashed border-blue-300 p-4">
            <div class="text-xs text-blue-400 mb-3 uppercase font-bold tracking-wide">Aperçu — Tableau de bord</div>
            <div class="grid grid-cols-3 gap-2">
              <div class="bg-blue-100 rounded-lg p-3 text-center">
                <div class="text-2xl font-bold text-blue-700">3</div>
                <div class="text-xs text-blue-600 mt-0.5">Réservations actives</div>
              </div>
              <div class="bg-green-100 rounded-lg p-3 text-center">
                <div class="text-2xl font-bold text-green-700">12</div>
                <div class="text-xs text-green-600 mt-0.5">Équipements disponibles</div>
              </div>
              <div class="bg-amber-100 rounded-lg p-3 text-center">
                <div class="text-2xl font-bold text-amber-700">1</div>
                <div class="text-xs text-amber-600 mt-0.5">En attente de validation</div>
              </div>
            </div>
          </div>
        </div>

        <!-- 2. Parcourir les équipements -->
        <div class="bg-purple-50 border border-purple-200 rounded-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-purple-500 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🔍</div>
            <div>
              <h2 class="text-lg font-bold text-purple-900">Parcourir les équipements</h2>
              <p class="text-sm text-purple-600">Trouver le matériel dont vous avez besoin</p>
            </div>
          </div>
          <div class="space-y-3 mb-4">
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-purple-500 text-white text-xs font-bold rounded-full flex items-center justify-center">1</span>
              <p class="text-sm text-gray-700 pt-0.5">Cliquez sur <strong>Équipements</strong> dans le menu latéral gauche.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-purple-500 text-white text-xs font-bold rounded-full flex items-center justify-center">2</span>
              <p class="text-sm text-gray-700 pt-0.5">Utilisez la <strong>barre de recherche</strong> en haut pour trouver un équipement par nom ou référence.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-purple-500 text-white text-xs font-bold rounded-full flex items-center justify-center">3</span>
              <p class="text-sm text-gray-700 pt-0.5">Filtrez par <strong>catégorie</strong> (appareil photo, objectif, éclairage…), par statut ou par emplacement.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-purple-500 text-white text-xs font-bold rounded-full flex items-center justify-center">4</span>
              <p class="text-sm text-gray-700 pt-0.5">Cliquez sur une <strong>carte d'équipement</strong> pour voir ses détails, photos et disponibilité.</p>
            </div>
          </div>
          <!-- Aperçu cartes équipements -->
          <div class="bg-white rounded-xl border-2 border-dashed border-purple-300 p-4">
            <div class="text-xs text-purple-400 mb-3 uppercase font-bold tracking-wide">Aperçu — Carte équipement</div>
            <div class="grid grid-cols-2 gap-2">
              <div class="border border-gray-200 rounded-lg p-3 flex items-center gap-2">
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-lg flex-shrink-0">📷</div>
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-gray-800 truncate">Canon EOS R5</p>
                  <span class="inline-block text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded mt-0.5">Disponible</span>
                </div>
              </div>
              <div class="border border-gray-200 rounded-lg p-3 flex items-center gap-2">
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-lg flex-shrink-0">🎬</div>
                <div class="min-w-0">
                  <p class="text-xs font-semibold text-gray-800 truncate">Trépied Manfrotto</p>
                  <span class="inline-block text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded mt-0.5">En prêt</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 3. Ajouter au panier -->
        <div class="bg-green-50 border border-green-200 rounded-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🛒</div>
            <div>
              <h2 class="text-lg font-bold text-green-900">Ajouter au panier</h2>
              <p class="text-sm text-green-600">Sélectionner les équipements à emprunter</p>
            </div>
          </div>
          <div class="space-y-3 mb-4">
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-green-500 text-white text-xs font-bold rounded-full flex items-center justify-center">1</span>
              <p class="text-sm text-gray-700 pt-0.5">Sur la fiche d'un équipement, cliquez sur le bouton <strong>"Ajouter au panier"</strong>.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-green-500 text-white text-xs font-bold rounded-full flex items-center justify-center">2</span>
              <p class="text-sm text-gray-700 pt-0.5">Le <strong>compteur du panier</strong> (icône chariot en haut à droite) se met à jour automatiquement.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-green-500 text-white text-xs font-bold rounded-full flex items-center justify-center">3</span>
              <p class="text-sm text-gray-700 pt-0.5">Vous pouvez ajouter <strong>plusieurs équipements</strong> avant de confirmer la réservation.</p>
            </div>
          </div>
          <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-start gap-2">
            <span class="text-lg flex-shrink-0">⚠️</span>
            <p class="text-sm text-amber-800">Seuls les équipements affichant un badge <span class="font-semibold text-green-700">vert "Disponible"</span> peuvent être ajoutés au panier. Les équipements déjà en prêt ou en maintenance ne sont pas réservables.</p>
          </div>
          <!-- Aperçu bouton panier -->
          <div class="bg-white rounded-xl border-2 border-dashed border-green-300 p-4 mt-4">
            <div class="text-xs text-green-400 mb-3 uppercase font-bold tracking-wide">Aperçu — Bouton panier</div>
            <div class="flex items-center gap-3">
              <button class="inline-flex items-center gap-2 bg-green-600 text-white text-sm font-semibold px-4 py-2 rounded-lg cursor-default">
                🛒 Ajouter au panier
              </button>
              <div class="flex items-center gap-1 bg-gray-100 rounded-lg px-3 py-2">
                <span class="text-sm">🛒</span>
                <span class="text-xs font-bold text-gray-700 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center">2</span>
              </div>
            </div>
          </div>
        </div>

        <!-- 4. Créer une réservation -->
        <div class="bg-orange-50 border border-orange-200 rounded-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-orange-500 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">📋</div>
            <div>
              <h2 class="text-lg font-bold text-orange-900">Créer une réservation</h2>
              <p class="text-sm text-orange-600">Soumettre votre demande d'emprunt</p>
            </div>
          </div>
          <div class="space-y-3 mb-4">
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-orange-500 text-white text-xs font-bold rounded-full flex items-center justify-center">1</span>
              <p class="text-sm text-gray-700 pt-0.5">Allez dans <strong>"Mon panier"</strong> (icône chariot dans le menu ou en haut à droite).</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-orange-500 text-white text-xs font-bold rounded-full flex items-center justify-center">2</span>
              <p class="text-sm text-gray-700 pt-0.5">Vérifiez la liste des équipements sélectionnés. Vous pouvez en retirer si nécessaire.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-orange-500 text-white text-xs font-bold rounded-full flex items-center justify-center">3</span>
              <p class="text-sm text-gray-700 pt-0.5">Renseignez le <strong>titre</strong> de votre réservation (ex : "Tournage projet BTS"), les <strong>dates de début et de fin</strong>.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-orange-500 text-white text-xs font-bold rounded-full flex items-center justify-center">4</span>
              <p class="text-sm text-gray-700 pt-0.5">Ajoutez éventuellement des <strong>notes</strong> pour l'administrateur (lieu de tournage, contexte du projet…).</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-orange-500 text-white text-xs font-bold rounded-full flex items-center justify-center">5</span>
              <p class="text-sm text-gray-700 pt-0.5">Cliquez sur <strong>"Confirmer la réservation"</strong>. Votre demande est soumise et en attente de validation.</p>
            </div>
          </div>
          <!-- Aperçu formulaire -->
          <div class="bg-white rounded-xl border-2 border-dashed border-orange-300 p-4">
            <div class="text-xs text-orange-400 mb-3 uppercase font-bold tracking-wide">Aperçu — Formulaire de réservation</div>
            <div class="space-y-2">
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Titre de la réservation</label>
                <div class="border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-400 bg-gray-50">Tournage projet documentaire…</div>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="block text-xs font-medium text-gray-500 mb-1">Date de début</label>
                  <div class="border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-400 bg-gray-50">📅 03/06/2025</div>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500 mb-1">Date de fin</label>
                  <div class="border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-400 bg-gray-50">📅 05/06/2025</div>
                </div>
              </div>
              <button class="w-full bg-orange-500 text-white text-sm font-semibold py-2 rounded-lg cursor-default mt-1">
                ✅ Confirmer la réservation
              </button>
            </div>
          </div>
        </div>

        <!-- 5. Suivi de votre réservation -->
        <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-indigo-500 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">📊</div>
            <div>
              <h2 class="text-lg font-bold text-indigo-900">Suivi de votre réservation</h2>
              <p class="text-sm text-indigo-600">Comprendre les étapes de validation</p>
            </div>
          </div>
          <p class="text-sm text-gray-700 mb-4">
            Chaque réservation passe par un <strong>circuit de validation</strong> en deux étapes (superviseur puis administrateur) avant d'être accordée.
            Suivez l'état de vos demandes dans <strong>Mes réservations</strong>.
          </p>
          <!-- Diagramme de statuts -->
          <div class="bg-white rounded-xl border-2 border-dashed border-indigo-300 p-4">
            <div class="text-xs text-indigo-400 mb-4 uppercase font-bold tracking-wide">Circuit de validation</div>
            <div class="flex flex-col gap-2">
              <div class="flex items-center gap-3">
                <span class="flex-shrink-0 inline-flex items-center gap-1.5 bg-gray-100 text-gray-700 px-3 py-1.5 rounded-full text-xs font-semibold">⏳ En attente</span>
                <span class="text-xs text-gray-400 flex-1">Soumise, en attente du superviseur</span>
              </div>
              <div class="ml-6 border-l-2 border-gray-200 h-4"></div>
              <div class="flex items-center gap-3">
                <span class="flex-shrink-0 inline-flex items-center gap-1.5 bg-blue-100 text-blue-700 px-3 py-1.5 rounded-full text-xs font-semibold">🔵 Approuvée superviseur</span>
                <span class="text-xs text-gray-400 flex-1">Validée par le superviseur, en attente de l'admin</span>
              </div>
              <div class="ml-6 border-l-2 border-gray-200 h-4"></div>
              <div class="flex items-center gap-3">
                <span class="flex-shrink-0 inline-flex items-center gap-1.5 bg-green-100 text-green-700 px-3 py-1.5 rounded-full text-xs font-semibold">✅ Approuvée</span>
                <span class="text-xs text-gray-400 flex-1">Validée par l'admin — matériel prêt à être remis</span>
              </div>
              <div class="ml-6 border-l-2 border-gray-200 h-4"></div>
              <div class="flex items-center gap-3">
                <span class="flex-shrink-0 inline-flex items-center gap-1.5 bg-purple-100 text-purple-700 px-3 py-1.5 rounded-full text-xs font-semibold">📦 En cours</span>
                <span class="text-xs text-gray-400 flex-1">Équipements remis, en cours d'utilisation</span>
              </div>
              <div class="ml-6 border-l-2 border-gray-200 h-4"></div>
              <div class="flex items-center gap-3">
                <span class="flex-shrink-0 inline-flex items-center gap-1.5 bg-teal-100 text-teal-700 px-3 py-1.5 rounded-full text-xs font-semibold">↩️ Retournée</span>
                <span class="text-xs text-gray-400 flex-1">Équipements rendus — réservation clôturée</span>
              </div>
              <div class="border-t border-gray-200 mt-3 pt-3">
                <div class="flex items-center gap-3">
                  <span class="flex-shrink-0 inline-flex items-center gap-1.5 bg-red-100 text-red-700 px-3 py-1.5 rounded-full text-xs font-semibold">❌ Refusée</span>
                  <span class="text-xs text-gray-400 flex-1">Demande non validée — contactez votre superviseur</span>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-4 bg-indigo-100 rounded-lg p-3 text-sm text-indigo-800">
            💡 <strong>Astuce :</strong> Vous recevrez un <strong>e-mail de notification</strong> à chaque changement de statut de votre réservation.
          </div>
        </div>

        <!-- 6. Retourner le matériel -->
        <div class="bg-teal-50 border border-teal-200 rounded-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-teal-500 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">↩️</div>
            <div>
              <h2 class="text-lg font-bold text-teal-900">Retourner le matériel</h2>
              <p class="text-sm text-teal-600">Procédure de rendu des équipements</p>
            </div>
          </div>
          <div class="space-y-3">
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-teal-500 text-white text-xs font-bold rounded-full flex items-center justify-center">1</span>
              <p class="text-sm text-gray-700 pt-0.5">Retournez le matériel <strong>à la date prévue</strong> indiquée dans votre réservation.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-teal-500 text-white text-xs font-bold rounded-full flex items-center justify-center">2</span>
              <p class="text-sm text-gray-700 pt-0.5">Présentez-vous à l'administrateur avec <strong>tous les équipements empruntés</strong>, dans leur état d'origine.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-teal-500 text-white text-xs font-bold rounded-full flex items-center justify-center">3</span>
              <p class="text-sm text-gray-700 pt-0.5">L'administrateur scanne ou valide chaque équipement — votre réservation passe en statut <strong>"Retournée"</strong>.</p>
            </div>
          </div>
          <div class="mt-4 bg-red-50 border border-red-200 rounded-lg p-3 flex items-start gap-2">
            <span class="text-lg flex-shrink-0">🔴</span>
            <p class="text-sm text-red-800">
              <strong>Important :</strong> Un retard non signalé peut bloquer vos futures réservations.
              Si vous avez un empêchement, contactez votre professeur ou l'administrateur à l'avance.
            </p>
          </div>
        </div>

        <!-- 7. Signaler un problème -->
        <div class="bg-red-50 border border-red-200 rounded-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-red-500 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🚨</div>
            <div>
              <h2 class="text-lg font-bold text-red-900">Signaler du matériel défectueux</h2>
              <p class="text-sm text-red-600">En cas de panne ou de dommage</p>
            </div>
          </div>
          <div class="space-y-3">
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">1</span>
              <p class="text-sm text-gray-700 pt-0.5">Ne tentez <strong>pas de réparer</strong> vous-même l'équipement endommagé.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">2</span>
              <p class="text-sm text-gray-700 pt-0.5">Signalez immédiatement le problème à l'<strong>administrateur</strong> ou votre <strong>professeur</strong> lors du retour.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">3</span>
              <p class="text-sm text-gray-700 pt-0.5">Si votre accès à la <strong>Déchèterie</strong> est activé, vous pouvez créer une fiche de signalement depuis le menu.</p>
            </div>
          </div>
        </div>

        <!-- Conseils finaux -->
        <div class="bg-gray-50 border border-gray-200 rounded-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-gray-400 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">💡</div>
            <div>
              <h2 class="text-lg font-bold text-gray-800">Bonnes pratiques</h2>
              <p class="text-sm text-gray-500">Pour une utilisation sereine du matériel</p>
            </div>
          </div>
          <ul class="space-y-2">
            <li class="flex items-start gap-2 text-sm text-gray-700">
              <span class="text-green-500 font-bold flex-shrink-0 mt-0.5">✓</span>
              <span>Réservez le matériel <strong>bien à l'avance</strong> pour éviter les indisponibilités.</span>
            </li>
            <li class="flex items-start gap-2 text-sm text-gray-700">
              <span class="text-green-500 font-bold flex-shrink-0 mt-0.5">✓</span>
              <span>Vérifiez l'état du matériel <strong>avant et après</strong> utilisation.</span>
            </li>
            <li class="flex items-start gap-2 text-sm text-gray-700">
              <span class="text-green-500 font-bold flex-shrink-0 mt-0.5">✓</span>
              <span>Respectez les dates de retour pour ne pas pénaliser vos camarades.</span>
            </li>
            <li class="flex items-start gap-2 text-sm text-gray-700">
              <span class="text-green-500 font-bold flex-shrink-0 mt-0.5">✓</span>
              <span>En cas de doute, n'hésitez pas à contacter l'administrateur.</span>
            </li>
          </ul>
        </div>

      </div>
      <!-- /TUTORIEL ÉTUDIANT -->

      <!-- ══════════════════════════════════════════════════════════════════ -->
      <!-- TUTORIEL PROFESSEUR                                                -->
      <!-- ══════════════════════════════════════════════════════════════════ -->
      <div v-if="activeTab === 'professeur'" class="space-y-6">

        <!-- 1. Bienvenue -->
        <div class="bg-purple-50 border border-purple-200 rounded-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-purple-500 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">👩‍🏫</div>
            <div>
              <h2 class="text-lg font-bold text-purple-900">Bienvenue, Professeur !</h2>
              <p class="text-sm text-purple-600">Présentation de vos capacités sur l'application</p>
            </div>
          </div>
          <p class="text-sm text-gray-700 leading-relaxed mb-3">
            En tant que professeur, vous disposez de <strong>capacités étendues</strong> par rapport aux étudiants.
            Vous pouvez réserver du matériel pour vos cours, <strong>superviser les réservations de vos étudiants</strong>,
            valider leur première étape d'approbation, et gérer votre groupe.
          </p>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="bg-purple-100 rounded-xl p-3 text-center">
              <div class="text-2xl mb-1">📋</div>
              <div class="text-xs font-semibold text-purple-800">Réserver du matériel</div>
            </div>
            <div class="bg-purple-100 rounded-xl p-3 text-center">
              <div class="text-2xl mb-1">👥</div>
              <div class="text-xs font-semibold text-purple-800">Superviser vos étudiants</div>
            </div>
            <div class="bg-purple-100 rounded-xl p-3 text-center">
              <div class="text-2xl mb-1">✅</div>
              <div class="text-xs font-semibold text-purple-800">Valider des demandes</div>
            </div>
          </div>
        </div>

        <!-- 2. Parcourir et filtrer -->
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🔍</div>
            <div>
              <h2 class="text-lg font-bold text-blue-900">Parcourir et filtrer les équipements</h2>
              <p class="text-sm text-blue-600">Identifier le matériel disponible pour votre cours</p>
            </div>
          </div>
          <div class="space-y-3 mb-4">
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-blue-500 text-white text-xs font-bold rounded-full flex items-center justify-center">1</span>
              <p class="text-sm text-gray-700 pt-0.5">Allez dans <strong>Équipements</strong> pour voir le catalogue complet.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-blue-500 text-white text-xs font-bold rounded-full flex items-center justify-center">2</span>
              <p class="text-sm text-gray-700 pt-0.5">Utilisez les filtres <strong>catégorie, statut, emplacement</strong> pour affiner votre recherche.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-blue-500 text-white text-xs font-bold rounded-full flex items-center justify-center">3</span>
              <p class="text-sm text-gray-700 pt-0.5">Consultez le <strong>Calendrier</strong> pour voir les périodes où le matériel est déjà réservé.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-blue-500 text-white text-xs font-bold rounded-full flex items-center justify-center">4</span>
              <p class="text-sm text-gray-700 pt-0.5">Cliquez sur un équipement pour voir sa fiche complète, historique et disponibilité.</p>
            </div>
          </div>
        </div>

        <!-- 3. Créer une réservation -->
        <div class="bg-green-50 border border-green-200 rounded-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">📋</div>
            <div>
              <h2 class="text-lg font-bold text-green-900">Créer une réservation</h2>
              <p class="text-sm text-green-600">Soumettre une demande d'emprunt pour vos cours</p>
            </div>
          </div>
          <div class="space-y-3 mb-4">
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-green-500 text-white text-xs font-bold rounded-full flex items-center justify-center">1</span>
              <p class="text-sm text-gray-700 pt-0.5">Ajoutez les équipements souhaités dans votre <strong>panier</strong> depuis le catalogue.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-green-500 text-white text-xs font-bold rounded-full flex items-center justify-center">2</span>
              <p class="text-sm text-gray-700 pt-0.5">Dans <strong>Mon panier</strong>, renseignez le titre, les dates, et éventuellement un message pour l'admin.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-green-500 text-white text-xs font-bold rounded-full flex items-center justify-center">3</span>
              <p class="text-sm text-gray-700 pt-0.5">Confirmez la réservation. Elle sera soumise au circuit de validation en <strong>deux niveaux</strong>.</p>
            </div>
          </div>
          <div class="bg-green-100 border border-green-300 rounded-lg p-3 text-sm text-green-800">
            💡 <strong>En tant que superviseur :</strong> si vous êtes désigné superviseur d'un groupe, vous pouvez approuver la première étape de validation pour vos propres réservations et celles de vos étudiants.
          </div>
        </div>

        <!-- 4. Circuit de validation -->
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-amber-500 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🔄</div>
            <div>
              <h2 class="text-lg font-bold text-amber-900">Circuit de validation</h2>
              <p class="text-sm text-amber-600">Les deux étapes d'approbation</p>
            </div>
          </div>
          <p class="text-sm text-gray-700 mb-4">
            Toutes les réservations (étudiants <em>et</em> professeurs) passent par un circuit en <strong>deux niveaux</strong> avant d'être accordées.
          </p>
          <div class="bg-white rounded-xl border border-amber-200 p-4">
            <div class="flex flex-col gap-3">
              <!-- Étape 1 -->
              <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-8 h-8 bg-amber-500 text-white text-xs font-bold rounded-full flex items-center justify-center">1</div>
                <div>
                  <p class="text-sm font-semibold text-gray-800">Validation superviseur</p>
                  <p class="text-xs text-gray-500 mt-0.5">Le superviseur du groupe vérifie la cohérence et l'utilité de la demande. En tant que professeur superviseur, vous pouvez approuver cette étape vous-même.</p>
                </div>
              </div>
              <div class="ml-4 border-l-2 border-amber-200 h-5 pl-4"></div>
              <!-- Étape 2 -->
              <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-8 h-8 bg-orange-500 text-white text-xs font-bold rounded-full flex items-center justify-center">2</div>
                <div>
                  <p class="text-sm font-semibold text-gray-800">Validation administrateur</p>
                  <p class="text-xs text-gray-500 mt-0.5">L'administrateur confirme la disponibilité du matériel et approuve la demande. La réservation passe alors en statut "Approuvée".</p>
                </div>
              </div>
              <div class="ml-4 border-l-2 border-amber-200 h-5 pl-4"></div>
              <!-- Résultat -->
              <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-8 h-8 bg-green-500 text-white text-xs font-bold rounded-full flex items-center justify-center">✓</div>
                <div>
                  <p class="text-sm font-semibold text-green-800">Matériel prêt à être remis</p>
                  <p class="text-xs text-gray-500 mt-0.5">Vous et votre étudiant recevez une notification par e-mail. Présentez-vous au service matériel pour récupérer les équipements.</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 5. Gérer votre groupe -->
        <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-indigo-500 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">👥</div>
            <div>
              <h2 class="text-lg font-bold text-indigo-900">Mon groupe (si superviseur)</h2>
              <p class="text-sm text-indigo-600">Superviser les réservations de vos étudiants</p>
            </div>
          </div>
          <div class="space-y-3 mb-4">
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-indigo-500 text-white text-xs font-bold rounded-full flex items-center justify-center">1</span>
              <p class="text-sm text-gray-700 pt-0.5">Allez dans <strong>Mon groupe</strong> dans le menu. Vous verrez tous les étudiants de votre groupe.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-indigo-500 text-white text-xs font-bold rounded-full flex items-center justify-center">2</span>
              <p class="text-sm text-gray-700 pt-0.5">Consultez les <strong>réservations en attente</strong> de vos étudiants nécessitant votre approbation.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-indigo-500 text-white text-xs font-bold rounded-full flex items-center justify-center">3</span>
              <p class="text-sm text-gray-700 pt-0.5">Approuvez ou refusez les demandes en un clic. Un e-mail est automatiquement envoyé à l'étudiant.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-indigo-500 text-white text-xs font-bold rounded-full flex items-center justify-center">4</span>
              <p class="text-sm text-gray-700 pt-0.5">Visualisez l'<strong>historique complet</strong> des emprunts de votre groupe.</p>
            </div>
          </div>
          <!-- Aperçu groupe -->
          <div class="bg-white rounded-xl border-2 border-dashed border-indigo-300 p-4">
            <div class="text-xs text-indigo-400 mb-3 uppercase font-bold tracking-wide">Aperçu — Vue superviseur</div>
            <div class="space-y-2">
              <div class="flex items-center justify-between p-2 bg-amber-50 border border-amber-200 rounded-lg">
                <div>
                  <p class="text-xs font-semibold text-gray-800">Martin Dupont — Canon EOS R5</p>
                  <p class="text-xs text-gray-500">03/06 → 05/06/2025</p>
                </div>
                <div class="flex gap-1">
                  <button class="text-xs bg-green-500 text-white px-2 py-1 rounded cursor-default">✓ OK</button>
                  <button class="text-xs bg-red-100 text-red-600 px-2 py-1 rounded cursor-default">✗</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 6. Déchèterie -->
        <div class="bg-red-50 border border-red-200 rounded-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-red-500 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">🗑️</div>
            <div>
              <h2 class="text-lg font-bold text-red-900">Signaler du matériel défectueux</h2>
              <p class="text-sm text-red-600">Déchèterie et fiches de signalement</p>
            </div>
          </div>
          <div class="space-y-3">
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">1</span>
              <p class="text-sm text-gray-700 pt-0.5">Accédez à la section <strong>Déchèterie</strong> dans le menu (si elle est activée pour votre rôle).</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">2</span>
              <p class="text-sm text-gray-700 pt-0.5">Créez une nouvelle fiche de signalement en précisant l'<strong>équipement concerné</strong>, la nature du problème et le <strong>numéro de série</strong>.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">3</span>
              <p class="text-sm text-gray-700 pt-0.5">Ajoutez une <strong>photo</strong> du dommage si possible — cela facilite l'évaluation par l'administrateur.</p>
            </div>
            <div class="flex gap-3 items-start">
              <span class="flex-shrink-0 w-7 h-7 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">4</span>
              <p class="text-sm text-gray-700 pt-0.5">L'administrateur sera notifié et prendra en charge la mise en maintenance ou le remplacement du matériel.</p>
            </div>
          </div>
        </div>

        <!-- 7. Bonnes pratiques -->
        <div class="bg-gray-50 border border-gray-200 rounded-2xl p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-gray-500 rounded-xl flex items-center justify-center text-2xl flex-shrink-0">✨</div>
            <div>
              <h2 class="text-lg font-bold text-gray-800">Bonnes pratiques professeur</h2>
              <p class="text-sm text-gray-500">Conseils pour une gestion efficace</p>
            </div>
          </div>
          <ul class="space-y-2">
            <li class="flex items-start gap-2 text-sm text-gray-700">
              <span class="text-purple-500 font-bold flex-shrink-0 mt-0.5">✓</span>
              <span>Anticipez vos réservations : réservez le matériel <strong>au moins 48h à l'avance</strong>.</span>
            </li>
            <li class="flex items-start gap-2 text-sm text-gray-700">
              <span class="text-purple-500 font-bold flex-shrink-0 mt-0.5">✓</span>
              <span>Traitez les demandes de validation de vos étudiants <strong>rapidement</strong> pour ne pas bloquer leur progression.</span>
            </li>
            <li class="flex items-start gap-2 text-sm text-gray-700">
              <span class="text-purple-500 font-bold flex-shrink-0 mt-0.5">✓</span>
              <span>Sensibilisez vos étudiants à la <strong>bonne manipulation</strong> du matériel avant chaque prêt.</span>
            </li>
            <li class="flex items-start gap-2 text-sm text-gray-700">
              <span class="text-purple-500 font-bold flex-shrink-0 mt-0.5">✓</span>
              <span>Signalez tout matériel défectueux même si le dommage semble mineur.</span>
            </li>
            <li class="flex items-start gap-2 text-sm text-gray-700">
              <span class="text-purple-500 font-bold flex-shrink-0 mt-0.5">✓</span>
              <span>Vérifiez régulièrement le <strong>Calendrier</strong> pour planifier vos activités sans conflit.</span>
            </li>
          </ul>
        </div>

      </div>
      <!-- /TUTORIEL PROFESSEUR -->

      <!-- Footer contact -->
      <div class="mt-8 bg-white border border-gray-200 rounded-2xl p-5 flex items-center gap-4">
        <div class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center text-xl flex-shrink-0">📧</div>
        <div>
          <p class="text-sm font-semibold text-gray-800">Besoin d'aide supplémentaire ?</p>
          <p class="text-sm text-gray-500">Contactez l'administrateur de l'application ou votre référent matériel.</p>
        </div>
      </div>

    </div>
  </AppLayout>
</template>
